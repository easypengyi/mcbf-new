<?php

namespace app\wapapi\controller;

use addons\bargain\service\Bargain;
use addons\blockchain\service\Block;
use addons\channel\model\VslChannelGoodsModel;
use addons\channel\model\VslChannelGoodsSkuModel;
use addons\channel\model\VslChannelModel;
use addons\coupontype\model\VslCouponTypeModel;
use addons\groupshopping\model\VslGroupShoppingModel;
use addons\liveshopping\model\LiveModel;
use addons\liveshopping\model\LiveRecordModel;
use addons\liveshopping\model\LiveUserDataModel;
use addons\membercard\server\Membercard as MembercardSer;
use addons\store\model\VslStoreAssistantModel;
use addons\store\model\VslStoreJobsModel;
use data\model\AlbumPictureModel;
use data\model\VslAppointOrderModel;
use data\model\VslMemberLevelModel;
use addons\discount\model\VslPromotionDiscountModel as DiscountModel;
use addons\discount\server\Discount;
use addons\distribution\service\Distributor;
use addons\fullcut\service\Fullcut;
use addons\gift\model\VslPromotionGiftModel;
use addons\giftvoucher\model\VslGiftVoucherModel;
use addons\poster\service\Poster;
use addons\presell\service\Presell;
use addons\presell\service\Presell as PresellService;
use addons\seckill\model\VslSeckGoodsModel;
use addons\store\model\VslStoreModel;
use addons\store\server\Store as storeServer;
use data\model\UserModel;
use data\model\VslGoodsModel;
use data\model\VslGoodsSkuModel;
use data\model\VslGoodsViewModel;
use data\model\VslMemberModel;
use data\model\VslStoreCartModel;
use data\model\VslStoreGoodsModel;
use data\model\VslStoreGoodsSkuModel as VslStoreGoodsSkuModel;
use data\service\Goods as GoodsService;
use data\service\GoodsCategory;
use data\service\AddonsConfig as AddonsConfigService;
use data\model\VslCartModel;
use data\service\Config;
use data\model\VslMemberExpressAddressModel;
use data\service\promotion\GoodsExpress;
use data\service\Address;
use data\service\Order\Order as OrderBusiness;
use data\service\promotion\GoodsPreference;
use data\service\User;
use think\Cache;
use think\Db;
use addons\coupontype\server\Coupon as CouponServer;
use addons\seckill\server\Seckill as SeckillServer;
use addons\groupshopping\server\GroupShopping as GroupShoppingServer;
use addons\groupshopping\model\VslGroupGoodsModel;
use think\Request;
use think\Session;
use addons\store\server\Store;
use addons\qlkefu\server\Qlkefu;
use data\service\ShopAccount;
use addons\invoice\server\Invoice as InvoiceServer;
use addons\shop\service\Shop as ShopServer;
use addons\microshop\service\MicroShop as  MicroShopService;
use addons\customform\server\Custom as CustomFormServer;
use addons\receivegoodscode\server\ReceiveGoodsCode as ReceiveGoodsCodeSer;
use addons\supplier\server\Supplier as SupplierSer;
use addons\luckyspell\server\Luckyspell as luckySpellServer;
use addons\luckyspell\model\VslLuckySpellModel;

class Goods extends BaseController
{

    public function __construct()
    {

        parent::__construct();
    }

    /**
     * 购物车页面
     */ 
    public function cart()
    {
        // 拉黑不能登录
        $user_status = $this->user->getUserStatus($this->uid);
        if ($user_status == USER_LOCK) {
            echo json_encode(['code' => USER_LOCK, 'message' => '用户被锁定'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $goods = new GoodsService();
        
        $msg = '';
        $cartlist = $goods->getCart($this->uid, 0, $msg);
        
        //分页 -- edit for 2020/09/25 修正该处分页引起的异常  前端没有做分页请求 暂时后台全部加载
        // $page_size = request()->post('page_size') ?: PAGESIZE;  //分页大小
        $page_size = count($cartlist) > 0 ? count($cartlist) : 1;  //分页大小
        
        $page_index = request()->post('page_index') ?: '1'; //分页索引
        $count = count($cartlist); //数据总数
        $page_count = ceil($count / $page_size); //总页数
        $start = $page_size * ($page_index - 1);   //开始读取key
        //筛选数据,去掉多余图片信息
        foreach ($cartlist as $k => $v) {
            //供应商关闭，不显示购物车
            if (getAddons('supplier',$this->website_id) && $v['supplier_id']){
                $supplier = new SupplierSer();
                $supplier_status = $supplier->getSupplierInfoBySupplierId($v['supplier_id'],'status');
                if ($supplier_status['status'] != 1){
                    unset($cartlist[$k]);
                    continue;
                }
            }
            if ($start <= $k && $k <= $start + $page_size - 1) {
                $cartlist[$k]['picture_info'] = getApiSrc($v['picture_info']['pic_cover']);
            } else {
                unset($cartlist[$k]);
            }
            //购物车暂时不考虑主播商品
            $cartlist[$k]['anchor_id'] = 0;
        }

        // 店铺，店铺中的商品
        $list = Array();
        
        //重组结构,按店铺组合
        if (!empty($cartlist)) {
            foreach ($cartlist as $i => $v) {
                $cartlist[$i]['bargain_id'] = 0;
                if ($this->is_seckill) {
                    //查出商品是否在秒杀活动
                    $goods_id = $cartlist[$i]["goods_id"];
                    $condition_is_seckill['nsg.goods_id'] = $goods_id;
                    $seckill_server = new SeckillServer();
                    $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill);
                }
                $list['shop_info'][$v['shop_id']]['goods_list'][] = $cartlist[$i];
                $list['shop_info'][$v['shop_id']]['shop_name'] = $cartlist[$i]["shop_name"];
                $list['shop_info'][$v['shop_id']]['shop_id'] = $cartlist[$i]["shop_id"];
                //折扣信息
                $list['shop_info'][$v['shop_id']]['discount_info'] = (object)array();
                //限时折扣
                if ($this->is_discount && $cartlist[$i]['promotion_type'] == 5) {
                    $discount_server = new Discount();
                    $discount_info = $discount_server->getBestDiscount($cartlist[$i]["shop_id"]);
                    $list['shop_info'][$v['shop_id']]['discount_info'] = !empty($discount_info) ? $discount_info : (object)array();
                    $promotion_discount = $discount_server->getPromotionInfo($cartlist[$i]['goods_id'], $cartlist[$i]['shop_id'], $cartlist[$i]['website_id']);
                    if ($promotion_discount) {
                        if ($promotion_discount['integer_type'] == 1) {
                            $cartlist[$i]['price'] = round($cartlist[$i]['oprice'] * $promotion_discount['discount_num'] / 10);
                        } else {
                            $cartlist[$i]['price'] = round($cartlist[$i]['oprice'] * $promotion_discount['discount_num'] / 10, 2);
                        }
                        if ($promotion_discount['discount_type'] == 2) {
                            $cartlist[$i]['price'] = $promotion_discount['discount_num'];
                        }
                    }
                }
                if ($is_seckill) {
                    $list['shop_info'][$v['shop_id']]['mansong_info'] = (object)array();
                    $list['shop_info'][$v['shop_id']]['discount_info'] = (object)array();
                }

            }
            $goods_service = new GoodsService();
            $payment_info = $goods_service->paymentData($cartlist);
            $list = $this->object2array($list);
        }
        //重新遍历，如果活动不一致则删除活动
        if (!empty($list)) {
            foreach ($list['shop_info'] as $k => $v) {
                foreach ($v['goods_list'] as $ka => $kb) {
                    //满减送
                    $list['shop_info'][$k]['mansong_info'] = $payment_info[$k]['full_cut'];
                    if (empty($list['shop_info'][$k]['mansong_info'])) {
                        $list['shop_info'][$k]['mansong_info'] = (object)array();
                    }
                    if (!empty($v['discount_info'])) {
                        if ($v['discount_info']['range'] == 2) {
                            $is_active_goods = $discount_server->checkIsDiscountProduct($kb['goods_id'], $kb['shop_id'], $v['discount_info']['discount_id']);
                            if (empty($is_active_goods)) {
                                $list['shop_info'][$k]['discount_info'] = (object)array();
                            }
                        }
                    } else {
                        $list['shop_info'][$k]['discount_info'] = (object)array();
                    }
//                    // todo... by sgw 商品max_buy返回
//                    if ($this->uid) {
//                        // 用户可购该商品最大数量
//                        $max_buy = $goods->getGoodsMaxBuyNums($kb['goods_id'], $kb['sku_id']);
//                        if ($max_buy < 0 ) {
//                            $list['shop_info'][$k]['goods_list'][$ka]['max_buy'] = -1;
//                        } else {
//                           $list['shop_info'][$k]['goods_list'][$ka]['max_buy'] = $max_buy;
//                        }
//                    }
                }
                //商城配置的配送方式
                $config = new Config();
                $has_express = $config->getConfig(0,'HAS_EXPRESS',$this->website_id, 1);
                if($has_express != 0) {
                    //开启了快递配送
                    $has_express = '';
                }else{
                    $has_express = 1;
                }

                if($this->is_store) {
                $store_server = new Store();
                $storeSet = $store_server->getStoreSet($k)['is_use'];
                if($storeSet) {
                    //开启了门店自提
                    $has_store = 1;
                }else{
                    $has_store = 0;
                }
                }else{
                    $has_store = 0;
                }

                $list['shop_info'][$k]['has_express'] = $has_express;
                $list['shop_info'][$k]['has_store'] = $has_store;
            }
        }
        if (empty($list)) {
            $lista['data'] = (object)array();
            $lista['message'] = "购物车暂无数据";
            $lista['code'] = 0;
            return json($lista);
        }
//        $num = 0;
        $new_list = array();
//        print_r($list['shop_info']);die;
//        p($list['shop_info'],' ddd');die;
//        foreach ($list['shop_info'] as $key => $v) {
//            $new_list['shop_info'][$num] = $v;
//            $num++;
//        }
        $new_list['shop_info'] = array_values($list['shop_info']);
    
        $lista['data'] = $new_list;
        $lista['data']['page_index'] = $page_index;
        $lista['data']['page_count'] = $page_count;
        $lista['data']['page_size'] = $page_size;
        if (!empty($msg)) {
            $lista['message'] = $msg;
            $lista['code'] = 3;
        } else {
            $lista['message'] = "获取成功";
            $lista['code'] = 0;
        }
        return json($lista);
    }

    /**
     * 购物车修改数量
     */
    public function cartAdjustNum()
    {
        $goods_service = new GoodsService();
        $cart_id = request()->post('cartid', '');
        $num = request()->post('num', '');
        if (empty($cart_id)) {
            $data['code'] = -1;
            $data['data'] = '';
            $data['message'] = "请选择购物车ID";
        }
        //判断当前商品是否是秒杀商品，若是，则不能超过最大限制购买量
        $cart_list = $goods_service->getCartList(['cart_id' => $cart_id]);
        $sku_id = $cart_list[0]['sku_id'];
        $seckill_id = (int)$cart_list[0]['seckill_id'];
        if (!$seckill_id) {
            if ($this->is_seckill) {
                $sec_service = new SeckillServer();
                $condition_seckill['nsg.sku_id'] = $sku_id;
                $seckill_info = $sec_service->isSkuStartSeckill($condition_seckill);
                if ($seckill_info) {
                    $seckill_id = $seckill_info['seckill_id'];
                }
            }
        }
        if ($seckill_id !== 0 && $this->is_seckill) {
            $sec_service = new SeckillServer();
            //查询该商品的虚拟购买量，条件为 未过期
            $condition_seckill['s.website_id'] = $this->website_id;
            $condition_seckill['s.shop_id'] = $this->instance_id;
            $condition_seckill['s.seckill_id'] = $seckill_id;
            $condition_seckill['nsg.sku_id'] = $sku_id;
            $is_seckill = $sec_service->isSeckillGoods($condition_seckill);
            if ($is_seckill) {
                $sku_list = $sec_service->getSeckillSkuInfo(['seckill_id' => $seckill_id, 'sku_id' => $sku_id]);
                $seck_limit_buy = $sku_list->seckill_limit_buy;
                if ($num > $seck_limit_buy) {
                    return json(['code' => -1, 'message' => '秒杀活动商品最大购买量不能超过' . $seck_limit_buy . '件']);
                }
            }
        }
        $goods = new GoodsService();
        $retval = $goods->cartAdjustNum($cart_id, $num);

        if ($retval) {
            $data['code'] = 0;
            $data['data'] = '';
            $data['message'] = "修改成功";
        } else {
            $data['code'] = 0;
            $data['data'] = '';
            $data['message'] = "修改失败";
        }

        return json($data);
    }

    /**
     * 店铺可领取券列表
     * */

    public function get_shopcounp_list()
    {

        //print_r($_REQUEST);exit;
        $website = $this->website_id;
        $shopid = request()->post('shop_id');
        if (!empty($shopid)) {
            $sql = "select * from `vsl_coupon_type` where `shop_range_type` = 2 and `shop_id` = $shopid and `is_fetch` = 1 and `website_id` = $website";
        } else {
            $sql = "select * from `vsl_coupon_type` where `shop_range_type` = 1 and `shop_id` = 0 and `is_fetch` = 1 and `website_id` = $website";
        }
        $list = Db::query($sql);
        if (empty($list)) {
            $result['code'] = '-1';
            $result['data'] = "[]";
            $result['message'] = '该店铺无可用优惠券';
            return json($result);
        }
        //统计已经领取的优惠券
        foreach ($list as $k => $v) {
            //已领取惠券数量
            $use_num = Db::query("select count(*) as `num` from `vsl_coupon` where coupon_type_id = " . $v['coupon_type_id']);
            $list[$k]['use_num'] = $use_num[0]['num'];
            if ($list[$k]['use_num'] == 0) {
                $list[$k]['use_percent'] = "100%";
            } else {
                $list[$k]['use_percent'] = number_format(($list[$k]['use_num'] / $v['count']) * 100, 2) . "%";
            }
        }
        $result['code'] = '0';
        $result['data'] = $list;
        $result['message'] = '获取成功';
        return json($result);


    }

    /**
     * 领取优惠券
     * */

    public function recieve_coup()
    {

        $coupon = new CouponServer();
        $coupon_type_id = request()->post('coupon_type_id',0);

        $result = '';
        if ($coupon->isCouponTypeReceivable($coupon_type_id, $this->uid)) {
            $result = $coupon->UserAchieveCoupon($this->uid, $coupon_type_id, '3');
        }
        if (empty($result)) {
            $data['message'] = "领取失败";
            $data['data'] = "";
            $data['code'] = '-1';
            return json($data);

        } else {
            $data['message'] = "领取成功";
            $data['data'] = "";
            $data['code'] = 0;
            return json($data);
        }

    }

    /**
     * 删除购物车商品
     * */

    public function delete_car_goods()
    {

        $cart_id = request()->post('cart_id');
        $cart_id_array = explode(',', $cart_id);
        $goods = new GoodsService();
        $result = $goods->cartDelete($cart_id_array);
        if ($result) {
            $data['message'] = "操作成功";
            $data['data'] = "";
            $data['code'] = 0;
            return json($data);
        } else {
            $data['message'] = "系统繁忙";
            $data['data'] = "";
            $data['code'] = '-1';
            return json($data);
        }
    }
    /**
     * 待付款订单需要的数据
     */
    public function orderInfo()
    {
        $order_tag = request()->post('order_tag');
        $cart_id_list = request()->post('cart_id_list/a');
        $sku_list = request()->post('sku_list/a');
        $address_id = request()->post('address_id', 0);
        $record_id = request()->post('record_id');
        $group_id = request()->post('group_id');
        $luckyspell_id = request()->post('luckyspell_id',0); //幸运拼id
        $presell_id = request()->post('presell_id', '');
        $shipping_type = request()->post('shipping_type', 1);
        $is_deduction = request()->post('is_deduction', 0);
        $lng = request()->post("lng", '');
        $lat = request()->post("lat", '');
        $cart_from = request()->post("cart_from", '1');//是平台购物车还是门店购物车,1平台购物车,2门店购物车
        $invoice_list = request()->post('invoice_list/a');//税费
        $is_membercard_deduction = request()->post('is_membercard_deduction', 0);//会员卡抵扣
        $goodsType = request()->post('goodsType', '');//6预约类型
        $place = [
            'lng' => $lng,
            'lat' => $lat
        ];
        $goodsSer = new GoodsService();
        $return_data = [];
        //平台购物车
        if ($order_tag == 'cart' && $cart_from == 1) {
            $cart_model = new VslCartModel();
            $cart_info = $cart_model::all(['cart_id' => ['IN', $cart_id_list]]);
            $sku_lists = $sku_list;
            $sku_list = [];
            foreach ($cart_info as $v) {
                $temp_sku = [];
                //查询出当前商品是否在活动中
                $goods_id = $v['goods_id'];
                
                $goods_info = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,price,goods_name,promotion_type');
                if ($goods_info['promotion_type'] == 3) {
                    return json(['code' => -1, 'message' => $goods_info['goods_name'] . ' 为参加预售活动商品，预售活动商品只能单独结算。']);
                }
                $temp_sku['sku_id'] = $v['sku_id'];
                if ($v['num'] <= 0) {
                    return json(['code' => -1, 'message' =>  $v['sku_name'].'规格库存不足']);
                }
                $temp_sku['num'] = $v['num'];
                $temp_sku['seckill_id'] = $v['seckill_id'];
                //主播id
                $temp_sku['anchor_id'] = $v['anchor_id'];
                $temp_sku['price'] = $v['price'];//秒杀商品
                $temp_sku['shop_id'] = $v['shop_id'];
                $temp_sku['cart_id'] = $v['cart_id'];
                $temp_sku['goods_id'] = $v['goods_id'];
                $temp_sku['goods_name'] = $v['goods_name'];
                if ($sku_lists) {
                    $temp_sku['coupon_id'] = $coupon_id = 0;
                    $receive_goods_code = '';
                    foreach ($sku_lists as $k2 => $v2) {
                        if ($v2['coupon_id'] > 0 && $temp_sku['sku_id'] == $v2['sku_id']) {
                            if ($coupon_id == 0) $coupon_id = $v2['coupon_id'];
                        }
                        if ($v2['store_id'] > 0 && $temp_sku['sku_id'] == $v2['sku_id']) {
                            $temp_sku['store_id'] = $v2['store_id'];
                        }
                        if ($v2['store_name'] && $temp_sku['sku_id'] == $v2['sku_id']) {
                            $temp_sku['store_name'] = $v2['store_name'];
                        }
                        if ($v2['receive_goods_code'] && $temp_sku['sku_id'] == $v2['sku_id']) {
                            $receive_goods_code = $v2['receive_goods_code'];
                        }
                        //如果供应商关闭，则不能提交该是商品
                        if(getAddons('supplier',$this->website_id)){
                            $supplierSer = new SupplierSer();
                            $checkRes = $supplierSer->isShowShopOfSupplierGoodsBySkuId($v2['sku_id']);
                            if (isset($checkRes['code']) && $checkRes['code'] <0){
                                return $checkRes;
                            }
                        }
                    }
                    $temp_sku['coupon_id'] = $coupon_id;
                    if ($receive_goods_code){
                        $temp_sku['receive_goods_code'] = $receive_goods_code;
                    }
                }
                $sku_list[] = $temp_sku;
            }
        } elseif ($order_tag == 'cart' && $cart_from == 2) {
            //门店购物车
            $cart_model = new VslStoreCartModel();
            $cart_info = $cart_model::all(['cart_id' => ['IN', $cart_id_list]]);
            $sku_lists = $sku_list;
            $sku_list = [];
            $store_str = '';
            foreach ($cart_info as $v) {
                $temp_sku = [];
                //查询出当前商品是否在活动中
                $goods_id = $v['goods_id'];
                $goods_info = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,price,goods_name,promotion_type');
                if ($goods_info['promotion_type'] == 3) {
                    return json(['code' => -1, 'message' => $goods_info['goods_name'] . ' 为参加预售活动商品，预售活动商品只能单独结算。']);
                }
                $temp_sku['sku_id'] = $v['sku_id'];
                if ($v['num'] <= 0) {
                    return json(['code' => -1, 'message' => $v['sku_name'].'规格库存不足']);
                }
                $temp_sku['num'] = $v['num'];
                $temp_sku['seckill_id'] = $v['seckill_id'];
                $temp_sku['price'] = $v['price'];//秒杀商品
                $temp_sku['shop_id'] = $v['shop_id'];
                $temp_sku['cart_id'] = $v['cart_id'];
                $temp_sku['goods_id'] = $v['goods_id'];
                if ($sku_lists) {
                    $temp_sku['coupon_id'] = $coupon_id = 0;
                    $receive_goods_code = '';
                    foreach ($sku_lists as $k2 => $v2) {
                        if ($v2['coupon_id'] > 0 && $temp_sku['sku_id'] == $v2['sku_id']) {
                            if ($coupon_id == 0) $coupon_id = $v2['coupon_id'];
                        }
                        if ($v2['store_id'] > 0 && $temp_sku['sku_id'] == $v2['sku_id']) {
                            $temp_sku['store_id'] = $v2['store_id'];
                        }
                        if ($v2['store_name'] && $temp_sku['sku_id'] == $v2['sku_id']) {
                            $temp_sku['store_name'] = $v2['store_name'];
                        }
                        if ($v2['receive_goods_code'] && $temp_sku['sku_id'] == $v2['sku_id']) {
                            $receive_goods_code = $v2['receive_goods_code'];
                        }
                    }
                    $temp_sku['coupon_id'] = $coupon_id;
                    if ($receive_goods_code){
                        $temp_sku['receive_goods_code'] = $receive_goods_code;
                    }                }
                $sku_list[] = $temp_sku;
            }
            if (count($sku_list) > 1) {
                foreach ($sku_list as $k => $v) {
                    $have_store = $goodsSer->getGoodsDetailById($v['goods_id'], 'goods_id,price,goods_name,store_list')['store_list'];
                    $store_str .= $have_store . ',';
                }
                //处理订单下不同商品对应着不同核销门店的情况
                $store_str = trim($store_str, ',');
                $store_str = explode(',', $store_str);
                if (count($store_str) != count(array_unique($store_str))) {
                    //取出共同的核销门店
                    $same_value = array_count_values($store_str);
                    foreach ($same_value as $k => $v) {
                        if ($v > 1) {
                            $same_value_arr[] = $k;
                        }
                    }
                }
            }
        }
        
        if (empty($sku_list)) {
            return json(['code' => -1, 'message' => '不存在商品信息']);
        }
        Session::set('order_tag', $order_tag);
        $goods_service = new GoodsService();
        $msg = '';
        $has_express = '';
        $store = 1;
        $is_many_shop = 0;
        foreach($sku_list as $k => $v){
            $bargain_id = $v['bargain_id'];
            //下面处理的问题是 砍价订单生成回退没有参加砍价再下单会报错
            if (!empty($bargain_id) && getAddons('bargain', $this->website_id, $this->instance_id)) {//砍价活动
                $bargain_server = new Bargain();
                $condition_bargain['bargain_id'] = $bargain_id;
                $condition_bargain['website_id'] = $this->website_id;
                $is_bargain = $bargain_server->isBargain($condition_bargain, $this->uid);
                if (!$is_bargain || empty(objToArr($is_bargain['my_bargain']))) {
                    $sku_list[$k]['bargain_id'] = 0;
                }
            }
        }
        //判断后台是否开启了快递配送
        $config = new Config();
        $config_has_express = $config->getConfig(0,'HAS_EXPRESS',$this->website_id, 1);
        if (getAddons('store', $this->website_id, $this->instance_id)) {
            $platform_stock = 1;
            //平台购物车过来的需要判断订单下的商品的库存，如果没有库存直接走门店
            if ($order_tag == 'cart' && $cart_from == 1) {
                $all_shop = [];
                foreach ($sku_list as $key => $val) {
                    $all_shop[] = $val['shop_id'];
                }
                $all_shop = array_unique($all_shop);
                if (count($all_shop) == 1) {
                    //单店铺
                    $sku_model = new VslGoodsSkuModel();
                    $store_str = '';
                    foreach ($sku_list as $k => $v) {
                        //判断这个商品平台有没有库存以及核销门店
                        $stock = $sku_model->Query(['sku_id' => $v['sku_id']], 'stock')[0];
                        $have_store = $goodsSer->getGoodsDetailById($v['goods_id'], 'goods_id,price,goods_name,store_list')['store_list'];
                        
                        if($config_has_express == 0 && empty($have_store)) {
                            //如果后台配置关闭了快递配送，并且某个商品没有勾选核销门店，则不能下单
                            return ['code' => -2, 'message' => $v['goods_name'] . '缺货，请取消勾选再结算或未开启配送方式，请联系客服' . PHP_EOL];
                        }elseif ($config_has_express && empty($have_store)) {
                            $store = 0;
                        }
                        
                        if (empty($stock) && empty($have_store)) {
                            //没有库存，没有核销门店，则不能下单
                            return ['code' => -2, 'message' => $v['goods_name'] . '缺货，请取消勾选再结算' . PHP_EOL];
                        } elseif (empty($stock) && $have_store) {
                            //平台没有库存，但有核销门店，则走门店
                            $platform_stock = 0;
                            $has_express = 1;
                        } elseif ($stock && $have_store) {
                            $store_str .= $have_store . ',';
                        }
                    }
                    if ($store_str && count($sku_list) > 1) {
                        //处理订单下不同商品对应不同核销门店的情况，此时只能快递配送
                        $store_str = trim($store_str, ',');
                        $store_str = explode(',', $store_str);
                        if (count($store_str) == count(array_unique($store_str))) {
                            $store = 0;
                        } else {
                            //取出共同的核销门店
                            $same_value = array_count_values($store_str);
                            foreach ($same_value as $k => $v) {
                                if ($v > 1) {
                                    $same_value_arr[] = $k;
                                }
                            }
                        }
                    }
                } else {
                    //多店铺
                    $is_many_shop = 1;
                }
            }

            //判断$sku_list中有没有store_id,如果有就是门店自提,用门店的价格,没有就是快递配送，用平台的价格
            foreach ($sku_list as $v) {
                $v['address_id'] = $address_id;
                if ($v['store_id']) {
                    $store_id_list[] = $v['store_id'];
                }
            }
            
            if (empty($store_id_list) && $platform_stock) {
                //走平台
//                $payment_info = $goods_service->paymentData($sku_list, $msg, $record_id, $group_id, $presell_id);
                $payment_info = $goods_service->paymentData($sku_list, $record_id, $group_id, $presell_id,0,'','',$luckyspell_id);
            } else {
                //走门店
                $storeServer = new storeServer();
//                $payment_info = $storeServer->paymentData($sku_list, $msg, $record_id, $group_id, $presell_id);
                $payment_info = $storeServer->paymentData($sku_list,$record_id, $group_id, $presell_id,0,'','',$luckyspell_id);
            }
        } else {
            //走平台
            
//            $payment_info = $goods_service->paymentData($sku_list, $msg, $record_id, $group_id, $presell_id);
            $payment_info = $goods_service->paymentData($sku_list, $record_id, $group_id, $presell_id,0,'','',$luckyspell_id);
        }
//        if ($payment_info['code'] == -2 || $payment_info['code'] == -3 || $payment_info['code'] == 2) {
//            return json($payment_info);
//        }
        
        $return_data['record_id'] = $record_id;
        $return_data['group_id'] = $group_id;
        $return_data['luckyspell_id'] = $luckyspell_id;
        // 收获地址
        $address = $goodsSer->getMemberExpressAddress($address_id);
        if (!empty($address)) {
            $return_data['address']['address_id'] = $address['id'];
            $return_data['address']['consigner'] = $address['consigner'];
            $return_data['address']['mobile'] = $address['mobile'];
            $return_data['address']['province_name'] = $address['area_province']['province_name'];
            $return_data['address']['city_name'] = $address['area_city']['city_name'];
            $return_data['address']['district_name'] = $address['area_district']['district_name'];
            $return_data['address']['address_detail'] = $address['address'];
            $return_data['address']['zip_code'] = $address['zip_code'];
            $return_data['address']['alias'] = $address['alias'];
            $return_data['address']['chinese_country_name'] = isset($address['chinese_country_name']) ? $address['chinese_country_name'] : '';
            $return_data['address']['english_country_name'] = isset($address['english_country_name']) ? $address['english_country_name'] : '';
            $return_data['address']['type'] = $address['type'];
        } else {
            $return_data['address'] = (object)[];
        }
        
        //end 收获地址
        $return_data['total_shipping'] = 0;
        $order_business = new OrderBusiness();
        $deduction_data = [];
        $return_point_data = [];
        $goodsExpress = new GoodsExpress();
        $storeServer = $this->is_store ? new Store() : '';
        $has_store = 0;//订单商品所属店铺中是否有门店
        $total_has_store = 0;
        $total_tax = 0;
        $is_free_shipping = 0;
        $total_memebrcard_deduction = 0;
        //判断会员是否有全场包邮的权益
        if(getAddons('membercard',$this->website_id)) {
            $membercard = new MembercardSer();
            $membercard_data = $membercard->checkMembercardStatus($this->uid);
            if($membercard_data['status'] && $membercard_data['membercard_info']['is_free_shipping']) {
                $is_free_shipping = 1;
            }
        }
        $receive_goods_code_exist = false;
        
        foreach ($payment_info as $shop_id => $shop_info) {
            $storeSet = 0;
            if ($this->is_store) {
                $storeSet = $storeServer->getStoreSet($shop_id)['is_use'];
                
            }
            $payment_info[$shop_id]['has_store'] = $storeSet ?: 0;
            
            if ($payment_info[$shop_id]['has_store']) {
                $has_store = 1;
            }
            $temp_goods = [];
            $allgoodsprice = 0;
            $all_actual_price = 0;
            foreach ($shop_info['goods_list'] as $sku_id => $sku_info) {
                $allgoodsprice += $sku_info['price'] * $sku_info['num'];
                $all_actual_price += $sku_info['discount_price'] * $sku_info['num'];
                if ($sku_info['receive_goods_code_used']){
                    $receive_goods_code_exist = true;
                }
                # max_buy
                $goodsInfo = $goodsSer->getGoodsDetailById($sku_info['goods_id']);
                $payment_info[$shop_id]['goods_list'][$sku_id]['max_buy']   = $goods_service->getUserMaxBuyGoodsSkuCount(0,$sku_info['goods_id'], $sku_info['sku_id']);
                $payment_info[$shop_id]['goods_list'][$sku_id]['least_buy'] = $goodsInfo['least_buy'];
                if ($goodsInfo['promotion_type'] == 4) {
                    $bargain_server = new Bargain();
                    $bargainInfo = $bargain_server->getBargainInfo(['website_id' => $this->website_id, 'goods_id' => $sku_info['goods_id']], 'bargain_id');
                    $payment_info[$shop_id]['goods_list'][$sku_id]['max_buy'] = $goods_service->getUserMaxBuyGoodsSkuCount(4,$bargainInfo['bargain_id'], $sku_info['sku_id']);
                    unset($bargainInfo);
                }elseif ($sku_info['seckill_id']) {/*秒杀*/
                    $payment_info[$shop_id]['goods_list'][$sku_id]['max_buy']   = $goods_service->getUserMaxBuyGoodsSkuCount(1,$sku_info['seckill_id'], $sku_info['sku_id']);
                    $payment_info[$shop_id]['goods_list'][$sku_id]['least_buy'] = $goods_service->getUserLeastBuyGoods(1,$sku_info['seckill_id']);
                }elseif ($group_id) {/*拼团*/
                    $payment_info[$shop_id]['goods_list'][$sku_id]['max_buy']   = $goods_service->getUserMaxBuyGoodsSkuCount(2,$group_id, $sku_info['sku_id']);
                    $payment_info[$shop_id]['goods_list'][$sku_id]['least_buy'] = $goods_service->getUserLeastBuyGoods(2,$group_id);
                }elseif ($luckyspell_id) {/*幸运拼团*/
                    $payment_info[$shop_id]['goods_list'][$sku_id]['max_buy']   = $goods_service->getUserMaxBuyGoodsSkuCount(6,$luckyspell_id, $sku_info['sku_id']);
                    $payment_info[$shop_id]['goods_list'][$sku_id]['least_buy'] = $goods_service->getUserLeastBuyGoods(6,$luckyspell_id);
                }elseif ($presell_id) {/*预售*/
                    $payment_info[$shop_id]['goods_list'][$sku_id]['max_buy']   = $goods_service->getUserMaxBuyGoodsSkuCount(3,$presell_id, $sku_info['sku_id']);
                    $payment_info[$shop_id]['goods_list'][$sku_id]['least_buy'] = $goods_service->getUserLeastBuyGoods(3,$presell_id);
                }
                if($shop_info['discount_promotion']<0){
                    $return_data['goods_amount'] += $sku_info['discount_price'] * $sku_info['num'];
                }else{
                    $return_data['goods_amount'] += $sku_info['price'] * $sku_info['num'];
                }
                
                if (!empty($shop_info['full_cut']) &&
                    !empty((array)$shop_info['full_cut']) &&
                    $shop_info['full_cut']['free_shipping'] == 1 &&
                    (in_array($sku_info['goods_id'], $shop_info['full_cut']['goods_limit']) || $shop_info['full_cut']['range_type'] == 1)) {
                    // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                    $payment_info[$shop_id]['goods_list'][$sku_id]['shipping_fee'] = 0;
                    continue;
                }
                if (empty($temp_goods[$sku_info['goods_id']])) {
                    $temp_goods[$sku_info['goods_id']]['count'] = $sku_info['num'];
                    $temp_goods[$sku_info['goods_id']]['goods_id'] = $sku_info['goods_id'];
                } else {
                    $temp_goods[$sku_info['goods_id']]['count'] += $sku_info['num'];
                }
                if (!empty($address['district'])) {
                    $tempgoods = [];
                    $tempgoods[$sku_info['goods_id']]['count'] = $temp_goods[$sku_info['goods_id']]['count'];
                    $tempgoods[$sku_info['goods_id']]['goods_id'] = $temp_goods[$sku_info['goods_id']]['goods_id'];
                    $payment_info[$shop_id]['goods_list'][$sku_id]['shipping_fee'] = $goodsExpress->getGoodsExpressTemplate($tempgoods, $address['district'])['totalFee'];
                    if($is_free_shipping) {
                        $payment_info[$shop_id]['goods_list'][$sku_id]['shipping_fee'] = 0;
                    }
                }
            }
            //修正 折扣后金额大于原商品总价，价格变更为商品总价
            if($shop_info['discount_promotion']<0){
                $shop_info['discount_promotion'] = $shop_info['discount_promotion'] + ($all_actual_price - $allgoodsprice);
            }
            $return_data['promotion_amount'] += $shop_info['member_promotion'] + $shop_info['discount_promotion'] + $shop_info['coupon_promotion'];
            if (isset($shop_info['full_cut']) && is_array($shop_info['full_cut'])) {
                $return_data['promotion_amount'] += ($shop_info['full_cut']['real_discount'] < $shop_info['total_amount']) ? $shop_info['full_cut']['real_discount'] : $shop_info['total_amount'];
            }
            
            if(getAddons('store', $this->website_id, $this->instance_id)) {
                if($has_store) {
                    //处理此笔订单下的商品，如果门店没有此商品的库存，则不能选择此门店
                    $storeModel = new VslStoreModel();
                    $storeGoodsModel = new VslStoreGoodsModel();
                    $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
                    $address_service = new Address();
                    $storeServer = new storeServer();
                    $have_stock_store_list = '';
                    $no_stock_store_list = '';
                    
                    //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
                    $stock_type = $storeServer->getStoreSet($shop_id)['stock_type'] ? $storeServer->getStoreSet($shop_id)['stock_type'] : 1;
                    foreach ($shop_info['goods_list'] as $key => $val) {
                        if ($same_value_arr) {
                            $store_list = implode(',', $same_value_arr);
                        } else {
                            $store_list = $goodsSer->getGoodsDetailById($val['goods_id'], 'goods_id,price,goods_name,store_list')['store_list'];
                        }
                        
                        if (empty($store_list)) {
                            $payment_info[$shop_id]['has_store'] = 0;
                            $have_stock_store_list = '';
                            $no_stock_store_list = '';
                            if(empty($config_has_express)) {
                                return ['code' => -2, 'message' => '未开启配送方式，请联系客服' . PHP_EOL];
                            }else{
                                break;
                            }
                        } else {
                            $store_list = explode(',', $store_list);
                            if($stock_type == 1) {
                                //使用门店独立库存
                                
                                foreach ($store_list as $k => $v) {
                                    //没有库存或者此门店没有上架此商品都不能选择此门店
                                    $stock_condition = [
                                        'goods_id' => $val['goods_id'],
                                        'sku_id' => $val['sku_id'],
                                        'store_id' => $v,
                                        'website_id' => $this->website_id
                                    ];
                                    
                                    $state_condition = [
                                        'goods_id' => $val['goods_id'],
                                        'store_id' => $v,
                                        'website_id' => $this->website_id
                                    ];
                                    $stock = $storeGoodsSkuModel->getInfo($stock_condition, 'stock');
                                    $state = $storeGoodsModel->getInfo($state_condition, 'state');
                                    if ($stock['stock'] <= 0 || $state['state'] == 0) {
                                        $no_stock_store_list .= $v . ',';
                                        unset($v);
                                    }
                                    if ($v) {
                                        $have_stock_store_list .= $v . ',';
                                    }
                                }
                                
                                
                            }elseif ($stock_type == 2) {
                                //使用店铺统一库存
                                $no_stock_store_list = '';
                                $have_stock_store_list .= implode(',',$store_list) . ',';
                            }
                        }
                    }
                    
                    if (empty($no_stock_store_list) && empty($have_stock_store_list)) {
                        $payment_info[$shop_id]['store_list'] = [];
                    } else {
                        $no_stock_store_list = trim($no_stock_store_list, ',');
                        $have_stock_store_list = trim($have_stock_store_list, ',');
                        $have_stock_store_list2 = trim($have_stock_store_list, ',');
                        if (empty($no_stock_store_list)) {
                            //所有门店可自提
                            $have_stock_store_list = explode(',', $have_stock_store_list);
                            $have_stock_store_list = array_unique($have_stock_store_list);
                            $new_store_list = $have_stock_store_list;
                        } elseif (empty($have_stock_store_list)) {
                            //没有门店可自提
                            $payment_info[$shop_id]['store_list'] = [];
                            $payment_info[$shop_id]['has_store'] = 0;
                        } elseif ($no_stock_store_list && $have_stock_store_list) {
                            $no_stock_store_list = explode(',', $no_stock_store_list);
                            $no_stock_store_list = array_unique($no_stock_store_list);
                            $have_stock_store_list = explode(',', $have_stock_store_list);
                            $have_stock_store_list = array_unique($have_stock_store_list);
                            $arr = array_merge($no_stock_store_list, $have_stock_store_list);
                            $arr = array_unique($arr);
                            foreach ($arr as $key => $val) {
                                foreach ($no_stock_store_list as $k => $v) {
                                    if ($val == $v) {
                                        unset($val);
                                    }
                                }
                                if ($val) {
                                    $res[] = $val;
                                }
                            }
                            $new_store_list = $res;
                        }
                        if ($is_many_shop) {
                            if ($have_stock_store_list2 && count($shop_info['goods_list']) > 1) {
                                //处理订单下不同商品对应不同核销门店的情况
                                $have_stock_store_list2 = explode(',', $have_stock_store_list2);
                                if (count($have_stock_store_list2) == count(array_unique($have_stock_store_list2))) {
                                    $have_share_store = [];
                                } else {
                                    //取出共同的核销门店
                                    $same_value2 = array_count_values($have_stock_store_list2);
                                    foreach ($same_value2 as $k => $v) {
                                        if ($v > 1) {
                                            $have_share_store[] = $k;
                                        }
                                    }
                                }
                                if ($have_share_store) {
                                    $new_store_list = $have_share_store;
                                    unset($have_share_store);
                                } else {
                                    $new_store_list = [];
                                }
                            } elseif ($have_stock_store_list2 && count($shop_info['goods_list']) == 1) {
                                $have_stock_store_list2 = explode(',', $have_stock_store_list2);
                                $new_store_list = $have_stock_store_list2;
                            }
                        }
                        
                        if ($new_store_list) {
                            foreach ($new_store_list as $k => $v) {
                                $store_info = $storeModel->getInfo(['store_id' => $v], '*');
                                if(!$store_info){
                                    continue;
                                }
                                $newList['distance'] = $storeServer->sphere_distance(['lat' => $store_info['lat'], 'lng' => $store_info['lng']], $place);
                                $newList['store_id'] = $store_info['store_id'];
                                $newList['shop_id'] = $store_info['shop_id'];
                                $newList['website_id'] = $store_info['website_id'];
                                $newList['store_name'] = $store_info['store_name'];
                                $newList['store_tel'] = $store_info['store_tel'];
                                $newList['address'] = $store_info['address'];
                                $newList['province_name'] = $address_service->getProvinceName($store_info['province_id']);
                                $newList['city_name'] = $address_service->getCityName($store_info['city_id']);
                                $newList['dictrict_name'] = $address_service->getDistrictName($store_info['district_id']);
                                $data[] = $newList;
                            }
                            $payment_info[$shop_id]['store_list'] = $data;
                            unset($data);
                            $payment_info[$shop_id]['has_store'] = 1;
                            unset($new_store_list);
                        } else {
                            $payment_info[$shop_id]['store_list'] = [];
                            $payment_info[$shop_id]['has_store'] = 0;
                        }
                    }
                    //前端需要的字段
                    if($is_many_shop) {
                        foreach ($sku_list as $k => $v){
                            if($v['shop_id'] == $shop_info['shop_id']){
                                $payment_info[$shop_id]['store_id'] = $v['store_id']?$v['store_id']:0;
                                $payment_info[$shop_id]['store_name'] = $v['store_name']?$v['store_name']:'';
                                break;
                            }
                        }
                    }else{
                        
                        $payment_info[$shop_id]['store_id'] = $sku_list[0]['store_id']?$sku_list[0]['store_id']:0;
                        $payment_info[$shop_id]['store_name'] = $sku_list[0]['store_name']?$sku_list[0]['store_name']:'';
                    }
                }else{
                    $payment_info[$shop_id]['has_store'] = 0;
                }
            }
            // 计算邮费
            $shipping_fee = 0;
            if ($temp_goods && !empty($address['district'])) {
                $shipping_fee = $goodsExpress->getGoodsExpressTemplate($temp_goods, $address['district'])['totalFee'];
                if($is_free_shipping) {
                    $shipping_fee = 0;
                }
            }
            //重新划分商品运费
            $ids = [];
            $fixed_ids = []; //固定运费
            if(count($payment_info[$shop_id]['goods_list']) > 1){
                foreach ($payment_info[$shop_id]['goods_list'] as $kv => $vv) {
                    if($vv['shipping_fee'] > 0 && $vv['shipping_fee_type'] == 2){
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
                        $payment_info[$shop_id]['goods_list'][$kv]['goods_weight'] = $goodsInfo['goods_weight'] * $vv['num'];
                        $payment_info[$shop_id]['goods_list'][$kv]['goods_volume'] = $goodsInfo['goods_volume'] * $vv['num'];
                        $payment_info[$shop_id]['goods_list'][$kv]['goods_count'] = $goodsInfo['goods_count'] * $vv['num'];
                        $payment_info[$shop_id]['goods_list'][$kv]['shipping_fee_id'] = $goodsInfo['shipping_fee_id'];
                        array_push($ids,$new_ship);
                    }else if($vv['shipping_fee'] > 0 && $vv['shipping_fee_type'] == 1){
                        $fixed_info = array(
                            'goods_id' => $vv['goods_id'],
                            'sku_id' => $vv['sku_id'],
                            'shipping_fee' => $vv['shipping_fee'],
                        );
                        array_push($fixed_ids, $fixed_info);
                        //获取固定运费，而且金额大于0的商品重新划分
                        
                    }
                }
                //处理多规格 固定运费问题
                if($fixed_ids){
                    $fixeds = array_group_by($fixed_ids,'goods_id');
                    foreach ($fixeds as $k_f => $v_f) {
                        if(count($v_f) >=2){
                            $new_fixeds_shipping_fee = round($v_f[0]['shipping_fee'] / count($v_f),2);
                            foreach ($v_f as $k_v_f => $v_v_f) {
                                foreach ($payment_info[$shop_id]['goods_list'] as $ka => $va) {
                                    
                                    if ($va['sku_id'] == $v_v_f['sku_id']) {
                                        $payment_info[$shop_id]['goods_list'][$ka]['shipping_fee'] = $new_fixeds_shipping_fee;
                                    }
                                }
                            }
                        }
                    }
                }
                if($ids){
                    foreach ($ids as $keys => $values) {
                        $resq[$values['shipping_fee_id']][] = $values;
                    }
                    
                    foreach ($resq as $keyp => $valuep) {
                        
                        if(count($valuep) > 1){
                            $checktempgoods = [];
                            $goods_weight = 0;
                            $goods_volume = 0;
                            $goods_count = 0;
                            foreach ($valuep as $keyt => $valuet) {
                                $checktempgoods[$keyt]['count'] = $valuet['num'];
                                $checktempgoods[$keyt]['goods_id'] = $valuet['goods_id'];
                                
                                $goods_weight += $valuet['goods_weight'];
                                $goods_volume += $valuet['goods_volume'];
                                $goods_count += $valuet['goods_count'];
                            }
                            $shipping_fee_valuet = $goodsExpress->getGoodsExpressTemplate($checktempgoods, $address['district'])['totalFee'];
                            if($is_free_shipping) {
                                $shipping_fee_valuet = 0;
                            }
                            //获取平均值
                            
                            foreach ($payment_info[$shop_id]['goods_list'] as $kb => $vb) {
                                
                                if($vb['shipping_fee_id'] == $keyp){
                                    if($goods_weight > 0){
                                        $payment_info[$shop_id]['goods_list'][$kb]['shipping_fee'] = round($shipping_fee_valuet / $goods_weight * $vb['goods_weight'],2);
                                    }
                                    if($goods_volume > 0){
                                        $payment_info[$shop_id]['goods_list'][$kb]['shipping_fee'] = round($shipping_fee_valuet / $goods_volume * $vb['goods_volume'],2);
                                    }
                                    if($goods_count > 0){
                                        $payment_info[$shop_id]['goods_list'][$kb]['shipping_fee'] = round($shipping_fee_valuet / $goods_count * $vb['goods_count'],2);
                                    }
                                    
                                }
                                
                            }
                            
                        }
                        
                    }
                }
                
            }
            
            $is_presell_info = objToArr($shop_info['presell_info']);
            if ($is_presell_info && $shipping_type != 2) {
                $payment_info[$shop_id]['presell_info']['shipping_fee'] = $shipping_fee;
            }
            //预售商品 线下自提 补充商品运费信息
            $payment_info[$shop_id]['temp_shipping_fee'] = $shipping_fee;//记录店铺运费，用于下面预售前端显示运费使用
            if ($is_presell_info && ($shipping_type == 2 && $payment_info[$shop_id]['has_store'] > 0)) {
                $payment_info[$shop_id]['presell_info']['shipping_fee'] = 0;
            }
            //预售商品 线下自提 没有开启自提门店
            // $payment_info[$shop_id]['has_store'] 店铺开启线下自提与否 待确认是否修改
            if ($is_presell_info || ($shipping_type == 2 && $payment_info[$shop_id]['has_store'] > 0)) {//等于2为自提
                $shipping_fee = 0;
            }
            $payment_info[$shop_id]['shipping_fee'] = $shipping_fee;//运费
            $payment_info[$shop_id]['total_amount'] += $shipping_fee;
            $return_data['total_shipping'] += $shipping_fee;
            if ($presell_id > 0 && $is_presell_info) {
                $payment_info[$shop_id]['goods_list'][0]['price'] = $shop_info['presell_info']['allmoney'];
                if($is_free_shipping) {
                    $payment_info[$shop_id]['presell_info']['shipping_fee'] = 0;
                }
                $final_real_money = ($payment_info[$shop_id]['presell_info']['allmoney'] - $payment_info[$shop_id]['presell_info']['firstmoney'])*$payment_info[$shop_id]['presell_info']['goods_num'] + $payment_info[$shop_id]['presell_info']['shipping_fee'];
                $payment_info[$shop_id]['presell_info']['final_real_money'] = $final_real_money;
                $payment_info[$shop_id]['shipping_fee'] = $payment_info[$shop_id]['temp_shipping_fee'];
            }
            # 领货码，使用discount_price作为sku商品单个价格
            if (getAddons('receivegoodscode',$this->website_id,$shop_id) && $receive_goods_code_exist ){
                $codeSer = new ReceiveGoodsCodeSer();
                if ($payment_info[$shop_id]['presell_info']['receive_goods_code_data'] && $presell_id){
                    # 领货码（多码处理）
                    $receiveGoodsPresellRes = $codeSer->receiveGoodsReturnPresellOrderGoodsSkuForMany($payment_info[$shop_id]['presell_info']);
                    $payment_info[$shop_id]['presell_info']['final_real_money'] = $receiveGoodsPresellRes['final_real_money'];
                    $payment_info[$shop_id]['presell_info']['firstmoney'] = $receiveGoodsPresellRes['firstmoney'];
                    $return_data['receive_goods_code_deduct'] += $receiveGoodsPresellRes['receive_order_goods_data']['money'];//优惠金额
                    //                    $payment_info[$shop_id]['total_amount'] = $payment_info[$shop_id]['presell_info']['firstmoney']*$payment_info[$shop_id]['presell_info']['goods_num'];
                    $payment_info[$shop_id]['total_amount'] = $receiveGoodsPresellRes['firstmoney']*$receiveGoodsPresellRes['goods_num'];
                    //                    $payment_info[$shop_id]['goods_list'][0]['real_money'] = $receiveGoodsPresellRes['final_real_money'] - $receiveGoodsPresellRes['shipping_fee']>0?round(($receiveGoodsPresellRes['final_real_money'] - $receiveGoodsPresellRes['shipping_fee'])/$receiveGoodsPresellRes['goods_num'],2):0;
                    //                    $payment_info[$shop_id]['goods_list'][0]['firstmoney'] = $receiveGoodsPresellRes['firstmoney'];
                    //预售需要real_money总商品
                    $presell_temp_final =  $receiveGoodsPresellRes['final_real_money'] - $receiveGoodsPresellRes['shipping_fee']>0?$receiveGoodsPresellRes['final_real_money'] - $receiveGoodsPresellRes['shipping_fee']:0;
                    $presell_temp_real_money = $receiveGoodsPresellRes['firstmoney']*$receiveGoodsPresellRes['goods_num'] + $presell_temp_final;
                    $payment_info[$shop_id]['goods_list'][0]['real_money'] = round($presell_temp_real_money/$receiveGoodsPresellRes['goods_num'],2);
                }else{
                    $receiveRes = $codeSer->receiveGoodsReturnOrderGoodsSkuForMany($payment_info[$shop_id]['goods_list']);
                    $payment_info[$shop_id]['goods_list'] = $receiveRes['sku_info'];
                    $receive_deduct = $receiveRes['shop_deduct'];//领货码抵扣金额
                    $payment_info[$shop_id]['total_amount'] -= $receive_deduct;
                //                    $return_data['promotion_amount'] += $receive_deduct;//优惠金额
                    $return_data['receive_goods_code_deduct'] += $receive_deduct;//优惠金额
                }
            }
            
            
            //抵扣积分
            $point_deductio = $order_business->pointDeductionOrder($payment_info[$shop_id]['goods_list'], $is_deduction, $shipping_type); //问题点
            
            //            $payment_info[$shop_id] = $order_business->pointDeductionOrderForPresell($payment_info[$shop_id],$point_deductio['total_deduction_money'],$is_deduction);
            $payment_info[$shop_id]['goods_list'] = $point_deductio['sku_info'];//TODO... by sgw
           
            
            if ($is_deduction && $point_deductio){
                if ($presell_id>0){
                    //                    $pre_deduct_point = $payment_info[$shop_id]['presell_info']['final_real_money'] - $point_deductio['total_deduction_money'];
                    //                    $payment_info[$shop_id]['presell_info']['final_real_money'] = $pre_deduct_point>0?$pre_deduct_point:0;
                    $payment_info[$shop_id] = $order_business->pointDeductionOrderForPresell($payment_info[$shop_id],$point_deductio['total_deduction_money'],$is_deduction);
                    //                    p($payment_info[$shop_id],' 返回结果');die;
                }else{
                    if ($point_deductio['sku_info'][0]['shop_id'] == $shop_info['shop_id']){
                        $payment_info[$shop_id]['total_amount'] -= $point_deductio['total_deduction_money'];
                    }
                }
            }
            
            $point_return = $order_business->pointReturnOrder($point_deductio['sku_info'], $shipping_type);
            
            $payment_info[$shop_id]['goods_list'] = $point_return['sku_info'];
            
            $addonsConfig = new AddonsConfigService();
            if ($addonsConfig->isAddonsIsUsed('invoice',$this->website_id,$shop_id)) {
                $invoiceServer = new InvoiceServer();
                $tax_result = $invoiceServer->calculateShopInvoiceTax($shop_info['shop_id']);
                
                $tax_result = empty($tax_result) ? objToArr([]) : $tax_result;
                $payment_info[$shop_id]['tax_fee'] = $tax_result;
                
                $invoiceSer = new InvoiceServer();
                if ($presell_id>0){
                    $invoiceRes = $invoiceSer->invoiceReturnPresellOrderGoodsSkuForMany($invoice_list,$payment_info[$shop_id]);
                    $payment_info[$shop_id] = $invoiceRes;
                    $total_tax += $invoiceRes['total_tax'];
                }else{
                    
                    $invoiceRes = $invoiceSer->invoiceReturnOrderGoodsSkuForMany($invoice_list,$payment_info[$shop_id]);
                    $payment_info[$shop_id] = $invoiceRes;
                    $total_tax += $invoiceRes['total_tax'];
                    
                }
            }
            
            $return_data['amount'] += $payment_info[$shop_id]['total_amount'];
            $total_has_store += $payment_info[$shop_id]['has_store'];
        }
        
        $has_express = $config_has_express == 0 ? 1:'';
        if (empty($store)) {
            $has_store1 = 0;
        } elseif ($total_has_store > 0) {
            $has_store1 = 1;
        } else {
            $has_store1 = 0;
        }
        $return_data['has_store'] = $has_store1;
        $return_data['has_express'] = $has_express;
        //积分抵扣
        $member_info = $this->user->getMemberAccount($this->uid);
        $return_data['deduction_point']['point'] = $member_info['point'];
        $return_data['deduction_point']['total_deduction_money'] = 0;
        $return_data['deduction_point']['total_deduction_point'] = 0;
        //积分赠送
        $return_data['total_give_point'] = 0;
        //获取积分汇率
        $info = $config->getShopConfig(0, $this->website_id);
        $convert_rate = (float)$info['convert_rate'];
        if(!$convert_rate){
            $convert_rate = 1;
        }
        $deduct_points_flag = true;
    
        foreach($payment_info as $shop_id => $shop_info){
            foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                if ($sku_goods['deduction_point'] >= $member_info['point']){
                    $return_data['deduction_point']['total_deduction_money'] += round(floor($member_info['point']) / $convert_rate, 2);
                    $return_data['deduction_point']['total_deduction_point'] += $member_info['point'];
                    $deduct_points_flag = false;
                }
                //积分抵扣
                if($deduct_points_flag && $sku_goods['deduction_money']>=0 ){
                
                    $return_data['deduction_point']['total_deduction_money'] += $sku_goods['deduction_money'];
                    $return_data['deduction_point']['total_deduction_point'] += $sku_goods['deduction_point'];
                    $member_info['point'] = $member_info['point'] - $sku_goods['deduction_point'];
                }
            
                //积分赠送
                $return_data['total_give_point'] += $sku_goods['return_point'];//积分抵扣累计
            }
        }
       
        # 会员卡抵扣计算
        if($this->is_membercard){
            $membercard = new MembercardSer();
            $payment_info = $membercard->dealMembercardReturnPoint($payment_info);
            
            //统计总积分
            $membercard_total_give_point = 0;
            foreach($payment_info as $shop_id => $shop_info){
                foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                    $membercard_total_give_point += $sku_goods['return_point'];//积分抵扣累计
                }
            }
            
            $return_data['total_give_point'] = $membercard_total_give_point;
            //会员卡抵扣处理
            $payment_info = $membercard->membercardReturnOrderInfo($payment_info,$is_membercard_deduction,$return_data); //问题点
            
            
        }
        
        $config = new Config();
        $point_deduction = $config->getShopConfig(0, $this->website_id);
        $return_data['is_point_deduction'] = $point_deduction['is_point_deduction'];
        $return_data['is_point'] = $point_deduction['is_point'];
        //查询是否
        if($goodsType == 6){
            //商品id换取表单id
            $sku_model = new VslGoodsSkuModel();
            foreach ($sku_list as $ks => $vs) {
                $goods_id = $sku_model->getInfo(['sku_id' => $vs['sku_id']], 'goods_id')['goods_id'];
                break;
            }
            $custom_id = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,price,goods_name,form_base_id')['form_base_id'];
            $custom = new CustomFormServer();
            $result = $custom->getCustomFormDetail($custom_id);
            $return_data['customform'] = $result['value'];
        }else{
            $return_data['customform'] = $this->user->getOrderCustomForm();
        }

        $return_data['shop'] = array_values($payment_info);
        //预约项目 展示医生和预约时间
        if($goodsType == 6){
            $store_id = $return_data['shop'][0]['store_id'];
            $where = [];
            $where['jobs_id'] = array('in', [6,7]);
            $begin = VslStoreAssistantModel::BEGIN;
            $end   = VslStoreAssistantModel::END;
            $today = date('Y-m-d');
            $tomorrow = date("Y-m-d", strtotime("+1 day"));
            $after_today = date("Y-m-d", strtotime("+2 day"));

            $where['store_id'] = $store_id;
            //获取医生列表
            $doctor_list = VslStoreAssistantModel::field('assistant_id as id,assistant_name as text,reservation_times')
                ->where($where)
                ->order('jobs_id', 'asc')
                ->select();
            $doctors = [];
            foreach ($doctor_list as $d){
                //时间格式话
                if(!empty($d['reservation_times'])){
                    $reservation_times = explode(',', $d['reservation_times']);
//                        var_dump($reservation_times);
                    $no_list = [];
                    for($i=$begin; $i<=$end; $i++){
                        if(!in_array($i, $reservation_times)){
                            $i_format = $i<10 ? '0'.$i : $i;
                            $j = $i+1;
                            $j_format = $j<10 ? '0'.$j : $j;
                            $no_list[] = ['begin'=> $i_format, 'end'=> $j_format];
                        }
                    }
                    $no_map = [];
                    foreach ($no_list as $io){
                        $no_map[] = [$today.' '.$io['begin'].':00:00', $today.' '.$io['end'].':00:00'];
                        $no_map[] = [$tomorrow.' '.$io['begin'].':00:00', $tomorrow.' '.$io['end'].':00:00'];
                        $no_map[] = [$after_today.' '.$io['begin'].':00:00', $after_today.' '.$io['end'].':00:00'];
                    }

                    //获取医生已被预约时间
                    $where = [];
                    $where['assistant_id'] = array('=', $d['id']);
                    $where['pay_status'] = array('=', 1);
                    $where['order_status'] = array('>=', 1);
                    $orders =  VslAppointOrderModel::field('order_id,appoint_time,appoint_no')->where($where)->select();
                    foreach ($orders as $o){
                        $day = date('Y-m-d', strtotime($o['appoint_time']));
                        $end = $o['appoint_no'] + 1;
                        $end_format = $end < 10 ? '0'.$end : $end;
                        $no_map[] = [$o['appoint_time'], $day.' '.$end_format.':00:00'];
                    }
                    $d['no_data'] = $no_map;

                    $doctors[] = $d;
                }
            }

            $return_data['shop'][0]['doctor_list'] = $doctors;
        }

        $return_data['total_tax'] = $total_tax ?: 0;
        
        //返回websorket地址
        //$return_data['websorket_url'] = config('websocket_url').'/websocket';
        return json(['code' => 1, 'message' => $msg, 'data' => $return_data]);
    }

    /**
     * 待付款订单需要的数据(微店升级的商品)
     */
    public function orderMicroShopInfo()
    {
        $order_tag = request()->post('order_tag');
        $cart_id_list = request()->post('cart_id_list/a');
        $sku_list = request()->post('sku_list/a');
        $address_id = request()->post('address_id', 0);
        $record_id = request()->post('record_id');
        $group_id = request()->post('group_id');
        $presell_id = request()->post('presell_id', '');
        $shipping_type = request()->post('shipping_type', 1);
        $is_deduction = request()->post('is_deduction', 0);
        $order_type = request()->post('order_type', 0);
        if ($order_type == 2 || $order_type == 3 || $order_type == 4) {
            $un_order = 1;
        } else {
            $un_order = 0;
        }
        $goods_service = new GoodsService();
        $return_data = [];
        if ($order_tag == 'cart') {
            $cart_model = new VslCartModel();
            $cart_info = $cart_model::all(['cart_id' => ['IN', $cart_id_list]]);
            $sku_lists = $sku_list;
            $sku_list = [];
            foreach ($cart_info as $v) {
                $temp_sku = [];
                //查询出当前商品是否在活动中
                $goods_id = $v['goods_id'];
                $goods_info = $goods_service->getGoodsDetailById($goods_id, 'goods_id,price,goods_name,promotion_type');
                if ($goods_info['promotion_type'] == 3 && !$un_order) {
                    return json(['code' => -1, 'message' => $goods_info['goods_name'] . ' 为参加预售活动商品，预售活动商品只能单独结算。']);
                }
                $temp_sku['sku_id'] = $v['sku_id'];
                $temp_sku['num'] = $v['num'];
                $temp_sku['seckill_id'] = $v['seckill_id'];
                $temp_sku['price'] = $v['price'];//秒杀商品
                $temp_sku['shop_id'] = $v['shop_id'];
                $temp_sku['cart_id'] = $v['cart_id'];
                if ($sku_lists) {
                    $temp_sku['coupon_id'] = $coupon_id = 0;
                    foreach ($sku_lists as $k2 => $v2) {
                        if ($v2['coupon_id'] > 0 && $temp_sku['sku_id'] == $v2['sku_id'] && !$un_order) {
                            if ($coupon_id == 0) $coupon_id = $v2['coupon_id'];
                        }
                    }
                    $temp_sku['coupon_id'] = $coupon_id;
                }
                $sku_list[] = $temp_sku;
            }
        }
        if (empty($sku_list)) {
            return json(['code' => -1, 'message' => '不存在商品信息']);
        }
        Session::set('order_tag', $order_tag);
        
        $msg = '';
        $payment_info = $goods_service->paymentData($sku_list, $msg, $record_id, $group_id, $presell_id, $un_order);
        if ($payment_info['code'] == -2) {
            return json($payment_info);
        }
        $return_data['record_id'] = $record_id;
        $return_data['group_id'] = $group_id;

        // 收获地址
        if (empty($address_id)) {
            $address_condition['uid'] = $this->uid;
            $address_condition['is_default'] = 1;
        } else {
            $address_condition['id'] = $address_id;
        }
        $address = $this->user->getMemberExpressAddress($address_condition, ['area_province', 'area_city', 'area_district']);
        if (!empty($address)) {
            $return_data['address']['address_id'] = $address['id'];
            $return_data['address']['consigner'] = $address['consigner'];
            $return_data['address']['mobile'] = $address['mobile'];
            $return_data['address']['province_name'] = $address['area_province']['province_name'];
            $return_data['address']['city_name'] = $address['area_city']['city_name'];
            $return_data['address']['district_name'] = $address['area_district']['district_name'];
            $return_data['address']['address_detail'] = $address['address'];
            $return_data['address']['zip_code'] = $address['zip_code'];
            $return_data['address']['alias'] = $address['alias'];
        } else {
            $return_data['address'] = (object)[];
        }
        //end 收获地址
        $return_data['total_shipping'] = 0;
        $order_business = new OrderBusiness();
        $deduction_data = [];
        $return_point_data = [];
        $goodsExpress = new GoodsExpress();
        foreach ($payment_info as $shop_id => $shop_info) {
            $return_data['promotion_amount'] += $shop_info['member_promotion'] + $shop_info['discount_promotion'] + $shop_info['coupon_promotion'];
            if (isset($shop_info['full_cut']) && is_array($shop_info['full_cut'])) {
                $return_data['promotion_amount'] += ($shop_info['full_cut']['discount'] < $shop_info['total_amount']) ? $shop_info['full_cut']['discount'] : $shop_info['total_amount'];
            }
            $temp_goods = [];
            foreach ($shop_info['goods_list'] as $sku_id => $sku_info) {
                $return_data['goods_amount'] += $sku_info['price'] * $sku_info['num'];
                if (!empty($shop_info['full_cut']) &&
                    (array)$shop_info['full_cut'] &&
                    $shop_info['full_cut']['free_shipping'] == 1 &&
                    (in_array($sku_info['goods_id'], $shop_info['full_cut']['goods_limit']) || $shop_info['full_cut']['range_type'] == 1)) {
                    // 有包邮的设定 && (商品在goods_limit里面 || 活动商品是全部商品)
                    $payment_info[$shop_id]['goods_list'][$sku_id]['shipping_fee'] = 0;
                    continue;
                }
                if (empty($temp_goods[$sku_info['goods_id']])) {
                    $temp_goods[$sku_info['goods_id']]['count'] = $sku_info['num'];
                    $temp_goods[$sku_info['goods_id']]['goods_id'] = $sku_info['goods_id'];
                } else {
                    $temp_goods[$sku_info['goods_id']]['count'] += $sku_info['num'];
                }
                if (!empty($address['district'])) {
                    $tempgoods = [];
                    $tempgoods[$sku_info['goods_id']]['count'] = $temp_goods[$sku_info['goods_id']]['count'];
                    $tempgoods[$sku_info['goods_id']]['goods_id'] = $temp_goods[$sku_info['goods_id']]['goods_id'];
                    $payment_info[$shop_id]['goods_list'][$sku_id]['shipping_fee'] = $goodsExpress->getGoodsExpressTemplate($tempgoods, $address['district'])['totalFee'];
                }
            }
            // 计算邮费
            $shipping_fee = 0;
            if ($temp_goods && !empty($address['district'])) {
                $shipping_fee = $goodsExpress->getGoodsExpressTemplate($temp_goods, $address['district'])['totalFee'];
            }
            $is_presell_info = objToArr($shop_info['presell_info']);
            if ($is_presell_info || $shipping_type == 2) {
                $shipping_fee = 0;
            }
            $payment_info[$shop_id]['shipping_fee'] = $shipping_fee;
            $payment_info[$shop_id]['total_amount'] += $shipping_fee;
            $return_data['total_shipping'] += $shipping_fee;
            //抵扣积分
            if ($presell_id > 0 && $is_presell_info) {
                $payment_info[$shop_id]['goods_list'][0]['price'] = $shop_info['presell_info']['allmoney'];
            }
            $point_deductio = $order_business->pointDeductionOrder($payment_info[$shop_id]['goods_list'], 1, $shipping_type);
            $deduction_data[] = $point_deductio;
            //返积分
            $point_return = $order_business->pointReturnOrder($point_deductio['sku_info'], $shipping_type);
            $return_point_data[] = $point_return;
            $payment_info[$shop_id]['goods_list'] = $point_return['sku_info'];
            $return_data['amount'] += $payment_info[$shop_id]['total_amount'];
        }
        //返积分
        $return_data['total_give_point'] = 0;
        if ($return_point_data) {
            foreach ($return_point_data as $k => $v) {
                if ($v['total_return_point'] > 0) {
                    $return_data['total_give_point'] += $v['total_return_point'];
                }
            }
        }
        //积分抵扣
        $return_data['deduction_point'] = [];
        $member_info = $this->user->getMemberAccount($this->uid);
        $return_data['deduction_point']['point'] = $member_info['point'];
        $return_data['deduction_point']['total_deduction_money'] = 0;
        $return_data['deduction_point']['total_deduction_point'] = 0;
        if ($deduction_data) {
            $points = 1;
            foreach ($deduction_data as $k => $v) {
                if ($v['total_deduction_money'] > 0 && $points == 1) {
                    $return_data['deduction_point']['total_deduction_money'] += $v['total_deduction_money'];
                    $return_data['deduction_point']['total_deduction_point'] += $v['total_deduction_point'];
                }
                if ($v['total_deduction_point'] >= $member_info['point']) $points = 0;
            }
            if ($return_data['deduction_point']['total_deduction_money'] > 0 && $is_deduction == 1 && !$presell_id) {
                $return_data['amount'] = "{$return_data['amount']}" - "{$return_data['deduction_point']['total_deduction_money']}";
            }
        }
        $config = new Config();
        $point_deduction = $config->getShopConfig(0, $this->website_id);
        $return_data['is_point_deduction'] = $point_deduction['is_point_deduction'];
        $return_data['is_point'] = $point_deduction['is_point'];
        $return_data['customform'] = $this->user->getOrderCustomForm();
        $return_data['shop'] = array_values($payment_info);
        
        return json(['code' => 1, 'message' => $msg, 'data' => $return_data]);
    }

    //检测商品ID是否在优惠券里
    public function check_is_coupon_goods($couponid, $goods_id)
    {
        $sql = "select * from `vsl_coupon_goods` where `coupon_type_id` = $couponid and `goods_id` = $goods_id";
        return Db::query($sql);
    }

    //计算每件商品运费
    public function resetShippingFee($goodsIds, $nums, $address_id, $ajax = '1')
    {
        if (!$address_id || !$goodsIds) {
            return 0;
        }
        $goods = [];
        foreach ($goodsIds as $key => $val) {
            $goods[$val]['count'] += $nums[$key];
            $goods[$val]['goods_id'] = $val;
        }
        $addressModel = new VslMemberExpressAddressModel();
        $address = $addressModel->getInfo(['id' => $address_id]);
        if (!$address) {
            return 0;
        }
        $goodsExpress = new GoodsExpress();
        $shippingFee = 0.00;
        $shippingFeeGet = $goodsExpress->getGoodsExpressTemplate($goods, $address['district'])['totalFee'];
        if ($shippingFeeGet) {
            $shippingFee = $shippingFeeGet;
        }
        if ($ajax == '1') {
            return json(AjaxReturn(1, $shippingFee));
        } else {
            return $shippingFee;
        }
    }

    /**
     * 收获地址
     */

    public function get_all_address()
    {

        $sql = "select * from `vsl_member_express_address` where `uid` = " . $this->uid;
        $result = Db::query($sql);
        if (!empty($result)) {
            $address = new Address();
            foreach ($result as $k => $v) {
                $address_info = $address->getAddress($v['province'], $v['city'], $v['district']);
                $result[$k]['address_info'] = $address_info;
            }
            $list['data'] = $result;
            $list['code'] = 0;
            $list['message'] = "获取成功";
            return json($list);
        } else {
            $list['message'] = "暂无收货地址";
            $list['data'] = "";
            $list['code'] = '0';
            return json($list);
        }
    }

    /**
     * 设为默认地址
     */

    public function set_default_address($id = '')
    {
        if (empty($id)) {
            $id = request()->post('id');
        }
        //先将地址还原 再设置默认
        Db::query("update `vsl_member_express_address` set `is_default` = 0 where `uid` = " . $this->uid);
        Db::query("update `vsl_member_express_address` set `is_default` = 1 where `id` = " . $id);
        $result['code'] = "0";
        $result['message'] = "设置成功";
        $result['data'] = "";
        return json($result);
    }


    /**
     * 删除收货地址
     */
    public function delete_address()
    {

        $condition['id'] = request()->post('id');
        $condition['uid'] = $this->uid;
        $data = Db::table('vsl_member_express_address')->where($condition)->delete();

        $result['data'] = '';
        $result['code'] = 1;
        $result['message'] = "删除成功";
        return json($result);
    }


    /**
     * 查询省市区
     */
    public function get_area_info()
    {

        $province = request()->post('province');
        $province_id = request()->post('province_id');
        $city_id = request()->post('city_id');

        //省
        if ($province == 'all') {
            $result = Db::query("select * from `sys_province`");
            $list['data'] = $result;
            $list['message'] = "获取成功";
            $list['code'] = 0;
            return json($list);
        } else if (!empty($province_id)) {
            //市
            $result = Db::query("select * from `sys_city` where `province_id` = " . $province_id);
            $list['data'] = $result;
            $list['message'] = "获取成功";
            $list['code'] = 0;
            return json($list);
        } else if (!empty($city_id)) {
            //区
            $result = Db::query("select * from `sys_district` where `city_id` = " . $city_id);
            $list['data'] = $result;
            $list['message'] = "获取成功";
            $list['code'] = 0;
            return json($list);
        } else {
            $list['data'] = '';
            $list['message'] = "获取失败";
            $list['code'] = -1;
            return json($list);
        }


    }


    /**
     * 新增收获地址
     */
    public function add_address()
    {

        $data = array(
            'uid' => $this->uid,
            'consigner' => request()->post('consigner'),
            'mobile' => request()->post('mobile'),
            'phone' => request()->post('phone') ? request()->post('phone') : '',
            'province' => request()->post('province'),
            'city' => request()->post('city'),
            'district' => request()->post('district'),
            'address' => request()->post('address'),
            'zip_code' => request()->post('zip_code') ? request()->post('zip_code') : '',
            'alias' => request()->post('alias') ? request()->post('alias') : '',
            'is_default' => request()->post('is_default') ? request()->post('is_default') : 0,
            'website_id' => $this->website_id
        );

        $data = Db::name('vsl_member_express_address')->insertGetId($data);
        if ($_REQUEST['is_default'] == '1') {
            $this->set_default_address($data);
        }
        if ($data > 0) {
            $result['code'] = 0;
            $result['data'] = "";
            $result['message'] = "添加成功";
            return json($result);
        } else {
            $result['code'] = -1;
            $result['data'] = "";
            $result['message'] = "添加失败";
            return json($result);
        }
    }

    public function area()
    {
        $refresh = request()->post('refresh');
        $areas = Cache::get('vue_areas');
        if ($areas && $refresh == '') {
            return json(['code' => 1, 'message' => '成功获取', 'data' => $areas]);

        }

        $areas = ['province_list' => [], 'province_id_list' => [], 'city_list' => [], 'city_id_list' => [], 'county_list' => [], 'county_id_list' => []];
        
        $data_address = new Address();
        $fields = ['sp.province_id', 'sp.province_name', 'sc.city_id', 'sc.city_name', 'sd.district_id', 'sd.district_name'];
        $area_info = $data_address->getAllAddress([], $fields, 'sd.district_id', 'sp.province_id ASC,sc.city_id ASC,sd.district_id ASC');
        //这个取数据的方式,没有district数据的将取不到数据，例如香港澳门,排序很重要，保证下面循环的时候xxxx_no的累加的时候不会覆盖掉未完成的内容
//        var_dump(Db::table('')->getLastSql());exit;

        $province_no = $temp_province_id = 0;
        $city_no = $temp_city_id = 0;
        $county_no = 0;
        foreach ($area_info as $k => $area) {
            $province_no = $area['province_id'] + 10;
            if ($temp_province_id != $area['province_id']) {
                $temp_province_id = $area['province_id'];
                $city_no = 0;
            }
            if ($temp_city_id != $area['city_id']) {
                $temp_city_id = $area['city_id'];
                $city_no++;
                $county_no = 0;
            }

            if (empty($areas['province_list']) || !in_array($area['province_name'], $areas['province_list'])) {
                // xx0000
                $province_pad = str_pad(str_pad($province_no, 2, '1', STR_PAD_LEFT), 6, '0');
                $areas['province_list'][$province_pad] = $area['province_name'];
                $areas['province_id_list'][$province_pad] = $area['province_id'];
            }
            if (empty($areas['city_list']) || !in_array($area['city_name'], $areas['city_list'])) {
                // xxyy00
                //$city_pad = str_pad((str_pad($province_no, 2, '0', STR_PAD_RIGHT) . $city_no), 6, '0');
                $city_pad = str_pad(str_pad($province_no, 2, '1', STR_PAD_LEFT) . str_pad($city_no, 2, '0', STR_PAD_LEFT), 6, '0');
                $areas['city_list'][$city_pad] = $area['city_name'];
                $areas['city_id_list'][$city_pad] = $area['city_id'];
            }
            if (empty($areas['district_list']) || !in_array($area['district_name'], $areas['district_list'])) {
                // xxyyzz
                $district_pad = str_pad($province_no, 2, '1', STR_PAD_LEFT) . str_pad($city_no, 2, '0', STR_PAD_LEFT) . str_pad($county_no, 2, '0', STR_PAD_LEFT);
                $areas['county_list'][$district_pad] = $area['district_name'];
                $areas['county_id_list'][$district_pad] = $area['district_id'];
            }
            $county_no++;
        }
        Cache::set('vue_areas', $areas, 3600);
        return json(['code' => 1, 'message' => '成功获取', 'data' => $areas]);
        //echo json_encode(['code' => 1, 'message' => '成功获取', 'data' => $areas]);exit;
    }

    public function areaInfo()
    {
        $refresh = request()->post('refresh');
        $areas = Cache::get('app_areas');
        if ($areas && $refresh == '') {
//            var_dump(strlen(json_encode($areas)));
//            var_dump(strlen(json($areas)));exit;
            return json(['code' => 1, 'message' => '成功获取', 'data' => $areas]);
            //echo json_encode(['code' => 1, 'message' => '成功获取', 'data' => $areas],JSON_UNESCAPED_UNICODE);exit;
        }
        $areas = [];
        $data_address = new Address();
        $fields = ['sp.province_id', 'sp.province_name', 'sc.city_id', 'sc.city_name', 'sd.district_id', 'sd.district_name'];
        $area_info = $data_address->getAllAddress([], $fields, 'sd.district_id', 'sp.province_id ASC,sc.city_id ASC,sd.district_id ASC');//这个取数据的方式,没有district数据的将取不到数据，例如香港澳门

        foreach ($area_info as $k => $area) {
            $areas[$area['province_id']]['province_id'] = $area['province_id'];
            $areas[$area['province_id']]['province_name'] = $area['province_name'];

            $areas[$area['province_id']]['city_list'][$area['city_id']]['city_id'] = $area['city_id'];
            $areas[$area['province_id']]['city_list'][$area['city_id']]['city_name'] = $area['city_name'];

            $areas[$area['province_id']]['city_list'][$area['city_id']]['district_list'][$area['district_id']]['district_id'] = $area['district_id'];
            $areas[$area['province_id']]['city_list'][$area['city_id']]['district_list'][$area['district_id']]['district_name'] = $area['district_name'];
        }

        // 将数组的key设为0-n
        $areas = array_values($areas);
        foreach ($areas as $k_p => $province) {
            $areas[$k_p]['city_list'] = array_values($province['city_list']);
            foreach ($areas[$k_p]['city_list'] as $k_c => $city) {
                $areas[$k_p]['city_list'][$k_c]['district_list'] = array_values($areas[$k_p]['city_list'][$k_c]['district_list']);
            }
        }
        Cache::set('app_areas', $areas, 3600);
        return json(['code' => 1, 'message' => '成功获取', 'data' => $areas]);
        //echo json_encode(['code' => 1, 'message' => '成功获取', 'data' => $areas],JSON_UNESCAPED_UNICODE);exit;
    }

    //对象转数组
    function object2array(&$object)
    {
        $object = json_decode(json_encode($object), true);
        return $object;
    }

    /**
     * 加入购物车
     *
     * @return unknown
     */
    public function addShoppingCartSession($cart_id_list, &$msg)
    {
        // 加入购物车
        $goods = new GoodsService();
        $session_cart_list = $cart_id_list;
        $cart_id_arr = explode(",", $session_cart_list);
        $cart_list = $goods->getCartList($cart_id_arr, $msg);
        if (count($cart_list) == 0) {
            return 0;
        }

        $list = Array();
        $str_cart_id = ""; // 购物车id
        $goods_sku_list = ''; // 商品skuid集合
        sort($cart_id_arr);
        for ($i = 0; $i < count($cart_list); $i++) {
            if ($cart_id_arr[$i] == $cart_list[$i]["cart_id"]) {
                $list[] = $cart_list[$i];
                $str_cart_id .= "," . $cart_list[$i]["cart_id"];
                $goods_sku_list .= "," . $cart_list[$i]['sku_id'] . ':' . $cart_list[$i]['num'];
            }
        }
        $goods_sku_list = substr($goods_sku_list, 1); // 商品sku列表
        $res["list"] = $list;
        $res["goods_sku_list"] = $goods_sku_list;
        return $res;
    }
    public function goodsShareDetail()
    {
        $goods_id = request()->post('goods_id');
        $channel_id = request()->post('channel_id');
        if (empty($goods_id) || !is_numeric($goods_id)) {
            return json(['code' => -1, 'message' => '无效商品']);
        }
        $goods_model = new VslGoodsModel();//联表查询sku,pic
        $goods_info = $goods_model::get(['goods_id' => $goods_id], ['sku', 'album_picture']);
        if (empty($goods_info)) {
            return json(['code' => -1, 'message' => '无效商品']);
        }
        $goods_data['goods_id'] = $goods_id;
        $goods_data['image'] = getApiSrc($goods_info->album_picture->pic_cover);

        // todo... iamge 进行云存储 by sgw
        if ($goods_data['image']) {
            $goods_server = new GoodsService();
            $upload_url = $goods_server->modifyImageUrl2AliOss($goods_data['image']);
            $goods_data['image'] = $upload_url;
        }

        $goods_data['goods_name'] = $goods_info['goods_name'];
        $goods_data['price'] = reset($goods_info->sku)['price'];
        $goods_data['market_price'] = reset($goods_info->sku)['market_price'];


        foreach ($goods_info->sku as $v) {
            $goods_data['price'] = ($goods_data['price'] > $v['price']) ? $v['price'] : $goods_data['price'];
            $goods_data['market_price'] = ($goods_data['market_price'] > $v['market_price']) ? $v['market_price'] : $goods_data['market_price'];
        }

        $goods_preference = new GoodsPreference();
        $member_discount = $goods_preference->getMemberLevelDiscount($this->uid);
        // 获取限时折扣
        $promotion_info['discount_num'] = 10;
        if ($this->is_discount) {
            $promotion = new Discount();
            $promotion_info = $promotion->getPromotionInfo($goods_id, $goods_data['shop_id'], $goods_data['website_id']);
        }
        $goods_data['member_discount'] = $member_discount;
        $goods_data['limit_discount'] = $promotion_info['discount_num'] / 10;

        $goods_data['uid'] = $this->uid;
        if ($channel_id) {
            //获取渠道商的信息。
            $channel_info = VslChannelModel::get(['uid' => $this->uid]);
            $goods_data['channel_id'] = $channel_info['channel_id'];
            $goods_data['is_channel'] = $goods_data['is_distributor'] && $this->uid && $channel_info['status'] == 1 && getAddons('channel', $this->website_id) ? true : false;
        }
        $user_info = UserModel::get(['user_model.uid' => $this->uid], ['member_info']);
        $goods_data['is_distributor'] = $this->uid && $user_info['member_info']['isdistributor'] == 2 && getAddons('distribution', $this->website_id) ? true : false;

        $goods_data['poster_image'] = '';
        if (getAddons('poster', $this->website_id, 0, true) && $user_info['member_info']['isdistributor'] == 2) {
            $poster_service = new Poster();
            $poster_info = $poster_service->poster(['website_id' => $this->website_id, 'is_default' => 1, 'is_system_default' => 0, 'type' => 2]);
            if ($poster_info) {
                $poster_result = $poster_service->posterImage($poster_info, $poster_info['poster_id'], 'poster', '54321', $goods_id);
                if ($poster_result['code'] == 1) {
                    $goods_data['poster_image'] = getApiSrc($poster_result['poster']);
                }
            }
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $goods_data]);
    }

    public function goodsAttribute()
    {
        $goods_id = request()->post('goods_id');
        if (empty($goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $goods_server = new GoodsService();
        $goods_attribute = $goods_server->goodsAttribute(['goods_id' => $goods_id], ['attribute_value']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $goods_attribute]);
    }

    /**
     * 修改地址
     */
    public function update_address()
    {

        $id = request()->post('id');
        $data = array(
            'consigner' => request()->post('consigner'),
            'mobile' => request()->post('mobile'),
            'phone' => request()->post('phone') ? request()->post('phone') : '',
            'province' => request()->post('province'),
            'city' => request()->post('city'),
            'district' => request()->post('district'),
            'address' => request()->post('address'),
            'zip_code' => request()->post('zip_code') ? request()->post('zip_code') : '',
            'alias' => request()->post('alias') ? request()->post('alias') : '',
            'is_default' => request()->post('is_default') ? request()->post('is_default') : 0,
            'website_id' => $this->website_id
        );
        $result = Db::table('vsl_member_express_address')->where('id', $id)->update($data);
        if (request()->post('is_default') == '1') {
            $this->set_default_address($id);
        }

        if ($result > 0) {
            $resulta['code'] = 0;
            $resulta['data'] = "";
            $resulta['message'] = "修改成功";
            return json($resulta);
        } else {
            $resulta['code'] = -1;
            $resulta['data'] = "";
            $resulta['message'] = "添加失败";
            return json($resulta);
        }
    }

    public function goodsReviewsList()
    {
        try {
            $goods_id = request()->post('goods_id');
            $page_index = request()->post('page_index');
            $page_size = request()->post('page_size') ?: PAGESIZE;
            $is_image = request()->post('is_image');
            $explain_type = request()->post('explain_type');
            $is_again = request()->post('is_again');
            if (empty($goods_id) || empty($page_index)) {
                return json(AjaxReturn(LACK_OF_PARAMETER));
            }
            if ($is_image) {
                $condition['image|again_image'] = ['NEQ', ''];
            }
            if ($explain_type && in_array($explain_type, [5, 3, 1])) {
                $condition['explain_type'] = $explain_type;
            }
            if ($is_again) {
                $condition['again_content'] = ['NEQ', ''];
            }

            $condition['goods_id'] = $goods_id;
            $condition['is_show'] = 1;


            $goods_service = new GoodsService();
            $field = ['id', 'content', 'image', 'explain_first', 'uid', 'member_name', 'again_content', 'again_image', 'again_explain',
                'explain_type', 'addtime', 'again_addtime', 'explain_time', 'again_explain_time'];
            $review_data = $goods_service->getGoodsEvaluateList($page_index, $page_size, $condition, 'addtime DESC', $field);
            foreach ($review_data['data'] as $k => &$v) {
                $images = !empty($v['image']) ? explode(',', $v['image']) : [];
                if ($images) {
                    foreach ($images as &$im1) {
                        $im1 = getApiSrc($im1);
                    }
                    unset($im1);
                }
                $review_data['data'][$k]['images'] = $images;
                $again_images = !empty($v['again_image']) ? explode(',', $v['again_image']) : [];
                if ($again_images) {
                    foreach ($again_images as &$im2) {
                        $im2 = getApiSrc($im2);
                    }
                    unset($im2);
                }
                $review_data['data'][$k]['again_images'] = $again_images;
                unset($review_data['data'][$k]['image'], $review_data['data'][$k]['again_image']);
                # 处理用户信息
				$v['user_img'] = getApiSrc($v['user_img']);
            }
            $evaluate_count = $goods_service->getGoodsEvaluateCount($goods_id);
            return json(['code' => 1, 'message' => '获取成功',
                'data' => [
                    'review_list' => $review_data['data'],
                    'total_count' => $review_data['total_count'],
                    'page_count' => $review_data['page_count'],
                    'evaluate_count' => $evaluate_count['evaluate_count'],
                    'again_count' => $evaluate_count['again_count'],
                    'imgs_count' => $evaluate_count['imgs_count'],
                    'praise_count' => $evaluate_count['praise_count'],
                    'center_count' => $evaluate_count['center_count'],
                    'bad_count' => $evaluate_count['bad_count'],
                ]
            ]);
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
            return json(AjaxReturn(SYSTEM_ERROR));
        }
    }

    /**
     * 收藏商品
     */
    public function collectGoods()
    {
        try {
            $goods_id = request()->post('goods_id');
            $seckill_id = request()->post('seckill_id', 0);
            if (empty($goods_id)) {
                return json(AjaxReturn(LACK_OF_PARAMETER));
            }
            if (!$this->uid) {
                return json(AjaxReturn(NO_LOGIN));
            }
            $result = $this->user->addMemberFavouites($goods_id, 'goods', '', $seckill_id);
            if ($result || $result == 0) {
                return json(AjaxReturn(SUCCESS));
            } else {
                return json(AjaxReturn(ADD_FAIL));
            }
        } catch (\Exception $e) {
            return json(AjaxReturn(SYSTEM_ERROR));
        }
    }

    /**
     * 取消商品收藏
     */
    public function cancelCollectGoods()
    {
        try {
            $goods_id = request()->post('goods_id');
            if (empty($goods_id)) {
                return json(AjaxReturn(LACK_OF_PARAMETER));
            }
            if (!$this->uid) {
                return json(AjaxReturn(NO_LOGIN));
            }
            $result = $this->user->deleteMemberFavorites($goods_id, 'goods');
            if ($result || $result == 0) {
                return json(AjaxReturn(SUCCESS));
            } else {
                return json(AjaxReturn(ADD_FAIL));
            }
        } catch (\Exception $e) {
            return json(AjaxReturn(SYSTEM_ERROR));
        }
    }

    /**
     * 添加购物车
     */
    public function addCart()
    {
        $sku_id = request()->post('sku_id');
        //需要秒杀id
        $seckill_id = request()->post('seckill_id', 0);
        $channel_id = request()->post('channel_id', 0);
        //只要是直播间过来的，购物车的商品就都算那个直播间的。
        $anchor_id = request()->post('anchor_id', 0);
        //$anchor_id = 1;//先写死为1
        $num = request()->post('num');
        if (empty($sku_id) || empty($num)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $uid = $this->uid ?: 0;
        $goods_server = new GoodsService();
        $sku_model = new VslGoodsSkuModel();
        $sku_info = $sku_model::get($sku_id, ['goods']);
        if (!$sku_info) {
            return json(['code' => -1, 'message' => '商品不存在']);
        }
        $goods_name = $sku_info->goods->goods_name;
        $goods_id = $sku_info->goods_id;
        $shop_id = $this->instance_id;
        $sku_name = $sku_info->sku_name;
        $supplier_id = $sku_info->goods->supplier_info;
        $msg = '';
        if (!empty($seckill_id) && $this->is_seckill) {
            $seckill_server = new seckillServer();
            //根据秒杀活动和sku_id获取秒杀sku的价格
            $seckill_condition['s.seckill_id'] = $seckill_id;
            $seckill_condition['nsg.sku_id'] = $sku_id;
            //判断活动是否开始 false 未开始  true 开始
            $seckill_sku_list = $seckill_server->getSeckillStatus($seckill_condition);
            if ($seckill_sku_list == 'ended'){
                $msg = '该商品秒杀活动已结束，价格将恢复为商品原价';
                $price = $sku_info->price;
            }  else {
                if($seckill_sku_list != 'unstart'){
                    $price = $seckill_sku_list->seckill_price;
                }
            }
        } else {
            $price = $sku_info->price;
        }

//        // 会员折扣是后价格 by sgw  加入购物车不需要
//        $goodsDiscountInfo = $goods_server->getGoodsInfoOfIndependentDiscount($goods_id, $price);
//        if ($goodsDiscountInfo) {
//            $price = $goodsDiscountInfo['member_price'];
//        }
        $picture_id = $sku_info->goods->picture;
        $_SESSION['order_tag'] = ""; // 清空订单
        $result = $goods_server->addcart($uid, $shop_id, $goods_id, $goods_name, $sku_id, $sku_name, $price, $num, $picture_id, 0, $seckill_id, $anchor_id,$supplier_id);
        if ($result > 0) {
            //添加成功后，添加数据到直播数据统计表  vsl_live_user_data
            if($uid){
                if(getAddons('liveshopping', $this->website_id)){
                    $live = new LiveModel();
                    $live_info = $live->getInfo(['anchor_id' => $anchor_id], 'live_id, status, play_time');
                    if($live_info['status'] == 2){
                        $live_id = $live_info['live_id'];
                        $play_time = $live_info['play_time'];
                        $live_user_data = new LiveUserDataModel();
                        $live_record = new LiveRecordModel();
                        $has_user_data = $live_user_data->where(['uid'=>$uid, 'live_id' => $live_id, 'type'=>4, 'play_time' => $play_time, 'website_id' => $this->website_id])->find();
                        if($has_user_data){
                            $live_user_data->num = $has_user_data->num + 1;
                            $live_user_data->save();
                        }else{
                            $user_data['uid'] = $uid;
                            $user_data['live_id'] = $live_id;
                            $user_data['type'] = 4;
                            $user_data['num'] = 1;
                            $user_data['play_time'] = $play_time;
                            $user_data['create_time'] = time();
                            $user_data['website_id'] = $this->website_id;
                            $live_user_data->save($user_data);
                            //在这里更新加入购物车人数
                            $up_cond['live_id'] = $live_id;
                            $up_cond['play_time'] = $live_info['play_time'];
                            $up_cond['website_id'] = $this->website_id;
                            $live_record_list = $live_record->where($up_cond)->find();
                            $live_record_list->add_cart_member = $live_record_list->add_cart_member + 1;
                            $live_record_list->save();
                        }
                        //在这里更新加入购物车次数
                        $up_cond['live_id'] = $live_id;
                        $up_cond['play_time'] = $live_info['play_time'];
                        $up_cond['website_id'] = $this->website_id;
                        $live_record_list = $live_record->where($up_cond)->find();
                        $live_record_list->add_cart = $live_record_list->add_cart + 1;
                        $live_record_list->save();
                    }
                }
            }
            if (empty($msg)) {
                return json(['code' => 0, 'message' => '添加成功', 'data' => ['cart_id' => $result]]);
            } else {
                return json(['code' => 1, 'message' => $msg, 'data' => ['cart_id' => $result]]);
            }
        } else {
            return json(AjaxReturn(ADD_FAIL));
        }
    }

    public function buyAgain()
    {
        $cart = request()->post('cart/a');
        if (empty($cart)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $goods_server = new GoodsService();
        $sku_model = new VslGoodsSkuModel();
        if ($this->is_seckill) {
            $seckill_server = new seckillServer();
        }
        foreach ($cart as $v) {
            $sku_info = $sku_model::get($v['sku_id'], ['goods']);
            //判断秒杀是否在活动期限内 添加活动那里验证了 24小时内只能加入同一个sku一次
            if ($this->is_seckill) {
                $seckill_condition['nsg.sku_id'] = $v['sku_id'];
                //$seckill_list存在则说明sku在活动中，若为null说明过期了
                $seckill_sku_list = $seckill_server->isSkuStartSeckill($seckill_condition);
            }
            if ($seckill_sku_list) {
                $price = $seckill_sku_list->seckill_price;
                $seckill_id = $seckill_sku_list->seckill_id;
            } else {
                $price = $sku_info->price;
                $seckill_id = 0;
            }
            $goods_server->addcart($this->uid, $this->instance_id, $sku_info['goods_id'], $sku_info['goods']['goods_name'], $sku_info['sku_id'], $sku_info['sku_name'], $price, $v['num'], $sku_info['goods']['picture'], 0, $seckill_id);
        }
        return json(['code' => 1, 'message' => '成功加入购物车']);
    }

    /**
     * 立即购买
     */
    public function buyNow()
    {
        $sku_id = request()->post('sku_id');
        $num = request()->post('num');
        if (empty($sku_id) || empty($num)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $_SESSION['order_tag'] = 'buy_now';
        $_SESSION['order_sku_list'] = $sku_id . ':' . $num;
        return json(['code' => 1, 'message' => '添加成功']);
    }

    /**
     * 计算运费
     **/
    public function count_free()
    {

        //计算运费
        $goods_id = request()->post('goods_id');
        $goods_id = [$goods_id];
        if (empty($goods_id)) {
            $res['code'] = '-1';
            $res['message'] = "商品ID不能为空";
        }
        $address_id = request()->post('address_id');
        $num = request()->post('num') ? request()->post('num') : '1';
        if (empty($address_id)) {
            $free_money = 0;
        } else {
            $free_money = $this->resetShippingFee($goods_id, $num, $address_id, 0);
        }
        $res['data']['free_money'] = $free_money;
        $res['code'] = 0;
        $res['message'] = "获取成功";
        return json($res);
    }

    /**
     * 商品列表 （自动推荐） 该方式是包含真实销量的
     */
    public function goodsList()
    {
        $goods_type = request()->post('goods_type');
        $microshop_type = request()->post('microshop_type');
        $last_id = request()->post('last_id', '');
        $uuid = request()->post('uuid', 0);
        $order = request()->post('order') ?: 'create_time';
        //edit for 2020/04/16 修改为可选自营或者全平台
        //默认自营店
        $microshop_types = 0;
        //查询是否开启应用
        if (getAddons('microshop', $this->website_id, $this->instance_id) && $microshop_type) {
            //获取设置
            $config= new MicroShopService();
            $list = $config->getMicroShopSite($this->website_id);
            
            $microshop_types = $list['pro_types'] ? $list['pro_types'] : 0;
            
        }
        $query = 'select goods_id,goods_name,price, max_price,album_picture.pic_cover,create_time,sales,real_sales, promotion_type,shop_id,market_price, activity_pic';
        if($order){
            $query .= ','.$order;
        }
        $query .= ' from '.config('database.database').'_goods where state = 1 and for_store = 0 ';
        $microshop_types = intval($microshop_types);
        if ($microshop_type && $microshop_types == 0) {
            
            $condition['ng.shop_id'] = 0;
            $query.= ' and shop_id =0';
        }

        $shopkeeper_id = request()->post('shopkeeper_id');
        $microshop_goods = '';
        $member = new VslMemberModel();
        if($shopkeeper_id){
            $microshop_goods = $member->getInfo(['uid' => $shopkeeper_id], 'microshop_goods')['microshop_goods'];
            if($microshop_type && !$microshop_goods){
                return json(['code' => 1, 'message' => '获取成功',
                    'data' => [
                        'goods_list' => [],
                        'page_count' => 0,
                        'total_count' => 0,
                        'last_id' => '',
                        'uuid' => $uuid
                    ]
                ]);
            }
            if($microshop_goods){
                $condition['ng.goods_id'] = ['in', $microshop_goods];
                $query.= ' and goods_id in ('.$microshop_goods.')';
            }
        }
        $search_text = request()->post('search_text');
        $page_index = request()->post('page_index');
        $page_size = request()->post('page_size') ?: PAGESIZE;
        
        $sort = request()->post('sort') ?: 'DESC';
        $min_price = request()->post('min_price');
        $max_price = request()->post('max_price');
        $is_recommend = request()->post('is_recommend', 0);
        $is_new = request()->post('is_new', 0);
        $is_hot = request()->post('is_hot', 0);
        $is_promotion = request()->post('is_promotion', 0);
        $is_shipping_free = request()->post('is_shipping_free', 0);
        $is_plus_member = request()->post('is_plus_member', 0);

        $category_id = request()->post('category_id');
        
        if(strlen($sort) > 4) {
            //防sql注入
            return json(AjaxReturn(PARAMETER_ERROR));
        }
        if($order != 'create_time') {
            if(strlen($order) > 10) {
                //防sql注入
                return json(AjaxReturn(PARAMETER_ERROR));
            }
        }
        if (empty($page_index)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $goods_server = new VslGoodsViewModel();
        $goodsSer = new GoodsService();
        $group = 'ng.goods_id';
        $order_sort = 'ng.' . $order . ' ' . $sort;
        $order_sort_es = $order . ' ' . $sort;
        $condition['ng.state'] = 1;
        
    
        if ($order == 'sales') {
            $order_sort = 'sales '.$sort;
            $order = 'sales+real_sales';
            $order_sort_es = ' sales + real_sales ' . $sort;
        }
        if ($search_text) {
            $condition['ng.goods_name'] = ['LIKE', '%' . $search_text . '%'];
            $query.= " and goods_name like '%".$search_text."%'";
        }
        if ($min_price != '') {
            $condition['ngs.price'][] = ['>=', $min_price];
            $query.= ' and max_price >='.$min_price;
        }
        if ($max_price != '') {
            $condition['ngs.price'][] = ['<=', $max_price];
            $query.= ' and min_price <='.$max_price;
        }
        if ($category_id) {
            $ids = [];
            $goods_category = new GoodsCategory();
            $category_list = $goods_category->getCategoryTreeList($category_id);
            $category_str = explode(",", $category_list);
            foreach ($category_str as $category_id1) {
                if (!$ids) {
                    $ids = Db::table('vsl_goods')->where('find_in_set(' . $category_id1 . ',extend_category_id)')->field('goods_id')->select();
                } else {
                    $else = Db::table('vsl_goods')->where('find_in_set(' . $category_id1 . ',extend_category_id)')->field('goods_id')->select();
                    $ids = array_merge($ids, $else);
                }
            }
            if($ids){
                $goods_id_str = array_column($ids,'goods_id');
                $condition[] = ['exp', 'ng.category_id_1 = '.$category_id.' or ng.category_id_2 = '.$category_id.' or ng.category_id_3 = '.$category_id.' or ng.goods_id in ('.implode(',',$goods_id_str).')'];
            }else{
                $condition['ng.category_id_1|ng.category_id_2|ng.category_id_3'] = $category_id;
            }
            $query.= " and all_cate_ids like '%,".$category_id.",%'";
        }
        if ($is_recommend == 1) {
            $condition['ng.is_recommend'] = 1;
            $query.= " and is_recommend = 1";
        }
        if ($is_new == 1) {
            $condition['ng.is_new'] = 1;
            $query.= " and is_new = 1";
        }
        if ($is_hot == 1) {
            $condition['ng.is_hot'] = 1;
            $query.= " and is_hot = 1";
        }
        if ($is_promotion == 1) {
            $condition['ng.is_promotion'] = 1;
            $query.= " and is_promotion = 1";
        }
        if ($is_shipping_free == 1) {
            $condition['ng.is_shipping_free'] = 1;
            $query.= " and is_shipping_free = 1";
        }
        if ($is_plus_member == 1) {
            $condition['ng.plus_member'] = 1;
            $query.= " and plus_member = 1";
        }
        //判断是否有店铺系统
        if ($this->is_shop){
            $condition['vs.shop_state'] = 1;
            $query.= " and shop_state = 1";
        }
        $condition['ng.website_id'] = $this->website_id;
        $query.= " and website_id =" .$this->website_id;
        // 0自营  1全平台 2店铺 $microshop_types=1
        $shopHide = 0;
        
        if($this->is_shop){
            $shop = new ShopServer();
            $shopHide = $shop->getShopInfo(0, 'shop_hide')['shop_hide'];
        }
        if (isset($goods_type) && $goods_type == 0 && $microshop_types == 0) {
            $condition['ng.shop_id'] = $shopHide ? ['<', 0] : 0;
            $query.= " and shop_id " . ($shopHide ? '<0' : '=0');
        } elseif ($goods_type == 2 && $this->is_shop && $microshop_types == 0) {
            //店铺商品
            if (request()->post('shop_id') != '') {
                $condition['ng.shop_id'] = request()->post('shop_id') ?: 0;
                $query.= " and shop_id = " . (request()->post('shop_id') ? : 0);
                
            } else {
                $condition['ng.shop_id'] = $shopHide ? ['<', 0] : 0;
                $query.= " and shop_id = " . ($shopHide ? '<0' : '=0');
                
            }
        }
        // 获取该用户的权限
        $microshop_goods = '';
        if($this->uid){
            $userService = new User();
            $userLevle = $userService->getUserLevelAndGroupLevel($this->uid);// code | <0 错误; 1系统会员; 2;分销商; 3会员
            
            if (!empty($userLevle)) {
                $microshop_goods = isset($userLevle['microshop_goods']) ? $userLevle['microshop_goods'] : '';
                $level_flag = false;
                $sql1 = '';
                $sql2 = '(';
                $levelQuery = '(';
                // 会员权限
                if (isset($userLevle['user_level'])) {
                    $level_flag = true;
                    $u_id = $userLevle['user_level'];
                    $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_u, ',' ), '," . $u_id . ",' ) OR ";
                    $sql2 .= "vgd.browse_auth_u IS NULL OR vgd.browse_auth_u = '' ";
                    $levelQuery .= " browse_auth_u like '%,".$u_id.",%'";
                }
                // 分销商权限
                if (isset($userLevle['distributor_level'])) {
                    $level_flag = true;
                    $d_id = $userLevle['distributor_level'];
                    $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_d, ',' ), '," . $d_id . ",' ) OR ";
                    $sql2 .= " OR vgd.browse_auth_d IS NULL OR vgd.browse_auth_d = '' ";
                    if($levelQuery == '('){
                        $levelQuery .= " browse_auth_d like '%,".$d_id.",%'";
                    }else{
                        $levelQuery .= " or browse_auth_d like '%,".$d_id.",%'";
                    }
                }
                // 标签权限
                if (isset($userLevle['member_group'])) {
                    $level_flag = true;
                    $g_ids = explode(',', $userLevle['member_group']);
                    foreach ($g_ids as $g_id) {
                        $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_s, ',' ), '," . $g_id . ",' ) OR ";
                        $sql2 .= " OR vgd.browse_auth_s IS NULL OR vgd.browse_auth_s = '' ";
                        if($levelQuery == '('){
                            $levelQuery .= " browse_auth_s like '%,".$g_id.",%'";
                        }else{
                            $levelQuery .= " or browse_auth_s like '%,".$g_id.",%'";
                        }
                    }
                } else {
                    $sql1 .= " ";
                }

                $sql2 .= " )";
                $levelQuery .= ') ';
                if ($level_flag){
                $condition[] = ['exp', $sql1 . $sql2];
                }
                if($levelQuery !== '()'){
                    $query .= ' and ' . $levelQuery;
                }
            }
        }
        //供应商关闭，不显示供应商商品
        if (getAddons('supplier',$this->website_id)){
            $condition[] = ['exp', ' if(ng.supplier_info >0, s.status =1, 1)'];//供应商必须为开启
            $query .= ' and (supplier_info = 0 or (supplier_info>0 and supplier_status=1))';
        }
        $query .= ' order by ' . $order_sort_es .',goods_id asc';
//        $list = $goods_server->wapGoods($page_index, $page_size, $condition, '*, ng.price as goods_price,ngs.market_price as market_price', $order_sort, $group);
        //$list = $goods_server->wapGoods($page_index, $page_size, $condition, 'ng.goods_id, ng.goods_name, ng.sales,ng.real_sales, pic_cover, ng.price as goods_price,ngs.market_price as market_price', $order_sort, $group);
        if(!$uuid){
            $uuid = str_replace(['{','}'],['',''],create_guid());
        }
        if(config('ecs.host')){
            $redis = connectRedis();
            $lastQuery = $redis->get($uuid);
            //使用redis记录上一次查询的查询条件,一样的话接着上一次返回的最后一条查下一页
            if($lastQuery === md5($query)){
                $list = $goodsSer->getWapGoodsEs($page_size, $query, $last_id, [$order,'goods_id']);
            }else{
                $redis->setex($uuid, 86400, md5($query));
                $list = $goodsSer->getWapGoodsEs($page_size, $query, 0, [$order,'goods_id']);
            }
        }else{
            $list = $goods_server->wapGoods($page_index, $page_size, $condition, 'ng.goods_id, ng.goods_name, ng.sales,ng.real_sales, pic_cover, ng.price as goods_price,max(ngs.market_price) as market_price,ng.sales + ng.real_sales sales, ng.promotion_type, ng.shop_id, ng.activity_pic', $order_sort, $group);
        }
        $goods_list = [];
        $album = new AlbumPictureModel();
        if(isset($list['data'])){
            foreach ($list['data'] as $k => $v) {
                $v['goods_price'] = isset($v['goods_price']) ? $v['goods_price'] : $v['price'];
                //是否已经登陆 获取用户
                $v['goods_types'] = 0;
                if ($this->uid) {
                    //获取会员价
                    $pprice = $v['goods_price'];
                    $goodsDiscountInfo = $goodsSer->getGoodsInfoOfIndependentDiscount($v['goods_id'], $v['goods_price'], ['price' => $v['goods_price'], 'shop_id' => $v['shop_id']]);
                    if ($goodsDiscountInfo) {
                        $v['goods_price'] = $goodsDiscountInfo['member_price'];
                        $v['goods_types'] = 1;
                        $discount_service = getAddons('discount', $this->website_id) ? new Discount() : '';
                        $limit_discount_info = getAddons('discount', $this->website_id) ? $discount_service->getPromotionInfo($v['goods_id'], $this->instance_id, $this->website_id) : ['discount_num' => 10];
                        if ($limit_discount_info['status'] == 1 && $limit_discount_info['discount_type'] == 1) {
                            $v['goods_types'] = 2; //限时折扣
                            $v['goods_price'] = $pprice * $limit_discount_info['discount_num'] / 10;
                            if ($limit_discount_info['integer_type'] == 1) {
                                $v['goods_price'] = round($v['goods_price']);
                            }
                        } else if ($limit_discount_info['status'] == 1 && $limit_discount_info['discount_type'] == 2) {
                            $v['goods_types'] = 2; //限时折扣
                            $v['goods_price'] = $limit_discount_info['discount_num'];
                        }
                    }
    
                    //分佣
                    $member = new VslMemberModel();
                    $distribution = $member->getInfo(['uid' => $this->uid], 'isdistributor');
                    if ($distribution['isdistributor'] == 2) {// 分销商
                        $distribution = new Distributor();
                        $info = $distribution->getGoodsCommission($this->website_id, $v['goods_id'], $this->uid, $v['goods_price']);
                        $commission = $info['commission'];
                        $point = $info['point'];
                    }
                }
                if($v['promotion_type'] > 0){
                    if ($this->is_seckill) {
                        //判断是否是秒杀商品
                        $seckill_server = new SeckillServer();
                        $sec_goods = new VslSeckGoodsModel();
                        //判断如果是秒杀商品，则取最低秒杀价
                        $goods_id = $v['goods_id'];
                        $seckill_condition['nsg.goods_id'] = $goods_id;
                        $is_seckill = $seckill_server->isSkuStartSeckill($seckill_condition);
                        if ($is_seckill) {
                            $sec_goods_list = $sec_goods->query(['seckill_id' => $is_seckill['seckill_id'], 'goods_id' => $goods_id], 'seckill_sales', '');
                            $total_sales = array_sum($sec_goods_list);
                            $v['sales'] += $total_sales;
                            $v['goods_price'] = $is_seckill['seckill_price'];
                            $v['goods_types'] = 3;
                        }
                    }
                    if ($this->is_presell) {
                        $presell_server = new Presell();
                        //判断如果是预售商品
                        $goods_id = $v['goods_id'];
                        $is_presell = $presell_server->getPresellInfoByGoodsIdIng($goods_id);
                        if ($is_presell) {
                            $v['goods_price'] = $is_presell[0]['all_money'];
                            $v['sales'] += $is_presell[0]['presell_sales'];
                            $v['goods_types'] = 4;
                            //获取该场次卖出去多少
        //                    $activity_num = $is_presell[0]['presell_sales'];
                        }
                    }
                    if ($this->is_bargain) {
                        $bargain_server = new Bargain();
                        //判断如果是预售商品
                        $goods_id = $v['goods_id'];
                        $condition_bargain['website_id'] = $this->website_id;
                        $condition_bargain['goods_id'] = $goods_id;
                        $condition_bargain['end_bargain_time'] = ['>', time()];//未结束的
                        $condition_bargain['start_bargain_time'] = ['<', time()];//未结束的
                        $is_bargain = $bargain_server->isBargainByGoodsId($condition_bargain, 0);
                        if ($is_bargain) {
                            $v['goods_price'] = $is_bargain['start_money'];
                            $v['sales'] += $is_bargain['bargain_sales'];
                            $v['goods_types'] = 5;
                            //获取该场次卖出去多少
        //                    $activity_num = $is_bargain['bargain_sales'];
                        }
                    }
                }
                //是否存在会员折扣
                //待补充
                $goods_list[$k]['goods_id'] = $v['goods_id'];
                $pic_cover = $album->getInfo(['pic_id' => $v['activity_pic']], 'pic_cover')['pic_cover'] ? : '';
                if($v['goods_id'] == 143562){
                    debugLog($album->getLastSql(), 'getlastsql'.$v['goods_id']);
                    debugLog($pic_cover, '$pic_cover'.$v['goods_id']);
                }
                $goods_list[$k]['activity_pic'] = getApiSrc($pic_cover);
                $goods_list[$k]['goods_types'] = $v['goods_types'];
                $goods_list[$k]['goods_name'] = $v['goods_name'];
                $goods_list[$k]['price'] = $v['goods_price'];
                $goods_list[$k]['market_price'] = $v['market_price'];
                $goods_list[$k]['sales'] = $v['sales'] + $v['real_sales'];
                $goods_list[$k]['real_sales'] = $v['real_sales'];
                $goods_list[$k]['logo'] = isset($v['pic_cover']) ? getApiSrc($v['pic_cover']) : ($v['album_picture'] ? getApiSrc($v['album_picture']['pic_cover']) : '');
                if ($commission) {
                    $goods_list[$k]['commission'] = $commission;
                }
                if ($point) {
                    $goods_list[$k]['point'] = $point;
                }
                $goods_list[$k]['point'] =isset($v['pic_cover']) ? getApiSrc($v['pic_cover']) : ($v['album_picture'] ? getApiSrc($v['album_picture']['pic_cover']) : '');
                if ($microshop_type && $microshop_goods) {
                    $goodsids = explode(',', $microshop_goods);
                    $goods_list[$k]['mic_selectedgoods'] = in_array($v['goods_id'], $goodsids) ? 1 : 0;
                }
               
            }
        }
        $res = json(['code' => 1, 'message' => '获取成功',
            'data' => [
                'goods_list' => $goods_list,
                'page_count' => (int)$list['page_count'],
                'total_count' => $list['total_count'],
                'last_id' => $list['search_after'],
                'uuid' => $uuid,
            ]
        ]);
        return $res;
    }

    public function categoryInfo()
    {
        $goods_category_server = new GoodsCategory();
        $condition['is_visible'] = 1;
        $condition['website_id'] = $this->website_id;
        $condition['category_id'] = array('<>', 127);
        $category_info = $goods_category_server->getGoodsCategoryList(1, 0, $condition, 'level ASC,sort ASC', '*');
        $category_list_visible = [];// 用于筛选父类is_visible = 0的内容
        foreach ($category_info['data'] as $k => $value) {
            $category_list_visible[$value['category_id']] = $value;
        }
        $category_list = [];
        foreach ($category_info['data'] as $k => $v) {
            $temp = [
                'category_id' => $v['category_id'],
                'category_name' => $v['category_name'],
                'short_name' => $v['short_name'],
                'category_pic' => getApiSrc($v['category_pic']),
            ];
            if ($v['level'] == 1) {
                $category_list[$v['category_id']] = $temp;
                continue;
            }
            if ($v['level'] == 2 && $category_list_visible[$v['pid']]['is_visible'] == 1) {
                $category_list[$v['pid']]['second_category'][$v['category_id']] = $temp;
                continue;
            }
            if ($v['level'] == 3 && $category_list_visible[$v['pid']]['is_visible'] == 1) {
                if (empty($category_list_visible[$category_list_visible[$v['pid']]['pid']]['is_visible'])) {
                    continue;
                }
                $category_list[$category_list_visible[$v['pid']]['pid']]['second_category'][$v['pid']]['third_category'][] = $temp;
                continue;
            }
        }

        // 将数组的key设为0-n
        if (!empty($category_list)) {
            $category_list = array_values($category_list);
        }
        foreach ($category_list as $k_f => $v_f) {
		if(!isset($category_list[$k_f]['category_id'])){
			unset($category_list[$k_f]);
			continue;
		}
            $category_list[$k_f]['second_category'] = !empty($v_f['second_category']) ? array_values($v_f['second_category']) : [];
            foreach ($category_list[$k_f]['second_category'] as $k_s => $v_s) {
                $category_list[$k_f]['second_category'][$k_s]['third_category'] = !empty($v_s['third_category']) ? array_values($v_s['third_category']) : [];
            }
        }

        return json(['code' => 1, 'message' => '获取成功', 'data' => $category_list]);
    }

    /**
     * 通过ID商品列表 (手动推荐) 该方式是不包含真实销量的
     */
    public function goodsListIndex()
    {
        $goods_ids = request()->post('goods_ids') ?: '';
        $goods_server = new VslGoodsViewModel();
        $album = new AlbumPictureModel();
        $query = 'select goods_id,goods_name,price,max_price,create_time,sales,real_sales,shop_id,album_picture.pic_cover,market_price from '.config('database.database').'_goods where state = 1 and for_store = 0 ';
        $goods_ids_arr = [];
        if ($goods_ids) {
            $goods_ids_arr = explode(',', $goods_ids);
            $condition['ng.goods_id'] = ['in', $goods_ids_arr];
            $query.= ' and goods_id in ('.$goods_ids.')';
        }
        $group = 'ng.goods_id';
        $condition['ng.state'] = 1;
        $condition['ng.website_id'] = $this->website_id;
        $query.= " and website_id =" .$this->website_id;
        
        //判断是否有店铺系统
        if (getAddons('shop',$this->website_id)){
            $condition['vs.shop_state'] = 1;
            $query.= " and shop_state = 1";
        }
        // 获取该用户的权限
        if ($this->uid) {
            $userService = new User();
            $userLevle = $userService->getUserLevelAndGroupLevel($this->uid);// code | <0 错误; 1系统会员; 2;分销商; 3会员
            if (!empty($userLevle)) {
                $level_flag = false;
                $sql1 = '';
                $sql2 = '(';
                $levelQuery = '(';
                // 会员权限
                if ($userLevle['user_level']) {
                    $level_flag = true;
                    $u_id = $userLevle['user_level'];
                    $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_u, ',' ), '," . $u_id . ",' ) OR ";
                    $sql2 .= "vgd.browse_auth_u IS NULL OR vgd.browse_auth_u = '' ";
                    $levelQuery .= " browse_auth_u like '%,".$u_id.",%'";
                }
                // 分销商权限
                if ($userLevle['distributor_level']) {
                    $level_flag = true;
                    $d_id = $userLevle['distributor_level'];
                    $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_d, ',' ), '," . $d_id . ",' ) OR ";
                    $sql2 .= " OR vgd.browse_auth_d IS NULL OR vgd.browse_auth_d = '' ";
                    if($levelQuery == '('){
                        $levelQuery .= " browse_auth_d like '%,".$d_id.",%'";
                    }else{
                        $levelQuery .= " or browse_auth_d like '%,".$d_id.",%'";
                    }
                }
                // 标签权限
                if ($userLevle['member_group']) {
                    $level_flag = true;
                    $g_ids = explode(',', $userLevle['member_group']);
                    foreach ($g_ids as $g_id) {
                        $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_s, ',' ), '," . $g_id . ",' ) OR ";
                        $sql2 .= " OR vgd.browse_auth_s IS NULL OR vgd.browse_auth_s = '' ";
                        if($levelQuery == '('){
                            $levelQuery .= " browse_auth_s like '%,".$g_id.",%'";
                        }else{
                            $levelQuery .= " or browse_auth_s like '%,".$g_id.",%'";
                        }
                    }
                } else {
                    $sql1 .= " ";
                }
                $sql2 .= " )";
                $levelQuery .= ') ';
                if ($level_flag){
                $condition[] = ['exp', $sql1 . $sql2];
                }
                if($levelQuery !== '()'){
                    $query .= ' and ' . $levelQuery;
                }
            }
        }
        //供应商关闭，不显示供应商商品
        if (getAddons('supplier',$this->website_id)){
            $condition[] = ['exp', ' if(ng.supplier_info >0, s.status =1, 1)'];//供应商必须为开启
            $query .= ' and (supplier_info = 0 or (supplier_info>0 and supplier_status=1))';
        }
        if(config('ecs.host')){
            $goodsSer = new GoodsService();
            $list = $goodsSer->getWapGoodsEs(999, $query);
        }else{
            $list = $goods_server->wapGoods(1, 999, $condition, 'ng.goods_id, ng.goods_name, ng.sales,ng.real_sales, pic_cover, ng.price as goods_price,ngs.market_price as market_price,ng.shop_id,ng.activity_pic', '', $group);
        }
        $goods_list = [];
        foreach ($list['data'] as $k => $v) {
            $v['goods_price'] = isset($v['goods_price']) ? $v['goods_price'] : $v['price'];
            $activity_num = 0;
            //是否已经登陆 获取用户
            $v['goods_types'] = 0;
            if ($this->uid) {
                //获取会员价
                $pprice = $v['goods_price'];
                $goods_server2 = new GoodsService();
                $goodsDiscountInfo = $goods_server2->getGoodsInfoOfIndependentDiscount($v['goods_id'], $v['goods_price'], ['price' => $v['goods_price'], 'shop_id' => $v['shop_id']]);
                if ($goodsDiscountInfo) {
                    $v['goods_price'] = $goodsDiscountInfo['member_price'];
                    $v['goods_types'] = 1;
                    $discount_service = getAddons('discount', $this->website_id) ? new Discount() : '';
                    $limit_discount_info = getAddons('discount', $this->website_id) ? $discount_service->getPromotionInfo($v['goods_id'], $this->instance_id, $this->website_id) : ['discount_num' => 10];
                    if ($limit_discount_info['status'] == 1 && $limit_discount_info['discount_type'] == 1) {
                        $v['goods_types'] = 2; //限时折扣
                        $v['goods_price'] = $pprice * $limit_discount_info['discount_num'] / 10;
                        if ($limit_discount_info['integer_type'] == 1) {
                            $v['goods_price'] = round($v['goods_price']);
                        }
                    } else if ($limit_discount_info['status'] == 1 && $limit_discount_info['discount_type'] == 2) {
                        $v['goods_types'] = 2; //限时折扣
                        $v['goods_price'] = $limit_discount_info['discount_num'];
                    }
                }
    
                //分佣
                $member = new VslMemberModel();
                $distribution = $member->getInfo(['uid' => $this->uid], 'isdistributor');
                if ($distribution['isdistributor'] == 2) {// 分销商
                    $distribution = new Distributor();
                    $info = $distribution->getGoodsCommission($this->website_id, $v['goods_id'], $this->uid, $v['goods_price']);
                    $commission = $info['commission'];
                    $point = $info['point'];
                }
                
            }
            if ($this->is_seckill) {
                //判断是否是秒杀商品
                $seckill_server = new SeckillServer();
                //判断如果是秒杀商品，则取最低秒杀价
                $goods_id = $v['goods_id'];
                $seckill_condition['nsg.goods_id'] = $goods_id;
                $is_seckill = $seckill_server->isSkuStartSeckill($seckill_condition);
                if ($is_seckill) {
                    $v['goods_price'] = $is_seckill['seckill_price'];
                    $v['goods_types'] = 3;
//                    $activity_num = $is_seckill['seckill_sales'];
                }
            }
            if ($this->is_presell) {
                $presell_server = new Presell();
                //判断如果是预售商品
                $goods_id = $v['goods_id'];
                $is_presell = $presell_server->getPresellInfoByGoodsIdIng($goods_id);
                if ($is_presell) {
                    $v['goods_price'] = $is_presell[0]['all_money'];
                    $v['goods_types'] = 4;
                    //获取该场次卖出去多少
//                    $activity_num = $is_presell[0]['presell_sales'];
                }
            }
            if ($this->is_bargain) {
                $bargain_server = new Bargain();
                //判断如果是预售商品
                $goods_id = $v['goods_id'];
                $condition_bargain['website_id'] = $this->website_id;
                $condition_bargain['goods_id'] = $goods_id;
                $condition_bargain['end_bargain_time'] = ['>', time()];//未结束的
                $condition_bargain['start_bargain_time'] = ['<', time()];//未结束的
                $is_bargain = $bargain_server->isBargainByGoodsId($condition_bargain, 0);
                if ($is_bargain) {
                    $v['goods_price'] = $is_bargain['start_money'];
                    $v['goods_types'] = 5;
                    //获取该场次卖出去多少
//                    $activity_num = $is_bargain['bargain_sales'];
                }
            }
            $goods_list[$k]['goods_id'] = $v['goods_id'];
            $album = new AlbumPictureModel();
            $pic_cover = $album->getInfo(['pic_id' => $v['activity_pic']], 'pic_cover')['pic_cover'] ? : '';
            $goods_list[$k]['activity_pic'] = $pic_cover;
            $goods_list[$k]['goods_types'] = $v['goods_types'];
            $goods_list[$k]['goods_name'] = $v['goods_name'];
            $goods_list[$k]['price'] = $v['goods_price'];
            $goods_list[$k]['market_price'] = $v['market_price'];
            $goods_list[$k]['sales'] = $v['sales'] + $v['real_sales'];
            $goods_list[$k]['logo'] = isset($v['pic_cover']) ? getApiSrc($v['pic_cover']) : ($v['album_picture'] ? getApiSrc($v['album_picture']['pic_cover']) : '');
            if ($commission) {
                $goods_list[$k]['commission'] = $commission;
            }
            if ($point) {
                $goods_list[$k]['point'] = $point;
            }
        }
        $new_goods_lists = [];
        $return_goods_lists = [];
        //排序
        if ($goods_ids_arr) {
            foreach ($goods_list as $k => $v){
                $new_goods_lists[$v['goods_id']] = $v;
            }
            foreach ($goods_ids_arr as $goods_id) {
                if ($new_goods_lists[$goods_id]){
                    $return_goods_lists[] = $new_goods_lists[$goods_id];
                }
            }
        } else {
            $return_goods_lists = $goods_list;
        }
        
        $res = json(['code' => 1, 'message' => '获取成功', 'data' => $return_goods_lists]);

        return $res;
    }

    //客服系统
    public function qlkefuInfo()
    {
        $shops_id = request()->post('shop_id', 0);
        $goods_id = request()->post('goods_id', 0);
        $goodsSer = new GoodsService();
        $goods_info = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,shop_id,goods_name,website_id');
        if ($goods_info) {
            $website_id = $goods_info['website_id'];
            $shop_id = $goods_info['shop_id'];
        } else {
            $website_id = $this->website_id;
            $shop_id = ($shops_id > 0) ? $shops_id : 0;
        }
        $is_qlkefu = getAddons('qlkefu', $website_id);
        $data = [];
        $data['is_qlkefu'] = $is_qlkefu;
        $data['domain'] = '';
        $data['domain2'] = '';
        if ($is_qlkefu) {
            $config = new Qlkefu();
            $qlkefu = $config->qlkefuConfig($website_id, $shop_id);
            if ($qlkefu['ql_domain'] && $qlkefu['seller_code'] && $qlkefu['is_use'] == 1) {
                $data['domain'] = $qlkefu['ql_domain'] . '/index/index/chatBoxJs/u/' . $qlkefu['seller_code'];
                $data['domain2'] = $qlkefu['ql_domain'] . '/index/index/chat/u/' . $qlkefu['seller_code'];
            }
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 获取商品图片base64
     * @param $goods_id int [商品id]
     * @param $is_list bool [是否获取商品全部主图 true:是 false:否]
     * @return string [image:base64]
     */
    public function getGoodsImgOfBase64()
    {
        $goods_id = request()->post('goods_id');
        $default = request()->post('is_list') ?: FALSE;
        if (empty($goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $goods_server = new GoodsService();
        $baseImg = $goods_server->getGoodsMasterImg($goods_id, $default);

        if ($baseImg) {
            return json([
                'code' => 1,
                'message' => '成功获取',
                'data' => $baseImg
            ]);
        } else {
            return json(['code' => 0, 'message' => '获取失败']);
        }
    }

    /**
     * 知识付费商品的目录列表
     */
    public function wapGetKnowledgePaymentList()
    {
        $goods_id = request()->post('goods_id');
        if (empty($goods_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $uid = $this->uid ? $this->uid : 0;

        $goodservice = new GoodsService();
        $retval = $goodservice->wapGetKnowledgePaymentList($goods_id, $uid);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $retval,
        ]);
    }

    /**
     * 知识付费商品试看或者观看/前往学习
     */
    public function seeKnowledgePayment()
    {
        $knowledge_payment_id = request()->post('knowledge_payment_id');
        $goods_id = request()->post('goods_id');
        $uid = $this->uid;
        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录', 'data' => []]);
        }

        $goodservice = new GoodsService();
        if ($knowledge_payment_id) {
            $data = $goodservice->seeKnowledgePayment($knowledge_payment_id, $uid);
        } elseif ($goods_id) {
            $data = $goodservice->goLearn($goods_id);
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data,
        ]);
    }

    /*
     * 会员中心->我的课程
     */
    public function myCourse()
    {
        $search_text = request()->post('search_text');
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $uid = $this->uid;
        $goodservice = new GoodsService();
        $data = $goodservice->myCourse($search_text, $page_index, $page_size, $uid);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data,
        ]);
    }

    /**
     * 新的获取商品基本信息以及对应的活动信息的接口，后面多个地方可通用
     */
    public function getGoodsBasicInfo()
    {
        $goods_id = request()->post('goods_id', '0');
        $store_id = request()->post('store_id', '0');
        $mic_goods = request()->post('mic_goods', 0);
        $seckill_id = request()->post('seckill_id', 0);
        $channel_id = request()->post('channel_id', 0);
        $bargain_id = request()->post('bargain_id', 0);
        $record_id = request()->post('record_id', 0);

        $goods_service = new GoodsService();
        $goods_info = $goods_service->getGoodsBasicInfo($goods_id, $store_id);
        if (empty($goods_info)) {
            return json(['code' => -1, 'message' => '商品不存在']);
        }

        $goods_detail = [];
        $goods_detail['price_type'] = $goods_info['price_type'];
        $limit_discount_info = $goods_info['limit_discount_info'];

        //开始处理活动信息
        if ($this->is_seckill && !$mic_goods) {
            //判断当前商品是否是秒杀商品，并且没有过期
            $seckill_server = new SeckillServer();
            if ($seckill_id) {
                $condition_is_seckill['s.seckill_id'] = $seckill_id;
                $condition_is_seckill['nsg.goods_id'] = $goods_info['goods_id'];
                $is_seckill = $seckill_server->isSeckillGoods($condition_is_seckill);
            } else {
                $condition_is_seckill['nsg.goods_id'] = $goods_info['goods_id'];
                $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill);
                $seckill_id = $is_seckill['seckill_id'];
            }
        }

        if ($this->is_bargain && !$mic_goods) {
            $bargain_server = new Bargain();
            if (!empty($bargain_id)) {
                //砍价是否过期
                $condition_bargain['website_id'] = $this->website_id;
                $condition_bargain['bargain_id'] = $bargain_id;
                $is_bargain = $bargain_server->isBargain($condition_bargain, 0);
            } else {
                $condition_bargain['website_id'] = $this->website_id;
                $condition_bargain['goods_id'] = $goods_info['goods_id'];
                $condition_bargain['end_bargain_time'] = ['>=', time()];//未结束的
                $is_bargain = $bargain_server->isBargainByGoodsId($condition_bargain, 0);
            }
        }

        $is_group = 0;
        $msg = '';
        if ($is_seckill && !$mic_goods) {
            $redis = connectRedis();
            //入商品队列
            $condition_sekcill_sku['seckill_id'] = $seckill_id;
            $condition_sekcill_sku['goods_id'] = $goods_info['goods_id'];
            $seckill_num_list = $seckill_server->getAllSeckillSkuList($condition_sekcill_sku);
            $seckill_num_arr = objToArr($seckill_num_list);
            $seckill_sales = 0;
            foreach ($seckill_num_arr as $k => $sku_item) {
                $sku_id = $sku_item['sku_id'];
                $store = $sku_item['remain_num'];
                //如果登录了，则需要看该用户购买了多少个秒杀商品
                $uid = $this->uid ?: 0;
                if ($uid) {
                    $goods_service = new GoodsService();
                    $website_id = $this->website_id;
                    $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $seckill_id);
                    $sku_buy_num[$sku_id] = $buy_num;
                }
                //redis队列key值
                $redis_goods_sku_store_key = 'store_' . $seckill_id . '_' . $goods_info['goods_id'] . '_' . $sku_id;//每个活动的库存都不一样
                $is_index = $redis->llen($redis_goods_sku_store_key);
                if (!$is_index) {
                    for ($num = 0; $num < $store; $num++) {
                        $redis->rpush($redis_goods_sku_store_key, 1);
                    }
                }
            }
            $condition_seckill['ns.website_id'] = $this->website_id;
            $condition_seckill['ns.seckill_id'] = $seckill_id;
            $condition_seckill['nsg.goods_id'] = $goods_info['goods_id'];
            $seckill_sku_price_arrs = $seckill_server->getGoodsSkuArr($condition_seckill, 'nsg.sku_id, nsg.seckill_price, nsg.remain_num, nsg.seckill_limit_buy, nsg.seckill_sales');
            $seckill_id_arr = array_column($seckill_sku_price_arrs, 'sku_id');
            $seckill_price_arr = array_column($seckill_sku_price_arrs, 'seckill_price');
            $seckill_stock_arr = array_column($seckill_sku_price_arrs, 'remain_num');
            $seckill_limit_buy_arr = array_column($seckill_sku_price_arrs, 'seckill_limit_buy');
            $seckill_sales = array_column($seckill_sku_price_arrs, 'seckill_sales');
            $seckill_sku_price_arr = array_combine($seckill_id_arr, $seckill_price_arr);
            $seckill_sku_stock_arr = array_combine($seckill_id_arr, $seckill_stock_arr);
            $seckill_sku_limit_buy_arr = array_combine($seckill_id_arr, $seckill_limit_buy_arr);
        }

        if (getAddons('groupshopping', $this->website_id, $goods_info['shop_id'])) {
            $groupGoods = new VslGroupGoodsModel();
            $group_server = new GroupShoppingServer();
            $is_group = $is_seckill ? 0 : $group_server->isGroupGoods($goods_info['goods_id']);//判断当前商品是否是拼团商品
        }

        $is_allow_buy = true;// 查询是否该用户有购买该商品权限by sgw
        $is_allow_browse = true;
        if ($this->uid) {
            $is_allow_buy = $goods_service->isAllowToBuyThisGoods($this->uid, $goods_info['goods_id']);
            $is_allow_browse = $goods_service->isAllowToBrowse($this->uid, $goods_info['goods_id']); // 是否有浏览权限
        }

        // 获取限时折扣
        $promotion_info['discount_num'] = 10;
        if ($this->is_discount && !$mic_goods) {
            $discount_service = new Discount();
            $promotion_info = $discount_service->getPromotionInfo($goods_info['goods_id'], $goods_info['shop_id'], $goods_info['website_id']);
        }

        //开始组装需要返回的基本信息
        $goods_detail['goods_id'] = $goods_info['goods_id'];
        $goods_detail['state'] = $goods_info['state'];
        $goods_detail['shop_id'] = $goods_info['shop_id'];
        $goods_detail['goods_name'] = $goods_info['goods_name'];
        $goods_detail['sales'] = $goods_info['sales'];
        $goods_detail['goods_images'] = $goods_info['goods_images'];
        $goods_detail['goods_image_yun'] = $goods_info['goods_image_yun'];
        $goods_detail['min_buy'] = $goods_info['goods_detail']['min_buy'];
        $goods_detail['max_buy'] = $goods_info['goods_detail']['max_buy'];
        $goods_detail['collects'] = $goods_info['goods_detail']['collects'];
        $goods_detail['goods_type'] = $goods_info['goods_detail']['goods_type'];
        $goods_detail['single_limit_buy'] = $goods_info['goods_detail']['single_limit_buy'];
        $goods_detail['video'] = $goods_info['video'];

        if ($goods_info['goods_detail']['shipping_fee_type'] == 0) {
            $goods_detail['shipping_fee'] = '包邮';
        } elseif ($goods_info['goods_detail']['shipping_fee_type'] == 1) {
            $goods_detail['shipping_fee'] = $goods_info['goods_detail']['shipping_fee'];
        } elseif ($goods_info['goods_detail']['shipping_fee_type'] == 2) {
            $user_location = get_city_by_ip();
            if ($user_location['status'] == 1) {
                // 定位成功，查询当前城市的运费
                $goods_express = new GoodsExpress();
                $address = new Address();
                $city = $address->getCityId($user_location["city"]);
                $district = $address->getCityFirstDistrict($city['city_id']);
                $express = $goods_express->getGoodsExpressTemplate([['goods_id' => $goods_info['goods_id'], 'count' => 1]], $district)['totalFee'];
                $goods_detail['shipping_fee'] = $express;
            }
        }

        //处理需要返回的活动信息
        //预售活动信息
        $presell_list = [];
        if ($this->is_presell && !$mic_goods) {
            $presell = new PresellService();
            $presell_info = $presell->getPresellInfoByGoodsId($goods_info['goods_id']);
            if (!empty($presell_info)) {
                foreach ($presell_info as $p => $pv) {
                    //获取已购买的数量，减去得到剩余数量
                    $have_buy = $presell->getPresellSkuNum($presell_info[0]['presell_id'], $pv['sku_id']);
                    $temp_sku['over_num'] = $pv['presell_num'] - $have_buy;
                    $presell_list['presellnum'] += $temp_sku['over_num']; //预售数量
                    $presell_list['max_buy'] += $pv['max_buy']; //预售数量
                }
                //查出当前用户购买了多少个该商品
                $presell_num = $goods_service->getActivityOrderSkuNum($this->uid ?: 0, $presell_info[0]['sku_id'], $this->website_id, '4', $presell_info[0]['id']);
                $presell_list['name'] = $presell_info[0]['name'];
                $presell_list['firstmoney'] = $presell_info[0]['firstmoney'];
                $presell_list['allmoney'] = $presell_info[0]['allmoney'];
                $presell_list['start_time'] = $presell_info[0]['start_time'];
                $presell_list['end_time'] = $presell_info[0]['end_time'];
                $presell_list['pay_start_time'] = $presell_info[0]['pay_start_time'];
                $presell_list['pay_end_time'] = $presell_info[0]['pay_end_time'];
                $presell_list['send_goods_time'] = $presell_info[0]['send_goods_time'];
                $presell_list['maxbuy'] = ($presell_list['max_buy'] - $presell_num >= 0) ? $presell_list['max_buy'] - $presell_num : 0;   //限购数量
                $presell_list['vrnum'] = $presell_info[0]['vrnum'];      //虚拟限购数量
                $presell_list['presell_id'] = $presell_info[0]['id'];      //预售活动ID
                $count_people = $presell->getPresellBuyNum($presell_list['presell_id']); //总购买人数
                $user_buy_count = $presell->getUserCount($presell_list['presell_id']);    //当前用户购买
                $presell_list['have_buy_now'] = $user_buy_count;
                $presell_list['presell_count_people'] = $count_people['buy_num'] ? $count_people['buy_num'] : 0;
                $goods_detail['sales'] += $presell_info[0]['presell_sales'];
                //判断状态是进行中还是
                if (time() > $presell_info[0]['start_time'] && time() < $presell_info[0]['end_time']) {
                    $presell_list['state'] = 1; //正在进行
                } else if (time() < $presell_list['start_time']) {
                    $presell_list['state'] = 2;//没开始
                } else {
                    $presell_list['state'] = 3;//结束了
                }
                $is_presell = 1;
            } else {
                $presell_list['name'] = $presell_info[0]['name'];
                $presell_list['firstmoney'] = '';
                $presell_list['allmoney'] = '';
                $presell_list['start_time'] = '';
                $presell_list['end_time'] = '';
                $presell_list['pay_start_time'] = '';
                $presell_list['pay_end_time'] = '';
                $presell_list['send_goods_time'] = '';
                $presell_list['presellnum'] = '';
                $presell_list['maxbuy'] = '';
                $presell_list['vrnum'] = '';
                $presell_list['presell_id'] = '';
                $presell_list['presell_count_people'] = '';
                $is_presell = 0;
            }
        } else {
            $is_presell = 0;
        }

        //秒杀活动
        $seckill_list = [];
        if ($this->is_seckill && !$mic_goods) {
            //获取即将进行的最近一场的seckill_id。 ['sg.goods_id'=>$goods_id,'s.seckill_now_time'=>['>=',time()-24*3600]]
            $seckill_id_condition['sg.goods_id'] = $goods_info['goods_id'];
            $seckill_id_condition['s.seckill_now_time'] = ['>=', time() - 24 * 3600];
            $seckill_id = $seckill_server->getSeckillId($seckill_id_condition);
            if ($seckill_id) {
                $condition_seckill['ns.website_id'] = $this->website_id;
                $condition_seckill['ns.seckill_id'] = $seckill_id;
                $condition_seckill['nsg.goods_id'] = $goods_info['goods_id'];
                $seckill_goods_list = $seckill_server->getWapSeckillGoodsList($condition_seckill, 'ns.seckill_now_time,nsg.seckill_num,nsg.remain_num');
                $seckill_goods_arr = objToArr($seckill_goods_list);
                $seckill_num = 0;
                $remain_num = 0;
                foreach ($seckill_goods_arr as $k => $seck_info) {
                    $seckill_num = $seckill_num + $seck_info['seckill_num'];
                    $remain_num = $remain_num + $seck_info['remain_num'];
                }
                $seckill_list['seckill_id'] = $seckill_id;
                $seckill_list['seckill_num'] = $seckill_num;
                $seckill_list['remain_num'] = $remain_num;
                $seckill_now_time = $seckill_goods_arr[0]['seckill_now_time'];
                $now_time = time();
                //是今天的判断是否正在进行或者未开始，结束
                if ($now_time >= $seckill_now_time && $now_time <= $seckill_now_time + 24 * 3600) {//正在进行
                    $seckill_list['seckill_day'] = '';
                    $seckill_list['seckill_status'] = 'going';
                    $seckill_list['seckill_time'] = '';
                    $seckill_list['start_time'] = '';
                    $seckill_list['end_time'] = $seckill_now_time + 24 * 3600;
                } elseif ($now_time > $seckill_now_time + 24 * 3600) {//已结束
                    $seckill_list['seckill_day'] = 'ended';
                    $seckill_list['seckill_day'] = date('m-d', $seckill_now_time);
                    $seckill_list['seckill_time'] = date('H', $seckill_now_time);
                    $seckill_list['start_time'] = '';
                } elseif ($now_time < $seckill_now_time) {//未开始
                    $today_date = date('Y-m-d', time());
                    $tomorrow_date = date('Y-m-d', strtotime('+1 day'));
                    $seckill_now_date = date('Y-m-d', $seckill_now_time);

                    if ($seckill_now_date == $today_date) {//今天还未开始
                        $seckill_list['seckill_day'] = 'today';
                        $seckill_list['seckill_status'] = 'unstart';
                        $seckill_list['seckill_time'] = date('H:i', $seckill_now_time);
                        $seckill_list['start_time'] = $seckill_now_time;
                    } elseif ($seckill_now_date == $tomorrow_date) {//明天还未开始
                        $seckill_list['seckill_day'] = 'tomorrow';
                        $seckill_list['seckill_status'] = 'unstart';
                        $seckill_list['seckill_time'] = date('H:i', $seckill_now_time);
                        $seckill_list['start_time'] = $seckill_now_time;
                    } else {//后天及以后的天数
                        $seckill_list['seckill_day'] = date('m-d', $seckill_now_time);
                        $seckill_list['seckill_status'] = 'unstart';
                        $seckill_list['seckill_time'] = date('H:i', $seckill_now_time);
                        $seckill_list['start_time'] = $seckill_now_time;
                    }
                }
            }
        }

        $bargain_list = $is_bargain;

        if (empty($presell_list)) {
            $presell_list = (object)[];
        }
        if (empty($seckill_list)) {
            $seckill_list = (object)[];
        }
        if (empty($bargain_list)) {
            $bargain_list = (object)[];
        } else {
            $goods_detail['sales'] += $bargain_list['bargain_sales'];
        }

        //拼团详情
        $group_list = [];
        if ($is_group && !$mic_goods) {
            $group_list['group_id'] = $is_group;
            $group_list['group_name'] = $group_server->getGroupName($is_group);
            $group_list['record_id'] = $record_id;
            $group_list['group_record_list'] = $group_server->goodsGroupRecordListForWap($goods_info['goods_id']);
            $group_list['group_record_count'] = $group_server->goodsGroupRecordCount($goods_info['goods_id']);
            $regimentCount = $group_server->goodsRegimentCount($goods_info['goods_id']);
            $group_list['regiment_count'] = !empty($regimentCount) ? $regimentCount['now_num'] * $regimentCount['group_num'] : 0;
        }
        if (empty($group_list)) {
            $group_list = (object)[];
        }

        if ($msg) {
            $code = 0;
            $msg = $msg;
        } else {
            $code = 1;
            $msg = '成功获取';
        }

        //处理sku信息
        $spec_obj = [];
        $goods_detail['sku']['tree'] = [];
        foreach ($goods_info['spec_list'] as $i => $spec_info) {
            $temp_spec = [];
            foreach ($spec_info['value'] as $s => $spec_value) {
                $temp_spec['k'] = $spec_info['spec_name'];
                $temp_spec['k_id'] = $spec_info['spec_id'];
                $temp_spec['v'][$s]['id'] = $spec_value['spec_value_id'];
                $temp_spec['v'][$s]['name'] = $spec_value['spec_value_name'];
                $temp_spec['k_s'] = 's' . $i;
                $spec_obj[$spec_info['spec_id']] = $temp_spec['k_s'];
                $goods_detail['sku']['tree'][$spec_info['spec_id']] = $temp_spec;
            }
        }
        //接口需要tree是数组，不是对象，去除tree以spec_id为key的值
        $goods_detail['sku']['tree'] = array_values($goods_detail['sku']['tree']);
        $goods_info['goods_detail']['sku']['tree'] = $goods_detail['sku']['tree'];

        //如果当前用户登录了，判断此用户有没有上级渠道商，如果有，库存显示平台库存+直属上级渠道商的库存
        if(getAddons('channel',$this->website_id,0)) {
            if(empty($channel_id) && empty($store_id)) {
                if($this->uid) {
                    $member_model = new VslMemberModel();
                    $referee_id = $member_model->Query(['uid'=>$this->uid,'website_id'=>$this->website_id],'referee_id')[0];
                    if($referee_id) {//如果有上级，判断是不是渠道商
                        $channel_model = new VslChannelModel();
                        $is_channel = $channel_model->Query(['uid'=>$referee_id,'website_id'=>$this->website_id],'channel_id')[0];
                        if($is_channel) {//如果上级是渠道商，判断上级渠道商有没有采购过这个商品
                            $channel_goods_model = new VslChannelGoodsModel();
                            $channel_goods_id = $channel_goods_model->Query(['goods_id'=>$goods_id,'channel_id'=>$is_channel,'website_id'=>$this->website_id],'goods_id')[0];
                            if($channel_goods_id) {
                                $channel_id = $is_channel;
                            }
                        }
                    }
                }
            }
        }

        $total_presell_num = 0;
        $temp_sku = [];
        $goods_detail['sku']['list'] = [];
        foreach ($goods_info['sku_list'] as $k => $sku) {
            $temp_sku['id'] = $sku['sku_id'];
            $temp_sku['sku_name'] = $sku['sku_name'];
            $temp_sku['price'] = $sku['price'];
            $temp_sku['min_buy'] = 1;
            $temp_sku['group_price'] = '';
            $temp_sku['group_limit_buy'] = '';
            if ($is_seckill && !$mic_goods) {
                $temp_sku['price'] = $seckill_sku_price_arr[$sku['sku_id']];
                $temp_sku['max_buy'] = $seckill_sku_limit_buy_arr[$sku['sku_id']] - $sku_buy_num[$sku['sku_id']] < 0 ? 0 : $seckill_sku_limit_buy_arr[$sku['sku_id']] - $sku_buy_num[$sku['sku_id']];
                //对上一行的处理
                $temp_sku['max_buy'] = $temp_sku['max_buy'] > $seckill_sku_stock_arr[$sku['sku_id']] ? $seckill_sku_stock_arr[$sku['sku_id']] : $temp_sku['max_buy'];
                $sku['price'] = $temp_sku['price'];
                $goods_detail['sales'] += array_sum($seckill_sales);
            } elseif ($is_group && !$mic_goods) {
                $groupSku = $groupGoods->getInfo(['sku_id' => $sku['sku_id'], 'goods_id' => $goods_info['goods_id'], 'group_id' => $is_group], 'group_price,group_limit_buy');
                $buyed = $goods_service->getActivityOrderSkuForGroup($this->uid ?: 0, $sku['sku_id'], $this->website_id, $is_group);
                $temp_sku['group_price'] = $groupSku['group_price'] ?: '';
                $temp_sku['group_limit_buy'] = $temp_sku['max_buy'] = $groupSku['group_limit_buy'] - $buyed >= 0 ? $groupSku['group_limit_buy'] - $buyed : 0;
            } elseif ($is_presell == 1 && !$mic_goods) {
                $presell = new PresellService();
                //获取当前商品的预售活动
                $presell_info = $presell->getPresellInfoByGoodsId($goods_info['goods_id']);
                $where['presell_id'] = $presell_info[0]['presell_id'];
                $where['sku_id'] = $sku['sku_id'];
                $where['goods_id'] = $goods_info['goods_id'];
                $presell_sku = $presell->getPresellSkuinfo($where);
                //查出当前用户购买了多少个该商品
                $presell_num = $goods_service->getActivityOrderSkuNum($this->uid ?: 0, $sku['sku_id'], $this->website_id, '4', $where['presell_id']);
                $temp_sku['max_buy'] = (($presell_sku['max_buy'] - $presell_num) >= 0) ? $presell_sku['max_buy'] - $presell_num : 0;
                $temp_sku['first_money'] = $presell_sku['first_money'];
                $temp_sku['all_money'] = $presell_sku['all_money'];
                $temp_sku['presell_num'] = $presell_sku['presell_num'];
                $temp_sku['vr_num'] = $presell_sku['vr_num'];
                //获取已购买的数量，减去得到剩余数量
                $have_buy = $presell->getPresellSkuNum($presell_info[0]['presell_id'], $sku['sku_id']);
                $temp_sku['over_num'] = $presell_sku['presell_num'] - $have_buy;
                $total_presell_num += $temp_sku['over_num'];
            }

            $temp_sku['market_price'] = $sku['market_price'];
            if ($is_seckill && !$mic_goods) {
                $temp_sku['stock_num'] = $seckill_sku_stock_arr[$sku['sku_id']];
            } elseif ($is_bargain && !$mic_goods) {//砍价的商品库存
                $temp_sku['stock_num'] = $is_bargain['bargain_stock'];
            } elseif ($channel_id && !$mic_goods) {
                $channel_sku_mdl = new VslChannelGoodsSkuModel();
                $channel_cond['channel_id'] = $channel_id;
                $channel_cond['sku_id'] = $sku['sku_id'];
                $channel_cond['website_id'] = $this->website_id;
                $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                $temp_sku['stock_num'] = $channel_stock + $sku['stock'] ?: 0;
                $temp_sku['max_buy'] = $channel_stock + $sku['stock'] ?: 0;
            } elseif ($is_presell) {
                $temp_sku['stock_num'] = $temp_sku['over_num'] ?: 0;
            } else {
                $temp_sku['stock_num'] = $sku['stock'];
            }
            $temp_sku['attr_value_items'] = $sku['attr_value_items'];

            $sku_temp_spec_array = explode(';', $sku['attr_value_items']);
            $temp_sku['s'] = [];
            foreach ($sku_temp_spec_array as $spec_id => $spec_combination) {
                $explode_spec = explode(':', $spec_combination);
                $spec_id = $explode_spec[0];
                $spec_value_id = $explode_spec[1];

                // ios wants string
                if ($spec_value_id) {
                    $temp_sku['s'][] = (string)$spec_value_id;
                    $temp_sku[$spec_obj[$spec_id] ?: 's0'] = (int)$spec_value_id;
                }
            }

            $goods_detail['min_price'] = reset($goods_info['sku_list'])['sku_id'] == $sku['sku_id']
                ? $sku['price'] : ($goods_detail['min_price'] <= $sku['price'] ? $goods_detail['min_price'] : $sku['price']);
            if ($promotion_info['discount_type'] == 2) {
                $goods_detail['min_price'] = $promotion_info['discount_num'];
            }
            $goods_detail['min_market_price'] = reset($goods_info['sku_list'])['sku_id'] == $sku['sku_id']
                ? $sku['market_price'] : ($goods_detail['min_market_price'] <= $sku['market_price'] ? $goods_detail['min_market_price'] : $sku['market_price']);
            $goods_detail['max_price'] = reset($goods_info['sku_list'])['sku_id'] == $sku['sku_id']
                ? $sku['price'] : ($goods_detail['max_price'] >= $sku['price'] ? $goods_detail['max_price'] : $sku['price']);
            $goods_detail['max_market_price'] = reset($goods_info['sku_list'])['sku_id'] == $sku['sku_id']
                ? $sku['market_price'] : ($goods_detail['max_market_price'] >= $sku['market_price'] ? $goods_detail['max_market_price'] : $sku['market_price']);

            $goods_detail['sku']['list'][] = $temp_sku;
        }
        $goods_info['goods_detail']['sku']['list'] = $goods_detail['sku']['list'];

        $goods_detail['is_collection'] = false;
        if ($this->uid) {
            $goods_detail['is_collection'] = $this->user->getIsMemberFavorites($this->uid, $goods_id, 'goods') ? true : false;
        }

        $goods_info = [
            'goods_detail' => $goods_detail,
            'seckill_list' => $seckill_list,
            'bargain_list' => $bargain_list,
            'group_list' => $group_list,
            'presell_list' => $presell_list,
            'is_allow_buy' => $is_allow_buy,
            'is_allow_browse' => $is_allow_browse,
            'limit_list' => $limit_discount_info,
        ];

        return json([
            'code' => $code,
            'message' => $msg,
            'data' => $goods_info
        ]);
    }

    /**
     * 购物车编辑规格或数量（新版购物车）
     */
    public function cartEditSkuOrNum()
    {
        $cart_id = request()->post('cart_id', '0');
        $num = request()->post('num',''); //编辑数量时才传
        $shop_id = request()->post('shop_id');
        $store_id = request()->post('store_id', '0');
        $sku_list = request()->post('sku_list/a', ''); //编辑规格的时候才传

        if (empty($cart_id)) {
            $data['code'] = -1;
            $data['data'] = [];
            $data['message'] = "请选择购物车ID";
        }
        $msg = '';
        $goods_service = new GoodsService();
        $cart_list = $goods_service->newGetCartList($cart_id, $num, $store_id, $sku_list, $shop_id, $msg);

        if($this->is_store) {
            $storeServer = new storeServer();
            //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
            $stock_type = $storeServer->getStoreSet($shop_id)['stock_type'] ? $storeServer->getStoreSet($shop_id)['stock_type'] : 1;
        }else{
            $stock_type = 2;
        }

        //判断当前商品是否是秒杀商品，若是，则不能超过最大限制购买量
        $sku_id = $cart_list['sku_id'];
        $seckill_id = (int)$cart_list['seckill_id'];
        if (!$seckill_id) {
            if ($this->is_seckill) {
                $sec_service = new SeckillServer();
                $condition_seckill['nsg.sku_id'] = $sku_id;
                $seckill_info = $sec_service->isSkuStartSeckill($condition_seckill);
                if ($seckill_info) {
                    $seckill_id = $seckill_info['seckill_id'];
                }
            }
        }
        if ($seckill_id !== 0 && $this->is_seckill) {
            $sec_service = new SeckillServer();
            //查询该商品的虚拟购买量，条件为 未过期
            $condition_seckill['s.website_id'] = $this->website_id;
            $condition_seckill['s.shop_id'] = $this->instance_id;
            $condition_seckill['s.seckill_id'] = $seckill_id;
            $condition_seckill['nsg.sku_id'] = $sku_id;
            $is_seckill = $sec_service->isSeckillGoods($condition_seckill);
            if ($is_seckill) {
                $sku_list = $sec_service->getSeckillSkuInfo(['seckill_id' => $seckill_id, 'sku_id' => $sku_id]);
                $seck_limit_buy = $sku_list->seckill_limit_buy;
                if ($num) {
                    if ($num > $seck_limit_buy) {
                        return json(['code' => -1, 'message' => '秒杀活动商品最大购买量不能超过' . $seck_limit_buy . '件']);
                    }
                } else {
                    if ($sku_list['num'] > $seck_limit_buy) {
                        return json(['code' => -1, 'message' => '秒杀活动商品最大购买量不能超过' . $seck_limit_buy . '件']);
                    }
                }

            }
        }

        //以店铺为维度，获取购物车中属于此店铺的所有商品，以及店铺的折扣，满减信息
        $cart_model = new VslCartModel();
        $cart_goods_list = $cart_model->getQuery(['shop_id' => $shop_id, 'website_id' => $this->website_id, 'buyer_id' => $this->uid], '*', 'cart_id ASC');
        if ($cart_goods_list) {
            foreach ($cart_goods_list as $k => $v) {
                $goods_info = $goods_service->getGoodsDetailById($v['goods_id']);
                $v['promotion_type'] = $goods_info['promotion_type'];
                $v['max_buy'] = $goods_info['max_buy'];
                $v['least_buy'] = $goods_info['least_buy'];

                if ($store_id && $stock_type == 1) {
                    $store_goods_sku_model = new VslStoreGoodsSkuModel();
                    $sku_info = $store_goods_sku_model->getInfo(['shop_id' => $shop_id, 'sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'store_id' => $store_id], '*');
                } else {
                    $goods_sku_model = new VslGoodsSkuModel();
                    $sku_info = $goods_sku_model->getInfo(['sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id']], '*');
                }
                if((getAddons('presell', $this->website_id, $this->instance_id))){
                        //判断当前商品是否在预售活动中
                    $presell = new Presell();
                    $is_presell = $presell->getIsInPresell($v['goods_id']);
                }
                if ($this->uid) {
                    //查询商品是否开启PLUS会员价
                    $is_member_discount = 1;
                    $goodsPower = $goods_service->getGoodsPowerDiscount($v['goods_id']);
                    $goodsPower = json_decode($goodsPower['value'],true);
                    $plus_member = $goodsPower['plus_member'] ? : 0;
                    if($plus_member == 1) {
                        if(getAddons('membercard',$this->website_id)) {
                            $membercard = new MembercardSer();
                            $membercard_data = $membercard->checkMembercardStatus($this->uid);
                            if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                                //有会员折扣权益
                                $member_price = $sku_info['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                                $is_member_discount = 0;
                            }
                        }
                    }

                    if($is_member_discount) {
                        // 查看用户会员价
                        $goodsDiscountInfo = $goods_service->getGoodsInfoOfIndependentDiscount($v['goods_id'], $sku_info['price'], ['price' => $goods_info['price'], 'shop_id' => $goods_info['shop_id']]);                       //计算会员折扣价
                        if ($goodsDiscountInfo) {
                            $member_price = $goodsDiscountInfo['member_price'];
                        }
                    }

                    if (getAddons('seckill', $this->website_id, $this->instance_id)) {
                        //判断是否有秒杀的商品并且是否过期，若有直接取秒杀价
                        $sec_server = new SeckillServer();
                        if (!empty($v['seckill_id'])) {
                            $condition_seckill['s.seckill_id'] = $v['seckill_id'];
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        } else {
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSkuStartSeckill($condition_seckill);
                            if ($is_seckill) {
                                $v['seckill_id'] = $is_seckill['seckill_id'];
                                $seckill_data['cart_id'] = $v["cart_id"];
                                $seckill_data['seckill_id'] = $is_seckill['seckill_id'];
                                $cart_model->data($seckill_data, true)->isupdate(true)->save();
                            }
                        }
                    }

                    if ($is_seckill) {
                        //取该商品该用户购买了多少
                        $sku_id = $v['sku_id'];
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $v['seckill_id']);
                        $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $v->seckill_id, 'sku_id' => $sku_id]);
                        $goods_info['max_buy'] = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? $sec_sku_info_list->seckill_limit_buy : $sec_sku_info_list->seckill_limit_buy - $buy_num;
                        $goods_info['max_buy'] = $goods_info['max_buy'] > $sku_info['stock'] ? $sku_info['stock'] : $goods_info['max_buy'];
                        $goods_info['least_buy'] = $goods_info['least_buy']?$goods_info['least_buy']:0;
                        //如果最大购买数小于购物车的数量并且不等于0
                        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $v['num']) {
                            // 更新购物车
                            $cart_goods_list[$k]['num'] = $goods_info['max_buy'];
                            $goods_service->cartAdjustNum($v['cart_id'], $goods_info['max_buy']);
                        }
                        if ($goods_info['max_buy'] == 0) {
                            unset($cart_goods_list[$k]);
                            $goods_service->cartDelete($v['cart_id']);
                            $msg .= $v['goods_name'] . "商品已达上限" . PHP_EOL;
                            continue;
                        }
                        $sku_info['stock'] = $goods_info['max_buy'];
                        $price = (float)$sec_sku_info_list->seckill_price;
                    }elseif($is_presell){
                        $can_buy = $presell->getMeCanBuy($is_presell['presell_id'], $v['sku_id']);
                        $sku_info['stock'] = $can_buy;
                        $goods_info['max_buy'] = $can_buy;
                        $goods_info['least_buy'] = $is_presell['least_buy']?$is_presell['least_buy']:0;
                        $price = $is_presell['all_money'];
                    } else {
                        $price = $member_price;
                        $v['promotion_type'] = 0;
                    }

                    //加图片
                    $picture = new AlbumPictureModel();
                    $cart_goods_list[$k]["picture_info"] = $picture->Query(['pic_id' => $v['goods_picture']], 'pic_cover')[0];

                    //快递配送,判断此用户有没有上级渠道商，如果有，库存显示平台库存+直属上级渠道商的库存
                    $channel_stock = 0;
                    if(getAddons('channel',$this->website_id,0)) {
                        if(empty($store_id)) {
                            $member_model = new VslMemberModel();
                            $referee_id = $member_model->Query(['uid'=>$this->uid,'website_id'=>$this->website_id],'referee_id')[0];
                            if($referee_id) {//如果有上级，判断是不是渠道商
                                $channel_model = new VslChannelModel();
                                $is_channel = $channel_model->Query(['uid'=>$referee_id,'website_id'=>$this->website_id],'channel_id')[0];
                                if($is_channel) {//如果上级是渠道商，判断上级渠道商有没有采购过这个商品
                                    $channel_sku_mdl = new VslChannelGoodsSkuModel();
                                    $channel_cond['channel_id'] = $is_channel;
                                    $channel_cond['sku_id'] = $v['sku_id'];
                                    $channel_cond['website_id'] = $this->website_id;
                                    $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                                }
                            }
                        }
                    }

                    $cart_goods_list[$k]["price"] = $price;
                    $cart_goods_list[$k]["goods_name"] = $v["goods_name"];
                    $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
                    $cart_goods_list[$k]['stock'] = $sku_info['stock'] + $channel_stock;
                    $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                    $cart_goods_list[$k]['least_buy'] = $goods_info['least_buy'];
                    $cart_goods_list[$k]['state'] = $goods_info['state'];
                    //购物车暂时不考虑主播商品
                    $cart_goods_list[$k]['anchor_id'] = 0;

                    if ($store_id) {
                        $cart_goods_list[$k]['store_id'] = $store_id;
                    }
                }
            }

            //重新遍历，获取店铺的折扣，满减信息
            $list = [];
            foreach ($cart_goods_list as $i => $v) {
                $cart_goods_list[$i]['bargain_id'] = 0;
                if ($this->is_seckill) {
                    //查出商品是否在秒杀活动
                    $goods_id = $cart_goods_list[$i]["goods_id"];
                    $condition_is_seckill['nsg.goods_id'] = $goods_id;
                    $seckill_server = new SeckillServer();
                    $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill);
                }

                //折扣信息
                $list['discount_info'] = (object)array();
                //限时折扣
                if ($this->is_discount && !empty($discount_info) && $cart_goods_list[$i]['promotion_type'] == 5) {
                    $discount_server = new Discount();
                    $discount_info = $discount_server->getBestDiscount($cart_goods_list[$i]["shop_id"]);
                    $list['discount_info'] = !empty($discount_info) ? $discount_info : (object)array();
                    $promotion_discount = $discount_server->getPromotionInfo($cart_goods_list[$i]['goods_id'], $cart_goods_list[$i]['shop_id'], $cart_goods_list[$i]['website_id']);
                    if ($promotion_discount) {
                        if ($store_id && $stock_type == 1) {
                            $store_goods_sku_model = new VslStoreGoodsSkuModel();
                            $sku_info = $store_goods_sku_model->getInfo(['shop_id' => $shop_id, 'sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'store_id' => $store_id], 'price');
                        } else {
                            $goods_sku_model = new VslGoodsSkuModel();
                            $sku_info = $goods_sku_model->getInfo(['sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'website_id' => $this->website_id], 'price');
                        }

                        if ($promotion_discount['integer_type'] == 1) {
                            $cart_goods_list[$i]['price'] = round($sku_info['price'] * $promotion_discount['discount_num'] / 10);
                        } else {
                            $cart_goods_list[$i]['price'] = round($sku_info['price'] * $promotion_discount['discount_num'] / 10, 2);
                        }
                        if ($promotion_discount['discount_type'] == 2) {
                            $cart_goods_list[$i]['price'] = $promotion_discount['discount_num'];
                        }
                    }
                }
                if ($is_seckill) {
                    $list['mansong_info'] = (object)array();
                    $list['discount_info'] = (object)array();
                }
            }
            $list['goods_list'] = $cart_goods_list;
            $list['shop_name'] = $cart_goods_list[0]["shop_name"];
            $list['shop_id'] = $cart_goods_list[0]["shop_id"];

            if ($store_id && $stock_type == 1) {
                $storeServer = new storeServer();
                $payment_info = $storeServer->paymentData($cart_goods_list);
            } else {
                $payment_info = $goods_service->paymentData($cart_goods_list);
            }
            $list['mansong_info'] = $payment_info[0]['full_cut'];
            $list = $this->object2array($list);
            if (empty($list['mansong_info'])) {
                $list['mansong_info'] = (object)array();
            }

            //重新遍历，如果活动不一致则删除活动
            if (!empty($list)) {
                foreach ($list['goods_list'] as $ka => $kb) {
                    if (!empty($list->discount_info)) {
                        if ($list['discount_info']['range'] == 2) {
                            $is_active_goods = $discount_server->checkIsDiscountProduct($kb['goods_id'], $kb['shop_id'], $list['discount_info']['discount_id']);
                            if (empty($is_active_goods)) {
                                $list['discount_info'] = (object)array();
                            }
                        }
                    } else {
                        $list['discount_info'] = (object)array();
                    }
                }
            }
        }

        if ($msg) {
            $data['code'] = -1;
            $data['data'] = $list;
            $data['message'] = $msg;
        } else {
            $data['code'] = 1;
            $data['data'] = $list;
            $data['message'] = "修改成功";
        }

        return json($data);
    }

    /**
     * 购物车/商品详情获取门店列表
     */
    public function getStoreList()
    {
        $lng = request()->post("lng", '');
        $lat = request()->post("lat", '');
        $shop_id = request()->post("shop_id"); //购物车获取门店列表时才传
        $sku_list = request()->post("sku_list/a", ''); //购物车获取门店列表时才传
        $goods_id = request()->post("goods_id",'0'); //商品详情获取门店列表时才传

        $place = [
            'lng' => $lng,
            'lat' => $lat
        ];

        $storeModel = new VslStoreModel();
        $storeGoodsModel = new VslStoreGoodsModel();
        $storeGoodsSkuModel = new VslStoreGoodsSkuModel();
        $goodsSkuModel = new VslGoodsSkuModel();
        $address_service = new Address();
        $storeServer = new storeServer();
        $goodsSer = new GoodsService();
        
        if($this->is_store) {
            //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
            $stock_type = $storeServer->getStoreSet($shop_id)['stock_type'] ? $storeServer->getStoreSet($shop_id)['stock_type'] : 1;
        }else{
            $stock_type = 2;
        }

        if($sku_list) {
            //购物车获取门店列表
            if (getAddons('store', $this->website_id, $this->instance_id)) {
                //判断这个店铺有没有o2o应用
                $store_list = '';
                //处理不同商品对应不同核销门店的情况
                if(count($sku_list) > 1) {
                    $store_str = '';
                    foreach ($sku_list as $k => $v) {
                        $goods_id = $goodsSkuModel->Query(['sku_id' => $v], 'goods_id')[0];
                        $have_store = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,shop_id,goods_name,store_list')['store_list'];
                        if ($have_store){
                            $store_str .= $have_store . ',';
                        }
                    }
                    if ($store_str) {
                        $store_str = trim($store_str, ',');
                        $store_str = explode(',', $store_str);
                        if (count($store_str) == count(array_unique($store_str))) {
                            $store_list = [];
                        } else {
                            //取出共同的核销门店
                            $same_value = array_count_values($store_str);
                            foreach ($same_value as $k => $v) {
                                if ($v > 1) {
                                    $same_value_arr[] = $k;
                                }
                            }
                        }
                    }
                }

                $have_stock_store_list = '';
                $no_stock_store_list = '';
                foreach ($sku_list as $k => $v) {
                    $goods_id = $goodsSkuModel->Query(['sku_id' => $v], 'goods_id')[0];
                    //判断这个商品有没有核销门店
                    if($same_value_arr) {
                        $goods_store_list = implode(',', $same_value_arr);
                    }else{
                        $goods_store_list = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,shop_id,goods_name,store_list')['store_list'];
                    }
                    if (empty($goods_store_list)) {
                        $store_list = [];
                        $have_stock_store_list = '';
                        $no_stock_store_list = '';
                        break;
                    } else {
                        $goods_store_list = explode(',', $goods_store_list);
                        if($stock_type == 1) {
                            foreach ($goods_store_list as $k1 => $v1) {
                                //没有库存或者此门店没有上架此商品都不能选择此门店
                                $stock_condition = [
                                    'goods_id' => $goods_id,
                                    'sku_id' => $v,
                                    'store_id' => $v1,
                                    'website_id' => $this->website_id
                                ];
                                $state_condition = [
                                    'goods_id' => $goods_id,
                                    'store_id' => $v1,
                                    'website_id' => $this->website_id
                                ];
                                $stock = $storeGoodsSkuModel->getInfo($stock_condition, 'stock');
                                $state = $storeGoodsModel->getInfo($state_condition, 'state');
                                if ($stock['stock'] <= 0 || $state['state'] == 0) {
                                    $no_stock_store_list .= $v1 . ',';
                                    unset($v1);
                                }
                                if ($v1) {
                                    $have_stock_store_list .= $v1 . ',';
                                }
                            }
                        }elseif ($stock_type == 2) {
                            $have_stock_store_list .= implode(',',$goods_store_list) . ',';
                            $no_stock_store_list = '';
                        }
                    }
                }
                if (empty($no_stock_store_list) && empty($have_stock_store_list)) {
                    $store_list = [];
                } else {
                    $no_stock_store_list = trim($no_stock_store_list, ',');
                    $have_stock_store_list = trim($have_stock_store_list, ',');
                    if (empty($no_stock_store_list)) {
                        //所有门店可自提
                        $have_stock_store_list = explode(',', $have_stock_store_list);
                        $have_stock_store_list = array_unique($have_stock_store_list);
                        $new_store_list = $have_stock_store_list;
                    } elseif (empty($have_stock_store_list)) {
                        //没有门店可自提
                        $store_list = [];
                    } elseif ($no_stock_store_list && $have_stock_store_list) {
                        $no_stock_store_list = explode(',', $no_stock_store_list);
                        $no_stock_store_list = array_unique($no_stock_store_list);
                        $have_stock_store_list = explode(',', $have_stock_store_list);
                        $have_stock_store_list = array_unique($have_stock_store_list);
                        $arr = array_merge($no_stock_store_list, $have_stock_store_list);
                        $arr = array_unique($arr);
                        foreach ($arr as $key => $val) {
                            foreach ($no_stock_store_list as $k => $v) {
                                if ($val == $v) {
                                    unset($val);
                                }
                            }
                            if ($val) {
                                $res[] = $val;
                            }
                        }
                        $new_store_list = $res;
                    }
                    if ($new_store_list) {
                        foreach ($new_store_list as $k => $v) {
                            $store_info = $storeModel->getInfo(['store_id' => $v], '*');
                            $newList['distance'] = $storeServer->sphere_distance(['lat' => $store_info['lat'], 'lng' => $store_info['lng']], $place);
                            $newList['store_id'] = $store_info['store_id'];
                            $newList['shop_id'] = $store_info['shop_id'];
                            $newList['website_id'] = $store_info['website_id'];
                            $newList['store_name'] = $store_info['store_name'];
                            $newList['store_tel'] = $store_info['store_tel'];
                            $newList['address'] = $store_info['address'];
                            $newList['province_name'] = $address_service->getProvinceName($store_info['province_id']);
                            $newList['city_name'] = $address_service->getCityName($store_info['city_id']);
                            $newList['dictrict_name'] = $address_service->getDistrictName($store_info['district_id']);
                            $data[] = $newList;
                        }
                        $store_list = $data;
                        unset($data);
                    } else {
                        $store_list = [];
                    }
                }
            } else {
                $store_list = [];
            }
        }elseif ($goods_id){
            //商品详情获取门店列表
            if(getAddons('store',$this->website_id,$this->instance_id)){
                $store_list = '';
                $goods_store_list = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,shop_id,goods_name,store_list')['store_list'];
                if($goods_store_list){
                    $goods_store_list = explode(',',$goods_store_list);
                    if($stock_type == 1) {
                        foreach ($goods_store_list as $k => $v) {
                            $state = $storeGoodsModel->Query(['goods_id' => $goods_id,'website_id' => $this->website_id,'store_id'=>$v],'state')[0];
                            //判断门店是否有上架此商品，如果没有上架，则删除这个门店
                            if($state == 0) {
                                unset($goods_store_list[$k]);
                            }
                        }
                    }
                    if($goods_store_list) {
                        if($stock_type == 1) {
                            //判断门店中有没有此商品的库存，如果没有库存则删除此门店
                            foreach ($goods_store_list as $k => $v){
                                $stock = $storeGoodsSkuModel->getSum(['goods_id' => $goods_id,'website_id' => $this->website_id,'store_id'=>$v],'stock');
                                if($stock <= 0) {
                                    unset($goods_store_list[$k]);
                                }
                            }
                        }
                        if($goods_store_list) {
                            foreach ($goods_store_list as $k => $v){
                                $store_info = $storeModel->getInfo(['store_id' => $v], '*');
                                $newList['distance'] = $storeServer->sphere_distance(['lat' => $store_info['lat'], 'lng' => $store_info['lng']], $place);
                                $newList['store_id'] = $store_info['store_id'];
                                $newList['shop_id'] = $store_info['shop_id'];
                                $newList['website_id'] = $store_info['website_id'];
                                $newList['store_name'] = $store_info['store_name'];
                                $newList['store_tel'] = $store_info['store_tel'];
                                $newList['address'] = $store_info['address'];
                                $newList['score'] = $store_info['score'];
                                $newList['stock'] = $storeGoodsSkuModel->getSum(['goods_id' => $goods_id,'website_id' => $this->website_id,'store_id'=>$v],'stock');
                                $newList['province_name'] = $address_service->getProvinceName($store_info['province_id']);
                                $newList['city_name'] = $address_service->getCityName($store_info['city_id']);
                                $newList['dictrict_name'] = $address_service->getDistrictName($store_info['district_id']);
                                $data[] = $newList;
                            }
                            $store_list = $data;
                            unset($data);
                        }else{
                            $store_list = [];
                        }
                    }else{
                        $store_list = [];
                    }
                }else{
                    $store_list = [];
                }
            }
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $store_list
        ]);
    }

    /**
     * 以店铺为维度，获取购物车中属于此店铺的所有商品，以及店铺的折扣，满减信息
     */
    public function cartGetGoodsList()
    {
        $shop_id = request()->post("shop_id");
        $store_id = request()->post("store_id");

        if($this->is_store) {
            $storeServer = new storeServer();
            //判断后台配置的是哪种库存方式 1:门店独立库存 2:店铺统一库存  默认为1
            $stock_type = $storeServer->getStoreSet($shop_id)['stock_type'] ? $storeServer->getStoreSet($shop_id)['stock_type'] : 1;
        }else{
            $stock_type = 2;
        }

        $msg = '';
        $goods_service = new GoodsService();
        $cart_model = new VslCartModel();
        $cart_goods_list = $cart_model->getQuery(['shop_id' => $shop_id, 'website_id' => $this->website_id, 'buyer_id' => $this->uid], '*', 'cart_id ASC');
        if ($cart_goods_list) {
            foreach ($cart_goods_list as $k => $v) {
                $goods_info = $goods_service->getGoodsDetailById($v['goods_id']);
                $v['promotion_type'] = $goods_info['promotion_type'];
                $v['max_buy'] = $goods_info['max_buy'];

                if ($store_id && $stock_type == 1) {
                    $store_goods_sku_model = new VslStoreGoodsSkuModel();
                    $sku_info = $store_goods_sku_model->getInfo(['shop_id' => $shop_id, 'sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'store_id' => $store_id], '*');
                } else {
                    $goods_sku_model = new VslGoodsSkuModel();
                    $sku_info = $goods_sku_model->getInfo(['sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'website_id' => $this->website_id], '*');
                }

                if ($this->uid) {
                    //查询商品是否开启PLUS会员价
                    $is_member_discount = 1;
                    $goodsPower = $goods_service->getGoodsPowerDiscount($v['goods_id']);
                    $goodsPower = json_decode($goodsPower['value'],true);
                    $plus_member = $goodsPower['plus_member'] ? : 0;
                    if($plus_member == 1) {
                        if(getAddons('membercard',$this->website_id)) {
                            $membercard = new MembercardSer();
                            $membercard_data = $membercard->checkMembercardStatus($this->uid);
                            if($membercard_data['status'] && $membercard_data['membercard_info']['is_member_discount']) {
                                //有会员折扣权益
                                $member_price = $sku_info['price'] * $membercard_data['membercard_info']['member_discount'] / 10;
                                $is_member_discount = 0;
                            }
                        }
                    }

                    if($is_member_discount) {
                        // 查看用户会员价
                        $goodsDiscountInfo = $goods_service->getGoodsInfoOfIndependentDiscount($v['goods_id'], $sku_info['price'],['price' => $goods_info['price'], 'shop_id' => $goods_info['shop_id']]);//计算会员折扣价
                        if ($goodsDiscountInfo) {
                            $member_price = $goodsDiscountInfo['member_price'];
                        }
                    }

                    if (getAddons('seckill', $this->website_id, $this->instance_id)) {
                        //判断是否有秒杀的商品并且是否过期，若有直接取秒杀价
                        $sec_server = new SeckillServer();
                        if (!empty($v['seckill_id'])) {
                            $condition_seckill['s.seckill_id'] = $v['seckill_id'];
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSeckillGoods($condition_seckill);
                        } else {
                            $condition_seckill['nsg.sku_id'] = $v['sku_id'];
                            $is_seckill = $sec_server->isSkuStartSeckill($condition_seckill);
                            if ($is_seckill) {
                                $v['seckill_id'] = $is_seckill['seckill_id'];
                                $seckill_data['cart_id'] = $v["cart_id"];
                                $seckill_data['seckill_id'] = $is_seckill['seckill_id'];
                                $cart_model->data($seckill_data, true)->isupdate(true)->save();
                            }
                        }
                    }

                    if ($is_seckill) {
                        //取该商品该用户购买了多少
                        $sku_id = $v['sku_id'];
                        $uid = $this->uid;
                        $website_id = $this->website_id;
                        $buy_num = $goods_service->getActivityOrderSku($uid, $sku_id, $website_id, $v['seckill_id']);
                        $sec_sku_info_list = $sec_server->getSeckillSkuInfo(['seckill_id' => $v->seckill_id, 'sku_id' => $sku_id]);
                        $goods_info['max_buy'] = (($sec_sku_info_list->seckill_limit_buy - $buy_num) < 0) ? $sec_sku_info_list->seckill_limit_buy : $sec_sku_info_list->seckill_limit_buy - $buy_num;
                        $goods_info['max_buy'] = $goods_info['max_buy'] > $sku_info['stock'] ? $sku_info['stock'] : $goods_info['max_buy'];
                        //如果最大购买数小于购物车的数量并且不等于0
                        if ($goods_info['max_buy'] != 0 && $goods_info['max_buy'] < $v['num']) {
                            // 更新购物车
                            $cart_goods_list[$k]['num'] = $goods_info['max_buy'];
                            $goods_service->cartAdjustNum($v['cart_id'], $goods_info['max_buy']);
                        }
                        if ($goods_info['max_buy'] == 0) {
                            unset($cart_goods_list[$k]);
                            $goods_service->cartDelete($v['cart_id']);
                            $msg .= $v['goods_name'] . "商品已达上限" . PHP_EOL;
                            continue;
                        }
                        $sku_info['stock'] = $goods_info['max_buy'];
                        $price = (float)$sec_sku_info_list->seckill_price;
                    } else {
                        $price = $member_price;
                    }

                    //加图片
                    $picture = new AlbumPictureModel();
                    $cart_goods_list[$k]["picture_info"] = $picture->Query(['pic_id' => $v['goods_picture']], 'pic_cover')[0];

                    $cart_goods_list[$k]["price"] = $price;
                    $cart_goods_list[$k]["goods_name"] = $v["goods_name"];
                    $cart_goods_list[$k]["sku_name"] = $sku_info["sku_name"];
                    $cart_goods_list[$k]['stock'] = $sku_info['stock'];
                    $cart_goods_list[$k]['max_buy'] = $goods_info['max_buy'];
                    $cart_goods_list[$k]['state'] = $goods_info['state'];
                    //购物车暂时不考虑主播商品
                    $cart_goods_list[$k]['anchor_id'] = 0;

                    if ($store_id) {
                        $cart_goods_list[$k]['store_id'] = $store_id;
                    }
                }
            }

            //重新遍历，获取店铺的折扣，满减信息
            $list = [];
            foreach ($cart_goods_list as $i => $v) {
                $cart_goods_list[$i]['bargain_id'] = 0;
                if ($this->is_seckill) {
                    //查出商品是否在秒杀活动
                    $goods_id = $cart_goods_list[$i]["goods_id"];
                    $condition_is_seckill['nsg.goods_id'] = $goods_id;
                    $seckill_server = new SeckillServer();
                    $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill);
                }

                //折扣信息
                $list['discount_info'] = (object)array();
                //限时折扣
                if ($this->is_discount && !empty($discount_info) && $cart_goods_list[$i]['promotion_type'] == 5) {
                    $discount_server = new Discount();
                    $discount_info = $discount_server->getBestDiscount($cart_goods_list[$i]["shop_id"]);
                    $list['discount_info'] = !empty($discount_info) ? $discount_info : (object)array();
                    $promotion_discount = $discount_server->getPromotionInfo($cart_goods_list[$i]['goods_id'], $cart_goods_list[$i]['shop_id'], $cart_goods_list[$i]['website_id']);
                    if ($promotion_discount) {
                        if ($store_id && $stock_type == 1) {
                            $store_goods_sku_model = new VslStoreGoodsSkuModel();
                            $sku_info = $store_goods_sku_model->getInfo(['shop_id' => $shop_id, 'sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'store_id' => $store_id], 'price');
                        } else {
                            $goods_sku_model = new VslGoodsSkuModel();
                            $sku_info = $goods_sku_model->getInfo(['sku_id' => $v['sku_id'], 'goods_id' => $v['goods_id'], 'website_id' => $this->website_id], 'price');
                        } 

                        if ($promotion_discount['integer_type'] == 1) {
                            $cart_goods_list[$i]['price'] = round($sku_info['price'] * $promotion_discount['discount_num'] / 10);
                        } else {
                            $cart_goods_list[$i]['price'] = round($sku_info['price'] * $promotion_discount['discount_num'] / 10, 2);
                        }
                        if ($promotion_discount['discount_type'] == 2) {
                            $cart_goods_list[$i]['price'] = $promotion_discount['discount_num'];
                        }
                    }
                }
                if ($is_seckill) {
                    $list['mansong_info'] = (object)array();
                    $list['discount_info'] = (object)array();
                }
            }
            $list['goods_list'] = $cart_goods_list;
            $list['shop_name'] = $cart_goods_list[0]["shop_name"];
            $list['shop_id'] = $cart_goods_list[0]["shop_id"];

            if ($store_id && $stock_type == 1) {
                $storeServer = new storeServer();
                $payment_info = $storeServer->paymentData($cart_goods_list);
            } else {
                $payment_info = $goods_service->paymentData($cart_goods_list);
            }
            $list['mansong_info'] = $payment_info[0]['full_cut'];
            $list = $this->object2array($list);
            if (empty($list['mansong_info'])) {
                $list['mansong_info'] = (object)array();
            }

            //重新遍历，如果活动不一致则删除活动
            if (!empty($list)) {
                foreach ($list['goods_list'] as $ka => $kb) {
                    if (!empty($list->discount_info)) {
                        if ($list['discount_info']['range'] == 2) {
                            $is_active_goods = $discount_server->checkIsDiscountProduct($kb['goods_id'], $kb['shop_id'], $list['discount_info']['discount_id']);
                            if (empty($is_active_goods)) {
                                $list['discount_info'] = (object)array();
                            }
                        }
                    } else {
                        $list['discount_info'] = (object)array();
                    }
                }
            }
        }

        if ($msg) {
            $data['code'] = -1;
            $data['data'] = $list;
            $data['message'] = $msg;
        } else {
            $data['code'] = 1;
            $data['data'] = $list;
            $data['message'] = "修改成功";
        }

        return json($data);
    }

    /**
     * 商品详情
     */
    public function goodsDetail()
    {
        try {
            $redis = connectRedis();
            $goods_server  = new GoodsService();
            $goods_id       = request()->post('goods_id');
            $mic_goods      = request()->post('mic_goods', 0);
            $seckill_id     = request()->post('seckill_id', 0);
            $channel_id     = request()->post('channel_id', 0);
            $bargain_id     = request()->post('bargain_id', 0);
            $record_id      = request()->post('record_id', 0);
            // *** 商品详情 ***
//            $goods_service = new GoodsService();
            $goods_data = $goods_server->getGoodsDetail($goods_id, 0, $mic_goods);
            
            if (empty($goods_data)) {
                return AjaxReturn(FAIL,[], '商品不存在!');
            }
            
            // *** 商品详情：组装gods_detail
            $goods_detail['price_type']             = $goods_data['price_type'];
            $goods_detail['goods_id']               = $goods_data['goods_id'];
            $goods_detail['state']                  = $goods_data['state'];
            $goods_detail['shop_id']                = $goods_data['shop_id'];
            $goods_detail['goods_name']             = htmlspecialchars_decode($goods_data['goods_name']);
            $goods_detail['sub_title']             = htmlspecialchars_decode($goods_data['sub_title']);
            $goods_detail['description']            = str_replace('"//', '"https://', $goods_data['description']);
            $goods_detail['sales']                  = $goods_data['sales'] + $goods_data['real_sales'];
            $goods_detail['min_buy']                = $goods_data['min_buy'];
            $goods_detail['collects']               = $goods_data['collects'];
            $goods_detail['shop_name']              = $goods_data['shop_name'];
            $goods_detail['goods_type']             = $goods_data['goods_type'];
            $goods_detail['video']                  = getApiSrc($goods_data['video']);
            $goods_detail['sales']                  = $goods_data['sales'] + $goods_data['real_sales'];//真实 + 虚拟
            
            # 购买权限
            $member_discount        = $goods_data['member_discount'];
            $member_is_label        = $goods_data['member_is_label'];// 是否取整
            $limit_discount_info    = $goods_data['limit_discount_info'];
            $is_allow_buy           = true;// 查询是否该用户有购买该商品权限by sgw
            $is_allow_browse        = true;
            if ($this->uid) {
                $is_allow_buy       = $goods_server->isAllowToBuyThisGoods($this->uid, $goods_id);
                $is_allow_browse    = $goods_server->isAllowToBrowse($this->uid, $goods_id); // 是否有浏览权限
            }
            $discount_choice        = $goods_data['discount_choice'] ?: 1;
            // 获取限时折扣
            $promotion_info['discount_num'] = 10;
            if ($this->is_discount && !$mic_goods) {
                $discount_service = new Discount();
                $promotion_info = $discount_service->getPromotionInfo($goods_id, $goods_data['shop_id'], $goods_data['website_id']);
            }
            
            # 知识付费商品
            if($goods_data['goods_type'] == 4) {
                $goods_detail['is_buy'] = $goods_data['is_buy'];
            }
            //$goods_detail['spec_list'] = $goods_data['spec_list'];
            $goods_detail['shipping_fee'] = 0;
            if ($goods_data['shipping_fee_type'] == 0) {
                $goods_detail['shipping_fee'] = '包邮';
            } elseif ($goods_data['shipping_fee_type'] == 1) {
                $goods_detail['shipping_fee'] = $goods_data['shipping_fee'];
            } elseif ($goods_data['shipping_fee_type'] == 2) {
                $user_location = get_city_by_ip();
                if ($user_location['status'] == 1) {
                    // 定位成功，查询当前城市的运费
                    $goods_express = new GoodsExpress();
                    $address = new Address();
                    $city = $address->getCityId($user_location["city"]);
                    $district = $address->getCityFirstDistrict($city['city_id']);
                    $express = $goods_express->getGoodsExpressTemplate([['goods_id' => $goods_id, 'count' => 1]], $district)['totalFee'];
                    $goods_detail['shipping_fee'] = $express;
                }
            }
            //商品属性
            $goods_attribute = $goods_server->goodsAttribute(['goods_id' => $goods_id], ['attribute_value']);
            $goods_attribute_list_new = array();
            foreach ($goods_attribute as $item) {
                $attr_value_name = '';
                foreach ($goods_attribute as $key => $item_v) {
                    if ($item_v['attr_value_id'] == $item['attr_value_id']) {
                        $attr_value_name .= $item_v['attr_value_name'] . ',';
                        unset($goods_attribute[$key]);
                    }
                }
                if (!empty($attr_value_name)) {
                    array_push($goods_attribute_list_new, array(
                        'attr_value_id' => $item['attr_value_id'],
                        'attr_value' => $item['attr_value'],
                        'attr_value_name' => rtrim($attr_value_name, ','),
                        'sort' => $item['sort'],
                    ));
                }
            }
            if ($goods_attribute_list_new) {
                array_multisort(array_column($goods_attribute_list_new, 'sort'), SORT_ASC, $goods_attribute_list_new);
            }
            $goods_detail['goods_attribute_list'] = $goods_attribute_list_new;
            //商品图片
            foreach ($goods_data['img_list'] as $k => $pic) {
                $goods_detail['goods_images'][] = getApiSrc($pic['pic_cover']);
            }
            // 处理图片域名,替换后上传云服务器（图片域名为第三方的）,目的是为了图片域名必须在小程序downloaddomain中
            if (!empty($goods_detail['goods_images'][0])) {
                $upload_url = $goods_server->modifyImageUrl2AliOss($goods_detail['goods_images'][0]);
                $goods_detail['goods_image_yun'] = $upload_url;
            }
            
            # 商品规格配置参数
            $spec_obj = [];
            $goods_detail['sku']['tree'] = [];
            if (!empty($goods_data['spec_list']) && $goods_data['spec_list'] != '[]') {
                foreach ($goods_data['spec_list'] as $i => $spec_info) {
                    $temp_spec = [];
                    foreach ($spec_info['value'] as $s => $spec_value) {
                        $temp_spec['k'] = $spec_info['spec_name'];
                        $temp_spec['k_id'] = $spec_info['spec_id'];
                        $temp_spec['v'][$s]['id'] = $spec_value['spec_value_id'];
                        $temp_spec['v'][$s]['name'] = $spec_value['spec_value_name'];
                        $temp_spec['v'][$s]['imgUrl'] = $spec_value['spec_value_data_src'];
                        $temp_spec['k_s'] = 's' . $i;
                        $spec_obj[$spec_info['spec_id']] = $temp_spec['k_s'];
                        $goods_detail['sku']['tree'][$spec_info['spec_id']] = $temp_spec;
                    }
                }
                //接口需要tree是数组，不是对象，去除tree以spec_id为key的值
                $goods_detail['sku']['tree'] = array_values($goods_detail['sku']['tree']);
            }
            
            # 限时折扣
            if(empty($limit_discount_info) == false && $goods_data['promotion_type'] == 5){
                //未到时间的限时活动
                if($goods_data['promote_id']){
                    $DiscountModel = new DiscountModel();
                    $limit_discount_info = $DiscountModel->getInfo(['discount_id' => $goods_data['promote_id']], '*');
                    if($limit_discount_info['status'] > 1){
                        $limit_discount_info = (object)[];
                    }else{
                        //获取折后价格
                        if($limit_discount_info['discount_type'] == 1){ //折扣价
                            $limit_discount_info['discount_price'] = $goods_data['market_price'] * $limit_discount_info['discount_num'] / 10;
                        }else if($limit_discount_info['discount_type'] == 2){//固定价格
                            $limit_discount_info['discount_price'] = $limit_discount_info['discount_num'];
                        }
                    }
                }
            }
            # 判断当前商品是否是幸运拼团商品
            if ($this->luckyspell) {
                $luckyspell_server = new luckySpellServer();
                $is_luckyspell = $luckyspell_server->isGroupGoods($goods_id);
            }
            # 判断当前商品是否是拼团商品
            
            if ($this->groupshopping) {
				$group_server = new GroupShoppingServer();
                $is_group = $group_server->isGroupGoods($goods_id);
            }
            # 砍价
            if ($this->is_bargain && !$mic_goods) {
                $bargain_server = new Bargain();
                if (!empty($bargain_id)) {
                    //砍价是否过期
                    $condition_bargain['website_id'] = $this->website_id;
                    $condition_bargain['bargain_id'] = $bargain_id;
                    $is_bargain = $bargain_server->isBargain($condition_bargain, 0);
                } else {
                    $condition_bargain['website_id'] = $this->website_id;
                    $condition_bargain['goods_id'] = $goods_id;
                    $condition_bargain['end_bargain_time'] = ['>=', time()];//未结束的
                    $is_bargain = $bargain_server->isBargainByGoodsId($condition_bargain, 0);
                }
            }
    
            # 渠道商
            # 如果当前用户登录了，判断此用户有没有上级渠道商，如果有，库存显示平台库存+直属上级渠道商的库存
            if($this->is_channel) {
                if(empty($channel_id)) {
                    if($this->uid) {
                        $member_model = new VslMemberModel();
                        $referee_id = $member_model->Query(['uid'=>$this->uid,'website_id'=>$this->website_id],'referee_id')[0];
                        if($referee_id) {//如果有上级，判断是不是渠道商
                            $channel_model = new VslChannelModel();
                            $is_channel = $channel_model->Query(['uid'=>$referee_id,'website_id'=>$this->website_id],'channel_id')[0];
                            if($is_channel) {//如果上级是渠道商，判断上级渠道商有没有采购过这个商品
                                $channel_goods_model = new VslChannelGoodsModel();
                                $channel_goods_id = $channel_goods_model->Query(['goods_id'=>$goods_id,'channel_id'=>$is_channel,'website_id'=>$this->website_id],'goods_id')[0];
                                if($channel_goods_id) {
                                    $channel_id = $is_channel;
                                }
                            }
                        }
                    }
                }
            }
        
            # 商品规格列表
            $sku_sales      = 0;
            $seckill_list   = [];
            $group_list     = [];
            $presell_list   = [];
            $bargain_list   = [];
            $luckyspell_list   = [];
            foreach ($goods_data['sku_list'] as $k => $sku) {
                $temp_sku['id'] = $sku['sku_id'];
                //如果没有登录便将初始价格*会员默认等级折扣
                if(!$this->uid){
                    $member_level = new VslMemberLevelModel();
                    $goods_discount = $member_level->getInfo(['is_default' => 1, 'website_id' => $this->website_id], 'goods_discount')['goods_discount']?:10;
                    $sku['price'] = $sku['price'] * $goods_discount/10;
                }
                //如果存在限时折扣，就需要去除会员折扣
                if($goods_data['price_type'] == 1){ //会员折扣
                    if($member_is_label == 1){
                        $sku['price'] = round($sku['price']);
                    }
                }else if($goods_data['price_type'] == 2){ //限时折扣
                    if($goods_data['limit_discount_info'] && $goods_data['limit_discount_info']['discount_type'] == 1 && $goods_data['limit_discount_info']['integer_type'] == 1){
                        $sku['price'] = round($sku['price']);
                    }
                }
                $temp_sku['sku_name']           = $sku['sku_name'];
                $temp_sku['price']              = $sku['price'];
                $temp_sku['max_buy']            = $goods_server->getUserMaxBuyGoodsSkuCount(0,$sku['goods_id'],$sku['sku_id']);
                $temp_sku['least_buy']          = $goods_data['least_buy'];
                $temp_sku['market_price']       = $sku['market_price'];//市场价
                $temp_sku['promote_price']      = $sku['promote_price'];//促销价
                $temp_sku['attr_value_items']   = $sku['attr_value_items'];
                $temp_sku['stock_num']          = $sku['stock'];//库存
                $sku_temp_spec_array            = explode(';', $sku['attr_value_items_format']);
                $temp_sku['s']                  = [];
                foreach ($sku_temp_spec_array as $spec_id => $spec_combination) {
                    $explode_spec = explode(':', $spec_combination);
                    $spec_id = $explode_spec[0];
                    $spec_value_id = $explode_spec[1];
            
                    // ios wants string
                    if ($spec_value_id) {
                        $temp_sku['s'][] = (string)$spec_value_id;
                        $temp_sku[$spec_obj[$spec_id] ?: 's0'] = (int)$spec_value_id;
                    }
                }
    
                $goods_detail['min_price'] = reset($goods_data['sku_list'])['sku_id'] == $sku['sku_id']
                    ? $sku['price'] : ($goods_detail['min_price'] <= $sku['price'] ? $goods_detail['min_price'] : $sku['price']);
                if ($promotion_info['discount_type'] == 2) {
                    $goods_detail['min_price'] = $promotion_info['discount_num'];
                }
                $goods_detail['min_market_price'] = reset($goods_data['sku_list'])['sku_id'] == $sku['sku_id']
                    ? $sku['market_price'] : ($goods_detail['min_market_price'] <= $sku['market_price'] ? $goods_detail['min_market_price'] : $sku['market_price']);//暂时不用
                $goods_detail['max_market_price'] = reset($goods_data['sku_list'])['sku_id'] == $sku['sku_id']
                    ? $sku['market_price'] : ($goods_detail['max_market_price'] >= $sku['market_price'] ? $goods_detail['max_market_price'] : $sku['market_price']);//暂时不用
    
                /************************** 营销活动处理 *******************************************/
                # todo... 1、秒杀
                if ($this->is_seckill && !$mic_goods) {
                    $seckill_server = new SeckillServer();
                    //判断当前商品是否是秒杀商品，并且没有过期
                    if ($seckill_id) {/*秒杀列表到商品详情*/
                        $condition_is_seckill['s.seckill_id'] = $seckill_id;
                        $condition_is_seckill['nsg.goods_id'] = $goods_id;
                        $is_seckill = $seckill_server->isSeckillGoods($condition_is_seckill);
                    } else {
                        $condition_is_seckill['nsg.goods_id'] = $goods_id;
                        $is_seckill = $seckill_server->isSkuStartSeckill($condition_is_seckill);
                        $seckill_id = $is_seckill['seckill_id'];
                    }
                    // 如果是秒杀商品且开始
                    if ($is_seckill && !$mic_goods) {
                        $condition_seckill = [
                            'ns.website_id'        => $this->website_id,
                            'ns.seckill_id'        => $seckill_id,
                            'nsg.goods_id'         => $goods_id
                        ];
                        //seckill_num:活动库存 remain_num:剩余库存 seckill_sales:销量
                        $seckill_num_arr = $seckill_server->getGoodsSkuArr($condition_seckill, 'nsg.sku_id, nsg.seckill_num,nsg.seckill_price, nsg.remain_num, nsg.seckill_limit_buy, nsg.seckill_sales,nsg.max_buy_time,ns.seckill_vrit_num');
                        $seckill_arr = array_column($seckill_num_arr, 'seckill_price');
                        $goods_detail['min_price'] = min($seckill_arr);
                        //$goods_detail['sales'] += $seckill_num_arr[0]['seckill_vrit_num'];//虚拟抢购量从vsl_seckill取
                        //$sku_sales = $seckill_num_arr[0]['seckill_vrit_num'];//虚拟抢购量从vsl_seckill取
                        foreach ($seckill_num_arr as $seckill_k => $seckill_sku_item) {
                            if ($seckill_sku_item['sku_id'] == $sku['sku_id']) {
                                $sku_id                 = $seckill_sku_item['sku_id'];
                                $temp_sku['id']         = $sku_id;
                                $temp_sku['stock_num']  = $seckill_sku_item['remain_num'];//秒杀(剩余库存)
                                # sku数据
                                $temp_sku['price']      = $seckill_sku_item['seckill_price'];//秒杀(活动价格)
                                $sku_can_buy = $goods_server->getUserMaxBuyGoodsSkuCount(1,$seckill_id, $sku_id);
                                $temp_sku['max_buy']    = $sku_can_buy;//秒杀(限购)
                                $temp_sku['least_buy']  = $goods_server->getUserLeastBuyGoods(1,$seckill_id);//秒杀(起购)
                                # detail数据
                                $goods_detail['sales']  += $seckill_sku_item['seckill_sales'];
                                //redis队列key值
                                $redis_goods_sku_seckill_key = 'seckill_' . $seckill_id . '_' . $goods_id . '_' . $sku_id;//每个活动的库存都不一样
                                $is_index = $redis->get($redis_goods_sku_seckill_key);
                                if (!$is_index) {
                                    $redis->set($redis_goods_sku_seckill_key, $seckill_sku_item['remain_num']);
                                }
                            }
                        }
                    }
                    // 秒杀活动
                    $survive_time = getSeckillSurviveTime($this->website_id);
                    //获取即将进行的最近一场的seckill_id。 ['sg.goods_id'=>$goods_id,'s.seckill_now_time'=>['>=',time()-24*3600]]
                    $seckill_id_condition['sg.goods_id'] = $goods_id;
                    $seckill_id_condition['s.seckill_now_time'] = ['>=', time() - $survive_time * 3600];
                    $seckill_id = $seckill_server->getSeckillId($seckill_id_condition);
                    if ($seckill_id) {
                        $condition_seckill['ns.website_id'] = $this->website_id;
                        $condition_seckill['ns.seckill_id'] = $seckill_id;
                        $condition_seckill['nsg.goods_id']  = $goods_id;
                        $seckill_goods_list = $seckill_server->getWapSeckillGoodsList($condition_seckill, 'ns.seckill_now_time,nsg.seckill_num,nsg.remain_num, nsg.seckill_vrit_num,nsg.seckill_price');
                        $seckill_goods_arr  = objToArr($seckill_goods_list);
                        $seckill_num        = 0;
                        $remain_num         = 0;
                        $seckill_vrit_num   = 0;
                        foreach ($seckill_goods_arr as $k => $seck_info) {
                            $seckill_num        = $seckill_num + $seck_info['seckill_num'];
                            $remain_num         = $remain_num + $seck_info['remain_num'];
                            $seckill_vrit_num   = $seckill_vrit_num + $seck_info['seckill_vrit_num'];
                            $seckill_price      = $seck_info['seckill_price'];
                        }
                        $seckill_list['seckill_id']     = $seckill_id;
                        $seckill_list['seckill_num']    = $seckill_num;
                        $seckill_list['remain_num']     = $remain_num;
                        $seckill_now_time               = $seckill_goods_arr[0]['seckill_now_time'];
                        $now_time = time();
                        //是今天的判断是否正在进行或者未开始，结束
                        if ($now_time >= $seckill_now_time && $now_time <= $seckill_now_time + $survive_time * 3600) {//正在进行
                            $seckill_list['seckill_day']    = '';
                            $seckill_list['seckill_status'] = 'going';
                            $seckill_list['seckill_time']   = '';
                            $seckill_list['start_time']     = '';
                            $seckill_list['end_time']       = $seckill_now_time + $survive_time * 3600;
                            $seckill_list['robbed_percent'] = round(($seckill_num - $remain_num) / $seckill_num * 100) . '%';
                            $seckill_list['robbed_num']     = ($seckill_num - $remain_num);
                        } elseif ($now_time > $seckill_now_time + $survive_time * 3600) {//已结束
                            $seckill_list['seckill_day']    = 'ended';
                            $seckill_list['seckill_day']    = date('m-d', $seckill_now_time);
                            $seckill_list['seckill_time']   = date('H', $seckill_now_time);
                            $seckill_list['start_time']     = '';
                            //                        $seckill_list['robbed_percent'] = (($seckill_num - ($remain_num + $seckill_vrit_num)) / $seckill_num * 100)) . '%';
                            $seckill_list['robbed_percent'] = round(($seckill_num - $remain_num) / $seckill_num * 100) . '%';//调整：虚拟抢购量算在已抢进度条
                            $seckill_list['robbed_num']     = ($seckill_num - $remain_num);
                        } elseif ($now_time < $seckill_now_time) {//未开始
                            $today_date                     = date('Y-m-d', time());
                            $tomorrow_date                  = date('Y-m-d', strtotime('+1 day'));
                            $seckill_now_date               = date('Y-m-d', $seckill_now_time);
                            $seckill_list['discount_price'] = $seckill_price;
                            if ($seckill_now_date == $today_date) {//今天还未开始
                                $seckill_list['seckill_day']    = 'today';
                                $seckill_list['seckill_status'] = 'unstart';
                                $seckill_list['seckill_time']   = date('H:i', $seckill_now_time);
                                $seckill_list['start_time']     = $seckill_now_time;
                                $seckill_list['robbed_percent'] = '0' . '%';
                            } elseif ($seckill_now_date == $tomorrow_date) {//明天还未开始
                                $seckill_list['seckill_day']    = 'tomorrow';
                                $seckill_list['seckill_status'] = 'unstart';
                                $seckill_list['seckill_time']   = date('H:i', $seckill_now_time);
                                $seckill_list['start_time']     = $seckill_now_time;
                                $seckill_list['robbed_percent'] = '0' . '%';
                            } else {//后天及以后的天数
                                $seckill_list['seckill_day']    = date('m-d', $seckill_now_time);
                                $seckill_list['seckill_status'] = 'unstart';
                                $seckill_list['seckill_time']   = date('H:i', $seckill_now_time);
                                $seckill_list['start_time']     = $seckill_now_time;
                                $seckill_list['robbed_percent'] = '0' . '%';
                            }
                        }
                    }
                }
    
                # todo... 2、拼团
                if ($is_group && !$mic_goods) {
                    $condition_group = [
                        'group_id' => $is_group,
                        'goods_id' => $goods_id
                    ];
                    $groupGoods = new VslGroupShoppingModel();
                    // group_price:活动价格 group_limit_buy:group_limit_buy
                    $groupSku = $groupGoods::get($condition_group, ['group_goods']);
                    foreach ($groupSku['group_goods'] as $group_k => $group_sku_item) {
                        if ($group_sku_item['sku_id'] == $sku['sku_id']) {
                            $temp_sku['group_price'] = $group_sku_item['group_price'];
                            $sku_can_buy = $goods_server->getUserMaxBuyGoodsSkuCount(2,$is_group,$sku['sku_id']);
                            $temp_sku['group_limit_buy'] = $sku_can_buy;
                            $temp_sku['group_least_buy']  = $goods_server->getUserLeastBuyGoods(2,$is_group);//拼团(起购)
                        }
                    }
                    
                    // 拼团详情
                    $group_list['group_id']                 = $is_group;
                    $group_list['group_name']               = $group_server->getGroupName($is_group);
                    $group_list['record_id']                = $record_id;
                    $group_list['group_record_list']        = $group_server->goodsGroupRecordListForWap($goods_id);
                    $group_list['group_record_count']       = $group_server->goodsGroupRecordCount($goods_id);
                    $regimentCount                          = $group_server->goodsRegimentCount($goods_id);
                    $group_list['regiment_count']           = !empty($regimentCount) ? $regimentCount['now_num'] * $regimentCount['group_num'] : 0;
                }
                
                # todo... 3、预售
                $is_presell = 0;
                if ($this->is_presell && !$mic_goods) {
                    $presell = new PresellService();
                    // all_money:预售价 presell_num:预售库存 max_buy:限购 vrnum:虚拟订购量
                    $presell_info = $presell->getPresellInfoByGoodsId($goods_id);
                    //$goods_detail['sales'] += $presell_info[0]['presell_sales'] + $presell_info[0]['vrnum'];
                    $sku_sales = $presell_info[0]['presell_sales'] + $presell_info[0]['vrnum'];
                    if (!empty($presell_info)) {
                        // 默认返回第一个用于展示
                        $presell_list['name']                       = $presell_info[0]['name'];
                        $presell_list['firstmoney']                 = $presell_info[0]['firstmoney'];
                        $presell_list['allmoney']                   = $presell_info[0]['all_money'];
                        $presell_list['start_time']                 = $presell_info[0]['start_time'];
                        $presell_list['end_time']                   = $presell_info[0]['end_time'];
                        $presell_list['pay_start_time']             = $presell_info[0]['pay_start_time'];
                        $presell_list['pay_end_time']               = $presell_info[0]['pay_end_time'];
                        $presell_list['send_goods_time']            = $presell_info[0]['send_goods_time'];
                        $presell_list['vrnum']                      = $presell_info[0]['vrnum'];//虚拟订购量
                        $presell_list['presell_id']                 = $presell_info[0]['id'];//预售活动ID
                        foreach ($presell_info as $presell_k => $presell_sku_item) {
                            if ($presell_sku_item['sku_id'] == $sku['sku_id']) {
                                $temp_sku['id']             = $presell_sku_item['sku_id'];
                                $temp_sku['stock_num']      = $temp_sku['presell_num'] = $presell_sku_item['presell_num'];//预售(库存)
                                //维护预售库存到redis
                                $presell_key = 'presell_'.$presell_info[0]['id'].'_'.$temp_sku['id'];
                                if(!$redis->get($presell_key)){
                                    $redis->set($presell_key, $presell_sku_item['presell_num']);
                                }
                                # sku数据
                                $temp_sku['first_money']    = $presell_sku_item['first_money'];
                                $temp_sku['all_money']      = $presell_sku_item['all_money'];//预售(活动价格)
                                $sku_can_buy                = $goods_server->getUserMaxBuyGoodsSkuCount(3,$presell_info[0]['id'],$presell_sku_item['sku_id']);
                                $temp_sku['max_buy']        = $sku_can_buy;//预售(限购)
                                $temp_sku['least_buy']      = $goods_server->getUserLeastBuyGoods(2,$presell_info[0]['id']);//预售(起购)
                            }
                        }
                        //获取支付剩余时间
                        $config = new Config();
                        $pay_limit_time = $config->getConfig(0, 'ORDER_BUY_CLOSE_TIME', $this->website_id, 1);
                        $presell_list['pay_limit_time'] = $pay_limit_time ? : 0;
                        //判断状态是进行中还是
                        if (time() > $presell_info[0]['start_time'] && time() < $presell_info[0]['end_time']) {
                            $presell_list['state'] = 1; //正在进行
                        } else if (time() < $presell_list['start_time']) {
                            $presell_list['state'] = 2;//没开始
                        } else {
                            $presell_list['state'] = 3;//结束了
                        }
                        $is_presell = 1;
                    }
                }
    
                # todo... 4、砍价
                if ($is_bargain && !$mic_goods) {
                    //bargain_stock:库存 limit_buy:限购
                    $sku_sales                      = $is_bargain['bargain_sales'];
                    $order = new \data\service\Order\Order();
                    $buy_num = $order->getActivityOrderSkuNum($this->uid, $temp_sku['id'], $this->website_id,3, $is_bargain['bargain_id']);//3是砍价
                    $limit_buy = (int)$is_bargain['limit_buy'];
                    $bargain_stock = $is_bargain['bargain_stock'];
                    if($limit_buy != 0){
                        $max_buy = $limit_buy - $buy_num;
                        $temp_sku['bargain_stock_num'] = $max_buy > 0 ? ($max_buy > $bargain_stock ? $bargain_stock : $max_buy) : 0;
                    }else{
                        $temp_sku['bargain_stock_num'] = $bargain_stock ? : 0;
                    }
                    $temp_sku['bargain_price'] = $is_bargain['start_money'];
                    //维护砍价库存到redis
                    $bargain_key = 'bargain_'.$is_bargain['bargain_id'].'_'.$temp_sku['id'];
                    if(!$redis->get($bargain_key)){
                        $redis->set($bargain_key, $temp_sku['bargain_stock_num']);
                    }
                    $temp_sku['bargain_max_buy']    = $limit_buy;
                    $bargain_list                   = $is_bargain;
                }
                
                # todo... 5、渠道商
                if ($channel_id && !$mic_goods) {
                    //如果上级是渠道商，库存显示平台库存+直属上级渠道商的库存
                    $channel_sku_mdl = new VslChannelGoodsSkuModel();
                    $channel_cond = [
                        'channel_id'        => $channel_id,
                        'sku_id'            => $sku['sku_id'],
                        'website_id'        => $this->website_id,
                    ];
                    $channel_stock = $channel_sku_mdl->getInfo($channel_cond, 'stock')['stock'];
                    if(empty($is_group) && empty($is_presell)) {
                        $temp_sku['stock_num']      = $channel_stock + $sku['stock'] ?: 0;
                    }else{
                        //拼团、预售有独立的限购
                        $temp_sku['stock_num']      = $channel_stock + $sku['stock'] ?: 0;
                    }
                    //维护渠道商库存到redis
                    $channel_key = 'channel_'.$channel_id.'_'.$temp_sku['id'];
                    $only_channel_key = 'only_channel_'.$channel_id.'_'.$temp_sku['id'];//用于当零售的商品库存同时有渠道商的和上级的时候，购买商品库存更新区分
                    $only_platform_key = 'only_platform_'.$channel_id.'_'.$temp_sku['id'];//用于当零售的商品库存同时有渠道商的和上级的时候，购买商品库存更新区分
                    if(!$redis->get($channel_key)){
                        $redis->set($channel_key, $temp_sku['stock_num']);
                        $redis->set($only_channel_key, $channel_stock);
                        $redis->set($only_platform_key, $sku['stock']);
                    }
                    if(!$redis->get($only_channel_key)){
                        $redis->set($only_channel_key, $channel_stock);
                    }
                    if(!$redis->get($only_platform_key)){
                        $redis->set($only_platform_key, $sku['stock']);
                    }
                }
                #6 幸运拼
               
                if($this->luckyspell && $is_luckyspell && !$mic_goods){
                    $condition_group = [
                        'group_id' => $is_luckyspell,
                        'goods_id' => $goods_id
                    ];
                    $groupGoods = new VslLuckySpellModel();
                    // group_price:活动价格 group_limit_buy:group_limit_buy
                    $groupSku = $groupGoods::get($condition_group, ['group_goods']);
                    $max_pri = '';
                    foreach ($groupSku['group_goods'] as $group_k => $group_sku_item) {
                        if ($group_sku_item['sku_id'] == $sku['sku_id']) {
                            $temp_sku['group_price'] = $group_sku_item['group_price'];
                            if($max_pri == ''){
                                $max_pri = $temp_sku['group_price'];
                            }
                            if($max_pri != '' && $max_pri < $temp_sku['group_price']){
                                $max_pri = $temp_sku['group_price'];
                            }
                            $sku_can_buy = $goods_server->getUserMaxBuyGoodsSkuCount(2,$is_luckyspell,$sku['sku_id']);
                            $temp_sku['group_limit_buy'] = $sku_can_buy;
                            $temp_sku['group_least_buy']  = $goods_server->getUserLeastBuyGoods(6,$is_luckyspell);//拼团(起购)
                        }
                    }
                    // 拼团详情
                    $luckyspell_list['group_record_list']  = $luckyspell_server->getLuckySpellUsers($is_luckyspell);//正在参加拼团成员列表
                    $luckyspell_list['thresholdtype_point'] = $groupSku['thresholdtype_point'];//门槛积分
                    $luckyspell_list['thresholdtype'] = $groupSku['thresholdtype'];//门槛积分
                    $luckyspell_list['win_num'] = $groupSku['win_num'];//中奖人数
                    $luckyspell_list['loser_num'] = $groupSku['group_num'] - $groupSku['win_num'];//未中奖人数
                    $luckyspell_list['group_num'] = $groupSku['group_num'];//成团人数
                    $luckyspell_list['rewardtype'] = $groupSku['rewardtype'];//失败奖励类型
                    $luckyspell_list['rewardmethod'] = $groupSku['rewardmethod'];//失败奖励方式
                    if($groupSku['rewardmethod'] == 1 ){ //百分比
                        $luckyspell_list['failure_reward'] = round($max_pri * $groupSku['failure_reward'] / 100,2);//失败奖励金额
                    }else{ //固定
                        $luckyspell_list['failure_reward'] = $groupSku['failure_reward'];//失败奖励金额
                    }
                    
                    $luckyspell_list['luckyspell_id']                 = $is_luckyspell;
                    $luckyspell_list['luckyspell_name']               = $luckyspell_server->getGroupName($is_luckyspell);
                    $luckyspell_list['luckyspell_suc_count']       = $luckyspell_server->goodsluckyspellRecordCount($goods_id); //已成功团数
                }
                # 最终sku
                $goods_detail['sku']['list'][] = $temp_sku;
                //维护普通商品库存到redis
                $goods_key = 'goods_'.$goods_id.'_'.$temp_sku['id'];
                if(!$redis->get($goods_key)){
                    $redis->set($goods_key, $temp_sku['stock_num']);
                }
            }
            $goods_detail['sales'] += $seckill_num_arr[0]['seckill_vrit_num'] ? : 0;
            # 商品销售量
            $goods_detail['sales']          += $sku_sales;
            $goods_detail['is_collection']  = false;
            if ($this->uid) {
                $goods_detail['is_collection'] = $this->user->getIsMemberFavorites($this->uid, $goods_id, 'goods') ? true : false;
            }
            
            #  满减送
            $full_cut_list = [];
            if ($this->is_full_cut && !$mic_goods) {
                $full_cut_server = new Fullcut();
                $full_cut_info = $full_cut_server->goodsFullCut($goods_id);
                
                $full_cut_list = [];
                
                foreach ($full_cut_info as $k => $v) {
                    $full_cut_list[$k]['mansong_id']        = $v['mansong_id'];
                    $full_cut_list[$k]['mansong_name']      = $v['mansong_name'];
                    $full_cut_list[$k]['start_time']        = $v['start_time'];
                    $full_cut_list[$k]['end_time']          = $v['end_time'];
                    $full_cut_list[$k]['shop_id']           = $v['shop_id'];
                    $full_cut_list[$k]['shop_name']         = $v['shop_name'];
                    $full_cut_list[$k]['range']             = $v['range'];
                    $full_cut_list[$k]['rules']             = [];
                    // 重新获取rules
                    $rules = $full_cut_server->rulesLists($v['mansong_id']);
                   
                    if($rules){
                        foreach ($rules as $i => $r) {
                            $full_cut_list[$k]['rules'][$i]['price']            = $r['price'];
                            $full_cut_list[$k]['rules'][$i]['discount']         = $r['discount'];
                            $full_cut_list[$k]['rules'][$i]['free_shipping']    = $r['free_shipping'];
                            $full_cut_list[$k]['rules'][$i]['give_point']       = $r['give_point'];
                            if ($r['give_coupon'] && $this->is_coupon_type) {
                                $coupon_type_model = new VslCouponTypeModel();
                                $full_cut_list[$k]['rules'][$i]['coupon_type_id']   = $r['give_coupon'] ?: '';
                                $full_cut_list[$k]['rules'][$i]['coupon_type_name'] = $coupon_type_model::get($r['give_coupon'])['coupon_name'] ?: '';
                            } else {
                                $full_cut_list[$k]['rules'][$i]['coupon_type_id']   = '';
                                $full_cut_list[$k]['rules'][$i]['coupon_type_name'] = '';
                            }
                            //礼品券
                            if ($r['gift_card_id'] && $this->gift_voucher) {
                                $gift_voucher = new VslGiftVoucherModel();
                                $full_cut_list[$k]['rules'][$i]['gift_card_id'] = $r['gift_card_id'];
                                $giftvoucher_name = $gift_voucher->getInfo(['gift_voucher_id' => $r['gift_card_id']], 'giftvoucher_name')['giftvoucher_name'];
                                $full_cut_list[$k]['rules'][$i]['gift_voucher_name'] = $giftvoucher_name;//送优惠券
                            } else {
                                $full_cut_list[$k]['rules'][$i]['gift_card_id']      = '';
                                $full_cut_list[$k]['rules'][$i]['gift_voucher_name'] = '';
                            }
                            //赠品
                            if ($r['gift_id'] && $this->is_gift) {
                        
                                $gift_mdl = new VslPromotionGiftModel();
                                $full_cut_list[$k]['rules'][$i]['gift_id'] = $r['gift_id'];
                                $gift_name = $gift_mdl->getInfo(['promotion_gift_id' => $r['gift_id']], 'gift_name')['gift_name'];
                                $full_cut_list[$k]['rules'][$i]['gift_name'] = $gift_name;//送优惠券
                            } else {
                                $full_cut_list[$k]['rules'][$i]['gift_id']   = '';
                                $full_cut_list[$k]['rules'][$i]['gift_name'] = '';
                            }
                        }
                    }
                    
                }
            }
            
            # 优惠券
            
            $coupon_type_list = [];
            if ($this->is_coupon_type && !$mic_goods) {
                $coupon_server = new CouponServer();
                $coupon_type_info = $coupon_server->getGoodsCoupon([$goods_id], $this->uid);
               
                foreach ($coupon_type_info as $k => $c) {
                    $temp_coupon = [];
                    $temp_coupon['coupon_type_id']          = $c['coupon_type_id'];
                    $temp_coupon['coupon_name']             = $c['coupon_name'];
                    $temp_coupon['coupon_genre']            = $c['coupon_genre'];
                    $temp_coupon['shop_range_type']         = $c['shop_range_type'];
                    $temp_coupon['money']                   = $c['money'];
                    $temp_coupon['discount']                = $c['discount'];
                    $temp_coupon['at_least']                = $c['at_least'];
                    $temp_coupon['start_time']              = $c['start_time'];
                    $temp_coupon['end_time']                = $c['end_time'];
                    $temp_coupon['shop_id']                 = $c['shop_id'];
                    $coupon_type_list[]                     = $temp_coupon;
                }
            }
            
            # 返积分
            $config = new Config();
            $config_info            = $config->getShopConfig(0, $this->website_id);
            $give_point             = [];
            $give_point['is_point'] = $config_info['is_point'];
            $give_point['point']    = 0;
            if ($config_info['is_point'] == 1) {
                if ($goods_data['point_return_max'] > 0 || $goods_data['point_return_max'] == '') {
                    $price = 0;
                    if ($config_info['integral_calculation'] == 1 || $config_info['integral_calculation'] == 3) {
                        if ($is_seckill) {
                            $price = $goods_detail['min_price'] + $goods_data['shipping_fee'];
                        } else {
                            //$price = ($goods_data['price']*$member_discount*$promotion_info['discount_num'] / 10) + $goods_data['shipping_fee'];
                            $user_price = $goods_data['member_price'];          // todo... 会员折扣
                            $price = ($user_price * $promotion_info['discount_num'] / 10) + $goods_data['shipping_fee'];
                            if ($promotion_info['discount_type'] == 2) {
                                $price = $promotion_info['discount_num'] + $goods_data['shipping_fee'];
                            }
                        }
                    } elseif ($config_info['integral_calculation'] == 2) {
                        if ($is_seckill) {
                            $price = $goods_detail['min_price'];
                        } else {
                            //$price = $goods_data['price']*$member_discount*$promotion_info['discount_num'] / 10;
                            $user_price = $goods_data['member_price'];// todo... 会员折扣
                            $price = $user_price * $promotion_info['discount_num'] / 10;
                            if ($promotion_info['discount_type'] == 2) {
                                $price = $promotion_info['discount_num'] + $goods_data['shipping_fee'];
                            }
                        }
                    }
            
                    if ($goods_data['point_return_max'] > 0) {
                        $return_point = $price * $goods_data['point_return_max'] / 100;
                    } else {
                        $return_point = $price * $config_info['point_invoice_tax'] / 100;
                    }
                    $give_point['point'] = floor($return_point);
            
                    if($this->uid) {
                        //如果登录了，判断会员有没有会员卡，如果有，判断会员卡有没有额外返积分的权益
                        if(getAddons('membercard',$this->website_id)) {
                            $membercard = new MembercardSer();
                            $membercard_data = $membercard->checkMembercardStatus($this->uid);
                            if($membercard_data['status'] && $membercard_data['membercard_info']['is_give_point']) {
                                //有额外返积分的权益
                                if($membercard_data['membercard_info']['point_type'] == 1) {
                                    //倍数类型返佣
                                    $give_point['point'] = $give_point['point'] + $give_point['point'] * $membercard_data['membercard_info']['point_num'];
                                }elseif ($membercard_data['membercard_info']['point_type'] == 2) {
                                    //固定类型返佣
                                    $give_point['point'] = $give_point['point'] + $membercard_data['membercard_info']['point_num'];
                                }
                            }
                        }
                    }
                } else {
                    $give_point['is_point'] = 0;
                }
            }
    
            # 店铺信息
            if ($this->is_shop) {
                $shop_id            = $goods_detail['shop_id'];
                $shop_name          = $goods_detail['shop_name'];
                $shop_server        = new ShopAccount();
                $shopServer         = new ShopServer();
                $shop_score         = $shopServer->getShopEvaluate($shop_id);
                $shop_type_info     = $shop_server->getStoreInformation($shop_id, $shop_name);
        
                $shop_logo = '';
                $picture = new AlbumPictureModel();
                $shop_picture = $picture->getInfo(['pic_id' =>$shop_type_info['shop_logo']],'pic_cover,pic_cover_mid,pic_cover_micro');
                if (!empty($shop_picture)) {
                    $shop_logo = getApiSrc($shop_picture['pic_cover']);
                }
                
        
                $shop_type_info['shop_logo']            = $shop_logo;
                $shop_type_info['comprehensive']        = $shop_score['comprehensive'];
                $shop_type_info['shop_deliverycredit']  = $shop_score['shop_stic'];
                $shop_type_info['shop_desccredit']      = $shop_score['shop_desc'];
                $shop_type_info['shop_servicecredit']   = $shop_score['shop_service'];
            } else {
                $shop_type_info = [];
            }
    
            # 分销商
            if ($this->is_distribution && $this->uid) {
                $is_distributor = VslMemberModel::get(['uid' => $this->uid])['isdistributor'] == 2 ? true : false;
            }
            
            $commission = '';
            $dis_point  = '';
            if ($this->uid) {
                if ($is_seckill) {
                    $price = $goods_detail['min_price'] + $goods_data['shipping_fee'];
                } else {
                    //$price = $goods_data['price']*$member_discount*$promotion_info['discount_num'] / 10;
                    $user_price = $goods_data['member_price'];
                    // $price = $user_price * $promotion_info['discount_num'] / 10;
                    $min_sku_price = $goods_server->getMinSkuPrice($goods_id);
                    $poster_price = $goods_server->getPromotionPrice($goods_data['promotion_type'], $goods_id, $min_sku_price, $goods_data['shop_id'], $goods_data['website_id']);
                    
                    $price = $poster_price ? $poster_price : $user_price; //原活动价格获取失效或者已经变更，导致获取的活动后价格失效，变更至获取海报价格 -2020/08/26
                   
                }
                
                $member = new VslMemberModel();
                $distribution = $member->getInfo(['uid' => $this->uid], 'isdistributor');
                if ($distribution['isdistributor'] == 2) {// 分销商
                    $distribution = new Distributor();
                    $info = $distribution->getGoodsCommission($this->website_id, $goods_id, $this->uid, $price);
                    $commission = $info['commission'];
                    $dis_point = $info['point'];
                }
            }
            
            # 返佣金和积分 getGoodsCommissionList 最新更新 已废弃该商品列表佣金查询
    
            # 区块链
            if (getAddons('blockchain', $this->website_id)) {
                $block = new Block();
                $blockchain_info = $block->getGoodsInfo();
                $service_charge  = $blockchain_info['service_charge'];
                $eth_market      = $blockchain_info['eth_market'];
                $eos_market      = $blockchain_info['eos_market'];
            } else {
                $service_charge  = '';
                $eth_market      = '';
                $eos_market      = '';
            }
    
            # 商城配置的配送方式
            $config = new Config();
            $has_express = $config->getConfig(0,'HAS_EXPRESS',$this->website_id, 1);
            $has_express = $has_express !=0 ? '' : 1;
    
            # 门店
            if($this->is_store) {
            $store_server = new Store();
            $storeSet = $store_server->getStoreSet($this->instance_id)['is_use'];
            $has_store = $storeSet? 1 : 0;
            }else{
                $has_store = 0;
            }
    
            # 会员折扣
            if ($promotion_info['discount_type'] == 2) {
                $discount = 1;
                $member_discount = 1;
            } else {
                $discount = $promotion_info['discount_num'] / 10;
            }
            if ($promotion_info['integer_type'] == 1) {
                $member_is_label = $promotion_info['integer_type'];
            }
/*            //会员折扣 限时折扣变更为无
            $discount = 1;
            $member_discount = 1;   group_price */
            
            $poster_price_sku = $goods_detail['sku']['list'][0]['price'];
            $goods_detail['poster_price'] = $poster_price ? $poster_price : $poster_price_sku;
            $return_data =[
                'service_charge'        => $service_charge,
                'eth_market'            => $eth_market,
                'eos_market'            => $eos_market,
                'commission'            => $commission,
                'dis_point'             => $dis_point,
                'goods_detail'          => $goods_detail,//主要商品详情内容 
                'full_cut_list'         => $full_cut_list,
                'coupon_type_list'      => $coupon_type_list,
                'shop_type_info'        => $shop_type_info,
                'seckill_list'          => $seckill_list,
                'bargain_list'          => $bargain_list,
                'member_discount'       => $member_discount,
                'limit_discount'        => $discount,
                'is_distributor'        => $is_distributor,
                'is_presell'            => $is_presell,
                'group_list'            => $group_list,
                'presell_list'          => $presell_list,
                'limit_list'            => $limit_discount_info,
                'give_point'            => $give_point,
                'is_allow_buy'          => $is_allow_buy,
                'is_allow_browse'       => $is_allow_browse,
                'member_is_label'       => $member_is_label,
                'discount_choice'       => $discount_choice,
                'has_express'           => $has_express,
                'has_store'             => $has_store,
                'luckyspell_list'       => $luckyspell_list
            ];
            

            return json(AjaxReturn(SUCCESS, $return_data));
        } catch (\Exception $e) {
            return json(AjaxReturn(SYSTEM_DELETE_FAIL,[], $e->getMessage()));
        }
    }
    /**
     * 获取预约表单内容
     */
    public function scheduleDetail(){
        $goods_id = request()->post('goods_id', '');
        $custom_id = request()->post('custom_id', '22');
        if(empty($custom_id)){
            $data['code'] = -1;
            $data['message'] = '获取失败,表单id或者商品id错误';
            return json($data);
        }
        //开始组装信息
        $custom = new CustomFormServer();
        $result = $custom->getCustomFormDetaillist($custom_id);
        
        $data['code'] = 0;
        $data['message'] = '获取成功';
        $data['data'] = $result;
        return json($data);
    }
    
    /**
     * 装修营销活动商品数据(前端)
     * @return \multitype
     */
    public function getCustomPromotionList ()
    {
   
        $promotion_type = request()->post('promotion_type');//促销类型 1秒杀，2团购, 3预售, 4砍价，5限时折扣,6幸运拼
        $page_index = request()->post('page_index',1);
        $page_size = request()->post('page_size',PAGESIZE);
        $order = request()->post('order');//1价格 2数量 3开始时间 4结束时间 5收藏数
        $sort = request()->post('sort');//升序asc 降序 desc
        $goods_type = request()->post('goods_type');//0自营 1全平台 2店铺(需要传shop_id)
        $shop_id = request()->post('shop_id');// $goods_type=2时需要
        $recommend_type = request()->post('recommend_type');//推荐类型0自动 1手动
        $goods_ids = request()->post('goods_ids');//$recommend_type=1时有
        //秒杀数据 (暂时不写入该接口)
        $condition_time = request()->post('condition_time') ?: '';
        $condition_day = request()->post('condition_day') ?: '';
        $tag_status = request()->post('tag_status') ?: '';

        if (!$promotion_type){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        if ($recommend_type == 1 && !$goods_ids){
            return json(['code' => 1, 'message' => '获取成功', 'data' => []]);
        }
        
        $condition = [
            'promotion_type' => $promotion_type,
            'page_index' => $page_index,
            'page_size' => $page_size,
            'order' => $order,
            'sort' => $sort,
            'goods_type' => $goods_type,
            'shop_id' => $shop_id,
            'recommend_type' => $recommend_type,
            'goods_ids' => $goods_ids,
            'condition_time' => $condition_time,
            'condition_day' => $condition_day,
            'tag_status' => $tag_status,
        ];
        $goodsSer = new GoodsService();
        $return_list = $goodsSer->getCustomPromotionGoodsListForWap($condition);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $return_list]);
    }
}
