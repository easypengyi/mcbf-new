<?php

namespace addons\invoice\model;

use data\model\BaseModel as BaseModel;
use think\Db;

/**
 * 发票列表
 * @author  www.vslai.com
 *
 */
class VslInvoiceModel extends BaseModel
{

    public $table = 'vsl_invoice';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;

    public function invoice_config()
    {
        return $this->belongsTo('VslInvoiceConfigModel', 'config_id', 'id');
    }

    /**
     * 修改发票订单状态
     * @param $data array [order_no数组]
     * @param $type int [订单状态]
     * @return mixed
     */
    public function updateOrderStatus($data, $type)
    {
        $invoice = $this->alias('i')
            ->where('order_no', 'in', $data)
            ->update(['order_status' => $type]);

        return $invoice;
    }
    
    /**
     * 获取发票列表数据
     * @param $page_index
     * @param $page_size
     * @param $condition
     * @param $order
     * @return array|\data\model\multitype
     */
    public function getInvoiceViewList($page_index, $page_size, $condition, $order){
        $queryList = $this->getInvoiceViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getInvoiceViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    public function getInvoiceViewQuery($page_index, $page_size, $condition, $order)
    {
    
        //设置查询视图
        $viewObj = $this->alias('i')
                        ->join('vsl_order o','i.order_id = o.order_id','left')
//                        ->join('vsl_order_goods og','o.order_id = og.order_id','left')
//                        ->field('i.*, o.order_money,og.goods_name,og.num,og.sku_name,og.sku_attr');
                        ->field('i.*, o.order_money,o.order_status as actual_order_status');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    public function getInvoiceViewCount($condition)
    {
        $viewObj = $this->alias('i')
                        ->join('vsl_order o','i.order_id = o.order_id','left')
//                        ->join('vsl_order_goods og','o.order_id = og.order_id','left')
//                        ->field('i.*, o.order_money,og.goods_name,og.num,og.sku_name,og.sku_attr');
                        ->field('i.*, o.order_money');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}