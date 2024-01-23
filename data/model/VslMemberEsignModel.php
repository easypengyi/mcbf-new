<?php

namespace data\model;

use data\model\BaseModel as BaseModel;

/**
 * e签宝信息
 * @author  www.vslai.com
 *
 */
class VslMemberEsignModel extends BaseModel {
    protected $table = 'vsl_member_esign';
    protected $rule = [
        'uid'  =>  '',
    ];
    protected $msg = [
        'uid'  =>  '',
    ];
}
