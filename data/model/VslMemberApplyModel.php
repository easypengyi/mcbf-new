<?php

namespace data\model;

use data\model\BaseModel as BaseModel;

/**
 * 申请vip
 */
class VslMemberApplyModel extends BaseModel
{

    protected $table = 'vsl_member_apply';
    protected $primary_key = 'id';

    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList($page_index, $page_size, $condition, $order, $field = ''){

        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order, $field);
        $queryCount = $this->getViewCount($condition);
        $list = $this->setReturnList($queryList, $queryCount, $page_size);
        return $list;
    }
    /**
     * 获取列表
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return \data\model\multitype:number
     */
    public function getViewQuery($page_index, $page_size, $condition, $order, $field = '')
    {
        $field = $field ?: 'a.id, a.uid, a.real_name as apply_real_name,a.nickname as apply_nickname,a.user_tel as apply_user_tel,
        a.type,a.image_url,a.create_time,a.status,
        nm.referee_id,su.user_headimg,su.user_name,su.real_name,su.user_tel, su.nick_name,sus.user_name as referee_user_name,
        sus.nick_name as referee_nick_name, sus.user_tel as referee_user_tel, sus.user_headimg as referee_user_headimg';

        //设置查询视图
        $viewObj = $this->alias('a')
            ->join('vsl_member nm','a.uid= nm.uid')
            ->join('sys_user su','a.uid= su.uid', 'left')
            ->join('sys_user sus','nm.referee_id= sus.uid','left')
            ->field($field);
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount($condition)
    {
        $viewObj = $this->alias('a')
            ->join('vsl_member nm','a.uid= nm.uid')
            ->join('sys_user su','a.uid= su.uid', 'left')
            ->field('a.uid');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

}
