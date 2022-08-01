<?php

namespace data\service;

/**
 * 系统配置业务层
 */

use data\model\AddonsConfigModel as AddonsConfigModel;
use data\model\SysAddonsModel;
use data\service\BaseService as BaseService;

class AddonsConfig extends BaseService
{

    private $config_module;

    function __construct()
    {
        parent::__construct();
        $this->config_module = new AddonsConfigModel();
    }

    /**
     * 设置应用配置
     * @param     $params
     * @param int $shop_id
     * @return false|int
     */
    public function setAddonsConfig($params, $shop_id=0)
    {
        if ($this->checkConfigKeyIsset($params['addons'],$shop_id)) {
            $res = $this->updateAddonsConfig($params['value'], $params['desc'], $params['is_use'], $params['addons'], $shop_id);
        } else {
            $res = $this->addAddonsConfig($params['value'], $params['desc'], $params['is_use'], $params['addons'], $shop_id);
        }
        return $res;
    }

    /**
     * 查询应用配置
     * @param     $addons
     * @param int $website_id
     * @param int $shop_id [店铺id]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getAddonsConfig($addons,$website_id=0,$shop_id=0, $onlyValue = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        //$redis = connectRedis();
        //$session = $redis->get($website_id.'_'.$shop_id.'_'.$addons.'_addons_config');
        //$status = $redis->get($website_id.'_'.$shop_id.'_'.$addons.'_status');
        if($status && $session != null && $session != 'null' && $session != ''){
            $info = json_decode($session, true);
        } else{
            $info = $this->config_module->getInfo([
                'website_id' => $website_id,
                'shop_id' => $shop_id,
                'addons' => $addons
            ]);
            //$redis->set($website_id.'_'.$shop_id.'_'.$addons.'_addons_config',json_encode($info,true));
            //$redis->set($website_id.'_'.$shop_id.'_'.$addons.'_status',1);
        }
        if (!$info) {
            return [];
        }
        if ($onlyValue) {
            $value = $info['value'] ? (is_array($info['value']) ? $info['value'] : json_decode($info['value'], true)) : [];
            return $value;
        }
        $info['value'] = is_array($info['value']) ? $info['value'] : json_decode($info['value'], true);
        return $info;
    }

    /**
     * 添加设置
     * @param        $value
     * @param        $desc
     * @param        $is_use
     * @param string $addons
     * @param int    $shop_id [店铺id]
     * @return false|int
     */
    public function addAddonsConfig($value, $desc, $is_use, $addons = '',$shop_id = 0)
    {
        $website_id = $this->website_id ?: 0;
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $data = array(
            'website_id' => $website_id,
            'shop_id' => $shop_id,
            'value' => $value,
            'desc' => $desc,
            'is_use' => $is_use,
            'create_time' => time(),
            'addons' => $addons
        );
        $res = $this->config_module->save($data);
        //$redis = connectRedis();
        //$redis->set($website_id.'_'.$shop_id.'_'.$addons.'_status',0);
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
    public function updateAddonsConfig($value, $desc, $is_use,$addons,$shop_id=0)
    {
        $website_id = $this->website_id ?: 0;
        if (is_array($value)) {
            $value = json_encode($value);
        }
        $data = array(
            'value' => $value,
            'desc' => $desc,
            'is_use' => $is_use,
            'modify_time' => time()
        );
        $res = $this->config_module->save($data, [
            'website_id' => $website_id,
            'shop_id' => $shop_id,
            'addons' => $addons
        ]);
        //$redis = connectRedis();
        //$redis->set($website_id.'_'.$shop_id.'_'.$addons.'_status',0);
        return $res;
    }

    /**
     * 判断当前设置是否存在
     * @param     $addons
     * @param int $shop_id
     * @return bool [存在返回 true 不存在返回 false]
     */
    public function checkConfigKeyIsset($addons,$shop_id=0)
    {

        $website_id = $this->website_id ?: 0;
        $num = $this->config_module->where([
            'website_id' => $website_id,
            'shop_id' => $shop_id,
            'addons' => $addons
        ])->count();
        return $num > 0 ? true : false;
    }
    
    /**
     * 查询某个应用（包含所有店铺）
     */
    public function getAddonsConfigList ($addons,$website_id,$field='*')
    {
        $info = $this->config_module->getQuery([
            'website_id' => $website_id,
            'addons' => $addons
        ],$field);
        
        return $info;
    }
    
    /**
     * 是否至少有一个应用启用（因为可能shop_id=0的关闭，其他shop_id的启用）
     * @param $addons
     * @param $website_id
     * @return bool
     */
    public function isAddonsIsLeastOne ($addons,$website_id)
    {
        $addonsList = $this->getAddonsConfigList($addons,$website_id);
        if ($addonsList){
            foreach($addonsList as $addons){
                if ($addons['is_use'] == 1){
                    return true;
                }
            }
            unset($addons);
        }
        return false;
        
    }
    
    /**
     * 是否店铺应用后台启用
     * @param     $addons
     * @param int $website_id
     * @param int $shop_id
     * @return bool
     */
    public function isAddonsIsUsed ($addons,$website_id=0,$shop_id=0)
    {
        $addonsConfigRes = $this->getAddonsConfig($addons,$website_id,$shop_id);
        if (!$addonsConfigRes){
            return false;
        }
        if ($addonsConfigRes['is_use'] != 1){
            return false;
        }
        return true;
    }
    
    /**
     * 查询应用信息
     * @param        $addons_name [应用名]
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getSysAddonsInfoByName ($addons_name,$field='*')
    {
        $addons = new SysAddonsModel();
        return $addons->getInfo(['name' => $addons_name],$field);
    }
}