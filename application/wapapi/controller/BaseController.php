<?php
namespace app\wapapi\controller;
use data\service\Address;
use data\service\Member as Member;
use data\service\WebSite as WebSite;
use think\Controller;
use think\Cookie;
use think\Request;
use think\Session;
\think\Loader::addNamespace('data', 'data/');
use data\service\AuthGroup;
class BaseController extends Controller
{

    public $user;

    protected $uid;

    protected $instance_id;

    protected $website_id;


    protected $is_member;

    protected $shop_name;

    protected $user_name;

    protected $shop_id;

    /**
     * 平台logo
     *
     * @var unknown
     */

    public $web_site;

    public $style;

    public $model;
    public $realm_ip;
    protected $is_seckill = 0;
    protected $is_bargain = 0;
    protected $is_channel = 0;
    protected $is_integral = 0;
    protected $is_coupon_type = 0;
    protected $is_full_cut = 0;
    protected $is_discount = 0;
    protected $is_distribution = 0;
//    protected $is_group_shopping = 0;
    protected $is_presell = 0;
    protected $groupshopping = 0;
    protected $gift_voucher = 0;
    protected $is_gift = 0;
    protected $is_shop = 0;
    protected $is_store = 0;
    protected $is_membercard = 0;
    protected $http = '';
    public function __construct()
    {
        Cookie::delete("default_client"); // 还原手机端访问
        parent::__construct();
        $this->user = new Member();
        $this->model = $this->user->getRequestModel();
        $website_id = checkUrl();
        $website_id = 1;
        if (empty($website_id) && is_numeric($website_id)) {
            echo json_encode(AjaxReturn(LACK_OF_PARAMETER), JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (Session::has($this->model . 'website_id') && Session::get($this->model . 'website_id') != $website_id) {
            // 处理已登录状态，切换平台，使得当前用户不在该平台下面仍然可以进行操作
            echo json_encode(['code' => LOGIN_EXPIRE, 'message' => '登录信息已过期，请重新登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $this->website_id = $website_id;
        Session::set($this->model . 'website_id', $website_id);
        $controller = strtolower(Request::instance()->controller());
        $action = request()->action();
        $params = Request::instance()->param();
        $addons_action = isset($params['action']) ? strtolower($params['action']) : '';
        $addons_controller = isset($params['controller']) ? strtolower($params['controller']) : '';
        $is_ssl = Request::instance()->isSsl();
        $this->http = "http://";
        if($is_ssl){
            $this->http = 'https://';
        }
        $this->uid = getUserId();
        if(!in_array($action, ['wchaturlback', 'wchatpay', 'aliurlback', 'alipay', 'aliPayReturn', 'recieve_card', 'wchatpay_api','withdraw','withdrawurlback','payurlbacks','ethpayurlback','eospayurlback','refundurlback','tlurlback','setgoodgather','ethchainurlback','gpayurlback','livemsgcallback','actLiveData'])){
                $_POST   && SafeFilter($_POST);
                $_COOKIE && SafeFilter($_COOKIE);
                //防注入
                if(checkSQLInject(request()->param(),request()->path())){
                        echo json_encode(['code' => -1, 'message' => '错误参数'], JSON_UNESCAPED_UNICODE);
                        exit;
                }
        }
        if(!IS_CLI){
            if (!isApiLegal() && !in_array($action, ['sysordercreate', 'esigncallback', 'joinpaytransferurlback','joinpayallocfundsurlback','joinpayfastreturnurlback','joinpaybankurlback','joinpaybankurlback','joinpayautocomsendurlback','joinpayautosendurlback','joinpayreturnurlback','joinpayurlback','joinpayaliurlback','wchaturlback', 'wchatpay', 'aliurlback', 'alipay', 'aliPayReturn', 'recieve_card', 'wchatpay_api','withdraw','withdrawurlback','payurlbacks','ethpayurlback','eospayurlback','refundurlback','tlurlback','setgoodgather','ethchainurlback','gpayurlback','livemsgcallback','actlivedata']) && !in_array($addons_action, ['showuserqrcode', 'userarchivevoucherpackage', 'userarchivecoupon']) && !in_array($addons_controller, ['goodhelper']) && $controller != 'polyapi' && $controller != 'gjpapi' && $controller != 'dsfgoodsapi' && $controller != 'wchat') {
                $data['code'] = -2;
                $data['message'] = '接口签名错误--';
                echo json_encode($data, JSON_UNESCAPED_UNICODE);
                exit;
            }
            $this->web_site = new WebSite();
            $web_info = $this->web_site->getWebSiteInfo($this->website_id);
            if($web_info['realm_ip']){
                $this->realm_ip = $this->http.$web_info['realm_ip'];
            }else{
                $this->realm_ip = $this->http.$_SERVER['HTTP_HOST'];
            }
            if ($web_info['wap_status'] == 0) {
                //app 不在此控制
//                echo json_encode(AjaxReturn(MALL_WAP_CLOSE), JSON_UNESCAPED_UNICODE);
                echo json_encode(['code' => MALL_WAP_CLOSE, 'message' => $web_info['close_reason'] ?: '商城移动端已关闭'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$this->uid && isWeixin() && !isMiniProgram() && !in_array($controller, ['config', 'custom']) &&
                !in_array($action, ['joinpayautocomsendurlback','joinpayautosendurlback','joinpayreturnurlback','joinpayurlback','joinpayaliurlback','wchaturlback', 'wchatpay', 'aliurlback', 'alipay', 'aliPayReturn', 'recieve_card', 'wchatpay_api','goodslistindex','goodslist','goodsdetail','categoryinfo','gpayurlback']) &&
                !in_array($addons_action, ['showuserqrcode', 'userarchivevoucherpackage','shopsearch','shopinfo']) && !isAnti()) {
                    // 微信浏览器要求保持登陆状态
                    echo json_encode(['code' => LOGIN_EXPIRE, 'message' => '登录信息已过期，请重新登录！'], JSON_UNESCAPED_UNICODE);
                    exit;

            }

            //是否有秒杀、砍价、渠道商、积分商城的应用
            $this->is_seckill = getAddons('seckill', $this->website_id);
            $this->is_bargain = getAddons('bargain', $this->website_id);
            $this->is_channel = getAddons('channel', $this->website_id);
            $this->is_integral = getAddons('integral', $this->website_id);
            $this->is_coupon_type = getAddons('coupontype', $this->website_id);
            $this->is_full_cut = getAddons('fullcut', $this->website_id);
            $this->is_discount = getAddons('discount', $this->website_id);
            $this->is_presell = getAddons('presell',$this->website_id);
            $this->is_distribution = getAddons('distribution', $this->website_id);
            $this->is_shop = getAddons('shop', $this->website_id);
            $this->gift_voucher = getAddons('giftvoucher', $this->website_id);
            $this->is_gift = getAddons('gift', $this->website_id);
            $this->groupshopping = getAddons('groupshopping', $this->website_id);
            $this->luckyspell = getAddons('luckyspell', $this->website_id);
            $this->is_store = getAddons('store', $this->website_id);
            $this->is_membercard = getAddons('membercard',$this->website_id);
            //getWapCache();//开启缓存
            $this->initInfo();
        }
    }
    public function initInfo()
    {


        $ssl =  \think\Request::instance()->domain().\think\Request::instance()->url();
        if(strpos($ssl, '/menu/addonmenu')){
            $auth_method = \request()->param('addons');
            if($auth_method){
                $auth = new AuthGroup();
                $auth_control = $auth->checkMethod($auth_method);
                if($auth_control==1){
                    //$uid = $this->user->getSessionUid();
                    if (empty($this->uid)) {
                        echo json_encode(['code' => LOGIN_EXPIRE, 'message' => '登录信息已过期，请重新登陆'], JSON_UNESCAPED_UNICODE);exit;
                    }
                }
            }
        }
        $this->instance_id = 0;
        $this->shop_id = request()->get('shop_id', 0);
        $this->shop_name = $this->user->getInstanceName();
    }

    /**
     * 获取省列表
     */
    public function getProvince()
    {
        $address = new Address();
        $province_list = $address->getProvinceList();
        return $province_list;
    }

    /**
     * 获取城市列表
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:, array>
     */
    public function getCity()
    {
        $address = new Address();
        $province_id = request()->post('province_id', 0);
        $city_list = $address->getCityList($province_id);
        return $city_list;
    }

    /**
     * 获取区域地址
     */
    public function getDistrict()
    {
        $address = new Address();
        $city_id = request()->post('city_id', 0);
        $district_list = $address->getDistrictList($city_id);
        return $district_list;
    }
}
