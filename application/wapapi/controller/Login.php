<?php

namespace app\wapapi\controller;

use addons\coupontype\model\VslCouponModel;
use addons\giftvoucher\model\VslGiftVoucherRecordsModel;
use addons\registermarketing\server\RegisterMarketing;
use addons\taskcenter\model\VslPosterRecordModel;
use data\extend\ThinkOauth as ThinkOauth;
use data\extend\WchatOauth;
use data\extend\WchatOpen;
use data\model\UserModel;
use data\model\UserTaskModel;
use data\model\VslMemberAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberModel;
use data\model\WebSiteModel;
use data\service\Config as WebConfig;
use data\service\Member as Member;
use data\service\User;
use data\service\WebSite as WebSite;
use think\Controller;
use think\Cookie;
use think\Db;
use think\Request;
use think\Session as Session;

\think\Loader::addNamespace('data', 'data/');

/**
 * 前台用户登录
 *
 * @author  www.vslai.com
 *
 */
class Login extends Controller
{

    public $user;

    public $web_site;

    public $style;

    protected $instance_id;

    protected $uid;

    protected $model;

    protected $website_id;

    protected $shop_name;

    protected $config;

    protected $user_model;

    // 验证码配置
    public $login_verify_code;
    # 登录失败次数
    protected $login_fail_times = 0;

    public function __construct()
    {
        parent::__construct();
        $this->user = new Member();
        $this->user_model = new UserModel();
        $this->model = $this->user->getRequestModel();
        
        $action = request()->action();
        $website_id = checkUrl();
        if ($website_id && is_numeric($website_id)) {
            Session::set($this->model . 'website_id', intval($website_id));
            $this->website_id = $website_id;
        } elseif (Session::get($this->model . 'website_id')) {
            $this->website_id = Session::get($this->model . 'website_id');
        } elseif (Session::get('shopwebsite_id')) {
            // 获取shop那边的website_id
            $this->website_id = Session::get('shopwebsite_id');
        } elseif (!in_array($action, ['oauthlogin', 'callback', 'oauthlogin_new'])) {
            echo json_encode(AjaxReturn(LOGIN_LACK_OF_PARAMETER), JSON_UNESCAPED_UNICODE);
            exit;
        }
        $this->web_site = new WebSite();
        $web_info = $this->web_site->getWebSiteInfo($this->website_id);
        if ($web_info['wap_status'] == 0 && !in_array($action, ['oauthlogin', 'callback']) && !Request()->isAndroid() && !Request()->isIOS()) {
            //app 不在此控制
//            echo json_encode(AjaxReturn(MALL_WAP_CLOSE));
            echo json_encode(['code' => MALL_WAP_CLOSE, 'message' => $web_info['close_reason'] ?: '商城移动端已关闭'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!isApiLegal() && !in_array($action, ['oauthlogin', 'callback', 'oauthlogin_new'])) {
            // 这些方法是第三方登陆不需要验证
            $data['code'] = -2;
            $data['message'] = '接口签名错误';
            if (request()->get('app_test')) {
                $app_key = $api_key = API_KEY;
                foreach (request()->post() as $key => $value) {
                    $api_key .= $key;
                }

                // app sign
                $module = strtolower(Request::instance()->module());
                $controller = strtolower(Request::instance()->controller());
                $action = strtolower(Request::instance()->action());
                if ($controller . $action == 'addonsexecute') {
                    $params = Request::instance()->param();
                    $module = strtolower($params['addons']);
                    $controller = strtolower($params['controller']);
                    $action = strtolower($params['action']);
                }
                $module_key = $app_key . $module;
                $controller_key = $app_key . $controller;
                $action_key = $app_key . $action;

                $data['php_sign'] = md5($api_key);
                $data['app_sign'] = md5($module_key);
                $data['api_key'] = $api_key;
                $data['module_key'] = $module_key;
                $data['controller_key'] = $controller_key;
                $data['action_key'] = $action_key;
                $data['app_post_sign'] = $_SERVER['HTTP_SIGN'];
            }
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $this->init();
    }

    public function init()
    {
        $this->web_site = new WebSite();
        
        $this->shop_name = $this->user->getInstanceName();
        $this->instance_id = 0;
        $this->uid = getUserId();
        //设置
//        $this->config = Session::get('website_config');
//        if (empty($this->config)) {
            $config_array = ['LOGINVERIFYCODE','EMAILMESSAGE', 'MOBILEMESSAGE'];
            $configSer = new WebConfig();
            $config_list = $configSer->getConfigBatch($this->instance_id, $config_array, $this->website_id);
            foreach ($config_list as $k => $v) {
                switch ($v['key']) {
                    case 'LOGINVERIFYCODE':
                        $info = json_decode($v['value'], true);
                        $this->config['captcha_code_type'] = $info['pc'] ? true : false;
                        break;
                    case 'EMAILMESSAGE':
                        if ($v['is_use'] == 0) {
                            $this->config['email_verification'] = false;
                            break;
                        }
                        $this->config['email_verification'] = true;
                        break;
                    case 'MOBILEMESSAGE':
                        if ($v['is_use'] == 0) {
                            $this->config['mobile_verification'] = false;
                            break;
                        }
                        $this->config['mobile_verification'] = true;
                        break;
                }
            }
            Session::set('website_config', $this->config);
//        }
    }

    public function captchaSrc()
    {
        $img = captcha_src();
        if ($img) {
            return json(['code' => 1, 'message' => '获取成功', 'data' => ['captcha_src' => __URL(__URL__ . $img)]]);
        } else {
            return json(['code' => -1, 'message' => '获取失败']);
        }
    }

    /**
     * 检测是否存在unionid or openid
     */
    public function wchatLogin()
    {

        $domain_name = Request::instance()->domain();
        if (!empty($_COOKIE[$domain_name . "member_access_token"])) {
            $token = json_decode($_COOKIE[$domain_name . "member_access_token"], true);
        } else {
            $wchat_oauth = new WchatOauth($this->website_id);
            $token = $wchat_oauth->get_member_access_token();
            if (!empty($token['access_token'])) {
                Cookie::set($domain_name . "member_access_token",json_encode($token));//access_token
            } else {
                // return get code url
                return ['code' => -9999, 'url' => $token];//code
            }
        }
        return ['code' => 1, 'token' => $token];//code

    }

    public function index()
    {

        //$bind_message_info = json_decode(Session::get("bind_message_info"), true);
        $password = request()->post('password', '');
        $mobile = request()->post('account', '');
        $verification_code = request()->post('verification_code');// 短信验证码
        $captcha_code = request()->post('captcha_code', '');// 图片验证码
        //登录的时候，判断是否是第三种账号体系
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id' => $this->website_id], 'account_type');
        if($website_info['account_type'] == 3){
            $mall_port = request()->post('mall_port', 0);
        }
        $login_fail_times = Session::get('login_fail_times') ?: 0;
        if (empty($mobile) || (empty($verification_code) && empty($password))) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        $send_mobile_verification_times = Session::get('send_mobile_verification_times') ?: 0;
        if ($captcha_code && !captcha_check($captcha_code) && ($send_mobile_verification_times >= 3 || $login_fail_times >= 3 ) ) {
            Session::set('login_fail_times', ++$login_fail_times);
            return json(['code' => -1, 'message' => '图片验证码错误']);
        }

        $sendMobile = Session::get('sendMobile');
        # 判断短信 + 验证码登录 + 手机判断
        if ($verification_code && !empty($sendMobile) && $mobile != $sendMobile) {
            Session::set('login_fail_times', ++$login_fail_times);
            return json(['code' => -1, 'message' => '手机已更改请重新获取验证码']);
        }

        # 判断短信 + 验证码登录 + 验证码
        if ($verification_code && $verification_code != Session::get('mobileVerificationCode')) {
            Session::set('login_fail_times', ++$login_fail_times);
            return json(['code' => -1, 'message' => '手机验证码错误']);
        }

        //因为去查数据库调用Login()时，会先Logout()一次，清除掉session
        $this->login_fail_times = Session::get('login_fail_times');
        if ($password) {
            $result = $this->user->login($mobile, $password, 1, '', $mall_port);//0-无来源 1-微信公众号 2-PC 3-移动h5 4-小程序 5-app
        } else {
            $result = $this->user->login($mobile, '', 1, '', $mall_port);
        }

        if (is_array($result)) {
            Session::delete(['send_mobile_verification_times', 'login_fail_times', 'sendMobile', 'mobileVerificationCode']);
            return json([
                'code' => 1,
                'message' => '登陆成功',
                'data' => [
                    'have_mobile' => !empty($result['user_tel']) ?: false,
                    'user_token' => md5($result['uid']),
                    'user_name' => $result['user_name'],
                    'user_headimg' => getApiSrc($result['user_headimg']),
                ]
            ]);
        } else {
            $login_fail_times = $this->login_fail_times;
            Session::set('login_fail_times', ++$login_fail_times);
            if ($login_fail_times >= 3 && $this->config['captcha_code_type']) {

                return json(['code' => 0, 'message' => '账号或者密码错误!']);
            } else {
                return json(AjaxReturn($result));
            }
        }

    }

    public function logout()
    {
        $this->user->logout();
        return json(['code' => 1, 'message' => '已退出登陆']);
    }

    public function checkVerificationCode()
    {
        $mobile = request()->post('mobile', '');
        $verification_code = request()->post('verification_code');

        if (empty($mobile) || empty($verification_code)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        if ($verification_code != Session::get('mobileVerificationCode') || $mobile != Session::get('sendMobile')) {
            return json(['code' => -1, 'message' => '手机验证码错误']);
        } else {
            Session::delete(['send_mobile_verification_times', 'mobileVerificationCode']);
            return json(['code' => 1, 'message' => '手机验证码正确']);
        }
    }

    public function checkEmailVerificationCode()
    {
        $email = request()->post('email');
        $verification_code = request()->post('verification_code');

        if (empty($email) || empty($verification_code)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        if ($verification_code != Session::get('emailVerificationCode') || $email != Session::get('sendEmail')) {
            return json(['code' => -1, 'message' => '邮箱验证码错误']);
        } else {
            Session::delete(['emailVerificationCode']);
            return json(['code' => 1, 'message' => '邮箱验证码正确']);
        }
    }

    /**
     * wap第三方登录
     */
    public function oauthLogin()
    {
        $member = new Member();
        $wchat_oauth = new WchatOauth($this->website_id);
        $type                   = request()->param('type')?:Session::get('oauth_login_type');
        $post                   = request()->post();
        $extend_code            = $post['extend_code'];
        // miniProgram
        $code                   = $post['code'];
        $encryptedData          = $post['encrypted_data'];
        $iv                     = $post['iv'];
        
        // qq
        $qq_redirect_url        =  $post['redirect_url'];
        if ($qq_redirect_url) {
            Session::set('temp_qq_redirect_url', '/wap'.$qq_redirect_url);
        }
        if ($extend_code) {
            Session::set('extend_code', $extend_code);
        }
        //判断账号体系
        $website = new WebSiteModel();
        $account_type = $website->getInfo(['website_id' => $this->website_id], 'account_type')['account_type'];

        Session::set('oauth_login_type', $type);
        if ($type == 'WCHAT') {
            if (isWeixin()) {
            //  微信环境 微信登陆流程
            //  1.url回调前端
            //  2.前端get带上1步拿到的code
            //  3.授权完成获取access_token
            //  4.执行相应入库操作，并返回USER_TOKEN，后面访问都带上USER_TOKEN,先用USER_TOKEN登录
                $result = $this->wchatLogin();
                if ($result['code'] == -9999) {
                    return json(['code' => 4, 'message' => '配置有效', 'data' => ['url' => $result['url']]]);
                }
                /****************** new code start ****************************/
                if ($result['code'] == 1) {
                    $domain_name = Request::instance()->domain();
                    $token = $result['token'] ?: json_decode($_COOKIE[$domain_name . "member_access_token"], true);

                    # 准备基本信息
                    $wechat_user_info = $wchat_oauth->get_oauth_member_info($token);//获取用户信息
                    $wx_unionid = !empty($token['unionid']) ? $token['unionid'] : $wechat_user_info['unionid'];
                    $wx_openid  = $token['openid'] ?: '';   // 新用户授权进来开始没有unionid
                    # 存储session
                    $oa_data['type']        = 1;//wechat
                    $oa_data['unionid']     = $wx_unionid;
                    $oa_data['openid']      = $wx_openid;
                    unset($result);
                    $result = ['code' => USER_NBUND];
                    if (!empty($wx_unionid)) { // unionid
                         // (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
                        $result = $this->user->wchatLoginNew(['wx_unionid' => $wx_unionid], 1);//联合登录判断
                         // 防止第一次没拿到unionid，然后生成记录，之后进来有unionid
                        if ($result['code'] == USER_NBUND) {
                            $result = $this->user->wchatLoginNew(['wx_openid' => $wx_openid], 1);
                        }
                    } elseif (!empty($wx_openid)) { // openid
                         $result = $this->user->wchatLoginNew(['wx_openid' => $wx_openid], 1);
                     }
                    /*** 根据上面$result 进行相应处理 ***/
                    if ($result['code'] == USER_LOCK) {
                        return json(['code' => 3, 'message' => '用户被锁定']);
                    }
                    if ($result['code'] == USER_NBUND) {
                        if($account_type == 3){
                            $mall_port = 1;
                        }else{
                            $mall_port = 0;
                        }
                        // 绑定新用户
                        $extend_code2 = request()->get('extend_code');
                        $extend_code = empty($extend_code) ? $extend_code2 : $extend_code;
                        $uid = $member->registerMember($extend_code, '', '', '', '', '', '', $wx_openid, '', $wx_unionid,'','','','', $mall_port,'',$wechat_user_info['nickname']);// (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
                        if ($uid > 1) {
                            $oa_data['nickname']            = $wechat_user_info['nickname'];
                            $oa_data['headimgurl']          = $wechat_user_info['headimgurl'];
                            $oa_data['sex']                 = $wechat_user_info['sex'];
                            $this->user->updateUserNew([
                                'user_headimg'      => $oa_data['headimgurl'],
                                'nick_name'         => $oa_data['nickname'],
                                'sex'               => $oa_data['sex'],
                                'user_token'        => md5($uid)
                            ], ['uid' => $uid]);
                            Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                            // 用户信息初始化
                            $this->user->wchatLoginNew(['uid' => $uid]);
                            $data = [
                                'user_token'        => md5($uid),
                                'have_mobile'       => false
                            ];
                            return AjaxReturn(SUCCESS, $data, '登录成功！');
                        } else {
                            return AjaxReturn(FAIL,[], '登录失败！');
                        }
                    }
                    if ($result['code'] == 1) {
                        //  如果用户已存在数据,则不覆盖
                        $user_info                  = $result['user_info'];
                        // 默认原来
                        $oa_data['nickname']        = $user_info['nick_name'];
                        $oa_data['headimgurl']      = $user_info['user_headimg'];
                        $oa_data['sex']             = $user_info['sex'];
                        // 去覆盖
                        if (empty($user_info['nick_name'])) {
                            $oa_data['nickname']    = $wechat_user_info['nickname'];
                            $oa_data['sex']         = $wechat_user_info['sex'];
                        }
                        if (empty($user_info['user_headimg'])) {
                            $oa_data['headimgurl']  = $wechat_user_info['headimgurl'];
                            $oa_data['sex']         = $wechat_user_info['sex'];
                        }
                        $this->user->updateUserNew([
                            'user_headimg'          => $oa_data['headimgurl'],
                            'nick_name'             => $oa_data['nickname'],
                            'sex'                   => $oa_data['sex'],
                            'user_token'            => md5($user_info['uid']),
                            'wx_openid'             => $user_info['wx_openid'] ?: $wx_openid,
                            'wx_unionid'            => $user_info['wx_unionid'] ?: $wx_unionid
                        ], ['uid' => $user_info['uid']]);
                        Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                        $data = [
                            'user_token'    => md5($user_info['uid']),
                            'have_mobile'   => !empty($user_info['user_tel']) ? true : false
                        ];
    
                        return AjaxReturn(SUCCESS, $data, '登录成功！');
                    }
                }
                /****************** new code /end ****************************/
            }
        } else if ($type == 'QQLOGIN') {
            $config = new WebConfig();
            $config_info = $config->getConfig(0, 'QQLOGIN', $this->website_id);
            if (empty($config_info['value']['APP_KEY']) || empty($config_info['value']['APP_SECRET'])) {
                return AjaxReturn(FAIL, [], '当前系统未设置QQ第三方登录!' );
            }
        } else if ($type == 'MP') {
            // 1.code去换回session_key、openid、unionid
            // 2.用户授权，前端传昵称、头像过来
            $token = Session::get('mp_token');
            $mp_session_key     = $token['session_key'] ?: '';
            $wx_unionid         = $token['unionid'] ?: '';
            $mp_open_id         = $token['openid'] ?: '';
            if ($code) {
                # 获取token
                $wchat_open = new WchatOpen($this->website_id);
                $codeRes = $wchat_open->code_to_session($code);
                $tokenRes = objToArr($codeRes);
                if ($tokenRes['errcode'] ) {
                    return AjaxWXReturn($tokenRes['errcode'],[], $tokenRes['errmsg']);
                }
                $token['session_key'] = $tokenRes['session_key'] ?:$mp_session_key;
                $token['unionid'] = $wx_unionid ?: $tokenRes['unionid'];
                $token['openid'] = $mp_open_id ?: $tokenRes['openid'];
                //https://developers.weixin.qq.com/community/develop/doc/0004c0b39d822862c9b66f32c51800
                Session::set('mp_token', $tokenRes, 2.9*24*3600);
                # 解密
                if ($iv && $encryptedData) {
                    # 解密信息
                    if(!$token['session_key']) { /*TODO... 前端重新检测session_key*/
                        return json(['code' => 5, 'message' => 'session_key已过期']);
                    }
                    $mp_result = $wchat_open->getMpUnionId($token['session_key'], $encryptedData, $iv);
                    if ($mp_result['code'] < 0) {
                        return $mp_result;
                    }
                    $wechat_user_info = json_decode($mp_result['data'], true);
                    /********** MP new start  **************/
                    $nick_name              = $wechat_user_info['nickName'];
                    $head_img               = $wechat_user_info['avatarUrl'];
                    $sex                    = $wechat_user_info['gender'] == 1 ?: 2;
                }
                $wx_unionid         = $wechat_user_info['unionId'] ?: $token['unionid'];
                $mp_open_id         = $token['openid'];

            } elseif(!$mp_session_key) {
                return json(['code' => 5, 'message' => 'session_key已过期']);
            }
            /********** MP new start  **************/
            # 准备数据
            $oa_data['type']            = 3;//mini program
            $oa_data['data']            = [];
            $oa_data['openid']          = $mp_open_id;
            $oa_data['unionid']         = $wx_unionid;
            $oa_data['nickname']        = $nick_name ?: '';
            $oa_data['headimgurl']      = $head_img ?: '';
            $oa_data['sex']             = $sex ?: '';
            
            unset($result);
            if (!empty($wx_unionid)) {
                // unionid登录
                $result = $this->user->wchatLoginNew(['wx_unionid' => $wx_unionid], 2);//联合登录 (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
                // 防止第一次没拿到unionid，然后生成记录，之后进来有unionid
                if ($result['code'] == USER_NBUND) {
                    $result = $this->user->wchatLoginNew(['mp_open_id' => $mp_open_id], 2);
                }
            } elseif (!empty($mp_open_id)) {
                // openid登录
                $result = $this->user->wchatLoginNew(['mp_open_id' => $mp_open_id], 2);
            } else {
                debugFile($oa_data,'3、（mp）授权登录unionid,mp_open_id '.$this->website_id.' ' ,'public/ErrorLog/mp/login_err.txt');
            }
            /*** 根据上面$result 进行相应处理 ***/
            if ($result['code'] == USER_LOCK) {
                return AjaxReturn(3, [], '用户被锁定');
            }
            if ($result['code'] == USER_NBUND) {
                if($account_type == 3){
                    $mall_port = 2;
                }else{
                    $mall_port = 0;
                }
                // 绑定新用户
                $uid = $member->registerMember($extend_code, '', '', '', '', '', '', '', '', $wx_unionid, $mp_open_id,
                    '', '', '', $mall_port,'',$oa_data['nickname'],$oa_data['headimgurl'],$oa_data['sex']);
                if ($uid > 1) {
                    $oa_data['type']            = 3;//mini program
                    $oa_data['unionid']         = $wx_unionid;
                    $oa_data['nickname']        = $nick_name ?: '';
                    $oa_data['headimgurl']      = $head_img ?: '';
                    $oa_data['sex']             = $sex ?: '';

                    // 这里修改用户头像和昵称
                    // 用户信息初始化
                    $this->user->wchatLoginNew(['uid' => $uid]);
                    Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                    $data = [
                        'user_token'    => md5($uid),
                        'have_mobile'   => false
                    ];

                    return AjaxReturn(SUCCESS, $data, '登录成功！');
                } else {
                    return AjaxReturn(FAIL, [], '登录失败！');
                }
            }
            if ($result['code'] == 1) {
                //  如果用户已存在数据,则不覆盖
                $user_info                  = $result['user_info'];
                // 默认原来
                $oa_data['nickname']        = $user_info['nick_name'];
                $oa_data['headimgurl']      = $user_info['user_headimg'];
                $oa_data['sex']             = $user_info['sex'];
                // 去覆盖
                if (empty($user_info['nick_name'])) {
                    $oa_data['nickname']    = $nick_name;
                }
                if (empty($user_info['user_headimg'])) {
                    $oa_data['headimgurl']  = $head_img;
                }
                if (empty($user_info['sex'])) {
                    $oa_data['sex']         = $sex;
                }
                $this->user->updateUserNew([
                    'nick_name'             => $oa_data['nickname'],
                    'user_headimg'          => $oa_data['headimgurl'],
                    'sex'                   => $oa_data['sex'],
                    'mp_open_id'            => $user_info['mp_open_id'] ?: $mp_open_id,
                    'wx_unionid'            => $user_info['wx_unionid'] ?: $wx_unionid
                ],
                    [ 'uid' => $user_info['uid']]
                );

                Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                $data = [
                    'user_token'            => md5($user_info['uid']),
                    'have_mobile'           => $user_info['user_tel'] ? true: false
                ];
                return AjaxReturn(SUCCESS, $data, '登录成功！');
            }
            /********** /MP new  end **************/
        } else {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $_SESSION['login_type'] = $type;
        $test = ThinkOauth::getInstance($type, null, $this->website_id);
        return AjaxReturn(4, ['url' => $test->getRequestCodeURL($this->website_id)], '配置有效!');
    }

    /**
     * app第三方登录
     */
    public function oauthAppLogin()
    {
        $member = new Member();
        $unionid = request()->post('unionid');
        $app_wx_openid = request()->post('wx_openid');
        $type = request()->post('type');
        $nick_name = request()->post('name', '');
        $sex = request()->post('gender', 0);
        if($sex){
            $sex = ($sex == '男') ? 1 : 2;
        }
        $user_headimg = request()->post('iconurl', '');
        $extend_code = request()->post('extend_code')? (int)request()->post('extend_code'): Session::get('extend_code');
        if ($extend_code) {
            Session::set('extend_code', $extend_code);
        }
        if (empty($unionid)) {
            return json(['code' => -1, 'message' => 'unionid不能为空']);
        }
        if (!in_array($sex, [1, 2])) {
            $sex = 0;
        }
        $member_service = new Member();
        $uid = $this->uid;
        $website = new WebSiteModel();
        $account_type = $website->getInfo(['website_id' => $this->website_id], 'account_type')['account_type'];
        if ($type == 'QQLOGIN') {
            if ($uid) {
                // 已登陆状态关联QQ
                $result = $member_service->updateUserNew(
                    [
                        'qq_openid' => $uid,
                        'nick_name' => $nick_name,
                        'user_headimg' => $user_headimg,
                        'sex' => $sex,
                    ], ['uid' => $uid]);
                if ($result) {
                    return json(['code' => 1, 'message' => '关联成功']);
                } else {
                    return json(['code' => -1, 'message' => '关联失败']);
                }
            }
            $user_info = $member_service->getUserInfoNew(['website_id' => $this->website_id, 'qq_openid' => $uid]);
            if (empty($user_info) || empty($user_info['user_tel'])) {
                // 去绑定手机
                $oa_data['type'] = 2;//qq
                $oa_data['data'] = [];
                $oa_data['unionid'] = $uid;
                $oa_data['nickname'] = $nick_name;
                $oa_data['headimgurl'] = $user_headimg;
                $oa_data['sex'] = $sex;
                Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                return ['code' => 2, 'message' => '请绑定手机'];
            }
            if ($user_info['user_status'] != 1) {
                return ['code' => 3, 'message' => '用户不可用'];
            }
            $user_token = md5($user_info['uid']);
            $member_service->updateUserNew(
                [
                    'login_num' => $user_info['login_num'] + 1,
                    'user_token' => $user_token,
                    'nick_name' => $nick_name,
                    'user_headimg' => $user_headimg,
                    'sex' => $sex,
                ], ['uid' => $user_info['uid']]);
            $member_service->initLoginInfo($user_info);
            return json(['code' => 1, 'message' => '登录成功', 'data' => ['user_token' => $user_token]]);

        } elseif ($type == 'WCHAT') {
            //app一定会存在unionid
            if (!empty($unionid)) {
                // unionid登录
                $result = $this->user->wchatLoginNew(['wx_unionid' => $unionid], 5);//联合登录 //0-无来源 1-微信公众号 2-PC 3-移动h5 4-小程序 5-app
                if ($result['code'] == USER_NBUND) {
                    $result = $this->user->wchatLoginNew(['wx_openid' => $app_wx_openid], 5);//联合登录 //0-无来源 1-微信公众号 2-PC 3-移动h5 4-小程序 5-app
                }
            }
            /*** 根据上面$result 进行相应处理 ***/
            if ($result['code'] == USER_LOCK) {
                return json(['code' => 3, 'message' => '用户被锁定']);
            }
            if ($result['code'] == USER_NBUND) {
                if($account_type == 3){
                    $mall_port = 5;
                }else{
                    $mall_port = 0;
                }
                // 绑定新用户
                $uid = $member->registerMember($extend_code, '', '', '', '', '', '', '', '', $unionid, '', '', $app_wx_openid,0, $mall_port,'',$nick_name);//联合登录 //0-无来源 1-微信公众号 2-PC 3-移动h5 4-小程序 5-app
                if ($uid > 1) {
                    $oa_data['type'] = 4;//app
                    $oa_data['unionid'] = $unionid;
                    $oa_data['nickname'] = $nick_name;
                    $oa_data['headimgurl'] = $user_headimg;
                    $oa_data['openid'] = $app_wx_openid;
                    $oa_data['sex'] = $sex;
                    $oa_data['openid'] = $app_wx_openid ?:'';

                    // 这里修改用户头像和昵称
                    $this->user->updateUserNew([
                        'nick_name' => $oa_data['nickname'],
                        'user_headimg' => $oa_data['headimgurl'],
                        'sex' => $oa_data['sex'],
                        'user_token' => md5($uid)
                    ], ['uid' => $uid]);
                    // 用户信息初始化
                    $this->user->wchatLoginNew(['uid' => $uid]);
                    Session::set('oauthWq', $oa_data,OAUTH_LOGIN_SESSION_TIME);
                    $data = [
                        'user_token' => md5($uid),
			'have_mobile' => false
                    ];

                    return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
                } else {
                    return json(['code' => -1, 'message' => '登录失败！']);
                }
            }
            if ($result['code'] == 1) {
                //  如果用户已存在数据,则不覆盖
                $user_info  = $result['user_info'];
                // 默认原来
                $oa_data['type'] = 4;
                $oa_data['nickname'] = $user_info['nick_name'];
                $oa_data['headimgurl'] = $user_info['user_headimg'];
                $oa_data['sex'] = $user_info['sex'];
                $oa_data['openid'] = $app_wx_openid ?:'';
                // 去覆盖
                if (empty($user_info['nick_name'])) {
                    $oa_data['nickname'] = $nick_name;
                    $oa_data['sex'] = $sex;
                }
                if (empty($user_info['user_headimg'])) {
                    $oa_data['headimgurl'] = $user_headimg;
                    $oa_data['sex'] = $sex;
                }
                $this->user->updateUserNew([
                    'nick_name' => $oa_data['nickname'],
                    'user_headimg' => $oa_data['headimgurl'],
                    'sex' => $oa_data['sex'],
                    'app_wx_openid' => $user_info['app_wx_openid'] ?: $app_wx_openid,
                    'wx_unionid' => $user_info['wx_unionid'] ?: $unionid
                ], ['uid' => $user_info['uid']]);

                Session::set('oauthWq', $oa_data,OAUTH_LOGIN_SESSION_TIME);
                $data = [
                    'user_token' => md5($user_info['uid']),
                    'app_wx_openid' => $app_wx_openid,
                    'have_mobile' => $user_info['user_tel'] ? true: false
                ];

                return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
            }
        }

        return json(['code' => -1, 'message' => '不支持其他登录方式']);
    }

    /**
     * qq,wechat登录回调
     */
    public function callback()
    {
        $code = request()->get('code', '');
        $redirect_url = Session::get('temp_qq_redirect_url') ?: '';// /wap/member/centre
        if (!$redirect_url) {
            $redirect_url = '/wap/author';
        }
        Session::delete('temp_qq_redirect_url');
        if (empty($code)) {
            if (request()->isMobile()) {
                $this->redirect('/wap/login');
            }
            die();
        }
        if ($_SESSION['login_type'] == 'QQLOGIN') {
            $qq = ThinkOauth::getInstance('QQLOGIN', null, $this->website_id);
            $token = $qq->getAccessToken($code, $this->website_id);
            if (!empty($token['openid'])) {
                $uid = $this->uid;
                if ($uid) {
                    // 绑定账号时已登录，直接更新unionid和openid
                    $this->user->updateUserNew(['qq_openid' => $token['openid']], ['uid' => $uid]);
                    $user_token = md5($uid);
                    $this->redirect($redirect_url . '?user_token=' . $user_token . '&state=qq');
                }
                $retval = $this->user->qqLoginNew($token['openid'], $this->website_id);
                // 已经绑定
                if ($retval > 1) {
                    if (request()->isMobile()) {
                        $user_token = md5($retval);
                        $this->redirect($redirect_url . '?user_token=' . $user_token . '&state=qq');
                    }
                    if (!empty($_SESSION['login_pre_url'])) {
                        $this->redirect($_SESSION['login_pre_url']);
                    } else {
                        $redirect = __URL(__URL__ . '/member/index');
                        $this->redirect($redirect);
                    }
                    // $this->success('登录成功', 'Index/index');
                }
                if ($retval == USER_NBUND) {
                    $qq = ThinkOauth::getInstance('QQLOGIN', $token, $this->website_id);
                    $data = $qq->call('user/get_user_info');
                    $qqInfo['data'] = $data;
                    $qqInfo['type'] = 2;
                    $qqInfo['openid'] = $token['openid'];
                    if ($qqInfo['data'] && $qqInfo['openid']) {
                        Session::set('oauthWq', $qqInfo,OAUTH_LOGIN_SESSION_TIME);
                    }
                    if (request()->isMobile()) {
                        // todo... 新增用户
                        $user_info = $qqInfo['data'];
                        // 绑定新用户
                        $user = new User();
                        $params = [
                            'user_name'     => $user_info['nickname'],
                            'is_system'     => 0,
                            'is_member'     => 1,
                            'qq_openid'     => 1,
                            'instance_id'   => 0,
                            'website_id'    => $this->website_id,
                        ];
//                        $uid = $user->add($user_info['nickname'], '', '', '', 0, 1, $qqInfo['openid'],'', '', '', '', 0, $this->website_id, '', '');
                        $uid = $user->add($params);
                        if ($uid > 1) {
                            $oa_data['type'] = 2;
                            $oa_data['nickname'] = $user_info['nickname'];
                            $oa_data['headimgurl'] = $user_info['figureurl_qq'];
                            if ($user_info['gender'] == '男') {
                                $sex = 1;
                            } else if ($user_info['gender'] == '女') {
                                $sex = 2;
                            } else {
                                $sex = 0;
                            }
                            $oa_data['sex'] = $sex;
                            $this->user->updateUserNew([
                                'user_headimg' => $oa_data['headimgurl'],
                                'nick_name'    => $oa_data['nickname'],
                                'sex'          => $oa_data['sex'],
                                'user_token'   => md5($uid)
                            ], ['uid' => $uid]);
                            Session::set('oauthWq', $oa_data,OAUTH_LOGIN_SESSION_TIME);
                            $this->redirect($redirect_url . '?user_token=' . md5($uid) . '&state=qq');
                        }
                        $redirect = __URL(__URL__ . '/login/bMobile');
                        $this->redirect($redirect);
                    }
                }
              }
            } else if ($_SESSION['login_type'] == 'WCHAT') {
                $wchat = ThinkOauth::getInstance('WCHAT', NULL, $this->website_id);
                $token = $wchat->getAccessToken($code, $this->website_id);
                $wchat = ThinkOauth::getInstance('WCHAT', $token, $this->website_id);
                $data = $wchat->call('sns/userinfo');
                $wxInfo['data'] = $data;
                $wxInfo['type'] = 1;
                $wxInfo['unionid'] = $token['unionid'];
                if ($wxInfo['data'] && $wxInfo['unionid']) {
                    Session::set('oauthWq', $wxInfo,OAUTH_LOGIN_SESSION_TIME);
                }
                if (!empty($token['unionid'])) {
                    $retval = $this->user->wchatUnionLogin($token['unionid'], $token['openid'], 4);//第三个参数 ： 1-公众号 2-小程序 3-移动H5  4-PC  5-APP
                    // 已经绑定
                    if ($retval > 1) {
                        if (request()->isMobile()) {
                            $user_token = md5(Session::get($this->model . 'uid'));
                            $this->redirect('/wap/author?user_token=' . $user_token);
                        }
                        if (!empty($_SESSION['login_pre_url'])) {
                            $this->redirect($_SESSION['login_pre_url']);
                        } else {
                            $redirect = __URL(__URL__ . 'memberCenter');
                            $this->redirect($redirect);
                        }
                    }
                    if ($retval == USER_NBUND) {
                        // 2.绑定操作
                        $wchat = ThinkOauth::getInstance('WCHAT', $token, $this->website_id);
                        $data = $wchat->call('sns/userinfo');
                        $wxInfo['data'] = $data;
                        $wxInfo['type'] = 1;
                        $wxInfo['unionid'] = $token['unionid'];
                        if ($wxInfo['data'] && $wxInfo['unionid']) {
                            Session::set('oauthWq', $wxInfo,OAUTH_LOGIN_SESSION_TIME);
                        }
                        if (request()->isMobile()) {
                            //$this->redirect('/wap/login/author?code=' . $code);
                            $this->redirect('/wap/bind/account');
                        }
                        //判断账号体系中是否是第三种并且是否设置了绑定手机
                        $member = new Member();
                        $retval = $member->registerMember('', $wxInfo['data']['nickname'], '', '', '', '','' , '', json_encode($wxInfo['data']), $wxInfo['unionid'], '', $wxInfo['data']['openid'], '', '', 4);
                        if($retval){
                            $this->user->wchatUnionLogin($token['unionid'], $token['openid'], 4);//第三个参数 ： 1-公众号 2-小程序 3-移动H5  4-PC  5-APP
                            if (!empty($_SESSION['login_pre_url'])) {
                                $this->redirect($_SESSION['login_pre_url']);
                            } else {
                                $redirect = __URL(__URL__ . '/member/index');
                                $this->redirect($redirect);
                            }
                        }
                    }
                }
            }
        }

    /**
     * 重设密码
     */
    public function resetPassword()
    {
        $mobile = request()->post('mobile');
        $verification_code = request()->post('verification_code');
        $password = request()->post('password');
        $website = new WebSiteModel();
        $website_info = $website->getInfo(['website_id' => $this->website_id], 'account_type');
        if (empty($mobile) || empty($password)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        if ($mobile != Session::get('sendMobile')) {
            return json(['code' => -1, 'message' => '手机已更改请重新获取验证码']);
        }
        if ($verification_code != Session::get('mobileVerificationCode')) {
            return json(['code' => -1, 'message' => '手机验证码错误']);
        }
        $data['user_password'] = md5($password);

        $condition['user_tel'] = $mobile;
        $condition['website_id'] = $this->website_id;
        $condition['is_member'] = 1;
        if($website_info['account_type'] == 3){
            $condition['mall_port'] == request()->post('mall_port', 0);
        }
        $result = $this->user->updateUserNew($data, $condition);
        if ($result > 0) {
            Session::delete('send_mobile_verification_times, sendMobile');
            return json(['code' => 1, 'message' => '修改成功']);
        } else {
            return json(['code' => -1, 'message' => '修改失败']);
        }
    }

    /**
     * 获取用户注册协议
     * type 1用户注册协议 2会员购买协议
     */
    public function registerProtocol()
    {
        $type = request()->post('type', 1);
        $name = $type == 1 ? 'register_protocol_article' : 'pur_protocol_article';
        $website_info = WebSiteModel::get($this->website_id, [$name]);
        if (!empty($website_info['reg_id']) || !empty($website_info['pur_id'])) {
            return json(['code' => 1, 'message' => '获取成功', 'data' => ['register_protocol' => $website_info[$name]['content']]]);
        } else {
            return json(['code' => 0, 'message' => '不存在注册协议']);
        }

    }

    /**
     * 注册账户
     */
    public function register()
    {
        $member = new Member();
        $password = request()->post('password', '');
        $mobile = request()->post('mobile', '');
        $extend_code = request()->post('extend_code', '');
        $verification_code = request()->post('verification_code');
        $country_code = request()->post('country_code');
        $mall_port = request()->post('mall_port', 0);
        if (empty($mobile) || empty($password) || empty($verification_code)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        if ($mobile != Session::get('sendMobile')) {
            return json(['code' => -1, 'message' => '手机已更改请重新获取验证码']);
        }
        if ($verification_code != Session::get('mobileVerificationCode')) {
            return json(['code' => -1, 'message' => '手机验证码错误']);
        }

        $retval = $member->registerMember($extend_code, '', $password, '', $mobile, '', '', '', '', '', '', '', '', '', $mall_port,$country_code,'');

        if ($retval > 0) {
            Session::delete(['mobileVerificationCode', 'sendMobile']);
            //注册营销
            if (getAddons('registermarketing', $this->website_id)) {
                $registerMarketingServer = new RegisterMarketing();
                $registerMarketingServer->deliveryAward($retval);
            }

            return json([
                'code' => 1,
                'message' => '注册成功',
                'data' => [
                    'user_token' => md5($retval),
                    'have_mobile' => true,// 注册要用手机，直接返回有手机
                ]
            ]);
        }

        return json(AjaxReturn($retval));
    }

    /**
     * 关联账号
     */
    public function AssociateAccount()
    {
        $post               = request()->post();
        $mobile             = $post['mobile'];
        $verification_code  = $post['verification_code'];
        $password           = $post['password']; // by sgw
//        $type               = $post['type'];//请求端：1微信 2qq 3小程序 4APP
        $mall_port          = $post['mall_port'];
        $country_code       = $post['country_code'];//区号
        $get_wx_mobile      = $post['get_wx_mobile'];//bool 是否获取微信用户手机（只针对小程序）
        $wap_port           = Session::get('wap_port', '') ?: $mall_port;//应用端口: 1-公众号 2-小程序 3-移动H5 4-PC 5-APP
        $oauthWq            = Session::get('oauthWq');
        $oauthWq['type']    = $oauthWq['type'];//1微信 2qq 3小程序 4APP
        # miniprogram
        $mp_session_key     = Session::get('mp_token')['session_key'];//oauthLogin接口小程序code调微信换回
        $encrypted_data     = $post['encrypted_data'];
        $iv                 = $post['iv'];
    
        if (!$oauthWq['type']){
            return AjaxReturn(LOGIN_EXPIRE);
        }
        if(!$wap_port){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        
        # 小程序获取用户手机号
        if ($get_wx_mobile) {
            if (!$mp_session_key) {
                return json(['code' => 5, 'message' => 'session_key已过期']);
            }
            if (!$encrypted_data || !$iv) {
                return AjaxReturn(LACK_OF_PARAMETER);
            }
            # 解密信息
            $wchat_open = new WchatOpen($this->website_id);
            $mp_result = $wchat_open->getMpUnionId($mp_session_key, $encrypted_data, $iv);
            if ($mp_result['code'] < 0) {
                return $mp_result;
            }
            $mp_result      = json_decode($mp_result['data'], true);
            $mobile         = $mp_result['purePhoneNumber'];//没有区号的手机号
            $country_code   = $mp_result['countryCode'];
            $password       = $mobile;//默认给密码为手机号
        }
    
        if (empty($mobile)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        // 判断是否有头像
        $member         = new Member();
        $user_model     = new UserModel();
        $member_model   = new VslMemberModel();
        $website        = new WebSiteModel();
        $uid            = $this->uid;//微信授权后新增的uid
        $account_type = $website->getInfo(['website_id' =>$this->website_id], 'account_type')['account_type'];
        //先通过uid查出当前是哪个端口
        if($account_type == 3){
            //先通过uid查出当前是哪个端口
            $mall_port = $user_model->getInfo(['uid' => $uid], 'mall_port')['mall_port'] ?:0;
        }else{
            $mall_port = 0;
        }

        // 检测是否绑定过，绑定过不能再绑

        if ($mobile != Session::get('sendMobile') && !$get_wx_mobile) {
            return AjaxReturn(FAIL, [], '手机已更改请重新获取验证码');
        }
        if ($verification_code != Session::get('mobileVerificationCode')  && !$get_wx_mobile) {
            return AjaxReturn(FAIL, [], '验证码错误！');
        }

        $condition['user_tel']              = $mobile;
        $condition['sys_user.website_id']   = $this->website_id;
        $condition['is_member']             = 1;
        if($mall_port){
            $condition['mall_port'] = $mall_port;
        }
        $user_info = $user_model::get($condition, ['member_info']);//shop注册
        if ($user_info['user_status'] == USER_LOCK) {
            return AjaxReturn(USER_LOCK);
        }
        if(!empty($user_info) ){
            if( !empty($user_info['wx_unionid']) && $account_type == 3){
                $wx_unionid = $user_model->getInfo(['uid' => $uid])['wx_unionid'];
                if($wx_unionid == $user_info['wx_unionid']){//如果当前用户unionid 跟查到的unionid 不相等说明是两个不同的账号，不能认为绑定过。
                    return AjaxReturn(FAIL, [], '已有其它账号绑定过该手机号码！');
                }
            }
            switch($wap_port){
                case 1://公众号
                    if( !empty($user_info['wx_openid']) ){//如果需要合并的账号微信open_id不为空，说明已经合并过了，不能再合并了
                        return AjaxReturn(FAIL, [], '已有其它账号绑定过该手机号码！');
                    }
                    break;
                case 2://小程序
                    if( !empty($user_info['mp_open_id']) ){//如果需要合并的账号微信open_id不为空，说明已经合并过了，不能再合并了
                        return AjaxReturn(FAIL, [], '已有其它账号绑定过该手机号码！');
                    }
                    break;
                case 5:
                    if( !empty($user_info['app_wx_openid']) ){//如果需要合并的账号微信open_id不为空，说明已经合并过了，不能再合并了
                        return AjaxReturn(FAIL, [], '已有其它账号绑定过该手机号码！');
                    }
                    break;//app
            }
        }
        if (!empty($user_info)) {
            //迁移海报奖励
            //会员账户表
            $member_account         = new VslMemberAccountModel();
            $member_account_records = new VslMemberAccountRecordsModel();
            $member_mdl             = new VslMemberModel();
            $del_account_info  = $member_account->getInfo(['uid' => $uid], '*');
            $real_account_info = $member_account->getInfo(['uid' => $user_info['uid']], '*');

            //成长值
            $del_growth  = $member_model->getInfo(['uid' => $uid], 'growth_num')['growth_num'];
            $real_growth = $member_model->getInfo(['uid' => $user_info['uid']], 'growth_num')['growth_num'];

            //积分
            $account_data['point']   = $real_account_info['point'] + $del_account_info['point'];
            //余额
            $account_data['balance'] = $real_account_info['balance'] + $del_account_info['balance'];
            $member_account->save($account_data, ['uid' => $user_info['uid']]);
            //成长值
            $member_data['growth_num'] = $del_growth + $real_growth;
            $member_model->save($member_data, ['uid' => $user_info['uid']]);
            //余额流水
            $member_account_records->save(['uid'=>$user_info['uid']], ['uid' => $uid]);
            if(getAddons('taskcenter', $this->website_id)){
                $user_task = new UserTaskModel();
                $poster_record = new VslPosterRecordModel();
                //任务
                $user_task->save(['uid'=>$user_info['uid']], ['uid' => $uid]);
                //海报扫描纪录
                $poster_record->save(['reco_uid'=>$user_info['uid']], ['reco_uid' => $uid]);
                $poster_record->save(['be_reco_uid'=>$user_info['uid']], ['be_reco_uid' => $uid]);
            }
            //推荐人
            //先查出来uid的上下级关系
            $up_uid_arr = $member_mdl->getInfo(['uid' => $uid], 'referee_id,recommend_id');
            $nodeal_member_info = $member_mdl->getInfo(['uid' => $user_info['uid']], 'referee_id,recommend_id');
            //判断如果 不删掉的会员信息里面有上下级关系，则不覆盖上下级。
            if(!$nodeal_member_info['referee_id']){
                $need_change_upuid['referee_id'] = $up_uid_arr['referee_id'];
                //假设在后台设置的关系：A的上级为B，A绑定手机，B去绑定同一个手机，B合并到A，B用户删掉，A用户的上级去掉，变为总店。
                if($nodeal_member_info['referee_id'] == $uid){
                    $need_change_upuid['referee_id'] = 0;
                }
                $member_mdl->save($need_change_upuid, ['uid' => $user_info['uid']]);
            }
            if($up_uid_arr['recommend_id']){
                $member_mdl->save(['recommend_id'=>$up_uid_arr['recommend_id']], ['uid' => $user_info['uid']]);
            }
            if(getAddons('giftvoucher', $this->website_id)){
                //礼品券
                $voucher_records = new VslGiftVoucherRecordsModel();
                $voucher_change_uid_data['uid'] = $user_info['uid'];
                $voucher_records->save($voucher_change_uid_data, ['uid' => $uid]);
            }
            if(getAddons('coupontype', $this->website_id)){
                //优惠券
                $coupon = new VslCouponModel();
                $coupon_change_uid_data['uid'] = $user_info['uid'];
                $coupon->save($coupon_change_uid_data, ['uid' => $uid]);
            }
            // 删除 之前unionid/openid 注册的账号
            $member->deleteMember($uid);
            switch($oauthWq['type']){
                case 1://微信
                    $open_id_arr['wx_openid'] = $oauthWq['openid'];
                    break;
                case 2://qq
                    //关联已有的账号
                    $result = $member->bUserInfo(2, $user_info['uid'], '', $mobile, json_encode($oauthWq['data']), $oauthWq['unionid'],$country_code);
                    if (!empty($result) && $result > 0) {
                        $user_info = $this->user->login($mobile, '', 1);
                        if (is_array($user_info)) {
                            Session::delete(['mobileVerificationCode', 'sendMobile']);
                            return AjaxReturn(SUCCESS, ['user_token' => md5($user_info['uid'])], '关联成功！');
                        } else {
                            return AjaxReturn(FAIL, [], '关联失败！');
                        }
                    }
                    break;
                case 3://mp
                    $open_id_arr['mp_open_id'] = $oauthWq['openid'];
                    break;
                case 4://app
                    $open_id_arr['app_wx_openid'] = $oauthWq['openid'];
                    break;
            }
            $open_id_arr['wx_unionid']      = $oauthWq['unionid']?:$user_info['wx_unionid'];
            $open_id_arr['nick_name']       = $user_info['nick_name'] ?: $oauthWq['nickname'];
            $open_id_arr['user_headimg']    = $user_info['user_headimg'] ?: $oauthWq['headimgurl'];
            $open_id_arr['sex']             = $user_info['sex'] ?: $oauthWq['sex'];
            // 如果用户有就不覆盖
            $this->user->updateUserNew($open_id_arr, ['uid' => $user_info['uid']]);
            unset($condition);
            $condition['user_tel']          = $mobile;
            $condition['website_id']        = $this->website_id;
            $condition['is_member']         = 1;
            if($account_type == 3 && $mall_port){
                $condition['mall_port'] = $mall_port;
            }
            $this->user->wchatLoginNew($condition, $mall_port);
            $res = $member_model->save(['mobile' => $mobile], ['uid' => $user_info['uid']]);
            $data = [
                'user_token'    => md5($user_info['uid']),
                'have_mobile'   => true
            ];
            if($res){
                return AjaxReturn(SUCCESS, $data, '关联成功！');
            }else{
                return AjaxReturn(FAIL, [], '关联失败！');
            }

        } else {
            //新增用户
            if ($oauthWq['type'] == 2) {
                //qq
                $result = $member->registerMember('', '', '', '', $mobile, $oauthWq['openid'], json_encode($oauthWq['data']), '', '', '','','','',0,0,$country_code);
            }
            if ($oauthWq['type'] == 1) {
                //wechat
                if (empty($password)) {
                    return AjaxReturn(REGISTER_PASSWORD_ERROR);
                }
                $update_data = [
                    'user_tel' => $mobile,
                    'user_password' => md5($password),
                    'country_code' => $country_code?:86,
                ];
                unset($condition);
                $condition['uid'] = $uid;
                $condition['website_id'] = $this->website_id;
                $condition['is_member'] = 1;
                $this->user->updateUserNew($update_data, $condition);
                //注册营销
                if (getAddons('registermarketing', $this->website_id)) {
                    $registerMarketingServer = new RegisterMarketing();
                    $registerMarketingServer->deliveryAward($uid);
                }
                $data = [
                    'user_token' => md5($uid),
                    'have_mobile' => true
                ];
                return AjaxReturn(SUCCESS, $data, '注册账号成功！');
            }
            if ($oauthWq['type'] == 3) {
                // mini program
                if (empty($password)) {
                    return AjaxReturn(LACK_OF_PARAMETER);
                }

                $update_data = [
                    'user_tel' => $mobile,
                    'user_password' => md5($password),
                    'country_code' => $country_code?:86,
                ];
                unset($condition);
                $condition['uid'] = $uid;
                $condition['website_id'] = $this->website_id;
                $condition['is_member'] = 1;
                $this->user->updateUserNew($update_data, $condition);
                $user_info = $this->user->loginNew($condition);
                $member_model->save(['mobile' => $mobile], ['uid' => $user_info['uid']]);
                //注册营销
                if (getAddons('registermarketing', $this->website_id)) {
                    $registerMarketingServer = new RegisterMarketing();
                    $registerMarketingServer->deliveryAward($user_info['uid']);
                }
                $data = [
                    'user_token' => md5($user_info['uid']),
                    'have_mobile' => true
                ];
                return AjaxReturn(SUCCESS, $data, '注册账号成功！');
            }

            if ($oauthWq['type'] == 4) {//app
                //wechat
                if (empty($password)) {
                    return AjaxReturn(LACK_OF_PARAMETER);
                }
                $update_data = [
                    'user_tel'          => $mobile,
                    'user_password'     => md5($password),
                    'country_code'      => $country_code?:86,
                ];
                unset($condition);
                $condition['uid']           = $uid;
                $condition['website_id']    = $this->website_id;
                $condition['is_member']     = 1;
                $this->user->updateUserNew($update_data, $condition);
                $user_info = $this->user->loginNew($condition);
                $member_model->save(['mobile' => $mobile], ['uid' => $user_info['uid']]);
                //注册营销
                if (getAddons('registermarketing', $this->website_id)) {
                    $registerMarketingServer = new RegisterMarketing();
                    $registerMarketingServer->deliveryAward($user_info['uid']);
                }
                $data = [
                    'user_token' => md5($user_info['uid']),
                    'have_mobile' => true
                ];
                return AjaxReturn(SUCCESS, $data, '注册账号成功！');
            }

            if (!empty($result) && $result > 0) {
                $user_info = $this->user->login($mobile, '', 1);
                if ($user_info == USER_LOCK) {
                    Session::delete(['mobileVerificationCode', 'sendMobile']);
                    return AjaxReturn(USER_LOCK);
                }
                if (is_array($user_info)) {
                    //注册营销
                    if (getAddons('registermarketing', $this->website_id)) {
                        $registerMarketingServer = new RegisterMarketing();
                        $registerMarketingServer->deliveryAward($user_info['uid']);
                    }

                    return AjaxReturn(SUCCESS, ['user_token' => md5($user_info['uid'])], '注册账号成功！');
                }
            }
            return AjaxReturn(FAIL,[], '注册账号失败！');
        }
    }
    // 判断手机号存在不
    public function mobile()
    {

        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $user_mobile = request()->post('mobile', '');
            $wap_port = request()->post('mall_port', 0);//应用端口:1-公众号 2-小程序 3-移动H5 4-PC 5-APP
            if($wap_port){
                Session::set('wap_port', $wap_port);//将端口存入session,用于下一步判断。
            }
            // 判断是哪种账号体系
            $website = new WebSiteModel();
            $account_type = $website->getInfo(['website_id' => $this->website_id], 'account_type')['account_type'];
            if($account_type == 3){
                $uid = $this->uid;
                if ($uid) {
                    $user = new UserModel();
                    $mall_port = $user->getInfo(['uid' => $uid], 'mall_port')['mall_port'];
                } else {
                    $mall_port = request()->post('mall_port', 0);
                }
            }else{
                $mall_port = 0;
            }
            if (empty($user_mobile)) {
                return AjaxReturn(LACK_OF_PARAMETER);
            }
            $member = new Member();
            $result = $member->memberIsMobile($user_mobile, $mall_port, $wap_port);
            if ($result) {
                if ($result === USER_LOCK) {
                    return json(AjaxReturn(USER_LOCK));
                }
                return json(['code' => 1, 'message' => '存在该手机号码']);
            } else {
                return json(['code' => 0, 'message' => '不存在该手机号码']);
            }
        }
    }

    /**
     * 判断邮箱是否存在
     */
    public function email()
    {
        if (request()->isAjax()) {
            // 获取数据库中的用户列表
            $user_email = request()->post('email', '');
            $member = new Member();
            $result = $member->memberIsEmail($user_email);
            if ($result) {
                if ($result === USER_LOCK) {
                    return json(AjaxReturn(USER_LOCK));
                }
                return json(['code' => 1, 'message' => '存在该邮箱']);
            } else {
                return json(['code' => 0, 'message' => '不存在该邮箱']);
            }
        }
    }

    /**
     * 获取手机短信验证码
     */
    public function getVerificationCode()
    {
        if (!$this->config['mobile_verification']) {
            return json(['code' => -1, 'message' => '商城未开启短信模版']);
        }
        $mobile = request()->post('mobile', '');
        $type = request()->post('type');
        $send_mobile_verification_times = Session::get('send_mobile_verification_times') ?: 0;

            $params['mobile'] = $mobile;
            $params['shop_id'] = $this->instance_id;
            $params['website_id'] = $this->website_id;
            $params['country_code'] = request()->post('country_code');
            switch ($type) {
                case 'login':
                    $result = runhook('Notify', 'loginBySms', $params);
                    break;
                case 'forget_password':
                    $result = runhook('Notify', 'forgotPasswordBySms', $params);
                    break;
                case 'reset_password':
                    $result = runhook('Notify', 'changePasswordBySms', $params);
                    break;
                case 'register':
                    $result = runhook('Notify', 'registerBefore', $params);
                    break;
                case 'bind_mobile':
                    $result = runhook('Notify', 'bindMobileBySms', $params);
                    break;
                case 'bind_email':
                    $result = runhook('Notify', 'bindEmailBySms', $params);
                    break;
                case 'change_pay_password':
                    $result = runhook('Notify', 'changePayPasswordBySms', $params);
                    break;
                case 'change_password':
                    $result = runhook('Notify', 'changePasswordBySms', $params);
                    break;
                case 'anti_forgot_password':
                    $result = runhook('Notify', 'antiForgotPasswordBySms', $params);
                    break;
            }

        if (!empty($result) && !empty($result['param'])) {
            $expire = 5 * 60;       
            Session::set('mobileVerificationCode', $result['param'], $expire);
            Session::set('sendMobile', $mobile, $expire);
            Db::table('sys_log')->insert(['content'=>$result['param']]);
            Session::set('send_mobile_verification_times', ++$send_mobile_verification_times, $expire);
            return json(['code' => 1, 'message' => '发送成功']);
        } elseif(isset($result['code']) && isset($result['message']) && $result['code']== -1) {
            return json(['code' => -1, 'message' => $result['message']]);
        } else {
            return json(['code' => -1, 'message' => '发送失败']);
        }
    }

    public function getEmailVerificationCode()
    {
        if (!$this->config['email_verification']) {
            return json(['code' => -1, 'message' => '网站没开启邮箱验证']);
        }
        $email = request()->post('email') ?: request()->get('email');
        $type = request()->post('type');
        switch ($type) {
            case 'bind_email':
                $params['to_email'] = $email;
                $params['shop_id'] = $this->instance_id;
                $params['website_id'] = $this->website_id;
                $params['expire'] = 5;
                $params['notify_type'] = 'user';
                $params['template_code'] = 'bind_email';
                $result = runhook('Notify', 'emailSend', $params);
                break;
        }
        if ($result['code'] == 0) {
            Session::set('EmailVerificationCode', $result['param'], $params['expire'] * 60);
            Session::set('sendEmail', $email, $params['expire'] * 60);
            return json(['code' => 1, 'message' => '发送成功']);
        } else {
            return json($result);
        }
    }

    /**
     * wap第三方登录 - 新处理
     */
    public function oauthLogin_new()
    {
        $member = new Member();
        $wchat_oauth = new WchatOauth($this->website_id);
        $type = request()->param('type')?:Session::get('oauth_login_type');
        $code = request()->post('code');
        $extend_code = request()->post('extend_code');
        // miniProgram
        $encrypted_data = request()->post("encrypted_data");
        $iv = request()->post("iv");
        // qq
        $qq_redirect_url =  request()->post('redirect_url','');
        if ($qq_redirect_url) {
            Session::set('temp_qq_redirect_url', '/wap'.$qq_redirect_url);
        }
        if ($extend_code) {
            Session::set('extend_code', $extend_code);
        }
        //判断账号体系
        $website = new WebSiteModel();
        $account_type = $website->getInfo(['website_id' => $this->website_id], 'account_type')['account_type'];
        # 默认登录
        if (isWeixin() && !empty($_SERVER['HTTP_USER_TOKEN']) ){
            // 让微信环境下每次进入商城不请求微信的授权登陆，先使用常规登陆
            $user_token = $_SERVER['HTTP_USER_TOKEN'];
            $result = $member->wchatLoginNew(['user_token' => $user_token, 'is_member' => 1]);
            if ($result['code'] == USER_LOCK) {
                return json(['code' => 3, 'message' => '用户被锁定']);
            }
            if ($result['code'] == USER_NBUND) {
                echo json_encode(['code' => LOGIN_EXPIRE, 'message' => '登录信息已过期，请重新登录'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($result['code'] == 1) {
                $user_info = $result['user_info'];
                unset($result);
                // 重新获取用户信息
                if (empty($user_info['user_headimg'])) {
                    if ($openid = $user_info['wx_openid']) {
                        $user_return = $wchat_oauth->get_oauth_member_info_openid($openid);//获取用户信息
                        if (!$user_return['errcode']) {
                            $this->user_model->save(['user_headimg' => $user_return['headimgurl'], 'user_token' => $user_token]);
                        }
                    }
                }
                $data['user_token'] = $user_token;
                $data['have_mobile'] = $user_info['user_tel'] ? true : false;
                if (isMiniProgram()) {
                    $oa_data['type'] = 3;
                    $oa_data['unionid'] = $user_info['wx_unionid'];
                    $oa_data['openid'] = $user_info['mp_open_id'];
                    $oa_data['nickname'] = $user_info['nick_name'];
                    $oa_data['headimgurl'] = $user_info['user_headimg'];
                    $oa_data['sex'] = $user_info['sex'];
                    Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                    return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
                }
                $oa_data['type'] = 1;
                $oa_data['unionid'] = $user_info['wx_unionid'];
                $oa_data['openid'] = $user_info['wx_openid'];
                $oa_data['nickname'] = $user_info['nick_name'];
                $oa_data['headimgurl'] = $user_info['user_headimg'];
                $oa_data['sex'] = $user_info['sex'];
                Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
            }
        }
        Session::set('oauth_login_type', $type);
        if ($type == 'WCHAT') {
            if (isWeixin()) {
                //  微信环境 微信登陆流程
                //  1.url回调前端
                //  2.前端get带上1步拿到的code
                //  3.授权完成获取access_token
                //  4.执行相应入库操作，并返回USER_TOKEN，后面访问都带上USER_TOKEN,先用USER_TOKEN登录
                $result = $this->wchatLogin();
                if ($result['code'] == -9999) {
                    return json(['code' => 4, 'message' => '配置有效', 'data' => ['url' => $result['url']]]);
                }
                /****************** new code start ****************************/
                if ($result['code'] == 1) {
                    $domain_name = Request::instance()->domain();
                    $token = $result['token'] ?: json_decode($_COOKIE[$domain_name . "member_access_token"], true);
                    # 准备基本信息
                    $wechat_user_info = $wchat_oauth->get_oauth_member_info($token);//获取用户信息
                    $wx_unionid = !empty($token['unionid']) ? $token['unionid'] : $wechat_user_info['unionid'];
                    $wx_openid = $token['openid'] ?: '';   // 新用户授权进来开始没有unionid
                    # 存储session
                    $oa_data['type'] = 1;//wechat
                    $oa_data['unionid'] = $wx_unionid;
                    $oa_data['openid'] = $wx_openid;
                    unset($result);
                    if (!empty($wx_unionid)) { // unionid
                        // (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
                        $result = $this->user->wchatLoginNew(['wx_unionid' => $wx_unionid], 1);//联合登录判断
                        // 防止第一次没拿到unionid，然后生成记录，之后进来有unionid
                        if ($result['code'] == USER_NBUND) {
                            $result = $this->user->wchatLoginNew(['wx_openid' => $wx_openid], 1);
                        }
                    } elseif (!empty($wx_openid)) { // openid
                        $result = $this->user->wchatLoginNew(['wx_openid' => $wx_openid], 1);
                    }
                    /*** 根据上面$result 进行相应处理 ***/
                    if ($result['code'] == USER_LOCK) {
                        return json(['code' => 3, 'message' => '用户被锁定']);
                    }
                    if ($result['code'] == USER_NBUND) {
                        if($account_type == 3){
                            $mall_port = 1;
                        }else{
                            $mall_port = 0;
                        }

                        // 绑定新用户
                        $extend_code2 = request()->get('extend_code');
                        $extend_code = empty($extend_code) ? $extend_code2 : $extend_code;
                        $uid = $member->registerMember($extend_code, '', '', '', '', '', '', $wx_openid, '', $wx_unionid,'','','','', $mall_port,'',$wechat_user_info['nickname']);// (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
                        if ($uid > 1) {
                            $oa_data['nickname'] = $wechat_user_info['nickname'];
                            $oa_data['headimgurl'] = $wechat_user_info['headimgurl'];
                            $oa_data['sex'] = $wechat_user_info['sex'];
                            $this->user->updateUserNew([
                                'user_headimg' => $oa_data['headimgurl'],
                                'nick_name' => $oa_data['nickname'],
                                'sex' => $oa_data['sex'],
                                'user_token' => md5($uid)
                            ], ['uid' => $uid]);
                            Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                            // 用户信息初始化
                            $this->user->wchatLoginNew(['uid' => $uid]);
                            $data = [
                                'user_token' => md5($uid),
                                'have_mobile' => false,
                                'openid' => $wx_openid
                            ];
                            return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
                        } else {
                            return json(['code' => -1, 'message' => '登录失败！']);
                        }
                    }
                    if ($result['code'] == 1) {
                        //  如果用户已存在数据,则不覆盖
                        $user_info  = $result['user_info'];
                        // 默认原来
                        $oa_data['nickname'] = $user_info['nick_name'];
                        $oa_data['headimgurl'] = $user_info['user_headimg'];
                        $oa_data['sex'] = $user_info['sex'];
                        // 去覆盖
                        if (empty($user_info['nick_name'])) {
                            $oa_data['nickname'] = $wechat_user_info['nickname'];
                            $oa_data['sex'] = $wechat_user_info['sex'];
                        }
                        if (empty($user_info['user_headimg'])) {
                            $oa_data['headimgurl'] = $wechat_user_info['headimgurl'];
                            $oa_data['sex'] = $wechat_user_info['sex'];
                        }
                        $this->user->updateUserNew([
                            'user_headimg' => $oa_data['headimgurl'],
                            'nick_name' => $oa_data['nickname'],
                            'sex' => $oa_data['sex'],
                            'user_token' => md5($user_info['uid']),
                            'wx_openid' => $user_info['wx_openid'] ?: $wx_openid,
                            'wx_unionid' => $user_info['wx_unionid'] ?: $wx_unionid
                        ], ['uid' => $user_info['uid']]);
                        Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                        $data = [
                            'user_token' => md5($user_info['uid']),
                            'have_mobile' => !empty($user_info['user_tel']) ? true : false,
                            'openid' => $wx_openid
                        ];

                        return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
                    }
                }
                /****************** new code /end ****************************/
            }
        } else if ($type == 'QQLOGIN') {
            $config = new WebConfig();
            $config_info = $config->getConfig(0, 'QQLOGIN', $this->website_id);
            if (empty($config_info['value']['APP_KEY']) || empty($config_info['value']['APP_SECRET'])) {
                return json(['code' => -1, 'message' => '当前系统未设置QQ第三方登录!']);
            }
        } else if ($type == 'MP') {
            // 1.code去换回session_key
            // 2.session_key,encrypted_data,iv去解密用户信息
            $wchat_open = new WchatOpen($this->website_id);
            if (empty($code) || empty($encrypted_data) || empty($iv)) {
                return json(['code' => -1, 'message' => '缺少参数!']);
            }
            # 获取token
            $token = $wchat_open->code_to_session($code);
            if ($token->errcode ) {
                return json(['code' => -1, 'message' => AjaxWXReturn($token->errcode,[], $token->errmsg)['message']]);
            }
            $session_key = $token->session_key ?: '';
            $unionid = $token->unionid ?: '';
            $openid = $token->openid ?: '';
            # 解密信息
            $mp_result = $wchat_open->getMpUnionId($session_key, $encrypted_data, $iv);
            if (!$mp_result['code'] < 0) {
                return $mp_result;
            }
            $wechat_user_info = json_decode($mp_result['data'], true);
            /********** MP new start  **************/
            $wx_unionid = $unionid ?: ($wechat_user_info['unionId'] ?: '');
            $mp_open_id = $openid ?: $wechat_user_info['openId'];
            $session_key = $session_key ?: '';
            # 准备数据
            $oa_data['type'] = 3;//mini program
            $oa_data['data'] = [];
            $oa_data['openid'] = $mp_open_id;
            $oa_data['unionid'] = $wx_unionid;
            $oa_data['session_key'] = $session_key;//会话密钥,暂时没用存session

            unset($result);
            if (!empty($wx_unionid)) {
                // unionid登录
                $result = $this->user->wchatLoginNew(['wx_unionid' => $wx_unionid], 2);//联合登录 (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
                // 防止第一次没拿到unionid，然后生成记录，之后进来有unionid
                if ($result['code'] == USER_NBUND) {
                    $result = $this->user->wchatLoginNew(['mp_open_id' => $mp_open_id], 2);
                }
            }elseif (!empty($mp_open_id)) {
                // openid登录
                $result = $this->user->wchatLoginNew(['mp_open_id' => $mp_open_id], 2);
            }
            /*** 根据上面$result 进行相应处理 ***/
            if ($result['code'] == USER_LOCK) {
                return json(['code' => 3, 'message' => '用户被锁定']);
            }
            if ($result['code'] == USER_NBUND) {
                if($account_type == 3){
                    $mall_port = 2;
                }else{
                    $mall_port = 0;
                }
                // 绑定新用户
                $uid = $member->registerMember($extend_code, '', '', '', '', '', '', '', '', $wx_unionid, $mp_open_id, '', '', '', $mall_port,'',$wechat_user_info['nickName']);
                if ($uid > 1) {
                    $oa_data['type'] = 3;//mini program
                    $oa_data['unionid'] = $wx_unionid;
                    $oa_data['nickname'] = $wechat_user_info['nickName'] ?: '';
                    $oa_data['headimgurl'] = $wechat_user_info['avatarUrl'] ?: '';
                    $oa_data['sex'] = $wechat_user_info['gender'] ?: '';

                    // 这里修改用户头像和昵称
                    $this->user->updateUserNew([
                        'nick_name' => $oa_data['nickname'],
                        'user_headimg' => $oa_data['headimgurl'],
                        'sex' => $oa_data['sex'],
                        'user_token' => md5($uid)
                    ], ['uid' => $uid]);
                    // 用户信息初始化
                    $this->user->wchatLoginNew(['uid' => $uid]);
                    Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                    $data = [
                        'user_token' => md5($uid),
                        'mp_open_id' => $mp_open_id,
                        'have_mobile' => false
                    ];

                    return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
                } else {
                    return json(['code' => -1, 'message' => '登录失败！']);
                }
            }
            if ($result['code'] == 1) {
                //  如果用户已存在数据,则不覆盖
                $user_info  = $result['user_info'];
                // 默认原来
                $oa_data['nickname'] = $user_info['nick_name'];
                $oa_data['headimgurl'] = $user_info['user_headimg'];
                $oa_data['sex'] = $user_info['sex'];
                // 去覆盖
                if (empty($user_info['nick_name'])) {
                    $oa_data['nickname'] = $wechat_user_info['nickName'] ?: '';
                    $oa_data['sex'] = $wechat_user_info['gender'] ?: '';
                }
                if (empty($user_info['user_headimg'])) {
                    $oa_data['headimgurl'] = $wechat_user_info['avatarUrl'] ?: '';
                    $oa_data['sex'] = $wechat_user_info['gender'] ?: '';
                }
                $this->user->updateUserNew([
                    'nick_name' => $oa_data['nickname'],
                    'user_headimg' => $oa_data['headimgurl'],
                    'sex' => $oa_data['sex'],
                    'mp_open_id' => $user_info['mp_open_id'] ?: $mp_open_id,
                    'wx_unionid' => $user_info['wx_unionid'] ?: $wx_unionid
                ], ['uid' => $user_info['uid']]);

                Session::set('oauthWq', $oa_data, OAUTH_LOGIN_SESSION_TIME);
                $data = [
                    'user_token' => md5($user_info['uid']),
                    'mp_open_id' => $mp_open_id,
                    'have_mobile' => $user_info['user_tel'] ? true: false
                ];

                return json(['code' => 1, 'message' => '登录成功！', 'data' => $data]);
            }
            /********** /MP new  end **************/
        } else {
            return json(['code' => -1006, 'message' => '缺失参数！']);
        }
        $_SESSION['login_type'] = $type;
        $test = ThinkOauth::getInstance($type, null, $this->website_id);
        return json(['code' => 4, 'message' => '配置有效', 'data' => ['url' => $test->getRequestCodeURL($this->website_id)]]);
    }
}
