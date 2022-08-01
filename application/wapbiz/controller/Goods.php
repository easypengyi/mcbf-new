<?php

namespace app\wapbiz\controller;

use addons\miniprogram\controller\Miniprogram;
use addons\miniprogram\model\WeixinAuthModel;
use data\extend\QRcode;
use data\model\WebSiteModel;
use data\service\Goods as GoodsService;
use data\service\GoodsCategory as GoodsCategory;
use data\service\Album;
use addons\miniprogram\service\MiniProgram as MiniProgramSer;
use data\service\Express as Express;

/**
 * 商品控制器
 */
class Goods extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 获取商品分类
     */

    public function getCategoryListForLink()
    {
        $goods_category = new GoodsCategory();
        $id = (int)request()->post('id', 0);
        $condition['website_id'] = $this->website_id;
        $condition['pid'] = $id;
        $goods_category_list = $goods_category->getGoodsCategoryList(1, 0, $condition,'sort desc', 'category_id,category_name,short_name,pid,attr_id,sort,level,website_id');
        $goods_category_list['category_list'] = $goods_category_list['data'];
        unset($goods_category_list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $goods_category_list]);
    }

    /**
     * 自营商品列表
     */
    public function selfgoodsList()
    {
        $goodservice = new GoodsService();
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $goods_name = request()->post('search_text', '');
        $type = request()->post('type', 0);
        if ($type) {
            switch ($type) {
                case 1:
                    $condition['ng.state'] = 1;
                    break;
                case 2:
                    $condition['ng.state'] = 0;
                    break;
                case 3:
                    $condition['ng.stock'] = ['<=', '0'];
                    break;
                case 4:
                    $condition['ng.min_stock_alarm'] = array(
                        "neq",
                        0
                    );
                    $condition['ng.stock'] = array(
                        "exp",
                        "<= ng.min_stock_alarm"
                    );
                    $condition['ng.state'] = 1;
                    break;
                default :
                    break;
            }
        }
        if (!empty($goods_name)) {
            $condition["ng.goods_name"] = array(
                "like",
                "%" . $goods_name . "%"
            );
        }
        $condition["ng.shop_id"] = $this->instance_id;
        if ($this->module == 'supplier') {
            $condition['ng.supplier_id'] = $this->supplier_id;
        }
//        else {
//            $condition["ng.shop_id"] = $this->instance_id;
//        }
        $condition["ng.website_id"] = $this->website_id;
        $result = $goodservice->getGoodsList($page_index, $page_size, $condition, [
            'ng.create_time' => 'desc'
        ],'ng.goods_id,ng.category_id,ng.brand_id,ng.picture,ng.shop_id,ng.website_id,sap.pic_id,ng.goods_name,sap.pic_cover,ng.stock,ng.sales+ng.real_sales as sales,ng.price,ng.promotion_type');
        $result['goods_list'] = [];
        if($result['data']){
            foreach($result['data'] as $key => $val){
                $result['goods_list'][$key]['goods_id'] = $val['goods_id'];
                $result['goods_list'][$key]['shop_id'] = $val['shop_id'];
                $result['goods_list'][$key]['website_id'] = $val['website_id'];
                $result['goods_list'][$key]['goods_name'] = $val['goods_name'];
                $result['goods_list'][$key]['pic_cover'] = getApiSrc($val['pic_cover']);
                $result['goods_list'][$key]['stock'] = $val['stock'];
                $result['goods_list'][$key]['sales'] = $val['sales'];
                $result['goods_list'][$key]['price'] = $val['price'];
                $result['goods_list'][$key]['promotion_type'] = $val['promotion_type'];
            }
            unset($val);
        }
        unset($result['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $result]);
    }

    public function getGoodsDetailQr()
    {
        $goods_id = request()->get('goods_id', 0);
        $coupon_type_id = request()->get('coupon_type_id', 0);
        $gift_voucher_id = request()->get('gift_voucher_id', 0);
        $voucher_package_id = request()->get('voucher_package_id', 0);
        $wheelsurf_id = request()->get('wheelsurf_id', 0); //大转盘
        $smash_egg_id = request()->get('smash_egg_id', 0); //砸金蛋
        $scratch_card_id = request()->get('scratch_card_id', 0); //刮刮乐
        $qr_type = request()->get('qr_type', 0);
        $wap_path = request()->get('wap_path', '');
        $mp_page = request()->get('mp_path', '');
        //直播id
        $live_id = request()->get('live_id', '');
        //主播id
        $anchor_id = request()->get('anchor_id', '');
        //小程序后台url参数获取
        $room_id = request()->get('room_id', 0);
        $type = request()->get('type', 0);
        $website_model = new WebSiteModel();
        $website_info = $website_model::get(['website_id' => $this->website_id]);
        $is_ssl = \think\Request::instance()->isSsl();
        $ssl = $is_ssl ? 'https://' : 'http://';
        if ($website_info['realm_ip']) {
            $domain_name = $ssl . $website_info['realm_ip'];
        } else {
            $ip = top_domain($_SERVER['HTTP_HOST']);
            $domain_name = $ssl . top_domain($website_info['realm_two_ip']) . '.' . $ip;
        }
        ob_start();
//        $mp_id = '';
        $scene = [];
        if ($goods_id) {
//            $id = $goods_id;
//            $mp_id = '&goods_id='.$goods_id;
            $id = '?goods_id=' . $goods_id;
            $scene['goods_id'] = $goods_id;
        } elseif ($coupon_type_id) {
//            $id = $coupon_type_id;
//            $mp_id = '&coupon_type_id='.$coupon_type_id;
            $id = '?coupon_type_id=' . $coupon_type_id;
            $scene['coupon_type_id'] = $coupon_type_id;
        } elseif ($gift_voucher_id) {//礼品券
//            $id = $gift_voucher_id;
//            $mp_id = '&gift_voucher_id='.$gift_voucher_id;
            $id = '?gift_voucher_id=' . $gift_voucher_id;
            $scene['gift_voucher_id'] = $gift_voucher_id;
        } elseif ($voucher_package_id) {//券包
//            $id = $voucher_package_id;
//            $mp_id = '&voucher_package_id='.$voucher_package_id;
            $id = '?voucher_package_id=' . $voucher_package_id;
            $scene['voucher_package_id'] = $voucher_package_id;
        } elseif ($wheelsurf_id) {//大转盘
//            $id = $wheelsurf_id;
//            $mp_id = '&wheelsurf_id='.$wheelsurf_id;
            $id = '?wheelsurf_id=' . $wheelsurf_id;
            $scene['wheelsurf_id'] = $wheelsurf_id;
        } elseif ($smash_egg_id) {//砸金蛋
//            $id = $smash_egg_id;
//            $mp_id = '&smash_egg_id='.$smash_egg_id;
            $id = '?smash_egg_id=' . $smash_egg_id;
            $scene['smash_egg_id'] = $smash_egg_id;
        } elseif ($scratch_card_id) {//刮刮乐
//            $id = $scratch_card_id;
//            $mp_id = '&scratch_card_id='.$scratch_card_id;
            $id = '?scratch_card_id=' . $scratch_card_id;
            $scene['scratch_card_id'] = $scratch_card_id;
        } elseif ($live_id) {
            $id = $live_id;
//            $mp_id = '&live_id='.$live_id;
            $scene['live_id'] = $live_id;
        }
        if ($anchor_id) {
            $id2 = $anchor_id;
//            $mp_id = '&anchor_id='.$anchor_id;
            $scene['anchor_id'] = $anchor_id;
        }
//        $mp_id = ltrim($mp_id, '&') ?: -1;
        if ($qr_type == 1) {
            //拼接出手机端的商品详情链接
            $wap_url = $domain_name . $wap_path . $id;
            QRcode::png($wap_url, false, 'L', '10', 0, false);
            $obcode = ob_get_clean();
            $code = imagecreatefromstring($obcode);
            header("Content-type: image/png");
            imagepng($code);
        } elseif ($qr_type == 3) {
            //拼接出小程序的直播间二维码
            $mp_url = $mp_page;
            $params = [
                'scene' => -1,
                'page' => $mp_url,
            ];
            $wx_auth_model = new WeixinAuthModel();
//            $wchat_open = new WchatOpen($this->website_id);
            $mp_info = $wx_auth_model->getInfo(['website_id' => $this->website_id], 'authorizer_access_token');
            if (empty($mp_info)) {
                return json(['code' => -1, 'message' => '参数错误！']);
            }
            ob_get_clean();
//            $imgRes = $wchat_open->getSunCodeApi($mp_info['authorizer_access_token'], $params, 2);
            $mpController = new Miniprogram();
            $imgRes = $mpController->getUnLimitMpCode($params, true);
            if ($imgRes['code'] < 0) {
                return $imgRes;
            }
            $code = imagecreatefromstring($imgRes['data']);
            header("Content-type: image/png");
            imagepng($code);
            exit;
        } else {
            $mpController = new Miniprogram();
//            if (cache($this->website_id.'mp_new_code')) {
            $miniSer = new MiniProgramSer();
            if ($miniSer->getNewCode()) {
                //组装太阳码参数
                $params = [
                    'scene' => json_encode($scene),
                    'page' => $mp_page
                ];
            } else {
                //拼接出小程序的商品详情链接
//            $mp_page = 'pages/goods/detail/index';
                $params = [
                    'scene' => '-1_' . $id,
                    'page' => $mp_page,
                    'width' => 280
                ];
                if ($id2) {
                    $params['scene'] = '-1_' . $id . '_' . $id2;
                }
            }
            $wx_auth_model = new WeixinAuthModel();
//            $wchat_open = new WchatOpen($this->website_id);
            $mp_info = $wx_auth_model->getInfo(['website_id' => $this->website_id], 'authorizer_access_token');
            if (empty($mp_info)) {
                return json(['code' => -1, 'message' => '参数错误！']);
            }
            ob_get_clean();
//            $imgRes = $wchat_open->getSunCodeApi($mp_info['authorizer_access_token'], $params, 2);
            $imgRes = $mpController->getUnLimitMpCode($params, true);
            if ($imgRes['code'] < 0) {
                return $imgRes;
            }
            $code = imagecreatefromstring($imgRes['data']);
            header("Content-type: image/png");
            imagepng($code);
            exit;
        }
    }


    /**
     * 发布商品
     */
    public function goodsInfo()
    {
        $goods = new GoodsService();
        $goodsId = (int)request()->post("goods_id", 0);
        if (!$goodsId) {
            return json(AjaxReturn(-1006));
        }
        $goods_info = $goods->getGoodsDetail($goodsId, 1);
        if (!$goods_info) {
            return json(['code' => -1, 'message' => '商品不存在']);
        }
        if($goods_info['supplier_goods_id']){
            return json(['code' => -1, 'message' => '供应商商品暂时只支持pc端后台修改']);
        }
        $data = [];
        $data['goods_id'] = $goods_info['goods_id'];
        $data['goods_type'] = $goods_info['goods_type'];
        $data['goods_name'] = $goods_info['goods_name'];
        $data['short_name'] = $goods_info['short_name'];
        $data['category_name'] = $goods_info['category_name'];
        $data['category_id'] = $goods_info['category_id'];
        $data['category_id_1'] = $goods_info['category_id_1'];
        $data['category_id_2'] = $goods_info['category_id_2'];
        $data['category_id_3'] = $goods_info['category_id_3'];
        $data['promotion_type'] = $goods_info['promotion_type'];
        $data['price'] = $goods_info['price'];
        $data['cost_price'] = $goods_info['cost_price'];
        $data['market_price'] = $goods_info['market_price'];
        $data['stock'] = $goods_info['stock'];
        $data['item_no'] = $goods_info['item_no'];
        $data['code'] = $goods_info['code'];
        $data['state'] = $goods_info['state'];
        $data['shipping_fee'] = $goods_info['shipping_fee'];
        $data['shipping_fee_id'] = $goods_info['shipping_fee_id'];
        $data['shipping_fee_type'] = $goods_info['shipping_fee_type'];
        $data['goods_weight'] = $goods_info['goods_weight'];
        $data['goods_volume'] = $goods_info['goods_volume'];
        $data['goods_attribute_id'] = $goods_info['goods_attribute_id'];
        $data['goods_volume'] = $goods_info['goods_volume'];
        $data['max_buy'] = $goods_info['max_buy'];
        $data['sku_list'] = [];
        $data['img_list'] = [];
        $data['sub_title'] = $goods_info['sub_title'];
        $data['activity_pic'] = $goods_info['activity_pic'];
        $data['activity_pic_url'] = $goods_info['activity_pic_url'];
        $data['goods_attribute_list'] = [];
        $data['sku_list'] = $goods_info['sku_list'];
        $data['store_list'] = $goods_info['store_list'];
        $data['sku_picture_vlaues'] = [];
        if($goods_info['img_list']){
            foreach($goods_info['img_list'] as $key_il => $val_il){
                $data['img_list'][$key_il]['pic_cover'] = $val_il['pic_cover'];
                $data['img_list'][$key_il]['pic_id'] = $val_il['pic_id'];
            }
            unset($val_il);
        }
        if($goods_info['goods_attribute_list']){
            foreach($goods_info['goods_attribute_list'] as $key_gal => $val_gal){
                if(!isset($val_gal['attr_id'])){
                    continue;
                }
                $data['goods_attribute_list'][$key_gal]['attr_id'] = $val_gal['attr_id'];
                $data['goods_attribute_list'][$key_gal]['attr_value_id'] = $val_gal['attr_value_id'];
                $data['goods_attribute_list'][$key_gal]['attr_value'] = $val_gal['attr_value'];
                $data['goods_attribute_list'][$key_gal]['attr_value_name'] = $val_gal['attr_value_name'];
            }
            unset($val_gal);
        }
        
        if (trim($goods_info['goods_spec_format']) != "") {

            $goods_spec_array = json_decode($goods_info['goods_spec_format'], true);
            $album = new Album();
            if ($goods_spec_array) {
                foreach ($goods_spec_array as $k => $v) {
                    foreach ($v["value"] as $t => $m) {
                        if (is_numeric($m["spec_value_data"]) && $v["show_type"] == 3) {//图片id
                            $data['sku_picture_vlaues'][$t]['spec_id'] = $m["spec_id"];
                            $data['sku_picture_vlaues'][$t]['spec_value_id'] = $m["spec_value_id"];
                            $data['sku_picture_vlaues'][$t]['img_ids'] = $m["spec_value_data"];
                            $picture_detail = $album->getAlubmPictureDetail([
                                "pic_id" => $m["spec_value_data"]
                            ]);
                            if (!empty($picture_detail)) {
                                $goods_spec_array[$k]["value"][$t]["spec_value_data_src"] = getApiSrc($picture_detail["pic_cover_micro"]);
                            }
                        } elseif (!is_numeric($m["spec_value_data"]) && $v["show_type"] == 3) {//图片路径
                            $goods_spec_array[$k]["value"][$t]["spec_value_data_src"] = getApiSrc($m["spec_value_data"]);
                        }
                    }
                }
            }
        }
        $data['goods_spec_format'] = $goods_spec_array;
        $data['description'] = str_replace(PHP_EOL, '', $goods_info['description']);
        $data['description'] = str_replace("'", "", htmlspecialchars_decode($goods_info['description']));
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }

    /**
     * 根据商品类型id查询，商品规格信息
     *
     */
    public function getGoodsSpecListByAttrId()
    {
        $goods = new GoodsService();
        $condition["attr_id"] = (int)request()->post("attr_id", 0);
        $condition['is_use'] = 1;
        $list = $goods->getGoodsAttrSpecQuery($condition);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

    /**
     * 删除商品
     */
    public function deleteGoods()
    {
        $goods_ids = request()->post('goods_ids');
        $goodservice = new GoodsService();
        $retval = $goodservice->deleteGoods($goods_ids);
        $this->addUserLogByParam("删除商品", $goods_ids);
        return json(AjaxReturn($retval));
    }

    /**
     * 删除回收站商品
     */
    public function emptyDeleteGoods()
    {
        $goods_ids = request()->post('goods_ids');
        $goodsservice = new GoodsService();
        $goods_string = '';
        if (is_array($goods_ids)) {
            foreach ($goods_ids as $k => $v) {
                $goods_string .= $v . ',';
            }
            $goods_ids = substr($goods_string, 0, -1);
        }
        $res = $goodsservice->deleteRecycleGoods($goods_ids);
        $this->addUserLogByParam("删除回收站商品", $goods_ids);
        return json(AjaxReturn($res));
    }

    /**
     * 功能说明：添加或更新商品时 ajax调用的函数
     */
    public function goodsCreateOrUpdate()
    {
        $product = request()->post('product/a');
        
        if (!$product) {
            return json(AjaxReturn(-1006));
        }
        //$product = json_decode($product, true);
        //计时计次商品
        $verificationinfo = array();
        if ($product['goods_type'] != 1 && $product['goods_type'] != 6) {
           return json(['code' => -1, 'message' => '该商品类型无法发布或编辑']);
        }
//        $shopId = $this->port == 'supplier' ? -1 :$this->instance_id;
        $shopId = $this->instance_id;
        $goodservice = new GoodsService();
        //核销门店
        $store_list = '';
        if (!empty($product['store_list'])) {
            if(!is_array($product['store_list'])){
                $product['store_list'] = explode(',', $product['store_list']);
            }
            foreach ($product['store_list'] as $k => $v) {
                $store_list .= $v . ',';
            }
        }
        if(!$product['title'] ||!$product['category_id']||!$product['picture'] ||!$product['imageArray'] ||!$product['goods_type']){
            return json(AjaxReturn(-1006));
        }
        $verificationinfo['store_list'] = substr($store_list, 0, -1);
        $goods_data = [
            'goods_id' => (int)$product["goods_id"], // 商品Id
            'goods_name' => $product["title"], // 商品标题
            'shopid' => $shopId,
            'category_id' => (int)$product["category_id"], // 商品类目
            'supplier_id' => $this->supplier_id,
            'goods_type' => $product['goods_type'],
            'market_price' => $product["market_price"],
            'price' => $product["price"], // 商品现价
            'cost_price' => $product["cost_price"],
            'shipping_fee' => $product["shipping_fee"],
            'shipping_fee_id' => (int)$product["shipping_fee_id"],
            'stock' => $product["stock"],
            'max_buy' => $product['max_buy'],
            'picture' => $product["picture"],
            'description' => $product["description"],
            'code' => $product["code"],
            'img_id_array' => $product["imageArray"],
            'sku_array' => $product["skuArray"], //规格组
            'state' => (int)$product["state"],
            'goods_attribute_id' => (int)$product['goods_attribute_id'],
            'goods_attribute' => $product['goods_attribute'],
            'goods_spec_format' => $product['goods_spec_format'],
            'goods_weight' => $product['goods_weight'],
            'goods_volume' => $product['goods_volume'],
            'shipping_fee_type' => $product['shipping_fee_type'],
            'sku_picture_values' => $product["sku_picture_vlaues"],
            'item_no' => $product['item_no'],
            'verificationinfo' => $verificationinfo, //核销信息,
            'goods_count' => $product["goods_count"],
            'sub_title' => $product["subTitle"], // 商品标题
            'activity_pic' => $product["activityPic"], // 活动图
        ];

        $res = $goodservice->addOrEditGoodsForBiz($goods_data);
        $message = '操作失败';
        if ($res) {
            if ($product["goods_id"]) {
                $message = '编辑成功';
                $this->addUserLogByParam('更新商品', $product["goods_id"] . '-' . $product["title"]);
            } else {
                $message = '添加成功';
                $this->addUserLogByParam('添加商品', $product["title"]);
            }
        }
        $dataa['code'] = $res;
        $dataa['message'] = $message;
        return json($dataa);
    }

    /**
     * 商品上架
     */
    public function modifyGoodsOnline()
    {
        $condition = request()->post('goods_ids', ''); // 将商品id用,隔开
        $goods_detail = new GoodsService();
        $result = $goods_detail->ModifyGoodsOnline($condition);
        $this->addUserLogByParam("商品上架", $condition);
        return json(AjaxReturn($result));
    }

    /**
     * 商品下架
     */
    public function modifyGoodsOffline()
    {
        $condition = request()->post('goods_ids', ''); // 将商品id用,隔开
        $goods_detail = new GoodsService();
        $result = $goods_detail->ModifyGoodsOffline($condition);
        $this->addUserLogByParam("商品下架", $condition);
        return json(AjaxReturn($result));
    }




    /**
     * 商品回收站列表
     */
    public function recycleList()
    {
        $goodservice = new GoodsService();
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $goods_name = request()->post('goods_name', '');

        if (!empty($goods_name)) {
            $condition["ng.goods_name"] = array(
                "like",
                "%" . $goods_name . "%"
            );
        }
        $condition["ng.shop_id"] = $this->instance_id;
        if ($this->module == 'supplier') {
            $condition['ng.supplier_id'] = $this->supplier_id;
//            $condition["ng.shop_id"] = -1;
        }
//        else {
//            $condition["ng.shop_id"] = $this->instance_id;
//        }
        $condition["ng.website_id"] = $this->website_id;
        $result = $goodservice->getGoodsDeletedList($page_index, $page_size, $condition, "ng.create_time desc");
        $result['goods_list'] = [];
        if($result['data']){
            foreach($result['data'] as $key => $val){
                $result['goods_list'][$key]['goods_id'] = $val['goods_id'];
                $result['goods_list'][$key]['shop_id'] = $val['shop_id'];
                $result['goods_list'][$key]['website_id'] = $val['website_id'];
                $result['goods_list'][$key]['goods_name'] = $val['goods_name'];
                $result['goods_list'][$key]['pic_cover'] = getApiSrc($val['pic_cover_mid']);
                $result['goods_list'][$key]['stock'] = $val['stock'];
                $result['goods_list'][$key]['sales'] = $val['sales'];
                $result['goods_list'][$key]['price'] = $val['price'];
            }
            unset($val);
        }
        unset($result['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $result]);
    }

    /**
     * 回收站商品恢复
     */
    public function regainGoodsDeleted()
    {
        $goods_ids = request()->post('goods_id', '');
        if(!$goods_ids){
            return json(AjaxReturn(-1006));
        }
        $goods_list = '';
        if (is_array($goods_ids)) {
            foreach ($goods_ids as $k => $v) {
                $goods_list .= $v . ',';
            }
            $goods_list = substr($goods_list, 0, -1);
        } else {
            $goods_list = $goods_ids;
        }

        $goods = new GoodsService();
        $res = $goods->regainGoodsDeleted($goods_list);
        $this->addUserLogByParam("回收站商品恢复", $res);
        return json(AjaxReturn($res));
    }
    
    public function getShippingList(){
        $express = new Express();
        $shi_condition['website_id'] = $this->website_id;
        $shi_condition['shop_id'] = $this->instance_id;
        $shi_condition['is_enabled'] = 1;
        $shipping_list = $express->shippingFeeQuery($shi_condition);
        $new_list = [];
        if($shipping_list){
            foreach($shipping_list as $key => $val){
                $new_list[$key]['shipping_fee_id'] = $val['shipping_fee_id'];
                $new_list[$key]['shipping_fee_name'] = $val['shipping_fee_name'];
                $new_list[$key]['is_default'] = $val['is_default'];
                $new_list[$key]['co_id'] = $val['co_id'];
                $new_list[$key]['calculate_type'] = $val['calculate_type'];
            }
            unset($val);
        }
        return json(['code' => 1, 'message' => '获取成功','data' => $new_list]);
    }
    /**
 * 获取筛选后的商品
 *
 * @return unknown
 */
    public function getSerchGoodsList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $shop_range_type = (int)request()->post('shop_range_type', 0);
        $search_text = request()->post("search_text", '');
        $category_id_1 = (int)request()->post('category_id_1', '');
        $category_id_2 = (int)request()->post('category_id_2', '');
        $category_id_3 = (int)request()->post('category_id_3', '');
        if ($category_id_3 != "") {
            $condition["category_id_3"] = $category_id_3;
        } elseif ($category_id_2 != "") {
            $condition["category_id_2"] = $category_id_2;
        } elseif ($category_id_1 != "") {
            $condition["category_id_1"] = $category_id_1;
        }
        if($search_text){
            $condition['goods_name'] = array(
                "like",
                "%" . $search_text . "%"
            );
        }

        if ($shop_range_type == 1 || $this->port != 'platform') {
            $condition['shop_id'] = $this->instance_id;
        }
        //判断是否开启店铺应用
        if (getAddons('shop', $this->website_id) == 0) {
            $condition['shop_id'] = 0;
        }
        $condition['state'] = 1;//正常的商品 0-下架 1-正常 2-禁售
        $condition['website_id'] = $this->website_id;
        $goods_detail = new GoodsService();
        $result = $goods_detail->getSearchGoodsList($page_index, $page_size, $condition);
        $result['goods_list'] = [];
        if($result['data']){
            $result['goods_list'] = array_columns($result['data'], 'goods_id,shop_id,picture_info,goods_name,stock,shop_name');
            foreach($result['goods_list'] as $key => $val){
                $result['goods_list'][$key]['picture'] = $val['picture_info'] ? getApiSrc($val['picture_info']['pic_cover_micro']) : '';
                unset($result['goods_list'][$key]['picture_info']);
            }
            unset($val);
        }
        unset($result['data']);
        return json(['code' => 1, 'message' => '获取成功','data' => $result]);
    }
 

}
