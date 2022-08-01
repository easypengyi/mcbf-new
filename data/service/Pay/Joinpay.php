<?php
namespace data\service\Pay;
use data\service\Config as WebConfig;
use data\model\VslMemberBankAccountModel;
use \data\service\Order as OrderService;
if(substr(PHP_VERSION, 0, 1) == 7){
    require_once 'data/extend/joinyfastpay_sdk_php7/Autoload.php'; //指定php5 -- php7需要切换sdk
}else{
    require_once 'data/extend/joinyfastpay_sdk_php5/Autoload.php'; //指定php5 -- php7需要切换sdk
}
use joinpay\Request;
use joinpay\SecretKey;
use joinpay\RequestUtil;
use joinpay\RandomUtil;
use joinpay\AESUtil;
use joinpay\Response;
use data\service\UnifyPay;
use data\model\VslOrderModel;
use addons\shop\model\VslShopSeparateModel;
use data\service\WebSite as WebSite;
use addons\shop\model\VslShopBankAccountModel;
/**
 *聚合支付文件
 */
class Joinpay extends PayParam {
    const UNI_PAY_URL = 'https://www.joinpay.com/trade/uniPayApi.action';
    const UNI_RETURN_URL = 'https://www.joinpay.com/trade/refund.action';
    const NOTIFY_URL = "/wapapi/pay/joinpayUrlBack";
    const NOTIFY_URLS = "/wapapi/pay/joinpayAliUrlBack";
    const RETURN_NOTIFY_URL = "/wapapi/pay/joinpayReturnUrlBack";
    const AUTO_NOTIFY_URL = "/wapapi/pay/joinpayAutoSendUrlBack"; //余额自动打款回调地址
    const SINGLEPAY_URL = 'https://www.joinpay.com/payment/pay/singlePay';//单笔代付接口
    const AUTO_NOTIFY_COM_URL = "/wapapi/pay/joinpayAutoComSendUrlBack"; //佣金自动打款回调地址
    const AGREEMENT_SMSAPI_URL = 'https://api.joinpay.com/fastpay';//协议支付短信接口
    const BANK_NOTIFY_URL = "/wapapi/pay/joinpayBankUrlBack";
    const FAST_RETURN_URL = "https://api.joinpay.com/refund";
    const FAST_RETURN_NOTIFY_URL = "/wapapi/pay/joinpayFastReturnUrlBack";
    const QUERY_URL = "https://api.joinpay.com/query";
    const ALLOCFUNDS_URL = "https://www.joinpay.com/allocFunds";//延迟分账请求接口 转账 
    const ALLOCFUNDS_NOTIFY_URL = "/wapapi/pay/joinpayAllocfundsUrlBack"; //延迟分账回调子商户回调地址 
    const TRANSFER_NOTIFY_URL = "/wapapi/pay/joinpayTransferUrlBack"; //商户转账回调地址
    
    function __construct($instance = 0) 
    {
        //当前域名需要获取真实域名
        $WebSite = new WebSite;
        $web_info = $WebSite->getWebSiteInfo($this->website_id);
        $is_ssl = \think\Request::instance()->isSsl();
        $this->http = "http://";
        if($is_ssl){
            $this->http = 'https://';
        }
        if($web_info['realm_ip']){
            $this->realm_ip = $this->http.$web_info['realm_ip'];
        }else{
            $this->realm_ip = $this->http.$web_info['realm_two_ip'].'.'.top_domain($_SERVER['HTTP_HOST']);
        }

        $webConfig = new WebConfig();
        $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $this->hmacVal = $joinPay['value']['hmacVal'];
        $this->p1_MerchantNo = $joinPay['value']['p1_MerchantNo'];
        parent::__construct($instance);
    }
    /**
     * 设置微信支付参数
     *
     * @param unknown $body
     *            订单描述
     * @param unknown $detail
     *            订单详情
     * @param unknown $total_fee
     *            订单金额
     * @param unknown $orderNumber
     *            订单编号
     * @param unknown $red_url
     *            异步回调域名
     * @param unknown $trade_type
     *            交易类型JSAPI、NATIVE、APP
     * @param unknown $openid
     *            支付人openid（jsapi支付必填）
     * @param unknown $product_id
     *            商品id(扫码支付必填)
     * @return unknown
     * 
     * edit for 2020/11/16 添加多商户支付 规则 商户1请求失败则换下一个商户2请求 直至最后一个 
     * 
     */
    public function setWeiXinPay($body, $detail, $total_fee, $orderNumber, $red_url, $trade_type, $openid, $product_id,$joinPay,$realm_ip,$count=1){
        //edit for 2020/10/13 支付单号换取该订单的店铺信息
        $total_fee = floatval($total_fee);
        //开始组装信息
        //1.版本号
        $params["p0_Version"] = '1.0';
        //2.商户编号
        $params["p1_MerchantNo"] = $joinPay['p1_MerchantNo'];
        //3.商户订单号
        $params["p2_OrderNo"] = $orderNumber;
        //4.订单金额
        $params["p3_Amount"] = sprintf("%.2f", $total_fee/100);
        //5.交易币种
        $params["p4_Cur"] = 1;
        //6.商品名称
        $params["p5_ProductName"] = $body;
        //7.商品描述
        $params["p6_ProductDesc"] = $detail;
        //8.公用回传参数
        // $params["p7_Mp"] = $_POST['p7_Mp'];
        //9.商户页面通知地址
        // $params["p8_ReturnUrl"] = $_POST['p8_ReturnUrl'];
        //10.服务器异步通知地址
        $params["p9_NotifyUrl"] = $realm_ip.self::NOTIFY_URL;
        //11.交易类型
        if($trade_type == 'WEIXIN_APP'){
            $params["q1_FrpCode"] = 'WEIXIN_APP3';
            $params["q7_AppId"] = $joinPay['joinpay_wx_appId'];
        }else{
            $params["q1_FrpCode"] = 'WEIXIN_GZH';
            $params["q5_OpenId"] = $openid;
            $params["q7_AppId"] = $joinPay['joinpay_wx_appId'];
            
        }
        // 'headline' => 'XXXX',           //主标题,大小 44pt，行高 62pt；长度限制：10
        // 'subheading_1' => 'XXXX',       //副标题-上,大小 36pt，行高 50pt；长度限制：19
        // 'subheading_2' => 'XXXX',       //副标题-下,大小 36pt，行高 50pt；长度限制：19
        // 'hyperlink_location' => 'XXXX' //超链接位置（参数：headline、subheading_1、subheading_2，remark）
        
       
        //12.银行商户编 码
        // $params["q2_MerchantBankCode"] = $_POST['q2_MerchantBankCode'];
        //13.子商户号
        // $params["q3_SubMerchantNo"] = $_POST['q3_SubMerchantNo'];
        //14.是否展示图片
        // $params["q4_IsShowPic"] = $_POST['q4_IsShowPic'];
        //15.微信 Openid
        // $params["q5_OpenId"] = $openid;
        // $params["q5_OpenId"] = 'orhcW1OPtV9KMcgCngTZlC5zsh2c';//$openid;  //测试写死orhcW1OPtV9KMcgCngTZlC5zsh2c
        //16.付款码数字
        // $params["q6_AuthCode"] = $_POST['q6_AuthCode'];
        //17.APPID
        // $params["q7_AppId"] = $this->real_appid;
        // $params["q7_AppId"] = 'wxbac10ea22f1537dd';//$this->real_appid;//测试写死公众号使用 'wxbac10ea22f1537dd';  app+支付使用wxe3be0f147d606d12
        // $params["q7_AppId"] = 'wxe3be0f147d606d12';//$this->real_appid;//测试写死公众号使用 'wxbac10ea22f1537dd';  app+支付使用wxe3be0f147d606d12
        //18.终端号
        // $params["q8_TerminalNo"] = $_POST['q8_TerminalNo'];
        //18.微信 H5 模 式
        // $params["q9_TransactionModel"] = $_POST['q9_TransactionModel'];  //新增
        //19.报备商户号
        $qa_TradeMerchantNo_Lists = explode(',',$joinPay['qa_TradeMerchantNo']);
        $qa_TradeMerchantNo = $qa_TradeMerchantNo_Lists[0];
        $params["qa_TradeMerchantNo"] = $qa_TradeMerchantNo;  //新增
        if($joinPay['joinpay_alt_is_use'] == 1){
                $params["qc_IsAlt"] = '1';
                $params["qd_AltType"] = '13';
                
        }
        if($trade_type != 'WEIXIN_APP'){
            $call_url = $realm_ip . '/wap/packages/pay/result?out_trade_no=' . $orderNumber;
            $qj_DJPlan = array(
                
                'remark' => '查看详情',               //备注-,大小 28pt，行高 40pt；长度限制：22
                'hyperlink' => $call_url,         //超链接，用于商户自定义链接跳转；长度限制：255
                'hyperlink_location' => 'headline' 
            );
            // $params["qj_DJPlan"] = json_encode($qj_DJPlan);
            $params["qj_DJPlan"] = $qj_DJPlan;
        }
        if(isset($params["qj_DJPlan"])){
            $params["qj_DJPlan"] = json_encode($qj_DJPlan,320);
           
        }
        
       
        $hmacVal = urlencode(hmacRequest($params,$joinPay["hmacVal"]));
        // var_dump(implode("", $params). $joinPay["hmacVal"]);
        $params['hmac'] = $hmacVal;
        
        debugFile($params, 'wechat_pay_2', 33333);
        
        $result = http_post(self::UNI_PAY_URL,$params); 
        debugFile($result, '汇聚支付-http_post1', 'joinpay_http_post');
        
        $results = json_decode($result,true);
        
        if($results['ra_Code'] == 100){
            //转换成支付所需参数
            
            $rc_Result = json_decode($results['rc_Result'],true);
            
            $rc_Result['rc_Result'] = $results['rc_Result'];
            $res['data'] = $rc_Result;
            $res['code'] = 1;
            $res['message'] = $results['rb_CodeMsg'];
            if($count > 1){
                //切换交易商户号了，更新交易商户号优先级
                $webConfig = new WebConfig();
                $webConfig->setJoinpayQaConfig($joinPay);
            }
            //写入订单支付商户号标识
            $orderModel = new VslOrderModel();
            $orderModel->save(['qa_TradeMerchantNo'=>$qa_TradeMerchantNo],['out_trade_no'=>$orderNumber]);
        }else if($results['ra_Code'] == 10013){ // 10013 商户被冻结 该状态下发起二次请求，直至所有商户号
            unset($qa_TradeMerchantNo_Lists[0]);
            array_push($qa_TradeMerchantNo_Lists,$qa_TradeMerchantNo); //排到最尾
            $qa_TradeMerchantNo_Lists = array_values($qa_TradeMerchantNo_Lists);
            $joinPay['qa_TradeMerchantNo'] = implode(',',$qa_TradeMerchantNo_Lists); //新排序字符串 更新
            $count +=1;
            if($count == count($qa_TradeMerchantNo_Lists)){
                $res['code'] = -1;
                $res['message'] = $results['rb_CodeMsg'];
            }else{
                return $this->setWeiXinPay($body, $detail, $total_fee, $orderNumber, $red_url, $trade_type, $openid, $product_id,$joinPay,$realm_ip,$count);
            }
            
        }else{
            $res['code'] = -1;
            $res['message'] = $results['rb_CodeMsg'];
        }
        return $res;
    }
    /**
     * 聚合支付宝支付
     */
    public function setWapPay($body, $detail, $total_fee, $orderNumber, $red_url, $trade_type, $openid, $product_id,$joinPay,$realm_ip,$return_url,$count=1){
        
        //1.版本号
        $params["p0_Version"] = '1.0';
        //2.商户编号
        $params["p1_MerchantNo"] = $joinPay['p1_MerchantNo'];
        //3.商户订单号
        $params["p2_OrderNo"] = $orderNumber;
        //4.订单金额
        $params["p3_Amount"] = sprintf("%.2f", $total_fee/100);
        //5.交易币种
        $params["p4_Cur"] = 1;
        //6.商品名称
        $params["p5_ProductName"] = $body;
        //7.商品描述
        $params["p6_ProductDesc"] = $detail;
        //8.公用回传参数
        // $params["p7_Mp"] = $_POST['p7_Mp'];
        //9.商户页面通知地址
        $params["p8_ReturnUrl"] = $return_url;
        //10.服务器异步通知地址
        $params["p9_NotifyUrl"] = $realm_ip.self::NOTIFY_URLS;
        //11.交易类型
        $params["q1_FrpCode"] = 'ALIPAY_H5';
        //12.银行商户编 码
        // $params["q2_MerchantBankCode"] = $_POST['q2_MerchantBankCode'];
        //13.子商户号
        // $params["q3_SubMerchantNo"] = $_POST['q3_SubMerchantNo'];
        //14.是否展示图片
        // $params["q4_IsShowPic"] = $_POST['q4_IsShowPic'];
        //15.微信 Openid
        // $params["q5_OpenId"] = $openid;
        //16.付款码数字
        // $params["q6_AuthCode"] = $_POST['q6_AuthCode'];
        //17.APPID
        // $params["q7_AppId"] = $this->pay_appid;
        //18.终端号
        // $params["q8_TerminalNo"] = $_POST['q8_TerminalNo'];
        //18.微信 H5 模 式
        $params["q9_TransactionModel"] = 'MODEL1';  //新增
        //19.报备商户号
        $qa_TradeMerchantNo_Lists = explode(',',$joinPay['qa_TradeMerchantNo']);
        $qa_TradeMerchantNo = $qa_TradeMerchantNo_Lists[0];
        $params["qa_TradeMerchantNo"] = $qa_TradeMerchantNo;  //新增
        //开始组装信息
        if($joinPay['joinpay_alt_is_use'] == 1){
                $params["qc_IsAlt"] = '1';
                $params["qd_AltType"] = '13';
                
        }
        $hmacVal = urlencode(hmacRequest($params,$joinPay["hmacVal"]));
        $params['hmac'] = $hmacVal;
        
        debugFile($params, '==>聚合支付获取支付宝支付H5-原生-信息0<==','1');
        // exit;
        $result = http_post(self::UNI_PAY_URL,$params); 
        debugFile($result, '汇聚支付-http_post2', 'joinpay_http_post');
        $results = json_decode($result,true);
        
        if($results['ra_Code'] == 100){
            //转换成支付所需参数
           
            //直接截取支付宝跳转链接
            $preg="/href='(.*?)'/is";
            preg_match_all($preg,$results['rc_Result'],$array2); 
            $res['data'] = stripslashes($array2[1][0]);
            $res['data2'] = $results['rc_Result'];
            $res['types'] = 1;
            $res['code'] = 1;
            $res['message'] = $results['rb_CodeMsg'];
            if($count > 1){
                //切换交易商户号了，更新交易商户号优先级
                $webConfig = new WebConfig();
                $webConfig->setJoinpayQaConfig($joinPay);
            }
            //写入订单支付商户号标识
            $orderModel = new VslOrderModel();
            $orderModel->save(['qa_TradeMerchantNo'=>$qa_TradeMerchantNo],['out_trade_no'=>$orderNumber]);
        }else if($results['ra_Code'] == 10013){ // 10013 商户被冻结 该状态下发起二次请求，直至所有商户号
            unset($qa_TradeMerchantNo_Lists[0]);
            array_push($qa_TradeMerchantNo_Lists,$qa_TradeMerchantNo); //排到最尾
            $qa_TradeMerchantNo_Lists = array_values($qa_TradeMerchantNo_Lists);
            $joinPay['qa_TradeMerchantNo'] = implode(',',$qa_TradeMerchantNo_Lists); //新排序字符串 更新
            $count +=1;
            if($count == count($qa_TradeMerchantNo_Lists)){
                $res['code'] = -1;
                $res['message'] = $results['rb_CodeMsg'];
            }else{
                return $this->setWapPay($body, $detail, $total_fee, $orderNumber, $red_url, $trade_type, $openid, $product_id,$joinPay,$realm_ip,$count);
            }
        }else{
            $res['code'] = -1;
            $res['message'] = $results['rb_CodeMsg'];
        }
        return $res;
    }
    /**
     * 
     */
    public function checkJoinPaySign($params, $sign,$website_id){
        //website_id换取聚合支付秘钥
        $webConfig = new WebConfig();
        $hmacVal = $webConfig->getConfig(0, 'JOINPAY', $website_id, 1)['hmacVal'];
        
        $hmacVal = urlencode(hmacRequest($params,$hmacVal));
        if($sign == $hmacVal){
            return 1;
        }else{
            return false;
        }
    }
    /**
     * 聚合退款
     */
    public function joinRefund($refund_trade_no,$out_trade_no,$refund_fee,$realm_ip,$joinpay_info=''){
        $params["p1_MerchantNo"] = $this->p1_MerchantNo ? $this->p1_MerchantNo : $joinpay_info['p1_MerchantNo'];
        $params["p2_OrderNo"] = $out_trade_no;
        $params["p3_RefundOrderNo"] = $refund_trade_no;
        $params["p4_RefundAmount"] = $refund_fee;
        $params["p6_NotifyUrl"] = $realm_ip.self::RETURN_NOTIFY_URL; //退款回调地址
        $params["q1_version"] = '2.0';
        $hmacVal = $this->hmacVal ? $this->hmacVal : $joinpay_info['hmacVal'];
        $hmacVal = urlencode(hmacRequest($params,$hmacVal));
        $params['hmac'] = $hmacVal;
        $result = http_post(self::UNI_RETURN_URL,$params); 
        debugFile($result, '汇聚支付-http_post3', 'joinpay_http_post');
        $results = json_decode($result,true);
        
        if($results['ra_Status'] == 100){
            return array(
                'is_success' => 1,
                'msg' => $results['rc_CodeMsg']
            );
        }else{
            return array(
                'is_success' => 0,
                'msg' => $results['rc_CodeMsg']
            );
        }
    }
    /**
     * 聚合单笔代付
     */
    public function jpWithdraw($withdraw_no,$uid,$bank_id,$money,$shop_id=0,$type=0){
        if($shop_id){
            $bank = new VslShopBankAccountModel();
            $bank_info = $bank->getInfo(['id'=>$bank_id]);
        }else{
            $bank = new VslMemberBankAccountModel();
            $bank_info = $bank->getInfo(['id'=>$bank_id]);
        }
        $webConfig = new WebConfig();
        $joinpay_infos = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $joinpay_info = $joinpay_infos['value'];
        if(!$joinpay_infos || $joinpay_info['joinpaytw_is_use'] != 1 || empty($joinpay_info['qa_TradeMerchantNo']) || empty($joinpay_info['p1_MerchantNo']) || empty($joinpay_info['hmacVal'])){
            $retval['is_success'] = -1;
            $retval['msg'] = '汇聚银行卡提现缺少参数';
            return $retval;
        }
        //组装请求数据
        //1.商户编号
        $params['userNo'] = $this->p1_MerchantNo ? $this->p1_MerchantNo : $joinpay_info['p1_MerchantNo'];
        //2.产品类型 朝夕付
        $params['productCode'] = 'BANK_PAY_DAILY_ORDER';
        //3.交易请求时间
        $params['requestTime'] =  date("Y-m-d H:i:s",time());
        //4.商户订单号
        $params['merchantOrderNo'] = $withdraw_no;
        //5.收款账户号
        $params['receiverAccountNoEnc'] = $bank_info['account_number'];
        //6.收款人
        $params['receiverNameEnc'] = $bank_info['realname'];
        //7.账户类型 对私账户：201    对公账户：204
        $params['receiverAccountType'] = 201;
        //8.收款账户联行号
        // if( $_POST['receiverBankChannelNo']!=''){
        //     $params['receiverBankChannelNo'] = $_POST['receiverBankChannelNo'];
        // }
        //9.交易金额 单位元
        $params['paidAmount'] =  sprintf("%.2f",$money);;
        //10.币种  人民币201
        $params['currency'] = 201;
        //11.是否复核
        $params['isChecked'] = $joinpay_info['joinpaytw_is_auto'] == 1 ? 202 : 201;
        //12.代付说明
        $params['paidDesc'] = '银行卡自动打款';
        //13.代付用途
        $params['paidUse'] = 201;
        //14.商户通知地址
        if($type == 1){
            $params['callbackUrl'] = $this->realm_ip.self::AUTO_NOTIFY_COM_URL;
        }else{
            $params['callbackUrl'] = $this->realm_ip.self::AUTO_NOTIFY_URL;
        }
        
        //15.代付说明 组合付参数
        // if($_POST['firstProductCode']!=''){
        //     $params['firstProductCode'] = $_POST['firstProductCode'];
        // }
        $hmacVal = $this->hmacVal ? $this->hmacVal : $joinpay_info['hmacVal'];
       
        // 验签方式
        // $pm_encryptType = $_POST["pm_encryptType"];
        $result = "";
        $hmacVal = urlencode(hmacRequest($params,$hmacVal));
        $params['hmac'] = $hmacVal;
        $result = http_post(self::SINGLEPAY_URL,$params,true); 
        debugFile($result, '汇聚支付-http_post4', 'joinpay_http_post');
        if($result){ 
            $result = json_decode($result,true);
            
            if($result['statusCode'] == 2001){//受理成功
                $retval['is_success'] = 1;
                return $retval;
            }else if($result['statusCode'] == 2002){//受理失败
                $rc_Result = $result['data'];
                
                $retval['msg'] = $rc_Result['errorDesc'];
                $retval['is_success'] = -1;
                return $retval;
            }else if($result['statusCode'] == 2003){//未知状态
                $retval['msg'] = '汇聚不能确定订单状态，建议10分钟后发起查询确认';
                $retval['is_success'] = -2;
                return $retval;
            }
        }else{ //请求失败
            $retval['msg'] = '请求失败,请稍后重试'; 
            $retval['is_success'] = -2;
            return $retval;
        }
        // 根据验签方式提交 MD5
        // if ($pm_encryptType == "1") {
        //     // 获取到页面提交的MD5 KEY
        //     $params['hmac'] = urlencode(hmacRequest($params,$_POST["hmacVal"]));
        // } else {
        //     $params['hmac'] = urlencode(hmacRequest($params,file_get_contents($_FILES["private_key"]['tmp_name']) ,2));
        // }
        // $result = http_post(Url::SINGLEPAY_URL,$params,true);
    }
    /**
     * 产生随机字符串，不长于32位
     *
     * @param int $length
     * @return 产生的随机字符串
     */
    public static function getNonceStr($length = 32)
    {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str = "";
        for ($i = 0; $i < $length; $i ++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    /**
     * 协议签约短信
     */
    public function sendSignSms($joinPay,$data_info){
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
// $platPublicKey  = "-----BEGIN PUBLIC KEY-----
// MIGfMA0GCSqGSIb3DQEBAQUAA4GNADCBiQKBgQCmUcOHsYTYi48KeLO0WgHkVsjP8idozwy7+fKnKka6KBMzXz8LasVTCCeYEl6XVXKlCQQO5a6dZF3Cf6gzMVpy4cP1hq/aTM8Fi9EYnZjNLQAPHs56H2J1DE1/Dgi4bqm5XBQWcLkaifpFHXyALQ5OmVyTucuPP6AyxDlRjlfeQQIDAQAB
// -----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        // $platPublicKey = $joinPay['fastpay_public_key'];
        // $mchPrivateKey = $joinPay['fastpay_private_key'];
        $secKey = RandomUtil::randomStr(16);
        
        //订单信息
        $data = [];
        $data["payer_name"] = AESUtil::encryptECB($data_info['bank_username'], $secKey);//加密
        $data["mch_order_no"] = "SI".getSerialNo();
        $data["order_amount"] = "0.01";
        $data["mch_req_time"] = date('Y-m-d H:i:s', time());
        $data["order_desc"] = "协议签约短信";
        $data["id_type"] = "1";
        //$data["callback_url"] = "http://10.10.10.37:8080";
        //$data["callback_param"] = null;
        $data["bank_card_no"] = AESUtil::encryptECB($data_info['account_number'], $secKey);//加密
        $data["id_no"] = AESUtil::encryptECB($data_info['bank_card'], $secKey);//加密
        $data["mobile_no"] = AESUtil::encryptECB($data_info['mobile'], $secKey);//加密
        if($data_info['validdate']){
            $data["expire_date"] = AESUtil::encryptECB($data_info['validdate'], $secKey);//加密
        }
        if($data_info['cvv2']){
            $data["cvv"] = AESUtil::encryptECB($data_info['cvv2'], $secKey);//加密
        }

        $request = new Request();
        $request->setMethod("fastPay.agreement.signSms");
        $request->setVersion("1.0");
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效

        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        
       
        
        try {
            $response = RequestUtil::doRequest(self::AGREEMENT_SMSAPI_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest0', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'q1_OrderNo'=>$data["mch_order_no"],
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P3000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'q1_OrderNo'=>$data["mch_order_no"],
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'],
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        } catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 短信签约接口
     */
    public function smsSign($joinPay,$data_info){
        
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        $secKey = RandomUtil::randomStr(16);
        //订单信息
        $data = [];
        $data["mch_order_no"] = $data_info['q1_OrderNo'];
        $data["sms_code"] = $data_info['smscode'];
        $request = new Request();
        $request->setMethod("fastPay.agreement.smsSign");
        $request->setVersion("1.0");
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效
        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        
        try {
            $response = RequestUtil::doRequest(self::AGREEMENT_SMSAPI_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest1', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'sign_no' => $dataArr["sign_no"],
                        'q1_OrderNo'=>$data["mch_order_no"],
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'sign_no' => $dataArr["sign_no"],
                        'q1_OrderNo'=>$data["mch_order_no"],
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$response->getBizMsg(),
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        }catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 银行卡支付接口
     * 无短支付接口
     * joinPay 配置信息
     * id 银行卡账号id
     * out_trade_no 订单支付编号
     */
    public function pay($joinPay,$id,$out_trade_no,$website_id=1){
        return;
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        $unifyPay = new UnifyPay();
        if (strstr($out_trade_no, 'QD')) {//渠道商订单
            $orders = $unifyPay->getChannelPayInfo($out_trade_no);
        } elseif (strstr($out_trade_no, 'DH')) {//兑换订单
            $orders = $unifyPay->getIntegralPayInfo($out_trade_no);
        } elseif (strstr($out_trade_no, 'eos')) {//购买eos内存订单
            $orders = $unifyPay->getEosPayInfo($out_trade_no);
        }else {
            $orders = $unifyPay->getPayInfo($out_trade_no);
        }
        if($orders < 0){
            return $orders;
        }
        $bank = new VslMemberBankAccountModel();
        $bank_info = $bank->getInfo(['id'=>$id]); 
        $secKey = RandomUtil::randomStr(16);
        //订单信息 
        $data = [];
        $mch_req_time = date('Y-m-d H:i:s', time());
        $data["mch_order_no"] = $out_trade_no;
        $data["order_amount"] = $orders['pay_money'];
        $data["mch_req_time"] = $mch_req_time;
        $data["order_desc"] = $orders['pay_detail'];
        $data["callback_url"] = $this->realm_ip.self::BANK_NOTIFY_URL;
        $data["callback_param"] = 1;//website_id 穿透备用
        $data["sign_no"] = AESUtil::encryptECB($bank_info['signed_id'], $secKey);//加密
        $request = new Request();
        $request->setMethod("fastPay.agreement.pay");
        $request->setVersion("1.0");
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效
        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        try {
            $response = RequestUtil::doRequest(self::AGREEMENT_SMSAPI_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest2', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                //此处更新下单时间
                $orderModel = new VslOrderModel();
                $orderModel->save(['mch_req_time'=>$mch_req_time],['out_trade_no'=>$out_trade_no,'website_id'=>$website_id]);
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$response->getBizMsg(),
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        }catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 银行异步回调处理
     */
    public function joinpayBankUrlBack($str,$website_id=1){
        $webConfig = new WebConfig();
        $fastpay_public_key = $webConfig->getConfig(0, 'JOINPAY', $website_id, 1)['fastpay_public_key'];
        // $joinpay_info = $joinpay_infos['value'];
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$fastpay_public_key."
-----END PUBLIC KEY-----";
        $response = new Response();
        $array=json_decode($str,true);
        $response->setBizCode($array['biz_code']);
        $response->setBizMsg($array['biz_msg']);
        $response->setData($array['data']);
        $response->setMchNo($array['mch_no']);
        $response->setRandStr($array['rand_str']);
        $response->setSign($array['sign']);
        $response->setSignType($array['sign_type']);
        
        //异步返回sign
        $signParam=$array['sign'];
        
        $signData=\joinpay\SignUtil::getSortedString($response,1);
        
        $isMatch = \joinpay\RSAUtil::verify($signData, $signParam, $platPublicKey);
        
        return $isMatch;

    }
    /**
     * 签约解绑
     */
    public function unSign($joinPay,$sign_no){
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        $secKey = RandomUtil::randomStr(16);
        //订单信息
        $data = [];
        $data["mch_order_no"] = "UNSI".getSerialNo();
        $data["mch_req_time"] = date('Y-m-d H:i:s', time());
        $data["sign_no"] = AESUtil::encryptECB($sign_no, $secKey);//加密
        $request = new Request();
        $request->setMethod("fastPay.agreement.unSign");
        $request->setVersion("1.0");
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效
        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        
        try {
            $response = RequestUtil::doRequest(self::AGREEMENT_SMSAPI_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest3', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$response->getBizMsg(),
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        }catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 支付短信接口
     */
    public function paySms($joinPay,$id,$out_trade_no,$website_id=1){
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        $unifyPay = new UnifyPay();
        if (strstr($out_trade_no, 'QD')) {//渠道商订单
            $orders = $unifyPay->getChannelPayInfo($out_trade_no);
        } elseif (strstr($out_trade_no, 'DH')) {//兑换订单
            $orders = $unifyPay->getIntegralPayInfo($out_trade_no);
        } elseif (strstr($out_trade_no, 'eos')) {//购买eos内存订单
            $orders = $unifyPay->getEosPayInfo($out_trade_no);
        }else {
            $orders = $unifyPay->getPayInfo($out_trade_no);
        }
        if($orders < 0){
            return $orders;
        }
        $bank = new VslMemberBankAccountModel();
        $bank_info = $bank->getInfo(['id'=>$id]); 
        $secKey = RandomUtil::randomStr(16);
        //订单信息 
        $data = [];
        $data["mch_order_no"] = $out_trade_no;
        $data["order_amount"] = $orders['pay_money'];
        $data["mch_req_time"] = date('Y-m-d H:i:s', time());
        $data["order_desc"] = $orders['pay_detail'];
        $data["callback_url"] = $this->realm_ip.self::BANK_NOTIFY_URL;
        $data["callback_param"] = 1;//website_id 穿透备用
        $data["sign_no"] = AESUtil::encryptECB($bank_info['signed_id'], $secKey);//加密
        $request = new Request();
        $request->setMethod("fastPay.agreement.paySms");
        $request->setVersion("1.0");
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效
        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        try {
            $response = RequestUtil::doRequest(self::AGREEMENT_SMSAPI_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest4', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$response->getBizMsg(),
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        }catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 有短支付接口
     * sms_code 支付短信验证码
     * mch_order_no 商户订单号，必须和支付短信接口订单号一致
     * mch_req_time 订单下单时间格式yyyy-MM-dd HH:mm:ss
     */
    public function smsPay($joinPay,$smscode,$out_trade_no,$website_id=1){
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        
        $secKey = RandomUtil::randomStr(16);
        //订单信息 
        $data = [];
        $mch_req_time = date('Y-m-d H:i:s', time());
        $data["mch_order_no"] = $out_trade_no;
        $data["sms_code"] = $smscode;
        $data["mch_req_time"] = $mch_req_time;
        $request = new Request();
        $request->setMethod("fastPay.agreement.smsPay");
        $request->setVersion("1.0");
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效 
        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        try {
            $response = RequestUtil::doRequest(self::AGREEMENT_SMSAPI_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest5', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                //此处更新下单时间
                $orderModel = new VslOrderModel();
                $orderModel->save(['mch_req_time'=>$mch_req_time],['out_trade_no'=>$out_trade_no,'website_id'=>$website_id]);
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                        'order_status' => $dataArr["order_status"],
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$response->getBizMsg(),
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        }catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 退款接口
     */
    public function refund(){

    }
    /**
     * 查询支付结果
     * QUERY_URL
     */
    public function payQuery($joinPay,$out_trade_no,$website_id=1){
//平台公钥
$platPublicKey = "-----BEGIN PUBLIC KEY-----
".$joinPay['fastpay_public_key']."
-----END PUBLIC KEY-----";
//商户私钥
$mchPrivateKey = "-----BEGIN RSA PRIVATE KEY-----
".$joinPay['fastpay_private_key']."
-----END RSA PRIVATE KEY-----";
        //订单号换取订单信息
        // $orderModel = new VslOrderModel();
        // $order_info = $orderModel->getInfo(['out_trade_no'=>$out_trade_no,'website_id'=>$website_id],'mch_req_time');
        $order_info['mch_req_time']="2020-09-25 09:40:19";
        
        $secKey = RandomUtil::randomStr(16);
        //订单信息 
        $data = [];
        $data["mch_order_no"] = $out_trade_no;
        $data["org_mch_req_time"] = $order_info['mch_req_time'];
        
        $request = new Request();
        
        $request->setMethod("fastPay.query");
        $request->setVersion("1.0");
        
        $request->setMchNo($joinPay['p1_MerchantNo']);
        $request->setSignType("2");
        
        $request->setRandStr(RandomUtil::randomStr(32));
        $request->setData($data);
        $request->setSecKey($secKey);//rsa有效
       
        $secretKey = new SecretKey();
        $secretKey->setReqSignKey($mchPrivateKey);//签名：使用商户私钥
        $secretKey->setRespVerifyKey($platPublicKey);//验签：使用平台公钥
        $secretKey->setSecKeyEncryptKey($platPublicKey);//sec_key加密：使用平台公钥
        $secretKey->setSecKeyDecryptKey($mchPrivateKey);//sec_key解密：使用商户私钥
        try {
            $response = RequestUtil::doRequest(self::QUERY_URL, $request, $secretKey);
            debugFile($response, 'jonipay_doRequest6', 'jonipay_doRequest');
            if ($response->isSuccess()) {//受理成功
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){//订单交易成功
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                    
                }else{
                    $result = array(
                        'biz_msg'=>$dataArr['err_msg'] ? $dataArr['err_msg'] : 'FAIL OR PROCESSING OR UNKNOWN',
                        'rc_CodeMsg'=>'FAIL OR PROCESSING OR UNKNOWN',
                        'biz_code'=>'-1',
                        'order_status' => $dataArr["order_status"],
                    );
                    // echo "FAIL OR PROCESSING OR UNKNOWN, Response = ";
                    // print_r($dataArr);exit;
                }
            }else{
                $dataArr = json_decode($response->getData(), true);
                
                if($dataArr["order_status"] == "P1000" || $dataArr["order_status"] == "P3000"){
                    $result = array(
                        'resp_code'=>'SUCCESS',
                        'order_status' => $dataArr["order_status"],
                        'biz_code'=>'JS000000',
                    );
                }else{
                    $result = array(
                        'biz_msg'=>$response->getBizMsg(),
                        'rc_CodeMsg'=>'受理失败',
                        'biz_code'=>'-2',
                    );
                }
                // echo "受理失败, Response = ";
                // print_r($response);exit;
            }
            return $result;
        }catch (\Exception $e) {
            print_r($e);exit;
        }
    }
    /**
     * 延迟分账开始 ALLOCFUNDS_URL
     * 拆分订单支持与否 or 整单支付单号处理
     * { "mch_no": "888000000000000", "method": "altHandle.singleLaterAllocate", "rand_str": "12345678901234567890123456789012", "sign_type": "1", "version": "1.0", "data": { "alt_order_no":"1555987155126", "mch_order_no": "1555987155126", "alt_info": [{ "alt_mch_no": "333100000006528", "alt_amount": "30" }, { "alt_mch_no": "333100000006529", "alt_amount": "30" }], "alt_url": "http://www.joinpay.com" } }
     */
    public function allocFunds($info,$out_trade_no,$joinpay_machid){
        //获取汇聚配置信息 $joinPay
        $webConfig = new WebConfig();
        $joinpay_infos = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $joinpay_info = $joinpay_infos['value'];
        //1.版本号
        $params["method"] = 'altHandle.singleLaterAllocate';
        //2.版本号
        $params["version"] = '1.0';
        //3.交易数据 ALLOCFUNDS_NOTIFY_URL
        $new_altInfo = array();
        $altInfo = array(
            'alt_mch_no'=>$joinpay_machid,
            'alt_amount'=>$info['pay_money_all']
        );
        array_push($new_altInfo,$altInfo);
        $data = array(
            'alt_url' => $this->realm_ip.self::ALLOCFUNDS_NOTIFY_URL,
            'mch_order_no' => $out_trade_no,
            'alt_order_no' => $info['order_no'],
            'alt_info' => $new_altInfo
        );
        $params["data"] = $data;
        //4.32字符串
        $params["rand_str"] = $this->getNonceStr();
        //5.签名类型
        $params["sign_type"] = '1';
        //6.商户编号
        $params["mch_no"] = $joinpay_info['p1_MerchantNo'];
        //7签名
        $hmacVal = $this->hmacVal ? $this->hmacVal : $joinpay_info['hmacVal'];
        $params['sign'] = hmacRequestMd5($params,$hmacVal);
        $result = HttpPostCurl(self::ALLOCFUNDS_URL,json_encode($params,JSON_UNESCAPED_SLASHES),'json'); 
        $shopSeparateModel = new VslShopSeparateModel();
        if($result['resp_code'] == 'A1000' && $result['data']['biz_code'] == 'B100000'){
            //受理失败
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>1],['id'=>$info['id']]);
        }else if($result['resp_code'] == 'A2000'){
            //受理成功
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>3],['id'=>$info['id']]);
        }else{
            //未知状态
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>3],['id'=>$info['id']]);
        }
        return;
    }
    /**
     * 商户间转账 
     * 平台->子商户
     * 子商户->子商户
     * 子商户->平台
     */
    public function transfer($info,$out_trade_no,$joinpay_machid,$transfer_type=1,$txt='订单确认分账'){
        $webConfig = new WebConfig();
        $joinpay_infos = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $joinpay_info = $joinpay_infos['value'];
        $params["method"] = 'transfer.launch';
        $params["version"] = '1.0';
        $params["data"] = array(
            'source_mch_no' => $joinpay_info['p1_MerchantNo'],//转账发起方编号
            'target_mch_no' => $joinpay_machid,//转账接收方编号
            'transfer_amount' => $info['pay_money_all'],//转账金额
            'transfer_type' => $transfer_type,//转账类型
            // 'phone_code' => 1,//短信验证码 非必填 转账发起方为分账方的，默认开通短验
            'mch_order_no' => $out_trade_no,//商户订单号 变更 支付号变更为订单号 
            'transfer_remark' => $txt,//转账说明 
            'callback_url' => $this->realm_ip.self::TRANSFER_NOTIFY_URL//回调地址
        );
        //4.32字符串
        $params["rand_str"] = $this->getNonceStr();
        //5.签名类型
        $params["sign_type"] = '1';
        //6.商户编号
        $params["mch_no"] = $joinpay_info['p1_MerchantNo'];
        //7签名
        $hmacVal = $this->hmacVal ? $this->hmacVal : $joinpay_info['hmacVal'];
        $params['sign'] = hmacRequestMd5($params,$hmacVal);
        $result = HttpPostCurl(self::ALLOCFUNDS_URL,json_encode($params,JSON_UNESCAPED_SLASHES),'json'); 
        
        $shopSeparateModel = new VslShopSeparateModel();
        if($result['resp_code'] == 'A1000' && $result['data']['biz_code'] == 'B100000'){
            //受理失败
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>1],['id'=>$info['id']]);
        }else if($result['resp_code'] == 'A2000'){
            //受理成功
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>3],['id'=>$info['id']]);
        }else{
            //未知状态
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>3],['id'=>$info['id']]);
        }
        return;
    }
    /**
     * 转账，分账签名校验
     */
    public function checkAllocFundsSign($params, $sign,$website_id){
        //website_id换取聚合支付秘钥
        $webConfig = new WebConfig();
        $hmacVal = $webConfig->getConfig(0, 'JOINPAY', $website_id, 1)['hmacVal'];
        //查询是否有data数据 有则先对data作出排序
        if($params['data']){
            
        }
        $hmacVal = hmacRequestMd5($params,$hmacVal);
        if($sign == strtoupper($hmacVal)){
            return 1;
        }else{
            return false;
        }
    }
    /**
     * 回调处理待打款记录 
     */
    public function allocFundsNotify(){
        
    }
    /**
     * 多次分账
     */
    public function manyallocFunds($info,$out_trade_no,$joinpay_machid){
        
        //获取汇聚配置信息 $joinPay
        $webConfig = new WebConfig();
        $joinpay_infos = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $joinpay_info = $joinpay_infos['value'];
        //1.版本号
        $params["method"] = 'altHandle.manyLaterAllocate';
        //2.版本号
        $params["version"] = '1.0';
        //3.交易数据 ALLOCFUNDS_NOTIFY_URL
        //获取订单支付总金额  手续费千分6 
        $order = new VslOrderModel();
        $pay_money = $order->getSum(['out_trade_no'=>$out_trade_no],'pay_money');//分红 变更至退款后的金额
        $pay_money = floatval($pay_money);
        $order_charge = $pay_money*0.006 > 0.01 ? round($pay_money*0.006,2) : 0.01;
        //本次分账金额
        $alt_amount = $info['pay_money_all']-$info['pay_money_all']/$pay_money*$order_charge;
        //本商户订单分账手续费
        $shop_charge = $alt_amount/$pay_money*$order_charge;
        $new_altInfo = array();
        $altInfo = array(
            'alt_mch_no'=>$joinpay_machid,
            'alt_amount'=>$alt_amount
        );
        
        array_push($new_altInfo,$altInfo);
        $data = array(
            'callback_url' => $this->realm_ip.self::ALLOCFUNDS_NOTIFY_URL,
            'mch_order_no' => $out_trade_no,
            'alt_order_no' => $info['order_no'],//分账订单号
            'alt_this_amount' => $info['pay_money_all'],//此次收单支 付分账金额
            'alt_info' => $new_altInfo
        );
        $params["data"] = $data;
        //4.32字符串
        $params["rand_str"] = $this->getNonceStr();
        //5.签名类型
        $params["sign_type"] = '1';
        //6.商户编号
        $params["mch_no"] = $joinpay_info['p1_MerchantNo'];
        //7签名
        $hmacVal = $this->hmacVal ? $this->hmacVal : $joinpay_info['hmacVal'];
        $params['sign'] = hmacRequestMd5($params,$hmacVal);
        
        $result = HttpPostCurl(self::ALLOCFUNDS_URL,json_encode($params,JSON_UNESCAPED_SLASHES),'json'); 
        $shopSeparateModel = new VslShopSeparateModel();
        if($result['resp_code'] == 'A1000' && $result['data']['biz_code'] == 'B100000'){
            //受理失败
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>1,'charge'=>$order_charge,'shop_charge'=>$shop_charge],['order_no'=>$info['order_no']],['id'=>$info['id']]);
        }else if($result['resp_code'] == 'A2000'){
            //受理成功
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>3,'charge'=>$order_charge,'shop_charge'=>$shop_charge],['order_no'=>$info['order_no']],['id'=>$info['id']]);
        }else{
            //未知状态
            $shopSeparateModel->save(['remark'=>$result['data']['biz_code'].$result['data']['biz_msg'],'send_time'=>time(),'status'=>3,'charge'=>$order_charge,'shop_charge'=>$shop_charge],['order_no'=>$info['order_no']],['id'=>$info['id']]);
        }
        return;
    }
}
