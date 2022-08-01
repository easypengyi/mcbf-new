<?php

namespace addons\Orderbarrage;

use addons\Addons;
use \addons\orderbarrage\server\OrderBarrage as OrderBarrageServer;

/**
 * 订单弹幕
 * Class Orderbarrage
 * @package addons\Orderbarrage
 */
class Orderbarrage extends Addons
{
    protected static $addons_name = 'orderbarrage';

    public $info = array(
        'name'              => 'orderbarrage',//插件名称标识
        'title'             => '订单弹幕',//插件中文名
        'description'       => '真实与虚拟数据相结合的弹幕',//插件描述
        'status'            => 1,//状态 1使用 0禁用
        'author'            => 'vslaishop',// 作者
        'version'           => '1.0',//版本号
        'has_addonslist'    => 1,//是否有下级插件
        'no_set'            => 1,//不需要应用设置
        'content'           => '',//插件的详细介绍或者使用方法
        'config_hook'       => 'orderBarrageSetting',//
        'logo'              => 'https://pic.vslai.com.cn/upload/common/dm_170.png',
        'logo_small'        => 'https://pic.vslai.com.cn/upload/common/dm_48.png',
        'logo_often'        => 'https://pic.vslai.com.cn/upload/common/dm_48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name'           => '订单弹幕',
            'parent_module_name'    => '应用',//上级模块名称 用来确定上级目录
            'sort'                  => 0,//菜单排序
            'is_menu'               => 1,//是否为菜单
            'is_dev'                => 0,//是否是开发模式可见
            'desc'                  => '真实与虚拟数据相结合的弹幕',//菜单描述
            'module_picture'        => '',//图片（一般为空）
            'icon_class'            => '',//字体图标class（一般为空）
            'is_control_auth'       => 1,//是否有控制权限
            'hook_name'             => 'orderBarrageSetting',
            'module'                => 'platform',
            'is_main'               => 1
        ],
        [
            'module_name'           => '基础设置',
            'parent_module_name'    => '订单弹幕',//上级模块名称 用来确定上级目录
            'sort'                  => 1,//菜单排序
            'is_menu'               => 1,//是否为菜单
            'is_dev'                => 0,//是否是开发模式可见
            'desc'                  => '',//菜单描述
            'module_picture'        => '',//图片（一般为空）
            'icon_class'            => '',//字体图标class（一般为空）
            'is_control_auth'       => 1,//是否有控制权限
            'hook_name'             => 'orderBarrageSetting',
            'module'                => 'platform'
        ],

        //admin
    );

    public function __construct()
    {
        parent::__construct();
        if ($this->module == 'platform' || $this->module == 'admin'){
            $this->assign('patchOrderBarrageSettingUrl', __URL(addons_url('orderbarrage://Orderbarrage/patchOrderBarrageSetting')));
            $this->assign('deleteOrderBarrageRuleUrl', __URL(addons_url('orderbarrage://Orderbarrage/deleteOrderBarrageRule')));
            $this->assign('getOrderBarrageRuleUrl', __URL(addons_url('orderbarrage://Orderbarrage/getOrderBarrageRule')));
        }
    }

    /**基础设置
     * @throws \Exception
     */
    public function orderBarrageSetting()
    {
        $barrage = new OrderBarrageServer();
        //初始化虚拟数据
        $barrage->initVirtualTableData();
        $barrage_info = $barrage->getOrderBarrageConfigInfo([
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ]);

        $rule_list =[];
        if ($barrage_info) {
            $rule_list = $barrage->getOrderBarrageRulesList(
                [
                    'website_id' => $this->website_id,
                    'shop_id' => $this->instance_id,
                    'config_id' => $barrage_info['id']
                ]);
            if ($rule_list){
                $rule_list = objToArr($rule_list);
                foreach ($rule_list as $key => $rule) {
//                $time = date('H:i:s', $rule['start_time']) . '-' . date('H:i:s', $rule['end_time']);
                    $rule_list[$key]['time'] = '';
                    if ($rule['start_time'] && $rule['end_time']){
                        $start_arr = explode(',',$rule['start_time']);
                        $end_arr   = explode(',',$rule['end_time']);
                        //处理时间
                        $start_time = date('H:i:s', mktime($start_arr[0],$start_arr[1],$start_arr[2]));
                        $end_time  = date('H:i:s', mktime($end_arr[0],$end_arr[1],$end_arr[2]));
                        $rule_list[$key]['time'] = $start_time.' - '.$end_time;
                    }
                }
            }else{
                $rule_list[0] = [
                    'time' => '',
                    'virtual_num' => '',
                    'start_time' => '',
                    'end_time' => '',
                    'space_end_time' => '',
                ];
            }
        }
        $this->assign('barrage_info', $barrage_info);
        $this->assign('rule_info', $rule_list);
        $this->fetch('template/' . $this->module . '/addOrderBarrageSetting');
    }

    /**
     * 安装方法
     */
    public function install()
    {
        // TODO: Implement install() method.


        return true;
    }

    /**
     * 卸载方法
     */
    public function uninstall()
    {

        return true;
        // TODO: Implement uninstall() method.
    }
}