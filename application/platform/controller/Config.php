<?php

namespace app\platform\controller;

use addons\miniprogram\service\MiniProgram as MiniProgramSer;
use addons\store\server\Store;
use data\model\WebSiteModel;
use data\service\Address as DataAddress;
use data\service\Config as WebConfig;
use data\service\Goods as Goods;
use data\extend\custom\Common;
use data\service\Order as OrderService;
use data\model\MessageCountModel;
use data\model\SysAddonsModel;
use data\model\VslBankModel;
use addons\helpcenter\model\VslQuestionModel;
use data\service\User as UserSer;
use data\service\Upload\AliOss;

/**
 * 网站设置模块控制器
 *
 * @author  www.vslai.com
 *
 */
class Config extends BaseController {

    public $backup_path = "runtime/dbsql/";
    private $dir;
    private $dirDefault;
    private $dir_common; //公共部分路径
    private $dir_shop_common; //店铺公共部分路径
    private $com;
    protected $realm_ip;
    protected $realm_two_ip;
    public function __construct() {
        parent::__construct();
        $this->dir = ROOT_PATH . 'public/static/custompc/data/web_' . $this->website_id . '/shop_' . $this->instance_id;
        $this->dirDefault = ROOT_PATH . 'public/static/custompc/data/default/tem';
        $this->dir_shop_common = ROOT_PATH . 'public/static/custompc/data/web_' . $this->website_id . '/shop_' . $this->instance_id . '/common';
        $this->dir_common = ROOT_PATH . 'public/static/custompc/data/web_' . $this->website_id . '/common';
        $this->com = new Common($this->instance_id, $this->website_id);
        $this->assign('realm_pay_callback',$this->http.$this->realm_ip.'/wapapi/member/wchatcallback');
        $web_info = $this->website->getWebSiteInfo();
        $this->realm_ip = $web_info['realm_ip'];
        $this->assign('realm_pay_callback',$this->http.$this->realm_ip.'/wapapi/member/wchatcallback');
        $this->assign('realm_ip',$this->realm_ip);
        $call_payback_url1 = $this->realm_ip . '/wap/pay/';
        $this->assign("call_payback_url1", $call_payback_url1);
        $hasHelpcenter = getAddons('helpcenter', $this->website_id);//帮助中心是否存在
        $this->assign('hasHelpcenter',$hasHelpcenter);
        $this->assign('alipay_callback',$this->http.$this->realm_ip.'/wapapi/pay/aliUrlBack');
        $this->assign('apply_back_realm',$this->realm_ip.'/wapapi/login/callback');
        $this->assign('apply_realm',$this->realm_ip);
        $real_ip = $this->http.$this->realm_ip;
        $this->assign('real_ip',$real_ip);
        $this->assign('http',$this->http);
    }

    //银行列表
    public function getBankList()
    {
        $bank = new VslBankModel();
        $bank_list = $bank->getQuery([], 'bank_short_name,bank_iocn', 'sort asc');
        if (empty($bank_list))
        {
            $resBank = $bank->setBankList();
            if ($resBank)
            {
                $bank_list = $bank->getQuery([], 'bank_short_name,bank_iocn', 'sort asc');
            }
        }
        $data['data'] = $bank_list;
        $data['code'] = 1;
        return json($data);
    }

    /**
     * 商城配置
     */
    public function sysConfig() {
        if (request()->isAjax()) {
            $config = new WebConfig();
            $config_type = request()->post('config_type', ''); // 设置类型
            $pay_type = request()->post('pay_type', ''); // 设置类型
            if ($config_type == 'basic' || $config_type == 'realm') {
                $websiteModel = new WebSiteModel();
                $list = $this->website->getWebSiteInfo();
                $list['realm'] = $config->getConfig(0, 'REALMIP', $this->website_id, 1);
                $helpCenter = getAddons('helpcenter', $this->website_id);
                if($helpCenter){
                    $article = new VslQuestionModel();
                    $list['pur_title'] = $article->getInfo(['question_id' => $list['pur_id']], 'title')['title'];
                    $list['reg_title'] = $article->getInfo(['question_id' => $list['reg_id']], 'title')['title'];
                }else{
                    $list['pur_title'] = '';
                    $list['reg_title'] = '';
                }
                $list['realm_ips'] = dealArray($websiteModel->Query([],'realm_two_ip'), true, false, '');
            }
            if ($config_type == 'copystyle') {
                $list = $config->getConfig($this->instance_id,'COPYSTYLE', $this->website_id, 1);
            }
            if ($config_type == 'redis') {
                $list = $config->getConfigMaster(0,'REDIS', 0, 1);
            }
            if ($config_type == 'wechat') {
                $list = $config->getConfig($this->instance_id,'WECHATOPEN', $this->website_id, 1);
            }
            if ($config_type == 'validate') {
                $list = $config->getConfig($this->instance_id,'LOGINVERIFYCODE', $this->website_id, 1);
            }
            if ($config_type == 'message') {
                $list['template_type_list'] = $config->getTemplateType();
                $list['email_message'] = $config->getConfig($this->instance_id,'EMAILMESSAGE', $this->website_id);
                $list['mobile_message'] = $config->getConfig($this->instance_id,'MOBILEMESSAGE', $this->website_id);
                $messageCount = new MessageCountModel();
                $list['count'] = (int)$messageCount->getInfo(['website_id' => $this->website_id],'count')['count'];
                //订单通知手机
                $userSer = new UserSer();
                $order_mobile = $userSer->getWebsiteOrShopOrderMobile($this->website_id,0);
                $list['mobile_message']['value']['order_mobile'] = $order_mobile;
            }
            if ($config_type == 'payment') {
                //                $list['pay_list'] = $config->getPayConfig($this->instance_id);
                $list['pay_list'] = $config->getAllPayConfig($this->instance_id);
                $list['b_set'] = $config->getConfig(0, 'BPAY', $this->website_id);
                $list['p_set'] = $config->getConfig(0, 'PPAY', $this->website_id);
                $list['d_set'] = $config->getConfig(0, 'DPAY', $this->website_id);
                $list['wx_set'] = $config->getConfig(0, 'WPAY', $this->website_id);
                $list['ali_set'] = $config->getConfig(0, 'ALIPAY', $this->website_id);
                $list['tl_set'] = $config->getConfig($this->instance_id, 'TLPAY', $this->website_id);
                $list['eth_set'] = $config->getConfig($this->instance_id, 'ETHPAY', $this->website_id);
                $list['eos_set'] = $config->getConfig($this->instance_id, 'EOSPAY', $this->website_id);
                $list['glopay_set'] = $config->getConfig(0, 'GLOPAY', $this->website_id);
                // add 汇聚支付  -- 该方法好像已经弃用 -- 
                $list['joinpay_set'] = $config->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                $list['offline_set'] = $config->getConfig(0, 'OFFLINEPAY', $this->website_id);
            }
            if($config_type == 'tradeset'){
                $list= $config->getShopConfig(0,$this->website_id);
               
            }
            if($config_type == 'returnsetting'){
                $page_index = request()->post('page_index', 1);
                $page_size = request()->post('page_size') ?: PAGESIZE;
                $order_service = new OrderService();
                $list= $order_service->getShopReturnList(0, $this->website_id,$page_index,$page_size);
                return json($list);
            }
            if($config_type == 'withdrawalset'){
                $config_service = new WebConfig();
                $list = $config_service->getConfig(0, 'WITHDRAW_BALANCE');
            }
            if($config_type == 'storageconfig'){
                $web_config = new WebConfig();
                $upload_type = $web_config->getUploadType();
                $list["type"] = $upload_type;
                // 获取七牛参数
                $config_alioss_info = $web_config->getConfigMaster(0, 'ALIOSS_CONFIG', 0, 1);
                $config_alioss_info = json_decode($config_alioss_info, true);
                $list["data"]["alioss"] = $config_alioss_info;
                
                $alioss_data = $config_alioss_info;
                
                $aliOss = new AliOss();
                
                $buckets = $aliOss->attachment_alioss_buctkets($alioss_data['Accesskey'], $alioss_data['Secretkey']);
                
                $list["buckets"] = [];
                if(is_array($buckets)){
                    foreach($buckets as $val){
                        $list["buckets"][] = $val;
                    }
                }
                $list["bucket_datacenter"] = array(
                    'oss-cn-hangzhou' => '杭州数据中心',
                    'oss-cn-qingdao' => '青岛数据中心',
                    'oss-cn-beijing' => '北京数据中心',
                    'oss-cn-hongkong' => '香港数据中心',
                    'oss-cn-shenzhen' => '深圳数据中心',
                    'oss-cn-shanghai' => '上海数据中心',
                    'oss-us-west-1' => '美国硅谷数据中心',
                );
            }
            return $list;
        }
        $config_type = request()->get('type', 'basic'); // 设置类型
        $this->assign('config_type',$config_type);
        $config_service = new WebConfig();
        $master_array = $config_service->getConfigMaster(0, 'MOBILEMESSAGE', 0, 1);
        $jd_sms = 1;//判断京东万象是否配置
        if(!$master_array['userid'] || !$master_array['username'] || !$master_array['password']){
            $jd_sms = 0;
        }
        //是否有小程序，有的话才需要配置微信开放平台
        $miniprogram = getAddons('miniprogram', $this->website_id);
        $this->assign('jd_sms',$jd_sms);
        $this->assign('miniprogram',$miniprogram);
        $wchat_config = $config_service->getConfig($this->instance_id, 'SHOPWCHAT', $this->website_id);
        $appid = $wchat_config["value"]['appid'];
        $this->assign('appid',$appid);
        $blockchain = getAddons('blockchain',$this->website_id);
        $this->assign('blockchain',(int)$blockchain);
        //门店
        if(getAddons('store',$this->website_id)) {
            $store_server = new Store();
            $store = $store_server->getStoreSet(0)['is_use'];
        }else{
            $store = 0;
        }
        $tab_type = request()->get('act', '');//标签类型
        $this->assign('tab_type', $tab_type);
        $this->assign('store',$store);
        return view($this->style . "Config/shopConfig");
    }

    public function payConfigHtml()
    {
        $web_info = $this->website->getWebSiteInfo();
        $this->realm_ip = $web_info['realm_ip'];
        $this->assign('realm_ip',$this->realm_ip);
        $call_payback_url1 = $this->realm_ip . '/wap/pay/';
        $this->assign("call_payback_url1", $call_payback_url1);
        $hasHelpcenter = getAddons('helpcenter', $this->website_id);//帮助中心是否存在
        $this->assign('hasHelpcenter',$hasHelpcenter);
        if($web_info['realm_two_ip']){
            $ip = top_domain($_SERVER['HTTP_HOST']);
            $web_info['realm_two_ip'] = $web_info['realm_two_ip'].'.'.$ip;
            $this->realm_two_ip = $web_info['realm_two_ip'];
            $call_payback_url2 = $this->realm_two_ip . '/wap/pay/';
            $this->assign("call_payback_url2", $call_payback_url2);
            $this->assign('realm_two_ip',$this->realm_two_ip);
            $this->assign('realm_two_pay_callback',$this->http.$this->realm_two_ip.'/wapapi/member/wchatcallback');
            $this->assign('top_ip',$ip);
            if($this->realm_ip){
                $this->assign('alipay_callback',$this->http.$this->realm_ip.'/wapapi/pay/aliUrlBack');
                $this->assign('apply_back_realm',$this->realm_ip.'/wapapi/login/callback');
                $this->assign('apply_realm',$this->realm_ip);
            }else{
                $this->assign('alipay_callback',$this->http.$this->realm_two_ip.'/wapapi/pay/aliUrlBack');
                $this->assign('apply_back_realm',$this->realm_two_ip.'/wapapi/login/callback');
                $this->assign('apply_realm',$this->realm_two_ip);
            }
        }
        $pay_type = request()->get("pay_type", 0);
        
        //小程序路由已经兼容微商来和店大师（addons_url_platform、addons_url_admin）
        $this->assign('payWxConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payWxConfigMir')));
        $this->assign('payBConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payBConfigMir')));
        $this->assign('payPConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payPConfigMir')));
        $this->assign('payDConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payDConfigMir')));
        $this->assign('payGpConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payGpConfigMir')));
        $this->assign('payTLConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payTLConfigMir')));
        //其他路由
        $this->assign('payWxConfigUrl', __URL(addons_url_platform('appshop://appshop/payWxConfig')));
        $this->assign('payBConfigUrl', __URL(addons_url_platform('appshop://appshop/payBConfig')));
        $this->assign('payDConfigUrl', __URL(addons_url_platform('appshop://appshop/payDConfig')));
        $this->assign('payAliConfigUrl', __URL(addons_url_platform('appshop://appshop/payAliConfig')));
        $this->assign('payTLConfigUrl', __URL(addons_url_platform('appshop://appshop/payTLConfig')));
        $this->assign('eosPayConfigUrl', __URL(addons_url_platform('appshop://appshop/eosPayConfig')));
        $this->assign('ethPayConfigUrl', __URL(addons_url_platform('appshop://appshop/ethPayConfig')));
        $this->assign('pay_type', $pay_type);

        //判断是否有聚合支付应用
        if(getAddons('thirdpay',$this->website_id)) {
            $this->assign('thirdpay_status', 1);
        }else{
            $addons = new SysAddonsModel();
            $thirdpay_info = $addons->getInfo(['name' => 'thirdpay'],'id,up_status,is_value_add');
            $this->assign('thirdpay_addons_id', $thirdpay_info['id']);
            $this->assign('thirdpay_up_status', $thirdpay_info['up_status']);
            $this->assign('thirdpay_is_value_add', $thirdpay_info['is_value_add']);
            $this->assign('thirdpay_status', 0);
        }
        $config_service = new WebConfig();
        
        # WAP端
        //微信配置
        $wx_set = $config_service->getConfig(0, 'WPAY', $this->website_id, 1);
        $this->assign("wx_wap",$wx_set);
        //支付宝配置
        $ali_wap = $config_service->getConfig(0, 'ALIPAY', $this->website_id, 1);
        $this->assign("ali_wap",$ali_wap);
        //GlobePay配置
        $glp_wap = $config_service->getConfig(0, 'GLOPAY', $this->website_id, 1);
        $this->assign("glp_wap",$glp_wap);
        //通联配置
        $tl_wap = $config_service->getConfig($this->instance_id, 'TLPAY', $this->website_id);['value'];
        $this->assign("tl_wap",$tl_wap);
        
        #小程序端
        if (getAddons('miniprogram', $this->website_id)) {
            //微信配置
            $mp_info = $config_service->getConfig(0, 'MPPAY', $this->website_id, 1);
            if (!$mp_info['appid']) {
                $mpSer = new MiniProgramSer();
                $mp_condition = [
                    'shop_id'   => $this->instance_id,
                    'website_id'=> $this->website_id,
                ];
                $mini_program_info = $mpSer->miniProgramInfo($mp_condition);
                $mp_info['appid'] = $mini_program_info['authorizer_appid'];
            }
            $mp_info['mch_id'] = $mp_info['mch_id'] ?:$mp_info['mchid'];//注意这里用mch_id（原本小程序是mchid）
            $this->assign("wx_mp",$mp_info);
            //GlobePay配置
            $glp_mp = $config_service->getConfig(0, 'GPPAY', $this->website_id, 1);
            $this->assign("glp_mp",$glp_mp);
            //通联配置
            $tl_mp = $config_service->getConfig($this->instance_id, 'TLPAYMP', $this->website_id, 1);
            $this->assign("tl_mp",$tl_mp);
        }
        # APP端
        if (getAddons('appshop', $this->website_id)) {
            //微信配置
            $app_wx_info = $config_service->getConfig(0, 'WPAYAPP', $this->website_id, 1);
            if (!$app_wx_info['appid']){
                //获取公众号的
                $wx_config = $config_service->getConfig($this->instance_id, 'SHOPWCHAT', $this->website_id, 1);
                $app_wx_info['appid'] = $wx_config['appid'];
                $app_wx_info['mch_key'] = $wx_config['encodingAESKey'];
            }
            $this->assign("wx_app",$app_wx_info);
            //支付宝配置
            $ali_app = $config_service->getConfig(0, 'ALIPAYAPP', $this->website_id, 1);
            $this->assign("ali_app",$ali_app);
            //通联配置
            $tl_app = $config_service->getConfig($this->instance_id, 'TLPAYAPP', $this->website_id, 1);
            $this->assign("tl_app",$tl_app);
        }

        $blockchain = getAddons('blockchain', $this->website_id);
        $this->assign("blockchain",(int)$blockchain);
        return view($this->style . 'Config/payConfig');
    }

    public function getPayList()
    {
        $pay_type = request()->post("pay_type", 0);
        $config = new WebConfig();
        $shop_id = $this->instance_id;
        switch($pay_type){
            case '1'://wap端(WAP、PC和微信支付)支付方式
                $list['pay_list'] = $config->getPayConfig($shop_id);
                $list['b_set'] = $config->getConfig(0, 'BPAY', $this->website_id);
                $list['p_set'] = $config->getConfig(0, 'PPAY', $this->website_id);
                $list['d_set'] = $config->getConfig(0, 'DPAY', $this->website_id);
                $list['wx_set'] = $config->getConfig(0, 'WPAY', $this->website_id);
                $list['ali_set'] = $config->getConfig(0, 'ALIPAY', $this->website_id);
                $list['tl_set'] = $config->getConfig($this->instance_id, 'TLPAY', $this->website_id);
                $list['eth_set'] = $config->getConfig($this->instance_id, 'ETHPAY', $this->website_id);
                $list['eos_set'] = $config->getConfig($this->instance_id, 'EOSPAY', $this->website_id);
                $list['gp_set'] = $config->getConfig(0, 'GLOPAY', $this->website_id);
                $list['offline_set'] = $config->getConfig(0, 'OFFLINEPAY', $this->website_id);
                $list['joinpay_set'] = $config->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                break;
            case '2'://小程序支付方式
                $list['pay_list'] = $config->getPayConfigMir($shop_id);
                $list['b_set'] = $config->getConfig(0, 'BPAYMP', $this->website_id);
                $list['p_set'] = $config->getConfig(0, 'PPAYMP', $this->website_id);
                $list['d_set'] = $config->getConfig(0, 'DPAYMP', $this->website_id);
                $list['wx_set'] = $config->getConfig(0, 'MPPAY', $this->website_id);
                $list['gp_set'] = $config->getConfig(0, 'GPPAY', $this->website_id);
                $list['tl_set'] = $config->getConfig($this->instance_id, 'TLPAYMP', $this->website_id);
		        $list['offline_set'] = $config->getConfig(0, 'OFFLINEPAYMP', $this->website_id);
                $list['joinpay_set'] = $config->getConfig($this->instance_id, 'MPJOINPAY', $this->website_id);
                break;
            case '3'://app支付方式
                $list['pay_list'] = $config->getAppPayConfig($this->instance_id);
                $list['b_set'] = $config->getConfig(0, 'BPAYAPP', $this->website_id);
                $list['d_set'] = $config->getConfig(0, 'DPAYAPP', $this->website_id);
                $list['wx_set'] = $config->getConfig(0, 'WPAYAPP', $this->website_id);
                $list['ali_set'] = $config->getConfig(0, 'ALIPAYAPP', $this->website_id);
                $list['tl_set'] = $config->getConfig($this->instance_id, 'TLPAYAPP', $this->website_id);
                $list['eth_set'] = $config->getConfig($this->instance_id, 'ETHPAYAPP', $this->website_id);
                $list['eos_set'] = $config->getConfig($this->instance_id, 'EOSPAYAPP', $this->website_id);
		$list['offline_set'] = $config->getOfflinePayConfig($this->website_id,'OFFLINEPAYAPP');
		        $list['joinpay_set'] = $config->getConfig(0, 'JOINPAYAPP', $this->website_id);
                break;
        }
        return $list;
    }
    /*
      * 配置物流查询
      * * */
   public function setCompany(){
       $Config = new WebConfig();
       if (request()->isPost()) {
           $keyValue = request()->post("keyValue", '');
           $array = array(
                'instance_id' => 0,
                'website_id' => $this->website_id,
                'key' => 'COMPANYCONFIGSET',
                'value' => $keyValue,
                'desc' => '物流配置',
                'is_use' => 1
            );
            $retval = $Config->setConfigOne($array);
           return AjaxReturn($retval);
       }
       $config_service = new WebConfig();
       $value = $config_service->getConfig(0, 'COMPANYCONFIGSET', $this->website_id, 1);
       return $value;
   }
    /*
     * 更改模板是否开启短信、邮箱验证
     * * */
    public function updateNoticeTemplateEnable() {
        $config = new WebConfig();
        $template_code = request()->post('template_code', '');
        $template_type = request()->post('model', '');
        $is_enable = request()->post('is_enable', 0);
        $website_id = $this->website_id;
        $instance_id = $this->instance_id;
        $condition = [
            'template_code' => $template_code,
            'template_type' => $template_type,
            'website_id' => $website_id,
            'instance_id' => $instance_id,
        ];
        $bool = $config->updateNoticeTemplateEnable($condition, $is_enable);
        if ($bool) {
            $this->addUserLogByParam('更改模板是否开启短信、邮箱验证', '模板类型：' . $template_type . '，模板名称：' . $template_code);
        }
        return AjaxReturn($bool);
    }

    /*
     * 更改配置的支付方式、第三方登录方式是否启用
     * * */
    public function updateConfigIsuse() {
        $config = new WebConfig();
        $id = request()->post('id', 0);
        $is_use = request()->post('is_use', 0);
        $condition = array(
            'instance_id' => $this->instance_id,
            'website_id' => $this->website_id,
            'id' => $id
        );
        $bool = $config->updateConfigIsuse($condition, $is_use);
        if ($bool) {
            $this->addUserLogByParam('更改配置的支付方式、第三方登录方式是否启用', '支付方式或者第三方登录方式id：' . $id);
        }
        return AjaxReturn($bool);
    }

    /**
     * 基本设置
     */
    public function webConfig() {
        if (request()->isAjax()) {
            // 网站设置
            $data = array();
            $data['wap_status'] = request()->post("wap_status", 0); // 手机端网站运营状态
            $data['wap_register_adv'] = request()->post("wap_register_adv", ''); // 手机端注册广告图
            $data['wap_register_jump'] = request()->post("wap_register_jump", ''); // 手机端注册广告图跳转
            $data['wap_login_adv'] = request()->post("wap_login_adv", ''); // 手机端登陆广告图
            $data['wap_login_jump'] = request()->post("wap_login_jump", ''); // 手机端登陆广告图跳转
            $data['wap_pop'] = request()->post("wap_pop", '0'); // 手机端首页弹窗广告
            $data['wap_pop_adv'] = request()->post("wap_pop_adv", ''); // 手机端首页弹窗广告图
            $data['wap_pop_jump'] = request()->post("wap_pop_jump", ''); // 手机端首页弹窗广告跳转
            $data['wap_pop_rule'] = request()->post("wap_pop_rule", ''); // 手机端弹窗规则
            $data['pur_id'] = request()->post("pur_id", '0'); // 用户购买协议
            $data['reg_id'] = request()->post("reg_id", '0'); // 用户注册协议
            $data['reg_rule'] = request()->post("reg_rule", '0'); // 用户购买协议
            $data['pur_rule'] = request()->post("pur_rule", '0'); // 用户注册协议
            $data['close_reason'] = request()->post("close_reason", '0'); // 关闭原因
            $data['modify_time'] = time();
            $data['mall_name'] = request()->post("mall_name", ''); // 商城名称
            $data['default_url'] = $this->http.$_SERVER['HTTP_HOST']; // 后台url
            $data['logo'] = request()->post("logo", ''); // 商城logo
            $retval = $this->website->updateWebSite($data);
            $this->codeConfig();
            $Config = new WebConfig();
            $styleConfig = $Config->getConfig(0, 'COPYSTYLE', $this->website_id);
            if(!$styleConfig){
                $Config->setStyleConfig('积分','余额');
            }
            if ($retval) {
                $this->addUserLogByParam('系统商城基本设置保存', $retval);
            }
            return AjaxReturn($retval);
        }
    }

    /**
     * 文案样式设置
     */
    public function styleConfig() {
        $Config = new WebConfig();
        $point_style = request()->post("point_style", '积分');
        $balance_style = request()->post("balance_style", '余额');
        $retval = $Config->setStyleConfig($point_style, $balance_style);
        return AjaxReturn($retval);
    }
    /**
     * redis设置
     */
    public function redisConfig() {
        $host = request()->post("host", '');
        $pass = request()->post("pass", '');
        $webConfig = new WebConfig();
        $param = [
            'instance_id' => 0,
            'website_id' => 0,
            'key' => 'REDIS',
            'value' => ['host' => $host, 'pass' => $pass],
            'desc' => 'redis配置',
            'is_use' => 1,
        ];
        $retval = $webConfig->setConfigOne($param);
        return AjaxReturn($retval);
    }
    /**
     * 微信开放平台设置
     */
    public function wechatOpenConfig() {
        $Config = new WebConfig();
        $open_appid = request()->post("open_appid", '');
        $open_secrect = request()->post("open_secrect", '');
        $open_key = request()->post("open_key", '');
        $open_token = request()->post("open_token", '');
        $retval = $Config->setWechatOpenConfig(0, $open_appid, $open_secrect, $open_key, $open_token);
        return AjaxReturn($retval);
    }


    /**
     * 验证码设置
     */
    public function codeConfig() {
        $webConfig = new WebConfig();
        $param = [
            'instance_id' => 0,
            'website_id' => $this->website_id,
            'key' => 'LOGINVERIFYCODE',
            'value' => ['pc' => 1],
            'is_use' => 1,
        ];
        $webConfig->setConfigOne($param);
    }
    public function selectWapUrl() {
        $config['shop'] = getAddons('shop',$this->website_id,0,true);
        $config['distribution'] = getAddons('distribution',$this->website_id,0,true);
        $config['areabonus'] = getAddons('areabonus',$this->website_id,0,true);
        $config['globalbonus'] = getAddons('globalbonus',$this->website_id,0,true);
        $config['teambonus'] = getAddons('teambonus',$this->website_id,0,true);
        $config['coupontype'] = getAddons('coupontype',$this->website_id,0,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,0,true);
        $config['integral'] = getAddons('integral',$this->website_id,0,true);
        $config['channel'] = getAddons('channel',$this->website_id,0,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,0,true);
        $config['presell'] = getAddons('presell',$this->website_id,0,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,0,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,0,true);
        $config['signin'] = getAddons('signin',$this->website_id,0,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,0,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,0,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,0,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,0,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,0,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,0,true);
        $config['qlkefu'] = getAddons('qlkefu',$this->website_id,0,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,0,true);
        $config['credential'] = getAddons('credential',$this->website_id,0,true);
        $config['thingcircle'] = getAddons('thingcircle',$this->website_id,0,true);
        $config['anticounterfeiting'] = getAddons('anticounterfeiting',$this->website_id,0,true);
        $config['helpcenter'] = getAddons('helpcenter',$this->website_id,0,true);
        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        $this->assign('type', request()->get('template_type'));
        return view($this->style . 'Config/linksDialog');
    }
    public function selectMinUrl() {
        $config['shop'] = getAddons('shop',$this->website_id,0,true);
        $config['distribution'] = getAddons('distribution',$this->website_id,0,true);
        $config['areabonus'] = getAddons('areabonus',$this->website_id,0,true);
        $config['globalbonus'] = getAddons('globalbonus',$this->website_id,0,true);
        $config['teambonus'] = getAddons('teambonus',$this->website_id,0,true);
        $config['coupontype'] = getAddons('coupontype',$this->website_id,0,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,0,true);
        $config['integral'] = getAddons('integral',$this->website_id,0,true);
        $config['channel'] = getAddons('channel',$this->website_id,0,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,0,true);
        $config['presell'] = getAddons('presell',$this->website_id,0,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,0,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,0,true);
        $config['signin'] = getAddons('signin',$this->website_id,0,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,0,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,0,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,0,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,0,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,0,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,0,true);
        $config['qlkefu'] = getAddons('qlkefu',$this->website_id,0,true);
        $config['credential'] = getAddons('credential',$this->website_id,0,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,0,true);
        $config['anticounterfeiting'] = getAddons('anticounterfeiting',$this->website_id,0,true);
        $config['liveshopping'] = getAddons('liveshopping',$this->website_id,0,true);
        $config['thingcircle'] = getAddons('thingcircle',$this->website_id,0,true);
        $config['miniprogram'] = getAddons('miniprogram',$this->website_id,0,true);

        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        if (request()->get('type') == 'mini') {
            $this->assign('type', request()->get('template_type'));
        }
        return view($this->style . 'Config/linksMinDialog');
    }
    public function selectKey() {
        return view($this->style . 'Config/linksKeyDialog');
    }
    /**
     * ajax 邮件接口
     */
    public function setEmailMessage() {
        $value = [];
        $value['email_host'] = request()->post('email_host', '');
        $value['email_port'] = request()->post('email_port', '');
        $value['email_addr'] = request()->post('email_addr', '');
        $value['email_id'] = request()->post('email_id', '');
        $value['email_pass'] = request()->post('email_pass', '');
        $is_use = request()->post('is_use', 0);
        $value['email_is_security'] = request()->post('email_is_security', false);
        $param = [
            'value' => $value,
            'key' => 'EMAILMESSAGE',
            'instance_id' => $this->instance_id,
            'website_id' => $this->website_id,
            'is_use' => $is_use
        ];
        $config = new WebConfig();
        $res = $config->setConfigOne($param);
        return AjaxReturn($res);
    }

    /**
     * ajax 短信接口
     */
    public function setMobileMessage() {
        $data['appKey'] = request()->post('app_key', '');
        $data['secretKey'] = request()->post('secret_key', '');
        $data['freeSignName'] = request()->post('free_sign_name', '');
        $data['user_type'] = request()->post('user_type', 1);
        $data['alarm_mobile'] = request()->post('alarm_mobile', '');
        $data['alarm_num'] = request()->post('alarm_num', '');
        $is_use = request()->post('is_use', '');
        $data['jd_sign_name'] = request()->post('jd_sign_name', '');
        $data['international'] = request()->post('international', 0);
        $data['int_sign_name'] = request()->post('int_sign_name', '');
        $data['order_mobile'] = request()->post('order_mobile');
        $param = [
            'value' => $data,
            'key' => 'MOBILEMESSAGE',
            'instance_id' => $this->instance_id,
            'website_id' => $this->website_id,
            'is_use' => $is_use
        ];
        $config = new WebConfig();
        $res = $config->setConfigOne($param);
        return AjaxReturn($res);
    }

    /**
     * @param int $convert_rate 积分抵扣 定义多少积分等于多少钱，用于退款退货
     * @param int $shopping_back_points 购物返积分节点 1-订单已完成 2-已收货 3-支付完成
     * @param int $point_invoice_tax 购物返积分比例
     * @param int $is_point 购物返积分是否开启 0-未开启 1-开启
     * @param int $integral_calculation 积分计算方式 1-订单总价 2-商品总价 3-实际支付金额
     * @param int $is_translation 是否开启自动评论 1-开启 0关闭
     * @param int $translation_time 自动评论时间
     * @param int $translation_text 自动评论内容
     * 余额转账设置
     * @param int $is_transfer, 1开启余额转账 0不开启
     * @param int $is_transfer_charge, 1开启转账费率
     * @param int $charge_type, 费率类型 1比例 2固定
     * @param int $charge_pares, 费率比例
     * @param int $charge_pares_min, 费率最低
     * @param int $charge_pares2, 费率固定
     * 余额积分转账设置
     * @param int $is_point_transfer, 1开启积分余额转账 0不开启
     * @param int $is_point_transfer_charge, 1开启转账费率
     * @param int $point_charge_type, 费率类型 1比例 2固定
     * @param int $point_charge_pares, 费率比例
     * @param int $point_charge_pares_min, 费率最低
     * @param int $point_charge_pares2,费率固定
     * 交易设置
     */
    public function shopSet() {
        if (request()->isAjax()) {
            $has_express = request()->post("has_express", '');
            $order_auto_delivery = request()->post("order_auto_delivery", '') ? request()->post("order_auto_delivery", '') : 0; //
            $order_delivery_complete_time = request()->post("order_delivery_complete_time", '') ? request()->post("order_delivery_complete_time", '') : 0; //
            $convert_rate = request()->post("convert_rate", '') ? request()->post("convert_rate", '') : ''; //
            $order_buy_close_time = request()->post("order_buy_close_time", '') ? request()->post("order_buy_close_time", '') : 0; //
            $is_point = request()->post("is_point", '0'); //
            $integral_calculation = request()->post("integral_calculation", '') ? request()->post("integral_calculation", '') : 0; //
            $point_invoice_tax = request()->post("point_invoice_tax", '') ? request()->post("point_invoice_tax", '') : 0; //
            $shopping_back_points = request()->post("shopping_back_points", '') ? request()->post("shopping_back_points", '') : 0; //
            $is_point_deduction = request()->post("is_point_deduction", '0'); 
            $point_deduction_calculation = request()->post("point_deduction_calculation", '') ? request()->post("point_deduction_calculation", '') : 0; 
            $point_deduction_max = request()->post("point_deduction_max", '') ? (int)request()->post("point_deduction_max", '') : '';
            $close_pay_password = request()->post("close_pay_password", '');//支付密码开关 1关闭 0开启
            $pay_password_length = request()->post("pay_password_length",0);//支付密码长度0:9位 1::6位
            
            $is_translation = request()->post("is_translation", '') ? request()->post("is_translation", '') : 0; 
            $translation_time = request()->post("translation_time", '') ? request()->post("translation_time", '') : 0; 
            $translation_text = request()->post("translation_text", '') ? request()->post("translation_text", '') : ''; 

            //余额转账设置
            $is_transfer = request()->post("is_transfer", '') ? request()->post("is_transfer", '') : 0; 
            $is_transfer_charge = request()->post("is_transfer_charge", '') ? request()->post("is_transfer_charge", '') : 0; 
            $charge_type = request()->post("charge_type", '') ? request()->post("charge_type", '') : 1; 
            $charge_pares = request()->post("charge_pares", '') ? request()->post("charge_pares", '') : 0; 
            $charge_pares_min = request()->post("charge_pares_min", '') ? request()->post("charge_pares_min", '') : 0; 
            $charge_pares2 = request()->post("charge_pares2", '') ? request()->post("charge_pares2", '') : 0; 
            //积分余额转账设置
            $is_point_transfer = request()->post("is_point_transfer", '') ? request()->post("is_point_transfer", '') : 0; 
            $is_point_transfer_charge = request()->post("is_point_transfer_charge", '') ? request()->post("is_point_transfer_charge", '') : 0; 
            $point_charge_type = request()->post("point_charge_type", '') ? request()->post("point_charge_type", '') : 1; 
            $point_charge_pares = request()->post("point_charge_pares", '') ? request()->post("point_charge_pares", '') : 0; 
            $point_charge_pares_min = request()->post("point_charge_pares_min", '') ? request()->post("point_charge_pares_min", '') : 0; 
            $point_charge_pares2 = request()->post("point_charge_pares2", '') ? request()->post("point_charge_pares2", '') : 0;

            //好评送积分
            $evaluate_give_point = request()->post("evaluate_give_point", '') ? request()->post("evaluate_give_point", '') : 0;
            $point_num = request()->post("point_num", '') ? request()->post("point_num", '') : 0;
            $is_beautiful_point_transfer = request()->post("is_beautiful_point_transfer", '') ? request()->post("is_beautiful_point_transfer", '') : 0; 

            $data = [
                'shop_id'                       => 0,
                'is_beautiful_point_transfer'   => $is_beautiful_point_transfer,
                'order_auto_delivery'           => $order_auto_delivery,
                'convert_rate'                  => $convert_rate,
                'order_delivery_complete_time'  => $order_delivery_complete_time,
                'order_buy_close_time'          => $order_buy_close_time,
                'shopping_back_points'          => $shopping_back_points,
                'point_invoice_tax'             => $point_invoice_tax,
                'is_point'                      => $is_point,
                'integral_calculation'          => $integral_calculation,
                'is_point_deduction'            => $is_point_deduction,
                'point_deduction_calculation'   => $point_deduction_calculation,
                'point_deduction_max'           => $point_deduction_max,
                'is_translation'                => $is_translation,
                'translation_time'              => $translation_time,
                'translation_text'              => $translation_text,
                'is_transfer'                   => $is_transfer,
                'is_transfer_charge'            => $is_transfer_charge,
                'charge_type'                   => $charge_type,
                'charge_pares'                  => $charge_pares,
                'charge_pares_min'              => $charge_pares_min,
                'charge_pares2'                 => $charge_pares2,
                'is_point_transfer'             => $is_point_transfer,
                'is_point_transfer_charge'      => $is_point_transfer_charge,
                'point_charge_type'             => $point_charge_type,
                'point_charge_pares'            => $point_charge_pares,
                'point_charge_pares_min'        => $point_charge_pares_min,
                'point_charge_pares2'           => $point_charge_pares2,
                'has_express'                   => $has_express,
                'evaluate_give_point'           => $evaluate_give_point,
                'point_num'                     => $point_num,
                'close_pay_password'            => $close_pay_password,
                'pay_password_length'            => $pay_password_length,
            ];
             
            $Config = new WebConfig(); 
//            $retval = $Config->setShopConfig(0, $order_auto_delivery, $convert_rate, $order_delivery_complete_time, $order_buy_close_time, $shopping_back_points, $point_invoice_tax, $is_point,
//                $integral_calculation,$is_point_deduction,$point_deduction_calculation,$point_deduction_max,$is_translation,$translation_time,$translation_text, $is_transfer,$is_transfer_charge,$charge_type,
//                $charge_pares,$charge_pares_min,$charge_pares2, $is_point_transfer,$is_point_transfer_charge,$point_charge_type,$point_charge_pares,$point_charge_pares_min,$point_charge_pares2,$has_express,
//                $evaluate_give_point, $point_num);
            $retval = $Config->setShopConfig($data);
            if ($retval) {
                $this->addUserLogByParam('系统交易设置保存', $retval);
            }
            return AjaxReturn($retval);
        }
    }

    /**
     * 提现设置
     */
    public function memberWithdrawSetting() {
        if (request()->isAjax()) {
            $key = 'WITHDRAW_BALANCE';
            $value = array(
                'withdraw_cash_min' => $_POST['cash_min'] ? $_POST['cash_min'] : 0,
                'withdraw_poundage' => $_POST['poundage'] ? $_POST['poundage'] : 0,
                'member_withdraw_poundage' => $_POST['member_poundage'] ? $_POST['member_poundage'] : 0,
                'withdraw_message' => $_POST['message'] ? $_POST['message'] : '',
                'is_examine' => $_POST['is_examine'] ? $_POST['is_examine'] : '',
                'make_money' => $_POST['make_money'] ? $_POST['make_money'] : '',
                'withdrawals_begin' => $_POST['withdrawals_begin'] ? $_POST['withdrawals_begin'] : '',
                'withdrawals_end' => $_POST['withdrawals_end'] ? $_POST['withdrawals_end'] : ''
            );
            $is_use = $_POST['is_use'];
            $param = array(
                'instance_id' => 0,
                'website_id' => $this->website_id,
                'key' => $key,
                'value' => $value,
                'desc' => '提现设置',
                'is_use' => $is_use
            );
            $config_service = new WebConfig();
            $retval = $config_service->setConfigOne($param);
            if ($retval) {
                $this->addUserLogByParam('系统提现设置保存', $retval);
            }
            return AjaxReturn($retval);
        }
    }

    /**
     * 地区管理
     */
    public function areaManagement() {
        $dataAddress = new DataAddress();
        $area_list = $dataAddress->getAreaList(); // 区域地址
        $list = $dataAddress->getProvinceList();
        foreach ($list as $k => $v) {
            if ($dataAddress->getCityCountByProvinceId($v['province_id']) > 0) {
                $v['issetLowerLevel'] = 1;
            } else {
                $v['issetLowerLevel'] = 0;
            }
            if (!empty($area_list)) {
                foreach ($area_list as $area) {
                    if ($area['area_id'] == $v['area_id']) {
                        $list[$k]['area_name'] = $area['area_name'];
                        break;
                    }
                }
            }
        }
        $this->assign("area_list", $area_list);
        $this->assign("list", $list);
        return view($this->style . 'Config/areaManagement');
    }

    public function selectCityListAjax() {
        if (request()->isAjax()) {
            $province_id = request()->post('province_id', '');
            $dataAddress = new DataAddress();
            $list = $dataAddress->getCityList($province_id);
            foreach ($list as $v) {
                if ($dataAddress->getDistrictCountByCityId($v['city_id']) > 0) {
                    $v['issetLowerLevel'] = 1;
                } else {
                    $v['issetLowerLevel'] = 0;
                }
            }
            return $list;
        }
    }

    public function selectDistrictListAjax() {
        if (request()->isAjax()) {
            $city_id = request()->post('city_id', '');
            $dataAddress = new DataAddress();
            $list['0'] = $dataAddress->getDistrictList($city_id);
            $list['1'] = $this->website_id;
            return $list;
        }
    }

    public function addCityAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $city_id = 0;
            $province_id = request()->post('superiorRegionId', '');
            $city_name = request()->post('regionName', '');
            $zipcode = request()->post('zipcode', '');
            $sort = request()->post('regionSort', '');
            $res = $dataAddress->addOrupdateCity($city_id, $province_id, $city_name, $zipcode, $sort);
            if ($res) {
                $this->addUserLogByParam('添加市级区域', $res);
            }
            return AjaxReturn($res);
        }
    }

    public function updateCityAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $city_id = request()->post('eventId', '');
            $province_id = request()->post('superiorRegionId', '');
            $city_name = request()->post('regionName', '');
            $zipcode = request()->post('zipcode', '');
            $sort = request()->post('regionSort', '');
            $res = $dataAddress->addOrupdateCity($city_id, $province_id, $city_name, $zipcode, $sort);
            if ($res) {
                $this->addUserLogByParam('修改市级区域', $res);
            }
            return AjaxReturn($res);
        }
    }

    public function addDistrictAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $district_id = 0;
            $city_id = request()->post('superiorRegionId', '');
            $district_name = request()->post('regionName', '');
            $sort = request()->post('regionSort', '');
            $res = $dataAddress->addOrupdateDistrict($district_id, $city_id, $district_name, $sort);
            if ($res) {
                $this->addUserLogByParam('添加县级区域', $res);
            }
            return AjaxReturn($res);
        }
    }

    public function updateDistrictAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $district_id = request()->post('eventId', '');
            $city_id = request()->post('superiorRegionId', '');
            $district_name = request()->post('regionName', '');
            $sort = request()->post('regionSort', '');
            $res = $dataAddress->addOrupdateDistrict($district_id, $city_id, $district_name, $sort);
            if ($res) {
                $this->addUserLogByParam('修改县级区域', $res);
            }
            return AjaxReturn($res);
        }
    }

    public function updateProvinceAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $province_id = request()->post('eventId', '');
            $province_name = request()->post('regionName', '');
            $sort = request()->post('regionSort', '');
            $area_id = request()->post('area_id', '');
            $res = $dataAddress->updateProvince($province_id, $province_name, $sort, $area_id);
            if ($res) {
                $this->addUserLogByParam('修改省级区域', $res);
            }
            return AjaxReturn($res);
        }
    }

    public function addProvinceAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $province_name = request()->post('regionName', ''); // 区域名称
            $sort = request()->post('regionSort', ''); // 排序
            $area_id = request()->post('area_id', 0); // 区域id
            $res = $dataAddress->addProvince($province_name, $sort, $area_id);
            if ($res) {
                $this->addUserLogByParam('添加省级区域', $res);
            }
            return AjaxReturn($res);
        }
    }

    public function deleteRegion() {
        if (request()->isAjax()) {
            $type = request()->post('type', '');
            $regionId = request()->post('regionId', '');
            $dataAddress = new DataAddress();
            if ($type == 1) {
                $res = $dataAddress->deleteProvince($regionId);
                return AjaxReturn($res);
            }
            if ($type == 2) {
                $res = $dataAddress->deleteCity($regionId);
                return AjaxReturn($res);
            }
            if ($type == 3) {
                $res = $dataAddress->deleteDistrict($regionId);
                return AjaxReturn($res);
            }
        }
    }

    public function updateRegionAjax() {
        if (request()->isAjax()) {
            $dataAddress = new DataAddress();
            $upType = request()->post('upType', '');
            $regionType = request()->post('regionType', '');
            $regionName = request()->post('regionName', '');
            $regionSort = request()->post('regionSort', '');
            $regionId = request()->post('regionId', '');
            $res = $dataAddress->updateRegionNameAndRegionSort($upType, $regionType, $regionName, $regionSort, $regionId);
            return AjaxReturn($res);
        }
    }

    /**
     * 支付方式列表
     */
    public function paymentConfig() {
        $config_service = new WebConfig();
        $shop_id = $this->instance_id;
        $pay_list = $config_service->getPayConfig($shop_id);
        $this->assign("pay_list", $pay_list);
        return view($this->style . 'Config/paymentConfig');
    }
	/**
     * GlobePay配置
     */
    public function glopayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            // GlobePay
            $appid = str_replace(' ', '', request()->post('appid', ''));
            $partner_code = str_replace(' ', '', request()->post('partner_code', ''));
            $credential_code = str_replace(' ', '', request()->post('credential_code', ''));
			$currency = str_replace(' ', '', request()->post('currency', ''));
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setGpayConfig($this->instance_id, $appid,$partner_code,$credential_code,$currency,$is_use);
            return AjaxReturn($retval);
        }
    }
    /**
     * 微信支付配置
     */
    public function payConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            // 微信支付
            $appkey = str_replace(' ', '', request()->post('appkey', ''));
            $MCHID = str_replace(' ', '', request()->post('MCHID', ''));
            $paySignKey = str_replace(' ', '', request()->post('paySignKey', ''));
            $cert = request()->post('cert', '');
            $certkey = request()->post('certkey', '');
            $is_use = request()->post('is_use', 0);
            $wx_tw = request()->post('wx_tw', 0);
            // 获取数据
            $retval = $web_config->setWpayConfig($this->instance_id, $appkey, $MCHID, $paySignKey, $is_use, $certkey, $cert,$wx_tw);
            return AjaxReturn($retval);
        }
    }

    /**
     * 余额支付配置
     */
    public function bPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setBpayConfig($this->instance_id, '', '', '', $is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 美丽分支付配置
     */
    public function pPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setPpayConfig($this->instance_id, '', '', '', $is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 货到付款配置
     */
    public function dPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setDpayConfig($this->instance_id, '', '', '', $is_use);
            return AjaxReturn($retval);
        }
    }
    /**
     * eth付款配置
     */
    public function ethPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setEthConfig($this->instance_id, $is_use);
            return AjaxReturn($retval);
        }
    }
    /**
     * eos付款配置
     */
    public function eosPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setEosConfig($this->instance_id, $is_use);
            return AjaxReturn($retval);
        }
    }
    /**
     * 通联支付配置
     */
    public function tlPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $tl_key = str_replace(' ', '', request()->post('tl_key', ''));
            $tl_appid = str_replace(' ', '', request()->post('tl_appid', ''));
            $tl_cusid = str_replace(' ', '', request()->post('tl_cusid', ''));
            $tl_id = str_replace(' ', '', request()->post('tl_id', ''));
            $tl_username = str_replace(' ', '', request()->post('tl_username', ''));
            $tl_password = str_replace(' ', '', request()->post('tl_password', ''));
            $tl_public = str_replace(' ', '', request()->post('tl_public', ''));
            $tl_private = str_replace(' ', '', request()->post('tl_private', ''));
            $is_use = request()->post('is_use', 0);
            $tl_tw = request()->post('tl_tw', 0);
            // 获取数据
            $retval = $web_config->settlConfig($this->instance_id,$tl_id, $tl_cusid, $tl_appid, $tl_key,$tl_username,$tl_password,$tl_public, $tl_private,$tl_tw,$is_use);
            return AjaxReturn($retval);
        }
    }
    /**
     * 支付宝配置
     */
    public function payAliConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            // 支付宝
            $partnerid = str_replace(' ', '', request()->post('ali_partnerid', ''));
            $seller = str_replace(' ', '', request()->post('ali_seller', ''));
            $ali_key = str_replace(' ', '', request()->post('ali_key', ''));
            $appid = trim(request()->post('appid', ''));
            $ali_public_key = trim(request()->post('ali_public_key', ''));
            $ali_private_key = trim(request()->post('ali_private_key', ''));
            $is_use = request()->post('is_use', 0);
            $ali_cert_type = request()->post('ali_cert_type', 0);
            $ali_cert_sn = request()->post('ali_cert_sn', '');
            $ali_public_key_cert = request()->post('ali_public_key_cert', '');
            $ali_root_cert_sn = request()->post('ali_root_cert_sn','');
            // 获取数据
            $retval = $web_config->setAlipayConfig($this->instance_id, $partnerid, $seller, $ali_key, $is_use, $appid, $ali_public_key, $ali_private_key, $ali_cert_type, $ali_cert_sn, $ali_public_key_cert, $ali_root_cert_sn);
            return AjaxReturn($retval);
        }
    }

    /**
     * 设置微信和支付宝开关状态是否启用
     */
    public function setStatus() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post("is_use", '');
            $type = request()->post("type", '');
            $retval = $web_config->setWpayStatusConfig($this->instance_id, $is_use, $type);
            return AjaxReturn($retval);
        }
    }
    
    /**
     * 商家地址详情
     */
    public function getShopReturn() {
        $order_service = new OrderService();
        $return_id = request()->post('return_id', 0);
        $shop_id = $this->instance_id;
        $website_id = $this->website_id;
        $info = $order_service->getShopReturn($return_id,$shop_id,$website_id);
        return $info;
    }
    
    /**
     * 商家地址
     */
    public function getShopReturnList() {
        $order_service = new OrderService();
        $list= $order_service->getShopReturnList($this->instance_id, $this->website_id);
        return $list;
    }

    /**
     * 商家地址
     */
    public function returnSetting() {
        $order_service = new OrderService();
        $shop_id = $this->instance_id;
        if (request()->isAjax()) {
            $return_id = request()->post('return_id', 0);
            $consigner = request()->post('consigner', '');
            $mobile = request()->post('mobile', '');
            $province = request()->post('province', '');
            $city = request()->post('city', '');
            $district = request()->post('district', '');
            $address = request()->post('address', '');
            $zip_code = request()->post('zip_code', '');
            $is_default = request()->post('is_default', 0);
            $retval = $order_service->updateShopReturnSet($shop_id,$return_id,$consigner,$mobile,$province,$city,$district,$address,$zip_code,$is_default);
            if ($retval) {
                $this->addUserLogByParam('系统商家地址保存', $retval);
            }
            return AjaxReturn($retval);
        }
    }
    
    /**
     * 删除商家地址
     */
    public function returnDelete() {
        $order_service = new OrderService();
        $shop_id = $this->instance_id;
        $return_id = request()->post('return_id', 0);
        $retval = $order_service->deleteShopReturnSet($shop_id,$return_id);
        if ($retval) {
            $this->addUserLogByParam('系统商家地址删除', $retval);
        }
        return AjaxReturn($retval);
    }

    /**
     * 修改短信通知模板
     */
    public function notifySmsTemplate() {
        $type = 'sms';
        $config_service = new WebConfig();
        $notify_type = request()->post('notify_type', '');
        $template_code = request()->post('template_code', '');
        $template_detail = $config_service->getNoticeTemplateDetail($template_code, $type, $notify_type);
        $template_type_list = $config_service->getNoticeTemplateType($notify_type, $template_code);
        $template_item_list = $config_service->getNoticeTemplateItem($template_code, ['all', 'sms']);
        $mobile_message = $config_service->getConfig($this->instance_id,'MOBILEMESSAGE', $this->website_id);
        $value = $mobile_message['value']['user_type'];
        $international = $mobile_message['value']['international'];
        $item_list['list'] = $template_item_list;
        $item_list['message'] = $value;
        $item_list['international'] = $international;
        $item_list["template_content"] = str_replace(PHP_EOL, '', $template_detail["template_content"]);
        $item_list["is_enable"] = (int)$template_detail['is_enable'];
        $item_list["template_title"] = $template_detail['template_title'] ? : '';
        $item_list["int_template_title"] = $template_detail['int_template_title'] ? : '';
        $item_list["template_name"] = $template_type_list['template_name'] ? : '';
        $item_list["notification_mode"] = $template_detail['notification_mode'] ? : '';
        $item_list["sample"] = $template_type_list['sample'] ? : '';
        return $item_list;
    }

    /**
     * 修改邮箱通知模板
     */
    public function notifyEmailTemplate() {
        $type = 'email';
        $config_service = new WebConfig();
        $notify_type = request()->post('notify_type', '');
        $template_code = request()->post('template_code', '');
        $template_detail = $config_service->getNoticeTemplateDetail($template_code, $type, $notify_type);
        $template_type_list = $config_service->getNoticeTemplateType($notify_type, $template_code);
        $template_item_list = $config_service->getNoticeTemplateItem($template_code, ['all', 'email']);
        $item_list['list'] = $template_item_list;
        $item_list['template_content'] = str_replace(PHP_EOL, '', $template_detail['template_content']);
        $item_list['is_enable'] = $template_detail['is_enable']? : '';;
        $item_list['template_title'] = $template_detail['template_title']? : '';
        $item_list['template_name'] = $template_type_list['template_name']? : '';
        $item_list['notification_mode'] = $template_detail['notification_mode']? : '';
        return $item_list;
    }

    /**
     * 更新通知模板
     */
    public function updateNotifyTemplate() {
        $is_enable = request()->post('is_enable', 0);
        $template_type = request()->post('type', '');
        $template_code = request()->post('template_code', '');
        $template_content = request()->post('template_content', '');
        $template_title = request()->post('template_title', '');
        $int_template_title = request()->post('int_template_title', '');
        $notify_type = request()->post("notify_type", "");
        $notification_mode = request()->post("notification_mode", "");
        $config_service = new WebConfig();
        $retval = $config_service->updateNoticeTemplate($is_enable, $template_type, $template_code, $template_content, $template_title, $notify_type, $notification_mode,$int_template_title);
        return AjaxReturn($retval);
    }

    public function searchGoods() {
        $goods_name = request()->post('goods_name', '');
        $category_id = request()->post('category_id', '');
        $category_level = request()->post('category_level', '');
        $where['ng.goods_name'] = array(
            'like',
            '%' . $goods_name . '%'
        );
        $where['ng.category_id_' . $category_level] = $category_id;
        $where['ng.state'] = 1;
        $where = array_filter($where);
        $goods = new Goods();
        $list = $goods->getGoodsList(1, 0, $where);
        return $list;
    }

    /**
     * 开启和关闭 邮件 和短信的开启和 关闭
     */
    public function updateNotifyEnable() {
        $id = request()->post('id', '');
        $is_use = request()->post('is_use', '');
        $config_service = new WebConfig();
        $retval = $config_service->updateConfigEnable($id, $is_use);
        return AjaxReturn($retval);
    }

    /**
     * 数据库列表
     */
    public function databaseList() {
        if (request()->isAjax()) {
            $web_config = new WebConfig();
            $database_list = $web_config->getDatabaseList();
            //将所有建都转为小写
            $database_list = array_map('array_change_key_case', $database_list);
            foreach ($database_list as $k => $v) {
                $database_list[$k]["data_length_info"] = format_bytes($v['data_length']);
            }
            return $database_list;
        } else {
            $child_menu_list = array(
                array(
                    'url' => "Config/DatabaseList",
                    'menu_name' => "数据库备份",
                    "active" => 1
                ),
                array(
                    'url' => "Config/importDataList",
                    'menu_name' => "数据库恢复",
                    "active" => 0
                )
            );
            $this->assign('child_menu_list', $child_menu_list);
            return view($this->style . "Config/databaseList");
        }
    }


    
    public function is_base64($str){
        if($str==base64_encode(base64_decode($str))){
            return true;
        }else{
            return false;
        }
    }


    /**
     * icon图标选择
     */
    public function modalIcons() {
        return view($this->style . 'Shop/iconDialog');
    }

    /**
     * wap_icon图标选择
     */
    public function modalWapIcons() {
        return view($this->style . 'Shop/wap_iconDialog');
    }

    // 弹窗广告设置 
    public function modalPopupAdv() {
        return view($this->style . 'Config/popupAdvDialog');
    }
    // 底部菜单设置 
    public function modalTabbar() {
        return view($this->style . 'Config/popupTabbar');
    }
    // 版权信息设置 
    public function modalCopyright() {
        return view($this->style . 'Config/popupCopyright');
    }

    // 图片热区设置 
    public function modalDrawregion() {
        return view($this->style . 'Config/popupDrawregion');
    }

    // 风格弹窗 
    public function modalStyles() {
        return view($this->style . 'Config/popupStyles');
    }
    // 商品弹窗 
    public function modalGoods() {
        return view($this->style . 'Config/popupGoodsDialog');
    }
    // 活动商品弹窗 
    public function modalPromoteGoods() {
        return view($this->style . 'Config/popupPromoteGoodsDialog');
    }
    // 优惠券弹窗 
    public function modalCoupon() {
        return view($this->style . 'Config/popupCouponDialog');
    }
    // 预约表单弹窗 
    public function modalForm() {
        return view($this->style . 'Config/popupFormDialog');
    }

    // 装修选择页面链接（多端统一）
    public function modalPageLinks() {
        $config['shop'] = getAddons('shop',$this->website_id,0,true);
        $config['store'] = getAddons('store',$this->website_id,0,true);
        $config['distribution'] = getAddons('distribution',$this->website_id,0,true);
        $config['areabonus'] = getAddons('areabonus',$this->website_id,0,true);
        $config['globalbonus'] = getAddons('globalbonus',$this->website_id,0,true);
        $config['teambonus'] = getAddons('teambonus',$this->website_id,0,true);
        $config['coupontype'] = getAddons('coupontype',$this->website_id,0,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,0,true);
        $config['integral'] = getAddons('integral',$this->website_id,0,true);
        $config['channel'] = getAddons('channel',$this->website_id,0,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,0,true);
        $config['presell'] = getAddons('presell',$this->website_id,0,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,0,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,0,true);
        $config['signin'] = getAddons('signin',$this->website_id,0,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,0,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,0,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,0,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,0,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,0,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,0,true);
        $config['qlkefu'] = getAddons('qlkefu',$this->website_id,0,true);
        $config['credential'] = getAddons('credential',$this->website_id,0,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,0,true);
        $config['anticounterfeiting'] = getAddons('anticounterfeiting',$this->website_id,0,true);
        $config['liveshopping'] = getAddons('liveshopping',$this->website_id,0,true);
        $config['thingcircle'] = getAddons('thingcircle',$this->website_id,0,true);
        $config['blockchain'] = getAddons('blockchain',$this->website_id,0,true);
        $config['miniprogram'] = getAddons('miniprogram',$this->website_id,0,true);
        $config['giftvoucher'] = getAddons('giftvoucher',$this->website_id,0,true);
        $config['voucherpackage'] = getAddons('voucherpackage',$this->website_id,0,true);
        $config['helpcenter'] = getAddons('helpcenter',$this->website_id,0,true);
        $config['mplive'] = getAddons('mplive',$this->website_id,0,true);
        $config['friendscircle'] = getAddons('friendscircle',$this->website_id,0,true);
        $config['paygrade'] = getAddons('paygrade',$this->website_id,0,true);
        $config['merchants'] = getAddons('merchants',$this->website_id,0,true);

        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        if (request()->get('platform') == 'mini') {
            $this->assign('type', request()->get('template_type'));
        }
        $this->assign('platform',request()->get('platform'));//mini、app、wap
        return view($this->style . 'Config/pageLinksDialog');
    }

    // 装修选择tabbar链接
    public function modalTabbarLinks() {
        $config['shop'] = getAddons('shop',$this->website_id,0,true);
        $config['store'] = getAddons('store',$this->website_id,0,true);
        $config['distribution'] = getAddons('distribution',$this->website_id,0,true);
        $config['areabonus'] = getAddons('areabonus',$this->website_id,0,true);
        $config['globalbonus'] = getAddons('globalbonus',$this->website_id,0,true);
        $config['teambonus'] = getAddons('teambonus',$this->website_id,0,true);
        $config['coupontype'] = getAddons('coupontype',$this->website_id,0,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,0,true);
        $config['integral'] = getAddons('integral',$this->website_id,0,true);
        $config['channel'] = getAddons('channel',$this->website_id,0,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,0,true);
        $config['presell'] = getAddons('presell',$this->website_id,0,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,0,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,0,true);
        $config['signin'] = getAddons('signin',$this->website_id,0,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,0,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,0,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,0,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,0,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,0,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,0,true);
        $config['qlkefu'] = getAddons('qlkefu',$this->website_id,0,true);
        $config['credential'] = getAddons('credential',$this->website_id,0,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,0,true);
        $config['anticounterfeiting'] = getAddons('anticounterfeiting',$this->website_id,0,true);
        $config['liveshopping'] = getAddons('liveshopping',$this->website_id,0,true);
        $config['thingcircle'] = getAddons('thingcircle',$this->website_id,0,true);
        $config['blockchain'] = getAddons('blockchain',$this->website_id,0,true);
        $config['miniprogram'] = getAddons('miniprogram',$this->website_id,0,true);
        $config['mplive'] = getAddons('mplive',$this->website_id,0,true);

        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        if (request()->get('type') == 'mini') {
            $this->assign('type', request()->get('template_type'));
        }
        $this->assign('platform',request()->get('platform'));//mini、app、wap
        return view($this->style . 'Config/tabbarLinksDialog');
    }
    /**
     * 获取wap、mp、app微信配置信息
     * @return \multitype
     * @throws \think\Exception\DbException
     */
    public function synWXPayConfig ()
    {
        $type = request()->post('type');//需要同步的配置
        $target_type = request()->post('target_type');//同步到什么类型，为了文件同步
        $config_service = new WebConfig();
        if ($type == 'wap'){
            $wx_set = $config_service->getConfig(0, 'WPAY', $this->website_id, 1);
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($wx_set['certkey']) {
                $src  = $wx_set['certkey'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $wx_set['certkey'] = $res['data']['file'];
                }else{
                    $wx_set['certkey'] = '';
                }
            }
            if ($wx_set['cert']) {
                $src  = $wx_set['cert'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $wx_set['cert'] = $res['data']['file'];
                }else{
                    $wx_set['cert'] = '';
                }
            }
            return AjaxReturn(SUCCESS,$wx_set);
        }
        if ($type == 'mp') {
            $mp_info = $config_service->getConfig(0, 'MPPAY', $this->website_id, 1);
            if (!$mp_info['appid']) {
                $mpSer = new MiniProgramSer();
                $mp_condition = [
                    'shop_id'   => $this->instance_id,
                    'website_id'=> $this->website_id,
                ];
                $mini_program_info = $mpSer->miniProgramInfo($mp_condition);
                $mp_info['appid'] = $mini_program_info['authorizer_appid'];
            }
            $mp_info['mch_id'] = $mp_info['mch_id'] ?:$mp_info['mchid'];//注意这里用mch_id（原本小程序是mchid）
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($mp_info['certkey']) {
                $src  = $mp_info['certkey'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $mp_info['certkey'] = $res['data']['file'];
                }else{
                    $mp_info['certkey'] = '';
                }
            }
            if ($mp_info['cert']) {
                $src  = $mp_info['cert'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $mp_info['cert'] = $res['data']['file'];
                }else{
                    $mp_info['cert'] = '';
                }
            }
            
            return AjaxReturn(SUCCESS,$mp_info);
        }
        if ($type == 'app') {
            $app_wx_info = $config_service->getConfig(0, 'WPAYAPP', $this->website_id, 1);
            if (!$app_wx_info['appid']){
                $wx_config = $config_service->getConfig($this->instance_id, 'SHOPWCHAT', $this->website_id, 1);
                $app_wx_info['appid'] = $wx_config['appid'];
                $app_wx_info['mch_key'] = $wx_config['encodingAESKey'];
            }
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($app_wx_info['certkey']) {
                $src  = $app_wx_info['certkey'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $app_wx_info['certkey'] = $res['data']['file'];
                }else{
                    $app_wx_info['certkey'] = '';
                }
            }
            if ($app_wx_info['cert']) {
                $src  = $app_wx_info['cert'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $app_wx_info['cert'] = $res['data']['file'];
                }else{
                    $app_wx_info['cert'] = '';
                }
            }
            return AjaxReturn(SUCCESS,$app_wx_info);
        }
    }
    
    /**
     * 获取wap、mp GlobePay配置信息
     * @return \multitype
     * @throws \think\Exception\DbException
     */
    public function synGlobePayConfig ()
    {
        $type = request()->post('type');
        $config_service = new WebConfig();
        if ($type == 'wap'){
            $glp_wap = $config_service->getConfig(0, 'GLOPAY', $this->website_id, 1);
            return AjaxReturn(SUCCESS,$glp_wap);
        }
        if ($type == 'mp') {
            $glp_mp = $config_service->getConfig(0, 'GPPAY', $this->website_id, 1);
            return AjaxReturn(SUCCESS,$glp_mp);
        }
        
    }
    
    /**
     * 获取wap、app 支付宝配置信息
     * @return \multitype
     * @throws \think\Exception\DbException
     */
    public function synAliPayConfig ()
    {
        $type = request()->post('type');
        $target_type = request()->post('target_type');//同步到什么类型，为了文件同步
        $config_service = new WebConfig();
        if ($type == 'wap'){
            $ali_wap = $config_service->getConfig(0, 'ALIPAY', $this->website_id, 1);
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($ali_wap['ali_cert_sn']) {
                $src  = $ali_wap['ali_cert_sn'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $ali_wap['ali_cert_sn'] = $res['data']['file'];
                }else{
                    $ali_wap['ali_cert_sn'] = '';
                }
            }
            if ($ali_wap['ali_public_key_cert']) {
                $src  = $ali_wap['ali_public_key_cert'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $ali_wap['ali_public_key_cert'] = $res['data']['file'];
                }else{
                    $ali_wap['ali_public_key_cert'] = '';
                }
            }
            if ($ali_wap['ali_root_cert_sn']) {
                $src  = $ali_wap['ali_root_cert_sn'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $ali_wap['ali_root_cert_sn'] = $res['data']['file'];
                }else{
                    $ali_wap['ali_root_cert_sn'] = '';
                }
            }
            
            return AjaxReturn(SUCCESS,$ali_wap);
        }
        if ($type == 'app') {
            $ali_app = $config_service->getConfig(0, 'ALIPAYAPP', $this->website_id, 1);
            return AjaxReturn(SUCCESS,$ali_app);
        }
    }
    
    /**
     * 获取wap、mp、app 通联配置信息
     * @return \multitype
     * @throws \think\Exception\DbException
     */
    public function synTLPayConfig ()
    {
        $type = request()->post('type');
        $target_type = request()->post('target_type');//同步到什么类型，为了文件同步
        $config_service = new WebConfig();
        if ($type == 'wap'){
            $tl_wap = $config_service->getConfig($this->instance_id, 'TLPAY', $this->website_id, 1);
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($tl_wap['tl_public']) {
                $src  = $tl_wap['tl_public'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $tl_wap['tl_public'] = $res['data']['file'];
                }else{
                    $tl_wap['tl_public'] = '';
                }
            }
            if ($tl_wap['tl_private']) {
                $src  = $tl_wap['tl_private'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $tl_wap['tl_private'] = $res['data']['file'];
                }else{
                    $tl_wap['tl_private'] = '';
                }
            }
            return AjaxReturn(SUCCESS,$tl_wap);
        }
        if ($type == 'mp') {
            $tl_mp = $config_service->getConfig($this->instance_id, 'TLPAYMP', $this->website_id, 1);
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($tl_mp['tl_public']) {
                $src  = $tl_mp['tl_public'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $tl_mp['tl_public'] = $res['data']['file'];
                }else{
                    $tl_mp['tl_public'] = '';
                }
            }
            if ($tl_mp['tl_private']) {
                $src  = $tl_mp['tl_private'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $tl_mp['tl_private'] = $res['data']['file'];
                }else{
                    $tl_mp['tl_private'] = '';
                }
            }
            return AjaxReturn(SUCCESS,$tl_mp);
        }
        if ($type == 'app') {
            $tl_app = $config_service->getConfig($this->instance_id, 'TLPAYAPP', $this->website_id, 1);
            //替换并复制原文件路径（如果以后上传的配置文件到云端，就需要重构这里）
            if ($tl_app['tl_public']) {
                $src  = $tl_app['tl_public'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $tl_app['tl_public'] = $res['data']['file'];
                }else{
                    $tl_app['tl_public'] = '';
                }
            }
            if ($tl_app['tl_private']) {
                $src  = $tl_app['tl_private'];
                $target = str_replace($type,$target_type, $src);
                $res = $config_service->copyPayConfigFile($src, $target);
                if ($res['code'] > 0){
                    $tl_app['tl_private'] = $res['data']['file'];
                }else{
                    $tl_app['tl_private'] = '';
                }
            }
            return AjaxReturn(SUCCESS,$tl_app);
        }
    }
    /**
     * 线下支付配置
     */
    public function setOfflinePayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            $pay_name = request()->post('pay_name', '');
            $collection_code = request()->post('collection_code', '');
            $collection_memo = request()->post('collection_memo', '');
            $pay_type = request()->post('pay_type', 0);
            if($pay_type == 1) {
                //网页端
                $key = 'OFFLINEPAY';
            }elseif ($pay_type == 2) {
                //小程序端
                $key = 'OFFLINEPAYMP';
            }elseif ($pay_type == 3) {
                //app端
                $key = 'OFFLINEPAYAPP';
            }
            $retval = $web_config->setOfflinePayConfig($this->instance_id, $is_use,$pay_name,$collection_code,$collection_memo,$key);
            return AjaxReturn($retval);
        }
    }
    /**
     * 检测是否重复开启设置
     */
    public function checkPayStatus(){
        $web_config = new WebConfig();
        $type = request()->post('type', ''); //WPAY 微信 ALIPAY 支付宝 BPAY 银行卡 JOINPAY汇聚支付
        $joinpay = request()->post('joinpay', ''); //joinpay 1 其他0
        $port = request()->post('port', ''); //端口 '' wap app MP
        $data['message'] = '可以开启使用';
        $data['code'] = 1;
       
        switch ($type) {
            case 'WPAY':
                $check_status = $web_config->checkStatus($type,$joinpay,$this->instance_id,$port);
                if($check_status == false){
                    $data['message'] = '是否关闭原微信支付,并且开启当前微信支付?';
                    $data['code'] = -1;
                }
                break;
            case 'ALIPAY':
                $check_status = $web_config->checkStatus($type,$joinpay,$this->instance_id,$port);
                if($check_status == false){
                    $data['message'] = '是否关闭原支付宝支付,并且开启当前支付宝支付?';
                    $data['code'] = -1;
                }
                break;
            case 'TLPAY':
                $check_status = $web_config->checkStatus($type,$joinpay,$this->instance_id,$port);
                if($check_status == false){
                    $data['message'] = '是否关闭原银行卡支付,并且开启当前银行卡支付?';
                    $data['code'] = -1;
                }
                break;
            case 'JOINPAY':
                $check_status = $web_config->checkStatus($type,$joinpay,$this->instance_id,$port);
                if($joinpay == 'WPAY'){
                    $text = '微信';
                }else if($joinpay == 'ALIPAY'){
                    $text = '支付宝';
                }else{
                    $text = '银行卡';
                }
                
                if($check_status == false){
                    $data['message'] = '是否关闭原'.$text.'支付,并且开启当前'.$text.'支付?';
                    $data['code'] = -1;
                }
                break;
            default:
                # code...
                break;
        }
        return json($data);
    }
    /**
     * 关闭操作
     * TLPAY 银行卡
     */
    public function changePayConfig(){
        $web_config = new WebConfig();
        $type = request()->post('type', '');
        $joinpay = request()->post('joinpay', '');
        $port = request()->post('port', ''); //端口 '' wap app MP
        
        if($type != 'JOINPAY'){
            
            //关闭系统原微信、支付宝、银行卡
            switch ($type) {
                case 'WPAY':
                    $web_config->changePayConfig('WPAY','',$port);
                    break;
                case 'ALIPAY':
                    $web_config->changePayConfig('ALIPAY','',$port);
                    break;
                case 'TLPAY':
                    $web_config->changePayConfig('TLPAY','',$port);
                    break;    
                default:
                    # code...
                    break;
            }
        }else{
            
            //关闭汇聚微信、支付宝、银行卡
            switch ($joinpay) {
                case 'WPAY':
                    $web_config->changePayConfig('WPAY','JOINPAY',$port);
                    break;
                case 'ALIPAY':
                    $web_config->changePayConfig('ALIPAY','JOINPAY',$port);
                    break;
                case 'TLPAY':
                    
                    $web_config->changePayConfig('TLPAY','JOINPAY',$port);
                    break;    
                default:
                    # code...
                    break;
            }
        }
        
        
        $data['code'] = 1;
        $data['message'] = '操作成功';
        return json($data);
    }
    /**
     * 聚合支付配置
     */
    public function joinPayConfig() {
        $web_config = new WebConfig();
        if (request()->isAjax()) {
            if(substr(PHP_VERSION, 0, 1) != '5' && substr(PHP_VERSION, 0, 1) != '7'){
                $data['message'] = '该支付方式仅支持php5.x,php7.x环境，请切换运行环境后重试';
                $data['code'] = -1;
                return json($data);
            }
            $port = request()->post('port', '');
            $is_use = request()->post('is_use', 0);
            $wx_is_use = request()->post('wx_is_use', 0);
            $ali_is_use = request()->post('ali_is_use', 0);
            $p1_MerchantNo = request()->post('p1_MerchantNo', '');
            $qa_TradeMerchantNo = request()->post('qa_TradeMerchantNo', '');
            $joinpaytw_is_use = request()->post('joinpaytw_is_use', '');
            $joinpaytw_is_auto = request()->post('joinpaytw_is_auto', '');
            $hmacVal = request()->post('hmacVal', '');
            $joinpay_wx_appId = request()->post('joinpay_wx_appId', '');
            $fastpay_is_use = request()->post('fastpay_is_use', 0);
            $fastpay_public_key = request()->post('fastpay_public_key', '');//公钥
            $fastpay_private_key = request()->post('fastpay_private_key', '');//私钥
            
            $joinpay_alt_is_use = request()->post('joinpay_alt_is_use', '');//汇聚支付分账
            $joinpay_alt_is_auto = request()->post('joinpay_alt_is_auto', '');//自动分账
            
            if($wx_is_use == 1){
                //查询是否已经开启系统微信支付 是则不允许再次开启
                $check_status = $web_config->checkStatus('WPAY','',$this->instance_id,$port);
                if($check_status == false){
                    $data['message'] = '请先关闭系统微信支付';
                    $data['code'] = -1;
                    return json($data);
                }
            }
            if($ali_is_use == 1){
                //查询是否已经开启系统支付宝支付 是则不允许再次开启
                $check_status = $web_config->checkStatus('ALIPAY','',$this->instance_id,$port);
                if($check_status == false){
                    $data['message'] = '请先关闭系统支付宝支付';
                    $data['code'] = -1;
                    return json($data);
                }
            }
            // 获取数据
            $retval = $web_config->setJoinpayConfig($this->instance_id, $p1_MerchantNo, $qa_TradeMerchantNo, $hmacVal,$ali_is_use,$wx_is_use,$is_use,$joinpaytw_is_use,$joinpaytw_is_auto,$fastpay_is_use,$fastpay_public_key,$fastpay_private_key,$joinpay_alt_is_use,$joinpay_alt_is_auto,$port,$joinpay_wx_appId);
            return AjaxReturn($retval);
        }
    }
    
    // 装修页面
    public function customTemplateList() {
        deleteCache();
        $redirect = __URL(__URL__ . '/' . $this->module . "/Customtemplate/customTemplateList");
        $this->redirect($redirect);return;//新装修地址所以返回(勿删！)
    }
}
