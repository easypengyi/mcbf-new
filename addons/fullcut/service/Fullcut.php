<?php

namespace addons\fullcut\service;  

use addons\coupontype\model\VslCouponModel;
use addons\gift\model\VslMemberGiftModel;
use addons\giftvoucher\model\VslGiftVoucherRecordsModel;
use addons\shop\model\VslShopModel;
use data\model\AlbumPictureModel as AlbumPictureModel;
use addons\coupontype\model\VslCouponTypeModel;
use addons\fullcut\model\VslPromotionMansongGoodsModel;
use addons\fullcut\model\VslPromotionMansongModel;
use addons\fullcut\model\VslPromotionMansongRuleModel;
use data\service\BaseService as BaseService;
use addons\giftvoucher\model\VslGiftVoucherModel;
use think\Db;
use data\service\AddonsConfig as AddonsConfigService;
use addons\gift\model\VslPromotionGiftModel;
use data\service\Goods as GoodsService;

/**
 * 店铺设置控制器
 *
 * @author  www.vslai.com
 *
 */
class Fullcut extends BaseService
{

    private $goods_ser;
    public function __construct()
    {
        parent::__construct();
        $this->goods_ser = new GoodsService();
    }




    //更改活动状态
    public function updateMansongStatus($data,$where){

        $promotion_discount = new VslPromotionMansongModel();
        $retval = $promotion_discount->save($data,$where);
        return $retval;

    }

    public function getPromotionMansongList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc')
    {
        $promotion_mansong = new VslPromotionMansongModel();

        $list = $promotion_mansong->pageQuery($page_index, $page_size, $condition, $order, '*');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if ($v['status'] == 0) {
                    $list['data'][$k]['status_name'] = '未开始';
                }
                if ($v['status'] == 1) {
                    $list['data'][$k]['status_name'] = '进行中';
                }
                if ($v['status'] == 2) {
                    $list['data'][$k]['status_name'] = '已取消';
                }
                if ($v['status'] == 3) {
                    $list['data'][$k]['status_name'] = '已失效';
                }
                if ($v['status'] == 4) {
                    $list['data'][$k]['status_name'] = '已结束';
                }
                //未开始
                if($v['start_time']>time() && $v['end_time']>time()){
                    $this->updateMansongStatus(['status'=>0],['mansong_id'=>$v['mansong_id']]);
                    $list['data'][$k]['status'] = 0;
                    $list['data'][$k]['status_name'] = '未开始';
                }

                //开始
                if($v['start_time']<time() && $v['end_time']>time() && $v['status']==0){
                    $this->updateMansongStatus(['status'=>1],['mansong_id'=>$v['mansong_id']]);
                    $list['data'][$k]['status'] = 1;
                    $list['data'][$k]['status_name'] = '进行中';
                }

                //结束
                if($v['start_time']<time() && $v['end_time']<time()){
                    $this->updateMansongStatus(['status'=>4],['mansong_id'=>$v['mansong_id']]);
                    $list['data'][$k]['status'] = 4;
                    $list['data'][$k]['status_name'] = '已结束';
                }
            }
        }
        return $list;
    }

    /**
     * (non-PHPdoc) 
     *
     * @see \data\api\IPromote::addPromotionMansong()
     */
    public function addPromotionMansong($mansong_name, $start_time, $end_time, $shop_id, $remark, $type, $range_type, $rule, $goods_id_array, $range, $status, $level,$category_extend_id,$extend_category_id_1s,$extend_category_id_2s,$extend_category_id_3s)
    {
        $promot_mansong = new VslPromotionMansongModel();
        $promot_mansong->startTrans();
        try {
            $err = 0;
            $shop_name = $this->instance_name;
            $data = array(
                'mansong_name' => $mansong_name,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'shop_id' => $shop_id,
                'shop_name' => $shop_name,
                'remark' => $remark,
                'type' => $type,
                'range_type' => $range_type,
                'create_time' => time(),
                'range' => $range,
                'status' => $status,
                'level' => $level,
                'website_id' => $this->website_id,
                'category_extend_id'=>$category_extend_id,
                'extend_category_id_1s'=>$extend_category_id_1s,
                'extend_category_id_2s'=>$extend_category_id_2s,
                'extend_category_id_3s'=>$extend_category_id_3s
            );
            $promot_mansong->save($data);
            $mansong_id = $promot_mansong->mansong_id;
            // 添加活动规则表
            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $get_rule = explode(',', $v);
                $data_rule = array(
                    'mansong_id' => $mansong_id,
                    'price' => $get_rule[0],
                    'discount' => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_coupon' => $get_rule[3],
                    'gift_id' => $get_rule[4],
                    'gift_card_id' => $get_rule[5]
                );
                $promot_mansong_rule = new VslPromotionMansongRuleModel();
                $promot_mansong_rule->save($data_rule);
            }
            // 满减送商品表
            if ($range_type == 0 && !empty($goods_id_array)) {
                // 部分商品
                //$goods_id_array = explode(',', $goods_id_array);
                foreach ($goods_id_array as $k => $v) {
                    $promotion_mansong_goods = new VslPromotionMansongGoodsModel();
                    // 查询商品名称图片
                    $goods_info =  $this->goods_ser->getGoodsDetailById($v, 'goods_name,picture');
                    $data_goods = array(
                        'mansong_id' => $mansong_id,
                        'goods_id' => $v,
                        'goods_name' => $goods_info['goods_name'],
                        'goods_picture' => $goods_info['picture'],
                        'status' => 0, // 状态重新设置
                        'start_time' => getTimeTurnTimeStamp($start_time),
                        'end_time' => getTimeTurnTimeStamp($end_time)
                    );
                    $promotion_mansong_goods->save($data_goods);
                }
            }
            if ($err > 0) {
                $promot_mansong->rollback();
                return ACTIVE_REPRET;
            } else {
                $promot_mansong->commit();
                return $mansong_id;
            }
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionMansongDetail()
     */
    public function getPromotionMansongDetail($mansong_id)
    {
        $promotion_mansong = new VslPromotionMansongModel();
        $data = $promotion_mansong->get($mansong_id);
        $promot_mansong_rule = new VslPromotionMansongRuleModel();
        $rule_list = $promot_mansong_rule->pageQuery(1, 0, 'mansong_id = ' . $mansong_id, '', '*');
        foreach ($rule_list['data'] as $k => $v) {
            if ($v['free_shipping'] == 1) {
                $rule_list['data'][$k]['free_shipping_name'] = "是";
            } else {
                $rule_list['data'][$k]['free_shipping_name'] = "否";
            }
            if ($v['give_coupon'] == 0) {
                $rule_list['data'][$k]['coupon_name'] = '';
            } else {
                $coupon_type = new VslCouponTypeModel();
                $coupon_name = $coupon_type->getInfo([
                    'coupon_type_id' => $v['give_coupon']
                ], 'coupon_name');
                $rule_list['data'][$k]['coupon_name'] = $coupon_name['coupon_name'];
            }
            if ($v['gift_id'] == 0) {
                $rule_list['data'][$k]['gift_name'] = '';
            } else {
                $gift = new VslPromotionGiftModel();
                $gift_name = $gift->getInfo([
                    'promotion_gift_id' => $v['gift_id']
                ], 'gift_name');
                $rule_list['data'][$k]['gift_name'] = $gift_name['gift_name'];
            }
            if($v['gift_card_id']>0){
                $gift_voucher = new VslGiftVoucherModel();
                $gift_voucher_name = $gift_voucher->getInfo([
                    'gift_voucher_id' => $v['gift_card_id']
                ], 'giftvoucher_name');
                $rule_list['data'][$k]['giftvoucher_name'] = $gift_voucher_name['giftvoucher_name'];
            }
        }
        $data['rule'] = $rule_list['data'];
        
        if ($data['range_type'] == 0) {
            $mansong_goods = new VslPromotionMansongGoodsModel();
            $list = $mansong_goods->getQuery([
                'mansong_id' => $mansong_id
            ], '*', '');
            if (!empty($list)) {
                foreach ($list as $k => $v) {

                    $goods_info = $this->goods_ser->getGoodsDetailById($v['goods_id'], 'price,stock,website_id,shop_id,picture', 1, 0, 0, 1);

                    $pic_info = array();
                    $pic_info['pic_cover'] = '';
                    
                    if (!empty($goods_info['album_picture']['pic_cover'])) {
                        $pic_info = [
                            'pic_cover' => $goods_info['album_picture']['pic_cover'],
                            'pic_cover_mid' => $goods_info['album_picture']['pic_cover_mid'],
                            'pic_cover_micro' => $goods_info['album_picture']['pic_cover_micro']
                        ];
                    }
                    $shop_info = [];
                    
                    if(getAddons('shop', $this->website_id)){
                        $shop_info['shop_name'] = $goods_info['shop_name'];
                    }else{
                        $shop_info['shop_name'] = $this->mall_name;
                    }

                    if(!empty($shop_info['shop_name'])){
                        $v['shop_name'] = $shop_info['shop_name'];
                    }else{
                        $v['shop_name'] = '-';
                    }

                    $v['picture_info'] = $pic_info;
                    $v['price'] = $goods_info['price'];
                    $v['stock'] = $goods_info['stock'];
                }
            }
            $data['goods_list'] = $list;
            $goods_id_array = array();
            foreach ($list as $k => $v) {
                $goods_id_array[] = $v['goods_id'];
            }
            $data['goods_id_array'] = $goods_id_array;
        }
        //获取分类 
        $extend_category_array = array();
        if (!empty($data['category_extend_id'])) {
            $extend_category_ids = $data['category_extend_id'];
            $extend_category_id_1s = $data['extend_category_id_1s'];
            $extend_category_id_2s = $data['extend_category_id_2s'];
            $extend_category_id_3s = $data['extend_category_id_3s'];
            $extend_category_id_str = explode(",", $extend_category_ids);
            $extend_category_id_1s_str = explode(",", $extend_category_id_1s);
            $extend_category_id_2s_str = explode(",", $extend_category_id_2s);
            $extend_category_id_3s_str = explode(",", $extend_category_id_3s);
            $good = new GoodsService();
            foreach ($extend_category_id_str as $k => $v) {
                $extend_category_name = $good->getGoodsCategoryName($extend_category_id_1s_str[$k], $extend_category_id_2s_str[$k], $extend_category_id_3s_str[$k]);
                $extend_category_array[] = array(
                    "extend_category_name" => $extend_category_name,
                    "extend_category_id" => $v,
                    "extend_category_id_1" => $extend_category_id_1s_str[$k],
                    "extend_category_id_2" => $extend_category_id_2s_str[$k],
                    "extend_category_id_3" => $extend_category_id_3s_str[$k]
                );
            }
        }
        $data['extend_category_name'] = "";
        $data['extend_category'] = $extend_category_array;
        return $data;
    }


    public function closePromotionMansong($mansong_id)
    {
        $promotion_mansong = new VslPromotionMansongModel();
        $retval = $promotion_mansong->save([
            'status' => 3
        ], [
            'mansong_id' => $mansong_id,
            'shop_id' => $this->instance_id
        ]);
        if ($retval == 1) {
            $promotion_mansong_goods = new VslPromotionMansongGoodsModel();

            $retval = $promotion_mansong_goods->save([
                'status' => 3
            ], [
                'mansong_id' => $mansong_id
            ]);
        }
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::delPromotionMansong()
     */
    public function delPromotionMansong($mansong_id)
    {
        $promotion_mansong = new VslPromotionMansongModel();
        $promotion_mansong_goods = new VslPromotionMansongGoodsModel();
        $promot_mansong_rule = new VslPromotionMansongRuleModel();
        $promotion_mansong->startTrans();
        try {
            $mansong_id_array = explode(',', $mansong_id);
            foreach ($mansong_id_array as $k => $v) {
                $status = $promotion_mansong->getInfo([
                    'mansong_id' => $v
                ], 'status');
                if ($status['status'] == 1) {
                    $promotion_mansong->rollback();
                    return -1;
                }
                $promotion_mansong->destroy($v);
                $promotion_mansong_goods->destroy([
                    'mansong_id' => $v
                ]);
                $promot_mansong_rule->destroy([
                    'mansong_id' => $v
                ]);
            }
            $promotion_mansong->commit();
            return 1;
        } catch (Exception $e) {
            $promotion_mansong->rollback();
            return $e->getMessage();
        }
    }



    /*
     * 开启满减送设置
     *
     */
    public function setConfig($is_use)
    {
        $ConfigService = new AddonsConfigService();
        $ManSong_info = $ConfigService->getAddonsConfig("fullcut");
        if (!empty($ManSong_info)) {
            $res = $ConfigService->updateAddonsConfig('', "满减送设置", $is_use, "fullcut");
        } else {
            $res = $ConfigService->addAddonsConfig('', '满减送设置', $is_use, 'fullcut');
        }
        return $res;
    }

    /*
     * 获取满减送设置
     *
     */
    public function getManSongSite()
    {
        $config = new AddonsConfigService();
        $manSong = $config->getAddonsConfig("fullcut");
        return $manSong;
    }

    /**
     * 根据购物车的商品信息或者对应的满减送活动优惠
     * @param array $cart_sku_info
     *
     * @return array $full_cut_info
     */
    public function getCartManSong(array $cart_sku_info)
    {
        if (!getAddons('fullcut', $this->website_id)) {
            return [];
        }
        $man_song_model = new VslPromotionMansongModel();
        $shop_id_array = [0];//为了能获取平台设置的全平台可用的活动
        $full_cut_info = [];//保存可以用的满减活动信息
        foreach ($cart_sku_info as $shop_id => $sku_info) {
            if (!in_array($shop_id, $shop_id_array)) {
                $shop_id_array[] = $shop_id;
            }
        }

        $condition['shop_id'] = ['IN', $shop_id_array];
        $condition['website_id'] = ['=', $this->website_id];
        $condition['status'] = 1;
        $condition['start_time'] = ['<=', time()];
        $condition['end_time'] = ['>=', time()];
        $man_song_info = $man_song_model::all($condition);
        unset($shop_id_array, $condition);

        //全平台的时候就要多循环2次$cart_sku_info，第一次计算每个店铺的总价格，第二次比较每个店铺的总价格
        //仅限本店使用的情况直接用循环$cart_sku_info[$info->shop_id]就可以计算总价格
        foreach ($man_song_info as $k => $info) {
            if ($info->range == 1 || $info->range == 3) {//仅本店可以使用
                if (empty($cart_sku_info[$info->shop_id])) {
                    continue;
                }
                if ($info->range_type == 1) {//全部商品可用
                    $total_price[$info->shop_id] = 0.00;
                    foreach ($cart_sku_info[$info->shop_id] as $sku) {
                        $total_price[$info->shop_id] += $sku['discount_price'] * $sku['num'];
                    }
                    //计算符合sku占满减优惠比率
                    if ($total_price[$info->shop_id] > 0) {
                        foreach ($cart_sku_info[$info->shop_id] as $sku_id => $sku_info) {
                            $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                        }
                    }
                    foreach ($info->rules as $rule) {
                        if ($total_price[$info->shop_id] >= $rule->price) {//满足 满额的要求
                            if (empty($full_cut_info[$info->shop_id]) || ($info->shop_id == $full_cut_info[$info->shop_id]['full_cut']['shop_id'] && ($full_cut_info[$info->shop_id]['full_cut']['level'] < $info->level || ($full_cut_info[$info->shop_id]['full_cut']['price'] < $rule->price && $full_cut_info[$info->shop_id]['full_cut']['level'] == $info->level))) || ($info->shop_id == $info->shop_id && $full_cut_info[$info->shop_id]['full_cut']['shop_id'] != $info->shop_id)) {
                                //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                $full_cut_info[$info->shop_id]['full_cut']['man_song_id'] = $info->mansong_id;
                                $full_cut_info[$info->shop_id]['full_cut']['rule_id'] = $rule->rule_id;
                                $full_cut_info[$info->shop_id]['full_cut']['man_song_name'] = $info->mansong_name;
                                $full_cut_info[$info->shop_id]['full_cut']['discount'] = $rule->discount;
                                $full_cut_info[$info->shop_id]['full_cut']['price'] = $rule->price;
                                $full_cut_info[$info->shop_id]['full_cut']['shop_id'] = $info->shop_id;
                                $full_cut_info[$info->shop_id]['full_cut']['goods_limit'] = [];
                                $full_cut_info[$info->shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                $full_cut_info[$info->shop_id]['full_cut']['give_point'] = $rule->give_point;
                                $full_cut_info[$info->shop_id]['full_cut']['range_type'] = $info->range_type;
                                $full_cut_info[$info->shop_id]['full_cut']['level'] = $info->level;
                                $full_cut_info[$info->shop_id]['full_cut']['give_coupon'] = $rule->give_coupon;
                                $full_cut_info[$info->shop_id]['full_cut']['gift_card_id'] = $rule->gift_card_id;
                                $full_cut_info[$info->shop_id]['full_cut']['gift_id'] = $rule->gift_id;
                                if ($rule->free_shipping == 1) {//包邮
                                    $full_cut_info[$info->shop_id]['shipping']['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$info->shop_id]['shipping']['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$info->shop_id]['shipping']['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$info->shop_id]['shipping']['free_shipping'] = true;
                                    $full_cut_info[$info->shop_id]['shipping']['price'] = $rule->price;
                                    $full_cut_info[$info->shop_id]['shipping']['shop_id'] = $info->shop_id;
                                    $full_cut_info[$info->shop_id]['shipping']['goods_limit'] = [];
                                    $full_cut_info[$info->shop_id]['shipping']['range_type'] = $info->range_type;
                                } else {
                                    unset($full_cut_info[$info->shop_id]['shipping']);
                                }
                                if ($rule->give_coupon && getAddons('coupontype', $this->website_id)) {
                                    $coupon_type_model = new VslCouponTypeModel();
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_name'] = $coupon_type_model::get($rule->give_coupon)['coupon_name'];
                                } else {
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_id'] = '';
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_name'] = '';
                                }
                                //礼品券
                                if ($rule->gift_card_id && getAddons('giftvoucher', $this->website_id)) {
                                    $gift_voucher = new VslGiftVoucherModel();
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_card_id'] = $rule->gift_card_id;
                                    $giftvoucher_name = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'giftvoucher_name')['giftvoucher_name'];
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_voucher_name'] = $giftvoucher_name;//送优惠券
                                }else{
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_card_id'] = '';
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_voucher_name'] = '';
                                }
                                //赠品
                                if ($rule->gift_id && getAddons('giftvoucher', $this->website_id)) {
                                    $gift_mdl = new VslPromotionGiftModel();
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_id'] = $rule->gift_id;
                                    $gift_name = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name')['gift_name'];
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_name'] = $gift_name;//送优惠券
                                }else{
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_id'] = '';
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_name'] = '';
                                }
                            }
                        }
                    }
                    continue;
                }
                if ($info->range_type == 0) {//部分商品可用
                    $goods_id_array = [];
                    foreach ($info->goods as $goods) {
                        $goods_id_array[] = $goods->goods_id;
                    }

                    $total_price[$info->shop_id] = 0.00;
                    $in_array_number = 0;
                    $all_goods_in_promotion = true;
                    foreach ($cart_sku_info[$info->shop_id] as $sku) {
                        if (in_array($sku['goods_id'], $goods_id_array)) {
                            $in_array_number++;
                            $total_price[$info->shop_id] += $sku['discount_price'] * $sku['num'];
                        } else {
                            $all_goods_in_promotion = false;
                        }
                    }

                    //计算符合sku占满减优惠比率
                    if ($total_price[$info->shop_id] > 0) {
                        foreach ($cart_sku_info[$info->shop_id] as $sku_id => $sku_info) {
                            if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        if ($total_price[$info->shop_id] >= $rule->price && $in_array_number > 0) {//满足 满额的要求 所有商品在满减活动指定商品列表中(1-24修改为活动内的商品满足满额要求就行了)
                            if (empty($full_cut_info[$info->shop_id]) ||
                                ($info->shop_id == $full_cut_info[$info->shop_id]['full_cut']['shop_id'] && ($full_cut_info[$info->shop_id]['full_cut']['level'] < $info->level || ($full_cut_info[$info->shop_id]['full_cut']['price'] < $rule->price && $full_cut_info[$info->shop_id]['full_cut']['level'] == $info->level))) ||
                                ($info->shop_id == $info->shop_id && $full_cut_info[$info->shop_id]['full_cut']['shop_id'] != $info->shop_id)) {
                                //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                $full_cut_info[$info->shop_id]['full_cut']['man_song_id'] = $info->mansong_id;
                                $full_cut_info[$info->shop_id]['full_cut']['rule_id'] = $rule->rule_id;
                                $full_cut_info[$info->shop_id]['full_cut']['man_song_name'] = $info->mansong_name;
                                $full_cut_info[$info->shop_id]['full_cut']['discount'] = $rule->discount;
                                $full_cut_info[$info->shop_id]['full_cut']['price'] = $rule->price;
                                $full_cut_info[$info->shop_id]['full_cut']['shop_id'] = $info->shop_id;
                                $full_cut_info[$info->shop_id]['full_cut']['goods_limit'] = $goods_id_array;
                                $full_cut_info[$info->shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                $full_cut_info[$info->shop_id]['full_cut']['give_point'] = $rule->give_point;
                                $full_cut_info[$info->shop_id]['full_cut']['range_type'] = $info->range_type;
                                $full_cut_info[$info->shop_id]['full_cut']['level'] = $info->level;
                                $full_cut_info[$info->shop_id]['full_cut']['give_coupon'] = $rule->give_coupon;
                                $full_cut_info[$info->shop_id]['full_cut']['gift_card_id'] = $rule->gift_card_id;
                                $full_cut_info[$info->shop_id]['full_cut']['gift_id'] = $rule->gift_id;
                                if ($rule->free_shipping == 1) {//包邮
                                    $full_cut_info[$info->shop_id]['shipping']['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$info->shop_id]['shipping']['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$info->shop_id]['shipping']['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$info->shop_id]['shipping']['free_shipping'] = true;
                                    $full_cut_info[$info->shop_id]['shipping']['price'] = $rule->price;
                                    $full_cut_info[$info->shop_id]['shipping']['shop_id'] = $info->shop_id;
                                    $full_cut_info[$info->shop_id]['shipping']['goods_limit'] = $goods_id_array;
                                    $full_cut_info[$info->shop_id]['shipping']['range_type'] = $info->range_type;
                                } else {
                                    unset($full_cut_info[$info->shop_id]['shipping']);
                                }
                                if ($rule->give_coupon && getAddons('coupontype', $this->website_id)) {
                                    $coupon_type_model = new VslCouponTypeModel();
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_name'] = $coupon_type_model::get($rule->give_coupon)['coupon_name'];
                                } else {
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_id'] = '';
                                    $full_cut_info[$info->shop_id]['full_cut']['coupon_type_name'] = '';
                                }
                                //礼品券
                                if ($rule->gift_card_id && getAddons('giftvoucher', $this->website_id)) {
                                    $gift_voucher = new VslGiftVoucherModel();
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_card_id'] = $rule->gift_card_id;
                                    $giftvoucher_name = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'giftvoucher_name')['giftvoucher_name'];
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_voucher_name'] = $giftvoucher_name;//送优惠券
                                }else{
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_card_id'] = '';
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_voucher_name'] = '';
                                }
                                //赠品
                                if ($rule->gift_id && getAddons('giftvoucher', $this->website_id)) {
                                    $gift_mdl = new VslPromotionGiftModel();
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_id'] = $rule->gift_id;
                                    $gift_name = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name')['gift_name'];
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_name'] = $gift_name;//送优惠券
                                }else{
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_id'] = '';
                                    $full_cut_info[$info->shop_id]['full_cut']['gift_name'] = '';
                                }
                            }
                        }
                    }
                    continue;
                }
                continue;
            }

            if ($info->range == 2) {//全平台设置的全平台可用
                $total_price = [];
                if ($info->range_type == 1) {//全部商品可用
                    foreach ($cart_sku_info as $shop_id => $sku) {
                        $total_price[$shop_id] = 0.00;
                        foreach ($sku as $sku_id => $sku_info) {
                            $total_price[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                        }
                        //计算每个sku占满减优惠比率
                        if ($total_price[$shop_id] > 0) {
                            foreach ($cart_sku_info[$shop_id] as $sku_id => $sku_info) {
                                $full_cut_info[$shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        foreach ($total_price as $shop_id => $sub_total_price) {
                            if ($sub_total_price >= $rule->price) {//满足 满额的要求
                                if (empty($full_cut_info[$shop_id]) ||
                                    ($info->shop_id == $full_cut_info[$shop_id]['full_cut']['shop_id'] && ($full_cut_info[$shop_id]['full_cut']['level'] < $info->level || ($full_cut_info[$shop_id]['full_cut']['price'] < $rule->price && $full_cut_info[$shop_id]['full_cut']['level'] == $info->level))) ||
                                    ($info->shop_id == $shop_id && $full_cut_info[$shop_id]['full_cut']['shop_id'] != $shop_id)) {
                                    //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                    //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                    $full_cut_info[$shop_id]['full_cut']['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$shop_id]['full_cut']['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$shop_id]['full_cut']['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$shop_id]['full_cut']['discount'] = $rule->discount;
                                    $full_cut_info[$shop_id]['full_cut']['price'] = $rule->price;
                                    $full_cut_info[$shop_id]['full_cut']['shop_id'] = $info->shop_id;
                                    $full_cut_info[$shop_id]['full_cut']['goods_limit'] = [];
                                    $full_cut_info[$shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                    $full_cut_info[$shop_id]['full_cut']['give_point'] = $rule->give_point;
                                    $full_cut_info[$shop_id]['full_cut']['range_type'] = $info->range_type;
                                    $full_cut_info[$shop_id]['full_cut']['level'] = $info->level;
                                    $full_cut_info[$shop_id]['full_cut']['give_coupon'] = $rule->give_coupon;
                                    $full_cut_info[$shop_id]['full_cut']['gift_card_id'] = $rule->gift_card_id;
                                    $full_cut_info[$shop_id]['full_cut']['gift_id'] = $rule->gift_id;
                                    if ($rule->free_shipping == 1) {
                                        $full_cut_info[$shop_id]['shipping']['man_song_id'] = $info->mansong_id;
                                        $full_cut_info[$shop_id]['shipping']['rule_id'] = $rule->rule_id;
                                        $full_cut_info[$shop_id]['shipping']['man_song_name'] = $info->mansong_name;
                                        $full_cut_info[$shop_id]['shipping']['free_shipping'] = true;
                                        $full_cut_info[$shop_id]['shipping']['price'] = $rule->price;
                                        $full_cut_info[$shop_id]['shipping']['shop_id'] = $info->shop_id;
                                        $full_cut_info[$shop_id]['shipping']['goods_limit'] = [];
                                        $full_cut_info[$shop_id]['shipping']['range_type'] = $info->range_type;
                                    } else {
                                        unset($full_cut_info[$shop_id]['shipping']);
                                    }
                                    if ($rule->give_coupon && getAddons('coupontype', $this->website_id)) {
                                        $coupon_type_model = new VslCouponTypeModel();
                                        $full_cut_info[$shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                        $full_cut_info[$shop_id]['full_cut']['coupon_type_name'] = $coupon_type_model::get($rule->give_coupon)['coupon_name'];
                                    } else {
                                        $full_cut_info[$shop_id]['full_cut']['coupon_type_id'] = '';
                                        $full_cut_info[$shop_id]['full_cut']['coupon_type_name'] = '';
                                    }
                                    //礼品券
                                    if ($rule->gift_card_id && getAddons('giftvoucher', $this->website_id)) {
                                        $gift_voucher = new VslGiftVoucherModel();
                                        $full_cut_info[$shop_id]['full_cut']['gift_card_id'] = $rule->gift_card_id;
                                        $giftvoucher_name = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'giftvoucher_name')['giftvoucher_name'];
                                        $full_cut_info[$shop_id]['full_cut']['gift_voucher_name'] = $giftvoucher_name;//送优惠券
                                    }else{
                                        $full_cut_info[$shop_id]['full_cut']['gift_card_id'] = '';
                                        $full_cut_info[$shop_id]['full_cut']['gift_voucher_name'] = '';
                                    }
                                    //赠品
                                    if ($rule->gift_id && getAddons('giftvoucher', $this->website_id)) {
                                        $gift_mdl = new VslPromotionGiftModel();
                                        $full_cut_info[$shop_id]['full_cut']['gift_id'] = $rule->gift_id;
                                        $gift_name = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name')['gift_name'];
                                        $full_cut_info[$shop_id]['full_cut']['gift_name'] = $gift_name;//送优惠券
                                    }else{
                                        $full_cut_info[$shop_id]['full_cut']['gift_id'] = '';
                                        $full_cut_info[$shop_id]['full_cut']['gift_name'] = '';
                                    }
                                }
                            }
                        }
                    }
                    continue;
                }

                if ($info->range_type == 0) {//部分商品可用
                    $goods_id_array = [];
                    foreach ($info->goods as $goods) {
                        $goods_id_array[] = $goods->goods_id;
                    }
                    $total_price = [];
                    $in_array_number = 0;

                    foreach ($cart_sku_info as $shop_id => $sku) {
                        $total_price[$shop_id] = 0.00;
                        $all_goods_in_promotion = true;
                        foreach ($sku as $sku_id => $sku_info) {
                            if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                $in_array_number++;
                                $total_price[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                            } else {
                                $all_goods_in_promotion = false;
                            }
                        }
                        //计算符合sku占满减优惠比率
                        if ($total_price[$shop_id] > 0) {
                            foreach ($cart_sku_info[$shop_id] as $sku_id => $sku_info) {
                                if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                                }
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        foreach ($total_price as $shop_id => $sub_total_price) {
                            if ($sub_total_price >= $rule->price && $in_array_number > 0) {//满足 满额的要求 所有商品在满减活动指定商品列表中(1-24修改为活动内的商品满足满额要求就行了)
                                if (empty($full_cut_info[$shop_id]) ||
                                    ($info->shop_id == $full_cut_info[$shop_id]['full_cut']['shop_id'] && ($full_cut_info[$shop_id]['full_cut']['level'] < $info->level || ($full_cut_info[$shop_id]['full_cut']['price'] < $rule->price && $full_cut_info[$shop_id]['full_cut']['level'] == $info->level))) ||
                                    ($info->shop_id == $shop_id && $full_cut_info[$shop_id]['full_cut']['shop_id'] != $shop_id)) {
                                    //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                    //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                    $full_cut_info[$shop_id]['full_cut']['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$shop_id]['full_cut']['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$shop_id]['full_cut']['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$shop_id]['full_cut']['discount'] = $rule->discount;
                                    $full_cut_info[$shop_id]['full_cut']['price'] = $rule->price;
                                    $full_cut_info[$shop_id]['full_cut']['shop_id'] = $info->shop_id;
                                    $full_cut_info[$shop_id]['full_cut']['goods_limit'] = $goods_id_array;
                                    $full_cut_info[$shop_id]['full_cut']['coupon_type_id'] = $rule->give_coupon;
                                    $full_cut_info[$shop_id]['full_cut']['give_point'] = $rule->give_point;
                                    $full_cut_info[$shop_id]['full_cut']['range_type'] = $info->range_type;
                                    $full_cut_info[$shop_id]['full_cut']['level'] = $info->level;

                                    if ($rule->free_shipping == 1) {
                                        $full_cut_info[$shop_id]['shipping']['man_song_id'] = $info->mansong_id;
                                        $full_cut_info[$shop_id]['shipping']['rule_id'] = $rule->rule_id;
                                        $full_cut_info[$shop_id]['shipping']['man_song_name'] = $info->mansong_name;
                                        $full_cut_info[$shop_id]['shipping']['free_shipping'] = true;
                                        $full_cut_info[$shop_id]['shipping']['price'] = $rule->price;
                                        $full_cut_info[$shop_id]['shipping']['shop_id'] = $info->shop_id;
                                        $full_cut_info[$shop_id]['shipping']['goods_limit'] = $goods_id_array;
                                        $full_cut_info[$shop_id]['shipping']['range_type'] = $info->range_type;
                                    } else {
                                        unset($full_cut_info[$shop_id]['shipping']);
                                    }
                                }

                            }
                        }
                    }
                    continue;
                }
                continue;
            }
        }
        return $full_cut_info;
    }

    /**
     * 结算页面满件送
     * @param array $cart_sku_info 
     * @return array
     * @throws \think\Exception\DbException
     */
    public function getPaymentFullCut(array $cart_sku_info)
    {
        
        if (!getAddons('fullcut', $this->website_id)) {
            return [];
        }
        $man_song_model = new VslPromotionMansongModel();
        $shop_id_array = [0];//为了能获取平台设置的全平台可用的活动
        $full_cut_info = [];//保存可以用的满减活动信息
        foreach ($cart_sku_info as $shop_id => $sku_info) {
            if (!in_array($shop_id, $shop_id_array)) {
                $shop_id_array[] = $shop_id;
            }
        }
        $condition['shop_id'] = ['IN', $shop_id_array];
        $condition['website_id'] = ['=', $this->website_id];
        $condition['status'] = 1;
        $condition['start_time'] = ['<=', time()];
        $condition['end_time'] = ['>=', time()];
        $man_song_info = $man_song_model::all($condition);
        //        p(Db::table('')->getLastSql());exit;
        unset($shop_id_array, $condition);
        // $man_song_info = objToArr($man_song_info);
        
        //全平台的时候就要多循环2次$cart_sku_info，第一次计算每个店铺的总价格，第二次比较每个店铺的总价格
        //仅限本店使用的情况直接用循环$cart_sku_info[$info->shop_id]就可以计算总价格
        foreach ($man_song_info as $k => $info) {
            if ($info->range == 1 || $info->range == 3) {//仅本店可以使用(1自营店 2全平台 3店铺)
                if (empty($cart_sku_info[$info->shop_id])) {
                    continue;
                }
                if ($info->range_type == 1) {//全部商品可用
                    $total_price[$info->shop_id] = 0.00;
                    foreach ($cart_sku_info[$info->shop_id] as $sku) {
                        $total_price[$info->shop_id] += $sku['discount_price'] * $sku['num'];
                    }
                    //计算符合sku占满减优惠比率
                    if ($total_price[$info->shop_id] > 0) {
                        foreach ($cart_sku_info[$info->shop_id] as $sku_id => $sku_info) {
                            $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                        }
                    }
                    foreach ($info->rules as $rule) {
                        if ($total_price[$info->shop_id] >= $rule->price) {//满足 满额的要求
                            if (empty($full_cut_info[$info->shop_id]) ||
                                ($info->shop_id == $full_cut_info[$info->shop_id]['shop_id'] && ($full_cut_info[$info->shop_id]['level'] < $info->level || ($full_cut_info[$info->shop_id]['price'] < $rule->price && $full_cut_info[$info->shop_id]['level'] == $info->level))) ||
                                ($info->shop_id == $info->shop_id && $full_cut_info[$info->shop_id]['shop_id'] != $info->shop_id)) {
                                //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                $full_cut_info[$info->shop_id]['man_song_id'] = $info->mansong_id;
                                $full_cut_info[$info->shop_id]['rule_id'] = $rule->rule_id;
                                $full_cut_info[$info->shop_id]['man_song_name'] = $info->mansong_name;
                                $full_cut_info[$info->shop_id]['discount'] = $rule->discount;
                                $full_cut_info[$info->shop_id]['rule_discount'] = $rule->discount;
                                $full_cut_info[$info->shop_id]['price'] = $rule->price;
                                $full_cut_info[$info->shop_id]['shop_id'] = $info->shop_id;
                                $full_cut_info[$info->shop_id]['goods_limit'] = [];
                                if(getAddons('coupontype', $this->website_id)){
                                    //判断当前优惠券是否还有领取数量
                                    $coupon = new VslCouponModel();
                                    $coupon_type = new VslCouponTypeModel();
                                    $coupon_type_num = $coupon_type->getInfo(['coupon_type_id' => $rule->give_coupon], 'count, max_fetch,coupon_name');
                                    //当前用户是否已经达到领取数目
                                    $user_coupon_total = $coupon->where(['uid'=>$this->uid, 'coupon_type_id'=>$rule->give_coupon])->count();
                                    //当前优惠券所有用户领取的总数
                                    $coupon_total = $coupon->where(['coupon_type_id'=>$rule->give_coupon])->count();
                                    //优惠券用户领取的数量不能大于每个用户的限制领取数量，否则不显示   或者所有用户领取的总量不能超过总数
                                    if($user_coupon_total < $coupon_type_num['max_fetch'] && $coupon_total < $coupon_type_num['count']){
                                        $full_cut_info[$info->shop_id]['coupon_type_id'] = $rule->give_coupon;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = $coupon_type_num['coupon_name'];//送优惠券
                                    }else{
                                        $full_cut_info[$info->shop_id]['coupon_type_id'] = 0;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = '';//送优惠券
                                    }
                                }
                                if(getAddons('giftvoucher', $this->website_id)){
                                    $gift_voucher = new VslGiftVoucherModel();
                                    $gift_voucher_record = new VslGiftVoucherRecordsModel();
                                    $giftvoucher_num = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'count, max_fetch, giftvoucher_name');
                                    //当前用户领取礼品券的总量  所有用户领取礼品券的总量
                                    $user_gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id, 'uid'=>$this->uid])->count();
                                    $gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id])->count();
                                    if(($user_gift_voucher_total < $giftvoucher_num['max_fetch'] && $gift_voucher_total < $giftvoucher_num['count']) || ($giftvoucher_num['max_fetch'] === 0 && $giftvoucher_num['count'] === 0)){
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;//送礼品券
                                        //获取礼品券的名字
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = $giftvoucher_num['giftvoucher_name'];//送礼品券
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = 0;//送礼品券
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                    }
                                }else{
                                    $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                }
                                if(getAddons('gift', $this->website_id)){
                                    //获取赠品的名字
                                    $gift_mdl = new VslPromotionGiftModel();
                                    $member_gift = new VslMemberGiftModel();
                                    $gift_num = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name, stock');
                                    $gift_total = $member_gift->where(['promotion_gift_id'=>$rule->gift_id])->count();
                                    if($gift_num['stock']){
                                        $full_cut_info[$info->shop_id]['gift_id'] = $rule->gift_id;
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;
                                        $full_cut_info[$info->shop_id]['gift_name'] = $gift_num['gift_name'];//送赠品
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_id'] = 0;
                                        $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                    }
                                }else{
                                    $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                }

                                $full_cut_info[$info->shop_id]['give_point'] = $rule->give_point;
                                $full_cut_info[$info->shop_id]['range_type'] = $info->range_type;
                                $full_cut_info[$info->shop_id]['free_shipping'] = $rule->free_shipping;
                                $full_cut_info[$info->shop_id]['level'] = $info->level;
                            }
                        }
                    }
                    continue;
                }
                if ($info->range_type == 0) {//部分商品可用
                    $goods_id_array = [];
                    foreach ($info->goods as $goods) {
                        $goods_id_array[] = $goods->goods_id;
                    }

                    $total_price[$info->shop_id] = 0.00;
                    $in_array_number = 0;
                    $all_goods_in_promotion = true;
                    $full_cut_count = [];
                    foreach ($cart_sku_info[$info->shop_id] as $sku) {
                        if (in_array($sku['goods_id'], $goods_id_array)) {
                            $in_array_number++;
                            $full_cut_count[] = $sku['sku_id'];
                            $total_price[$info->shop_id] += $sku['discount_price'] * $sku['num'];
                        } else {
                            $all_goods_in_promotion = false;
                        }
                    }
                    //计算符合sku占满减优惠比率
                    $count = count($full_cut_count);
                    $i = 0;
                    $allPercent = 0;
                    
                    if ($total_price[$info->shop_id] > 0) {
                        foreach ($cart_sku_info[$info->shop_id] as $sku_id => $sku_info) {
                            
                            if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                $i++;
                                if($i != $count){
                                    $allPercent += round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                                    $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                                }else{
                                    $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = 1- $allPercent;
                                }
                                
                            }else{
                                $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = 0;
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        if ($total_price[$info->shop_id] >= $rule->price && $in_array_number > 0) {//满足 满额的要求 所有商品在满减活动指定商品列表中(1-24修改为活动内的商品满足满额要求就行了)
                            if (empty($full_cut_info[$info->shop_id]) ||
                                ($info->shop_id == $full_cut_info[$info->shop_id]['shop_id'] && ($full_cut_info[$info->shop_id]['level'] < $info->level || ($full_cut_info[$info->shop_id]['price'] < $rule->price && $full_cut_info[$info->shop_id]['level'] == $info->level))) ||
                                ($info->shop_id == $info->shop_id && $full_cut_info[$info->shop_id]['shop_id'] != $info->shop_id)) {
                                //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                $full_cut_info[$info->shop_id]['man_song_id'] = $info->mansong_id;
                                $full_cut_info[$info->shop_id]['rule_id'] = $rule->rule_id;
                                $full_cut_info[$info->shop_id]['man_song_name'] = $info->mansong_name;
                                $full_cut_info[$info->shop_id]['rule_discount'] = $rule->discount;
                                $full_cut_info[$info->shop_id]['discount'] = $rule->discount > $total_price[$info->shop_id] ? $total_price[$info->shop_id] : $rule->discount;
                                $full_cut_info[$info->shop_id]['price'] = $rule->price;
                                $full_cut_info[$info->shop_id]['shop_id'] = $info->shop_id;
                                $full_cut_info[$info->shop_id]['goods_limit'] = $goods_id_array;
                                if(getAddons('coupontype', $this->website_id)){
                                    //判断当前优惠券是否还有领取数量
                                    $coupon = new VslCouponModel();
                                    $coupon_type = new VslCouponTypeModel();
                                    $coupon_type_num = $coupon_type->getInfo(['coupon_type_id' => $rule->give_coupon], 'count, max_fetch,coupon_name');
                                    //当前用户是否已经达到领取数目
                                    $user_coupon_total = $coupon->where(['uid'=>$this->uid, 'coupon_type_id'=>$rule->give_coupon])->count();
                                    //当前优惠券所有用户领取的总数
                                    $coupon_total = $coupon->where(['coupon_type_id'=>$rule->give_coupon])->count();
                                    //优惠券用户领取的数量不能大于每个用户的限制领取数量，否则不显示   或者所有用户领取的总量不能超过总数
                                    if($user_coupon_total < $coupon_type_num['max_fetch'] && $coupon_total < $coupon_type_num['count']){
                                        $full_cut_info[$info->shop_id]['coupon_type_id'] = $rule->give_coupon;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = $coupon_type_num['coupon_name'];//送优惠券
                                    }else{
                                        $full_cut_info[$info->shop_id]['coupon_type_id'] = 0;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = '';//送优惠券
                                    }
                                }
                                if(getAddons('giftvoucher', $this->website_id)){
                                    $gift_voucher = new VslGiftVoucherModel();
                                    $gift_voucher_record = new VslGiftVoucherRecordsModel();
                                    $giftvoucher_num = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'count, max_fetch, giftvoucher_name');
                                    //当前用户领取礼品券的总量  所有用户领取礼品券的总量
                                    $user_gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id, 'uid'=>$this->uid])->count();
                                    $gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id])->count();
                                    if(($user_gift_voucher_total < $giftvoucher_num['max_fetch'] && $gift_voucher_total < $giftvoucher_num['count']) || ($giftvoucher_num['max_fetch'] === 0 && $giftvoucher_num['count'] === 0)){
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;//送礼品券
                                        //获取礼品券的名字
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = $giftvoucher_num['giftvoucher_name'];//送礼品券
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = 0;//送礼品券
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                    }
                                }else{
                                    $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                }
                                if(getAddons('gift', $this->website_id)){
                                    //获取赠品的名字
                                    $gift_mdl = new VslPromotionGiftModel();
                                    $member_gift = new VslMemberGiftModel();
                                    $gift_num = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name, stock');
                                    $gift_total = $member_gift->where(['promotion_gift_id'=>$rule->gift_id])->count();
                                    if($gift_num['stock']){
                                        $full_cut_info[$info->shop_id]['gift_id'] = $rule->gift_id;
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;
                                        $full_cut_info[$info->shop_id]['gift_name'] = $gift_num['gift_name'];//送赠品
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_id'] = 0;
                                        $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                    }
                                }else{
                                    $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                }

                                $full_cut_info[$info->shop_id]['give_point'] = $rule->give_point;
                                $full_cut_info[$info->shop_id]['range_type'] = $info->range_type;
                                $full_cut_info[$info->shop_id]['free_shipping'] = $rule->free_shipping;
                                $full_cut_info[$info->shop_id]['level'] = $info->level;
                            }
                        }
                    }
                    continue;
                }
                if ($info->range_type == 3) {//分类商品可用
                    $goods_id_array = [];

                    $total_price[$info->shop_id] = 0.00;
                    $in_array_number = 0;
                    $all_goods_in_promotion = true;
                    $full_cut_count = [];
                    foreach ($cart_sku_info[$info->shop_id] as $sku) {
                        //获取商品分类
                        $goods_info = $this->goods_ser->getGoodsDetailById($sku['goods_id'], 'category_id,category_id_1,category_id_2,category_id_3');
                        // -- 
                        if($goods_info['category_id'] > 0){
                            $goods_category_arr = [
                                $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
                            ];
                            $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                            if (!$goods_category_arr || !array_intersect($goods_category_arr,explode(',', $info->category_extend_id))) {
                                $all_goods_in_promotion = false;
                            }else{
                                $in_array_number++;
                                $full_cut_count[] = $sku['sku_id'];
                                $total_price[$info->shop_id] += $sku['discount_price'] * $sku['num'];
                            }
                        }
                    }
                    //计算符合sku占满减优惠比率
                    $count = count($full_cut_count);
                    $i = 0;
                    $allPercent = 0;
                    
                    if ($total_price[$info->shop_id] > 0) {
                        foreach ($cart_sku_info[$info->shop_id] as $sku_id => $sku_info) {
                            
                            if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                $i++;
                                if($i != $count){
                                    $allPercent += round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                                    $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$info->shop_id], 2);
                                }else{
                                    $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = 1- $allPercent;
                                }
                                
                            }else{
                                $full_cut_info[$info->shop_id]['discount_percent'][$sku_id] = 0;
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        if ($total_price[$info->shop_id] >= $rule->price && $in_array_number > 0) {//满足 满额的要求 所有商品在满减活动指定商品列表中(1-24修改为活动内的商品满足满额要求就行了)
                            if (empty($full_cut_info[$info->shop_id]) ||
                                ($info->shop_id == $full_cut_info[$info->shop_id]['shop_id'] && ($full_cut_info[$info->shop_id]['level'] < $info->level || ($full_cut_info[$info->shop_id]['price'] < $rule->price && $full_cut_info[$info->shop_id]['level'] == $info->level))) ||
                                ($info->shop_id == $info->shop_id && $full_cut_info[$info->shop_id]['shop_id'] != $info->shop_id)) {
                                //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                $full_cut_info[$info->shop_id]['man_song_id'] = $info->mansong_id;
                                $full_cut_info[$info->shop_id]['rule_id'] = $rule->rule_id;
                                $full_cut_info[$info->shop_id]['man_song_name'] = $info->mansong_name;
                                $full_cut_info[$info->shop_id]['rule_discount'] = $rule->discount;
                                $full_cut_info[$info->shop_id]['discount'] = $rule->discount > $total_price[$info->shop_id] ? $total_price[$info->shop_id] : $rule->discount;
                                $full_cut_info[$info->shop_id]['price'] = $rule->price;
                                $full_cut_info[$info->shop_id]['shop_id'] = $info->shop_id;
                                $full_cut_info[$info->shop_id]['goods_limit'] = $goods_id_array;
                                if(getAddons('coupontype', $this->website_id)){
                                    //判断当前优惠券是否还有领取数量
                                    $coupon = new VslCouponModel();
                                    $coupon_type = new VslCouponTypeModel();
                                    $coupon_type_num = $coupon_type->getInfo(['coupon_type_id' => $rule->give_coupon], 'count, max_fetch,coupon_name');
                                    //当前用户是否已经达到领取数目
                                    $user_coupon_total = $coupon->where(['uid'=>$this->uid, 'coupon_type_id'=>$rule->give_coupon])->count();
                                    //当前优惠券所有用户领取的总数
                                    $coupon_total = $coupon->where(['coupon_type_id'=>$rule->give_coupon])->count();
                                    //优惠券用户领取的数量不能大于每个用户的限制领取数量，否则不显示   或者所有用户领取的总量不能超过总数
                                    if($user_coupon_total < $coupon_type_num['max_fetch'] && $coupon_total < $coupon_type_num['count']){
                                        $full_cut_info[$info->shop_id]['coupon_type_id'] = $rule->give_coupon;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = $coupon_type_num['coupon_name'];//送优惠券
                                    }else{
                                        $full_cut_info[$info->shop_id]['coupon_type_id'] = 0;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = '';//送优惠券
                                    }
                                }
                                if(getAddons('giftvoucher', $this->website_id)){
                                    $gift_voucher = new VslGiftVoucherModel();
                                    $gift_voucher_record = new VslGiftVoucherRecordsModel();
                                    $giftvoucher_num = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'count, max_fetch, giftvoucher_name');
                                    //当前用户领取礼品券的总量  所有用户领取礼品券的总量
                                    $user_gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id, 'uid'=>$this->uid])->count();
                                    $gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id])->count();
                                    if(($user_gift_voucher_total < $giftvoucher_num['max_fetch'] && $gift_voucher_total < $giftvoucher_num['count']) || ($giftvoucher_num['max_fetch'] === 0 && $giftvoucher_num['count'] === 0)){
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;//送礼品券
                                        //获取礼品券的名字
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = $giftvoucher_num['giftvoucher_name'];//送礼品券
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = 0;//送礼品券
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                    }
                                }else{
                                    $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                }
                                if(getAddons('gift', $this->website_id)){
                                    //获取赠品的名字
                                    $gift_mdl = new VslPromotionGiftModel();
                                    $member_gift = new VslMemberGiftModel();
                                    $gift_num = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name, stock');
                                    $gift_total = $member_gift->where(['promotion_gift_id'=>$rule->gift_id])->count();
                                    if($gift_num['stock']){
                                        $full_cut_info[$info->shop_id]['gift_id'] = $rule->gift_id;
                                        $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;
                                        $full_cut_info[$info->shop_id]['gift_name'] = $gift_num['gift_name'];//送赠品
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_id'] = 0;
                                        $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                    }
                                }else{
                                    $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                }

                                $full_cut_info[$info->shop_id]['give_point'] = $rule->give_point;
                                $full_cut_info[$info->shop_id]['range_type'] = $info->range_type;
                                $full_cut_info[$info->shop_id]['free_shipping'] = $rule->free_shipping;
                                $full_cut_info[$info->shop_id]['level'] = $info->level;
                            }
                        }
                    }
                    continue;
                }
                continue;
            }
            
            if ($info->range == 2) {//全平台设置的全平台可用
                $total_price = [];
                if ($info->range_type == 1) {//全部商品可用
                    foreach ($cart_sku_info as $shop_id => $sku) {
                        $total_price[$shop_id] = 0.00;
                        foreach ($sku as $sku_id => $sku_info) {
                            $total_price[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                        }
                        //计算每个sku占满减优惠比率
                        $j = 0;
                        $count = count($cart_sku_info);
                        $allPercent = 0;
                        if ($total_price[$shop_id] > 0) {
                            foreach ($cart_sku_info[$shop_id] as $sku_id => $sku_info) {
                                $j++;
                                if($j != $count){
                                    $allPercent += round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                                }else{
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = 1 - $allPercent;
                                }
                                
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        foreach ($total_price as $shop_id => $sub_total_price) {
                            if ($sub_total_price >= $rule->price) {//满足 满额的要求
                                if (empty($full_cut_info[$shop_id]) ||
                                    ($info->shop_id == $full_cut_info[$shop_id]['shop_id'] && ($full_cut_info[$shop_id]['level'] < $info->level || ($full_cut_info[$shop_id]['price'] < $rule->price && $full_cut_info[$shop_id]['level'] == $info->level))) ||
                                    ($info->shop_id == $shop_id && $full_cut_info[$shop_id]['shop_id'] != $shop_id)) {
                                    //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                    //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                    $full_cut_info[$shop_id]['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$shop_id]['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$shop_id]['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$shop_id]['discount'] = $rule->discount;
                                    $full_cut_info[$shop_id]['rule_discount'] = $rule->discount;
                                    $full_cut_info[$shop_id]['price'] = $rule->price;
                                    $full_cut_info[$shop_id]['shop_id'] = $info->shop_id;
                                    $full_cut_info[$shop_id]['goods_limit'] = [];
                                    if(getAddons('coupontype', $this->website_id)){
                                        //判断当前优惠券是否还有领取数量
                                        $coupon = new VslCouponModel();
                                        $coupon_type = new VslCouponTypeModel();
                                        $coupon_type_num = $coupon_type->getInfo(['coupon_type_id' => $rule->give_coupon], 'count, max_fetch,coupon_name');
                                        //当前用户是否已经达到领取数目
                                        $user_coupon_total = $coupon->where(['uid'=>$this->uid, 'coupon_type_id'=>$rule->give_coupon])->count();
                                        //当前优惠券所有用户领取的总数
                                        $coupon_total = $coupon->where(['coupon_type_id'=>$rule->give_coupon])->count();
                                        //优惠券用户领取的数量不能大于每个用户的限制领取数量，否则不显示   或者所有用户领取的总量不能超过总数
                                        if($user_coupon_total < $coupon_type_num['max_fetch'] && $coupon_total < $coupon_type_num['count']){
                                            $full_cut_info[$info->shop_id]['coupon_type_id'] = $rule->give_coupon;//送优惠券
                                            $full_cut_info[$info->shop_id]['coupon_type_name'] = $coupon_type_num['coupon_name'];//送优惠券
                                        }else{
                                            $full_cut_info[$info->shop_id]['coupon_type_id'] = 0;//送优惠券
                                            $full_cut_info[$info->shop_id]['coupon_type_name'] = '';//送优惠券
                                        }
                                    }
                                    if(getAddons('giftvoucher', $this->website_id)){
                                        $gift_voucher = new VslGiftVoucherModel();
                                        $gift_voucher_record = new VslGiftVoucherRecordsModel();
                                        $giftvoucher_num = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'count, max_fetch, giftvoucher_name');
                                        //当前用户领取礼品券的总量  所有用户领取礼品券的总量
                                        $user_gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id, 'uid'=>$this->uid])->count();
                                        $gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id])->count();
                                        if(($user_gift_voucher_total < $giftvoucher_num['max_fetch'] && $gift_voucher_total < $giftvoucher_num['count']) || ($giftvoucher_num['max_fetch'] === 0 && $giftvoucher_num['count'] === 0)){
                                            $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;//送礼品券
                                            //获取礼品券的名字
                                            $full_cut_info[$info->shop_id]['gift_voucher_name'] = $giftvoucher_num['giftvoucher_name'];//送礼品券
                                        }else{
                                            $full_cut_info[$info->shop_id]['gift_card_id'] = 0;//送礼品券
                                            $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                        }
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                    }
                                    if(getAddons('gift', $this->website_id)){
                                        //获取赠品的名字
                                        $gift_mdl = new VslPromotionGiftModel();
                                        $member_gift = new VslMemberGiftModel();
                                        $gift_num = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name, stock');
                                        $gift_total = $member_gift->where(['promotion_gift_id'=>$rule->gift_id])->count();
                                        if($gift_num['stock']){
                                            $full_cut_info[$info->shop_id]['gift_id'] = $rule->gift_id;
                                            $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;
                                            $full_cut_info[$info->shop_id]['gift_name'] = $gift_num['gift_name'];//送赠品
                                        }else{
                                            $full_cut_info[$info->shop_id]['gift_id'] = 0;
                                            $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                        }
                                    }else{
                                        $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                    }
                                    $full_cut_info[$shop_id]['give_point'] = $rule->give_point;
                                    $full_cut_info[$shop_id]['range_type'] = $info->range_type;
                                    $full_cut_info[$shop_id]['free_shipping'] = $rule->free_shipping;
                                    $full_cut_info[$shop_id]['level'] = $info->level;
                                }
                            }
                        }
                    }
                    continue;
                }
                if ($info->range_type == 0) {//部分商品可用
                    $goods_id_array = [];
                    foreach ($info->goods as $goods) {
                        $goods_id_array[] = $goods->goods_id;
                    }
                    $total_price = [];
                    $in_array_number = 0;

                    foreach ($cart_sku_info as $shop_id => $sku) {
                        $total_price[$shop_id] = 0.00;
                        $all_goods_in_promotion = true;
                        foreach ($sku as $sku_id => $sku_info) {
                            if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                $in_array_number++;
                                $total_price[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                            } else {
                                $all_goods_in_promotion = false;
                            }
                        }
                        //计算符合sku占满减优惠比率
                        if ($total_price[$shop_id] > 0) {
                            foreach ($cart_sku_info[$shop_id] as $sku_id => $sku_info) {
                                if (in_array($sku_info['goods_id'], $goods_id_array)) {
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                                }else{
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = 0;
                                }
                            }
                        }
                    }
                    foreach ($info->rules as $rule) {
                        foreach ($total_price as $shop_id => $sub_total_price) {
                            if ($sub_total_price >= $rule->price && $in_array_number > 0) {//满足 满额的要求 所有商品在满减活动指定商品列表中(1-24修改为活动内的商品满足满额要求就行了)
                                if (empty($full_cut_info[$shop_id]) ||
                                    ($info->shop_id == $full_cut_info[$shop_id]['shop_id'] && ($full_cut_info[$shop_id]['level'] < $info->level || ($full_cut_info[$shop_id]['price'] < $rule->price && $full_cut_info[$shop_id]['level'] == $info->level))) ||
                                    ($info->shop_id == $shop_id && $full_cut_info[$shop_id]['shop_id'] != $shop_id)) {
                                    //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                    //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                    $full_cut_info[$shop_id]['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$shop_id]['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$shop_id]['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$shop_id]['rule_discount'] = $rule->discount;
                                    $full_cut_info[$shop_id]['discount'] = $rule->discount > $total_price[$info->shop_id] ? $total_price[$info->shop_id] : $rule->discount;
                                    $full_cut_info[$shop_id]['price'] = $rule->price;
                                    $full_cut_info[$shop_id]['shop_id'] = $info->shop_id;
                                    $full_cut_info[$shop_id]['goods_limit'] = $goods_id_array;
                                    if(getAddons('coupontype', $this->website_id)){
                                        //判断当前优惠券是否还有领取数量
                                        $coupon = new VslCouponModel();
                                        $coupon_type = new VslCouponTypeModel();
                                        $coupon_type_num = $coupon_type->getInfo(['coupon_type_id' => $rule->give_coupon], 'count, max_fetch,coupon_name');
                                        //当前用户是否已经达到领取数目
                                        $user_coupon_total = $coupon->where(['uid'=>$this->uid, 'coupon_type_id'=>$rule->give_coupon])->count();
                                        //当前优惠券所有用户领取的总数
                                        $coupon_total = $coupon->where(['coupon_type_id'=>$rule->give_coupon])->count();
                                        //优惠券用户领取的数量不能大于每个用户的限制领取数量，否则不显示   或者所有用户领取的总量不能超过总数
                                        if($user_coupon_total < $coupon_type_num['max_fetch'] && $coupon_total < $coupon_type_num['count']){
                                            $full_cut_info[$shop_id]['coupon_type_id'] = $rule->give_coupon;//送优惠券
                                            $full_cut_info[$shop_id]['coupon_type_name'] = $coupon_type_num['coupon_name'];//送优惠券
                                        }else{
                                            $full_cut_info[$shop_id]['coupon_type_id'] = 0;//送优惠券
                                            $full_cut_info[$shop_id]['coupon_type_name'] = '';//送优惠券
                                        }
                                    }
                                    if(getAddons('giftvoucher', $this->website_id)){
                                        $gift_voucher = new VslGiftVoucherModel();
                                        $gift_voucher_record = new VslGiftVoucherRecordsModel();
                                        $giftvoucher_num = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'count, max_fetch, giftvoucher_name');
                                        //当前用户领取礼品券的总量  所有用户领取礼品券的总量
                                        $user_gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id, 'uid'=>$this->uid])->count();
                                        $gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id])->count();
                                        if(($user_gift_voucher_total < $giftvoucher_num['max_fetch'] && $gift_voucher_total < $giftvoucher_num['count']) || ($giftvoucher_num['max_fetch'] === 0 && $giftvoucher_num['count'] === 0)){
                                            $full_cut_info[$shop_id]['gift_card_id'] = $rule->gift_card_id;//送礼品券
                                            //获取礼品券的名字
                                            $full_cut_info[$shop_id]['gift_voucher_name'] = $giftvoucher_num['giftvoucher_name'];//送礼品券
                                        }else{
                                            $full_cut_info[$shop_id]['gift_card_id'] = 0;//送礼品券
                                            $full_cut_info[$shop_id]['gift_voucher_name'] = '';//送礼品券
                                        }
                                    }else{
                                        $full_cut_info[$shop_id]['gift_voucher_name'] = '';//送礼品券
                                    }
                                    if(getAddons('gift', $this->website_id)){
                                        //获取赠品的名字
                                        $gift_mdl = new VslPromotionGiftModel();
                                        $member_gift = new VslMemberGiftModel();
                                        $gift_num = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name, stock');
                                        $gift_total = $member_gift->where(['promotion_gift_id'=>$rule->gift_id])->count();
                                        if($gift_num['stock']){
                                            $full_cut_info[$shop_id]['gift_id'] = $rule->gift_id;
                                            $full_cut_info[$shop_id]['gift_card_id'] = $rule->gift_card_id;
                                            $full_cut_info[$shop_id]['gift_name'] = $gift_num['gift_name'];//送赠品
                                        }else{
                                            $full_cut_info[$shop_id]['gift_id'] = 0;
                                            $full_cut_info[$shop_id]['gift_name'] = '';//送赠品
                                        }
                                    }else{
                                        $full_cut_info[$shop_id]['gift_name'] = '';//送赠品
                                    }
                                    $full_cut_info[$shop_id]['give_point'] = $rule->give_point;
                                    $full_cut_info[$shop_id]['range_type'] = $info->range_type;
                                    $full_cut_info[$shop_id]['free_shipping'] = $rule->free_shipping;
                                    $full_cut_info[$shop_id]['level'] = $info->level;
                                }
                            }
                        }
                    }
                    continue;
                }
                if ($info->range_type == 3) {//分类商品可用
                    $goods_id_array = [];

                    $total_price[$info->shop_id] = 0.00;
                    $in_array_number = 0;
                    $all_goods_in_promotion = true;
                    $full_cut_count = [];
                    //全平台 表示所有店铺可用 所以该处的shop_id不作限制 
                    
                    //变更start
                    foreach ($cart_sku_info as $shop_id => $sku) {
                        
                        $total_price[$shop_id] = 0.00;
                        foreach ($sku as $sku_id => $sku_info) {
                            //获取商品分类
                            $goods_info = $this->goods_ser->getGoodsDetailById($sku_info['goods_id'], 'category_id,category_id_1,category_id_2,category_id_3');
                            // -- 
                            if($goods_info['category_id'] > 0){
                                $goods_category_arr = [
                                    $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
                                ];
                                $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                                if (!$goods_category_arr || !array_intersect($goods_category_arr,explode(',', $info->category_extend_id))) {
                                    $all_goods_in_promotion = false;
                                }else{
                                    $in_array_number++;
                                    $full_cut_count[] = $sku_info['sku_id'];
                                    $total_price[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                                }
                            }
                            // $total_price[$shop_id] += $sku_info['discount_price'] * $sku_info['num'];
                        }
                        //计算每个sku占满减优惠比率
                        $j = 0;
                        $count = count($cart_sku_info);
                        $allPercent = 0;
                        if ($total_price[$shop_id] > 0) {
                            foreach ($cart_sku_info[$shop_id] as $sku_id => $sku_info) {
                                $j++;
                                if($j != $count){
                                    $allPercent += round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = round($sku_info['discount_price'] * $sku_info['num'] / $total_price[$shop_id], 2);
                                }else{
                                    $full_cut_info[$shop_id]['discount_percent'][$sku_id] = 1 - $allPercent;
                                }
                                
                            }
                        }
                    }
                    $t = 0;
                    
                    foreach ($info->rules as $rule) {
                        foreach ($total_price as $shop_id => $sub_total_price) {
                            if ($sub_total_price >= $rule->price) {//满足 满额的要求
                               
                                if ($t == 0 || empty($full_cut_info[$shop_id]) ||
                                    ($info->shop_id == $full_cut_info[$shop_id]['shop_id'] && ($full_cut_info[$shop_id]['level'] < $info->level || ($full_cut_info[$shop_id]['price'] < $rule->price && $full_cut_info[$shop_id]['level'] == $info->level))) ||
                                    ($info->shop_id == $shop_id && intval($full_cut_info[$shop_id]['shop_id']) != $shop_id)) {
                                    $t += 1;
                                        
                                    //当前活动id == 当前保存活动的店铺id时 高等级 > price
                                    //当前活动id == 当前目前商品店铺id时 && 取活动店铺id != 商品店铺id
                                    $full_cut_info[$shop_id]['man_song_id'] = $info->mansong_id;
                                    $full_cut_info[$shop_id]['rule_id'] = $rule->rule_id;
                                    $full_cut_info[$shop_id]['man_song_name'] = $info->mansong_name;
                                    $full_cut_info[$shop_id]['rule_discount'] = $rule->discount;
                                    $full_cut_info[$shop_id]['discount'] = $rule->discount > $total_price[$info->shop_id] ? $total_price[$info->shop_id] : $rule->discount;
                                    $full_cut_info[$shop_id]['price'] = $rule->price;
                                    $full_cut_info[$shop_id]['shop_id'] = $info->shop_id;
                                    $full_cut_info[$shop_id]['goods_limit'] = [];
                                    if (getAddons('coupontype', $this->website_id)) {
                                        //判断当前优惠券是否还有领取数量
                                        $coupon = new VslCouponModel();
                                        $coupon_type = new VslCouponTypeModel();
                                        $coupon_type_num = $coupon_type->getInfo(['coupon_type_id' => $rule->give_coupon], 'count, max_fetch,coupon_name');
                                        //当前用户是否已经达到领取数目
                                        $user_coupon_total = $coupon->where(['uid'=>$this->uid, 'coupon_type_id'=>$rule->give_coupon])->count();
                                        //当前优惠券所有用户领取的总数
                                        $coupon_total = $coupon->where(['coupon_type_id'=>$rule->give_coupon])->count();
                                        //优惠券用户领取的数量不能大于每个用户的限制领取数量，否则不显示   或者所有用户领取的总量不能超过总数
                                        if ($user_coupon_total < $coupon_type_num['max_fetch'] && $coupon_total < $coupon_type_num['count']) {
                                            $full_cut_info[$info->shop_id]['coupon_type_id'] = $rule->give_coupon;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = $coupon_type_num['coupon_name'];//送优惠券
                                        } else {
                                            $full_cut_info[$info->shop_id]['coupon_type_id'] = 0;//送优惠券
                                        $full_cut_info[$info->shop_id]['coupon_type_name'] = '';//送优惠券
                                        }
                                    }
                                    if (getAddons('giftvoucher', $this->website_id)) {
                                        $gift_voucher = new VslGiftVoucherModel();
                                        $gift_voucher_record = new VslGiftVoucherRecordsModel();
                                        $giftvoucher_num = $gift_voucher->getInfo(['gift_voucher_id'=>$rule->gift_card_id], 'count, max_fetch, giftvoucher_name');
                                        //当前用户领取礼品券的总量  所有用户领取礼品券的总量
                                        $user_gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id, 'uid'=>$this->uid])->count();
                                        $gift_voucher_total = $gift_voucher_record->where(['gift_voucher_id'=>$rule->gift_card_id])->count();
                                        if (($user_gift_voucher_total < $giftvoucher_num['max_fetch'] && $gift_voucher_total < $giftvoucher_num['count']) || ($giftvoucher_num['max_fetch'] === 0 && $giftvoucher_num['count'] === 0)) {
                                            $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;//送礼品券
                                        //获取礼品券的名字
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = $giftvoucher_num['giftvoucher_name'];//送礼品券
                                        } else {
                                            $full_cut_info[$info->shop_id]['gift_card_id'] = 0;//送礼品券
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                        }
                                    } else {
                                        $full_cut_info[$info->shop_id]['gift_voucher_name'] = '';//送礼品券
                                    }
                                    if (getAddons('gift', $this->website_id)) {
                                        //获取赠品的名字
                                        $gift_mdl = new VslPromotionGiftModel();
                                        $member_gift = new VslMemberGiftModel();
                                        $gift_num = $gift_mdl->getInfo(['promotion_gift_id'=>$rule->gift_id], 'gift_name, stock');
                                        $gift_total = $member_gift->where(['promotion_gift_id'=>$rule->gift_id])->count();
                                        if ($gift_num['stock']) {
                                            $full_cut_info[$info->shop_id]['gift_id'] = $rule->gift_id;
                                            $full_cut_info[$info->shop_id]['gift_card_id'] = $rule->gift_card_id;
                                            $full_cut_info[$info->shop_id]['gift_name'] = $gift_num['gift_name'];//送赠品
                                        } else {
                                            $full_cut_info[$info->shop_id]['gift_id'] = 0;
                                            $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                        }
                                    } else {
                                        $full_cut_info[$info->shop_id]['gift_name'] = '';//送赠品
                                    }

                                    $full_cut_info[$shop_id]['give_point'] = $rule->give_point;
                                    $full_cut_info[$shop_id]['range_type'] = $info->range_type;
                                    $full_cut_info[$shop_id]['free_shipping'] = $rule->free_shipping;
                                    $full_cut_info[$shop_id]['level'] = $info->level;
                                }
                            }
                        }
                }   
            
                    continue;
                }
                continue;
            }
        }
        return $full_cut_info;
    }

    /**
     * 获取商品满减送
     * @param string/int $goods_id
     */
    public function goodsFullCut($goods_id)
    {
        $man_song_goods_model = new VslPromotionMansongGoodsModel();
        $man_song_model = new VslPromotionMansongModel();

        $goods_info = $this->goods_ser->getGoodsDetailById($goods_id, 'shop_id,category_id,category_id_1,category_id_2,category_id_3');

        // 获取全商品满减送
        $conditions = [
            'start_time' => ['ELT', time()],
            'end_time' => ['EGT', time()],
            'range_type' => 1,// 全商品可用
            'status' => 1,// 活动状态
            'website_id' => $this->website_id,
        ];
        //      商品所在店鋪满减送 OR 全平台可用满减送
        //           SELECT * FROM `vsl_promotion_mansong` WHERE
        //          `start_time` <= 1548402295  AND
        //          `end_time` >= 1548402295  AND
        //          `range_type` = 1  AND
        //          `status` = 1  AND
        //          `website_id` = 17  AND
        //          (`shop_id` = 29 OR (`shop_id` = 0 AND `range` IN (2) ) )
        $whereOr['shop_id'] = $goods_info['shop_id'];
        $full_cut_list = $man_song_model::all(function ($query) use ($conditions, $whereOr) {
            $whereOrAnd['shop_id'] = 0;
            $whereOrAnd['range'] = ['IN', [2]];
            $query->where($conditions)->where(function ($q1) use ($whereOr, $whereOrAnd) {
                $q1->where($whereOr)->whereOr(function ($q2) use ($whereOrAnd) {
                    $q2->where($whereOrAnd);
                });
            });
        });
        unset($conditions);

        // 通过商品id获取到满减送类型
        $full_cut_id_list = $man_song_goods_model->getQuery([
            'goods_id' => $goods_id
        ], 'mansong_id', '');
        
        if ($full_cut_id_list) {
            $id = [];
            foreach ($full_cut_id_list as $k => $v) {
                $id[] = $v['mansong_id'];
            }
            $conditions = array(
                'mansong_id' => ['IN', $id],
                'start_time' => ['ELT', time()],
                'end_time' => ['EGT', time()],
                'range_type' => 0,
                'status' => 1,// 活动状态
                'website_id' => $this->website_id
            );
            $full_cut_list_again = $man_song_model::all($conditions);
            
            //转一下数组 拉登
            $full_cut_list_again = objToArr($full_cut_list_again);
            $full_cut_list = array_merge($full_cut_list, $full_cut_list_again);
        }
        
        //获取分类满减送 goods_id换取分类id
        #商品 3级分类     满减可有1级大分类 或者2级大分类 或者 3级分类
        #商品 2级分类     满减可有1级大分类 或者2级分类
        #商品 1级分类     满减可有1级分类
        # 2021/01/07 拉登
        if($goods_info['category_id']){
            $goods_category_arr = [
                $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
            ];
            $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
            $category_id_str = implode(',',$goods_category_arr);
            if($category_id_str){
                $sql = 'select *from vsl_promotion_mansong where category_extend_id in ('.$category_id_str.') and status=1 and website_id='.$this->website_id;
                $full_list_cate = Db::Query($sql);
                if($full_list_cate){
                        $full_cut_list = array_merge($full_cut_list, $full_list_cate);
                }
            }
        }
        
        // 剔除 店铺存在满减送时，平台的满减送
        $shop_flag = false;
        foreach ($full_cut_list as $v) {
            if ($v['shop_id'] == $shop_id && $shop_id != 0) {
                $shop_flag = true;
                break;
            }
        }

        if ($shop_flag) {
            foreach ($full_cut_list as $k => $v) {
                if ($v['shop_id'] != $shop_id) {
                    unset($full_cut_list[$k]);
                    continue;
                }
            }
            $full_cut_list = array_values($full_cut_list);
        }
        return $full_cut_list;
    }
    /**
     * 获取优惠规则
     */
    public function rulesLists($mansong_id){
        $promotionMansongRuleModel = new VslPromotionMansongRuleModel();
        $list = $promotionMansongRuleModel->getQuery(['mansong_id'=>$mansong_id],'*');
        
        return $list;
    }
    /**
     * 获取当前商品满减送活动(只查询部分商品的满减送活动)
     * @param unknown $goods_id
     */
    public function getGoodsMansongPromotion($goods_id)
    {
        $time = date("Y-m-d H:i:s", time());

        //查询当前部分商品活动
        $condition = array(
            'status' => 1,
            'range_type' => 0,
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id

        );
        $promotion_mansong = new VslPromotionMansongModel();
        $list = $promotion_mansong->getQuery($condition, '*', 'create_time desc');
        foreach ($list as $k => $v) {
            //检测当前满减送或送是否与此商品有关
            $promotion_mansong_goods = new VslPromotionMansongGoodsModel();
            $info = $promotion_mansong_goods->getInfo(['mansong_id' => $v['mansong_id'], 'goods_id' => $goods_id], '*');
            if (!empty($info)) {
                return $v;
            }

        }
        unset($v);
        return '';
    }

    /**
     * 获取满减送规则
     * @param unknown $mansong_id
     */
    public function getMansongRule($mansong_id)
    {
        $mansong_rule = new VslPromotionMansongRuleModel();
        $rule_list = $mansong_rule->getQuery(['mansong_id' => $mansong_id], '*', 'price desc');
        return $rule_list;
    }


    /**
     * 查询商品的满减送详情(应用商品详情)
     * @param unknown $goods_id
     */
    public function getGoodsMansongDetail($goods_id)
    {
        //查询全场满减送活动
        //检测店铺是否存在正在进行的全场满减送活动
        $condition = array(
            'status' => 1,
            'range_type' => 1,
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id
        );
        $promotion_mansong = new VslPromotionMansongModel();
        $list_quan = $promotion_mansong->getQuery($condition, '*', 'create_time desc');
        if (!empty($list_quan[0])) {
            $mansong_promotion = $list_quan[0];
        }

        //1. 查询商品满减送活动
        if (empty($mansong_promotion)) {
            $mansong_promotion = $this->getGoodsMansongPromotion($goods_id);
        }
        if (!empty($mansong_promotion)) {
            $rule = $this->getMansongRule($mansong_promotion['mansong_id']);
            $mansong_promotion['rule'] = $rule;

        }
        return $mansong_promotion;
    }

    /**
     * 查询商品满减送活动名称
     * @param unknown $goods_id
     */
    public function getGoodsMansongName($goods_id)
    {
        //查询满减送活动详情
        $mansong_detail = $this->getGoodsMansongDetail($goods_id);
        $mansong_name = '';
        if (!empty($mansong_detail)) {
            foreach ($mansong_detail['rule'] as $k => $v) {
                $mansong_name .= '满' . $v['price'] . '减' . $v['discount'] . ' ';
                if ($v['free_shipping'] == 1) {
                    $mansong_name .= '免邮' . ' ';
                }
                if ($v['give_point'] != 0) {
                    $mansong_name .= '赠送' . $v['give_point'] . '积分' . ' ';
                }
                if ($v['give_coupon'] != 0) {
                    $coupon = new VslCouponModel();
                    $coupon_name = $coupon->getInfo(['coupon_type_id' => $v['give_coupon']], 'money');
                    $mansong_name .= '赠送' . $coupon_name['money'] . '元优惠券' . ' ';
                }
                if ($v['gift_id'] != 0) {
                    $gift = new VslPromotionGiftModel();
                    $gift_name = $gift->getInfo(['promotion_gift_id' => $v['gift_id']], 'gift_name');
                    $mansong_name .= '赠送' . $gift_name['gift_name'];
                }
                $mansong_name .= '; ';
            }
            unset($v);
        }
        return $mansong_name;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::updatePromotionMansong()
     */
    public function updatePromotionMansong($mansong_id, $mansong_name, $start_time, $end_time, $remark, $type, $range_type, $rule, $goods_id_array, $range, $status, $level)
    {
        $promot_mansong = new VslPromotionMansongModel();
        $promot_mansong->startTrans();
        try {
            $err = 0;
            $data = array(
                'mansong_name' => $mansong_name,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'status' => 0, // 状态重新设置
                'range' => $range,
                'remark' => $remark,
                'type' => $type,
                'level' => $level,
                'range_type' => $range_type,
                'create_time' => time()
            );
            $promot_mansong->save($data, [
                'mansong_id' => $mansong_id
            ]);
            // 添加活动规则表
            $promot_mansong_rule = new VslPromotionMansongRuleModel();
            $promot_mansong_rule->destroy([
                'mansong_id' => $mansong_id
            ]);
            $rule_array = explode(';', $rule);
            foreach ($rule_array as $k => $v) {
                $promot_mansong_rule = new VslPromotionMansongRuleModel();
                $get_rule = explode(',', $v);
                $data_rule = array(
                    'mansong_id' => $mansong_id,
                    'price' => $get_rule[0],
                    'discount' => $get_rule[1],
                    'free_shipping' => $get_rule[2],
                    'give_coupon' => $get_rule[3],
                    'gift_id' => $get_rule[4],
                    'gift_card_id' => $get_rule[5]
                );
                $promot_mansong_rule->save($data_rule);
            }

            // 满减送商品表
            $promotion_mansong_goods = new VslPromotionMansongGoodsModel();
            if ($range_type == 0 && !empty($goods_id_array)) {
                // 部分商品
                // $goods_id_array = explode(',', $goods_id_array);
                $promotion_mansong_goods->destroy([
                    'mansong_id' => $mansong_id
                ]);
                foreach ($goods_id_array as $k => $v) {
                    // 查询商品名称图片
                    $promotion_mansong_goods = new VslPromotionMansongGoodsModel();
                    $goods_info = $this->goods_ser->getGoodsDetailById($v, 'goods_name,picture');
                    $data_goods = array(
                        'mansong_id' => $mansong_id,
                        'goods_id' => $v,
                        'goods_name' => $goods_info['goods_name'],
                        'goods_picture' => $goods_info['picture'],
                        'status' => 0, // 状态重新设置
                        'start_time' => getTimeTurnTimeStamp($start_time),
                        'end_time' => getTimeTurnTimeStamp($end_time)
                    );
                    $promotion_mansong_goods->save($data_goods);
                }
            }else{
                $promotion_mansong_goods->destroy([
                    'mansong_id' => $mansong_id
                ]);
            }
            if ($err > 0) {
                $promot_mansong->rollback();
                return ACTIVE_REPRET;
            } else {

                $promot_mansong->commit();
                return 1;
            }
        } catch (\Exception $e) {
            $promot_mansong->rollback();
            return $e->getMessage();
        }
    }
}
