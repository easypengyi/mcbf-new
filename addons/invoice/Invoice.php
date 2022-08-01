<?php

namespace addons\invoice;

use addons\Addons;
use addons\invoice\model\VslInvoiceConfigModel;

/**
 * 发票助手
 * Class Invoice
 * @package addons\goodhelper
 */
class Invoice extends Addons
{
    protected static $addons_name = 'invoice';

    public $info = array(
        'name' => 'invoice',//插件名称标识
        'title' => '发票助手',//插件中文名
        'description' => '电子普通发票，增值税专用发票',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'no_set' => 1,//不需要应用设置
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'invoiceList',//
        'config_admin_hook' => 'invoiceList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/1583392484.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1583392361.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1583392441.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '发票助手',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '电子普通发票，增值税专用发票',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'invoiceList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '开票列表',
            'parent_module_name' => '发票助手', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'invoiceList',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '发票助手',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'invoiceSetting',
            'module' => 'platform'
        ],

        //admin
        [
            'module_name' => '发票助手',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '电子普通发票，增值税专用发票',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'invoiceList',
            'module' => 'admin',
            'is_admin_main' => 1
        ],
        [
            'module_name' => '开票列表',
            'parent_module_name' => '发票助手', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'invoiceList',
            'module' => 'admin'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '发票助手',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'invoiceSetting',
            'module' => 'admin'
        ],

    );

    public function __construct()
    {
        parent::__construct();
        if ($this->module == 'platform' || $this->module == 'admin'){
            $this->assign('postInvoiceSettingUrl', __URL(addons_url('invoice://Invoice/postInvoiceSetting')));
            $this->assign('invoiceListUrl', __URL(addons_url('invoice://Invoice/invoiceList')));
            $this->assign('getInvoicesCountUrl', __URL(addons_url('invoice://invoice/getInvoicesCount')));
            $this->assign('invoiceConfirmImportUrl', __URL(addons_url('invoice://invoice/invoiceConfirmImport')));
            $this->assign('invoiceBatchConfirmImportUrl', __URL(addons_url('invoice://invoice/invoiceBatchConfirmImport')));
            $this->assign('invoiceExportExcelUrl', __URL(addons_url('invoice://invoice/invoiceExportExcel')));
            $this->assign('updateInvoiceStatusUrl', __URL(addons_url('invoice://invoice/updateInvoiceStatus')));
        }
    }

    public function invoiceList()
    {
        $status = request()->get('status', 0);
        $this->assign('status', $status);
        $this->fetch('template/' . $this->module . '/invoiceList');
    }
    /**
     * 基础设置
     */
    public function invoiceSetting()
    {
        $invoiceModel = new VslInvoiceConfigModel();
        $invoice_info = $invoiceModel::get(['website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
        $this->assign('invoice_info', $invoice_info);

        return $this->fetch('/template/' . $this->module . '/invoiceSetting');
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