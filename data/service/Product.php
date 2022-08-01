<?php
namespace data\service;
/**
 * 产品服务层
 */
use data\service\BaseService as BaseService;
use data\model\VslProductModel;

class Product extends BaseService
{
    function __construct(){
        parent:: __construct();
    }
    
    /**
     * 产品列表
     */
    public function productList($page_index = 1, $page_size = 0, $condition = '', $order = 'create_time desc')
    {
        $vsl_product = new VslProductModel();
        $list = $vsl_product->getProductViewList($page_index, $page_size, $condition, $order);
        if($list['data']){
            foreach($list['data'] as $k => $v){
                $list['data'][$k]['product_pic'] = __IMG($v['product_pic']);
                $list['data'][$k]['create_time'] = date("Y-m-d H:i:s",$v['create_time']);
            }
            unset($v);
        }
        return $list;
    }
    
    /**
     * 添加
     */
    public function addProduct($input)
    {
        $vsl_product = new VslProductModel();
        $vsl_product->startTrans();
        try {
            $vsl_product->save($input);
            $vsl_product->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $vsl_product->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 修改
     */
    public function updateProduct($input ,$where)
    {
        $vsl_product = new VslProductModel();
        $vsl_product->startTrans();
        try {
            $vsl_product->save($input,$where);
            $vsl_product->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $vsl_product->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 删除
     */
    public function deleteProduct($product_id)
    {
        $vsl_product = new VslProductModel();
        $vsl_product->startTrans();
        try {
            $where['product_id'] = $product_id;
            $res = $vsl_product::destroy($where);
            $vsl_product->commit();
            return $res;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $vsl_voucher->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 获取详情
     */
    public function getProductDetail($condition)
    {
        $vsl_product = new VslProductModel();
        $info = $vsl_product->getInfo($condition);
        if($info){
            $info['product_pic'] = __IMG($info['product_pic']);
        }
        return $info;
    }
}