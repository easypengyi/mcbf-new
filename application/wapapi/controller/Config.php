<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/8/20 0020
 * Time: 10:31
 */

namespace app\wapapi\controller;

use addons\blockchain\service\Block;
use data\extend\WchatOauth;
use data\service\AddonsConfig;
use data\service\Config as ConfigServer;
use data\service\Customtemplate as  CustomtemplateSer;
use data\service\WebSite as WebSiteSer;
use data\model\AddonsConfigModel;
use addons\qlkefu\server\Qlkefu;
use data\service\Weixin as WeixinSer;

class Config extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function share()
    {
        $weixin = new WchatOauth($this->website_id);
        $url = request()->post('url', '');
        $wx_share = $weixin->shareWx(urldecode($url));
        return json(['code' => 1, 'message' => '成功获取', 'data' => $wx_share]);
    }
    //文案样式
    public function copyStyle(){
        $config = new ConfigServer();
        $copystyle = $config->getConfig(0,'COPYSTYLE', $this->website_id, 1);
        $copystyle['balance_style'] = '积分';
        if(getAddons('membercard',$this->website_id)) {
            $addons = new AddonsConfig();
            $value = $addons->getAddonsConfig('membercard', $this->website_id, 0, 1);
        }
        if($copystyle){
            $data['data']  = $copystyle;
            $data['code'] = '0';
        }else{
            $copystyle['point_style'] = '积分';
            $copystyle['balance_style'] = '积分';
            $data['data'] = $copystyle;
            $data['code'] = '0';
        }
        if($value) {
            $data['data']['membercard'] = $value['membercard'];
            $data['data']['plus'] = $value['plus'];
        }else{
            $data['data']['membercard'] = '会员卡';
            $data['data']['plus'] = 'PLUS专享';
        }

        return json($data);
    }
    //app的基本设置和支付配置，第三方登录配置
    public function appConfig(){
        $configSer = new ConfigServer();
        $config_array = ['BPAYAPP', 'ALIPAYAPP','DPAYAPP','WPAYAPP', 'WCHATAPP'];
        $config_list = $configSer->getConfigBatch(0, $config_array, $this->website_id);
        $base_config['dpay'] = false;
        $base_config['bpay'] = false;
        $base_config['ali_pay'] = false;
        $base_config['wechat_pay'] = false;
        $base_config['wechat_login'] = false;
        foreach ($config_list as $k => $v) {
            switch ($v['key']) {
                case 'DPAYAPP':
                    if ($v['is_use'] == 0) {
                        $base_config['dpay'] = false;
                        break;
                    }
                    $base_config['dpay'] = true;
                    break;
                case 'BPAYAPP':
                    if ($v['is_use'] == 0) {
                        $base_config['bpay'] = false;
                        break;
                    }
                    $base_config['bpay'] = true;
                    break;
                case 'ALIPAYAPP':
                    if ($v['is_use'] == 0) {
                        $base_config['ali_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['ali_partnerid']) || empty($info['ali_seller']) || empty($info['ali_key'])) {
                        $base_config['ali_pay'] = false;
                        break;
                    }
                    $base_config['ali_pay'] = true;
                    break;
                case 'WPAYAPP':
                    if ($v['is_use'] == 0) {
                        $base_config['wechat_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['appid'])  || empty($info['mch_id']) || empty($info['mch_key'])) {
                        $base_config['wechat_pay'] = false;
                        break;
                    }
                    $base_config['wechat_pay'] = true;
                    break;
                case 'WCHATAPP':
                    if ($v['is_use'] == 0) {
                        $base_config['wechat_login'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['APP_KEY']) || empty($info['APP_SECRET'])) {
                        $base_config['wechat_login'] = false;
                        break;
                    }
                    $base_config['wechat_login'] = true;
                    break;
            }
        }
        $addons_service = new AddonsConfig();
        $list['app_set'] = $addons_service->getAddonsConfig('appshop',$this->website_id);
        if($list['app_set']['value']){
            $base_config['app_set'] = $list['app_set']['value'];
        }else{
            $base_config['app_set'] = (object)[];
        }
        $data['config'] = $base_config;
        return json(['code' => 1, 'message' => '成功获取', 'data' => $data]);
    }
    //小程序支付配置设置，第三方登录配置
    public function mipConfig(){
        $configSer = new ConfigServer();
        $config_array = ['BPAYMP', 'DPAYMP','MPPAY','GPPAY'];
        $config_list = $configSer->getConfigBatch(0, $config_array, $this->website_id);
        $base_config['dpay'] = false;
        $base_config['bpay'] = false;
        $base_config['wechat_pay'] = false;
		$base_config['gppay'] = false;
        foreach ($config_list as $k => $v) {
            switch ($v['key']) {
                case 'DPAYMP':
                    if ($v['is_use'] == 0) {
                        $base_config['dpay'] = false;
                        break;
                    }
                    $base_config['dpay'] = true;
                    break;
                case 'BPAYMP':
                    if ($v['is_use'] == 0) {
                        $base_config['bpay'] = false;
                        break;
                    }
                    $base_config['bpay'] = true;
                    break;
                case 'MPPAY':
                    if ($v['is_use'] == 0) {
                        $base_config['wechat_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['appid'])  || empty($info['mchid']) || empty($info['mch_key'])) {
                        $base_config['wechat_pay'] = false;
                        break;
                    }
                    $base_config['wechat_pay'] = true;
                    break;
                case 'GPPAY':
                    if ($v['is_use'] == 0) {
                        $base_config['gppay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['appid'])  || empty($info['partner_code']) || empty($info['credential_code']) || empty($info['currency'])) {
                        $base_config['gppay'] = false;
                        break;
                    }
                    $base_config['gppay'] = true;
                    break;
            }
        }
        $data['config'] = $base_config;
        return json(['code' => 1, 'message' => '成功获取', 'data' => $data]);
    }

    //新接口
    public function newConfig()
    {
        $program =  requestForm();//1==》移动端，2=》小程序，3=》app
        $websiteSer = new WebSiteSer();
        $website_setting = $websiteSer->getWebSiteInfo($this->website_id);
        $config = new ConfigServer();
        $wchat_config = $config->getConfig($this->instance_id, 'SHOPWCHAT', $this->website_id);
        $reg_rule = true;
        if ($website_setting['reg_rule'] == 0) {
            $reg_rule = false;
        }
        $pur_rule = true; //购买协议
        if ($website_setting['pur_rule'] == 0) {
            $pur_rule = false;
        }
        $is_wchat = false;
        if (!empty($wchat_config['value']['appid']) && !empty($wchat_config['value']['public_name']) && !empty($wchat_config['value']['appsecret'])) {
            $is_wchat = true;
        }

        $base_config = [];
        if ($website_setting) {
            $base_config['mall_name'] = $website_setting['mall_name'];
            $base_config['logo'] = getApiSrc($website_setting['logo']);
            $base_config['icon'] = getApiSrc($website_setting['icon']);
            //$base_config['web_status'] = $website_setting['web_status'];
            $base_config['wap_status'] = $website_setting['wap_status'];
            $base_config['close_reason'] = $website_setting['close_reason'];
            //$base_config['shop_status'] = $website_setting['shop_status'];
            $base_config['wap_register_adv'] = getApiSrc($website_setting['wap_register_adv']);
            $base_config['wap_register_jump'] = $website_setting['wap_register_jump'];
            $base_config['wap_login_adv'] = getApiSrc($website_setting['wap_login_adv']);
            $base_config['wap_login_jump'] = $website_setting['wap_login_jump'];
            $base_config['wap_pop'] = $config->getConfig(0, 'POPADV_CONFIG', $this->website_id, 1);
            $base_config['is_wchat'] = $is_wchat;
            $base_config['reg_rule'] = $reg_rule;
            $base_config['pur_rule'] = $pur_rule;
            $base_config['account_type'] = $website_setting['account_type'];
            $base_config['mobile_type'] = $website_setting['mobile_type'];
            $base_config['is_bind_phone'] = $website_setting['is_bind_phone'];
        }

        //获取是否开启转账设置
        $is_transfer = $config->getConfig($this->instance_id, 'IS_TRANSFER', $this->website_id);
        $is_point_transfer = $config->getConfig($this->instance_id, 'IS_POINT_TRANSFER', $this->website_id);
        $is_beautiful_point_transfer = $config->getConfig($this->instance_id, 'IS_BEAUTIFUL_POINT_TRANSFER', $this->website_id);
        $base_config['is_transfer'] = $is_transfer['value'] ? $is_transfer['value'] : 0;
        $base_config['is_beautiful_point_transfer'] = $is_beautiful_point_transfer['value'] ? $is_beautiful_point_transfer['value'] : 0;
        $base_config['is_point_transfer'] = $is_point_transfer['value'] ? $is_point_transfer['value'] : 0;
        //积分汇率
        $convert_rate = $config->getConfig($this->instance_id, 'POINT_DEDUCTION_NUM', $this->website_id);
        $base_config['convert_rate'] = $convert_rate['value'] ? $convert_rate['value'] : 0;
        //支付密码开关
        $close_pay_password = $config->getClosePayPassword( $this->website_id);
        $base_config['cpp'] = $close_pay_password['value'] ? 1 : 0;//1关闭 0开启
        //支付密码长度
        $pay_length = $config->getPayPasswordLengthValue($this->website_id);
        $base_config['ppl_prev'] = 0;
        $base_config['ppl_now'] = 0;
        if ($pay_length){
            $base_config['ppl_prev'] = $pay_length['prev'];
            $base_config['ppl_now'] = $pay_length['now'];
        }
//        $userServer = new User();
//        $base_config['is_subscribe'] = $userServer->checkUserIsSubscribe($this->uid);//是否关注公众号
        $config_array = ['SEO_TITLE', 'SEO_META', 'SEO_DESC', 'LOGINVERIFYCODE', 'BPAY', 'ALIPAY','DPAY','TLPAY','ETHPAY','EOSPAY','WPAY', 'WCHAT', 'QQLOGIN', 'EMAILMESSAGE', 'MOBILEMESSAGE', 'WITHDRAW_BALANCE','BPAYMP', 'DPAYMP','MPPAY','GPPAY','BPAYAPP', 'ALIPAYAPP','DPAYAPP','WPAYAPP', 'WCHATAPP','GLOPAY','OFFLINEPAY','OFFLINEPAYMP','OFFLINEPAYAPP','JOINPAY','MPJOINPAY','JOINPAYAPP'];
        $blockchain = getAddons('blockchain',$this->website_id);
        $eth_set = false;
        $eos_set = false;
        if($blockchain){
            $site = new Block();
            $site_info = $site->getBlockChainSite($this->website_id);
            if($site_info['is_use']==1 && $site_info['wallet_type']){
                $wallet_type = explode(',',$site_info['wallet_type']);
                if(in_array(1,$wallet_type)){
                    $eth_set = true;
                }
                if(in_array(2,$wallet_type)){
                    $eos_set = true;
                }
            }
        }
        $base_config['withdraw_conf']['is_withdraw_start'] = false;
        $data = [];
        $data['pay_config'] = [
            'wap_pay_set' => [
                'ali_pay' => false,
                'bpay' => false,
                'dpay' => false,
                'eospay' => false,
                'ethpay' => false,
                'tlpay' => false,
				'gppay' => false,
                'wechat_pay' => false,
                'offline_pay' => [
                    'is_use' => false,
                    'pay_name' => '',
                    'collection_code' => '',
                    'collection_memo' => '',
                ]
            ],
            'app_pay_set' => [
                'ali_pay' => false,
                'wechat_pay' => false,
                'bpay' => false,
                'dpay' => false,
                'wechat_login' => false,
                'offline_pay' => [
                    'is_use' => false,
                    'pay_name' => '',
                    'collection_code' => '',
                    'collection_memo' => '',
                ]
            ],
            'mp_pay_set' => [
                'ali_pay' => false,
                'wechat_pay' => false,
                'bpay' => false,
                'dpay' => false,
                'gppay' => false,
                'offline_pay' => [
                    'is_use' => false,
                    'pay_name' => '',
                    'collection_code' => '',
                    'collection_memo' => '',
                ]
            ]
        ];

        $config_list = $config->getConfigBatch(0, $config_array, $this->website_id);
        foreach ($config_list as $k => $v) {
            switch ($v['key']) {
                case 'SEO_TITLE':
                case 'SEO_META' :
                case 'SEO_DESC' :
                    $base_config[strtolower($v['key'])] = $v['value'];
                    break;
                case 'WITHDRAW_BALANCE':
                    $info = json_decode($v['value'], true);
                    $base_config['withdraw_conf']['is_withdraw_start'] = $v['is_use'] ? true  : false;
                    $base_config['withdraw_conf']['lowest_withdraw'] = $info['withdraw_cash_min'] ? : 0;
                    $base_config['withdraw_conf']['withdraw_message'] = explode(',', $info['withdraw_message']) ? : [];
                    if($base_config['withdraw_conf']['withdraw_message'] && in_array(3,$base_config['withdraw_conf']['withdraw_message'])){
                        $info = $config->getConfig(0, 'ALIPAY', $this->website_id);
                        if($info['is_use']==0){
                            $base_config['withdraw_conf']['withdraw_message'] = array_merge(array_diff($base_config['withdraw_conf']['withdraw_message'], [3]));
                        }
                    }
                    if($base_config['withdraw_conf']['withdraw_message'] && in_array(2,$base_config['withdraw_conf']['withdraw_message'])){
                        $info = $config->getConfig(0, 'WPAY', $this->website_id);
                        $wx_tw = $info['value']['wx_tw'];
                        if($wx_tw==0 || $info['is_use']==0){
                            $base_config['withdraw_conf']['withdraw_message'] = array_merge(array_diff($base_config['withdraw_conf']['withdraw_message'], [2]));
                        }
                    }
                    if($base_config['withdraw_conf']['withdraw_message'] && in_array(1,$base_config['withdraw_conf']['withdraw_message'])){
                        $info = $config->getConfig(0, 'TLPAY', $this->website_id);
                        $tl_tw = $info['value']['tl_tw'];
                        //获取汇聚设置
                        $jpinfo = $config->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                        $jp_tw = $jpinfo['value']['joinpaytw_is_use'];
                        if($tl_tw==0 && $jp_tw == 0){
                            $base_config['withdraw_conf']['withdraw_message'] = array_merge(array_diff($base_config['withdraw_conf']['withdraw_message'], [1]));
                        }
                    }
                    break;
                case 'LOGINVERIFYCODE':
                    $info = json_decode($v['value'], true);
                    $base_config['captcha_code_type'] = $info['pc'] ? true : false;
                    break;
                case 'DPAY':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['wap_pay_set']['dpay'] = true;
                    }
                    break;
                case 'TLPAY':
                    $tlinfo = json_decode($v['value'], true);
                    if ($v['is_use'] == 1 && $tlinfo['tl_cusid'] && $tlinfo['tl_appid'] && $tlinfo['tl_key']) {
                        $data['pay_config']['wap_pay_set']['tlpay'] = true; //此处需要加多一个判断
                    }
                    break;
                case 'ETHPAY':
                    if($eth_set && $v['is_use'] == 1){
                        $data['pay_config']['wap_pay_set']['ethpay'] = true;
                    }
                    break;
                case 'EOSPAY':
                    if($eos_set && $v['is_use'] == 1){
                        $data['pay_config']['wap_pay_set']['eospay'] = true;
                    }
                    break;
                case 'BPAY':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['wap_pay_set']['bpay'] = true;
                    }
                    break;
                case 'ALIPAY':
                    if(!$v['is_use']){
                        $data['pay_config']['wap_pay_set']['ali_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['ali_partnerid'] && $info['ali_seller'] && $info['ali_key']) {
                        $data['pay_config']['wap_pay_set']['ali_pay'] = true;
                    }
                    break;
                case 'WPAY':
                    if(!$v['is_use']){
                        $data['pay_config']['wap_pay_set']['wechat_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['appid'] && $info['mch_id'] && $info['mch_key']) {
                        $data['pay_config']['wap_pay_set']['wechat_pay'] = true;
                    }
                    break;
				case 'GLOPAY':
                    if(!$v['is_use']){
                        $data['pay_config']['wap_pay_set']['gppay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['partner_code'] && $info['credential_code'] && $info['currency']) {
                        $data['pay_config']['wap_pay_set']['gppay'] = true;
                    }
                    break;
                case 'OFFLINEPAY':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['wap_pay_set']['offline_pay']['is_use'] = true;
                        $info = json_decode($v['value'], true);
                        $data['pay_config']['wap_pay_set']['offline_pay']['pay_name'] = $info['pay_name'];
                        $data['pay_config']['wap_pay_set']['offline_pay']['collection_code'] = $info['collection_code'];
                        $data['pay_config']['wap_pay_set']['offline_pay']['collection_memo'] = $info['collection_memo'];
                    }
                    break;

                case 'DPAYMP':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['mp_pay_set']['dpay'] = true;
                    }
                    break;
                case 'BPAYMP':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['mp_pay_set']['bpay'] = true;
                    }
                    break;
                case 'MPPAY':
                    if(!$v['is_use']){
                        $data['pay_config']['mp_pay_set']['wechat_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['appid'] && $info['mchid'] && $info['mch_key']) {/*注意是mchid*/
                        $data['pay_config']['mp_pay_set']['wechat_pay'] = true;
                    }
                    break;
                case 'GPPAY':
                    if(!$v['is_use']){
                        $data['pay_config']['mp_pay_set']['gppay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['appid'] && $info['partner_code'] && $info['credential_code'] && $info['currency']) {
                        $data['pay_config']['mp_pay_set']['gppay'] = true;
                    }
                    break;
                case 'OFFLINEPAYMP':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['mp_pay_set']['offline_pay']['is_use'] = true;
                        $info = json_decode($v['value'], true);
                        $data['pay_config']['mp_pay_set']['offline_pay']['pay_name'] = $info['pay_name'];
                        $data['pay_config']['mp_pay_set']['offline_pay']['collection_code'] = $info['collection_code'];
                        $data['pay_config']['mp_pay_set']['offline_pay']['collection_memo'] = $info['collection_memo'];
                    }
                    break;
                case 'DPAYAPP':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['app_pay_set']['dpay'] = true;
                    }
                    break;
                case 'BPAYAPP':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['app_pay_set']['bpay'] = true;
                    }
                    break;
                case 'ALIPAYAPP':
                    if (!$v['is_use']) {
                        $data['pay_config']['app_pay_set']['ali_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['ali_partnerid'] && $info['ali_seller'] && $info['ali_key']) {
                        $data['pay_config']['app_pay_set']['ali_pay'] = true;
                    }
                    break;
                case 'WPAYAPP':
                    if (!$v['is_use']) {
                        $data['pay_config']['app_pay_set']['wechat_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['appid'] && $info['mch_id'] && $info['mch_key']) {
                        $data['pay_config']['app_pay_set']['wechat_pay'] = true;
                    }
                    break;
                case 'OFFLINEPAYAPP':
                    if ($v['is_use'] == 1) {
                        $data['pay_config']['app_pay_set']['offline_pay']['is_use'] = true;
                        $info = json_decode($v['value'], true);
                        $data['pay_config']['app_pay_set']['offline_pay']['pay_name'] = $info['pay_name'];
                        $data['pay_config']['app_pay_set']['offline_pay']['collection_code'] = $info['collection_code'];
                        $data['pay_config']['app_pay_set']['offline_pay']['collection_memo'] = $info['collection_memo'];
                    }
                    break;
                case 'WCHATAPP':
                    if (!$v['is_use']) {
                        $data['pay_config']['app_pay_set']['wechat_login'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if ($info['APP_KEY'] && $info['APP_SECRET']) {
                        $data['pay_config']['app_pay_set']['wechat_login'] = true;
                    }
                    break;
                case 'WCHAT':
                    //{"APP_KEY":"","APP_SECRET":"","AUTHORIZE":"","CALLBACK":""}
                    if ($v['is_use'] == 0) {
                        $base_config['wechat_login'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['APP_KEY']) || empty($info['APP_SECRET']) || empty($info['AUTHORIZE']) || empty($info['CALLBACK'])) {
                        $base_config['wechat_login'] = false;
                        break;
                    }
                    $base_config['wechat_login'] = true;
                    break;
                case 'QQLOGIN':
                    //{"APP_KEY":"","APP_SECRET":"","AUTHORIZE":"","CALLBACK":""}
                    if ($v['is_use'] == 0) {
                        $base_config['qq_login'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['APP_KEY']) || empty($info['APP_SECRET']) || empty($info['AUTHORIZE']) || empty($info['CALLBACK'])) {
                        $base_config['qq_login'] = false;
                        break;
                    }
                    $base_config['qq_login'] = true;
                    break;
                case 'EMAILMESSAGE':
                    //{"email_host":"","email_addr":"","email_id":"","email_pass":"oqzvqvolpdvhbhjj","email_is_security":false}
                    if ($v['is_use'] == 0) {
                        $base_config['email_verification'] = false;
                        break;
                    }
                    $base_config['email_verification'] = true;
                    break;
                case 'MOBILEMESSAGE':
                    if ($v['is_use'] == 0) {
                        $base_config['mobile_verification'] = false;
                        break;
                    }
                    $base_config['mobile_verification'] = true;
                    break;
                case 'JOINPAY': //该支付拆分成 wap mp app独立设置 -- so需要独立查询
                    if ($v['is_use'] == 1) {
                        $info = json_decode($v['value'], true);
                        if($info['ali_use'] == 1){
                            $data['pay_config']['wap_pay_set']['ali_pay'] = true;
                        }
                        if($info['wx_use'] == 1){
                            $data['pay_config']['wap_pay_set']['wechat_pay'] = true;
                        }
                        if($info['fastpay_is_use'] == 1){
                            $data['pay_config']['wap_pay_set']['tlpay'] = true;
                        }
                    }
                    break;
                case 'MPJOINPAY': //该支付拆分成 wap mp app独立设置 -- so需要独立查询
                    if ($v['is_use'] == 1) {
                        $info = json_decode($v['value'], true);
                        if($info['ali_use'] == 1){
                            $data['pay_config']['mp_pay_set']['ali_pay'] = true;
                        }
                        if($info['wx_use'] == 1){
                            $data['pay_config']['mp_pay_set']['wechat_pay'] = true;
                        }
                        if($info['fastpay_is_use'] == 1){
                            $data['pay_config']['mp_pay_set']['tlpay'] = true;
                        }
                    }
                    break;
                case 'JOINPAYAPP': //该支付拆分成 wap mp app独立设置 -- so需要独立查询
                    if ($v['is_use'] == 1) {
                        $info = json_decode($v['value'], true);
                        if($info['ali_use'] == 1){
                            $data['pay_config']['app_pay_set']['ali_pay'] = true;
                        }
                        if($info['wx_use'] == 1){
                            $data['pay_config']['app_pay_set']['wechat_pay'] = true;
                        }
                        if($info['fastpay_is_use'] == 1){
                            $data['pay_config']['app_pay_set']['tlpay'] = true;
                        }
                    }
                    break;
            }
        }
        $customSer = new CustomtemplateSer();
        //装修主题
        $base_config['theme'] = $customSer->getCurrentThemeColor();
        // app版本号
        $base_config['app_version'] = config('common_config.app_version');
        $base_config['app_wgturl'] = config('common_config.app_wgturl');

        $data['config'] = $base_config;

        $addons = new AddonsConfig();
        $custom_info = $addons->getAddonsConfig('customform', $this->website_id, 0, 1);
        $custom = [];
        $custom['member_status'] = $custom_info['member_status'];
        $custom['order_status'] = $custom_info['order_status'];
        $custom['distributor_status'] = $custom_info['distributor_status'];
        $custom['shareholder_status'] = $custom_info['shareholder_status'];
        $custom['captain_status'] = $custom_info['captain_status'];
        $custom['channel_status'] = $custom_info['channel_status'];
        $custom['area_status'] = $custom_info['area_status'];
        $data['customform'] = $custom;
        $model = new AddonsConfigModel();
        $all_addons = $model->getQuery(['website_id' => $this->website_id], '', '');
        foreach ($all_addons as $k => $v) {
            $data['addons'][$v['addons']] = getAddons($v['addons'], $this->website_id, $this->instance_id);
        }
        $other_addons = ['appshop', 'areabonus', 'coupontype', 'discount', 'fullcut', 'gift', 'giftvoucher', 'integral', 'pcport',
            'seckill', 'shop', 'teambonus', 'voucherpackage', 'distribution', 'bargin', 'channel', 'customform', 'store', 'microshop', 'poster','blockchain',
            'taskcenter', 'credential','agent', 'liveshopping','helpcenter'];
        foreach ($other_addons as $k => $v) {
            if (!isset($data['addons'][$v])){
                $data['addons'][$v] = getAddons($v, $this->website_id, $this->instance_id);
            }
        }
        if(getAddons('qlkefu', $this->website_id)){
            $qlkefu = new Qlkefu();
            $qlkefu_info = $qlkefu->qlkefuConfig($this->website_id,0);
            $data['config']['qlkefu_domain_port'] = $qlkefu_info['ql_domain'].':'.$qlkefu_info['ql_port'];
            if(empty($qlkefu_info['ql_domain']))$data['config']['qlkefu_domain_port'] = '';
            $is_qlkefu = $qlkefu->isQlkefuShop($this->website_id);
            $data['addons']['qlkefu'] = $is_qlkefu['is_use'];
        }
        $data['config']['website_id'] = $this->website_id;
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['in_use'] = 1;
        $system_condition['is_system_default'] = 1;
        $system_condition['is_default'] = 1;

        switch ($program) {
            case 1:
                $custom_data = $customSer->getCommonTemplateDataToDeal(1);
                $data['tab_bar']        = json_decode($custom_data['tab_bar'][1],true);//底部
                $data['copyright']      = json_decode($custom_data['copyright'][1],true);//版权
                $data['wechat_set']     = json_decode($custom_data['wechat_set'],true);//公众号
                $data['popup_adv']      = json_decode($custom_data['popadv'][1],true);//弹窗
//                //处理用户关注公总号
//                if ($this->uid) {
//                    $weixin = new WeixinSer();
//                    $count = $weixin->getWeixinFansCount(['uid'=> $this->uid,'is_subscribe'=>1]);
//                    $data['wechat_set']['is_show'] = $count ? 0 : 1;
//                }
                break;
            case 2:
                $custom_data = $customSer->getCommonTemplateDataToDeal(2);
                $data['tab_bar']        = json_decode($custom_data['tab_bar'][2],true);//底部
                $data['copyright']      = json_decode($custom_data['copyright'][2],true);//版权
                $data['popup_adv']      = json_decode($custom_data['popadv'][2],true);//弹窗
                break;
            case 3:
                $custom_data = $customSer->getCommonTemplateDataToDeal(3);
                $data['tab_bar']        = json_decode($custom_data['tab_bar'][3],true);//底部
                $data['copyright']      = json_decode($custom_data['copyright'][3],true);//版权
                $data['popup_adv']      = json_decode($custom_data['popadv'][3],true);//弹窗
                break;
            default:
                break;
        }
        if (empty($data['config']['wap_pop'])){
            $data['config']['wap_pop'] = $data['popup_adv'];
        }
        //订单弹幕的websocket_url、回调接口地址返回
        $barrage_conf = $addons->getAddonsConfig('orderbarrage', $this->website_id);
        $data['order_barrage_url_config'] = [
            'websocket_url' => config('websocket_url').'/websocket',
            'request_url' => config('rabbit_interface_url.url').'/task/barrageMsg',
            'use_place' => $barrage_conf['value']['use_place'],//1-全局 2-首页 3-首页+商品详情
            'is_high_powered' => config('is_high_powered'),//1-高性能
        ];
        return AjaxReturn(SUCCESS, $data);
    }

    //区号列表
    public function getCountryCode(){
        $countryCodeModel = new \data\model\SysCountryCodeModel();
        $countryCode = $countryCodeModel->getQuery([], '*', 'sort asc');
        if(!$countryCode){
            $data=[
                ['sort' => 1,'country' => '中国', 'country_code' => '86', 'country_code_long' => '0086'],
                ['sort' => 2,'country' => '香港', 'country_code' => '852', 'country_code_long' => '00852'],
                ['sort' => 3,'country' => '澳门', 'country_code' => '853', 'country_code_long' => '00853'],
                ['sort' => 4,'country' => '台湾', 'country_code' => '886', 'country_code_long' => '00886'],
                ['sort' => 5,'country' => '美国', 'country_code' => '1', 'country_code_long' => '001'],
                ['sort' => 6,'country' => '日本', 'country_code' => '81', 'country_code_long' => '0081'],
                ['sort' => 7,'country' => '韩国', 'country_code' => '82', 'country_code_long' => '0082'],
                ['sort' => 8,'country' => '新加坡', 'country_code' => '65', 'country_code_long' => '0065']
            ];
            $countryCodeModel->saveAll($data, true);
            $countryCode = $countryCodeModel->getQuery([], '*', 'sort asc');
        }

        return json(['code' => 1, 'message' => '成功获取', 'data' => $countryCode]);
    }

    /**
     * 网络图片转base64
     * @return \multitype
     */
    public function imgTransform ()
    {
        $redis = connectRedis();
        $str_redis = $redis->get('user_img_'.$this->uid);
        if($str_redis){
            return AjaxReturn(SUCCESS,$str_redis);
        }
        $img = request()->post('img');

        if (!$img){return AjaxReturn(LACK_OF_PARAMETER);}
        try{
            $str = imgtobase64($img);
            $redis->setex('user_img_'.$this->uid, 3600, $str);
            return AjaxReturn(SUCCESS,$str);
        }catch (\Exception $e){
            recordErrorLog($e);
            return AjaxReturn(FAIL);
        }
    }
}
