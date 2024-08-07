<?php

namespace data\service\Member;

/**
 * 会员流水账户
 */

use addons\channel\model\VslChannelOrderModel;
use addons\membercard\model\VslMembercardUserModel;
use data\model\VslMemberBankAccountModel;
use data\model\VslOrderModel;
use data\service\BaseService;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberAccountModel;
use data\model\VslPointConfigModel;
use data\service\Config;
use data\service\Pay\AliPay;
use data\service\Pay\tlPay;
use data\service\Pay\WeiXinPay;
use data\model\VslMemberBalanceWithdrawModel;
use data\service\ShopAccount;
use think\Db;
use data\service\Pay\Joinpay;
use trendClass;

class MemberAccount extends BaseService {

    function __construct() {
        parent::__construct();
    }

    /**
     * 添加会员消费
     * @param unknown $shop_id
     * @param unknown $uid
     * @param unknown $consum
     */
    public function addMmemberConsum($shop_id, $uid, $consum) {
        $account_statistics = new VslMemberAccountModel();
        $acount_info = $account_statistics->getInfo(['uid' => $uid, 'shop_id' => $shop_id, 'website_id' => $this->website_id], 'member_cunsum');
        $data = array(
            'member_cunsum' => $acount_info['member_cunsum'] + $consum
        );
        $retval = $account_statistics->save($data, ['uid' => $uid, 'shop_id' => $shop_id, 'website_id' => $this->website_id]);
        return $retval;
    }

    /* --------------------------------------------------------  提现和充值过程  ------------------------------------------------- */

    /**
     * 添加账户流水（余额提现，充值，后台调整）
     * @param int $account_type 1:积分，2:余额
     */
    public function addMemberAccountData($account_type, $uid, $sign, $number, $from_type, $data_id, $text = '', $is_examine = '', $make_money = '', $wx_openid = '', $withdraw_no = '', $type = '', $account_number = '',$service_charge=0,$bank_account_id='',$charge=0, $real_name = '') {

        $member_account_record = new VslMemberAccountRecordsModel();
        //前期检测
        $member_account = new VslMemberAccountModel();
        $all_info = $member_account->getInfo(['uid' => $uid], '*');
        $member_all_point = $all_info['point'];
        $member_all_beautiful_point = $all_info['beautiful_point'];
        $member_all_balance = $all_info['balance'];
        $freezing_balance = $all_info['freezing_balance'];
        //美丽分
        if ($account_type == 3) {
            if($from_type == 1){
                $data_member = array(
                    'beautiful_point' => $member_all_beautiful_point - abs($number)
                );
                $order = new VslOrderModel();
                $shop_id = $order->getInfo(['order_id' => $data_id], 'shop_id')['shop_id'];
                $res = $member_account->save($data_member, ['uid' => $uid, 'website_id' => $all_info['website_id']]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Op' . getSerialNo(),
                    'account_type' => $account_type,
                    'shop_id' => $shop_id ? $shop_id : 0,
                    'uid' => $uid,
                    'sign' => 1,
                    'number' => (-1) * abs($number),
                    'balance'=> $member_all_balance,
                    'point'=> $data_member['beautiful_point'],
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $all_info['website_id']
                );

                $member_account_record->save($data);
                return $res;
            }else{
                //当前可用积分
                $data_member['beautiful_point'] = $member_all_beautiful_point + $number;
                if($data_member['beautiful_point'] < 0){
                    $data_member['beautiful_point'] = 0;
                }
                if ($number > 0) {
                    //计算会员累计积分
                    // $data_member['member_sum_point'] = $member_all_point + $number;
                }
                $res = $member_account->save($data_member, ['uid' => $uid, 'website_id' =>  $all_info['website_id']]);
                $data = array(
                    'records_no' => getSerialNo(),
                    'account_type' => $account_type,
                    'uid' => $uid,
                    'sign' => $sign,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'point'=>$member_all_beautiful_point + $number,
                    'balance'=>$member_all_balance,
                    'website_id' =>  $all_info['website_id']
                );
                $member_account_record->save($data);
                return $res;
            }
        }
        //更新对应会员账户
        if ($account_type == 1) {
            //当前可用积分
            $data_member['point'] = $member_all_point + $number;
            if($data_member['point'] < 0){
                $data_member['point'] = 0;
            }
            if ($number > 0) {
                //计算会员累计积分
                $data_member['member_sum_point'] = $member_all_point + $number;
            }
            $res = $member_account->save($data_member, ['uid' => $uid, 'website_id' =>  $all_info['website_id']]);
            $data = array(
                'records_no' => getSerialNo(),
                'account_type' => $account_type,
                'uid' => $uid,
                'sign' => $sign,
                'number' => $number,
                'from_type' => $from_type,
                'data_id' => $data_id,
                'text' => $text,
                'create_time' => time(),
                'point'=>$member_all_point + $number,
                'balance'=>$member_all_balance,
                'website_id' =>  $all_info['website_id']
            );
            $member_account_record->save($data);
            return $res;
        }
        if ($account_type == 2) {
            if ($from_type == 8) {//提现
                $email_params['shop_id'] = $params['shop_id'] = 0;
                $email_params['take_out_money'] = $params['takeoutmoney'] = abs($number);
                $email_params['uid'] = $params['uid'] = $uid;
                $email_params['website_id'] = $params['website_id'] = $this->website_id;
                $email_params['template_code'] = 'withdrawal_success';
                $email_params['notify_type'] = 'user';
                $params['withdraw_no'] = $withdraw_no;
                if ($from_type == 8 && $is_examine == 1 && $make_money == 1 && $type == 2) {//提现自动审核并且自动打款(微信提现)
                    //会员账户改变
                    $data_member = array(
                        'freezing_balance' => $freezing_balance + abs($service_charge)+abs($charge),
                        'balance' => $member_all_balance - abs($service_charge)-abs($charge)
                    );
                    $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                    $weixin_pay = new WeiXinPay();
                    $retval = $weixin_pay->EnterprisePayment($wx_openid, $withdraw_no, '', abs($service_charge), '余额微信提现',$this->website_id);
                    if ($retval['is_success'] == 1) {//自动打款成功
                        runhook('Notify', 'withdrawalSuccessBySms', $params);
                        runhook('Notify', 'emailSend', $email_params);
                        runhook('Notify', 'successfulWithdrawalsByTemplate', $params);
                        runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);
                        return $this->addMemberAccountRecords($number, $data_id, $uid, 3, '余额微信提现，打款成功');
                    } else {//自动打款失败
                        $this->addMemberAccountRecords($number, $data_id, $uid, 5, '余额微信提现，打款失败',$retval['msg']);
                        return -9000;
                    }
                }elseif ($from_type == 8 && $is_examine == 1 && $make_money == 1 && $type == 3) {//提现自动审核并且自动打款(支付宝提现)
                    //会员账户改变
                    $data_member = array(
                        'freezing_balance' => $freezing_balance + abs($service_charge)+abs($charge),
                        'balance' => $member_all_balance - abs($service_charge)-abs($charge)
                    );
                    $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                    $alipay_pay = new AliPay();
                    $retval = $alipay_pay->aliPayTransferNew($withdraw_no, $account_number, abs($service_charge), $real_name);
                    if ($retval['is_success'] == 1) {
                        runhook('Notify', 'withdrawalSuccessBySms', $params);
                        runhook('Notify', 'emailSend', $email_params);
                        runhook('Notify', 'successfulWithdrawalsByTemplate', $params);
                        runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);
                        return $this->addMemberAccountRecords($number, $data_id, $uid, 2, '余额支付宝提现，打款成功');
                    } else {//自动打款失败
                        $this->addMemberAccountRecords($number, $data_id, $uid, 5, '余额支付宝提现，打款失败',$retval['msg']);
                        return -9000;
                    }
                }elseif ($from_type == 8 && $is_examine == 1 && $make_money == 1 && $type == 1) {//提现自动审核并且自动打款(银行卡提现)
                    //会员账户改变
                    $data_member = array(
                        'freezing_balance' => $freezing_balance + abs($service_charge)+abs($charge),
                        'balance' => $member_all_balance - abs($service_charge)-abs($charge)
                    );
                    $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                    //此处判断是汇聚支付 还是通联支付
                    $webConfig = new Config();
                    $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                    if($joinPay['value']['joinpaytw_is_use'] == 1){
                        $joinpay = new Joinpay();
                        $retval = $joinpay->jpWithdraw($withdraw_no,$uid,$bank_id,abs($balance_info['service_charge']),0,1);
                    }else{
                        $tlpay_pay = new tlPay();
                        $retval = $tlpay_pay->tlWithdraw($withdraw_no,$uid,$bank_account_id,abs($service_charge));
                    }

                    if ($retval['is_success'] == 1) {
                        runhook('Notify', 'withdrawalSuccessBySms', $params);
                        runhook('Notify', 'emailSend', $email_params);
                        runhook('Notify', 'successfulWithdrawalsByTemplate', $params);
                        runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);
                        return $this->addMemberAccountRecords($number, $data_id, $uid, 6, '余额银行卡提现，打款成功');
                    } else {//自动打款失败
                        $this->addMemberAccountRecords($number, $data_id, $uid, 5, '余额银行卡提现，打款失败',$retval['msg']);
                        return -9000;
                    }
                } elseif($from_type == 8 && $is_examine == 1 && $make_money == 2 && $type == 1) {//银行卡提现审核通过，待打款
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 0, '余额银行卡提现审核通过，待打款');
                } elseif ($from_type == 8 && $is_examine == 1 && $make_money == 2 && $type == 2) {//自动审核待打款
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 0, '余额微信提现审核通过，待打款');
                } elseif ($from_type == 8 && $is_examine == 1 && $make_money == 2 && $type == 3) {//自动审核待打款
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 0, '余额支付宝提现审核通过，待打款');
                } elseif ($from_type == 8 && $is_examine == 2 && $type == 2) {//手动审核,微信提现
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 1, '余额微信提现待审核');
                } elseif ($from_type == 8 && $is_examine == 2 && $type == 3) {//手动审核,支付宝提现
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 1, '余额支付宝提现待审核');
                } elseif ($from_type == 8 && $is_examine == 2  && $type == 1) {//手动审核,银行卡提现
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 1, '余额银行卡提现待审核');
                }elseif ($from_type == 8 && $is_examine == 2  && $type == 4) {//手动审核,银行卡提现
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 1, '余额银行卡提现待审核');
                }elseif($from_type == 8 && $is_examine == 1 && $make_money == 2 && $type == 4) {//银行卡提现审核通过，待打款
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 0, '余额银行卡提现审核通过，待打款');
                }elseif($from_type == 8 && $is_examine == 1 && $make_money == 1 && $type == 4) {//银行卡提现审核通过，待打款
                    return $this->addMemberAccountRecords($number, $data_id, $uid, 0, '余额银行卡提现审核通过，待打款');
                }
            } elseif ($from_type == 1 || $from_type == 27) {//商城订单 27渠道商订单
                $status = 21;
                if($from_type == 27){
                    $status = 27;
                }
                return $this->addMemberAccountRecords($number, $data_id, $uid, $status, $text);
            } elseif ($from_type == 4) {//充值
                return $this->addMemberAccountRecords($number, $data_id, $uid, 20, $text,'',$all_info['website_id'],$type);
            } elseif ($from_type == 10) {//调整
                return $this->addMemberAccountRecords($number, $data_id, $uid, 19, $text);
            } elseif ($from_type == 29) {
                return $this->addMemberAccountRecords($number, $data_id, $uid, 29, $text, '', $all_info['website_id']);//海报奖励余额
            }elseif ($from_type == 30){
                return $this->addMemberAccountRecords($number, $data_id, $uid, 30, $text, '', $all_info['website_id']);//积分兑换余额
            }elseif ($from_type == 50){
                return $this->addMemberAccountRecords($number, $data_id, $uid, 50, $text, '', $all_info['website_id'],0,$charge);//余额转账
            }elseif ($from_type == 51){
                return $this->addMemberAccountRecords($number, $data_id, $uid, 51, $text, '', $all_info['website_id'],0,$charge);//余额兑换
            }elseif ($from_type == 52){
                return $this->addMemberAccountRecords($number, $data_id, $uid, 51, $text, '', $all_info['website_id'],0,$charge);//积分兑换
            }elseif ($from_type == 64) {//购买会员卡
                return $this->addMemberAccountRecords($number, $data_id, $uid, 58, $text);
            }elseif ($from_type == 65) {//会员卡充值
                return $this->addMemberAccountRecords($number, $data_id, $uid, 59, $text);
            }
        }
        if($account_type == 5) {//货款
            if($from_type == 53) {//货款充值
                return $this->addMemberAccountRecords($number, $data_id, $uid, 55, $text,'',$all_info['website_id'],$type);
            }elseif ($from_type == 27) {//渠道商订单货款支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 56, $text,'',$all_info['website_id'],$type);
            }
        }
        if($account_type == 6) {//会员卡
            if($from_type == 63) {//后台调整会员卡余额
                return $this->addMemberAccountRecords($number, $data_id, $uid, 57, $text);
            }elseif ($from_type == 66) {//会员卡充值，余额支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 60, $text);
            }elseif ($from_type == 67) {//会员卡充值，微信支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 61, $text);
            }elseif ($from_type == 68) {//会员卡充值，支付宝支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 62, $text);
            }elseif ($from_type == 69) {//会员卡充值，银行卡支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 63, $text);
            }elseif ($from_type == 73) {//会员卡充值，eth支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 64, $text);
            }elseif ($from_type == 71) {//会员卡充值，eos支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 65, $text);
            }elseif ($from_type == 72) {//会员卡充值，GlobePay支付
                return $this->addMemberAccountRecords($number, $data_id, $uid, 66, $text);
            }elseif ($from_type == 75) {//会员卡,订单抵扣
                return $this->addMemberAccountRecords($number, $data_id, $uid, 67, $text);
            }elseif ($from_type == 76) {//订单关闭，返还会员卡余额
                return $this->addMemberAccountRecords($number, $data_id, $uid, 68, $text);
            }
        }
        // TODO Auto-generated method stub
    }

    /**
     * 添加账户流水（余额提现审核通过，自动打款）
     * @param unknown $shop_id
     */
    public function addAuditMemberAccountData($data_id, $uid, $number, $wx_openid, $withdraw_no, $type, $account_number = '',$service_charge=0, $real_name = '') {
        $email_params['shop_id'] = $params['shop_id'] = 0;
        $email_params['take_out_money'] = $params['takeoutmoney'] = abs($number);
        $email_params['uid'] = $params['uid'] = $uid;
        $email_params['website_id'] = $params['website_id'] = $this->website_id;
        $email_params['template_code'] = 'withdrawal_success';
        $email_params['notify_type'] = 'user';
        $params['withdraw_no'] = $withdraw_no;
        if($type==1){//提现审核通过，自动打款(银行卡提现)
            $bank = new VslMemberBankAccountModel();
            $bank_id = $bank->getInfo(['account_number'=>$account_number,'uid'=>$uid],'id')['id'];
            //此处判断是汇聚支付 还是通联支付
            $webConfig = new Config();
            $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
            if($joinPay['value']['joinpaytw_is_use'] == 1){
                $joinpay = new Joinpay();
                $retval = $joinpay->jpWithdraw($withdraw_no,$uid,$bank_id,abs($balance_info['service_charge']));
            }else{
                $tlpay_pay = new tlPay();
                $retval = $tlpay_pay->tlWithdraw($withdraw_no,$uid,$bank_id,abs($service_charge));
            }

            if ($retval['is_success'] == 1) {
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                runhook('Notify', 'emailSend', $email_params);
                runhook('Notify', 'successfulWithdrawalsByTemplate', $params);
                runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);
                return $this->addMemberAccountRecords($number, $data_id, $uid, 6, '余额银行卡提现审核通过,打款成功');
            } else {//自动打款失败
                if ($this->addMemberAccountRecords($number, $data_id, $uid, 5, '余额银行卡提现审核通过，打款失败',$retval['msg'])) {
                    return $retval;
                }
                return -1;
            }
        }
        if($type==4){//提现审核通过，需要手动打款(银行卡提现)
            return $this->addMemberAccountRecords($number, $data_id, $uid, 7, '余额银行卡提现审核通过待打款');
        }
        if($type==2) {//提现审核通过，自动打款(微信提现)
            $weixin_pay = new WeiXinPay();
            $retval = $weixin_pay->EnterprisePayment($wx_openid, $withdraw_no, '', abs($service_charge), '余额微信提现审核通过',$this->website_id);
            if ($retval['is_success'] == 1) {//打款成功
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                runhook('Notify', 'emailSend', $email_params);
                runhook('Notify', 'successfulWithdrawalsByTemplate', $params);
                runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);
                return $this->addMemberAccountRecords($number, $data_id, $uid, 3, '余额微信提现审核通过，打款成功');
            } else {//打款失败
                return $this->addMemberAccountRecords($number, $data_id, $uid, 5, '余额微信提现审核通过，打款失败',$retval['msg']);
            }
        }
        if($type==3) {//提现审核通过，自动打款(支付宝提现)
            $alipay_pay = new AliPay();
            $retval = $alipay_pay->aliPayTransferNew($withdraw_no, $account_number, abs($service_charge), $real_name);
            if ($retval['is_success'] == 1) {//打款成功
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                runhook('Notify', 'emailSend', $email_params);
                runhook('Notify', 'successfulWithdrawalsByTemplate', $params);
                runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);
                return $this->addMemberAccountRecords($number, $data_id, $uid, 2, '余额支付宝提现审核通过，打款成功');
            } else {//打款失败
                return $this->addMemberAccountRecords($number, $data_id, $uid, 5, '余额支付宝提现审核通过，打款失败',$retval['msg']);
            }
        }
        // TODO Auto-generated method stub
    }
    /**
     * 添加账户流水（余额提现审核通过自动打款，拒绝打款，审核不通过，充值）
     * @param unknown $shop_id
     */
    public function addMemberAccountRecords($number, $data_id, $uid, $status, $text,$msg='', $website_id = 0,$type=0,$charge=0) {
        if(!$this->website_id){
            $this->website_id =  $website_id;
        }

        $member_account = new VslMemberAccountModel();
        $all_info = $member_account->getInfo(['uid' => $uid, 'website_id' => $this->website_id], '*');
        $member_all_balance = $all_info['balance'];
        $withdraw_balance = $all_info['withdraw'];
        $charge_balance = $all_info['charge'];
        $freezing_balance = $all_info['freezing_balance'];
        $proceeds = $all_info['proceeds'];//货款
        $member_account_record = new VslMemberAccountRecordsModel();
        $member_account_record->startTrans();
        try {
            if ($status == 6) {//银行卡打款成功
                $from_type = 8;
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                $withdraw_data = array(
                    'status' => 3,
                    'payment_date'=>time(),
                    'modify_date' => time(),
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']), //冻结余额减少
                    'withdraw' =>  $withdraw_balance + abs($number) ,//已提现余额增加
                    'charge'=>$charge_balance +abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                $record_info = $member_account_record->getInfo(['data_id'=>$data_id],'id');
                if($record_info){
                    //修改会员账户流水
                    $update_data = array(
                        'status'=>3,
                        'text' => '余额银行卡提现成功',
                    );
                }else{
                    //添加会员账户流水
                    $data = array(
                        'records_no' => 'Bt' . getSerialNo(),
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'number' => (-1)*abs($number),
                        'charge' => $balance_info['charge'],
                        'from_type' => 8,
                        'data_id' => $data_id,
                        'text' => '余额银行卡提现成功',
                        'balance'=>$member_all_balance,
                        'point'=>$all_info['point'],
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
                // 添加平台的整体资金流水和平台提现流水
                $acount = new ShopAccount();
                //更新平台提现金额
                $acount->updateAccountUserWithdraw($number);//更新平台提现金额
                if(abs($balance_info['charge'])>0){
                    $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($balance_info['charge']), 25, $data_id, '余额银行卡提现成功，提现手续费增加!');
                }
                $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 36, $data_id, '余额银行卡提现成功，平台账户提现金额增加!');
            }
            if ($status == 3) {//微信打款成功
                $from_type = 8;
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                //更新平台提现金额
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                $withdraw_data = array(
                    'status' => 3,
                    'payment_date'=>time(),
                    'modify_date' => time(),
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']), //冻结余额减少
                    'withdraw' => $withdraw_balance + abs($number), //已提现余额增加
                    'charge'=>$charge_balance +abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                $record_info = $member_account_record->getInfo(['data_id'=>$data_id],'id');
                if($record_info){
                    //修改会员账户流水
                    $update_data = array(
                        'status'=>3,
                        'text' => '余额微信提现成功',
                    );
                }else{
                    //添加会员账户流水
                    $data = array(
                        'records_no' => 'Bt' . getSerialNo(),
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'number' => (-1)*abs($number),
                        'charge' => $balance_info['charge'],
                        'from_type' => 8,
                        'data_id' => $data_id,
                        'text' => '余额微信提现成功',
                        'balance'=>$member_all_balance,
                        'point'=>$all_info['point'],
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
                // 添加平台的整体资金流水和平台提现流水
                $acount = new ShopAccount();
                $acount->updateAccountUserWithdraw($number);//更新平台提现金额
                if(abs($balance_info['charge'])>0){
                    $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($balance_info['charge']), 25, $data_id, '余额微信提现成功，提现手续费增加!');
                }
                $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 7, $data_id, '余额微信提现成功，平台账户提现金额增加!');
            }
            if ($status == 2) {//支付宝打款成功
                $from_type = 8;
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                $withdraw_data = array(
                    'status' => 3,
                    'payment_date'=>time(),
                    'modify_date' => time(),
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']), //冻结余额减少
                    'withdraw' =>  $withdraw_balance + abs($number), //已提现余额增加
                    'charge'=>$charge_balance +abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                $record_info = $member_account_record->getInfo(['data_id'=>$data_id],'id');
                if($record_info){
                    //修改会员账户流水
                    $update_data = array(
                        'status'=>3,
                        'text' => '余额支付宝提现成功',
                    );
                }else{
                    //添加会员账户流水
                    $data = array(
                        'records_no' => 'Bt' . getSerialNo(),
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'number' => (-1)*abs($number),
                        'charge' => $balance_info['charge'],
                        'from_type' => 8,
                        'data_id' => $data_id,
                        'text' => '余额支付宝提现成功',
                        'balance'=>$member_all_balance,
                        'point'=>$all_info['point'],
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
                // 添加平台的整体资金流水和平台提现流水
                $acount = new ShopAccount();
                //更新平台提现金额
                $acount->updateAccountUserWithdraw($number);
                if(abs($balance_info['charge'])>0){
                    $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($balance_info['charge']), 25, $data_id, '余额支付宝提现成功，提现手续费增加!');
                }
                $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 8, $data_id, '余额支付宝提现成功，平台账户提现金额增加!');
            }
            if ($status == 5) {//打款失败
                $from_type = 8;
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'charge');
                $withdraw_data = array(
                    'status' => 5,
                    'memo'=>$msg,
                    'modify_date' => time(),
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                $record_info = $member_account_record->getInfo(['data_id'=>$data_id],'id');
                if($record_info){
                    //修改会员账户流水
                    $update_data = array(
                        'status'=>3,
                        'text' => '余额提现打款失败，等待商家重新打款',
                    );
                }else{
                    //添加会员账户流水
                    $data = array(
                        'records_no' => 'Bt' . getSerialNo(),
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'status'=>$status,
                        'number' => (-1)*abs($number),
                        'charge' => $balance_info['charge'],
                        'from_type' => 8,
                        'balance'=>$member_all_balance,
                        'point'=>$all_info['point'],
                        'data_id' => $data_id,
                        'text' => '余额提现打款失败，等待商家重新打款',
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
            }
            if ($status == 1) {//待审核
                $from_type = 8;
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance + abs($balance_info['service_charge'])+abs($balance_info['charge']),
                    'balance' => $member_all_balance - abs($balance_info['service_charge'])-abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bt' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' =>(-1)*abs($number),
                    'from_type' => 8,
                    'data_id' => $data_id,
                    'balance'=>$member_all_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']),
                    'point'=>$all_info['point'],
                    'charge' => $balance_info['charge'],
                    'status' => $status,
                    'text' => '余额提现待审核，会员可用余额减少，冻结余额增加',
                    'create_time' => time(),
                    'website_id' => $this->website_id,
                );
            }
            if ($status == -1){//审核不通过
                $from_type = 8;
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                $withdraw_data = array(
                    'status' => -1,
                    'memo'=>$text,
                    'modify_date' => time(),
                );
                    $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']),
                    'balance' => $member_all_balance + abs($balance_info['service_charge'])+abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //修改会员账户流水
                $update_data = array(
                    'status'=>$status,
                    'msg'=>$text,
                    'text' => '余额审核不通过，会员可用余额增加，冻结余额减少',
                );
                $acount = new ShopAccount();
                // 添加平台的会员提现资金流水和添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '余额提现审核不通过', $number, 9, $data_id, '余额提现申请，平台审核不通过!');
                $params = array(
                    'shop_id' => 0,
                    'uid' => $uid,
                    'money'=>abs($number),
                    'refusal' => $text,
                    'withdraw_no'=>$data_id,
                    'website_id' => $this->website_id,
                    'template_code' => 'withdrawal_fail',
                    'notify_type' => 'user',
                );
                runhook('Notify', 'withdrawalFailBySms', $params);
                runhook('Notify','emailSend',$params);
                runhook('Notify', 'failureWithdrawalsByTemplate', $params);
                runhook('MpMessage', 'failureWithdrawalsByMpTemplate', $params);
            }
            if ($status == 4) {//拒绝打款
                $from_type = 8;
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                $withdraw_data = array(
                    'status' => 4,
                    'memo'=>$text,
                    'modify_date' => time(),
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']),
                    'balance' => $member_all_balance + abs($balance_info['service_charge'])+abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //修改会员账户流水
                $update_data = array(
                    'status' => $status,
                    'msg'=>$text,
                    'text' => '平台拒绝打款，会员可用余额增加，冻结余额减少',
                );
                $acount = new ShopAccount();
                // 添加平台的会员提现资金流水和添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '余额提现拒绝打款', $number, 10, $data_id, '余额提现拒绝打款!');
                $params = array(
                    'shop_id' => 0,
                    'uid' => $uid,
                    'money'=>abs($number),
                    'refusal' => $text,
                    'withdraw_no'=>$data_id,
                    'website_id' => $this->website_id,
                    'template_code' => 'withdrawal_fail',
                    'notify_type' => 'user',
                );
                runhook('Notify', 'withdrawalFailBySms', $params);
                runhook('Notify','emailSend',$params);
                runhook('Notify', 'failureWithdrawalsByTemplate', $params);
                runhook('MpMessage', 'failureWithdrawalsByMpTemplate', $params);
            }
            if ($status == 0) {//自动审核，待打款
                $from_type = 8;
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'service_charge,charge');
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance + abs($balance_info['service_charge'])+abs($balance_info['charge']),
                    'balance' => $member_all_balance - abs($balance_info['service_charge'])-abs($balance_info['charge'])
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bt' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => (-1)*abs($number),
                    'balance'=>$member_all_balance - abs($balance_info['service_charge'])-abs($balance_info['charge']),
                    'point'=>$all_info['point'],
                    'charge' => $balance_info['charge'],
                    'from_type' => 8,
                    'data_id' => $data_id,
                    'text' => '余额提现待打款，会员可用余额减少，冻结余额增加',
                    'create_time' => time(),
                    'status' => 2,
                    'website_id' => $this->website_id
                );
            }
            if ($status == 7) {//手动审核，待打款
                $from_type = 8;
                //修改会员账户流水
                $update_data = array(
                    'status' => 2,
                    'text' => '余额提现手动审核通过待打款',
                );
            }
            if ($status == 20) {//余额充值成功
                $from_type = 4;
                //会员账户改变
                $data_member = array(
                    'balance' => $member_all_balance+$number
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 4,
                    'balance'=> $member_all_balance+$number,
                    'point'=>$all_info['point'],
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                if($type==1){
                    $acount->addAccountRecords(0, $uid, '余额微信充值成功', $number, 12, $data_id, '余额充值成功!');
                }
                if($type==2){
                    $acount->addAccountRecords(0, $uid, '余额支付宝充值成功', $number, 13, $data_id, '余额充值成功!');
                }
				if($type==20){
                    $acount->addAccountRecords(0, $uid, '余额GlobePay充值成功', $number, 47, $data_id, '余额充值成功!');
                }
            }
            if ($status == 29) {//海报奖励余额
                $from_type = 32;
                //会员账户改变
                $data_member = array(
                    'balance' => $member_all_balance+$number
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 32,//海报奖励余额
                    'data_id' => $data_id,
                    'balance'=> $member_all_balance+$number,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $website_id
                );
                $acount = new ShopAccount();
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '海报奖励余额成功', $number, 35, $data_id, '海报奖励余额成功!', $website_id);
            }
            if ($status == 30) {//兑换余额
                $from_type = 38;
                //会员账户改变
                $data_member = array(
                    'balance' => $member_all_balance+$number
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 38,//积分兑换余额
                    'balance'=> $member_all_balance+$number,
                    'point'=>$all_info['point'],
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $website_id
                );
            }
            if ($status == 19) {//后台调整
                $from_type = 10;
                $real_balance = $member_all_balance+$number;
                if($real_balance<0){
                    $real_balance = 0;
                }
                //会员账户改变
                $data_member = array(
                    'balance' => $real_balance
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 10,
                    'data_id' => $data_id,
                    'balance'=> $real_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '余额后台调整', $number, 11, $data_id, '余额后台调整成功!');
            }
            if ($status == 50) {//余额转账
                $from_type = 50;
                $real_balance = $member_all_balance+$number;
                if($real_balance<0){
                    $real_balance = 0;
                }
                //会员账户改变
                $data_member = array(
                    'balance' => $real_balance
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 50,
                    'data_id' => $data_id,
                    'balance'=> $real_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id,
                    'charge' => $charge
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '余额转账', $number, 11, $data_id, '余额转账成功!');
            }
            if ($status == 51) {//余额兑换
                $from_type = 51;
                $real_balance = $member_all_balance+$number;
                if($real_balance<0){
                    $real_balance = 0;
                }
                //会员账户改变
                $data_member = array(
                    'balance' => $real_balance
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 51,
                    'data_id' => $data_id,
                    'balance'=> $real_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id,
                    'charge' => $charge
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '余额转账', $number, 11, $data_id, '余额转账成功!');
            }
            if ($status == 52) {//积分兑换
                $from_type = 52;
                $real_balance = $member_all_balance+$number;
                if($real_balance<0){
                    $real_balance = 0;
                }
                //会员账户改变
                $data_member = array(
                    'balance' => $real_balance
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 52,
                    'data_id' => $data_id,
                    'balance'=> $real_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id,
                    'charge' => $charge
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '余额转账', $number, 11, $data_id, '余额转账成功!');
            }
            if ($status == 21 || $status == 27) {//商城订单创建（余额支付）
                $from_type = 1;
                if($status == 27){
                    $from_type = 27;
                }
                $data_member = array(
                    'balance' => $member_all_balance - abs($number)
                );
                $order = new VslOrderModel();
                $shop_id = $order->getInfo(['order_id' => $data_id], 'shop_id')['shop_id'];
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Op' . getSerialNo(),
                    'account_type' => 2,
                    'shop_id' => $shop_id ? $shop_id : 0,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => (-1) * abs($number),
                    'balance'=> $member_all_balance - abs($number),
                    'point'=>$all_info['point'],
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
            }
            if ($status == 55) {//货款充值成功
                $from_type = 4;
                //会员货款改变
                $data_member = array(
                    'proceeds' => $proceeds + $number
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                //添加会员货款流水
                $data = array(
                    'records_no' => 'Bc' . getSerialNo(),
                    'account_type' => 5,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => 53,
                    'balance'=> $member_all_balance,
                    'point'=> $all_info['point'],
                    'proceeds'=> $proceeds + $number,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                if($type==1){
                    $acount->addAccountRecords(0, $uid, '货款微信充值成功', $number, 45, $data_id, '货款充值成功!');
                }
                if($type==2){
                    $acount->addAccountRecords(0, $uid, '货款支付宝充值成功', $number, 45, $data_id, '货款充值成功!');
                }
            }
            if ($status == 56) {//渠道商订单货款支付
                $data_member = array(
                    'proceeds' => $proceeds - abs($number)
                );
                $order = new VslChannelOrderModel();
                $shop_id = $order->getInfo(['order_id' => $data_id], 'shop_id')['shop_id'];
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Op' . getSerialNo(),
                    'account_type' => 5,
                    'shop_id' => $shop_id,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => (-1) * abs($number),
                    'balance'=> $member_all_balance,
                    'point'=>$all_info['point'],
                    'proceeds'=> $proceeds - abs($number),
                    'from_type' => 27,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
            }
            if($status == 57) {//会员卡余额后台调整
                $from_type = 63;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = bcadd($membercard_balance,$number,2);
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡余额后台调整', $number, 51, $data_id, '会员卡余额后台调整成功!');
            }
            if($status == 58) {//购买会员卡,余额支付
                $from_type = 64;
                $data_member = array(
                    'balance' => $member_all_balance - abs($number)
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 2,
                    'shop_id' => 0,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => (-1) * abs($number),
                    'balance'=> $member_all_balance - abs($number),
                    'point'=>$all_info['point'],
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->updateAccountOrderBalance($number);
                $acount->addAccountRecords(0, $uid, '购买会员卡', $number, 52, $data_id, "购买会员卡成功，余额支付总额增加", $website_id);
            }
            if ($status == 59) {//会员卡充值,余额支付
                $from_type = 65;
                $data_member = array(
                    'balance' => $member_all_balance - abs($number)
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 2,
                    'shop_id' => 0,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => (-1) * abs($number),
                    'from_type' => $from_type,
                    'balance'=> $member_all_balance - abs($number),
                    'point'=>$all_info['point'],
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值', $number, 53, $data_id, "会员卡充值，余额支付总额增加", $website_id);
            }
            if($status == 60) {//会员卡充值，余额支付
                $from_type = 66;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = bcadd($membercard_balance,$number,2);
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，余额支付', $number, 65, $data_id, '会员卡充值，余额支付成功!');
            }
            if($status == 61) {//会员卡充值，微信支付
                $from_type = 67;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，微信支付', $number, 65, $data_id, '会员卡充值，微信支付成功!');
            }
            if($status == 62) {//会员卡充值，支付宝支付
                $from_type = 68;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，支付宝支付', $number, 66, $data_id, '会员卡充值，支付宝支付成功!');
            }
            if($status == 63) {//会员卡充值，银行卡支付
                $from_type = 69;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，银行卡支付', $number, 67, $data_id, '会员卡充值，银行卡支付成功!');
            }
            if($status == 64) {//会员卡充值，eth支付
                $from_type = 73;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，eth支付', $number, 67, $data_id, '会员卡充值，eth支付成功!');
            }
            if($status == 65) {//会员卡充值，eos支付
                $from_type = 71;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，eos支付', $number, 68, $data_id, '会员卡充值，eos支付成功!');
            }
            if($status == 66) {//会员卡充值，GlobePay支付
                $from_type = 72;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡充值，GlobePay支付', $number, 69, $data_id, '会员卡充值，GlobePay支付成功!');
            }
            if($status == 67) {//会员卡余额,订单抵扣
                $from_type = 75;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid,'website_id' => $this->website_id],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance - abs($number);
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => (-1) * abs($number),
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                $acount = new ShopAccount();
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '会员卡余额，订单抵扣', $number, 70, $data_id, '会员卡余额，订单抵扣!');
            }
            if($status == 68) {//订单关闭或退款，返还会员卡余额
                $from_type = 76;
                $membercard_user_mdl = new VslMembercardUserModel();
                $membercard_balance = $membercard_user_mdl->getInfo(['uid' => $uid],'membercard_balance')['membercard_balance'];
                $real_membercard_balance = $membercard_balance + $number;
                if($real_membercard_balance < 0){
                    $real_membercard_balance = 0;
                }
                //会员卡账户改变
                $data_member = array(
                    'membercard_balance' => $real_membercard_balance
                );
                $membercard_user_mdl->save($data_member, ['uid' => $uid]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Mc' . getSerialNo(),
                    'account_type' => 6,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $number,
                    'from_type' => $from_type,
                    'data_id' => $data_id,
                    'balance'=> $real_membercard_balance,
                    'point'=>$all_info['point'],
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
//                $acount->addAccountUserWithdraw($number, $data_id);
                // 添加平台的整体资金流水
                $acount->addAccountRecords(0, $uid, '订单关闭或退款，返还会员卡余额', $number, 71, $data_id, '订单关闭或退款，返还会员卡余额!');
            }
            if ($status == 69) {//直播订单
                //直播订单支付，冻结分成增加
                $data = array(
                    'records_no' => 'Op' . getSerialNo(),
                    'account_type' => 2,
                    'shop_id' => $shop_id,
                    'uid' => $uid,
                    'sign' => 1,
                    'number' => abs($number),
                    'balance'=> $member_all_balance,
                    'point'=>$all_info['point'],
                    'from_type' => 77,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
            }
            if ($status == 70) {//直播订单
                //直播订单退款，冻结分成减少
                $data = array(
                    'records_no' => 'Op' . getSerialNo(),
                    'account_type' => 2,
                    'shop_id' => $shop_id,
                    'uid' => $uid,
                    'sign' => 1,
                    'number' => (-1) * abs($number),
                    'balance'=> $member_all_balance,
                    'point'=>$all_info['point'],
                    'from_type' => 77,
                    'data_id' => $data_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
            }
            if($data){
                $res = $member_account_record->save($data);
            }
            if($update_data){
                $res = $member_account_record->save($update_data,['data_id'=>$data_id, 'from_type' => $from_type]);
            }
            $member_account_record->commit();
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $member_account_record->rollback();
            return $e->getMessage();
        }
    }

    /*
     * 同意打款（手动打款）
     * */

    public function addAgreeMemberAccountData($data_id, $uid, $number, $text,$wx_openid,$withdraw_no,$type,$account_number='', $real_name = '') {
        $fail = 0;
        $email_params['shop_id'] = $params['shop_id'] = 0;
        $email_params['take_out_money'] = $params['takeoutmoney'] = abs($number);
        $email_params['uid'] = $params['uid'] = $uid;
        $email_params['website_id'] = $params['website_id'] = $this->website_id;
        $email_params['template_code'] = 'withdrawal_success';
        $email_params['notify_type'] = 'user';
        $member_account = new VslMemberAccountModel();
        $all_info = $member_account->getInfo(['uid' => $uid, 'website_id' => $this->website_id], 'freezing_balance,withdraw,charge');
        $freezing_balance = $all_info['freezing_balance'];
        $withdraw_balance = $all_info['withdraw'];
        $charge_balance = $all_info['charge'];
        $member_account_record = new VslMemberAccountRecordsModel();
        $balance_withdraw = new VslMemberBalanceWithdrawModel();
        $card_type = 1;
        $member_account_record->startTrans();
        try {
            $balance_info = $balance_withdraw->getInfo(['id' => $data_id],'charge,service_charge,type');
            $charge = abs($balance_info['charge']);
            if($type==2){
                $weixin_pay = new WeiXinPay();
                $retval = $weixin_pay->EnterprisePayment($wx_openid, $withdraw_no, '', abs($balance_info['service_charge']), '余额微信提现，在线打款',$this->website_id);
            }
            if($type==3){
                $alipay_pay = new AliPay();
                $retval = $alipay_pay->aliPayTransferNew($withdraw_no, $account_number, abs($balance_info['service_charge']), $real_name);
            }
            if ($type == 1) {
                $bank = new VslMemberBankAccountModel();
                $bank_id = $bank->getInfo(['account_number'=>$account_number,'uid'=>$uid],'id')['id'];
                //此处判断是汇聚支付 还是通联支付
                $webConfig = new Config();
                $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                if($joinPay['value']['joinpaytw_is_use'] == 1){
                    $card_type = 2;

                    $joinpay = new Joinpay();
                    $retval = $joinpay->jpWithdraw($withdraw_no,$uid,$bank_id,abs($balance_info['service_charge']));

                }else{
                    $tlpay = new tlPay();
                    $retval = $tlpay->tlWithdraw($withdraw_no,$uid,$bank_id,abs($balance_info['service_charge']));
                }

            }
            if ($type == 5) {//线下手动打款
                $balance_withdraw->save(['status'=>3], ['id'=>$data_id]);
                $retval['is_success'] = 1;
            }
            if ($retval['is_success'] == 1) {//打款成功
                if($card_type == 2){ //聚合银行卡自动
                    //处理中
                    $withdraw_data = array(
                        'status' => 6,
                        'modify_date' => time(),
                        'memo' => '银行处理中'
                    );
                    $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                    $member_account_record->commit();
                    return 1;
                }
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                runhook('Notify', 'emailSend', $email_params);
                // 修改会员提现状态

                $withdraw_data = array(
                    'status' => 3,
                    'payment_date'=>time(),
                    'modify_date' => time(),
                    'memo' => '打款成功'
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id]);
                //会员账户改变
                $data_member = array(
                    'freezing_balance' => $freezing_balance - abs($balance_info['service_charge'])-abs($charge),
                    'withdraw' => $withdraw_balance + abs($number),//已提现余额增加
                    'charge'=>$charge_balance +abs($charge)
                );
                $member_account->save($data_member, ['uid' => $uid, 'website_id' => $this->website_id]);
                //添加会员账户流水
                $data = array(
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'charge' => (-1)*abs($charge),
                    'number' => (-1)*abs($number),
                    'from_type' => 8,
                    'data_id' => $data_id,
                    'status' => 3,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $this->website_id
                );
                //平台账户改变
                $acount = new ShopAccount();
                $acount->updateAccountUserWithdraw($number);
                // 添加平台的整体资金流水
                if($type==1){
                    if(abs($charge)>0){
                        $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($charge), 25, $data_id, '余额银行卡提现成功，提现手续费增加!');
                    }
                    $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 36, $data_id, '余额银行卡提现手动在线打款成功，平台账户提现金额增加!');
                }
                if($type==2){
                    if(abs($charge)>0){
                        $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($charge), 25, $data_id, '余额微信提现成功，提现手续费增加!');
                    }
                    $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 7, $data_id, '余额微信提现手动在线打款成功，平台账户提现金额增加!');
                }
                if($type==3){
                    if(abs($charge)>0){
                        $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($charge), 25, $data_id, '余额支付宝提现成功，提现手续费增加!');
                    }
                    $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 8, $data_id, '余额支付宝提现手动在线打款成功，平台账户提现金额增加!');
                }
                if ($type == 5) {
                    if($balance_info['type'] == 2){
                        if(abs($charge)>0){
                            $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($charge), 25, $data_id, '余额微信提现成功，提现手续费增加!');
                        }
                        $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 7, $data_id, '余额微信提现线下打款成功，平台账户提现金额增加!');
                    }elseif($balance_info['type'] == 3){
                        if(abs($charge)>0){
                            $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($charge), 25, $data_id, '余额支付宝提现成功，提现手续费增加!');
                        }
                        $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 8, $data_id, '余额支付宝提现线下打款成功，平台账户提现金额增加!');
                    }elseif($balance_info['type'] == 1){
                        if(abs($charge)>0){
                            $acount->addAccountRecords(0, $uid, '余额提现成功手续费', abs($charge), 25, $data_id, '余额银行卡提现成功，提现手续费增加!');
                        }
                        $acount->addAccountRecords(0, $uid, '余额提现成功', abs($number), 36, $data_id, '余额银行卡提现线下打款成功，平台账户提现金额增加!');
                    }

                }
            } else {//打款失败
                // 修改会员提现状态
                $balance_withdraw = new VslMemberBalanceWithdrawModel();
                $withdraw_data = array(
                    'status' => 5,
                    'memo'=>$retval['msg'],
                    'modify_date' => time(),
                );
                $balance_withdraw->save($withdraw_data, ['id' => $data_id, 'uid' => $uid]);
                if($type==1){
                    //添加会员账户流水
                    $data = array(
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'charge' => (-1)*abs($charge),
                        'number' => (-1)*abs($number),
                        'from_type' => 8,
                        'data_id' => $data_id,
                        'status' => 5,
                        'text' => '余额银行卡提现打款失败，等待商家重新打款',
                        'msg'=>$retval['msg'],
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
                if($type==2){
                    $balance_withdraw->save($withdraw_data, ['id' => $data_id, 'uid' => $uid]);
                    //添加会员账户流水
                    $data = array(
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'charge' => (-1)*abs($charge),
                        'number' => (-1)*abs($number),
                        'from_type' => 8,
                        'data_id' => $data_id,
                        'status' => 5,
                        'text' => '余额微信提现打款失败，等待商家重新打款',
                        'msg'=>$retval['msg'],
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
                if($type==3){
                    //添加会员账户流水
                    $data = array(
                        'account_type' => 2,
                        'uid' => $uid,
                        'sign' => 0,
                        'charge' => (-1)*abs($charge),
                        'number' => (-1)*abs($number),
                        'from_type' => 8,
                        'data_id' => $data_id,
                        'status' => 5,
                        'text' => '余额支付宝提现打款失败，等待商家重新打款',
                        'msg'=>$retval['msg'],
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                }
                $fail =1;
            }
            $member_account_record->save($data, ['data_id' => $data_id, 'from_type' => 8]);
            $member_account_record->commit();
            if($fail == 1){
                return -9000;
            }
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $member_account_record->rollback();
            return $e->getMessage();
        }
    }

    /* --------------------------------------------------------  提现过程  ------------------------------------------------- */

    public function getMemberAccount($uid, $account_type = '') {
        $condition = array(
            'uid' => $uid,
            'website_id' => $this->website_id
        );
        $member_account = new VslMemberAccountModel();
        $account = $member_account->getInfo($condition, '*');
        return $account;
    }

    /**

     * 积分转换成余额
     * @param unknown $point    积分
     * @param unknown $convert_rate 积分/余额
     */
    public function pointToBalance($point) {
        $point_config = new VslPointConfigModel();
        $convert_rate = $point_config->getInfo(['is_open' => 1, 'website_id' => $this->website_id], 'convert_rate');
        if (!$convert_rate || $convert_rate == '') {
            $convert_rate = 0;
        }
        $balance = $point * $convert_rate['convert_rate'];
        return $balance;



    }

    /**
     * 获取购物币余额转化关系
     */
    public function getCoinConvertRate() {
        $config = new Config();
        $config_rate = $config->getConfig(0, 'COIN_CONFIG', $this->website_id, 1);
        if (empty($config_rate)) {
            return 1;
        } else {
            return $config_rate['convert_rate'];
        }
    }

    /**
     * 获取会员余额数
     * @param unknown $uid
     */
    public function getMemberBalance($uid) {
        $member_account = new VslMemberAccountModel();
        $balance = $member_account->getInfo(['uid' => $uid, 'website_id' => $this->website_id], 'balance');
        if (!empty($balance)) {
            return $balance['balance'];
        } else {
            return 0.00;
        }
    }

    /**
     * 获取会员购物币
     * @param unknown $uid
     * @return unknown|number
     */
    public function getMemberCoin($uid) {
        $member_account = new VslMemberAccountModel();
        $coin = $member_account->getInfo(['uid' => $uid, 'website_id' => $this->website_id], 'coin');
        if (!empty($coin)) {
            return $coin['coin'];
        } else {
            return 0.00;
        }
    }

    public function getMemberPoint($uid) {
        $member_account = new VslMemberAccountModel();
        //查询全部积分
        $point = $member_account->where(['uid' => $uid, 'website_id' => $this->website_id])->sum('point');
        if (!empty($point)) {
            return $point;
        } else {
            return 0;
        }
    }
    public function getMemberBeautifulPoint($uid) {
        $member_account = new VslMemberAccountModel();
        //查询全部积分
        $point = $member_account->where(['uid' => $uid, 'website_id' => $this->website_id])->sum('beautiful_point');
        if (!empty($point)) {
            return $point;
        } else {
            return 0;
        }
    }
    public static function getMemberAccountRecordsName($from_type) {
        switch ($from_type) {
            case 203:
                $type_name = '打卡奖励';
                break;
            case 201:
                $type_name = '美丽分转账';
                break;
            case 202:
                $type_name = '美丽分转账';
                break;
            case 1:
                $type_name = '订单支付';
                break;
            case 2:
                $type_name = '订单退款';
                break;
            case 3:
                $type_name = '兑换';
                break;
            case 4:
                $type_name = '余额充值';
                break;
            case 5:
                $type_name = '签到';
                break;
            case 6:
                $type_name = '分享';
                break;
            case 7:
                $type_name = '注册';
                break;
            case 8:
                $type_name = '提现';
                break;
            case 9:
                $type_name = '提现退还';
                break;
            case 10:
                $type_name = '后台调整';
                break;
            case 11:
                $type_name = '分红提现';
                break;
            case 12:
                $type_name = '分红提现';
                break;
            case 13:
                $type_name = '分红提现';
                break;
            case 14:
                $type_name = '收益提现';
                break;
            case 15:
                $type_name = '分销提现';
                break;
            case 16:
                $type_name = '活动消费积分';
                break;
            case 17:
                $type_name = '活动获得积分';
                break;
            case 18:
                $type_name = '活动获得余额';
                break;
            case 19:
                $type_name = '点赞';
                break;
            case 20:
                $type_name = '评论';
                break;
            case 27:
                $type_name = '采购订单支付';
                break;
            case 28:
                $type_name = '零售收款';
                break;
            case 29:
                $type_name = '下级升级';
                break;
            case 70:
                $type_name = '会员零售退款';
                break;
            case 30:
                $type_name = '分销订单完成赠送积分';
                break;
            case 130:
                $type_name = '分销订单完成赠送美丽分';
                break;
            case 31:
                $type_name = '订单积分抵扣';
                break;
            case 32:
                $type_name = '海报奖励余额';
                break;
            case 33:
                $type_name = '海报奖励积分';
                break;
            case 34:
                $type_name = '积分兑换成ETH';
                break;
            case 35:
                $type_name = '积分兑换成EOS';
                break;
            case 36:
                $type_name = 'ETH兑换成积分';
                break;
            case 37:
                $type_name = 'EOS兑换成积分';
                break;
            case 38:
                $type_name = '积分兑换余额';
                break;
            case 39:
                $type_name = '好物圈';
                break;
            case 40:
                $type_name = '防伪溯源';
                break;
            case 50:
                $type_name = '余额转账';
                break;
            case 51:
                $type_name = '余额兑换';
                break;
            case 52:
                $type_name = '积分兑换';
                break;
            case 53:
                $type_name = '货款充值';
                break;
            case 54:
                $type_name = '拼多多订单奖金，冻结金额增加';
                break;
            case 55:
                $type_name = '拼多多推广奖金，冻结金额增加';
                break;
            case 56:
                $type_name = '拼多多订单奖金，冻结金额减少，可用余额增加';
                break;
            case 57:
                $type_name = '拼多多推广奖金，冻结金额减少，可用余额增加';
                break;
            case 58:
                $type_name = '京东订单奖金，冻结金额增加';
                break;
            case 59:
                $type_name = '京东推广奖金，冻结金额增加';
                break;
            case 60:
                $type_name = '京东订单奖金，冻结金额减少，可用余额增加';
                break;
            case 61:
                $type_name = '京东推广奖金，冻结金额减少，可用余额增加';
                break;
            case 62:
                $type_name = '招商员业绩提现';
                break;
            case 63:
                $type_name = '会员卡余额后台调整';
                break;
            case 64:
                $type_name = '购买会员卡';
                break;
            case 65:
                $type_name = '会员卡充值';
                break;
            case 66:
                $type_name = '充值，余额支付';
                break;
            case 67:
                $type_name = '充值，微信支付';
                break;
            case 68:
                $type_name = '充值，支付宝支付';
                break;
            case 69:
                $type_name = '充值，银行卡支付';
                break;
            case 73:
                $type_name = '充值，eth支付';
                break;
            case 71:
                $type_name = '充值，eos支付';
                break;
            case 72:
                $type_name = '充值，GlobePay支付';
                break;
            case 74:
                $type_name = '好评送积分';
                break;
            case 75:
                $type_name = '订单抵扣';
                break;
            case 76:
                $type_name = '订单关闭或退款';
                break;
            case 77:
                $type_name = '直播订单';
                break;
            case 78:
                $type_name = '直播赠送礼物积分';
                break;
            case 81:
                $type_name = '幸运拼';
                break;
            case 82:
                $type_name = '积分转账';
                break;
            case 100:
                $type_name = '特约开通VIP';
                break;
            default:
                $type_name = '';
                break;
        }
        return $type_name;
    }

    /**
     * 通过提现流水号获取小程序提现模板form_id
     * @param $withdrawa_no [提现流水号]
     * @return mixed
     */
    public function getWithdrawalsFormIdByWithdrawNo($withdrawa_no)
    {
        $balance_withdraw = new VslMemberBalanceWithdrawModel();
        $result = $balance_withdraw->getInfo(['website_id' => $this->website_id, 'withdraw_no' => $withdrawa_no], 'form_id');

        if ($result['form_id']) {
            return $result['form_id'];
        }
        return '';
    }

    /**
     * 通过提现记录id获取小程序提现模板form_id,withdraw_no
     * @param $id [提现流水号]
     * @return mixed
     */
    public function getWithdrawalsFormIdByWithdrawId($id)
    {
        $balance_withdraw = new VslMemberBalanceWithdrawModel();
        $result = $balance_withdraw->getInfo(['website_id' => $this->website_id, 'id' => $id], 'form_id, withdraw_no');

        if ($result['form_id']) {
            return $result;
        }
        return '';
    }
    /**
     * 美丽分转账
     */
    public function transBeautifulPoint($user_id, $uid, $money, $website_id){
        try {
            Db::startTrans();
            $member_account_record = new VslMemberAccountRecordsModel();
            //前期检测
            $member_account = new VslMemberAccountModel();
            $user_info = $member_account->getInfo(['uid' => $user_id, 'website_id' => $website_id], 'beautiful_point,balance');
            $my_info = $member_account->getInfo(['uid' => $uid, 'website_id' => $website_id], 'beautiful_point,balance');
            if($my_info['beautiful_point'] < $money){
                return ['code'=>-1,'message'=>'美丽分不足，转账失败'];
            }
            //先减少本人
            $my_new_beautiful_point = $my_info['beautiful_point'] - $money;
            $my_beautiful_point = $my_info['beautiful_point'];
            $sql = "update vsl_member_account set beautiful_point=$my_new_beautiful_point where uid=$uid and website_id=$website_id and beautiful_point=$my_beautiful_point";
            debugFile($sql, 'transBeautifulPoint-1', 1111112);
            $results = Db::execute($sql);
            if($results <= 0){
                Db::rollback();
                return ['code'=>-1,'message'=>'更新转出账户失败,转账失败,请稍后重试'];
            }

            $user_new_beautiful_point = $user_info['beautiful_point'] + $money;
            $user_beautiful_point = $user_info['beautiful_point'];
            $user_sql = "update vsl_member_account set beautiful_point=$user_new_beautiful_point where uid=$user_id and website_id=$website_id and beautiful_point=$user_beautiful_point";
            $res = Db::execute($user_sql);
            if($res <= 0){
                Db::rollback();
                return ['code'=>-1,'message'=>'更新转入账户失败,转账失败,请稍后重试'];
            }
            #转出记录
            $data = array(
                'records_no' => getSerialNo(),
                'account_type' => 3,
                'uid' => $uid,
                'sign' => -1,
                'number' => $money,
                'from_type' => 201,
                'data_id' => 0,
                'text' => '美丽分转账',
                'create_time' => time(),
                'point'=>$my_new_beautiful_point,
                'balance'=>$my_info['balance'],
                'website_id' =>  $website_id
            );
            $member_account_record->save($data);
            #转入记录
            $data = array(
                'records_no' => getSerialNo(),
                'account_type' => 3,
                'uid' => $user_id,
                'sign' => 1,
                'number' => $money,
                'from_type' => 202,
                'data_id' => 0,
                'text' => '美丽分转账',
                'create_time' => time(),
                'point'=>$user_new_beautiful_point,
                'balance'=>$user_info['balance'],
                'website_id' =>  $website_id
            );
            $member_account_record = new VslMemberAccountRecordsModel();
            $member_account_record->save($data);
            Db::commit();
            return ['code'=>-1,'message'=>'转账成功'];
        } catch (\Throwable $e) {
            debugFile($e->getMessage(), 'transBeautifulPoint-e', 1111112);
            Db::rollback();
            return ['code'=>-1,'message'=>$e->getMessage()];
        }

    }
}
