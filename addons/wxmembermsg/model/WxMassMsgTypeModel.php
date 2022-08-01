<?php

namespace addons\wxmembermsg\model;

use data\model\BaseModel as BaseModel;

/**
 * 微信会员群发方式
 * @author  www.vslai.com
 *
 */
class WxMassMsgTypeModel extends BaseModel
{
    public $table = 'vsl_weixin_mass_msg_type';
    protected $rule = [
    ];
    protected $msg = [
    ];
    protected $autoWriteTimestamp = true;
}