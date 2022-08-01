<?php
namespace addons\caltimegoods;

use addons\Addons;

class Caltimegoods extends Addons
{
    public $info = array(
        'name' => 'caltimegoods',//插件名称标识
        'title' => '计时/次商品',//插件中文名
        'description' => '',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'no_set' => 1,//不需要应用设置
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'caltimeGoods',//
        'config_admin_hook' => 'caltimeGoods', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/jsjc170.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/jsjc48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/jsjc48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '计时/次商品',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'caltimeGoods',
            'module' => 'platform',
            'is_main' => 1
        ],
        
        //admin
        [
            'module_name' => '计时/次商品',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'caltimeGoods',
            'module' => 'admin',
            'is_admin_main' => 1//c端应用页面主入口标记
        ],
    );

    public function __construct()
    {
        parent::__construct();
    }
    
    public function caltimeGoods()
    {
        if ($this->module == 'platform') {
            $redirect = (__URL(__URL__.'/'. PLATFORM_MODULE . "/goods/addgoods"));
        }else{
            $redirect = (__URL(__URL__.'/'. ADMIN_MODULE . "/goods/addgoods"));
        }

        header('location:' . $redirect);
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