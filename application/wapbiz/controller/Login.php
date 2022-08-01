<?php

namespace app\wapbiz\controller;

\think\Loader::addNamespace('data', 'data/');

use data\model\UserModel;
use data\service\AdminUser as AdminUser;
use think\Controller;
use \think\Session as Session;
use data\service\Member as Member;

class Login extends Controller
{

    public $user;

    /**
     * 当前版本的路径
     *
     * @var string
     */
    public $style;
    // 验证码配置
    public $login_verify_code;
    

    public function __construct()
    {
        parent::__construct();
        $this->init();
    }

    private function init()
    {
        $this->user = new AdminUser();
        if (!isApiLegal()) {
            $data['code'] = -2;
            $data['message'] = '接口签名错误';
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * 用户登录
     *
     * @return number
     */
    public function login()
    {
        $user_name = request()->post('username', '');
        $password = request()->post('codes', '');
        $port = request()->post('port', '');
        if(!$port || !$user_name || !$password){
            return json(AjaxReturn(-1006));
        }
        if(!in_array($port, ['admin', 'platform', 'supplier'])){
            return json(AjaxReturn(PARAMETER_ERROR));
        }
        $retval = $this->user->login($user_name, $password, 0,0,0, $port);
        if (is_array($retval)) {
            return json([
                'code' => 1,
                'message' => '登陆成功',
                'data' => $retval
            ]);
        }else{
            return json(AjaxReturn($retval));
        }
    }
    /**
     * 选择店铺或供应商
     *
     * @return number
     */
    public function choose()
    {
        $uid = (int)request()->post('uid', 0);
        $port = request()->post('port', '');
        if(!$port || !$uid){
            return json(AjaxReturn(-1006));
        }
        if(!in_array($port, ['admin', 'supplier'])){
            return json(AjaxReturn(PARAMETER_ERROR));
        }
        $sePort = $this->user->getSessionPort();//上一步存了port,可以通过判断是否是通过手动请求接口来选择
        if(!$sePort || $port != $sePort){
            return json(AjaxReturn(-1000));
        }
        $model = \think\Request::instance()->module();
        $shop_or_sup_list = Session::get($model . 'shop_or_sup_list');
        if(!$shop_or_sup_list){
            return json(AjaxReturn(-1000));
        }
        $retval = $this->user->choose($uid, $port);
        if (is_array($retval)) {
            return json([
                'code' => 1,
                'message' => '操作成功',
                'data' => $retval
            ]);
        }else{
            return json(AjaxReturn($retval));
        }
    }



    public function checkVerificationCode()
    {
        $verificationCode = request()->post('verification_code', '');
        $param = Session::get('forgotPasswordVerificationCodeBiz');
        if ($verificationCode == $param && $verificationCode != '') {
            Session::delete('forgotPasswordVerificationCodeBiz');
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
        return json($retval);
    }


    

    /**
     * 找回密码
     */
    public function setNewPasswordByMobile()
    {
        $mobile = request()->post('mobile', '');
        $password = request()->post('password', '');
        $port = request()->post('port', '');
        
        $verificationCode = request()->post('verification_code', '');
        if(!$mobile || !$password || !$port || !$verificationCode){
            return json(AjaxReturn(-1006));
        }
        if(!in_array($port, ['admin', 'platform', 'supplier'])){
            return json(AjaxReturn(PARAMETER_ERROR));
        }
        $param = Session::get('forgotPasswordVerificationCodeBiz');
        if ($verificationCode != $param) {
            return json(['code' => -1, 'message' => "验证码不一致"]);
        } 
        $codeMobile = Session::get("codeMobile");
        if ($mobile != $codeMobile) {
            return json(["code" => -1,"message" => "该手机号与验证手机不符"]);
        }
        $member = new Member();
        $condition = ['user_tel' => $mobile];
        if($port == 'platform' || $port == 'admin'){
            $condition['port'] = $port;
            $condition['is_system'] = 1;
        }
        if($port == 'supplier'){
            $condition['supplier_id'] = ['>',0];
        }
        $res = $member->updatePassword($password, $condition);
        Session::delete('forgotPasswordVerificationCodeBiz');
        Session::delete("codeMobile");
        
        return json(AjaxReturn($res));
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $this->user->Logout();
        return json(AjaxReturn(1));
    }
    /*
     * 获取店铺或供应商列表,从缓存取
     */
    public function getShopOrSupList(){
        $module = \think\Request::instance()->module();
        $port = request()->post('port', '');
        $sessionPort = Session::get($module . 'port');
        if($sessionPort && $port != $sessionPort){
            return json(['code' => -1, 'message' => '前台端口与缓存端口不一致，请重新登录']);
        }
        if($sessionPort && !in_array($sessionPort, ['admin', 'supplier'])){
            return json(['code' => -1, 'message' => '登录信息已过期，请重新登录']);
        }
        $data['port'] = $sessionPort ? : $port;
        $data['list'] = (array)Session::get($module . 'shop_or_sup_list');
        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }
    
    /**
     * 短信验证
     */
    public function forgotValidation() {
        $mobile = request()->post("mobile", "");
        $port = request()->post("port", "");
        if(!$mobile || !$port){
            return json(AjaxReturn(-1006));
        }
        if(!in_array($port, ['admin', 'platform', 'supplier'])){
            return json(AjaxReturn(PARAMETER_ERROR));
        }
        $condition = ['user_tel' => $mobile];
        if($port == 'platform' || $port == 'admin'){
            $condition['port'] = $port;
            $condition['is_system'] = 1;
        }
        if($port == 'supplier'){
            $condition['supplier_id'] = ['>',0];
        }
        $member = new UserModel();
        $user_list = $member->getInfo($condition);
        if (!$user_list) {
            return json(AjaxReturn(USER_NO_FOUND));
        }
        $params = array(
            "mobile" => $mobile,
            "shop_id" => 0,
            "website_id" => 0
        );
        $result = runhook("Notify", "merchantForgotPasswordBySms", $params);
        Session::set('forgotPasswordVerificationCodeBiz', $result['param'], 300);
        Session::set('codeMobile', $mobile, 300);
        if (empty($result)) {
            return json(['code' => -1, 'message' => "发送失败"]);
        } else if ($result['code'] < 0) {
            return json(['code' => $result['code'], 'message' => $result['message']]);
        } else {
            return json(['code' => 1, 'message' => "发送成功"]);
        }
    }

}
