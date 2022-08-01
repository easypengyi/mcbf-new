<?php

/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/30 0030
 * Time: 11:03
 */

namespace addons\discount\server;

use data\service\BaseService;
use addons\discount\model\VslPromotionDiscountModel;
use data\service\WebSite;
use addons\discount\model\VslPromotionDiscountGoodsModel;
use data\model\AlbumPictureModel as AlbumPictureModel;
use data\service\Goods as GoodsService;
use data\service\ActiveList;
use data\service\GoodsCategory;
use data\model\VslGoodsSkuModel;
use think\Db;

class Discount extends BaseService
{

    protected $goods_ser = '';
    function __construct()
    {
        parent::__construct();
        $this->goods_ser = new GoodsService();
    }

    /**
     * 商品所属平台（店铺）的优先级最高
     */
    public function getPromotionInfo($goods_id, $shop_id, $website_id, $time = NULL)
    {
        $default = ['discount_num' => 10, 'status' => 0];
        if (!getAddons('discount', $website_id)) {
            return $default;
        }
        $promotion_discount_model = new VslPromotionDiscountModel();
        if (!$time) {
            $time = time();
        }
        $condition['start_time'] = ['<=', $time];
        $condition['end_time'] = ['>=', $time];
        $condition['status'] = ['=', 1];
        $condition['website_id'] = $website_id;

        $promotion_discount_lists = $promotion_discount_model::all($condition);

        $promotion_discount_info = [];
        if(!$promotion_discount_lists){
            return $default;
        }
        foreach ($promotion_discount_lists as $k => $list) {
            if (($list->range_type == 1 || $list->range_type == 3) && $list->shop_id != $shop_id) {
                //仅本店使用
                continue;
            }
            if ($list->range == 1) {//全部商品可用
                //当前list.shop_id匹配 或者 目前promotion_discount_info.shop_id不匹配，取level值大的
                if (empty($promotion_discount_info) ||
                        ($list->shop_id == $shop_id && $promotion_discount_info['shop_id'] == $shop_id && ($promotion_discount_info['level'] < $list->level)) ||
                        ($promotion_discount_info['shop_id'] != $shop_id && $list->shop_id == $shop_id)) {
                    $promotion_discount_info['level'] = $list->level;
                    $promotion_discount_info['discount_num'] = $list->discount_num;
                    $promotion_discount_info['discount_id'] = $list->discount_id;
                    $promotion_discount_info['discount_name'] = $list->discount_name;
                    $promotion_discount_info['shop_id'] = $list->shop_id;
                    $promotion_discount_info['start_time'] = $list->start_time;
                    $promotion_discount_info['end_time'] = $list->end_time;
                    $promotion_discount_info['discount_type'] = $list->discount_type;
                    $promotion_discount_info['integer_type'] = $list->integer_type;
                }
            } elseif ($list->range == 2) {//部分商品可用
                if ($list->goods()->where('goods_id', $goods_id)->count() > 0) {
                    if (empty($promotion_discount_info) ||
                            ($list->shop_id == $shop_id && $promotion_discount_info['shop_id'] == $shop_id && ($promotion_discount_info['level'] < $list->level)) ||
                            ($promotion_discount_info['shop_id'] != $shop_id && $list->shop_id == $shop_id)) {
                        $promotion_discount_info['level'] = $list->level;
                        $promotion_discount_info['discount_num'] = $list->goods()->where('goods_id', $goods_id)->find()['discount'] ?: 10;
                        $promotion_discount_info['discount_id'] = $list->discount_id;
                        $promotion_discount_info['discount_name'] = $list->discount_name;
                        $promotion_discount_info['shop_id'] = $list->shop_id;
                        $promotion_discount_info['start_time'] = $list->start_time;
                        $promotion_discount_info['end_time'] = $list->end_time;
                        $promotion_discount_info['discount_type'] = $list->discount_type;
                        $promotion_discount_info['integer_type'] = $list->integer_type;
                    }
                } 
            } else { //分类商品可用 --待处理
                //获取商品分类
                $goods_info = $this->goods_ser->getGoodsDetailById($goods_id, 'category_id_1,category_id_2,category_id_3,category_id,promote_id,promotion_type');
                //需要判断商品是否存在活动id，是则不参加分类折扣
                if ($goods_info['category_id'] && (intval($goods_info['promote_id']) == 0 || intval($goods_info['promotion_type']) == 0)) { //存在3级分类 -- 变更 大分类包含小分类 需要
                    $goods_category_arr = [
                        $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
                    ];
                    $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                    if (!$goods_category_arr || !array_intersect($goods_category_arr,explode(',', $list->category_extend_id))) {
                        continue;
                    }
                    if (empty($promotion_discount_info) ||
                            ($list->shop_id == $shop_id && $promotion_discount_info['shop_id'] == $shop_id && ($promotion_discount_info['level'] < $list->level)) ||
                            ($promotion_discount_info['shop_id'] != $shop_id && $list->shop_id == $shop_id)) {
                        $promotion_discount_info['level'] = $list->level;
                        if ($list->discount_type == 1) { //比例
                            $promotion_discount_info['discount_num'] = floatval($list->uniform_discount ?: 10);
                        } else { //固定
                            $promotion_discount_info['discount_num'] = floatval($list->uniform_price);
                        }

                        $promotion_discount_info['discount_id'] = $list->discount_id;
                        $promotion_discount_info['status'] = $list->status;
                        $promotion_discount_info['discount_name'] = $list->discount_name;
                        $promotion_discount_info['shop_id'] = $list->shop_id;
                        $promotion_discount_info['start_time'] = $list->start_time;
                        $promotion_discount_info['end_time'] = $list->end_time;
                        $promotion_discount_info['discount_type'] = $list->discount_type;
                        $promotion_discount_info['integer_type'] = $list->integer_type;
                    }
                }
            }
        }

        return empty($promotion_discount_info) ? $default : $promotion_discount_info;
    }

    //更新限时折扣
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::updatePromotionDiscount()
     */
    public function updatePromotionDiscount($discount_id, $discount_name, $start_time, $end_time, $remark, $goods_id_array,$level,$range,$status,$range_type,$discount_num,$discount_type,$uniform_discount_type,$uniform_discount,$integer_type,$uniform_price_type,$uniform_price,$category_extend_id,$extend_category_id_1s,$extend_category_id_2s,$extend_category_id_3s)
    {
        $promotion_discount = new VslPromotionDiscountModel();
        $goodsSer = new GoodsService();
        $promotion_discount->startTrans();
        try {
            $shop_name = $this->instance_name;
            $data = array(
                'discount_name' => $discount_name,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'shop_name' => $shop_name,
                'status' => 0,
                'remark' => $remark,
                'level' => $level,
                'range' => $range,
                'status' => 0,
                'range_type' => $range_type,
                'discount_num' => $discount_num,
                'create_time' => time(),
                'discount_type'=>$discount_type,
                'uniform_discount_type'=>$uniform_discount_type,
                'uniform_discount'=>$uniform_discount,
                'integer_type'=>$integer_type,
                'uniform_price_type'=>$uniform_price_type,
                'uniform_price'=>$uniform_price,
                'category_extend_id'=>$category_extend_id,
                'extend_category_id_1s'=>$extend_category_id_1s,
                'extend_category_id_2s'=>$extend_category_id_2s,
                'extend_category_id_3s'=>$extend_category_id_3s
            );
            $promotion_discount->save($data, [
                'discount_id' => $discount_id
            ]);
            $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
            
            //先将旧的商品prmotion清0，再重新加
            $old_id_array = $promotion_discount_goods->Query(['discount_id'=>$discount_id],'goods_id');
            $goodsSer->updateGoods(['goods_id'=>['in',$old_id_array]], ['promotion_type' => 0]);
            $goods_id_array = explode(',', $goods_id_array);
            $promotion_discount_goods->destroy([
                'discount_id' => $discount_id
            ]);
            foreach ($goods_id_array as $k => $v) {
                $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
                $discount_info = explode(':', $v);
                $count = $this->getGoodsIsDiscount($discount_info[0], $start_time, $end_time);
                // 查询商品名称图片
                if ($count > 0) {
                    $promotion_discount->rollback();
                    return ACTIVE_REPRET;
                }
                // 查询商品名称图片
                $goods_info = $this->goods_ser->getGoodsDetailById($discount_info[0], 'goods_name,picture');

                $dis = $discount_info[1];
                //new
                if($uniform_discount_type == 1){
                    $dis = $uniform_discount;
                }elseif($uniform_price_type == 1){
                    $dis = $uniform_price;
                }

                $data_goods = array(
                    'discount_id' => $discount_id,
                    'goods_id' => $discount_info[0],
                    'discount' => $dis,
                    'status' => 0,
                    'start_time' => getTimeTurnTimeStamp($start_time),
                    'end_time' => getTimeTurnTimeStamp($end_time),
                    'goods_name' => $goods_info['goods_name'],
                    'goods_picture' => $goods_info['picture'],
                    'discount_type'=>$discount_type
                );
                $this->updatePromotionType($range,$discount_info[0],$discount_id);
                $promotion_discount_goods->save($data_goods);
            }
            $promotion_discount->commit();
            return $discount_id;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取限时折扣列表
     */
    public function getDiscountGoodsList($page_index, $page_size, $condition, $order = '')
    {
        $discountModel = new VslPromotionDiscountModel();
        return $discountModel->getDiscountGoodsViewList($page_index, $page_size, $condition, $order);
    }

    /**
     * 计算商品的抢购价
     * @param $discount_id
     * @param $price
     * @return float|string
     */
    public function calculationDiscountPrice($discount_id, $price)
    {
        $discountModel = new VslPromotionDiscountModel();
//        $discountInfo = $discountModel->getInfo(['discount_id' => $discount_id], 'discount_type,uniform_discount_type,uniform_discount,integer_type,uniform_price');
        $discountInfo = $discountModel::get(['discount_id' => $discount_id],['goods']);
        if (!$discountInfo) {
            return $price;
        }

        if ($discountInfo['discount_type'] == 1) {
            $uniform_discount = reset($discountInfo['goods'])['discount'];
            $price = $discountInfo['integer_type'] == 1 ? round($price * $discountInfo['uniform_discount'], 2) : bcmul($price, $uniform_discount, 2);
        } else {
            $price = $discountInfo['uniform_price'];
        }

        return $price;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::addPromotiondiscount()
     */
    public function addPromotiondiscount($discount_name, $start_time, $end_time, $remark, $goods_id_array,$level,$range,$status,$range_type,$discount_num,$shop_id,$discount_type,$uniform_discount_type,$uniform_discount,$integer_type,$uniform_price_type,$uniform_price,$category_extend_id,$extend_category_id_1s,$extend_category_id_2s,$extend_category_id_3s)
    {
        $promotion_discount = new VslPromotionDiscountModel();
        Db::startTrans();
        try {
            //print_r($goods_id_array);exit;
            $shop_name = $this->instance_name;
            $data = array(
                'discount_name' => $discount_name,
                'start_time' => getTimeTurnTimeStamp($start_time),
                'end_time' => getTimeTurnTimeStamp($end_time),
                'shop_id' => $shop_id,
                'shop_name' => $shop_name,
                'status' => 0,  //直接添加状态变更为未添加状态 即原1直接变更为0
                'level' => $level,
                'range' => $range,
                'range_type'=>$range_type,
                'discount_num'=>$discount_num,
                'remark' => $remark,
                'create_time' => time(),
                'website_id'=>$this->website_id,
                'discount_type'=>$discount_type,
                'uniform_discount_type'=>$uniform_discount_type,
                'uniform_discount'=>$uniform_discount,
                'integer_type'=>$integer_type,
                'uniform_price_type'=>$uniform_price_type,
                'uniform_price'=>$uniform_price, 
                'category_extend_id'=>$category_extend_id,
                'extend_category_id_1s'=>$extend_category_id_1s,
                'extend_category_id_2s'=>$extend_category_id_2s,
                'extend_category_id_3s'=>$extend_category_id_3s
            );
            
            $promotion_discount->save($data);
            $discount_id = $promotion_discount->discount_id;
            $goods_id_array = explode(',', $goods_id_array);
            $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
            $promotion_discount_goods->destroy([
                'discount_id' => $discount_id
            ]);
            $new_goods_id_array = '';
            if($goods_id_array){
                foreach ($goods_id_array as $k => $v) {
                    //改版，检测关掉 2018.4.25
                    // 添加检测考虑商品在一个时间段内只能有一种活动
                    $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
                    $discount_info = explode(':', $v);
                    $goods_info = $this->goods_ser->getGoodsDetailById($discount_info[0], 'goods_name,picture');
                    $dis = $discount_info[1];
                    $new_goods_id_array .= $discount_info[0].',';
                    //new
                    if($uniform_discount_type == 1){
                        $dis = $uniform_discount;
                    }elseif($uniform_price_type == 1){
                        $dis = $uniform_price;
                    }
                    $data_goods = array(
                        'discount_id' => $discount_id,
                        'goods_id' => $discount_info[0],
                        'discount' => $dis,
                        'status' => 0,
                        'start_time' => getTimeTurnTimeStamp($start_time),
                        'end_time' => getTimeTurnTimeStamp($end_time),
                        'goods_name' => $goods_info['goods_name'],
                        'goods_picture' => $goods_info['picture'],
                        'discount_type'=>$discount_type
                    );
                    if($goods_info){
                        $promotion_discount_goods->save($data_goods);
                    }
                    
                }
            }
            //商品状态也变更至活动列表执行 折扣商品列表可以写入  在次写入活动列表
            if($new_goods_id_array){
                $new_goods_id_array = rtrim($new_goods_id_array, ',');
            }
            $act_data = array(
                'shop_id' =>$shop_id,
                'website_id'=>$this->website_id,
                'status'=>0,
                'type'=>1,
                'act_id'=>$discount_id,
                'stime'=>getTimeTurnTimeStamp($start_time),
                'etime'=>getTimeTurnTimeStamp($end_time),
                'goods_id'=> $new_goods_id_array,
                'category_extend_id'=> $category_extend_id
            );
            $activeListServer = new ActiveList();
            $activeListServer->addActive($act_data);
            //延时队列处理砍价活动开始的状态
            $url = config('rabbit_interface_url.url');
            $back_url1 = $url.'/rabbitTask/activityStatus';
            $delay_time1 = strtotime($start_time) - time();
            $this->actDiscountDelay($discount_id, $delay_time1, $back_url1);
            //延时队列处理砍价活动结束的promotion_type
            $back_url2 = $url.'/rabbitTask/upActivityGoodsProType';
            $delay_time2 = strtotime($end_time) - time();
            $this->actDiscountDelay($discount_id, $delay_time2, $back_url2);

            Db::commit();
            return $discount_id;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
    /**
     * 处理活动过期
     * @param $discount_id
     * @param $end_time
     */
    public function actDiscountDelay($discount_id, $delay_time, $back_url)
    {
        if(config('is_high_powered')){
            $website_id = $this->website_id;
            $config['delay_exchange_name'] = config('rabbit_delay_activity.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_activity.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_activity.delay_routing_key');
            //查询出订单配置过期自动关闭时间
            $delay_time = $delay_time * 1000;//毫秒
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'type' => 'discount',
                'discount_id' => $discount_id,
                'website_id' => $website_id
            ];
            $data = json_encode($data);
            $custom_type = 'activity_promotion';//活动
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    //获取最优折扣活动
    public function getBestDiscount($shopid)
    {
        $discountModel = new VslPromotionDiscountModel();
        if ($shopid == '0') {
            $result = $discountModel->where('`status` =1 and `start_time`<' . time() . ' and `end_time` >' . time() . ' and (`range`= 1 OR `range`=2) and `website_id` = ' . $this->website_id)->order('`level` desc')->find();
        } else {
            $result = $discountModel->where('`status` =1 and `shop_id` != 0 and (`range`=3 OR `range`=2) and `start_time`<' . time() . ' and `end_time` >' . time() . ' and `website_id` = ' . $this->website_id)->order('`level` desc')->find();
        }
        return $result;
    }

    //活动价格
    public function getDiscountPrice($price, $discount_num)
    {
        $promotion_price = sprintf("%.2f", $price * $discount_num / 10);
        return $promotion_price;
    }

    /**
     * (non-PHPdoc)
     * 获取限时折扣详情
     * @see \data\api\IPromote::getPromotionDiscountDetail()
     */
    public function getPromotionDiscountDetail($discount_id, $select_goods = true)
    {
        $websiteService = new WebSite();
        $promotion_discount = new VslPromotionDiscountModel();
        $promotion_detail = $promotion_discount->get($discount_id);
        if ($select_goods) {
            $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
            $promotion_goods_list = $promotion_discount_goods->getQuery([
                'discount_id' => $discount_id
                    ], '*', '');
            if (!empty($promotion_goods_list)) {
                $picture = new AlbumPictureModel();
                foreach ($promotion_goods_list as $k => $v) {
                    $goods_info = $this->goods_ser->getGoodsDetailById($v['goods_id'], 'goods_name,picture,price,stock,website_id,shop_id', 1, 0, 0, 1);
                    $v['picture_info'] = [
                        'pic_cover' => $goods_info['album_picture']['pic_cover'],
                        'pic_cover_mid' => $goods_info['album_picture']['pic_cover_mid'],
                        'pic_cover_micro' => $goods_info['album_picture']['pic_cover_micro']
                    ];
                    $v['price'] = $goods_info['price'];
                    $v['stock'] = $goods_info['stock'];
                    $promotion_goods_list[$k]['shop_name'] = $websiteService->getWebMallName($this->website_id);
                    if (getAddons('shop', $this->website_id)) {
                        $promotion_goods_list[$k]['shop_name'] = $goods_info['shop_name'];
                    }
                }
            }

            $promotion_detail['goods_list'] = $promotion_goods_list;
            
        }
        //获取分类 
        $extend_category_array = array();
        if (!empty($promotion_detail['category_extend_id'])) {
            $extend_category_ids = $promotion_detail['category_extend_id'];
            $extend_category_id_1s = $promotion_detail['extend_category_id_1s'];
            $extend_category_id_2s = $promotion_detail['extend_category_id_2s'];
            $extend_category_id_3s = $promotion_detail['extend_category_id_3s'];
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
        $promotion_detail['extend_category_name'] = "";
        $promotion_detail['extend_category'] = $extend_category_array;
        $category_extend_id = intval($promotion_detail['category_extend_id']);
    
        if($category_extend_id > 0){
            $goodsCategory = new GoodsCategory();
            $promotion_detail['category_name'] = $goodsCategory->getName($category_extend_id) ? $goodsCategory->getName($category_extend_id)['category_name'] : '';
        }else{
            $promotion_detail['category_name'] = '';
        }
        
        return $promotion_detail;
    }

    /**
     * (non-PHPdoc)
     * 获取限时折扣详情
     * @see \data\api\IPromote::getPromotionDiscountDetail()
     */
    public function getPromotionDiscountDetailPage($page_index, $page_size, $discount_id)
    {
        $websiteService = new WebSite();
        $promotion_discount = new VslPromotionDiscountModel();
        $promotion_detail = $promotion_discount->get($discount_id);
        $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
        $category_extend_id = intval($promotion_detail['category_extend_id']);
        if($category_extend_id > 0){
            $goodsCategory = new GoodsCategory();
            $promotion_detail['category_name'] = $goodsCategory->getName($category_extend_id) ? $goodsCategory->getName($category_extend_id)['category_name'] : '';
        }else{
            $promotion_detail['category_name'] = '';
        }
        $promotion_goods_list = $promotion_discount_goods->pageQuery($page_index, $page_size, [
            'discount_id' => $discount_id
                ], '', '*');
        if (!empty($promotion_goods_list['data'])) {
            $picture = new AlbumPictureModel();
            
            foreach ($promotion_goods_list['data'] as $k => $v) {
                $goods_info = $this->goods_ser->getGoodsDetailById($v['goods_id'], 'goods_name,picture,price,stock,website_id,shop_id', 1, 0, 0, 1);
                $v['picture_info'] = [
                    'pic_cover' => $goods_info['album_picture']['pic_cover'],
                    'pic_cover_mid' => $goods_info['album_picture']['pic_cover_mid'],
                    'pic_cover_micro' => $goods_info['album_picture']['pic_cover_micro']
                ];
                $v['price'] = $goods_info['price'];
                $v['stock'] = $goods_info['stock'];
                $promotion_goods_list['data'][$k]['shop_name'] = $websiteService->getWebMallName($this->website_id);
                if (getAddons('shop', $this->website_id)) {
                    $promotion_goods_list['data'][$k]['shop_name'] = $goods_info['shop_name'];
                }
            }
        }
        $promotion_detail['goods_list'] = $promotion_goods_list;
        return $promotion_detail;
    }

    /**
     *
     * {@inheritdoc}
     * 删除限时折扣
     * @see \data\api\IPromote::delPromotionDiscount()
     */
    public function delPromotionDiscount($discount_id)
    {
        $promotion_discount = new VslPromotionDiscountModel();
        $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
        $goodsSer = new GoodsService();
        $activeListServer = new ActiveList();
        $promotion_discount->startTrans();
        try {
            $discount_id_array = explode(',', $discount_id);
            $retval = 1;
            foreach ($discount_id_array as $k => $v) {
                $promotion_detail = $promotion_discount->get($discount_id);
                if ($promotion_detail['status'] == 1) {
                    $promotion_discount->rollback();
                    return - 1;
                }
                //查出对应参加限时折扣的商品
                $goods_ids_list = $promotion_discount_goods->getQuery(['discount_id' => $v], 'goods_id', '');
                $goods_ids_list = objToArr($goods_ids_list);
                if ($goods_ids_list) {
                    $goods_ids_arr = array_column($goods_ids_list, 'goods_id');
                    $goods_condition['goods_id'] = ['in', $goods_ids_arr];
                    $goods_condition['promotion_type'] = 5;
                    $goods_condition['website_id'] = $this->website_id;
                    $goodsSer->updateGoods($goods_condition,['promotion_type' => 0]);
                }
                $promotion_discount->destroy($v);
                $activeListServer->delActive($v, 1);
                $retval = $promotion_discount_goods->destroy([
                    'discount_id' => $v
                ]);
                if (!$retval) {
                    $retval = 0;
                }
            }
            $promotion_discount->commit();
            return 1;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IPromote::getPromotionDiscountList()
     */
    public function getPromotionDiscountList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc')
    {
        $promotion_discount = new VslPromotionDiscountModel();
        $list = $promotion_discount->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    //关闭限时折扣
    public function closePromotionDiscount($discount_id)
    {
        $promotion_discount = new VslPromotionDiscountModel();
        $promotion_discount->startTrans();
        try {
            $retval = $promotion_discount->save([
                'status' => 3
                    ], [
                'discount_id' => $discount_id
            ]);
            if ($retval == 1) {
                //活动列表变更为结束
                $activeListServer = new ActiveList();
                $activeListServer->changeActive($discount_id,1,2,$this->website_id);

                $data_goods = array(
                    'promotion_type' => 5,
                    'promote_id' => $discount_id
                );
                $goods_id_list = $this->goods_ser->getGoodsListByCondition($data_goods, 'goods_id');
                if (!empty($goods_id_list)) {

                    foreach ($goods_id_list as $k => $goods_id) {
                        $goods_info = $this->goods_ser->getGoodsDetailById($goods_id['goods_id'], 'price');
                        $this->goods_ser->updateGoods([
                            'goods_id' => $goods_id['goods_id']
                        ], [
                        'promotion_price' => $goods_info['price']
                            ], $goods_id['goods_id']);
                        $goods_sku = new VslGoodsSkuModel();
                        $goods_sku_list = $goods_sku->getQuery([
                            'goods_id' => $goods_id['goods_id']
                                ], 'price,sku_id', '');
                        foreach ($goods_sku_list as $k_sku => $sku) {
                            $goods_sku = new VslGoodsSkuModel();
                            $data_goods_sku = array(
                                'promote_price' => $sku['price']
                            );
                            $goods_sku->save($data_goods_sku, [
                                'sku_id' => $sku['sku_id']
                            ]);
                        }
                    }
                }
                $this->goods_ser->updateGoods($data_goods, [
                    'promotion_type' => 0,
                    'promote_id' => 0
                        ]);
                $promotion_discount_goods = new VslPromotionDiscountGoodsModel();
                $retval = $promotion_discount_goods->save([
                    'status' => 3
                        ], [
                    'discount_id' => $discount_id
                ]);
                //更新活动状态
                $activeListServer = new ActiveList();
                $activeListServer->changeActive($discount_id,1,2,$this->website_id);
            }
            $promotion_discount->commit();
            return $retval;
        } catch (\Exception $e) {
            $promotion_discount->rollback();
            return $e->getMessage();
        }
    }
    //判断是否是折扣商品
    //$type: 店铺ID，0表自营店，取范围为自营和全平台（1,2）
    //               !=0表店铺，取范围为全平台和店铺（2，3）
    public function checkIsDiscountProduct($goods_id, $type)
    {
        if(empty($goods_id)){
            return "商品信息不存在";
        }
        $time = time();
        if ($type == '0') {
            $sql = "SELECT a.* ,b.`goods_id`,b.`goods_name`,b.`status`,b.`discount` FROM `vsl_promotion_discount` AS a LEFT JOIN `vsl_promotion_discount_goods` AS b ON a.`discount_id` = b.`discount_id` WHERE a.`website_id` = $this->website_id and ((b.`goods_id` = $goods_id and a.range = 2) or a.`range` = 1) and (a.`range_type` = 1 or a.`range_type` = 2) and a.`start_time` < $time and a.`end_time` > $time and a.`status` = 1 ORDER BY a.`level` asc LIMIT 0,1";
        } else {
            $sql = "SELECT a.* ,b.`goods_id`,b.`goods_name`,b.`status`,b.`discount` FROM `vsl_promotion_discount` AS a LEFT JOIN `vsl_promotion_discount_goods` AS b ON a.`discount_id` = b.`discount_id` WHERE a.`website_id` = $this->website_id and ((b.`goods_id` = $goods_id and a.range = 2) or a.`range` = 1) and (a.`range_type` = 3 or a.`range_type` = 2) and a.`start_time` < $time and a.`end_time` > $time and a.`status` = 1 ORDER BY a.`shop_id` desc, a.`level` asc LIMIT 0,1";
        }
        $result = Db::query($sql);
        return $result;

    }
    //修改商品活动类型
    public function updatePromotionType($range, $goodsid='', $discount_id = 0){

        if($goodsid!=0 || $range==2){
            $goodsSer = new GoodsService();
            $condition['goods_id'] = ['in', $goodsid];
            $data['promotion_type'] = 5;
            $data['promote_id'] = $discount_id;//商品表更新
            $res = $goodsSer->updateGoods($condition, $data);
            return $res;
        }
    }
    
    /**
     * 查询商品在某一时间段是否有限时折扣活动
     * @param unknown $goods_id
     */
    public function getGoodsIsDiscount($goods_id, $start_time, $end_time)
    {
        $discount_goods = new VslPromotionDiscountGoodsModel();
        $condition_1 = array(
            'start_time'=> array('ELT', $end_time),
            'end_time'  => array('EGT', $end_time),
            'status'     => array('NEQ', 3),
            'goods_id'  => $goods_id
        );
        $condition_2 = array(
            'start_time'=> array('ELT', $start_time),
            'end_time'  => array('EGT', $start_time),
            'status'     => array('NEQ', 3),
            'goods_id'  => $goods_id
        );
        $condition_3 = array(
            'start_time'=> array('EGT', $start_time),
            'end_time'  => array('ELT', $end_time),
            'status'     => array('NEQ', 3),
            'goods_id'  => $goods_id
        );
        $count_1 = $discount_goods->where($condition_1)->count();
        $count_2 = $discount_goods->where($condition_2)->count();
        $count_3 = $discount_goods->where($condition_3)->count();
        $count = $count_1 + $count_2 + $count_3;
        return $count;
    }
    /**
     * 获取 一个商品的 限时折扣信息
     * @param unknown $goods_id
     */
    public function getDiscountByGoodsid($goods_id){
        $discount_goods = new VslPromotionDiscountGoodsModel();
        $discount = $discount_goods->getInfo(['goods_id'=>$goods_id, 'status'=>1], 'discount');
        if(empty($discount)){
            return -1;
        }else{
            return $discount['discount'];
        }
    }
    

    /**
     * 根据限时折扣规则查询商品信息
     * @param int    $website_id
     * @param int    $shop_id
     * @param int    $page_size
     * @param int    $goods_type //0自营 1全平台 2店铺
     * @param string $search_text [搜索的商品名]
     * @param string $goods_ids [商品id，用‘,’分割] $goods_ids=0表示
     * @return array
     */
    public function getStartingPromotionDiscountData ($website_id=0, $shop_id=0,$page_size=10,$goods_type=2,$search_text='',array $goods_ids=[])
    {
        $field1 = '*';
        $field2 = 'goods_id,goods_name,goods_picture,discount_type,discount,goods_name,goods_picture';
        $cate_field = 'goods_id,goods_name,picture,shop_id';
        
        $website_id = $website_id?:$this->website_id;
        $shop_id = $shop_id?:$this->instance_id;
    
        $time = time();
        $discount_condition = [
            'end_time' => ['>=', $time],
            'website_id' => $website_id,
            'shop_id' => $shop_id,
        ];
        if (empty($goods_ids)) {
            $discount_condition['start_time'] = ['<',$time];
        }
        if ($goods_type==1) {
            unset($discount_condition['shop_id']);
        } else if ($goods_type==0) {
            $discount_condition['shop_id'] = 0;
        }
        $promotion_discount_model = new VslPromotionDiscountModel();
        $list = $promotion_discount_model->getQuery($discount_condition, $field1,'discount_id asc');
        if (!$list) {
            return [];
        }
        foreach ($list as $key => $val)
        {
            //非分类
            if ($val['range'] != 3) {
                $promotion_discount_goods_model = new VslPromotionDiscountGoodsModel();
                $condition1 = ['discount_id' => $val['discount_id']];
                #### 传入的筛选条件
                if ($search_text) {
                    $condition1['goods_name'] = ['like', "%" . $search_text . "%"];
                }
                if ($goods_ids) {
                    $condition1['goods_id'] = ['in',$goods_ids];
                }
                $list[$key]['discount_goods'] =  $promotion_discount_goods_model->getQuery($condition1, $field2);
            }
        }
        $list = objToArr($list);
        //处理商品数据 vsl_goods sys_album_picture
        $new_goods_list = [];
        $num = 0;
        $has_geted_goods_ids = [];//已查询的商品id
        foreach ($list as $key => $val)
        {
            if ($num >= $page_size) {break;}
            $goodsSer = new GoodsService();
//            if ($val['discount_goods'] && !$val['category_extend_id']) {
            if ($val['range']!=3) {/*非分类*/
                $goods_ids_list = array_column($val['discount_goods'],'goods_id');
                $j_list = array_intersect($has_geted_goods_ids,$goods_ids_list);
                $goods_ids_list = array_diff($goods_ids_list,$j_list);
                $has_geted_goods_ids = array_merge($has_geted_goods_ids,$goods_ids_list);
                if (!$goods_ids_list){continue;}
                $goods_list = $goodsSer->getQueryGoodsDataOfPicture(['goods_id'=>['in',$goods_ids_list]]);
                //处理价格
                $integer_type = $val['integer_type'];//1取整 0不取整
                foreach ($val['discount_goods'] as $k => $v)
                {
                    if ($goods_list[$v['goods_id']]) {
                        $temp = array_merge($v,$goods_list[$v['goods_id']]);
                        if ($temp['discount_type'] == 1) {
                            $temp['price'] = roundLengthNumber($temp['discount']*$temp['price'],2, $integer_type);
                        } else if ($temp['discount_type'] == 2) {
                            $temp['price'] = $temp['discount'];
                        }
                        $temp['discount_price'] = $temp['price'];
                        $temp['start_time'] = $val['start_time'];
                        $temp['end_time'] = $val['end_time'];
                        if ($this->instance_id == 0) {
                            $temp['goods_type'] = $val['shop_id'] >0 ? 1 : 0;
                        }else{
                            $temp['goods_type'] = 2;
                        }
                        $num++;
                        array_push($new_goods_list,$temp);
                        unset($val['discount_goods'][$k]);
                        unset($goods_list[$v['goods_id']]);
                        continue;
                    }
                }
            } else {
                //分类
                $end_num = $page_size - $num;
                if ($end_num <=0){break;}
                $category_arr = explode(',', $val['category_extend_id']);//分类id
                $condition2 = ['category_id' => ['in',$category_arr]];
                #### 传入的筛选条件
                if ($search_text) {
                    $condition2['goods_name'] = ['like', "%" . $search_text . "%"];
                }
                if ($goods_ids) {
                    $condition2['goods_id'] = ['in',$goods_ids];
                }
                $cate_goods_list = $goodsSer->getSearchGoodsList(1,$end_num, $condition2,'',$cate_field);
                $cate_goods_list = objToArr($cate_goods_list);
                if (!$cate_goods_list['data']) {continue;}
                $cate_goods_ids_list = array_column($cate_goods_list['data'],'goods_id');
                $j_list = array_intersect($has_geted_goods_ids,$cate_goods_ids_list);
                $cate_goods_ids_list = array_diff($cate_goods_ids_list,$j_list);
                $has_geted_goods_ids = array_merge($has_geted_goods_ids,$cate_goods_ids_list);
                if (!$cate_goods_ids_list) {
                    continue;
                }
                $goods_data = $goodsSer->getQueryGoodsDataOfPicture(['goods_id'=>['in',$cate_goods_ids_list]]);
                $integer_type = $val['integer_type'];//1取整 0不取整
                foreach ($cate_goods_list['data'] as $c_k => $c_v)
                {
                    if ($goods_data[$c_v['goods_id']]) {
                        $temp = array_merge($c_v,$goods_data[$c_v['goods_id']]);
                        if ($val['discount_type'] == 1){/*折扣*/
                            if ($val['uniform_discount_type'] == 1){/*统一折扣状态*/
                                //$val['uniform_discount'];
                                $temp['price'] = roundLengthNumber($temp['uniform_discount']*$temp['price'],2, $integer_type);
                            }
                        } else {/*固定价*/
                            if ($val['uniform_price_type'] == 1){/*统一价格状态*/
                                $temp['price'] = $val['uniform_price'];
                            }
                        }
                        $temp['discount_price'] = $temp['price'];
                        $temp['start_time'] = $val['start_time'];
                        $temp['end_time'] = $val['end_time'];
                        if ($this->instance_id == 0) {
                            $temp['goods_type'] = $val['shop_id'] >0 ? 1 : 0;
                        }else{
                            $temp['goods_type'] = 2;
                        }
                        $num++;
                        array_push($new_goods_list,$temp);
                        unset($cate_goods_list['data'][$c_k]);
                        unset($goods_data[$c_v['goods_id']]);
                        continue;
                    }
                }
            }
        }
        return $new_goods_list;
    }
}