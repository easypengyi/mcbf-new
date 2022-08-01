<?php
namespace addons\distribution\model;

use data\model\BaseModel as BaseModel;
/**
 * 分销商绑定记录
 * @author  www.vslai.com
 *
 */
class VslDistributorRefereeLogModel extends BaseModel {

    protected $table = 'vsl_distributor_referee_log';
    protected $rule = [
        'id'  =>  '',
    ];
    protected $msg = [
        'id'  =>  '',
    ];

/**
 * 多字段查询
 *
 */
    public function findBy($where=[], $field=['*'])
    {
         return $this->field($field)->where($where)->find();
    }
}