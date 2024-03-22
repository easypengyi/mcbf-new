<?php
namespace data\model;

use data\model\BaseModel as BaseModel;

class VslAppointOrderModel extends BaseModel {

    protected $table = 'vsl_appoint_order';
    protected $rule = [
        'order_id'  =>  '',
    ];
    protected $msg = [
        'order_id'  =>  '',
    ];

    public function buyer()
    {
        return $this->belongsTo('UserModel', 'buyer_id', 'uid');
    }

    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList($page_index, $page_size, $condition, $order){

        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
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
    public function getViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('nm')
            ->join('sys_user su','nm.buyer_id= su.uid','left')
            ->join('vsl_goods g','nm.goods_id= g.goods_id','left')
            ->join('vsl_store s','nm.store_id= s.store_id','left')
            ->field('nm.*,su.uid,su.user_name as name,su.user_tel,su.nick_name,g.picture,g.goods_name,s.store_name');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount($condition)
    {
        $viewObj = $this->alias('nm')
            ->join('sys_user su','nm.buyer_id= su.uid','left')
            ->join('vsl_goods g','nm.goods_id= g.goods_id','left')
            ->join('vsl_store s','nm.store_id= s.store_id','left')
            ->field('nm.uid');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

}
