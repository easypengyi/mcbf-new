<?php

namespace addons\paygrade\model;

use data\model\BaseModel as BaseModel;

/**
 * 付费等级套餐表
 * @author  www.vslai.com
 *
 */
class VslPayGradeSetmealModel extends BaseModel
{

    protected $table = 'vsl_pay_grade_setmeal';
    
    /*
     * 获取等级列表
     */
    public function getSetmealList($condition, $field, $order){
        $list = $this->getQuery($condition, $field, $order);
        return $list;
    }
}