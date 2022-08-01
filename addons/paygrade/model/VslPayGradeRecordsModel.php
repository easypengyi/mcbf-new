<?php

namespace addons\paygrade\model;

use data\model\BaseModel as BaseModel;

/**
 * 付费等级购买记录表
 * @author  www.vslai.com
 *
 */
class VslPayGradeRecordsModel extends BaseModel
{

    protected $table = 'vsl_pay_grade_records';
    
    
    /*
     * 获取分页列表
     */
    public function getRecordViewList($page_index, $page_size, $condition, $order)
    {
        $queryList = $this->getRecordViewQuery($page_index, $page_size, $condition, $order);
        $queryCount = $this->getRecordViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /*
     * 获取数据
     */
    public function getRecordViewQuery($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->alias('pgr')
        ->join('sys_user u', 'u.uid = pgr.uid', 'left')
        ->field('pgr.*,u.nick_name,u.user_name,u.user_tel,u.user_headimg');
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /*
     * 获取数量
     */
    public function getRecordViewCount($condition)
    {
        $viewObj = $this->alias('pgr')
        ->join('sys_user u', 'u.uid = pgr.uid', 'left');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
    
    /*
     * 获取购买记录详情
     */
    public function getRecordDetail($condition, $field, $order){
        $detail = $this->where($condition)->field($field)->order($order)->find();
        return $detail;
    }
}