<?php
/**
 * ModuleModel.php
 *
 * 微商来 - 专业移动应用开发商!
 * =========================================================
 * Copyright (c) 2014 广州领客信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.vslai.com
 * 
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================



 */
namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 预售商品表
 * @author Administrator
 *
 */
class VslPresellModel extends BaseModel {

    protected $table = 'vsl_presell';
    
    
    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getPresellGoodsViewList($page_index, $page_size, $condition, $order)
    {
        $queryList = $this->getPresellGoodsViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getPresellGoodsViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /*
     * 获取数据
     */
    public function getPresellGoodsViewQuery($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->alias('ng')
                        ->join('vsl_goods vg','ng.goods_id = vg.goods_id','left')
                        ->join('sys_album_picture sap','vg.picture = sap.pic_id', 'left')
                        ->field('ng.*,vg.goods_name,vg.price,sap.pic_cover_mid,sap.pic_cover');
        
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /*
     * 获取数量
     */
    public function getPresellGoodsViewCount($condition)
    {
        $viewObj = $this->alias('ng')
                        ->join('vsl_goods vg','ng.goods_id = vg.goods_id','left')
                        ->join('sys_album_picture sap','vg.picture = sap.pic_id', 'left')
                        ->field('ng.*,vg.goods_name,vg.price,sap.pic_cover_mid,sap.pic_cover');
        
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}
