<?php
namespace data\service;
use addons\membercard\server\Membercard as MembercardSer;
use data\model\OrderLockModel;
class PointDeduction extends BaseService
{
    public function actPointDeduction(\data\service\member $user_service, $uid, $payment_info, &$return_data, $is_membercard_deduction, $is_membercard)
    {
        //积分抵扣
        $member_info = $user_service->getMemberAccount($uid);//data/service/member
        $return_data['deduction_point']['point'] = $member_info['point'];
        $return_data['deduction_point']['total_deduction_money'] = 0;
        $return_data['deduction_point']['total_deduction_point'] = 0;
        //会员卡抵扣
        # 初始积分计算
        $deduct_points_flag = true;
        foreach($payment_info as $shop_id => $shop_info){
            foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                //积分抵扣
                if($deduct_points_flag && $sku_goods['deduction_money']>=0){
                    $return_data['deduction_point']['total_deduction_money'] += $sku_goods['deduction_money'];
                    $return_data['deduction_point']['total_deduction_point'] += $sku_goods['deduction_point'];
                    $member_info['point'] = $member_info['point'] - $sku_goods['deduction_point'];
                }
                if ($sku_goods['deduction_point'] >= $member_info['point']){
                    $return_data['deduction_point']['total_deduction_money'] += $member_info['point'];
                    $return_data['deduction_point']['total_deduction_point'] += $member_info['point'];
                    $deduct_points_flag = false;
                }
                //积分赠送
                $return_data['total_return_point'] += $sku_goods['return_point'];//积分抵扣累计
            }
        }
        # 会员卡抵扣计算
        if($is_membercard){
            $membercard = new MembercardSer();
            $payment_info = $membercard->dealMembercardReturnPoint($payment_info);//会员卡额外积分
            //统计总积分
            $membercard_total_give_point = 0;
            foreach($payment_info as $shop_id => $shop_info){
                $sku_goods_return_point = 0;
                foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                    $membercard_total_give_point += $sku_goods['return_point'];//积分抵扣累计
                    $sku_goods_return_point += $sku_goods['return_point'];//店铺维度总返积分
                }
                $payment_info[$shop_id]['total_return_point'] = $sku_goods_return_point;
            }
            $return_data['total_return_point'] = $membercard_total_give_point;
            //会员卡抵扣处理
            $payment_info = $membercard->membercardReturnOrderInfo($payment_info,$is_membercard_deduction,$return_data);
        }
        return $payment_info;
    }

    /**
     * 处理积分抵扣金额
     * @param $order_info
     */
    public function pointDeductionMoney($order_info)
    {
        if ($order_info['deduction_money'] > 0) {
            if (!empty($order_info['sku_info'][0]['presell_id'])) {//预售处理
                $order_info['final_money'] = $order_info['final_money'] - $order_info['deduction_money'];
                if ($order_info['final_money'] <= 0) {
                    $order_info['order_money'] = $order_info['order_money'] + $order_info['final_money'];
                    if ($order_info['pay_money'] > 0) {
                        $order_info['pay_money'] = $order_info['pay_money'] + $order_info['final_money'];
                        $order_info['final_money'] = 0;
                    }
                    if ($order_info['user_platform_money'] > 0) {
                        $order_info['user_platform_money'] = $order_info['user_platform_money'] + $order_info['final_money'];
                        $order_info['final_money'] = 0;
                    }
                }
                if ($order_info['pay_money'] == 0 && $order_info['final_money'] == 0) {
                    $order_info['money_type'] = 2;
                }
            } else {
                if ($order_info['user_platform_money'] > 0) {
                    $order_info['user_platform_money'] = $order_info['user_platform_money'] - $order_info['deduction_money'];
                }
            }
            //积分抵扣算进平台优惠
            $order_info['platform_promotion_money'] += $order_info['deduction_money'];
            // edit for 2020/09/25 变更 测试要求变更  积分抵扣不用额外平台补贴
            if ($order_info['pay_money'] != 0) {
                $order_info['order_status'] = 0;
                $order_info['pay_status'] = 0;
            } else {
                $order_info['order_status'] = 1;
                $order_info['pay_status'] = 2;
            }
        }
        return $order_info;
    }
}