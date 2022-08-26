<?php
namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 商品表
 * @author  www.vslai.com
 *
 */
class VslGoodsModel extends BaseModel {

    protected $table = 'vsl_goods';
    protected $rule = [
        'goods_id'  =>  '',
        'description'  =>  'no_html_parse',
        'goods_spec_format'  =>  'no_html_parse',
        'area_list'  =>  'no_html_parse',
    ];
    protected $msg = [
        'goods_id'  =>  '',
        'description'  =>  '',
        'goods_spec_format'  =>  ''
    ];

    const DEFAULT_GOODS_ID = 55;

    protected $autoWriteTimestamp = true;

    public function album_picture()
    {
        return $this->belongsTo('AlbumPictureModel','picture','pic_id');
    }

    public function sku()
    {
        return $this->hasMany('VslGoodsSkuModel','goods_id','goods_id');
    }

    public function shop()
    {
        return $this->belongsTo('\addons\shop\model\VslShopModel','shop_id','shop_id');
    }

    public function shipping_company()
    {
        return $this->belongsTo('VslOrderExpressCompanyModel','shipping_fee_id','co_id');
    }

    /*
     * 获取秒杀商品信息
     * **/
    public function getSeckillGoodsInfo($condition){
//        $this->alias()->field()->select
    }

    /**
     * 供应商市场
     */
    public function getSupplierMarketViewList($page_index, $page_size, $condition, $order){
        $queryList = $this->getSupplierMarketViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getSupplierMarketViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /**
     * 供应商市场
     */
    public function getSupplierMarketViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('g')
                        ->join('vsl_supplier s', 'g.supplier_id = s.supplier_id', 'left')
                        ->join('sys_album_picture sap', 'g.picture = sap.pic_id', 'left')
                        ->field('g.goods_id,g.shop_id,g.goods_name,g.price,g.cost_price,g.stock, g.state,g.supplier_id,g.payment_type,g.picture,g.supplier_rebate,g.illegal_reason,g.shop_list
                ,g.supplier_pick_status,g.supplier_goods_id,g.shop_list_second,s.supplier_name,s.shop_id as shop_id_s,sap.pic_cover_mid as goods_picture');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 供应商市场
     */
    public function getSupplierMarketViewCount($condition)
    {
        $viewObj = $this->alias('g')
                        ->join('vsl_supplier s', 'g.supplier_id = s.supplier_id', 'left')
                        ->join('sys_album_picture sap', 'g.picture = sap.pic_id', 'left')
                        ->field('g.goods_id,g.shop_id,g.goods_name,g.price,g.cost_price,g.stock, g.state,g.supplier_id,g.payment_type,g.picture,g.supplier_rebate,g.illegal_reason,g.shop_list
                ,g.supplier_pick_status,g.supplier_goods_id,g.shop_list_second,s.supplier_name,s.shop_id as shop_id_s,sap.pic_cover_mid as goods_picture');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

    /**
     * 商家端查询供应商市场商品列表数量（过滤关闭的供应商商品）
     */
    public function getSupplierMarketViewCount2($condition)
    {
        $viewObj = $this->alias('g')
                        ->join('vsl_supplier s', 'g.supplier_id = s.supplier_id', 'left');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

    /*
     * 供应商对应店铺单个商品
     */
    public function getSupplierOfShopGoodsInfo ($condition)
    {
        $goods_info = $this->alias('g')
                        ->join('sys_album_picture sap', 'g.picture = sap.pic_id', 'left')
                        ->field('g.goods_id,g.shop_id,g.goods_name,g.price,g.cost_price,g.stock, g.state,g.supplier_id,g.payment_type,g.picture,g.supplier_rebate,g.illegal_reason,g.shop_list
                ,g.supplier_pick_status,g.supplier_goods_id,g.shop_list_second,sap.pic_cover_mid as goods_picture')
                        ->where($condition)
                        ->find();
        return $goods_info;
    }

    /*
     * 店铺已选供应商的全部商品列表
     */
    public function getShopPickedSupplierGoodsData ($condition)
    {
        $goods_info = $this->alias('g')
                           ->join('sys_album_picture sap', 'g.picture = sap.pic_id', 'left')
                           ->field('g.goods_id,g.shop_id,g.goods_name,g.price,g.cost_price,g.stock, g.state,g.supplier_id,g.payment_type,g.picture,g.supplier_rebate,g.illegal_reason,g.shop_list
                ,g.supplier_pick_status,g.supplier_goods_id,g.shop_list_second,sap.pic_cover_mid as goods_picture')
                           ->where($condition)
                           ->select();
        return $goods_info;
    }
}
