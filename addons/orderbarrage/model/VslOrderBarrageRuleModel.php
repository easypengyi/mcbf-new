<?php

namespace addons\orderbarrage\model;

use data\model\BaseModel as BaseModel;

/**
 * 订单弹幕配置 - 规则
 * @author  www.vslai.com
 *
 */
class VslOrderBarrageRuleModel extends BaseModel
{

    public $table = 'vsl_order_barrage_rule';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;

    /**
     * 关联订单弹幕配置表
     * @return \think\model\Relation
     */
    public function barrage_config()
    {
        return $this->belongsTo('VslOrderBarrageConfigModel');
    }
}