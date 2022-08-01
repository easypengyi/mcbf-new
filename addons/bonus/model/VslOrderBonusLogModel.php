<?php
namespace addons\bonus\model;

use data\model\BaseModel as BaseModel;

class VslOrderBonusLogModel extends BaseModel {

    protected $table = 'vsl_order_bonus_log';
    protected $rule = [
        'order_goods_id'  =>  '',
        'team_bonus_details'  =>  'no_html_parse',
        'global_bonus_details'  =>  'no_html_parse',
        'area_bonus_details'  =>  'no_html_parse',
    ];
    protected $msg = [
        'order_goods_id'  =>  '',
        'team_bonus_details'  =>  '',
        'global_bonus_details'  =>  '',
        'area_bonus_details'  =>  '',
    ];
}