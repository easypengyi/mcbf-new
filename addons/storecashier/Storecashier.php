<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008
 * Time: 11:16
 */

namespace addons\storecashier;

use addons\Addons;
use data\service\Config;

class Storecashier extends Addons
{
    protected static $addons_name = 'storecashier';

    public $info = array(
        'name' => 'storecashier',//插件名称标识
        'title' => '门店收银',//插件中文名
        'description' => '线下门店收银系统',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'storeCashierSetting',//
        'config_admin_hook' => 'storeCashierSetting', //
        'no_set' => 1,//是否有应用设置
        'logo' => 'https://pic.vslai.com.cn/upload/common/mdsy170.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/mdsy48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/mdsy48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '门店收银',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => '',//上一个菜单名称 用来确定菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '线下门店收银系统',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'storeCashierSetting',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '小票机设置',
            'parent_module_name' => '门店收银', //上级模块名称 确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '线下门店收银系统设置。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'storeCashierSetting',
            'module' => 'platform'
        ],
        //admin
    );

    public function __construct()
    {
        parent::__construct();
        if ($this->module == 'platform' || $this->module == 'admin') {
            $this->assign('saveSettingUrl', __URL(call_user_func('addons_url_' . $this->module, 'storecashier://StoreCashier/saveSetting')));
        }
    }

    /**
     * 小票机设置
     * @throws \Exception
     */
    public function storeCashierSetting()
    {
        $config = new Config();
        $printerInfo = $config->getConfig($this->instance_id,'PRINTER_INFO', $this->website_id, 1);
        $this->assign('printerInfo', $printerInfo);
        $this->fetch('template/' . $this->module . '/storeCashierSetting');
    }
    
    public function run(){
    
    }
    /**
     * 安装方法
     */
    public function install()
    {
        return true;
    }

    /**
     * 卸载方法
     */
    public function uninstall()
    {
        return true;
    }
}