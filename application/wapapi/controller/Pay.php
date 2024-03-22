<?php
namespace app\wapapi\controller;

use addons\anticounterfeiting\model\AntiCounterfeitingBatchModel;
use addons\blockchain\model\VslEosOrderPayMentModel;
use addons\blockchain\service\Block;
use addons\anticounterfeiting\server\AntiCounterfeiting;
use addons\channel\model\VslChannelGoodsSkuModel;
use addons\channel\model\VslChannelOrderModel;
use addons\channel\model\VslChannelOrderPaymentModel;
use addons\channel\server\Channel;
use data\model\VslAppointOrderModel;
use data\service\AddonsConfig;
use data\model\VslMemberRechargeModel;
use data\model\VslOrderModel;
use data\model\VslOrderPaymentModel;
use data\model\WebSiteModel;
use data\service\Order;
use data\service\Pay\tlPay;
use data\service\UnifyPay;
use data\service\Pay\GlobePay as globalpay;
use data\service\Pay\Joinpay;
use addons\shop\model\VslShopSeparateModel;
/**
 * 支付控制器
 *
 * @author  www.vslai.com
 *
 */
class Pay extends BaseController
{

    public $style;

    public $shop_config;

    protected $website_id;

    public function __construct()
    {
        parent::__construct();
    }

    /* 演示版本 */
    public function demoVersion()
    {
        return view($this->style . 'Pay/demoVersion');
    }

    //付尾款
    public function pay_last_money()
    {
        $order_id = request()->post('order_id','');
        if(empty($order_id)){
            $data['code'] = -1;
            $data['message'] = "缺少参数";
            $data['data'] = '';
            return json($data);
        }
        //查出用于付尾款的交易号
        $order = new VslOrderModel();
        $order_payment_mdl = new VslOrderPaymentModel();
        $order_info = $order->getInfo(['order_id'=>$order_id],'out_trade_no_presell, final_money');
        $out_trade_no = $order_info['out_trade_no_presell'];
        $last_money = $order_info['final_money'] ? : 0;
        $pay = new UnifyPay();
        $order_payment_info = $order_payment_mdl->getInfo(['out_trade_no'=>$out_trade_no]);
        if (!$order_payment_info) {
            $pay->createPayment(0, $out_trade_no, '尾款支付', '商品尾款支付', $last_money, 1, $order_id);
        }
        $data['data'] = [];
        $data['code'] = 0;
        $data['message'] = "支付单据创建成功";
        $data['data']['out_trade_no'] = $out_trade_no;
        return json($data);
    }

    /**
     * 获取支付相关信息
     */
    public function getPayValue()
    {
        $out_trade_no = request()->get('out_trade_no', '');
        if (empty($out_trade_no)) {
            $this->error("没有获取到支付信息");
        }
        $pay = new UnifyPay();
        $pay_config = $pay->getPayConfig();
        $this->assign("pay_config", $pay_config);
        //渠道商订单 前缀QD
        if( strstr($out_trade_no, 'QD') ){
            $pay_value = $pay->getChannelPayInfo($out_trade_no);
            $order_status = $this->getChannelOrderStatusByOutTradeNo($out_trade_no);
            //获取渠道商设置时间
            $config = new AddonsConfig();
            $channel_setting_arr = $config->getAddonsConfig('channel', $this->website_id, 0, 1);
            $this->shop_config['order_buy_close_time'] = $channel_setting_arr['channel_order_close_time'];
        }else{
            $pay_value = $pay->getPayInfo($out_trade_no);
            $order_status = $this->getOrderStatusByOutTradeNo($out_trade_no);
        }
        if (empty($pay_value)) {
            $this->error("订单主体信息已发生变动!", __URL(__URL__ . "/member/index"));
        }
        // 订单关闭状态下是不能继续支付的
        if ($order_status == 5) {
            $this->error("订单已关闭");
        }
        $zero1 = time(); // 当前时间 ,注意H 是24小时 h是12小时
        $zero2 = $pay_value['create_time'];
        if ($zero1 > ($zero2 + ($this->shop_config['order_buy_close_time'] * 60))) {
            $this->error("订单已关闭");
        } else {
            $this->assign('pay_value', $pay_value);
            if (request()->isMobile()) {
                return view($this->style . 'Pay/getPayValue'); // 手机端
            } else {
                return view($this->style . 'Pay/pcOptionPaymentMethod'); // PC端
            }
        }
    }

    /**
     * 支付完成后回调界面
     *
     * status 1 成功
     *
     * @return \think\response\View
     */
    public function payCallback()
    {
        $out_trade_no = request()->get('out_trade_no', ''); // 流水号
        $msg = request()->get('msg', ''); // 测试，-1：在其他浏览器中打开，1：成功，2：失败
        $this->assign("status", $msg);
        $order_no = $this->getOrderNoByOutTradeNo($out_trade_no);
        $this->assign("order_no", $order_no);
        if (request()->isMobile()) {
            return view($this->style . "Pay/payCallback");
        } else {
            return view($this->style . "Pay/payCallbackPc");
        }
    }

    //判断订单是否为尾款，是则更改状态
    public function check_is_last_money($out_trade_no){
        $pay = new UnifyPay();
        $order_info = $pay->get_order_info($out_trade_no);
        if($order_info['pay_status']==2&&$order_info['money_type']==1){
            $order = new VslOrderModel();
            $order->save(['money_type'=>2],['order_id'=>$order['order_id']]);
        }
    }
	/**
	 * GlobePay异步回调（只有异步回调对订单进行处理）
	 */
	public function gpayUrlBack()
	{
		$response = json_decode($GLOBALS['HTTP_RAW_POST_DATA'], true);
		if(empty($response)){
			$response = json_decode(file_get_contents('php://input'), true);
		}
		if (!empty($response)) {
			$out_trade_no=$response['partner_order_id'];
			$pay = new UnifyPay();
			$gbpay = new globalpay();
			if(strstr($out_trade_no, 'DH')){
				$key = 'integral_pay_' . $out_trade_no;
				$redis = connectRedis();
				$integral_order = $redis->get($key);
				$integral_order_arr = json_decode($integral_order, true);
				$order_from['order_from'] = $integral_order_arr['type']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
			}else if(strstr($out_trade_no, 'MD')){
				$key = 'store_pay_' . $out_trade_no;
				$redis = connectRedis();
				$store_order = $redis->get($key);
				$store_order_arr = json_decode($store_order, true);
				$order_from = [];
				$order_from['order_from'] = $store_order_arr['order_from']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
			}else if(strstr($out_trade_no, 'eos')){
				$order_eos = new VslEosOrderPayMentModel();
				$order_from = $order_eos->getInfo(['out_trade_no'=>"{$out_trade_no}",'type'=>1],'pay_from,website_id');
			}elseif(strstr($out_trade_no, 'QD')){
				$order = new VslChannelOrderPaymentModel();
				$order_from = $order->getInfo(['out_trade_no'=>"{$out_trade_no}",'type'=>1],'pay_from,website_id');
			} else{
				$order = new VslOrderPaymentModel();
				$order_from = $order->getInfo(['out_trade_no'=>$out_trade_no],'pay_from, website_id');
			}
			// 支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
			if($order_from){
				if($order_from['pay_from'] == 6 ) { //by sgw
					$check_sign = $gbpay->checkSignMp($response, $response['sign'],$order_from['website_id']);
				}else{
					$check_sign = $gbpay->checkSign($response, $response['sign'],$order_from['website_id']);
				}
			} else {
				$order_recharge = new VslMemberRechargeModel();
				$website_id = $order_recharge->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
				$check_sign = $gbpay->checkSignMp($response, $response['sign'],$website_id);
			}
			if ($response['pay_time'] && $check_sign) {
				if(strstr($out_trade_no, 'eos')){
					$block = new Block();
					$block->eosPayBack("{$out_trade_no}");
				}else{
					$pay->onlinePay("{$out_trade_no}", 20, '');
				}
			}
		}

    }
    /**
     * 聚合银行卡代付异步回调
     * 201
    订单已创建
    汇聚受理并创建代付订单的初始状态
    202
    待商户审核
    代付订单处在等待商户审核的阶段
    203
    交易处理中
    代付订单处理中状态
    204
    交易失败
    明确交易失败的状态码
    205
    交易成功
    明确交易成功的状态码
    208
    订单已取消
    代付订单被商户审核为拒绝付款
    210
    账务冻结中
    确认出款后对商户的账户余额进行冻结处理
    211
    账务解冻中
    确认失败后对商户的账户余额进行解冻处理
    212
    订单取消中
    商户审核为拒绝付款的中间状态
    213
    账务扣款中
    确认成功后对商户的账户冻结款的扣款处理
    214
    订单不存在
    汇聚未受理该笔代付请求，找不到该笔订单，明确失败
     */
    public function joinpayAutoSendUrlBack(){
        $postStr2 = file_get_contents('php://input');
        $result = json_decode($postStr2,true);
        debugLog($result,'==>joinpayAutoSendUrlBack1<==');
        debugLog($postStr2,'==>joinpayAutoSendUrlBack12<==');
        $status = $result['status'];
        $merchantOrderNo = $result['merchantOrderNo'];
        $errorCodeDesc = $result['errorCodeDesc'];
        $memberAccount = new MemberAccount();
        $res = 0;
        switch($status){
            case '205':
                $res = $memberAccount->changeStatus($merchantOrderNo,1);//交易成功
                break;
            case '204':
                $res = $memberAccount->changeStatus($merchantOrderNo,2,$errorCodeDesc);//交易失败
                break;
            default:
                $res = $memberAccount->changeStatus($merchantOrderNo,3); //处理中
                break;
        }
        if($res == 1 && ($status == 205 || $status == 204)){
            $results['statusCode'] = "2001";
            $results['message'] = "成功";
            debugLog(json_encode($results),'==>joinpayAutoSendUrlBack2<==');
            echo json_encode($results);
        }
    }
    public function joinpayAutoComSendUrlBack(){
        $postStr2 = file_get_contents('php://input');
        $result = json_decode($postStr2,true);
        debugLog($result,'==>joinpayAutoSendUrlBack1<==');
        debugLog($postStr2,'==>joinpayAutoSendUrlBack12<==');
        $status = $result['status'];
        $merchantOrderNo = $result['merchantOrderNo'];
        $errorCodeDesc = $result['errorCodeDesc'];
        $groupTicket = new GroupTicket();
        debugLog($status,'==>status<==');
        $res = 0;
        switch($status){
            case '205':
                $res = $groupTicket->changeStatus($merchantOrderNo,1);//交易成功
                break;
            case '204':
                $res = $groupTicket->changeStatus($merchantOrderNo,2,$errorCodeDesc);//交易失败
                break;
            default:
                $res = $groupTicket->changeStatus($merchantOrderNo,3); //处理中
                break;
        }
        if($res == 1 && ($status == 205 || $status == 204)){
            $results['statusCode'] = "2001";
            $results['message'] = "成功";
            debugLog(json_encode($results),'==>joinpayAutoSendUrlBack2<==');
            echo json_encode($result);
        }
    }
    /**
     * 聚合转账回调
     */
    public function joinpayTransferUrlBack(){
        $postStr2 = file_get_contents('php://input');
        debugLog($postStr2,'==>聚合转账回调异步回调信息2<==');
        $result = json_decode($postStr2,true);
        $hmac = $result['sign'];
        //支付单号换取website_id
        $order_no = $result['data']['mch_order_no'];
        $sepModel = new VslShopSeparateModel();
        $sep_info = $sepModel->getInfo(['order_no'=>$order_no],'website_id,id');
        $website_id = $sep_info['website_id'];
        if($sep_info['r_status'] == 2){
            $respJson['resp_code'] = "A1000";
            $respJson['resp_msg'] = "success";
            echo json_encode($respJson);
            exit;
        }
        //换取待打款记录 -- 
        unset($result['sign']);
        unset($result['aes_key']);
        $joinpay_server = new Joinpay();
        $verify_result = $joinpay_server->checkAllocFundsSign($result, $hmac,$website_id);
        if($verify_result === false){
            $respJson['resp_code'] = "A2000";
            $respJson['resp_msg'] = "false,签名验证失败";
            echo json_encode($respJson);
            exit;
        }
        if($verify_result == 1 && $result['resp_code'] == 'A1000' && $result['data']['transfer_status'] == 'P1000'){
            //转账成功 
            $insert_data['r_status'] = 2;
            if($sep_info['joinpay'] != 1){
                $insert_data['status'] = 2;
            }
            $sepModel->save($insert_data,['id'=>$sep_info['id']]);
        }else if($verify_result == 1 && $result['data']['transfer_status'] == 'P2000'){
            // 交易失败
            $insert_data['r_status'] = 3;
            if($sep_info['joinpay'] != 1){
                $insert_data['status'] = 3;
                $insert_data['remark'] = $result['data']['biz_code'].$result['data']['biz_msg'];
            }
            $sepModel->save($insert_data,['id'=>$sep_info['id']]);
        }else if($verify_result == 1 && $result['resp_code'] == 'A2000'){
            // 受理失败
            $insert_data['r_status'] = 3;
            if($sep_info['joinpay'] != 1){
                $insert_data['status'] = 3;
                $insert_data['remark'] = $result['data']['biz_code'].$result['data']['biz_msg'];
            }
            $sepModel->save($insert_data,['id'=>$sep_info['id']]);
        }
        $respJson['resp_code'] = "A1000";
        $respJson['resp_msg'] = "success";
        echo json_encode($respJson);
        exit;
    }
    /**
     * 聚合分账回调
     */
    public function joinpayAllocfundsUrlBack(){
        $postStr2 = file_get_contents('php://input');
        debugLog($postStr2,'==>聚合分账回调异步回调信息2<==');
        $result = json_decode($postStr2,true);
        $hmac = $result['sign'];
        //支付单号换取website_id
        $order_no = $result['data']['alt_order_no'];
        $sepModel = new VslShopSeparateModel();
        $sep_info = $sepModel->getInfo(['order_no'=>$order_no],'website_id,id');
        $website_id = $sep_info['website_id'];
        if($sep_info['status'] == 2){
            $respJson['resp_code'] = "A1000";
            $respJson['resp_msg'] = "success";
            echo json_encode($respJson);
            exit;
        }
        //换取待打款记录 -- 
        unset($result['sign']);
        unset($result['aes_key']);
        $joinpay_server = new Joinpay();
        $verify_result = $joinpay_server->checkAllocFundsSign($result, $hmac,$website_id);
        if($verify_result === false){
            $respJson['resp_code'] = "A2000";
            $respJson['resp_msg'] = "false,签名验证失败";
            echo json_encode($respJson);
            exit;
        }
        if($verify_result == 1 && $result['resp_code'] == 'A1000' && $result['data']['alt_main_status'] == 'P1000'){
            //转账成功 
            //开始处理更新数据 allocate_status 100 分账成功 102 分账已创建 所有可以忽略该值
            // 因为已经拆单，每次只处理一个店铺的分账请求，所有实际只有一条待处理
            $insert_data['status'] = 2;
            $sepModel->save($insert_data,['id'=>$sep_info['id']]);
        }else if($verify_result == 1 && $result['data']['alt_main_status'] == 'P2000'){
            // 交易失败
            $insert_data['status'] = 3;
            $insert_data['remark'] = $result['data']['biz_code'].$result['data']['biz_msg'];
            $sepModel->save($insert_data,['id'=>$sep_info['id']]);
        }else if($verify_result == 1 && $result['resp_code'] == 'A2000'){
            // 受理失败
            $insert_data['status'] = 3;
            $insert_data['remark'] = $result['data']['biz_code'].$result['data']['biz_msg'];
            $sepModel->save($insert_data,['id'=>$sep_info['id']]);
        }
        $respJson['resp_code'] = "A1000";
        $respJson['resp_msg'] = "success";
        echo json_encode($respJson);
        exit;
    }
    /**
     * 聚合银行卡支付异步回调
     * data->callback_param 穿透参数回传website_id
     */
    public function joinpayBankUrlBack(){
        $postStr = file_get_contents('php://input');
        debugLog($postStr,'==>joinpayBankUrlBack<==');
        $result = json_decode($postStr,true);
        debugLog($result,'==>joinpayBankUrlBack1<==');
        $data = json_decode($result['data'],true);
        debugLog($data,'==>joinpayBankUrlBack2<==');
        $out_trade_no = $data['mch_order_no'];
        if($data['callback_param']){
            $website_id = $data['callback_param'];
        }else{
            //订单号换取website_id
            if(strstr($out_trade_no, 'DH')){
                $out_trade_no = $out_trade_no;
                $key = 'integral_pay_' . $out_trade_no;
                $redis = $this->connectRedis();
                $integral_order = $redis->get($key);
                $integral_order_arr = json_decode($integral_order, true);
                $order_from['order_from'] = $integral_order_arr['type']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
            }else if(strstr($out_trade_no, 'MD')){
                $out_trade_no = $out_trade_no;
                $key = 'store_pay_' . $out_trade_no;
                $redis = $this->connectRedis();
                $store_order = $redis->get($key);
                $store_order_arr = json_decode($store_order, true);
                $order_from = [];
                $order_from['order_from'] = $store_order_arr['order_from']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
            }else if(strstr($out_trade_no, 'eos')){
                $order_eos = new VslEosOrderPayMentModel();
                $order_from = $order_eos->getInfo(['out_trade_no'=>"{$out_trade_no}",'type'=>1],'pay_from,website_id');
            }elseif(strstr($out_trade_no, 'QD')){
                $order = new VslChannelOrderPaymentModel();
                $order_from = $order->getInfo(['out_trade_no'=>"{$out_trade_no}",'type'=>1],'pay_from,website_id');
            } else{
                $order = new VslOrderPaymentModel();
                $order_from = $order->getInfo(['out_trade_no'=>$out_trade_no],'pay_from, website_id');
            }
            $website_id = $order_from['website_id'];
        }
        
        
        $joinpay = new Joinpay();
        $check_sign = $joinpay->joinpayBankUrlBack($postStr,$website_id);
        debugLog($check_sign,'==>oinpayBankUrlBack4-check_sign<==');
        
       
        //验签异常，暂时去掉$check_sign === 1 && 
        if($check_sign === true && $result['biz_code'] == 'JS000000' && $data['order_status'] == 'P1000'){
            if(strstr($out_trade_no, 'eos')){
                $block = new Block();
                $block->eosPayBack("{$out_trade_no}");
                $results['statusCode'] = "2001";
                $results['message'] = "成功";
                echo json_encode($results);
                exit;
            }else{
                
                $pay = new UnifyPay();
                $pay->onlinePay("{$out_trade_no}", 3, '', 1);
                $results['statusCode'] = "2001";
                $results['message'] = "成功";
                echo json_encode($results);
                exit;
            }
        }

    }
    public function joinpayFastReturnUrlBack(){
        $postStr = file_get_contents('php://input');
        debugLog($postStr,'==>joinpayBankUrlBack<==');
        $result = json_decode($postStr,true);
        debugLog($result,'==>joinpayBankUrlBack1<==');
        $data = json_decode($result['data'],true);
        debugLog($data,'==>joinpayBankUrlBack2<==');
        $out_trade_no = $data['mch_order_no'];
    }
    /**
     * 聚合退款回调
     */
    public function joinpayReturnUrlBack(){
        $postStr = $_REQUEST;;
        $postStr2 = file_get_contents('php://input');
        debugLog($postStr,'==>聚合支付获取支付宝支付异步回调信息1<==');
        debugLog($postStr2,'==>聚合支付获取支付宝支付异步回调信息2<==');
        echo "success";
    }
    /**
     * 聚合支付支付宝支付异步回调
     */
    public function joinpayAliUrlBack()
    {
        $pay = new UnifyPay();
        $order = new VslOrderModel();
        // $out_trade_no = request()->post('out_trade_no', '');
        
        $postStr = $_REQUEST;
        // $postStr4 = $_SERVER["QUERY_STRING"];
        $postObj = $postStr;
        
        $out_trade_no = $postStr['r2_OrderNo'];
        
        if(strstr($out_trade_no, 'QD')){
            $channel_order = new VslChannelOrderModel();
            $website_id = $channel_order->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }else{
            $website_id = $order->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }
        if(!$website_id){
            $order_recharge = new VslMemberRechargeModel();
            $website_id = $order_recharge->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }
        
        $hmac = $postObj['hmac'];
        unset($postObj['s']);
        unset($postObj['hmac']);
        $postObj['ra_PayTime'] = urldecode($postObj['ra_PayTime']);
        $postObj['rb_DealTime'] = urldecode($postObj['rb_DealTime']);
        
        $verify_result = $pay->checkJoinPaySign($postObj, $hmac,$website_id);
        
        // $verify_result = $pay->alipayNotify($_POST,$website_id);
        if ($verify_result == 1) { // 验证成功
            $out_trade_no = $out_trade_no;
            $trade_no = $postStr['r7_TrxNo'];
            $r6_Status = $postStr['r6_Status'];
            // 支付宝交易号
            // $trade_no = request()->post('trade_no', '');
            // 交易状态
            $trade_status = request()->post('trade_status', '');
            if ($r6_Status == 100) {
                if(strstr($out_trade_no, 'eos')){
                    $block = new Block();
                    $block->eosPayBack("{$out_trade_no}");
                }else{
                    $res = $pay->onlinePay($out_trade_no, 2, $trade_no, 1);
                    
                }
            }
            echo "success";
        } else {
            // 验证失败
            echo "fail";
        }
    }
    /**
     * 聚合支付异步回调（只有异步回调对订单进行处理）
     * add for 2020/08/18
     */
    public function joinpayUrlBack(){
        $postStr = $_REQUEST;
        
        debugLog($postStr,'==>聚合支付获取微信支付异步回调信息1<==');
        
        if (!empty($postStr)) {
            // $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
            $postObj = $postStr;
            $pay = new UnifyPay();
            $out_trade_no = $postStr['r2_OrderNo'];
            debugLog($out_trade_no,'==>聚合支付获取微信支付异步回调信息-订单号3<==');
            if(strstr($out_trade_no, 'DH')){
                $out_trade_no = $out_trade_no;
                $key = 'integral_pay_' . $out_trade_no;
                $redis = $this->connectRedis();
                $integral_order = $redis->get($key);
                $integral_order_arr = json_decode($integral_order, true);
                $order_from['order_from'] = $integral_order_arr['type']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
            }else if(strstr($out_trade_no, 'MD')){
                $out_trade_no = $out_trade_no;
                $key = 'store_pay_' . $out_trade_no;
                $redis = $this->connectRedis();
                $store_order = $redis->get($key);
                $store_order_arr = json_decode($store_order, true);
                $order_from = [];
                $order_from['order_from'] = $store_order_arr['order_from']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
            }else if(strstr($out_trade_no, 'eos')){
                $order_eos = new VslEosOrderPayMentModel();
                $order_from = $order_eos->getInfo(['out_trade_no'=>"{$out_trade_no}",'type'=>1],'pay_from,website_id');
            }elseif(strstr($out_trade_no, 'QD')){
                $order = new VslChannelOrderPaymentModel();
                $order_from = $order->getInfo(['out_trade_no'=>"{$out_trade_no}",'type'=>1],'pay_from,website_id');
            } else{
                $order = new VslOrderPaymentModel();
                $order_from = $order->getInfo(['out_trade_no'=>$out_trade_no],'pay_from, website_id');
            }
            $hmac = $postObj['hmac'];
            unset($postObj['s']);
            unset($postObj['hmac']);
            $postObj['ra_PayTime'] = urldecode($postObj['ra_PayTime']);
            $postObj['rb_DealTime'] = urldecode($postObj['rb_DealTime']);
        
            // 支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
            if($order_from){ //聚合支付只有一种验签方式
                $check_sign = $pay->checkJoinPaySign($postObj, $hmac,$order_from['website_id']);
                
            } else{
                $order_recharge = new VslMemberRechargeModel();
                $website_id = $order_recharge->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
                $check_sign = $pay->checkJoinPaySign($postObj, $hmac,$website_id);
            }
            debugLog($check_sign,'==>聚合支付获取微信支付异步回调信息4-验签结果<==');
            if ($postObj['r6_Status'] == 100 && $check_sign == 1) {
                if(strstr($out_trade_no, 'eos')){
                    $block = new Block();
                    $block->eosPayBack("{$out_trade_no}");
                    echo 'success';
                }else{
                    $pay->onlinePay("{$out_trade_no}", 1, '', 1);
                    echo 'success';
                }
            }
        }
    }
    /**
     * 微信支付异步回调（只有异步回调对订单进行处理）
     */
    public function wchatUrlBack()
    {
            $postStr = file_get_contents('php://input');
            debugLog($postStr, '');
//            $postStr = '<xml><appid><![CDATA[wx6336862822972aeb]]></appid><attach><![CDATA[1]]></attach><bank_type><![CDATA[OTHERS]]></bank_type><cash_fee><![CDATA[1]]></cash_fee><fee_type><![CDATA[CNY]]></fee_type><is_subscribe><![CDATA[N]]></is_subscribe><mch_id><![CDATA[1504681951]]></mch_id><nonce_str><![CDATA[79u9penbmf7ss7428wkz6vq1cur1yeat]]></nonce_str><openid><![CDATA[oKYMF5nDhWsbt4Y7Kl3SgZCHFHUA]]></openid><out_trade_no><![CDATA[158942813971831000]]></out_trade_no><result_code><![CDATA[SUCCESS]]></result_code><return_code><![CDATA[SUCCESS]]></return_code><sign><![CDATA[BCF4ECFFBBFB923558F256E49683D89D]]></sign><time_end><![CDATA[20200514114928]]></time_end><total_fee>1</total_fee><trade_type><![CDATA[JSAPI]]></trade_type><transaction_id><![CDATA[4200000561202005147551916499]]></transaction_id></xml>';
            if (! empty($postStr)) {
                $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                $pay = new UnifyPay();
                if(strstr($postObj->out_trade_no, 'DH')){
                    $out_trade_no = $postObj->out_trade_no;
                    $key = 'integral_pay_' . $out_trade_no;
                    $redis = connectRedis();
                    $integral_order = $redis->get($key);
                    $integral_order_arr = json_decode($integral_order, true);
                    $order_from['order_from'] = $integral_order_arr['type']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
                }else if(strstr($postObj->out_trade_no, 'MD')){
                    $out_trade_no = $postObj->out_trade_no;
                    $key = 'store_pay_' . $out_trade_no;
                    $redis = connectRedis();
                    $store_order = $redis->get($key);
                    $store_order_arr = json_decode($store_order, true);
                    $order_from = [];
                    $order_from['order_from'] = $store_order_arr['order_from']; //支付来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
                }else if(strstr($postObj->out_trade_no, 'eos')){
                    $order_eos = new VslEosOrderPayMentModel();
                    $order_from = $order_eos->getInfo(['out_trade_no'=>"{$postObj->out_trade_no}",'type'=>1],'pay_from,website_id');
                }elseif(strstr($postObj->out_trade_no, 'QD')){
                    $order = new VslChannelOrderPaymentModel();
                    $order_from = $order->getInfo(['out_trade_no'=>"{$postObj->out_trade_no}",'type'=>1],'pay_from,website_id');
                } elseif(strstr($postObj->out_trade_no, 'TC')){
                    $order = new VslAppointOrderModel();
                    $order_from = $order->getInfo(['out_trade_no'=>"{$postObj->out_trade_no}",'payment_type'=>1],'pay_from,website_id');
                } else{
                    $order = new VslOrderPaymentModel();
                    $order_from = $order->getInfo(['out_trade_no'=>$postObj->out_trade_no],'pay_from, website_id');
                }
                // 支付来源,1 微信浏览器,2 手机浏览器,3 PC，4 ios,5 Android,6 小程序
                if($order_from){
                    if($order_from['pay_from']==4 || $order_from['pay_from']==5){
                        $check_sign = $pay->checkSignApp($postObj, $postObj->sign,$order_from['website_id']);
                    } elseif($order_from['pay_from'] == 6) { //by sgw
                        $check_sign = $pay->checkSignMp($postObj, $postObj->sign,$order_from['website_id']);
                    } else{
                        $check_sign = $pay->checkSign($postObj, $postObj->sign,$order_from['website_id']);
                    }
                } else {
                    $order_recharge = new VslMemberRechargeModel();
                    $website_id = $order_recharge->getInfo(['out_trade_no'=>$postObj->out_trade_no],'website_id')['website_id'];
                    $check_sign = $pay->checkSign($postObj, $postObj->sign,$website_id);
                }
                $return_wchat_data = [];
                if ($postObj->result_code == 'SUCCESS' && $check_sign == 1) {
                    $return_wchat_data['return_code'] = 'SUCCESS';
                    $return_wchat_data['return_msg'] = 'OK';
                    if(strstr($postObj->out_trade_no, 'eos')){
                        $block = new Block();
                        $block->eosPayBack("{$postObj->out_trade_no}");
                    }else{
                        $pay->onlinePay("{$postObj->out_trade_no}", 1, '');
                    }
                }else{
                    $return_wchat_data['return_code'] = 'FAIL';
                    $return_wchat_data['return_msg'] = $postObj->err_code;
                }
                if($return_wchat_data){
                    $xml = '';
                    $xml = $this->toXml($return_wchat_data);
                    echo $xml;
                }
            }
    }
    
    function toXml($returnData=[])
    {
        $xml = "<xml>";
        foreach($returnData as $key=>$val)
        {
            if(is_numeric($val)){
                $xml.="<".$key.">".$val."</".$key.">";
            }else{
                $xml.="<".$key."><![CDATA[".$val."]]></".$key.">";   
            }
        }
        $xml.="</xml>";
        return $xml;
    }
    /**
     * 支付宝支付异步回调
     */
    public function aliUrlBack()
    {
        $pay = new UnifyPay();
        $order = new VslOrderModel();
        $out_trade_no = request()->post('out_trade_no', '');
        if(strstr($out_trade_no, 'QD')){
            $channel_order = new VslChannelOrderModel();
            $website_id = $channel_order->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }else{
            $website_id = $order->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }
        if(!$website_id){
            $order_recharge = new VslMemberRechargeModel();
            $website_id = $order_recharge->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }
        $verify_result = $pay->alipayNotify($_POST,$website_id);
        if ($verify_result) { // 验证成功
            $out_trade_no = request()->post('out_trade_no', '');
            // 支付宝交易号
            $trade_no = request()->post('trade_no', '');
            // 交易状态
            $trade_status = request()->post('trade_status', '');
            if ($trade_status == 'TRADE_SUCCESS' || $trade_status == 'TRADE_FINISHED') {
                if(strstr($out_trade_no, 'eos')){
                    $block = new Block();
                    $block->eosPayBack("{$out_trade_no}");
                }else{
                    $pay->onlinePay($out_trade_no, 2, $trade_no);
                }
            }
            echo "success";
        } else {
            // 验证失败
            echo "fail";
        }
    }

    /**
     * 根据流水号查询订单编号，
     *
     * @param unknown $out_trade_no            
     * @return string
     */
    public function getOrderNoByOutTradeNo($out_trade_no)
    {
        $order_no = "";
        $order = new Order();
        $list = $order->getOrderNoByOutTradeNo($out_trade_no);
        if (! empty($list)) {
            foreach ($list as $v) {
                $order_no .= $v['order_no'];
            }
        }
        return $order_no;
    }

    /**
     * 根据外部交易号查询订单状态，订单关闭状态下是不能继续支付的
     *
     * @param unknown $out_trade_no            
     * @return number
     */
    public function getOrderStatusByOutTradeNo($out_trade_no)
    {
        $order = new Order();
        $order_status = $order->getOrderStatusByOutTradeNo($out_trade_no);
        if (!empty($order_status)) {
            return $order_status['order_status'];
        }
        return 0;
    }
    /**
     * 根据外部交易号查询渠道商订单状态，订单关闭状态下是不能继续支付的
     *
     * @param unknown $out_trade_no
     * @return number
     */
    public function getChannelOrderStatusByOutTradeNo($out_trade_no)
    {
        if($this->is_channel){
            $channel_order = new Channel();
            $order_status = $channel_order->getOrderStatusByOutTradeNo($out_trade_no);
            if (! empty($order_status)) {
                return $order_status['order_status'];
            }
        }
        return 0;
    }
    /**
     * 通联支付回调
     *
     */
    public function tlUrlBack(){
        $pay = new UnifyPay();
        $order = new VslOrderModel();
        $out_trade_no = $_POST['outtrxid'];
        if(strstr($out_trade_no, 'QD')){
            $channel_order = new VslChannelOrderModel();
            $website_id = $channel_order->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }else{
            $website_id = $order->getInfo(['out_trade_no'=>$out_trade_no],'website_id')['website_id'];
        }
        if(!$website_id){
            // $order_recharge = new VslMemberRechargeModel();
            $website_id = $order->getInfo(['out_trade_no_presell'=>$out_trade_no],'website_id')['website_id'];
        }
        $verify_result = $pay->tlpayNotifys($_POST,$website_id);
        if ($verify_result) { // 验证成功
            // 交易状态
            if ($_POST['trxstatus'] == '0000') {
                if(strstr($out_trade_no, 'eos')){
                    $block = new Block();
                    $block->eosPayBack("{$out_trade_no}");
                }else{
                    $trade_no =$_POST['trxid'];
                    $pay->onlinePay($out_trade_no, 3, $trade_no);
                }
            }
            echo "success";
        } else {
            // 验证失败
            echo "fail";
        }
    }
    /**
     * eth和eos提现回调
     *
     */
    public function withdrawUrlBack(){
        $appId = request()->post('appId',0);
        $website = new WebSiteModel();
        $public_key = $website->getInfo(['website_id'=>$appId])['public_key'];
        if($_POST['sign']){
            $result = PublicDecrypt($_POST['sign'],$public_key);
            if($result){
                $block = new Block();
                $result  = explodeString($result);
                $trade_no = $result['outTradeNo'];
                if($_POST['status']==1){
                    $block->withdrawNotify($trade_no,1,$result['msg']);
                }else if($_POST['status']==2){
                    $block->withdrawNotify($trade_no,2,$result['msg']);
                }
            }
        }
    }
    /**
     * eth和eos退款回调
     *
     */
    public function refundUrlBack(){
        $appId = $_POST['appId'];
        $website = new WebSiteModel();
        $public_key = $website->getInfo(['website_id'=>$appId])['public_key'];
        if($_POST['sign']){
            $result = PublicDecrypt($_POST['sign'],$public_key);
            if($result){
                $block = new Block();
                $outTradeNo = explodeString($result)['outTradeNo'];
                if($_POST['status']==1){
                    $block->refundNotify($outTradeNo,1);
                }else{
                    $block->refundNotify($outTradeNo,2);
                }
            }
        }
    }
    /**
     * eth和eos兑换成积分支付回调
     *
     */
    public function payUrlBacks(){
        $appId = $_POST['appId'];
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id'=>$appId]);
        $key = $website_info['common_key'];
        $public_key = $website_info['public_key'];
        $pay = new UnifyPay();
        $result = $pay->blockChainNotify($_POST,$public_key,$key,$_POST['sign']);
        if($result){
            $block = new Block();
            $trade_no = $_POST['outTradeNO'];
            if($_POST['status']==1){
                $block->payNotifys($trade_no,1,$_POST['msg']);
            }else if($_POST['status']==2){
                $block->payNotifys($trade_no,2,$_POST['msg']);
            }
        }
    }

    /**
     * eth和eos订单支付回调
     *
     */
    public function ethPayUrlBack(){
        $appId = $_POST['appId'];
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id'=>$appId]);
        $key = $website_info['common_key'];
        $public_key = $website_info['public_key'];
        $pay = new UnifyPay();
        $result = $pay->blockChainNotify($_POST,$public_key,$key,$_POST['sign']);
        if($result){
            $out_trade_no = $_POST['outTradeNO'];
            $block = new Block();
            if($_POST['status']==1){
                $pay->onlinePay($out_trade_no, 16, '');
                $block->payNotify($out_trade_no,1);
            }else if($_POST['status']==2){
                $order = new VslOrderModel();
                $order->save(['order_status'=>5],['out_trade_no'=>$out_trade_no]);
                $block->payNotify($out_trade_no,2,$_POST['msg']);
            }
        }
    }
    public function eosPayUrlBack(){
        $appId = $_POST['appId'];
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id'=>$appId]);
        $key = $website_info['common_key'];
        $public_key = $website_info['public_key'];
        $pay = new UnifyPay();
        $result = $pay->blockChainNotify($_POST,$public_key,$key,$_POST['sign']);
        if($result){
            $out_trade_no = $_POST['outTradeNO'];
            $block = new Block();
            if($_POST['status']==1){
                $pay->onlinePay($out_trade_no, 17, '');
                $block->payNotify($out_trade_no,1);
            }else if($_POST['status']==2){
                $block->payNotify($out_trade_no,2,$_POST['msg']);
            }
        }
    }

    /**
     * eth上链回调
     *
     */
    public function ethChainUrlBack(){
        $appId = $_POST['appId'];
        $website = new WebSiteModel();
        $public_key = $website->getInfo(['website_id'=>$appId])['public_key'];
        if($_POST['sign']){
            $result = PublicDecrypt($_POST['sign'],$public_key);
            if($result){
                $anti = new AntiCounterfeiting();
                $outTradeNo = explodeString($result)['ipfsHash'];
                $chain_code = explodeString($result)['sourceCode'];
                if($_POST['status']==1){
                    $anti->chainNotify($outTradeNo,$chain_code,1);
                }else{
                    $anti->chainNotify($outTradeNo,$chain_code,2);
                }
            }
        }
    }
}