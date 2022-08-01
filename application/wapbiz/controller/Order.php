<?php

namespace app\wapbiz\controller;

use addons\blockchain\model\VslBlockChainRecordsModel;
use addons\bonus\model\VslAgentLevelModel;
use addons\invoice\server\Invoice as InvoiceService;
use data\model\UserModel;
use data\model\VslMemberModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use data\service\Address as AddressService;
use data\service\Express as ExpressService;
use data\service\Order\OrderGoods;
use data\service\Order as OrderService;
use addons\bonus\model\VslOrderBonusLogModel;
use data\model\DistrictModel;

/**
 * 订单控制器
 *
 * @author  www.vslai.com
 *
 */
class Order extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 订单列表
     */
    public function orderList()
    {
        $order_service = new OrderService();
        $orderCount = $order_service->getUserOrderCountByCondition(['website_id' => $this->website_id]);
        if ($this->merchant_status && $orderCount >= 50) {
            return json(['code' => -1, 'message' => '免费版本最多创建50张订单,当前商城订单数量已达上限.为了不影响商家后台正常使用,请联系客服进行升级!']);
        }
        $page_index = (int)request()->post('page_index', 1);
        $page_size = (int)request()->post('page_size', PAGESIZE);
        $order_status = request()->post('order_status', '');
        $search_text = trim(request()->post('search_text', ''));
        $member_id = (int)request()->post('member_id', 0);
        $condition['is_deleted'] = 0; // 未删除订单
        if (is_numeric($search_text)) {
            $condition['order_no'] = ['LIKE', '%' . $search_text . '%'];
        } elseif (strstr($search_text, 'DH')) {
            $condition['order_no'] = ['LIKE', '%' . $search_text . '%'];
        } elseif (!empty($search_text)) {
            $condition['shop_name|goods_name|receiver_name|user_name'] = array(
                'like',
                '%' . $search_text . '%'
            );
        }

        if ($order_status !== '') {
            // $order_status 1 待发货
            if ($order_status == 1) {
                // 订单状态为待发货实际为已经支付未完成还未发货的订单
                $condition['shipping_status'] = 0; // 0 待发货
                $condition['pay_status'] = 2; // 2 已支付
                $condition['order_status'][] = ['neq', 4]; // 4 已完成
                $condition['order_status'][] = ['neq', 5]; // 5 关闭订单
                $condition['order_status'][] = ['neq', -1]; // -1 售后订单
            }  else {
                $condition['order_status'] = $order_status;
            }
        }
        if($member_id){
            $condition['buyer_id'] = $member_id;
        }
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $list = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');
        $order_list = [];
        if($list['data']){
            foreach ($list['data'] as $k => $order) {
            $order_list[$k]['order_id'] = $order['order_id'];
            $order_list[$k]['order_no'] = $order['order_no'];
            $order_list[$k]['shop_id'] = $order['shop_id'];
            $order_list[$k]['shop_name'] = $order['shop_name'];
            $order_list[$k]['order_point'] = $order['order_point'];
            $order_list[$k]['money_type'] = $order['money_type'];
            $order_list[$k]['pay_money'] = $order['pay_money'];
            $order_list[$k]['presell_id'] = $order['presell_id'];
            $order_list[$k]['pay_type_name'] = $order['pay_type_name'];
            $order_list[$k]['order_type'] = $order['order_type'];
            $order_list[$k]['order_status'] = $order['order_status'];
            $order_list[$k]['order_money'] = $order['order_money'];
            $order_list[$k]['commission'] = $order['commission'];
            $order_list[$k]['bonus'] = $order['bonus'];
            $order_list[$k]['global_bonus'] = $order['global_bonus'];
            $order_list[$k]['area_bonus'] = $order['area_bonus'];
            $order_list[$k]['team_bonus'] = $order['team_bonus'];
            $order_list[$k]['order_type_name'] = $order['order_type_name'];
            if (!empty($order['status_name'])) {
                $order_list[$k]['order_status_name'] = $order['status_name'];
            }
            if (isset($order['operation'])) {
                foreach($order['operation'] as $ok => $ov){
                    $order_list[$k]['operation'][] = $ov;
                }
                unset($ov);
            }
            if($order['coin_after']){
                $order_list[$k]['operation'] = [];
            }
            $order_list[$k]['promotion_status'] = ($order['promotion_money'] + $order['coupon_money'] > 0) ? 1 : 0;
            foreach ($order['order_item_list'] as $key_sku => $item) {
                $order_list[$k]['order_item_list'][$key_sku]['order_goods_id'] = $item['order_goods_id'];
                $order_list[$k]['order_item_list'][$key_sku]['goods_id'] = $item['goods_id'];
                $order_list[$k]['order_item_list'][$key_sku]['sku_id'] = $item['sku_id'];
                $order_list[$k]['order_item_list'][$key_sku]['goods_name'] = $item['goods_name'];
                $order_list[$k]['order_item_list'][$key_sku]['price'] = $item['price'];
                $order_list[$k]['order_item_list'][$key_sku]['num'] = $item['num'];
                $order_list[$k]['order_item_list'][$key_sku]['pic_cover'] = getApiSrc($item['picture']['pic_cover'] ?: $item['picture']['pic_cover_mid']);
                $order_list[$k]['order_item_list'][$key_sku]['spec'] = $item['spec'];
                $order_list[$k]['order_item_list'][$key_sku]['status_name'] = $item['status_name'];
                $order_list[$k]['order_item_list'][$key_sku]['refund_status'] = $item['refund_status'];
                $order_list[$k]['order_item_list'][$key_sku]['refund_type'] = $item['refund_type'];
                $order_list[$k]['order_item_list'][$key_sku]['real_refund_reason'] = $item['real_refund_reason'];
                $order_list[$k]['order_item_list'][$key_sku]['refund_require_money'] = $item['refund_require_money'];
                if($item['new_refund_operation']){
                    foreach ($item['new_refund_operation'] as $k1 => $v1) {
                        if($v1['no'] == 'judge_refund') {
                            array_splice($item['new_refund_operation'], $k1);
                            $item['new_refund_operation'][] = [
                                'no' => 'judge_refund_agree',
                                'name' => '同意退款',
                            ];
                            $item['new_refund_operation'][] = [
                                'no' => 'judge_refund_refuse',
                                'name' => '拒绝退款',
                            ];
                        }
                        if($v1['no'] == 'confirm_receipt') {
                            array_splice($item['new_refund_operation'], $k1);
                            $item['new_refund_operation'][] = [
                                'no' => 'confirm_receipt_agree',
                                'name' => '确认签收',
                            ];
                            $item['new_refund_operation'][] = [
                                'no' => 'confirm_receipt_refuse',
                                'name' => '拒绝签收',
                            ];
                        }
                        if($v1['no'] == 'confirm_refund') {
                            array_splice($item['new_refund_operation'], $k1);
                            $item['new_refund_operation'][] = [
                                'no' => 'confirm_refund_agree',
                                'name' => '同意打款',
                            ];
                            $item['new_refund_operation'][] = [
                                'no' => 'confirm_refund_refuse',
                                'name' => '拒绝打款',
                            ];
                        }
                        if($v1['no'] == 'judge_return') {
                            array_splice($item['new_refund_operation'], $k1);
                            $item['new_refund_operation'][] = [
                                'no' => 'judge_return_agree',
                                'name' => '同意退货',
                            ];
                            $item['new_refund_operation'][] = [
                                'no' => 'judge_return_refuse',
                                'name' => '拒绝退货',
                            ];
                        }
                    }
                }
                $order_list[$k]['order_item_list'][$key_sku]['new_refund_operation'] = $item['new_refund_operation'] ? $item['new_refund_operation'] : [];
                $order_list[$k]['order_item_list'][$key_sku]['return_id'] = $item['return_id'];
            }
            
            if ($order['payment_type'] == 16 || $order['payment_type'] == 17) {
                $order_list[$k]['promotion_status'] = 1;
            }
        }
        }
        $data = [
            'order_list' => $order_list,
            'page_count' => $list['page_count'],
            'total_count' => $list['total_count']
        ];
        unset($list);
        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * 售后订单列表
     *
     */
    public function afterOrderList()
    {
        $order_service = new OrderService();
        $orderCount = $order_service->getUserOrderCountByCondition(['website_id' => $this->website_id]);
        if ($this->merchant_status && $orderCount >= 50) {
            return json(['code' => -1, 'message' => '免费版本最多创建50张订单,当前商城订单数量已达上限.为了不影响商家后台正常使用,请联系客服进行升级!']);
        }
        $page_index = (int)request()->post('page_index', 1);
        $page_size = (int)request()->post('page_size', PAGESIZE);
        $search_text = trim(request()->post('search_text', ''));
        $refund_status = request()->post('refund_status', '9');
        $condition['is_deleted'] = 0; // 未删除订单
        if (is_numeric($search_text)) {
            $condition['order_no'] = ['LIKE', '%' . $search_text . '%'];
        } elseif (strstr($search_text, 'DH')) {
            $condition['order_no'] = ['LIKE', '%' . $search_text . '%'];
        } elseif (!empty($search_text)) {
            $condition['shop_name|goods_name|receiver_name|user_name'] = array(
                'like',
                '%' . $search_text . '%'
            );
        }
        switch ($refund_status) {
            case '':
                $condition['refund_status'] = [1, 2, 3, 4, 5, -3, -1];
                break;
            case 0:
                $condition['refund_status'] = [2];
                break;
            case 1:
                $condition['refund_status'] = [1, 3, 4];
                break;
            case 2:
                $condition['refund_status'] = [5];
                break;
            case 3:
                $condition['refund_status'] = [-1, -3];
                break;
            case -2:
                break;
            default :
                //全部列表只显示需要售后操作的订单商品,2018/12/06 改为还是显示全部
                $condition['refund_status'] = [1, 2, 3, 4, 5, -3, -1];
        }
        $condition['website_id'] = $this->website_id;
        if ($refund_status == -2) {
            $order = new VslOrderModel();
            $order_id = $order->Query(['shop_after' => 1], 'order_id');
            if ($order_id) {
                $condition['order_id'] = ['in', $order_id];
                $condition['shop_id'] = ['neq', 0];
            } else {
                $condition['shop_id'] = ['neq', 0];
                $condition['order_id'] = ['eq', 0];
            }
        } else {
            $condition['shop_id'] = $this->instance_id;
        }

        $list = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');
        $order_list = [];
        if($list['data']){
             foreach ($list['data'] as $k => $order) {
                $order_list[$k]['order_id'] = $order['order_id'];
                $order_list[$k]['order_no'] = $order['order_no'];
                $order_list[$k]['shop_id'] = $order['shop_id'];
                $order_list[$k]['shop_name'] = $order['shop_name'];
                $order_list[$k]['order_point'] = $order['order_point'];
                $order_list[$k]['money_type'] = $order['money_type'];
                $order_list[$k]['pay_money'] = $order['pay_money'];
                $order_list[$k]['presell_id'] = $order['presell_id'];
                $order_list[$k]['pay_type_name'] = $order['pay_type_name'];
                $order_list[$k]['order_type'] = $order['order_type'];
                $order_list[$k]['order_status'] = $order['order_status'];
                $order_list[$k]['order_money'] = $order['order_money'];
                $order_list[$k]['commission'] = $order['commission'];
                $order_list[$k]['bonus'] = $order['bonus'];
                $order_list[$k]['global_bonus'] = $order['global_bonus'];
                $order_list[$k]['area_bonus'] = $order['area_bonus'];
                $order_list[$k]['team_bonus'] = $order['team_bonus'];
                $order_list[$k]['order_type_name'] = $order['order_type_name'];
                if (!empty($order['status_name'])) {
                    $order_list[$k]['order_status_name'] = $order['status_name'];
                }
                $order_list[$k]['promotion_status'] = ($order['promotion_money'] + $order['coupon_money'] > 0) ? 1 : 0;
                foreach ($order['order_item_list'] as $key_sku => $item) {
                    $order_list[$k]['order_item_list'][$key_sku]['order_goods_id'] = $item['order_goods_id'];
                    $order_list[$k]['order_item_list'][$key_sku]['goods_id'] = $item['goods_id'];
                    $order_list[$k]['order_item_list'][$key_sku]['sku_id'] = $item['sku_id'];
                    $order_list[$k]['order_item_list'][$key_sku]['goods_name'] = $item['goods_name'];
                    $order_list[$k]['order_item_list'][$key_sku]['price'] = $item['price'];
                    $order_list[$k]['order_item_list'][$key_sku]['num'] = $item['num'];
                    $order_list[$k]['order_item_list'][$key_sku]['pic_cover'] = getApiSrc($item['picture']['pic_cover'] ?: $item['picture']['pic_cover_mid']);
                    $order_list[$k]['order_item_list'][$key_sku]['spec'] = $item['spec'];
                    $order_list[$k]['order_item_list'][$key_sku]['status_name'] = $item['status_name'];
                    $order_list[$k]['order_item_list'][$key_sku]['refund_status'] = $item['refund_status'];
                    $order_list[$k]['order_item_list'][$key_sku]['refund_type'] = $item['refund_type'];
                    $order_list[$k]['order_item_list'][$key_sku]['real_refund_reason'] = $item['real_refund_reason'];
                    $order_list[$k]['order_item_list'][$key_sku]['refund_require_money'] = $item['refund_require_money'];
                    $order_list[$k]['order_item_list'][$key_sku]['return_id'] = $item['return_id'];
                    if($item['new_refund_operation']){
                        foreach ($item['new_refund_operation'] as $k1 => $v1) {
                            if($v1['no'] == 'judge_refund') {
                                array_splice($item['new_refund_operation'], $k1);
                                $item['new_refund_operation'][] = [
                                    'no' => 'judge_refund_agree',
                                    'name' => '同意退款',
                                ];
                                $item['new_refund_operation'][] = [
                                    'no' => 'judge_refund_refuse',
                                    'name' => '拒绝退款',
                                ];
                            }
                            if($v1['no'] == 'confirm_receipt') {
                                array_splice($item['new_refund_operation'], $k1);
                                $item['new_refund_operation'][] = [
                                    'no' => 'confirm_receipt_agree',
                                    'name' => '确认签收',
                                ];
                                $item['new_refund_operation'][] = [
                                    'no' => 'confirm_receipt_refuse',
                                    'name' => '拒绝签收',
                                ];
                            }
                            if($v1['no'] == 'confirm_refund') {
                                array_splice($item['new_refund_operation'], $k1);
                                $item['new_refund_operation'][] = [
                                    'no' => 'confirm_refund_agree',
                                    'name' => '同意打款',
                                ];
                                $item['new_refund_operation'][] = [
                                    'no' => 'confirm_refund_refuse',
                                    'name' => '拒绝打款',
                                ];
                            }
                            if($v1['no'] == 'judge_return') {
                                array_splice($item['new_refund_operation'], $k1);
                                $item['new_refund_operation'][] = [
                                    'no' => 'judge_return_agree',
                                    'name' => '同意退货',
                                ];
                                $item['new_refund_operation'][] = [
                                    'no' => 'judge_return_refuse',
                                    'name' => '拒绝退货',
                                ];
                            }
                        }
                    }

                    $order_list[$k]['order_item_list'][$key_sku]['new_refund_operation'] = $item['new_refund_operation'] ? $item['new_refund_operation'] : [];
                    
                }
                if ($order['payment_type'] == 16 || $order['payment_type'] == 17) {
                    $order_list[$k]['promotion_status'] = 1;
                }
            }
        }
       
        $data = [
            'order_list' => $order_list,
            'page_count' => $list['page_count'],
            'total_count' => $list['total_count']
        ];
        unset($list);
        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
        /* 筛选退款状态
         * 待买家操作 2,-3
         * 待卖家操作 1 3 4
         * 已退款  5
         * 已拒绝 -1
         * 处理店铺售后 -2
         */
    }

    /**
     * 自营订单详情
     *
     * @return Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function orderDetail()
    {
        $order_id = (int)request()->post('order_id', 0);

        if (!$order_id) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $order_service = new OrderService();
        $district_model = new DistrictModel();
        $order_info = $order_service->getOrderDetail($order_id);
        if (!$order_info) {
            return json(['code' => -1, 'message' => '没有获取到订单信息']);
        }
        $order_detail['order_money'] = '￥' .$order_info['order_money'];
        $order_detail['commission'] = round((isset($order_info['commissionA']) ? $order_info['commissionA'] : 0) + (isset($order_info['commissionB']) ? $order_info['commissionB'] : 0) + (isset($order_info['commissionC']) ? $order_info['commissionC'] : 0), 2);
        $order_detail['global_bonus'] = $order_info['global_bonus'];
        $order_detail['area_bonus'] = $order_info['area_bonus'];
        $order_detail['team_bonus'] = $order_info['team_bonus'];
        if($order_info['presell_id']){
            if($order_info['payment_type']==16 && $order_info['money_type']==1){
                $member_account = new VslBlockChainRecordsModel();
                $pay_cash = $member_account->getInfo(['data_id'=>$order_info['out_trade_no']],'cash')['cash'];
                $order_detail['pay_money'] = $pay_cash.'ETH';
            }
            if($order_info['payment_type']==17 && $order_info['money_type']==1){
                $member_account = new VslBlockChainRecordsModel();
                $pay_cash = $member_account->getInfo(['data_id'=>$order_info['out_trade_no']],'cash')['cash'];
                $order_detail['pay_money'] = $pay_cash.'EOS';
            }
            if($order_info['payment_type']==16 && $order_info['money_type']==2){
                $member_account = new VslBlockChainRecordsModel();
                $pay_cash = $member_account->getInfo(['data_id'=>$order_info['out_trade_no']],'cash')['cash'];
                if($order_info['payment_type_presell']==16){
                    $pay_cash1 = $member_account->getInfo(['data_id'=>$order_info['out_trade_no_presell']],'cash')['cash'];
                    $order_detail['order_money'] = $pay_cash.'ETH +'.$pay_cash1.'ETH';
                } else if($order_info['payment_type_presell']==17){
                    $pay_cash1 = $member_account->getInfo(['data_id'=>$order_info['out_trade_no_presell']],'cash')['cash'];
                    $order_detail['order_money'] = $pay_cash.'ETH +'.$pay_cash1.'EOS';
                }else{
                    $order_detail['order_money'] = $pay_cash.'ETH + ¥ '. $order_info['final_money'];
                }
            }
            if($order_info['payment_type_presell']==16 && $order_info['money_type']==2){
                if($order_info['payment_type']!=16 && $order_info['payment_type']!=17){
                    $member_account = new VslBlockChainRecordsModel();
                    $pay_cash1 = $member_account->getInfo(['data_id'=>$order_info['out_trade_no_presell']],'cash')['cash'];
                    $order_detail['order_money'] = '¥ ' .$order_info['first_money'].'+'.$pay_cash1.'ETH';
                }
            }
            if($order_info['payment_type_presell']==17 && $order_info['money_type']==2){
                if($order_info['payment_type']!=16 && $order_info['payment_type']!=17){
                    $member_account = new VslBlockChainRecordsModel();
                    $pay_cash1 = $member_account->getInfo(['data_id'=>$order_info['out_trade_no_presell']],'cash')['cash'];
                    $order_detail['order_money'] = '¥ ' .$order_info['first_money'].'+'.$pay_cash1.'EOS';
                }
            }
            if($order_info['payment_type']==17 && $order_info['money_type']==2){
                $member_account = new VslBlockChainRecordsModel();
                $pay_cash = $member_account->getInfo(['data_id'=>$order_info['out_trade_no']],'cash')['cash'];
                if($order_info['payment_type_presell']==16){
                    $pay_cash1 = $member_account->getInfo(['data_id'=>$order_info['out_trade_no_presell']],'cash')['cash'];
                    $order_detail['order_money'] = $pay_cash.'EOS +'.$pay_cash1.'ETH';
                } else if($order_info['payment_type_presell']==17){
                    $pay_cash1 = $member_account->getInfo(['data_id'=>$order_info['out_trade_no_presell']],'cash')['cash'];
                    $order_detail['order_money'] = $pay_cash.'EOS +'.$pay_cash1.'EOS';
                }else{
                    $order_detail['order_money'] = $pay_cash.'EOS + ¥ '. $order_info['final_money'];
                }
            }
        }else{
            if($order_info['payment_type']==16){
                $order_detail['order_money'] = $order_info['coin'].'ETH';
            }
            if($order_info['payment_type']==17){
                $order_detail['order_money'] = $order_info['coin'].'EOS';
            }
        }
        $order_detail['order_id'] = $order_info['order_id'];
        $order_detail['order_no'] = $order_info['order_no'];
        $order_detail['out_trade_no'] = $order_info['out_trade_no'];
        $order_detail['out_trade_no_presell'] = $order_info['out_trade_no_presell'];
        $order_detail['shop_name'] = $order_info['shop_name'];
        $order_detail['shop_id'] = $order_info['shop_id'];
        $order_detail['presell_id'] = $order_info['presell_id'];
        $order_detail['order_status'] = $order_info['order_status'];
        $order_detail['offline_pay'] = $order_info['offline_pay'];
        $order_detail['payment_type_name'] = $order_info['payment_type_name'];
        $order_detail['payment_type'] = $order_info['payment_type'];
        $order_detail['promotion_status'] = ($order_info['promotion_money'] + $order_info['coupon_money'] > 0) ? 1 : 0;
        $order_detail['order_refund_status'] = reset($order_info['order_goods'])['refund_status'];
        $order_detail['is_evaluate'] = $order_info['is_evaluate'];
        $order_detail['first_money'] = $order_info['first_money'];
        $order_detail['final_money'] = $order_info['final_money'];
        $order_detail['first_real_money'] = $order_info['first_real_money'];
        $order_detail['final_real_money'] = $order_info['final_real_money'];
        $order_detail['invoice_tax'] = $order_info['invoice_tax'];
        $order_detail['order_real_money'] = $order_info['order_real_money'];
        $order_detail['goods_money'] = $order_info['goods_money'];
        $order_detail['shipping_fee'] = $order_info['shipping_money'] - $order_info['promotion_free_shipping'];
        $order_detail['promotion_money'] = 0;
        //订单时间信息
        $order_detail['create_time'] = $order_info['create_time'] ? date('Y-m-d H:i:s',$order_info['create_time']) : '';
        $order_detail['pay_time'] = $order_info['pay_time'] ? date('Y-m-d H:i:s',$order_info['pay_time']) : '';
        $order_detail['consign_time'] = $order_info['consign_time'] ? date('Y-m-d H:i:s',$order_info['consign_time']) : '';
        $order_detail['finish_time'] = $order_info['finish_time'] ? date('Y-m-d H:i:s',$order_info['finish_time']) : '';
        $address_info = $district_model::get($order_info['receiver_district'], ['city.province']);
        $order_detail['receiver_name'] = $order_info['receiver_name'];
        $order_detail['receiver_mobile'] = $order_info['receiver_mobile'];
        $order_detail['receiver_province'] = $address_info->city->province->province_name;
        $order_detail['receiver_city'] = $address_info->city->city_name;
        $order_detail['receiver_district'] = $address_info->district_name;
        $order_detail['receiver_address'] = $order_info['receiver_address'];
        $order_detail['buyer_message'] = $order_info['buyer_message'];
        $order_detail['group_id'] = $order_info['group_id'];
        $order_detail['group_record_id'] = $order_info['group_record_id'];
        $order_detail['presell_status'] = $order_info['presell_status'];
        $order_detail['shipping_type_name'] = $order_info['shipping_type_name'];
        $order_detail['buyer_message'] = $order_info['buyer_message'];
        $order_detail['order_type'] = $order_info['order_type'];
        $order_detail['order_type_name'] = $order_info['order_type_name'];
        $order_detail['goods_type'] = $order_info['goods_type'];
        $order_detail['shipping_type'] = $order_info['shipping_type'];
        $order_detail['deduction_money'] = $order_info['deduction_money'];
        $order_detail['membercard_deduction_money'] = $order_info['membercard_deduction_money'];
        $order_detail['store_id'] = $order_info['store_id'];
        //积分兑换订单
        $order_detail['order_point'] = $order_info['point'];
        if ($order_info['store_id']) {
            $order_detail['store_name'] = $order_info['order_pickup']['store_name'];
            $order_detail['store_tel'] = $order_info['order_pickup']['store_tel'];
            $order_detail['receiver_province'] = $order_info['order_pickup']['province_name'];
            $order_detail['receiver_city'] = $order_info['order_pickup']['city_name'];
            $order_detail['receiver_district'] = $order_info['order_pickup']['dictrict_name'];
            $order_detail['receiver_address'] = $order_info['order_pickup']['address'];
        }
        $order_detail['operation'] = []; 
        if (!empty($order_info['operation'])) {
            $operation_array = $order_info['operation'];
            foreach ($operation_array as $k => $v) {
                $order_detail['operation'][] = $v;
            }
            unset($v);
        }
        $order_goods = [];
        $order_info['order_goods'] = objToArr($order_info['order_goods']);//因为有对象和数组在一起
        foreach ($order_info['order_goods'] as $k => $v) {
            $order_goods[$k]['order_goods_id'] = $v['order_goods_id'];
            $order_goods[$k]['goods_id'] = $v['goods_id'];
            $order_goods[$k]['goods_name'] = $v['goods_name'];
            $order_goods[$k]['sku_id'] = $v['sku_id'];
            $order_goods[$k]['sku_name'] = $v['sku_name'];
            $order_goods[$k]['price'] = $v['price'];
            $order_goods[$k]['goods_point'] = $v['goods_point'];
            $order_goods[$k]['num'] = $v['num'];
            $order_goods[$k]['refund_status'] = $v['refund_status'];
            $order_goods[$k]['spec'] = $v['spec'];
            $order_goods[$k]['pic_cover'] = $v['picture_info']['pic_cover'] ? getApiSrc($v['picture_info']['pic_cover']) : '';
            if ($order_info['payment_type'] == 16 || $order_info['payment_type'] == 17 || $order_info['payment_type_presell'] == 16 || $order_info['payment_type_presell'] == 17) {
                $order_detail['promotion_status'] = 1;
            }
            $order_detail['promotion_money'] += round(($v['price'] - $v['actual_price'] + $v['adjust_money']) * $v['num'], 2) + $v['promotion_free_shipping'];
        }
        $order_detail['order_goods'] = $order_goods;
        if ($this->website_id == 4794 || $this->website_id == 1086 || $this->website_id == 18) {
            $order_info['receiver_mobile'] = '演示系统手机已加密';
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $order_detail]);
    }

    /**
     * 订单退款详情
     */
    public function orderRefundDetail()
    {
        $order_goods_id = request()->post('order_goods_id');
        if ($order_goods_id == 0) {
            return json(['code' => -1, 'message' => '没有获取到退款信息']);
        }
        $order_service = new OrderService();
        $info = $order_service->getOrderGoodsRefundInfo($order_goods_id);
        $refund_account_records = $order_service->getOrderRefundAccountRecordsByOrderGoodsId($order_goods_id);
        $remark = ""; // 退款备注，只有在退款成功的状态下显示
        if (!empty($refund_account_records)) {
            if (!empty($refund_account_records['remark'])) {

                $remark = $refund_account_records['remark'];
            }
        }
        $order_goods = new OrderGoods();
        // 退款余额
        $refund_balance = $order_goods->orderGoodsRefundBalance($order_goods_id);
        $data = [
            'refund_balance' => sprintf("%.2f", $refund_balance),
            'order_goods' => $info,
            'remark' => $remark
        ];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 线下支付
     */
    public function orderOffLinePay()
    {
        $order_service = new OrderService();
        $order_id = (int)request()->post('order_id', '');
        $payment_type = (int)request()->post('payment_type', '');//1微信,2支付宝
        $seller_memo = request()->post('seller_memo', '');
        if (empty($order_id) || empty($payment_type)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $res = $order_service->orderOffLinePay($order_id, $payment_type, 0);
        if ($res['code'] > 0) {
            $this->addUserLogByParam('后台操作线下支付', $order_id);
        }
        if ($seller_memo) {
            $memo_data['order_id'] = $order_id;
            $memo_data['uid'] = $this->uid;
            $memo_data['memo'] = $seller_memo;
            $memo_data['create_time'] = time();
            $order_service->addOrderSellerMemoNew($memo_data);
        }

        return json($res);
    }

    /*
     * 订单发货模态框
     */
    public function orderDeliveryModal()
    {
        $order_service = new OrderService();
        $express_service = new ExpressService();
        $address_service = new AddressService();
        $order_id = (int)request()->post('order_id');
        if(!$order_id){
            return json(AjaxReturn(-1006));
        }
        $order_info = $order_service->getOrderDetail($order_id);
        if(!$order_info || $order_info['order_status'] != 1){
            return json(['code' => -1, 'message' => '订单有误']);
        }
        $order_info['goods_type'] = 1;
        $order_info['address'] = $address_service->getAddress($order_info['receiver_province'], $order_info['receiver_city'], $order_info['receiver_district']);
        // 快递公司列表
        $express_company_list = $express_service->getExpressCompanyList(1, 0, $this->website_id, $this->instance_id, ['nr.website_id' => $this->website_id, 'nr.shop_id' => $this->instance_id], '')['data'];
        // 订单商品项
        $order_goods_list = $order_service->getOrderGoods($order_id);
        if ($order_goods_list) {
            $order_info['goods_type'] = $order_goods_list[0]['goods_type'];
            foreach ($order_goods_list as $k => $v) {
                $order_goods_list[$k]['num'] = $v['num'] - $v['delivery_num'];
                $order_goods_list[$k]['image'] = getApiSrc($v['picture_info']['pic_cover']);
                $order_goods_list[$k]['express_no'] = isset($v['express_info']['express_no']) ? $v['express_info']['express_no'] : '';
                $order_goods_list[$k]['status_name'] = $v['supplier_id'] ? '由供应商发货' : ($v['refund_status'] !=0 ? $v['status_name'] : $v['shipping_status_name']);
            }
        }
        $data = [
            'order_info' => [
                'goods_type' => $order_info['goods_type'],
                'receiver_name' => $order_info['receiver_name'],
                'receiver_mobile' => $order_info['receiver_mobile'],
                'address' => $order_info['address'],
                'receiver_address' => $order_info['receiver_address'],
                'shipping_company_id' => $order_info['shipping_company_id']
            ],
            'express_company_list' => array_columns($express_company_list, 'co_id,company_name'),
            'order_goods_list' => array_columns($order_goods_list, 'shipping_status,refund_status,num,supplier_id,shipping_status,order_goods_id,image,goods_name,express_no,status_name,spec')
        ];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /*
     * 修改物流模态框
     */
    public function orderUpdateShippingModal()
    {
        $order_service = new OrderService();
        $express_service = new ExpressService();
        $address_service = new AddressService();
        $order_id = (int)request()->post('order_id', '');
        if(!$order_id){
            return json(AjaxReturn(-1006));
        }
        $order_info = $order_service->getOrderDetail($order_id);
        if(!$order_info || $order_info['order_status'] != 2){
            return json(['code' => -1, 'message' => '订单有误']);
        }
        $order_info['address'] = $address_service->getAddress($order_info['receiver_province'], $order_info['receiver_city'], $order_info['receiver_district']);
        // 快递公司列表
        $express_company_list = $express_service->getExpressCompanyList(1, 0, $this->website_id, $this->instance_id, ['nr.website_id' => $this->website_id, 'nr.shop_id' => $this->instance_id], '')['data'];
        //$package_list = [];
        foreach ($order_info['goods_packet_list'] as $key => $v) {
            
            foreach($v['order_goods_list'] as $kk => $vv){
                $order_info['goods_packet_list'][$key]['order_goods_list'][$kk]['image'] = $vv['picture_info']['pic_cover'];
                $order_info['goods_packet_list'][$key]['order_goods_list'][$kk]['express_no'] = $vv['express_info']['express_no'];
            }
            $order_info['goods_packet_list'][$key]['order_goods_list'] = array_columns($order_info['goods_packet_list'][$key]['order_goods_list'], 'image,goods_name,spec,num,express_no,shipping_status_name');
//            $package_list[$v['express_id']] = [
//                'express_code' => $v['express_code'],
//                'express_company_id' => $v['express_company_id'],
//                'order_goods_list' => $order_info['goods_packet_list'][$key]['order_goods_list']
//            ];
        }
        unset($v);
        $data = [
            'order_info' => [
                'goods_type' => $order_info['goods_type'],
                'receiver_name' => $order_info['receiver_name'],
                'receiver_mobile' => $order_info['receiver_mobile'],
                'address' => $order_info['address'],
                'receiver_address' => $order_info['receiver_address'],
                'shipping_company_id' => $order_info['shipping_company_id'],
                'goods_packet_list' => array_columns($order_info['goods_packet_list'], 'order_goods_list,express_company_id,company_name,express_code,express_id')
            ],
            'express_company_list' => array_columns($express_company_list, 'co_id,company_name'),
//            'package_list' => array_columns($package_list, 'express_code,express_company_id,order_goods_list'),
        ];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }


    /**
     * 订单发货
     */
    public function orderDelivery()
    {

        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        $order_goods_id_array = request()->post('order_goods_id_array', '');
        $express_name = request()->post('express_name', '');
        $shipping_type = request()->post('shipping_type', 1);
        $express_company_id = request()->post('express_company_id', '');
        $express_no = trim(request()->post('express_no', ''));
        $delivery_num = request()->post('delivery_num', '');
        $order_goods_id_array = trim($order_goods_id_array, ',');
        $delivery_num = trim($delivery_num, ','); //发货数量,多个商品之间用，分隔
        $delivery_num = explode(',', $delivery_num);
        foreach ($delivery_num as $k => $v) {
            if ($v <= 0) {
                return json(['code' => -1, 'message' => '发货数量有误']);
            }
        }
        $delivery_num = implode(',', $delivery_num);
        //虚拟商品是不需要物流信息的
        $order_goods_list = $order_service->getOrderGoods($order_id);
        $goods_type = 0;
        if ($order_goods_list) {
            $goods_type = $order_goods_list[0]['goods_type'];
        }
        if (!$express_no && $goods_type != 3) {
            return json(AjaxReturn(0));
        }
        if ($goods_type == 3) {
            $shipping_type = 0; //虚拟商品变更为0，不用物流
        }
        $express_no = str_replace(' ', '', $express_no);
        $memo = request()->post('seller_memo');
        $order_info = $order_service->getOrderDetail($order_id);
        if ($order_info['order_status'] != 1) {
            return json(['code' => -1, 'message' => '操作失败，订单状态已改变']);
        }

        if ($shipping_type == 1) {
            $res = $order_service->orderDelivery($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no, $delivery_num);
            $this->addUserLogByParam("订单发货", $res);
            //发货后将待发货状态改成待收获
        } else {
            $res = $order_service->orderGoodsDelivery($order_id, $order_goods_id_array, $delivery_num);
            $this->addUserLogByParam("订单发货", $res);
        }
        if (!empty($memo)) {
            $data['order_id'] = $order_id;
            $data['memo'] = $memo;
            $data['uid'] = $this->uid;
            $data['create_time'] = time();
            $order_service->addOrderSellerMemoNew($data);
        }
        return json(AjaxReturn($res));
    }
    /*
     * 修改物流信息
     */
    public function updateOrderDelivery()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_service = new OrderService();
        $id = request()->post('id');
        $order_id = request()->post('order_id');
        $express_company_id = request()->post('express_company_id');
        $express_company = request()->post('express_company');
        $express_no = trim(request()->post('express_no', ''));
        if (!$express_no || !$id || !$order_id || !$express_company_id) {
            return json(AjaxReturn(-1006));
        }
        $express_no = str_replace(' ', '', $express_no);
        $express_name = request()->post('express_name');

        $data['express_company_id'] = $express_company_id;
        $data['express_company'] = $express_company;
        $data['express_name'] = $express_name;
        $data['express_no'] = $express_no;
        $result = $order_service->updateDelivery($id, $data);
        unset($data);
        $memo = request()->post('seller_memo');
        if (!empty($memo)) {
            $data['order_id'] = $order_id;
            $data['memo'] = $memo;
            $data['uid'] = $this->uid;
            $data['create_time'] = time();
            $order_service->addOrderSellerMemoNew($data);
        }
        $this->addUserLogByParam("更新订单物流信息", $order_id);
        return json(AjaxReturn($result));
    }

    /**
     * 获取订单修改价格的信息
     */
    public function getAdjustPriceModal()
    {
        $order_id = (int)request()->post('order_id');
        if (!$order_id) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $order_service = new OrderService();
        $order_goods_list = $order_service->getOrderGoods($order_id);
        $order_info = $order_service->getOrderInfo($order_id, 'shipping_money,promotion_free_shipping,pay_money,user_platform_money,shipping_type');
        if($order_info['order_status'] || $order_info['presell_id']){
            return json(['code' => -1, 'message' => '该订单无法调整价格']);
        }
        $newOrderGoods = [];
        if($order_goods_list){
            foreach($order_goods_list as $key => $val){
                $newOrderGoods[$key]['sku_id'] = $val['sku_id'];
                $newOrderGoods[$key]['goods_name'] = $val['goods_name'];
                $newOrderGoods[$key]['spec'] = $val['spec'];
                $newOrderGoods[$key]['actual_price'] = $val['actual_price'];
                $newOrderGoods[$key]['num'] = $val['num'];
                $newOrderGoods[$key]['order_goods_id'] = $val['order_goods_id'];
                $newOrderGoods[$key]['real_money'] = $val['real_money'];
                $newOrderGoods[$key]['adjust_money'] = $val['adjust_money'];
                $newOrderGoods[$key]['picture'] = getApiSrc($val['picture_info']['pic_cover']);
            }
        }
        $data = [
            'order_goods_list' => $newOrderGoods,
            'order_info' => $order_info,
        ];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }


    /**
     * 订单价格调整
     */
    public function orderAdjustMoney()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_id = request()->post('order_id', '');
        $order_goods_id_adjust_array = request()->post('order_goods_id_adjust_array', '');
        $shipping_fee = request()->post('shipping_fee', 0);
        $memo = request()->post('memo');
        if(!$order_id || !$order_goods_id_adjust_array){
            return json(AjaxReturn(-1006));
        }
        $adjust_array = explode(';', $order_goods_id_adjust_array);
        $order_service = new OrderService();
        $order_info = $order_service->getOrderInfo($order_id,'shipping_type,order_status');
        if($order_info['order_status']){
            return json(['code' => -1, 'message' => '该订单不是待付款订单,不允许修改价格']);
        }
        if($order_info['shipping_type'] == 2){
            $shipping_fee = 0;
        }
        $new_adjust_array = [];
        foreach($adjust_array as $key => $val){
            $order_goods = explode(',', $val);
            if(!$order_goods[0] || !$order_goods[1]){
                continue;
            }
            $order_goods_id = $order_goods[0];
            $adjust_money = $order_goods[1];
            if($adjust_money < 0){
                return json(['code' => -1, 'message' => '订单金额有误,请重新填写']);
            }
            $order_goods_info = $order_service->getOrderGoodsInfo($order_goods_id);
            $num = $adjust_money/$order_goods_info['num'] - $order_goods_info['actual_price'];
            $new_adjust_array[$key] = $order_goods_id.','.$num;
        }
        $new_adjust_string = implode(';', $new_adjust_array);//移动端与后台传参不一致,重新组装成后台那种格式
        $res = $order_service->orderMoneyAdjust($order_id, $new_adjust_string, $shipping_fee);
        $this->addUserLogByParam("订单价格调整", $res);
        if (!empty($memo)) {
            $data['order_id'] = $order_id;
            $data['memo'] = $memo;
            $data['uid'] = $this->uid;
            $data['create_time'] = time();
            $order_service->addOrderSellerMemoNew($data);
        }

        return json(AjaxReturn($res));
    }

    /**
     * 卖家同意买家退款申请
     *
     * @return number
     */
    public function orderGoodsRefundAgree()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_id = (int)request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id/a', '');
        $return_id = (int)request()->post('return_id', 0);
        if (empty($order_goods_id)) {
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
        }
        if (empty($order_id) || empty($order_goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsRefundAgree($order_id, $order_goods_id, $return_id);
        $this->addUserLogByParam("卖家同意买家退款申请", $retval);
        // 修改发票状态
        if (getAddons('invoice', $this->website_id, $this->instance_id)) {
            $invoice = new InvoiceService();
            $invoice->updateOrderStatusByOrderId($order_id, 2); //关闭发票状态
        }
        return json(AjaxReturn($retval));
    }

    /**
     * 卖家拒绝本次退款
     *
     * @return Ambigous <number, Exception>
     */
    public function orderGoodsRefuseOnce()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_id = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id/a', '');
        if (empty($order_goods_id)) {
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
        }
        $reason = request()->post('reason');
        if (empty($order_id) || empty($order_goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsRefuseOnce($order_id, $order_goods_id, $reason);
        $this->addUserLogByParam("卖家拒绝本次退款", $retval);
        return json(AjaxReturn($retval));
    }

    /**
     * 卖家确认收货
     *
     * @return Ambigous <number, Exception>
     */
    public function orderGoodsConfirmReceive()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_id = request()->post('order_id');
        $order_goods_id = request()->post('order_goods_id/a');
        if (empty($order_goods_id)) {
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
        }
        if (empty($order_id) || empty($order_goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsConfirmReceive($order_id, $order_goods_id);
        $this->addUserLogByParam("卖家确认收货", $retval);
        return json(AjaxReturn($retval));
    }

    /**
     * 卖家确认退款
     *
     * @return array <Exception, unknown>
     */
    public function orderGoodsConfirmRefund()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        $order_id = request()->post('order_id');
        $password = request()->post('password/a');
        $order_goods_id = request()->post('order_goods_id/a');
        $refundtype = 0;
        if (empty($order_goods_id)) {
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
            $refundtype = 1;
        }
        if (empty($order_id) || empty($order_goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        $order_service = new OrderService();
        if (!empty($password)) {
            $retval = $order_service->orderGoodsConfirmRefunds($order_id, $password, $refundtype);
        } else {
            $retval = $order_service->orderGoodsConfirmRefund($order_id, $order_goods_id, $refundtype);
        }
        if ($retval['code'] == 1) {
            //获取订单商品是否全部发货 余下发生退款 变更订单状态
            $order_detail = $order_service->getOrderDetail($order_id);
            if ($order_detail['order_status'] == 1) {
                //统计订单商品项
                $order_goods_model = new VslOrderGoodsModel();
                $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
                $rtype = 0;
                //获取未发货数量
                $order_goods_pay = $order_goods_model::all(['order_id' => $order_id, 'order_status' => 0]);
                $order_goods_send = $order_goods_model::all(['order_id' => $order_id, 'order_status' => 2]);
                if (count($order_goods_info) > 1 && count($order_goods_send) >= 1 && (count($order_goods_send) + count($order_goods_pay) == count($order_goods_info))) {
                    //??怎么查询到是否为最后一项
                    foreach ($order_goods_info as $key => $value) {
                        if ($value['order_status'] == 0 && $value['refund_status'] == 0) {
                            //存在未处理商品 忽略操作
                            $rtype = 1;
                        }
                    }
                    if ($rtype == 0) {
                        //更新订单状态
                        $order_model = new VslOrderModel();
                        $order_model->save(['order_status' => 2], ['order_id' => $order_id]);
                    }
                }
            }

            $this->addUserLogByParam("卖家确认退款", $order_id);
        }
        // 修改发票状态
        if (getAddons('invoice', $this->website_id, $this->instance_id)) {
            $invoice = new InvoiceService();
            $invoice->updateOrderStatusByOrderId($order_id, 2); //关闭发票状态
        }
        return json($retval);
    }

    /**
     * 添加备注
     */
    public function addMemo()
    {
        $order_service = new OrderService();
        $data['order_id'] = request()->post('order_id');
        $data['memo'] = request()->post('memo');
        if(!$data['order_id'] || !$data['memo']){
            return json(AjaxReturn(-1006));
        }
        $data['uid'] = $this->uid;
        $data['create_time'] = time();
        $result = $order_service->addOrderSellerMemoNew($data);
        $this->addUserLogByParam("添加备注", $result);
        return json(AjaxReturn($result));
    }


    /**
     * 获取修改收货地址的信息
     *
     * @return string
     */
    public function getOrderUpdateAddress()
    {
        $order_service = new OrderService();
        $order_id = request()->post('order_id');
        $res = $order_service->getOrderReceiveDetail($order_id);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $res]);
        ;
    }

    /**
     * 修改收货地址的信息
     *  m
     * @return string
     */
    public function updateOrderAddress()
    {
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        $receiver_name = request()->post('receiver_name', '');
        $receiver_mobile = request()->post('receiver_mobile', '');
        $receiver_zip = request()->post('receiver_zip', '');
        $receiver_province = request()->post('seleAreaNext', '');
        $receiver_city = request()->post('seleAreaThird', '');
        $receiver_district = request()->post('seleAreaFouth', '');
        $receiver_address = request()->post('address_detail', '');
        $res = $order_service->updateOrderReceiveDetail($order_id, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name);
        $this->addUserLogByParam("修改收货地址的信息", $order_id);
        return json(AjaxReturn($res));
    }

    /**
     * 收货
     */
    public function orderTakeDelivery()
    {
        if ($this->merchant_expire == 1) {
            return json(AjaxReturn(-1));
        }
        //查询是否存在售后订单未完成 refund_status 0 正常状态 1已提交申请 -3已拒绝 4同意退款 -3拒绝打款  5同意打款而且关闭订单
        $order_service = new OrderService();
        $order_id = request()->post('order_id', '');
        $order_info = $order_service->getOrderInfo($order_id, 'order_status');
        if($order_info['order_status'] != 2){
            return json(['code' => -1, 'message' => '操作失败，订单非待收货状态']);
        }
        $check_status = $order_service->checkReturn($order_id);
        if ($check_status == true) {
            return json(['code' => -1, 'message' => '操作失败，订单存在进行中的售后']);
        }

        $res = $order_service->OrderTakeDelivery($order_id);
        $this->addUserLogByParam("收货", $order_id);
        return json(AjaxReturn($res));
    }

    /**
     * 删除订单
     */
    public function deleteOrder()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        if (request()->isAjax()) {
            $order_service = new OrderService();
            $order_id = request()->post("order_id", "");
            $res = $order_service->deleteOrder($order_id, 1, $this->instance_id);
            $this->addUserLogByParam("删除订单", $order_id);
            return json(AjaxReturn($res));
        }
    }


    public function bonusMember()
    {
        $bonus = new VslOrderBonusLogModel();
        $user = new UserModel();
        $member = new VslMemberModel();
        $agent = new VslAgentLevelModel();
        $order_id = (int)request()->post("order_id", 0);
	if(!$order_id){
            return json(AjaxReturn(-1006));
        }
        $type = request()->post("type", 0);
        $condition = ['order_id'=>$order_id];
        $details = '';
        switch ($type) {
            case 1:
                $condition['global_bonus'] = ['>', 0];
                $details = 'global_bonus_details';
                break;
            case 2:
                $condition['area_bonus'] = ['>', 0];
                $details = 'area_bonus_details';
                break;
            case 3:
                $condition['team_bonus'] = ['>', 0];
                $details = 'team_bonus_details';
                break;
            default:
                break;
        }
        $list = [];
        $bonusLog = $bonus->getQuery($condition,'*');
        if(!$bonusLog || !$details){
            return $list;
        }
        foreach($bonusLog as $val){
            $bonusDetails =  json_decode(htmlspecialchars_decode($val[$details]),true);
            if(!$bonusDetails || !is_array($bonusDetails)){
                continue;
            }
            foreach($bonusDetails as $uid => $memberBonus){
                if(isset($list[$uid])){
                    $list[$uid]['bonus'] += $memberBonus['bonus']; 
                }else{
                    $list[$uid]['bonus'] = $memberBonus['bonus']; 
                    $list[$uid]['level_award'] = $memberBonus['level_award']; 
                    $user_info = $user->getInfo(['uid'=>$uid],'user_headimg,user_tel,user_name,nick_name');
                    $member_info = $member->getInfo(['uid'=>$uid],'global_agent_level_id,area_agent_level_id,team_agent_level_id');
                    if($type==1){
                        $list[$uid]['level_name'] = $agent->getInfo(['id'=>$member_info['global_agent_level_id']],'level_name')['level_name'];
                    }
                    if($type==2){
                        $list[$uid]['level_name'] = $agent->getInfo(['id'=>$member_info['area_agent_level_id']],'level_name')['level_name'];
                    }
                    if($type==3){
                        $list[$uid]['level_name'] = $agent->getInfo(['id'=>$member_info['team_agent_level_id']],'level_name')['level_name'];
                    }
                    $list[$uid]['user_headimg'] =$user_info['user_headimg'];
                    $list[$uid]['user_name'] = $user_info['user_name']?:($user_info['nick_name']?:$user_info['user_tel']);
                    $list[$uid]['user_tel'] =$user_info['user_tel'];
                    $list[$uid]['uid'] = $uid;
                }
            }
            unset($memberBonus);
        }
        unset($val);
        $list = array_merge($list);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }
    
    public function commissionMember(){
        $order_id = (int) request()->post('order_id');
        if(!$order_id){
            return json(AjaxReturn(-1006));
        }
        $order_service = new OrderService();
        $result = $order_service->getCommissionMember($order_id);
        return json(['code' => 1,'message' => '获取成功', 'data' => $result]);
    }
    
    /**
     * 商家地址
     */
    public function getShopReturnList() {
        $order_service = new OrderService();
        $list= $order_service->getShopReturnList($this->instance_id, $this->website_id);
        return json(['code' => 1,'message' => '获取成功', 'data' => $list]);
    }
}
