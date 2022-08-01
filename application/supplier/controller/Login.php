<?php

namespace app\supplier\controller;

\think\Loader::addNamespace('data', 'data/');

use addons\supplier\model\VslSupplierModel;
use data\service\AdminUser as AdminUser;
use data\service\Config as ConfigSer;
use data\service\WebSite as WebSite;
use think\Controller;
use think\Request;
use \think\Session as Session;
use data\model\UserModel;
use data\service\Member as Member;

class Login extends Controller {

    public $user;
    public $website_id;

    /**
     * 当前版本的路径
     *
     * @var string
     */
    public $style;
    // 验证码配置
    public $login_verify_code;

    public function __construct() {
        parent::__construct();
        $this->init();
    }

    private function init() {

        $this->user = new AdminUser();
        $web_site = new WebSite();
        $this->style = 'supplier/';
        $this->website_id = (int)request()->get('website_id', 0);
        $web_info = $web_site->getWebSiteInfo();
        $this->style = STYLE_DEFAULT_SUPPLIER . '/';
        $this->assign("style", STYLE_DEFAULT_SUPPLIER);
        $this->assign("title_name", $web_info['title']);
        $config_service = new ConfigSer();
        $logoConfig = $config_service->getLogoConfig();
        $this->assign('logo_config', $logoConfig);
        if($this->website_id){
            $this->assign("website_id", $this->website_id);
        }
        if( request()->isGet() && empty($this->website_id)){
            $this->error('无权限访问');
        }
        if (!empty($web_info['picture']['logo'])) {
            $this->assign("logo", $web_info['picture']['logo']);
        } else {
            $this->assign("logo", 'public/admin/images/shop_login_logo.png');
        }
    }

    public function index() {
        return view($this->style . 'Login/login');
    }

    public function admin_login() {
        return view($this->style . 'login/login');
    }
    public function versionLow() {

        return view($this->style . 'Login/versionLow');
    }
    public function loginMobile() {

        return view($this->style . 'Login/loginMobile');
    }

    /**
     * 用户登录
     *
     * @return number
     */
    public function login() {
        $user_name = request()->post('userName', '');
        $password = request()->post('password', '');
        $website_id = request()->post('website_id', '');
        if(empty($website_id)){
            return AjaxReturn(-3000);
        }
        if(!getAddons('supplier',$website_id)) {
            return AjaxReturn(-2);
        }
        $retval = $this->user->login($user_name, $password,0,$website_id);
//        if($retval != 1) {
//            //判断是否是普通供应商登录
//            $condition['account'] = $user_name;
//            $condition['password'] = md5($password);
//            $condition['website_id'] = $website_id;
//            $supplier_info = $supplier_mdl->getInfo($condition,'supplier_id,website_id,shop_id');
//            if($supplier_info) {
//                Session::set($model . 'instance_id', $supplier_info['shop_id']);
//                Session::set($model . 'website_id', $supplier_info['website_id']);
//                Session::set('supplier_id', $supplier_info['supplier_id']);
//                //供应商端口独立获取模块id
//                $no_control = $this->user->getNoControlAuth();
//                $module = new ModuleModel();
//                $supplier_module_id = $module->Query(['module' => 'supplier'],'module_id');
//                if($supplier_module_id) {
//                    $supplier_module_id = implode(',',$supplier_module_id);
//                    Session::set($model . 'module_id_array', $no_control . $supplier_module_id);
//                }
//                $retval = 1;
//            }else{
//                return AjaxReturn(-2001);
//            }
//        }
        if ($retval == 1) {
            //检查供应商状态
            $model = Request::instance()->module();
            $supplier_id = Session::get($model.'supplier_id');
            $supplier_mdl = new VslSupplierModel();
            $status = $supplier_mdl->Query(['supplier_id' => $supplier_id],'status')[0];
//            if($status == -1 || $status == 0 || $status == 2) {
//                return AjaxReturn(-3);
//            }
            $web_site = new WebSite();
            $web_info = $web_site->getWebSiteInfo();
            if (strtotime($web_info['shop_validity_time']) + 3600 * 7 * 24 <= time()  && $web_info['shop_validity_time'] > 0) {
                return AjaxReturn(-1001);
            }else{
                return AjaxReturn($retval);
            }
        }else{
            return AjaxReturn($retval);
        }
    }

    /**
     * 退出登录
     */
    public function logout() {
        $this->user->Logout();
        $redirect = __URL(__URL__ . '/' . SUPPLIER_MODULE . '/login?website_id=').$this->website_id;
        $this->redirect($redirect);
    }
    /*
     * 以下为找回密码页面
     */

    public function retrievePwd() {
        if (request()->isAjax()) {
            $member = new UserModel();
            // 获取数据库中的用户列表
            $tel = request()->get('username', '');
            $website_id = request()->get('website_id', '');
            $exist = 0;
            $user_list = $member->getInfo(['user_tel' => $tel, 'instance_id'=> -1,'website_id' => $website_id]);//供应商端的用户只能是shop_id=-1
            if ($user_list) {
                $exist = 1;
            }
            return $exist;
        }

        // 获取商城logo
        $website = new WebSite();
        $web_info = $website->getWebSiteInfo();
        $this->assign("web_info", $web_info);
        $this->assign("title_before", "密码找回");
        return view($this->style . "Login/retrievePwd");
    }
    /**
     * 短信验证
     */
    public function forgotValidation() {
        $send_type = request()->post("type", "");
        $send_param = request()->post("send_param", "");
        $website_id = request()->post("website_id");
        if ($send_type == 'sms') {
            $member = new UserModel();
            $user_list = $member->getInfo(['user_tel' => $send_param, 'instance_id'=> -1,'website_id' => $website_id]);//供应商端的用户只能是shop_id=-1
            if (!$user_list) {
                return $result = [
                    'code' => -1,
                    'message' => "该手机号未注册"
                ];
            }
            $params = array(
                "send_type" => $send_type,
                "mobile" => $send_param,
                "shop_id" => 0,/*供应商短信使用平台的发送*/
                "website_id" => $website_id
            );
            $result = runhook("Notify", "forgotPasswordBySms", $params);
            Session::set('forgotPasswordVerificationCodeA', $result['param'],300);
            if (empty($result)) {
                return $result = [
                    'code' => -1,
                    'message' => "发送失败"
                ];
            } else if ($result['code'] < 0) {
                return $result = [
                    'code' => $result['code'],
                    'message' => $result['message']
                ];
            } else {
                return $result = [
                    'code' => 1,
                    'message' => "发送成功"
                ];
            }
        }
    }
    public function check_find_password_code() {
        $send_param = request()->post('send_param', '');
        $param = Session::get('forgotPasswordVerificationCodeA');
        if ($send_param == $param && $send_param != '') {
            Session::delete('forgotPasswordVerificationCodeA');
            $retval = [
                'code' => 1,
                'message' => "验证码一致"
            ];
        } else {
            $retval = [
                'code' => -1,
                'message' => "验证码不一致"
            ];
        }
        return $retval;
    }
    /**
     * 修改密码
     */
    public function setNewPassword()
    {
        $userInfo = request()->post('userInfo', '');
        $password = request()->post('password', '');
        $website_id = request()->post('website_id', '');
        if(!$userInfo || !$password || !$website_id){
            return AjaxReturn(0);
        }
        $member = new Member();
        $condition = ['user_tel' => $userInfo, 'instance_id'=> -1,'website_id' => $website_id];//供应商端的用户只能是shop_id=-1
        $res = $member->updatePassword($password,$condition);
        return AjaxReturn($res);
    }

}
