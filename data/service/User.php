<?php

namespace data\service;

use addons\supplier\model\VslSupplierModel;
use data\model\AdminUserViewModel;
use data\model\SysAddonsModel;
use data\model\UserModel;
use data\model\VslIncreMentOrderModel;
use data\model\VslMemberAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberGroupModel;
use data\model\VslMemberRechargeModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use think\Db;
use \think\Session as Session;
use data\service\BaseService as BaseService;
use data\model\AdminUserModel as AdminUserModel;
use data\model\AuthGroupModel as AuthGroupModel;
use data\model\UserLogModel as UserLogModel;
use data\model\WebSiteModel;
use data\model\ModuleModel;
use data\model\WeixinFansModel;
use data\model\VslMemberLevelModel;
use addons\shop\model\VslShopModel;
use think\Cache;
use data\model\VslMemberModel;
use data\service\Order\Order as Orders;

class User extends BaseService
{
    public $user;
    public $error_login_url;

    function __construct()
    {
        parent::__construct();
        $this->user = new UserModel();
        $this->error_login_url = 'public/ErrorLog/mp/login_err.txt';
    }

    /**
     *
     * @return unknown
     */
    public function getUserInfo($uid = '', $field='*')
    {
        if(empty($uid)) {
            if(!$this->uid){
                return array();
            }
            $uid = $this->uid;
        }
        $res = $this->user->getInfo('uid=' . $uid, $field);
        return $res;
    }

    /**
     * 查询用户信息
     */
    public function getUserBaseInfo ($condition = '', $field = '*')
    {
        return $this->user->getInfo($condition,$field);
    }

    public function getUserInfoNew($condition,array $with = [])
    {
        $user_model = new UserModel();
        return $user_model::get($condition, $with);
    }

    public function getUserFirstData ($condition, $field = '*', $order='')
    {
        return $this->user->getFirstData($condition, $order, $field);
    }
    public function getUserLists($page_index, $page_size = 0, $condition, $order = '')
    {
        $list = $this->user->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    /**
     * 获取当前登录用户的uid
     */
    public function getSessionUid()
    {
        return $this->uid;
    }

    /**
     * 获取当前登录用户的实例ID
     */
    public function getSessionInstanceId()
    {
        return $this->instance_id;
    }
    /**
     * 获取当前登录移动商家端的端口
     */
    public function getSessionPort()
    {
        return $this->port;
    }

    /**
     * 获取当前登录用户的平台ID
     */
    public function getSessionWebsiteId()
    {
        return $this->website_id;
    }
    /**
     * 获取当前登录用户的供应商ID
     */
    public function getSessionSupplierId()
    {
        return $this->supplier_id;
    }

    /**
     * 获取当前登录用户是否是前台会员
     */
    public function getSessionUserIsMember()
    {
        return $this->is_member;
    }

    public function getSessionUserIsSystem()
    {
        return $this->is_system;
    }

    /**
     * 获取当前登录用户的权限列
     */
    public function getSessionModuleIdArray()
    {
        return $this->module_id_array;
    }

    public function getInstanceName()
    {
        if (empty($this->instance_name)) {
            $web_site = new WebSiteModel();
            $info = $web_site->getInfo(['website_id' => $this->website_id], 'title');
            return $info['title'];
        } else {
            return $this->instance_name;
        }
    }

    /**
     * 用户登录之后初始化数据
     * @param unknown $user_info
     */
    public function initLoginInfo($user_info, $port = '')
    {
        $model = $this->getRequestModel();
        if (Session::pull('oa_login_type')){
            // 因为回调的module是wapapi 所以设置一个标识 在保存登陆信息的时候保存在 shop 名下
            $model = 'shop';
        }
        Session::set($model . 'uid', $user_info['uid']);
        Session::set(md5($user_info['uid']), $user_info['uid']);
        Session::set($model . 'is_system', $user_info['is_system']);
        Session::set($model . $user_info['website_id'] . 'is_member', $user_info['is_member']);
        if($port){
            Session::set($model . 'port', $port);
        }
        if($model == 'admin' || $model == 'platform' || $port == 'admin' || $port == 'platform'){
            Session::set($model . 'instance_id', $user_info['instance_id']);
        }elseif ($model == 'supplier' || $port == 'supplier') {
            //如果是供应端，并且是普通供应商，那么instance_id写死为-1
            if($user_info['port']) {
                Session::set($model . 'instance_id', $user_info['instance_id']);
            }else{
                Session::set($model . 'instance_id', -1);
            }
        }

        Session::set($model . 'website_id', $user_info['website_id']);
        if($user_info['is_member']==1){
            if($model == 'shop' || $model == 'wap' || $model == 'wapapi'){
                $member = new Member();
                $member->uvRecord($user_info['uid']);
            }
        }
        $website = new WebSiteModel();
        $instance_name = $website->getInfo(['website_id' => $user_info['website_id']], 'title,create_time,mall_name,logo');
        Session::set($model . 'website_create_time'.$user_info['website_id'], $instance_name['create_time']);
        Session::set($model . 'logo', $instance_name['logo']);
        Session::set($model . 'mall_name', $instance_name['mall_name']);
        if ((($model == 'admin' || $model == 'supplier') && $user_info['port']) || $port == 'admin') {
            $shop_model = new VslShopModel();
            $shop_info = $shop_model->getInfo(['shop_id' => $user_info['instance_id']], 'shop_name');
            Session::set($model . 'instance_name', $shop_info['shop_name']);
            Session::set($model . 'website_ids',$user_info['website_id']);
        } elseif ($model == 'platform' || $port == 'platform') {
            Session::set($model . 'instance_name', $instance_name['title']);
            //平台化版本设置不同
        } elseif ($model == 'master') {
            Session::set($model . 'instance_name', '微商来');
        }
        if($model == 'supplier' || $port == 'supplier') {
            $supplierModel = new VslSupplierModel();
            $supplier_info = $supplierModel->getInfo(['supplier_id' => $user_info['supplier_id']], 'supplier_name');
            Session::set($model.'supplier_id', $user_info['supplier_id']);
            Session::set($model.'supplier_name', $supplier_info['supplier_name']);
        }
        $module = new ModuleModel();
        $addons = new SysAddonsModel();
        $addons_sign_module = '';
        $up_status_ids = $addons->Query(['up_status'=>2],'id');
        if($up_status_ids && !$port){
            foreach ($up_status_ids as $v){
                $addons_sign_module .= ','.implode(',',$module->Query(['addons_sign' => $v],'module_id'));
            }
            unset($v);
            if($addons_sign_module){
                $addons_sign_modules = explode(',',$addons_sign_module);
                foreach($addons_sign_modules as $k=>$v){
                    if( !$v )
                        unset($addons_sign_modules[$k] );
                }
                unset($v);
                Session::set('addons_sign_module', $addons_sign_modules);
            }
        }
        if ($user_info['is_system'] && $model != 'supplier' && $port != 'supplier') {
            $admin_info = new AdminUserModel();
            $admin_info = $admin_info->getInfo('uid=' . $user_info['uid'], 'is_admin,group_id_array');
            Session::set($model . 'is_admin', $admin_info['is_admin'] ?: '');
            $auth_group = new AuthGroupModel();
            $auth = $auth_group->getInfo(['group_id'=>$admin_info['group_id_array']]);
            Session::set($model . 'group_name', $auth['group_name'] ?: '');
            $system_auth = $auth_group->getInfo(['is_system'=>1,'instance_id'=>0,'website_id'=>$user_info['website_id']],'order_id,group_id');
            Session::set($model . 'group_is_system', $auth['is_system'] ?: 0);
            if($system_auth['order_id']){
                $bonus_id = [];
                $unbonus_id = [];
                $module = new ModuleModel();
                $addonsmodel = new SysAddonsModel();
                $module_infoId = $module->getInfo(['method'=>'bonusRecordList','module'=>'platform'],'module_id')['module_id'];
                $area_moduleId = $addonsmodel->getInfo(['name'=>'areabonus'],'module_id')['module_id'];
                $global_moduleId = $addonsmodel->getInfo(['name'=>'globalbonus'],'module_id')['module_id'];
                $team_moduleId = $addonsmodel->getInfo(['name'=>'teambonus'],'module_id')['module_id'];
                $order_ids = explode(',',$system_auth['order_id']);
                $order = new VslIncreMentOrderModel();
                $module_id_arrays = ',';
                $shop_module_id_arrays=',';
                $default_module_id_array = explode(',',$auth['module_id_array']);
                $default_shop_module_id_array = explode(',',$auth['shop_module_id_array']);
                foreach ($order_ids as $value){
                    $addons_id = $order->getInfo(['order_id'=>$value],'addons_id,expire_time');
                    $module_id = $module->Query(['addons_sign'=>$addons_id['addons_id'],'module'=>'platform'],'module_id');
                    $shop_module_id = $module->Query(['addons_sign'=>$addons_id['addons_id'],'module'=>'admin'],'module_id');
                    if(in_array($global_moduleId,$module_id) || in_array($area_moduleId,$module_id) || in_array($team_moduleId,$module_id)){
                        $bonus_id[] = $module_infoId;
                    }
                    if($addons_id['expire_time']>time()){
                        $module_id_array = implode(',',$module_id);
                        $shop_module_id_array = implode(',',$shop_module_id);
                        $module_id_arrays .= ','.$module_id_array;
                        $shop_module_id_arrays.= $shop_module_id_array;
                    }else{
                        if(in_array($global_moduleId,$module_id) || in_array($area_moduleId,$module_id) || in_array($team_moduleId,$module_id)){
                            $unbonus_id[] = $module_infoId;
                        }
                        $default_module_id_array = array_diff($default_module_id_array,$module_id);
                        $default_shop_module_id_array= array_diff($default_shop_module_id_array,$shop_module_id);
                    }
                }
                unset($value);
                if($system_auth['group_id'] == $auth['group_id']) {
                    //如果登录的是超级管理员才执行，因为增值应用支付完成时，并没有把权限加到相应的权限组里，所以在这里拼接
                    $auth['module_id_array'] = implode(',',$default_module_id_array).$module_id_arrays;
                    $auth['shop_module_id_array'] = implode(',',$default_shop_module_id_array).$shop_module_id_arrays;
                }

                if(count($bonus_id)==count($unbonus_id) && $bonus_id){
                    $unid = [];
                    $real_module_id_array = explode(',',$auth['module_id_array']);
                    $unid[] = $bonus_id[0];
                    $auth['module_id_array'] = implode(',',array_diff($real_module_id_array,$unid));
                }
            }
            $no_control = $this->getNoControlAuth();
            checkWebNeedUpdate($user_info['website_id']);
            Session::set($model . 'module_id_array', $no_control . $auth['module_id_array']);
            Session::set($model . 'shop_module_id_array', $no_control . $auth['shop_module_id_array']);
        }else{
            //供应商端口独立获取模块id
            $no_control = $this->getNoControlAuth();
            $supplier_module_id = $module->Query(['module' => 'supplier'],'module_id');
            if($supplier_module_id) {
                $supplier_module_id = implode(',',$supplier_module_id);
                Session::set($model . 'module_id_array', $no_control . $supplier_module_id);
            }
        }
        $data = array(
            'last_login_time' => $user_info['current_login_time'],
            'last_login_ip' => $user_info['current_login_ip'],
            'last_login_type' => $user_info['current_login_type'],
            'current_login_ip' => request()->ip(),
            'current_login_time' => time(),
            'current_login_type' => 1
        );
        //离线购物车同步
        $goods = new Goods();
        $goods->syncUserCart($user_info['uid']);
        $retval = $this->user->save($data, ['uid' => $user_info['uid']]);
        return $retval;
    }

    /**
     * 系统用户登录
     *
     * @param unknown $user_name
     * @param unknown $password
     */
    public function login($user_name, $password = '', $is_member = 0,$website_id=0, $mall_port=0, $port = '')
    { //0-无来源 1-微信公众号 2-PC 3-移动h5 4-小程序 5-app
        $this->Logout();
        $model = $this->getRequestModel();
        if($port){//移动商家端登陆,接口共用,以用户选择的端口为准
            $model = $port;
        }
	    $condition = array();
        if ($password){
            $condition['user_password'] = md5($password);
        }
        if($is_member == 2){//前台点击商家中心
            $condition['user_password'] = $password;
        }
        if ($model == 'shop' || $model == 'wap' || $model == 'wapapi') {
            //判断是第几种账号体系，并且是否设置绑定手机
            $website = new WebSiteModel();
            $website_info = $website->getInfo(['website_id' => $this->website_id], 'account_type, is_bind_phone');
            $condition['user_name|user_tel'] =  $user_name;
            $condition['is_member'] = 1;
            $condition['website_id'] = $this->website_id;
            if($website_info['account_type'] == 3){
                $condition['mall_port'] = $mall_port;//第三个参数 ： 1-公众号 2-小程序 3-移动H5  4-PC  5-APP
            }
        } elseif ($model == 'admin') {
            $condition['user_tel'] = $user_name;
			$condition['port'] = 'admin';
            $condition['website_id'] = $website_id;
        } elseif ($model == 'platform') {
            $condition['user_tel'] = $user_name;
			$condition['port'] = 'platform';
        } elseif ($model == 'master') {
            $condition['user_tel'] = $user_name;
            $condition['port'] = 'master';
        } elseif ($model == 'supplier') {//供应商
            $condition['user_tel'] = $user_name;
            $condition['supplier_id'] = ['>',0];
            $condition['website_id'] = $website_id;
        }
        if ($is_member == 1) {
            $condition['is_member'] = 1;
        }

        if($port){
            unset($condition['website_id']);
            $user_info = $this->user->getQuery($condition, 'uid,user_tel,user_status,user_name,user_headimg,is_system,instance_id,is_member,current_login_ip, current_login_time, current_login_type,website_id,supplier_id,port, login_num');
        }else{
            $user_info = $this->user->getInfo($condition, $field = 'uid,user_tel,user_status,user_name,user_headimg,is_system,instance_id,is_member,current_login_ip, current_login_time, current_login_type,website_id,supplier_id,port, login_num');
        }

        if (!empty($user_info)) {
            if($port){
                return $this->dealLoginSuccess($port, $user_info);
            }else{
                if ($user_info['user_status'] == 0) {
                    return USER_LOCK;
                } else {
                    $this->initLoginInfo($user_info, $port);
                    //登录成功后增加用户的登录次数
                    $set_inc_condition['uid'] = $user_info['uid'];
                    $this->user->save(['login_num'=>$user_info['login_num'] + 1,'user_token'=>md5($user_info['uid'])],$set_inc_condition);
                    if (in_array($model, ['wapapi','wap'])) {
                        return $user_info;
                    } else {
                        return 1;
                    }
                }
            }
        } else {
            if($website_info['account_type'] == 3){
                $condition['mall_port'] = 0;
                $user_info = $this->user->getInfo($condition, $field = 'uid,user_tel,user_status,user_name,user_headimg,is_system,instance_id,is_member,current_login_ip, current_login_time, current_login_type,website_id,mall_port');
                if($user_info && $user_info['mall_port'] == 0){//没有绑定的时候绑定上端口
                    $this->user->save( ['mall_port' => $mall_port], ['uid' => $user_info['uid']]);
                    if ($user_info['user_status'] == 0) {
                        return USER_LOCK;
                    } else {
                        $this->initLoginInfo($user_info);
                        //登录成功后增加用户的登录次数
                        $set_inc_condition['uid'] = $user_info['uid'];
                        $this->user->save(['login_num'=>$user_info['login_num'] + 1,'user_token'=>md5($user_info['uid'])],$set_inc_condition);
                        if (in_array($model, ['wapapi','wap'])) {
                            return $user_info;
                        } else {
                            return 1;
                        }
                    }
                }
            }
            return USER_ERROR;
        }
    }

    /**
     * 获取不控制权限模块组
     */
    public function getNoControlAuth($module = '')
    {
        $moudle = new ModuleModel();
        $list = $moudle->getQuery([
            "is_control_auth" => 0,
            "module" => $module
        ], "module_id", '');
        $str = "";
        foreach ($list as $v) {
            $str .= $v["module_id"] . ",";
        }
        unset($v);
        return $str;
    }

    public function qqLoginNew($qq, $website_id)
    {
        $this->Logout();
        $condition = array(
            'qq_openid' => $qq,
            'website_id' => $website_id
        );
        $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,user_headimg,is_system,instance_id,is_member, current_login_ip, current_login_time, current_login_type,website_id');
        if (!empty($user_info)) {
            if ($user_info['user_status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($user_info);
                return $user_info['uid'];
            }
        } else
            return USER_NBUND;
    }

    /**
     * 微信登录整理 by sgw
     * @param array $condition
     * @return array
     */
    public function wchatLoginNew($condition = [], $mall_port = 0)
    {
        // pc 微信登陆的时候回调地址(wapapi)和保存website_id(shop)的文件不一样，所以不存在website_id直接取shop保存的website id
        $website_id = $this->website_id ?: (Session::get('shopwebsite_id') ?: checkUrl());
        //获取当前是哪种账号体系
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id' => $website_id], 'account_type');
        if($website_info['account_type'] == 3){//账号体系第三种是独立的，先带端口查一下，如果没有查到则去掉端口查，查到了并且端口为空，则更新这条记录为当前端口，否则按照新账号处理。
            $condition['website_id'] = $website_id;
            $condition['mall_port'] = $mall_port;//(1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
            $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,nick_name,user_headimg,user_tel,sex,website_id,is_system
        ,is_member,instance_id,current_login_time,current_login_ip,current_login_type,wx_unionid,wx_openid,mp_open_id,login_num');
            if(!$user_info){
                $condition['mall_port'] = 0;
                $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,nick_name,user_headimg,user_tel,sex,website_id,is_system
        ,is_member,instance_id,current_login_time,current_login_ip,current_login_type,wx_unionid,wx_openid,mp_open_id,login_num,mall_port');
                if($user_info && $user_info['mall_port'] == 0){//如果是0，则说明这个账号还没有绑定端口,更改为当前端口
                    $this->user->where(['uid'=>$user_info['uid']])->update(['mall_port' => $mall_port]);//将该账号改为PC端。
                }else{//如果没查到或者商城端类型不为0，认为ta这个端没有账号
                    $user_info = '';
                }
            }
        }else{
            $condition['website_id'] = $website_id;
            $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,nick_name,user_headimg,user_tel,sex,website_id,is_system
        ,is_member,instance_id,current_login_time,current_login_ip,current_login_type,wx_unionid,wx_openid,mp_open_id,login_num');
        }

        if (!isset($user_info) || empty($user_info)) {
            return ['code' => USER_NBUND];
        }
        if ($user_info['user_status'] == 0) {
            return ['code' => USER_LOCK];
        }
        $this->initLoginInfo($user_info);
        $set_inc_condition['uid'] = $user_info['uid'];
        $this->user->save(['login_num' => $user_info['login_num'] + 1, 'user_token' => md5($user_info['uid'])], $set_inc_condition);
        return ['code' => 1, 'user_info' => $user_info];
    }

    public function loginNew($condition)
    {
        $this->Logout();
        $user_info = $this->user->getInfo($condition, '*');
        if (!empty($user_info)) {
            if ($user_info['user_status'] == 0) {
                return USER_LOCK;
            } else {
                    $this->initLoginInfo($user_info);
                    $set_inc_condition['uid'] = $user_info['uid'];
                    $this->user->save(['login_num' => $user_info['login_num'] + 1, 'user_token' => md5($user_info['uid'])], $set_inc_condition);
                return $user_info;
            }
        } else
            return USER_NBUND;

    }

    /**
     * 微信unionid登录(non-PHPdoc)
     * @see \ata\api\IUser::wchatUnionLogin()
     */
    public function wchatUnionLogin($unionid, $wx_openid = '', $mall_port = 0)
    {
        $this->Logout();
        // pc 微信登陆的时候回调地址(wapapi)和保存website_id(shop)的文件不一样，所以不存在website_id直接取shop保存的website id
        $website_id = $this->website_id ?: Session::get('shopwebsite_id');
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id' => $website_id], 'account_type');
        if($website_info['account_type'] == 3){//账号体系第三种是独立的，先带端口查一下，如果没有查到则去掉端口查，查到了并且端口为空，则更新这条记录为当前端口，否则按照新账号处理。
            $condition = array(
                'wx_unionid' => $unionid,
                'website_id' => $website_id,
                'mall_port' => $mall_port //pc的端口4 (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
            );
            $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,nick_name,user_headimg,user_tel,sex,website_id,is_system
        ,is_member,instance_id,current_login_time,current_login_ip,current_login_type,wx_unionid,wx_openid,mp_open_id,login_num');
            if(!$user_info){
//                unset($condition['mall_port']);//去掉端口查一下
                $condition['mall_port'] = 0;
                $user_info = $this->user->getInfo($condition, $field = 'uid,user_status,user_name,nick_name,user_headimg,user_tel,sex,website_id,is_system
        ,is_member,instance_id,current_login_time,current_login_ip,current_login_type,wx_unionid,wx_openid,mp_open_id,login_num,mall_port');
                if($user_info && $user_info['mall_port'] == 0){//如果是0，则说明这个账号还没有绑定端口,更改为当前端口
                    if($mall_port == 4){//PC端
                        $this->user->where(['uid'=>$user_info['uid']])->update(['mall_port' => $mall_port, 'pcwx_open_id' => $wx_openid]);//将该账号改为PC端。
                    }
                }else{//如果没查到或者商城端类型不为0，认为ta这个端没有账号
                    $user_info = '';
                }
            }
        }else{
            $condition = array(
                'wx_unionid' => $unionid,
                'website_id' => $website_id
            );
            $user_info = $this->user->getInfo($condition);
        }
        if (!empty($user_info)) {
            if ($user_info['user_status'] == 0) {
                return USER_LOCK;
            } else {
                if (isWeixin() && !empty($wx_openid)){
                    // 账号不可提现 && 微信环境下登陆使用的appid 生成的openid才能用于提现
                    $this->user->save(['wx_openid' => $wx_openid], $condition);
                }
                if($mall_port == 4){//PC端
                    $this->user->save(['pcwx_open_id' => $wx_openid], $condition);
                }
                $this->initLoginInfo($user_info);
                return $user_info['uid'];
            }
        } else
            return USER_NBUND;
    }

    /**
     * 检测用户是否具有打开权限(non-PHPdoc)
     *
     * @see \data\api\IAdmin::checkAuth()
     */
    public function checkAuth($module_id)
    {
        if ($this->is_admin) {
            return 1;
        } else {
            $module_id_array = explode(',', $this->module_id_array);
            if (in_array($module_id, $module_id_array)) {
                return 1;
            } else {
                return 0;
            }
        }
    }

    /**
     * 系统用户基础添加方式
     *
     * @param unknown $user_name
     * @param unknown $password
     * @param unknown $email
     * @param unknown $mobile
     */
    public function add($data)
    {
        $this->user = new UserModel();
        $user_name                  = $data['user_name'];
        $password                   = $data['password'];
        $email                      = $data['email'];
        $mobile                     = $data['mobile'];
        $is_system                  = $data['is_system'];
        $is_member                  = $data['is_member'];
        $qq_openid                  = $data['qq_openid'];
        $qq_info                    = $data['qq_info'];
        $wx_openid                  = $data['wx_openid'];
        $wx_info                    = $data['wx_info'];
        $wx_unionid                 = $data['wx_unionid'];
        $instance_id                = $data['instance_id'] ?: 0;
        $website_id                 = $data['website_id'] ?: 0;
        $port                       = $data['port'];
        $mp_open_id                 = $data['mp_open_id'];
        $pcwx_open_id               = $data['pcwx_open_id'];
        $app_wx_openid              = $data['app_wx_openid'];
        $mall_port                  = $data['mall_port'];
        $pic                        = $data['pic'];
        $nickname                   = $data['nickname'];
        $country_code               = $data['country_code'];
        $user_headimg               = $data['user_headimg'];
        $sex                        = $data['sex'];

        $where = [];
        if ($is_system) {
            $where['port'] = $port;
            if ($port == 'admin') {
                $where['website_id'] = $website_id;
            }
        } else {
            $where['website_id'] = $website_id;
            $where['port'] = '';
        }
        if (!empty($mobile)) {
            $where['user_tel'] = $mobile;
            $website = new WebSiteModel();
            $account_type = $website->getInfo(['website_id' => $website_id], 'account_type')['account_type'];
            if($account_type == 3){
                $where['mall_port'] = $mall_port;
            }
            $count = $this->user->where($where)->count();
            if ($count > 0) {
                return USER_REPEAT;
            }
            $nick_name = $mobile;
        } elseif (!empty($email)) {
            $where['user_email'] = $email;
            $count = $this->user->where($where)->count();
            if ($count > 0) {
                return USER_REPEAT;
            }
            $nick_name = $email;
        }
        $user_qq = '';
        if (!empty($qq_openid)) {
            $qq_info_array = json_decode($qq_info);
            $nick_name = $this->filterStr($qq_info_array->nickname);
            $user_head_img = $qq_info_array->figureurl_qq_2;
            $qq_info = $this->filterStr($qq_info);
        } elseif (!empty($wx_openid) || !empty($wx_unionid)) {
            $wx_info_array = json_decode($wx_info);
            $nick_name = $this->filterStr($wx_info_array->nickname);
            $user_head_img = $wx_info_array->headimgurl;
            $wx_info = $this->filterStr($wx_info);
        } else {
            $user_head_img = $pic ? $pic : '';
            $user_qq = $qq_info;
        }
        if($nickname){
            $nick_name = $nickname;
        }
        $user_head_img = $user_head_img ?: $user_headimg;

        if(!preg_match("/^[a-f0-9]{32}$/", $password)){
            $password = md5($password);
        }else{
            $password = $password;
        }

        $data = array(
            'user_name' => $user_name,
            'user_password' 		=> $password,
            'user_status'               => 1,
            'user_headimg'              => $user_head_img ?:'',
            'nick_name'                 => $nick_name?:'',//超级海报获取推送奖励信息的时候得不到nick_name，故加上。
            'is_system'                 => (bool)$is_system,
            'is_member'                 => $is_member,
            'user_tel'                  => $mobile,
            'user_tel_bind'             => 0,
            'user_qq'                   => $user_qq,
            'qq_openid'                 => $qq_openid,
            'qq_info'                   => $qq_info,
            'reg_time'                  => time(),
            'login_num'                 => 0,
            'user_email'                => $email,
            'user_email_bind'           => 0,
            'wx_sub_time'               => '0',
            'wx_notsub_time'            => '0',
            'wx_is_sub'                 => 0,
            'wx_info'                   => $wx_info,
            'other_info'                => '',
            'instance_id'               => $instance_id,
            'website_id'                => $website_id,
            'wx_openid'                 => $wx_openid,
            'wx_unionid'                => $wx_unionid,
            'port'                      => $port,
            'mp_open_id'                => $mp_open_id,
            'pcwx_open_id'              => $pcwx_open_id,
            'app_wx_openid'             => $app_wx_openid,
            'mall_port'                 => $mall_port,
            'country_code'              => $country_code ?: 86,
            'sex'                       => $sex,
        );


        $this->user->save($data);
        $uid = $this->user->uid;
        debugFile($uid, 'memberImport-手机号注册-uid', 1111114);
        //用户添加成功后
        $data['uid'] = $uid;
        // hook("userAddSuccess", $data);
        return $uid;
    }

    /**
     * 过滤特殊字符
     * @param unknown $str
     */
    public function filterStr($str)
    {
        if ($str) {
            $name = $str;
            $name = preg_replace_callback('/\xEE[\x80-\xBF][\x80-\xBF]|\xEF[\x81-\x83][\x80-\xBF]/', function ($matches) {
                return '';
            }, $name);
            $name = preg_replace_callback('/xE0[x80-x9F][x80-xBF]‘.‘|xED[xA0-xBF][x80-xBF]/S', function ($matches) {
                return '';
            }, $name);
            // 汉字不编码
            $name = json_encode($name);
            $name = preg_replace_callback("/\\\ud[0-9a-f]{3}/i", function ($matches) {
                return '';
            }, $name);
            if (!empty($name)) {
                $name = json_decode($name);
                return $name;
            } else {
                return '';
            }

        } else {
            return '';
        }
    }

    /**
     * 系统用户修改密码
     *
     * @param unknown $uid
     * @param unknown $old_password
     * @param unknown $new_password
     */
    public function ModifyUserPassword($uid, $old_password, $new_password)
    {
        $condition = array(
            'uid' => $uid,
            'user_password' => md5($old_password)
        );
        $res = $this->user->getInfo($condition, $field = "uid");
        if (!empty($res['uid'])) {
            $data = array(
                'user_password' => md5($new_password)
            );
            $res = $this->user->save($data, [
                'uid' => $uid
            ]);
            return $res;
        } else
            return PASSWORD_ERROR;
    }

    /**
     *
     * @param unknown $uid
     * @param unknown $user_name
     * @return number|string|Ambigous <number, \think\false, boolean, string>
     */
    public function ModifyUserName($website_id, $user_name)
    {
        $website = new WebSiteModel();
        $count = $website->where([
            'title' => $user_name
        ])->count();
        if ($count > 0) {
            return USER_REPEAT;
        }
        $data = array(
            'title' => $user_name
        );
        $res = $website->save($data, [
            'website_id' => $website_id
        ]);
        return $res;
    }

    /**
     * 添加用户登录日志
     *
     * @param unknown $uid
     * @param unknown $url
     * @param unknown $desc
     */
    public function addUserLog($uid, $is_system, $controller, $method, $ip, $get_data, $module_name, $supplier_id = 0)
    {
        $data = array(
            'uid' => $uid,
            'is_system' => $is_system,
            'controller' => $controller,
            'method' => $method,
            'ip' => $ip,
            'data' => $get_data,
            'module_name' => $module_name,
            'create_time' => time(),
            'instance_id' => $this->instance_id,
            'website_id' => $this->website_id,
            'supplier_id' => $supplier_id,
        );
        $user_log = new UserLogModel();
        $res = $user_log->save($data);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IUser::getUserDetail()
     */
    public function  getUserDetail($uid = null, $website_id = null)
    {
        if($uid){
            $this->uid = $uid;
        }
        $user_info = $this->user->getInfo(['uid' => $this->uid, 'website_id' => $this->website_id], '*');
        if (!empty($user_info['qq_openid'])) {
            $qq_info = json_decode($user_info['qq_info'], true);
            $user_info['qq_info_array'] = $qq_info;
        }
        if (!empty($user_info['wx_openid'])) {
            $user_info['wx_info'] = json_decode($user_info['wx_info'], true);
        }
        if (!empty($user_info['user_headimg'])) {
            $user_info['user_headimg'] = __IMG($user_info['user_headimg']);
        }
        $user_info['username'] = ($user_info['nick_name'])?$user_info['nick_name']:($user_info['user_tel']?$user_info['user_tel']:($user_info['user_name']?$user_info['user_name']:$user_info['uid']));
        return $user_info;
    }

    /**
     * 会员锁定
     * (non-PHPdoc)
     *
     * @see \ata\api\IUser::userLock()
     */
    public function userLock($uid)
    {
        //检测越权
        $user_info = $this->user->getInfo(['uid' => $uid], 'instance_id');
        if (empty($user_info)) {
            return NO_AITHORITY;
        }
        if($user_info['instance_id'] != $this->instance_id){
            return -2;
        }
        $retval = $this->user->save([
            'user_status' => 0
        ], [
            'uid' => $uid
        ]);

        return $retval;
    }

    /**
     * 会员自主申请锁定
     * (non-PHPdoc)
     *
     * @see \ata\api\IUser::userLock()
     */
    public function userWapLock($uid)
    {
        //检测越权
        $user_info = $this->user->getInfo(['uid' => $uid], 'uid');
        if (empty($user_info)) {
            return NO_AITHORITY;
        }
        $retval = $this->user->save([
            'user_status' => 0
        ], [
            'uid' => $uid
        ]);

        return $retval;
    }
    /**
     * 会员解锁
     *
     * @param unknown $uid
     * @return number|\think\false
     */
    public function userUnlock($uid)
    {
        $user_info = $this->user->getInfo(['uid' => $uid], 'instance_id');
        if (empty($user_info)) {
            return NO_AITHORITY;
        }
        if($user_info['instance_id'] != $this->instance_id){
            return -2;
        }
        $retval = $this->user->save([
            'user_status' => 1
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }

    /**
     * 用户退出
     */
    public function Logout($isChangeShop = false)
    {
        $model = $this->getRequestModel();
        if ($model == 'wapapi' && !Session::has('oa_login_type')) {
            // 因为在pc端第三方登陆的时候保存session 为 shop，回调为wapapi，取不到session，login的方法先执行logout，所以不要让这样情况清除掉标识oa_login_type
            Session::destroy();
        } else {
            Session::set($model . $this->website_id . 'is_member', '');
            Session::set($model . 'website_id', 0);
            Session::set(md5(Session::get($model . 'uid')), '');
            Session::set($model . 'instance_id', 0);
            Session::set($model . 'uid', '');
            Session::set($model . 'is_admin', 0);
            Session::set($model . 'module_id_array', '');
            Session::set($model . 'instance_name', '');
            Session::set($model . 'is_system', '');
            Session::set($model . 'website_create_time', '');
            Session::set($model . 'mall_name', '');
            Session::set($model . 'logo', '');
            Session::set($model . 'group_name', '');
            Session::set($model . 'group_is_system', 0);
            if(!$isChangeShop){ //移动商家端完全退出登陆的时候才清掉这两个缓存,切换店铺的时候还需要用到
                Session::set($model . 'port', '');
                Session::set($model . 'shop_or_sup_list', []);
            }
            Session::set($model . 'shop_module_id_array', []);
            Session::set('module_list', '');
            Session::set('addons_sign_module', []);
            Session::set($model .'supplier_id', '');
            Session::set($model .'supplier_name', '');
            $_SESSION["user_cart"] = '';
            Cache::set('WEBSITEINFO', '');
        }
    }

    /**
     * (non-PHPdoc)
     *  绑定邮箱
     * @see \ata\api\IUser::modifyEmail()
     */
    public function modifyEmail($uid, $email)
    {
        $user_info = $this->user->getInfo(['uid' => $uid], 'instance_id');
        if (empty($user_info) || $user_info['instance_id'] != $this->instance_id) {
            return NO_AITHORITY;
        }
        $retval = $this->user->save([
            'user_email' => $email

        ], [
            'uid' => $uid
        ]);
        return $retval;
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \ata\api\IUser::resetUserPassword()
     */
    public function resetUserPassword($uid)
    {
        $user_info = $this->user->getInfo(['uid' => $uid], 'instance_id');
        if (empty($user_info) || $user_info['instance_id'] != $this->instance_id) {
            return NO_AITHORITY;
        }
        $retval = $this->user->save([
            'user_password' => md5(123456)
        ], [
            'uid' => $uid
        ]);
        return 1;
    }
    public function updateUserPassword($pass_new,$pass)
    {
        $userInfo = $this->user->getInfo(['uid' => $this->uid], 'user_password,ucid');
        if($userInfo['user_password'] == md5($pass)){
            $retval = $this->user->save([
                'user_password' => md5($pass_new)
            ], [
                'uid' => $this->uid
            ]);
            if($retval){
                $website = new WebSite();
                $website->updateUcMemberPassword($userInfo['ucid'], $pass, $pass_new);
                return array(
                    'code'=>1,
                    'message'=>'修改成功'
                );
            }
        }else{
            return array(
                'code'=>-1,
                'message'=>'原密码错误'
            );
        }

    }

    /**
     * (non-PHPdoc)
     *
     */
    public function memberIsMobile($mobile, $mall_port=0, $wap_port=0)
    {
        //$wap_port  1-公众号 2-小程序 3-移动H5  4-PC  5-APP
        $condition = [
            'user_tel' => $mobile,
            'website_id' => $this->website_id,
            'is_member' => 1
        ];
        if($mall_port){
            $condition['mall_port'] = $mall_port;
        }
        $mobile_info = $this->user->getInfo($condition,'user_status,wx_openid,mp_open_id,app_wx_openid');
        if (!empty($mobile_info) && $mobile_info['user_status'] == 0){
            return USER_LOCK;
        }
        if(isset($mobile_info) && !empty($mobile_info) ){
            //只要绑定过该手机，就不能让其绑定其它账号了
            switch($wap_port){
                case 1://公众号
                    if( !empty($mobile_info['wx_openid']) ){//如果需要合并的账号微信open_id不为空，说明已经合并过了，不能再合并了
                        return true;
                    }
                    break;
                case 2://小程序
                    if( !empty($mobile_info['mp_open_id']) ){//如果需要合并的账号微信open_id不为空，说明已经合并过了，不能再合并了
                        return true;
                    }
                    break;
                case 3://h5
                    return true;
                    break;
                case 5:
                    if( !empty($mobile_info['app_wx_openid']) ){//如果需要合并的账号微信open_id不为空，说明已经合并过了，不能再合并了
                        return true;
                    }
                    break;//app
            }
        }else{
            return false;
        }
        return true;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberIsEmail()
     */
    public function memberIsEmail($email)
    {
        $email_info = $this->user->get([
            'user_email' => $email,
            'website_id' => $this->website_id
        ]);
        if (!empty($email_info) && $email_info['user_status'] == 0){
            return USER_LOCK;
        }
        return !empty($email_info);
    }

    /**
     * (non-PHPdoc)

     * @see \ata\api\IUser::checkUserIsSubscribe()
     */
    public function checkUserIsSubscribe($uid = 0)
    {
        $user_info = $this->user->getInfo(['uid' => $uid], 'wx_openid');
        if(!$user_info || !$user_info['wx_openid']){
            return false;
        }
        $weixin_fans = new WeixinFansModel();
        $count = $weixin_fans->getCount(['openid' => $user_info['wx_openid'], 'is_subscribe' => 1,'website_id'=>$this->website_id]);
        if (!$count) {
            return false;
        }
        return true;
    }

    /* (non-PHPdoc)
     * @see \ata\api\IUser::getUserCount()
     */
    public function getUserCount($condition)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $user_list = $user->getQuery($condition, "count(*) as count", '');
        return $user_list[0]["count"];
    }

    /**
     * 根据用户邮箱更改密码
     */
    public function updateUserPasswordByEmail($userInfo, $password, $mall_port = 0)
    {
        $data = array(
            'user_password' => md5($password)
        );
        $retval = $this->user->save($data, ['user_email' => $userInfo,'website_id'=>$this->website_id, 'mall_port' => $mall_port]);
        return $retval;
    }

    /**
     * 根据用户邮箱更改密码
     */
    public function updateUserPasswordByMobile($userInfo, $password, $mall_port=0)
    {
        $data = array(
            'user_password' => md5($password)
        );
        $retval = $this->user->save($data, ['user_tel' => $userInfo,'website_id' => $this->website_id, 'mall_port' => $mall_port]);
        return $retval;
    }
    /**
     * 修改密码
     */
    public function updatePassword($password,$condition)
    {
        $data = array(
            'user_password' => md5($password)
        );
        $retval = $this->user->save($data, $condition);
        $ucid = $this->user->getInfo($condition,'ucid')['ucid'];
        if($retval && $condition['port'] == 'platform'){
            $website = new WebSite();
            $result = $website->resetUcMemberPassword($ucid, $password, $condition['user_tel']);
            if(!$ucid){
                $this->user->isUpdate(true)->save(['ucid' => $result],$condition);
            }
        }
        return $retval;
    }
    /**
     * 会员等级自动升级
     * (non-PHPdoc)
     * @see \data\api\IUser::updateUserLevel()
     */
    public function updateUserLevel($user_id)
    {
        $member_model = new VslMemberModel();
        $count = $member_model->getCount(['uid' => $user_id]);
        if ($count == 0) {
            return;
        }
        #得到会员的信息
        $member_obj = $member_model->getInfo(['uid'=>$user_id],'growth_num,website_id');
        if (!empty($member_obj)) {
            $member_level_model = new VslMemberLevelModel();
            $condition['growth_num'] = ['<=', $member_obj['growth_num']];
            $condition['website_id'] = $member_obj['website_id'];
            $level_list = $member_level_model->getFirstData($condition,  "growth_num DESC");
            if ($level_list) {
                $member_model->save(["member_level" => $level_list['level_id']], ["uid" => $user_id]);
            }
        }
    }

    /**
     * 会员成长值增加
     * (non-PHPdoc)
     * @see \data\api\IUser::updateUserLevel()
     */
    public function updateUserGrowthNum($type, $uid, $num = 0,$order_id)
    {
        $member_model = new VslMemberModel();
        $member_account_record = new VslMemberAccountRecordsModel();
        $count = $member_model->getCount(['uid' => $uid]);
        if ($count == 0) {
            return;
        }
        $order = new Orders();
        $pay_money = $order->getOrderRealPayMoney($order_id);
        $recharge_order = new VslMemberRechargeModel();
        $recharge_money = $recharge_order->getInfo(['id'=>$order_id],'recharge_money')['recharge_money'];
        #得到会员的信息
        $member_obj = $member_model->getInfo(['uid' => $uid],'website_id,growth_num');
        if ($num == 0) {
            $webiste = new WebSiteModel();
            $webiste_info = $webiste->getInfo(['website_id' => $member_obj['website_id']], 'order_money,order_multiple,pay_num,recharge_money,recharge_num,complete_num');
            if ($type == 1 && $webiste_info['order_money']>0 && $pay_money>=$webiste_info['order_money']) {
                $multiple = $pay_money/$webiste_info['order_money'];
				$multiple = $webiste_info['order_multiple'] == 2 ? $multiple : 1;
                $num = $webiste_info['pay_num']*floor($multiple);
                $text = '订单支付成功会员成长值增加';
                $sign = '订单支付';
            }
            if ($type == 2 && $webiste_info['recharge_money']>0 && $recharge_money>=$webiste_info['recharge_money']) {
                $multiples = $recharge_money/$webiste_info['recharge_money'];
                $num = $webiste_info['recharge_num']*floor($multiples);
                $text = '订单充值成功会员成长值增加';
                $sign = '订单充值';
            }
            if ($type == 3 && $webiste_info['complete_num']) {
                $num = $webiste_info['complete_num'];
                $text = '订单完成会员成长值增加';
                $sign = '订单完成';
            }
        }
        if ($type == 4) {
            $text = '订单完成会员成长值增加';
        }
        if (!empty($member_obj)) {
            if($num>0){
                $growthNum = $member_obj['growth_num'] + $num;
                $member_model->save(['growth_num' => $growthNum], ['uid' => $uid]);
                $data = array(
                    'records_no' => getSerialNo(),
                    'account_type' => 4,
                    'uid' => $uid,
                    'sign' => $sign,
                    'number' => $num,
                    'from_type' => 1,
                    'data_id' => $order_id,
                    'text' => $text,
                    'create_time' => time(),
                    'website_id' => $member_obj['website_id']
                );
                $member_account_record->save($data);
            }
        }
    }
    /**
     * 会员自动打标签
     * (non-PHPdoc)
     * @see \data\api\IUser::updateUserLevel()
     */
    public function updateUserLabel($uid,$website_id)
    {
        $member_model = new VslMemberModel();
        $count = $member_model->getCount(['uid' => $uid]);
        if ($count == 0) {
            return;
        }
        $group = new VslMemberGroupModel();
        $group_ids = $group->Query(['website_id'=>$website_id,'is_label'=>1],'*');
        if($group_ids){
            $order = new VslOrderModel();
            $member_account = new VslMemberAccountModel();
            $order_goods = new VslOrderGoodsModel();
            $order_money = $order->getSum(['buyer_id'=>$uid,'order_status'=>4],'order_money');
            $order_pay = $order->getCount(['buyer_id'=>$uid,'order_status'=>[['>',0],['<',5]]]);
            foreach ($group_ids as $v) {
              $goods_info = [];
              $goods_id = $order_goods->Query(['goods_id' => $v['goods_id'], 'buyer_id' => $uid], 'order_id');
              if ($goods_id) {
                  $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4], 'order_id');
              }
              $account = $member_account->getInfo(['uid'=>$uid],'point,balance');
               $conditions = explode(',', $v['labelconditions']);
               $result = [];
                foreach ($conditions as $k1 => $v1) {
                        switch ($v1) {
                            case 1:
                                if ($order_money >= $v['order_money']) {
                                    $result[] = 1;//交易额
                                }
                                break;
                            case 2:
                                if ($order_pay >= $v['order_pay']) {
                                    $result[] = 2;//支付订单
                                }
                                break;
                            case 3:
                                if ($account['point']>$v['point']) {
                                    $result[] = 3;//积分
                                }
                                break;

                            case 4:
                                if ($account['balance']>$v['balance']) {
                                    $result[] = 4;//余额
                                }
                                break;
                            case 5:
                                if ($goods_info) {
                                    $result[] = 5;//指定商品
                                }
                                break;
                        }
                    }
                if ($v['label_condition'] == 1) {//满足所有勾选条件
                        if (count($result) == count($conditions)) {
                            $group_default = $member_model->getInfo(['uid'=>$uid],'group_id')['group_id'];
                            if($group_default){
                                $default_ids = explode(',',$group_default);
                                if(!in_array($v['group_id'],$default_ids)){
                                    $member = new VslMemberModel();
                                    $member->save(['group_id' => $group_default.$v['group_id'].','], ['uid' => $uid]);
                                }
                            }else{
                                $member = new VslMemberModel();
                                $member->save(['group_id' => $v['group_id'].','], ['uid' => $uid]);
                            }
                        }
                    }
                if ($v['label_condition'] == 2) {//满足勾选条件任意一个即可
                        if (count($result) >= 1) {
                            $group_default = $member_model->getInfo(['uid'=>$uid],'group_id')['group_id'];
                            if($group_default){
                                $default_ids = explode(',',$group_default);
                                if(!in_array($v['group_id'],$default_ids)){
                                    $member = new VslMemberModel();
                                    $member->save(['group_id' => $group_default.$v['group_id'].','], ['uid' => $uid]);
                                }
                            }else{
                                $member = new VslMemberModel();
                                $member->save(['group_id' => $v['group_id'].','], ['uid' => $uid]);
                            }
                        }
                    }
            }
            unset($v);
            unset($v1);
        }
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IUser::updateUser()
     */
    public function updateUser($uid, $website_id)
    {
        $user = new UserModel();
        $res = $user->save([
            'website_id' => $website_id
        ], [
            'uid' => $uid
        ]);
        return $res;

    }

    /**
     * 修改user表内容
     * @param array $data
     * @param string|array $condition
     * @param bool $only_update_one_row
     *
     * @return int
     */
    public function updateUserNew(array $data, $condition, $only_update_one_row = true)
    {
        //如果有mall_port，先查询用户，如果没有查到去掉mall_port查询，如果查到并且mall_port为空，则将mall_port更新到用户中
        if($condition['mall_port']){
            if($this->user->getCount($condition) == 0){//如果没有查到
                $mall_port = $condition['mall_port'];
                $condition['mall_port'] = 0;
                $user_info = $this->user->getInfo($condition,'uid, mall_port');
                if($user_info && empty($user_info['mall_port'])){//如果去掉mall_port查到了并且应用端口mall_port为空
                    $this->user->save(['mall_port'=>$mall_port], ['uid' => $user_info['uid']]);
                }
            }
        }
        //只允许修改修改一条记录
        if ($only_update_one_row && ($this->user->getCount($condition) != 1)) {
            return 0;
        }
        $member = new VslMemberModel();
        if($condition['user_name'] || $condition['real_name']){
            $data3 = array(
                "member_name" => $condition['user_name'],
                "real_name" => $condition['real_name'],
            );
            $member->save($data3, ['uid' => $condition['uid']]);
        }
        // 修改电话
        if ($data['user_tel']) {
            $member->save(['mobile' => $data['user_tel']], ['uid' => $condition['uid']]);
        }

        return $this->user->save($data, $condition);
    }

    /**
     * 检测是否绑定过
     * @$mobile string | 检测是否绑定的手机号
     * return bool
     */
    public function checkIsAssociate($mobile, $mall_port = 0)
    {
        $condition = ([
            'user_tel' => ['EQ', $mobile],
            'website_id' => $this->website_id,
            'mall_port' => $mall_port,
        ]);

        $user_info = $this->user->isMobileAssociate($condition);
        if (!empty($user_info)) {
            return  true;
        } else {
            return  false;
        }
    }

    /**
     * 用户状态
     * @param int $uid 用户id
     * @return int;
     */
    public function getUserStatus($uid = '')
    {
        $user_status = $this->user->getInfo(['uid' => $uid], $field = 'user_status');
        if ($user_status['user_status'] === 0) {
            return USER_LOCK;
        }
    }

    /**
     * 添加好物圈号
     */
    public function addUserInfoThingcircleId($tcid,$uid)
    {
        $res = $this->user->save(['thing_circle_uid' => $tcid], ['uid' => $uid]);
        return $res;
    }

    /**
     * 获取当前用户的权限列
     */
    public function getUserModuleIdArray($condition)
    {
        $user_model = new UserModel();
        $res = $user_model->getModuleIdArray($condition);
        return $res;
    }

    /**
     * 查询该用户属于的会员等级、分销商等级、会员标签
     * @param $uid
     * @return code| member_group会员标签 distributor_level分销商 user_level普通会员
     */
    public function getUserLevelAndGroupLevel($uid)
    {
        $user = new UserModel();
        $member = new VslMemberModel();
        $u_condition = [
            'uid' => $uid,
            'website_id' => $this->website_id
        ];
        $m_condition = [
            'uid' => $uid,
            'website_id' => $this->website_id,
        ];

        $userInfo = [];
        // member表
        $memberRes = $member->getInfo($m_condition, 'member_level,isdistributor,distributor_level_id,group_id, microshop_goods');
        // 查询会员标签
        if ($memberRes['group_id']) {
            // 会员标签是用','拼接，可以多个 eg： 3,2，
            $userInfo['member_group'] = rtrim($memberRes['group_id'], ',');
        }
        if ($memberRes['microshop_goods']) {
            // 会员标签是用','拼接，可以多个 eg： 3,2，
            $userInfo['microshop_goods'] = $memberRes['microshop_goods'];
        }
        // user表
        $userRes = $user->getInfo($u_condition, 'is_system, is_member,user_status');
        // 查询分销商等级
        if ($userRes['is_member'] == 1 && $memberRes ) {
            if ($memberRes['isdistributor'] == 2 ) {
                $userInfo['distributor_level'] =  $memberRes['distributor_level_id'];
                $userInfo['user_level'] =  $memberRes['member_level'];
            } else {// 查询会员等级
                $userInfo['user_level'] = $memberRes['member_level'];
            }
        }

        return $userInfo;

    }

    /**
     * 查询店铺联系方式（暂时默认获取的是第一个user数据的人电话）
     * @return mixed|string string [电话号码]
     */
    public function getShopFirstMobile()
    {
        $port = 'platform';
        if ($this->instance_id) {
            $port = 'admin';
        }
        $condition = [
            'website_id' => $this->website_id,
            'instance_id' => $this->instance_id,
            'port' => $port
        ];
        $userModel = new UserModel();
        $user = $userModel->getFirstData($condition, 'uid ASC', 'user_tel');
        if ($user){
            return $user['user_tel'];
        }
        return '';
    }

    /**
     * 查询用户信息
     * @param array  $condition
     * @param string $field
     * @param string $order
     * @return array
     */
    public function getUserData ($condition =[], $field='*', $order='', $offset=0, $limit=0)
    {
//        $data = $this->user->getQuery($condition, $field, $order);
        $user_obj = $this->user->where($condition)->field($field)->order($order);
        if(!empty($limit)){
            $user_obj->limit($offset, $limit);
        }
        $data = $user_obj->select();
        return objToArr($data);
    }

    /*
     * 获取platform或者admin的注册者电话
     */
    public function getWebsiteOrShopMainMobile ($website_id, $shop_id =0)
    {
        if ($shop_id && getAddons('shop', $website_id)) {
            $shopModel = new VslShopModel();
            $uid = $shopModel->getInfo(['shop_id' => $shop_id,'website_id' => $website_id],'uid')['uid'];
        }else{
            $websiteModel = new WebSiteModel();
            $uid = $websiteModel->getInfo(['website_id' => $website_id],'uid')['uid'];
        }
        $user_tel = '';
        if ($uid) {
            $user_tel =  $this->user->getInfo(['uid' => $uid], 'user_tel')['user_tel'];
        }
        return $user_tel;
    }

    /*
     * 获取admin的注册者电话列表
     */
    public function getShopMainMobileList ($website_id, array $shop_id)
    {
        if ($shop_id && getAddons('shop', $website_id)) {
            $shopModel = new VslShopModel();
            $userRes = $shopModel->getQuery(['shop_id' => ['in', $shop_id],'website_id' => $website_id],'uid');
        }
        if (!$userRes){return[];}
        $uid_list = array_column(objToArr($userRes), 'uid');
        $mobile_list = [];
        foreach ($uid_list as $uid){
            $user_tel =  $this->user->getInfo(['uid' => $uid], 'user_tel')['user_tel'];
            if ($user_tel){
                $mobile_list[] = $user_tel;
            }
        }
        return $mobile_list;
    }
    /*
     * 获取supplier的注册者电话列表
     */
    public function getSupplierMainMobileList ($website_id, $shop_id = '', $supplier_id, $field='*')
    {
        $supplier_id_arr = is_array($supplier_id) ?$supplier_id: [$supplier_id];

        $condition = [
            'website_id'    => $website_id,
            'supplier_id'   => ['in', $supplier_id_arr]
        ];
        if ($shop_id !== ''){
            $condition['instance_id'] = $shop_id;
        }
        return $this->user->getQuery($condition,$field);

    }

    /*
     * 订单通知手机（后台设置：优先使用该号码，不存在则使用商家或店铺注册默认手机号）
     */
    public function getWebsiteOrShopOrderMobile ($website_id, $shop_id =0)
    {
        # 订单通知手机
        $configSer = new Config();
        $mobile = $configSer->getWebsiteOrShopOrderMobile($website_id, $shop_id);
        if ($mobile) {
            return $mobile;
        }

        # 使用商家或店铺注册默认手机号
        return $this->getWebsiteOrShopMainMobile($website_id, $shop_id);
    }

    /*
     * 处理移动商家端登陆成功返回信息
     */
    public function dealLoginSuccess($port = '', $userInfo = '', $isChoose = 0){
        if(!$port || !$userInfo){
            return -2001;
        }
        $model = $this->getRequestModel();
        $data = [];
        $data['port'] = $port;
        $data['count'] = count($userInfo);
        if(count($userInfo) == 1 || $isChoose){
            $user_info = $isChoose ? $userInfo : $userInfo[0];
            if ($user_info['user_status'] == 0) {
                return USER_LOCK;
            } else {
                $this->initLoginInfo($user_info, $port);
                //登录成功后增加用户的登录次数
                $set_inc_condition['uid'] = $user_info['uid'];
                $this->user->save(['login_num'=>$user_info['login_num'] + 1,'user_token'=>md5($user_info['uid'])],$set_inc_condition);
            }
            $data['user_token'] = md5($user_info['uid']);
            $data['user_tel'] = $user_info['user_tel'];
            $data['website_id'] = Session::get($model . 'website_id');
            $data['mall_name'] = Session::get($model . 'mall_name');
            $data['logo'] = Session::get($model . 'logo');
            $data['group_name'] = Session::get($model . 'group_name');
            $data['group_is_system'] = Session::get($model . 'group_is_system');
            $data['supplier_id'] = Session::get('supplier_id');
            $data['instance_id'] = Session::get($model . 'instance_id');
            $data['shop_name'] = Session::get($model . 'instance_name');
            $data['supplier_name'] = Session::get($model.'supplier_name');
            return $data;
        }
        if(!is_array($userInfo)){
            return -2001;
        }
        Session::set($model . 'port', $port);
        $websiteModel = new WebSiteModel();
        if($port == 'platform'){
            return ABNORMAL_ACCOUNT;
        }elseif($port == 'admin'){
            $shopModel = new VslShopModel();
            foreach($userInfo as $val){
                $websiteInfo = $websiteModel->getInfo(['website_id' => $val['website_id']], 'title,create_time,mall_name');
                $shopInfo = $shopModel::get(['vsl_shop_model.shop_id' => $val['instance_id']], ['logo']);
                $shopList[]= [
                    'shop_name' => $shopInfo['shop_name'],
                    'uid' => $val['uid'],
                    'mall_name' => $websiteInfo['mall_name'],
                    'shop_state' => $shopInfo['shop_state'],
                    'shop_logo' => getApiSrc($shopInfo->logo->pic_cover),
                    ];
            }
            unset($val);
            Session::set($model . 'shop_or_sup_list', $shopList);
        }else{
            $supplierModel = new VslSupplierModel();
            foreach($userInfo as $val){
                $websiteInfo = $websiteModel->getInfo(['website_id' => $val['website_id']], 'title,create_time,mall_name');
                $supplierInfo = $supplierModel::get(['vsl_supplier_model.supplier_id' => $val['supplier_id']], ['logo']);
                $supplierList[]= [
                    'supplier_name' => $supplierInfo['supplier_name'],
                    'uid' => $val['uid'],
                    'mall_name' => $websiteInfo['mall_name'],
                    'status' => $supplierInfo['status'],
                    'supplier_logo' => getApiSrc($supplierInfo->logo->pic_cover),
                    ];
            }
            unset($val);
            Session::set($model . 'shop_or_sup_list', $supplierList);
        }
        return $data;
    }

    /**
     * 移动商家端选择店铺或供应商
     *
     * @param int $uid
     * @param string $port
     */
    public function choose($uid = 0, $port = '')
    {
        if(!$port || !$uid){
            return LACK_OF_PARAMETER;
        }
        if(!in_array($port, ['admin', 'supplier'])){
            return LACK_OF_PARAMETER;
        }
        $this->Logout(1);
	$condition = array();
        if ($port == 'admin') {
            $condition['uid'] = $uid;
            $condition['port'] = 'admin';
        } else {//供应商
            $condition['uid'] = $uid;
            $condition['supplier_id'] = ['>',0];
        }
        $user_info = $this->user->getInfo($condition, $field = 'uid,user_tel,user_status,user_name,user_headimg,is_system,instance_id,is_member,current_login_ip, current_login_time, current_login_type,website_id,supplier_id,port, login_num');
        if (!empty($user_info)) {
            return $this->dealLoginSuccess($port, $user_info, 1);
        } else {
            return USER_ERROR;
        }
    }

    /**
     * 更新用户信息
     */
    public function updateUserBaseInfo ($data,$condition)
    {
        return $this->user->save($data,$condition);
    }
}

