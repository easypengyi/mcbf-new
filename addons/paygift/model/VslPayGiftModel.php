<?php

namespace addons\paygift\model;

use data\model\BaseModel as BaseModel;
use data\model\AlbumPictureModel;

/**
 * 支付有礼活动表
 * @author  www.vslai.com
 *
 */
class VslPayGiftModel extends BaseModel
{

    protected $table = 'vsl_pay_gift';
    
    /*
     * 获取列表
     */
    public function getPaygiftViewList($page_index, $page_size, $condition, $order)
    {
        $queryList = $this->getPaygiftViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getPaygiftViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /*
     * 获取数据
     */
    public function getPaygiftViewQuery($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->field('pay_gift_id,paygift_name,start_time,end_time,state,modes,modes_id,modes_money');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /*
     * 获取各状态数量
     */
    public function getPaygiftNum($condition)
    {
        unset($condition['state']);
        $wholeCount = $this->getPaygiftViewCount($condition);
        $condition['state'] = 1;
        $stayCount = $this->getPaygiftViewCount($condition);
        $condition['state'] = 2;
        $startCount = $this->getPaygiftViewCount($condition);
        $condition['state'] = 3;
        $endCount = $this->getPaygiftViewCount($condition);
        $count['whole'] = $wholeCount;
        $count['stay'] = $stayCount;
        $count['start'] = $startCount;
        $count['end'] = $endCount;
        return $count;
    }
    /*
     * 获取数量
     */
    public function getPaygiftViewCount($condition)
    {
        $count = $this->getCount($condition);
        return $count;
    }
    /*
     * 获取支付有礼详情
     */
    public function getPaygiftDetail($condition){
        $detail = $this->getInfo($condition,'');
        $detail['modes_goods'] = [];
        if($detail['modes']==2){
            $goods = [];
            $goodsSer = new \data\service\Goods();
            $vslgoods = $goodsSer->getGoodsDetailById($detail['modes_id'], 'goods_id,goods_name,price,picture', 1);
            $goods["goods_id"] = $vslgoods['goods_id'];
            $goods["goods_name"] = $vslgoods['goods_name'];
            $goods["price"] = $vslgoods['price'];
            $goods['pic_cover'] = __IMG($vslgoods['album_picture']['pic_cover']);
            $detail['modes_goods'] = $goods;
        }
        return $detail;
    }
}