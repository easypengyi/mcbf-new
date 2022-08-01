<?php

namespace app\wapbiz\controller;

/**
 * 用户权限控制器
 */
use data\service\AuthGroup as AuthGroup;
use addons\shop\service\Shop as Shop;

class Auth extends BaseController
{

    private $auth_group;

    public function __construct()
    {
        parent::__construct();
        $this->auth_group = new AuthGroup();
        if($this->port == 'supplier'){
            return json(['code' => -1, 'message' => '供应商端暂时无法操作']);
        }
    }

    /**
     * 子账号列表列表
     */
    public function userList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition = array(
            'sur.instance_id' => $this->instance_id,
            'su.website_id' => $this->website_id,
        );
        $user_list = $this->user->adminUserList($page_index, $page_size, $condition);
        $shopService = new Shop();
        if ($user_list['data']) {
            foreach ($user_list['data'] as $key => $val) {
                if($this->port == 'admin'){
                    $checkIsAdmin = $shopService->getShopByUid($val['uid']);
                    $user_list['data'][$key]['is_admin'] = $checkIsAdmin ? 1 : 0;
                }elseif($this->port == 'platform'){
                    $user_list['data'][$key]['is_admin'] = $val['uid'] == $val['adminid'] ? 1 : 0;
                }
            }
            unset($val);
        }
        $user_list['user_list'] = [];
        if($user_list['data']){
            $user_list['user_list'] = array_columns($user_list['data'], 'admin_name,uid,user,is_admin,user_tel');
        }
        unset($user_list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $user_list]);
    }

    /**
     * 添加 子账号
     */
    public function addUser()
    {
        $admin_name = request()->post('admin_name', '');
        $group_id = request()->post('group_id', '');
        $user_password = request()->post('user_password', '123456');
        $user = request()->post('user', '');
        $mobile = request()->post('mobile', '');
        if(!$group_id || !$user_password || !$mobile){
            return json(AjaxReturn(-1006));
        }
        if($this->port == 'platform'){
            $check = $this->user->selectAdminUser($mobile, $this->website_id);
            if ($check) {
                return json(['code' => '-1', 'message' => '手机号已在平台使用']);
            }
        }
        $condition = ['user_tel' => $mobile, 'website_id' => $this->website_id, 'port' => $this->port];
        $res = $this->user->checkAdminUser($condition);
        if ($res < 0) {
            $retval = $this->user->addAdminUser($admin_name, $group_id, $user_password, '', $user, $this->instance_id, $this->port, $mobile);
            if ($retval) {
                $this->addUserLogByParam('添加后台用户', $retval);
            }
            return json(AjaxReturn($retval));
        } else {
            return json(['code' => '-1', 'message' => '手机号已存在']);
        }
    }

    /**
     * 修改 子账号
     */
    public function editUser()
    {
        $uid = request()->post('uid', 0);
        $admin_name = request()->post('admin_name', '');
        $group_id = request()->post('group_id', 0);
        $user = request()->post('user', '');
        $mobile = request()->post('mobile', '');
        if($this->port == 'platform'){
            $check = $this->user->selectAdminUser($mobile, $this->website_id);
            if ($check) {
                return json(['code' => '-1', 'message' => '手机号已在平台使用']);
            }
        }
        
        $res = $this->user->checkAdminUser(['uid' => ['neq', $uid], 'user_tel' => $mobile, 'website_id' => $this->website_id, 'port' => $this->port]);
        if ($res < 0) {
            $retval = $this->user->editAdminUser($uid, $admin_name, $group_id, '', $user, $mobile);
            if ($retval) {
                $this->addUserLogByParam('添加后台用户', $retval);
            }
            return json(AjaxReturn($retval));
        } else {
            return json(['code' => '-1', 'message' => '手机号已存在']);
        }
    }
    /**
     * 获取员工信息
     * @return type
     */
    public function getUserInfo()
    {
        $uid = request()->post('uid', 0);
        if (!$uid) {
            return json(AjaxReturn(-1006));
        }
        $user_info = $this->user->getAdminUserInfo(['uid' => $uid, 'website_id' => $this->website_id], $field = "*");
        if (!$uid) {
            return json(['code' => -1, 'message' => '没有获取到用户信息']);
        }
        $is_admin = $user_info['is_admin'];
        if($this->port == 'admin'){
            $shopService = new Shop();
            $is_admin = $shopService->getShopByUid($uid);
        }elseif($this->port == 'platform'){
            $is_admin = $this->website->getWebByUid($uid);
        }
        if($is_admin){
            return json(['code' => -1, 'message' => '超级管理员无法编辑']);
        }
        $user_info['admin_name'] = substr($user_info['admin_name'], strpos($user_info['admin_name'], ':') + 1);
        $new_user_info = [
            'uid' => $user_info['uid'],
            'user' => $user_info['user'],
            'group_id_array' => $user_info['group_id_array'],
            'mobile' => $user_info['mobile'],
        ];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $new_user_info]);
    }

    /**
     * 角色列表
     */
    public function authGroupList()
    {
        $condition["instance_id"] = $this->instance_id;
        $condition["website_id"] = $this->website_id;
        $list = $this->auth_group->getSystemUserGroupAll($condition);
        $group_list = array_columns($list, 'group_id,group_name');
        return json(['code' => 1, 'message' => '获取成功', 'data' => $group_list]);
    }

}
