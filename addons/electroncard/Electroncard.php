<?php

namespace addons\electroncard;

use addons\Addons;
use addons\electroncard\model\VslElectroncardBaseModel;
use addons\electroncard\model\VslElectroncardCategoryModel;
use addons\electroncard\server\Electroncard as ElectroncardServer;

class Electroncard extends Addons
{
    public $info = array(
        'name' => 'electroncard',//插件名称标识
        'title' => '电子卡密',//插件中文名
        'description' => '电子卡密商品的数据库',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'no_set' => 1,//不需要应用设置
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'electroncardBase',//
        'config_admin_hook' => 'electroncardBase', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/km170.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/km48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/km48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '电子卡密',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '电子卡密商品的数据库',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBase',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '卡密库',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBase',
            'module' => 'platform'
        ],
        [
            'module_name' => '卡密库分类',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardCategory',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBaseSetting',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加或编辑卡密库',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addOrUpdateElectroncardBase',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加数据',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addData',
            'module' => 'platform'
        ],
        [
            'module_name' => '卡密库详情',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBaseDetail',
            'module' => 'platform'
        ],
        [
            'module_name' => '编辑卡密库数据',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'editElectroncardData',
            'module' => 'platform'
        ],

        //admin
        [
            'module_name' => '电子卡密',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '电子卡密商品的数据库',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBase',
            'module' => 'admin',
            'is_admin_main' => 1//c端应用页面主入口标记
        ],
        [
            'module_name' => '卡密库',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBase',
            'module' => 'admin'
        ],
        [
            'module_name' => '卡密库分类',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardCategory',
            'module' => 'admin'
        ],
        [
            'module_name' => '添加或编辑卡密库',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addOrUpdateElectroncardBase',
            'module' => 'admin'
        ],
        [
            'module_name' => '添加数据',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addData',
            'module' => 'admin'
        ],
        [
            'module_name' => '卡密库详情',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'electroncardBaseDetail',
            'module' => 'admin'
        ],
        [
            'module_name' => '编辑卡密库数据',
            'parent_module_name' => '电子卡密',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'editElectroncardData',
            'module' => 'admin'
        ],
    );

    public function __construct()
    {
        parent::__construct();
        if ($this->module == 'platform') {
            $this->assign('addOrUpdateCategoryUrl', __URL(addons_url_platform('electroncard://Electroncard/addOrUpdateCategory')));
            $this->assign('electroncardCategoryListUrl', __URL(addons_url_platform('electroncard://Electroncard/electroncardCategoryList')));
            $this->assign('delElectroncardCategoryUrl', __URL(addons_url_platform('electroncard://Electroncard/delElectroncardCategory')));
            $this->assign('baseSettingUrl', __URL(addons_url_platform('electroncard://Electroncard/electroncardBaseSetting')));
            $this->assign('saveSettingUrl', __URL(addons_url_platform('electroncard://Electroncard/saveSetting')));
            $this->assign('electroncardBaseListUrl', __URL(addons_url_platform('electroncard://Electroncard/electroncardBaseList')));
            $this->assign('addOrUpdateElectroncardBaseUrl', __URL(addons_url_platform('electroncard://Electroncard/addOrUpdateElectroncardBase')));
            $this->assign('delElectroncardBaseUrl', __URL(addons_url_platform('electroncard://Electroncard/delElectroncardBase')));
            $this->assign('autoFillUrl', __URL(addons_url_platform('electroncard://Electroncard/autoFill')));
            $this->assign('addElectroncardDataUrl', __URL(addons_url_platform('electroncard://Electroncard/addElectroncardData')));
            $this->assign('downTplUrl', __URL(addons_url_platform('electroncard://Electroncard/downTpl')));
            $this->assign('electroncardBaseDetailUrl', __URL(addons_url_platform('electroncard://Electroncard/electroncardBaseDetail')));
            $this->assign('delElectroncardDataUrl', __URL(addons_url_platform('electroncard://Electroncard/delElectroncardData')));
            $this->assign('editElectroncardDataUrl', __URL(addons_url_platform('electroncard://Electroncard/editElectroncardData')));
            $this->assign('updateElectroncardDataUrl', __URL(addons_url_platform('electroncard://Electroncard/updateElectroncardData')));
            $this->assign('dataExcelUrl', __URL(addons_url_platform('electroncard://Electroncard/dataExcel')));
            $this->assign('uploadFileUrl', __URL(addons_url_platform('electroncard://Electroncard/uploadFile')));
        }else{
            $this->assign('addOrUpdateCategoryUrl', __URL(addons_url_admin('electroncard://Electroncard/addOrUpdateCategory')));
            $this->assign('electroncardCategoryListUrl', __URL(addons_url_admin('electroncard://Electroncard/electroncardCategoryList')));
            $this->assign('delElectroncardCategoryUrl', __URL(addons_url_admin('electroncard://Electroncard/delElectroncardCategory')));
            $this->assign('baseSettingUrl', __URL(addons_url_admin('electroncard://Electroncard/electroncardBaseSetting')));
            $this->assign('saveSettingUrl', __URL(addons_url_admin('electroncard://Electroncard/saveSetting')));
            $this->assign('electroncardBaseListUrl', __URL(addons_url_admin('electroncard://Electroncard/electroncardBaseList')));
            $this->assign('addOrUpdateElectroncardBaseUrl', __URL(addons_url_admin('electroncard://Electroncard/addOrUpdateElectroncardBase')));
            $this->assign('delElectroncardBaseUrl', __URL(addons_url_admin('electroncard://Electroncard/delElectroncardBase')));
            $this->assign('autoFillUrl', __URL(addons_url_admin('electroncard://Electroncard/autoFill')));
            $this->assign('addElectroncardDataUrl', __URL(addons_url_admin('electroncard://Electroncard/addElectroncardData')));
            $this->assign('downTplUrl', __URL(addons_url_admin('electroncard://Electroncard/downTpl')));
            $this->assign('electroncardBaseDetailUrl', __URL(addons_url_admin('electroncard://Electroncard/electroncardBaseDetail')));
            $this->assign('delElectroncardDataUrl', __URL(addons_url_admin('electroncard://Electroncard/delElectroncardData')));
            $this->assign('editElectroncardDataUrl', __URL(addons_url_admin('electroncard://Electroncard/editElectroncardData')));
            $this->assign('updateElectroncardDataUrl', __URL(addons_url_admin('electroncard://Electroncard/updateElectroncardData')));
            $this->assign('dataExcelUrl', __URL(addons_url_admin('electroncard://Electroncard/dataExcel')));
            $this->assign('uploadFileUrl', __URL(addons_url_admin('electroncard://Electroncard/uploadFile')));
            $this->assign('adminDataExcelUrl', __URL(addons_url_admin('electroncard://Electroncard/adminDataExcel')));
        }

    }

    /**
     * 卡密库
     */
    public function electroncardBase()
    {
        $electroncard_category_mdl = new VslElectroncardCategoryModel();
        $list = $electroncard_category_mdl->getQuery(['website_id' => $this->website_id, 'shop_id' => $this->instance_id], '*', '');
        $this->assign('category_list', $list);
        $this->fetch('template/' . $this->module . '/electroncardBase');
    }

    /**
     * 卡密库分类
     */
    public function electroncardCategory()
    {
        $this->fetch('template/' . $this->module . '/electroncardCategory');
    }

    /**
     * 基础设置
     */
    public function electroncardBaseSetting()
    {
        $this->fetch('template/' . $this->module . '/baseSetting');
    }

    /**
     * 添加或编辑卡密库
     */
    public function addOrUpdateElectroncardBase()
    {
        $id = $_GET['id'];
        if($id) {
            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $info = $electroncard_base_mdl->getInfo(['id' => $id],'*');
            $info['key_name'] = explode(',',$info['key_name']);
        }
        $electroncard_category_mdl = new VslElectroncardCategoryModel();
        $list = $electroncard_category_mdl->getQuery(['website_id' => $this->website_id, 'shop_id' => $this->instance_id], '*', '');
        $this->assign('category_list', $list);
        $this->assign('id', $id);
        $this->assign('data', $info);
        $this->fetch('template/' . $this->module . '/addOrUpdateElectroncardBase');
    }

    /**
     * 添加数据
     */
    public function addData()
    {
        $id = $_GET['id'];
        $electroncard_base_mdl = new VslElectroncardBaseModel();
        $info = $electroncard_base_mdl->getInfo(['id' => $id],'electroncard_base_name,key_name');
        $info['key_name'] = explode(',',$info['key_name']);
        $num = count($info['key_name']) + 1;
        $this->assign('info', $info);
        $this->assign('num', $num);
        $this->assign('id', $id);
        $this->fetch('template/' . $this->module . '/addData');
    }

    /**
     * 卡密库详情
     */
    public function electroncardBaseDetail()
    {
        $id = $_GET['id'];
        $electroncard_server = new ElectroncardServer;
        $info = $electroncard_server->getElectroncardBaseDetail($id);
        $this->assign('id', $id);
        $this->assign('info', $info);
        $this->fetch('template/' . $this->module . '/electroncardBaseDetail');
    }

    /**
     * 编辑卡密库数据
     */
    public function editElectroncardData()
    {
        $id = $_GET['id'];
        $electroncard_server = new ElectroncardServer;
        $info = $electroncard_server->getElectroncardDataDetail($id);
        $this->assign('info', $info);
        $this->fetch('template/' . $this->module . '/editElectroncardData');
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