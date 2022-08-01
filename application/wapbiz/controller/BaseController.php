<?php

namespace app\wapbiz\controller;

\think\Loader::addNamespace('data', 'data/');

use data\model\MerchantVersionModel;
use think\session;
use data\model\ModuleModel;
use data\model\SysAddonsModel;
use data\service\AdminUser as User;
use data\service\WebSite as WebSite;
use think\Controller;

class BaseController extends Controller
{

    protected $user = null;
    protected $website = null;
    protected $uid;
    protected $instance_id;
    protected $website_id;
    protected $instance_name;
    protected $user_headimg;
    protected $module = null;
    protected $controller = null;
    protected $action = null;
    protected $module_info = null;
    protected $rootid = null;
    protected $moduleid = null;
    protected $merchant_status;
    protected $merchant_expire;
    protected $pcportStatus = 0;
    protected $http = '';
    protected $port = '';
    protected $supplier_id = '';

    /**
     * 当前版本的路径
     *
     * @var string
     */
    protected $style = null;
    protected $limit = 0; //是否达到限制,不同值代表进不同的页面1->订单,2->会员,3->分销

    public function __construct()
    {
        
        parent::__construct();
        $this->user = new User();
        $this->website = new WebSite();
        $this->init();
        
    }

    /**
     * 功能说明：action基类 调用 加载头部数据的方法
     */
    public function init()
    {
        //防注入
        if (checkSQLInject(request()->param(), request()->path())) {
            echo json_encode(['code' => -1, 'message' => '错误参数,请检查输入的参数'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        if (!isApiLegal()) {
            $data['code'] = -2;
            $data['message'] = '接口签名错误';
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }
        $this->uid = $this->user->getSessionUid();
        $is_system = $this->user->getSessionUserIsSystem();
        $this->port = $this->user->getSessionPort();
        if ((!$this->uid && !$this->port) || (!$is_system && $this->port && $this->uid && $this->port != 'supplier') || $this->port == 'platform' && !$this->uid) {
            echo json_encode(['code' => -999, 'message' => '登录信息已过期，请重新登录'], JSON_UNESCAPED_UNICODE);
            exit;
        }elseif($this->port && !$this->uid){
            echo json_encode(['code' => -998, 'message' => '请选择店铺'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        $this->instance_id = $this->user->getSessionInstanceId();
        $this->website_id = $this->user->getSessionWebsiteId();
        $this->instance_name = $this->user->getInstanceName();
        $this->supplier_id = $this->user->getSessionSupplierId();
        $this->module = \think\Request::instance()->module();
        $this->controller = \think\Request::instance()->controller();
        $this->action = \think\Request::instance()->action();
        if(($this->port == 'admin' || $this->port == 'supplier') && $this->controller == 'finance'){
            $this->controller = 'financial';
        }
        if(($this->port == 'admin' || $this->port == 'supplier') && $this->controller == 'addonslist'){
            $this->controller = 'promotion';
        }
        $this->module_info = $this->website->getModuleIdByModule($this->controller, $this->action, $this->port);

        // 过滤控制权限 为0
        if (empty($this->module_info)) {
            $this->moduleid = 0;
            $check_auth = 1;
        } elseif ($this->module_info["is_control_auth"] == 0) {
            $this->moduleid = $this->module_info['module_id'];
            $check_auth = 1;
        } else {
            $this->moduleid = $this->module_info['module_id'];
            $check_auth = $this->user->checkAuth($this->moduleid);
        }
        $addons_sign = $this->module_info ? $this->module_info['addons_sign'] : 0;
        if ($addons_sign) {
            $addons = new SysAddonsModel();
            $up_status = $addons->getInfo(['id' => $addons_sign], 'up_status')['up_status'];
            if ($up_status == 2) {
                $check_auth = 0;
            }
        }
        if ($check_auth) {
            $web_info = $this->website->getWebSiteInfo();
            if ($web_info['status']) {
                echo json_encode(['code' => -1, 'message' => '当前用户没有操作权限'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($web_info['shop_validity_time'] && (strtotime($web_info['shop_validity_time'])) <= time()) {
                echo json_encode(['code' => -1, 'message' => '商城已过期'], JSON_UNESCAPED_UNICODE);
                exit;
            }
        } else {
            echo json_encode(AjaxReturn(NO_AITHORITY), JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /**
     * 添加操作日志（通过方法参数添加，当前考虑所有操作），
     */
    public function addUserLogByParam($operation, $target = '')
    {
        $this->user->addUserLog($this->uid, 1, $this->controller, $this->action, \think\Request::instance()->ip(), $operation . ':' . $target, $operation);
    }

    public function getOperationTips($tips)
    {
        $tips_array = array();
        if (!empty($tips)) {
            $tips_array = explode("///", $tips);
        }
        $this->assign("tips", $tips_array);
    }

}
