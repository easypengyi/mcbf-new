<?php
namespace data\service\Pay;
/* *
 * 功能：alipay.fund.trans.uni.transfer(单笔转账到支付宝账户接口)接口业务参数封装
 * 版本：2.0
 * 修改日期：2017-05-01
 * 说明：
 * 以下代码只是为了方便商户测试而提供的样例代码，商户可以根据自己网站的需要，按照技术文档编写,并非一定要使用该代码。
 */


class AlipayFundTransUniTransferBuilder
{

    // 商户转账唯一订单号。发起转账来源方定义的转账单据ID，用于将转账回执通知给来源方。 
    private $out_biz_no;

    // 收款方账户信息
    private $payee_info;

    // 转账金额
    private $trans_amount;
    
    // 转账备注
    private $remark;

    private $bizContentarr = array();

    private $bizContent = NULL;

    public function getBizContent()
    {
        if(!empty($this->bizContentarr)){
            $this->bizContent = json_encode($this->bizContentarr,JSON_UNESCAPED_UNICODE);
        }
        return $this->bizContent;
    }

    public function __construct()
    {
        $this->bizContentarr['product_code'] = "TRANS_ACCOUNT_NO_PWD";
        $this->bizContentarr['biz_scene'] = "DIRECT_TRANSFER";
    }

    public function AlipayTradeWapPayContentBuilder()
    {
        $this->__construct();
    }

    public function getOutBizNo()
    {
        return $this->out_biz_no;
    }

    public function setOutBizNo($outbizno)
    {
        $this->out_biz_no = $outbizno;
        $this->bizContentarr['out_biz_no'] = $outbizno;
    }

    public function getPayeeInfo()
    {
        return $this->payee_info;
    }

    public function setPayeeInfo($identity, $identity_type, $real_name)
    {
        $payeeInfo = ['identity' => $identity, 'identity_type' => $identity_type, 'name' => $real_name];
        $this->payee_info = $payeeInfo;
        $this->bizContentarr['payee_info'] = $payeeInfo;
    }

    public function setTransAmount($amount)
    {
        $this->trans_amount = $amount;
        $this->bizContentarr['trans_amount'] = $amount;
    }

    public function getTransAmount()
    {
        return $this->trans_amount;
    }

    public function setRemark($remark)
    {
        $this->remark = $remark;
        $this->bizContentarr['remark'] = $remark;
    }

    public function getRemark()
    {
        return $this->remark;
    }

}

?>