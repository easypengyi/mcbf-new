<?php
namespace data\model;

use data\model\BaseModel as BaseModel;
use data\model\VslGoodsGroupModel as VslGoodsGroupModel;
use data\model\VslGoodsSkuModel as VslGoodsSkuModel;
use think\Db;

/**
 * 商品表视图
 * @author  www.vslai.com
 *
 */
class VslGoodsViewModel extends BaseModel {

    protected $table = 'vsl_goods';
    
    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getGoodsViewList($page_index, $page_size, $condition = [], $order = '', $field="*"){
        $condition['ng.for_store'] = 0;
        $queryList = $this->getGoodsViewQuery($page_index, $page_size, $condition, $order, $field);
        $queryCount = $this->getGoodsrViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /**
     * 查询商品的视图
     * @param unknown $condition
     * @param unknown $field
     * @param unknown $order
     * @return unknown
     */
    public function getGoodsViewQueryField($condition, $field, $order=""){
        $viewObj = $this->alias('ng')
            ->join('vsl_goods_category ngc','ng.category_id = ngc.category_id','left')
            ->join('vsl_goods_brand ngb','ng.brand_id = ngb.brand_id','left')
            ->join('sys_album_picture sap','ng.picture = sap.pic_id', 'left')
            ->join('vsl_shop nss','ng.website_id = nss.website_id','left')
            ->field($field);
        $list = $viewObj->where($condition)
        ->order($order)
        ->select();
        return $list;
    }
    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getGoodsViewQuery($page_index, $page_size, $condition, $order, $field = "*")
    {
        $viewObj = $this->alias('ng')
        ->join('vsl_goods_category ngc','ng.category_id = ngc.category_id','left')
        ->join('vsl_goods_brand ngb','ng.brand_id = ngb.brand_id','left')
        ->join('sys_album_picture sap','ng.picture = sap.pic_id', 'left')
        ->join('vsl_shop nss','ng.shop_id = nss.shop_id and ng.website_id = nss.website_id','left')
        ->join('vsl_supplier s', 'ng.supplier_info = s.supplier_id', 'left')
        ->field($field.',ng.short_name as short_name, ngc.short_name as category_short_name');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        if(!empty($list))
        {
            $goods_group_model = new VslGoodsGroupModel();
            $goods_sku = new VslGoodsSkuModel();
            foreach ($list as $k=>$v)
            {
                if(isset($v['group_id_array'])){
                    //获取group列表
                    $group_name_query = $goods_group_model->all($v['group_id_array']);

                    $list[$k]['group_query'] = $group_name_query;
                }
                
                //获取sku列表
                $sku_list = $goods_sku->where(['goods_id'=>$v['goods_id']])->select();
                if(!$sku_list){
                    $goods_sku->save(['goods_id' => $v['goods_id'],'market_price' => $v['market_price'],'price' => $v['price'],'promote_price' => $v['price'],'cost_price' => $v['cost_price'],'stock' => $v['stock'],'create_date' => time(),'code' => $v['code'], 'QRcode' => $v['QRcode']]);
                    $sku_list = $goods_sku->where(['goods_id'=>$v['goods_id']])->select();
                }
                $list[$k]['sku_list'] = $sku_list;
            }
        }
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getGoodsrViewCount($condition)
    {
        $viewObj = $this->alias('ng')
        ->join('vsl_goods_category ngc','ng.category_id = ngc.category_id','left')
        ->join('vsl_goods_brand ngb','ng.brand_id = ngb.brand_id','left')
        ->join('sys_album_picture sap','ng.picture = sap.pic_id', 'left')
        ->join('vsl_shop nss','ng.shop_id = nss.shop_id and ng.website_id = nss.website_id','left')
        ->join('vsl_supplier s', 'ng.supplier_info = s.supplier_id', 'left')
        ->field('ng.goods_id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

    public function wapGoods($page_index = 1, $page_size = PAGESIZE, $condition = [], $field = '*', $order = '', $group = '')
    {
        $condition['ng.for_store'] = 0;
        $view_obj = $this->alias('ng')
            ->join('vsl_goods_sku ngs', 'ng.goods_id = ngs.goods_id', 'LEFT')
            ->join('sys_album_picture sap', 'ng.picture = sap.pic_id', 'LEFT')
            ->join('vsl_shop vs', 'ng.shop_id = vs.shop_id and ng.website_id = vs.website_id', 'LEFT')
            ->join('vsl_goods_discount vgd', 'vgd.goods_id = ng.goods_id', 'LEFT')
            ->join('vsl_supplier s', 'ng.supplier_info = s.supplier_id', 'LEFT')
            ->field($field);
        $query_list = $this->viewPageQuerys($view_obj, $page_index, $page_size, $condition, $order, $group);
        $query_count = $this->alias('ng')
            ->join('vsl_goods_sku ngs', 'ng.goods_id = ngs.goods_id', 'LEFT')
            ->join('vsl_shop vs', 'ng.shop_id = vs.shop_id and ng.website_id = vs.website_id', 'LEFT')
            ->join('vsl_goods_discount vgd', 'vgd.goods_id = ng.goods_id', 'LEFT')
            ->join('vsl_supplier s', 'ng.supplier_info = s.supplier_id', 'LEFT')
            ->field('COUNT(ng.goods_id)')
            ->where($condition)
            ->group($group)
            ->select();
        $query_count = count($query_count);

        $list = $this->setReturnList($query_list, $query_count, $page_size);
        return $list;
    }
    /**
     * 获取对应优惠券类型的优惠卷分类商品
     */
    public function getCouponGoodsLists($page_index, $page_size, $condition,$field, $order,$group)
    {
        $view_obj = $this->alias('ng')
        ->join('vsl_goods_sku ngs', 'ng.goods_id = ngs.goods_id', 'LEFT')
        ->join('sys_album_picture sap', 'ng.picture = sap.pic_id', 'LEFT')
        ->join('vsl_shop vs', 'ng.shop_id = vs.shop_id and ng.website_id = vs.website_id', 'LEFT')
        ->field($field);
        $query_list = $this->viewPageQuerys($view_obj, $page_index, $page_size, $condition, $order, $group);
        $query_count = $this->alias('ng')
        ->join('vsl_goods_sku ngs', 'ng.goods_id = ngs.goods_id', 'LEFT')
        ->join('vsl_shop vs', 'ng.shop_id = vs.shop_id and ng.website_id = vs.website_id', 'LEFT')
        ->where($condition)
        ->group($group)
        ->select();
        $query_count = count($query_count);
        $list = $this->setReturnList($query_list, $query_count, $page_size);
        return $list;
    }
}