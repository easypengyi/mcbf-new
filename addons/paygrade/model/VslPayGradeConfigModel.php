<?php

namespace addons\paygrade\model;

use data\model\BaseModel as BaseModel;

/**
 *付费等级配置表
 * @author  www.vslai.com
 *
 */
class VslPayGradeConfigModel extends BaseModel
{

    protected $table = 'vsl_pay_grade_config';
    
    /*
     * 获取等级详情
     */
    public function getDetail($condition, $field){
        $detail = $this->getInfo($condition, $field);
        return $detail;
    }
}