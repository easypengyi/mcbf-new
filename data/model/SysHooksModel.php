<?php
/**
 * SysHooksModel.php
 *
 * 微商来 - 专业移动应用开发商!
 * =========================================================
 * Copyright (c) 2014 广州领客信息科技有限公司, 保留所有权利。
 * ----------------------------------------------
 * 官方网址: http://www.vslai.com
 * 
 * 任何企业和个人不允许对程序代码以任何形式任何目的再发布。
 * =========================================================



 */

namespace data\model;

use data\model\BaseModel as BaseModel;
/**
 * 后台用户表
 */
class SysHooksModel extends BaseModel {
    
    protected $table = 'sys_hooks';
    protected $rule = [
        'id'  =>  '',
    ];
    protected $msg = [
        'id'  =>  '',
    ];
}