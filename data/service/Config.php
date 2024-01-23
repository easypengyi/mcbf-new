<?php

namespace data\service;

/**
 * 系统配置业务层
 */

use addons\appshop\model\AppCustomTemplate;
use addons\blockchain\service\Block as BlockSer;
use addons\miniprogram\model\WeixinAuthModel;
use data\model\AddonsConfigModel;
use data\model\ConfigModel as ConfigModel;
use data\model\CustomTemplateModel;
use data\model\NoticeTemplateItemModel;
use data\model\NoticeTemplateModel;
use data\model\NoticeTemplateTypeModel;
use data\model\WeixinFansModel;
use data\model\WeixinGroupModel;
use data\service\BaseService as BaseService;
use think\Cache;
use data\model\VslMemberBankAccountModel;
use think\Db;

class Config extends BaseService
{

    private $config_module;

    function __construct()
    {
        parent::__construct();
        $this->config_module = new ConfigModel();
    }
    /*
     * 批量获取
     */
    public function getConfigBatch($instance_id = 0, $key_array = [], $website_id = 0){
        $result = $this->config_module->getQuery(['instance_id' => $instance_id, '`key`' => ['in', $key_array], 'website_id' => $website_id], '*');
        return $result;
    }
    /*
     * 根据查询条件获取
     */
    public function getConfigByCondition($data = [], $onlyValue = 0)
    {
        $info = $this->config_module->getInfo($data);
        if (!$info) {
            return [];
        }
        if(strpos($info['value'],'{') !== false){ 
            $info['value']  = json_decode($info['value'], true);
        }
        if ($onlyValue) {
            $value = $info['value'];
            return $value;
        }
        return $info;
    }
    /**
     *
     * @param type $instance_id
     * @param type $key
     * @param type $website_id
     * @param type $onlyValue 只返回value
     * @return type
     */
    public function getConfig($instance_id = 0, $key = '', $website_id = 0, $onlyValue = 0, $supplier_id = 0)
    {
        $new_website_id = $website_id ?: $this->website_id;
        $redis = connectRedis();
        $result = $redis->get($key .'_'. (int)$instance_id .'_'. (int)$new_website_id .'_'.(int)$supplier_id);
        $result = null;
        if($result && $result !='null'){
            $info = json_decode($result, true);
        }else{
            $info = $this->config_module->getInfo([
                'instance_id' => $instance_id,
                'website_id' => $new_website_id,
                '`key`' => $key,
                'supplier_id' => $supplier_id,
            ]);
            $redis->set($key .'_'. (int)$instance_id .'_'. (int)$new_website_id .'_'.(int)$supplier_id, json_encode($info));
        }
        if (!$info) {
            return [];
        }
        if(strpos($info['value'],'{') !== false){ 
            $info['value']  = json_decode($info['value'], true);
        }
        if ($onlyValue) {
            $value = $info['value'];
            return $value;
        }
        return $info;
    }

    /**
     * 这个方法website_id不会根据当前端口变化,传什么就是什么
     * @param type $instance_id
     * @param type $key
     * @param type $website_id
     * @param type $onlyValue 只返回value
     * @return type
     */
    public function getConfigMaster($instance_id, $key, $website_id = 0, $onlyValue = 0, $supplier_id = 0)
    {
        // $website_id = $website_id ?: $this->website_id;
        $info = $this->config_module->getInfo([
                'instance_id' => $instance_id,
                'website_id' => $website_id,
                '`key`' => $key,
                'supplier_id' => $supplier_id
            ]);
        
        if (!$info) {
            return [];
        }
        
        if(strpos($info['value'],'{') !== false){ 
           
            $info['value']  = json_decode($info['value'], true);
        }
        if ($onlyValue) {
            $value = $info['value'];
            return $value;
        }
        return $info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setConfig()
     */
    public function setConfig($params)
    {
        foreach ($params as $value) {
            if ($this->checkConfigKeyIsset($value['instance_id'], $value['key'], $value['website_id'], (int)$value['supplier_id'])) {
                $res = $this->updateConfig($value['instance_id'], $value['key'], $value['value'], $value['desc'], $value['is_use'], $value['website_id'], (int)$value['supplier_id']);
            } else {
                $res = $this->addConfig($value['instance_id'], $value['key'], $value['value'], $value['desc'], $value['is_use'], $value['website_id'], (int)$value['supplier_id']);
            }
        }
        unset($value);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setConfig()
     */
    public function setConfigOne($params = [])
    {
        if ($this->checkConfigKeyIsset($params['instance_id'], $params['key'], $params['website_id'], (int)$params['supplier_id'])) {
            $res = $this->updateConfig($params['instance_id'], $params['key'], $params['value'], $params['desc'], $params['is_use'], $params['website_id'], (int)$params['supplier_id']);
        } else {
            $res = $this->addConfig($params['instance_id'], $params['key'], $params['value'], $params['desc'], $params['is_use'], $params['website_id'], (int)$params['supplier_id']);
        }
        return $res;
    }

    /**
     * 添加设置
     *
     * @param unknown $instance_id
     * @param unknown $key
     * @param unknown $value
     * @param unknown $desc
     * @param unknown $is_use
     */
    public function addConfig($instance_id = 0, $key = '', $value = [], $desc = '', $is_use = 0, $website_id = 0, $supplier_id = 0)
    {
        if (is_array($value)) {
            $value = json_encode($value);
        }

        $data = array(
            'instance_id' => $instance_id,
            'website_id' => $website_id,
            'supplier_id' => $supplier_id,
            'key' => $key,
            'value' => $value,
            'desc' => $desc,
            'is_use' => $is_use,
            'create_time' => time()
        );
        $configModel = new ConfigModel();
        $res = $configModel->save($data);
        $redis = connectRedis();
        $redis->del($key .'_'. $instance_id .'_'. $website_id .'_'.$supplier_id);
        //Cache::set($key .'_'. $instance_id .'_'. $website_id .'_'.$supplier_id, $data);
        return $res;
    }

    /**
     * 修改配置
     *
     * @param unknown $instance_id
     * @param unknown $key
     * @param unknown $value
     * @param unknown $desc
     * @param unknown $is_use
     */
    public function updateConfig($instance_id = 0, $key = '', $value = [], $desc = '', $is_use = 0, $website_id = 0, $supplier_id = 0)
    {
        
        
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $data = array(
            'value' => $value,
            'desc' => $desc,
            'is_use' => $is_use,
            'modify_time' => time()
        );
        $configModule = new ConfigModel();
        $res = $configModule->save($data, [
            'instance_id' => $instance_id,
            'website_id' => $website_id,
            'supplier_id' => $supplier_id,
            'key' => $key
        ]);
        $redis = connectRedis();
        $redis->del($key .'_'. $instance_id .'_'. $website_id .'_'.$supplier_id);
        //Cache::set($key .'_'. $instance_id .'_'. $website_id .'_'.$supplier_id, json_encode($data,true));
        return $res;
    }

    /**
     * 判断当前设置是否存在
     * 存在返回 true 不存在返回 false
     *
     * @param unknown $instance_id
     * @param unknown $key
     */
    public function checkConfigKeyIsset($instance_id = 0, $key = '', $website_id = 0, $supplier_id = 0)
    {
        $num = $this->config_module->where([
                    'instance_id' => $instance_id,
                    'website_id' => $website_id,
                    'key' => $key,
                    'supplier_id' => $supplier_id
                ])->count();
        return $num > 0 ? true : false;
    }
    
    public function deleteConfig($instance_id = 0, $key = '', $website_id = 0, $supplier_id = 0){
        $res = $this->config_module->where(['instance_id' => $instance_id, 'website_id' => $website_id, 'key' => $key, 'supplier_id' => $supplier_id])->delete();
        if($res){
            $redis = connectRedis();
            $redis->del($key .'_'. $instance_id .'_'. $website_id .'_'.$supplier_id);
            //Cache::rm($key .'_'. $instance_id .'_'. $website_id .'_'.$supplier_id);
        }
        return $res;
    }

    public function getWechatOpen($appid, $shop_id)
    {
        $res = $this->getConfigByCondition(['instance_id' => $shop_id, 'value' => ['like', '%' . $appid . '%'], 'key' => 'WECHATOPEN'], 1);
        return $res;
    }

    public function getSeoConfig($shop_id)
    {
        $seo_config = Cache::get("seo_config" . $shop_id . $this->website_id);
        if (empty($seo_config)) {
            $seo_title = $this->getConfig($shop_id, 'SEO_TITLE');
            $seo_meta = $this->getConfig($shop_id, 'SEO_META');
            $seo_desc = $this->getConfig($shop_id, 'SEO_DESC');
            if (empty($seo_title) || empty($seo_meta) || empty($seo_desc)) {
                $this->SetSeoConfig($shop_id, '', '', '');
                $array = array(
                    'seo_title' => '',
                    'seo_meta' => '',
                    'seo_desc' => ''
                );
            } else {
                $array = array(
                    'seo_title' => $seo_title['value'],
                    'seo_meta' => $seo_meta['value'],
                    'seo_desc' => $seo_desc['value']
                );
            }
            Cache::set("seo_config" . $shop_id . $this->website_id, $array);
            $seo_config = $array;
        }

        return $seo_config;
    }

    public function setAlipayConfig($instanceid, $partnerid, $seller, $ali_key, $is_use, $appid, $ali_public_key, $ali_private_key, $ali_cert_type = 0, $ali_cert_sn = '', $ali_public_key_cert = '', $ali_root_cert_sn = '')
    {
        $data = array(
            'ali_partnerid' => $partnerid,
            'ali_seller' => $seller,
            'ali_key' => $ali_key,
            'appid' => $appid,
            'ali_public_key' => $ali_public_key,
            'ali_private_key' => $ali_private_key,
            'ali_cert_type' => $ali_cert_type,
            'ali_cert_sn' => $ali_cert_sn,
            'ali_public_key_cert' => $ali_public_key_cert,
            'ali_root_cert_sn' => $ali_root_cert_sn,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'ALIPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setBpayConfig($instanceid, $partnerid, $seller, $ali_key, $is_use)
    {
        $data = array(
            'ali_partnerid' => $partnerid,
            'ali_seller' => $seller,
            'ali_key' => $ali_key
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'BPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    /**
     * 美丽分支付
     *
     * @param $instanceid
     * @param $partnerid
     * @param $seller
     * @param $ali_key
     * @param $is_use
     * @return false|int
     */
    public function setPpayConfig($instanceid, $partnerid, $seller, $ali_key, $is_use)
    {
        $data = array(
            'ali_partnerid' => $partnerid,
            'ali_seller' => $seller,
            'ali_key' => $ali_key
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'PPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setDpayConfig($instanceid, $partnerid, $seller, $ali_key, $is_use)
    {
        $data = array(
            'ali_partnerid' => $partnerid,
            'ali_seller' => $seller,
            'ali_key' => $ali_key
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'DPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setWpayConfig($instanceid, $appid, $mch_id, $mch_key, $is_use, $certkey, $cert, $wx_tw)
    {
        $data_info = array(
            'appid' => $appid,
            'mch_id' => $mch_id,
            'mch_key' => $mch_key,
            'certkey' => $certkey,
            'cert' => $cert,
            'wx_tw' => $wx_tw
        );
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'WPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setEthConfig($instanceid, $is_use)
    {
        $data_info = array();
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'ETHPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setEosConfig($instanceid, $is_use)
    {
        $data_info = array();
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'EOSPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setTlConfig($instanceid, $tl_id, $tl_cusid, $tl_appid, $tl_key, $tl_username, $tl_password, $tl_public, $tl_private, $tl_tw, $is_use)
    {
        $info = $this->getConfig($instanceid, 'TLPAY', $this->website_id, 1);
        $data_info = array(
            'tl_id' => $tl_id,
            'tl_cusid' => $tl_cusid,
            'tl_appid' => $tl_appid,
            'tl_key' => $tl_key,
            'tl_username' => $tl_username,
            'tl_password' => $tl_password,
            'tl_public' => $tl_public,
            'tl_private' => $tl_private,
            'tl_tw' => $tl_tw,
        );
        $change = 0;
        //查询此次变更是否改变了商户号\应用id 如发生变更，则该商户所有银行卡信息变更为待更新
        if($info){
            if ($info['tl_cusid'] != $data_info['tl_cusid'] || $info['tl_appid'] != $data_info['tl_appid'] || $info['tl_id'] != $data_info['tl_id']) {
                $change = 1; //需要更新
            }
        }
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'TLPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        if ($change == 1 && $res) {
            $memberBankAccountModel = new VslMemberBankAccountModel();
            $memberBankAccountModel->save(['is_update' => 1], ['website_id' => $this->website_id, 'type' => 1]);
        }
        return $res;
        // TODO Auto-generated method stub
    }

    public function setStyleConfig($point_style, $balance_style)
    {
        $value = array(
            'point_style' => $point_style,
            'balance_style' => $balance_style,
        );
        $data = array(
            'instance_id' => 0,
            'website_id' => $this->website_id,
            'key' => 'COPYSTYLE',
            'value' => json_encode($value),
            'desc' => '文案样式',
            'is_use' => 1
        );
        $res = $this->setConfigOne($data);
        return $res;
    }

    public function setSeoConfig($shop_id, $seo_title, $seo_meta, $seo_desc)
    {
        $array[0] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'SEO_TITLE',
            'value' => $seo_title,
            'desc' => '网页标题',
            'is_use' => 1
        );
        $array[1] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'SEO_META',
            'value' => $seo_meta,
            'desc' => '商城关键词',
            'is_use' => 1
        );
        $array[2] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'SEO_DESC',
            'value' => $seo_desc,
            'desc' => '关键词描述',
            'is_use' => 1
        );
        $res = $this->setConfig($array);
        Cache::set("seo_config" . $shop_id . $this->website_id, '');
        return $res;
    }

    # APP

    public function setBpayConfigApp($instanceid, $is_use)
    {
        $data = [];
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'BPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setDpayConfigApp($instanceid, $is_use)
    {
        $data = [];
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'DPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setWpayConfigApp($instanceid, $appid, $mch_id, $mch_key, $is_use = 0, $certkey = '', $cert = '', $wx_tw = 0)
    {
        $data_info = array(
            'appid' => $appid,
            'mch_id' => $mch_id,
            'mch_key' => $mch_key,
            'certkey' => $certkey,
            'cert' => $cert,
            'wx_tw' => $wx_tw
        );
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'WPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setAlipayConfigApp($instanceid, $partnerid, $seller, $ali_key, $is_use, $appid, $ali_public_key, $ali_private_key, $ali_cert_type = 0, $ali_cert_sn = '', $ali_public_key_cert = '', $ali_root_cert_sn = '')
    {
        $data = array(
            'ali_partnerid' => $partnerid,
            'ali_seller' => $seller,
            'ali_key' => $ali_key,
            'appid' => $appid,
            'ali_public_key' => $ali_public_key,
            'ali_private_key' => $ali_private_key,
            'ali_cert_type' => $ali_cert_type,
            'ali_cert_sn' => $ali_cert_sn,
            'ali_public_key_cert' => $ali_public_key_cert,
            'ali_root_cert_sn' => $ali_root_cert_sn,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'ALIPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setEthConfigAPP($instanceid, $is_use)
    {
        $data_info = array();
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'ETHPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setEosConfigAPP($instanceid, $is_use)
    {
        $data_info = array();
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'EOSPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setTlConfigAPP($instanceid, $tl_id, $tl_cusid, $tl_appid, $tl_key, $tl_username, $tl_password, $tl_public, $tl_private, $tl_tw, $is_use)
    {
        $info = $this->getConfig($instanceid, 'TLPAYAPP', $this->website_id, 1);
        $data_info = array(
            'tl_id' => $tl_id,
            'tl_cusid' => $tl_cusid,
            'tl_appid' => $tl_appid,
            'tl_key' => $tl_key,
            'tl_username' => $tl_username,
            'tl_password' => $tl_password,
            'tl_public' => $tl_public,
            'tl_private' => $tl_private,
            'tl_tw' => $tl_tw,
        );
        $change = 0;
        //查询此次变更是否改变了商户号\应用id 如发生变更，则该商户所有银行卡信息变更为待更新
        if($info){
            if ($info['tl_cusid'] != $data_info['tl_cusid'] || $info['tl_appid'] != $data_info['tl_appid'] || $info['tl_id'] != $data_info['tl_id']) {
                $change = 1; //需要更新
            }
        }
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'TLPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        if ($change == 1 && $res) {
            $memberBankAccountModel = new VslMemberBankAccountModel();
            $memberBankAccountModel->save(['is_update' => 1], ['website_id' => $this->website_id, 'type' => 1]);
        }
        return $res;
        // TODO Auto-generated method stub
    }

    # 小程序
//    public function setWpayConfigMir($instanceid, $appid, $mch_id, $mch_key, $is_use)

    public function setWpayConfigMir($instanceid, $appid, $mchid, $mch_key, $is_use = 0, $certkey = '', $cert = '', $wx_tw = 0)
    {
        $data_info = array(
            'appid' => $appid,
            'mchid' => $mchid, /* 注意小程序是mchid不是mch_id */
            'mch_key' => $mch_key,
            'certkey' => $certkey,
            'cert' => $cert,
            'wx_tw' => $wx_tw
        );
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'MPPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setBpayConfigMir($instanceid, $is_use)
    {
        $data = [];
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'BPAYMP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setPpayConfigMir($instanceid, $is_use)
    {
        $data = [];
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'PPAYMP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setDpayConfigMir($instanceid, $is_use)
    {
        $data = [];
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'DPAYMP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
        // TODO Auto-generated method stub
    }

    public function setTlConfigMir($instanceid, $tl_id, $tl_cusid, $tl_appid, $tl_key, $tl_username, $tl_password, $tl_public, $tl_private, $tl_tw, $is_use)
    {
        $info = $this->getConfig($instanceid, 'TLPAYMP', $this->website_id, 1);
        $data_info = array(
            'tl_id' => $tl_id,
            'tl_cusid' => $tl_cusid,
            'tl_appid' => $tl_appid,
            'tl_key' => $tl_key,
            'tl_username' => $tl_username,
            'tl_password' => $tl_password,
            'tl_public' => $tl_public,
            'tl_private' => $tl_private,
            'tl_tw' => $tl_tw,
        );
        $change = 0;
        //查询此次变更是否改变了商户号\应用id 如发生变更，则该商户所有银行卡信息变更为待更新
        if($info){
            if ($info['tl_cusid'] != $data_info['tl_cusid'] || $info['tl_appid'] != $data_info['tl_appid'] || $info['tl_id'] != $data_info['tl_id']) {
                $change = 1; //需要更新
            }
        }
        $value = json_encode($data_info);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'TLPAYMP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        if ($change == 1 && $res) {
            $memberBankAccountModel = new VslMemberBankAccountModel();
            $memberBankAccountModel->save(['is_update' => 1], ['website_id' => $this->website_id, 'type' => 1]);
        }
        return $res;
        // TODO Auto-generated method stub
    }

    /**
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::setInstanceWchatConfig()
     */
    public function setInstanceWchatConfig($type, $appid, $appsecret, $token, $public_name, $encodingAESKey)
    {
        $data_info = $this->getConfig(0, 'SHOPWCHAT', $this->website_id, 1);
        if ($type == 1) {
            $data = array(
                'appid' => $data_info['appid'],
                'public_name' => $data_info['public_name'],
                'appsecret' => $data_info['appsecret'],
                'encodingAESKey' => $encodingAESKey,
                'token' => $data_info['token']
            );
        } else {
            $data = array(
                'appid' => $appid,
                'public_name' => $public_name,
                'appsecret' => $appsecret,
                'encodingAESKey' => $encodingAESKey,
                'token' => $token
            );
        }
        $value = json_encode($data);
        $param = array(
            'key' => 'SHOPWCHAT',
            'value' => $value,
            'is_use' => 1,
            'instance_id' => 0,
            'website_id' => $this->website_id,
        );
        $res = $this->setConfigOne($param);
        if ($data_info) {
            $fans = new WeixinFansModel();
            $fans->delData(['website_id' => $this->website_id]);
            $group = new WeixinGroupModel();
            $group->delData(['website_id' => $this->website_id]);
        }
        return $res;
    }

    /**
     *
     * 得到店铺的系统通知的详情
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::getNoticeTemplateDetail()
     */
    public function getNoticeTemplateDetail($template_code, $type, $notify_type)
    {
        $notice_template_model = new NoticeTemplateModel();
        $condition = array(
            "template_type" => $type,
            "notify_type" => $notify_type,
            "template_code" => $template_code,
            'website_id' => $this->website_id
        );
        $template_list = $notice_template_model->getInfo($condition, "*");
        return $template_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getNoticeTemplateOneDetail()
     */
    public function getNoticeTemplateOneDetail($shop_id, $template_type, $template_code)
    {
        $notice_template_model = new NoticeTemplateModel();
        $info = $notice_template_model->getInfo([
            'instance_id' => $shop_id,
            'template_type' => $template_type,
            'template_code' => $template_code
        ]);
        return $info;
    }

    public function getTemplateTypeDetail($type_id)
    {
        $notice_template_model = new NoticeTemplateTypeModel();
        $info = $notice_template_model->getInfo([
            'type_id' => $type_id
        ]);
        return $info;
    }

    /**
     * 更新通知模板的信息
     * (non-PHPdoc)
     *
     * @see \ata\api\IConfig::updateNoticeTemplate()
     */
    public function updateNoticeTemplate($is_enable, $template_type, $template_code, $template_content, $template_title, $notify_type, $notification_mode, $int_template_title)
    {

        $notice_template_model = new NoticeTemplateModel();
        $count = $notice_template_model->getCount([
            "instance_id" => 0,
            "template_type" => $template_type,
            "template_code" => $template_code,
            "notify_type" => $notify_type,
            "website_id" => $this->website_id
        ]);
        if ($count > 0) {
            // 更新
            $data = array(
                "template_title" => $template_title,
                "int_template_title" => $int_template_title,
                "template_content" => $template_content,
                "is_enable" => $is_enable,
                "modify_time" => time(),
                "notification_mode" => $notification_mode
            );
            $res = $notice_template_model->save($data, [
                "instance_id" => 0,
                "template_type" => $template_type,
                "template_code" => $template_code,
                "notify_type" => $notify_type,
                "website_id" => $this->website_id
            ]);
        } else {
            // 添加
            $data = array(
                "instance_id" => 0,
                "template_type" => $template_type,
                "template_code" => $template_code,
                "template_title" => $template_title,
                "int_template_title" => $int_template_title,
                "template_content" => $template_content,
                "is_enable" => $is_enable,
                "modify_time" => time(),
                "notify_type" => $notify_type,
                "notification_mode" => $notification_mode,
                "website_id" => $this->website_id
            );
            $res = $notice_template_model->save($data);
        }
        return $res;
    }

    /**
     * 得到店铺的邮件发送项
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getNoticeSendItem()
     */
    public function getNoticeTemplateItem($template_code, $item_type)
    {
        $notice_model = new NoticeTemplateItemModel();
        $item_list = $notice_model->where("FIND_IN_SET('" . $template_code . "', type_ids)")->where(['item_type' => ['IN', $item_type]])->select();
        return $item_list;
    }

    /**
     * 得到店铺模板的集合
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::getNoticeTemplateType()
     */
    public function getNoticeTemplateType($notify_type, $template_code)
    {
        $notice_type_model = new NoticeTemplateTypeModel();
        $condition['notify_type'] = $notify_type;
        $condition['template_code'] = $template_code;
        $type_list = $notice_type_model->getInfo($condition, '*');
        return $type_list;
    }

    public function getTemplateType()
    {
        $notice_type_model = new NoticeTemplateTypeModel();
        $anti = $notice_type_model->where(['template_code' => 'anti_forgot_password'])->find();
        if (!$anti) {
            $this->setDefaultAnti();
        }
        $checkSendPass = $notice_type_model->getInfo(['template_code' => 'send_password'], 'type_id');
        if (!$checkSendPass) {
            $notice_type_model->save([
                'template_name' => '发送会员密码',
                'template_code' => 'send_password',
                'template_type' => 'sms',
                'website_id' => 0,
                'sort' => 29,
                'is_platform' => 1,
                'sms_type' => 2,
                'notify_type' => 'user',
                'sample' => '您的会员密码是${password}。',
            ]);
        }
        $checkGiveCoupon = $notice_type_model->getInfo(['template_code' => 'send_give_coupon_msg'], 'type_id');
        if (!$checkGiveCoupon) {
            $notice_type_model->save([
                'template_name' => '发送送券通知',
                'template_code' => 'send_give_coupon_msg',
                'template_type' => 'sms',
                'website_id' => 0,
                'sort' => 31,
                'is_platform' => 1,
                'sms_type' => 2,
                'notify_type' => 'user',
                'sample' => '有${couponnum}张优惠券，${giftnum}张礼品券已偷偷放入了你的钱包，赶紧前往会员中心查看吧~',
            ]);
            $notice_template_item = new NoticeTemplateItemModel();
            $coupon_item = $notice_template_item->Query(['item_name' => '优惠券数量'], 'id')[0];
            if (empty($coupon_item)) {
                $coupon_data = [
                    'item_name' => '优惠券数量',
                    'show_name' => '{优惠券数量}',
                    'replace_name' => '${couponnum}',
                    'type_ids' => 'send_give_coupon_msg',
                    'item_type' => 'sms',
                ];
                $notice_template_item = new NoticeTemplateItemModel();
                $notice_template_item->save($coupon_data);
            }
            $gift_item = $notice_template_item->Query(['item_name' => '礼品券数量'], 'id')[0];
            if (empty($gift_item)) {
                $gift_data = [
                    'item_name' => '礼品券数量',
                    'show_name' => '{礼品券数量}',
                    'replace_name' => '${giftnum}',
                    'type_ids' => 'send_give_coupon_msg',
                    'item_type' => 'sms',
                ];
                $notice_template_item = new NoticeTemplateItemModel();
                $notice_template_item->save($gift_data);
            }
        }
        $checkElectroncardMsg = $notice_type_model->getInfo(['template_code' => 'send_electroncard_msg'], 'type_id');
        if (!$checkElectroncardMsg) {
            $notice_type_model->save([
                'template_name' => '发送电子卡密信息',
                'template_code' => 'send_electroncard_msg',
                'template_type' => 'sms',
                'website_id' => 0,
                'sort' => 30,
                'is_platform' => 1,
                'sms_type' => 2,
                'notify_type' => 'user',
                'sample' => '您购买的${goodsname}，信息为${electroncardmsg}。',
            ]);
            $notice_template_item = new NoticeTemplateItemModel();
            $goods_item_id = $notice_template_item->Query(['item_name' => '商品名称'], 'id')[0];
            if (empty($goods_item_id)) {
                $goods_data = [
                    'item_name' => '商品名称',
                    'show_name' => '{商品名称}',
                    'replace_name' => '${goodsname}',
                    'type_ids' => 'send_electroncard_msg',
                    'item_type' => 'sms',
                ];
                $notice_template_item = new NoticeTemplateItemModel();
                $notice_template_item->save($goods_data);
            }
            $msg_item_id = $notice_template_item->Query(['item_name' => '卡密信息'], 'id')[0];
            if (empty($msg_item_id)) {
                $msg_data = [
                    'item_name' => '卡密信息',
                    'show_name' => '{卡密信息}',
                    'replace_name' => '${electroncardmsg}',
                    'type_ids' => 'send_electroncard_msg',
                    'item_type' => 'sms',
                ];
                $notice_template_item = new NoticeTemplateItemModel();
                $notice_template_item->save($msg_data);
            }
        }
        $type_list = $notice_type_model->getQuery(['is_platform' => 1], '*', 'sort asc');
        $notice_model = new NoticeTemplateModel();
        foreach ($type_list as $k => $v) {
            $is_sms_enable = $notice_model->getInfo(['website_id' => $this->website_id, 'template_type' => 'sms', 'template_code' => $v['template_code']], 'is_enable')['is_enable'];
            if ($v['sms_type'] == 1 && !$is_sms_enable) {
                $is_sms_enable = $this->setTemplateDefault($v['template_code']); //没有数据的话，默认开启验证码类型短信
            }
            $type_list[$k]['is_sms_enable'] = $is_sms_enable;
            $type_list[$k]['is_email_enable'] = $notice_model->getInfo(['website_id' => $this->website_id, 'template_type' => 'email', 'template_code' => $v['template_code']], 'is_enable')['is_enable'];
        }
        unset($v);
        return $type_list;
    }

    public function setTemplateDefault($template_code = '')
    {
        if (!$template_code) {
            return 0;
        }
        $notice_template_model = new NoticeTemplateModel();
        $notice_template = $notice_template_model->getInfo(['template_code' => $template_code, 'website_id' => $this->website_id]);
        if ($notice_template) {
            return 0; //有数据，只是没开启
        }
        $notice_type_model = new NoticeTemplateTypeModel();
        $notice_type = $notice_type_model->getInfo(['template_code' => $template_code], '*');
        $data = [
            'template_type' => 'sms',
            'template_code' => $template_code,
            'template_content' => $notice_type['sample'],
            'is_enable' => 1,
            'modify_time' => time(),
            'notify_type' => $notice_type['notify_type'],
            'website_id' => $this->website_id,
            'instance_id' => $this->instance_id
        ];
        $res = $notice_template_model->save($data);
        if (!$res) {
            return 0;
        }
        return 1;
    }

    /*
     * 更改模板是否开启短信、邮箱验证
     * * */

    public function updateNoticeTemplateEnable($condition, $is_enable)
    {
        $notice_model = new NoticeTemplateModel();
        $res['is_enable'] = $is_enable;
        $bool = $notice_model->where($condition)->update($res);
        return $bool;
    }

    /*
     * 更改支付方式是否可用
     * * */

    public function updateConfigIsuse($condition, $is_use)
    {
        $res['is_use'] = $is_use;
        $bool = $this->config_module->where($condition)->update($res);
        return $bool;
    }

    public function getAllPayConfig($shop_id)
    {
        //小程序、app是否开启
        $is_minipro = getAddons('miniprogram', $this->website_id);
        if ($is_minipro) {
            $weixin_auth = new WeixinAuthModel();
            $new_auth_state = $weixin_auth->getInfo(['website_id' => $this->website_id], 'new_auth_state')['new_auth_state'];
            if (isset($new_auth_state) && $new_auth_state == 0) {
                $is_minipro = 1;
            } else {
                $is_minipro = 0;
            }
        }
        //app
        $is_app = getAddons('appshop', $this->website_id);
        if ($is_app) {
            $addons_conf = new AddonsConfigModel();
            $is_app = $addons_conf->getInfo(['addons' => 'appshop', 'website_id' => $this->website_id], 'is_use')['is_use'];
            $is_app = $is_app ?: 0;
        }
        $return_pay_list['is_minipro'] = $is_minipro;
        $return_pay_list['is_app'] = $is_app;
        $return_pay_list['wap_pay_list'] = $this->getPayConfig($shop_id, 1);
        $return_pay_list['mp_pay_list'] = $this->getPayConfigMir($shop_id, 1);
        $return_pay_list['app_pay_list'] = $this->getAppPayConfig($shop_id, 1);
        return $return_pay_list;
    }

    /**
     * WAP、PC和微信支付的通知项
     *
     * @param unknown $shop_id
     * @param int $show_all 【是否展示全部1是 0 否】
     * @return string|NULL
     */
    public function getPayConfig($shop_id, $show_all = 0)
    {
        $key_string = ['WPAY','ALIPAY','BPAY','DPAY','TLPAY','GLOPAY','OFFLINEPAY','JOINPAY','PPAY'];//'WPAY,ALIPAY,BPAY,DPAY,TLPAY,GLOPAY,OFFLINEPAY,JOINPAY';
        if ($show_all) {
            $key_string = ['WPAY','ALIPAY','BPAY','DPAY','TLPAY','GLOPAY','ETHPAY','EOSPAY','OFFLINEPAY','JOINPAY','PPAY'];//'WPAY,ALIPAY,BPAY,DPAY,TLPAY,GLOPAY,ETHPAY,EOSPAY,OFFLINEPAY,JOINPAY';
        }
        $joinpay_info = $this->getConfig(0, 'JOINPAY', $this->website_id);
        if (empty($joinpay_info)) {
            $this->setJoinpayConfig($shop_id);
        }
        $b_info = $this->getConfig(0, 'BPAY', $this->website_id);
        if (empty($b_info)) {
            $this->setBpayConfig($shop_id, '', '', '', 0);
        }
        $p_info = $this->getConfig(0, 'PPAY', $this->website_id);
        if (empty($p_info)) {
            $this->setPpayConfig($shop_id, '', '', '', 0);
        }
        $d_info = $this->getConfig(0, 'DPAY', $this->website_id);
        if (empty($d_info)) {
            $this->setDpayConfig($shop_id, '', '', '', 0);
        }
        $wx_info = $this->getConfig(0, 'WPAY', $this->website_id);
        if (empty($wx_info)) {
            $this->setWpayConfig($shop_id, '', '', '', 0, '', '', 0);
        }
        $ali_info = $this->getConfig(0, 'ALIPAY', $this->website_id);
        if (empty($ali_info)) {
            $this->setAlipayConfig($shop_id, '', '', '', 0, '', '', '');
        }
        $tl_info = $this->getConfig(0, 'TLPAY', $this->website_id);
        if (empty($tl_info)) {
            $this->setTlConfig($shop_id, '', '', '', '', '', '', '', '', 0, 0);
        }
        if ($this->isOpenBlockChainETH()) {
            $key_string[] = 'ETHPAY';
            $eth_info = $this->getConfig(0, 'ETHPAY', $this->website_id);
            if (empty($eth_info)) {
                $this->setEthConfig($shop_id, 0);
            }
        }
        if ($this->isOpenBlockChainEOS()) {
            $key_string[] = 'EOSPAY';
            $eos_info = $this->getConfig(0, 'EOSPAY', $this->website_id);
            if (empty($eos_info)) {
                $this->setEosConfig($shop_id, 0);
            }
        }
        $glopay_info = $this->getConfig(0, 'GLOPAY', $this->website_id);
        if (empty($glopay_info)) {
            $this->setGpayConfig(0, '', '', '', '', 0);
        }
        $offline_info = $this->getConfig(0, 'OFFLINEPAY', $this->website_id);
        if (empty($offline_info)) {
            $this->setOfflinePayConfig($shop_id, 0, '', '', '', 'OFFLINEPAY');
        }
        $notify_list = objToArr($this->getConfigBatch($shop_id, $key_string, $this->website_id));
        //按照原型上面排序
        foreach ($notify_list as $k => $notify_info) {
            if ($notify_info['key'] == 'BPAY') {
                $new_notify_list[0] = $notify_info;
            } else if ($notify_info['key'] == 'PPAY') {
                $new_notify_list[10] = $notify_info;
            } else if ($notify_info['key'] == 'DPAY') {
                $new_notify_list[1] = $notify_info;
            } else if ($notify_info['key'] == 'WPAY') {
                $new_notify_list[2] = $notify_info;
            } else if ($notify_info['key'] == 'ALIPAY') {
                $new_notify_list[3] = $notify_info;
            } else if ($notify_info['key'] == 'TLPAY') {
                $new_notify_list[4] = $notify_info;
            } else if ($notify_info['key'] == 'EOSPAY') {
                $new_notify_list[5] = $notify_info;
            } else if ($notify_info['key'] == 'ETHPAY') {
                $new_notify_list[6] = $notify_info;
            } else if ($notify_info['key'] == 'GLOPAY') {
                $new_notify_list[7] = $notify_info;
            } else if ($notify_info['key'] == 'OFFLINEPAY') {
                $new_notify_list[8] = $notify_info;
            } else if ($notify_info['key'] == 'JOINPAY') {
                $new_notify_list[9] = $notify_info;
            }
        }
        unset($notify_info);
        
        if (!empty($new_notify_list)) {
			ksort($new_notify_list);
			$new_notify_list = array_values($new_notify_list); //重排索引
            for ($i = 0; $i < count($new_notify_list); $i++) {
                if ($new_notify_list[$i]["key"] == "WPAY") {
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/wechat-pay.png";
                    $new_notify_list[$i]["pay_name"] = "微信支付";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)与全渠道提现";
                } elseif ($new_notify_list[$i]["key"] == "ALIPAY") {
                    $new_notify_list[$i]["pay_name"] = "支付宝支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/alipay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)与全渠道提现";
                } elseif ($new_notify_list[$i]["key"] == "BPAY") {
                    $new_notify_list[$i]["pay_name"] = "余额支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/balance-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "PPAY") {
                    $new_notify_list[$i]["pay_name"] = "美丽分支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/balance-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "TLPAY") {
                    $new_notify_list[$i]["pay_name"] = "通联支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/bank-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)与全渠道提现";
                } elseif ($new_notify_list[$i]["key"] == "DPAY") {
                    $new_notify_list[$i]["pay_name"] = "货到付款";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/to-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "ETHPAY") {
                    $new_notify_list[$i]["pay_name"] = "ETH支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/eth-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "EOSPAY") {
                    $new_notify_list[$i]["pay_name"] = "EOS支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/eos-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "GLOPAY") {
                    $new_notify_list[$i]["pay_name"] = "GlobePay支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/logo_horizontal.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "OFFLINEPAY") {
                    $new_notify_list[$i]["pay_name"] = "线下转款支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/offline-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "JOINPAY") {
                    $new_notify_list[$i]["pay_name"] = "汇聚支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/joiny_pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、APP端)与银行卡提现转账";
                }
            }
            return $new_notify_list;
        } else {
            $this->setBpayConfig($shop_id, '', '', '', 0);
            $this->setDpayConfig($shop_id, '', '', '', 0);
            $this->setWpayConfig($shop_id, '', '', '', '', 0, '', 0);
            $this->setAlipayConfig($shop_id, '', '', '', 0, '', '', '');
            $this->setTlConfig($shop_id, '', '', '', '', '', '', '', '', 0, 0);
            $this->setEthConfig($shop_id, 0);
            $this->setEosConfig($shop_id, 0);
            $this->setGpayConfig(0, '', '', '', '', 0);
            $this->setJoinpayConfig($shop_id);
            $this->setOfflinePayConfig($shop_id, 0, '', '', '', 'OFFLINEPAY');
            return $this->getPayConfig($shop_id);
        }
    }

    /**
     * app支付的通知项
     *
     * @param unknown $shop_id
     * @param int $show_all 【是否展示全部1是 0 否】
     * @return string|NULL
     */
    public function getAppPayConfig($shop_id, $show_all = 0)
    {
        $key_string = ['WPAYAPP', 'ALIPAYAPP', 'BPAYAPP', 'DPAYAPP', 'TLPAYAPP', 'OFFLINEPAYAPP', 'JOINPAY'];
        if ($show_all) {
            $key_string = ['WPAYAPP', 'ALIPAYAPP', 'BPAYAPP', 'DPAYAPP', 'TLPAYAPP', 'ETHPAYAPP', 'EOSPAYAPP', 'OFFLINEPAYAPP', 'JOINPAY'];
        }
        $joinpay_info = $this->getConfig(0, 'JOINPAY', $this->website_id);
        if (empty($joinpay_info)) {
            $this->setJoinpayConfigMir($shop_id);
        }
        $b_info = $this->getConfig(0, 'BPAYAPP', $this->website_id);
        if (empty($b_info)) {
            $this->setBpayConfigApp(0, 0);
        }
        $d_info = $this->getConfig(0, 'DPAYAPP', $this->website_id);
        if (empty($d_info)) {
            $this->setDpayConfigApp(0, 0);
        }
        $wx_info = $this->getConfig(0, 'WPAYAPP', $this->website_id);
        if (empty($wx_info)) {
//            $this->setWpayConfigApp(0, '',  '', '', 0);
            $this->setWpayConfigApp($shop_id, '', '', '', 0, '', '', 0);
        }
        $ali_info = $this->getConfig(0, 'ALIPAYAPP', $this->website_id);
        if (empty($ali_info)) {
            $this->setAlipayConfigApp($shop_id, '', '', '', 0, '', '', '');
        }
        $tl_info = $this->getConfig(0, 'TLPAYAPP', $this->website_id);
        if (empty($tl_info)) {
            $this->setTlConfigAPP($shop_id, '', '', '', '', '', '', '', '', 0, 0);
        }
        $offline_info = $this->getConfig(0, 'OFFLINEPAYAPP', $this->website_id);
        if (empty($offline_info)) {
            $this->setOfflinePayConfig($shop_id, 0, '', '', '', 'OFFLINEPAYAPP');
        }
        if ($this->isOpenBlockChainETH() && !$show_all) {
            $key_string[] = 'ETHPAYAPP';
            $eth_info = $this->getConfig(0, 'ETHPAYAPP', $this->website_id);
            if (empty($eth_info)) {
                $this->setEthConfigAPP($shop_id, 0);
            }
        }
        if ($this->isOpenBlockChainEOS() && !$show_all) {
            $key_string[] = 'EOSPAYAPP';
            $eos_info = $this->getConfig(0, 'EOSPAYAPP', $this->website_id);
            if (empty($eos_info)) {
                $this->setEosConfigAPP($shop_id, 0);
            }
        }
        $notify_list = objToArr($this->getConfigBatch($shop_id, $key_string, $this->website_id));
        foreach ($notify_list as $k => $notify_info) {
            if ($notify_info['key'] == 'BPAYAPP') {
                $new_notify_list[0] = $notify_info;
            } elseif ($notify_info['key'] == 'DPAYAPP') {
                $new_notify_list[1] = $notify_info;
            } elseif ($notify_info['key'] == 'WPAYAPP') {
                $new_notify_list[2] = $notify_info;
            } elseif ($notify_info['key'] == 'ALIPAYAPP') {
                $new_notify_list[3] = $notify_info;
            } elseif ($notify_info['key'] == 'TLPAYAPP') {
                $new_notify_list[4] = $notify_info;
            } elseif ($notify_info['key'] == 'ETHPAYAPP') {
                $new_notify_list[5] = $notify_info;
            } elseif ($notify_info['key'] == 'EOSPAYAPP') {
                $new_notify_list[6] = $notify_info;
            } elseif ($notify_info['key'] == 'OFFLINEPAYAPP') {
                $new_notify_list[7] = $notify_info;
            } else if ($notify_info['key'] == 'JOINPAY') {
                $new_notify_list[8] = $notify_info;
            }
        }
        unset($notify_info);
        
        if (!empty($new_notify_list)) {
			ksort($new_notify_list);
			$new_notify_list = array_values($new_notify_list); //重排索引
            for ($i = 0; $i < count($new_notify_list); $i++) {
                if ($new_notify_list[$i]["key"] == "WPAYAPP") {
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/wechat-pay.png";
                    $new_notify_list[$i]["pay_name"] = "微信支付";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于APP端支付";
                } elseif ($new_notify_list[$i]["key"] == "ALIPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "支付宝支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/alipay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于APP端支付";
                } elseif ($new_notify_list[$i]["key"] == "BPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "余额支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/balance-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于APP端支付";
                } elseif ($new_notify_list[$i]["key"] == "DPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "货到付款";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/to-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于APP端支付";
                } elseif ($new_notify_list[$i]["key"] == "TLPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "通联支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/bank-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)与全渠道提现";
                } elseif ($new_notify_list[$i]["key"] == "ETHPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "ETH支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/eth-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "EOSPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "EOS支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/eos-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)";
                } elseif ($new_notify_list[$i]["key"] == "OFFLINEPAYAPP") {
                    $new_notify_list[$i]["pay_name"] = "线下转款支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/offline-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于APP端支付";
                } elseif ($new_notify_list[$i]["key"] == "JOINPAY") {
                    $new_notify_list[$i]["pay_name"] = "汇聚支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/joiny_pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、APP端)与银行卡提现转账";
                }
            }
            return $new_notify_list;
        } else {
            $this->setBpayConfigApp(0, 0);
            $this->setDpayConfigApp(0, 0);
//            $this->setWpayConfigApp(0, '', '', '', 0);
            $this->setWpayConfigApp($shop_id, '', '', '', 0, '', '', 0);
            $this->setAlipayConfigApp(0, '', '', '', 0, '', '', '');
            $this->setTlConfigAPP($shop_id, '', '', '', '', '', '', '', '', 0, 0);
            $this->setEthConfigAPP($shop_id, 0);
            $this->setEosConfigAPP($shop_id, 0);
            $this->setOfflinePayConfig($shop_id, 0, '', '', '', 'OFFLINEPAYAPP');
            $this->setJoinpayConfigAPP($shop_id);
            return $this->getAppPayConfig($shop_id);
        }
    }
    /**
     * 小程序支付的通知项
     *
     * @param unknown $shop_id
     * @param int $show_all 【是否展示全部1是 0 否】
     * @return string|NULL
     */
    public function getPayConfigMir($shop_id, $show_all=0)
    {
        $key_string = ['BPAYMP','DPAYMP','MPPAY','GPPAY','TLPAYMP','OFFLINEPAYMP','JOINPAY','PPAYMP'];
        if ($show_all) {
            $key_string = ['BPAYMP','DPAYMP','MPPAY','GPPAY','TLPAYMP','OFFLINEPAYMP','JOINPAY','PPAYMP'];
        }
        $joinpay_info = $this->getConfig(0, 'JOINPAY', $this->website_id);
        if (empty($joinpay_info)) {
            $this->setJoinpayConfigMir($shop_id);
        }
        $b_info = $this->getConfig(0, 'BPAYMP', $this->website_id);
        if (empty($b_info)) {
            $this->setBpayConfigMir(0, 0);
        }
        $p_info = $this->getConfig(0, 'PPAYMP', $this->website_id);
        if (empty($p_info)) {
            $this->setPpayConfigMir(0, 0);
        }
        $d_info = $this->getConfig(0, 'DPAYMP', $this->website_id);
        if (empty($d_info)) {
            $this->setDpayConfigMir(0, 0);
        }
        $wx_info = $this->getConfig(0, 'MPPAY', $this->website_id);
        if (empty($wx_info)) {
//            $this->setWpayConfigMir(0, '',  '', '', 0);
            $this->setWpayConfigMir($shop_id, '', '', '', 0, '', '', 0);
        }
        $gp_info = $this->getConfig(0, 'GPPAY', $this->website_id);
        if (empty($gp_info)) {
            $this->setGpayConfigMir(0, '', '', '', '', 0);
        }
        $tl_info = $this->getConfig(0, 'TLPAYMP', $this->website_id);
        if (empty($tl_info)) {
            $this->setTlConfigMir($shop_id, '', '', '', '', '', '', '', '', 0, 0);
        }
        $offline_info = $this->getConfig(0, 'OFFLINEPAYMP', $this->website_id);
        if (empty($offline_info)) {
            $this->setOfflinePayConfig($shop_id, 0, '', '', '', 'OFFLINEPAYMP');
        }
        $notify_list = objToArr($this->getConfigBatch($shop_id, $key_string, $this->website_id));
        foreach ($notify_list as $k => $notify_info) {
            if ($notify_info['key'] == 'BPAYMP') {
                $new_notify_list[0] = $notify_info;
            } elseif ($notify_info['key'] == 'DPAYMP') {
                $new_notify_list[1] = $notify_info;
            } elseif ($notify_info['key'] == 'MPPAY') {
                $new_notify_list[2] = $notify_info;
            } elseif ($notify_info['key'] == 'GPPAY') {
                $new_notify_list[3] = $notify_info;
            } elseif ($notify_info['key'] == 'TLPAYMP') {
                $new_notify_list[4] = $notify_info;
            } elseif ($notify_info['key'] == 'OFFLINEPAYMP') {
                $new_notify_list[5] = $notify_info;
            } else if ($notify_info['key'] == 'JOINPAY') {
                $new_notify_list[6] = $notify_info;
            } else if ($notify_info['key'] == 'PPAYMP') {
                $new_notify_list[7] = $notify_info;
            }


        }
        unset($notify_info);
        ksort($new_notify_list);
        $new_notify_list = array_values($new_notify_list); //索引重排
        if (!empty($new_notify_list)) {
            for ($i = 0; $i < count($new_notify_list); $i++) {
                if ($new_notify_list[$i]["key"] == "MPPAY") {
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/wechat-pay.png";
                    $new_notify_list[$i]["pay_name"] = "微信支付";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于小程序端支付";
                } elseif ($new_notify_list[$i]["key"] == "BPAYMP") {
                    $new_notify_list[$i]["pay_name"] = "余额支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/balance-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于小程序端支付";
                } elseif ($new_notify_list[$i]["key"] == "DPAYMP") {
                    $new_notify_list[$i]["pay_name"] = "货到付款";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/to-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于小程序端支付";
                } elseif ($new_notify_list[$i]["key"] == "GPPAY") {
                    $new_notify_list[$i]["pay_name"] = "GlobePay支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/logo_horizontal.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于小程序端支付";
                } elseif ($new_notify_list[$i]["key"] == "TLPAYMP") {
                    $new_notify_list[$i]["pay_name"] = "通联支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/bank-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、PC端)与全渠道提现";
                } elseif ($new_notify_list[$i]["key"] == "OFFLINEPAYMP") {
                    $new_notify_list[$i]["pay_name"] = "线下转款支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/offline-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于小程序端支付";
                } elseif ($new_notify_list[$i]["key"] == "JOINPAY") {
                    $new_notify_list[$i]["pay_name"] = "汇聚支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/joiny_pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于网页端(微信端、H5端、APP端)与银行卡提现转账";
                } elseif ($new_notify_list[$i]["key"] == "PPAYMP") {
                    $new_notify_list[$i]["pay_name"] = "美丽分支付";
                    $new_notify_list[$i]["logo"] = "/public/platform/static/images/balance-pay.png";
                    $new_notify_list[$i]["desc"] = "该支付方式适用于小程序端支付";
                }
            }
            return $new_notify_list;
        } else {
            $this->setBpayConfigMir(0, 0);
            $this->setDpayConfigMir(0, 0);
//            $this->setWpayConfigMir(0, '', '', '', 0);
            $this->setWpayConfigMir($shop_id, '', '', '', 0, '', '', 0);
            $this->setGpayConfigMir(0, '', '', '', 0);
            $this->setTlConfigMir($shop_id, '', '', '', '', '', '', '', '', 0, 0);
            $this->setOfflinePayConfig($shop_id, 0, '', '', '', 'OFFLINEPAYMP');
            $this->setJoinpayConfigMir($shop_id);
            return $this->getPayConfigMir($shop_id);
        }
    }

    public function getShopConfigNew($shop_id, array $default_return_key_array = [], $website_id = 0)
    {
        $config_model = new ConfigModel();
        $website_id = $website_id ? : $this->website_id;
        if (empty($default_return_key_array)) {
            $default_return_key_array = ['ORDER_AUTO_DELIVERY', 'ORDER_BALANCE_PAY', 'ORDER_DELIVERY_COMPLETE_TIME', 'ORDER_SHOW_BUY_RECORD', 'ORDER_INVOICE_TAX',
                'ORDER_INVOICE_CONTENT', 'ORDER_DELIVERY_PAY', 'BUYER_SELF_LIFTING', 'SHOPPING_BACK_POINTS', 'POINT_INVOICE_TAX', 'IS_POINT', 'INTEGRAL_CALCULATION',
                'ORDER_BUY_CLOSE_TIME', 'ORDER_IS_LOGISTICS', 'ORDER_SELLER_DISPATCHING'];
        }
        $config_info = $config_model::all(['instance_id' => $shop_id, 'website_id' => $website_id, 'key' => ['IN', $default_return_key_array]]);

        $return_array = [];
        foreach ($config_info as $c) {
            if (in_array($c['key'], $default_return_key_array)) {
                switch ($c['key']) {
                    case 'ORDER_BUY_CLOSE_TIME' :
                        $return_array['order_buy_close_time'] = $c['value'] ?: 60;
                        break;
                    case 'ORDER_IS_LOGISTICS' :
                        $return_array['is_logistics'] = $c['value'];
                        break;
                    case 'ORDER_SELLER_DISPATCHING':
                        $return_array['seller_dispatching'] = $c['value'] ?: '';
                        break;
                    default:
                        // 数组key == 字段key转小写 && 否定时为’‘ 的情况使用
                        $return_array[strtolower($c['key'])] = $c['value'] ?: '';
                }
                //得到 没有设置值 的数组，这些数组来源于function getShopConfig
                unset($default_return_key_array[array_search($c['key'], $default_return_key_array)]);
            }
        }
        unset($c);
        //有些店铺没设置一些包含在default_return_key_array里面的key，这种情况有可能导致使用的时候出现index不存在的错误，所以在这里给这些值设置为空
        foreach ($default_return_key_array as $rest) {
            $return_array[strtolower($rest)] = '';
        }
        unset($rest);
        return $return_array;
    }

    /*
     * 商城配置
     */

    public function setShopConfig($data)
    {
        $shop_id = $data['shop_id'];
        $order_auto_delivery = $data['order_auto_delivery'];
        $convert_rate = $data['convert_rate']; //积分抵扣 定义多少积分等于多少钱，用于退款退货
        $order_delivery_complete_time = $data['order_delivery_complete_time'];
        $order_buy_close_time = $data['order_buy_close_time'];
        $shopping_back_points = $data['shopping_back_points']; //购物返积分节点 1-订单已完成 2-已收货 3-支付完成
        $point_invoice_tax = $data['point_invoice_tax']; //购物返积分比例
        $is_point = $data['is_point']; //购物返积分是否开启 0-未开启 1-开启
        $integral_calculation = $data['integral_calculation']; //积分计算方式 1-订单总价 2-商品总价 3-实际支付金额
        $is_point_deduction = $data['is_point_deduction'];
        $is_beautiful_point_transfer = $data['is_beautiful_point_transfer'];
        $point_deduction_calculation = $data['point_deduction_calculation'];
        $point_deduction_max = $data['point_deduction_max'];
        $is_translation = $data['is_translation'];
        $translation_time = $data['translation_time'];
        $translation_text = $data['translation_text'];
        $is_transfer = $data['is_transfer'];
        $is_transfer_charge = $data['is_transfer_charge'];
        $charge_type = $data['charge_type'];
        $charge_pares = $data['charge_pares'];
        $charge_pares_min = $data['charge_pares_min'];
        $charge_pares2 = $data['charge_pares2'];
        $is_point_transfer = $data['is_point_transfer'];
        $is_point_transfer_charge = $data['is_point_transfer_charge'];
        $point_charge_type = $data['point_charge_type'];
        $point_charge_pares = $data['point_charge_pares'];
        $point_charge_pares_min = $data['point_charge_pares_min'];
        $point_charge_pares2 = $data['point_charge_pares2'];
        $has_express = $data['has_express'];
        $evaluate_give_point = $data['evaluate_give_point'];
        $point_num = $data['point_num'];
        $close_pay_password = $data['close_pay_password'];
        $pay_password_length = $data['pay_password_length'];


        //支付密码长度逻辑处理
        $payPassArr = $this->changePayPasswordLengthType($pay_password_length);

        $array[0] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'ORDER_AUTO_DELIVERY',
            'value' => $order_auto_delivery,
            'desc' => '订单多长时间自动收货',
            'is_use' => 1
        );
        $array[1] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'ORDER_DELIVERY_COMPLETE_TIME',
            'value' => $order_delivery_complete_time,
            'desc' => '收货后多长时间自动完成',
            'is_use' => 1
        );
        $array[2] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'ORDER_BUY_CLOSE_TIME',
            'value' => $order_buy_close_time,
            'desc' => '订单自动关闭时间',
            'is_use' => 1
        );
        $array[3] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_DEDUCTION_NUM',
            'value' => $convert_rate,
            'desc' => '积分抵扣额度',
            'is_use' => 1
        );
        $array[4] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_POINT',
            'value' => $is_point,
            'desc' => '购物返积分是否开启',
            'is_use' => 1
        );
        $array[5] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'SHOPPING_BACK_POINTS',
            'value' => $shopping_back_points,
            'desc' => '购物返积分节点设置', //1-订单已完成 2-已收货 3-支付完成
            'is_use' => 1
        );
        $array[6] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'INTEGRAL_CALCULATION',
            'value' => $integral_calculation,
            'desc' => '积分计算方式', //1-订单总价 2-商品总价 3-实际支付金额
            'is_use' => 1
        );
        $array[7] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_INVOICE_TAX',
            'value' => $point_invoice_tax,
            'desc' => '购物返积分利率设置',
            'is_use' => 1
        );
        $array[8] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_POINT_DEDUCTION',
            'value' => $is_point_deduction,
            'desc' => '积分抵扣是否开启',
            'is_use' => 1
        );
        $array[9] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_DEDUCTION_CALCULATION',
            'value' => $point_deduction_calculation,
            'desc' => '积分抵扣计算方式', //1-订单总价 2-商品总价 3-实际支付金额
            'is_use' => 1
        );
        $array[10] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_DEDUCTION__MAX',
            'value' => $point_deduction_max,
            'desc' => '积分最大抵扣',
            'is_use' => 1
        );
        //自动评论设置
        $array[11] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_TRANSLATION',
            'value' => $is_translation,
            'desc' => '是否开启自动评论',
            'is_use' => 1
        );
        $array[12] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'TRANSLATION_TIME',
            'value' => $translation_time,
            'desc' => '自动评论时间',
            'is_use' => 1
        );
        $array[13] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'TRANSLATION_TEXT',
            'value' => $translation_text,
            'desc' => '自动评论内容',
            'is_use' => 1
        );
        //余额转账设置

        $array[14] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_TRANSFER',
            'value' => $is_transfer,
            'desc' => '余额转账设置',
            'is_use' => 1
        );
        $array[15] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_TRANSFER_CHARGE',
            'value' => $is_transfer_charge,
            'desc' => '余额转账费率设置',
            'is_use' => 1
        );
        $array[16] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'CHARGE_TYPE',
            'value' => $charge_type,
            'desc' => '余额转账费率类型',
            'is_use' => 1
        );
        $array[17] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'CHARGE_PARES',
            'value' => $charge_pares,
            'desc' => '余额转账费率比例',
            'is_use' => 1
        );
        $array[18] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'CHARGE_PARES_MIN',
            'value' => $charge_pares_min,
            'desc' => '余额转账费率最低限制',
            'is_use' => 1
        );
        $array[19] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'CHARGE_PARES2',
            'value' => $charge_pares2,
            'desc' => '余额转账费率固定',
            'is_use' => 1
        );

        $array[20] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_POINT_TRANSFER',
            'value' => $is_point_transfer,
            'desc' => '积分余额转账设置',
            'is_use' => 1
        );
        $array[21] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_POINT_TRANSFER_CHARGE',
            'value' => $is_point_transfer_charge,
            'desc' => '积分余额转账费率设置',
            'is_use' => 1
        );
        $array[22] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_CHARGE_TYPE',
            'value' => $point_charge_type,
            'desc' => '积分余额转账费率类型',
            'is_use' => 1
        );
        $array[23] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_CHARGE_PARES',
            'value' => $point_charge_pares,
            'desc' => '积分余额转账费率比例',
            'is_use' => 1
        );
        $array[24] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_CHARGE_PARES_MIN',
            'value' => $point_charge_pares_min,
            'desc' => '积分余额转账费率最低限制',
            'is_use' => 1
        );
        $array[25] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_CHARGE_PARES2',
            'value' => $point_charge_pares2,
            'desc' => '积分余额转账费率固定',
            'is_use' => 1
        );
        $array[26] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'HAS_EXPRESS',
            'value' => $has_express,
            'desc' => '是否开启快递配送', //0:不开启  1:开启
            'is_use' => 1
        );
        $array[27] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'IS_EVALUATE_GIVE_POINT',
            'value' => $evaluate_give_point,
            'desc' => '是否开启好评送积分', //0:不开启  1:开启
            'is_use' => 1
        );
        $array[28] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'POINT_NUM',
            'value' => $point_num,
            'desc' => '好评送积分的数量',
            'is_use' => 1
        );
        $array[29] = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => 'CLOSE_PAY_PASSWORD',
            'value' => $close_pay_password,
            'desc' => '支付密码开关',
            'is_use' => 1
        );
        $array[30] = array(
            'instance_id' => 0,
            'website_id' => $this->website_id,
            'key' => 'PAY_PASSWORD_LENGTH',
            'value' => json_encode($payPassArr),
            'desc' => '支付密码长度',
            'is_use' => $close_pay_password == 1 ? 0 : 1/* 支付密码关闭，则该长度不需要用 */
        );
        $array[31] = array(
            'instance_id' => 0,
            'website_id' => $this->website_id,
            'key' => 'IS_BEAUTIFUL_POINT_TRANSFER',
            'value' => $is_beautiful_point_transfer,
            'desc' => '美丽分转赠',
            'is_use' => 1
        );
        
        $res = $this->setConfig($array);
        return $res;
    }

    public function getShopConfig($shop_id, $website_id = '')
    {
        $order_auto_delivery = $this->getConfig($shop_id, 'ORDER_AUTO_DELIVERY', $website_id); //
        $order_delivery_complete_time = $this->getConfig($shop_id, 'ORDER_DELIVERY_COMPLETE_TIME', $website_id); //
        $convert_rate = $this->getConfig($shop_id, 'POINT_DEDUCTION_NUM', $website_id); //
        $order_buy_close_time = $this->getConfig($shop_id, 'ORDER_BUY_CLOSE_TIME', $website_id);
        $shopping_back_points = $this->getConfig($shop_id, 'SHOPPING_BACK_POINTS', $website_id);
        $point_invoice_tax = $this->getConfig($shop_id, 'POINT_INVOICE_TAX', $website_id);
        $is_point = $this->getConfig($shop_id, 'IS_POINT', $website_id);
        $integral_calculation = $this->getConfig($shop_id, 'INTEGRAL_CALCULATION', $website_id);
        $is_point_deduction = $this->getConfig($shop_id, 'IS_POINT_DEDUCTION', $website_id);
        $point_deduction_calculation = $this->getConfig($shop_id, 'POINT_DEDUCTION_CALCULATION', $website_id);
        $point_deduction_max = $this->getConfig($shop_id, 'POINT_DEDUCTION__MAX', $website_id);
        //获取自动评论设置
        $is_translation = $this->getConfig($shop_id, 'IS_TRANSLATION', $website_id);
        $translation_time = $this->getConfig($shop_id, 'TRANSLATION_TIME', $website_id);
        $translation_text = $this->getConfig($shop_id, 'TRANSLATION_TEXT', $website_id);
        //获取余额转账设置
        $is_transfer = $this->getConfig($shop_id, 'IS_TRANSFER', $website_id);
        $is_transfer_charge = $this->getConfig($shop_id, 'IS_TRANSFER_CHARGE', $website_id);
        $charge_type = $this->getConfig($shop_id, 'CHARGE_TYPE', $website_id);
        $charge_pares = $this->getConfig($shop_id, 'CHARGE_PARES', $website_id);
        $charge_pares_min = $this->getConfig($shop_id, 'CHARGE_PARES_MIN', $website_id);
        $charge_pares2 = $this->getConfig($shop_id, 'CHARGE_PARES2', $website_id);
        //获取积分余额转账设置
        $is_point_transfer = $this->getConfig($shop_id, 'IS_POINT_TRANSFER', $website_id);
        
        $is_point_transfer_charge = $this->getConfig($shop_id, 'IS_POINT_TRANSFER_CHARGE', $website_id);
        $point_charge_type = $this->getConfig($shop_id, 'POINT_CHARGE_TYPE', $website_id);
        $point_charge_pares = $this->getConfig($shop_id, 'POINT_CHARGE_PARES', $website_id);
        $point_charge_pares_min = $this->getConfig($shop_id, 'POINT_CHARGE_PARES_MIN', $website_id);
        $point_charge_pares2 = $this->getConfig($shop_id, 'POINT_CHARGE_PARES2', $website_id);
        //是否有快递配送
        $has_express = $this->getConfig($shop_id, 'HAS_EXPRESS', $website_id);
        //好评送积分
        $evaluate_give_point = $this->getConfig($shop_id, 'IS_EVALUATE_GIVE_POINT', $website_id);
        $point_num = $this->getConfig($shop_id, 'POINT_NUM', $website_id);
        $beautiful_point_transfer = $this->getConfig($shop_id, 'IS_BEAUTIFUL_POINT_TRANSFER', $website_id);
        //支付密码开关
        $close_pay_password = $this->getClosePayPassword($website_id);
        $pay_password_length = $this->getPayPasswordLength($website_id);

        $array = array(
            'order_auto_delivery' => $order_auto_delivery['value'] ? $order_auto_delivery['value'] : 0,
            'order_delivery_complete_time' => $order_delivery_complete_time['value'],
            'convert_rate' => $convert_rate['value'] ? $convert_rate['value'] : '',
            'order_buy_close_time' => $order_buy_close_time['value'] ? $order_buy_close_time['value'] : '',
            'shopping_back_points' => $shopping_back_points['value'] ? $shopping_back_points['value'] : 0,
            'point_invoice_tax' => $point_invoice_tax['value'] ? $point_invoice_tax['value'] : 0,
            'is_point' => $is_point['value'],
            'integral_calculation' => $integral_calculation['value'] ? $integral_calculation['value'] : 0,
            'is_point_deduction' => $is_point_deduction['value'],
            'point_deduction_calculation' => $point_deduction_calculation['value'] ? $point_deduction_calculation['value'] : 0,
            'point_deduction_max' => $point_deduction_max['value'] ? $point_deduction_max['value'] : 0,
            'is_translation' => $is_translation['value'] ? $is_translation['value'] : 0,
            'translation_time' => $translation_time['value'] ? $translation_time['value'] : 0,
            'translation_text' => $translation_text['value'] ? $translation_text['value'] : '',
            'is_transfer' => $is_transfer['value'] ? $is_transfer['value'] : 0,
            'is_transfer_charge' => $is_transfer_charge['value'] ? $is_transfer_charge['value'] : 0,
            'charge_type' => $charge_type['value'] ? $charge_type['value'] : 1,
            'charge_pares' => $charge_pares['value'] ? $charge_pares['value'] : 0,
            'charge_pares_min' => $charge_pares_min['value'] ? $charge_pares_min['value'] : 0,
            'charge_pares2' => $charge_pares2['value'] ? $charge_pares2['value'] : 0,
            'is_point_transfer' => $is_point_transfer['value'] ? $is_point_transfer['value'] : 0,
            'is_point_transfer_charge' => $is_point_transfer_charge['value'] ? $is_point_transfer_charge['value'] : 0,
            'point_charge_type' => $point_charge_type['value'] ? $point_charge_type['value'] : 1,
            'point_charge_pares' => $point_charge_pares['value'] ? $point_charge_pares['value'] : 0,
            'point_charge_pares_min' => $point_charge_pares_min['value'] ? $point_charge_pares_min['value'] : 0,
            'point_charge_pares2' => $point_charge_pares2['value'] ? $point_charge_pares2['value'] : 0,
            'has_express' => $has_express['value'],
            'evaluate_give_point' => $evaluate_give_point['value'],
            'point_num' => $point_num['value'],
            'close_pay_password' => $close_pay_password['value'] ?: 0,
            'is_beautiful_point_transfer' => $beautiful_point_transfer['value'] ?: 0,
            'pay_password_length' => $pay_password_length,
        );

        if ($array['order_buy_close_time'] == 0) {
            $array['order_buy_close_time'] = 60;
        }

        return $array;
    }

    /**
     * 修改状态
     * (non-PHPdoc)
     *
     * @see \data\api\IConfig::updateConfigEnable()
     */
    public function updateConfigEnable($id, $is_use)
    {
        $data = array(
            "is_use" => $is_use,
            "modify_time" => time()
        );
        $retval = $this->config_module->save($data, [
            "id" => $id
        ]);
        return $retval;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::setUploadType()
     * a端和源码用
     */

    public function setUploadType($value, $website_id = 0)
    {
        $params = array(
            'instance_id' => 0,
            'website_id' => $website_id,
            'key' => 'UPLOAD_TYPE',
            'value' => $value,
            'desc' => "上传方式 1 本地  2 阿里云",
            'is_use' => 1
        );
        $res = $this->setConfigOne($params);
        // TODO Auto-generated method stub
        return $res;
    }

    /**
     * 更新交易商户号顺序
     */
    public function setJoinpayQaConfig($joinPay)
    {
        $value = json_encode($joinPay);
        $res = $this->updateConfig($this->instance_id, 'JOINPAY', $value, '', 1, $this->website_id);
        return $res;
    }

    public function setDefaultAnti()
    {
        $notice_type_model = new NoticeTemplateTypeModel();
        //$sort = $notice_type_model->max('sort');
        $condition[0] = array('template_name' => '操作员忘记密码', 'template_code' => 'anti_forgot_password', 'template_type' => 'sms', 'notify_type' => 'operator', 'sort' => 0, 'sample' => '您的验证码是${code}，请保管好验证码并在15分钟内进行验证。', 'is_platform' => 1, 'website_id' => 0, 'sms_type' => 1);
        $notice_type_model->saveAll($condition);
        $item_model = new NoticeTemplateItemModel();
        $res = $item_model->where(['item_name' => '验证码'])->find();
        $type_ids = $res['type_ids'] . ',anti_forgot_password';
        $item_model->where(['item_name' => '验证码'])->update(['type_ids' => $type_ids]);
    }

    public function setGpayConfigMir($instanceid, $appid, $partner_code, $credential_code, $currency, $is_use)
    {
        $data = array(
            'appid' => $appid,
            'partner_code' => $partner_code,
            'credential_code' => $credential_code,
            'currency' => $currency,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'GPPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
    }

    public function setGpayConfig($instanceid, $appid, $partner_code, $credential_code, $currency, $is_use)
    {
        $data = array(
            'appid' => $appid,
            'partner_code' => $partner_code,
            'credential_code' => $credential_code,
            'currency' => $currency,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'GLOPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
    }

    /*
     * 获取后台设置订单通知手机
     */

    public function getWebsiteOrShopOrderMobile($website_id, $shop_id = 0)
    {
        $value = $this->getConfig($shop_id, 'MOBILEMESSAGE', $website_id, 1);
        if ($value) {
            $mobile = $value['order_mobile'];
            if ($mobile) {
                return $mobile;
            }
        }
        return '';
    }

    /*
     * 是否开启区块链EHT
     */

    public function isOpenBlockChainETH()
    {
        if (!getAddons('blockchain', $this->website_id, $this->instance_id)) {
            return false;
        }
        $blockSer = new BlockSer();
        $eth_open = $blockSer->isOpenBlockChainETHOrEOS($this->website_id, 1);
        return $eth_open;
    }

    /*
     * 是否开启区块链EOS
     */

    public function isOpenBlockChainEOS()
    {
        if (!getAddons('blockchain', $this->website_id, $this->instance_id)) {
            return false;
        }
        $blockSer = new BlockSer();
        $eos_open = $blockSer->isOpenBlockChainETHOrEOS($this->website_id, 2);
        return $eos_open;
    }

    /**
     * 支付相关文件复制
     * @param $dis_file 原文件地址 upload/26/0/wap/aa.key
     * @param $target_file 目标地址 upload/26/0/mp/aa.key
     * @return string
     */
    public function copyPayConfigFile($dis_file, $target_file)
    {
        $n_dis_file = $dis_file;
        $n_target_file = $target_file;
        try {
            if (substr($n_dis_file, 0, 1) == '/') {
                $n_dis_file = substr($n_dis_file, 1);
            }
            if (substr($n_target_file, 0, 1) == '/') {
                $n_target_file = substr($n_target_file, 1);
            }
            if (!is_dir($key_target_dir = pathinfo($n_target_file)['dirname'])) {
                @mkdir($key_target_dir, 0777, true);
            }
            $r_dis_file = str_replace('\\', '/', ROOT_PATH . $n_dis_file);
            $r_target_file = str_replace('\\', '/', ROOT_PATH . $n_target_file);
            copy($r_dis_file, $r_target_file);
            return AjaxReturn(SUCCESS, ['file' => $target_file]);
        } catch (\Exception $e) {
            return AjaxReturn(FAIL, [], $e->getMessage());
        }
    }

    /*
     * 获取商城设置的支付密码所需长度
     */

    public function getPayPasswordLength($website_id)
    {
        $website_id = $website_id ?: $this->website_id;
        $pay_length = 9;
        $pay_password_Info = $this->getConfig(0, 'PAY_PASSWORD_LENGTH', $website_id);
        if ($pay_password_Info) {
            $val_data = $pay_password_Info['value'];
            $pay_length = isset($val_data['now']) ?$val_data['now']: 0;
        }

        return $pay_length ?: 9;
    }

    /*
     * 获取商城设置的支付密码所需长度value值
     */

    public function getPayPasswordLengthValue($website_id)
    {
        $website_id = $website_id ?: $this->website_id;
        $return = [
            'prev' => 0,
            'now' => 0,
        ];
        $pay_password_Info = $this->getConfig(0, 'PAY_PASSWORD_LENGTH', $website_id);
        if ($pay_password_Info) {
            $return = $pay_password_Info['value'];
        }
        return $return;
    }

    /*
     * 商城是否使用支付密码 1使用 0不使用
     */

    public function isUsePayPassword($website_id)
    {
        //先判断是否开启支付密码，如果关闭了，则不验证密码长度（相当于密码可以为空）
        $close_pay_password = $this->getClosePayPassword($website_id);
        if ($close_pay_password) {
            if ($close_pay_password['value'] == 1) {
                return false;
            }
        }
        return true;
    }

    /**
     * 检测密码长度类型是否有修改过（默认空即原始9~20位， 6即长度，9即9位）
     * @param     $pay_password_length_now [修改时密码长度]
     * @param int $website_id
     * @return array
     */
    public function changePayPasswordLengthType($pay_password_length_now, $website_id = 0)
    {

        //0-0   0-9    9-6   9-0（不用密码支付）
        if ($pay_password_length_now == 1) {
            $pay_password_length_now = 9; //9位
        } elseif ($pay_password_length_now == 2) {
            $pay_password_length_now = 6;
        }
        $website_id = $website_id ?: $this->website_id;
        //先查询是否已经存储过
        $payPre = $this->getConfig(0, 'PAY_PASSWORD_LENGTH', $website_id);

        if (!$payPre) {
            return ['prev' => 0, 'now' => 0];
        }
        if ($payPre['value']) {
            $value_data = $payPre['value'];
            if (isset($value_data['prev'])) {
                if ($value_data['now'] == $pay_password_length_now) {
                    /* 说明没有改变，不需要修改 */
                    $return = $value_data;
                } else {
                    $return = [
                        'prev' => $value_data['now'],
                        'now' => $pay_password_length_now,
                    ];
                }
            } else {
                $return = [
                    'prev' => 0,
                    'now' => 0,
                ];
            }
            return $return;
        }
    }

    /*     * *************** 获取后台 支付配置 （优先级获取其中一个配置） 网页(type=1) > 小程序(type=2) > APP(type=3) *********************** */

    /** 1、余额支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopBpayConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $bpay = $this->getConfig($instance_id, 'BPAY', $website_id);
            if ($bpay) {
                if ($bpay['is_use'] == 1) {
                    return $bpay;
                }
            }
        }
        if (in_array($type, [0, 2])) {
            $bpay_mir = $this->getConfig($instance_id, 'BPAYMP', $website_id);
            if ($bpay_mir) {
                if ($bpay_mir['is_use'] == 1) {
                    return $bpay_mir;
                }
            }
        }
        if (in_array($type, [0, 3])) {
            $bpay_app = $this->getConfig($instance_id, 'BPAYAPP', $website_id);
            if ($bpay_app) {
                if ($bpay_app['is_use'] == 1) {
                    return $bpay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 2、货到付款
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopDpayConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $dpay = $this->getConfig($instance_id, 'DPAY', $website_id);
            if ($dpay) {
                if ($dpay['is_use'] == 1) {
                    return $dpay;
                }
            }
        }
        if (in_array($type, [0, 2])) {
            $dpay_mir = $this->getConfig($instance_id, 'DPAYMP', $website_id);
            if ($dpay_mir) {
                if ($dpay_mir['is_use'] == 1) {
                    return $dpay_mir;
                }
            }
        }
        if (in_array($type, [0, 3])) {
            $dpay_app = $this->getConfig($instance_id, 'DPAYAPP', $website_id);
            if ($dpay_app) {
                if ($dpay_app['is_use'] == 1) {
                    return $dpay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 3、微信支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopWpayConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $wpay = $this->getConfig($instance_id, 'WPAY', $website_id);
            if ($wpay) {
                if ($wpay['is_use'] == 1) {
                    return $wpay;
                }
            }
        }
        if (in_array($type, [0, 2])) {
            $wpay_mir = $this->getConfig($instance_id, 'MPPAY', $website_id);
            if ($wpay_mir) {
                if ($wpay_mir['is_use'] == 1) {
                    return $wpay_mir;
                }
            }
        }
        if (in_array($type, [0, 3])) {
            $wpay_app = $this->getConfig($instance_id, 'WPAYAPP', $website_id);
            if ($wpay_app) {
                if ($wpay_app['is_use'] == 1) {
                    return $wpay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 4、支付宝支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopAlipayConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $alipay = $this->getConfig($instance_id, 'ALIPAY', $website_id);
            if ($alipay) {
                if ($alipay['is_use'] == 1) {
                    return $alipay;
                }
            }
        }
        if (in_array($type, [0, 3])) {
            $alipay_app = $this->getConfig($instance_id, 'ALIPAYAPP', $website_id);
            if ($alipay_app) {
                if ($alipay_app['is_use'] == 1) {
                    return $alipay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 5、通联支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopTlConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $tlpay = $this->getConfig($instance_id, 'TLPAY', $website_id);
            if ($tlpay) {
                if ($tlpay['is_use'] == 1) {
                    return $tlpay;
                }
            }
        }
        if (in_array($type, [0, 2])) {
            $tlpay_mir = $this->getConfig($instance_id, 'TLPAYMP', $website_id);
            if ($tlpay_mir) {
                if ($tlpay_mir['is_use'] == 1) {
                    return $tlpay_mir;
                }
            }
        }
        if (in_array($type, [0, 3])) {
            $tlpay_app = $this->getConfig($instance_id, 'TLPAYAPP', $website_id);
            if ($tlpay_app) {
                if ($tlpay_app['is_use'] == 1) {
                    return $tlpay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 6、EOS支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopEosConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $eospay = $this->getConfig($instance_id, 'EOSPAY', $website_id);
            if ($eospay) {
                if ($eospay['is_use'] == 1) {
                    return $eospay;
                }
            }
        }

        if (in_array($type, [0, 3])) {
            $eospay_app = $this->getConfig($instance_id, 'EOSPAYAPP', $website_id);
            if ($eospay_app) {
                if ($eospay_app['is_use'] == 1) {
                    return $eospay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 7、ETH支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopEthConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $ethpay = $this->getConfig($instance_id, 'ETHPAY', $website_id);
            if ($ethpay) {
                if ($ethpay['is_use'] == 1) {
                    return $ethpay;
                }
            }
        }

        if (in_array($type, [0, 3])) {
            $ethpay_app = $this->getConfig($instance_id, 'ETHPAYAPP', $website_id);
            if ($ethpay_app) {
                if ($ethpay_app['is_use'] == 1) {
                    return $ethpay_app;
                }
            }
        }

        return ['value' => [], 'is_use' => 0];
    }

    /** 8、GlobePay支付
     * @param     $website_id [实例id]
     * @param     $instance_id [店铺id]
     * @param int $type [默认:优先级筛选。网页(type=1) > 小程序(type=2) > APP(type=3)]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getTopGpayConfig($website_id, $instance_id = 0, $type = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        if (in_array($type, [0, 1])) {
            $gpay = $this->getConfig($instance_id, 'GLOPAY', $website_id);
            if ($gpay) {
                if ($gpay['is_use'] == 1) {
                    return $gpay;
                }
            }
        }
        if (in_array($type, [0, 2])) {
            $gpay_mir = $this->getConfig($instance_id, 'GPPAY', $website_id);
            if ($gpay_mir) {
                if ($gpay_mir['is_use'] == 1) {
                    return $gpay_mir;
                }
            }
        }
        return ['value' => [], 'is_use' => 0];
    }

    /**
     * 设置线下转款支付,网页端:OFFLINEPAY,小程序端:OFFLINEPAYMP,APP端:OFFLINEPAYAPP
     */
    public function setOfflinePayConfig($shop_id, $is_use, $pay_name, $collection_code, $collection_memo, $key)
    {
        $data = array(
            'pay_name' => $pay_name,
            'collection_code' => $collection_code,
            'collection_memo' => $collection_memo
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $shop_id,
            'website_id' => $this->website_id,
            'key' => $key,
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
    }

    /**
     * 获取线下转款支付配置,网页端:OFFLINEPAY,小程序端:OFFLINEPAYMP,APP端:OFFLINEPAYAPP
     */
    public function getOfflinePayConfig($website_id = 0,$key)
    {
        if(empty($website_id)){
            $website_ids = $this->website_id;
        }else{
            $website_ids = $website_id;
        }
        $info = $this->config_module->getInfo([
            'instance_id' => 0,
            'website_id' => $website_ids,
            'key' => $key
        ], 'value,is_use');
        if (empty($info['value'])) {
            return array(
                'value' => array(
                    'pay_name' => '',
                    'collection_code' => '',
                    'collection_memo' => ''
                ),
                'is_use' => 0
            );
        } else {
            $info['value'] = json_decode($info['value'], true);
            return $info;
        }
    }
    /*     * add 汇聚支付 2020/12/03 */

    /**
     * 设置汇聚支付
     */
    public function setJoinpayConfig($instanceid, $p1_MerchantNo = '', $qa_TradeMerchantNo = '', $hmacVal = '', $ali_use = 0, $wx_use = 0, $is_use = 0, $joinpaytw_is_use = 0, $joinpaytw_is_auto = 0, $fastpay_is_use = 0, $fastpay_public_key = '', $fastpay_private_key = '', $joinpay_alt_is_use = '', $joinpay_alt_is_auto = '', $port = '', $joinpay_wx_appId = '')
    {
        if ($port == 'mp') {
            $check_key = 'MPJOINPAY';
        } else if ($port == 'app') {
            $check_key = 'JOINPAYAPP';
        } else {
            $check_key = 'JOINPAY';
        }
        $data = array(
            'p1_MerchantNo' => $p1_MerchantNo,
            'qa_TradeMerchantNo' => $qa_TradeMerchantNo,
            'hmacVal' => $hmacVal,
            'joinpay_wx_appId' => $joinpay_wx_appId,
            'ali_use' => $ali_use,
            'wx_use' => $wx_use,
            'joinpaytw_is_use' => $joinpaytw_is_use,
            'joinpaytw_is_auto' => $joinpaytw_is_auto,
            'fastpay_is_use' => $fastpay_is_use,
            'fastpay_public_key' => $fastpay_public_key,
            'fastpay_private_key' => $fastpay_private_key,
            'joinpay_alt_is_use' => $joinpay_alt_is_use,
            'joinpay_alt_is_auto' => $joinpay_alt_is_auto,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => $check_key,
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
    }

    //Mir
    public function setJoinpayConfigMir($instanceid, $p1_MerchantNo = '', $qa_TradeMerchantNo = '', $hmacVal = '', $ali_use = 0, $wx_use = 0, $is_use = 0, $joinpaytw_is_use = 0, $joinpaytw_is_auto = 0, $fastpay_is_use = 0, $fastpay_public_key = '', $fastpay_private_key = '', $joinpay_alt_is_use = '', $joinpay_alt_is_auto = '')
    {
        $data = array(
            'p1_MerchantNo' => $p1_MerchantNo,
            'qa_TradeMerchantNo' => $qa_TradeMerchantNo,
            'hmacVal' => $hmacVal,
            'ali_use' => $ali_use,
            'wx_use' => $wx_use,
            'joinpaytw_is_use' => $joinpaytw_is_use,
            'joinpaytw_is_auto' => $joinpaytw_is_auto,
            'fastpay_is_use' => $fastpay_is_use,
            'fastpay_public_key' => $fastpay_public_key,
            'fastpay_private_key' => $fastpay_private_key,
            'joinpay_alt_is_use' => $joinpay_alt_is_use,
            'joinpay_alt_is_auto' => $joinpay_alt_is_auto,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'MPJOINPAY',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
    }

    //APP
    public function setJoinpayConfigAPP($instanceid, $p1_MerchantNo = '', $qa_TradeMerchantNo = '', $hmacVal = '', $ali_use = 0, $wx_use = 0, $is_use = 0, $joinpaytw_is_use = 0, $joinpaytw_is_auto = 0, $fastpay_is_use = 0, $fastpay_public_key = '', $fastpay_private_key = '', $joinpay_alt_is_use = '', $joinpay_alt_is_auto = '')
    {
        $data = array(
            'p1_MerchantNo' => $p1_MerchantNo,
            'qa_TradeMerchantNo' => $qa_TradeMerchantNo,
            'hmacVal' => $hmacVal,
            'ali_use' => $ali_use,
            'wx_use' => $wx_use,
            'joinpaytw_is_use' => $joinpaytw_is_use,
            'joinpaytw_is_auto' => $joinpaytw_is_auto,
            'fastpay_is_use' => $fastpay_is_use,
            'fastpay_public_key' => $fastpay_public_key,
            'fastpay_private_key' => $fastpay_private_key,
            'joinpay_alt_is_use' => $joinpay_alt_is_use,
            'joinpay_alt_is_auto' => $joinpay_alt_is_auto,
        );
        $value = json_encode($data);
        $params = array(
            'instance_id' => $instanceid,
            'website_id' => $this->website_id,
            'key' => 'JOINPAYAPP',
            'value' => $value,
            'is_use' => $is_use
        );
        $res = $this->setConfigOne($params);
        return $res;
    }

    /**
     * 检测开启状况
     */
    public function checkStatus($key = '', $type = '', $instanceid = 0, $port = '')
    {
        if (empty($key)) {
            return false;
        }
        $check_key = $key;
        if ($port == 'mp' && $key == 'WPAY') {
            $check_key = 'MPPAY';
        }
        if ($port == 'mp' && $key == 'JOINPAY') {
            $check_key = 'MP' . $key;
        }
        if ($port == 'app') {
            $check_key = $key . 'APP';
        }

        $info = $this->getConfig($instanceid, $check_key, $this->website_id);
        if (empty($info)) {
            return true;
        } else {
            //  ALIPAY  JOINPAY
            if ($key == 'WPAY' || $key == 'ALIPAY' || $key == 'TLPAY') {
                return $info['is_use'] == 1 ? false : true;
            } else if ($key == 'JOINPAY' && $type == 'WPAY') {
                $values = $info['value'];
                return $values['wx_use'] == 1 ? false : true;
            } else if ($key == 'JOINPAY' && $type == 'ALIPAY') {
                $values = $info['value'];
                return $values['ali_use'] == 1 ? false : true;
            } else if ($key == 'JOINPAY' && $type == 'TLPAY') {
                $values = $info['value'];
                return $values['fastpay_is_use'] == 1 ? false : true;
            }
        }
    }

    /**
     * 冲突关闭微信、支付宝、银行卡设置
     */
    public function changePayConfig($key, $type = '', $port = '')
    {
        if (empty($key)) {
            return;
        }

        if ($type == 'JOINPAY') {
            if ($port == 'mp') {
                $check_key = 'MPJOINPAY';
            } else if ($port == 'app') {
                $check_key = 'JOINPAYAPP';
            } else {
                $check_key = 'JOINPAY';
            }
            $info = $this->getConfig($this->instance_id, $check_key, $this->website_id);

            if (empty($info)) {
                return;
            }
            $detail = $info['value'];
            if ($key == 'WPAY') {
                $detail['wx_use'] = 0;
            }
            if ($key == 'ALIPAY') {
                $detail['ali_use'] = 0;
            }
            if ($key == 'TLPAY') {
                $detail['fastpay_is_use'] = 0;
            }

            $value = json_encode($detail);
            $this->updateConfig($this->instance_id, $check_key, $value, '', $info['is_use'], $this->website_id);
        } else {
            if ($port == 'mp' && $key == 'WPAY') {
                $check_key = 'MPPAY';
            }
            if ($port == 'app') {
                $check_key = $key . 'APP';
            }
            $data_info = $this->getConfig($this->instance_id, $check_key, $this->website_id);
            if (empty($data_info)) {
                return;
            }
            //更新数据
            $data = array(
                'key' => $check_key,
                'value' => $data_info['value'],
                'is_use' => 0,
                'modify_time' => time()
            );
            $this->updateConfig($this->instance_id, $check_key, $data_info['value'], '', 0, $this->website_id);
        }
        return;
    }
/**
     * 获取手机端自定义模板
     *
     * {@inheritdoc}
     *
     * @see \data\api\IConfig::getCustomTeplateList()
     */
    function getCustomTemplateInfo($condition)
    {
        $custom_template_model = new CustomTemplateModel();
        $res = $custom_template_model->getInfo($condition, '*');
        return $res;
    }

    /**
     * 新增模板页面
     * @param array $data
     *
     * @return int $res
     */
    public function createCustomTemplate(array $data)
    {
        $custom_template_model = new CustomTemplateModel();
        $res = $custom_template_model->save($data);
        return $res;
    }

    /**
     * 添加手机端自定义模板
     *
     * {@inheritdoc}
     *
     * @see \data\api\IConfig::saveCustomTemplate()
     */
    public function saveCustomTemplate(array $data, $id, $type = '')
    {
        $custom_template_model = new CustomTemplateModel();
        if (!$id) {
            $return = $custom_template_model->save($data);
        } else {
            $return = $custom_template_model->save($data, ['id' => $id]);
            if($type == 9 && getAddons('appshop', $this->website_id)){
                $app_custom_template_model = new AppCustomTemplate();
                $default_conf0['type'] = 9;
                $default_conf0['is_default'] = 1;
                $default_conf0['in_use'] = 1;
                $default_conf0['website_id'] = $this->website_id;
                //判断是否存在type为9的装修数据
                $app_custom_info = $app_custom_template_model->getInfo($default_conf0);
                if(!$app_custom_info){
                    //取出移动端默认的数据装修名字
                    $default_conf['type'] = 9;
                    $default_conf['is_system_default'] = 1;
                    $template_name = $custom_template_model->getInfo($default_conf, 'template_name')['template_name'];
                    $appcustom['template_name'] = $template_name;
                    $appcustom['template_data'] = $data['template_data'];
                    $appcustom['create_time'] = time();
                    $appcustom['modify_time'] = time();
                    $appcustom['website_id'] = $this->website_id;
                    $appcustom['type'] = 9;
                    $appcustom['is_default'] = 1;
                    $appcustom['in_use'] = 1;
                    $app_custom_template_model->save($appcustom);
                }
            }
        }
        return $return;
    }

    /**
     * 获取手机端自定义模板列表
     *
     * {@inheritdoc}
     *
     * @see \data\api\IConfig::saveCustomTemplate()
     */
    public function getCustomTemplateList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $custom_template_model = new CustomTemplateModel();
        return $custom_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
    }

    /**
     * 获取模板数目
     *
     * @param $condition
     */
    public function getCustomTemplateCount($condition)
    {
        $custom_template_model = new CustomTemplateModel();
        return $custom_template_model->getCount($condition);
    }

    /**
     * 初始化店铺(平台)装修模板
     *
     * @param $website_id
     * @param $shop_id
     */
    public function initCustomTemplate($website_id, $shop_id)
    {
        $custom_template_model = new CustomTemplateModel();
        $condition['is_system_default'] = 1;
        if ($shop_id > 0) {
            // 店铺的初始化可装修的页面只有店铺首页和商品详情页
            $condition['type'] = ['IN', [2, 3]];
        }
        $system_default_list = $custom_template_model->getQuery($condition, '*', '');
        $data = [];
        foreach ($system_default_list as $k => $v) {
            if ($v['is_default'] == 1){
                $data[$k]['template_name'] = $v['template_name'];
                $data[$k]['template_data'] = $v['template_data'];
                $data[$k]['website_id'] = $website_id;
                $data[$k]['shop_id'] = $shop_id;
                $data[$k]['create_time'] = time();
                $data[$k]['modify_time'] = time();
                $data[$k]['type'] = $v['type'];
                $data[$k]['is_default'] = 1;
                $data[$k]['in_use'] = 1;
            }
        }
        unset($v);
        if (!empty($data)) {
            $custom_template_model->saveAll($data);
        }
    }

    /**
     * 设置手机端默认模板
     * @param int|string $id
     * @param int|string $type
     * @param int|string $shop_id
     * @param int|string $website_id
     *
     * @return int result
     *
     * @see \data\api\IConfig::useCustomTemplate()
     */
    public function useCustomTemplate($id, $type, $shop_id =0, $website_id = '')
    {
        $website_id = $website_id ?: $this->website_id;
        $shop_id = $shop_id ?: $this->instance_id;
        $custom_config = new CustomTemplateModel();
        $custom_config->startTrans();
        try {
            $custom_config->save(['in_use' => 0], ['type' => $type, 'in_use' => 1, 'shop_id' => $shop_id, 'website_id' => $website_id, 'id' => ['NEQ', $id]]);
            $custom_config->save(['in_use' => 1], ['id' => $id]);
            $custom_config->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            //var_dump($e->getMessage());
            $custom_config->rollback();
            return UPDATA_FAIL;
        }
    }

    /**
     * 设置商品详情模板
     * 2018年5月23日 11:16:34
     *
     * {@inheritdoc}
     *
     * @see \data\api\IConfig::setDefaultCustomTemplate()
     */
    public function setGoodsdetailTemplate($id, $shop_id)
    {
        if (!$id) {
            return UPDATA_FAIL;
        }
        $custom_template_model = new CustomTemplateModel();
        $data['modify_time'] = time();
        $data['is_enable'] = 0;
        $condition['shop_id'] = $shop_id;
        $condition['range'] = 2;
        $condition['website_id'] = $this->website_id;
        $result = $custom_template_model->save($data, $condition);
        if (!$result) {
            return UPDATA_FAIL;
        }
        $condition['id'] = $id;
        $res = $custom_template_model->save(['modify_time' => time(), 'is_enable' => 1], $condition);
        return $res;
    }
    
    /**
     * 删除自定义模板
     * 2017年7月31日 11:16:34
     *
     * {@inheritdoc}
     *
     * @see \data\api\IConfig::deleteCustomTemplateById()
     */
    public function deleteCustomTemplateById($condition)
    {
        
        $custom_template_model = new CustomTemplateModel();
        return $custom_template_model->destroy($condition);
    }
    /**
     * 商城支付密码开关
     */
    public function getClosePayPassword ($website_id = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        $result =  $this->getConfig(0,'CLOSE_PAY_PASSWORD',$website_id);
        if (currentEnv() == 1) {
            //如果第三种账号体系且关闭手机绑定则不需要支付密码
            $website = new WebSite();
            $webInfo = $website->getWebInfo($website_id, 'account_type,is_bind_phone');
            
            if ($webInfo['account_type'] == 3 && $webInfo['is_bind_phone'] == 0) {
                $result['value'] = 1;
            }
        }
        return $result;
    }
    
    /**
     * 获取商城logo信息
     * @param int $website_id
     * @return type
     */
    public function getLogoConfig()
    {
        return $this->getConfigMaster(0,'LOGO_CONFIG',0,true);
    }
    
     /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::databaseList()
     */
    public function getDatabaseList()
    {
        // TODO Auto-generated method stub
        $databaseList = Db::query("SHOW TABLE STATUS");
        return $databaseList;
    }
    
     /*
     * (non-PHPdoc)
     * @see \data\api\IConfig::getUploadType()
     */
    public function getUploadType($website_id=0)
    {
        // TODO Auto-generated method stub
        $upload_type = $this->config_module->getInfo([
            "key" => "UPLOAD_TYPE",
            "instance_id" => 0,
            "website_id" => $website_id,
        ], "*");
        if (empty($upload_type)) {
            $sqlData = array(
                'instance_id' => 0,
                'website_id' => $website_id,
                'key' => "UPLOAD_TYPE",
                'value' => 1,
                'desc' => "上传方式 1 本地  2 阿里云",
                'is_use' => 1,
                'create_time' => time()
            );
            $res = $this->config_module->save($sqlData);
            return 1;
        } else {
            return $upload_type['value'];
        }
    }

    public function setAliossConfig($value,$website_id=0)
    {
        
        $sqlData = array(
            'instance_id' => 0,
            'website_id' => $website_id,
            'key' => "ALIOSS_CONFIG",
            'value' => json_encode($value),
            'desc' => "阿里云云存储参数配置",
            'is_use' => 1,
            'create_time' => time()
        );
        $res = $this->setConfigOne($sqlData);
        return $res;
    }
    
     public function setCompanyConfig($keyValue)
    {
        $config = new ConfigModel();
        $info = $config->getInfo(['key' => 'COMPANYCONFIGSET', 'website_id' => $this->website_id], '*');
        if($info){
            $array = array(
                'instance_id' => 0,
                'website_id' => $this->website_id,
                'key' => 'COMPANYCONFIGSET',
                'value' => $keyValue,
                'modify_time'=>time(),
                'desc' => '物流配置',
                'is_use' => 1
            );
            $res = $config->save($array,['id'=>$info['id']]);
        }else{
            $array = array(
                'instance_id' => 0,
                'website_id' => $this->website_id,
                'key' => 'COMPANYCONFIGSET',
                'value' => $keyValue,
                'create_time'=>time(),
                'desc' => '物流配置',
                'is_use' => 1
            );
            $res = $config->save($array);
        }
        return $res;
    }
    
    public function setRedisConfig($shop_id,$host, $pass)
    {
            $value = array(
                'host' => $host,
                'pass' => $pass,
            );
            $data = array(
                'instance_id' => 0,
                'website_id' => 0,
                'key' => 'REDIS',
                'value' => json_encode($value),
                'desc' => 'redis配置',
                'is_use' => 1
            );
            $info= $this->config_module->getInfo(['instance_id' => 0,'website_id' => 0,'key'=>'REDIS']);
            if(empty($info)){
                $res = $this->config_module->save($data);
            }else{
                $res = $this->config_module->save($data,['instance_id' => 0,'website_id' => 0,'key'=>'REDIS']);
            }

        return $res;
    }
    /*
     * 微信开放平台设置
     */
    public function setWechatOpenConfig($shop_id,$open_appid, $open_secrect, $open_key, $open_token)
    {
            $value = array(
                'open_appid' => $open_appid,
                'open_secrect' => $open_secrect,
                'open_key' => $open_key,
                'open_token' => $open_token,
            );
            $data = array(
                'instance_id' => $shop_id,
                'website_id' => $this->website_id,
                'key' => 'WECHATOPEN',
                'value' => json_encode($value),
                'desc' => '微信开放平台配置',
                'is_use' => 1
            );
            $res = $this->setConfigOne($data);

        return $res;
    }
}