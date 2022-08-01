<?php

namespace addons\orderbarrage\model;

use data\model\BaseModel as BaseModel;

/**
 * 订单弹幕配置
 * @author  www.vslai.com
 *
 */
class VslOrderBarrageConfigModel extends BaseModel
{

    public $table = 'vsl_order_barrage_config';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;

    /**
     * 关联订单弹幕规则表
     * @return \think\model\Relation
     */
    public function rule()
    {
        return $this->hasMany('VslOrderBarrageRuleModel', 'config_id');
    }
}