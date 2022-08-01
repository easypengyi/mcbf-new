<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008
 * Time: 11:16
 */

namespace addons\storecashier\controller;

use addons\storecashier\Storecashier as baseStoreCashier;
use data\service\AddonsConfig;
use data\service\Config;

class StoreCashier extends baseStoreCashier
{
    public function __construct()
    {
        parent::__construct();
    }
    /*
     * 小票机设置
     */
    public function saveSetting()
    {
        $config = new Config();
        $addons_service = new AddonsConfig();
        $post_data = request()->post();
        if(!$post_data['user'] || !$post_data['ukey']){
            return AjaxReturn(-1006);
        }
        $params = array(
            'instance_id' => $this->instance_id,
            'website_id' => $this->website_id,
            'key' => 'PRINTER_INFO',
            'value' => $post_data,
            'desc' => '小票机设置',
            'is_use' => 1
        );
        $config_param[0] = $params;
        $res = $config->setConfig($config_param);
        $params['addons'] = self::$addons_name;
        $addons_service->setAddonsConfig($params);
        if(!$res){
            return AjaxReturn(0);
        }
        setAddons('storecashier', $this->website_id, $this->instance_id);

        $this->addUserLog('小票机设置',$res);
        return AjaxReturn($res);
    }
}