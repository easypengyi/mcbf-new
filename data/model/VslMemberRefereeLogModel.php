<?php

namespace data\model;

use data\model\BaseModel as BaseModel;

/**
 * 修改推荐人记录
 */
class VslMemberRefereeLogModel extends BaseModel
{

    protected $table = 'vsl_member_referee_log';
    protected $primary_key = 'id';
    protected $rule = [
        'id' => '',
    ];
    protected $msg = [
        'id' => '',
    ];

    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList($page_index, $page_size, $condition, $order){
        $queryList = $this->getViewQuery($page_index, $page_size, $condition, $order);
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
    public function getViewQuery($page_index, $page_size, $condition, $order)
    {
        //设置查询视图
        $viewObj = $this->alias('l')
            ->join('sys_user su','l.create_uid = su.uid','left')
            ->join('sys_user sus','l.referee_id = sus.uid','left')
            ->field('l.*,su.user_tel as admin_user_name,sus.user_name as referee_user_name,
        sus.nick_name as referee_nick_name, sus.user_tel as referee_user_tel, sus.user_headimg as referee_user_headimg');
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
        $viewObj = $this->alias('l')
            ->join('sys_user su','l.create_uid = su.uid','left')
            ->join('sys_user sur','l.referee_id = sur.uid','left')
            ->field('l.id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}
