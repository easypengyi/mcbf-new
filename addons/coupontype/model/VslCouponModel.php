<?php

namespace addons\coupontype\model;

use data\model\BaseModel as BaseModel;
use think\Db;

/**
 * 优惠券表
 * @author  www.vslai.com
 *
 */
class VslCouponModel extends BaseModel
{

    protected $table = 'vsl_coupon';
    protected $rule = [
        'coupon_id' => '',
    ];
    protected $msg = [
        'coupon_id' => '',
    ];

    public function coupon_type()
    {
        return $this->belongsTo('VslCouponTypeModel', 'coupon_type_id', 'coupon_type_id', 'nct');
    }

    public function user()
    {
        return $this->belongsTo('\data\model\UserModel', 'uid', 'uid');
    }

    public function shop()
    {
        return $this->belongsTo('\addons\shop\model\VslShopModel', 'shop_id', 'shop_id');
    }

    public function orderInfo()
    {
        return $this->belongsTo('\data\model\VslOrderModel', 'order_id', 'use_order_id');
    }
    public function getCouponViewList($page_index, $page_size, $condition, $order){
        $queryList = $this->getCouponViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getCouponViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    public function getCouponViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('nc')
            ->join('vsl_coupon_type ct','nc.coupon_type_id = ct.coupon_type_id','left')
            ->join('vsl_shop ns','ct.shop_id = ns.shop_id AND ct.website_id = ns.website_id','left')
            ->field('nc.*, ct.*,ns.shop_name');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    public function getCouponViewCount($condition)
    {
        $viewObj = $this->alias('nc')
            ->join('vsl_coupon_type ct','nc.coupon_type_id = ct.coupon_type_id','left')
            ->join('vsl_shop ns','ct.shop_id = ns.shop_id AND ct.website_id = ns.website_id','left')
            ->field('ct.coupon_type_id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
    public function getCouponCounts($condition)
    {
        $viewObj = $this->alias('nc')
            ->join('vsl_coupon_type ct','nc.coupon_type_id = ct.coupon_type_id','left')
            ->field('nc.coupon_id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

    /**
     * 获取方式
     *
     * @param int $type
     * @return mixed|string
     */
    public static function types($type = 3){
        //1->订单,2->首页领取,3->注册营销获取,4->购物车获取,5->商品详情获取,6->复制链接领取,7->券包获取,8->活动领取,
        //9->签到,10->海报设置奖励,11->会员卡开通送优惠券12->会员卡周期送优惠券,13->定向送券,14->朋友赠送15->装修设置
        $arr = [
            1=> '订单',
            2=> '首页领取',
            3=> '注册营销获取',
            4=> '购物车获取',
            5=> '商品详情获取',
            6=> '复制链接领取',
            7=> '券包获取',
            8=> '活动领取',
            9=> '签到',
            10=> '海报设置奖励',
            11=> '会员卡开通送优惠券',
            12=> '会员卡周期送优惠券',
            13=> '定向送券',
            14=> '朋友赠送',
            15=> '装修设置'
        ];

        return isset($arr[$type]) ? $arr[$type] : '';
    }


}