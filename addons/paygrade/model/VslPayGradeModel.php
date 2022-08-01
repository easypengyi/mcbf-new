<?php

namespace addons\paygrade\model;

use data\model\BaseModel as BaseModel;

/**
 * 等级列表
 * @author  www.vslai.com
 *
 */
class VslPayGradeModel extends BaseModel
{

    protected $table = 'vsl_pay_grade';
    
    /*
     * 获取等级列表
     */
    public function getPayGradeList($condition, $field, $order){
        $list = $this->getQuery($condition, $field, $order);
        return $list;
    }
    /*
     * 获取等级详情
     */
    public function getDetail($condition, $field){
        $detail = $this->getInfo($condition, $field);
        return $detail;
    }
}