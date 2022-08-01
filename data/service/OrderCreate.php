<?php

namespace data\service;
/**
 * 订单
 */
use addons\supplier\server\Supplier;
use data\model\OrderLockModel;
use data\model\VslOrderModel;
use data\service\Goods as GoodsService;
use data\service\WebSite as WebSite;
use addons\invoice\controller\Invoice as InvoiceController;
class OrderCreate extends BaseService
{
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

    /**
     * 重组订单提交的活动信息和商品数据
     * @param null $goods_service
     * @param $shop_list
     * @param $address
     * @return \think\response\Json
     * @throws \think\Exception\DbException
     */
    public function getOrderGoodsArr($goods_service = null, $shop_list, $address)
    {
        $tempUserArea = [];
        if ($address) {
            $tempUserArea = [
                'province' => $address['province'],
                'city' => $address['city'],
                'district' => $address['district'],
            ];
        }
        $new_sku = [];
        if($shop_list){
            foreach ($shop_list as $keys => $values) {
                foreach ($values['goods_list'] as $k => $v){
                    if(getAddons('supplier',$this->website_id)){
                        $supplierSer = new Supplier();
                        $checkRes = $supplierSer->isShowShopOfSupplierGoodsBySkuId($v['sku_id']);
                        if (isset($checkRes['code']) && $checkRes['code'] <0){
                            return $checkRes;
                        }
                    }
                    //线下自提
                    if ($values['store_id']){
                        $rdata['goods_id'] = $v['goods_id'];
                        if ($v['presell_id']) {
                            $rdata['presell_id'] = intval($v['presell_id']);
                        }
                        if (intval($values['coupon_id'])) {
                            $rdata['coupon_id'] = intval($values['coupon_id']);
                        }
                        if (intval($v['seckill_id'])) {
                            $rdata['seckill_id'] = intval($v['seckill_id']);
                        }
                        if(intval($v['channel_id'])) {
                            $rdata['channel_id'] = 0;
                        }
                        if(intval($v['bargain_id'])){//砍价
                            $rdata['bargain_id'] = intval($v['bargain_id']);
                            $order_data['bargain_id'] = $rdata['bargain_id'];
                        }
                        if($v['anchor_id']){
                            $rdata['anchor_id'] = intval($v['anchor_id']);
                        }
                        if ($values['receive_goods_code']) {
                            $rdata['receive_goods_code'] = $values['receive_goods_code'];
                        }
                        $rdata['store_id'] = $values['store_id'];
                        $rdata['sku_id'] = intval($v['sku_id']);
                        $rdata['num'] = abs(intval($v['num']));
                        array_push($new_sku, $rdata);
                    }elseif($values['card_store_id']){
                        $rdata['goods_id'] = $v['goods_id'];
                        //计时计次商品
                        if ($v['presell_id']) {
                            $rdata['presell_id'] = intval($v['presell_id']);
                        }
                        if (intval($values['coupon_id'])) {
                            $rdata['coupon_id'] = intval($values['coupon_id']);
                        }
                        if (intval($v['seckill_id'])) {
                            $rdata['seckill_id'] = intval($v['seckill_id']);
                        }
                        if(intval($v['bargain_id'])){//砍价
                            $rdata['bargain_id'] = intval($v['bargain_id']);
                            $order_data['bargain_id'] = $rdata['bargain_id'];
                        }
                        if(intval($v['channel_id'])) {
                            $rdata['channel_id'] = 0;
                        }
                        if($v['anchor_id']){
                            $rdata['anchor_id'] = intval($v['anchor_id']);
                        }
                        if ($values['receive_goods_code']) {
                            $rdata['receive_goods_code'] = $values['receive_goods_code'];
                        }
                        $rdata['store_id'] = $values['card_store_id'];
                        $rdata['sku_id'] = intval($v['sku_id']);
                        $rdata['num'] = abs(intval($v['num']));
                        array_push($new_sku, $rdata);
                    }else{
                        $rdata['goods_id'] = $v['goods_id'];
                        //查询是否有
                        # todo... 处理商品地区限购
                        $goodsAreaArr = $goods_service->getGoodsAreaList($v['goods_id']);
                        $areaRes = $goods_service->isUserAreaBelongGoodsAllowArea($tempUserArea, $goodsAreaArr);
                        if ($areaRes) {
                            unset($shop_list[$keys]['goods_list'][$k]);
                            continue;//限购的直接跳过
                        }
                        if($v['presell_id']){
                            $rdata['presell_id'] = intval($v['presell_id']);
                        }
                        if(intval($values['coupon_id'])){
                            $rdata['coupon_id'] = intval($values['coupon_id']);
                        }
                        if(intval($v['seckill_id'])){
                            $rdata['seckill_id'] = intval($v['seckill_id']);
                        }
                        if(intval($v['bargain_id'])){
                            $rdata['bargain_id'] = intval($v['bargain_id']);
                            $order_data['bargain_id'] = $rdata['bargain_id'];
                        }
                        if(intval($v['channel_id'])){
                            $rdata['channel_id'] = intval($v['channel_id']);
                        }
                        if($v['anchor_id']){
                            $rdata['anchor_id'] = intval($v['anchor_id']);
                        }
                        if ($values['receive_goods_code']) {
                            $rdata['receive_goods_code'] = $values['receive_goods_code'];
                        }
                        $rdata['sku_id'] = intval($v['sku_id']);
                        $rdata['num'] = abs(intval($v['num']));
                        array_push($new_sku,$rdata);
                        if (intval($v['channel_id'])) {
                            unset($rdata['channel_id']);
                        }
                    }
                    unset($rdata);
                }
            }
            return  $new_sku;
        }
    }

    /**
     * 重新分配运费
     */
    /**
     * 重新分配运费
     * @param $goods_list
     * @param $goods_model
     * @param $goods_express
     * @param $is_free_shipping
     * @param $address
     * @param int $is_full_cut
     */
    public function reAllocateShippingFee(&$goods_list, $full_cut, $goods_express, $is_free_shipping, $address)
    {
        $ids = [];
        $fixed_ids = []; //固定运费
        $goodsSer = new GoodsService();
        if (count($goods_list) > 1) {
            foreach ($goods_list as $kv => $vv) {
                if ($vv['shipping_fee_type'] == 2) {
                    //存在运费 且是运费模板商品
                    //获取运费
                    $goodsInfo = $goodsSer->getGoodsDetailById($vv['goods_id']);
                    //重组
                    $new_ship = array(
                        'goods_id' => $vv['goods_id'],
                        'sku_id' => $vv['sku_id'],
                        'shipping_fee_id' => $goodsInfo['shipping_fee_id'],
                        'num' => $vv['num'],
                        'goods_weight' => $goodsInfo['goods_weight'] * $vv['num'],
                        'goods_volume' => $goodsInfo['goods_volume'] * $vv['num'],
                        'goods_count' => $goodsInfo['goods_count'] * $vv['num'],
                    );
                    $goods_list[$kv]['goods_weight'] = $goodsInfo['goods_weight'] * $vv['num'];
                    $goods_list[$kv]['goods_volume'] = $goodsInfo['goods_volume'] * $vv['num'];
                    $goods_list[$kv]['goods_count'] = $goodsInfo['goods_count'] * $vv['num'];
                    $goods_list[$kv]['shipping_fee_id'] = $goodsInfo['shipping_fee_id'];
                    array_push($ids, $new_ship);
                }else if($vv['shipping_fee'] > 0 && $vv['shipping_fee_type'] == 1){
                    $fixed_info = array(
                        'goods_id' => $vv['goods_id'],
                        'sku_id' => $vv['sku_id'],
                        'shipping_fee' => $vv['shipping_fee'],
                    );
                    array_push($fixed_ids, $fixed_info);
                    //获取固定运费，而且金额大于0的商品重新划分
                    $goods_list[$kv]['goods_weight'] = 0;
                    $goods_list[$kv]['goods_volume'] = 0;
                    $goods_list[$kv]['goods_count'] = 0;
                    $goods_list[$kv]['shipping_fee_id'] = 0;
                } else {
                    $goods_list[$kv]['goods_weight'] = 0;
                    $goods_list[$kv]['goods_volume'] = 0;
                    $goods_list[$kv]['goods_count'] = 0;
                    $goods_list[$kv]['shipping_fee_id'] = 0;
                }
            }
            //处理多规格 固定运费问题
            if($fixed_ids){
                $fixeds = array_group_by($fixed_ids,'goods_id');
                foreach ($fixeds as $k_f => $v_f) {
                    if(count($v_f) >=2){
                        $new_fixeds_shipping_fee = round($v_f[0]['shipping_fee'] / count($v_f),2);
                        foreach ($v_f as $k_v_f => $v_v_f) {
                            foreach ($goods_list as $ka => $va) {
                                if ($va['sku_id'] == $v_v_f['sku_id']) {
                                    $goods_list[$ka]['shipping_fee_total'] = 0;
                                    if (!empty($full_cut) &&
                                        (array)$full_cut &&
                                        $full_cut['free_shipping'] == 1 &&
                                        (in_array($full_cut['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                        // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                        $goods_list[$ka]['shipping_fee'] = 0;
                                        $goods_list[$ka]['shipping_fee_total'] = $new_fixeds_shipping_fee;
                                    }else{
                                        $goods_list[$ka]['shipping_fee'] = $new_fixeds_shipping_fee;
                                    }
                                }
                            }
                        }
                    }
                }
            }
            if ($ids) {
                foreach ($ids as $keys => $values) {
                    $resq[$values['shipping_fee_id']][] = $values;
                }
                foreach ($resq as $keyp => $valuep) {

                    if (count($valuep) > 1) {
                        $checktempgoods = [];
                        $goods_weight = 0;
                        $goods_volume = 0;
                        $goods_count = 0;
                        foreach ($valuep as $keyt => $valuet) {
                            $checktempgoods[$keyt]['count'] = $valuet['num'];
                            $checktempgoods[$keyt]['goods_id'] = $valuet['goods_id'];
                            $goods_weight += $valuet['goods_weight'] > 0 ? $valuet['goods_weight'] : 1;
                            $goods_volume += $valuet['goods_volume'] > 0 ? $valuet['goods_volume'] : 1;
                            $goods_count += $valuet['goods_count'] > 0 ? $valuet['goods_count'] : 1;
                        }
                        $shipping_fee_valuet = $goods_express->getGoodsExpressTemplate($checktempgoods, $address['district'])['totalFee'];
                        if($is_free_shipping) {
                            $shipping_fee_valuet = 0;
                        }
                        //获取平均值  2/3可能会发生除不尽的情况 最后一位获取剩余
                        $count_weight_shipping_fee = 0;
                        $count_volume_shipping_fee = 0;
                        $count_count_shipping_fee = 0;
                        foreach ($goods_list as $kb => $vb) {
                            //处理体积 重量为0的情况
                            $vb['goods_weight'] = $vb['goods_weight'] > 0 ? $vb['goods_weight'] : 1;
                            $vb['goods_volume'] = $vb['goods_volume'] > 0 ? $vb['goods_volume'] : 1;
                            $vb['goods_count'] = $vb['goods_count'] > 0 ? $vb['goods_count'] : 1;
                            if ($vb['shipping_fee_id'] == $keyp) {
                                if($kb == count($goods_list)-1){
                                    //最后一项
                                    if ($goods_weight > 0) {
                                        $goods_list[$kb]['shipping_fee_total'] = 0;
                                        if (!empty($full_cut) &&
                                            (array)$full_cut &&
                                            $full_cut['free_shipping'] == 1 &&
                                            (in_array($vb['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                            // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                            $goods_list[$kb]['shipping_fee'] = 0;
                                            $goods_list[$kb]['shipping_fee_total'] = round($shipping_fee_valuet - $count_weight_shipping_fee, 2);
                                        }else{
                                            $goods_list[$kb]['shipping_fee'] = round($shipping_fee_valuet - $count_weight_shipping_fee, 2);
                                        }

                                    }
                                    if ($goods_volume > 0) {
                                        $goods_list[$kb]['shipping_fee_total'] = 0;
                                        if (!empty($full_cut) &&
                                            (array)$full_cut &&
                                            $full_cut['free_shipping'] == 1 &&
                                            (in_array($vb['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                            // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                            $goods_list[$kb]['shipping_fee'] = 0;
                                            $goods_list[$kb]['shipping_fee_total'] = round($shipping_fee_valuet - $count_volume_shipping_fee, 2);
                                        }else{
                                            $goods_list[$kb]['shipping_fee'] = round($shipping_fee_valuet - $count_volume_shipping_fee, 2);
                                        }
                                    }
                                    if ($goods_count > 0) {
                                        $goods_list[$kb]['shipping_fee_total'] = 0;
                                        if (!empty($full_cut) &&
                                            (array)$full_cut &&
                                            $full_cut['free_shipping'] == 1 &&
                                            (in_array($vb['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                            // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                            $goods_list[$kb]['shipping_fee'] = 0;
                                            $goods_list[$kb]['shipping_fee_total'] = round($shipping_fee_valuet - $count_count_shipping_fee, 2);
                                        }else{
                                            $goods_list[$kb]['shipping_fee'] = round($shipping_fee_valuet - $count_count_shipping_fee, 2);
                                        }
                                    }
                                }else{
                                    if ($goods_weight > 0) {
                                        $goods_list[$kb]['shipping_fee_total'] = 0;
                                        if (!empty($full_cut) &&
                                            (array)$full_cut &&
                                            $full_cut['free_shipping'] == 1 &&
                                            (in_array($vb['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                            // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                            $goods_list[$kb]['shipping_fee'] = 0;
                                            $goods_list[$kb]['shipping_fee_total'] = round($shipping_fee_valuet / $goods_weight * $vb['goods_weight'], 2);
                                        }else{
                                            $goods_list[$kb]['shipping_fee'] = round($shipping_fee_valuet / $goods_weight * $vb['goods_weight'], 2);
                                        }
                                        $count_weight_shipping_fee += $goods_list[$kb]['shipping_fee'];
                                    }
                                    if ($goods_volume > 0) {
                                        $goods_list[$kb]['shipping_fee_total'] = 0;
                                        if (!empty($full_cut) &&
                                            (array)$full_cut &&
                                            $full_cut['free_shipping'] == 1 &&
                                            (in_array($vb['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                            // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                            $goods_list[$kb]['shipping_fee'] = 0;
                                            $goods_list[$kb]['shipping_fee_total'] = round($shipping_fee_valuet / $goods_volume * $vb['goods_volume'], 2);
                                        }else{
                                            $goods_list[$kb]['shipping_fee'] = round($shipping_fee_valuet / $goods_volume * $vb['goods_volume'], 2);
                                        }

                                        $count_volume_shipping_fee += $goods_list[$kb]['shipping_fee'];
                                    }
                                    if ($goods_count > 0) {
                                        $goods_list[$kb]['shipping_fee_total'] = 0;
                                        if (!empty($full_cut) &&
                                            (array)$full_cut &&
                                            $full_cut['free_shipping'] == 1 &&
                                            (in_array($vb['goods_id'], $full_cut['goods_limit']) || $full_cut['range_type'] == 1)) {
                                            // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                                            $goods_list[$kb]['shipping_fee'] = 0;
                                            $goods_list[$kb]['shipping_fee_total'] = round($shipping_fee_valuet / $goods_count * $vb['goods_count'], 2);
                                        }else{
                                            $goods_list[$kb]['shipping_fee'] = round($shipping_fee_valuet / $goods_count * $vb['goods_count'], 2);
                                        }

                                        $count_count_shipping_fee += $goods_list[$kb]['shipping_fee'];
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取订单数据
     * @param $return_data
     * @return mixed
     */
    public function getOrderData($return_data, &$order_data)
    {
        $order_data['order'] = array();
        foreach ($return_data as $key => $value) {
            $shop_id = $value['shop_id'];
            foreach ($value['goods_list'] as $ks => $vs) {
                $temp_sku_id = $vs['sku_id'];
                $order_data['order'][$shop_id]['sku'][$ks]['sku_id'] = $temp_sku_id;
                $order_data['order'][$shop_id]['sku'][$ks]['num'] = $vs['num'];
                $order_data['order'][$shop_id]['sku'][$ks]['goods_name'] = $vs['goods_name'];
                $order_data['order'][$shop_id]['sku'][$ks]['goods_id'] = $vs['goods_id'];
                $order_data['order'][$shop_id]['sku'][$ks]['seckill_id'] = $vs['seckill_id'];
            }
            $order_data['order'][$shop_id]['shop_name'] = $value['shop_name'];
            //组装优惠金额
            $order_data['shop'][$shop_id]['member_amount'] = $value['member_promotion'];
            $order_data['promotion'][$shop_id]['coupon']['coupon_reduction_amount'] = $value['coupon_promotion'];
        }
        return $order_data;
    }

    /**
     * 发票数据处理
     * @param goods $goods_service
     * @param $post_data
     * @param $order_info
     * @param $order_id
     * @param $vv
     */
    public function actInvoiceData(\data\service\goods $goods_service, $post_data, $order_info, $order_id, $vv)
    {
        $invoice_goods_detail = '';
        $invoice_goods_category = '';
        foreach ($post_data['shop_list'] as $keys => $values) {
            if ($values['shop_id'] != $vv['shop_id']) {
                continue;
            }
            $temp_shop_amount = $order_info['goods_money'] - $order_info['deduction_money'] > 0 ? $order_info['goods_money'] - $order_info['deduction_money'] : $values['shop_amount'];
            if ($temp_shop_amount <= 0) {
                continue;
            }
            $invoice_goods_detail_goods_name = '';
            foreach ($values['goods_list'] as $k => $v) {
                //商品明细 （名字 价格/ 分类 价格）
                $goodsCategoryName = $goods_service->getGoodsCategoryNameByGoodId($v['goods_id']);
                $goodsSku = $goods_service->getSkuBySkuid($v['sku_id'],'sku_name')['sku_name'];
                $invoice_goods_detail_goods_name = $v['goods_name'];
                $invoice_goods_detail .= ' '.$goodsSku .' *'.$v['num'].' ￥'.$v['price'].'、';//eg:华为P30 8+256g黑色  *1 99.00、
                $invoice_goods_category .= $goodsCategoryName;//eg 服装>上衣 520¥__
            }
            $invoice_goods_detail = $invoice_goods_detail_goods_name.rtrim($invoice_goods_detail,'、');
            if (getAddons('invoice', $this->website_id, $this->instance_id)) {
                if (!isset($values['invoice']['type'])) {
                    continue;
                }
                $order_model = new VslOrderModel();
                $order_no = $order_model->getInfo(['order_id' => $order_id], 'order_no')['order_no'];
                //发票数据入库
                $invoice = new InvoiceController();
                $values['invoice']['shop_id'] = $values['shop_id'];
                $values['invoice']['website_id'] = $this->website_id;
                $values['invoice']['order_no'] = $order_no;
                $values['invoice']['order_id'] = $order_id;
                $values['invoice']['invoice_goods_detail'] = rtrim($invoice_goods_detail,'__');
                $values['invoice']['invoice_goods_category'] = rtrim($invoice_goods_category, '__');
                $values['invoice']['price'] = $temp_shop_amount;//订单价格
                $values['invoice']['pay_money'] = $order_info['pay_money'];//支付0则直接完成
                //                        $values['invoice']['status'] = $is_presell_info ? 3 :0;//预售未付尾款
                $invoice->postInvoiceByOrderCreate($values['invoice']);//todo... invoice_type 优化，不应该由前端传过来的值
            }
        }
    }

    /**
     * 获取当前商品是参与的什么活动，塞回库存
     * @param $post_data
     * @return string
     */
    public function actRedisRefoundStock($post_data)
    {
        $redis = connectRedis();
        foreach ($post_data['shop_list'] as $shop_id => $v) {
            foreach ($v['goods_list'] as $sku => $sku_list) {
                switch($sku_list['goods_type']){
                    case 'store':
                        $redis_stock_key = 'store_goods_'.$sku_list['goods_id'].'_'.$sku_list['sku_id'];
                        break;
                    case 'seckill':
                        $redis_stock_key = 'seckill_' . $sku_list['seckill_id'] . '_' . $sku_list['goods_id'] . '_' . $sku_list['sku_id'];
                        break;
                    case 'channel':
                        $redis_stock_key = 'channel_'.$sku_list['channel_id'].'_'.$sku_list['sku_id'];
                        break;
                    case 'bargain':
                        $redis_stock_key = 'bargain_'.$sku_list['bargain_id'].'_'.$sku_list['sku_id'];
                        break;
                    case 'presell':
                        $redis_stock_key = 'presell_'.$sku_list['presell_id'].'_'.$sku_list['sku_id'];
                        break;
                    case 'group':
                        $redis_stock_key = 'goods_'.$sku_list['goods_id'].'_'.$sku_list['sku_id'];
                        break;
                    default:
                        $redis_stock_key = 'goods_'.$sku_list['goods_id'].'_'.$sku_list['sku_id'];
                        break;
                }
                for($s=0;$s<$sku_list['num'];$s++){
                    $redis->incr($redis_stock_key);
                }
            }
        }
        return $redis_stock_key;
    }
}