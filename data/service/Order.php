<?php

namespace data\service;
/**
 * 订单
 */

use addons\abroadreceivegoods\model\VslCountryListModel;
use addons\bargain\service\Bargain;
use addons\blockchain\model\VslBlockChainRecordsModel;
use addons\blockchain\service\Block;
use addons\bonus\model\VslOrderBonusLogModel;
use addons\channel\model\VslChannelGoodsSkuModel;
use addons\channel\model\VslChannelModel;
use addons\channel\model\VslChannelOrderGoodsModel;
use addons\channel\model\VslChannelOrderModel;
use addons\channel\model\VslChannelOrderSkuRecordModel;
use addons\channel\server\Channel;
use addons\coupontype\model\VslCouponModel;
use addons\customform\server\Custom as CustomSer;
use addons\erphelper\model\VslErpHelperModel;
use addons\erphelper\model\VslErpHelperTaskModel;
use addons\integral\model\VslIntegralGoodsModel;
use addons\integral\model\VslIntegralGoodsSkuModel;
use addons\invoice\server\Invoice as InvoiceService;
use addons\liveshopping\model\OrderAnchorEarningsModel;
use addons\liveshopping\service\Liveshopping;
use addons\membercard\server\Membercard as MembercardSer;
use addons\merchants\server\Merchants;
use addons\microshop\service\MicroShop as MicroShopService;
use addons\receivegoodscode\server\ReceiveGoodsCode as ReceiveGoodsCodeSer;
use addons\shop\model\VslShopAccountRecordsModel;
use addons\shop\service\Shop;
use addons\store\server\Store as storeServer;
use addons\supplier\model\VslSupplierModel;
use data\model\AlbumPictureModel;
use data\model\CityModel;
use data\model\DistrictModel;
use data\model\OrderTradeRecordModel;
use data\model\SysAddonsModel;
use data\model\UserModel;
use data\model\VslAccountRecordsModel;
use data\model\VslCartModel;
use data\model\VslGoodsDeletedModel;
use data\model\VslGoodsEvaluateModel;
use data\model\VslGoodsModel;
use data\model\VslGoodsSkuModel;
use data\model\VslGoodsSpecValueModel;
use data\model\VslIncreMentOrderPaymentModel;
use data\model\VslIncreMentOrderModel;
use data\model\VslMemberAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberModel;
use data\model\VslOrderCalculateModel;
use data\model\VslOrderExpressCompanyModel;
use data\model\VslOrderGoodsExpressModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderMemoModel;
use data\model\VslOrderModel;
use data\model\VslOrderPaymentModel;
use data\model\VslOrderShopReturnModel;
use addons\presell\model\VslPresellModel;
use addons\discount\model\VslPromotionDiscountModel;
use data\model\VslOrderTeamLogModel;
use data\model\VslPromotionMansongRuleModel;
use data\model\VslGoodsTicketModel;
use addons\shop\model\VslShopModel;
use data\model\ProvinceModel;
use data\model\VslStoreCartModel;
use data\model\VslStoreGoodsSkuModel;
use data\service\AddonsConfig as AddonsConfigSer;
use data\service\Config;
use data\service\Goods as GoodsService;
use data\service\GoodsCalculate\GoodsCalculate;
use data\service\Order\Order as OrderBusiness;
use data\service\Order\OrderAccount;
use data\service\Order\OrderExpress;
use data\service\Order\OrderGoods;
use data\service\Order\OrderStatus;
use data\service\Pay\tlPay;
use data\service\Pay\WeiXinPay;
use data\service\promotion\GoodsExpress;
use data\service\promotion\GoodsPreference;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use think\Db;
use think\Log;
use data\model\VslOrderRefundAccountRecordsModel;
use addons\distribution\model\VslOrderDistributorCommissionModel;
use addons\bonus\model\VslOrderBonusModel;
use addons\presell\service\Presell as PresellService;
use addons\seckill\server\Seckill as SeckillServer;
use data\model\VslShopEvaluateModel;
use think\Request;
use think\Session;
use addons\groupshopping\server\GroupShopping;
use addons\store\server\Store;
use addons\paygift\server\PayGift;
use addons\store\model\VslStoreModel;
use addons\store\model\VslStoreAssistantModel;
use addons\microshop\model\VslOrderMicroShopProfitModel;
use data\model\VslOrderPromotionDetailsModel;
use data\model\VslOrderGoodsPromotionDetailsModel;
use addons\distribution\service\Distributor as  DistributorService;
use addons\invoice\server\Invoice as InvoiceServer;
use addons\agent\service\Agent as agent;
use addons\agent\model\VslAgentOrderModel;
use data\service\Pay\GlobePay;
use data\model\VslShopOrderCheckModel;
use addons\distribution\model\VslDistributorLevelModel;
use data\service\WebSite as WebSite;
use data\model\VslOrderScheduleModel as VslOrderSchedule;
use addons\systemform\server\Systemform as CustomFormServer;
use data\service\Pay\Joinpay;
use addons\luckyspell\server\Luckyspell as luckySpellServer;
class Order extends BaseService
{


    const VERIFICATION_QRCODE_2 = '==>verification_qrcode2<==';

    function __construct()
    {
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
        parent::__construct();

    }

    /*
     * 订单商品信息
     */
    public function getOrderGoodsData ($condition, $field='*')
    {
        $order_goods_model = new VslOrderGoodsModel();
        return $order_goods_model->getQuery($condition, $field);
    }

    /*
     * (non-PHPdoc)订单详情
     * @see \data\api\IOrder::getOrderDetail()
     */
    public function getOrderDetail($order_id, $channel_status = '')
    {
        // 查询主表信息
        $order = new OrderBusiness();
        $detail = $order->getDetail($order_id, $channel_status);
        if (empty($detail)) {
            return array();
        }
        //查询订单分红
        if (getAddons('globalbonus', $this->website_id) || getAddons('teambonus', $this->website_id) || getAddons('areabonus', $this->website_id)) {
            $order_bonus = new VslOrderBonusLogModel();
            if($this->instance_id > 0){
                $detail['global_bonus'] = $order_bonus->getSum(['order_id' => $order_id, 'global_return_status' => 0, 'shop_id' => $this->instance_id], 'global_bonus');
                $detail['area_bonus'] = $order_bonus->getSum(['order_id' => $order_id, 'area_return_status' => 0,'shop_id' => $this->instance_id], 'area_bonus');
                $detail['team_bonus'] = $order_bonus->getSum(['order_id' => $order_id, 'team_return_status' => 0,'shop_id' => $this->instance_id], 'team_bonus');
            }else{
                $detail['global_bonus'] = $order_bonus->getSum(['order_id' => $order_id,'global_return_status' => 0], 'global_bonus');
                $detail['area_bonus'] = $order_bonus->getSum(['order_id' => $order_id, 'area_return_status' => 0], 'area_bonus');
                $detail['team_bonus'] = $order_bonus->getSum(['order_id' => $order_id, 'team_return_status' => 0], 'team_bonus');
            }

            $order_team_bonus = new VslOrderTeamLogModel();
            $sum_team_bonus = $order_team_bonus->getSum(['order_id' => $order_id], 'team_bonus');
            $detail['team_bonus'] += $sum_team_bonus;
        }
        // debugLog($this->website_id, '==>店铺佣金账户订单website_id<==');
        //查询订单佣金
        if (getAddons('distribution', $this->website_id)) {
            $order_commission = new VslOrderDistributorCommissionModel();
            $member = new UserModel();
            if($this->instance_id > 0){
                $orders = $order_commission->Query(['order_id' => $order_id,'return_status' => 0,'shop_id' => $this->instance_id], '*');
            }else{
                $orders = $order_commission->Query(['order_id' => $order_id,'return_status' => 0], '*');
            }
            // debugLog($orders, '==>店铺佣金账户订单orders<==');
            foreach ($orders as $key1 => $value) {
                if ($value['commissionA_id']) {
                    $detail['commissionA_id'] = $value['commissionA_id'];
                    $commissionA_info = $member->getInfo(['uid' => $value['commissionA_id']], '*');
                    if ($commissionA_info['user_name']) {
                        $detail['commissionA_name'] = $commissionA_info['user_name'];
                    } else {
                        $detail['commissionA_name'] = $commissionA_info['nick_name'];
                    }
                    $detail['commissionA_user_headimg'] = $commissionA_info['user_headimg'];
                    $detail['commissionA_mobile'] = $commissionA_info['user_tel'];
                    $detail['commissionA'] += $value['commissionA'];
                    $detail['pointA'] += $value['pointA'];
                    $detail['beautiful_pointA'] += $value['beautiful_pointA'];
                }
                if ($value['commissionB_id']) {
                    $detail['commissionB_id'] = $value['commissionB_id'];
                    $commissionB_info = $member->getInfo(['uid' => $value['commissionB_id']], '*');
                    if ($commissionB_info['user_name']) {
                        $detail['commissionB_name'] = $commissionB_info['user_name'];
                    } else {
                        $detail['commissionB_name'] = $commissionB_info['nick_name'];
                    }
                    $detail['commissionB_user_headimg'] = $commissionB_info['user_headimg'];
                    $detail['commissionB_mobile'] = $commissionB_info['user_tel'];
                    $detail['commissionB'] += $value['commissionB'];
                    $detail['pointB'] += $value['pointB'];
                    $detail['beautiful_pointB'] += $value['beautiful_pointB'];
                }
                if ($value['commissionC_id']) {
                    $detail['commissionC_id'] = $value['commissionC_id'];
                    $commissionC_info = $member->getInfo(['uid' => $value['commissionC_id']], '*');
                    if ($commissionC_info['user_name']) {
                        $detail['commissionC_name'] = $commissionC_info['user_name'];
                    } else {
                        $detail['commissionC_name'] = $commissionC_info['nick_name'];
                    }
                    $detail['commissionC_user_headimg'] = $commissionC_info['user_headimg'];
                    $detail['commissionC_mobile'] = $commissionC_info['user_tel'];
                    $detail['commissionC'] += $value['commissionC'];
                    $detail['pointC'] += $value['pointC'];
                    $detail['beautiful_pointC'] += $value['beautiful_pointC'];
                }
            }
            unset($value);
        }
        if (getAddons('microshop', $this->website_id, $this->instance_id)) {
            $order_profit = new VslOrderMicroShopProfitModel();
            $member = new UserModel();
            $orders = $order_profit->Query(['order_id' => $order_id,'return_status' => 0], '*');
            foreach ($orders as $key1 => $value) {
                if ($value['profitA_id']) {
                    $detail['profitA_id'] = $value['profitA_id'];
                    $profitA_info = $member->getInfo(['uid' => $value['profitA_id']], '*');
                    if ($profitA_info['user_name']) {
                        $detail['profitA_name'] = $profitA_info['user_name'];
                    } else {
                        $detail['profitA_name'] = $profitA_info['nick_name'];
                    }
                    $detail['profitA_user_headimg'] = $profitA_info ['user_headimg'];
                    $detail['profitA_mobile'] = $profitA_info['user_tel'];
                    $detail['profitA'] += $value['profitA'];
                    $detail['pointA'] += $value['pointA'];
                }
                if ($value['profitB_id']) {
                    $detail['profitB_id'] = $value['profitB_id'];
                    $profitB_info = $member->getInfo(['uid' => $value['profitB_id']], '*');
                    if ($profitB_info['user_name']) {
                        $detail['profitB_name'] = $profitB_info['user_name'];
                    } else {
                        $detail['profitB_name'] = $profitB_info['nick_name'];
                    }
                    $detail['profitB_user_headimg'] = $profitB_info['user_headimg'];
                    $detail['profitB_mobile'] = $profitB_info['user_tel'];
                    $detail['profitB'] += $value['profitB'];
                    $detail['pointB'] += $value['pointB'];
                }
                if ($value['profitC_id']) {
                    $detail['profitC_id'] = $value['profitC_id'];
                    $profitC_info = $member->getInfo(['uid' => $value['profitC_id']], '*');
                    if ($profitC_info['user_name']) {
                        $detail['profitC_name'] = $profitC_info['user_name'];
                    } else {
                        $detail['profitC_name'] = $profitC_info['nick_name'];
                    }
                    $detail['profitC_user_headimg'] = $profitC_info['user_headimg'];
                    $detail['profitC_mobile'] = $profitC_info['user_tel'];
                    $detail['profitC'] += $value['profitC'];
                    $detail['pointC'] += $value['pointC'];
                }
            }
            unset($value);
        }
        $detail['pay_status_name'] = $this->getPayStatusInfo($detail['pay_status'])['status_name'];
        $detail['shipping_status_name'] = $this->getShippingInfo($detail['shipping_status'])['status_name'];
        $detail['order_type_name'] = OrderStatus::getOrderType($detail['order_type']) ?: '商品订单';
        $express_list = $this->getOrderGoodsExpressList($order_id);
        // 未发货的订单项
        $order_goods_list = array();
        // 已发货的订单项
        $order_goods_delive = array();
        // 没有配送信息的订单项
        $order_goods_exprss = array();
        $detail['order_adjust_money'] = 0;// 订单金额调整
        $detail['goods_type'] = 0;
        if ($detail['order_goods']) {
            $detail['goods_type'] = $detail["order_goods"][0]['goods_type'];
        }
        $temp_receive_goods_code_deduct_all = 0;
        $temp_receive_goods_code = 0;
        $temp_invoice_tax = 0;
        foreach ($detail["order_goods"] as $order_goods_obj) {
            $detail['order_adjust_money'] += $order_goods_obj['adjust_money'] * $order_goods_obj['num'];
//            $order_goods_obj['order_goods_promotion_money'] = ($order_goods_obj['price'] - $order_goods_obj['actual_price'] + $order_goods_obj['adjust_money']) * $order_goods_obj['num'] + $order_goods_obj['receive_goods_code_deduct'];
            $order_goods_obj['order_goods_promotion_money'] = ($order_goods_obj['price'] - $order_goods_obj['actual_price'] + $order_goods_obj['adjust_money']) * $order_goods_obj['num'] - $order_goods_obj['membercard_deduction_money'] - $order_goods_obj['receive_goods_code_deduct'];
            if($order_goods_obj['invoice_tax']){
                $order_goods_obj['order_goods_promotion_money'] = bcadd($order_goods_obj['order_goods_promotion_money'],$order_goods_obj['invoice_tax'],2);
            }
            $order_goods_obj['order_goods_promotion_money'] = $order_goods_obj['order_goods_promotion_money']>0?$order_goods_obj['order_goods_promotion_money']:0;
            if ($order_goods_obj['presell_id']) {
                $order_goods_obj['order_goods_promotion_money'] = 0;
            }
            if ($order_goods_obj["shipping_status"] == 0 && $order_goods_obj['delivery_num'] == 0) {
                // 未发货
                $order_goods_list[] = $order_goods_obj;
            } else {
                $order_goods_delive[] = $order_goods_obj;
            }
            $temp_receive_goods_code_deduct_all += $order_goods_obj['receive_goods_code_deduct'];
            $temp_receive_goods_code = $order_goods_obj['receive_goods_code_deduct'];
            $temp_invoice_tax += $order_goods_obj['invoice_tax'];
        }

        $detail['invoice_tax'] = $temp_invoice_tax;//总税费
        unset($order_goods_obj);

        // 订单优惠金额
        // $n4 = bcsub($n1,$n2,2); //php高精度运算
        $detail['order_promotion_money'] = bcsub(($detail['goods_money'] + $detail['shipping_money'] + $detail['order_adjust_money']),($detail['promotion_free_shipping'] + $detail['order_money']),2);

        //        $detail['order_promotion_money'] = bcsub(($detail['goods_money'] + $detail['shipping_money'] + $detail['order_adjust_money']),($detail['promotion_free_shipping'] + $detail['order_money']),2);
        //领货码
        $detail['order_promotion_money'] = bcsub($detail['order_promotion_money'],$temp_receive_goods_code_deduct_all,2);
        //税费
        if(isset($detail['invoice_tax'])){
            $detail['order_promotion_money'] = bcadd($detail['order_promotion_money'], $detail['invoice_tax'],2);
        }

        //积分抵扣
        if($detail['deduction_money']>0){
//            $detail['order_promotion_money'] = "{$detail['order_promotion_money']}" - "{$detail['deduction_money']}";
            $detail['order_promotion_money'] = bcsub($detail['order_promotion_money'],$detail['deduction_money'], 2);
        }
        //会员卡
        if (isset($detail['membercard_deduction_money'])){
            $detail['order_promotion_money'] = bcsub($detail['order_promotion_money'], $detail['membercard_deduction_money'],2);
        }
        # 最后优惠价格
        $detail['order_promotion_money'] = $detail['order_promotion_money']>0?$detail['order_promotion_money']:0;
        $detail["order_goods_no_delive"] = $order_goods_list;
        // 没有配送信息的订单项
        if (!empty($order_goods_delive) && count($order_goods_delive) > 0) {
            foreach ($order_goods_delive as $goods_obj) {
                $is_have = false;
                $order_goods_id = $goods_obj["order_goods_id"];
                foreach ($express_list as $express_obj) {
                    $order_goods_id_array = $express_obj["order_goods_id_array"];
                    $goods_id_str = explode(",", $order_goods_id_array);
                    if (in_array($order_goods_id, $goods_id_str)) {
                        $is_have = true;
                    }
                }
                unset($express_obj);
                if (!$is_have) {
                    $order_goods_exprss[] = $goods_obj;
                }
            }
            unset($goods_obj);
        }
        $goods_packet_list = array();
        if (count($order_goods_exprss) > 0) {
            $packet_obj = array(
                "packet_name" => "无需物流",
                "express_name" => "",
                "express_code" => "",
                "express_id" => 0,
                "is_express" => 0,
                "order_goods_list" => $order_goods_exprss
            );
            $goods_packet_list[] = $packet_obj;
        }
        if (!empty($express_list) && count($express_list) > 0 && count($order_goods_delive) > 0) {
            $packet_num = 1;
            foreach ($express_list as $express_obj) {
                //供应商端过滤非供应商包裹
                if ($this->model=='supplier' && $express_obj['order_goods_id_array']){
                    $supplierGoodsRes = $this->getOrderGoodsData(['order_goods_id'=> ['in',$express_obj['order_goods_id_array']]],'supplier_id');
                    if (current($supplierGoodsRes)['supplier_id'] != $this->supplier_id){
                        continue;
                    }
                }
                $packet_goods_list = array();
                $order_goods_id_array = $express_obj["order_goods_id_array"];
                $goods_id_str = explode(",", $order_goods_id_array);
                foreach ($order_goods_delive as $delive_obj) {
                    $order_goods_id = $delive_obj["order_goods_id"];
                    if (in_array($order_goods_id, $goods_id_str)) {
                        $packet_goods_list[] = $delive_obj;
                    }
                }
                unset($delive_obj);
                $packet_obj = array(
                    'packet_name' => '包裹' . $packet_num,
                    'express_name' => $express_obj['express_name'],
                    'express_company_id' => $express_obj['express_company_id'],
                    'express_code' => $express_obj['express_no'],
                    'express_id' => $express_obj['id'],
                    'is_express' => 1,
                    'order_goods_list' => $packet_goods_list
                );
                //获取收货人后四位手机号
                $receiver_phone = substr($detail['receiver_mobile'], 7);
                $shipping_info = getShipping($express_obj['express_no'], $express_obj['express_company_id'], 'auto', $this->website_id, $receiver_phone);
                if ($shipping_info['code'] == 1) {
                    $packet_obj['shipping_info'] = $shipping_info['data'];
                }else{
					$packet_obj['shipping_info'] = ['mailNo'=>$packet_obj['express_code'],'expTextName'=>$packet_obj['express_name']];
				}
                $packet_num = $packet_num + 1;
                $goods_packet_list[] = $packet_obj;
            }
            unset($express_obj);
        }
        $detail["goods_packet_list"] = $goods_packet_list;
        $detail["goods_packet_num"] = count($goods_packet_list);
        $memo_model = new VslOrderMemoModel();
        $memo_lists = $memo_model::all(['order_id' => $order_id], ['user']);
        $detail['memo_lists'] = [];
        foreach ($memo_lists as $k => $v) {
            $memo_data['order_memo_id'] = $v['order_memo_id'];
            $memo_data['order_id'] = $v['order_id'];
            $memo_data['memo'] = $v['memo'];
            $memo_data['create_time'] = $v['create_time'];
            $memo_data['create_date'] = date('Y-m-d H:i:s', $v['create_time']);
            $memo_data['uid'] = $v['uid'];
            $memo_data['user_name'] = $v['user']['user_name'];
            //取回的数据以id顺序，所以在数组头部插入数据让最新的数据出现在头部
            array_unshift($detail['memo_lists'], $memo_data);
        }
        unset($v);
        $detail['unrefund'] = 0;
        $detail['unrefund_reason'] = '';
        if (getAddons('groupshopping', $this->website_id) && $detail['group_record_id']) {
            $groupServer = new GroupShopping();
            $record = $groupServer->groupRecordDetail($detail['group_record_id']);
            if ($record['status'] == 0) {
                $detail['unrefund'] = 1;//待成团订单不能退款
                $detail['unrefund_reason'] = '拼团订单暂时无法退款，若在' . time_diff(time(), $record['finish_time']) . '未成团，将自动退款！';//成团时限
            }
        }
        if (getAddons('luckyspell', $this->website_id) && $detail['luckyspell_id']) {
            $luckySpellServer = new luckySpellServer();
            $record = $luckySpellServer->groupluckySpellRecordDetail($detail['order_id']);
            if ($record['status'] == 0) {
                $order_list['data'][$k]['unrefund'] = 1;//待成团订单不能退款
                $order_list['data'][$k]['unrefund_reason'] = '幸运拼订单无法主动申请退款,未中奖会自动退款';
            }
            $isGroupSuccess = $record['status'];
        }
        //货到付款订单，在未确认收货情况下不能申请退款退货
        if($detail['order_status'] != 5){
            if($detail['payment_type']==4 && ($detail['order_status']<3 || $detail['order_status']>4) && $detail['order_status'] != -1){
                $detail['unrefund'] = 1;
                $detail['unrefund_reason'] = '货到付款订单，在未确认收货情况下不能申请退款退货！';
            }
        }
        //系统表单
        if (getAddons('customform', $this->website_id) && $detail['goods_type'] != 6) {
            $addConSer = new AddonsConfigSer();
            $addinfo = $addConSer->getAddonsConfig('customform',$this->website_id);
            if ($addinfo['value']){
                $customSer = new CustomSer();
                $info = $customSer->getCustomData(1,10,1,'',['nm.order_id'=>$order_id]);

                $detail['custom_list'] = $info;
            }
        }else if(getAddons('systemform', $this->website_id) && $detail['goods_type'] == 6){
            $customFormServer = new CustomFormServer();
            $info = $customFormServer->getCustomData(1,10,1,'',$order_id);

            $detail['custom_list'] = $info;
        }

        //领货码
        if (getAddons('receivegoodscode',$this->website_id,$this->instance_id)){
            $detail['receive_goods_code_deduct'] = $detail['receive_goods_code_deduct_all'] = $temp_receive_goods_code_deduct_all;
            $detail['receive_goods_code'] = $temp_receive_goods_code;
//            $detail['order_promotion_money'] -= $temp_receive_goods_code_deduct_all;
//            $detail['order_money'] -= $detail['receive_goods_code_deduct_all'];//显示去除领货码抵扣值
        }
        if ($this->model == 'supplier'){
            $supplier_money = 0;
            $supplier_shipping_fee = 0;
            foreach ($detail["order_goods"] as $order_goods_obj){
                $supplier_money += ($order_goods_obj['supplier_money']-$order_goods_obj['shipping_fee']);
                $supplier_shipping_fee += $order_goods_obj['shipping_fee'];
            }
            $detail['supplier_order_money'] = $supplier_money >0?$supplier_money:0;
            $detail['promotion_free_shipping'] = 0;//供应商端html不需要
            $detail['shipping_money'] = $supplier_shipping_fee;
        }
        //预售优惠金额为0
        if ($detail['presell_id'] > 0) {
            $detail['order_promotion_money'] = 0;
        }

        return $detail;
        // TODO Auto-generated method stub
    }

    /**
     * 获取订单基础信息
     *
     * @param unknown $order_id
     */
    public function getOrderInfo($order_id, $field='*')
    {
        $order_model = new VslOrderModel();
        $order_info = $order_model->getInfo(['order_id' => $order_id],$field);
        return $order_info;
    }

    public function getIncrementOrderInfo($order_id)
    {
        $order_model = new VslIncreMentOrderModel();
        $order_info = $order_model->getInfo(['order_id' => $order_id]);
        return $order_info;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getOrderList()
     */
    public function getOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $this->website_id = $this->website_id ? $this->website_id : $condition['website_id'];
        $wapStore = false;
        if ($condition['wapstore']) {
            $wapStore = true;
            unset($condition['wapstore']);
        }
        //导出订单的查询标记
        $isExcel = false;
        if ($condition['is_excel']) {
            $isExcel = true;
            unset($condition['is_excel']);
        }
        //过滤售后商品,导出订单用
        $filter = false;
        if ($condition['filter']) {
            $filter = true;
            unset($condition['filter']);
        }
        $order_model = new VslOrderModel();
        $orderGoodsModel = new VslOrderGoodsModel();
        $goodsSer = new GoodsService();
        //如果有订单表以外的字段，则先按条件查询其他表的orderid，并取出数据的交集，组装到原有查询条件里
        $query_order_ids = 'uncheck';
        $un_query_order_ids = array();
        $checkOthers = false;
        $isGroup = false;
        if ($condition['express_no']) {
            $checkOthers = true;
            $expressNo = $condition['express_no'];
            $orderGoodsExpressModel = new VslOrderGoodsExpressModel();
            $orderGoodsExpressList = $orderGoodsExpressModel->pageQuery($page_index, $page_size, ['express_no' => $expressNo, 'website_id' => $condition['website_id']], '', 'order_id');
            unset($condition['express_no']);
            $express_order_ids = array();
            if ($orderGoodsExpressList['data']) {
                foreach ($orderGoodsExpressList['data'] as $keyEx => $valEx) {
                    $express_order_ids[] = $valEx['order_id'];
                }
                unset($valEx);
            }
            $query_order_ids = $express_order_ids;
        }

        // 接口用
        if ($condition['or'] && $condition['goods_name'] && $condition['shop_name']) {
            $checkOthers = true;
            $order_goods_condition = ['website_id' => $this->website_id];
            $goods_condition = [
                'website_id' => $this->website_id,
                'goods_name|code' =>  $condition['goods_name']
            ];

            $goods_name = $goodsSer->getGoodsDetailByCondition($goods_condition, 'goods_name');
            $order_goods_condition['goods_name'] = $goods_name ? $goods_name['goods_name'] : '';

            $orderGoodsList = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition, '', 'order_id');
            $goods_order_ids = array();
            if ($orderGoodsList['data']) {
                foreach ($orderGoodsList['data'] as $keyG => $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }

            $order_condition['website_id'] = $condition['website_id'];
            $order_condition['shop_name'] = $condition['shop_name'];
            $order_list = $order_model->pageQuery(1, 0, $order_condition, '', 'order_id');
            if ($order_list['data']) {
                foreach ($order_list['data'] as $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }
            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $goods_order_ids);
            } else {
                $query_order_ids = $goods_order_ids;
            }
            unset($condition['or'], $condition['goods_name'], $condition['shop_name'], $order_condition, $order_list);
        }
        if ($condition['or'] && $condition['goods_name'] && $condition['goods_id']) {
            $checkOthers = true;
            $order_goods_condition = ['website_id' => $this->website_id];
            $order_goods_condition['goods_name'] = $condition['goods_name'];
            $orderGoodsList = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition, '', 'order_id');
            if ($orderGoodsList['data']) {
                foreach ($orderGoodsList['data'] as $keyG => $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }
            $order_goods_condition2 = ['website_id' => $this->website_id];
            $order_goods_condition2['goods_id'] = $condition['goods_id'];
            $orderGoodsList2 = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition2, '', 'order_id');
            if ($orderGoodsList2['data']) {
                foreach ($orderGoodsList2['data'] as $keyG2 => $valG2) {
                    $goods_order_ids[] = $valG2['order_id'];
                }
                unset($valG2);
            }
            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $goods_order_ids);
            } else {
                $query_order_ids = $goods_order_ids;
            }
            unset($condition['or'], $condition['goods_name'], $condition['goods_id'], $order_goods_condition,$order_goods_condition2, $order_list);
        }
        //移动商家端
        if (isset($condition['shop_name|goods_name|receiver_name|user_name'])) {
            $searchText = $condition['shop_name|goods_name|receiver_name|user_name'];
            $checkOthers = true;
            $goods_order_ids = [];
            $order_goods_condition = ['website_id' => $this->website_id];
            $goods_condition = [
                'website_id' => $this->website_id,
                'goods_name|code' =>  $searchText
            ];
            $goods_name = $goodsSer->getGoodsDetailByCondition($goods_condition, 'goods_name');
            $order_goods_condition['goods_name'] = $goods_name ? $goods_name['goods_name'] : '';
            $orderGoodsList = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition, '', 'order_id');

            if ($orderGoodsList['data']) {
                foreach ($orderGoodsList['data'] as $keyG => $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }

            $nameCondition['website_id'] = $condition['website_id'];
            $nameCondition['shop_name|receiver_name|user_name'] = $searchText;
            $orderListByShop = $order_model->pageQuery(1, 0, $nameCondition, '', 'order_id');
            if ($orderListByShop['data']) {
                foreach ($orderListByShop['data'] as $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }
            $result_order_ids = array_unique($goods_order_ids);

            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $result_order_ids);
            } else {
                $query_order_ids = $result_order_ids;
            }
            unset($condition['shop_name|goods_name|receiver_name|user_name']);
        }
        //print_r($condition);die;
        # 售后订单处理
        if ($condition['goods_name'] || $condition['refund_status']) {
            $checkOthers = true;
            $order_goods_condition = ['website_id' => $this->website_id];
            if ($condition['goods_name']) {
                if(is_numeric($condition['goods_name'])) {
                    //商品编号搜索
                    $goods_condition = [
                        'code' =>  $condition['goods_name'],
                        'website_id' =>  $this->website_id,
                    ];
                    $goods_name = $goodsSer->getGoodsDetailByCondition($goods_condition, 'goods_name');
                    $order_goods_condition['goods_name'] = $goods_name ? $goods_name['goods_name'] : '';
                }else{
                    $order_goods_condition['goods_name'] = $condition['goods_name'];
                }
            }
            if ($condition['refund_status']) {
                if ($condition['refund_status'] == 'backList') {
                    $order_goods_condition['refund_status'] = ['neq', 0];
                } else {
                    $order_goods_condition['refund_status'] = ['IN', $condition['refund_status']];
                    if ($this->model == 'supplier'){
                        $order_goods_condition['supplier_id'] = $this->supplier_id;
                    }
                }
            }
            if ($condition['buyer_id']) {
                $order_goods_condition['buyer_id'] = $condition['buyer_id'];
            }
            $orderGoodsList = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition, '', 'order_id');
            unset($condition['goods_name'], $condition['refund_status']);
            $goods_order_ids = array();
            if ($orderGoodsList['data']) {
                foreach ($orderGoodsList['data'] as $keyG => $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }
            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $goods_order_ids);
            } else {
                $query_order_ids = $goods_order_ids;
            }
        }
        if ($condition['vgsr_status']) {
            $isGroup = true;
            $checkOthers = true;
            $vgsr_status = $condition['vgsr_status'];
            $group_server = new GroupShopping();
            $unGroupOrderIds = $group_server->getPayedUnGroupOrder($this->instance_id, $this->website_id);

            if ($vgsr_status == 1) {
                if ($query_order_ids != 'uncheck') {
                    $query_order_ids = array_intersect($query_order_ids, $unGroupOrderIds);
                } else {
                    $query_order_ids = $unGroupOrderIds;
                }
            }
            if ($vgsr_status == 2 && $unGroupOrderIds) {
                $un_query_order_ids = $unGroupOrderIds;
            }
            unset($condition['vgsr_status']);
        }
        if(getAddons('supplier', $this->website_id)){
            $supplier = new \addons\supplier\server\Supplier();
            $unCheckSupplierOrder = $supplier->unCheckSupplierOrder();
            debugLog($unCheckSupplierOrder, 'unCheckSupplierOrder');
            if($unCheckSupplierOrder){
                if($un_query_order_ids){
                    $un_query_order_ids = array_merge($unCheckSupplierOrder,$un_query_order_ids);
                }else{
                    $un_query_order_ids = $unCheckSupplierOrder;
                }
            }
        }
        if ($checkOthers) {
            if ($query_order_ids != 'uncheck') {
                $condition['order_id'] = ['in', implode(',', $query_order_ids)];
            } elseif ($un_query_order_ids) {
                $condition['order_id'] = ['not in', implode(',', $un_query_order_ids)];
            }
        }elseif($un_query_order_ids){
            $condition['order_id'] = ['not in', implode(',', $un_query_order_ids)];
        }
        $order_memo = false;
        if ($condition['order_memo']) {
            $order_memo = true;
            unset($condition['order_memo']);
        }
        if ($condition['order_amount']) {
            unset($condition['order_amount']);
//            $order_amount = $order_model->getSum($condition, 'order_money');
        }
        // 查询主表
        if($condition['order_status'] && $condition['order_status'] == 9){
            unset($condition['order_status']);
        }
        // 重组订单条件
        $new_condition = array();
        if($condition){
            foreach ($condition as $keys => $values) {
                if($keys === 'user_type'){
                    if($values == 2){
                        $new_condition['su.isdistributor'] = 2;
                    }else if($values == 3){
                        $new_condition['su.is_team_agent'] = 2;
                    }else{
                        $new_condition['su.isdistributor'] = ['<',2];
                    }
                }elseif (is_int($keys)){/*给框架 EXP使用 */
                    $new_condition[$keys] = $values;
                }else{
                    $new_condition['nm.'.$keys] = $values;
                }
            }
            unset($values);
        }

        $order_list = $order_model->getViewList2($page_index, $page_size, $new_condition, $order);
        $shop = getAddons('shop', $this->website_id);
        $globalbonus = getAddons('globalbonus', $this->website_id);
        $areabonus = getAddons('areabonus', $this->website_id);
        $teambonus = getAddons('teambonus', $this->website_id);
        $distribution = getAddons('distribution', $this->website_id);
        $store = getAddons('store', $this->website_id);
        $fullcut = getAddons('fullcut', $this->website_id);
        $coupontype = getAddons('coupontype', $this->website_id);
        $groupshopping = getAddons('groupshopping', $this->website_id, $this->instance_id);
        $luckyspell = getAddons('luckyspell', $this->website_id, $this->instance_id);
        $microshop = getAddons('microshop', $this->website_id, $this->instance_id);
        $all_order_money = 0;//所有订单总金额
        $model = $this->getRequestModel();
        if (!empty($order_list['data'])) {
            //处理订单信息,用于批量操作,减少数据库查询
            $orderListArr = objToArr($order_list['data']);//5.5版本无法从对象获取,所以要转成数组
            $orderIds = dealArray(array_column($orderListArr, 'order_id'), false, true);
            $provinceIds = dealArray(array_column($orderListArr, 'receiver_province'), true, true);
            $cityIds = dealArray(array_column($orderListArr, 'receiver_city'), true, true);
            $districtIds = dealArray(array_column($orderListArr, 'receiver_district'), true, true);
            $shopIds = dealArray(array_column($orderListArr, 'shop_id'), false, true);
            $buyerIds = dealArray(array_column($orderListArr, 'buyer_id'), true, true);
            $orderCommissions = [];
            $orderBonus = [];
            $shopLists = [];
            $orderPromotionList = [];
            if($distribution){
                //批量查找订单结果的佣金
                $orderCommissions = $this->getOrderCommission($orderIds);
            }
            if($microshop){
                //批量查找订单结果的微店收益
                $orderProfits = $this->getOrderProfit($orderIds);
            }
            if ($globalbonus || $areabonus || $teambonus) {
                //批量查找订单结果的分红
                $orderBonus = $this->getOrderBonus($orderIds);
            }
            //批量查找订单结果的物流信息
            $order_express_info = $this->getGoodsExpress($orderIds);
            //批量查找订单结果的省信息,部分订单没有地区信息,不作联表查询
            $orderProvinces = $this->getOrderProvince($provinceIds);
            //批量查找订单结果的市信息
            $orderCitys = $this->getOrderCity($cityIds);
            //批量查找订单结果的区信息
            $orderDistricts = $this->getOrderDistrict($districtIds);
            if($shop){
                //批量查找订单结果的店铺名称
                $shopLists = $this->getOrderShop($shopIds);
            }
            if($fullcut && $isExcel){
                //订单查询批量查找订单结果的店铺名称
                $orderPromotionList = $this->getOrderPromotion($orderIds);
            }
            //批量查找订单结果的购买人信息
            $userList = $this->getOrderBuyer($buyerIds);
            //批量查找订单项
            $orderGoodsList = $this->getOrderGoodsForList($orderIds, $filter);
            foreach ($order_list['data'] as $k => $v) {
                //字符串转整型
                $order_list['data'][$k]['presell_id'] = intval($v['presell_id']);


                $all_order_money += $v['order_money'];
                $orderId = $v['order_id'];
//                if ($v['presell_id'] == 0) {
//                    $order_list['data'][$k]['pay_money'] += $order_list['data'][$k]['invoice_tax'];
//                    $order_list['data'][$k]['order_money'] += $order_list['data'][$k]['invoice_tax'];
//                }
                $order_list['data'][$k]['order_point'] = $v['point'];
                //查询订单是否满足满减送的条件
                $order_list['data'][$k]['promotion_status'] = ($order_list['data'][$k]['promotion_money'] + $order_list['data'][$k]['coupon_money'] > 0) ? 1 : 0;
                //预售的应该是定金加上尾款
                $order_list['data'][$k]['first_money'] = $v['pay_money'];
                if ($v['presell_id'] && $v['money_type'] == 2) {
                    $order_list['data'][$k]['order_money'] = $v['pay_money'] + $v['final_money'];
                    $order_list['data'][$k]['pay_money'] = $v['pay_money'] + $v['final_money'];
                }
                $order_list['data'][$k]['global_bonus'] = 0;
                $order_list['data'][$k]['area_bonus'] = 0;
                $order_list['data'][$k]['team_bonus'] = 0;
                //查询订单分红

                if (isset($orderBonus[$orderId]['sum_global'])) {
                    $order_list['data'][$k]['global_bonus'] = twoDecimal($orderBonus[$orderId]['sum_global']);
                }
                if (isset($orderBonus[$orderId]['sum_area'])) {
                    $order_list['data'][$k]['area_bonus'] = twoDecimal($orderBonus[$orderId]['sum_area']);
                }
                if (isset($orderBonus[$orderId]['sum_team'])) {
                    $order_list['data'][$k]['team_bonus'] = twoDecimal($orderBonus[$orderId]['sum_team']);
                }

                $order_list['data'][$k]['bonus'] = $order_list['data'][$k]['global_bonus'] + $order_list['data'][$k]['area_bonus'] + $order_list['data'][$k]['team_bonus'];
                //查询订单佣金和积分
                $order_list['data'][$k]['commission'] = 0;
                $order_list['data'][$k]['commissionA'] = 0;
                $order_list['data'][$k]['commissionB'] = 0;
                $order_list['data'][$k]['commissionC'] = 0;
                $order_list['data'][$k]['point'] = 0;
                $order_list['data'][$k]['pointA'] = 0;
                $order_list['data'][$k]['pointB'] = 0;
                $order_list['data'][$k]['pointC'] = 0;
                $order_list['data'][$k]['profit'] = 0;
                $order_list['data'][$k]['profitA'] = 0;
                $order_list['data'][$k]['profitB'] = 0;
                $order_list['data'][$k]['profitC'] = 0;
                $order_list['data'][$k]['beautiful_point'] = 0;
                $order_list['data'][$k]['beautiful_pointA'] = 0;
                $order_list['data'][$k]['beautiful_pointB'] = 0;
                $order_list['data'][$k]['beautiful_pointC'] = 0;
                if (isset($orderCommissions[$orderId])) {
                    foreach ($orderCommissions[$orderId] as $key1 => $value) {
                        if ($value['commissionA_id'] == $v['buyer_id']) {
                            $order_list['data'][$k]['commission'] += $value['commissionA'];
                            $order_list['data'][$k]['point'] += $value['pointA'];
                            $order_list['data'][$k]['beautiful_point'] += $value['beautiful_pointA'];
                        }
                        if ($value['commissionB_id'] == $v['buyer_id']) {
                            $order_list['data'][$k]['commission'] += $value['commissionB'];
                            $order_list['data'][$k]['point'] += $value['pointB'];
                            $order_list['data'][$k]['beautiful_point'] += $value['beautiful_pointB'];
                        }
                        if ($value['commissionC_id'] == $v['buyer_id']) {
                            $order_list['data'][$k]['commission'] += $value['commissionC'];
                            $order_list['data'][$k]['point'] += $value['pointC'];
                            $order_list['data'][$k]['beautiful_point'] += $value['beautiful_pointC'];
                        }
                        if ($value['commissionA_id']) {
                            $order_list['data'][$k]['commissionA_id'] = $value['commissionA_id'];
                            $order_list['data'][$k]['commissionA'] += $value['commissionA'];
                            $order_list['data'][$k]['pointA'] += $value['pointA'];
                            $order_list['data'][$k]['beautiful_pointA'] += $value['beautiful_pointA'];
                        }
                        if ($value['commissionB_id']) {
                            $order_list['data'][$k]['commissionB_id'] = $value['commissionB_id'];
                            $order_list['data'][$k]['commissionB'] += $value['commissionB'];
                            $order_list['data'][$k]['pointB'] += $value['pointB'];
                            $order_list['data'][$k]['beautiful_pointB'] += $value['beautiful_pointB'];
                        }
                        if ($value['commissionC_id']) {
                            $order_list['data'][$k]['commissionC_id'] = $value['commissionC_id'];
                            $order_list['data'][$k]['commissionC'] += $value['commissionC'];
                            $order_list['data'][$k]['pointC'] += $value['pointC'];
                            $order_list['data'][$k]['beautiful_pointC'] += $value['beautiful_pointC'];
                        }
                        $order_list['data'][$k]['commission'] = round($order_list['data'][$k]['commissionA'] + $order_list['data'][$k]['commissionB'] + $order_list['data'][$k]['commissionC'],2);
                    }
                    unset($value);
                    $order_list['data'][$k]['commissionA'] = round($order_list['data'][$k]['commissionA'],2);
                    $order_list['data'][$k]['commissionB'] = round($order_list['data'][$k]['commissionB'],2);
                    $order_list['data'][$k]['commissionC'] = round($order_list['data'][$k]['commissionC'],2);
                }
                if (isset($orderProfits[$orderId])) {
                    foreach ($orderProfits[$orderId] as $key1 => $value) {
                        if ($value['profitA_id'] == $v['buyer_id']) {
                            $order_list['data'][$k]['profit'] += $value['profitA'];
                        }
                        if ($value['profitB_id'] == $v['buyer_id']) {
                            $order_list['data'][$k]['profit'] += $value['profitB'];
                        }
                        if ($value['profitC_id'] == $v['buyer_id']) {
                            $order_list['data'][$k]['profit'] += $value['profitC'];
                        }
                        if(!isset($order_list['data'][$k]['profitA_id'])){
                                $order_list['data'][$k]['profitA_id'] = $value['profitA_id'];
                        }
                        $order_list['data'][$k]['profitA'] += $value['profitA'];
                        if(!isset($order_list['data'][$k]['profitB_id'])){
                                $order_list['data'][$k]['profitB_id'] = $value['profitB_id'];
                        }
                        $order_list['data'][$k]['profitB'] += $value['profitB'];
                        if(!isset($order_list['data'][$k]['profitC_id'])){
                                $order_list['data'][$k]['profitC_id'] = $value['profitC_id'];
                        }
                        $order_list['data'][$k]['profitC'] += $value['profitC'];
                        $order_list['data'][$k]['profit'] = $order_list['data'][$k]['profitA'] + $order_list['data'][$k]['profitB'] + $order_list['data'][$k]['profitC'];
                    }
                    unset($value);
                }
                // 查询订单项表
                $order_item_list = objToArr($orderGoodsList[$orderId]);//$order_item->where($orderItemCondition)->select();
                //是否存在退款状态商品
                $order_goods_refund_status = array_column($order_item_list,'refund_status');
                $is_order_goods_refund = array_intersect($order_goods_refund_status,[1,2,3,4]);
//                $supplier_money = array_column($order_item_list,'supplier_money');
                // 查询最新的卖家备注
//                if (isset($order_memo) && $order_memo) {
//                    $order_list['data'][$k]['order_memo'] = $order_memo_model->where(['order_id' => $orderId])->order('order_memo_id DESC')->limit(1)->find()['memo'];
//                }
                //订单省市区
                $order_list['data'][$k]['receiver_province_name'] = isset($orderProvinces[$v["receiver_province"]]) ? $orderProvinces[$v["receiver_province"]]["province_name"] : '';
                $order_list['data'][$k]['receiver_city_name'] = isset($orderCitys[$v["receiver_city"]]) ? $orderCitys[$v["receiver_city"]]["city_name"] : '';
                $order_list['data'][$k]['receiver_district_name'] = isset($orderDistricts[$v["receiver_district"]]) ? $orderDistricts[$v["receiver_district"]]["district_name"] : '';

                $order_list['data'][$k]['operation'] = [];
                // 订单来源名称
                $order_from = OrderStatus::getOrderFrom($v['order_from']);
                $order_list['data'][$k]['order_type_name'] = OrderStatus::getOrderType($v['order_type']);
                if ( !$this->supplier_id && $v['supplier_id']){
                    $order_list['data'][$k]['order_type_name'] = '供应商-'.$order_list['data'][$k]['order_type_name'];
                }
                $order_list['data'][$k]['order_type_color'] = OrderStatus::getOrderTypeColor($v['order_type']);
                $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                $order_list['data'][$k]['order_from_tag'] = $order_from['tag'];
                $order_list['data'][$k]['pay_type_name'] = OrderStatus::getPayType($v['payment_type']);
                if($v['payment_type'] == 18 && $v['membercard_deduction_money'] != 0.00) {
                    $order_list['data'][$k]['pay_type_name'] = '会员卡抵扣';
                }
                $order_list['data'][$k]['unrefund'] = 0;
                $order_list['data'][$k]['unrefund_reason'] = '';
                $isGroupSuccess = 0;
                if ($groupshopping && $v['group_record_id']) {
                    $groupServer = new GroupShopping();
                    $record = $groupServer->groupRecordDetail($v['group_record_id']);
                    if ($record['status'] == 0) {
                        $order_list['data'][$k]['unrefund'] = 1;//待成团订单不能退款
                        $order_list['data'][$k]['unrefund_reason'] = '拼团订单暂时无法退款，若在' . time_diff(time(), $record['finish_time']) . '未成团，将自动退款！';
                    }
                    $isGroupSuccess = $record['status'];
                }
                //幸运拼订单状态
                $order_list['data'][$k]['is_win'] = 0;
                if ($luckyspell && $v['luckyspell_id']) {
                    $luckySpellServer = new luckySpellServer();
                    $record = $luckySpellServer->groupluckySpellRecordDetail($v['order_id']);
                    $order_list['data'][$k]['is_win'] = intval($record['is_win']);
                    if ($record['status'] == 0) {
                        $order_list['data'][$k]['unrefund'] = 1;//待成团订单不能退款
                        $order_list['data'][$k]['unrefund_reason'] = '幸运拼订单无法主动申请退款,未中奖会自动退款';
                    }
                    $isGroupSuccess = $record['status'];
                }
                //货到付款订单 订单未确认收货前不支持退款退货 edit by 2019/10/12
                if ($v['order_status'] != 5) {
                    if ($v['payment_type'] == 4 && ($v['order_status'] < 3 || $v['order_status'] > 4) && $v['order_status'] != -1) {
                        $order_list['data'][$k]['unrefund'] = 1;//
                        $order_list['data'][$k]['unrefund_reason'] = '货到付款订单在未确认收货前是无法退款的订单！';
                    }
                }

                if ($microshop) {
                    if ($v['order_type'] == 2 || $v['order_type'] == 3 || $v['order_type'] == 4) {
                        $order_list['data'][$k]['unrefund'] = 1;//微店店主续费升级成为店主订单不能退款
                        $order_list['data'][$k]['unrefund_reason'] = '微店店主续费升级和成为店主是无法退款的订单！';
                    }
                }
                //店铺名称
                $order_list['data'][$k]['shop_name'] = $shop ? (isset($shopLists[$v['shop_id']]) ? $shopLists[$v['shop_id']]['shop_name'] : '') : $this->mall_name;
                if ($order_list['data'][$k]['shipping_type'] == 1) {
                    $order_list['data'][$k]['shipping_type_name'] = '商家配送';
                } elseif ($order_list['data'][$k]['shipping_type'] == 2) {
                    $order_list['data'][$k]['shipping_type_name'] = '门店自提';
                } else {
                    $order_list['data'][$k]['shipping_type_name'] = '';
                }
                // 根据订单类型判断订单相关操作

                if ($wapStore) {
                    $order_status = OrderStatus::getSinceOrderStatusForStore($order_list['data'][$k]['order_type'], $isGroupSuccess);
                } else {
                    if ($order_list['data'][$k]['payment_type'] == 6 || $order_list['data'][$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus($order_list['data'][$k]['order_type'], $isGroupSuccess, $order_list['data'][$k]['card_store_id']);
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus($order_list['data'][$k]['order_type'], $isGroupSuccess, $order_list['data'][$k]['card_store_id'], $order_item_list ? $order_item_list[0]['goods_type'] : 0);
                    }
                }
                $order_list['data'][$k]['excel_order_money'] = $v['goods_money'] + $v['shipping_money'] - $v['promotion_free_shipping'];

//                //处理供应商端订单状态
//                if($model=='supplier'){
//                    if($v['supplier_id'] == $this->supplier_id) {
////                        $order_goods_info = 1;
//                        $supplier_goods_order_status = [];
//                        foreach ($order_item_list as $temp_order_goods){
//                            if ($temp_order_goods['supplier_id']>0){
//                                $supplier_goods_order_status[] = $temp_order_goods['order_status'];
//                            }
//                        }
//                        //订单包含店铺商品、供应商商品。所以显示订单状态只能筛选订单中供应商的商品中订单状态最小值
//                        $v['order_status'] = min($supplier_goods_order_status);
//                    }
//                }

                $refund_member_operation = [];
                // 查询订单操作
                foreach ($order_status as $k_status => $v_status) {
                    if ($v_status['status_id'] == $v['order_status']) {
                        //代付定金
                        if ($v['presell_id'] != 0 && $v['pay_status'] == 0 && $v['money_type'] == 0 && $v['order_status'] != 5) {
                            $v_status['status_name'] = "待付定金";
                            unset($v_status['operation'][1]);//调整价格 去掉
                        }
                        //待付尾款
                        if ($v['presell_id'] != 0 && $v['pay_status'] == 0 && $v['money_type'] == 1 && $v['order_status'] != 5) {
                            $v_status['status_name'] = "待付尾款";
                            unset($v_status['operation'][1]);//调整价格 去掉
                        }

                        //已付定金，去掉定金退款按钮
                        if ($v['presell_id'] != 0 && $v['pay_status'] == 0 && $v['money_type'] == 1) {
                            $v_status['refund_member_operation'] = [];
                        }
                        //积分订单没有支付、退款
                        if ($v['order_type'] == 10) {
                            $v_status['refund_member_operation'] = '';
                            //判断当前商品是否是虚拟商品，虚拟商品是没有物流信息的
                            $goods_exchange_type = $order_item_list[0]['goods_exchange_type'];
                            if ($goods_exchange_type != 0) {//是虚拟商品
                                if ($v_status['member_operation']) {
                                    foreach ($v_status['member_operation'] as $s_k => $s_v) {
                                        if ($s_v['no'] == 'logistics') {
                                            unset($v_status['member_operation'][$s_k]);
                                        } elseif ($s_v['no'] == 'evaluation') {
                                            unset($v_status['member_operation'][$s_k]);
                                        } elseif ($s_v['no'] == 'buy_again') {
                                            unset($v_status['member_operation'][$s_k]);
                                        }
                                    }
                                    unset($s_v);
                                    foreach ($v_status['operation'] as $s_k => $s_v) {
                                        if ($s_v['no'] == 'logistics') {
                                            unset($v_status['operation'][$s_k]);
                                        }
                                    }
                                    unset($s_v);
                                }
                            }
                        }
                        //知识付费商品去掉查看物流、再次购买
                        if(count($order_item_list) == 1) {
                            if($order_item_list[0]['goods_type'] == 4) {
                                if ($v_status['member_operation']) {
                                    foreach ($v_status['member_operation'] as $s_k => $s_v) {
                                        if ($s_v['no'] == 'logistics') {
                                            unset($v_status['member_operation'][$s_k]);
                                        } elseif ($s_v['no'] == 'buy_again') {
                                            unset($v_status['member_operation'][$s_k]);
                                        }
                                    }
                                    unset($s_v);
                                }
                            }
                        }
                        //只要其中一件未确认售后及以后状态，则不能显示“确认收货”
                        if ($is_order_goods_refund){
                            unset($v_status['operation'][2]);
                        }
                        //预约商品去除发货按钮
                        $order_list['data'][$k]['operation'] = $v_status['operation']?:[];
                        $order_list['data'][$k]['member_operation'] = $v_status['member_operation'];
                        $order_list['data'][$k]['status_name'] = $v_status['status_name'];

                        $order_list['data'][$k]['is_refund'] = $v_status['is_refund'];
                        $refund_member_operation = $v_status['refund_member_operation'];
                    }
                    if ($order_list['data'][$k]['order_type'] == 9) {
                        $refund_member_operation = [];
                    }
                }
                unset($v_status);
                $temp_refund_operation = [];// 将需要整单进行售后的操作保存在operation（卖家操作）里面


                //获取发货数目和总数目判断是否部分发货
                $express_num = 0;
                if(!$order_item_list){
                    unset($order_list['data'][$k]);
                    continue;
                }
                $supplier_money = 0;
                $supplier_shipping_fee = 0;
                foreach ($order_item_list as $key_item => $v_item) {
                    $order_item_list[$key_item]['refund_status'] = intval($v_item['refund_status']);
                    if($model == 'supplier') {
                        //如果是供应商端，筛选出属于该供应商的订单项
                        if($v_item['supplier_id'] != $this->supplier_id) {
                            unset($order_item_list[$key_item]);
                            continue;
                        }
                    }
                    if (isset($order_express_info[$v['order_id']])) {
                        foreach ($order_express_info[$v['order_id']] as $express_info) {
                            $express_order_goods_id_array = explode(',', $express_info['order_goods_id_array']);
                            if (in_array($v_item['order_goods_id'], $express_order_goods_id_array)) {
                                $order_item_list[$key_item]['express_no'] = $express_info['express_no'];
                                $order_item_list[$key_item]['express_name'] = $express_info['express_name'];
                                $express_num++;
                            } else {
                                $order_item_list[$key_item]['express_no'] = '';
                                $order_item_list[$key_item]['express_name'] = '';
                            }
                        }
                        unset($express_info);
                    } else {
                        $order_item_list[$key_item]['express_no'] = '';
                        $order_item_list[$key_item]['express_name'] = '';
                    }
                    // 查询商品sku表开始
                    if ($v_item['order_type'] == 10) {
                        $goods_model = new VslIntegralGoodsModel();
                        $goods_sku = new VslIntegralGoodsSkuModel();
                        $goods_sku_info = $goods_sku->getInfo([
                            'sku_id' => $v_item['sku_id']
                        ], 'code');
                        $order_item_list[$key_item]['code'] = $goods_sku_info['code'];
                        $goods_info = $goods_model->getInfo([
                            'goods_id' => $v_item['goods_id']
                        ], 'code,item_no');
                        $order_item_list[$key_item]['goods_code'] = $goods_info['code'];
                        $order_item_list[$key_item]['item_no'] = $goods_sku_info['code'] ? $goods_sku_info['code'] : $goods_info['item_no'];
                    } else {
                        $order_item_list[$key_item]['code'] = isset($v_item['goods_sku']) ? $v_item['goods_sku']['code'] : 0;
                        $order_item_list[$key_item]['goods_code'] = isset($v_item['goods']) ? $v_item['goods']['code'] : 0;
                        $order_item_list[$key_item]['item_no'] = (isset($v_item['goods_sku']) && $v_item['goods_sku']['code']) ? $v_item['goods_sku']['code'] : (isset($v_item['goods']) ? $v_item['goods']['item_no'] : 0);
                    }
                    $order_item_list[$key_item]['spec'] = [];
                    $order_item_list[$key_item]['real_refund_reason'] = OrderStatus::getRefundReason($v_item['refund_reason']);
                    if ($v_item['sku_attr']) {
                        $order_item_list[$key_item]['spec'] = json_decode(html_entity_decode($v_item['sku_attr']), true);
                    }
                    // 查询商品sku结束
                    $order_item_list[$key_item]['picture'] = isset($v_item['goods_pic']) ? $v_item['goods_pic'] : ['pic_cover_micro' => ''];
                    $order_item_list[$key_item]['refund_type'] = $v_item['refund_type'];
                    $order_item_list[$key_item]['refund_operation'] = [];
                    $order_item_list[$key_item]['new_refund_operation'] = [];
                    $order_item_list[$key_item]['member_operation'] = [];
                    $order_item_list[$key_item]['status_name'] = '';
                    $temp_member_refund_operation = [];
                    if ($v['payment_type'] == 16 || $v['payment_type'] == 17 || $v['payment_type_presell'] == 16 || $v['payment_type_presell'] == 17) {
                        $order_list['data'][$k]['promotion_status'] = 1;
                    }
                    if (!in_array($v['order_type'], [2, 3, 4, 10,15])) {
                        // 2,3,4微店订单 不参与售后 幸运拼也不参加
                        if ($v_item['refund_status'] != 0) {
                            $order_refund_status = OrderStatus::getRefundStatus()[$v_item['refund_status']];
                            if ($v_item['refund_type'] == 1 && $order_refund_status['status_id'] == 1) {
                                //去除处理退货申请
                                unset($order_refund_status['new_refund_operation'][1]);
                            } elseif ($v_item['refund_type'] == 2 && $order_refund_status['status_id'] == 1) {
                                //去除处理退款申请
                                unset($order_refund_status['new_refund_operation'][0]);
                            }
                            $order_item_list[$key_item]['refund_operation'] = $order_refund_status['refund_operation'];
                            if ($order_list['data'][$k]['promotion_status'] == 1) {//活动商品
                                $order_item_list[$key_item]['member_operation'] = [];
                                $temp_member_refund_operation = $order_refund_status['member_operation'];
                            } else {
                                $order_item_list[$key_item]['member_operation'] = $order_refund_status['member_operation'];
                            }
                            $order_item_list[$key_item]['new_refund_operation'] = $temp_refund_operation = array_values($order_refund_status['new_refund_operation']);
                            $order_item_list[$key_item]['status_name'] = $order_refund_status['status_name'];
                        } elseif ($order_list['data'][$k]['promotion_status'] != 1) {//普通商品
                            if ($v_item['invoice_tax'] > 0) {
                                $order_refund_status = OrderStatus::getRefundStatus()[$v_item['refund_status']];
                                $temp_member_refund_operation = $order_refund_status;
                            }
                            $order_item_list[$key_item]['member_operation'] = $refund_member_operation;
                        }
                    }

                    //知识付费商品去掉申请售后
                    if($v_item['goods_type'] == 4) {
                        $order_item_list[$key_item]['member_operation'] = [];
                    }

                    //订单导出需要查出优惠
                    if($isExcel){
                        $ordergoods_promotion = new VslOrderGoodsPromotionDetailsModel();
                        $ordergoods_promotion_info = $ordergoods_promotion->where(['order_id' => $v['order_id'], 'sku_id' => $v_item['sku_id']])->find();
                        $order_item_list[$key_item]['manjian_money'] = $order_item_list[$key_item]['coupon_money'] = '';
                        if ($ordergoods_promotion_info['promotion_type'] == 'MANJIAN' && $fullcut) {
                            $order_item_list[$key_item]['manjian_money'] = $ordergoods_promotion_info['discount_money'];
                        }
                        if ($ordergoods_promotion_info['promotion_type'] == 'COUPON' && $coupontype) {
                            $order_item_list[$key_item]['coupon_money'] = $ordergoods_promotion_info['discount_money'];
                        }
                    }


                    //分销信息
                    $order_item_list[$key_item]['commission'] = '';
                    $order_item_list[$key_item]['commissionA'] = '';
                    $order_item_list[$key_item]['commissionB'] = '';
                    $order_item_list[$key_item]['commissionC'] = '';
                    if ($distribution) {
                        if ($this->instance_id) {
                            /* $order_commission_info = $order_commission->where(['order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id'], 'shop_id' => $this->instance_id])->find();
                            $order_item_list[$key_item]['commission'] = $order_commission_info['commission'];
                            $order_item_list[$key_item]['commissionA'] = $order_commission_info['commissionA'];
                            $order_item_list[$key_item]['commissionB'] = $order_commission_info['commissionB'];
                            $order_item_list[$key_item]['commissionC'] = $order_commission_info['commissionC']; */
                        } else {
                            /* $order_commission_info = $order_commission->where(['order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id']])->find();
                            $order_item_list[$key_item]['commission'] = $order_commission_info['commission'];
                            $order_item_list[$key_item]['commissionA'] = $order_commission_info['commissionA'];
                            $order_item_list[$key_item]['commissionB'] = $order_commission_info['commissionB'];
                            $order_item_list[$key_item]['commissionC'] = $order_commission_info['commissionC']; */
                        }
                    }
                    //佣金用于导表 by sgw
                    if ($distribution) {
                        $order_commission = new VslOrderDistributorCommissionModel();
                        if ($this->instance_id) {
                            $order_commission_info = $order_commission->where(['order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id'], 'shop_id' => $this->instance_id])->find();
                            $order_item_list[$key_item]['commission'] = $order_commission_info['commission'];
                            $order_item_list[$key_item]['commissionA'] = $order_commission_info['commissionA'];
                            $order_item_list[$key_item]['commissionB'] = $order_commission_info['commissionB'];
                            $order_item_list[$key_item]['commissionC'] = $order_commission_info['commissionC'];
                        } else {
                            $order_commission_info = $order_commission->where(['order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id']])->find();
                            $order_item_list[$key_item]['commission'] = $order_commission_info['commission'];
                            $order_item_list[$key_item]['commissionA'] = $order_commission_info['commissionA'];
                            $order_item_list[$key_item]['commissionB'] = $order_commission_info['commissionB'];
                            $order_item_list[$key_item]['commissionC'] = $order_commission_info['commissionC'];
                        }
                    }

                    //分红信息
                    $order_item_list[$key_item]['bonus'] = 0.00;
                    $order_item_list[$key_item]['bonusA'] = 0;
                    $order_item_list[$key_item]['bonusB'] = 0;
                    $order_item_list[$key_item]['bonusC'] = 0;
                    if ($globalbonus || $teambonus || $areabonus) {
                        /* $order_item_list[$key_item]['bonusA'] = $order_bonus->getSum(['website_id' => $this->website_id,'order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id'],'from_type' => 1],'bonus');
                        $order_item_list[$key_item]['bonusB'] = $order_bonus->getSum(['website_id' => $this->website_id,'order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id'],'from_type' => 2],'bonus');
                        $order_item_list[$key_item]['bonusC'] = $order_bonus->getSum(['website_id' => $this->website_id,'order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id'],'from_type' => 3],'bonus');
                        $order_item_list[$key_item]['bonus'] = $order_bonus->getSum(['website_id' => $this->website_id,'order_id' => $v['order_id'], 'order_goods_id' => $v_item['order_goods_id'],'from_type' => ['in',[1,2,3]]],'bonus'); */
                    }

                    //收益信息
                    $order_item_list[$key_item]['profit'] = '';
                    $order_item_list[$key_item]['profitA'] = '';
                    $order_item_list[$key_item]['profitB'] = '';
                    $order_item_list[$key_item]['profitC'] = '';
                    if ($microshop) {
                        /* $order_profit = new VslOrderMicroShopProfitModel();
                        $order_profit_info = $order_profit->where(['order_id' => $v['order_id'],'order_goods_id' => $v_item['order_goods_id']])->find();
                        $order_item_list[$key_item]['profit'] = $order_profit_info['profit'];
                        $order_item_list[$key_item]['profitA'] = $order_profit_info['profitA'];
                        $order_item_list[$key_item]['profitB'] = $order_profit_info['profitB'];
                        $order_item_list[$key_item]['profitC'] = $order_profit_info['profitC']; */
                    }
                    //供应商
                    if ($this->model == 'supplier' && $v_item['supplier_id']){
                        $supplier_money += $v_item['supplier_money'];
                        $supplier_shipping_fee += $v_item['shipping_fee'];
                        $order_item_list[$key_item]['supplier_money'] = roundLengthNumber(($v_item['supplier_money']-$v_item['shipping_fee'])/$v_item['num']);
                        $order_item_list[$key_item]['supplier_money'] = $order_item_list[$key_item]['supplier_money']>0? $order_item_list[$key_item]['supplier_money'] :0;
                    }
                }

                //供应商'实际结算'
                if ($this->model == 'supplier' ){
                    $order_list['data'][$k]['order_money'] = $supplier_money;
                    $order_list['data'][$k]['promotion_free_shipping'] = 0;//这里为了避免html的计算
                    $order_list['data'][$k]['shipping_money'] = $supplier_shipping_fee;
                }
                unset($v_item);
                //如果是满减送有赠品、礼品券的，需要链表找出赠品信息(1个订单对应多个)
                $giftArr = [];
                if ($v['is_mansong']){
                    $orderPromotionModel = new VslOrderPromotionDetailsModel();
                    $giftRes = $orderPromotionModel->getQuery(['order_id' => $v['order_id']], 'gift_value');
                    if ($giftRes) {
                        foreach ($giftRes as $g_k => $g_v) {
                            $g_v = json_decode(htmlspecialchars_decode($g_v['gift_value']), true);
                            $giftValArr = [];
                            if ($g_v){
                                foreach ($g_v as $gg_k => $gg_v) {
                                    $base_pre = '【赠品】';
                                    if ($gg_k == 'gift_coupon') {
                                        $base_pre = '【优惠券】';
                                    }
                                    if ($gg_k == 'gift_voucher') {
                                        $base_pre = '【礼品券】';
                                    }
                                    $giftValArr[$g_k] = [
                                        'is_gift'           => true,
                                        'picture'           => ['pic_cover'=> $gg_v['pic']],
                                        'goods_name'        => $base_pre.$gg_v['name'],
                                        'price'             => '0.00',
                                        'num'               => 1,
                                        'member_operation'  => []
                                    ];
                                    $giftArr = array_merge($giftArr, $giftValArr);
                                }
                                unset($gg_v);
                            }
                        }
                        unset($g_v);
                    }
                }

                //订单优惠
                $order_list['data'][$k]['order_adjust_money'] = 0;// 订单金额调整
                $supplier_money = 0;
                foreach ($order_item_list as $order_goods_obj) {
                    $order_list['data'][$k]['order_adjust_money'] += $order_goods_obj['adjust_money'] * $order_goods_obj['num'];
//                    $supplier_money += $order_goods_obj['supplier_money'];
                    if ($order_goods_obj['supplier_id'] == $this->supplier_id){
                        $supplier_money += $order_goods_obj['real_money'];
                    }
                }

                unset($order_goods_obj);
                $order_item_list = array_merge($order_item_list, $giftArr);
                unset($v_item);
                $order_list['data'][$k]['all_express'] = ($express_num >= count($order_item_list)) ?: false;
                $order_list['data'][$k]['order_item_list'] = $order_item_list?:[];

                if (!$v['presell_id']) {
                    // $order_list['data'][$k]['order_promotion_money'] = $v['goods_money'] + $v['shipping_money'] - $v['promotion_free_shipping'] - ($v['pay_money'] - $v['invoice_tax']) + $order_list['data'][$k]['order_adjust_money'];
                    //计算精度导致异常
                    $order_promotion_money = bcadd($v['goods_money'],$v['shipping_money'],2);
                    $order_promotion_money = bcsub ($order_promotion_money,$v['promotion_free_shipping'],2);
                    $order_promotion_money = bcadd ($order_promotion_money,$v['invoice_tax'],2);
                    $order_promotion_money = bcsub ($order_promotion_money,$v['pay_money'],2);
                    $order_promotion_money = bcadd ($order_promotion_money,$order_list['data'][$k]['order_adjust_money'],2);
                    $order_list['data'][$k]['order_promotion_money'] = $order_promotion_money;
                    if ($v['deduction_money'] > 0) {
                        $order_list['data'][$k]['order_promotion_money'] = "{$order_list['data'][$k]['order_promotion_money']}" - "{$v['deduction_money']}";
                    }
                    if ($v['membercard_deduction_money'] > 0) {
                        $order_list['data'][$k]['order_promotion_money'] = "{$order_list['data'][$k]['order_promotion_money']}" - "{$v['membercard_deduction_money']}";
                    }
                } else {
                    $order_list['data'][$k]['order_promotion_money'] = 0;
                }
                $order_list['data'][$k]['order_promotion_money'] = $order_list['data'][$k]['order_promotion_money']>0?$order_list['data'][$k]['order_promotion_money']:0;
                if (Request::instance()->module() == 'supplier'){
                    //供应商端后台显示的订单pay_money要只显示供应商的商品支付金额
                    $order_list['data'][$k]['pay_money'] = $supplier_money;
                }
                //查询会员信息
                $user_item_info = $userList[$v['buyer_id']];
                $order_list['data'][$k]['user_tel'] = $user_item_info['user_tel'];
                //用户昵称>会员名称>会员手机>uid
                $order_list['data'][$k]['buyer_name'] = ($user_item_info['nick_name']) ? $user_item_info['nick_name'] : ($user_item_info['user_name'] ? $user_item_info['user_name'] : ($user_item_info['user_tel'] ? $user_item_info['user_tel'] : $user_item_info['uid']));

                //查询核销门店信息
                $order_list['data'][$k]['store_name'] = '';
                $order_list['data'][$k]['assistant_name'] = '';
                if ($store && $v['store_id']) {
                    $storeModel = new VslStoreModel();
                    $store_assistant = new VslStoreAssistantModel();
                    $order_list['data'][$k]['store_name'] = $storeModel->where(['store_id' => $v['store_id']])->value('store_name');
                    $order_list['data'][$k]['assistant_name'] = $store_assistant->where(['assistant_id' => $v['assistant_id']])->value('assistant_name');
                }

                //订单导出查询满额赠送
                $order_list['data'][$k]['manjian_remark'] = '';
                if (isset($orderPromotionList[$v['order_id']]['coupon'])) {
                    $order_list['data'][$k]['manjian_remark'] .= $orderPromotionList[$v['order_id']]['coupon']['coupon_name'];
                }
                if (isset($orderPromotionList[$v['order_id']]['gift'])) {
                    $order_list['data'][$k]['manjian_remark'] .= $orderPromotionList[$v['order_id']]['gift']['gift_name'];
                }
                if (isset($orderPromotionList[$v['order_id']]['gift_voucher'])) {
                    $order_list['data'][$k]['manjian_remark'] .= $orderPromotionList[$v['order_id']]['gift_voucher']['giftvoucher_name'];
                }

                // 将需要整单进行售后的 售后操作 放到 非售后操作数组内 因为后者的位置就是位于 th = 操作的 那一列
                if ($temp_refund_operation && $order_list['data'][$k]['promotion_status'] == 1) {
                    $order_list['data'][$k]['operation'] = array_merge($order_list['data'][$k]['operation'], $temp_refund_operation);
                    //$order_list['data'][$k]['refund_operation_goods'] = array_column($order_list['data'][$k]['order_item_list'], 'order_goods_id');
                }

                $order_list['data'][$k]['operation'] = array_values($order_list['data'][$k]['operation']);

                //积分兑换订单是没有售后的
                if (!in_array($v['order_status'], [4, 5]) && $v['order_type'] != 10 && $v['order_type'] != 15) {
                    // 已完成，已关闭没有售后
                    if ($order_list['data'][$k]['promotion_status'] == 1) {
                        if (!empty($temp_member_refund_operation)) {
                            $order_list['data'][$k]['member_operation'] = array_merge($order_list['data'][$k]['member_operation'], $temp_member_refund_operation);
                        }
                        $order_list['data'][$k]['member_operation'] = array_merge($order_list['data'][$k]['member_operation'], $refund_member_operation);
                    } else {
                        // 将common里面的售后操作放到订单商品里面
                        $orderItem = $order_list['data'][$k]['order_item_list'];
                        foreach ($orderItem as &$v_item) {
                            /*赠品不能退款*/
                            if ($v_item['refund_status'] == 0 && $v_item['goods_type'] != 4 && !$v_item['is_gift']) {
                                $v_item['member_operation'] = $refund_member_operation;
                            }
                            if ($v['invoice_type'] > 0) {
                                $v_item['member_operation'] = [];
                            }
                        }
                        if ($v['invoice_type'] > 0) {
                            $order_list['data'][$k]['member_operation'] = array_merge($order_list['data'][$k]['member_operation'], $refund_member_operation);
                        }
                        unset($v_item);
                        $order_list['data'][$k]['order_item_list'] = $orderItem;
                    }
                }
                if ($v['shop_after']) {
                    $order_list['data'][$k]['operation'] = [];
                }
                if($this->website_id == 4794 || $this->website_id == 1086 || $this->website_id == 18){
                        $order_list['data'][$k]['user_tel'] = '演示系统手机已加密';
                        $order_list['data'][$k]['receiver_mobile'] = '演示系统手机已加密';
                }
            }

        }

        //获取该次查询订单总金额
        $sum_totals = $order_model->getViewCount3($new_condition);
        $order_list['all_pay_money'] = $sum_totals[0]['all_pay_money'] ? $sum_totals[0]['all_pay_money'] : 0;
        if (isset($order_amount)) {
            $order_list['order_amount'] = $order_amount;
        }
        $order_list['order_amount'] = $all_order_money;
        return $order_list;
    }


    /**
     * 订单收件人列表，收件人信息（姓名手机）匹配相当于一个收件人
     * @param int $page_index
     * @param int $page_size
     * @param string $condition
     * @param string $order
     * @return array
     */
    public function getOrderReceiverList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $order_model = new VslOrderModel();
        //如果有订单表以外的字段，则先按条件查询其他表的orderid，并取出数据的交集，组装到原有查询条件里
        $query_order_ids = 'uncheck';
        $un_query_order_ids = array();
        $checkOthers = false;

        if ($condition['goods_name']) {
            $checkOthers = true;
            $orderGoodsModel = new VslOrderGoodsModel();
            $order_goods_condition = ['website_id' => $this->website_id];
            if ($condition['goods_name']) {
                $order_goods_condition['goods_name'] = $condition['goods_name'];
            }
            if ($condition['buyer_id']) {
                $order_goods_condition['buyer_id'] = $condition['buyer_id'];
            }
            $orderGoodsList = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition, '', 'order_id');
            unset($condition['goods_name'], $condition['refund_status']);
            $goods_order_ids = array();
            if ($orderGoodsList['data']) {
                foreach ($orderGoodsList['data'] as $keyG => $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }
            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $goods_order_ids);
            } else {
                $query_order_ids = $goods_order_ids;
            }
        }
        if ($condition['vgsr_status']) {
            $isGroup = true;
            $checkOthers = true;
            $vgsr_status = $condition['vgsr_status'];
            $group_server = new GroupShopping();
            $unGroupOrderIds = $group_server->getPayedUnGroupOrder($this->instance_id, $this->website_id);

            if ($vgsr_status == 1) {
                if ($query_order_ids != 'uncheck') {
                    $query_order_ids = array_intersect($query_order_ids, $unGroupOrderIds);
                } else {
                    $query_order_ids = $unGroupOrderIds;
                }
            }
            if ($vgsr_status == 2 && $unGroupOrderIds) {
                $un_query_order_ids = $unGroupOrderIds;
            }
            unset($condition['vgsr_status']);
        }
        if ($checkOthers) {
            if ($query_order_ids != 'uncheck') {
                $condition['order_id'] = ['in', implode(',', $query_order_ids)];
            } elseif ($un_query_order_ids) {
                $condition['order_id'] = ['not in', implode(',', $un_query_order_ids)];
            }
        }
        // 查询主表
        $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        $province_model = new ProvinceModel();
        $city_model = new CityModel();
        $district_model = new DistrictModel();

        $return_list = [];
        $key_list = [];
        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                $key = md5($v['receiver_name'] . $v['receiver_mobile'] . $v['receiver_province'] . $v['receiver_city'] . $v['receiver_district'] . $v['receiver_address'] . $v['receiver_zip']);

                if (!in_array($key, $key_list)) {
                    $return_list[$key]['receiver_name'] = $v['receiver_name'];
                    $return_list[$key]['receiver_mobile'] = $v['receiver_mobile'];
                    $return_list[$key]['receiver_province_id'] = $v['receiver_province'];
//                    $return_list[$key]['receiver_province_name'] = $province_model::get($v['receiver_province'])['province_name'];
                    $return_list[$key]['receiver_city_id'] = $v['receiver_city'];
//                    $return_list[$key]['receiver_city_name'] = $city_model::get($v['receiver_city'])['city_name'];
                    $return_list[$key]['receiver_district_id'] = $v['receiver_district'];
//                    $return_list[$key]['receiver_district_name'] = $district_model::get($v['receiver_district'])['district_name'];
                    $return_list[$key]['receiver_address'] = $v['receiver_address'];
                    $return_list[$key]['receiver_zip_code'] = $v['receiver_zip'];
                    $key_list[] = $key;
                }

                $return_list[$key]['order_id_array'][] = $v['order_id'];
                $return_list[$key]['order_num']++;
                $return_list[$key]['order_amount'] += $v['order_money'];
            }
            unset($v);
        }
        return $return_list;
    }

    public function printOrderList(array $condition = [], array $with = [])
    {
        $order_goods_model = new VslOrderGoodsModel();
        $province_model = new ProvinceModel();
        $city_model = new CityModel();
        $district_model = new DistrictModel();
        $sku_model = new VslGoodsSkuModel();
        $order_goods_list = $order_goods_model::all($condition, $with);
        $goodsSer = new GoodsService();

        $return_data = [];
        foreach ($order_goods_list as $v) {
            $return_data[$v->order_id]['order_id'] = $v->order_id;
            $return_data[$v->order_id]['order_no'] = $v->order->order_no;
            $return_data[$v->order_id]['receiver_name'] = $v->order->receiver_name;
            $return_data[$v->order_id]['receiver_mobile'] = $v->order->receiver_mobile;
            $return_data[$v->order_id]['receiver_province_id'] = $v->order->receiver_province;
            $return_data[$v->order_id]['receiver_province_name'] = $province_model::get($return_data[$v->order_id]['receiver_province_id'])['province_name'];
            $return_data[$v->order_id]['receiver_city_id'] = $v->order->receiver_city;
            $return_data[$v->order_id]['receiver_city_name'] = $city_model::get($return_data[$v->order_id]['receiver_city_id'])['city_name'];
            $return_data[$v->order_id]['receiver_district_id'] = $v->order->receiver_district;
            $return_data[$v->order_id]['receiver_district_name'] = $district_model::get($return_data[$v->order_id]['receiver_district_id'])['district_name'];
            $return_data[$v->order_id]['receiver_zip_code'] = $v->order->receiver_zip;
            $return_data[$v->order_id]['receiver_address'] = $v->order->receiver_address;

            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['order_goods_id'] = $v['order_goods_id'];
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['goods_name'] = $v['goods_name'];
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['goods_id'] = $v['goods_id'];
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['sku_id'] = $v['sku_id'];
            $sku = $sku_model->getInfo(['sku_id' => $v['sku_id']],'sku_name');
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['sku_name'] = $sku ? $sku['sku_name'] : '';
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['short_name'] = $goodsSer->getGoodsDetailById($v['goods_id'], 'goods_id,goods_name,price,collects,short_name')['short_name'];
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['price'] = $v['price'];
            $return_data[$v->order_id]['goods_list'][$v['order_goods_id']]['num'] = $v['num'];
        }
        unset($v);
        return $return_data;
    }

    public function deliveryOrderList(array $condition = [], array $with = [])
    {
        $order_goods_model = new VslOrderGoodsModel();
        $order_goods_express_model = new VslOrderGoodsExpressModel();
        $order_goods_list = $order_goods_model::all($condition, $with);
        $return_data = [];
        $order_id_array = [];
        $i = 1;
        foreach ($order_goods_list as $v) {
            $return_data[$v->order_id]['i'] = $i++;
            $return_data[$v->order_id]['order_id'] = $v->order_id;
            $return_data[$v->order_id]['order_no'] = $v->order->order_no;
            $return_data[$v->order_id]['order_goods_id_array'][] = $v->order_goods_id;
            $return_data[$v->order_id]['order_status'] = $v->order->order_status;
            $return_data[$v->order_id]['order_status_name'] = OrderStatus::getOrderCommonStatus()[$v->order->order_status]['status_name'];

            if (!in_array($v->order_id, $order_id_array)) {
                $order_id_array[] = $v->order_id;
            }
        }
        unset($v);
        $order_goods_express = $order_goods_express_model::all(['order_id' => ['IN', $order_id_array]]);
        foreach ($order_goods_express as $e) {
            $temp_order_goods_id_array = explode(',', $e->order_goods_id_array);
            $return_data[$e->order_id]['company_name'] = $e->express_name;
            $return_data[$e->order_id]['express_no'] = $e->express_no;
            foreach ($return_data[$e->order_id]['order_goods_id_array'] as $k => $order_goods_id) {
                if (in_array($order_goods_id, $temp_order_goods_id_array)) {
                    // 删除已发货的订单商品
                    unset($return_data[$e->order_id]['order_goods_id_array'][$k]);
                }
            }
            unset($order_goods_id);
        }
        unset($e);
        return $return_data;
    }

    public function addPrintTimes($type, $order_goods_id_array)
    {
        try {
            Db::startTrans();
            if ($type == 'express') {
                $order_goods_field = 'express_print_num';
                $order_field = 'express_order_status';
            } else {
                $order_goods_field = 'delivery_print_num';
                $order_field = 'delivery_order_status';
            }
            $order_goods_model = new VslOrderGoodsModel();
            $order_model = new VslOrderModel();
            $order_goods_model->where(['order_goods_id' => ['IN', $order_goods_id_array]])->setInc($order_goods_field);
            $order_goods_list = $order_goods_model::all(['order_goods_id' => ['IN', $order_goods_id_array]]);
            foreach ($order_goods_list as $v) {
                if ($v['order'][$order_field] == 3) {
                    // 全部已打印
                    continue;
                }
                if ($v['order'][$order_field] == 1) {
                    // 未打印
                    if ($order_goods_model->where(['order_id' => $v['order_id'], $order_goods_field => ['EQ', 0]])->count() > 0) {
                        // 部分打印
                        $order_model->save([$order_field => 2], ['order_id' => $v['order_id']]);
                    } else {
                        // 全部打印
                        $order_model->save([$order_field => 3], ['order_id' => $v['order_id']]);
                    }
                }
            }
            unset($v);
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return UPDATA_FAIL;
        }
    }

    public function checkBeforeOrderCreate(array $order_data)
    {
        $now_time = time();
        $man_song_rule_model = getAddons('fullcut', $this->website_id) ? new VslPromotionMansongRuleModel() : '';
        $sku_model = new VslGoodsSkuModel();
        $coupon_model = getAddons('coupontype', $this->website_id) ? new VslCouponModel() : '';
        $promotion_discount_model = getAddons('discount', $this->website_id) ? new VslPromotionDiscountModel() : '';
        $sec_service = getAddons('seckill', $this->website_id, $this->instance_id) ? new seckillServer() : '';
        $storeServer = getAddons('store', $this->website_id, $this->instance_id) ? new Store() : '';
        $user_model = new UserModel();
        $user_info = $user_model::get($this->uid, ['member_info.level', 'member_account']);
        if ($order_data['pay_type'] == 5 && $user_info->member_account->balance < $order_data['total_pay_amount']) {
            return ['result' => false, 'message' => '账号余额不足'];
        }
        if (empty($order_data['address_id']) && $order_data['shipping_type'] == 1) {
            return ['result' => false, 'message' => '缺少收货地址'];
        }
        $web_config = new Config();
        $bpay = $web_config->getConfig(0, 'BPAY')['is_use'];
        if ($order_data['pay_type'] == 5 && $bpay != 1) {
            return ['result' => false, 'message' => '没有开启余额支付'];
        }
        $member_discount = $user_info->member_info->level->goods_discount ? ($user_info->member_info->level->goods_discount / 10) : 1;
        $member_is_label = $user_info->member_info->level->is_label;
        foreach ($order_data['order'] as $shop_id => $order_sku) {
            if (empty($order_sku['sku'])) {
                return ['result' => false, 'message' => '没有商品信息'];
            }
            $has_store = getAddons('store', $this->website_id, $this->instance_id) ? $storeServer->getStoreSet($shop_id)['is_use'] : 0;
            if($has_store && empty($order_sku['store_id']) && $order_data['address_id']){
                $has_store = 0;
            }
            if ($order_data['shipping_type'] == 2 && $has_store && !$order_data['shop'][$shop_id]['store_id'] && !$order_data['shop'][$shop_id]['card_store_id']) {
                return ['result' => false, 'message' => '没有选择门店'];
            }
            if (empty($order_data['address_id']) && $order_data['shipping_type'] == 2 && !$has_store) {
                return ['result' => false, 'message' => '缺少收货地址'];
            }
            foreach ($order_sku['sku'] as $sku_id => $sku_info) {
                $sku_id = $sku_info['sku_id'];
                if (getAddons('seckill', $this->website_id, $this->instance_id)) {
                    $seckill_id = $sku_info['seckill_id'];
                    $condition_is_seckill['s.seckill_id'] = $seckill_id;
                    $condition_is_seckill['nsg.sku_id'] = $sku_id;
                    $is_seckill = $sec_service->isSeckillGoods($condition_is_seckill);
                    //获取秒杀商品的价格、库存、最大购买量
                    $condition_sku_info['seckill_id'] = $seckill_id;
                    $condition_sku_info['sku_id'] = $sku_id;
                    $sku_info_list = $sec_service->getSeckillSkuInfo($condition_sku_info);
                    $sku_info_arr = objToArr($sku_info_list);
                }
                if($sku_info['store_id']){
                    $store_goods_sku_model = new VslStoreGoodsSkuModel();
                    $sku_info_db = $store_goods_sku_model::get(['sku_id' => $sku_id,'store_id' => $sku_info['store_id']], ['goods']);
                }else{
                    $sku_info_db = $sku_model::get($sku_id, ['goods']);
                }
                $goods_name = $sku_info_db->goods->goods_name;
                //秒杀商品入口
                if ($seckill_id && getAddons('seckill', $this->website_id, $this->instance_id)) {
                    if ($is_seckill) {
                        if ($sku_info['num'] > $sku_info_arr['remain_num']) {
                            return ['result' => false, 'message' => $goods_name . ' 购买数目超出秒杀库存', 'operation' => 'refresh'];
                        }
                        $goods_service = new Goods();
                        //通过用户累计购买量判断，先判断redis是否有内容
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $seckill_id);
                        if ($sku_info_arr['seckill_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $sku_info_arr['seckill_limit_buy'])) {
                            return ['result' => false, 'message' => $goods_name . ' 该商品规格您总共购买数目超出最大秒杀限购数目', 'operation' => 'refresh'];
                        }

                        if ($sku_info['price'] != $sku_info_arr['seckill_price']) {
                            return ['result' => false, 'message' => $goods_name . ' 商品价格变动', 'operation' => 'refresh'];
                        }
                    }
                } else {
                    //sku 信息检查
                    if ($sku_info_db->goods->state != 1) {
                        return ['result' => false, 'message' => $goods_name . ' 物品为不可购买状态'];
                    }
                    if ($sku_info['num'] > $sku_info_db->stock) {
                        return ['result' => false, 'message' => $goods_name . ' 购买数目超出库存'];
                    }
                    if (($sku_info['num'] > $sku_info_db->goods->max_buy) && $sku_info_db->goods->max_buy != 0) {
                        return ['result' => false, 'message' => $goods_name . ' 购买数目超出最大购买数目'];
                    }
                    if ($sku_info['price'] != $sku_info_db->price) {
                        return ['result' => false, 'message' => $goods_name . ' 商品价格变动', 'operation' => 'refresh'];
                    }

                    // todo... by sgw 修改规格商品价格为会员折扣价格
                    if ($this->uid) {
                        // 查询商品是否有开启会员折扣
                        $goods_server = new GoodsService();
                        $goodsDiscountInfo = $goods_server->getGoodsInfoOfIndependentDiscount($sku_info_db['goods_id'], $sku_info_db['price']);
                        if ($goodsDiscountInfo) {
                            $member_discount = $goodsDiscountInfo['member_discount'];
                            $member_is_label = $goodsDiscountInfo['member_is_label'];
                            if ($member_discount == 1) {
                                $sku_info['price'] = $goodsDiscountInfo['member_price'];//固定价格
                            }
                        }
                    }

                    //折扣价格检查 包括 会员折扣 限时折扣
                    if (!empty($sku_info['discount_id'])) {
                        if (!getAddons('discount', $this->website_id)) {
                            return ['result' => false, 'message' => '限时折扣应用已关闭', 'operation' => 'refresh'];
                        }
                        $promotion_discount_info_db = $promotion_discount_model::get($sku_info['discount_id'], ['goods']);
                        if ($promotion_discount_info_db->status != 1) {
                            return ['result' => false, 'message' => $goods_name . ' 限时折扣状态不可用', 'operation' => 'refresh'];
                        }
                        if ($promotion_discount_info_db->start_time > $now_time || $promotion_discount_info_db->end_time < $now_time) {
                            return ['result' => false, 'message' => '限时折扣不在可用时间内', 'operation' => 'refresh'];
                        }
                        if (($promotion_discount_info_db->range_type == 1 || $promotion_discount_info_db->range_type == 3) &&
                            ($promotion_discount_info_db->shop_id != $shop_id)) {
                            return ['result' => false, 'message' => $goods_name . ' 限时折扣不在可用范围内', 'operation' => 'refresh'];
                        }
                        if ($promotion_discount_info_db->range == 2) {
                            if ($promotion_discount_info_db->goods()->where(['goods_id' => ['=', $sku_info['goods_id']]])->count() == 0) {
                                return ['result' => false, 'message' => $goods_name . ' 商品不在限时折扣指定商品范围内'];
                            }
                        }

                        // 限时折扣主表的折扣
                        if ($member_is_label) {//开启会员折扣取整
                            $member_discount_price = round($member_discount * $sku_info['price']);
                        } else {
                            $member_discount_price = round($member_discount * $sku_info['price'], 2);
                        }
                        $discount_price_1 = round(($promotion_discount_info_db->discount_num / 10) * $member_discount_price, 2);
                        // 限时折扣商品表的折扣
                        $goods_discount = $promotion_discount_info_db->goods()->where(['goods_id' => $sku_info['goods_id']])->find();
                        if ($goods_discount) {
                            $promotion_discount = $promotion_discount_model->where(['discount_id' => $goods_discount['goods_id']])->find();

                            if ($promotion_discount['integer_type'] == 1) {
                                $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price);
                            } else {
                                $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price, 2);
                            }
                            if ($goods_discount['discount_type'] == 2) {
                                $discount_price_2 = $goods_discount['discount'];
                            }
                        }
                        if ($sku_info['discount_price'] != $discount_price_1 && $sku_info['discount_price'] != $discount_price_2) {
                            return ['result' => false, 'message' => $goods_name . ' 商品折扣价格变化', 'operation' => 'refresh'];
                        }
                    } else {
                        if ($member_is_label) {//开启会员折扣取整
                            $discount_price = round($member_discount * $sku_info['price']);
                        } else {
                            $discount_price = round($member_discount * $sku_info['price'], 2);
                        }

                        $sku_info['discount_price'] = round($sku_info['discount_price'], 2);
                        if ($sku_info['discount_price'] != $discount_price) {
                            return ['result' => false, 'message' => $goods_name . ' 商品折扣价格变化', 'operation' => 'refresh'];
                        }
                    }
                }
            }
            unset($sku_info);
            //满减送信息
            if (!empty($order_data['promotion'][$shop_id]['man_song'])) {
                if (!getAddons('fullcut', $this->website_id)) {
                    return ['result' => false, 'message' => '满减送应用已关闭', 'operation' => 'refresh'];
                }
                foreach ($order_data['promotion'][$shop_id]['man_song'] as $man_song_rule_id => $man_song_info) {
                    $rule_db_info = $man_song_rule_model::get($man_song_rule_id, ['promotion_man_song.goods']);
                    if (!empty($man_song_info['full_cut']) || !empty($man_song_info['shipping'])) {
                        if ($rule_db_info->promotion_man_song->status != 1) {
                            return ['result' => false, 'message' => '满减送活动状态不可用', 'operation' => 'refresh'];
                        }
                        if ($rule_db_info->promotion_man_song->start_time > $now_time || $rule_db_info->promotion_man_song->end_time < $now_time) {
                            return ['result' => false, 'message' => '满减送活动不在可用时间内', 'operation' => 'refresh'];
                        }
                        if ($rule_db_info->promotion_man_song->range == 1 && $rule_db_info->promotion_man_song->shop_id != $shop_id) {
                            return ['result' => false, 'message' => '满减送活动不在可用店铺范围内', 'operation' => 'refresh'];
                        }
                        $man_song_compare_amount = 0.00;
                        if ($rule_db_info->promotion_man_song->range_type == 0) {
                            if ($rule_db_info->promotion_man_song->goods()->where(['goods_id' => ['IN', $order_data['shop'][$shop_id]['goods_id_array']]])->count() == 0) {
                                return ['result' => false, 'message' => '满减送活动指定可用商品变化', 'operation' => 'refresh'];
                            }
                            foreach ($order_sku['sku'] as $sku_id => $sku_info) {
                                if (in_array($sku_info['goods_id'], $man_song_info['full_cut']['goods_limit'])) {
                                    $man_song_compare_amount += $sku_info['discount_price'] * $sku_info['num'];
                                }
                            }
                            unset($sku_info);
                        } else {
                            $man_song_compare_amount = $order_data['shop'][$shop_id]['discount_amount'];
                        }
                        if ($rule_db_info->price > $man_song_compare_amount) {
                            return ['result' => false, 'message' => '满减送活动达不到金额要求', 'operation' => 'refresh'];
                        }
                        if (!empty($man_song_info['full_cut']['discount']) && $rule_db_info->discount != $man_song_info['full_cut']['discount']) {
                            return ['result' => false, 'message' => '满减送活动优惠金额变化', 'operation' => 'refresh'];
                        }
                        if (!empty($man_song_info['shipping']['free_shipping_fee']) && $man_song_info['shipping']['free_shipping_fee'] && $rule_db_info->free_shipping != 1) {
                            return ['result' => false, 'message' => '满减送活动包邮活动变化', 'operation' => 'refresh'];
                        }
                    }
                }
                unset($man_song_info);
            }

            //优惠券信息
            if (!empty($order_data['promotion'][$shop_id]['coupon'])) {
                if (!getAddons('coupontype', $this->website_id)) {
                    return ['result' => false, 'message' => '优惠券应用已关闭', 'operation' => 'refresh'];
                }
                $coupon_info_db = $coupon_model::get($order_data['promotion'][$shop_id]['coupon']['coupon_id'], ['coupon_type.goods']);
                if ($coupon_info_db->state != 1) {
                    return ['result' => false, 'message' => '优惠券状态不可用', 'operation' => 'refresh'];
                }
                if ($coupon_info_db->coupon_type->start_time > $now_time || $coupon_info_db->coupon_type->end_time < $now_time) {
                    return ['result' => false, 'message' => '优惠券不在可用时间内', 'operation' => 'refresh'];
                }
                if ($coupon_info_db->coupon_type->shop_range_type == 1 && $coupon_info_db->coupon_type->shop_id != $shop_id) {
                    return ['result' => false, 'message' => '优惠券不在可用店铺范围内', 'operation' => 'refresh'];
                }
                if ($coupon_info_db->uid != $this->uid) {
                    return ['result' => false, 'message' => '优惠券持有者错误', 'operation' => 'refresh'];
                }
                if ($order_data['promotion'][$shop_id]['coupon']['coupon_genre'] == 1 || $order_data['promotion'][$shop_id]['coupon']['coupon_genre'] == 2) {
                    if ($order_data['promotion'][$shop_id]['coupon']['money'] != $coupon_info_db->coupon_type->money) {
                        return ['result' => false, 'message' => '优惠券优惠金额变化', 'operation' => 'refresh'];
                    }
                } elseif ($order_data['promotion'][$shop_id]['coupon']['coupon_genre'] == 3) {
                    if ($order_data['promotion'][$shop_id]['coupon']['discount'] != $coupon_info_db->coupon_type->discount) {
                        return ['result' => false, 'message' => '优惠券折扣变化', 'operation' => 'refresh'];
                    }
                } else {
                    return ['result' => false, 'message' => '优惠券类型不存在'];
                }
                $coupon_compare_amount = 0.00;
                if ($coupon_info_db->coupon_type->range_type == 0) {
                    if ($coupon_info_db->coupon_type->goods()->where(['goods_id' => ['IN', $order_data['shop'][$shop_id]['goods_id_array']]])->count() == 0) {
                        return ['result' => false, 'message' => '优惠券活动指定可用商品变化', 'operation' => 'refresh'];
                    }
                    foreach ($order_sku['sku'] as $sku_id => $sku_info) {
                        if (in_array($sku_info['goods_id'], $order_data['promotion'][$shop_id]['coupon']['goods_limit'])) {
                            $coupon_compare_amount += $sku_info['discount_price'] * $sku_info['num'];
                        }
                        if ($sku_info['full_cut_sku_percent'] > 0 && $sku_info['full_cut_sku_amount'] > 0) {
                            $coupon_compare_amount -= $sku_info['full_cut_sku_percent'] * $sku_info['full_cut_sku_amount'];
                        }
                    }
                    unset($sku_info);
                } else {
                    foreach ($order_sku['sku'] as $sku_id => $sku_info) {
                        $coupon_compare_amount += $sku_info['discount_price'] * $sku_info['num'];
                        if ($sku_info['full_cut_sku_percent'] > 0 && $sku_info['full_cut_sku_amount'] > 0) {
                            $coupon_compare_amount -= $sku_info['full_cut_sku_percent'] * $sku_info['full_cut_sku_amount'];
                        }
                    }
                    unset($sku_info);
                }
                if ($coupon_info_db->coupon_type->at_least > $coupon_compare_amount) {
                    return ['result' => false, 'message' => '优惠券达不到金额要求', 'operation' => 'refresh'];
                }
            }
        }
        unset($order_sku);
        return ['result' => true, 'message' => 'ok'];
    }
    /**
     * 计算移动端/app 提交创建订单 获取会员折扣 限时折扣 分销等级折扣
     * @param int $sku_id 规格项id
     */
    public function getDiscountPrice($sku_id){
        //规格项id 换取当前商品规格的售价 ，已经折扣设置等
        $user_model = new UserModel();
        $sku_model = new VslGoodsSkuModel();
        $sku_db_info = $sku_model::get($sku_id, ['goods']);
        $price = $sku_db_info->goods->price;
        // 是否开启会员折扣 会员折扣顺序: 独立分销等级折扣>独立会员等级折扣>会员折扣
        $is_member_discount = $sku_db_info->goods->is_member_discount;

        $goods_id = $sku_db_info->goods->goods_id;

        // 查询商品是否有开启会员折扣
        $GoodsService = new GoodsService();
        $goodsPower = $GoodsService->getGoodsPowerDiscount($goods_id);
        $discountVal = json_decode($goodsPower['value'],true);

        $is_user_obj_open = $discountVal['is_user_obj_open']; //会员折扣是否开启
        $is_distributor_obj_open = $discountVal['is_distributor_obj_open']; //分销这块是否开启
        // $distributor_independent = $sku_db_info->goods->distributor_independent;
        $user_info = $user_model::get($this->uid, ['member_info.level', 'member_account', 'member_address']);

        if($is_member_discount == 0){
            $member_discount = $user_info->member_info->level->goods_discount ? ($user_info->member_info->level->goods_discount / 10) : 1; //会员折扣
            $member_is_label = $user_info->member_info->level->is_label;
            //会员折扣金额
            if($member_is_label){
                $member_price = round($member_discount * $price);
            }else{
                $member_price = round($member_discount * $price,2);
            }

            //独立会员折扣
            if($is_user_obj_open == 1 && $discountVal['user_obj']['u_discount_choice'] == 1){ //折扣
                //获取当前会员等级
                $member_level = $user_info->member_info->member_level;
                $member_discount = '';
                foreach ($discountVal['user_obj']['u_level_data'] as $key => $value) {
                    if($key == $member_level){
                        $member_discount = $value['val'];
                    }
                }
                if($discountVal['user_obj']['u_is_label']){
                    $member_price = empty($member_discount) ? $price : round($member_discount * $price);
                }else{
                    $member_price = empty($member_discount) ? $price : round($member_discount * $price,2);
                }

            }else if($is_user_obj_open == 1 && $discountVal['user_obj']['u_discount_choice'] == 2){//固定金额
                //获取当前会员等级
                $member_level = $user_info->member_info->member_level;
                $member_price = $price;
                foreach ($discountVal['user_obj']['u_level_data'] as $key => $value) {
                    if($key == $member_level){
                        $member_price = $value['val'];
                    }
                }
            }
            //独立分销折扣
            if($is_distributor_obj_open == 1 && $discountVal['distributor_obj']['d_discount_choice'] == 1){ //折扣
                //获取会员分销折扣
                $distributor_level_id = $user_info->member_info->distributor_level_id;
                $isdistributor = $user_info->member_info->isdistributor;
                if($isdistributor == 2){
                    $member_discount = '';
                    foreach ($discountVal['distributor_obj']['d_level_data'] as $key => $value) {
                        if($key == $distributor_level_id){
                            $member_discount = $value['val'];
                        }
                    }
                    if($discountVal['distributor_obj']['d_is_label']){
                        $member_price = empty($member_discount) ? $price : round($member_discount * $price);
                    }else{
                        $member_price = empty($member_discount) ? $price : round($member_discount * $price,2);
                    }
                }
            }else if($is_distributor_obj_open == 1 && $discountVal['distributor_obj']['d_discount_choice'] == 2){//固定金额
                //获取当前会员等级
                $distributor_level_id = $user_info->member_info->distributor_level_id;
                $isdistributor = $user_info->member_info->isdistributor;
                if($isdistributor == 2){
                    foreach ($discountVal['distributor_obj']['d_level_data'] as $key => $value) {
                        if($key == $member_level){
                            $member_price = $value['val'];
                        }
                    }
                }

            }
        }
        return $member_price;
        //是否开启独立会员等级折扣

        //是否开启独立分销等级折扣

    }
    /**
     * 重组创建订单所需数组
     */
    public function calculateCreateOrderDataTesy(array $order_data, $is_free_shipping = 0, $website_id=0, $uid=0)
    {
        if(!$website_id){
            $website_id = $this->website_id;
        }
        if(!$uid){
            $uid = $this->uid;
        }
        $shop_id = $this->instance_id;
        $redis = connectRedis();
        $now_time = time();
        $promotion_discount_model = getAddons('discount', $website_id) ? new VslPromotionDiscountModel() : '';
        $sec_service = getAddons('seckill', $website_id, $shop_id) ? new seckillServer() : '';
        $group_server = getAddons('groupshopping', $website_id, $shop_id) ? new GroupShopping() : '';
        $has_store = getAddons('store', $website_id, $shop_id) ? 1 : 0;
        $return_data['address_id'] = $order_data['address_id'];
        $return_data['group_id'] = $order_data['group_id'];
        $return_data['luckyspell_id'] = $order_data['luckyspell_id'];
        $return_data['record_id'] = $order_data['record_id'];
        $return_data['shipping_type'] = $order_data['shipping_type'];
        $return_data['is_deduction'] = $order_data['is_deduction'];
        foreach ($order_data['shop_list'] as $shop) {
            //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
            if($has_store) {
                $storeServer = new storeServer();
                $stock_type = $storeServer->getStoreSet($shop['shop_id'])['stock_type'] ? $storeServer->getStoreSet($shop['shop_id'])['stock_type'] : 1;
            }else{
                $stock_type = 2;
            }

            $shop_id = $shop['shop_id'];
            $return_data['shop'][$shop_id]['store_id'] = $shop['store_id'] ?: 0;
            $return_data['shop'][$shop_id]['card_store_id'] = (empty($shop['card_store_id'])) ? 0 : $shop['card_store_id'];
            $return_data['shop'][$shop_id]['shop_channel_amount'] = 0;
            $return_data['shop'][$shop_id]['leave_message'] = $shop['leave_message'] ?: '';
            //处理店铺商品
            foreach ($shop['goods_list'] as $k => $sku_info) {
                //循环 处理单商品信息
                if($shop['card_store_id'] && $stock_type == 1){
                    //计时计次商品
                    $sku_model = new VslStoreGoodsSkuModel();
                    $sku_db_info = $sku_model::get(['sku_id' => $sku_info['sku_id'],'store_id'=>$shop['card_store_id']], ['goods']);
                }elseif ($shop['store_id'] && $stock_type == 1) {
                    //线下自提
                    $sku_model = new VslStoreGoodsSkuModel();
                    $sku_db_info = $sku_model::get(['sku_id' => $sku_info['sku_id'],'store_id'=>$shop['store_id']], ['goods']);
                }else{
                    $sku_model = new VslGoodsSkuModel();
                    $sku_db_info = $sku_model::get($sku_info['sku_id'], ['goods']);
                }

                $temp_sku_id = $sku_info['sku_id'];
                $return_data['order'][$shop_id]['sku'][$k]['sku_id'] = $temp_sku_id;
                $return_data['order'][$shop_id]['sku'][$k]['goods_id'] = $sku_info['goods_id'];
                $return_data['order'][$shop_id]['sku'][$k]['channel_id'] = $sku_info['channel_id'];
                $return_data['order'][$shop_id]['sku'][$k]['seckill_id'] = $sku_info['seckill_id'];
                $return_data['order'][$shop_id]['sku'][$k]['seckill_id'] = $sku_info['seckill_id'];
                if ($sku_info['presell_id'] && getAddons('presell', $website_id, $shop_id)) {
                    $presell_key = 'presell_'.$sku_info['presell_id'].'_'.$sku_info['sku_id'];
                    //判断预售是否关闭或者过期
                    $presell_id = $sku_info['presell_id'];
                    //如果是预售的商品，则更改其单价为预售价
                    $presell_mdl = new VslPresellModel();
                    $presell_condition['p.id'] = $presell_id;
                    $presell_condition['p.start_time'] = ['<', time()];
                    $presell_condition['p.end_time'] = ['>=', time()];
                    $presell_condition['p.status'] = ['neq', 3];
                    $presell_condition['pg.sku_id'] = $sku_info['sku_id'];
                    $presell_goods_info = $presell_mdl->alias('p')->where($presell_condition)->join('vsl_presell_goods pg', 'p.id = pg.presell_id', 'LEFT')->find();
                    if (!$presell_goods_info) {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($presell_key);
                        }
                        return ['result' => -2, 'message' => '预售活动已过期或已关闭'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['presell_id'] = $sku_info['presell_id'];
                } else {
                    $return_data['order'][$shop_id]['sku'][$k]['presell_id'] = 0;
                }

                $return_data['order'][$shop_id]['sku'][$k]['bargain_id'] = $sku_info['bargain_id'];
                $return_data['order'][$shop_id]['sku'][$k]['price'] = $sku_info['price'];
                //会员价
                $return_data['order'][$shop_id]['sku'][$k]['member_price'] = $sku_info['member_price'];
                $return_data['order'][$shop_id]['sku'][$k]['discount_id'] = $sku_info['discount_id'];
                $return_data['order'][$shop_id]['sku'][$k]['discount_price'] = $sku_info['discount_price'];
                $return_data['order'][$shop_id]['sku'][$k]['num'] = $sku_info['num'];
                $return_data['order'][$shop_id]['sku'][$k]['shop_id'] = $sku_db_info->goods->shop_id;
                $return_data['order'][$shop_id]['sku'][$k]['point_deduction_max'] = $sku_db_info->goods->point_deduction_max;
                $return_data['order'][$shop_id]['sku'][$k]['point_return_max'] = $sku_db_info->goods->point_return_max;

                $return_data['shop'][$shop_id]['goods_id_array'][] = $sku_info['goods_id'];
                $return_data['shop'][$shop_id]['member_amount'] += $return_data['order'][$shop_id]['sku'][$k]['member_price'] * $sku_info['num'];
                $return_data['shop'][$shop_id]['shop_total_amount'] += $sku_info['price'] * $sku_info['num'];
                $return_data['shop'][$shop_id]['shop_discount_amount'] += $sku_info['discount_price'] ? ($sku_info['discount_price'] * $sku_info['num']) : 0;

                if (getAddons('groupshopping', $website_id, $shop_id)) {
                    $is_group = $group_server->isGroupGoods($sku_info['goods_id']);//是否团购商品
                    $group_sku_info_obj = $group_server->getGroupSkuInfo(['sku_id' => $sku_info['sku_id'], 'goods_id' => $sku_info['goods_id'], 'group_id' => $is_group]);
                    $group_sku_info_arr = objToArr($group_sku_info_obj);//商品团购信息
                }
                if (getAddons('luckyspell', $website_id, $shop_id) && $return_data['luckyspell_id'] && $return_data['luckyspell_id']>0) {
                    $group_server = new luckySpellServer();
                    $is_luckyspell = $group_server->isGroupGoods($sku_info['goods_id']);//是否团购商品
                    $group_sku_info_obj = $group_server->getGroupSkuInfo(['sku_id' => $sku_info['sku_id'], 'goods_id' => $sku_info['goods_id'], 'group_id' => $return_data['luckyspell_id']]);
                    $group_sku_info_arr = objToArr($group_sku_info_obj);//商品团购信息
                }

                //快递配送或者无需物流，如果上级渠道商有库存，则优先扣上级渠道商的库存
                if($return_data['shipping_type'] == 1 || empty($return_data['shipping_type'])) {
                    if (!empty($sku_info['channel_id']) && getAddons('channel', $website_id)) {
                        $channel_key = 'channel_'.$sku_info['channel_id'].'_'.$sku_info['sku_id'];
                        $channel_gs_mdl = new VslChannelGoodsSkuModel();
                        $channel_gs_info = $channel_gs_mdl->getInfo(['website_id' => $website_id, 'channel_id' => $sku_info['channel_id'], 'sku_id' => $sku_info['sku_id']], 'stock');

                        if (($sku_info['num'] > $channel_gs_info['stock'] + $sku_db_info->stock)) {
                            for($s=0;$s<$sku_info['num'];$s++){
                                $redis->incr($channel_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . '商品库存不足'];
                        }else{
                            $cut_channel_stock = 0;
                            $sku_db_info->stock = $channel_gs_info['stock'] + $sku_db_info->stock;
                            //判断渠道商的库存够不够扣此次的购买量，不够就扣平台的
                            if($sku_info['num'] - $channel_gs_info['stock'] <= 0) {
                                //全部扣渠道商的库存
                                $cut_channel_stock = $sku_info['num'];
                            }elseif ($sku_info['num'] - $channel_gs_info['stock'] > 0) {
                                //部分扣渠道商的
                                $cut_channel_stock = $channel_gs_info['stock'];
                            }
                            $return_data['order'][$shop_id]['sku'][$k]['channel_stock'] =  $cut_channel_stock;
                        }
                    }
                }

                if (!empty($sku_info['seckill_id']) && getAddons('seckill', $website_id, $shop_id)) {
                    $redis_goods_sku_seckill_key = 'seckill_' . $sku_info['seckill_id'] . '_' . $sku_info['goods_id'] . '_' . $sku_info['sku_id'];
                    //判断秒杀商品是否过期
                    $sku_id = $sku_info['sku_id'];
                    $seckill_id = $sku_info['seckill_id'];
                    $condition_is_seckill['s.seckill_id'] = $seckill_id;
                    $condition_is_seckill['nsg.sku_id'] = $sku_id;
                    $is_seckill = $sec_service->isSeckillGoods($condition_is_seckill);
                    if ($is_seckill) {
                        //获取秒杀商品的价格、库存、最大购买量
                        $condition_sku_info['seckill_id'] = $seckill_id;
                        $condition_sku_info['sku_id'] = $sku_id;
                        $sku_info_list = $sec_service->getSeckillSkuInfo($condition_sku_info);
                        $sku_info_arr = objToArr($sku_info_list);
                        //获取限购量
                        $goods_service = new Goods();
                        //通过用户累计购买量判断，先判断redis是否有内容
                        $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $sku_info['seckill_id']);
                        if ($sku_info['num'] > $is_seckill['seckill_num']) {
                            for($s=0;$s<$sku_info['num'];$s++){
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                        }
                        //判断是否超过限购
                        if ($sku_info_arr['seckill_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $sku_info_arr['seckill_limit_buy'])) {
                            for($s=0;$s<$sku_info['num'];$s++){
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格购买数目已经超出最大秒杀限购数目'];
                        }
                        //价格
                        if ($is_seckill['seckill_price'] != $sku_info['price']) {
                            for($s=0;$s<$sku_info['num'];$s++){
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 秒杀商品价格变动'];
                        }
                    } else {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($redis_goods_sku_seckill_key);
                        }
                        return ['result' => -2, 'message' => $sku_db_info->goods->goods_name . '商品秒杀已结束，将恢复正常商品价格。'];
                    }
                } elseif ($is_group && $order_data['group_id'] && getAddons('groupshopping', $website_id, $shop_id))
                { //拼团 不确定 待测试
                    $goods_key = 'goods_'.$sku_info['goods_id'].'_'.$sku_info['sku_id'];
                    if (($sku_info['num'] > $sku_db_info->stock)) {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }
                    if ($order_data['record_id']) {
                        $checkGroup = $group_server->checkGroupIsCan($order_data['record_id'], $uid);//判断该团购是否能参加
                        if ($checkGroup < 0) {
                            for($s=0;$s<$sku_info['num'];$s++){
                                $redis->incr($goods_key);
                            }
                            return ['result' => false, 'message' => '该团无法参加，请选择其他团或者自己发起团购'];
                        }
                    }
                    $goods_service = new Goods();
                    //通过用户累计购买量判断
                    $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $sku_info['sku_id'], $website_id, $order_data['group_id']);
                    if ($group_sku_info_arr['group_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $group_sku_info_arr['group_limit_buy'])) {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大拼团限购数目'];
                    }
                    //                    var_dump($sku_info['price'], $group_sku_info_arr['group_price']);exit;
                    if ($sku_info['price'] != $group_sku_info_arr['group_price']) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 团购商品价格变动'];
                    }
                }elseif ($is_luckyspell && $order_data['luckyspell_id'] && getAddons('luckyspell', $website_id, $shop_id))
                { //幸运拼团 新增

                    $goods_key = 'goods_'.$sku_info['goods_id'].'_'.$sku_info['sku_id'];
                    if (($sku_info['num'] > $sku_db_info->stock)) {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($goods_key);
                        }
                        // return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }

                    $goods_service = new Goods();
                    //通过用户累计购买量判断
                    $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $sku_info['sku_id'], $website_id, $order_data['luckyspell_id'],6);
                    if ($group_sku_info_arr['group_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $group_sku_info_arr['group_limit_buy'])) {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($goods_key);
                        }
                        // return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大幸运拼团限购数目'];
                    }

                    if ($sku_info['price'] != $group_sku_info_arr['group_price']) {
                        for($s=0;$s<$sku_info['num'];$s++){
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 幸运团购商品价格变动'];
                    }
                } elseif (!empty($sku_info['bargain_id']) && getAddons('bargain', $website_id, $shop_id)) {//砍价
                    $bargain_key = 'bargain_' . $sku_info['bargain_id'] . '_' . $sku_info['sku_id'];
                    $bargain = new Bargain();
                    $order_server = new \data\service\Order\Order();
                    $condition_bargain['bargain_id'] = $sku_info['bargain_id'];
                    $condition_bargain['website_id'] = $website_id;
                    $is_bargain = $bargain->isBargain($condition_bargain, $uid);
                    if ($is_bargain) {
                        $return_data['bargain_id'] = $sku_info['bargain_id'];
                        //库存
                        $bargain_stock = $is_bargain['bargain_stock'];
                        $limit_buy = $is_bargain['limit_buy'];
                        $price = $is_bargain['my_bargain']['now_bargain_money'];
                        $buy_num = $order_server->getActivityOrderSkuNum($uid, $sku_info['sku_id'], $website_id, 3, $sku_info['bargain_id']);
                        if (($sku_info['num'] > $bargain_stock)) {
                            for ($s = 0; $s < $sku_info['num']; $s++) {
                                $redis->incr($bargain_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出砍价活动库存'];
                        }
                        if ($limit_buy != 0 && ($sku_info['num'] + $buy_num > $limit_buy)) {
                            for ($s = 0; $s < $sku_info['num']; $s++) {
                                $redis->incr($bargain_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大砍价限购数目'];
                        }
                        //价格
                        if ($sku_info['price'] != $price) {
                            for ($s = 0; $s < $sku_info['num']; $s++) {
                                $redis->incr($bargain_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 砍价商品价格变动'];
                        }

                    } else {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($bargain_key);
                        }
                        return ['result' => -2, 'message' => '砍价活动已过期或已关闭'];
                    }
                } elseif (!empty($sku_info['presell_id']) && getAddons('presell', $website_id, $shop_id)) {
                    $presell_key = 'presell_' . $sku_info['presell_id'] . '_' . $sku_info['sku_id'];
                    $presell_service = new PresellService();
                    $count_people = $presell_service->getPresellBuyNum($sku_info['presell_id']);
                    $presell_info = $presell_service->getPresellBySku($sku_info['presell_id'], $sku_info['sku_id']);
                    if (($presell_info['presellnum'] < $count_people)) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($presell_key);
                        }
                        return ['result' => false, 'message' => '预售商品库存不足'];
                    }
                    $user_buy = $presell_service->getUserCount($sku_info['presell_id']);//当前用户购买数
                    if (($user_buy > $presell_info['maxbuy']) && $presell_info['maxbuy'] != 0) {//当前用户购买数大于每人限购
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($presell_key);
                        }
                        return ['result' => false, 'message' => '您已达到商品预售购买上限'];
                    }
                    //付定金去掉运费
                    $shipping_fee_all[$shop_id] = 0;
                } else {
                    if (($stock_type == 1 && $shop['card_store_id']) || ($stock_type == 1 && $shop['store_id'])) {
                        $goods_key = 'store_goods_' . $sku_info['goods_id'] . '_' . $sku_info['sku_id'];
                    } else {
                        $goods_key = 'goods_' . $sku_info['goods_id'] . '_' . $sku_info['sku_id'];
                    }
                    // 普通商品
                    //sku 信息检查
                    if ($sku_db_info->goods->state != 1) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 物品为不可购买状态'];
                    }
                    if ($sku_info['num'] > $sku_db_info->stock) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }
                    if (($sku_info['num'] > $sku_db_info->goods->max_buy) && $sku_db_info->goods->max_buy != 0) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出最大购买数目'];
                    }

                    if ($sku_info['price'] != $sku_db_info->price) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_info->goods->goods_name . ' 商品价格变动'];
                    }
                }
                // 限时折扣
                $discount_price = $sku_info['discount_price'];
                if (!empty($sku_info['discount_id'])) {
                    if (!getAddons('discount', $website_id)) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => '限时折扣应用已关闭'];
                    }
                    $promotion_discount_info_db = $promotion_discount_model::get($sku_info['discount_id'], ['goods']);
                    if ($promotion_discount_info_db->status != 1) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . '限时折扣状态不可用'];
                    }
                    if ($promotion_discount_info_db->start_time > $now_time || $promotion_discount_info_db->end_time < $now_time) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => '限时折扣不在可用时间内'];
                    }
                    if (($promotion_discount_info_db->range_type == 1 || $promotion_discount_info_db->range_type == 3) &&
                        ($promotion_discount_info_db->shop_id != $shop_id)) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 限时折扣不在可用范围内'];
                    }
                    if ($promotion_discount_info_db->range == 2) {
                        if ($promotion_discount_info_db->goods()->where(['goods_id' => ['=', $sku_db_info->goods_id]])->count() == 0) {
                            for ($s = 0; $s < $sku_info['num']; $s++) {
                                $redis->incr($goods_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 商品不在限时折扣指定商品范围内'];
                        }
                    }
                    // 会员折扣
                    $member_discount_price = $sku_info['discount_price'];
                    $discount_price_1 = round(($promotion_discount_info_db->discount_num / 10) * $member_discount_price, 2);
                    // 限时抢购商品表的折扣
                    $goods_discount = $promotion_discount_info_db->goods()->where(['goods_id' => $sku_info['goods_id']])->find();
                    if ($goods_discount) {
                        $promotion_discount = $promotion_discount_model->where(['discount_id' => $goods_discount['goods_id']])->find();
                        if ($promotion_discount['integer_type'] == 1) {
                            $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price);
                        } else {
                            $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price, 2);
                        }
                        if ($goods_discount['discount_type'] == 2) {
                            $discount_price_2 = $goods_discount['discount'];
                        }
                    } else if ($promotion_discount_info_db->range == 3) {//分类
                        if ($promotion_discount_info_db->integer_type == 1) {
                            $discount_price_2 = round($promotion_discount_info_db->uniform_discount / 10 * $member_discount_price);
                        } else {
                            $discount_price_2 = round($promotion_discount_info_db->uniform_discount / 10 * $member_discount_price, 2);
                        }
                        if ($promotion_discount_info_db->discount_type == 2) {
                            $discount_price_2 = $promotion_discount_info_db->uniform_price;
                        }
                    }

                    if ($discount_price != $discount_price_1 && $discount_price != $discount_price_2) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 商品折扣价格变化'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['promotion_shop_id'] = $promotion_discount_info_db->shop_id;// 限时折扣店铺id，用于识别优惠来源
                }
                //计时/次商品
                $goodsSer = new GoodsService();
                $goods_info = $goodsSer->getGoodsDetailById($sku_info['goods_id']);
                $return_data['order'][$shop_id]['sku'][$k]['shipping_fee'] = $goods_info['shipping_fee_type'] == 1 ? ($goods_info['shipping_fee'] ?: 0) : 0;
                if ($is_free_shipping) {
                    $return_data['order'][$shop_id]['sku'][$k]['shipping_fee'] = 0;
                }
                $return_data['order'][$shop_id]['sku'][$k]['shipping_fee_type'] = $goods_info['shipping_fee_type'];
                if (getAddons('store', $website_id, $shop_id) && $goods_info['goods_type'] == 0) {
                    $return_data['address_id'] = 0;
                    if ($return_data['shop'][$shop_id]['card_store_id'] == 0) {
                        for ($s = 0; $s < $sku_info['num']; $s++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => '请选择使用门店'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['card_store_id'] = $return_data['shop'][$shop_id]['card_store_id'];
                    $return_data['order'][$shop_id]['sku'][$k]['cancle_times'] = $goods_info['cancle_times'];
                    $return_data['order'][$shop_id]['sku'][$k]['cart_type'] = $goods_info['cart_type'];
                    if ($goods_info['valid_type'] == 1) {
                        $return_data['order'][$shop_id]['sku'][$k]['invalid_time'] = time() + $goods_info['valid_days'] * 24 * 60 * 60;
                    } else {
                        $return_data['order'][$shop_id]['sku'][$k]['invalid_time'] = $goods_info['invalid_time'];
                    }
                    if ($goods_info['is_wxcard'] == 1) {
                        $return_data['order'][$shop_id]['sku'][$k]['wx_card_id'] = $goods_info['wx_card_id'];
                        $ticket = new VslGoodsTicketModel();
                        $ticket_info = $ticket->getInfo(['goods_id' => $sku_info['goods_id']]);
                        $return_data['order'][$shop_id]['sku'][$k]['card_title'] = $ticket_info['card_title'];
                    }
                }
            }
            unset($sku_info);
        }
        unset($shop);
        return $return_data;

    }

    /**
     * 计算移动端/app 提交创建订单所需数据
     * @param array $order_data
     */
    public function calculateCreateOrderData(array $order_data)
    {
        // $order_data => $shop  => $sku_info
        $now_time = time();
        $man_song_rule_model = getAddons('fullcut', $this->website_id) ? new VslPromotionMansongRuleModel() : '';
        $sku_model = new VslGoodsSkuModel();
        $coupon_model = getAddons('coupontype', $this->website_id) ? new VslCouponModel() : '';
        $promotion_discount_model = getAddons('discount', $this->website_id, $this->instance_id) ? new VslPromotionDiscountModel() : '';
        $user_model = new UserModel();
        $sec_service = getAddons('seckill', $this->website_id, $this->instance_id) ? new seckillServer() : '';
        $goodsExpress = new GoodsExpress();
        $group_server = getAddons('groupshopping', $this->website_id, $this->instance_id) ? new GroupShopping() : '';
        $storeServer = getAddons('store', $this->website_id, $this->instance_id) ? new Store() : '';
        $user_info = $user_model::get($this->uid, ['member_info.level', 'member_account', 'member_address']);
        if ($user_info->user_status == 0) {
            return ['result' => false, 'message' => '当前用户状态不能购买'];
        }
        if (empty($order_data['address_id']) && $order_data['shipping_type'] == 1) {
            return ['result' => false, 'message' => '缺少收货地址'];
        }

        $return_data['address_id'] = $order_data['address_id'];
        $return_data['group_id'] = $order_data['group_id'];
        $return_data['record_id'] = $order_data['record_id'];
        $return_data['shipping_type'] = $order_data['shipping_type'];
        $return_data['is_deduction'] = $order_data['is_deduction'];
        $member_discount = $user_info->member_info->level->goods_discount ? ($user_info->member_info->level->goods_discount / 10) : 1;
        $member_is_label = $user_info->member_info->level->is_label;
        $return_data['order'] = [];
        $return_data['promotion'] = [];
        $return_data['shop'] = [];
        if (empty($order_data['shop_list'])) {
            return ['result' => false, 'message' => '空的商品信息'];
        }
        // 准备一些数据
        // 计算运费        满件送/优惠券每个sku优惠占比  每个店铺购买商品总数，计算每个sku邮费占比
        $shipping_goods = $promotion_shop_amount = $shop_goods_num = [];

        foreach ($order_data['shop_list'] as $shop) {

            $shop_id = $shop['shop_id'];
            $promotion_shop_amount[$shop_id] = 0;
            $shop_goods_num[$shop_id] = 0;
            $has_store = getAddons('store', $this->website_id, $this->instance_id) ? $storeServer->getStoreSet($shop_id)['is_use'] : 0;
            if ($order_data['shipping_type'] == 2 && $has_store && !$shop['store_id'] && empty($shop['card_store_id'])) {
                return ['result' => false, 'message' => '没有选择门店'];
            }
            if (empty($order_data['address_id']) && $order_data['shipping_type'] == 2 && !$has_store && empty($shop['card_store_id'])) {
                return ['result' => false, 'message' => '缺少收货地址'];
            }
            $return_data['shop'][$shop_id]['leave_message'] = $shop['leave_message'] ?: '';
            $return_data['shop'][$shop_id]['store_id'] = $shop['store_id'] ?: 0;
            $return_data['shop'][$shop_id]['card_store_id'] = (empty($shop['card_store_id'])) ? 0 : $shop['card_store_id'];

            foreach ($shop['goods_list'] as $sku_info) {
                $goods_id = $sku_info['goods_id'];
                $shop_goods_num[$shop_id] += $sku_info['num'];
                if (empty($shipping_goods[$shop_id][$goods_id])) {
                    $shipping_goods[$shop_id][$goods_id]['goods_id'] = $goods_id;
                    $shipping_goods[$shop_id][$goods_id]['count'] = $sku_info['num'];
                } else {
                    $shipping_goods[$shop_id][$goods_id]['count'] += $sku_info['num'];
                }
                if (empty($sku_info['seckill_id']) && empty($order_data['record_id'])) {
                    $promotion_shop_amount[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                }
            }
            unset($sku_info);
        }
        unset($shop);
        // 获取以每个店铺,每个店铺下面每个商品(goods_id)的邮费,邮费与goods_id绑定
        $district_id = $user_info->member_address()->where(['id' => $order_data['address_id']])->find()['district'];
        $shipping_fee_all = [];
        $unShippingTypeCount = [];//没使用运费模板的数量
        $unShippingTypeMoney = [];//没使用运费模板的运费金额
        if (!empty($shipping_goods)) {
            /*
            * 修复bug 运费模板体积重量件数叠加计算运费
            * $author ljt 2018-08-05
            */
            foreach ($shipping_goods as $shop_id => $goods) {
                $return_data['shop'][$shop_id]['shipping_fee'] = 0;
                $tempgoods = [];


                foreach ($goods as $goods_id => $info) {
                    $tempgoods[$goods_id]['count'] = $info['count'];
                    $tempgoods[$goods_id]['goods_id'] = $info['goods_id'];
                }
                unset($info);
                $fee = $goodsExpress->getGoodsExpressTemplate($tempgoods, $district_id);
                $shippingType = $goodsExpress->getGoodsExpressTemplate($tempgoods, $district_id);
                $fee = $shippingType['totalFee'];

                if ($order_data['shipping_type'] == 2) {
                    $fee = 0;
                }
                //$shipping_goods[$shop_id][$goods]['fee'] = $fee;
                $shipping_fee_all[$shop_id] = $fee;
                $unShippingTypeCount[$shop_id] = $shippingType['unShippingType'];
                $unShippingTypeMoney[$shop_id] = $shippingType['unShippingTypeMoney'];

            }
            unset($goods);
        }


        // start 计算,校验
        foreach ($order_data['shop_list'] as $shop) {
            $shop_id = $shop['shop_id'];
            $return_data['shop'][$shop_id]['shop_channel_amount'] = 0;

            //处理店铺商品
            foreach ($shop['goods_list'] as $k => $sku_info) {
                //循环 处理单商品信息
                $sku_db_info = $sku_model::get($sku_info['sku_id'], ['goods']);
                $temp_sku_id = $sku_info['sku_id'];
                $return_data['order'][$shop_id]['sku'][$k]['sku_id'] = $temp_sku_id;
                $return_data['order'][$shop_id]['sku'][$k]['goods_id'] = $sku_info['goods_id'];
                $return_data['order'][$shop_id]['sku'][$k]['channel_id'] = $sku_info['channel_id'];
                $return_data['order'][$shop_id]['sku'][$k]['seckill_id'] = $sku_info['seckill_id'];
                $return_data['order'][$shop_id]['sku'][$k]['seckill_id'] = $sku_info['seckill_id'];
                if ($sku_info['presell_id'] && getAddons('presell', $this->website_id, $this->instance_id)) {
                    //判断预售是否关闭或者过期
                    $presell_id = $sku_info['presell_id'];
                    //如果是预售的商品，则更改其单价为预售价
                    $presell_mdl = new VslPresellModel();
                    $presell_condition['p.id'] = $presell_id;
                    $presell_condition['p.start_time'] = ['<', time()];
                    $presell_condition['p.end_time'] = ['>=', time()];
                    $presell_condition['p.status'] = ['neq', 3];
                    $presell_condition['pg.sku_id'] = $sku_info['sku_id'];
                    $presell_goods_info = $presell_mdl->alias('p')->where($presell_condition)->join('vsl_presell_goods pg', 'p.id = pg.presell_id', 'LEFT')->find();
                    if (!$presell_goods_info) {
                        return ['result' => -2, 'message' => '预售活动已过期或已关闭'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['presell_id'] = $sku_info['presell_id'];
                } else {
                    $return_data['order'][$shop_id]['sku'][$k]['presell_id'] = 0;
                }

                $return_data['order'][$shop_id]['sku'][$k]['bargain_id'] = $sku_info['bargain_id'];
                $return_data['order'][$shop_id]['sku'][$k]['price'] = $sku_info['price'];

                //获取会员折扣 auth 拉登&2019/10/17
                $return_data['order'][$shop_id]['sku'][$k]['member_price'] = $this->getDiscountPrice($sku_info['sku_id']);

                $return_data['order'][$shop_id]['sku'][$k]['discount_id'] = $sku_info['discount_id'];
                $return_data['order'][$shop_id]['sku'][$k]['discount_price'] = $sku_info['discount_price'];
                $return_data['order'][$shop_id]['sku'][$k]['num'] = $sku_info['num'];
                $return_data['order'][$shop_id]['sku'][$k]['shop_id'] = $sku_db_info->goods->shop_id;
                $return_data['order'][$shop_id]['sku'][$k]['point_deduction_max'] = $sku_db_info->goods->point_deduction_max;
                $return_data['order'][$shop_id]['sku'][$k]['point_return_max'] = $sku_db_info->goods->point_return_max;
                //$return_data['order'][$shop_id]['sku'][$temp_sku_id]['shipping_fee'] = $goodsExpress->getGoodsExpressTemplate(['goods_id'=>], $address['district']);

                $return_data['shop'][$shop_id]['goods_id_array'][] = $sku_info['goods_id'];
                $return_data['shop'][$shop_id]['member_amount'] += $return_data['order'][$shop_id]['sku'][$k]['member_price'] * $sku_info['num'];
                $return_data['shop'][$shop_id]['shop_total_amount'] += $sku_info['price'] * $sku_info['num'];
                $return_data['shop'][$shop_id]['shop_discount_amount'] += $sku_info['discount_price'] * $sku_info['num'];
                if (getAddons('groupshopping', $this->website_id, $this->instance_id)) {
                    $is_group = $group_server->isGroupGoods($sku_info['goods_id']);//是否团购商品
                    $group_sku_info_obj = $group_server->getGroupSkuInfo(['sku_id' => $sku_info['sku_id'], 'goods_id' => $sku_info['goods_id'], 'group_id' => $is_group]);
                    $group_sku_info_arr = objToArr($group_sku_info_obj);//商品团购信息
                }
                if (!empty($sku_info['channel_id']) && getAddons('channel', $this->website_id)) {
                    $website_id = $this->website_id;
                    $channel_gs_mdl = new VslChannelGoodsSkuModel();
                    $channel_gs_info = $channel_gs_mdl->getInfo(['website_id' => $website_id, 'channel_id' => $sku_info['channel_id'], 'sku_id' => $sku_info['sku_id']], 'stock');
                    if ($sku_info['num'] > $channel_gs_info['stock']) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . '商品渠道商库存不足'];
                    }
                    $sku_db_info->stock = $channel_gs_info['stock'];
                    //用于计算渠道商金额的字段 (shop_channel_amount/shop_discount_amount)*shop_should_paid_amount
                    $return_data['shop'][$shop_id]['shop_channel_amount'] += $sku_info['discount_price'] * $sku_info['num'];
                    //                    $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $sku_info['discount_price'] * $sku_info['num'];
                    //                    var_dump($sku_info['discount_price'] , $sku_info['num']);echo '<br>';
                }
                if (!empty($sku_info['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                    //判断秒杀商品是否过期
                    $sku_id = $sku_info['sku_id'];
                    $seckill_id = $sku_info['seckill_id'];
                    $condition_is_seckill['s.seckill_id'] = $seckill_id;
                    $condition_is_seckill['nsg.sku_id'] = $sku_id;
                    $is_seckill = $sec_service->isSeckillGoods($condition_is_seckill);
                    if ($is_seckill) {
                        //获取秒杀商品的价格、库存、最大购买量
                        $condition_sku_info['seckill_id'] = $seckill_id;
                        $condition_sku_info['sku_id'] = $sku_id;
                        $sku_info_list = $sec_service->getSeckillSkuInfo($condition_sku_info);
                        $sku_info_arr = objToArr($sku_info_list);
                        //获取限购量
                        $goods_service = new Goods();
                        //通过用户累计购买量判断，先判断redis是否有内容
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $sku_info['seckill_id']);
                        if ($sku_info['num'] > $is_seckill['seckill_num']) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                        }
                        //判断是否超过限购
                        if ($sku_info_arr['seckill_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $sku_info_arr['seckill_limit_buy'])) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格购买数目已经超出最大秒杀限购数目'];
                        }
                        //价格
                        if ($is_seckill['seckill_price'] != $sku_info['price']) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 秒杀商品价格变动'];
                        }
                    } else {
                        return ['result' => -2, 'message' => $sku_db_info->goods->goods_name . '商品秒杀已结束，将恢复正常商品价格。'];
                    }
                    $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $sku_info['discount_price'] * $sku_info['num'];

                } elseif ($is_group && $order_data['group_id'] && getAddons('groupshopping', $this->website_id, $this->instance_id)) {
                    if ($sku_info['num'] > $sku_db_info->stock) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }
                    if ($order_data['record_id']) {
                        $checkGroup = $group_server->checkGroupIsCan($order_data['record_id'], $this->uid);//判断该团购是否能参加
                        if ($checkGroup < 0) {
                            return ['result' => false, 'message' => '该团无法参加，请选择其他团或者自己发起团购'];
                        }
                    }
                    $goods_service = new Goods();
                    //通过用户累计购买量判断
                    $uid = $this->uid;
                    $website_id = $this->website_id;
                    $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $sku_info['sku_id'], $website_id, $order_data['group_id']);
                    if ($group_sku_info_arr['group_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $group_sku_info_arr['group_limit_buy'])) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大拼团限购数目'];
                    }
                    if ($sku_info['price'] != $group_sku_info_arr['group_price']) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 团购商品价格变动'];
                    }

                    $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $sku_info['discount_price'] * $sku_info['num'];

                } elseif (!empty($sku_info['bargain_id']) && getAddons('bargain', $this->website_id, $this->instance_id)) {//砍价
                    $bargain = new Bargain();
                    $order_server = new \data\service\Order\Order();
                    $condition_bargain['bargain_id'] = $sku_info['bargain_id'];
                    $uid = $this->uid;
                    $condition_bargain['website_id'] = $this->website_id;
                    $is_bargain = $bargain->isBargain($condition_bargain, $uid);
                    if ($is_bargain) {
                        $return_data['bargain_id'] = $sku_info['bargain_id'];
                        //库存
                        $bargain_stock = $is_bargain['bargain_stock'];
                        $limit_buy = $is_bargain['limit_buy'];
                        $price = $is_bargain['my_bargain']['now_bargain_money'];
                        $buy_num = $order_server->getActivityOrderSkuNum($uid, $sku_info['sku_id'], $this->website_id, 3, $sku_info['bargain_id']);
                        if ($sku_info['num'] > $bargain_stock) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出砍价活动库存'];
                        }
                        if ($sku_info['num'] + $buy_num > $limit_buy) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大砍价限购数目'];
                        }
                        //价格
                        if ($sku_info['price'] != $price) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 砍价商品价格变动'];
                        }
                        $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $price * $sku_info['num'];
                    } else {
                        return ['result' => -2, 'message' => '砍价活动已过期或已关闭'];
                    }
                } elseif (!empty($sku_info['presell_id']) && getAddons('presell', $this->website_id, $this->instance_id)) {
                    $presell_service = new PresellService();
                    $count_people = $presell_service->getPresellBuyNum($sku_info['presell_id']);
                    $presell_info = $presell_service->getPresellBySku($sku_info['presell_id'], $sku_info['sku_id']);
                    if ($presell_info['presellnum'] < $count_people) {
                        return ['result' => false, 'message' => '预售商品库存不足'];
                    }
                    $user_buy = $presell_service->getUserCount($sku_info['presell_id']);//当前用户购买数
                    if ($user_buy > $presell_info['maxbuy']) {//当前用户购买数大于每人限购
                        return ['result' => false, 'message' => '您已达到商品预售购买上限'];
                    }
                    //付定金去掉运费
                    $shipping_fee_all_last = $shipping_fee_all[$shop_id];
                    $shipping_fee_all[$shop_id] = 0;

                    $return_data['shop'][$shop_id]['shop_should_paid_amount'] = $sku_info['num'] * $presell_info['firstmoney'];
                } else {
                    // 普通商品
                    //sku 信息检查
                    if ($sku_db_info->goods->state != 1) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 物品为不可购买状态'];
                    }
                    if ($sku_info['num'] > $sku_db_info->stock) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }
                    if (($sku_info['num'] > $sku_db_info->goods->max_buy) && $sku_db_info->goods->max_buy != 0) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出最大购买数目'];
                    }

                    if ($sku_info['price'] != $sku_db_info->price) {
                        return ['result' => false, 'message' => $sku_info->goods->goods_name . ' 商品价格变动'];
                    }
                    $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $sku_info['discount_price'] * $sku_info['num'];
                }

                // 限时折扣
                $discount_price = $sku_info['discount_price'];
                if (!empty($sku_info['discount_id'])) {
                    if (!getAddons('discount', $this->website_id)) {
                        return ['result' => false, 'message' => '限时折扣应用已关闭'];
                    }
                    $promotion_discount_info_db = $promotion_discount_model::get($sku_info['discount_id'], ['goods']);
                    if ($promotion_discount_info_db->status != 1) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . '限时折扣状态不可用'];
                    }
                    if ($promotion_discount_info_db->start_time > $now_time || $promotion_discount_info_db->end_time < $now_time) {
                        return ['result' => false, 'message' => '限时折扣不在可用时间内'];
                    }
                    if (($promotion_discount_info_db->range_type == 1 || $promotion_discount_info_db->range_type == 3) &&
                        ($promotion_discount_info_db->shop_id != $shop_id)) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 限时折扣不在可用范围内'];
                    }
                    if ($promotion_discount_info_db->range == 2) {
                        if ($promotion_discount_info_db->goods()->where(['goods_id' => ['=', $sku_db_info->goods_id]])->count() == 0) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 商品不在限时折扣指定商品范围内'];
                        }
                    }
                    $member_discount_price = $sku_info['discount_price'];
                    $discount_price_1 = round(($promotion_discount_info_db->discount_num / 10) * $member_discount_price, 2);
                    // 限时抢购商品表的折扣
                    $goods_discount = $promotion_discount_info_db->goods()->where(['goods_id' => $sku_info['goods_id']])->find();
                    if ($goods_discount) {
                        $promotion_discount = $promotion_discount_model->where(['discount_id' => $goods_discount['goods_id']])->find();
                        if ($promotion_discount['integer_type'] == 1) {
                            $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price);
                        } else {
                            $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price, 2);
                        }

                        if ($goods_discount['discount_type'] == 2) {
                            $discount_price_2 = $goods_discount['discount'];
                        }
                    }

                    if ($discount_price != $discount_price_1 && $discount_price != $discount_price_2) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 商品折扣价格变化'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['promotion_shop_id'] = $promotion_discount_info_db->shop_id;// 限时折扣店铺id，用于识别优惠来源
                }
                //计时/次商品
                $goodsSer = new GoodsService();
                $goods_info = $goodsSer->getGoodsDetailById($sku_info['goods_id']);
                $return_data['order'][$shop_id]['sku'][$k]['shipping_fee'] = $goods_info['shipping_fee_type'] == 1 ? ($goods_info['shipping_fee'] ?: 0) : 0;
                $return_data['order'][$shop_id]['sku'][$k]['shipping_fee_type'] = $goods_info['shipping_fee_type'];
                if (getAddons('store', $this->website_id, $this->instance_id) && $goods_info['goods_type'] == 0) {
                    $return_data['address_id'] = 0;
                    if ($return_data['shop'][$shop_id]['card_store_id'] == 0) {
                        return ['result' => false, 'message' => '请选择使用门店'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['card_store_id'] = $return_data['shop'][$shop_id]['card_store_id'];
                    $return_data['order'][$shop_id]['sku'][$k]['cancle_times'] = $goods_info['cancle_times'];
                    $return_data['order'][$shop_id]['sku'][$k]['cart_type'] = $goods_info['cart_type'];
                    if ($goods_info['valid_type'] == 1) {
                        $return_data['order'][$shop_id]['sku'][$k]['invalid_time'] = time() + $goods_info['valid_days'] * 24 * 60 * 60;
                    } else {
                        $return_data['order'][$shop_id]['sku'][$k]['invalid_time'] = $goods_info['invalid_time'];
                    }
                    if ($goods_info['is_wxcard'] == 1) {
                        $return_data['order'][$shop_id]['sku'][$k]['wx_card_id'] = $goods_info['wx_card_id'];
                        $ticket = new VslGoodsTicketModel();
                        $ticket_info = $ticket->getInfo(['goods_id' => $sku_info['goods_id']]);
                        $return_data['order'][$shop_id]['sku'][$k]['card_title'] = $ticket_info['card_title'];
                    }
                }
            }
            unset($sku_info);
            // 满减送
            if (!empty($shop['rule_id'])) {
                if (!getAddons('fullcut', $this->website_id)) {
                    return ['result' => false, 'message' => '满减送应用已关闭'];
                }
                $rule_db_info = $man_song_rule_model::get($shop['rule_id'], ['promotion_man_song.goods']);

                if ($rule_db_info->promotion_man_song->status != 1) {
                    return ['result' => false, 'message' => '满减送活动状态不可用'];
                }
                if ($rule_db_info->promotion_man_song->start_time > $now_time || $rule_db_info->promotion_man_song->end_time < $now_time) {
                    return ['result' => false, 'message' => '满减送活动不在可用时间内'];
                }
                if (($rule_db_info->promotion_man_song->range == 1 || $rule_db_info->promotion_man_song->range == 3) && $rule_db_info->promotion_man_song->shop_id != $shop_id) {
                    return ['result' => false, 'message' => '满减送活动不在可用店铺范围内'];
                }
                $man_song_compare_amount = 0.00;
                // 满减送活动指定商品范围
                $full_cut_goods_id_array = [];
                foreach ($rule_db_info->promotion_man_song->goods as $goods) {
                    $full_cut_goods_id_array[] = $goods->goods_id;
                }
                unset($goods);
                if ($rule_db_info->promotion_man_song->range_type == 0) {
                    // 部分商品
                    foreach ($shop['goods_list'] as $sku_id => $sku_info) {
                        if (in_array($sku_info['goods_id'], $full_cut_goods_id_array)) {
                            $man_song_compare_amount += $sku_info['discount_price'] * $sku_info['num'];
                        }
                    }
                    unset($sku_info);
                } else {
                    $man_song_compare_amount = $promotion_shop_amount[$shop_id];
                }
                if ($rule_db_info->price > $man_song_compare_amount) {
                    return ['result' => false, 'message' => '满减送活动达不到金额要求'];
                }
                $return_data['promotion'][$shop_id]['man_song'][$rule_db_info->mansong_id]['rule_id'] = $rule_db_info->rule_id;
                $return_data['promotion'][$shop_id]['man_song'][$rule_db_info->mansong_id]['price'] = $rule_db_info->price;
                $return_data['promotion'][$shop_id]['man_song'][$rule_db_info->mansong_id]['discount'] = $rule_db_info->discount;
                $return_data['promotion'][$shop_id]['man_song'][$rule_db_info->mansong_id]['shop_id'] = $rule_db_info->promotion_man_song->shop_id;
                $return_data['promotion'][$shop_id]['man_song'][$rule_db_info->mansong_id]['free_shipping_fee'] = $rule_db_info->free_shipping;
                $return_data['shop'][$shop_id]['shop_should_paid_amount'] -= $rule_db_info->discount;
                $return_data['shop'][$shop_id]['man_song_amount'] = $rule_db_info->discount;
                $return_data['shop'][$shop_id]['man_song_coupon_type_id'] = $rule_db_info->give_coupon;
                $return_data['shop'][$shop_id]['man_song_point'] = $rule_db_info->give_point;
                // 计算每个sku的满减送优惠占比,邮费占比
                $all_full_count = 0;
                $all_full_count_sku = [];
                foreach ($return_data['order'][$shop_id]['sku'] as $key => $goods_1) {
                    if (empty($goods_1['seckill_id']) && empty($order_data['record_id'])) {
                        if (in_array($goods_1['goods_id'], $full_cut_goods_id_array) || $rule_db_info->promotion_man_song->range_type == 1) {
                            $all_full_count_sku[] = $goods_1['sku_id'];
                            $all_full_count += $goods_1['discount_price'] * $goods_1['num'];
                        }
                    }
                }
                unset($goods_1);
                $j = 0;
                $partPercent = 0;
                $tempgoods2 = [];
                $temp_nmu = $shop_goods_num[$shop_id];// 订单商品件数
                $temp_shipping_fee = 0;
                $unMansong = [];
                $allFee = $shipping_fee_all[$shop_id]; //总运费
                foreach ($return_data['order'][$shop_id]['sku'] as $key => $goods_1) {
                    if (empty($goods_1['seckill_id']) && empty($order_data['record_id'])) {
                        $fullCount = count($all_full_count_sku);
                        if (in_array($goods_1['goods_id'], $full_cut_goods_id_array) || $rule_db_info->promotion_man_song->range_type == 1) {// 全部商品
                            $j++;
                            $return_data['order'][$shop_id]['sku'][$key]['promotion_id'] = $rule_db_info->promotion_man_song->mansong_id;
                            $percent = round(($goods_1['discount_price'] * $goods_1['num'] / $all_full_count), 2);

                            if ($j != $fullCount) {
                                $partPercent += $percent;
                                $return_data['order'][$shop_id]['sku'][$key]['full_cut_sku_percent'] = $percent;
                            } else {
                                $return_data['order'][$shop_id]['sku'][$key]['full_cut_sku_percent'] = 1 - $partPercent;
                            }

                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_sku_amount'] = $rule_db_info->discount;
                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_shop_id'] = $rule_db_info->promotion_man_song->shop_id;
                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_range'] = $rule_db_info->promotion_man_song->range;
                            //  todo... 包邮
                            if ($rule_db_info->free_shipping == 1) {
                                $return_data['order'][$shop_id]['sku'][$key]['is_free_shipping'] = 1;// sku 满减送包邮信息
                                $return_data['order'][$shop_id]['sku'][$key]['free_shipping_shop_id'] = $rule_db_info->promotion_man_song->shop_id;
                                $return_data['order'][$shop_id]['sku'][$key]['shipping_fee'] = 0;
                                $temp_shipping_fee += $goods_1['shipping_fee'];
                                $temp_nmu -= $goods_1['num'];
                                $shipping_fee_all[$shop_id] -= $goods_1['shipping_fee'];
                            } else {
                                $return_data['order'][$shop_id]['sku'][$key]['is_free_shipping'] = 0;
                                $return_data['order'][$shop_id]['sku'][$key]['free_shipping_shop_id'] = 0;
                                if ($tempgoods2[$goods_1['goods_id']]) {
                                    $tempgoods2[$goods_1['goods_id']]['count'] += $goods_1['num'];
                                } else {
                                    $tempgoods2[$goods_1['goods_id']]['goods_id'] = $goods_1['goods_id'];
                                    $tempgoods2[$goods_1['goods_id']]['count'] = $goods_1['num'];
                                }
                                // todo... 运费均分（剔除满减包邮的情况）
                                if (($shop_goods_num[$shop_id] > $unShippingTypeCount[$shop_id]) && ($shipping_fee_all[$shop_id] > $unShippingTypeMoney[$shop_id]) && $goods_1['shipping_fee_type'] == 2) {
                                    $sku_shipping_fee = round(($goods_1['num'] / ($shop_goods_num[$shop_id] - $unShippingTypeCount[$shop_id])) * ($shipping_fee_all[$shop_id] - $unShippingTypeMoney[$shop_id]), 2);
                                    $return_data['order'][$shop_id]['sku'][$key]['shipping_fee'] = $sku_shipping_fee;
                                }

                            }
                        } else {
                            $return_data['order'][$shop_id]['sku'][$key]['promotion_id'] = 0;
                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_sku_percent'] = 0;
                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_sku_amount'] = 0;
                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_shop_id'] = 0;
                            $return_data['order'][$shop_id]['sku'][$key]['full_cut_range'] = 0;
                            $return_data['order'][$shop_id]['sku'][$key]['is_free_shipping'] = 0;
                            $return_data['order'][$shop_id]['sku'][$key]['free_shipping_shop_id'] = 0;
                            if ($tempgoods2[$goods_1['goods_id']]) {
                                $tempgoods2[$goods_1['goods_id']]['count'] += $goods_1['num'];
                            } else {
                                $tempgoods2[$goods_1['goods_id']]['goods_id'] = $goods_1['goods_id'];
                                $tempgoods2[$goods_1['goods_id']]['count'] = $goods_1['num'];
                            }
                            if ($goods_1['shipping_fee_type'] == 2) {
                                $unMansong[$key] = $goods_1;
                            } else {
                                $shipping_fee_all[$shop_id] -= $return_data['order'][$shop_id]['sku'][$key]['shipping_fee'];
                            }
                        }
                    }
                }
                unset($goods_1);
                $countUnMansong = count($unMansong);
                $i = 1;
                $nowShipping = 0;
                foreach ($unMansong as $unkey => $unval) {
                    if ($i < $countUnMansong) {
                        $return_data['order'][$shop_id]['sku'][$unkey]['shipping_fee'] = round(($unval['num'] / count($unMansong)) * ($allFee - $unShippingTypeMoney[$shop_id]), 2);
                        $nowShipping += round(($unval['num'] / count($unMansong)) * ($allFee - $unShippingTypeMoney[$shop_id]), 2);
                    } else {

                        $return_data['order'][$shop_id]['sku'][$unkey]['shipping_fee'] = round(($allFee - $unShippingTypeMoney[$shop_id]) - $nowShipping);
                    }
                    $i++;
                }
                unset($unval);
            } elseif ($return_data['shipping_type'] != 2) {
                // 没有包邮，快递配送,计算每个sku邮费占比,
                foreach ($return_data['order'][$shop_id]['sku'] as $key => $goods_1) {
                    $t_shipping_fee = 0;
                    if ($goods_1['shipping_fee_type'] == 2) {
                        if ($goods_1['presell_id']) {
                            $t_shipping_fee = 1; //作用于区分预售运费
                            $sku_shipping_fee = round(($goods_1['num'] / ($shop_goods_num[$shop_id] - $unShippingTypeCount[$shop_id])) * ($shipping_fee_all_last - $unShippingTypeMoney[$shop_id]), 2);
                        } else {
                            $sku_shipping_fee = round(($goods_1['num'] / ($shop_goods_num[$shop_id] - $unShippingTypeCount[$shop_id])) * ($shipping_fee_all[$shop_id] - $unShippingTypeMoney[$shop_id]), 2);
                        }

                        $return_data['order'][$shop_id]['sku'][$key]['shipping_fee'] = $sku_shipping_fee;
                    }

                }
                unset($goods_1);
                if ($t_shipping_fee == 1) {
                    $return_data['shop'][$shop_id]['shipping_fee'] = $shipping_fee_all_last;
                } else {
                    $return_data['shop'][$shop_id]['shipping_fee'] = $shipping_fee_all[$shop_id];
                }
                $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $shipping_fee_all[$shop_id];
            } else {
                //门店自提
                //没有满减送
                //门店自提，若门店没有开启自提点，需物流运费,并且没有满减送
                if ($shop['has_store'] > 0) {
                    $return_data['shop'][$shop_id]['shipping_fee'] = 0;
                } else {
                    foreach ($return_data['order'][$shop_id]['sku'] as $key => $goods_1) {
                        if ($goods_1['shipping_fee_type'] == 2) { //运费模板
                            //获取当前商品运费模板
                            $tempgoods_2 = [];
                            $s_goods_id = $goods_1['goods_id'];
                            $tempgoods_2[$s_goods_id]['count'] = $goods_1['num'];
                            $tempgoods_2[$s_goods_id]['goods_id'] = $goods_1['goods_id'];
                            $return_data['shop'][$shop_id]['shipping_fee'] += $goodsExpress->getGoodsExpressTemplate($tempgoods2, $district_id)['totalFee'];

                        } else if ($goods_1['shipping_fee_type'] == 1) {//固定运费
                            $return_data['shop'][$shop_id]['shipping_fee'] += $goods_1['shipping_fee'];
                        } else {//包邮
                            $return_data['shop'][$shop_id]['shipping_fee'] += 0;
                        }
                    }
                    $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $return_data['shop'][$shop_id]['shipping_fee'];
                }
            }


            if ($tempgoods2 && !empty($district_id) && ($return_data['shipping_type'] != 2 || ($return_data['shipping_type'] == 2 && $shop['has_store'] == 0))) {//非门店自提 或者门店自提，但是没有开启可自提门店
                $return_data['shop'][$shop_id]['shipping_fee'] = $goodsExpress->getGoodsExpressTemplate($tempgoods2, $district_id)['totalFee'];
                $tempgoods2 = [];
                $return_data['shop'][$shop_id]['shop_should_paid_amount'] += $return_data['shop'][$shop_id]['shipping_fee'];
                $return_data['shop'][$shop_id]['promotion_free_shipping'] += round($allFee - $return_data['shop'][$shop_id]['shipping_fee'], 2);
            }

            if (!empty($shop['coupon_id'])) {
                if (!getAddons('coupontype', $this->website_id)) {
                    return ['result' => false, 'message' => '优惠券应用已关闭'];
                }
                $coupon_db_info = $coupon_model::get($shop['coupon_id'], ['coupon_type.goods']);
                if ($coupon_db_info->state != 1) {
                    return ['result' => false, 'message' => '优惠券状态为不可用'];
                }
                if ($coupon_db_info->coupon_type->start_time > $now_time || $coupon_db_info->coupon_type->end_time < $now_time) {
                    return ['result' => false, 'message' => '优惠券不在可用时间内'];
                }
                if ($coupon_db_info->coupon_type->shop_range_type == 1 && $coupon_db_info->coupon_type->shop_id != $shop_id) {
                    return ['result' => false, 'message' => '优惠券不在可用店铺范围内'];
                }
                $couponGoods = [];
                if ($coupon_db_info->coupon_type->range_type == 0) {
                    $couponGoodsModel = new \addons\coupontype\model\VslCouponGoodsModel();
                    $couponGoods = $couponGoodsModel->Query(['coupon_type_id' => $coupon_db_info->coupon_type_id, 'website_id' => $this->website_id], 'goods_id');
                    // 部分商品可用
                    foreach ($shop['goods_list'] as $k => $sku_info) {
                        if ($coupon_db_info->coupon_type->goods()->where(['goods_id' => $sku_info['goods_id']])->count() == 0 && empty($sku_info['seckill_id']) && empty($sku_info['record_id'])) {
                            // 有商品不在活动方位内时
                            return ['result' => false, 'message' => '优惠券活动指定可用商品变化'];
                        }
                    }
                    unset($sku_info);
                }
                $amount_for_coupon_discount = 0;// 计算优惠券的优惠金额，比较是否达到门槛要求
                foreach ($return_data['order'][$shop_id]['sku'] as $k => $sku_info) {
                    if ($couponGoods) {
                        if (in_array($sku_info['goods_id'], $couponGoods) && empty($sku_info['seckill_id']) && empty($sku_info['record_id'])) {
                            $amount_for_coupon_discount += $sku_info['discount_price'] * $sku_info['num'];
                        }
                    } else {
                        if (empty($sku_info['seckill_id']) && empty($sku_info['record_id'])) {
                            $amount_for_coupon_discount += $sku_info['discount_price'] * $sku_info['num'];
                        }
                    }
                    if ($sku_info['full_cut_sku_amount'] > 0 && $sku_info['full_cut_sku_percent'] > 0) {
                        $amount_for_coupon_discount -= $sku_info['full_cut_sku_amount'] * $sku_info['full_cut_sku_percent'];
                    }
                }
                unset($sku_info);
                if (($coupon_db_info->coupon_type->at_least > $amount_for_coupon_discount) && ($coupon_db_info->coupon_type->coupon_genre == 2 || $coupon_db_info->coupon_type->coupon_genre == 3)) {
                    // 门槛要求类型
                    return ['result' => false, 'message' => '优惠券达不到门槛要求'];
                }
                if ($coupon_db_info->coupon_type->coupon_genre == 3) {
                    // 折扣类型
                    $coupon_amount = round($amount_for_coupon_discount * (1 - $coupon_db_info->coupon_type->discount / 10), 2);
                } else {
                    // 满减无门槛类型
                    $coupon_amount = $coupon_db_info->coupon_type->money;
                }
                $return_data['shop'][$shop_id]['shop_should_paid_amount'] -= $coupon_amount;
                $return_data['promotion'][$shop_id]['coupon']['coupon_reduction_amount'] = $coupon_amount;
                $return_data['promotion'][$shop_id]['coupon']['shop_id'] = $coupon_db_info->coupon_type->shop_id;
                $return_data['promotion'][$shop_id]['coupon']['coupon_id'] = $coupon_db_info->coupon_id;
                $return_data['promotion'][$shop_id]['coupon']['coupon_genre'] = $coupon_db_info->coupon_type->coupon_genre;
                $return_data['promotion'][$shop_id]['coupon']['money'] = $coupon_db_info->coupon_type->money;
                $return_data['promotion'][$shop_id]['coupon']['discount'] = $coupon_db_info->coupon_type->discount;
                $return_data['promotion'][$shop_id]['coupon']['at_least'] = $coupon_db_info->coupon_type->at_least;
                // 计算每个sku的优惠券优惠占比
                $i = 0;//优惠券金额叠加,最后一个不计算,直接用剩下的优惠
                $skuCount = count($return_data['order'][$shop_id]['sku']);
                $partCoupon = 0;
                foreach ($return_data['order'][$shop_id]['sku'] as $ke => $sku_info) {
                    $i++;
                    if ($couponGoods) {
                        if (empty($sku_info['seckill_id']) && empty($order_data['record_id']) && in_array($sku_info['goods_id'], $couponGoods)) {
                            $return_data['order'][$shop_id]['sku'][$ke]['coupon_id'] = $coupon_db_info->coupon_id;
                            $percent = round(($sku_info['discount_price'] * $sku_info['num'] - $sku_info['full_cut_sku_amount'] * $sku_info['full_cut_sku_percent']) / $amount_for_coupon_discount, 2);
                            $return_data['order'][$shop_id]['sku'][$ke]['coupon_sku_percent'] = $percent;
                            $return_data['order'][$shop_id]['sku'][$ke]['coupon_sku_percent_amount'] = round($percent * $return_data['promotion'][$shop_id]['coupon']['coupon_reduction_amount'], 2);
                            if ($i != $skuCount) {
                                $partCoupon += round($percent * $return_data['promotion'][$shop_id]['coupon']['coupon_reduction_amount'], 2);
                            } else {
                                $return_data['order'][$shop_id]['sku'][$ke]['coupon_sku_percent_amount'] = round($coupon_amount - $partCoupon, 2);
                            }
                        }
                    } else {
                        if (empty($sku_info['seckill_id']) && empty($order_data['record_id'])) {
                            $return_data['order'][$shop_id]['sku'][$ke]['coupon_id'] = $coupon_db_info->coupon_id;
                            $percent = round(($sku_info['discount_price'] * $sku_info['num'] - $sku_info['full_cut_sku_amount'] * $sku_info['full_cut_sku_percent']) / $amount_for_coupon_discount, 2);
                            $return_data['order'][$shop_id]['sku'][$ke]['coupon_sku_percent'] = $percent;
                            $return_data['order'][$shop_id]['sku'][$ke]['coupon_sku_percent_amount'] = round($percent * $return_data['promotion'][$shop_id]['coupon']['coupon_reduction_amount'], 2);
                            if ($i != $skuCount) {
                                $partCoupon += round($percent * $return_data['promotion'][$shop_id]['coupon']['coupon_reduction_amount'], 2);
                            } else {
                                $return_data['order'][$shop_id]['sku'][$ke]['coupon_sku_percent_amount'] = round($coupon_amount - $partCoupon, 2);
                            }
                        }
                    }

                }
                unset($sku_info);
            }
            if ($return_data['shop'][$shop_id]['shop_should_paid_amount'] < 0) {
                $return_data['shop'][$shop_id]['shop_should_paid_amount'] = 0;
            }
            if ($shop['shop_amount'] < 0) {
                $shop['shop_amount'] = 0;
            }
            // 店铺总额不相等
            if ($return_data['order_type'] != 2 && $return_data['order_type'] != 3 && $return_data['order_type'] != 4) {//不享受任何优惠不用验证
                if (round($return_data['shop'][$shop_id]['shop_should_paid_amount'], 2) != round($shop['shop_amount'], 2)) {
                    return ['result' => false, 'message' => '店铺应付金额不匹配'];
                }
            }
        }
        unset($shop);
        //end 计算,校验
        return ['result' => true, 'data' => $return_data];
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getOrderTradeNo()
     */
    public function getOrderTradeNo()
    {
        $order = new OrderBusiness();
        $no = $order->createOutTradeNo();
        return $no;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderDelivery()
     */
    public function orderDelivery($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no, $delivery_num = 0)
    {
        $order_express = new OrderExpress();
        $retval = $order_express->delivery($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no, $delivery_num);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id_array' => $order_goods_id_array,
                'express_name' => $express_name,
                'shipping_type' => $shipping_type,
                'express_company_id' => $express_company_id,
                'express_no' => $express_no
            ];
            //邮件通知
            runhook('Notify', 'emailSend', ['website_id' => $this->website_id, 'shop_id' => 0, 'order_id' => $order_id, 'notify_type' => 'user', 'template_code' => 'order_deliver']);
        }
        return $retval;
    }

    /**
     * 发货助手批量发货
     * @param array $order_list
     */
    public function ordersDelivery(array $order_list)
    {
        try {
            foreach ($order_list as $v) {
                if (!empty($v['order_goods_id_array'])) {
                    $this->orderDelivery($v['order_id'], implode(',', $v['order_goods_id_array']), $v['express_name'], 1, $v['express_company_id'], $v['express_no']);
                }
            }
            unset($v);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return UPDATA_FAIL;
        }
    }

    /**
     * 更新物流信息
     * @param int $id
     * @param array $data
     * @return int
     */
    public function updateDelivery($id, array $data)
    {
        $order_express = new OrderExpress();
        return $order_express->updateDelivery($id, $data);
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsDelivery()
     */
    public function orderGoodsDelivery($order_id, $order_goods_id_array, $delivery_num = 0, $website_id = 0)
    {
        $this->website_id = $this->website_id ? $this->website_id : $website_id;
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsDelivery($order_id, $order_goods_id_array, $delivery_num);
        if ($retval) {
            $params = [
                'order_id' => $order_id,
                'order_goods_id_array' => $order_goods_id_array
            ];
            hook('orderDeliverySuccess', $params);
            //邮件通知
            runhook('Notify', 'emailSend', ['website_id' => $this->website_id, 'shop_id' => 0, 'order_id' => $order_id, 'notify_type' => 'user', 'template_code' => 'order_deliver']);
        }
        return $retval;
    }

    /*
     * 订单超时关闭(普通、秒杀、拼团等)
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderClose()
     */
    public function orderClose($order_id, $task_mark = 0, $website_id = 0, $instance_id = 0)
    {
        $this->website_id = $this->website_id ? $this->website_id : $website_id;
        $this->instance_id = $this->instance_id ? $this->instance_id : $instance_id;
        $order = new OrderBusiness();
        $retval = $order->orderClose($order_id, $task_mark);
        if ($retval) {
            $params = array(
                "order_id" => $order_id,
                "website_id" => $this->website_id,
                "shop_id" => $this->instance_id,
            );
            runhook("Notify", "orderCancelByTemplate", $params);
            runhook("MpMessage", "orderCloseMpByTemplate", $params);
            runhook("Notify", "orderCancelBySms", $params);
            runhook('Notify', 'emailSend', ['order_id' => $order_id, 'notify_type' => 'user', 'template_code' => 'cancel_order']);
        }
        return $retval;
    }

    public function channelOrderClose($order_id)
    {
        $order = new OrderBusiness();
        $retval = $order->channelOrderClose($order_id);
        if ($retval) {
            $params = array(
                "order_id" => $order_id,
                "channel_status" => 1,
                "website_id" => $this->website_id,
                "shop_id" => $this->instance_id,
            );
            runhook("Notify", "orderCancelByTemplate", $params);
            runhook("MpMessage", "orderCloseMpByTemplate", $params);
            runhook("Notify", "orderCancelBySms", $params);
            runhook('Notify', 'emailSend', ['order_id' => $order_id, 'notify_type' => 'user', 'template_code' => 'cancel_channel_order', 'is_channel' => 1]);
        }
        return $retval;
    }

    //代理订单关闭
    public function agentOrderClose($order_id)
    {
        $order = new OrderBusiness();
        $retval = $order->agentOrderClose($order_id);
        if ($retval) {
            $params = array(
                "order_id" => $order_id,
                "channel_status" => 1,
                "website_id" => $this->website_id,
            );
            runhook("Notify", "orderAgentByTemplate", $params);
            runhook("Notify", "orderAgentMpByTemplate", $params);
            runhook("Notify", "orderAgentBySms", $params);
            runhook('Notify', 'emailSend', ['order_id' => $order_id, 'notify_type' => 'user', 'template_code' => 'cancel_agent_order', 'is_agent' => 1]);
        }
        return $retval;
    }

    /**
     * 订单完成后自动评论
     */
    public function ordersComment($order_info, $website_id = 0, $text = '')
    {
        if ($website_id) {
            $websiteid = $website_id;
        } else {
            $websiteid = $this->website_id;
        }
        if (empty($order_info)) {
            return;
        }
        $buyer_id = $order_info['buyer_id'];
        $userModel = new UserModel();
        if (!$order_info['user_name']) {
            $user_info = $userModel->getInfo(['uid' => $buyer_id], 'user_name,nick_name');
            $order_info['user_name'] = $user_info['user_name'] ?: $user_info['nick_name'];
        }
        //获取订单是否已经产生评论 //没有则订单本人自动好评
        $goods_evalute = new VslGoodsEvaluateModel();
        $condition['order_id'] = $order_info['order_id'];
        $condition['uid'] = $buyer_id;
        $condition['website_id'] = $websiteid;
        $comment_count = $goods_evalute->where($condition)->count();
        if ($comment_count > 0) {
            return;
        }
        //获取该订单是否存在店铺 存在则写入店铺评价
        if (getAddons('shop', $websiteid)) {
            $data_shop = array(
                'order_id' => $order_info['order_id'],
                'order_no' => $order_info['order_no'],
                'website_id' => $websiteid,
                'shop_id' => $order_info['shop_id'],
                'shop_desc' => 5,
                'shop_service' => 5,
                'shop_stic' => 5,
                'add_time' => time(),
                'member_name' => $order_info['user_name'],
                'uid' => $buyer_id,
            );
            $this->addShopEvaluate($data_shop);
        }
        //存在门店，则写入门店评价
        if (getAddons('store', $websiteid, $order_info['shop_id']) && $order_info['store_id']) {
            $storeServer = new Store();
            $data_store = array(
                'order_id' => $order_info['order_id'],
                'order_no' => $order_info['order_no'],
                'website_id' => $websiteid,
                'shop_id' => $order_info['shop_id'],
                'store_id' => $order_info['store_id'],
                'store_service' => 5,
                'add_time' => time(),
                'member_name' => $order_info['user_name'],
                'uid' => $buyer_id,
            );
            $storeServer->addStoreEvaluate($data_store);
        }
        //活动订单商品信息 写入商品评论
        // 查询订单项表
        $order_item = new VslOrderGoodsModel();
        $order_item_list = $order_item->where([
            'order_id' => $order_info['order_id']
        ])->select();
        foreach ($order_item_list as $key_item => $v_item) {
            $orderGoods = $this->getOrderGoodsInfo($v_item['order_goods_id']);
            $data = array(
                'order_id' => $order_info['order_id'],
                'order_no' => $order_info['order_no'],
                'order_goods_id' => $v_item['order_goods_id'],
                'website_id' => $orderGoods['website_id'],
                'goods_id' => $orderGoods['goods_id'],
                'goods_name' => $orderGoods['goods_name'],
                'goods_price' => $orderGoods['goods_money'],
                'goods_image' => $orderGoods['goods_picture'],
                'shop_id' => $orderGoods['shop_id'],
                'content' => $text, //内容
                'addtime' => time(),
                'image' => '',
                'member_name' => $order_info['user_name'],
                'explain_type' => 5,
                'uid' => $buyer_id,
            );
            $dataArr[] = $data;
        }
        unset($v_item);
        $result = $this->addGoodsEvaluate($dataArr, $order_info['order_id']);
        //不作成功与失败处理，执行完直接返回
        return;

    }

    /*
     * 订单完成的函数
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderComplete()
     */
    public function orderComplete($orderid, $website_id = 0, $types = 0)
    {
        try {
            $order = new OrderBusiness();
            $retval = $order->orderComplete($orderid);

            if ($website_id) {
                $websiteid = $website_id;
            } else {
                $websiteid = $this->website_id;
            }
            $distribution_status = getAddons('distribution', $websiteid);
            $global_status = getAddons('globalbonus', $websiteid);
            $area_status = getAddons('areabonus', $websiteid);
            $team_status = getAddons('teambonus', $websiteid);
            $channel_status = getAddons('channel', $websiteid);
            $live_status = getAddons('liveshopping', $websiteid);
            $microshop_status = getAddons('microshop', $websiteid);
            $shop_status = getAddons('shop', $websiteid);
            $paygift_status = getAddons('paygift', $websiteid);
            $merchants_status = getAddons('merchants', $websiteid);

            if ($retval == 1) {
                // 发放赠送的优惠卷
                $this->sendCoupon($orderid);
                //暂时防止立即完成的订单与支付完成的定时任务重复执行
                // if($types == 1){
                //     sleep(1);
                // }


                if ($microshop_status == 1) {
                    //执行钩子：微店结算
                    hook('updateOrderMicroShop', ['order_id' => $orderid, 'website_id' => $websiteid]);
                }
                if ($distribution_status == 1) {
                    //执行钩子：分销佣金结算
                    hook('updateOrderCommission', ['order_id' => $orderid, 'website_id' => $websiteid]);
                }
                if ($global_status == 1) {
                    //执行钩子：全球分红结算
                    hook('updateOrderGlobalBonus', ['order_id' => $orderid, 'website_id' => $websiteid]);
                }
                if ($area_status == 1) {
                    //执行钩子：区域分红结算
                    hook('updateOrderAreaBonus', ['order_id' => $orderid, 'website_id' => $websiteid]);
                }
                if ($team_status == 1) {
                    //执行钩子：团队分红结算
                    hook('updateOrderTeamBonus', ['order_id' => $orderid, 'website_id' => $websiteid]);
                }
                if ($channel_status == 1) {
                    //渠道商佣金解冻
                    $channel = new Channel();
                    $channel->updateMemberAccountBalance($orderid, $websiteid);
                }
                //主播分成解冻
                if ($live_status == 1) {
                    $live_shopping = new Liveshopping();
                    $live_shopping->actUnfreezeEarnings($orderid);
                }

                // 处理店铺的账户资金
                if ($shop_status == 1) {
                    $this->dealShopAccount_OrderComplete("", $orderid);
                }
                //支付有礼自动领奖
                if ($paygift_status == 1) {
                    $paygift_server = new PayGift();
                    $paygift_server->grantPrize($orderid);
                }
                // 处理平台的账户资金
                $this->updateAccountOrderComplete($orderid);
                $user_service = new User();
                // 更新会员的等级
                $order_model = new VslOrderModel();
                $order_detail = $order_model->getInfo([
                    "order_id" => $orderid
                ], "buyer_id,order_money,shop_id");
                // 更新会员的成长值
                $user_service->updateUserGrowthNum(3, $order_detail["buyer_id"], 0, $orderid);
                $user_service->updateUserLevel($order_detail["buyer_id"]);
                $user_service->updateUserLabel($order_detail["buyer_id"], $websiteid);
                //招商员业绩解冻
                if ($merchants_status == 1 && $order_detail['shop_id'] > 0) {
                    hook('unfreezingMerchantsBonus', ['order_id' => $orderid, 'website_id' => $websiteid]);
                    hook('updateMerchantsLevel', ['order_id' => $orderid, 'website_id' => $websiteid, 'uid' => 0]);
                }
            }
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write($e->getMessage());
            throw new \Exception($e->getMessage());
        }
        return $retval;
        // TODO Auto-generated method stub
    }

    /*
     * 订单在线支付
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderOnLinePay()
     * is_yue 小程序余额变动模板通知
     */
    public function orderOnLinePay($order_pay_no, $pay_type, $order_id = 0, $is_yue = 0, $joinpay = 0)
    {
        $order = new OrderBusiness();
        $order_model = new VslOrderModel();
        if (!strstr($order_pay_no, 'DH') && !strstr($order_pay_no, 'MD')) {
            $retval = $order->OrderPay($order_pay_no, $pay_type, 0, $joinpay = 0);
        } else {
            if (strstr($order_pay_no, 'DH')) {
                $integral_order_info = $order_model->getInfo(['out_trade_no' => $order_pay_no], 'order_id, buyer_id');
                $order_service = new \data\service\Order\Order();
                $order_service->addOrderAction($integral_order_info['order_id'], $integral_order_info['buyer_id'], '订单支付');
            }
            $retval = 1;//如果是兑换就不用更改订单状态了
        }

        try {
            if ($retval > 0) {
                if ($order_id) {
                    $order_info = $order_model->getInfo(['order_id' => $order_id]);
                    if ($order_info['goods_type'] == 6) {
                        $orderSchedule = new VslOrderSchedule();
                        $orderSchedule->save(['status' => 1], ['order_id' => $order_id]);
                    }
                    if($order_info['order_type'] == 15){
                        //幸运拼类型
                        $luckySpellServer = new luckySpellServer();
                        $luckySpellServer->addOrderRecords($order_info);
                    }
                    $buyer_id = $order_info['buyer_id'];
                    if (getAddons('microshop', $order_info['website_id'], $order_info['shop_id'])) {
                        if ($order_info['order_type'] == 2 || $order_info['order_type'] == 3 || $order_info['order_type'] == 4) {
                            $config = new MicroShopService();
                            $config->becomeShopKeeper($buyer_id, $order_id);
                        }
                    }
                    $user = new User();
                    $shop_account = new ShopAccount();
                    // 平台订单积分抵扣金额
                    if ($order_info["deduction_money"] > 0) {
                        if ($order_info['order_type'] == 7) {//判断预售订单
                            $account_model = new VslAccountRecordsModel();
                            $orderInfo = $account_model->getInfo(['type_alis_id' => $order_id, 'remark' => '预售订单支付完成,积分抵扣金额']);
                            if (!$orderInfo) {
                                // 处理平台的账户的积分抵扣金额
                                if ($order_info['deduction_money']) {
                                    $shop_account->updateAccountOrderPoint($order_info['deduction_money'], $order_info['website_id']);
                                }
                                $shop_account->addAccountRecords($order_info['shop_id'], $buyer_id, '订单支付完成', $order_info["deduction_money"], 33, $order_id, "预售订单支付完成,积分抵扣金额", $order_info["website_id"]);
                            }
                        } else {
                            // 处理平台的账户的积分抵扣金额
                            if ($order_info['deduction_money']) {
                                $shop_account->updateAccountOrderPoint($order_info['deduction_money'], $order_info['website_id']);
                            }
                            $shop_account->addAccountRecords($order_info['shop_id'], $buyer_id, '订单支付完成', $order_info["deduction_money"], 33, $order_id, "订单支付完成,积分抵扣金额", $order_info["website_id"]);
                        }
                    }
                    // 平台订单优惠金额
                    $platform_promotion_money = $order_info["platform_promotion_money"];
                    if ($platform_promotion_money > 0) {
                        $shop_account->addAccountRecords($order_info['shop_id'], $buyer_id, '订单支付完成', $platform_promotion_money, 23, $order_id, "订单支付完成，平台优惠金额", $order_info["website_id"]);
                    }
                    //会员成长值
                    $user->updateUserGrowthNum(1, $buyer_id, 0, $order_id);
                    //会员自动打标签
                    $user->updateUserLabel($buyer_id, $order_info['website_id']);
                    //处理店铺资金账户
                    if (getAddons('shop', $order_info['website_id']) == 1) {
                        $this->dealShopAccount_OrderPay('', $order_id);
                    }
                    // 处理平台的资金账户
                    $this->dealPlatformAccountOrderPay('', $order_id);
                    runhook("Notify", "orderPayBySms", array(
                        "order_id" => $order_id
                    ));
                    runhook("Notify", "orderPayByTemplate", array(
                        "order_id" => $order_id
                    ));
                    runhook("MpMessage", "orderPayMpByTemplate", array(
                        "order_id" => $order_id,
                        "website_id" => $order_info['website_id'],
                        "shop_id" => $order_info['shop_id'],
                    ));
                    if ($is_yue == 1) {
                        runhook("MpMessage", "balanceChangeByMpTemplate", array(
                            "order_id" => $order_id,
                            "website_id" => $order_info['website_id'],
                            "shop_id" => $order_info['shop_id'],
                            "type" => 1
                        ));
                    }
                    runhook('Notify', 'orderRemindBusinessBySms', [
                        "order_id" => $order_id,
                        "shop_id" => 0,
                        "website_id" => $order_info['website_id']
                    ]); // 订单提醒
                    // 邮件通知 - 用户
                    runhook('Notify', 'emailSend', [
                        'website_id' => $order_info['website_id'],
                        'shop_id' => 0,
                        'order_id' => $order_id,
                        'notify_type' => 'user',
                        'template_code' => 'pay_success'
                    ]);
                    // 邮件通知 - 卖家
                    runhook('Notify', 'emailSend', [
                        'website_id' => $order_info['website_id'],
                        'shop_id' => 0,
                        'order_id' => $order_id,
                        'notify_type' => 'business',
                        'template_code' => 'order_remind'
                    ]);
                    // 订单信息写入弹幕数据表
                    runhook('OrderBarrage', 'postOrderBarrageTrueQueue', [
                        'website_id' => $order_info['website_id'],
                        'shop_id' => 0,
                        'order_id' => $order_id,
                        'uid' => $order_info['buyer_id'],
                    ]);
                    $order_info = $order_model->getInfo(['order_id' => $order_id], "order_id,group_id,group_record_id,verification_code,shop_id,pay_status");
                    if ($order_info['group_id']) {

                        $group_server = new GroupShopping();
                        $group_server->createGroupRecord($order_id);
                    }
                    if ($order_info['verification_code']) {
                        $url = __URLS('/clerk/pages/verify/order?code=' . $order_info['verification_code'] . '&website_id=' . $order_info['website_id']);
                        //根据核销码生成核销二维码
                        $verification_qrcode = getQRcode($url, 'upload/' . $order_info['website_id'] . '/verification_code', 'verification_code_' . $order_info['verification_code']);
                        if ($order_info['shop_id']) {
                            $verification_qrcode = getQRcode($url, 'upload/' . $order_info['website_id'] . '/' . $order_info['shop_id'] . '/verification_code', 'verification_code_' . $order_info['verification_code']);
                        }

                        $store_server = new Store();
                        $store_server->orderVerCodeSet($verification_qrcode, $order_id);
                    }
                    if (getAddons('paygift', $order_info['website_id'], $order_info['shop_id'])) {
                        $paygift_server = new PayGift();
                        $paygift_server->createPaygiftRecord($order_id);
                    }
                    if ($order_info['pay_status'] == 2) {
                        // 判断是否需要在本阶段赠送积分
                        $order = new OrderBusiness();
                        $order->giveGoodsOrderPoint($order_id, 3);
                    }
                } else {

                    $condition = "out_trade_no='" . $order_pay_no . "' OR out_trade_no_presell = '" . $order_pay_no . "'";
                    $order_list = $order_model->getQuery($condition, "order_id,group_id,group_record_id,verification_code,shop_id,pay_status,money_type,order_type,website_id,shop_id,goods_type,buyer_id", "");
                    foreach ($order_list as $k => $v) {
                        if ($v['pay_status'] == 2 && $v['money_type'] == 1) {
                            //修改支付状态
                            //$pay = new VslOrderPaymentModel();
                            //$pay->save(['pay_status'=>2],['out_trade_no'=>$order_pay_no]);
                            $order = new VslOrderModel();
                            $order->save(['money_type' => 2], ['order_id' => $v['order_id']]);
                        }
                        if ($v['pay_status'] == 2) {
                            if ($v['goods_type'] == 6) {
                                $orderSchedule = new VslOrderSchedule();
                                $orderSchedule->save(['status' => 1], ['order_id' => $v['order_id']]);
                            }
                        }
                    }
                    unset($v);
                    $this->dealPlatformAccountOrderPay($order_pay_no);
                    $user = new User();
                    $orders = $order_model->getInfo(['out_trade_no' => $order_pay_no], 'order_id');
                    if (empty($orders)) {
                        $orders = $order_model->getInfo(['out_trade_no_presell' => $order_pay_no], 'order_id');
                    }

                    $order_id = $orders['order_id'];
                    $order_info = $order_model->getInfo(['order_id' => $order_id]);
                    if($order_info['order_type'] == 15){
                        //幸运拼类型
                        $luckySpellServer = new luckySpellServer();
                        $luckySpellServer->addOrderRecords($order_info);
                    }
                    if ($v['pay_status'] == 2) {
                        // 计算主播分成
                        if (getAddons('liveshopping', $v['website_id'])) {
                            $live_shopping = new Liveshopping();
                            $live_shopping->computeAnchorEarnings($v["order_id"]);
                        }
                    }
                    if (getAddons('shop', $order_info['website_id']) == 1) {
                        $this->dealShopAccount_OrderPay($order_pay_no);
                    }

                    $buyer_id = $order_info['buyer_id'];
                    if (getAddons('microshop', $order_info['website_id'], $order_info['shop_id'])) {
                        if ($order_info['order_type'] == 2 || $order_info['order_type'] == 3 || $order_info['order_type'] == 4) {
                            $config = new MicroShopService();
                            $config->becomeShopKeeper($buyer_id, $order_id);
                        }
                    }

                    // 平台订单积分抵扣金额
                    if ($order_info["deduction_money"] > 0) {
                        $shop_account = new ShopAccount();
                        if ($order_info['order_type'] == 7) {//判断预售订单
                            $account_model = new VslAccountRecordsModel();
                            $orderInfo = $account_model->getInfo(['type_alis_id' => $order_id, 'remark' => '预售订单支付完成,积分抵扣金额']);
                            if (!$orderInfo) {
                                // 处理平台的账户的积分抵扣金额
                                if ($order_info['deduction_money']) {
                                    $shop_account->updateAccountOrderPoint($order_info['deduction_money'], $order_info['website_id']);
                                }
                                $shop_account->addAccountRecords($order_info['shop_id'], $buyer_id, '订单支付完成', $order_info["deduction_money"], 33, $order_id, "预售订单支付完成,积分抵扣金额", $order_info["website_id"]);
                            }
                        } else {
                            // 处理平台的账户的积分抵扣金额
                            if ($order_info['deduction_money']) {
                                $shop_account->updateAccountOrderPoint($order_info['deduction_money'], $order_info['website_id']);
                            }
                            $shop_account->addAccountRecords($order_info['shop_id'], $buyer_id, '订单支付完成', $order_info["deduction_money"], 33, $order_id, "订单支付完成,积分抵扣金额", $order_info["website_id"]);
                        }

                    }
                    // 平台订单优惠金额
                    $platform_promotion_money = $order_info["platform_promotion_money"];
                    if ($platform_promotion_money > 0) {
                        $shop_account = new ShopAccount();
                        $shop_account->addAccountRecords($order_info['shop_id'], $buyer_id, '订单支付完成', $platform_promotion_money, 23, $order_id, "订单支付完成，平台优惠金额", $order_info["website_id"]);
                    }

                    $user->updateUserGrowthNum(1, $buyer_id, 0, $order_id);
                    $user->updateUserLabel($buyer_id, $order_info['website_id']);

                    foreach ($order_list as $k => $v) {
                        if ($v['money_type'] == 0 || $v['money_type'] == 2) {
                            runhook("Notify", "orderPayBySms", array(
                                "order_id" => $v["order_id"],
                                "order_type" => $order_list[0]['order_type']
                            ));
                            runhook("Notify", "orderPayByTemplate", array(
                                "order_id" => $v["order_id"],
                                "order_type" => $order_list[0]['order_type']
                            ));
                            runhook("MpMessage", "orderPayMpByTemplate", array(
                                "order_id" => $order_id,
                                "website_id" => $v['website_id'],
                                "shop_id" => $v['shop_id'],
                            ));
                            // 微信支付不用
                            if ($is_yue == 1) {
                                runhook("MpMessage", "balanceChangeByMpTemplate", array(
                                    "order_id" => $order_id,
                                    "website_id" => $v['website_id'],
                                    "shop_id" => $v['shop_id'],
                                    "type" => 1
                                ));
                            }
                            runhook('Notify', 'orderRemindBusinessBySms', [
                                "order_id" => $v["order_id"],
                                "shop_id" => 0,
                                "website_id" => $v['website_id'],
                                "order_type" => $order_list[0]['order_type']
                            ]); // 订单提醒
                            // 邮件通知 - 用户
                            runhook('Notify', 'emailSend', [
                                'website_id' => $v['website_id'],
                                'shop_id' => 0,
                                'order_id' => $v['order_id'],
                                'notify_type' => 'user',
                                'template_code' => 'pay_success',
                                "order_type" => $order_list[0]['order_type']
                            ]);
                            // 邮件通知 - 卖家
                            runhook('Notify', 'emailSend', [
                                'website_id' => $v['website_id'],
                                'shop_id' => 0,
                                'order_id' => $v['order_id'],
                                'notify_type' => 'business',
                                'template_code' => 'order_remind',
                                "order_type" => $order_list[0]['order_type']
                            ]);
                            // 订单信息写入弹幕数据表
                            runhook('OrderBarrage', 'postOrderBarrageTrueQueue', [
                                'website_id' => $v['website_id'],
                                'shop_id' => 0,
                                'order_id' => $v['order_id'],
                                'uid' => $v['buyer_id'],
                            ]);
                        }
                        if ($v['group_id']) {
                            $group_server = new GroupShopping();
                            $group_server->createGroupRecord($v['order_id']);
                        }
                        if ($v['verification_code']) {
                            $url = __URLS('/clerk/pages/verify/order?code=' . $v['verification_code'] . '&website_id=' . $v['website_id']);
                            $verification_qrcode = getQRcode($url, 'upload/' . $v['website_id'] . '/verification_code', 'verification_code_' . $v['verification_code']);
                            if ($v['shop_id']) {
                                $verification_qrcode = getQRcode($url, 'upload/' . $v['website_id'] . '/' . $v['shop_id'] . '/verification_code', 'verification_code_' . $v['verification_code']);
                            }
                            $store_server = new Store();
                            $store_server->orderVerCodeSet($verification_qrcode, $v['order_id']);
                        }

                        if (getAddons('paygift', $v['website_id'], $v['shop_id'])) {
                            $paygift_server = new PayGift();
                            $paygift_server->createPaygiftRecord($v['order_id']);
                        }
                        if ($v['pay_status'] == 2) {
                            // 判断是否需要在本阶段赠送积分
                            $order = new OrderBusiness();
                            $res = $order->giveGoodsOrderPoint($v["order_id"], 3);
                        }
                    }
                    unset($v);
                }

            }
        } catch (\Exception $e) {
            debugLog($e->getMessage(), '==>余额支付-虚拟5<==');
            recordErrorLog($e);
            Log::write($e->getMessage());
        }
        return $retval;
    }

    /*
     * 订单线下支付
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderOffLinePay()
     */
    public function orderOffLinePay($order_id, $pay_type, $status)
    {
        $order = new OrderBusiness();
        $order_model = new VslOrderModel();
        if ($order_model::get($order_id)['order_status'] != 0) {
            return ['code' => -1, 'message' => '订单非未支付状态'];
        }

        //查询出该订单是否是预售的订单
        $is_presell_order = $order_model->getInfo(['order_id' => $order_id], 'presell_id, order_type, money_type, out_trade_no, out_trade_no_presell');
        if ($is_presell_order['order_type'] == 7 && !empty($is_presell_order['presell_id'])) {
            if ($is_presell_order['money_type'] == 0) {//付定金
                $order_model->save(['offline_pay_presell' => 2], ['order_id' => $order_id]);
                $new_no = $is_presell_order['out_trade_no'];
            } elseif ($is_presell_order['money_type'] == 1) {
                $new_no = $is_presell_order['out_trade_no_presell'];
                $order_model->save(['offline_pay' => 2], ['order_id' => $order_id]);
            }
        } else {
            $order_model->save(['offline_pay' => 2], ['order_id' => $order_id]);
            $new_no = $this->getOrderNewOutTradeNo($order_id);
            if (!$new_no) {
                return ['code' => -1, 'message' => '创建新的交易号失败'];
            }
        }
        $retval = $order->OrderPay($new_no, $pay_type, $status);
        if ($retval > 0) {
            $pay = new UnifyPay();
            $pay->offLinePay($new_no, $pay_type);
            $order_info = $order_model->getInfo(['order_id' => $order_id]);
            if($order_info['order_type'] == 15){
                //幸运拼类型
                $luckySpellServer = new luckySpellServer();
                $luckySpellServer->addOrderRecords($order_info);
            }
            $buyer_id = $order_info['buyer_id'];
            $user = new User();
            $user->updateUserGrowthNum(1, $buyer_id, 0, $order_id);
            $user->updateUserLabel($buyer_id, $this->website_id);
            if (getAddons('microshop', $this->website_id, $this->instance_id)) {
                if ($order_info['order_type'] == 2 || $order_info['order_type'] == 3 || $order_info['order_type'] == 4) {
                    $config = new MicroShopService();
                    $config->becomeShopKeeper($buyer_id, $order_id);
                }
            }
            if (getAddons('microshop', $this->website_id, $this->instance_id)) {
                if ($order_info['order_type'] == 2 || $order_info['order_type'] == 3 || $order_info['order_type'] == 4) {
                    $config = new MicroShopService();
                    $config->becomeShopKeeper($buyer_id, $order_id);
                }
            }
            // 处理店铺的账户资金
            if (getAddons('shop', $this->website_id) == 1) {
                $this->dealShopAccount_OrderPay('', $order_id);
            }
            // 处理平台的资金账户
            $this->dealPlatformAccountOrderPay('', $order_id);
            if ($order_info['verification_code']) {
                $url = __URLS('/clerk/pages/verify/order?code=' . $order_info['verification_code'] . '&website_id=' . $this->website_id);
                //根据核销码生成核销二维码
                $verification_qrcode = getQRcode($url, 'upload/' . $this->website_id . '/verification_code', 'verification_code_' . $order_info['verification_code']);
                if ($order_info['shop_id']) {
                    $verification_qrcode = getQRcode($url, 'upload/' . $this->website_id . '/' . $order_info['shop_id'] . '/verification_code', 'verification_code_' . $order_info['verification_code']);
                }
                $store_server = new Store();

                $store_server->orderVerCodeSet($verification_qrcode, $order_id);
            }
            if (getAddons('paygift', $this->website_id, $order_info['shop_id'])) {
                $paygift_server = new PayGift();
                $paygift_server->createPaygiftRecord($order_id);
            }
            // 判断是否需要在本阶段赠送积分
            $order = new OrderBusiness();
            $order->giveGoodsOrderPoint($order_id, 3);
            //                $pay_type_name = OrderStatus::getPayType($pay_type);
            hook('orderOffLinePaySuccess', [
                'order_id' => $order_id
            ]);
            //完善之后开启
            /*   runhook('Notify', 'orderRemindBusiness', [
                 "out_trade_no" => $new_no,
                 "shop_id" => 0
             ]); // 订单提醒   */

        }
        return ['code' => $retval, 'message' => '操作成功'];

    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getOrderNewOutTradeNo()
     */
    public function getOrderNewOutTradeNo($order_id)
    {
        $order_model = new VslOrderModel();
        $order = new OrderBusiness();
        $order_trade_record = new OrderTradeRecordModel();
        //将旧的交易流水号保存
        $order_info = $order_model->getInfo(['order_id' => $order_id], 'out_trade_no, website_id');
        $website_id = $order_info['website_id'] ?: 0;
        $old_no = $order_info['out_trade_no'] ?: '';
        $old_trade_no = $order_trade_record->getInfo(['order_id' => $order_id], 'old_trade_no')['old_trade_no'];//
        if ($old_trade_no) {
            $old_arr = json_decode($old_trade_no, true);
            array_push($old_arr, $old_no);
            $update_data['old_trade_no'] = json_encode($old_arr);
            $order_trade_record->save($update_data, ['order_id' => $order_id]);
        } else {
            $insert_data['order_id'] = $order_id;
            $insert_data['old_trade_no'] = json_encode([$old_no]);
            $insert_data['website_id'] = $website_id;
            $insert_data['create_time'] = time();
            $order_trade_record->save($insert_data);
        }
        $new_no = $order->createNewOutTradeNo($order_id);
        $order_model->where(['order_id' => $order_id])->update(['out_trade_no' => $new_no]);
        $pay = new UnifyPay();
        $pay->modifyNo($order_id, $new_no);
        return $new_no;
    }

    /**
     * 重新定义新的渠道商订单外部交易号
     */
    public function getChannelOrderNewOutTradeNo($order_id)
    {
        $order_model = new VslChannelOrderModel();
        $order = new OrderBusiness();
        $new_no = $order->createChannelNewOutTradeNo($order_id);
        $order_model->where(['order_id' => $order_id])->update(['out_trade_no' => $new_no]);
        $pay = new UnifyPay();
        $pay->modifyChannelNo($order_id, $new_no);
        return $new_no;
    }

    /**
     * 订单调整金额(non-PHPdoc)
     *
     * @see \data\api\IOrder::orderMoneyAdjust()
     */
    public function orderMoneyAdjust($order_id, $order_goods_id_adjust_array, $shipping_fee)
    {
        // 调整订单
        Db::startTrans();
        try {
            $order_goods = new OrderGoods();
            $order_adjust_money = 0;//统计订单金额变化
            $retval = $order_goods->orderGoodsAdjustMoney($order_id, $order_goods_id_adjust_array, $order_adjust_money, $shipping_fee);

            if ($retval >= 0) {
                // 计算整体商品调整金额
//                $new_no = $this->getOrderNewOutTradeNo($order_id);
                $order = new OrderBusiness();
                $retval_order = $order->orderAdjustMoney($order_id, $order_adjust_money, $shipping_fee);
                $order_model = new VslOrderModel();
                $order_money = $order_model->getInfo([
                    'order_id' => $order_id
                ], 'pay_money');
                $pay = new UnifyPay();
                $pay->modifyPayMoney(['type' => 1, 'type_alis_id' => $order_id], $order_money['pay_money']);
                Db::commit();
                return $retval_order;
            } else {
                return $retval;
            }
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }

    }

    /**
     * 查询订单项退款信息(non-PHPdoc)
     *
     * @see \data\api\IOrder::getOrderGoodsRefundInfo()
     */
    public function getOrderGoodsRefundInfo($order_goods_id)
    {
        $order_goods = new OrderGoods();
        $order_goods_info = $order_goods->getOrderGoodsRefundDetail($order_goods_id);
        return $order_goods_info;
    }

    /**
     * 重新申请退款修改退款状态
     */
    public function updateOrderStatus($order_goods_id, $order_id)
    {
        $order_goods = new VslOrderGoodsModel();
        if ($order_goods_id) {
            $order_goods->save(['refund_status' => 0], ['order_goods_id' => $order_goods_id]);
        }
        if ($order_id) {
            $order_goods->save(['refund_status' => 0], ['order_id' => $order_id]);
        }
    }

    public function getOrderGoodsRefundInfoNew(array $condition, $types = 0)
    {
        $order_goods_model = new VslOrderGoodsModel();
        $order_goods_info = $order_goods_model::all($condition, ['goods_sku', 'goods_pic']);
        $refund_info['refund_max_money'] = 0;
        $refund_info['require_refund_money'] = 0;
        $refund_info['refund_point'] = 0;
        $refund_info['refund_status'] = reset($order_goods_info)['refund_status'];
        $refund_info['order_status'] = reset($order_goods_info)->order->order_status;
        if ($types == 1) {
            $refund_info['order_status'] = reset($order_goods_info)['order_status'];
        }
//        $order_refund_status = OrderStatus::getRefundStatus();
//        $refund_info['status_name'] = $order_refund_status[$refund_info['refund_status']]['status_name'];
//        $refund_info['new_refund_operation'] = $order_refund_status[$refund_info['refund_status']]['new_refund_operation'];

        foreach ($order_goods_info as $k => $v) {
            // 处理发票退费问题
            $refund_tax = 0;
            if (getAddons('invoice', $this->website_id, $v['shop_id'])) {
                $invoice = new InvoiceServer();
                $invoiceConfig = $invoice->getInvoiceConfig(['website_id' => $this->website_id, 'shop_id' => $v['shop_id']], 'is_refund');
                if ($invoiceConfig) {
                    $is_refund = $invoiceConfig['is_refund'];
                    if ($is_refund == 0) {//不退款
                        $refund_tax = -$v['invoice_tax'];//因为税费已经算在real_money里面，所以不退款的话从real_money扣除税费
                    }
                }
            }
            //存在质数余额 理论上误差值不会大于1
            if ($v['real_money'] > 0 && $v['remainder'] > 0 && $v['remainder'] < 1) {
                $v['real_money'] += $v['remainder'];
            }
            //领货码

            //会员卡
//            if ($v['membercard_deduction_money'] && $v['real_money']==0){
            if ($v['membercard_deduction_money']) {
                $v['real_money'] += $v['membercard_deduction_money'];
            }

            if ($refund_info['order_status'] == 2 || $refund_info['order_status'] == 3 || $refund_info['order_status'] == 4 || ($refund_info['order_status'] == 0 && $v['delivery_num'])) {
                /*已发货、确认收货、已完成*/
                $refund_info['refund_max_money'] += $v['real_money'] - $v['shipping_fee'] + $refund_tax;
            } else {

                if ($v['membercard_deduction_money']) {
                    $v['shipping_fee'] -= $v['membercard_deduct_shipping_fee'];
                }
                //货到付款订单 退款需要扣除运费
                $order_model = new VslOrderModel();
                $payment_type = $order_model->getInfo(['order_id' => $v['order_id']], '*');
                if ($payment_type['payment_type'] == 4) {

                    $refund_info['refund_max_money'] += $v['real_money'] - $v['shipping_fee'];
                } else {
                    $refund_info['refund_max_money'] += $v['real_money'] + $refund_tax;
                }
                // $refund_info['refund_max_money'] += $v['real_money'];

            }

            if ((in_array($refund_info['order_status'], [2, 3, 4, 5]) && $v['deduction_freight_point'] > 0) || ($refund_info['order_status'] == 0 && $v['delivery_num'] && $v['deduction_freight_point'] > 0)) {
                $refund_info['refund_point'] += $v['deduction_point'] - $v['deduction_freight_point'];
            } else {
                $refund_info['refund_point'] += $v['deduction_point'];
            }
            $refund_info['require_refund_money'] += $v['refund_require_money'];
            $refund_info['refund_type'] = $v['refund_type'];
            $refund_info['refund_shipping_company'] = $v['refund_shipping_company'];
            $refund_info['refund_shipping_code'] = $v['refund_shipping_code'];
            $refund_info['refund_shipping_company_name'] = VslOrderExpressCompanyModel::get($v['refund_shipping_company'])['company_name'] ?: '';

            $temp_info['order_goods_id'] = $v['order_goods_id'];
            $temp_info['order_id'] = $v['order_id'];
            $temp_info['real_money'] = $v['real_money'];
            $temp_info['num'] = $v['num'];
            $temp_info['goods_id'] = $v['goods_id'];
            $temp_info['goods_name'] = $v['goods_name'];
            $temp_info['sku_id'] = $v['sku_id'];
            $temp_info['refund_type'] = $v['refund_type'];
            $temp_info['goods_type'] = $v['goods_type'];
            $temp_info['pic_cover'] = $v->goods_pic->pic_cover;
            $temp_info['delivery_num'] = $v['delivery_num'];
            $temp_info['supplier_id'] = $v['supplier_id'];
//            $temp_info['refund_shipping_company'] = $v['refund_shipping_company'];
//            $temp_info['refund_shipping_code'] = $v['refund_shipping_code'];
//            $temp_info['refund_shipping_company_name'] = VslOrderExpressCompanyModel::get($v['refund_shipping_company'])['company_name'] ?: '';

            if ($v->goods_sku->attr_value_items) {
                $goods_spec_value = new VslGoodsSpecValueModel();
                $spec_info = [];
                $sku_spec_info = explode(';', $v->goods_sku->attr_value_items);
                foreach ($sku_spec_info as $k_spec => $v_spec) {
                    $spec_value_id = explode(':', $v_spec)[1];
                    $sku_spec_value_info = $goods_spec_value::get($spec_value_id, ['goods_spec']);
                    $spec_info[$k_spec]['spec_value_name'] = $sku_spec_value_info['spec_value_name'];
                    $spec_info[$k_spec]['spec_name'] = $sku_spec_value_info['goods_spec']['spec_name'];
                    //$order_item_list[$key_item]['spec'][$k_spec]['spec_value_name'] = $sku_spec_value_info['spec_value_name'];
                    //$order_item_list[$key_item]['spec'][$k_spec]['spec_name'] = $sku_spec_value_info['goods_spec']['spec_name'];
                }

                $temp_info['spec'] = $spec_info;
                unset($sku_spec_value_info, $goods_sku_info, $sku_spec_info, $spec_info, $v_spec);
            }

            // 卖家拒绝时的理由
            $refund_goods_info = $v->order_goods_refund()->where(['action_way' => 2])->select();
            $temp_info['refund_reason'] = $v->refund_reason;

            $refund_info['shop_id'] = $v['shop_id'];
            $refund_info['order_id'] = $v->order_id;
            $refund_info['presell_id'] = $v->presell_id;
            $refund_info['reason'] = end($refund_goods_info)['reason'];
            $refund_info['refund_reason'] = $temp_info['refund_reason'];
            $refund_info['goods_list'][] = $temp_info;
            $refund_info['return_id'] = $v['return_id'];

        }
        unset($v);
        return $refund_info;
    }

    /**
     * 查询订单的订单项列表
     *
     * @param unknown $order_id
     */
    public function getOrderGoods($order_id)
    {
        $order = new OrderBusiness();
        return $order->getOrderGoods($order_id);
    }

    /**
     * 查询订单的订单项列表
     *
     * @param unknown $order_id
     */
    public function getOrderGoodsInfo($order_goods_id)
    {
        $order = new OrderBusiness();
        $picture = new AlbumPictureModel();
        $order_goods_info = $order->getOrderGoodsInfo($order_goods_id);
        $order_goods_info['goods_picture'] = $picture->getInfo(['pic_id' => $order_goods_info['goods_picture']], 'pic_cover')['pic_cover'];
        return $order_goods_info;
    }

    /**
     * 查询订单的模板消息详情
     *
     * @param unknown $order_id
     */
    public function getOrderMessage($order_id, $channel_status = NULL, $website_id = 0)
    {
        $order = new VslOrderModel();
        if (!$channel_status) {
            $order_goods = new VslOrderGoodsModel();
        } elseif ($channel_status == 1 && getAddons('channel', $website_id)) {//关闭渠道商订单
            $order = new VslChannelOrderModel();
            $order_goods = new VslChannelOrderGoodsModel();
        }
        $order_info = $order->getInfo(['order_id' => $order_id], '*');
        $address = new Address();
        $address_info = $address->getAddress($order_info['receiver_province'], $order_info['receiver_city'], $order_info['receiver_district']);
        $address_infos = $address_info . ' ' . $order_info['receiver_address'];
        $order_goods_info = $order_goods->Query(['order_id' => $order_id], 'goods_name');
        $info = [];
        $info['goods_name'] = implode(' ', $order_goods_info);
        $info['receiver_address'] = $address_infos;
        $info['order_money'] = $order_info['order_money'];
        $info['final_money'] = $order_info['final_money'];
        $info['order_no'] = $order_info['order_no'];
        $info['buyer_id'] = $order_info['buyer_id'];
        $info['order_time'] = $order_info['create_time'];
        $info['website_id'] = $order_info['website_id'];
        $info['form_id'] = $order_info['form_id'] ?: '';
        return $info;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefundAskfor()
     */
    public function orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason, $uid)
    {
        $order_goods = new OrderGoods();
        $order_model = new VslOrderModel();

        $order_info = $order_model->getInfo(['order_id' => $order_id], 'offline_pay,order_status,order_money,payment_type,order_no,shop_id,website_id,store_id,shipping_status');
        if ($order_info['order_status'] == 4) {
            return ['code' => -1, 'message' => '该订单已完成，不能再申请退款、退货操作'];
        }
        if ($order_info['order_status'] == 5) {
            return ['code' => -1, 'message' => '该订单已关闭！'];
        }
        if ($order_info['offline_pay'] == 2) {
            return ['code' => -1, 'message' => '该订单为后台操作付款，不能在线操作售后，请联系客服'];
        }
        if ($order_info['order_status'] != 5) {
            if ($order_info['payment_type'] == 4 && ($order_info['order_status'] < 3 || $order_info['order_status'] > 4) && $order_info['order_status'] != -1) {
                return ['code' => -1, 'message' => '货到付款订单，在未确认收货情况下不能申请退款退货！'];
            }
        }


        if (strstr($refund_require_money, 'ETH') || strstr($refund_require_money, 'EOS')) {
            $refund_require_money = $order_info['order_money'];
        }
        $retval = $order_goods->orderGoodsRefundAskfor($order_id, $order_goods_id, $refund_type, $refund_require_money, $refund_reason);
        if ($retval['code'] > 0) {
            //如果是门店订单,就推送订单信息到店员端
            if ($order_info['store_id']) {
                $store_server = new Store();
                $store_server->orderMessagePushToClerk($order_info['order_no'], $refund_require_money, 2, $order_info['store_id'], $order_info['shop_id'], $order_info['website_id']);
            }
            runhook("Notify", "orderRefoundBusinessBySms", [
                "shop_id" => 0,
                "website_id" => $this->website_id,
                "order_id" => $order_id
            ]); // 商家退款提醒
            runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $this->website_id, 'notify_type' => 'business', 'order_id' => $order_id, 'template_code' => 'refund_order']);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsCancel()
     */
    public function orderGoodsCancel($order_id, $order_goods_id)
    {
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsCancel($order_id, $order_goods_id);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsReturnGoods()
     */
    public function orderGoodsReturnGoods($order_id, $order_goods_id, $refund_shipping_company, $refund_shipping_code)
    {
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsReturnGoods($order_id, $order_goods_id, $refund_shipping_company, $refund_shipping_code);
        if ($retval) {
            runhook("Notify", "orderRefoundBusinessBySms", [
                "shop_id" => 0,
                "website_id" => $this->website_id,
                "order_id" => $order_id
            ]); // 商家退款提醒
            runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $this->website_id, 'notify_type' => 'business', 'order_id' => $order_id, 'template_code' => 'refund_order']);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefundAgree()
     */
    public function orderGoodsRefundAgree($order_id, $order_goods_id, $return_id = 0)
    {
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsRefundAgree($order_id, $order_goods_id, $return_id);
        if ($retval == 1) {
            //同意退款退货短信提醒
            $refund_type = Session::pull('refund_type');
            $params['order_id'] = $order_id;
            $params['order_goods_id'] = $order_goods_id;
            $params['refund_type'] = $refund_type;
            $params['website_id'] = $this->website_id;
            $params['shop_id'] = $this->instance_id;
            runhook('Notify', 'agreeRefundOrReturnBySms', $params);
            if ($refund_type == 1) {
                // 仅退款
                runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $this->website_id, 'refund_type' => $refund_type, 'order_goods_id' => $order_goods_id, 'notify_type' => 'user', 'template_code' => 'agree_refund']);
            } else {
                // 退货退款
                runhook('Notify', 'agreedReturnByTemplate', ['website_id' => $this->website_id, 'order_goods_id' => $order_goods_id]);
                runhook('MpMessage', 'agreedReturnByMpTemplate', $params);
                runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $this->website_id, 'order_goods_id' => $order_goods_id, 'notify_type' => 'user', 'template_code' => 'agree_return']);
            }
            // 修改发票状态
            if (getAddons('invoice', $this->website_id, $this->instance_id)) {
                $invoice = new InvoiceService();
                $invoice->updateOrderStatusByOrderId($order_id, 2);//关闭发票状态
            }
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefuseForever()
     */
    public function orderGoodsRefuseForever($order_id, $order_goods_id, $reason = '')
    {
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsRefuseForever($order_id, $order_goods_id, $reason);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsRefuseOnce()
     */
    public function orderGoodsRefuseOnce($order_id, $order_goods_id, $reason = '')
    {
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsRefuseOnce($order_id, $order_goods_id, $reason);
        if ($retval) {
            //拒绝退款退货短信提醒
            $params['order_id'] = $order_id;
            $params['order_goods_id'] = $order_goods_id;
            $params['refusal'] = $reason;
            $params['website_id'] = $this->website_id;
            $params['type'] = 1;
            runhook('Notify', 'refundFailedByTemplate', $params);
            runhook('Notify', 'refundFailedByMpTemplate', $params);
            runhook('Notify', 'refuseRefundOrReturnBySms', $params);
            runhook('Notify', 'refuseReturnByTemplate', $params);
            runhook('MpMessage', 'refundAfterSaleByMpTemplate', $params);
            runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $this->website_id, 'refuse_reason' => $reason, 'order_goods_id' => $order_goods_id, 'notify_type' => 'user']);
        }
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsConfirmRecieve()
     */
    public function orderGoodsConfirmReceive($order_id, $order_goods_id)
    {
        $order_goods = new OrderGoods();
        $retval = $order_goods->orderGoodsConfirmRecieve($order_id, $order_goods_id);
        return $retval;
    }

    /*  卖家确认退款
     * (non-PHPdoc)
     * @see \data\api\IOrder::orderGoodsConfirmRefund()
     * @params int $refundtype 0单笔 1整单
     */
    public function orderGoodsConfirmRefund($order_id, $order_goods_id, $refundtype = 0)
    {

        try {
            Db::startTrans();
            $member_account_record = new VslMemberAccountRecordsModel();
            // 已经退款的状态
            $had_refund_status_id = OrderStatus::getRefundStatus()[5]['status_id'];

            $order_goods_model = new VslOrderGoodsModel();
            $order = new VslOrderModel();
            $order_goods_info = $order_goods_model::all(['order_goods_id' => ['IN', $order_goods_id], 'refund_status' => ['NEQ', $had_refund_status_id]], ['order']);
            $order_info = $order->getInfo(['order_id' => $order_id], 'order_type,bargain_id,pay_gift_status,website_id,pay_money,membercard_deduction_money,buyer_id,refund_membercard_money,shipping_status,supplier_id');//判断是否是砍价订单
            if (empty($order_goods_info)) {
                return ['code' => 2, 'message' => '商品已退款'];
            }
            $payment_type = reset($order_goods_info)->order->payment_type;//1微信 2支付宝 3银行卡 4货到付款 5余额支付 10线下支付 16eth支付 17eos支付 20globepay
            $order_type = reset($order_goods_info)->order->order_type;
            $order_status = reset($order_goods_info)->order->order_status;
            $shipping_status = reset($order_goods_info)->order->shipping_status;
            //        $receive_goods_code = reset($order_goods_info)->receive_goods_code;
            $website_id = reset($order_goods_info)->website_id;
            $shop_id = reset($order_goods_info)->shop_id;

            //货到付款，退款到余额
            if ($payment_type == 4 && $order_type != 5) {
                $payment_type = 5;
            }
            if ($payment_type == 4 && $order_type == 5 && ($order_status == 3 || $order_status == 4 || $order_status == 5 || $order_status == -1)) {
                $payment_type = 5;
            }
            $refund_real_money = 0;
            $refund_point = $refund_point2 = 0;
            $deduction_point = $deduction_point2 = 0;
            $model = $this->getRequestModel();

            //领货码(多张)
            $receive_order_goods_data = [];
            $refund_membercard_money = 0;//会员卡抵扣的钱
            foreach ($order_goods_info as $k => $v) {
                if ($model == 'supplier') {
                    //如果是供应商端，则不能处理平台或店铺的商品
                    if (empty($v['supplier_id'])) {
                        return ['code' => -2];
                    }
                } else {
                    //如果是平台或店铺端，则不能处理供应商的商品  edit for 2020/12/08 平台活动的供应商商品，由于定时关闭并退款处理会拦截，所以先过滤拼团预售订单
                    if ($v['supplier_id'] && $order_info['order_type'] != 5 && $order_info['order_type'] != 7 && $order_info['order_type'] != 15) {
                        return ['code' => -3];
                    }
                }
                // 处理发票退费问题
                //            $is_refund = 0;
                //            if (getAddons('invoice', $v['website_id'], $v['shop_id'])) {
                //                $invoice = new InvoiceService();
                //                $invoiceConfig = $invoice->getInvoiceConfig(['website_id' =>$v['website_id'], 'shop_id' => $v['shop_id']] , 'is_refund');
                //                p($invoiceConfig,' ');
                //                if ($invoiceConfig) {
                //                    $is_refund = $invoiceConfig['is_refund'];//1退款0不退款
                //                }
                //            }
                //            $invoice_tax = $is_refund == 0 ? $v['invoice_tax'] : 0;
                //            p($invoice_tax,'$invoice_tax');
                //            if($v['membercard_deduction_money']){/*如果有会员卡，则税费不用再扣*/
                //                $invoice_tax = 0;
                //            }
                            //余额退款+会员卡退款：优先抵扣余额

                //            p($v);//退款

                //            $refund_real_money += $v['refund_require_money'] - $invoice_tax;
                $refund_real_money += $v['refund_require_money'];
                //赠送的积分
                if (in_array($shipping_status, [1, 2, 3]) && $v['return_freight_point']) {
                    $refund_point += $v['give_point'] - $v['return_freight_point'];
                } else {
                    $refund_point += $v['give_point'];
                }
                $refund_point2 += $v['give_point'];
                //积分抵扣的
                if (in_array($shipping_status, [1, 2, 3]) && $v['deduction_freight_point']) {
                    $deduction_point += $v['deduction_point'] - $v['deduction_freight_point'];
                } else {
                    $deduction_point += $v['deduction_point'];
                }
                $deduction_point2 += $v['deduction_point'];
                //去除主播冻结收益
                if (getAddons('liveshopping', $order_info['website_id'])) {
                    $live_shopping = new Liveshopping();
                    $live_shopping->refoundAnchorEarnings($order_id, $v['order_goods_id']);
                }
                //领货码(多张)
                if ($v['receive_order_goods_data']) {
                    $receive_order_goods_data[] = json_decode(htmlspecialchars_decode($v['receive_order_goods_data']), true);
                }

                if ($v['membercard_deduction_money']) {
                    //如果发货退款扣除抵扣的运费费用
                    if (in_array($v['order_status'], [2, 3, 4]) || $v['order_status'] == 0 && $v['delivery_num']) {
                        $v['membercard_deduction_money'] = bcsub($v['membercard_deduction_money'], $v['membercard_deduct_shipping_fee'], 2);
                    }
                    //                $refund_membercard_money += $v['membercard_deduction_money'];
                    $refund_membercard_money = bcadd($refund_membercard_money, $v['membercard_deduction_money'], 2);
                    //                $refund_real_money2 = bcsub($refund_real_money,$refund_membercard_money,2);
                    //                $refund_real_money2 = $refund_real_money2>0?$refund_real_money2:0;
                }
            }
            unset($v);
            //退款如果订单有积分则扣除 并且是在 2-已收货，3-已支付的节点要扣除获得的积分
            $give_point_type = reset($order_goods_info)->order->give_point_type;
            if ($refund_point > 0 && ($give_point_type == 2 || $give_point_type == 3)) {
                $uid = reset($order_goods_info)->order->buyer_id;
                $website_id = reset($order_goods_info)->order->website_id;
                $order_id = reset($order_goods_info)->order->order_id;
                //判断是否真实已获得积分
                $refund_point_info = $member_account_record->getInfo(['uid' => $uid, 'website_id' => $website_id, 'account_type' => 1, 'sign' => 1, 'number' => $refund_point2, 'data_id' => $order_id]);
                if (!empty($refund_point_info)) {
                    $member_mdl = new VslMemberAccountModel();
                    $convert_rate = reset($order_goods_info)->order->point_convert_rate;//积分兑换金额
                    $all_info = $member_mdl->getInfo(['uid' => $uid, 'website_id' => $website_id], '*');
                    if (empty($all_info)) {
                        $member_all_point = 0;
                    } else {
                        $member_all_point = $all_info['point'];
                    }
                    //更新对应会员账户
                    $data_member['point'] = $member_all_point - $refund_point;
                    if ($data_member['point'] < 0) {//如果会员积分不足以抵扣退款积分，则将其换成金钱
                        $data_member['point'] = 0;
                        $change_point = abs($data_member['point']);
                        //换算 积分不足兑换成金钱
                        $change_money = $change_point / $convert_rate;
                        //减掉不足的积分兑换成金钱减掉
                        $refund_real_money = $refund_real_money - $change_money > 0 ? $refund_real_money - $change_money : 0;
                    }
                    $data = array(
                        'records_no' => getSerialNo(),
                        'account_type' => 1,
                        'uid' => $uid,
                        'sign' => 0,
                        'number' => '-' . $refund_point,
                        'from_type' => 2,//订单退还
                        'data_id' => $order_id,
                        'text' => '订单退款扣除会员获得的相应积分',
                        'create_time' => time(),
                        'website_id' => $website_id
                    );
                    $member_account_record->insert($data);

                    //计算会员累计积分
                    $data_member['member_sum_point'] = $member_all_point - $refund_point;
                    $member_mdl->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                }
            }
            if ($deduction_point > 0) {
                $uid = reset($order_goods_info)->order->buyer_id;
                $website_id = reset($order_goods_info)->order->website_id;
                $order_id = reset($order_goods_info)->order->order_id;
                //判断是否真实已抵扣积分
                $deduction_point_info = $member_account_record->getInfo(['uid' => $uid, 'website_id' => $website_id, 'account_type' => 1, 'sign' => 0, 'number' => '-' . $deduction_point2, 'data_id' => $order_id]);
                if (!empty($deduction_point_info)) {
                    $member_mdl = new VslMemberAccountModel();
                    $all_info = $member_mdl->getInfo(['uid' => $uid, 'website_id' => $website_id], '*');
                    if (empty($all_info)) {
                        $member_all_point = 0;
                    } else {
                        $member_all_point = $all_info['point'];
                    }
                    $data = array(
                        'records_no' => getSerialNo(),
                        'account_type' => 1,
                        'uid' => $uid,
                        'sign' => 1,
                        'number' => $deduction_point,
                        'from_type' => 2,//订单退还
                        'data_id' => $order_id,
                        'text' => '订单退款退还积分抵扣的积分',
                        'create_time' => time(),
                        'website_id' => $website_id
                    );
                    $member_account_record->insert($data);
                    //更新对应会员账户
                    $data_member = [];
                    $data_member['point'] = $member_all_point + $deduction_point;
                    $member_mdl->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                }
            }
            //退礼品
            if ($order_info['pay_gift_status'] == 1) {
                $paygift_server = new PayGift();
                //$paygift_server->returnPayGift($order_id);
            }
            //减掉渠道商的冻结余额
            if (getAddons('channel', $this->website_id)) {
                //如果是渠道商订单则减去冻结金额。
                $channel = new Channel();
                $channel->deleteMemberFreezingAccountBalance($order_id, $order_goods_id, $this->website_id);
                sleep(1);
            }
            //减掉招商员的冻结金额
            if (getAddons('merchants', $this->website_id)) {
                $merchants = new Merchants();
                $merchants->deleteMerchantsFreezingBonus($order_id, $order_goods_id, $this->website_id, $refundtype);
            }
            $refund_trade_no = date("YmdHis") . rand(100000, 999999);

            // 支付方式也是退款方式
            $presell_id = reset($order_goods_info)->order->presell_id;
            $money_type = reset($order_goods_info)->order->money_type;
            if ($presell_id) {//预售退款
                if ($money_type == 2 && getAddons('presell', $this->website_id, $this->instance_id)) {
                    $first_money = reset($order_goods_info)->order->pay_money;
                    $final_money = reset($order_goods_info)->order->final_money;

                    //判断此笔订单有没有使用会员卡抵扣，并且判断订单金额是不是全部是会员卡抵扣的
                    if ($first_money == 0.00 && $final_money == 0.00 && $order_info['membercard_deduction_money'] != 0.00) {
                        //全部是会员卡抵扣
                        if (getAddons('membercard', $order_info['website_id'])) {
                            $membercard = new MembercardSer();
                            $membercard->adjustBalance($order_info['buyer_id'], $order_info['membercard_deduction_money'], '订单退款', 76, $order_id);
                        }
                    } else {
                        if ($order_info['membercard_deduction_money'] != 0.00) {
                            //使用了会员卡抵扣一部分
                            //$refund_real_money = $refund_real_money - $order_info['membercard_deduction_money'];
                            if (getAddons('membercard', $order_info['website_id'])) {
                                $membercard = new MembercardSer();
                                $membercard->adjustBalance($order_info['buyer_id'], $order_info['membercard_deduction_money'], '订单退款', 76, $order_id);
                            }
                        }

                        if (reset($order_goods_info)->order->shipping_status == 1) {//如果发货了，则运费不退了
                            $after_final_money = reset($order_goods_info)->order->final_money - reset($order_goods_info)->order->shipping_money;
                        } else {
                            $after_final_money = reset($order_goods_info)->order->final_money;
                        }
                        # 如果使用了会员卡 - 会员卡不能直接处理$refund_real_money，因为下面财务账户会用到申请退款金额
                        $refund_real_money_new = $refund_real_money;
                        if ($refund_real_money > 0) {
                            if ($refund_membercard_money) {
                                $refund_real_money_new = bcsub($refund_real_money_new, $refund_membercard_money, 2);
                                $refund_real_money_new = $refund_real_money_new > 0 ? $refund_real_money_new : 0;
                            }
                            //算出各个付款方式付的金额
                            if ($first_money > 0 || $after_final_money > 0) {
                                $refund_first_money = round($refund_real_money_new * ($first_money / ($first_money + $after_final_money)), 2);
                            } else {
                                $refund_first_money = 0;
                            }

                            //退款金额 * （定金  / (定金+尾款)）
                            $refund_final_money = $refund_real_money_new - $refund_first_money;
                            $payment_type_presell = reset($order_goods_info)->order->payment_type_presell;
                            $payment_arr = ['payment_type' => $payment_type, 'payment_type_presell' => $payment_type_presell];
                            //循环退
                            $merge_refund_real_money = 0;
                            if ($refund_membercard_money) {
                                $merge_refund_real_money = bcadd($merge_refund_real_money, $refund_membercard_money, 2);
                            }
                            foreach ($payment_arr as $key => $payment) {
                                $refund_trade_no = date("YmdHis") . rand(100000, 999999);
                                $refund_real_money_new = 0;
                                //申请是540
                                if ($key == 'payment_type') {
                                    $refund_real_money_new = $refund_first_money; //实际支付定金
                                    $merge_refund_real_money += $refund_first_money;
                                    $pay_money = $first_money;
                                    $refund_first_money = 0;
                                } else {
                                    $pay_money = $final_money;
                                    $refund_real_money_new = $refund_final_money; //实际支付尾款
                                    $merge_refund_real_money += $refund_final_money;
                                }

                                if ($payment == 5) {
                                    // 退还会员的账户余额
                                    $retval = $this->updateMemberAccount($order_id, reset($order_goods_info)->order->buyer_id, $refund_real_money_new);
                                    if (!is_numeric($retval)) {
                                        Db::rollback();
                                        return ['code' => -1, 'message' => '余额退款失败'];
                                    }
                                } else if ($payment == 1 || $payment == 2) {
                                    // 在线原路退款（微信/支付宝）
                                    $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money_new, $payment, $refund_trade_no, $pay_money, $key);
                                    if ($refund['is_success'] != 1) {
                                        Db::rollback();
                                        return ['code' => -1, 'message' => $refund['msg']];
                                    }
                                } else if ($payment == 3) {
                                    // 在线原路退款（银行卡）
                                    $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money_new, $payment, $refund_trade_no, $pay_money, $key);
                                    if ($refund['is_success'] != 1) {
                                        Db::rollback();
                                        return ['code' => -1, 'message' => $refund['msg']];
                                    }
                                } else if ($payment == 20) {
                                    // 在线原路退款（GlobyPay）
                                    $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money_new, $payment, $refund_trade_no, $pay_money, $key);
                                    if ($refund['is_success'] != 1) {
                                        Db::rollback();
                                        return ['code' => -1, 'message' => $refund['msg']];
                                    }
                                }
                            }
                        }
                        unset($payment);
                    }
                } else {
                    Db::rollback();
                    return ['code' => -1, 'message' => '退款失败'];
                }
            } else {//正常流程退款
                if ($payment_type == 4) {
                    $payment_type = 5;
                }


                $refund_real_money_new = $refund_real_money;//优先扣除会员卡
                if ($refund_membercard_money > 0) {
                    $temp_refund_money = bcsub($refund_real_money_new, $refund_membercard_money, 2);
                    if ($temp_refund_money >= 0) {
                        $refund_membercard_money = $refund_membercard_money;
                        $refund_real_money_new = $temp_refund_money;
                    } else {
                        $refund_membercard_money = $refund_real_money_new;
                        $refund_real_money_new = 0;
                    }
                    //更新会员卡余额(优先会员卡)
                    if (getAddons('membercard', $order_info['website_id'])) {
                        $membercard = new MembercardSer();
                        $membercard->adjustBalance($order_info['buyer_id'], $refund_membercard_money, '订单退款', 76, $order_id);
                    }
                    //更新此笔订单已经退还到会员卡的金额
                    $order->isUpdate(true)->save(['refund_membercard_money' => bcadd($order_info['refund_membercard_money'], $refund_membercard_money, 2)], ['order_id' => $order_id]);
                }

                if ($refund_real_money_new > 0) {
                    if ($payment_type == 5) {
                        // 退还会员的账户余额
                        $retval = $this->updateMemberAccount($order_id, reset($order_goods_info)->order->buyer_id, $refund_real_money_new);
                        if (!is_numeric($retval)) {
                            debugFile($retval, 'createLuckySpell-0-7-1-2-2', 1111112);
                            Db::rollback();
                            return ['code' => -1, 'message' => '余额退款失败'];
                        }
                    }
                    else if ($payment_type == 7) {
                        // 退还会员的账户美丽分
                        $retval = $this->updateMemberBeautifulPoint($order_id, reset($order_goods_info)->order->buyer_id, $refund_real_money_new);
                        if (!is_numeric($retval)) {
                            debugFile($retval, 'createLuckySpell-0-7-1-2-2', 1111113);
                            Db::rollback();
                            return ['code' => -1, 'message' => '美丽分退款失败'];
                        }
                    }
                    else if ($payment_type == 1 || $payment_type == 2) {
                        // 在线原路退款（微信/支付宝）
                        $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money_new, $payment_type, $refund_trade_no, reset($order_goods_info)->order->pay_money);
                        if ($refund['is_success'] != 1) {
                            Db::rollback();
                            return ['code' => -1, 'message' => $refund['msg']];
                        }
                    } else if ($payment_type == 3) {
                        // 在线原路退款（银行卡）
                        $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money_new, $payment_type, $refund_trade_no, reset($order_goods_info)->order->pay_money);
                        if ($refund['is_success'] != 1) {
                            Db::rollback();
                            return ['code' => -1, 'message' => $refund['msg']];
                        }
                    } else if ($payment_type == 20) {
                        // 在线原路退款（GlobyPay）
                        $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money_new, $payment_type, $refund_trade_no, reset($order_goods_info)->order->pay_money);
                        if ($refund['is_success'] != 1) {
                            Db::rollback();
                            return ['code' => -1, 'message' => $refund['msg']];
                        }
                    }
                }
            }

            if ($refundtype == 1) {
                //全单退款
                foreach ($order_goods_info as $k => $v) {

                    $payment_typeg = $v->order->payment_type;
                    if ($payment_typeg == 4 && $order_type != 5) {
                        $payment_typeg = 5;
                    }
                    if ($payment_type == 4 && $order_type == 5 && ($order_status == 3 || $order_status == 4 || $order_status == 5 || $order_status == -1)) {
                        $payment_type = 5;
                    }
                    $order_goods = new OrderGoods();
                    $order_goods->orderGoodsConfirmRefund($order_id, $v->order_goods_id, $v->refund_require_money, $v->real_money, $refund_trade_no, $payment_typeg, '');
                    // 计算店铺的账户
                    $order_goods_id = $v->order_goods_id;
                    if ($v->shop_id > 0) {
                        $shop_id = $v->shop_id;
                    } else {
                        $shop_id = 0;
                    }
                    // 计算平台的账户
                    $this->updateAccountOrderRefund($v->order_goods_id, $refundtype); //处理分销分红应用信息
                }
                unset($v);
                // 计算店铺的账户
                //merge_refund_real_money 只有在预售的时候存在
                $refund_real_money = $merge_refund_real_money > 0 ? $merge_refund_real_money : $refund_real_money;
                if ($shop_id > 0) {
                    $this->updateShopAccount_OrderRefund_All($order_id, $refund_real_money);
                }

            } else {
                foreach ($order_goods_info as $k => $v) {

                    $payment_typeg = $v->order->payment_type;
                    if ($payment_typeg == 4 && $order_type != 5) {
                        $payment_typeg = 5;
                    }
                    if ($payment_type == 4 && $order_type == 5 && ($order_status == 3 || $order_status == 4 || $order_status == 5 || $order_status == -1)) {
                        $payment_type = 5;
                    }
                    $order_goods = new OrderGoods();

                    $r1 = $order_goods->orderGoodsConfirmRefund($order_id, $v->order_goods_id, $v->refund_require_money, $v->real_money, $refund_trade_no, $payment_typeg, '');
                    // 计算店铺的账户

                    if ($v->shop_id > 0) {
                        $this->updateShopAccount_OrderRefund($v->order_goods_id);
                    }

                    // 计算平台的账户
                    $r3 = $this->updateAccountOrderRefund($v->order_goods_id);

                }
                unset($v);
            }
            $website_id = reset($order_goods_info)->order->website_id;
            if (getAddons('channel', $website_id)) {
                //判断订单是否是渠道商零售相关的,是的话就删除这条零售记录
                $cosr_mdl = new VslChannelOrderSkuRecordModel();
                if ($refundtype == 1) {
                    //整单退
                    $channel_retail_info = $cosr_mdl->getInfo(['order_id' => $order_id], "*");
                    if ($channel_retail_info) {
                        $cosr_mdl->where(['order_id' => $order_id])->delete();
                    }
                } else {
                    if (is_int($order_goods_id) || is_string($order_goods_id)) {
                        $goods_id = $order_goods_model->getInfo(['order_goods_id' => $order_goods_id], 'goods_id');
                        $channel_retail_info = $cosr_mdl->getInfo(['order_id' => $order_id, 'goods_id' => $goods_id['goods_id']], "*");
                        if ($channel_retail_info) {
                            $cosr_mdl->where(['order_id' => $order_id, 'goods_id' => $goods_id['goods_id']])->delete();
                        }
                    } else {
                        foreach ($order_goods_id as $k => $v) {
                            $goods_id = $order_goods_model->getInfo(['order_goods_id' => $v], 'goods_id');
                            $channel_retail_info = $cosr_mdl->getInfo(['order_id' => $order_id, 'goods_id' => $goods_id['goods_id']], "*");
                            if ($channel_retail_info) {
                                $cosr_mdl->where(['order_id' => $order_id, 'goods_id' => $goods_id['goods_id']])->delete();
                            }
                        }
                        unset($v);
                    }

                }
            }
            // 修改发票状态
            if (getAddons('invoice', $website_id, $shop_id)) {
                $invoice = new InvoiceService();
                $invoice->updateOrderStatusByOrderId($order_id, 2);//关闭发票状态
            }
            //领货码
            //        if (getAddons('receivegoodscode',$website_id, $shop_id) && $receive_goods_code){
            if (getAddons('receivegoodscode', $website_id, $shop_id) && $receive_order_goods_data) {
                $codeSer = new ReceiveGoodsCodeSer();
                $code_ids = array_column($receive_order_goods_data, 'code_id');
                foreach ($code_ids as $code_id) {
                    foreach ($code_id as $id) {
                        $codeSer->rollbackUserReceiveGoodsCodeByCodeId($id, $website_id, $shop_id);
                    }
                }
            }

            Db::commit();
            //确认退款提醒
            $params['website_id'] = $order_info['website_id'] ?: $this->website_id;
            $params['shop_id'] = 0;
            $params['order_id'] = $order_id;
            $params['uid'] = reset($order_goods_info)->order->buyer_id;
            $params['order_goods_id'] = $order_goods_id;
            $params['notify_type'] = 'user';
            $params['template_code'] = 'return_success';
            $params['refund_money'] = $refund_real_money;
            runhook('Notify', 'confirmRefundBySms', $params);
            runhook('Notify', 'emailSend', $params);
            runhook('Notify', 'refundSuccessByTemplate', $params);
            runhook('MpMessage', 'refundAfterSaleByMpTemplate', $params);
            return ['code' => 1, 'message' => '退款成功'];
        } catch (\Exception $e) {
            debugFile($e->getMessage(), 'createLuckySpell-0-7-1-2-9', 1111112);
        }
    }

    public function orderGoodsConfirmRefunds($order_id, $password)
    {
        if (!isset($password['ethPassword']) && !isset($password['eosPassword'])) {
            $password = isset($password[0]) ? $password[0] : $password;
        }

        $order_goods_model = new VslOrderGoodsModel();
        $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
        $order_goods_id = [];
        foreach ($order_goods_info as $v) {
            $order_goods_id[] = $v->order_goods_id;
        }
        unset($v);
        // 已经退款的状态
        $had_refund_status_id = OrderStatus::getRefundStatus()[5]['status_id'];
        $order_goods_model = new VslOrderGoodsModel();
        $order_goods_info = $order_goods_model::all(['order_goods_id' => ['IN', $order_goods_id], 'refund_status' => ['NEQ', $had_refund_status_id]], ['order']);
        if (empty($order_goods_info)) {
            return ['code' => 2, 'message' => '商品已退款'];
        }
        $order = new VslOrderModel();
        $money = 0;
        $order_info = $order->getInfo(['order_id' => $order_id], '*');
        if (($order_info['order_status'] == -1 && $order_info['shipping_money'] && $order_info['shipping_status'] >= 1) || ($order_info['order_status'] == 2 && $order_info['shipping_money'])) {
            $money = $order_info['shipping_money'];
        }
        if ($order_info['presell_id']) {
            if ($order_info['payment_type'] == 16 && $order_info['payment_type_presell'] == 16) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info1 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 4]);
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no_presell'], 'from_type' => 4]);
                $coin_cash = floatval($eth_info1['cash'] + $eth_info2['cash']);
                $coin_gas = floatval($eth_info1['gas'] + $eth_info2['gas']);
                $result2 = $block->ethPayRefund($order_info['out_trade_no'], $order_info['out_trade_no_presell'], $password, $money, $coin_cash, $coin_gas);
                if ($result2['code'] == 200) {
                    $order = new VslOrderModel();
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                } else if ($result2['code'] == 1000) {
                } else {
                    return ['code' => -1, 'message' => $result2['msg']];
                }
                return ['code' => 1, 'message' => '链上处理中'];
            }
            if ($order_info['payment_type'] == 16 && $order_info['payment_type_presell'] == 17) {
                if (!isset($password['ethPassword']) && !isset($password['eosPassword'])) {
                    return ['code' => -1, 'message' => LACK_OF_PARAMETER];
                }
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info1 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 4]);
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no_presell'], 'from_type' => 8]);
                $result1 = $block->ethPayRefund($order_info['out_trade_no'], $order_info['out_trade_no'], $password['ethPassword'], 0, $eth_info1['cash'], $eth_info1['gas']);
                $result2 = $block->eosPayRefund($order_info['out_trade_no_presell'], $order_info['out_trade_no_presell'], $password['eosPassword'], $money, $eth_info2['cash']);

                if (($result1['code'] == 200 || $result1['code'] == 1012) && ($result2['code'] == 200 || $result2['code'] == 1012)) {
                    $order = new VslOrderModel();
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                } else {
                    if ($result1['code'] != 200 && $result1['code'] != 1012) {
                        return ['code' => -1, 'message' => $result1['msg'], 'msgs' => 123];
                    }
                    if ($result2['code'] != 200 && $result2['code'] != 1012) {
                        return ['code' => -1, 'message' => $result2['msg'], 'msgs' => 456];
                    }
                }
                return ['code' => 1, 'message' => '链上处理中'];
            }
            if ($order_info['payment_type'] == 17 && $order_info['payment_type_presell'] == 16) {
                if (!isset($password['ethPassword']) && !isset($password['eosPassword'])) {
                    return ['code' => -1, 'message' => LACK_OF_PARAMETER];
                }
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info1 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 8]);
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no_presell'], 'from_type' => 4]);
                $result1 = $block->eosPayRefund($order_info['out_trade_no'], $order_info['out_trade_no'], $password['eosPassword'], 0, $eth_info1['cash']);
                $result2 = $block->ethPayRefund($order_info['out_trade_no_presell'], $order_info['out_trade_no_presell'], $password['ethPassword'], $money, $eth_info2['cash'], $eth_info2['gas']);

                if (($result1['code'] == 200 || $result1['code'] == 1012) && ($result2['code'] == 200 || $result2['code'] == 1012)) {
                    $order = new VslOrderModel();
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                } else {
                    if ($result1['code'] != 200 && $result1['code'] != 1012) {
                        return ['code' => -1, 'message' => $result1['msg'], 'msgs' => 123];
                    }
                    if ($result2['code'] != 200 && $result2['code'] != 1012) {
                        return ['code' => -1, 'message' => $result2['msg'], 'msgs' => 456];
                    }
                }
                return ['code' => 1, 'message' => '链上处理中'];
            }
            if ($order_info['payment_type'] == 17 && $order_info['payment_type_presell'] == 17) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info1 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 8]);
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no_presell'], 'from_type' => 8]);
                $result1 = $block->eosPayRefund($order_info['out_trade_no'], $order_info['out_trade_no'], $password, 0, $eth_info1['cash']);
                if ($result1['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                } else if ($result1['code'] == 1000) {
                } else {
                    return ['code' => -1, 'message' => $result1['msg']];
                }
                $result2 = $block->eosPayRefund($order_info['out_trade_no_presell'], $order_info['out_trade_no_presell'], $password, $money, $eth_info2['cash']);
                if ($result2['code'] == 200) {
                    $order = new VslOrderModel();
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                } else if ($result2['code'] == 1000) {
                } else {
                    return ['code' => -1, 'message' => $result2['msg']];
                }
                return ['code' => 1, 'message' => '链上处理中'];
            }
            if ($order_info['payment_type'] == 16 && $order_info['payment_type_presell'] != 16 && $order_info['payment_type_presell'] != 17) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info1 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 4]);
                $result = $block->ethPayRefund($order_info['order_no'], $order_info['out_trade_no'], $password, 0, $eth_info1['cash'], $eth_info1['gas']);//付定金不应该传运费
                if ($result['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                    return ['code' => 1, 'message' => '链上处理中'];
                } else {
                    return ['code' => -1, 'message' => $result['msg']];
                }
            }
            if ($order_info['payment_type'] == 17 && $order_info['payment_type_presell'] != 16 && $order_info['payment_type_presell'] != 17) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 8]);
                $result = $block->eosPayRefund($order_info['order_no'], $order_info['out_trade_no'], $password, 0, $eth_info2['cash']);//付定金不应该传运费
                if ($result['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                    return ['code' => 1, 'message' => '链上处理中'];
                } else {
                    return ['code' => -1, 'message' => $result['msg']];
                }
            }
            if ($order_info['payment_type_presell'] == 16 && $order_info['payment_type'] != 16 && $order_info['payment_type'] != 17) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no_presell'], 'from_type' => 4]);
                $result = $block->ethPayRefund($order_info['out_trade_no_presell'], $order_info['out_trade_no_presell'], $password, $money, $eth_info2['cash'], $eth_info2['gas']);
                if ($result['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                    return ['code' => 1, 'message' => '链上处理中'];
                } else {
                    return ['code' => -1, 'message' => $result['msg']];
                }
            }
            if ($order_info['payment_type_presell'] == 17 && $order_info['payment_type'] != 16 && $order_info['payment_type'] != 17) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no_presell'], 'from_type' => 8]);//4为eth支付,8为eos支付
                $result = $block->eosPayRefund($order_info['out_trade_no_presell'], $order_info['out_trade_no_presell'], $password, $money, $eth_info2['cash']);
                if ($result['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                    return ['code' => 1, 'message' => '链上处理中'];
                } else {
                    return ['code' => -1, 'message' => $result['msg']];
                }
            }
        } else {
            if ($order_info['payment_type'] == 16) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info1 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 4]);
                $result = $block->ethPayRefund($order_info['order_no'], $order_info['out_trade_no'], $password, $money, $eth_info1['cash'], $eth_info1['gas']);//付定金不应该传运费
                if ($result['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                    return ['code' => 1, 'message' => '链上处理中'];
                } else {
                    return ['code' => -1, 'message' => $result['msg']];
                }
            }
            if ($order_info['payment_type'] == 17) {
                $block = new Block();
                $member_account_record = new VslBlockChainRecordsModel();
                $eth_info2 = $member_account_record->getInfo(['data_id' => $order_info['out_trade_no'], 'from_type' => 8]);
                $result = $block->eosPayRefund($order_info['order_no'], $order_info['out_trade_no'], $password, $money, $eth_info2['cash']);
                if ($result['code'] == 200) {
                    $order->save(['coin_after' => 1], ['order_id' => $order_id]);
                    return ['code' => 1, 'message' => '链上处理中'];
                } else {
                    return ['code' => -1, 'message' => $result['msg']];
                }
            }
        }
    }

    /**
     * 在线原路退款（虚拟币退款回调）
     */
    public function orderRefundBack($order_id)
    {
        try {
            Db::startTrans();
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
            unset($v);
            $payment_type = reset($order_goods_info)->order->payment_type;
            $shipping_status = reset($order_goods_info)->order->shipping_status;
            // 已经退款的状态
            $had_refund_status_id = OrderStatus::getRefundStatus()[5]['status_id'];
            $order_goods_model = new VslOrderGoodsModel();
            $order = new VslOrderModel();
            $order_goods_info = $order_goods_model::all(['order_goods_id' => ['IN', $order_goods_id], 'refund_status' => ['NEQ', $had_refund_status_id]], ['order']);
            $order_info = $order->getInfo(['order_id' => $order_id], 'bargain_id,pay_gift_status,website_id');//判断是否是砍价订单
            if (empty($order_goods_info)) {
                return ['code' => 2, 'message' => '商品已退款'];
            }
            $website_id = $order_info['website_id'];
            $refund_real_money = 0;
            $refund_point = $refund_point2 = 0;
            $deduction_point = $deduction_point2 = 0;
            foreach ($order_goods_info as $k => $v) {
                $refund_real_money += $v['refund_require_money'];
                //赠送的积分
                if (in_array($shipping_status, [1, 2, 3]) && $v['return_freight_point'] > 0) {
                    $refund_point += $v['give_point'] - $v['return_freight_point'];
                } else {
                    $refund_point += $v['give_point'];
                }
                $refund_point2 += $v['give_point'];
                //积分抵扣的
                if (in_array($shipping_status, [1, 2, 3]) && $v['deduction_freight_point'] > 0) {
                    $deduction_point += $v['deduction_point'] - $v['deduction_freight_point'];
                } else {
                    $deduction_point += $v['deduction_point'];
                }
                $deduction_point2 += $v['deduction_point'];
            }
            unset($v);
            $member_account_record = new VslMemberAccountRecordsModel();
            //退款如果订单有积分则扣除 并且是在 2-已收货，3-已支付的节点要扣除获得的积分
            $give_point_type = reset($order_goods_info)->order->give_point_type;
            if ($refund_point > 0 && ($give_point_type == 2 || $give_point_type == 3)) {
                $uid = reset($order_goods_info)->order->buyer_id;
                $website_id = reset($order_goods_info)->order->website_id;
                $order_id = reset($order_goods_info)->order->order_id;
                //判断是否真实已获得积分
                $refund_point_info = $member_account_record->getInfo(['uid' => $uid, 'website_id' => $website_id, 'account_type' => 1, 'sign' => 1, 'number' => $refund_point2, 'data_id' => $order_id]);
                if (!empty($refund_point_info)) {
                    $member_mdl = new VslMemberAccountModel();
                    $convert_rate = reset($order_goods_info)->order->point_convert_rate;//积分兑换金额
                    $all_info = $member_mdl->getInfo(['uid' => $uid, 'website_id' => $website_id], '*');
                    if (empty($all_info)) {
                        $member_all_point = 0;
                    } else {
                        $member_all_point = $all_info['point'];
                    }
                    $data = array(
                        'records_no' => getSerialNo(),
                        'account_type' => 1,
                        'uid' => $uid,
                        'sign' => 0,
                        'number' => '-' . $refund_point,
                        'from_type' => 2,//订单退还
                        'data_id' => $order_id,
                        'text' => '订单退款扣除会员获得的相应积分',
                        'create_time' => time(),
                        'website_id' => $website_id
                    );
                    $member_account_record->insert($data);
                    //更新对应会员账户
                    $data_member['point'] = $member_all_point - $refund_point;
                    if ($data_member['point'] < 0) {//如果会员积分不足以抵扣退款积分，则将其换成金钱
                        $data_member['point'] = 0;
                        $change_point = abs($data_member['point']);
                        //换算 积分不足兑换成金钱
                        $change_money = $change_point / $convert_rate;
                        //减掉不足的积分兑换成金钱减掉
                        $refund_real_money = $refund_real_money - $change_money;
                    }
                    //计算会员累计积分
                    $data_member['member_sum_point'] = $member_all_point - $refund_point;
                    $member_mdl->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                }
            }
            if ($deduction_point > 0) {
                $uid = reset($order_goods_info)->order->buyer_id;
                $website_id = reset($order_goods_info)->order->website_id;
                $order_id = reset($order_goods_info)->order->order_id;
                //判断是否真实已抵扣积分
                $deduction_point_info = $member_account_record->getInfo(['uid' => $uid, 'website_id' => $website_id, 'account_type' => 1, 'sign' => 0, 'number' => '-' . $deduction_point2, 'data_id' => $order_id]);
                if (!empty($deduction_point_info)) {
                    $member_mdl = new VslMemberAccountModel();
                    $all_info = $member_mdl->getInfo(['uid' => $uid, 'website_id' => $website_id], '*');
                    if (empty($all_info)) {
                        $member_all_point = 0;
                    } else {
                        $member_all_point = $all_info['point'];
                    }
                    $data = array(
                        'records_no' => getSerialNo(),
                        'account_type' => 1,
                        'uid' => $uid,
                        'sign' => 1,
                        'number' => $deduction_point,
                        'from_type' => 2,//订单退还
                        'data_id' => $order_id,
                        'text' => '订单退款退还积分抵扣的积分',
                        'create_time' => time(),
                        'website_id' => $website_id
                    );
                    $member_account_record->insert($data);
                    //更新对应会员账户
                    $data_member = [];
                    $data_member['point'] = $member_all_point + $deduction_point;
                    $member_mdl->save($data_member, ['uid' => $uid, 'website_id' => $website_id]);
                }
            }
            //退礼品
            if ($order_info['pay_gift_status'] == 1) {
                $paygift_server = new PayGift();
                //$paygift_server->returnPayGift($order_id);
            }
            $refund_trade_no = date("YmdHis") . rand(100000, 999999);
            $presell_id = reset($order_goods_info)->order->presell_id;
            $money_type = reset($order_goods_info)->order->money_type;
            // 支付方式也是退款方式
            if ($presell_id) {//预售退款
                if ($money_type == 2 && getAddons('presell', $website_id, 0)) {
                    $first_money = reset($order_goods_info)->order->pay_money;
                    $final_money = reset($order_goods_info)->order->final_money;
                    if (reset($order_goods_info)->order->shipping_status == 1) {//如果发货了，则运费不退了
                        $after_final_money = reset($order_goods_info)->order->final_money - reset($order_goods_info)->order->shipping_money;
                    } else {
                        $after_final_money = reset($order_goods_info)->order->final_money;
                    }
                    //算出各个付款方式付的金额
                    if ($first_money > 0 || $after_final_money > 0) {
                        $refund_first_money = round($refund_real_money * ($first_money / ($first_money + $after_final_money)), 2);
                    } else {
                        $refund_first_money = 0;
                    }
                    $refund_final_money = $refund_real_money - $refund_first_money;
                    $payment_type_presell = reset($order_goods_info)->order->payment_type_presell;
                    $payment_arr = ['payment_type' => $payment_type, 'payment_type_presell' => $payment_type_presell];
                    foreach ($payment_arr as $key => $payment) {
                        $refund_trade_no = date("YmdHis") . rand(100000, 999999);
                        if ($key == 'payment_type') {
                            $refund_real_money = $refund_first_money;
                            $pay_money = $first_money;
                        } else {
                            $pay_money = $final_money;
                            $refund_real_money = $refund_final_money;
                        }
                        if ($refund_real_money > 0) {
                            if ($payment == 5) {
                                // 退还会员的账户余额
                                $retval = $this->updateMemberAccount($order_id, reset($order_goods_info)->order->buyer_id, $refund_real_money);
                                if (!is_numeric($retval)) {
                                    Db::rollback();
                                    return ['code' => -1, 'message' => '余额退款失败'];
                                }
                            } else if ($payment == 1 || $payment == 2) {
                                // 在线原路退款（微信/支付宝）
                                $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money, $payment, $refund_trade_no, $pay_money, $key);
                                if ($refund['is_success'] != 1) {
                                    Db::rollback();
                                    return ['code' => -1, 'message' => $refund['msg']];
                                }
                            } else if ($payment == 3) {
                                // 在线原路退款（银行卡）
                                $refund = $this->onlineOriginalRoadRefund($order_id, $refund_real_money, $payment, $refund_trade_no, $pay_money, $key);
                                if ($refund['is_success'] != 1) {
                                    Db::rollback();
                                    return ['code' => -1, 'message' => $refund['msg']];
                                }
                            }
                        }
                    }
                    unset($payment);
                } else {
                    Db::rollback();
                    return ['code' => -1, 'message' => '退款失败'];
                }
            }
            foreach ($order_goods_info as $k => $v) {
                $order_goods = new OrderGoods();
                $order_goods->orderGoodsConfirmRefund($order_id, $v->order_goods_id, $v->refund_require_money, $v->real_money, $refund_trade_no, $v->order->payment_type, '');
                // 计算店铺的账户
                if ($v->shop_id > 0) {
                    $this->updateShopAccount_OrderRefund($v->order_goods_id);
                }
                // 计算平台的账户
                $this->updateAccountOrderRefund($v->order_goods_id);
            }
            unset($v);
            //判断订单是否是渠道商零售相关的,是的话就删除这条零售记录
            $cosr_mdl = new VslChannelOrderSkuRecordModel();
            $channel_retail_info = $cosr_mdl->getInfo(['order_id' => $order_id], "*");
            if ($channel_retail_info) {
                $cosr_mdl->where(['order_id' => $order_id])->delete();
            }
            Db::commit();
            //确认退款提醒
            $params['website_id'] = $order_info['website_id'] ?: $this->website_id;
            $params['shop_id'] = 0;
            $params['order_id'] = $order_id;
            $params['uid'] = reset($order_goods_info)->order->buyer_id;
            $params['order_goods_id'] = $order_goods_id;
            $params['notify_type'] = 'user';
            $params['template_code'] = 'return_success';
            $params['refund_money'] = $refund_real_money;
            runhook('Notify', 'confirmRefundBySms', $params);
            runhook('Notify', 'emailSend', $params);
            runhook('Notify', 'refundSuccessByTemplate', $params);
            runhook('MpMessage', 'refundAfterSaleByMpTemplate', $params);
            return ['code' => 1, 'message' => '退款成功'];
        } catch (\Exception $e) {
            Db::rollback();
        }
    }

    /**
     * /**
     * 在线原路退款（微信、支付宝）
     * @param 订单id $order_id
     * @param 退款金额 $refund_fee
     * @param 退款方式（1：微信，2：支付宝，10：线下） $refund_way
     * @param 退款交易号 $refund_trade_no
     * @param 订单总金额 $total_fee
     * @return number[]|string[]|\data\extend\weixin\成功时返回，其他抛异常|mixed[]
     */
    private function onlineOriginalRoadRefund($order_id, $refund_fee, $refund_way, $refund_trade_no, $total_fee, $is_presell_pay = 'payment_type')
    {
        // 1.根据订单id查询外部交易号
        $order_model = new VslOrderModel();
        $out_trade_no = $order_model->getInfo([
            'order_id' => $order_id
        ], "out_trade_no, out_trade_no_presell,website_id,joinpay");
        if ($is_presell_pay == 'payment_type') {
            $out_trade_no['out_trade_no'] = $out_trade_no['out_trade_no'];
        } elseif ($is_presell_pay == 'payment_type_presell') {
            $out_trade_no['out_trade_no'] = $out_trade_no['out_trade_no_presell'];
        }
        if ($refund_fee == 0) {
            return array(
                "is_success" => 0,
                'msg' => "退款金额不能为0"
            );
        }
        //多商户订单总金额获取会失败，变更支付单号获取该支付号所有支付金额
        $total_fee = $order_model->getSum(['out_trade_no' => $out_trade_no['out_trade_no']], 'pay_money');//分红 变更至退款后的金额

        //查询是否为聚合支付是则走聚合退款方式
        if ($out_trade_no['joinpay'] == 1) {

            if ($refund_way == 1 || $refund_way == 2) { //聚合微信
                $web_Config = new Config();
                $joinpay_info = $web_Config->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                $joinpay = new Joinpay();
                $retval = $joinpay->joinRefund($refund_trade_no, $out_trade_no['out_trade_no'], $refund_fee, $this->realm_ip, $joinpay_info['value']);
            } else {
                $retval = array(
                    "is_success" => 1,
                    'msg' => ""
                );
            }
            return $retval;
        }
        // 2.根据外部交易号查询trade_no（交易号）支付宝支付会返回一个交易号，微信传空
//        $vsl_order_payment_model = new VslOrderPaymentModel();
//        $trade_no = $vsl_order_payment_model->getInfo([
//            "out_trade_no" => $out_trade_no['out_trade_no']
//        ], 'trade_no');
        // 3.根据用户选择的退款方式，进行不同的原路退款操作
        if ($refund_way == 1) {
            // 微信退款
            $weixin_pay = new WeiXinPay();
            $retval = $weixin_pay->setWeiXinRefund($refund_trade_no, $out_trade_no['out_trade_no'], $refund_fee * 100, $total_fee * 100, $out_trade_no['website_id']);
        } elseif ($refund_way == 2) {
            // 支付宝退款
            $ali_pay = new UnifyPay();
            $retval = $ali_pay->aliPayNewRefund($refund_trade_no, $out_trade_no['out_trade_no'], $refund_fee);
            $result = json_decode(json_encode($retval), TRUE);
            if ($result['code'] == '10000' && $result['msg'] == 'Success') {
                $retval = array(
                    "is_success" => 1,
                    'msg' => ""
                );
            } else {
                $retval = array(
                    "is_success" => 0,
                    'msg' => $result['msg']
                );
            }

        } elseif ($refund_way == 3) {
            $tl = new tlPay();
            $order_payment = new VslOrderPaymentModel();
            $trade_no = $order_payment->getInfo(['out_trade_no' => $out_trade_no['out_trade_no']], 'trade_no')['trade_no'];
            $retval = $tl->tlRefund($out_trade_no['out_trade_no'], $refund_fee * 100, $out_trade_no['website_id'], $trade_no);
            if ($retval['retcode'] == 'SUCCESS' && $retval['trxstatus'] == '0000') {
                $retval = array(
                    "is_success" => 1,
                    'msg' => ""
                );
            } else {
                $retval = array(
                    "is_success" => 0,
                    'msg' => $retval['errmsg']
                );
            }
        } elseif ($refund_way == 20) {
            // GlobePay退款
            $globepay = new GlobePay();
            $retval = $globepay->setGpRefund($refund_trade_no, $out_trade_no['out_trade_no'], $refund_fee, $total_fee, $out_trade_no['website_id']);
            if ($retval['result_code'] == 'SUCCESS') {
                $retval = array(
                    "is_success" => 1,
                    'msg' => ""
                );
            } else {
                $retval = array(
                    "is_success" => 0,
                    'msg' => $retval['return_msg']
                );
            }
        } else {

            // 线下操作，直接通过
            $retval = array(
                "is_success" => 1,
                'msg' => ""
            );
        }

        return $retval;
    }

    /**
     * 在线原路退款（账户余额）
     *
     * @param unknown $goods_sku_list
     */
    public function updateMemberAccount($order_id, $uid, $refund_real_money)
    {
        $member_account_record = new VslMemberAccountRecordsModel();
        $member_account_record->startTrans();
        try {
            if ($refund_real_money == 0) {
                return "退款金额不能为0";
            }
            $member = new VslMemberAccountModel();
            $account = $member->getInfo(['uid' => $uid], '*');
            if ($account) {
                $balance = $account['balance'] + $refund_real_money;
                $member->save(['balance' => $balance], ['uid' => $uid]);
                //添加会员账户流水
                $data = array(
                    'records_no' => 'Br' . getSerialNo(),
                    'account_type' => 2,
                    'uid' => $uid,
                    'sign' => 0,
                    'number' => $refund_real_money,
                    'point' => $account['point'],
                    'balance' => $balance,
                    'from_type' => 2,
                    'data_id' => $order_id,
                    'text' => '订单余额退款，会员可用余额增加',
                    'create_time' => time(),
                    'website_id' => $account['website_id']
                );
                $res = $member_account_record->save($data);
            }

            $member_account_record->commit();
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $member_account_record->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 在线原路退款（美丽分）
     *
     * @param unknown $goods_sku_list
     */
    public function updateMemberBeautifulPoint($order_id, $uid, $refund_real_money)
    {
        $member_account_record = new VslMemberAccountRecordsModel();
        $member_account_record->startTrans();
        try {
            if ($refund_real_money == 0) {
                return "退款金额不能为0";
            }
            $member = new VslMemberAccountModel();
            $account = $member->getInfo(['uid' => $uid], '*');

            if ($account) {
                $beautiful_point = $account['beautiful_point'] + $refund_real_money;
                $res_member = $member->save(['beautiful_point' => $beautiful_point], ['uid' => $uid]);

                //添加会员账户流水
                $data = array(
                    'records_no' => 'Br' . getSerialNo(),
                    'account_type' => 3,
                    'uid' => $uid,
                    'sign' => 1,
                    'number' => $refund_real_money,
                    'point' => $beautiful_point,
                    'balance' => $account['balance'],
                    'from_type' => 2,
                    'data_id' => $order_id,
                    'text' => '订单退款，会员美丽分增加',
                    'create_time' => time(),
                    'website_id' => $account['website_id']
                );
                $res = $member_account_record->save($data);
            }

            $member_account_record->commit();
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $member_account_record->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取对应sku列表价格
     *
     * @param unknown $goods_sku_list
     */
    public function getGoodsSkuListPrice($goods_sku_list)
    {
        $goods_preference = new GoodsPreference();
        $money = $goods_preference->getGoodsSkuListPrice($goods_sku_list);
        return $money;
    }

    /**
     *
     * 确认收货
     * @see \data\api\IOrder::OrderTakeDelivery()
     */
    public function OrderTakeDelivery($order_id, $website_id = 0)
    {
        $this->website_id = $this->website_id ? $this->website_id : $website_id;
        $order = new OrderBusiness();
        $res = $order->OrderTakeDelivery($order_id);
        //发送确认收货消息
        runhook('Notify', 'orderCompleteBySms', ['order_id' => $order_id]);
        runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $this->website_id, 'order_id' => $order_id, 'notify_type' => 'user', 'template_code' => 'confirm_order']);
        return $res;
    }

    /**
     * 删除购物车的商品
     * @param array $condition
     */
    public function deleteCartNew(array $condition)
    {
        $cart_model = new VslCartModel();
        $cart_model::destroy($condition);
        unset($_SESSION['user_cart'], $_SESSION['order_tag']);
    }

    /**
     * 删除门店购物车的商品
     * @param array $condition
     */
    public function deleteStoreCartNew(array $condition)
    {
        $cart_model = new VslStoreCartModel();
        $cart_model::destroy($condition);

        unset($_SESSION['user_cart'], $_SESSION['order_tag']);
    }

    /**
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderCount()
     */
    public function getOrderCount($condition)
    {
        $order = new VslOrderModel();
        $count = $order->where($condition)->count();
        return $count;
    }

    public function getMemberOrderCount($condition)
    {
        $order = new VslOrderModel();
        $list = $order->where($condition)->group('buyer_id')->select();
        return count($list);
    }

    public function getOrderMoneySum($condition, $filed)
    {
        $order_model = new VslOrderModel();
        $money_sum = $order_model->where($condition)->sum($filed);
        return $money_sum;
    }

    /**
     * 获取具体配送状态信息
     *
     * @param unknown $shipping_status_id
     * @return Ambigous <NULL, multitype:string >
     */
    public static function getShippingInfo($shipping_status_id)
    {
        $shipping_status = OrderStatus::getShippingStatus();
        $info = null;
        foreach ($shipping_status as $shipping_info) {
            if ($shipping_status_id == $shipping_info['shipping_status']) {
                $info = $shipping_info;
                break;
            }
        }
        unset($shipping_info);
        return $info;
    }

    /**
     * 获取具体支付状态信息
     *
     * @param unknown $pay_status_id
     * @return multitype:multitype:string |string
     */
    public static function getPayStatusInfo($pay_status_id)
    {
        $pay_status = OrderStatus::getPayStatus();
        $info = null;
        foreach ($pay_status as $pay_info) {
            if ($pay_status_id == $pay_info['pay_status']) {
                $info = $pay_info;
                break;
            }
        }
        unset($pay_info);
        return $info;
    }

    /**
     * 获取订单各状态数量
     */
    public static function getOrderStatusNum($condition = '')
    {
        $order = new VslOrderModel();
        $orderStatusNum['all'] = $order->where($condition)->count(); // 全部
        $condition['order_status'] = 0; // 待付款
        $orderStatusNum['wait_pay'] = $order->where($condition)->count();
        $condition['order_status'] = 1; // 待发货
        $orderStatusNum['wait_delivery'] = $order->where($condition)->count();
        $condition['order_status'] = 2; // 待收货
        $orderStatusNum['wait_recieved'] = $order->where($condition)->count();
        $condition['order_status'] = 3; // 已收货
        $orderStatusNum['recieved'] = $order->where($condition)->count();
        $condition['order_status'] = 4; // 交易成功
        $orderStatusNum['success'] = $order->where($condition)->count();
        $condition['order_status'] = 5; // 已关闭
        $orderStatusNum['closed'] = $order->where($condition)->count();
        $condition['order_status'] = -1; // 退款中
        $orderStatusNum['refunding'] = $order->where($condition)->count();
        $condition['order_status'] = -2; // 已退款
        $orderStatusNum['refunded'] = $order->where($condition)->count();
        $condition['order_status'] = array(
            'in',
            '3,4'
        ); // 已收货
        $condition['is_evaluate'] = 0; // 未评价
        $orderStatusNum['wait_evaluate'] = $order->where($condition)->count(); // 待评价
        $condition['order_status'] = -3; // 退货
        $orderStatusNum['refunded_goods'] = $order->where($condition)->count();
        return $orderStatusNum;
    }

    /**
     * 店铺评价-添加
     */
    public function addShopEvaluate($data)
    {
        $goodsEvaluate = new VslShopEvaluateModel();
        $res = $goodsEvaluate->save($data);

        $shop_model = new VslShopModel();
        $shop_service = new Shop();
        $shop_evaluate = $shop_service->getShopEvaluate($data['shop_id']);
        $shop_data['shop_desccredit'] = number_format(($shop_evaluate['shop_desc'] * $shop_evaluate['count'] + $data['shop_desc']) / ($shop_evaluate['count'] + 1), 1);
        $shop_data['shop_servicecredit'] = number_format(($shop_evaluate['shop_service'] * $shop_evaluate['count'] + $data['shop_service']) / ($shop_evaluate['count'] + 1), 1);
        $shop_data['shop_deliverycredit'] = number_format(($shop_evaluate['shop_stic'] * $shop_evaluate['count'] + $data['shop_stic']) / ($shop_evaluate['count'] + 1), 1);
        $shop_data['comprehensive'] = number_format(($shop_data['shop_desccredit'] + $shop_data['shop_servicecredit'] + $shop_data['shop_deliverycredit']) / 3, 1);
        $shop_model->save($shop_data, ['shop_id' => $data['shop_id'], 'website_id' => $data['website_id']]);

        return $res;
    }

    /**
     * 商品评价-添加
     *
     * @param unknown $dataList
     *            评价内容的 数组
     * @return Ambigous <multitype:, \think\false>
     */
    public function addGoodsEvaluate($dataArr, $order_id)
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        $goodsSer = new GoodsService();
        $res = $goodsEvaluate->saveAll($dataArr);
        $result = false;
        if ($res != false) {
            // 修改订单评价状态
            $order = new VslOrderModel();
            $data = array(
                'is_evaluate' => 1
            );
            $result = $order->save($data, [
                'order_id' => $order_id
            ]);
        }
        foreach ($dataArr as $item) {
            $goodsSer->updateGoodsIncOrDec([
                'goods_id' => $item['goods_id']
            ], 'evaluates', 1, $item['goods_id']);
        }
        unset($item);
        hook("goodsEvaluateSuccess", [
            'order_id' => $order_id,
            'data' => $dataArr
        ]);

        return $result;
    }

    /**
     * 商品评价-追评
     *
     * @param unknown $again_content
     *            追评内容
     * @param unknown $againImageList
     *            传入追评图片的 数组
     * @param unknown $ordergoodsid
     *            订单项ID
     * @return Ambigous <number, \think\false>
     */
    public function addGoodsEvaluateAgain($again_content, $againImageList, $order_goods_id)
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        $data = array(
            'again_content' => $again_content,
            'again_addtime' => time(),
            'again_image' => $againImageList
        );
        $res = $goodsEvaluate->save($data, [
            'order_goods_id' => $order_goods_id
        ]);
        hook("goodsEvaluateAgainSuccess", [
            'again_content' => $again_content,
            'againImageList' => $againImageList,
            'order_goods_id' => $order_goods_id
        ]);
        return $res;
    }

    /**
     * 评价信息 分页
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return number
     */
    public function getOrderEvaluateDataList($page_index, $page_size, $condition, $order)
    {
        $goodsEvaluate = new VslGoodsEvaluateModel();
        $list = $goodsEvaluate->pageQuery($page_index, $page_size, $condition, $order, "*");
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['spec'] = [];
            $list['data'][$k]['del_status'] = 0;
            $order_item = new VslOrderGoodsModel();
            $order_item_list = $order_item->getInfo(['order_goods_id' => $v['order_goods_id']], 'sku_id');
            $goods_del = new VslGoodsDeletedModel();
            $del_status = $goods_del->getInfo(['goods_id' => $v['goods_id']]);
            if ($del_status) {
                $list['data'][$k]['del_status'] = 1;
            }
            // 查询商品sku表开始
            $goods_sku = new VslGoodsSkuModel();
            $goods_sku_info = $goods_sku->getInfo([
                'sku_id' => $order_item_list['sku_id']
            ], 'code,attr_value_items');
            $goods_spec_value = new VslGoodsSpecValueModel();
            $sku_spec_info = explode(';', $goods_sku_info['attr_value_items']);
            foreach ($sku_spec_info as $k_spec => $v_spec) {
                $spec_value_id = explode(':', $v_spec)[1];
                $sku_spec_value_info = $goods_spec_value::get($spec_value_id, ['goods_spec']);
                $list['data'][$k]['spec'][$k_spec]['spec_value_name'] = $sku_spec_value_info['spec_value_name'];
                $list['data'][$k]['spec'][$k_spec]['spec_name'] = $sku_spec_value_info['goods_spec']['spec_name'];
            }
        }
        unset($v);
        return $list;
    }

    /**
     * 修改订单数据
     *
     * @param unknown $order_id
     * @param unknown $data
     */
    public function modifyOrderInfo($data, $order_id)
    {
        $order = new VslOrderModel();
        return $order->save($data, [
            'order_id' => $order_id
        ]);
    }


    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopOrderStatics()
     */
    public function getShopOrderStatics($shop_id, $start_time, $end_time)
    {
        $order_account = new OrderAccount();
        $order_sum = $order_account->getShopOrderSum($shop_id, $start_time, $end_time);
        $order_refund_sum = $order_account->getShopOrderSumRefund($shop_id, $start_time, $end_time);
        $order_sum_account = $order_sum - $order_refund_sum;
        $array = array(
            'order_sum' => $order_sum,
            'order_refund_sum' => $order_refund_sum,
            'order_account' => $order_sum_account
        );
        return $array;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopOrderAccountDetail()
     */
    public function getShopOrderAccountDetail($shop_id)
    {
        // 获取总销售统计
        $account_all = $this->getShopOrderStatics($shop_id, '2015-1-1', '3050-1-1');
        // 获取今日销售统计
        $date_day_start = date("Y-m-d", time());
        $date_day_end = date("Y-m-d H:i:s", time());
        $account_day = $this->getShopOrderStatics($shop_id, $date_day_start, $date_day_end);
        // 获取周销售统计（7天）
        $date_week_start = date('Y-m-d', strtotime('-7 days'));
        $date_week_end = $date_day_end;
        $account_week = $this->getShopOrderStatics($shop_id, $date_week_start, $date_week_end);
        // 获取月销售统计(30天)
        $date_month_start = date('Y-m-d', strtotime('-30 days'));
        $date_month_end = $date_day_end;
        $account_month = $this->getShopOrderStatics($shop_id, $date_month_start, $date_month_end);
        $array = array(
            'day' => $account_day,
            'week' => $account_week,
            'month' => $account_month,
            'all' => $account_all
        );
        return $array;
    }

    /*
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopAccountCountInfo()
     */
    public function getShopAccountCountInfo($shop_id)
    {
        // 本月第一天
        $date_month_start = getTimeTurnTimeStamp(date('Y-m-d', strtotime('-30 days')));
        $date_month_end = getTimeTurnTimeStamp(date("Y-m-d H:i:s", time()));
        // 下单金额
        $order_account = new OrderAccount();
        $condition["create_time"] = [
            [
                ">=",
                $date_month_start
            ],
            [
                "<=",
                $date_month_end
            ]
        ];
        $condition['order_status'] = array(
            'NEQ',
            0
        );
        $condition['order_status'] = array(
            'NEQ',
            5
        );
        if ($shop_id >= 0) {
            $condition['shop_id'] = $shop_id;
        }

        $order_money = $order_account->getShopSaleSum($condition);
        // var_dump($order_money);
        // 下单会员
        $order_user_num = $order_account->getShopSaleUserSum($condition);
        // 下单量
        $order_num = $order_account->getShopSaleNumSum($condition);
        // 下单商品数
        $order_goods_num = $order_account->getShopSaleGoodsNumSum($condition);
        // 平均客单价
        if ($order_user_num > 0) {
            $user_money_average = $order_money / $order_user_num;
        } else {
            $user_money_average = 0;
        }
        // 平均价格
        if ($order_goods_num > 0) {
            $goods_money_average = $order_money / $order_goods_num;
        } else {
            $goods_money_average = 0;
        }
        $array = array(
            "order_money" => sprintf('%.2f', $order_money),
            "order_user_num" => $order_user_num,
            "order_num" => $order_num,
            "order_goods_num" => $order_goods_num,
            "user_money_average" => sprintf('%.2f', $user_money_average),
            "goods_money_average" => sprintf('%.2f', $goods_money_average)
        );
        return $array;
    }

    /*
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getShopGoodsSalesList()
     */
    public function getShopGoodsSalesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        // $goods_calculate = new GoodsCalculate();
        // $goods_sales_list = $goods_calculate->getGoodsSalesInfoList($page_index, $page_size , $condition , $order );
        // return $goods_sales_list;
        $goods_model = new VslGoodsModel();
        $start_date = $condition["start_date"];
        $end_date = $condition["end_date"];
        unset($condition['start_date']);
        unset($condition['end_date']);
        $tmp_array = $condition;
        if (!empty($condition["order_status"])) {
            $order_condition["order_status"] = $condition["order_status"];
            unset($tmp_array["order_status"]);
        }
        $goods_list = $goods_model->pageQuery($page_index, $page_size, $tmp_array, $order, '*');
        // 条件

        if ($start_date != "" && $end_date != "") {
            $order_condition["create_time"] = [
                [
                    ">",
                    getTimeTurnTimeStamp($start_date)
                ],
                [
                    "<",
                    getTimeTurnTimeStamp($end_date)
                ]
            ];
        } else {
            if ($start_date != "" && $end_date == "") {
                $order_condition["create_time"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ]
                ];
            } else {
                if ($start_date == "" && $end_date != "") {
                    $order_condition["create_time"] = [
                        [
                            "<",
                            getTimeTurnTimeStamp($end_date)
                        ]
                    ];
                }
            }
        }


        $order_condition["shop_id"] = $condition["shop_id"];
        $goods_calculate = new GoodsCalculate();
        // 得到条件内的订单项
        $order_goods_list = $goods_calculate->getOrderGoodsSelect($order_condition);
        // 遍历商品
        foreach ($goods_list["data"] as $k => $v) {
            $data = array();
            $goods_sales_num = $goods_calculate->getGoodsSalesNum($order_goods_list, $v["goods_id"]);
            $goods_sales_money = $goods_calculate->getGoodsSalesMoney($order_goods_list, $v["goods_id"]);
            $data["sales_num"] = $goods_sales_num;
            $data["sales_money"] = $goods_sales_money;
            $goods_list["data"][$k]["sales_info"] = $data;
        }
        unset($v);
        return $goods_list;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IOrder::getShopGoodsSalesAll()
     */
    public function getShopGoodsSalesQuery($shop_id, $start_date, $end_date, $condition)
    {
        // TODO Auto-generated method stub
        // 商品
        $goods_model = new VslGoodsModel();
        $goods_list = $goods_model->getQuery($condition, "*", '');
        // 订单项
        $condition['create_time'] = [
            'between',
            [
                $start_date,
                $end_date
            ]
        ];
        $order_condition["create_time"] = [
            [
                ">=",
                $start_date
            ],
            [
                "<=",
                $end_date
            ]
        ];
        $order_condition['order_status'] = array(
            'NEQ',
            0
        );
        $order_condition['order_status'] = array(
            'NEQ',
            5
        );
        if ($shop_id != '') {
            $order_condition["shop_id"] = $shop_id;
        }
        $goods_calculate = new GoodsCalculate();
        $order_goods_list = $goods_calculate->getOrderGoodsSelect($order_condition);
        // 遍历商品
        foreach ($goods_list as $k => $v) {
            $data = array();
            $goods_sales_num = $goods_calculate->getGoodsSalesNum($order_goods_list, $v["goods_id"]);
            $goods_sales_money = $goods_calculate->getGoodsSalesMoney($order_goods_list, $v["goods_id"]);
            $goods_list[$k]["sales_num"] = $goods_sales_num;
            $goods_list[$k]["sales_money"] = $goods_sales_money;
        }
        unset($v);
        return $goods_list;
    }

    /**
     * 查询一段时间内的店铺下单金额
     *
     * @param unknown $shop_id
     * @param unknown $start_date
     * @param unknown $end_date
     * @return Ambigous <\data\service\Order\unknown, number, unknown>
     */
    public function getShopSaleSum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num = $order_account->getShopSaleSum($condition);
        return $sales_num;
    }


    /**
     * 查询一段时间内的店铺下单量
     *
     * @param unknown $shop_id
     * @param unknown $start_date
     * @param unknown $end_date
     * @return unknown
     */
    public function getShopSaleNumSum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num = $order_account->getShopSaleNumSum($condition);
        return $sales_num;
    }

    /**
     * 查询一段时间内的店铺下单金额
     *
     * @param unknown $shop_id
     * @param unknown $start_date
     * @param unknown $end_date
     * @return Ambigous <\data\service\Order\unknown, number, unknown>
     */
    public function getShopSaleMemberNumSum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num = $order_account->getShopSaleUserSum($condition);
        return $sales_num;
    }

    /**
     * ***********************************************店铺账户--Start******************************************************
     */
    /**
     * 订单支付的时候 调整店铺账户
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    private function dealShopAccount_OrderPay($order_out_trade_no = "", $order_id = 0)
    {
        $order_model = new VslOrderModel();
        if ($order_out_trade_no != "" && $order_id == 0) {
            $condition = ["out_trade_no" => $order_out_trade_no];
            $order_list = $order_model->Query($condition, "order_id");
            if (!$order_list) {
                $condition2 = ["out_trade_no_presell" => $order_out_trade_no];
                $order_list = $order_model->Query($condition2, "order_id");
            }
            foreach ($order_list as $k => $v) {
                $shop_id = $order_model->getInfo(['order_id' => $v], 'shop_id')['shop_id'];
                if ($shop_id > 0) {
                    $this->updateShopAccount_OrderPay($v);
                }
            }
            unset($v);
        } else if ($order_out_trade_no == "" && $order_id != 0) {
            $shop_id = $order_model->getInfo(['order_id' => $order_id], 'shop_id')['shop_id'];
            if ($shop_id > 0) {
                $this->updateShopAccount_OrderPay($order_id);
            }
        }
    }

    /**
     * 订单完成的时候调整账户金额
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    private function dealShopAccount_OrderComplete($order_out_trade_no = "", $order_id = 0)
    {
        $order_model = new VslOrderModel();
        if ($order_out_trade_no != "" && $order_id == 0) {
            $condition = " out_trade_no=" . $order_out_trade_no;
            $order_list = $order_model->getQuery($condition, "order_id", "");
            foreach ($order_list as $k => $v) {
                $shop_id = $order_model->getInfo(['order_id' => $v['order_id']], 'shop_id')['shop_id'];
                if ($shop_id > 0) {
                    $this->updateShopAccount_OrderComplete($v["order_id"]);
                }
            }
            unset($v);
        } else {
            if ($order_out_trade_no == "" && $order_id != 0) {
                $shop_id = $order_model->getInfo(['order_id' => $order_id], 'shop_id')['shop_id'];
                if ($shop_id > 0) {
                    $this->updateShopAccount_OrderComplete($order_id);
                }

            }
        }

    }

    /**
     * 该方法处理定时任务完成后 继续进行店铺操作的中继
     * @param unknown $order_id
     */
    public function task_updateShopAccount_OrderPay($order_id, $shop_id = 0)
    {
        if (empty($order_id) || empty($shop_id)) {
            // return;
        }
        //先查询一下改订单是否已经有记录了，有就不需要再继续
        // $shop_account_records = new VslShopAccountRecordsModel();
        // $account_info = $shop_account_records->getInfo(['type_alis_id'=>$order_id,'account_type'=>1,'shop_id'=>$shop_id]);
        // if($account_info){
        //     return;
        // }

        $this->updateShopAccount_OrderPay($order_id, 1);
    }

    /**
     * 订单支付
     *
     * @param unknown $order_id
     *  times 1 定时 0直接执行---先不执行等定时执行  -- 预售的会进来两次 -- 尾款不走定时
     */
    private function updateShopAccount_OrderPay($order_id, $times = 0)
    {
        // if($times==0){return;}
        debugFile($order_id, '==>updateShopAccount_OrderPay——1<==', '699');
        debugFile($times, '==>updateShopAccount_OrderPay——2<==', '699');
        $order_model = new VslOrderModel();
        //$order_obj = $order_model->getInfo(['order_id' => $order_id], '*');
        $order_obj = $order_model::get(['order_id' => $order_id], ['order_goods']);
        if ($times == 0 && $order_obj['money_type'] != 2) {
            return; //非定时执行的 先不予执行 -- 尾款不走定时
        }
        //先查询分销设置，是否开启店铺分销 是则有该店铺负责佣金 否则还是由平台负责
        $distribution_admin_status = 0;
        $isdistributionStatus = getAddons('distribution', $order_obj['website_id']);

        if ($isdistributionStatus) {
            //获取分销基础设置，是否开启店铺分销
            $config = new distributorService();
            $distributionStatusAdmin = $config->getDistributionSite($order_obj['website_id']);
            $distribution_admin_status = $distributionStatusAdmin['distribution_admin_status'];
        }
        if ($distribution_admin_status == 1) {
            //该处先进行检验该订单分销分红任务是否已处理完成 如未计算完成则暂不写入
            //此处再次检测一下，防止重复了
            $shopOrderCheckModel = new VslShopOrderCheckModel();
            $check_info = $shopOrderCheckModel->getInfo(['order_id' => $order_id], '*');
            //如果没有开启店铺分销则跳过
            if ($check_info) {
                //预售订单需要进来两次 所以标记一下
                if ($check_info['status'] == 0) {
                    if ($order_obj['order_type'] == 7 && $order_obj['money_type'] == 1) {
                        // -- 预售首付定金  预售的首付状态已发生变更 且已有一次写入记录，这时应该拒绝二次写入 ！
                        return;
                    } else if ($order_obj['order_type'] == 7 && $order_obj['money_type'] == 2) {
                        // -- 预售尾款
                        if ($times == 1) {
                            return;
                        }
                    } else {
                        return;
                    }
                } else {
                    $order_calculate_model = new VslOrderCalculateModel();
                    $calculate_info = $order_calculate_model->getInfo(['order_id' => $order_id, 'had_cal' => 0], 'id');
                    if ($calculate_info) {
                        debugLog($calculate_info, $order_id . '==>店铺佣金账户订单终止2<==');
                        return;
                    } else {
                        $shopOrderCheckModel->save(['status' => 0, 'update_time' => time()], ['order_id' => $order_id]);
                    }

                }
            } else {
                $order_calculate_model = new VslOrderCalculateModel();
                $calculate_info = $order_calculate_model->getInfo(['order_id' => $order_id, 'had_cal' => 0], 'id');
                if ($calculate_info) {
                    //此处标记一下，防止一些意外导致的异常无法追查
                    $shopOrderCheckModel = new VslShopOrderCheckModel();
                    $shopOrderCheckModel->save(['order_id' => $order_id, 'status' => 1, 'time' => time()]);
                    debugLog($calculate_info, $order_id . '==>店铺佣金账户订单终止3<==');
                    return;
                } else {
                    $shopOrderCheckModel->save(['order_id' => $order_id, 'status' => 0, 'time' => time(), 'update_time' => time()]);
                }
            }
        }

        $shop_account = new ShopAccount();
        $order = new OrderBusiness();
        $this->website_id = $this->website_id ? $this->website_id : $order_obj['website_id'];
        $this->instance_id = $this->instance_id ? $this->instance_id : $order_obj['shop_id'];
        // $shipping_fee_all = array_column(objToArr($order_obj['order_goods']),'shipping_fee'); //这里商品运费包含供应商或者非供应商 所以不能获取所有运费 获取供应商部分商品的运费处理
        $shipping_fee_all = $this->calculSupplierShippingMoney($order_id, 0);
        // $order_model = new VslOrderModel();
        // $order_model->startTrans();
        debugFile($order_obj, '==>updateShopAccount_OrderPay——3<==', '699');
        if ($times == 0 && $order_obj['money_type'] != 2) {
            return; //非定时执行的 先不予执行 -- 尾款不走定时
        }
        Db::startTrans();
        //edit for 2020/10/11 该事务开启后会偶尔造成没有写入的情况 暂时去除事务
        try {
            $pay_money_all = 0;
            if ($order_obj['presell_id'] != 0 && $order_obj['order_type'] == 7 && $order_obj['money_type'] == 1) {
                $presell_text = '预售定金';
            } else if ($order_obj['presell_id'] != 0 && $order_obj['order_type'] == 7 && $order_obj['money_type'] == 2) {
                $presell_text = '预售尾款';
            } else {
                $presell_text = '';
            }
            if ($order_obj['presell_id'] != 0 && getAddons('presell', $this->website_id, $this->instance_id) && $order_obj['money_type'] == 2) {
                //由于全额抵扣 尾款为0
                $presell_mdl = new VslPresellModel();
                $presell_info = $presell_mdl->getInfo(['id' => $order_obj['presell_id']], '*');
                $firstmoney = $presell_info['firstmoney']; //定金
                $allmoney = $presell_info['allmoney'];
                $secondmoney = $allmoney - $firstmoney; //尾款
                $num = $order_obj['pay_money'] / $firstmoney;
                $pay_money_all = $pay_money = $secondmoney * $num + $order_obj['shipping_money'];
                if ($pay_money_all == 0 && $order_obj['platform_promotion_money'] > 0 && $order_obj['pay_money'] == 0) {
                    $pay_money_all = $pay_money = $order_obj['platform_promotion_money'];
                }
                //$pay_money_all = $pay_money = $order_obj['final_money'] + $order_obj['shipping_money'];
                $pay_money_all = $pay_money = $order_obj['final_money'];

                //查看是
            } else {
                // 订单的实际付款金额
                $pay_money = $order->getOrderRealPayMoney($order_id);
                $pay_money_all = $order->getOrderRealPayShopMoney($order_id);
            }

            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            //变更 支付成功后即写入扣除分销分红后的金额 edit for 2020/07/04
            //查询该笔订单是否启用分销 有则扣除相应的(佣金 积分换算成金额) 再写入店铺收益
            //获取订单佣金信息 获取平台汇率设置
            $order_commission = new VslOrderDistributorCommissionModel();
            $orders = $order_commission->Query(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], '*');
            $order_detail = array();
            if ($orders) {
                foreach ($orders as $key1 => $value) {
                    if ($value['commissionA_id']) {
                        $order_detail['commissionA'] += $value['commissionA'];
                        $order_detail['pointA'] += $value['pointA'];
                    }
                    if ($value['commissionB_id']) {
                        $order_detail['commissionB'] += $value['commissionB'];
                        $order_detail['pointB'] += $value['pointB'];
                    }
                    if ($value['commissionC_id']) {
                        $order_detail['commissionC'] += $value['commissionC'];
                        $order_detail['pointC'] += $value['pointC'];
                    }
                }
                unset($value);
                $order_detail['commission'] = $order_detail['commissionA'] + $order_detail['commissionB'] + $order_detail['commissionC'];
                $order_detail['point'] = $order_detail['pointA'] + $order_detail['pointB'] + $order_detail['pointC'];
            }


            $commission_money = 0;
            if ($order_detail && floatval($order_detail['commission']) > 0 || floatval($order_detail['point']) > 0) {
                //返积分
                $config = new Config();
                $config_info = $config->getShopConfig(0, $order_obj['website_id']);
                $convert_rate = $config_info['convert_rate'] ? $config_info['convert_rate'] : 1; //汇率后台不设置 默认比例为1:1
                $commission_point_money = floor($order_detail['point'] / $convert_rate);
                $commission_money = $commission_point_money + $order_detail['commission'];

            }

            // 查询该笔订单是否启用店铺分红 有则扣除相应的(分红金额) 再写入店铺收益
            $orderBonusModel = new VslOrderBonusLogModel();
            $area_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], 'area_bonus');//区域分红
            $global_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], 'global_bonus');//全球分红
            $team_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], 'team_bonus');//团队分红
            $strs = '';
            if ($commission_money) {
                $strs .= '分销返佣' . $commission_money . '元，';
            }
            if ($area_bonus) {
                $strs .= '区域分红' . $area_bonus . '元，';
            }
            if ($global_bonus) {
                $strs .= '全球分红' . $global_bonus . '元，';
            }
            if ($team_bonus) {
                $strs .= '团队分红' . $team_bonus . '元，';
            }
            debugFile(1, '==>updateShopAccount_OrderPay——4<==', '699');
            //判断是否是主播订单扣除主播分成
            $anchor_earnings = 0;
            if (getAddons('liveshopping', $order_obj['website_id'])) {
                $order_anchor_earnings = new OrderAnchorEarningsModel();
                $anchor_earnings = $order_anchor_earnings->getSum(['order_id' => $order_id], 'anchor_earnings');
                if ($anchor_earnings != 0) {
                    $strs .= "主播分成收益" . $anchor_earnings . "元，";
                }
            }
            debugFile(1, '==>updateShopAccount_OrderPay——5<==', '699');
            // 修改店铺的入账金额
            $pay_money_all = $pay_money_all - $commission_money - $area_bonus - $global_bonus - $team_bonus - $anchor_earnings;
            //add 会员卡抵扣信息 2020/08/18
            if ($order_obj['membercard_deduction_money']) {
                if ($order_obj['presell_id'] != 0 && $order_obj['order_type'] == 7) {
                    if ($order_obj['pay_money'] > 0) {
                        if ($order_obj['money_type'] == 1) {
                            $pay_money_all = bcsub($pay_money_all, $order_obj['membercard_deduction_money'], 2);
                            $strs .= '';
                        } else {
                            $pay_money_all = bcadd($pay_money_all, $order_obj['membercard_deduction_money'], 2);
                            $strs .= $order_obj['membercard_deduction_money'] > 0 ? '会员卡抵扣' . $order_obj['membercard_deduction_money'] . '元，' : '';
                        }
                    } else {
                        $pay_money_all = bcadd($pay_money_all, $order_obj['membercard_deduction_money'], 2);
                        $strs .= $order_obj['membercard_deduction_money'] > 0 ? '会员卡抵扣' . $order_obj['membercard_deduction_money'] . '元，' : '';
                    }
                } else {
                    //$pay_money_all = bcadd($pay_money_all,$order_obj['membercard_deduction_money'],2);
                    $strs .= $order_obj['membercard_deduction_money'] > 0 ? '会员卡抵扣' . $order_obj['membercard_deduction_money'] . '元，' : '';
                }
            }
            if ($order_obj['invoice_type']) {
                if ($order_obj['presell_id'] != 0 && $order_obj['order_type'] == 7 && $order_obj['money_type'] == 1) {/*定金不显示*/
                    $strs .= '';
                } else {
                    $strs .= '税费' . $order_obj['invoice_tax'] . '元，';
                }
            }
            if ($order_obj['order_goods']) {
                $receive_goods_code_arr = array_column(objToArr($order_obj['order_goods']), 'receive_goods_code_deduct');
                if (count($receive_goods_code_arr)) {

                    if ($order_obj['presell_id'] != 0 && $order_obj['order_type'] == 7) {
                        if ($order_obj['pay_money'] > 0) {
                            if ($order_obj['money_type'] == 2) {/*已付尾款*/
                                $receive_goods_code_deduct = array_sum(array_column(objToArr($order_obj['order_goods']), 'receive_goods_code_deduct'));
                                if ($receive_goods_code_deduct) {
                                    $strs .= $receive_goods_code_deduct > 0 ? '领货码抵扣' . $receive_goods_code_deduct . '元，' : '';
                                }
                            }
                        } else {
                            if ($order_obj['money_type'] == 1) {
                                $receive_goods_code_deduct = array_sum(array_column(objToArr($order_obj['order_goods']), 'receive_goods_code_deduct'));
                                if ($receive_goods_code_deduct) {
                                    $strs .= $receive_goods_code_deduct > 0 ? '领货码抵扣' . $receive_goods_code_deduct . '元，' : '';
                                }
                            }
                        }
                    } else {
                        $receive_goods_code_deduct = array_sum(array_column(objToArr($order_obj['order_goods']), 'receive_goods_code_deduct'));
                        if ($receive_goods_code_deduct) {
                            $strs .= $receive_goods_code_deduct > 0 ? '领货码抵扣' . $receive_goods_code_deduct . '元，' : '';
                        }
                    }

                }
            }
            //判断这笔订单中是否有供应商商品，如果有，就要扣除结算给供应商的钱
            $supplier_money_info = $this->calculSupplierMoney($order_id, 1);//支付
            if ($supplier_money_info['supplier_money']) {
                if ($order_obj['presell_id'] != 0 && $order_obj['order_type'] == 7 && $order_obj['money_type'] == 1) {/*定金不显示*/
                    $strs .= '';
                } else {
//                    $pay_money_all = $pay_money_all - $supplier_money_info['supplier_money'] - $shipping_fee_all;
                    $pay_money_all = $pay_money_all - $supplier_money_info['supplier_money'];
//                    $pay_money_all_out_shipping = $pay_money_all-$shipping_fee_all>0?bcsub($pay_money_all,$shipping_fee_all,2):0;
                    $shop_tec_fee = roundLengthNumber($pay_money_all * $supplier_money_info['supplier_tec_fee_ratio'] / 100, 2, false);//技术服务费
                    $shop_tec_fee = $shop_tec_fee > 0 ? $shop_tec_fee : 0;
                    $strs .= '技术服务费' . $shop_tec_fee . '元';
                    $strs .= '供应商金额' . $supplier_money_info['supplier_money'] . '元，';
                    $pay_money_all = $pay_money_all - $shop_tec_fee;
                }
            }
            debugFile(1, '==>updateShopAccount_OrderPay——6<==', '699');
            // 添加店铺的整体资金流水
            //变更 订单号：xxxx，实付金额x元，平台优惠x元，店铺优惠x元，实际到账x元，已进入冻结账户。
            //变更 订单号：202000000000000，订单金额100元，平台优惠5元，店铺优惠5元，分销返佣5元，全球分红5元，区域分红5元，团队分红5元，实际支付95.00元，实际到账75元，已进入冻结账户。
            // $string = "订单号：".$order_no."，订单金额".$order_obj["order_money"]."元，平台优惠".$order_obj["platform_promotion_money"]."元，店铺优惠".$order_obj["shop_promotion_money"]."元，".$strs."实际支付".$pay_money."元，实际到账".$pay_money_all."元,已进入冻结账户
            $shop_account->updateShopAccountTotalMoney($shop_id, $pay_money_all);
            $shop_account->updateShopRealAccountTotalMoney($shop_id, $pay_money_all);
            $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, $pay_money_all, 1, $order_id, "订单号：" . $order_no . "，商品金额" . $order_obj["goods_money"] . "元，平台优惠" . $order_obj["platform_promotion_money"] . "元，店铺优惠" . $order_obj["shop_promotion_money"] . "元，" . $strs . "实际支付" . $pay_money . "元" . $presell_text . "，实际到账" . $pay_money_all . "元,已进入冻结账户", "订单支付完成，资金入账", $order_obj["website_id"]);
            //此处写入待处理分账记录
            //数据 订单号 订单id 实际支付金额 实际到账金额 店铺id 处理状态 分账方式是否自动 备注
            $separate_info = array(
                'website_id' => $order_obj['website_id'],
                'joinpay' => $order_obj['joinpay'],
                'order_no' => $order_no,
                'order_id' => $order_id,
                'pay_money' => $pay_money,
                'pay_money_all' => $pay_money_all ? $pay_money_all : $pay_money,
                'shop_id' => $shop_id,
                'add_time' => time(),
                'status' => 0
            );
            debugFile($separate_info, '==>updateShopAccount_OrderPay——7<==', '699');
            $shop_account->separateAccounts($separate_info);
            $shop_account->addAccountRecords($shop_id, $order_obj['buyer_id'], '订单支付完成', $pay_money_all, 50, $order_id, "订单支付完成", $order_obj["website_id"]);
            // $order_model->commit();
            debugFile(1, '==>updateShopAccount_OrderPay——8<==', '699');
            Db::commit();
        } catch (\Exception $e) {
            debugFile($e->getMessage(), '==>店铺佣金账户订单_支付_错误updateShopAccount_OrderPay<==', '69');
            recordErrorLog($e);
            Log::write("错误updateShopAccount_OrderPay" . $e->getMessage());
            // $order_model->rollback();
            Db::rollback();
            debugFile($e->getMessage(), '==>店铺佣金账户订单_支付_错误updateShopAccount_OrderPay2<==', '69');
            // debugLog($e->getMessage(), '==>店铺佣金账户订单_支付_错误updateShopAccount_OrderPay2<==');
        }
    }

    /**
     * 店铺整笔订单项退款
     *
     * @param unknown $order_goods_id
     * 540 500 40
     * refund_real_money 退款金额
     */
    private function updateShopAccount_OrderRefund_All($order_id = 0, $refund_real_money)
    {
        debugLog($refund_real_money, $order_id . '==>店铺流水-1-updateShopAccount_OrderRefund_All<==');
        // $order_goods_model = new VslOrderGoodsModel();
        $order_model = new VslOrderModel();
        $shop_account = new ShopAccount();
        $order_model->startTrans();
        try {
            // 查询订单项的信息
            // $order_goods_obj = $order_goods_model->get($order_goods_id);
            // // 退款金额
            $refund_money = $refund_real_money;
            // // 订单id
            // $order_id = $order_goods_obj["order_id"];
            // 订单信息
            $order_obj = $order_model->get($order_id);
            // 订单的支付方式
            $payment_type = $order_obj["payment_type"];
            // 店铺优惠金额
            $shop_promotion_money = $order_obj["shop_promotion_money"];
            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            //预售支付进度
            $money_type = $order_obj["money_type"];
            //变更 edit for 2020/07/07 订单退款 返还佣金分红等  订单金额 100 分销分红扣除80 店铺收入20 此时用户申请10元或者60元或者90元退款
            //查询该订单 退款金额是否超出店铺实际收入 是则开始返还分销分红金额 1先全额返还 2再扣除退款金额 3变更冻结金额
            //获取订单佣金信息 获取平台汇率设置
            $order_commission = new VslOrderDistributorCommissionModel();
            $orders = $order_commission->Query(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], '*');
            $order_detail = array();
            if ($orders) {
                foreach ($orders as $key1 => $value) {
                    if ($value['commissionA_id']) {
                        $order_detail['commissionA'] += $value['commissionA'];
                        $order_detail['pointA'] += $value['pointA'];
                    }
                    if ($value['commissionB_id']) {
                        $order_detail['commissionB'] += $value['commissionB'];
                        $order_detail['pointB'] += $value['pointB'];
                    }
                    if ($value['commissionC_id']) {
                        $order_detail['commissionC'] += $value['commissionC'];
                        $order_detail['pointC'] += $value['pointC'];
                    }
                }
                unset($value);
                $order_detail['commission'] = $order_detail['commissionA'] + $order_detail['commissionB'] + $order_detail['commissionC'];
                $order_detail['point'] = $order_detail['pointA'] + $order_detail['pointB'] + $order_detail['pointC'];
            }

            $commission_money = 0;
            if ($order_detail && floatval($order_detail['commission']) > 0 || floatval($order_detail['point']) > 0) {
                //返积分
                $config = new Config();
                $config_info = $config->getShopConfig(0, $order_obj['website_id']);
                $convert_rate = $config_info['convert_rate'] ? $config_info['convert_rate'] : 1; //汇率后台不设置 默认比例为1:1
                $commission_point_money = floor($order_detail['point'] / $convert_rate);
                $commission_money = $commission_point_money + $order_detail['commission'];

            }
            // 查询该笔订单是否启用店铺分红 有则扣除相应的(分红金额) 再写入店铺收益
            $orderBonusModel = new VslOrderBonusLogModel();
            $area_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], 'area_bonus');//区域分红
            $global_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], 'global_bonus');//全球分红
            $team_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'shop_id' => $order_obj['shop_id']], 'team_bonus');//团队分红
            $strs = '';
            if ($commission_money) {
                $strs .= "返还分销佣金" . $commission_money . "元，";
            }
            if ($area_bonus) {
                $strs .= "返还区域分红" . $area_bonus . "元，";
            }
            if ($global_bonus) {
                $strs .= "返还全球分红" . $global_bonus . "元，";
            }
            if ($team_bonus) {
                $strs .= "返还团队分红" . $team_bonus . "元，";
            }
            $all_commission = $commission_money + $area_bonus + $global_bonus + $team_bonus;
            //该处处理预售订单 --
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id, 'refund_status' => 5]);
            $presell_id = 0;
            foreach ($order_goods_info as $v) {
                $presell_id = $v->presell_id;
            }
            if ($presell_id != 0 && $money_type == 2) {
                //更正真实支付金额
                $order_obj["pay_money"] += $order_obj["final_money"];
                //更正真实店铺到账金额 + 平台优惠//平台优惠也需要解冻
                $order_obj["shop_order_money"] += ($order_obj["final_money"] + $order_obj['platform_promotion_money']);
            }

            # 会员卡
            if (isset($order_obj['membercard_deduction_money'])) {
                $order_obj["pay_money"] += $order_obj['membercard_deduction_money'];
                //如果不退税，扣除税费再计算
                if (getAddons('invoice', $order_obj['website_id'], $order_obj['shop_id']) && $order_obj['invoice_tax']) {
                    $invoice = new InvoiceServer();
                    $invoiceConfig = $invoice->getInvoiceConfig(['website_id' => $order_obj['website_id'], 'shop_id' => $order_obj['shop_id']], 'is_refund');
                    if ($invoiceConfig) {
                        $is_refund = $invoiceConfig['is_refund'];
                        if ($is_refund == 0) {//不退款
                            $order_obj["pay_money"] = bcsub($order_obj["pay_money"], $order_obj['invoice_tax'], 2);
                        }
                    }
                }
            }
            $order_obj["shop_order_money"] = $order_obj["shop_order_money"] - $all_commission;
            if ($order_obj["pay_money"] <= $refund_money) { //全额退款
                if ($order_obj['supplier_id']) {
                    $order_goods_model = new VslOrderGoodsModel();
                    $goodsSer = new GoodsService();
                    $order_goods_objs = $order_goods_model::all(['order_id' => $order_id]);
                    $deduct_supplier_money = 0;
                    $deduct_shop_money = 0;
                    $deduct_tec_fee_money = 0;//技术费店铺没有收取，是平台收取，实际应该由店铺扣除
                    $anchor_earnings = 0;//主播收益
                    foreach ($order_goods_objs as $order_goods_obj) {
                        if ($order_goods_obj['supplier_id']) {
                            $deduct_supplier_money += $order_goods_obj['supplier_money'];
                            $order_goods_obj['supplier_money'] = $order_goods_obj['order_status'] >= 2 ? $order_goods_obj['supplier_money'] - $order_goods_obj['shipping_fee'] : $order_goods_obj['supplier_money'];
                            $deduct_shop_money += $order_goods_obj['supplier_shop_income'];
                            $deduct_tec_fee_money += $order_goods_obj['supplier_tec_fee'];
                            //供应商实际商品id:
                            $supplier_goods_id = $goodsSer->getGoodsDetailById($order_goods_obj['goods_id'], 'goods_id,goods_name,price,collects,supplier_goods_id')['supplier_goods_id'];
                            //供应商退款
                            $remark = "订单号：" . $order_no . "，商品ID:" . $supplier_goods_id . "，订单退款，冻结金额减少" . $order_goods_obj['supplier_money'] . "元。";
                            $shop_account->updateSupplierFreezingMoney($order_goods_obj['supplier_id'], $order_goods_obj['supplier_money'], $order_obj['website_id'], 5);
                            $shop_account->addSupplierAccountRecords(getSerialNo(), $order_goods_obj['supplier_id'], $order_goods_obj['supplier_money'], 3, $order_id, $remark, "订单退款，冻结金额减少", $order_goods_obj['website_id']);
                        }
                    }
                    //处理店铺金额
                    $order_obj["shop_order_money"] = $order_obj["shop_order_money"] - $deduct_supplier_money - $deduct_tec_fee_money - $anchor_earnings;//TODO 优惠金额让店铺承担
                }
                if (getAddons('liveshopping', $order_obj['website_id'])) {
                    $anchor_earnings = 0;//主播收益
                    $order_goods_objs = $order_goods_model::all(['order_id' => $order_id]);
                    foreach ($order_goods_objs as $order_goods_obj) {
                        if ($order_goods_obj['anchor_id']) {
                            $order_anchor_earnings = new OrderAnchorEarningsModel();
                            $anchor_goods_earnings = $order_anchor_earnings->getInfo(['order_goods_id' => $order_goods_obj['order_goods_id']])['anchor_earnings'];
                            $anchor_earnings += $anchor_goods_earnings;
                        }
                    }
                }

                $order_obj["shop_order_money"] = $order_obj["shop_order_money"] - $anchor_earnings;
                $order_obj["shop_order_money"] = $order_obj["shop_order_money"] > 0 ? $order_obj["shop_order_money"] : 0;
                $shop_account->updateShopAccountTotalMoney($shop_id, (-1) * $order_obj["shop_order_money"]);
                $shop_account->updateShopRealAccountTotalMoney($shop_id, (-1) * $order_obj["shop_order_money"]);
                // 添加店铺的整体资金流水
                $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, (-1) * $refund_money, 3, $order_id, "订单号：" . $order_no . "，订单退款金额" . $refund_money . "元，" . $strs . "冻结金额实际变动-" . $order_obj["shop_order_money"] . "元。", $order_obj['website_id']);
            } else {

                $get_money = $order_obj["pay_money"] - $refund_money + $order_obj['platform_promotion_money'];//可提现
                //处理供应商部分退款以及店铺退款
                if ($order_obj['supplier_id']) {
                    $supplierRes = $this->dealSupplierOrderRefundAll($order_id, $refund_money, $order_obj["shop_order_money"], $order_no);
                    $order_obj["shop_order_money"] = $supplierRes['shop_order_money'];
                    $get_money = $supplierRes['order_goods_shop_tx_money']; //180
                    $refund_money = $supplierRes['order_goods_shop_deduct_all'];
                }
                $get_money = bcsub($get_money, $order_obj['deduction_money'], 2);//退款去除积分抵扣
                //解冻100 剩余到账0 店解-100  --   供应商-100 剩余20
                $shop_account->updateShopAccountTotalMoney($shop_id, (-1) * $order_obj["shop_order_money"]);
                $shop_account->updateShopRealAccountTotalMoney($shop_id, (-1) * $order_obj["shop_order_money"]);
                $shop_account->updateShopAccountMoney($shop_id, $get_money);

                // 添加店铺的整体资金流水
                $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, $get_money, 5, $order_id, "订单号为：" . $order_no . ",订单退款金额" . $refund_money . "元, 剩余到账" . $get_money . "元, 支付方式【在线支付】, 已入账户。", "订单退款完成，资金入账", $order_obj['website_id']);
                // 添加店铺的整体资金流水
                $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, (-1) * $refund_money, 3, $order_id, "订单号：" . $order_no . "，订单退款金额" . $refund_money . "元，" . $strs . "冻结金额实际变动-" . $refund_money . "元。", $order_obj['website_id']);
            }
            $order_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write("错误updateShopAccount_OrderRefund:" . $e->getMessage());
            $order_model->rollback();
            debugLog($e->getMessage(), $order_id . '==>店铺流水5-recordErrorLog<==');
        }
    }

    /**
     * 订单项退款 - 后台手动
     *
     * @param unknown $order_goods_id
     */
    private function updateShopAccount_OrderRefund($order_goods_id, $order_id = 0)
    {
        debugLog($order_goods_id, $order_id . '==>店铺流水-1-updateShopAccount_OrderRefund<==');
        $order_goods_model = new VslOrderGoodsModel();
        $order_model = new VslOrderModel();
        $shop_account = new ShopAccount();
        $order_goods_model->startTrans();
        try {
            // 查询订单项的信息
            $order_goods_obj = $order_goods_model->get($order_goods_id);
            // 退款金额
            $refund_money = $order_goods_obj["refund_require_money"];
            // 订单id
            $order_id = $order_goods_obj["order_id"];
            //商品订单状态
            $order_status = $order_goods_obj["order_status"];
            $shipping_fee = $order_goods_obj["shipping_fee"];//商品运费
            // 订单信息
            $order_obj = $order_model->get($order_id);
            if ($order_goods_obj["refund_require_money"] == 0 && $order_obj['order_type'] == 5) {
                $refund_money = $order_obj['shop_order_money'];
            }
            //如果该订单没有使用活动，使用了会员折扣，则需要扣除会员折扣
            // 订单的支付方式
            $payment_type = $order_obj["payment_type"];
            // 店铺优惠金额
            $shop_promotion_money = $order_obj['shop_promotion_money'];
            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            //预售支付进度
            $money_type = $order_obj["money_type"];
            //店铺挑选的供应商商品
            // if ($order_goods_obj['supplier_id']){
            //      $supplier_refund_money = $refund_money = $order_goods_obj['supplier_shop_income'];//店铺收入
            // }

            if ($order_goods_obj['supplier_id']) {
                $supplier_refund_money = 0;
                $refund_money = $order_goods_obj['supplier_shop_income'] < $refund_money ? $order_goods_obj['supplier_shop_income'] : $refund_money;//店铺收入

                //$refund_money = $refund_money + $order_goods_obj['supplier_tec_fee'];//这里要用 +
                # 优先供应商退款
                if ($order_status >= 2) {
                    $order_goods_obj['supplier_money'] = $order_goods_obj['supplier_money'] - $shipping_fee;
                } else {
                    $order_goods_obj['actual_price'] = bcadd($order_goods_obj['actual_price'], $shipping_fee, 2);//actual_price的运费是有供应商获得
                }
                if ($order_goods_obj['refund_require_money'] < $order_goods_obj['actual_price']) {
                    //实际退款金额小于应退款金额，店铺优先扣除
                    $get_money = $order_goods_obj['actual_price'] - $order_goods_obj['refund_require_money'];

                    if ($get_money >= $refund_money) {
                        $supplier_refund_money = bcsub($get_money, $refund_money, 2);
                    } else {
                        $supplier_refund_money = 0;
                        $refund_money = bcsub($refund_money, $get_money, 2);//店铺应退金额
                    }
                }
                //供应商实际商品id:
                $goodsSer = new GoodsService();
                $supplier_goods_id = $goodsSer->getGoodsDetailById($order_goods_obj['goods_id'], 'goods_id,goods_name,price,collects,supplier_goods_id')['supplier_goods_id'];
                $order_goods_obj['supplier_money'] = $order_goods_obj['supplier_money'] - $supplier_refund_money;
                $remark = "订单号：" . $order_no . "，商品ID:" . $supplier_goods_id . "，订单退款，冻结金额减少" . $order_goods_obj['supplier_money'] . "元。";
                $shop_account->updateSupplierFreezingMoney($order_goods_obj['supplier_id'], $order_goods_obj['supplier_money'], $order_obj['website_id'], 5);
                $shop_account->addSupplierAccountRecords(getSerialNo(), $order_goods_obj['supplier_id'], $order_goods_obj['supplier_money'], 3, $order_id, $remark, "订单退款，冻结金额减少", $order_obj['website_id']);
                $shipping_fee_all = $this->calculSupplierShippingMoney(0, $order_goods_id);
                if ($refund_money > $shipping_fee_all) {
                    // $refund_money -= $shipping_fee_all;
                }
                // $refund_money -= $shipping_fee_all; //供应商运费由供应商自己承担 还有10？当前是因为支付的时候写入了运费 然后这里还要减一次 or 该店铺实际到账金额是否可用代替退款金额？
                //退款金额 是否大于店铺收入
            }


            //变更 edit for 2020/07/07 订单退款 返还佣金分红等  订单金额 100 分销分红扣除80 店铺收入20 此时用户申请10元或者60元或者90元退款
            //查询该订单 退款金额是否超出店铺实际收入 是则开始返还分销分红金额 1先全额返还 2再扣除退款金额 3变更冻结金额
            //获取订单佣金信息 获取平台汇率设置
            $order_commission = new VslOrderDistributorCommissionModel();
            $orders = $order_commission->Query(['order_id' => $order_id, 'order_goods_id' => $order_goods_id, 'shop_id' => $order_obj['shop_id']], '*');
            $order_detail = array();
            if ($orders) {
                foreach ($orders as $key1 => $value) {
                    if ($value['commissionA_id']) {
                        $order_detail['commissionA'] += $value['commissionA'];
                        $order_detail['pointA'] += $value['pointA'];
                    }
                    if ($value['commissionB_id']) {
                        $order_detail['commissionB'] += $value['commissionB'];
                        $order_detail['pointB'] += $value['pointB'];
                    }
                    if ($value['commissionC_id']) {
                        $order_detail['commissionC'] += $value['commissionC'];
                        $order_detail['pointC'] += $value['pointC'];
                    }
                }
                unset($value);
                $order_detail['commission'] = $order_detail['commissionA'] + $order_detail['commissionB'] + $order_detail['commissionC'];
                $order_detail['point'] = $order_detail['pointA'] + $order_detail['pointB'] + $order_detail['pointC'];
            }

            $commission_money = 0;
            if ($order_detail && floatval($order_detail['commission']) > 0 || floatval($order_detail['point']) > 0) {
                //返积分
                $config = new Config();
                $config_info = $config->getShopConfig(0, $order_obj['website_id']);
                $convert_rate = $config_info['convert_rate'] ? $config_info['convert_rate'] : 1; //汇率后台不设置 默认比例为1:1
                $commission_point_money = floor($order_detail['point'] / $convert_rate);
                $commission_money = $commission_point_money + $order_detail['commission'];

            }
            // 查询该笔订单是否启用店铺分红 有则扣除相应的(分红金额) 再写入店铺收益
            $orderBonusModel = new VslOrderBonusLogModel();
            $area_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'order_goods_id' => $order_goods_id], 'area_bonus');//区域分红
            $global_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'order_goods_id' => $order_goods_id], 'global_bonus');//全球分红
            $team_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'order_goods_id' => $order_goods_id], 'team_bonus');//团队分红
            $strs = '';
            if ($commission_money) {
                $strs .= "返还分销佣金" . $commission_money . "元，";
            }
            if ($area_bonus) {
                $strs .= "返还区域分红" . $area_bonus . "元，";
            }
            if ($global_bonus) {
                $strs .= "返还全球分红" . $global_bonus . "元，";
            }
            if ($team_bonus) {
                $strs .= "返还团队分红" . $team_bonus . "元，";
            }
            //判断是否是主播订单扣除主播分成
            $anchor_earnings = 0;
            if (getAddons('liveshopping', $order_obj['website_id'])) {
                $order_anchor_earnings = new OrderAnchorEarningsModel();
                $order_earnings_info = $order_anchor_earnings->getInfo(['order_goods_id' => $order_goods_id]);
                $anchor_earnings = $order_earnings_info['anchor_earnings'];
                if ($order_earnings_info['anchor_earnings']) {
                    $strs .= "返还主播分成收益" . $anchor_earnings . "元，";
                }
            }
            // $refund_money
            $change_refund_money = $commission_money + $area_bonus + $global_bonus + $team_bonus + $anchor_earnings - $refund_money;

            //查询单项退款是否解冻
            //文案 订单号：202000000000000，订单退款金额X元，返还分销佣金X元，返还全球分红X元，返还区域分红X元，返还团队分红X元，冻结金额实际变动X元。
            if ($change_refund_money >= 0) {
                $changes = "+";
                $real_change_refund_money = abs($change_refund_money);
            } else if ($change_refund_money < 0) {
                $changes = "-";
                $real_change_refund_money = (-1) * abs($change_refund_money);
            }

            //查看该笔商品店铺实际收入 是否少于退款金额 是则变更实际变动金额
            //预售退款 定金+尾款 全额退 就全额解冻
            // $string = "订单号：".$order_no."，订单退款金额".$refund_money."元，".$strs."冻结金额实际变动".$changes.$change_refund_money."元。"
            // 修改店铺的入账金额
            $shop_account->updateShopAccountTotalMoney($shop_id, $real_change_refund_money);
            $shop_account->updateShopRealAccountTotalMoney($shop_id, $real_change_refund_money);
            //供应商技术费退款处理逻辑 by sgw
            if ($order_goods_obj['supplier_id']) {
                $refund_money = bcadd($refund_money, $order_goods_obj['supplier_tec_fee'], 2);//这里要用 +
            }

            // 添加店铺的整体资金流水
            $res = $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, (-1) * $real_change_refund_money, 3, $order_id, "订单号：" . $order_no . "，订单退款金额" . $refund_money . "元，" . $strs . "冻结金额实际变动" . $changes . abs($change_refund_money) . "元。", '', $order_obj['website_id']);

            $order_goods_model->commit();
            // 查询是否为最后一笔商品项退款，是则进行完成操作
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id, 'refund_status' => 5]);
            $m = 0;
            $presell_id = 0;
            foreach ($order_goods_info as $v) {
                $m += $v->refund_require_money;
                $presell_id = $v['presell_id'];
            }
            unset($v);
            # 会员卡
            if ($order_goods_obj['membercard_deduction_money']) {
                $order_obj["pay_money"] = bcadd($order_obj["pay_money"], $order_obj['membercard_deduction_money'], 2);
            }
            $count = $order_goods_model->getCount(['website_id' => $order_obj['website_id'], 'refund_status' => ['neq', 5], 'order_id' => $order_id]);

            if ($count == 0 && $m < $order_obj["pay_money"]) {
                $this->updateShopAccount_OrderComplete($order_id, 1, 1);//如果是最后一笔且申请的所有订单商品退款金额<订单支付金额则处理退款费用
            }
            //            else{
            //                $shop_account->updateShopAccountTotalMoney($shop_id,  (-1)*$order_obj['platform_promotion_money']);
            //            }
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write("错误updateShopAccount_OrderRefund:" . $e->getMessage());
            $order_goods_model->rollback();
        }
    }

    /**
     * 订单完成（店铺收入）
     *
     * @param unknown $order_id
     *
     */
    /**
     * @param     $order_id
     * @param int $is_change 1所有商品项退款完成 结算金额 :所有订单商品已处理
     * @param int $is_last 最后一笔订单商品处理 0否 1是
     */
    private function updateShopAccount_OrderComplete($order_id, $is_change = 0, $is_last = 0)
    {
        debugLog($order_id . '==>店铺流水-1-updateShopAccount_OrderComplete<==');
        $order_model = new VslOrderModel();
        $shop_account = new ShopAccount();
        $order = new OrderBusiness();
        $order_model->startTrans();
        //test 启用
        // $is_change = 1;

        try {
            #订单的信息
            $order_obj = $order_model->get($order_id);
            $order_sataus = $order_obj["order_status"];
            #判断当前订单的状态是否 已经交易完成 或者 已退款的状态
            if ($order_sataus == ORDER_COMPLETE_SUCCESS || $order_sataus == ORDER_COMPLETE_REFUND || $order_sataus == ORDER_COMPLETE_SHUTDOWN || $is_change == 1) {

                #订单的实际付款金额 这里有个情况 供应商商品 店铺实际收入跟原shop_order_money不一致 所有要重新处理一下
                //$pay_money = $order->getOrderRealPayShopMoney($order_id);
                $pay_money_all = $order->getOrderRealPayShopMoney($order_id);
                //预售订单需要加上尾款金额
                if ($order_obj['order_type'] == 7 && $order_obj['money_type'] == 2 && $order_obj['presell_id']) {
                    $pay_money_all += $order_obj['final_money'];
                }

                // 多商品订单 获取订单是否发生单商品退款
                $order_goods_model = new VslOrderGoodsModel();
                $order_goods_info = $order_goods_model::all(['order_id' => $order_id, 'refund_status' => 5]);//订单关闭
                $m = 0;
                $deduction_money = 0;//积分抵扣金额
                $supplier_money = 0; //已退款的供应商金额
                $shop_tec_fee = 0; //已退款的供应商的技术服务费
                $discount_price = 0; //会员折扣的差额
                $refound_eargnings = 0;//直播商品退款去掉的分成
                foreach ($order_goods_info as $v) {
                    $m += $v->refund_require_money;
                    $deduction_money += $v->deduction_money;
                    //获取会员折扣金额
                    $discount_price += ($v['price'] - $v['member_price']);
                    //订单商品 + 供应商流水 order_id查询订单商品（查询供应商，有运费，refund_status=5）
                    // 只有所有其他订单商品都完成，且这是最后一笔退款（订单商品总退款金额<订单支付金额）；且发货了，则把运费移到'可提现'
                    //退款的order_status不一定大于0

                    if ($v['supplier_id']) {
                        $supplier_money += $v->supplier_money;
                        $shop_tec_fee += $v->supplier_tec_fee;
                        if ($v['shipping_fee'] > 0 && $v['order_status'] > 0) {
                            $pay_money_all = $pay_money_all - $v['shipping_fee'];//供应商运费
                            $shop_account->updateSupplierTotalMoney($v['supplier_id'], $v['shipping_fee'], $v['website_id']);
                            $remark = "供应商退款解冻运费，冻结金额减少" . $v['shipping_fee'] . "元。";
                            $shop_account->addSupplierAccountRecords(getSerialNo(), $v['supplier_id'], $v['shipping_fee'], 3, $order_id, $remark, "运费到可提现，冻结金额减少", $v['website_id']);
                        }
                        //处理供应商部分退款以及店铺退款
                        if (($v['refund_require_money'] < $v['actual_price']) && $is_last) {//说明退款金额 != 实际退款金额,则优先退供应商，扣除后，剩余的为店铺的  -- 这里有问题 拉登
                            // actual_price 商品金额
                            if ($v['refund_require_money'] < $v['supplier_money']) {
                                // $m += $v['actual_price'] - $v['refund_require_money'] + $v['shipping_fee'];

                            }
                        }
                    }
                    if (getAddons('liveshopping', $order_obj['website_id'])) {
                        //获取是否该商品是直播的商品，去掉退款的分成
                        $order_goods_id = $v['order_goods_id'] ?: 0;
                        $order_anchor_earnings = new OrderAnchorEarningsModel();
                        $refound_eargnings_info = $order_anchor_earnings->getInfo(['order_id' => $order_id, 'order_goods_id' => $order_goods_id]);
                        $refound_eargnings = $refound_eargnings_info['anchor_earnings'] ?: 0;
                    }
                }

                # 会员卡
//                if ($order_obj['membercard_deduction_money']){
//                    $pay_money_all = bcadd($pay_money_all, $order_obj['membercard_deduction_money'],2);
//                }
                unset($v);
//                $pay_money -= $m;
                $pay_money_all -= $m;
                $str = '';
                if ($is_last) {
                    $pay_money_all = $pay_money_all - $order_obj['platform_promotion_money'];
                    if ($pay_money_all <= 0) {
                        $order_model->commit();
                        return;
                    }
                } else {
                    $pay_money_all = $pay_money_all - $deduction_money - $discount_price;
                    if ($discount_price) {
                        $str .= '扣除会员优惠' . $discount_price . '元,';
                    }
                    if ($deduction_money) {
                        $str .= '扣除积分优惠' . $deduction_money . '元,';
                    }
                    if ($pay_money_all <= 0) {
                        $order_model->commit();
                        return;
                    }
                }

                #订单的支付方式
                $payment_type = $order_obj["payment_type"];
                #店铺id
                $shop_id = $order_obj["shop_id"];
                #订单号
                $order_no = $order_obj["order_no"];
                // 店铺优惠金额
                $shop_promotion_money = $order_obj["shop_promotion_money"];
                // 修改店铺的优惠金额
                $shop_account->updateShopPromotionMoney($shop_id, $shop_promotion_money);

                //先查询分销设置，是否开启店铺分销 是则有该店铺负责佣金 否则还是由平台负责
                $distribution_admin_status = 0;
                $isdistributionStatus = getAddons('distribution', $order_obj['website_id']);

                if ($isdistributionStatus) {
                    //获取分销基础设置，是否开启店铺分销
                    $config = new distributorService();
                    $distributionStatusAdmin = $config->getDistributionSite($order_obj['website_id']);
                    $distribution_admin_status = $distributionStatusAdmin['distribution_admin_status'];
                }
                //查询该笔订单是否启用分销 有则扣除相应的(佣金 积分换算成金额) 再写入店铺收益
                //获取订单佣金信息 获取平台汇率设置
                $order_commission = new VslOrderDistributorCommissionModel();
                $orders = $order_commission->Query(['order_id' => $order_id, 'return_status' => 0, 'shop_id' => $order_obj['shop_id']], '*');
                $order_detail = array();
                if ($orders) {
                    foreach ($orders as $key1 => $value) {
                        if ($value['commissionA_id']) {
                            $order_detail['commissionA'] += $value['commissionA'];
                            $order_detail['pointA'] += $value['pointA'];
                        }
                        if ($value['commissionB_id']) {
                            $order_detail['commissionB'] += $value['commissionB'];
                            $order_detail['pointB'] += $value['pointB'];
                        }
                        if ($value['commissionC_id']) {
                            $order_detail['commissionC'] += $value['commissionC'];
                            $order_detail['pointC'] += $value['pointC'];
                        }
                    }
                    unset($value);
                    $order_detail['commission'] = $order_detail['commissionA'] + $order_detail['commissionB'] + $order_detail['commissionC'];
                    $order_detail['point'] = $order_detail['pointA'] + $order_detail['pointB'] + $order_detail['pointC'];
                }

                $commission_money = 0;
                if ($order_detail && floatval($order_detail['commission']) > 0 || floatval($order_detail['point']) > 0) {
                    //返积分
                    $config = new Config();
                    $config_info = $config->getShopConfig(0, $order_obj['website_id']);
                    $convert_rate = $config_info['convert_rate'] ? $config_info['convert_rate'] : 1; //汇率后台不设置 默认比例为1:1
                    $commission_point_money = floor($order_detail['point'] / $convert_rate);
                    $commission_money = $commission_point_money + $order_detail['commission'];

                }
                // debugLog($commission_money, '==>店铺佣金账户commission_money<==');
                // debugLog($order_detail, '==>店铺佣金账户订单order_detail<==');
                // debugLog($order_id, '==>店铺佣金账户订单order_id<==');
                // debugLog($config_info, '==>店铺佣金账户订单config_info<==');
                // 查询该笔订单是否启用店铺分红 有则扣除相应的(分红金额) 再写入店铺收益
                $orderBonusModel = new VslOrderBonusLogModel();
                $area_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'area_return_status' => 0, 'shop_id' => $order_obj['shop_id']], 'area_bonus');//区域分红
                $global_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'global_return_status' => 0, 'shop_id' => $order_obj['shop_id']], 'global_bonus');//全球分红
                $team_bonus = $orderBonusModel->getSum(['order_id' => $order_id, 'team_return_status' => 0, 'shop_id' => $order_obj['shop_id']], 'team_bonus');//团队分红
                $strs = '';
                if ($commission_money) {
                    $strs .= "扣除佣金" . $commission_money . "元，";
                }
                if ($area_bonus) {
                    $strs .= "扣除区域分红" . $area_bonus . "元，";
                }
                if ($global_bonus) {
                    $strs .= "扣除全球分红" . $global_bonus . "元，";
                }
                if ($team_bonus) {
                    $strs .= "扣除团队分红" . $team_bonus . "元，";
                }

                //$real_pay_money = $pay_money - $commission_money - $area_bonus - $global_bonus - $team_bonus;
                $pay_money_all = $pay_money_all - $commission_money - $area_bonus - $global_bonus - $team_bonus;

                //判断这笔订单中是否有供应商商品，如果有，就要扣除结算给供应商的钱
                $supplier_money_info = $this->calculSupplierMoney($order_id, 4); //这里有种情况就是部分退款，剩余的要结算  edit for 2020/12/26 先扣店铺再扣供应商
                if ($supplier_money_info['supplier_money']) {
                    $pay_money_all = $pay_money_all - $supplier_money_info['supplier_money'];
                    $shop_tec_fee2 = roundLengthNumber($pay_money_all * $supplier_money_info['supplier_tec_fee_ratio'] / 100, 2, false);//技术服务费
                    $shop_tec_fee2 = $shop_tec_fee2 > 0 ? $shop_tec_fee2 : 0;
                    $pay_money_all = $pay_money_all - $shop_tec_fee2;
                }
                //是否要去除主播收益
                $anchor_earnings = 0;
                if (getAddons('liveshopping', $order_obj['website_id'])) {
                    $order_anchor_earnings = new OrderAnchorEarningsModel();
                    $anchor_earnings = $order_anchor_earnings->getSum(['order_id' => $order_id], 'anchor_earnings') - $refound_eargnings;
                }
                $pay_money_all = $pay_money_all - $supplier_money - $shop_tec_fee - $anchor_earnings;
                $calculFreezingMoney = $this->calculFreezingMoney($order_id, $shop_id);

                // 修改店铺的入账金额
                $shop_account->updateShopAccountMoney($shop_id, $pay_money_all);
                //edit for 2020/09/28 变更 完成需要扣除待结算金额
                $shop_account->updateShopAccountTotalMoney($shop_id, (-1) * $pay_money_all);

                if ($is_last == 1) {
                    $shop_account->updateShopAccountTotalMoney($shop_id, (-1) * $order_obj['platform_promotion_money']);
                } else {

                    //这里解冻需要处理 判断一下是否已经是全部解冻完
                    $calculFreezingMoney = $this->calculFreezingMoney($order_id, $shop_id);
                    if ($calculFreezingMoney > $pay_money_all) {
                        $shop_account->updateShopAccountTotalMoney($shop_id, (-1) * ($calculFreezingMoney - $pay_money_all));
                    }
                }
                // 添加店铺的整体资金流水
                //文案修改 订单号：202000000000000，订单已完成，实际到账75元已解冻，已进入可提现账户。
                // $string = "订单号：".$order_no."，订单已完成，实际到账".$real_pay_money."元已解冻，已进入可提现账户。";
                if ($pay_money_all == 0) {
                    $order_model->commit();
                }
                if ($commission_money || $area_bonus || $global_bonus || $team_bonus) {
                    $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, $pay_money_all, 5, $order_id, "订单号：" . $order_no . "，订单已完成，" . $str . "实际到账" . $pay_money_all . "元已解冻，已进入可提现账户。", "订单完成，资金入账", $order_obj['website_id']);
                } else {
                    $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, $pay_money_all, 5, $order_id, "订单完成金额" . $pay_money_all . "元, 订单号为：" . $order_no . ", " . $str . "支付方式【在线支付】, 已入账户。", "订单完成，资金入账", $order_obj['website_id']);
                }

            }
            $order_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write("错误updateShopAccount_OrderComplete:" . $e->getMessage());
            $order_model->rollback();
        }
    }

    /**
     * ***********************************************店铺账户--End******************************************************
     */

    /**
     * ***********************************************平台账户计算--Start******************************************************
     */
    /**
     * 订单支付时处理 平台的账户
     *
     * @param string $order_out_trade_no
     * @param number $order_id
     */
    public function dealPlatformAccountOrderPay($order_out_trade_no = "", $order_id = 0)
    {
        if ($order_out_trade_no != "" && $order_id == 0) {
            $order_model = new VslOrderModel();
            $order_list = $order_model->Query(["out_trade_no" => $order_out_trade_no], "order_id");
            if (!$order_list) {
                $order_list = $order_model->Query(["out_trade_no_presell" => $order_out_trade_no], "order_id");
            }
            foreach ($order_list as $k => $v) {
                $this->updateAccountOrderPay($v);
            }
            unset($v);
        } else
            if ($order_out_trade_no == "" && $order_id != 0) {
                $this->updateAccountOrderPay($order_id);
            }
    }


    //通过外部交易号或者状态
    public function get_status_by_outno($out_no)
    {

        $order = new VslOrderModel();
        $info = $order->getInfo(['out_trade_no|out_trade_no_presell' => $out_no]);
        return $info;
    }

    //通过外部交易号或者状态查询渠道商的订单状态
    public function getChannelStatusByOutno($out_no)
    {
        $order = new VslChannelOrderModel();
        $info = $order->getInfo(['out_trade_no' => $out_no]);
        return $info;
    }

    /**
     * 订单支付成功后处理 平台账户
     *
     * @param unknown $orderid
     */
    public function updateAccountOrderPay($order_id)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $shop_account = new ShopAccount();
        $order = new OrderBusiness();
        $order_model->startTrans();
        $order_obj = $order_model->getInfo(['order_id' => $order_id], '*');
        $website_id = $order_obj['website_id'];
        try {
            // 订单的实际付款金额
            if ($order_obj['presell_id'] != 0 && getAddons('presell', $website_id, $this->instance_id) && $order_obj['money_type'] == 2) {
                $presell_mdl = new VslPresellModel();
                $presell_info = $presell_mdl->getInfo(['id' => $order_obj['presell_id']], '*');
                $firstmoney = $presell_info['firstmoney'];
                $allmoney = $presell_info['allmoney'];
                $secondmoney = $allmoney - $firstmoney;
                $num = $order_obj['pay_money'] / $firstmoney;
                $pay_money = $secondmoney * $num + $order_obj['shipping_money'];
                //判断这笔订单中是否有供应商商品，如果有，就要扣除结算给供应商的钱
                $supplier_money_info = $this->calculSupplierMoney($order_id, 1);
                $real_money = $pay_money - $supplier_money_info['supplier_money'] >= 0 ? $pay_money - $supplier_money_info['supplier_money'] : 0;
            } else {
                //变更 实际收入变更为店铺
                if ($order_obj['shop_id'] > 0) {
                    $pay_money = $order->getOrderRealPayShopMoney($order_id);
                } else {
                    $pay_money = $order->getOrderRealPayMoney($order_id);
                    //判断这笔订单中是否有供应商商品，如果有，就要扣除结算给供应商的钱
                    $supplier_money_info = $this->calculSupplierMoney($order_id, 1);//平台商品处理供应商
                    $real_money = $pay_money - $supplier_money_info['supplier_money'] >= 0 ? $pay_money - $supplier_money_info['supplier_money'] : 0;
                }
            }
            $pay_money += $order_obj['invoice_tax'];
            if ($order_obj['membercard_deduction_money']) {
                $pay_money += $order_obj['membercard_deduction_money'];//会员卡抵扣
            }
//            $channel_money = (float)$order_obj['channel_money'] - (float)$order_obj['shipping_money'];
            $channel_money = $order_obj['channel_money'];
            // 订单的类型
            $order_type = $order_obj["order_type"];
            // 订单的支付方式
            $payment_type = $order_obj["payment_type"];
            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            // 用户id
            $uid = $order_obj["buyer_id"];
            //查出当前订单是渠道商订单并且购买的是谁的
            $channel_id = $order_goods_model->getInfo(['order_id' => $order_id, 'channel_info' => ['neq', 0]], 'channel_info')['channel_info'];
            if (getAddons('channel', $website_id)) {
                $channel = new Channel();
                $channel_mdl = new VslChannelModel();
                $channel_uid = $channel_mdl->getInfo(['channel_id' => $channel_id], 'uid')['uid'];
            }
            if ($payment_type != ORDER_REFUND_STATUS) {
                // 在线支付 处理平台的资金账户
                if ($payment_type == 5) {//余额支付
                    //零售的渠道商的商品，钱也是到平台，提现才分钱。
                    $shop_account->updateAccountOrderBalance($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 14, $order_id, "订单余额支付成功，余额支付总额增加", $website_id);
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单余额支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 14, $order_id, "订单余额支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                } elseif ($payment_type == 1) {//微信支付
                    $shop_account->updateAccountOrderMoney($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 15, $order_id, "订单微信支付成功，入账总额增加", $website_id);
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel = new Channel();
                            //更新渠道商的账户表
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单微信支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 15, $order_id, "订单微信支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                } elseif ($payment_type == 2) {//支付宝支付
                    $shop_account->updateAccountOrderMoney($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 16, $order_id, "订单支付宝支付成功，入账总额增加", $website_id);
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel = new Channel();
                            //更新渠道商的账户表
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单支付宝支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 16, $order_id, "订单支付宝支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                } elseif ($payment_type == 3) {//银行卡支付
                    $shop_account->updateAccountOrderMoney($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 42, $order_id, "订单银行卡支付成功，入账总额增加", $website_id);
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel = new Channel();
                            //更新渠道商的账户表
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单银行卡支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 42, $order_id, "订单银行卡支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                } elseif ($payment_type == 16) {//eth支付
                    $shop_account->updateAccountOrderMoney($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 43, $order_id, "订单eth支付成功，入账总额增加", $website_id);
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel = new Channel();
                            //更新渠道商的账户表
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单eth支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 43, $order_id, "订单eth支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                } elseif ($payment_type == 17) {//eos支付
                    $shop_account->updateAccountOrderMoney($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 44, $order_id, "订单eos支付成功，入账总额增加", $website_id);
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel = new Channel();
                            //更新渠道商的账户表
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单eos支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 44, $order_id, "订单eos支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                } elseif ($payment_type == 4) {//货到付款
                    //零售的渠道商的商品，钱也是到平台，提现才分钱。
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单货到付款，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                } elseif ($payment_type == 20) {//GlobePay支付
                    $shop_account->updateAccountOrderMoney($pay_money);
                    $shop_account->addAccountRecords($shop_id, $uid, '订单支付', $pay_money, 46, $order_id, "订单GlobePay支付成功，入账总额增加");
                    if (getAddons('channel', $website_id)) {
                        //如果渠道商的金额不为0，则更新用户账户表
                        if ($channel_money != 0) {
                            $channel = new Channel();
                            //更新渠道商的账户表
                            $channel->updateMemberAccountFreezingBalance($channel_money, $channel_uid);
                            //26是代表渠道商订单
                            $channel->addMemberAccountRecords($channel_uid, "渠道商订单GlobePay支付成功，冻结金额增加", $channel_money, $order_id, $website_id);
                        }
                    }
                    if ($supplier_money_info['supplier_money']) {
                        //如果要结算钱给供应商，就再加一条流水
                        $shop_account->addAccountRecords($shop_id, $uid, '订单支付完成', $real_money, 46, $order_id, "订单GlobePay支付成功，供应商金额:" . $supplier_money_info['supplier_money'] . "元", $website_id);
                    }
                }
                $order_id_array = [];
                if ($order_type != 2 && $order_type != 3 && $order_type != 4 && $order_type != 10) {
                    if (getAddons('distribution', $website_id) == 1) {// 计算分销佣金
                        $order_id_array[] = $order_id;
                    }
                    if (getAddons('globalbonus', $website_id) == 1) {// 计算全球分红
                        $order_id_array[] = $order_id;
                    }
                    if (getAddons('areabonus', $website_id) == 1) {// 计算区域分红
                        $order_id_array[] = $order_id;
                    }
                    if (getAddons('teambonus', $website_id)) {// 计算团队分红
                        $order_id_array[] = $order_id;
                    }
                    if (getAddons('microshop', $website_id) == 1) {// 微店计算
                        $order_id_array[] = $order_id;
                    }
                }

                if (!empty($order_id_array)) {
                    $order_calculate_model = new VslOrderCalculateModel();
                    $order_calculate_model->save(['had_paid' => 1], ['order_id' => ['IN', $order_id_array]]);
                    /*if (class_exists('\swoole_client')) {
                        $client = new \swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC);
                        $ret = $client->connect("127.0.0.1", 9501);
                        $task_path = 'http://' . $_SERVER["HTTP_HOST"] . '/task/load_task_five';
                        if ($ret) {

                            $data = json_encode(['url' => $task_path, 'website_id' => $this->website_id]);
                            $client->send($data);
                        }
                    }*/
                    try {
//                        if(class_exists('\PhpAmqpLib\Connection\AMQPStreamConnection')){
////                        $connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
//                            $host = config('rabbitmq.host');
//                            $port = config('rabbitmq.port');
//                            $user = config('rabbitmq.user');
//                            $pass = config('rabbitmq.pass');
//                            $exchange_name = config('rabbitmq.exchange_name');
//                            $connection = new AMQPStreamConnection($host, $port, $user, $pass);
//                            $channel = $connection->channel();
//                            $channel->exchange_declare($exchange_name, 'fanout', false, true, false);
//                            $msg = new AMQPMessage($order_id, array('delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT));
//                            $channel->basic_publish($msg, $exchange_name);
////                        $channel->close();
////                        $connection->close();
//                        }
                        if (config('is_high_powered')) {
                            $exchange_name = config('rabbit_discom.exchange_name');
                            $queue_name = config('rabbit_discom.queue_name');
                            $routing_key = config('rabbit_discom.routing_key');
                            $url = config('rabbit_interface_url.url');
                            $dis_com_url = $url.'/rabbitTask/rabbitmqActDistributionOrCommition';
                            $data['order_id'] = $order_id;
                            $request_data = json_encode($data);
                            $push_data = [
                                "customType" => "distribution_commition",//标识什么业务场景
                                "data" => $request_data,//请求数据
                                "requestMethod" => "POST",
                                "url" => $dis_com_url,
                                "timeOut" => 20,
                            ];
                            $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                            $push_arr = json_decode($push_res, true);
                            debugLog($push_arr, '$push_arr支付时候打印推送结果');
                            if ($push_arr['code'] == 103) {//未创建队列
                                $create_res = createQueue($exchange_name, $queue_name, $routing_key);
                                $create_arr = json_decode($create_res, true);
                                debugLog($create_arr, '$create_arr创建结果');
                                if ($create_arr['code'] == 200) {
                                    $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                                    $push_arr = json_decode($push_res, true);
                                    debugLog($push_arr, '$push_arr支付时候打印推送结果2');
                                }
                            }
                        }//
                    } catch (\Exception $e) {

                    }
                }
            }
            $order_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write("错误updateAccountOrderPay:" . $e->getMessage());
            $order_model->rollback();
        }
    }

    /**
     * 订单完成时 处理平台的抽成
     *
     * @param unknown $order_id
     */
    public function updateAccountOrderComplete($order_id)
    {
        $order_model = new VslOrderModel();
        $order = new OrderBusiness();
        $order_obj = $order_model->getInfo(['order_id' => $order_id], '*');
        $order_sataus = $order_obj["order_status"];
        #判断当前订单的状态是否 已经交易完成
        if ($order_sataus == ORDER_COMPLETE_SUCCESS || $order_sataus == ORDER_COMPLETE_REFUND || $order_sataus == ORDER_COMPLETE_SHUTDOWN) {
            if (!empty($order_obj)) {
                $shop_id = $order_obj["shop_id"];
                // 订单的实际付款金额
//                $pay_money = $order->getOrderRealPayMoney($order_id);
                // 用户id
//                $uid = $order_obj["buyer_id"];
//                $account_service = new ShopAccount();
                // 添加平台的整体资金流水和订单流水
//                $account_service->addAccountRecords($shop_id, $uid, '订单完成', $pay_money, 50, $order_id, "订单完成", $order_obj["website_id"]);
                //处理供应商金额
                if ($shop_id == 0 && getAddons('supplier', $order_obj['website_id'])) {
                    //TODO... 订单完成

                    $this->calculSupplierMoney($order_id, 4);
                }
            }
        }

    }


    /**
     * 订单退款 更新平台的订单支付金额
     * @param     $order_goods_id [订单商品id]
     * @param int $refundtype [1全单退款 2部分退款]
     */
    public function updateAccountOrderRefund($order_goods_id, $refundtype = 0)
    {

        $order_goods_model = new VslOrderGoodsModel();
        $order_model = new VslOrderModel();
        $shop_account = new ShopAccount();
        $order_goods_model->startTrans();
        try {
            // 查询订单项的信息
            $order_goods_obj = $order_goods_model->getInfo(['order_goods_id' => $order_goods_id], '*');
            // 退款金额
            $refund_money = $order_goods_obj["refund_require_money"];
            // 订单id
            $order_id = $order_goods_obj["order_id"];
            //商品订单状态
            $order_status = $order_goods_obj["order_status"];
            $shipping_fee = $order_goods_obj["shipping_fee"];//商品运费
            if ($order_goods_obj['supplier_id']) {
                //                $refund_money = $order_goods_obj['supplier_shop_income']+$order_goods_obj['supplier_tec_fee'];
                $refund_money = $order_goods_obj['supplier_shop_income'];
            }
            // 订单信息
            $order_obj = $order_model->getInfo(['order_id' => $order_id], '*');
            // 订单的支付方式
            $payment_type = $order_obj["payment_type"];
            // 店铺id
            $shop_id = $order_obj["shop_id"];
            // 订单号
            $order_no = $order_obj["order_no"];
            // 用户id
            $uid = $order_obj["buyer_id"];
            $real_refund_money = (-1) * $refund_money;
            // 在线退款 处理平台的资金账户
            if ($payment_type == 5) {//余额支付退款
                $shop_account->updateAccountMoney($real_refund_money, $order_obj['website_id']);
                $shop_account->updateAccountOrderBalance($real_refund_money, $order_obj['website_id']);
            } else {
                $shop_account->updateAccountMoney($real_refund_money, $order_obj['website_id']);
            }
            //处理平台抽取店铺利润
            if (getAddons('shop', $order_goods_obj['website_id']) == 1) {
                $shop_account->updateShopOrderGoodsReturnRecords($order_id, $order_goods_id, $shop_id);
            }

            // 添加平台的整体资金流水和订单流水
            //            $shop_account->addAccountOrderRecords($shop_id, -$refund_money, 2, $order_goods_id, "订单项退款金额" . $refund_money . "元, 订单号：" . $order_no . "。", $uid,$this->website_id);
            $shop_account->addAccountRecords($shop_id, $uid, '订单退款', -$refund_money, 18, $order_id, "订单退款成功", $order_goods_obj['website_id']);
            # 如果是平台商品
            if ($shop_id == 0) {
                if ($refundtype == 1) {
                    $this->dealSupplierOrderRefundAll($order_id, $refund_money, $order_obj["shop_order_money"], $order_no);
                } else {
                    //如果是供应商商品，则扣除供应商的冻结金额
                    if ($order_goods_obj['supplier_id']) {
                        if ($order_status >= 2) {
                            $order_goods_obj['supplier_money'] = $order_goods_obj['supplier_money'] - $shipping_fee;
                        }
                        $remark = "订单号：" . $order_no . "，商品ID:" . $order_goods_obj['goods_id'] . "，订单退款，冻结金额减少" . $order_goods_obj['supplier_money'] . "元。";
                        $shop_account->updateSupplierFreezingMoney($order_goods_obj['supplier_id'], $order_goods_obj['supplier_money'], $order_obj['website_id'], 5);
                        $shop_account->addSupplierAccountRecords(getSerialNo(), $order_goods_obj['supplier_id'], $order_goods_obj['supplier_money'], 3, $order_id, $remark, "订单退款，冻结金额减少", $order_obj['website_id']);
                    }
                }
            }
            $is_distribution_loser = 0;
            $is_teambonus_loser = 0;
            $is_areabonus_loser = 0;
            $is_globalbonus_loser = 0;
            if($order_obj['luckyspell_id'] > 0){
                //如果是幸运拼活动订单 查看是否有开启失败结算，有则不执行
                $addonsConfServer = new AddonsConfigSer();
                $addons_info = $addonsConfServer->getAddonsConfig('luckyspell', $order_obj['website_id']);
                $addons_data = $addons_info['value'];
                $bonus_val = json_decode($addons_data['bonus_val'],true);
                $is_distribution_loser = $bonus_val ? $bonus_val['is_distribution_loser'] : 0;
                $is_teambonus_loser = $bonus_val ? $bonus_val['is_teambonus_loser'] : 0;
                $is_areabonus_loser = $bonus_val ? $bonus_val['is_areabonus_loser'] : 0;
                $is_globalbonus_loser = $bonus_val ? $bonus_val['is_globalbonus_loser'] : 0;
            }
            if (getAddons('distribution', $order_obj['website_id']) == 1 && $is_distribution_loser != 1) {
                // 执行钩子:重新计算订单的佣金情况
                hook('updateCommissionMoney', ['order_id' => $order_id, 'website_id' => $order_goods_obj['website_id'], 'order_goods_id' => $order_goods_id]);

            }
            if (getAddons('globalbonus', $order_obj['website_id']) == 1 && $is_globalbonus_loser != 1) {
                // 执行钩子:重新计算全球分红情况
                hook('updateGlobalBonusMoney', ['order_id' => $order_id, 'website_id' => $order_goods_obj['website_id'], 'order_goods_id' => $order_goods_id]);

            }
            if (getAddons('areabonus', $order_obj['website_id']) == 1 && $is_areabonus_loser != 1) {
                // 执行钩子:重新计算区域分红情况
                hook('updateAreaBonusMoney', ['order_id' => $order_id, 'website_id' => $order_goods_obj['website_id'], 'order_goods_id' => $order_goods_id]);

            }
            if (getAddons('teambonus', $order_obj['website_id']) && $is_teambonus_loser != 1) {
                // 执行钩子:重新计算团队分红情况
                hook('updateTeamBonusMoney', ['order_id' => $order_id, 'website_id' => $order_goods_obj['website_id'], 'order_goods_id' => $order_goods_id]);

            }
            if (getAddons('microshop', $order_obj['website_id'], $this->instance_id)) {
                // 执行钩子:重新计算微店情况
                hook('updateMicroShopMoney', ['order_id' => $order_id, 'website_id' => $order_goods_obj['website_id'], 'order_goods_id' => $order_goods_id]);

            }

            $order_goods_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            $order_goods_model->rollback();
        }

    }

    /**
     * ***********************************************平台账户计算--End******************************************************
     */
    /**
     * 查询店铺的退货地址列表
     * @param $shop_id
     * @param $website_id
     */
    public function getShopReturnList($shop_id, $website_id, $page_index = 1, $page_size = 0, $order = '', $field = '*')
    {
        $shop_return = new VslOrderShopReturnModel();
        $list = $shop_return->pageQuery($page_index, $page_size, ['shop_id' => $shop_id, 'website_id' => $website_id], $order, $field);

        // $list = $shop_return->getQuery([
        //     'shop_id' => $shop_id,
        //     'website_id' => $website_id
        // ], '*', 'is_default desc');
        if ($list) {
            $address = new Address();
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['province_name'] = $address->getProvinceName($v['province']);
                $list['data'][$k]['city_name'] = $address->getCityName($v['city']);
                $list['data'][$k]['dictrict_name'] = $address->getDistrictName($v['district']);
            }
        }

        return $list;
    }

    /**
     * 查询店铺的退货设置
     * @param $return_id
     * @param $shop_id
     * @param $website_id
     */
    public function getShopReturn($return_id, $shop_id, $website_id, $supplier_id = 0)
    {
        $shop_return = new VslOrderShopReturnModel();
        $shop_return_obj = $shop_return->getInfo(['return_id' => $return_id, 'shop_id' => $shop_id, 'website_id' => $website_id, 'supplier_id' => $supplier_id]);
        return $shop_return_obj;
    }

    /**
     *
     * 更新店铺的退货信息
     * (non-PHPdoc)
     */
    public function updateShopReturnSet($shop_id, $return_id, $consigner, $mobile, $province, $city, $district, $address, $zip_code, $is_default, $supplier_id = 0)
    {
        $shop_return = new VslOrderShopReturnModel();
        $data = array(
            "consigner" => $consigner,
            "mobile" => $mobile,
            "province" => $province,
            "city" => $city,
            "district" => $district,
            "address" => $address,
            "zip_code" => $zip_code,
            "is_default" => $is_default,
            "supplier_id" => $supplier_id,
        );
        if ($is_default == 1) {
            $shop_return->save(['is_default' => 0], ['shop_id' => $shop_id, 'website_id' => $this->website_id, 'supplier_id' => $supplier_id]);
        }
        if ($return_id > 0) {
            $data['modify_time'] = time();
            $result = $shop_return->save($data, ['return_id' => $return_id]);
        } else {
            $data['shop_id'] = $shop_id;
            $data['website_id'] = $this->website_id;
            $data['supplier_id'] = $supplier_id;
            $data['create_time'] = time();
            $result = $shop_return->insert($data);
        }
        return $result;
    }

    /**
     * 删除店铺的退货信息
     */
    public function deleteShopReturnSet($shop_id, $return_id)
    {
        $shop_return = new VslOrderShopReturnModel();
        $result = $shop_return->where(['return_id' => $return_id, 'shop_id' => $shop_id])->delete();
        return $result;
    }

    /**
     * 得到订单的发货信息
     *
     * @param unknown $order_ids
     */
    public function getOrderGoodsExpressDetail($order_ids, $shop_id)
    {
        $order_goods_model = new VslOrderGoodsModel();
        $order_model = new VslOrderModel();
        $order_goods_express = new VslOrderGoodsExpressModel();
        // 查询订单的订单项的商品信息
        $order_goods_list = $order_goods_model->where(" order_id in ($order_ids)")->select();

        for ($i = 0; $i < count($order_goods_list); $i++) {
            $order_id = $order_goods_list[$i]["order_id"];
            $order_goods_id = $order_goods_list[$i]["order_goods_id"];
            $order_obj = $order_model->get($order_id);
            $order_goods_list[$i]["order_no"] = $order_obj["order_no"];
            $goods_express_obj = $order_goods_express->where("FIND_IN_SET($order_goods_id,order_goods_id_array)")->select();
            if (!empty($goods_express_obj)) {
                $order_goods_list[$i]["express_company"] = $goods_express_obj[0]["express_company"];
                $order_goods_list[$i]["express_no"] = $goods_express_obj[0]["express_no"];
            } else {
                $order_goods_list[$i]["express_company"] = "";
                $order_goods_list[$i]["express_no"] = "";
            }
        }
        return $order_goods_list;
    }

    /**
     * 通过订单id 得到 该订单的发货物流
     *
     * @param unknown $order_id
     */
    public function getOrderGoodsExpressList($order_id)
    {
        $order_goods_express_model = new VslOrderGoodsExpressModel();
        $express_list = $order_goods_express_model->getQuery([
            "order_id" => $order_id
        ], "*", "");
//        $express_list = $order_goods_express_model::all(['order_id' => $order_id],['express_company']);
        return $express_list;
    }

    /**
     * 添加卖家对订单的备注
     * @param array $data
     *
     * @return int $ret_val
     */
    public function addOrderSellerMemoNew(array $data)
    {
        //供应商
        if ($this->supplier_id) {
            $supplierSer = new SupplierService();
            $uid = $supplierSer->getUserInfoBySupplierId($this->supplier_id, 'uid')['uid'];
            $data['uid'] = $uid;
        }
        $order_memo_model = new VslOrderMemoModel();
        $ret_val = $order_memo_model->save($data);
        return $ret_val;
    }

    /**
     * 获取订单备注信息
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderRemark()
     */
    public function getOrderSellerMemo($order_id)
    {
        $order = new VslOrderModel();
        $res = $order->getQuery([
            'order_id' => $order_id
        ], "seller_memo", '');
        $seller_memo = "";
        if (!empty($res[0]['seller_memo'])) {
            $seller_memo = $res[0]['seller_memo'];
        }
        return $seller_memo;
    }

    /**
     * 获取订单备注
     * @param array $condition
     * @param string $order
     *
     * @return $memo_lists
     */
    public function getOrderMemoNew(array $condition, $order = 'id DESC')
    {
        $order_memo_model = new VslOrderMemoModel();
        $memo_lists = $order_memo_model->getQuery($condition, '*', $order);
        return $memo_lists;
    }

    /**
     * 得到订单的收货地址
     *
     * @param unknown $order_id
     * @return unknown
     */
    public function getOrderReceiveDetail($order_id)
    {
        $order = new VslOrderModel();
        $res = $order->getInfo([
            'order_id' => $order_id
        ], "order_id,receiver_mobile,receiver_province,receiver_city,receiver_district,receiver_address,receiver_zip,receiver_name", '');
        return $res;
    }

    /**
     * 更新订单的收货地址
     *
     * @param unknown $order_id
     * @param unknown $receiver_mobile
     * @param unknown $receiver_province
     * @param unknown $receiver_city
     * @param unknown $receiver_district
     * @param unknown $receiver_address
     * @param unknown $receiver_zip
     * @param unknown $receiver_name
     */
    public function updateOrderReceiveDetail($order_id, $receiver_mobile, $receiver_province, $receiver_city, $receiver_district, $receiver_address, $receiver_zip, $receiver_name)
    {
        $order = new VslOrderModel();
        $data = array(
            'receiver_mobile' => $receiver_mobile,
            'receiver_province' => $receiver_province,
            'receiver_city' => $receiver_city,
            'receiver_district' => $receiver_district,
            'receiver_address' => $receiver_address,
            'receiver_zip' => $receiver_zip,
            'receiver_name' => $receiver_name
        );
        $retval = $order->save($data, [
            'order_id' => $order_id
        ]);
        return $retval;
    }

    public function getOrderNumByOrderStatu($condition)
    {
        $order = new VslOrderModel();
        return $order->getCount($condition);
    }


    /**
     *
     * 查询会员的某个订单的条数
     * (non-PHPdoc)
     *
     * @see \data\api\IOrder::getUserOrderDetailCount()
     */
    public function getUserOrderDetailCount($user_id, $order_id)
    {
        $orderModel = new VslOrderModel();
        $condition = array(
            "buyer_id" => $user_id,
            "order_id" => $order_id
        );
        $order_count = $orderModel->getCount($condition);
        return $order_count;
    }

    /**
     * 查询会员某个条件的订单的条数
     *
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getUserOrderCountByCondition($condition)
    {
        $orderModel = new VslOrderModel();
        $order_count = $orderModel->getCount($condition);
        return $order_count;
    }

    /**
     * 删除订单
     *
     * @param unknown $order_id
     *            订单id
     * @param unknown $operator_type
     *            操作人类型 1商家 2用户
     * @param unknown $operator_id
     *            操作人id
     */
    public function deleteOrder($order_id, $operator_type, $operator_id)
    {
        $order_model = new VslOrderModel();
        $data = array(
            "is_deleted" => 1,
            "operator_type" => $operator_type,
            "operator_id" => $operator_id
        );
        $order_id_array = explode(',', $order_id);
        if ($operator_type == 1) {
            // 商家删除 目前之针对已关闭订单
            $res = $order_model->save($data, [
                "order_status" => 5,
                "order_id" => [
                    "in",
                    $order_id_array
                ],
                "shop_id" => $operator_id
            ]);
        } elseif ($operator_type == 2) {
            // 用户删除
            $res = $order_model->save($data, [
                "order_status" => 5,
                "order_id" => [
                    "in",
                    $order_id_array
                ],
                "buyer_id" => $operator_id
            ]);
        }
        return 1;
    }

    /**
     * 根据外部交易号查询订单编号，为了兼容多店版。所以返回一个数组
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderNoByOutTradeNo()
     */
    public function getOrderNoByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslOrderModel();
            $list = $order_model->getQuery([
                'out_trade_no' => $out_trade_no
            ], 'order_no', '');
            return $list;
        }
        return [];
    }

    /**
     * 根据外部交易号查询订单编号，为了兼容多店版。所以返回一个数组
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderNoByOutTradeNo()
     */
    public function getIntermentOrderNoByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslIncreMentOrderModel();
            $list = $order_model->getQuery([
                'out_trade_no' => $out_trade_no
            ], 'order_no', '');
            return $list;
        }
        return [];
    }

    /**
     *
     * 根据外部交易号查询订单状态
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderStatusByOutTradeNo()
     */
    public function getOrderStatusByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            if (strstr($out_trade_no, 'QD')) {
                $order_model = new VslChannelOrderModel();
            } else {
                $order_model = new VslOrderModel();
            }
            $order_status = $order_model->getInfo([
                'out_trade_no' => $out_trade_no
            ], 'order_status', '');
            return $order_status;
        }
        return 0;
    }

    /**
     *
     * 根据外部交易号查询增值订单状态
     *
     * @ERROR!!!
     *
     * @see \data\api\IOrder::getOrderStatusByOutTradeNo()
     */
    public function getIntermentOrderStatusByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslIncreMentOrderModel();
            $order_status = $order_model->getInfo([
                'out_trade_no' => $out_trade_no
            ], 'order_status');
            return $order_status;
        }
        return 0;
    }

    /**
     * 根据订单项id查询订单退款账户记录
     *
     * {@inheritdoc}
     *
     * @see \data\api\IOrder::getOrderRefundAccountRecordsByOrderGoodsId()
     */
    public function getOrderRefundAccountRecordsByOrderGoodsId($order_goods_id)
    {
        $model = new VslOrderRefundAccountRecordsModel();
        $info = $model->getInfo([
            "order_goods_id" => $order_goods_id
        ], "*");
        return $info;
    }

    /**
     * 获取快递单打印内容
     * @param unknown $order_ids
     * @param unknown $shop_id
     */
    public function getOrderPrint($order_ids, $shop_id)
    {
        $order_goods_model = new VslOrderGoodsModel();
        $order_model = new VslOrderModel();
        $order_goods_express = new VslOrderGoodsExpressModel();
        // 查询订单的订单项的商品信息
        $order_id_array = explode(',', $order_ids);
        $order_goods_list = array();
        foreach ($order_id_array as $order_id) {
            $order_express_list = $order_goods_express->getQuery(["order_id" => $order_id], "*", "");
            if (!empty($order_express_list) && count($order_express_list) > 0) {
                $express_order_goods_ids = "";
                foreach ($order_express_list as $order_express_obj) {
                    $order_goods_id_array = $order_express_obj["order_goods_id_array"];
                    if (!empty($express_order_goods_ids)) {
                        $express_order_goods_ids .= "," . $order_goods_id_array;
                    } else {
                        $express_order_goods_ids = $order_goods_id_array;
                    }
                    $order_goods_list_print = $order_goods_model->where("FIND_IN_SET(order_goods_id, '$order_goods_id_array') and order_id=$order_id and shop_id=$shop_id")->select();
                    $order_print_item = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 1, $order_express_obj["express_company_id"], $order_express_obj["express_name"], $order_express_obj["express_no"], $order_express_obj["id"]);
                    $order_goods_list[] = $order_print_item;
                }
                unset($order_express_obj);
                $order_goods_list_print = $order_goods_model->where("FIND_IN_SET(order_goods_id, '$express_order_goods_ids')=0 and order_id=$order_id and shop_id=$shop_id")->select();
                if (!empty($order_goods_list_print) && count($order_goods_list_print) > 0) {
                    $order_print_item = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 0, 0, "", "");
                    $order_goods_list[] = $order_print_item;
                }
            } else {
                $order_goods_list_print = $order_goods_model->where("order_id=$order_id and shop_id=$shop_id")->select();
                $order_print_item = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 0, 0, "", "");
                $order_goods_list[] = $order_print_item;
            }
        }
        unset($order_id);
        return $order_goods_list;
    }

    /**
     * 处理订单打印数据
     * @param unknown $order_id
     * @param unknown $order_goods_list_print
     * @param unknown $is_express
     * @param unknown $express_company_id
     * @param unknown $express_company_name
     * @param unknown $express_no
     * @param number $express_id
     */
    public function dealPrintOrderGoodsList($order_id, $order_goods_list_print, $is_express, $express_company_id, $express_company_name, $express_no, $express_id = 0)
    {
        $order_goods_item_obj = array();
        $order_goods_ids = "";
        $print_goods_array = array();
        $is_print = 1;
        $tmp_express_company = "";
        $tmp_express_company_id = 0;
        $tmp_express_no = "";
        foreach ($order_goods_list_print as $k => $order_goods_print_obj) {
            $print_goods_array[] = array(
                "goods_id" => $order_goods_print_obj["goods_id"],
                "goods_name" => $order_goods_print_obj["goods_name"],
                "sku_id" => $order_goods_print_obj["sku_id"],
                "sku_name" => $order_goods_print_obj["sku_name"]
            );
            if (!empty($order_goods_ids)) {
                $order_goods_ids = $order_goods_ids . "," . $order_goods_print_obj["order_goods_id"];
            } else {
                $order_goods_ids = $order_goods_print_obj["order_goods_id"];
            }
            if ($k == 0) {
                $tmp_express_company = $order_goods_print_obj["tmp_express_company"];
                $tmp_express_company_id = $order_goods_print_obj["tmp_express_company_id"];
                $tmp_express_no = $order_goods_print_obj["tmp_express_no"];
            }
        }
        unset($order_goods_print_obj);
        if (empty($tmp_express_company) || empty($tmp_express_company_id) || empty($tmp_express_no)) {
            $is_print = 0;
        }
        if ($is_express == 0) {
            $express_company_id = $tmp_express_company_id;
            $express_company_name = $tmp_express_company;
            $express_no = $tmp_express_no;
        }
        $order_model = new VslOrderModel();
        $order_obj = $order_model->get($order_id);
        $order_goods_item_obj = array(
            "order_id" => $order_id,
            "order_goods_ids" => $order_goods_ids,
            "goods_array" => $print_goods_array,
            "is_devlier" => $is_express,
            "is_print" => $is_print,
            "express_company_id" => $express_company_id,
            "express_company_name" => $express_company_name,
            "express_no" => $express_no,
            "order_no" => $order_obj["order_no"],
            "express_id" => $express_id
        );
        return $order_goods_item_obj;
    }

    /**
     * 通过订单id获取未发货订单项
     * @param unknown $order_ids
     */
    public function getNotshippedOrderByOrderId($order_ids)
    {
        $order_goods_model = new VslOrderGoodsModel();
        $order_id_array = explode(',', $order_ids);
        $order_goods_list = array();
        foreach ($order_id_array as $order_id) {
            $order_goods_list_print = $order_goods_model->getQuery(["order_id" => $order_id, "shipping_status" => 0, "refund_status" => 0], "*", "");
            $order_goods_item = $this->dealPrintOrderGoodsList($order_id, $order_goods_list_print, 0, $order_goods_list_print[0]["tmp_express_company_id"], $order_goods_list_print[0]["tmp_express_company"], $order_goods_list_print[0]["tmp_express_no"]);
            $order_goods_list[] = $order_goods_item;
        }
        unset($order_id);
        return $order_goods_list;
    }

    public function getBusinessProfileList($start_date, $end_date, $condition)
    {
        // TODO Auto-generated method stub
        $order_model = new VslOrderModel();
        $order_list = $order_model->getQuery($condition, '(pay_money+user_platform_money) as allmoney,create_time,order_status', '');
        $list = array();
        for ($start = $start_date; $start <= $end_date; $start += 24 * 3600) {
            $list[date("Y-m-d", $start)] = array();
            $allCount = 0;
            $payCount = 0;
            $returnCount = 0;
            $sum = 0.00;
            foreach ($order_list as $v) {
                if (date("Y-m-d", $v["create_time"]) == date("Y-m-d", $start)) {
                    $allCount = $allCount + 1;
                    if ($v['order_status'] > 0 && $v['order_status'] < 5) {
                        $sum = $sum + $v['allmoney'];
                        $payCount = $payCount + 1;
                    }
                    if ($v['order_status'] < 0) {
                        $returnCount = $returnCount + 1;
                    }
                }
            }
            unset($v);
            $list[date("Y-m-d", $start)]['count'] = $allCount;
            $list[date("Y-m-d", $start)]['paycount'] = $payCount;
            $list[date("Y-m-d", $start)]['returncount'] = $returnCount;
            $list[date("Y-m-d", $start)]['sum'] = $sum;
        }
        return $list;
    }

    public function getOrderPaymentByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslOrderPaymentModel();
            $order = $order_model->getInfo([
                'out_trade_no' => $out_trade_no
            ], '*');
            return $order;
        }
        return false;
    }

    public function getIncrementOrderPaymentByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslIncreMentOrderPaymentModel();
            $order = $order_model->getInfo([
                'out_trade_no' => $out_trade_no
            ], '*');
            return $order;
        }
        return false;
    }

    /*
     * 根据订单交易号关闭订单
     */
    public function orderCloseByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslOrderModel();
            $orderList = $order_model->getQuery(['out_trade_no' => $out_trade_no, 'website_id' => $this->website_id, 'order_status' => 0], 'order_id', 'create_time desc');
            if (!$orderList) {
                return false;
            }
            foreach ($orderList as $val) {
                $this->orderClose($val['order_id']);
            }
            unset($val);
            return true;
        }
        return false;
    }

    /*
     * 根据订单交易号关闭订单
     */
    public function incrementOrderCloseByOutTradeNo($out_trade_no)
    {
        if (!empty($out_trade_no)) {
            $order_model = new VslIncreMentOrderModel();
            $orderList = $order_model->getQuery(['out_trade_no' => $out_trade_no, 'website_id' => $this->website_id, 'order_status' => 0], 'order_id,order_type', 'create_time desc');
            if (!$orderList) {
                return false;
            }
            foreach ($orderList as $val) {
                if (in_array($val['order_type'], ['4', '5'])) {
                    //版本续费、升级需要关闭
                    $order_model->save(['order_status' => 1], ['out_trade_no' => $out_trade_no]);
                    continue;
                }
                $this->orderClose($val['order_id']);
            }
            unset($val);
            return true;
        }
        return false;
    }

    public function cancelOrder($order_id)
    {
        $order_model = new VslIncreMentOrderModel();
        $res = $order_model->save(['order_status' => 1], ['order_id' => $order_id, 'website_id' => $this->website_id]);
        return $res;
    }

    public function getIncrementOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $order_model = new VslIncreMentOrderModel();
        $moduleModel = new \data\model\ModuleModel();
        // 查询主表
        $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (!empty($order_list['data'])) {
            foreach ($order_list['data'] as $k => $v) {
                if ($v['order_type'] == 1) {
                    $addons = new SysAddonsModel();
                    $module_info = $addons->getInfo(['id' => $v['addons_id']]);
                    $module = $moduleModel->getInfo(['module_id' => $module_info['module_id']]);
                    $info['cycle_price'] = $module_info['cycle_price'] ? json_decode(str_replace("&quot;", "\"", $module_info['cycle_price']), true) : '';
                    $order_list['data'][$k]['module_name'] = $module_info['title'];
                    $order_list['data'][$k]['logo'] = $module_info['logo'];
                    $order_list['data'][$k]['logo_small'] = $module_info['logo_small'];
                    $order_list['data'][$k]['title'] = $module_info['title'];
                    $order_list['data'][$k]['url'] = $module['url'];
                    if ($info['cycle_price']) {
                        foreach ($info['cycle_price'] as $k1 => $value) {
                            if ($v['circle_time'] == $value['cycle']) {
                                $order_list['data'][$k]['market_price'] = $value['market_price'];
                            }
                        }
                        unset($value);
                    }
                    if ($v['circle_time'] == 1) {
                        $order_list['data'][$k]['time'] = '一个月';
                    }
                    if ($v['circle_time'] == 2) {
                        $order_list['data'][$k]['time'] = '三个月';
                    }
                    if ($v['circle_time'] == 3) {
                        $order_list['data'][$k]['time'] = '五个月';
                    }
                    if ($v['circle_time'] == 4) {
                        $order_list['data'][$k]['time'] = '一年';
                    }
                    if ($v['circle_time'] == 5) {
                        $order_list['data'][$k]['time'] = '两年';
                    }
                    if ($v['circle_time'] == 6) {
                        $order_list['data'][$k]['time'] = '三年';
                    }
                    if ($v['circle_time'] == 7) {
                        $order_list['data'][$k]['time'] = '四年';
                    }
                } elseif ($v['order_type'] == 2) {
                    $order_list['data'][$k]['module_name'] = '短信套餐';
                    $order_list['data'][$k]['logo'] = '';
                    $order_list['data'][$k]['title'] = '短信套餐';
                    $order_list['data'][$k]['market_price'] = $v['order_money'];
                    $order_list['data'][$k]['time'] = $v['circle_time'] . '条';
                } elseif (in_array($v['order_type'], [4, 5])) {
                    //版本续费、充值
                    $order_list['data'][$k]['title'] = $v['order_type'] == 4 ? '版本续费' : '版本升级';
                    $order_list['data'][$k]['market_price'] = $v['order_money'];
                    $web = new WebSite();
                    $webInfo = $web->getWebSiteInfo($this->website_id);
                    $order_list['data'][$k]['logo'] = $webInfo['logo'] ?: '';
                    $order_list['data'][$k]['time'] = $v['circle_time'] ? number2chinese($v['circle_time']) . '年' : '';
                }
                if ($v['order_status'] == 1) {
                    $order_list['data'][$k]['status_name'] = '已关闭';
                }
                if ($v['order_status'] == 0) {
                    $order_list['data'][$k]['status_name'] = '待付款';
                }
                if ($v['order_status'] == 2) {
                    $order_list['data'][$k]['status_name'] = '已支付';
                }
            }
            unset($v);
        }
        return $order_list;
    }

    public function getIncrementOrderDetail($order_id)
    {
        $order_model = new VslIncreMentOrderModel();
        // 查询主表
        $order_list = $order_model->getInfo(['order_id' => $order_id], '*');
        $order_list['close_time'] = date('Y-m-d H:i:s', $order_list['create_time'] + (30 * 60));
        if ($order_list['order_type'] == 1) {
            $addons = new SysAddonsModel();
            $module_info = $addons->getInfo(['id' => $order_list['addons_id']]);
            $info['cycle_price'] = $module_info['cycle_price'] ? json_decode(str_replace("&quot;", "\"", $module_info['cycle_price']), true) : '';
            $order_list['module_name'] = $module_info['title'];
            $order_list['logo'] = $module_info['logo'];
            $order_list['description'] = $module_info['description'];
            foreach ($info['cycle_price'] as $k1 => $value) {
                if ($order_list['circle_time'] == $value['cycle']) {
                    $order_list['market_price'] = $value['market_price'];
                }
            }
            unset($value);
            if ($order_list['circle_time'] == 1) {
                $order_list['time'] = '一个月';
            }
            if ($order_list['circle_time'] == 2) {
                $order_list['time'] = '三个月';
            }
            if ($order_list['circle_time'] == 3) {
                $order_list['time'] = '五个月';
            }
            if ($order_list['circle_time'] == 4) {
                $order_list['time'] = '一年';
            }
            if ($order_list['circle_time'] == 5) {
                $order_list['time'] = '两年';
            }
            if ($order_list['circle_time'] == 6) {
                $order_list['time'] = '三年';
            }
            if ($order_list['circle_time'] == 7) {
                $order_list['time'] = '四年';
            }
        } elseif ($order_list['order_type'] == 2) {
            $order_list['module_name'] = '短信套餐';
            $order_list['logo'] = '';
            $order_list['market_price'] = $order_list['order_money'];
        } elseif (in_array($order_list['order_type'], [4, 5])) {
            //版本续费、充值
            $order_list['module_name'] = $order_list['order_type'] == 4 ? '版本续费' : '版本升级';
            $order_list['market_price'] = $order_list['order_money'];
            $web = new WebSite();
            $webInfo = $web->getWebSiteInfo($this->website_id);
            $order_list['logo'] = $webInfo['logo'] ?: '';
            $order_list['time'] = $order_list['circle_time'] ? number2chinese($order_list['circle_time']) . '年' : '';
        }
        if ($order_list['order_status'] == 1) {
            $order_list['status_name'] = '已关闭';
        }
        if ($order_list['order_status'] == 0) {
            $order_list['status_name'] = '待付款';
        }
        if ($order_list['order_status'] == 2) {
            $order_list['status_name'] = '已支付';
        }
        return $order_list;
    }

    public function updateOrder(array $condition, array $data)
    {
        $order_model = new VslOrderModel();
        return $order_model->save($data, $condition);
    }

    /*
     * 根据外部交易号获取订单id
     */
    public function getOrderIdByOutno($out_trade_no)
    {
        $orderModel = new VslOrderModel();
        $orderIds = $orderModel->Query(['out_trade_no' => $out_trade_no], 'order_id');
        if (count($orderIds) > 1) {//多单暂时不需要返回
            return 0;
        }
        return $orderIds[0];
    }

    /**
     * 通过订单order_id查询订单支付对应的form_id
     * @param $order_id
     * @return string $form_id [小程序模板消息提交form_id]
     */
    public function getOrderPaymentFormIdByOrderId($order_id)
    {
        if (empty($order_id)) {
            return false;
        }
        $orderModel = new VslOrderModel();
        $trade_result = $orderModel->getInfo(['website_id' => $this->website_id, 'order_id' => $order_id], 'out_trade_no');
        if (empty($trade_result)) {
            return false;
        }
        $orderPaymentModel = new VslOrderPaymentModel();
        $form_result = $orderPaymentModel->getInfo(['website_id' => $this->website_id, 'out_trade_no' => $trade_result['out_trade_no']], 'form_id');
        return $form_result['form_id'];
    }

    /**
     * 通过订单order_id设置订单支付对应的form_id
     * @param $order_id
     * @param $form_id
     * @return string $form_id [小程序模板消息提交form_id]
     */
    public function setOrderPaymentFormIdByOrderId($order_id, $form_id)
    {
        $orderModel = new VslOrderModel();
        $trade_result = $orderModel->getInfo(['website_id' => $this->website_id, 'order_id' => $order_id], 'out_trade_no');
        if (empty($trade_result)) {
            return false;
        }
        $orderPaymentModel = new VslOrderPaymentModel();
        $result = $orderPaymentModel->save(['form_id' => $form_id], ['website_id' => $this->website_id, 'out_trade_no' => $trade_result['out_trade_no']]);

        return $result;
    }

    /**
     * 通过订单商品表订单商品表id获取form_id
     * @param $order_goods_id [订单商品表id]
     * @return string
     */
    public function getOrderRefundFormIdByOrderGoodsId($order_goods_id)
    {
        $order_goods = new VslOrderGoodsModel();
        $result = $order_goods->getInfo(['website_id' => $this->website_id, 'order_goods_id' => $order_goods_id], 'form_id');
        if ($result['form_id']) {
            return $result['form_id'];
        }
        return '';
    }

    /**
     * 重组创建门店订单所需数组
     */
    public function calculateCreateStoreOrderDataTesy(array $order_data)
    {
        $now_time = time();
        $man_song_rule_model = getAddons('fullcut', $this->website_id) ? new VslPromotionMansongRuleModel() : '';
        $coupon_model = getAddons('coupontype', $this->website_id) ? new VslCouponModel() : '';
        $promotion_discount_model = getAddons('discount', $this->website_id, $this->instance_id) ? new VslPromotionDiscountModel() : '';
        $user_model = new UserModel();
        $sec_service = getAddons('seckill', $this->website_id, $this->instance_id) ? new seckillServer() : '';
        $goodsExpress = new GoodsExpress();
        $group_server = getAddons('groupshopping', $this->website_id, $this->instance_id) ? new GroupShopping() : '';
        $storeServer = getAddons('store', $this->website_id, $this->instance_id) ? new Store() : '';
        $user_info = $user_model::get($this->uid, ['member_info.level', 'member_account', 'member_address']);
        $return_data['address_id'] = $order_data['address_id'];
        $return_data['group_id'] = $order_data['group_id'];
        $return_data['record_id'] = $order_data['record_id'];
        $return_data['shipping_type'] = $order_data['shipping_type'];
        $return_data['is_deduction'] = $order_data['is_deduction'];
        $redis = connectRedis();
        foreach ($order_data['shop_list'] as $shop) {
            $shop_id = $shop['shop_id'];
            $return_data['shop'][$shop_id]['store_id'] = $shop['store_id'] ?: 0;
            $return_data['shop'][$shop_id]['card_store_id'] = (empty($shop['card_store_id'])) ? 0 : $shop['card_store_id'];
            $return_data['shop'][$shop_id]['shop_channel_amount'] = 0;
            $return_data['shop'][$shop_id]['leave_message'] = $shop['leave_message'] ?: '';
            //处理店铺商品
            foreach ($shop['goods_list'] as $k => $sku_info) {
                //循环 处理单商品信息
                if ($shop['store_id']) {
                    $sku_model = new VslStoreGoodsSkuModel();
                    $sku_db_info = $sku_model::get(['sku_id' => $sku_info['sku_id'], 'store_id' => $shop['store_id']], ['goods']);
                } elseif ($shop['card_store_id']) {
                    //计时计次商品
                    $sku_model = new VslStoreGoodsSkuModel();
                    $sku_db_info = $sku_model::get(['sku_id' => $sku_info['sku_id'], 'store_id' => $shop['card_store_id']], ['goods']);
                } else {
                    $sku_model = new VslGoodsSkuModel();
                    $sku_db_info = $sku_model::get($sku_info['sku_id'], ['goods']);
                }

                $temp_sku_id = $sku_info['sku_id'];
                $return_data['order'][$shop_id]['sku'][$k]['sku_id'] = $temp_sku_id;
                $return_data['order'][$shop_id]['sku'][$k]['goods_id'] = $sku_info['goods_id'];
                $return_data['order'][$shop_id]['sku'][$k]['channel_id'] = $sku_info['channel_id'];
                $return_data['order'][$shop_id]['sku'][$k]['seckill_id'] = $sku_info['seckill_id'];
                $return_data['order'][$shop_id]['sku'][$k]['seckill_id'] = $sku_info['seckill_id'];
                if ($sku_info['presell_id'] && getAddons('presell', $this->website_id, $this->instance_id)) {
                    //判断预售是否关闭或者过期
                    $presell_id = $sku_info['presell_id'];
                    //如果是预售的商品，则更改其单价为预售价
                    $presell_mdl = new VslPresellModel();
                    $presell_condition['p.id'] = $presell_id;
                    $presell_condition['p.start_time'] = ['<', time()];
                    $presell_condition['p.end_time'] = ['>=', time()];
                    $presell_condition['p.status'] = ['neq', 3];
                    $presell_condition['pg.sku_id'] = $sku_info['sku_id'];
                    $presell_goods_info = $presell_mdl->alias('p')->where($presell_condition)->join('vsl_presell_goods pg', 'p.id = pg.presell_id', 'LEFT')->find();
                    if (!$presell_goods_info) {
                        return ['result' => -2, 'message' => '预售活动已过期或已关闭'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['presell_id'] = $sku_info['presell_id'];
                } else {
                    $return_data['order'][$shop_id]['sku'][$k]['presell_id'] = 0;
                }

                $return_data['order'][$shop_id]['sku'][$k]['bargain_id'] = $sku_info['bargain_id'];
                $return_data['order'][$shop_id]['sku'][$k]['price'] = $sku_info['price'];
                //会员价
                $return_data['order'][$shop_id]['sku'][$k]['member_price'] = $sku_info['member_price'];

                $return_data['order'][$shop_id]['sku'][$k]['discount_id'] = $sku_info['discount_id'];
                $return_data['order'][$shop_id]['sku'][$k]['discount_price'] = $sku_info['discount_price'];
                $return_data['order'][$shop_id]['sku'][$k]['num'] = $sku_info['num'];
                $return_data['order'][$shop_id]['sku'][$k]['shop_id'] = $sku_db_info->goods->shop_id;
                $return_data['order'][$shop_id]['sku'][$k]['point_deduction_max'] = $sku_db_info->goods->point_deduction_max;
                $return_data['order'][$shop_id]['sku'][$k]['point_return_max'] = $sku_db_info->goods->point_return_max;

                $return_data['shop'][$shop_id]['goods_id_array'][] = $sku_info['goods_id'];
                $return_data['shop'][$shop_id]['member_amount'] += $return_data['order'][$shop_id]['sku'][$k]['member_price'] * $sku_info['num'];
                $return_data['shop'][$shop_id]['shop_total_amount'] += $sku_info['price'] * $sku_info['num'];
                $return_data['shop'][$shop_id]['shop_discount_amount'] += $sku_info['discount_price'] * $sku_info['num'];

                if (getAddons('groupshopping', $this->website_id, $this->instance_id)) {
                    $is_group = $group_server->isGroupGoods($sku_info['goods_id']);//是否团购商品
                    $group_sku_info_obj = $group_server->getGroupSkuInfo(['sku_id' => $sku_info['sku_id'], 'goods_id' => $sku_info['goods_id'], 'group_id' => $is_group]);
                    $group_sku_info_arr = objToArr($group_sku_info_obj);//商品团购信息
                }
                if (!empty($sku_info['channel_id']) && getAddons('channel', $this->website_id)) {
                    $website_id = $this->website_id;
                    $channel_gs_mdl = new VslChannelGoodsSkuModel();
                    $channel_gs_info = $channel_gs_mdl->getInfo(['website_id' => $website_id, 'channel_id' => $sku_info['channel_id'], 'sku_id' => $sku_info['sku_id']], 'stock');
                    $channel_key = 'channel_' . $sku_info['channel_id'] . '_' . $sku_info['sku_id'];
                    if ($sku_info['num'] > $channel_gs_info['stock']) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($channel_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . '商品渠道商库存不足'];
                    }
                    $sku_db_info->stock = $channel_gs_info['stock'];
                    $return_data['shop'][$shop_id]['shop_channel_amount'] += $sku_info['discount_price'] * $sku_info['num'];
                }
                if (!empty($sku_info['seckill_id']) && getAddons('seckill', $this->website_id, $this->instance_id)) {
                    //判断秒杀商品是否过期
                    $sku_id = $sku_info['sku_id'];
                    $seckill_id = $sku_info['seckill_id'];
                    $condition_is_seckill['s.seckill_id'] = $seckill_id;
                    $condition_is_seckill['nsg.sku_id'] = $sku_id;
                    $is_seckill = $sec_service->isSeckillGoods($condition_is_seckill);
                    $redis_goods_sku_seckill_key = 'seckill_' . $seckill_id . '_' . $sku_info['goods_id'] . '_' . $sku_id;
                    if ($is_seckill) {
                        //获取秒杀商品的价格、库存、最大购买量
                        $condition_sku_info['seckill_id'] = $seckill_id;
                        $condition_sku_info['sku_id'] = $sku_id;
                        $sku_info_list = $sec_service->getSeckillSkuInfo($condition_sku_info);
                        $sku_info_arr = objToArr($sku_info_list);
                        //获取限购量
                        $goods_service = new Goods();
                        //通过用户累计购买量判断，先判断redis是否有内容
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $sku_info['seckill_id']);
                        if ($sku_info['num'] > $is_seckill['seckill_num']) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                        }
                        //判断是否超过限购
                        if ($sku_info_arr['seckill_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $sku_info_arr['seckill_limit_buy'])) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格购买数目已经超出最大秒杀限购数目'];
                        }
                        //价格
                        if ($is_seckill['seckill_price'] != $sku_info['price']) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 秒杀商品价格变动'];
                        }
                    } else {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($redis_goods_sku_seckill_key);
                        }
                        return ['result' => -2, 'message' => $sku_db_info->goods->goods_name . '商品秒杀已结束，将恢复正常商品价格。'];
                    }


                } elseif ($is_group && $order_data['group_id'] && getAddons('groupshopping', $this->website_id, $this->instance_id)) { //拼团 不确定 待测试
                    $goods_key = 'goods_' . $sku_info['goods_id'] . '_' . $sku_info['sku_id'];
                    if ($sku_info['num'] > $sku_db_info->stock) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }
                    if ($order_data['record_id']) {
                        $checkGroup = $group_server->checkGroupIsCan($order_data['record_id'], $this->uid);//判断该团购是否能参加
                        if ($checkGroup < 0) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($goods_key);
                            }
                            return ['result' => false, 'message' => '该团无法参加，请选择其他团或者自己发起团购'];
                        }
                    }
                    $goods_service = new Goods();
                    //通过用户累计购买量判断
                    $uid = $this->uid;
                    $website_id = $this->website_id;
                    $buy_num = $goods_service->getActivityOrderSkuForGroup($uid, $sku_info['sku_id'], $website_id, $order_data['group_id']);
                    if ($group_sku_info_arr['group_limit_buy'] != 0 && ($sku_info['num'] + $buy_num > $group_sku_info_arr['group_limit_buy'])) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大拼团限购数目'];
                    }
                    if ($sku_info['price'] != $group_sku_info_arr['group_price']) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 团购商品价格变动'];
                    }


                } elseif (!empty($sku_info['bargain_id']) && getAddons('bargain', $this->website_id, $this->instance_id)) {//砍价
                    $bargain_key = 'bargain_' . $sku_info['bargain_id'] . '_' . $sku_info['sku_id'];
                    $bargain = new Bargain();
                    $order_server = new \data\service\Order\Order();
                    $condition_bargain['bargain_id'] = $sku_info['bargain_id'];
                    $uid = $this->uid;
                    $condition_bargain['website_id'] = $this->website_id;
                    $is_bargain = $bargain->isBargain($condition_bargain, $uid);
                    if ($is_bargain && !empty(objToArr($is_bargain['my_bargain']))) {

                        $return_data['bargain_id'] = $sku_info['bargain_id'];
                        //库存
                        $bargain_stock = $is_bargain['bargain_stock'];
                        $limit_buy = $is_bargain['limit_buy'];
                        $price = $is_bargain['my_bargain']['now_bargain_money'];
                        $buy_num = $order_server->getActivityOrderSkuNum($uid, $sku_info['sku_id'], $this->website_id, 3, $sku_info['bargain_id']);
                        if ($sku_info['num'] > $bargain_stock) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($bargain_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出砍价活动库存'];
                        }
                        if ($limit_buy != 0 && ($sku_info['num'] + $buy_num > $limit_buy)) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($bargain_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 该商品规格您总共购买数目超出最大砍价限购数目'];
                        }
                        //价格
                        if ($sku_info['price'] != $price) {
                            for ($c = 0; $c < $sku_info['num']; $c++) {
                                $redis->incr($bargain_key);
                            }
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 砍价商品价格变动'];
                        }

                    } else {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($bargain_key);
                        }
                        return ['result' => -2, 'message' => '砍价活动已过期或已关闭'];
                    }
                } elseif (!empty($sku_info['presell_id']) && getAddons('presell', $this->website_id, $this->instance_id)) {
                    $presell_key = 'presell_' . $sku_info['presell_id'] . '_' . $sku_info['sku_id'];
                    $presell_service = new PresellService();
                    $count_people = $presell_service->getPresellBuyNum($sku_info['presell_id']);
                    $presell_info = $presell_service->getPresellBySku($sku_info['presell_id'], $sku_info['sku_id']);
                    if ($presell_info['presellnum'] < $count_people) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($presell_key);
                        }
                        return ['result' => false, 'message' => '预售商品库存不足'];
                    }
                    $user_buy = $presell_service->getUserCount($sku_info['presell_id']);//当前用户购买数
                    if ($user_buy > $presell_info['maxbuy']) {//当前用户购买数大于每人限购
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($presell_key);
                        }
                        return ['result' => false, 'message' => '您已达到商品预售购买上限'];
                    }
                    //付定金去掉运费
                    $shipping_fee_all_last = $shipping_fee_all[$shop_id];
                    $shipping_fee_all[$shop_id] = 0;


                } else {
                    if ($shop['store_id'] || $shop['card_store_id']) {
                        $goods_key = 'store_goods_' . $sku_info['goods_id'] . '_' . $sku_info['sku_id'];
                    } else {
                        $goods_key = 'goods_' . $sku_info['goods_id'] . '_' . $sku_info['sku_id'];
                    }
                    // 普通商品
                    //sku 信息检查
                    if ($sku_db_info->goods->state != 1) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 物品为不可购买状态'];
                    }
                    if ($sku_info['num'] > $sku_db_info->stock) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出库存'];
                    }
                    if (($sku_info['num'] > $sku_db_info->goods->max_buy) && $sku_db_info->goods->max_buy != 0) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 购买数目超出最大购买数目'];
                    }

                    if ($sku_info['price'] != $sku_db_info->price) {
                        for ($c = 0; $c < $sku_info['num']; $c++) {
                            $redis->incr($goods_key);
                        }
                        return ['result' => false, 'message' => $sku_info->goods->goods_name . ' 商品价格变动'];
                    }

                }

                // 限时折扣
                $discount_price = $sku_info['discount_price'];
                if (!empty($sku_info['discount_id'])) {
                    if (!getAddons('discount', $this->website_id)) {
                        return ['result' => false, 'message' => '限时折扣应用已关闭'];
                    }
                    $promotion_discount_info_db = $promotion_discount_model::get($sku_info['discount_id'], ['goods']);
                    if ($promotion_discount_info_db->status != 1) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . '限时折扣状态不可用'];
                    }
                    if ($promotion_discount_info_db->start_time > $now_time || $promotion_discount_info_db->end_time < $now_time) {
                        return ['result' => false, 'message' => '限时折扣不在可用时间内'];
                    }
                    if (($promotion_discount_info_db->range_type == 1 || $promotion_discount_info_db->range_type == 3) &&
                        ($promotion_discount_info_db->shop_id != $shop_id)) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 限时折扣不在可用范围内'];
                    }
                    if ($promotion_discount_info_db->range == 2) {
                        if ($promotion_discount_info_db->goods()->where(['goods_id' => ['=', $sku_db_info->goods_id]])->count() == 0) {
                            return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 商品不在限时折扣指定商品范围内'];
                        }
                    }

                    $member_discount_price = $sku_info['discount_price'];
                    $discount_price_1 = round(($promotion_discount_info_db->discount_num / 10) * $member_discount_price, 2);
                    // 限时抢购商品表的折扣
                    $goods_discount = $promotion_discount_info_db->goods()->where(['goods_id' => $sku_info['goods_id']])->find();
                    if ($goods_discount) {
                        $promotion_discount = $promotion_discount_model->where(['discount_id' => $goods_discount['goods_id']])->find();
                        if ($promotion_discount['integer_type'] == 1) {
                            $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price);
                        } else {
                            $discount_price_2 = round($goods_discount['discount'] / 10 * $member_discount_price, 2);
                        }

                        if ($goods_discount['discount_type'] == 2) {
                            $discount_price_2 = $goods_discount['discount'];
                        }
                    }

                    if ($discount_price != $discount_price_1 && $discount_price != $discount_price_2) {
                        return ['result' => false, 'message' => $sku_db_info->goods->goods_name . ' 商品折扣价格变化'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['promotion_shop_id'] = $promotion_discount_info_db->shop_id;// 限时折扣店铺id，用于识别优惠来源
                }
                //计时/次商品
                $goodsSer = new GoodsService();
                $goods_info = $goodsSer->getGoodsDetailById($sku_info['goods_id']);
                $return_data['order'][$shop_id]['sku'][$k]['shipping_fee'] = $goods_info['shipping_fee_type'] == 1 ? ($goods_info['shipping_fee'] ?: 0) : 0;
                $return_data['order'][$shop_id]['sku'][$k]['shipping_fee_type'] = $goods_info['shipping_fee_type'];
                if (getAddons('store', $this->website_id, $this->instance_id) && $goods_info['goods_type'] == 0) {
                    $return_data['address_id'] = 0;
                    if ($return_data['shop'][$shop_id]['card_store_id'] == 0) {
                        return ['result' => false, 'message' => '请选择使用门店'];
                    }
                    $return_data['order'][$shop_id]['sku'][$k]['card_store_id'] = $return_data['shop'][$shop_id]['card_store_id'];
                    $return_data['order'][$shop_id]['sku'][$k]['cancle_times'] = $goods_info['cancle_times'];
                    $return_data['order'][$shop_id]['sku'][$k]['cart_type'] = $goods_info['cart_type'];
                    if ($goods_info['valid_type'] == 1) {
                        $return_data['order'][$shop_id]['sku'][$k]['invalid_time'] = time() + $goods_info['valid_days'] * 24 * 60 * 60;
                    } else {
                        $return_data['order'][$shop_id]['sku'][$k]['invalid_time'] = $goods_info['invalid_time'];
                    }
                    if ($goods_info['is_wxcard'] == 1) {
                        $return_data['order'][$shop_id]['sku'][$k]['wx_card_id'] = $goods_info['wx_card_id'];
                        $ticket = new VslGoodsTicketModel();
                        $ticket_info = $ticket->getInfo(['goods_id' => $sku_info['goods_id']]);
                        $return_data['order'][$shop_id]['sku'][$k]['card_title'] = $ticket_info['card_title'];
                    }
                }
            }
            unset($sku_info);
        }
        unset($shop);
        return $return_data;

    }

    /**
     * 检测订单是否存在退款状态
     * 存在退款状态订单不允许确认收货
     */
    public function checkReturn($order_id)
    {
        $order = new VslOrderModel();
        $order_info = $order->getInfo(['order_id' => $order_id], '*'); //->count()
        $orderGoods = new VslOrderGoodsModel();
        // $order_info = $order->getInfo(['order_id' => $order_id], '*'); //->count()
        $order_goods_count = $orderGoods->where(" order_id = ($order_id)")->count();
        if ($order_goods_count <= 1) {
            return false;
        }
        $order_goods_list = $orderGoods->where(" order_id = ($order_id)")->select();

        //获取所有，查询是否存在售后商品
        // 1	买家申请	发起了退款申请,等待卖家处理
        // 2	等待买家退货	卖家已同意退款申请,等待买家退货
        // 3	等待卖家确认收货	买家已退货,等待卖家确认收货
        // 4	等待卖家确认退款	卖家同意退款
        // 0	退款已成功	卖家退款给买家，本次维权结束
        // -1	退款已拒绝	卖家拒绝本次退款，本次维权结束
        // -2	退款已关闭	主动撤销退款，退款关闭
        // -3	退款申请不通过	拒绝了本次退款申请,等待买家修改

        foreach ($order_goods_list as $key => $value) {
            if ($value['refund_status'] > 0 && $value['refund_status'] != 5) {
                //查询售后记录
                return true;
            } else {
                continue;
            }
        }
        unset($value);
        return false;
    }

    /**
     * 批量查找订单结果的佣金
     * @param type $orderIds
     */
    public function getOrderCommission($orderIds = [])
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $order_commission = new VslOrderDistributorCommissionModel();
        if ($this->instance_id) {
            $orders = $order_commission->where(['order_id' => ['in', $orderIds], 'shop_id' => $this->instance_id, 'return_status' => 0])->select();
        } else {
            $orders = $order_commission->where(['order_id' => ['in', $orderIds], 'return_status' => 0])->select();
        }

        if (!$orders) {
            return [];
        }
        $newArray = [];
        foreach ($orders as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']][] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的分红
     * @param type $orderIds
     * @param type $fromType
     */
    public function getOrderBonus($orderIds = [])
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $orderBonusLogModel = new VslOrderBonusLogModel();
        if ($this->instance_id) {
            $bonus = $orderBonusLogModel->where(['website_id' => $this->website_id, 'order_id' => ['in', $orderIds], 'shop_id' => $this->instance_id, 'area_return_status' => 0])->field('sum(area_bonus) as sum_area,sum(team_bonus) as sum_team,sum(global_bonus) as sum_global, order_id')->group('order_id')->select();
        } else {
            $bonus = $orderBonusLogModel->where(['website_id' => $this->website_id, 'order_id' => ['in', $orderIds], 'area_return_status' => 0])->field('sum(area_bonus) as sum_area,sum(team_bonus) as sum_team,sum(global_bonus) as sum_global, order_id')->group('order_id')->select();
        }

        //团队极差
        $orderBonusLogModel = new VslOrderTeamLogModel();
        $lists = $orderBonusLogModel->where(['website_id' => $this->website_id, 'order_id' => ['in', $orderIds]])->field('sum(team_bonus) as sum_team, order_id')->group('order_id')->select();
        $team_list = [];
        if($lists){
            $team_list = objToArr($lists);
        }

        if (!$bonus) {
            if($lists){
                return array_column($team_list, null, 'order_id');
            }
            return [];
        }

        $bonus_list = array_merge(objToArr($bonus), $team_list);


//        foreach ($bonus_list as $key=>&$item){
//            if(isset($team_list[$item['order_id']])){
//                $item['sum_team'] = $team_list[$item['order_id']]['sum_team'];
//            }
//        }

//        var_dump($team_list, $bonus_list);die;


        return array_column($bonus_list, null, 'order_id');;
    }

    /**
     * 批量查找订单结果的微店收益
     * @param type $orderIds
     */
    public function getOrderProfit($orderIds = [])
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $orderProfitModel = new VslOrderMicroShopProfitModel();
        $orderProfit = $orderProfitModel->where(['order_id' => ['in', $orderIds], 'return_status' => 0])->select();
        if (!$orderProfit) {
            return [];
        }
        $newArray = [];
        foreach ($orderProfit as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']][] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的物流信息
     * @param type $orderIds
     */
    public function getGoodsExpress($orderIds = [])
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $goodsExpressModel = new VslOrderGoodsExpressModel();
        $goodsExpressList = $goodsExpressModel::all(['order_id' => ['in', $orderIds]]);
        if (!$goodsExpressList) {
            return [];
        }
        $newArray = [];
        foreach ($goodsExpressList as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的省信息
     * @param type $proviceIds
     */
    public function getOrderProvince($proviceIds = [])
    {
        if (!$proviceIds || !is_array($proviceIds)) {
            return [];
        }
        $provinceModel = new ProvinceModel();
        $provinceList = $provinceModel->where(['province_id' => ['in', $proviceIds]])->select();
        if (!$provinceList) {
            return [];
        }
        return array_column(objToArr($provinceList), null, 'province_id');
    }

    /**
     * 批量查找订单结果的市信息
     * @param type $cityIds
     */
    public function getOrderCity($cityIds = [])
    {
        if (!$cityIds || !is_array($cityIds)) {
            return [];
        }
        $cityModel = new CityModel();
        $cityList = $cityModel->where(['city_id' => ['in', $cityIds]])->select();
        if (!$cityList) {
            return [];
        }
        return array_column(objToArr($cityList), null, 'city_id');
    }

    /**
     * 批量查找订单结果的区信息
     * @param type $districtIds
     */
    public function getOrderDistrict($districtIds = [])
    {
        if (!$districtIds || !is_array($districtIds)) {
            return [];
        }
        $districtModel = new DistrictModel();
        $districtList = $districtModel->where(['district_id' => ['in', $districtIds]])->select();

        if (!$districtList) {
            return [];
        }
        return array_column(objToArr($districtList), null, 'district_id');
    }

    /**
     * 批量查找订单结果的店铺信息
     * @param type $shopIds
     */
    public function getOrderShop($shopIds = [])
    {
        if (!$shopIds || !is_array($shopIds)) {
            return [];
        }
        $shopModel = new VslShopModel();
        $shopList = $shopModel->where(['shop_id' => ['in', $shopIds], 'website_id' => $this->website_id])->select();
        if (!$shopList) {
            return [];
        }
        return array_column(objToArr($shopList), null, 'shop_id');
    }

    /**
     * 批量查找订单结果的满额赠送信息
     * @param type $orderIds
     */
    public function getOrderPromotion($orderIds = [])
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $orderPromotionModel = new VslOrderPromotionDetailsModel();
        $manjianList = $orderPromotionModel->where(['order_id' => ['in', $orderIds], 'promotion_type' => 'MANJIAN'])->field('remark,order_id')->select();
        if (!$manjianList) {
            return [];
        }
        $newArray = [];
        foreach ($manjianList as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']] = json_decode($value['remark'], true);
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的购买人信息
     * @param type $buyerIds
     */
    public function getOrderBuyer($buyerIds = [])
    {
        if (!$buyerIds || !is_array($buyerIds)) {
            return [];
        }
        $userModel = new UserModel();
        $userList = $userModel->where(['uid' => ['in', $buyerIds], 'website_id' => $this->website_id])->field('user_tel,nick_name,user_name,uid')->select();
        if (!$userList) {
            return [];
        }
        return array_column(objToArr($userList), null, 'uid');
    }

    public function getOrderGoodsForList($orderIds = [], $filter = 0)
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $condition = ['order_id' => ['in', $orderIds]];
        if ($filter) {
            $condition['refund_status'] = 0;
        }
        $orderGoodsModel = new VslOrderGoodsModel();
        $orderGoodsList = $orderGoodsModel::all($condition, ['goods_sku', 'goods_pic', 'goods']);
        if (!$orderGoodsList) {
            return [];
        }
        $newArray = [];
        foreach ($orderGoodsList as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']][] = $value;
        }
        unset($value);
        return $newArray;
    }

    //获取订单锁
    public function getOrderLock($shop_list)
    {
        foreach ($shop_list as $k => $v) {
            foreach ($v['goods_list'] as $k1 => $v1) {
                $goods_id = $v1['goods_id'];
            }
            unset($v1);
        }
        unset($v);
        $condition['goods_id'] = $goods_id;
        $goods_mdl = new VslGoodsModel();
        $lock_version = $goods_mdl->getInfo($condition, 'lock_version')['lock_version'];
        $lock_version = $lock_version + 1;
        $data['lock_version'] = $lock_version;
        $lock = $goods_mdl->save($condition, $data);
        return $lock;
    }

    /**
     * 计算供应商金额（订单支付、订单完成）
     */
    public function calculSupplierMoney($order_id, $status)
    {
        // Db::startTrans();
        try {
//            $goods_mdl = new VslGoodsModel();
            $order_mdl = new VslOrderModel();
            $shop_account = new ShopAccount();
            $order_info = $order_mdl->getInfo(['order_id' => $order_id], 'order_no,order_money,website_id,shop_id,supplier_id,presell_id,money_type');
            $website_id = $this->website_id ?: $order_info['website_id'];
            $shop_id = $this->instance_id ?: $order_info['shop_id'];
            $supplier_money_all = 0;//供应商的收益
            $total_shop_profit = 0;//店铺的总收益
            $shop_tec_fee_all = 0;//店铺挑选的供应商商品技术服务费
            $supplier_tec_fee = 0;
            //预售付定金的时候先不结算供应商金额
            if ($order_info['presell_id'] != 0 && getAddons('presell', $website_id, $shop_id) && $order_info['money_type'] == 1) {
                return [
                    'supplier_money' => $supplier_money_all,
                    'total_shop_profit' => $total_shop_profit,
                    'shop_tec_fee' => $shop_tec_fee_all,
                ];
            }
            $condition['order_id'] = $order_id;
            if ($status == 4) {//订单完成
                $condition['refund_status'] = ['<>', 5];
            }

            if ($order_info['supplier_id']) {
                $order_goods_mdl = new VslOrderGoodsModel();
                $order_goods_list = $order_goods_mdl->getQuery($condition, 'order_goods_id,goods_id,num,shipping_fee,real_money,supplier_id,supplier_money,sku_id', '');
                //平台收取的供应商商品技术服务费
                $addConSer = new AddonsConfigSer();
                $supplier_config = $addConSer->getAddonsConfig('supplier', $website_id, 0, 1);
                $supplier_tec_fee_ratio = 100;//默认百分百
                if ($supplier_config) {
                    $supplier_tec_fee_ratio = $supplier_config['service_money'];
                }
                $shop_income = $supplier_money = 0;
                $goodsSkuModel = new VslGoodsSkuModel();

                foreach ($order_goods_list as $k => $v) {
                    if ($v['supplier_id']) {

                        //供应商商品,查出该商品的结算方式
//                        $goods_info = $goods_mdl->getInfo(['goods_id' => $v['goods_id']], 'price,cost_price,payment_type,supplier_rebate,supplier_goods_id');
                        $goods_sku_info = $goodsSkuModel::get(['sku_id' => $v['sku_id']], ['goods']);
                        $supplier_goods_sku_id = $goods_sku_info['supplier_sku_id'];//该sku信息
                        if (!$supplier_goods_sku_id) {
                            continue;
                        }
                        $supplier_goods_sku_info = $goodsSkuModel->getInfo(['sku_id' => $supplier_goods_sku_id], 'price,cost_price');//sku 对应的供应商信息
                        $goods_info['payment_type'] = $goods_sku_info['goods']['payment_type'];
                        $goods_info['price'] = $goods_info['payment_type'] == 1 ? $goods_sku_info['price'] : $supplier_goods_sku_info['price'];
                        $goods_info['cost_price'] = $supplier_goods_sku_info['cost_price'];
                        $goods_info['supplier_rebate'] = $goods_sku_info['goods']['supplier_rebate'];
                        $supplier_goods_id = $goods_sku_info['goods']['supplier_goods_id'];
                        if ($goods_info['payment_type'] == 1) {//成本价结算
//                            $supplier_goods_money = $goods_info['cost_price'] * $v['num'] + $v['shipping_fee'];
//                            $supplier_money = $goods_info['cost_price'] * $v['num'];
//                            $supplier_goods_money = $supplier_money + $v['shipping_fee'];//供应商财务冻结金额
//                            $shop_profit = $goods_info['price'] * $v['num'] - $supplier_money;//店铺收入
                            $shop_income = roundLengthNumber(($goods_info['price'] - $goods_info['cost_price']) * $v['num'], 2, false);//店铺收入
                            $supplier_tec_fee = roundLengthNumber($shop_income * $supplier_tec_fee_ratio / 100, 2, false);//技术服务费
                            $supplier_money = $goods_info['cost_price'] * $v['num'] + $v['shipping_fee'];//供应商收入(成本 + 运费)
                        } elseif ($goods_info['payment_type'] == 2) {//供应售价扣除佣金结算
                            //                            $supplier_goods_money = ($goods_info['price'] - roundLengthNumber($goods_info['price'] * ($goods_info['supplier_rebate'] / 100),2,false)) * $v['num'] + $v['shipping_fee'];
                            //                            $shop_profit = $goods_info['price'] * roundLengthNumber(($goods_info['supplier_rebate'] / 100),2,false) * $v['num'];//店铺收入
                            $shop_income = roundLengthNumber($goods_info['price'] * $v['num'] * ($goods_info['supplier_rebate'] / 100), 2, false);//店铺收入
                            $supplier_tec_fee = roundLengthNumber($shop_income * $supplier_tec_fee_ratio / 100, 2, false);//技术服务费
                            $supplier_money = $goods_info['price'] * $v['num'] - $shop_income + $v['shipping_fee'];//供应商收入（供应商售价- 店铺收入 + 运费）
                            //                            $supplier_money = ($goods_info['price'] - 1) * $v['num'];
                            //                            $supplier_goods_money = $supplier_money + $v['shipping_fee'];
                            //                            $shop_profit = $goods_info['price'] * roundLengthNumber(($goods_info['supplier_rebate'] / 100),2,false) * $v['num'];//店铺收入
                        }
                        $supplier_money = $supplier_money > 0 ? $supplier_money : 0;
                        //最终技术服务费 = 订单商品的实际价格与技术服务费的差值
                        if ($v['real_money'] - $supplier_tec_fee >= 0) {
                            $supplier_tec_fee = $supplier_tec_fee;
                        } else {
                            $supplier_tec_fee = $v['real_money'];
                        }
                        if ($status == 1) {//订单支付，冻结金额
                            $shop_account->updateSupplierFreezingMoney($v['supplier_id'], $supplier_money, $order_info['website_id'], 1);
                            $shop_account->addSupplierAccountRecords(getSerialNo(), $v['supplier_id'], $supplier_money, 1, $order_id, "订单号：" . $order_info['order_no'] . "，订单金额" . $order_info["order_money"] . "元，商品ID:" . $supplier_goods_id . "，实际到账" . $supplier_money . "元,已进入冻结账户", "订单支付完成，资金入账", $order_info['website_id']);
                            //结算给供应商的金额存到order_goods表，订单退款、完成时用
                            $order_goods_mdl = new VslOrderGoodsModel();
                            $supplier_shop_income = $shop_income - $supplier_tec_fee > 0 ? $shop_income - $supplier_tec_fee : 0;//店铺挑选的供应商商品的最终收益
                            $order_goods_mdl->isUpdate(true)->save(['supplier_money' => $supplier_money, 'supplier_tec_fee' => $supplier_tec_fee, 'supplier_shop_income' => $supplier_shop_income], ['order_goods_id' => $v['order_goods_id']]);
                        } else {//订单完成，冻结金额减少，可提现金额增加
                            $shop_account->updateSupplierTotalMoney($v['supplier_id'], $v['supplier_money'], $order_info['website_id']);
                            $shop_account->addSupplierAccountRecords(getSerialNo(), $v['supplier_id'], $v['supplier_money'], 5, $order_id, "订单号：" . $order_info['order_no'] . "，商品ID:" . $supplier_goods_id . "，订单已完成,已解冻" . $v['supplier_money'] . "元进入可提现账户。", "订单完成，资金入账", $order_info['website_id']);
                        }
                    }
                    $total_shop_profit += $shop_income;
                    $supplier_money_all += $supplier_money;
                    $shop_tec_fee_all += $supplier_tec_fee;
                }
                unset($v);
            }
            // Db::commit();
            $supplier_acc = [
                'total_shop_profit' => $total_shop_profit,
                'shop_tec_fee' => $shop_tec_fee_all,//店铺收入（未扣技术费）
                'supplier_money' => $supplier_money_all,
                'supplier_tec_fee_ratio' => $supplier_tec_fee_ratio,
            ];
            return $supplier_acc;
        } catch (\Exception $e) {
            // Db::rollback();
            return '行号：' . $e->getLine() . ' 错误: ' . $e->getMessage();
        }
    }

    /**
     * 添加领货码使用记录  -- old ： 单个码的时候接口 暂时不用
     * @param $order_id
     * @throws \think\Exception\DbException
     */
    public function addReceiveGoodsCodeRecord($order_id, $website_id = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        $addonsSer = new AddonsConfigSer();
        if ($addonsSer->isAddonsIsLeastOne('receivegoodscode', $website_id)) {
            //查询 订单商品数据
            $orderM = new VslOrderModel();
            $res = $orderM::get(['order_id' => $order_id], ['order_goods']);
            $res = objToArr($res);
            if (!$code_id = current($res['order_goods'])['receive_goods_code']) {
                return;
            }
            $codeSer = new ReceiveGoodsCodeSer();
            # 判断用户是否绑定了该码
            $user_code = $codeSer->getUserOfCode([
                'uid' => $res['buyer_id'],
                'website_id' => $res['website_id'],
                'shop_id' => $res['shop_id'],
                'code_id' => $code_id,
            ], 'id');
            if (!$user_code) {
                //绑定码
                $codeSer->bindReceiveGoodsCodeToUser($code_id, $res['buyer_id'], $res['website_id'], $res['shop_id']);
                $codeSer->updateUserOfCode([
                    'code_id' => $code_id,
                    'website_id' => $res['website_id'],
                    'shop_id' => $res['shop_id']
                ], ['code_status' => 1]);
            }
            //修改码的使用状态
            $codeSer->updateReceiveGoodsCodeStatusByCodeId($code_id, 1);
            //码的使用记录
            $codeInfo = $codeSer->getReceiveGoodsCodeConfigByCodeId($code_id);
            $deduct_count = array_sum(array_column($res['order_goods'], 'receive_goods_code_deduct')) ?: 0;//抵扣总总金额
            $record_data = [
                'website_id' => $website_id,
                'shop_id' => $res['shop_id'],
                'config_id' => $codeInfo['config_id'],
                'order_no' => $res['order_no'],
                'uid' => $res['buyer_id'],
                'user_name' => $res['user_name'],
                'discount_price' => $deduct_count,
                'use_time' => $res['create_time'],
                'code' => $codeInfo['code'],
            ];

            $codeSer->addReceiveGoodsCodeRecord($record_data);
        }

    }

    /**
     * 添加领货码使用记录
     * @param $order_id
     * @throws \think\Exception\DbException
     */
    public function addReceiveGoodsCodeRecordForMany($order_id, $website_id = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        $addonsSer = new AddonsConfigSer();
        if ($addonsSer->isAddonsIsLeastOne('receivegoodscode', $website_id)) {
            //查询 订单商品数据
            $orderM = new VslOrderModel();
            $res = $orderM::get(['order_id' => $order_id], ['order_goods']);
            $order_goods_arr = objToArr($res);
            foreach ($order_goods_arr['order_goods'] as $order_goods) {
//                $code_ids = json_decode(htmlspecialchars_decode($order_goods['receive_order_goods_data']),true)['code_id'];
                $code_arr = json_decode(htmlspecialchars_decode($order_goods['receive_order_goods_data']), true);
                $code_ids = $code_arr['code_id'];
                if (empty($code_ids)) {
                    continue;
                }
                foreach ($code_ids as $code_id) {
                    $codeSer = new ReceiveGoodsCodeSer();
                    # 判断用户是否绑定了该码
                    $user_code = $codeSer->getUserOfCode($data = [
                        'uid' => $res['buyer_id'],
                        'website_id' => $res['website_id'],
                        'shop_id' => $res['shop_id'],
                        'code_id' => $code_id,
                    ], 'id');
                    if (!$user_code) {
                        //绑定码
                        $codeSer->bindReceiveGoodsCodeToUser($code_id, $res['buyer_id'], $res['website_id'], $res['shop_id']);
                        $codeSer->updateUserOfCode([
                            'code_id' => $code_id,
                            'website_id' => $res['website_id'],
                            'shop_id' => $res['shop_id']
                        ], ['code_status' => 1]);
                    }
                    //修改码的使用状态
                    $codeSer->updateReceiveGoodsCodeStatusByCodeId($code_id, 1);
                    //码的使用记录
                    $codeInfo = $codeSer->getReceiveGoodsCodeConfigByCodeId($code_id);
//                    $deduct_count = array_sum(array_column(objToArr($res['order_goods']),'receive_goods_code_deduct')) ?:0;//抵扣总总金额
                    $code_deduct = isset($code_arr['code_deduct'][$code_id]) ? $code_arr['code_deduct'][$code_id] : $codeInfo['code_config']['discount_price'];
                    $record_data = [
                        'website_id' => $website_id,
                        'shop_id' => $res['shop_id'],
                        'config_id' => $codeInfo['config_id'],
                        'order_no' => $res['order_no'],
                        'uid' => $res['buyer_id'],
                        'user_name' => $res['user_name'],
                        'discount_price' => $code_deduct,
                        'use_time' => $res['create_time'],
                        'code' => $codeInfo['code'],
                    ];
                    $codeSer->addReceiveGoodsCodeRecord($record_data);
                }
            }
        }
    }

    /*
     * 订单分销明细
     */
    public function getCommissionMember($order_id = 0)
    {
        $detail = [];
        if (!getAddons('distribution', $this->website_id)) {
            return $detail;
        }
        //查询订单佣金
        if (getAddons('distribution', $this->website_id)) {
            $order_commission = new VslOrderDistributorCommissionModel();
            $agent_model = new VslDistributorLevelModel();
            $user = new UserModel();
            $member_model = new VslMemberModel();
            if ($this->instance_id > 0) {
                $orders = $order_commission->Query(['order_id' => $order_id, 'return_status' => 0, 'shop_id' => $this->instance_id], '*');
            } else {
                $orders = $order_commission->Query(['order_id' => $order_id, 'return_status' => 0], '*');
            }
            // debugLog($orders, '==>店铺佣金账户订单orders<==');
            foreach ($orders as $value) {
                if ($value['commissionA_id']) {
                    $detail['commissionA_id'] = $value['commissionA_id'];
                    $commissionA_info = $user->getInfo(['uid' => $value['commissionA_id']], 'user_name,nick_name,user_headimg,user_tel,user_tel');
                    $member_info = $member_model->getInfo(['uid' => $value['commissionA_id']], 'distributor_level_id');
                    if ($commissionA_info['user_name']) {
                        $detail['commissionA_name'] = $commissionA_info['user_name'];
                    } else {
                        $detail['commissionA_name'] = $commissionA_info['nick_name'];
                    }
                    $detail['commissionA_user_headimg'] = __IMG($commissionA_info['user_headimg']);
                    $detail['commissionA_mobile'] = $commissionA_info['user_tel'];
                    $detail['commissionA'] += $value['commissionA'];
                    $detail['pointA'] += $value['pointA'];
                    $detail['user_tel'] = $commissionA_info['user_tel'];
                    $detail['level_name'] = $agent_model->getInfo(['id' => $member_info['distributor_level_id']], 'level_name')['level_name'];
                }
                if ($value['commissionB_id']) {
                    $detail['commissionB_id'] = $value['commissionB_id'];
                    $commissionB_info = $user->getInfo(['uid' => $value['commissionB_id']], 'user_name,nick_name,user_headimg,user_tel,user_tel');
                    $member_info = $member_model->getInfo(['uid' => $value['commissionB_id']], 'distributor_level_id');
                    if ($commissionB_info['user_name']) {
                        $detail['commissionB_name'] = $commissionB_info['user_name'];
                    } else {
                        $detail['commissionB_name'] = $commissionB_info['nick_name'];
                    }
                    $detail['commissionB_user_headimg'] = __IMG($commissionB_info['user_headimg']);
                    $detail['commissionB_mobile'] = $commissionB_info['user_tel'];
                    $detail['commissionB'] += $value['commissionB'];
                    $detail['pointB'] += $value['pointB'];
                    $detail['user_tel'] = $commissionB_info['user_tel'];
                    $detail['level_name'] = $agent_model->getInfo(['id' => $member_info['distributor_level_id']], 'level_name')['level_name'];
                }
                if ($value['commissionC_id']) {
                    $detail['commissionC_id'] = $value['commissionC_id'];
                    $commissionC_info = $user->getInfo(['uid' => $value['commissionC_id']], 'user_name,nick_name,user_headimg,user_tel,user_tel');
                    $member_info = $member_model->getInfo(['uid' => $value['commissionC_id']], 'distributor_level_id');
                    if ($commissionC_info['user_name']) {
                        $detail['commissionC_name'] = $commissionC_info['user_name'];
                    } else {
                        $detail['commissionC_name'] = $commissionC_info['nick_name'];
                    }
                    $detail['commissionC_user_headimg'] = __IMG($commissionC_info['user_headimg']);
                    $detail['commissionC_mobile'] = $commissionC_info['user_tel'];
                    $detail['commissionC'] += $value['commissionC'];
                    $detail['pointC'] += $value['pointC'];
                    $detail['user_tel'] = $commissionC_info['user_tel'];
                    $detail['level_name'] = $agent_model->getInfo(['id' => $member_info['distributor_level_id']], 'level_name')['level_name'];
                }
            }
            unset($value);
        }
        return $detail;
    }

    /**
     * 查询一段时间内的店铺下单件数
     *
     * @param unknown $shop_id
     * @param unknown $start_date
     * @param unknown $end_date
     * @return Ambigous <\data\service\Order\unknown, number, unknown>
     */
    public function getShopSaleGoodsNum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num = $order_account->getShopSaleGoodsNumSum($condition);
        return $sales_num;
    }

    /**
     * 查询一段时间内的店铺实付金额
     *
     * @return Ambigous <\data\service\Order\unknown, number, unknown>
     */
    public function getShopOrderPaySum($condition)
    {
        $order_account = new OrderAccount();
        $sales_num = $order_account->getShopOrderPayMoneySum($condition);
        return $sales_num;
    }

    /**
     * 付款审核
     */
    public function checkPay($order_id,$check_status)
    {
        $order_mdl = new VslOrderModel();
        $order_info = $order_mdl->getInfo(['order_id' => $order_id],'out_trade_no,create_time,order_type,website_id,money_type,presell_id');
        $out_trade_no = $order_info['out_trade_no'];
        if($check_status == 1) {
            //审核通过,走支付流程
            $res =$this->orderOnLinePay($out_trade_no,10);
            if($res == -10009) {
                //拼团已结束，无法参加，订单关闭
                $this->orderClose($order_id,1);
            }
        }else{
            //审核不通过,如果超过支付限时就关闭订单，否则改为待支付状态
            $config = new AddonsConfig();
            if($order_info['order_type'] == 5) {
                //拼团订单
                $conf = $config->getAddonsConfig('groupshopping', $order_info['website_id'], 0, 1);
                $close_time = $conf['pay_time_limit'] ?: 1;
            }elseif ($order_info['order_type'] == 6) {
                //秒杀订单
                $conf = $config->getAddonsConfig('seckill', $order_info['website_id'], 0, 1);
                $close_time = $conf['pay_limit_time'] ?: 1;
            }elseif ($order_info['order_type'] == 8) {
                //砍价订单
                $conf = $config->getAddonsConfig('bargain', $order_info['website_id'], 0, 1);
                $close_time = $conf['pay_time_limit'] ?: 1;
            }else{
                $config = new Config();
                $config_info = $config->getConfig(0, 'ORDER_BUY_CLOSE_TIME', $order_info['website_id']);
                $close_time = $config_info['value'] ?: 1;
            }
            $time = time() - $close_time * 60;

            if($order_info['order_type'] == 7 && $order_info['money_type'] == 1){
                $presell_mdl = new VslPresellModel();
                $presell_list = $presell_mdl->getInfo(['id'=>$order_info['presell_id']], 'pay_end_time');
                $pay_end_time = $presell_list['pay_end_time'];
                if(time() <= $pay_end_time){ //如果预售订单当前时间小于尾款支付时间，则不让其关闭。
                    $res = $order_mdl->isUpdate(true)->save(['order_status' => 0,'pay_time' => 0],['out_trade_no|out_trade_no_presell' => $out_trade_no]);
                }else{
                    $res = $this->orderClose($order_id,1);
                }
            }elseif ($order_info['create_time'] < $time) {
                $res = $this->orderClose($order_id,1);
            }else{
                $res = $order_mdl->isUpdate(true)->save(['order_status' => 0,'pay_time' => 0],['out_trade_no|out_trade_no_presell' => $out_trade_no]);
            }
        }
        return $res;
    }
    public function dealSupplierOrderRefundAll($order_id, $refund_money = 0, $shop_order_money = 0, $order_no = '')
    {
        //1、优先抵扣店铺收入，技术费，供应商收入。
        $order_goods_model = new VslOrderGoodsModel();
        $goodsSer = new GoodsService();
        $order_goods_objs = $order_goods_model::all(['order_id' => $order_id]);
        $deduct_supplier_money = 0;
        $deduct_shop_money = 0;
        $deduct_tec_fee_money = 0;//技术费店铺没有收取，是平台收取，实际应该由店铺扣除
        $suppllier_refund_money = 0;
        $shop_refund_money = 0;
        $order_goods_shop_deduct = 0;//店铺抵扣金额
        $order_goods_shop_deduct_all = 0;//店铺总抵扣金额
        $order_goods_shop_deduct_show = 0;//店铺显示退款金额
        $order_goods_shop_deduct_show_all = 0;//店铺显示总退款金额
        $order_goods_supplier_deduct = 0;//供应商抵扣金额
        $order_goods_shop_un_freezing = 0;//店铺解冻金额
        $order_goods_supplier_un_freezing = 0;//供应商解冻金额
        $order_goods_shop_tx_money = 0;//店铺可提现金额
        $order_goods_supplier_tx_money = 0;//供应商可提现金额
        $shop_account = new ShopAccount();
        foreach ($order_goods_objs as $order_goods_obj) {
            $refund_require_money = $order_goods_obj['refund_require_money'];//用户实际退款商品金额
            $supplier_shop_income = $order_goods_obj['supplier_shop_income'];//商品店铺实际收入
            $supplier_tec_fee = $order_goods_obj['supplier_tec_fee'];//商品技术费
            $supplier_money = $order_goods_obj['supplier_money'];//商品供应商收入
            $shipping_fee = $order_goods_obj['shipping_fee'];//商品运费
            //供应商商品则用供应商方式去处理
            if ($order_goods_obj['supplier_id']) {
                $shop_order_money = $shop_order_money - $supplier_money - $supplier_tec_fee;//店铺最终解冻金额
                $order_goods_supplier_un_freezing = $supplier_money;//供应商最终解冻金额
//                $supplier_money = $order_goods_obj['order_status'] == 0? $supplier_money:$supplier_money-$shipping_fee;
                $supplier_money = $supplier_money;//部分退款，不退运费（不管是否发货）
                if ($refund_require_money <= $supplier_shop_income) {
                    $order_goods_shop_deduct = $refund_require_money;
                    $order_goods_shop_deduct_show = $order_goods_shop_deduct + $supplier_tec_fee;
                    $order_goods_shop_un_freezing = $supplier_shop_income - $order_goods_shop_deduct > 0 ? $supplier_shop_income - $order_goods_shop_deduct : 0;
                    $order_goods_supplier_tx_money = $supplier_money;
                } else if ($refund_require_money > $supplier_shop_income && $refund_require_money <= $supplier_shop_income + $supplier_tec_fee) {
                    $order_goods_shop_deduct = $supplier_shop_income;
                    $order_goods_shop_deduct_show = $refund_require_money;
                    $order_goods_shop_un_freezing = 0;
                    $order_goods_supplier_tx_money = $supplier_money;
                } else {
                    $order_goods_shop_deduct = $supplier_shop_income;
                    $order_goods_shop_deduct_show = $supplier_shop_income + $supplier_tec_fee;
                    $order_goods_supplier_deduct = $refund_require_money - ($supplier_shop_income + $supplier_tec_fee);
                    if ($order_goods_supplier_deduct <= $supplier_money) {
                        $order_goods_supplier_deduct = $order_goods_supplier_deduct;
                        $order_goods_supplier_tx_money = $supplier_money - $order_goods_supplier_deduct > 0 ? $supplier_money - $order_goods_supplier_deduct : 0;//供应商提现金额
                    } else {
                        $order_goods_supplier_deduct = $supplier_money;
                        $order_goods_supplier_tx_money = 0;
                    }
                }
                $refund_money = $refund_money - $refund_require_money;
                //供应商退款
                //供应商实际商品id:
                $supplier_goods_id = $goodsSer->getGoodsDetailById($order_goods_obj['goods_id'], 'goods_id,goods_name,price,collects,supplier_goods_id')['supplier_goods_id'];
                //供应商退款 updateSupplierTotalMoney
                $remark = "订单号：" . $order_no . "，商品ID:" . $supplier_goods_id . "，订单退款，冻结金额减少" . $supplier_money . "元。";
                $shop_account->updateSupplierFreezingMoney($order_goods_obj['supplier_id'], $supplier_money, $order_goods_obj['website_id'], 5);
                $shop_account->addSupplierAccountRecords(getSerialNo(), $order_goods_obj['supplier_id'], $supplier_money, 3, $order_id, $remark, "订单退款，冻结金额减少", $order_goods_obj['website_id']);
                $shop_account->updateSupplierTotalMoney($order_goods_obj['supplier_id'], $order_goods_supplier_tx_money, $order_goods_obj['website_id']);
                $remark = "供应商退款提现+" . $order_goods_supplier_tx_money . "元。";
                $shop_account->addSupplierAccountRecords(getSerialNo(), $order_goods_obj['supplier_id'], $order_goods_supplier_tx_money, 3, $order_id, $remark, "退款到可提现", $order_goods_obj['website_id']);

            } else {
                //1、店铺解冻金额 2、店铺可提现金额
                $order_goods_shop_un_freezing = $order_goods_obj['refund_require_money'];
                $order_goods_shop_deduct_show = $order_goods_shop_deduct = $order_goods_obj['refund_require_money'];
            }
            $order_goods_shop_tx_money += $order_goods_shop_un_freezing;
            $order_goods_shop_deduct_all += $order_goods_shop_deduct;
            $order_goods_shop_deduct_show_all += $order_goods_shop_deduct_show;
        }
        return [
            'shop_order_money' => $shop_order_money,
            'order_goods_shop_tx_money' => $order_goods_shop_tx_money,
            'order_goods_shop_deduct_all' => $order_goods_shop_deduct_all,
            'order_goods_shop_deduct_show_all' => $order_goods_shop_deduct_show_all,
        ];
    }

    /**
     * 获取订单中的供应商商品运费
     */
    public function calculSupplierShippingMoney($order_id = 0, $order_goods_id = 0)
    {
        $order_goods_model = new VslOrderGoodsModel();
        if ($order_id > 0) {
            $shipping_fee = $order_goods_model->getSum(['order_id' => $order_id, 'supplier_id' => ['NEQ', 0]], 'shipping_fee');
        }
        if ($order_goods_id > 0) {
            $shipping_fee = $order_goods_model->getSum(['order_goods_id' => $order_goods_id, 'supplier_id' => ['NEQ', 0]], 'shipping_fee');
        }
        return $shipping_fee ? $shipping_fee : 0;
    }

    /**
     * 获取剩余可解冻金额
     * 发生方式1订单支付完成 2订单完成，店铺优惠金额增加 3订单退款账户金额减少 4订单退款优惠金额减少 5订单完成|订单退款，资金入账 8店铺提现
     */
    public function calculFreezingMoney($order_id, $shop_id)
    {
        $shopAccountRecordsModel = new VslShopAccountRecordsModel();
        $freezing = $shopAccountRecordsModel->getSum(['shop_id' => $shop_id, 'type_alis_id' => $order_id, 'account_type' => 1], 'money');
        $freezing = $freezing ? $freezing : 0;
        $returnmoney = $shopAccountRecordsModel->getSum(['shop_id' => $shop_id, 'type_alis_id' => $order_id, 'account_type' => 3], 'money');
        $returnmoney = $returnmoney ? $returnmoney : 0;
        return bcsub($freezing, $returnmoney, 2);

    }

    public function test_updateShopAccount_OrderComplete($order_id)
    {
        $this->updateShopAccount_OrderComplete($order_id, $is_change = 0, $is_last = 0);
    }

    public function test_updateShopAccount_OrderRefund($order_goods_id)
    {
        $this->updateShopAccount_OrderRefund($order_goods_id);
    }

    public function test_updateShopAccount_OrderRefund_All($order_id, $refund_real_money)
    {
        $this->updateShopAccount_OrderRefund_All($order_id, $refund_real_money);
    }

    public function test_updateShopAccount_OrderPay($order_id)
    {
        $this->updateShopAccount_OrderPay($order_id, 1);
    }

    /**
     * 支付单号换取订单店铺信息
     * return array(array('shop_id'=>1,'joinpay_machid'=>1,money),...);
     */
    public function getShopPayInfo($orderNumber = '')
    {
        if (empty($orderNumber)) {
            return array();
        }
        $order_model = new VslOrderModel();
        $order_list = $order_model->Query(["out_trade_no" => $orderNumber], "shop_id,order_money,shop_order_money");
        if (empty($order_list)) {
            return array();
        }
        $data = array();
        foreach ($order_list as $key => $value) {
            if ($value['shop_id'] == 0) {
                continue;
            }
            //shop_id换取店铺对应的收款商户号joinpay_machid
            $shopModel = new VslShopModel();
            $shop_info = $shopModel->getInfo(['shop_id' => $value['shop_id']], 'joinpay_machid');
            if (!$shop_info || empty($shop_info['joinpay_machid'])) {
                continue;
            }
            $data_info = array(
                'shop_id' => $value['shop_id'],
                'money' => $value['order_money'] >= $value['shop_order_money'] ? $value['shop_order_money'] : $value['order_money'],
                'joinpay_machid' => $shop_info['joinpay_machid']
            );
            array_push($data, $data_info);
        }
        return $data;
    }

    /**
     * 待发货订单导出
     */
    public function getExcelsExportOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        //过滤售后商品,导出订单用
        $filter = false;
        if ($condition['filter']) {
            $filter = true;
            unset($condition['filter']);
        }

        //查出主订单
        $order_mdl = new VslOrderModel();
        $order_goods_mdl = new VslOrderGoodsModel();

        $field = 'order_id,order_no,create_time,receiver_name,receiver_mobile,receiver_province,receiver_city,receiver_district,receiver_address,order_type,group_record_id';
        $order_goods_field = 'supplier_id,goods_name,sku_name,num,real_money,order_goods_id,sku_id,goods_name,goods_type';

        $data = $order_mdl->pageQuery($page_index, $page_size, $condition, $order, $field);
        $order_list['data'] = $data['data'];
        if ($data['page_count'] > 1) {
            for ($i = 2; $i <= $data['page_count']; $i++) {
                $res = $order_mdl->pageQuery($i, $page_size, $condition, $order, $field)['data'];
                $order_list['data'] = array_merge($order_list['data'], $res);
            }
        }

        if ($order_list['data']) {
            $goods_sku_mdl = new VslGoodsSkuModel();
            if (getAddons('supplier', $condition['website_id'])) {
                $supplier_mdl = new VslSupplierModel();
            }
            $address_server = new Address();
            $order_goods_condition = [];
            if ($filter) {
                $order_goods_condition['refund_status'] = 0;
            }

            foreach ($order_list['data'] as $k => $v) {
                $province = $address_server->getProvinceName($v['receiver_province']);
                $city = $address_server->getCityName($v['receiver_city']);
                $district = $address_server->getDistrictName($v['receiver_district']);
                $order_list['data'][$k]['receiver_address'] = $province . $city . $district . $v['receiver_address'];

                $order_goods_condition['order_id'] = $v['order_id'];
                $order_goods_list = $order_goods_mdl->getQuery($order_goods_condition, $order_goods_field, '');
                $order_list['data'][$k]['order_item_list'] = [];
                if ($order_goods_list) {
                    foreach ($order_goods_list as $k1 => $v1) {
                        $goods_sku_info = $goods_sku_mdl->getInfo(['sku_id' => $v1['sku_id']], 'code');
                        $order_goods_list[$k1]['item_no'] = $goods_sku_info['code'];
                        $order_goods_list[$k1]['supplier_name'] = '';
                        if ($v1['supplier_id']) {
                            $order_goods_list[$k1]['supplier_name'] = $supplier_mdl->Query(['supplier_id' => $v1['supplier_id']], 'supplier_name')[0];
                        }
                    }
                    $order_list['data'][$k]['order_item_list'] = $order_goods_list;
                    unset($v1);
                }
            }
            unset($v);
        }

        return $order_list;
    }

    /**
     * 订单列表导出
     */
    public function getOrderExcelsList($page_index = 1, $page_size = 0, $condition = '', $order = '', $supplier_id = 0)
    {
        $website_id = $condition['website_id'];
        $shop_id = $condition['shop_id'] ?: 0;

        //过滤售后商品,导出订单用
        $filter = false;
        if ($condition['filter']) {
            $filter = true;
            unset($condition['filter']);
        }

        $order_model = new VslOrderModel();
        $orderGoodsModel = new VslOrderGoodsModel();
        //如果有订单表以外的字段，则先按条件查询其他表的orderid，并取出数据的交集，组装到原有查询条件里
        $query_order_ids = 'uncheck';
        $un_query_order_ids = array();
        $checkOthers = false;
        $refundStatus = '';
        if ($condition['express_no']) {
            $checkOthers = true;
            $expressNo = $condition['express_no'];
            $orderGoodsExpressModel = new VslOrderGoodsExpressModel();
            $express_order_ids = $orderGoodsExpressModel->Query(['express_no' => $expressNo, 'website_id' => $website_id], 'order_id');
            unset($condition['express_no']);
            $query_order_ids = $express_order_ids;
        }

        if ($condition['or'] && $condition['goods_name'] && $condition['goods_id']) {
            $checkOthers = true;
            $order_goods_condition = ['website_id' => $website_id];
            $order_goods_condition['goods_name'] = $condition['goods_name'];
            if ($supplier_id) {
                $order_goods_condition['supplier_id'] = $supplier_id;
            }
            $orderGoodsList = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition, '', 'order_id');
            if ($orderGoodsList['data']) {
                foreach ($orderGoodsList['data'] as $keyG => $valG) {
                    $goods_order_ids[] = $valG['order_id'];
                }
                unset($valG);
            }
            $order_goods_condition2 = ['website_id' => $website_id];
            $order_goods_condition2['goods_id'] = $condition['goods_id'];
            if ($supplier_id) {
                $order_goods_condition2['supplier_id'] = $supplier_id;
            }
            $orderGoodsList2 = $orderGoodsModel->pageQuery(1, 0, $order_goods_condition2, '', 'order_id');
            if ($orderGoodsList2['data']) {
                foreach ($orderGoodsList2['data'] as $keyG2 => $valG2) {
                    $goods_order_ids[] = $valG2['order_id'];
                }
                unset($valG2);
            }
            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $goods_order_ids);
            } else {
                $query_order_ids = $goods_order_ids;
            }
            unset($condition['or'], $condition['goods_name'], $condition['goods_id'], $order_goods_condition, $order_goods_condition2, $orderGoodsList, $orderGoodsList2);
        }

        if ($condition['goods_name'] || $condition['refund_status'] || $condition['buyer_id']) {
            $checkOthers = true;
            $orderGoodsModel = new VslOrderGoodsModel();
            $order_goods_condition = ['website_id' => $website_id];
            if ($condition['goods_name']) {
                if (is_numeric($condition['goods_name'])) {
                    //商品编号搜索
                    $goods_condition = [
                        'code' => $condition['goods_name'],
                        'website_id' => $website_id,
                    ];
                    $goodsSer = new GoodsService();
                    $goods_name = $goodsSer->getGoodsDetailByCondition($goods_condition, 'goods_name');
                    $order_goods_condition['goods_name'] = $goods_name ? $goods_name['goods_name'] : '';
                }else{
                    $order_goods_condition['goods_name'] = $condition['goods_name'];
                }
            }

            if ($condition['refund_status']) {
                if ($condition['refund_status'] == 'backList') {
                    $order_goods_condition['refund_status'] = ['neq', 0];
                } else {
                    $refundStatus = $condition['refund_status'];
                    $order_goods_condition['refund_status'] = ['IN', $condition['refund_status']];
                }
            }
            if ($condition['buyer_id']) {
                $order_goods_condition['buyer_id'] = $condition['buyer_id'];
            }
            if ($supplier_id) {
                $order_goods_condition['supplier_id'] = $supplier_id;
            }
            $goods_order_ids = $orderGoodsModel->Query($order_goods_condition, 'order_id');
            unset($condition['goods_name'], $condition['refund_status'], $condition['buyer_id']);

            if ($query_order_ids != 'uncheck') {
                $query_order_ids = array_intersect($query_order_ids, $goods_order_ids);
            } else {
                $query_order_ids = $goods_order_ids;
            }
        }
        if ($condition['vgsr_status']) {
            $isGroup = true;
            $checkOthers = true;
            $vgsr_status = $condition['vgsr_status'];
            $group_server = new GroupShopping();
            $unGroupOrderIds = $group_server->getPayedUnGroupOrder($this->instance_id, $this->website_id);

            if ($vgsr_status == 1) {
                if ($query_order_ids != 'uncheck') {
                    $query_order_ids = array_intersect($query_order_ids, $unGroupOrderIds);
                } else {
                    $query_order_ids = $unGroupOrderIds;
                }
            }
            if ($vgsr_status == 2 && $unGroupOrderIds) {
                $un_query_order_ids = $unGroupOrderIds;
            }
            unset($condition['vgsr_status']);
        }
        if ($checkOthers) {
            if ($query_order_ids != 'uncheck') {
                $condition['order_id'] = ['in', implode(',', $query_order_ids)];
            } elseif ($un_query_order_ids) {
                $condition['order_id'] = ['not in', implode(',', $un_query_order_ids)];
            }
        }

        if ($condition['order_status'] && $condition['order_status'] == 9) {
            unset($condition['order_status']);
        }
        if (isset($condition['receiver_mobile'])) {
            $condition['buyer_id'] = $condition['receiver_mobile'];
            unset($condition['receiver_mobile']);
        }
        // 重组订单条件
        $new_condition = array();
        if ($condition) {
            foreach ($condition as $keys => $values) {
                if ($keys === 'user_type') {
                    if ($values == 2) {
                        $new_condition['su.isdistributor'] = 2;
                    } else if ($values == 3) {
                        $new_condition['su.is_team_agent'] = 2;
                    } else {
                        $new_condition['su.isdistributor'] = ['<', 2];
                    }
                } elseif (is_int($keys)) {/*给框架 EXP使用 */
                    $new_condition[$keys] = $values;
                } else {
                    $new_condition['nm.' . $keys] = $values;
                }
            }
            unset($values);
        }

        //查询主订单
        $field = 'nm.order_id,nm.order_no,nm.create_time,nm.receiver_name,nm.receiver_mobile,nm.receiver_province,nm.receiver_city,nm.receiver_district,nm.receiver_address,nm.buyer_id,nm.out_trade_no,nm.order_type,nm.order_status,nm.shipping_money,nm.pay_money,nm.payment_type,nm.user_name,su.mobile,nm.final_money,nm.buyer_message,nm.pay_time,nm.consign_time,nm.sign_time,nm.finish_time,nm.shop_name,s.store_name,sa.assistant_name,nm.receiver_country,nm.receiver_type,nm.membercard_deduction_money,nm.money_type,nm.user_platform_money';
        $data = $order_model->getViewList2($page_index, $page_size, $new_condition, $order, $field);
        $list['data'] = [];
        if ($data['page_count'] >= 1) {
            $globalbonus = getAddons('globalbonus', $website_id);
            $areabonus = getAddons('areabonus', $website_id);
            $teambonus = getAddons('teambonus', $website_id);
            $distribution = getAddons('distribution', $website_id);
            $fullcut = getAddons('fullcut', $website_id);
            $coupontype = getAddons('coupontype', $website_id);
            $microshop = getAddons('microshop', $website_id, $shop_id);
            $abroad_receive = getAddons('abroadreceivegoods', $website_id);

            for ($i = 1; $i <= $data['page_count']; $i++) {
                $order_list['data'] = [];
                $order_list['data'] = $order_model->getViewList2($i, $page_size, $new_condition, $order, $field)['data'];

                if (!empty($order_list['data'])) {
                    //处理订单信息,用于批量操作,减少数据库查询
                    $orderListArr = objToArr($order_list['data']);//5.5版本无法从对象获取,所以要转成数组
                    $orderIds = dealArray(array_column($orderListArr, 'order_id'), false, true);
                    $provinceIds = dealArray(array_column($orderListArr, 'receiver_province'), true, true);
                    $cityIds = dealArray(array_column($orderListArr, 'receiver_city'), true, true);
                    $districtIds = dealArray(array_column($orderListArr, 'receiver_district'), true, true);
                    $countryIds = dealArray(array_column($orderListArr, 'receiver_country'), true, true);

                    //批量查找订单结果的物流信息
                    $order_express_info = $this->getGoodsExpressInfo($orderIds);
                    //批量查找订单结果的省信息,部分订单没有地区信息,不作联表查询
                    $orderProvinces = $this->getOrderProvinceInfo($provinceIds);
                    //批量查找订单结果的市信息
                    $orderCitys = $this->getOrderCityInfo($cityIds);
                    //批量查找订单结果的区信息
                    $orderDistricts = $this->getOrderDistrictInfo($districtIds);
                    //批量查找订单结果的国家信息
                    if ($abroad_receive) {
                        $orderCountrys = $this->getOrderCountryInfo($countryIds);
                    }
                    //批量查找订单商品信息
                    $order_goods_lists = $this->getOrderGoodsForList2($orderIds, $filter, $refundStatus);
                    //批量查找order_goods_id
                    $orderGoodsIds = $this->getOrderGoodsIds($orderIds, $filter, $refundStatus);

                    $orderPromotionList = [];
                    if ($fullcut) {
                        //订单查询批量查找订单结果的满额赠送
                        $orderPromotionList = $this->getOrderPromotion($orderIds);
                    }
                    $orderCommissions = [];
                    $orderProfits = [];
                    $orderBonus = [];
                    if ($distribution) {
                        //批量查找订单结果的佣金
                        $orderCommissions = $this->getOrderCommissionInfo($orderGoodsIds, $shop_id);
                    }
                    if ($microshop) {
                        //批量查找订单结果的微店收益
                        $orderProfits = $this->getOrderProfitInfo($orderGoodsIds);
                    }
                    //批量查找订单结果的全球分红、区域分红、团队分红
                    if ($globalbonus || $areabonus || $teambonus) {
                        $orderBonus = $this->getOrderBonusInfo($orderGoodsIds, $shop_id);
                    }

                    foreach ($order_list['data'] as $k => $v) {
                        $order_list['data'][$k]['order_type_name'] = $this->getOrderType($v['order_type']);
                        $order_list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                        $order_list['data'][$k]['pay_time'] = $v['pay_time'] ? date('Y-m-d H:i:s', $v['pay_time']) : '';
                        $order_list['data'][$k]['consign_time'] = $v['consign_time'] ? date('Y-m-d H:i:s', $v['consign_time']) : '';
                        $order_list['data'][$k]['sign_time'] = $v['sign_time'] ? date('Y-m-d H:i:s', $v['sign_time']) : '';
                        $order_list['data'][$k]['finish_time'] = $v['finish_time'] ? date('Y-m-d H:i:s', $v['finish_time']) : '';
                        $order_list['data'][$k]['uid'] = $v['buyer_id'];
                        $order_list['data'][$k]['user_tel'] = $v['mobile'];
                        //组装收货地址
                        $receiver_province_name = isset($orderProvinces[$v["receiver_province"]]) ? $orderProvinces[$v["receiver_province"]]["province_name"] : '';
                        $receiver_city_name = isset($orderCitys[$v["receiver_city"]]) ? $orderCitys[$v["receiver_city"]]["city_name"] : '';
                        $receiver_district_name = isset($orderDistricts[$v["receiver_district"]]) ? $orderDistricts[$v["receiver_district"]]["district_name"] : '';
                        $chinese_country_name = isset($orderCountrys[$v["receiver_country"]]) ? $orderCountrys[$v["receiver_country"]]["chinese_country_name"] : '';
                        $english_country_name = isset($orderCountrys[$v["receiver_country"]]) ? $orderCountrys[$v["receiver_country"]]["english_country_name"] : '';
                        if (empty($v['receiver_type'])) {
                            //国内
                            $order_list['data'][$k]['receiver_province_name'] = $receiver_province_name;
                            $order_list['data'][$k]['receiver_city_name'] = $receiver_city_name;
                            $order_list['data'][$k]['receiver_district_name'] = $receiver_district_name;
                            $order_list['data'][$k]['receiver_address'] = $v['receiver_address'];
                            $order_list['data'][$k]['country_name'] = '';
                        } else {
                            //国外
                            $order_list['data'][$k]['receiver_province_name'] = '';
                            $order_list['data'][$k]['receiver_city_name'] = '';
                            $order_list['data'][$k]['receiver_district_name'] = '';
                            $order_list['data'][$k]['receiver_address'] = $chinese_country_name . $english_country_name . ' ' . $v['receiver_address'];
                            $order_list['data'][$k]['country_name'] = $chinese_country_name . $english_country_name;
                        }

                        $order_list['data'][$k]['status_name'] = $this->getOrderStatusName($v['order_status']);
                        $order_list['data'][$k]['pay_type_name'] = $this->getOrderPayTypeName($v['payment_type']);
                        if ($v['payment_type'] == 18 && $v['membercard_deduction_money'] != 0.00) {
                            $order_list['data'][$k]['pay_type_name'] = '会员卡抵扣';
                        }

                        if ($v['money_type'] == 1) {
                            $order_list['data'][$k]["pay_money"] = ($v['user_platform_money'] > 0) ? $v['user_platform_money'] : ($v['pay_money'] + $v['final_money']);
                            $order_list['data'][$k]["first_money"] = $v['pay_money'];
                        } else {
                            $order_list['data'][$k]["pay_money"] = ($v['user_platform_money'] > 0) ? $v['user_platform_money'] : $v['pay_money'];
                            $order_list['data'][$k]["first_money"] = $v['pay_money'] - $v['final_money'];
                        }

                        $order_list['data'][$k]['manjian_remark'] = '';
                        if (isset($orderPromotionList[$v['order_id']]['coupon'])) {
                            $order_list['data'][$k]['manjian_remark'] .= $orderPromotionList[$v['order_id']]['coupon']['coupon_name'];
                        }
                        if (isset($orderPromotionList[$v['order_id']]['gift'])) {
                            $order_list['data'][$k]['manjian_remark'] .= $orderPromotionList[$v['order_id']]['gift']['gift_name'];
                        }
                        if (isset($orderPromotionList[$v['order_id']]['gift_voucher'])) {
                            $order_list['data'][$k]['manjian_remark'] .= $orderPromotionList[$v['order_id']]['gift_voucher']['giftvoucher_name'];
                        }

                        $order_list['data'][$k]['order_item_list'] = [];
                        if (isset($order_goods_lists[$v['order_id']])) {
                            $order_goods_list = $order_goods_lists[$v['order_id']];
                            foreach ($order_goods_list as $k1 => $v1) {
                                $order_goods_list[$k1]['goods_code'] = $v1['g_code'];
                                $order_goods_list[$k1]['item_no'] = $v1['code'] ? $v1['code'] : $v1['item_no'];

                                $order_goods_list[$k1]['coupon_money'] = '';
                                $order_goods_list[$k1]['manjian_money'] = '';
                                if ($v1['promotion_type'] == 'COUPON' && $coupontype) {
                                    $order_goods_list[$k1]['coupon_money'] = $v1['discount_money'];
                                }
                                if ($v1['promotion_type'] == 'MANJIAN' && $fullcut) {
                                    $order_item_list[$k1]['manjian_money'] = $v1['discount_money'];
                                }

                                $order_goods_list[$k1]['express_no'] = '';
                                $order_goods_list[$k1]['express_name'] = '';
                                if (isset($order_express_info[$v['order_id']])) {
                                    $express_order_goods_id_array = explode(',', $order_express_info[$v['order_id']]['order_goods_id_array']);
                                    if (in_array($v1['order_goods_id'], $express_order_goods_id_array)) {
                                        $order_goods_list[$k1]['express_no'] = $order_express_info[$v['order_id']]['express_no'];
                                        $order_goods_list[$k1]['express_name'] = $order_express_info[$v['order_id']]['express_company'];
                                    }
                                }

                                //分销
                                $order_goods_list[$k1]['commission'] = '';
                                $order_goods_list[$k1]['commissionA'] = '';
                                $order_goods_list[$k1]['commissionB'] = '';
                                $order_goods_list[$k1]['commissionC'] = '';
                                if (isset($orderCommissions[$v1['order_goods_id']])) {
                                    $order_goods_list[$k1]['commission'] = $orderCommissions[$v1['order_goods_id']]['commission'];
                                    $order_goods_list[$k1]['commissionA'] = $orderCommissions[$v1['order_goods_id']]['commissionA'];
                                    $order_goods_list[$k1]['commissionB'] = $orderCommissions[$v1['order_goods_id']]['commissionB'];
                                    $order_goods_list[$k1]['commissionC'] = $orderCommissions[$v1['order_goods_id']]['commissionC'];
                                }

                                //分红
                                $order_goods_list[$k1]['bonus'] = 0;
                                $order_goods_list[$k1]['bonusA'] = 0;
                                $order_goods_list[$k1]['bonusB'] = 0;
                                $order_goods_list[$k1]['bonusC'] = 0;
                                if (isset($orderBonus[$v1['order_goods_id']])) {
                                    $order_goods_list[$k1]['bonusA'] = $orderBonus[$v1['order_goods_id']]['sum_global'];
                                    $order_goods_list[$k1]['bonusB'] = $orderBonus[$v1['order_goods_id']]['sum_area'];
                                    $order_goods_list[$k1]['bonusC'] = $orderBonus[$v1['order_goods_id']]['sum_team'];
                                }
                                $order_goods_list[$k1]['bonus'] = $order_goods_list[$k1]['bonusA'] + $order_goods_list[$k1]['bonusB'] + $order_goods_list[$k1]['bonusC'];

                                //收益
                                $order_goods_list[$k1]['profit'] = '';
                                $order_goods_list[$k1]['profitA'] = '';
                                $order_goods_list[$k1]['profitB'] = '';
                                $order_goods_list[$k1]['profitC'] = '';
                                if (isset($orderProfits[$v1['order_goods_id']])) {
                                    $order_goods_list[$k1]['profit'] = $orderProfits[$v1['order_goods_id']]['profit'];
                                    $order_goods_list[$k1]['profitA'] = $orderProfits[$v1['order_goods_id']]['profitA'];
                                    $order_goods_list[$k1]['profitB'] = $orderProfits[$v1['order_goods_id']]['profitB'];
                                    $order_goods_list[$k1]['profitC'] = $orderProfits[$v1['order_goods_id']]['profitC'];
                                }
                            }
                            $order_list['data'][$k]['order_item_list'] = $order_goods_list;
                            unset($v1);
                        }

                    }
                    $list['data'] = array_merge($list['data'], $order_list['data']);
                }
            }
        }

        return $list;
    }

    /**
     * 批量查找订单结果的物流信息
     * @param type $orderIds
     */
    public function getGoodsExpressInfo($orderIds = [])
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }
        $goodsExpressModel = new VslOrderGoodsExpressModel();
        $goodsExpressList = $goodsExpressModel->getQuery(['order_id' => ['in', $orderIds]], 'order_goods_id_array,order_id,express_company,express_no,shipping_time', '');
        if (!$goodsExpressList) {
            return [];
        }
        $newArray = [];
        foreach ($goodsExpressList as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的省信息
     * @param type $proviceIds
     */
    public function getOrderProvinceInfo($proviceIds = [])
    {
        if (!$proviceIds || !is_array($proviceIds)) {
            return [];
        }
        $provinceModel = new ProvinceModel();
        $provinceList = $provinceModel->where(['province_id' => ['in', $proviceIds]])->field('province_id,province_name')->select();
        if (!$provinceList) {
            return [];
        }
        return array_column(objToArr($provinceList), null, 'province_id');
    }

    /**
     * 批量查找订单结果的市信息
     * @param type $cityIds
     */
    public function getOrderCityInfo($cityIds = [])
    {
        if (!$cityIds || !is_array($cityIds)) {
            return [];
        }
        $cityModel = new CityModel();
        $cityList = $cityModel->where(['city_id' => ['in', $cityIds]])->field('city_id,city_name')->select();
        if (!$cityList) {
            return [];
        }
        return array_column(objToArr($cityList), null, 'city_id');
    }

    /**
     * 批量查找订单结果的区信息
     * @param type $districtIds
     */
    public function getOrderDistrictInfo($districtIds = [])
    {
        if (!$districtIds || !is_array($districtIds)) {
            return [];
        }
        $districtModel = new DistrictModel();
        $districtList = $districtModel->where(['district_id' => ['in', $districtIds]])->field('district_id,district_name')->select();

        if (!$districtList) {
            return [];
        }
        return array_column(objToArr($districtList), null, 'district_id');
    }

    /**
     * 批量查找订单结果的国家信息
     * @param type $country
     */
    public function getOrderCountryInfo($countryIds = [])
    {
        if (!$countryIds || !is_array($countryIds)) {
            return [];
        }
        $countrylistModel = new VslCountryListModel();
        $countryList = $countrylistModel->where(['id' => ['in', $countryIds]])->field('id,chinese_country_name,english_country_name')->select();

        if (!$countryList) {
            return [];
        }
        return array_column(objToArr($countryList), null, 'id');
    }

    /**
     * 批量查找订单商品信息
     */
    public function getOrderGoodsForList2($orderIds = [], $filter = 0, $refundStatus = '')
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }

        $order_goods_condition = [];
        if ($filter) {
            $order_goods_condition['og.refund_status'] = 0;
        } elseif ($refundStatus) {
            $order_goods_condition['og.refund_status'] = ['in', $refundStatus];
        }

        $order_goods_condition['og.order_id'] = ['in', $orderIds];

        $order_goods_field = 'og.goods_name,og.sku_name,og.num,og.real_money,og.order_goods_id,og.sku_id,og.goods_name,og.price,og.refund_status,og.order_id,og.goods_money,og.member_price,og.discount_price,og.adjust_money,og.shipping_fee,og.cost_price,s.code,d.promotion_type,d.discount_money,g.item_no,g.code as g_code';
        $orderGoodsModel = new VslOrderGoodsModel();
        $orderGoodsList = $orderGoodsModel->alias('og')
            ->join('vsl_goods_sku s', 'og.sku_id=s.sku_id', 'left')
            ->join('vsl_goods g', 'og.goods_id=g.goods_id', 'left')
            ->join('vsl_order_goods_promotion_details d', ['og.sku_id=d.sku_id', 'og.order_id=d.order_id'], 'left')
            ->field($order_goods_field)
            ->where($order_goods_condition)
            ->select();
        if (!$orderGoodsList) {
            return [];
        }
        $newArray = [];
        foreach ($orderGoodsList as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_id']][] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找order_goods_id
     */
    public function getOrderGoodsIds($orderIds = [], $filter = 0, $refundStatus = '')
    {
        if (!$orderIds || !is_array($orderIds)) {
            return [];
        }

        $order_goods_condition = [];
        if ($filter) {
            $order_goods_condition['refund_status'] = 0;
        } elseif ($refundStatus) {
            $order_goods_condition['refund_status'] = ['in', $refundStatus];
        }

        $order_goods_condition['order_id'] = ['in', $orderIds];

        $orderGoodsModel = new VslOrderGoodsModel();
        $order_goods_ids = $orderGoodsModel->Query($order_goods_condition, 'order_goods_id');
        return $order_goods_ids;
    }

    /**
     * 获取订单类型
     */
    public static function getOrderType($type_id)
    {
        $order_type = [
            1 => [
                'type_name' => '普通订单'
            ],
            2 => [
                'type_name' => '普通订单'
            ],
            3 => [
                'type_name' => '普通订单'
            ],
            4 => [
                'type_name' => '普通订单'
            ],
            5 => [
                'type_name' => '普通订单'
            ],
            6 => [
                'type_name' => '普通订单'
            ],
            7 => [
                'type_name' => '普通订单'
            ],
            8 => [
                'type_name' => '普通订单'
            ],
            9 => [
                'type_name' => '普通订单'
            ],
            10 => [
                'type_name' => '普通订单'
            ],
            11 => [
                'type_name' => '普通订单'
            ],
            12 => [
                'type_name' => '普通订单'
            ],
            15 => [
                'type_name' => '幸运拼订单'
            ]
        ];

        return $order_type[$type_id]['type_name'];
    }

    /**
     * 获取订单状态
     */
    public static function getOrderStatusName($type_id)
    {
        $order_type = [
            0 => [
                'type_name' => '待付款'
            ],
            1 => [
                'type_name' => '待发货'
            ],
            2 => [
                'type_name' => '已发货'
            ],
            3 => [
                'type_name' => '已收货'
            ],
            4 => [
                'type_name' => '已完成'
            ],
            5 => [
                'type_name' => '已关闭'
            ],
            6 => [
                'type_name' => '链上处理中'
            ],
            7 => [
                'type_name' => '待审核'
            ],
            -1 => [
                'type_name' => '售后中'
            ]
        ];

        return $order_type[$type_id]['type_name'];
    }

    /**
     * 获取支付方式
     */
    public static function getOrderPayTypeName($type_id)
    {
        $order_type = [
            0 => [
                'type_name' => '在线支付'
            ],
            1 => [
                'type_name' => '微信支付'
            ],
            2 => [
                'type_name' => '支付宝'
            ],
            3 => [
                'type_name' => '银行卡'
            ],
            4 => [
                'type_name' => '货到付款'
            ],
            5 => [
                'type_name' => '余额支付'
            ],
            6 => [
                'type_name' => '到店支付'
            ],
            10 => [
                'type_name' => '线下支付'
            ],
            16 => [
                'type_name' => 'ETH支付'
            ],
            17 => [
                'type_name' => 'EOS支付'
            ],
            20 => [
                'type_name' => 'globepay支付'
            ]
        ];

        return $order_type[$type_id]['type_name'];
    }

    /**
     * 批量查找订单结果的佣金
     */
    public function getOrderCommissionInfo($orderGoodsIds = [], $shop_id)
    {
        if (!$orderGoodsIds || !is_array($orderGoodsIds)) {
            return [];
        }
        $order_commission = new VslOrderDistributorCommissionModel();
        if ($shop_id) {
            $orders = $order_commission->where(['order_goods_id' => ['in', $orderGoodsIds], 'shop_id' => $shop_id, 'return_status' => 0])->field('order_goods_id,commissionA,commissionB,commissionC,commission')->select();
        } else {
            $orders = $order_commission->where(['order_goods_id' => ['in', $orderGoodsIds], 'return_status' => 0])->field('order_goods_id,commissionA,commissionB,commissionC,commission')->select();
        }

        if (!$orders) {
            return [];
        }
        $newArray = [];
        foreach ($orders as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_goods_id']] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的微店收益
     */
    public function getOrderProfitInfo($orderGoodsIds = [])
    {
        if (!$orderGoodsIds || !is_array($orderGoodsIds)) {
            return [];
        }
        $orderProfitModel = new VslOrderMicroShopProfitModel();
        $orderProfit = $orderProfitModel->where(['order_goods_id' => ['in', $orderGoodsIds], 'return_status' => 0])->field('order_goods_id,profitA,profitB,profitC,profit')->select();
        if (!$orderProfit) {
            return [];
        }

        $newArray = [];
        foreach ($orderProfit as $value) {
            if (!$value) {
                continue;
            }
            $newArray[$value['order_goods_id']] = $value;
        }
        unset($value);
        return $newArray;
    }

    /**
     * 批量查找订单结果的分红
     */
    public function getOrderBonusInfo($orderGoodsIds = [], $shop_id)
    {
        if (!$orderGoodsIds || !is_array($orderGoodsIds)) {
            return [];
        }

        $orderBonusLogModel = new VslOrderBonusLogModel();
        if ($shop_id) {
            $bonus = $orderBonusLogModel->where(['order_goods_id' => ['in', $orderGoodsIds], 'shop_id' => $shop_id, 'area_return_status' => 0])->field('sum(area_bonus) as sum_area,sum(team_bonus) as sum_team,sum(global_bonus) as sum_global, order_goods_id')->group('order_goods_id')->select();
        } else {
            $bonus = $orderBonusLogModel->where(['order_goods_id' => ['in', $orderGoodsIds], 'area_return_status' => 0])->field('sum(area_bonus) as sum_area,sum(team_bonus) as sum_team,sum(global_bonus) as sum_global, order_goods_id')->group('order_goods_id')->select();
        }

        if (!$bonus) {
            return [];
        }
        return array_column(objToArr($bonus), null, 'order_goods_id');
    }

    /**
     * 是否该订单用户收货地址属于其商品限购区域
     * @param $order_id
     * @return bool 【true 属于 false 不属于】
     * @throws \think\Exception\DbException
     */
    public function isOrderGoodsBelongAreaList($order_id, &$goods_name_area)
    {
        $orderM = new VslOrderModel();
        $order_data = $orderM::get(['order_id' => $order_id], ['order_goods']);

        if (!$order_data) {
            return false;
        }
        $goodsSer = new GoodsService();
        foreach ($order_data['order_goods'] as $goods) {
            $area_list = $goodsSer->getGoodsAreaList($goods['goods_id']);
            if (!$area_list['district']) {
                continue;
            }
            $check = $goodsSer->isUserAreaBelongGoodsAllowArea($order_data['receiver_district'], $area_list);
            if ($check) {
                $goods_name_area = $goods['goods_name'];
                return true;
            }
        }
        return false;
    }
    public function addBonusToLog($order_id = 0){
        if(!is_dir('addons/areabonus') && !is_dir('addons/globalbonus') && !is_dir('addons/teambonus')){
            return;
        }
        $check = Db::table('vsl_order_bonus_log')->where(['order_id' => $order_id])->field('id')->find();
        if($check){
            return true;
        }
        $orderBonus = objToArr(Db::table('vsl_order_bonus')->alias('a')->join('vsl_order b','a.order_id=b.order_id')->where(['a.order_id' => $order_id])->field('a.*,b.create_time,b.finish_time')->select());
        $insertData = [];
        if(!$orderBonus){
            return true;
        }
        foreach($orderBonus as $val){
            $order_goods_id = $val['order_goods_id'];
            if($val['from_type'] == 1){
                $insertData[$order_goods_id]['global_bonus'] = isset($insertData[$order_goods_id]['global_bonus']) ? ($insertData[$order_goods_id]['global_bonus'] + $val['bonus']) : $val['bonus'];
            }
            if($val['from_type'] == 2){
                $insertData[$order_goods_id]['area_bonus'] = isset($insertData[$order_goods_id]['area_bonus']) ? ($insertData[$order_goods_id]['area_bonus'] + $val['bonus']) : $val['bonus'];
            }
            if($val['from_type'] == 3){
                $insertData[$order_goods_id]['team_bonus'] += isset($insertData[$order_goods_id]['team_bonus']) ? ($insertData[$order_goods_id]['team_bonus'] + $val['bonus']) : $val['bonus'];
            }
            $orderbonusglobal = objToArr(Db::table('vsl_order_bonus')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 1])->field('uid,bonus')->select());
            if($orderbonusglobal){
                $orderbonusglobal = array_columns($orderbonusglobal, NULL, 'uid');
                foreach($orderbonusglobal as $kg => $vg){
                    $orderbonusglobal[$kg]['tips'] = '_'.$vg['uid'].'_';
                    unset($orderbonusglobal[$kg]['uid']);
                }
                unset($vg);
                $insertData[$order_goods_id]['global_bonus_details'] = json_encode($orderbonusglobal,true);
            }
            $orderbonusarea = objToArr(Db::table('vsl_order_bonus')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 2])->field('uid,bonus')->select());
            if($orderbonusarea){
                $orderbonusarea = array_columns($orderbonusarea, NULL, 'uid');
                foreach($orderbonusarea as $ka => $va){
                    $orderbonusarea[$ka]['tips'] = '_'.$va['uid'].'_';
                    unset($orderbonusarea[$ka]['uid']);
                }
                unset($va);
                $insertData[$order_goods_id]['area_bonus_details'] = json_encode($orderbonusarea,true);
            }
            $orderbonusteam = objToArr(Db::table('vsl_order_bonus')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 3])->field('uid,bonus,level_award')->select());
            if($orderbonusteam){
                $orderbonusteam = array_columns($orderbonusteam, NULL, 'uid');
                foreach($orderbonusteam as $kt => $vt){
                    $orderbonusteam[$kt]['tips'] = '_'.$vt['uid'].'_';
                    unset($orderbonusteam[$kt]['uid']);
                }
                unset($vt);
                $insertData[$order_goods_id]['team_bonus_details'] = json_encode($orderbonusteam,true);
            }
            if(isset($insertData[$order_goods_id]['order_id'])){
                continue;
            }

            $insertData[$order_goods_id]['order_id'] = $val['order_id'];
            $insertData[$order_goods_id]['order_goods_id'] = $val['order_goods_id'];
            $insertData[$order_goods_id]['buyer_id'] = $val['buyer_id'];
            $insertData[$order_goods_id]['website_id'] = $val['website_id'];
            $insertData[$order_goods_id]['area_pay_status'] = $val['pay_status'];
            $insertData[$order_goods_id]['team_pay_status'] = $val['pay_status'];
            $insertData[$order_goods_id]['global_pay_status'] = $val['pay_status'];
            $insertData[$order_goods_id]['area_cal_status'] = $val['cal_status'];
            $insertData[$order_goods_id]['team_cal_status'] = $val['cal_status'];
            $insertData[$order_goods_id]['global_cal_status'] = $val['cal_status'];
            $insertData[$order_goods_id]['area_return_status'] = $val['return_status'];
            $insertData[$order_goods_id]['team_return_status'] = $val['return_status'];
            $insertData[$order_goods_id]['global_return_status'] = $val['return_status'];
            $insertData[$order_goods_id]['shop_id'] = $val['shop_id'];
            $insertData[$order_goods_id]['create_time'] = $val['create_time'];
            $insertData[$order_goods_id]['update_time'] = $val['finish_time'];
        }
        unset($val);
        $insertData = array_merge($insertData);
        $bonuslog = new \addons\bonus\model\VslOrderBonusLogModel();
        $bonuslog->saveAll($insertData);
        return true;
    }
    /**
     * 订单完成赠送卷
     */
    public function sendCoupon($orderid){
        debugLog($orderid,'订单完成赠送卷-1');
        $orderModel = new VslOrderModel();
        $order_info = $orderModel->getInfo(['order_id'=>$orderid],'order_status,website_id');
        #获取订单商品 查看商品对应的赠送设置
        $order_item = new VslOrderGoodsModel();
        $order_item_list = $order_item->where(['order_id' => $orderid])->select();
        if(!$order_item_list){
            debugLog($orderid,'订单完成赠送卷-终止1');
            return;
        }
        $goodsModel = new VslGoodsModel();
        foreach ($order_item_list as $key_item => $v_item) {
            if($v_item['send_status'] == 1){
                debugLog($orderid,'订单完成赠送卷-终止1-1');
                continue;
            }
            if($v_item['refund_status'] == 5){
                debugLog($orderid,'订单完成赠送卷-终止2');
                continue;
            }
            $goods_info = $goodsModel->getInfo(['goods_id'=>$v_item['goods_id']],'coupon_type_id_array_num,gift_voucher_id_array_num,package_voucher_id_array_num');
            if(!$goods_info){
                debugLog($orderid,'订单完成赠送卷-终止3');
                return;
            }
            #优惠卷
            if($goods_info['coupon_type_id_array_num']){
                $coupon = new \addons\coupontype\server\Coupon();
                $coupon_type_id_array_num = unserialize($goods_info['coupon_type_id_array_num']);
                debugLog($coupon_type_id_array_num,'订单完成赠送卷-2');
                foreach ($coupon_type_id_array_num as $k1 => $v1) {
                    foreach ($v1 as $k2 => $v2) {
                        if($v2 <= 0){
                            continue;
                        }
                        for ($i=0; $i < $v2; $i++) {
                            $res = $coupon->userAchieveCoupon($v_item['buyer_id'], $k2, 7);
                            debugLog($res,'订单完成赠送卷-2-1');
                        }
                    }
                }
            }

           #赠品卷
            if($goods_info['gift_voucher_id_array_num']){
                $voucher = new \addons\giftvoucher\server\GiftVoucher();
                $gift_voucher_id_array_num = unserialize($goods_info['gift_voucher_id_array_num']);
                debugLog($gift_voucher_id_array_num,'订单完成赠送卷-3');
                foreach ($gift_voucher_id_array_num as $k1 => $v1) {
                    foreach ($v1 as $k2 => $v2) {
                        if($v2 <= 0){
                            continue;
                        }
                        for ($i=0; $i < $v2; $i++) {
                            if($voucher->isGiftVoucherReceive(['gift_voucher_id' => $k2, 'website_id' => $order_info['website_id']])){
                                $res = $voucher->getUserReceive($v_item['buyer_id'],$k2,3,1,$orderid);
                                debugLog($res,'订单完成赠送卷-3-1');
                            }
                        }
                    }
                }
            }

            #卷包
            if($goods_info['package_voucher_id_array_num']){
                $voucherPackage = new \addons\voucherpackage\service\VoucherPackage();
                $package_voucher_id_array_num = unserialize($goods_info['package_voucher_id_array_num']);
                debugLog($package_voucher_id_array_num,'订单完成赠送卷-4');
                foreach ($package_voucher_id_array_num as $k1 => $v1) {
                    foreach ($v1 as $k2 => $v2) {
                        if($v2 <= 0){
                            continue;
                        }
                        for ($i=0; $i < $v2; $i++) {
                            $res = $voucherPackage->userAchieveVoucherPackage($v_item['buyer_id'],$k2,$orderid);
                            debugLog($res,'订单完成赠送卷-4-1');
                        }
                    }
                }
            }
            #更新当前订单项发放状态
            $orderGoodsModel = new VslOrderGoodsModel();
            $orderGoodsModel->save(['send_status'=>1],['order_goods_id'=>$v_item['order_goods_id']]);
        }
        return;
    }

    /**
     * 获取统计积分订单
     *
     * @param int $page_index
     * @param int $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    public function getCommissionOrderList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {

        $order_model = new VslOrderModel();
        $orders = $order_model->getViewList4($page_index, $page_size, $condition, $order);
        $order_list = objToArr($orders);
        $distribution = getAddons('distribution', $this->website_id);
        $teambonus = getAddons('teambonus', $this->website_id);
        if (!empty($order_list['data'])) {
            //处理订单信息,用于批量操作,减少数据库查询
            $orderIds = dealArray(array_column($order_list['data'], 'order_id'), false, true);

            $orderCommissions = [];
            $orderBonus = [];
            if ($distribution) {
                //批量查找订单结果的佣金
                $orderCommissions = $this->getOrderCommission($orderIds);
            }
            if ($teambonus) {
                //批量查找订单结果的分红
                $orderBonus = $this->getOrderBonus($orderIds);
            }
            foreach ($order_list['data'] as $k => &$v) {
                $orderId = $v['order_id'];
                $order_list['data'][$k]['team_bonus'] = 0;
                //查询订单分红
                if (isset($orderBonus[$orderId]['sum_team'])) {
                    $order_list['data'][$k]['team_bonus'] = twoDecimal($orderBonus[$orderId]['sum_team']);
                }
                //查询订单佣金和积分
                $commission = 0;
                if (isset($orderCommissions[$orderId])) {
                    foreach ($orderCommissions[$orderId] as $key1 => $value) {
                        $commission += $value['commission'];
                    }
                }
                $order_list['data'][$k]['commission'] = round($commission, 2);
                $v['sign_time'] = date('Y-m-d H:i:s', $v['sign_time']);
                $v['order_status_name'] = $v['order_status'] == 3 ? '未发放':'已发放';
                $v['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            }
        }

        return $order_list;
    }
}
