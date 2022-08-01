<?php

namespace addons;

use addons\poster\model\PosterModel;
use addons\taskcenter\model\VslGeneralPosterModel;
use data\model\MerchantVersionModel;
use think\Config;
use think\Request;
use think\Session as Session;
use think\View;
use think\Db;
use data\service\AdminUser as User;
use data\service\Addons as Addon;
use data\service\WebSite as WebSite;
use data\service\Weixin;

/**
 * 插件基类
 * Class Addons
 * @author Byron Sampson <xiaobo.sun@qq.com>
 * @package think\addons
 */
abstract class Addons extends \think\Controller
{
    /**
     * 视图实例对象
     * @var view
     * @access protected
     */
    protected $view = null;
    // 当前错误信息
    protected $error;
    /**
     * $info = [
     *  'name'          => 'Test',
     *  'title'         => '测试插件',
     *  'description'   => '用于thinkphp5的插件扩展演示',
     *  'status'        => 1,
     *  'author'        => 'byron sampson',
     *  'version'       => '0.1'
     * ]
     */
    public $info = [];
    public $addons_path = '';
    public $config_file = '';
    public $website_id;
    public $instance_id;
    public $supplier_id;
    public $uid;
    public $user = null;
    public $module;
    public $merchant_status;
    public $merchant_expire;
    public $action;
    public $http;
    public $realm_ip;
    protected $controller = null;

    /**
     * 架构函数
     * @access public
     */
    public function __construct()
    {
        // 获取当前插件目录
        $this->addons_path = ADDON_PATH . $this->getName() . DS;
        $this->user = new User();
        $this->website_id = $this->user->getSessionWebsiteId();
        $this->uid = $this->user->getSessionUid() ? : getUserId();
        $this->module = \think\Request::instance()->module();
        $this->instance_id = $this->user->getSessionInstanceId()?:(int)Session::get($this->module . 'instance_id');
        $this->supplier_id = $this->user->getSessionSupplierId()?:(int)Session::get($this->module.'supplier_id');
        $WebSite = new WebSite;
        $web_info = $WebSite->getWebSiteInfo($this->website_id);
        $this->controller = \think\Request::instance()->controller();
        $is_ssl = \think\Request::instance()->isSsl();
        $this->http = "http://";
        if($is_ssl){
            $this->http = 'https://';
        }
        if($web_info['realm_ip']){
            $this->realm_ip = $this->http.$web_info['realm_ip'];
        }else{
            $this->realm_ip = $this->http.$_SERVER['HTTP_HOST'];
        }
        if($this->module !== 'wapapi'){
            $merchant = new MerchantVersionModel();
            $merchant_is_default = $merchant->getInfo(['merchant_versionid'=>$web_info['merchant_versionid'],'is_default'=>1],'*');
            if ($merchant_is_default) {
                $this->merchant_status = 1;
            }
            if((strtotime($web_info['shop_validity_time'])) <= time() && $web_info['shop_validity_time'] > 0){
                $this->merchant_expire = 1;
            }
        
            $addons = request()->get('addons');

            if ($this->module == 'platform' && $addons == $this->info['config_hook']) {
                $addonsService = new Addon();
                $addonsService->addAddonsClicks($addons);//b端增加应用访问记录
            }
            if ($this->module == 'admin' && $addons == $this->info['config_admin_hook']) {
                $addonsService = new Addon();
                $addonsService->addAddonsClicks($addons, 'admin');//c端增加应用访问记录
            }
        }
        //TODO... 供应商应用

        // 读取当前插件配置信息   判断是否有下级插件列表
        if ($this->info['has_addonslist'] == 1) {
            if (is_file($this->addons_path . 'core/config.php')) {
                $this->config_file = $this->addons_path . 'core/config.php';
            }
        } else {
            if (is_file($this->addons_path . 'config.php')) {
                $this->config_file = $this->addons_path . 'config.php';
            }
        }
        // 初始化视图模型
        $config = ['view_path' => $this->addons_path];

        $config = array_merge(Config::get('template'), $config);

        $this->view = new View($config, Config::get('view_replace_str'));
        // 控制器初始化
        if ($this->module !== 'wapapi' && method_exists($this, '_initialize')) {
            $this->_initialize();
            $this->controller = \think\Request::instance()->controller();
            if ($this->controller == 'Menu') {
                $this->controller = 'addonslist';
                $this->action = request()->param('addons');
            }

            $this->website = new WebSite();
            $this->module_info = $this->website->getModuleIdByModule($this->controller, $this->action);
            $this->assign('second_menu', $this->module_info);
        }
        $this->user = new User();
    }

    public function returnJson($data)
    {
        ob_clean();
        $result = json_encode($data);
        header('Content-Type:application/json');
        echo $result;
        exit;
    }

    //对象转数组
    function object2array(&$object)
    {
        $object = json_decode(json_encode($object), true);
        return $object;
    }

    /**
     * 获取插件的配置数组
     * @param string $name 可选模块名
     * @return array|mixed|null
     */
    final public function getOneConfig($name = '')
    {
        static $_config = array();
        if (empty($name)) {
            $name = $this->getName();
        }
        if (isset($_config[$name])) {
            return $_config[$name];
        }
        $config = [];
        if (is_file($this->config_file)) {
            $temp_arr = include $this->config_file;
            foreach ($temp_arr as $key => $value) {
                $config[$key] = $value;
//                 if ($value['type'] == 'group') {
//                     foreach ($value['options'] as $gkey => $gvalue) {
//                         foreach ($gvalue['options'] as $ikey => $ivalue) {
//                             $config[$ikey] = $ivalue['value'];
//                         }
//                     }
//                 } else {
//                     $config[$key] = $temp_arr[$key]['value'];
//                 }
            }
            unset($temp_arr);
        }
        $_config[$name] = $config;
        return $config;
    }

    /**
     * 获取插件的配置数组
     * @param string $name 可选模块名
     * @return array|mixed|null
     */
    final public function getAllConfig($name = '')
    {
        static $_config = array();
        if (empty($name)) {
            $name = $this->getName();
        }
        if (isset($_config[$name])) {
            return $_config[$name];
        }
        $config = [];

        $handler = opendir($this->addons_path);
        while (($filename = readdir($handler)) !== false) {//务必使用!==，防止目录下出现类似文件名“0”等情况
            if ($filename != "." && $filename != ".." && $filename != "core") {
                if (is_file($this->addons_path . '/' . $filename . '/config.php')) {
                    $temp_arr = include $this->addons_path . '/' . $filename . '/config.php';
                    $config[] = $temp_arr;
                }
            }
        }
        closedir($handler);
        return $config;
    }

    /**
     * 获取当前模块名
     * @return string
     */
    final public function getName()
    {
        $data = explode('\\', get_class($this));
        return strtolower(array_pop($data));
    }

    /**
     * 检查配置信息是否完整
     * @return bool
     */
    final public function checkInfo()
    {
        $info_check_keys = ['name', 'title', 'description', 'status', 'author', 'version'];
        foreach ($info_check_keys as $value) {
            if (!array_key_exists($value, $this->info)) {
                return false;
            }
        }
        return true;
    }

    /**
     * 加载模板和页面输出 可以返回输出内容
     * @access public
     * @param string $template 模板文件名或者内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     * @throws \Exception
     */
    public function fetch($template = '', $vars = [], $replace = [], $config = [])
    {
        if (!is_file($template)) {
            $template = '/' . $template;
        }
        // 关闭模板布局
        $this->view->engine->layout(false);
        echo $this->view->fetch($template, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @param array $replace 替换内容
     * @param array $config 模板参数
     * @return mixed
     */
    public function display($content = '', $vars = [], $replace = [], $config = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);
        echo $this->view->display($content, $vars, $replace, $config);
    }

    /**
     * 渲染内容输出
     * @access public
     * @param string $content 内容
     * @param array $vars 模板输出变量
     * @return mixed
     */
    public function show($content, $vars = [])
    {
        // 关闭模板布局
        $this->view->engine->layout(false);
        echo $this->view->fetch($content, $vars, [], [], true);
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param mixed $name 要显示的模板变量
     * @param mixed $value 变量的值
     * @return void
     */
    public function assign($name, $value = '')
    {
        $this->view->assign($name, $value);
    }

    /**
     * 获取当前错误信息
     * @return mixed
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * 添加操作日志（当前考虑所有操作），
     */
    public function addUserLog($operation, $target)
    {
        $this->user->addUserLog($this->uid, 1, $this->controller, $this->action, \think\Request::instance()->ip(), $operation . ':' . $target, $operation);
    }
    /*
     * 清除海报缓存，并删掉微信素材中的图片
     * **/
    public function deletePoster()
    {
        $website_id = $this->website_id;
        $weixin_service = new Weixin();
        $weixin_service->deletePoster($website_id);
        return ajaxReturn(1);
    }
    /*
     * 微信关键词去重
     * **/
    public function isRepeatKeyword()
    {
        $key_words = $_REQUEST['keyword']?:'';
        $general_poster_id = request()->post('general_poster_id',0);
        $poster_id = request()->post('poster_id',0);
        $general_poster_condition['back_keywords'] = $key_words;
        $general_poster_condition['website_id'] = $this->website_id;
        if($general_poster_id){
            $general_poster_condition['general_poster_id'] = ['neq',$general_poster_id];
        }
        $poster_condition['key'] = $key_words;
        $poster_condition['website_id'] = $this->website_id;
//        var_dump($key_words);exit;
        if($poster_id){
            $poster_condition['poster_id'] = ['neq',$poster_id];
        }
        $p_id = $poster_id ? $poster_id : $general_poster_id;
        if (empty($key_words)) {
            return  ['code'=>0, 'message'=>''];
        }
        // 全部匹配
        $key1 = ';'.$key_words;
        $key2 = ';'.$key_words.';';
        $key3 = $key_words.';';
        $info = Db::table('sys_weixin_key_replay')
            ->where('key', ['=', $key_words], ['like', '%'.$key1],['like', '%'.$key2.'%'],['like', $key3.'%'], 'or')
            ->where('website_id', ['=', $this->website_id], 'and')
            ->where('match_type', ['=', 2], 'and')
            ->where('poster_id',['neq', $p_id], 'and')
            ->find();
        if (empty($info)) {
            // 模糊匹配
            $info = Db::table('sys_weixin_key_replay')
                ->where('key', ['like', '%'.$key_words.'%'], ['like', '%'.$key1.'%'],['like', '%'.$key2.'%'],['like', '%'.$key3.'%'], 'or')
                ->where('website_id', ['=', $this->website_id], 'and')
                ->where('match_type', ['=', 1], 'and')
                ->where('poster_id',['neq', $p_id], 'and')
                ->find();
        }
        $taskcenter = getAddons('taskcenter', $this->website_id);
        if($taskcenter){
            $general_poster_mdl = new VslGeneralPosterModel();
            $general_poster_data = $general_poster_mdl->getInfo($general_poster_condition,'*');
        }

        $poster = getAddons('poster', $this->website_id);
        if($poster){
            $poster_mdl = new PosterModel();
            $poster_data = $poster_mdl->getInfo($poster_condition,'*');
        }

        if($info || $general_poster_data || $poster_data){
            return  ['code'=>-1, 'message'=>'回复关键词已存在'];
        }else{
            return  ['code'=>0, 'message'=>''];
        }
    }

    /**
     * 砸金蛋、刮刮乐等等活动的开启与关闭
     * @param $promotion_key
     * @param $promotion_id
     * @param $type
     * @param $delay_time
     */
    public function smallPromotionOpenOrClose($promotion_key, $promotion_id, $type, $delay_time)
    {
        if(config('is_high_powered')){
            //订单完成延时队列
            $config['delay_exchange_name'] = config('rabbit_delay_small_promotion.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_small_promotion.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_small_promotion.delay_routing_key');
            //查询出订单配置过期自动关闭时间
            $delay_time = $delay_time * 1000;//毫秒
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'type' => $type,
                ''. $promotion_key .'' => $promotion_id,
            ];
            $data = json_encode($data);
            $url = config('rabbit_interface_url.url');
            $back_url = $url.'/rabbitTask/smallPromotionOpenOrClose';
            $custom_type = 'small_promotion';
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    //必须实现安装
    abstract public function install();

    //必须卸载插件方法
    abstract public function uninstall();
}