<?php
/**
 * AlbumPictureModel.php
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
use data\model\AlbumPictureModel as AlbumPictureModel;
use think\Db;
use data\model\BaseModel as BaseModel;
/**
 * 图片model
 */
class VslMemberCheckLogModel extends BaseModel {

    protected $table = 'vsl_member_check_log';
    protected $rule = [
        'uid'  =>  '',
    ];
    protected $msg = [
        'uid'  =>  '',
    ];

    /**后台添加区分普通会员或者分销商身份**/
    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList($page_index, $page_size, $condition, $order)
    {

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
        $viewObj = $this->field('id,create_time,status,remark');
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
        $viewObj = $this->field('id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }

    /**后台添加区分普通会员或者分销商身份**/
    /**
     * 获取列表返回数据格式
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param unknown $order
     * @return unknown
     */
    public function getViewList1($page_index, $page_size, $condition, $order)
    {

        $queryList = $this->getViewQuery1($page_index, $page_size, $condition, $order);
        $queryCount = $this->getViewCount1($condition);
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
    public function getViewQuery1($page_index, $page_size, $condition, $order)
    {
        $viewObj = $this->alias('nm')
            ->join('sys_user su','nm.uid= su.uid','left')
            ->field('nm.id,nm.image_url,nm.create_time,nm.status,nm.remark,nm.score,
            su.uid,su.nick_name,su.user_name,su.real_name,su.user_tel');

        //设置查询视图
        $list = $this->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);
        return $list;
    }
    /**
     * 获取列表数量
     * @param unknown $condition
     * @return \data\model\unknown
     */
    public function getViewCount1($condition)
    {
        $viewObj = $this->alias('nm')
            ->join('sys_user su','nm.uid= su.uid','left')
            ->field('nm.id');
        $count = $this->viewCount($viewObj,$condition);
        return $count;
    }
}