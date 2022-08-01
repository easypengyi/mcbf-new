<?php

namespace addons\miniprogram\model;

use data\model\BaseModel as BaseModel;
use think\Db;

/**
 * 小程序提交审核历史
 *
 */
class MpSubmitModel extends BaseModel
{
    protected $table = 'sys_mp_submit';

    public function auth()
    {
        return $this->belongsTo('WeixinAuthModel', 'auth_id', 'auth_id');
    }

    /**
     * 取出最新状态记录，以auth_id分组
     * @param int $status [提交状态0为审核成功，1为审核失败，2为审核中 3发布失败 4发布成功]
     * @return mixed
     */
    public function getMpSubmitNewestListGroupOfAuthId($status = 2)
    {
        return Db::query(" SELECT s.id,s.status,s.auth_id,s.audit_id,s.website_id,s.submit_time,w.authorizer_access_token
 FROM ( SELECT `id`,`status`,`auth_id`,`audit_id`,`website_id`,`submit_time` FROM `sys_mp_submit` ORDER BY `id` DESC ) as s
 LEFT JOIN `sys_weixin_auth` as w ON s.auth_id = w.auth_id WHERE s.status={$status} GROUP BY s.auth_id");
    }
}
