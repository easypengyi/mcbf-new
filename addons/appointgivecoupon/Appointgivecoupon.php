<?php

namespace addons\appointgivecoupon;

use addons\Addons;

class Appointgivecoupon extends Addons
{
    public $info = array(
        'name' => 'appointgivecoupon',//插件名称标识
        'title' => '定向送券',//插件中文名
        'description' => '给指定的人群赠送优惠券',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'no_set' => 1,//不需要应用设置
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'appointgivecouponBaseSetting',//
        'config_admin_hook' => 'appointgivecouponBaseSetting', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/dxsq170.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/dxsq48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/dxsq48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '定向送券',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '给指定的人群赠送优惠券',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'appointgivecouponBaseSetting',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'appointgivecouponBaseSetting',
            'module' => 'platform'
        ],
        [
            'module_name' => '送券记录',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'giveCouponRecord',
            'module' => 'platform'
        ],
        [
            'module_name' => '送券记录详情',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'giveCouponRecordDetail',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加优惠券',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'addCoupon',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加赠品',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'appointAddGift',
            'module' => 'platform'
        ],
        //admin
        [
            'module_name' => '定向送券',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '给指定的人群赠送优惠券',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'appointgivecouponBaseSetting',
            'module' => 'admin',
            'is_admin_main' => 1//c端应用页面主入口标记
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'appointgivecouponBaseSetting',
            'module' => 'admin'
        ],
        [
            'module_name' => '送券记录',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'giveCouponRecord',
            'module' => 'admin'
        ],
        [
            'module_name' => '送券记录详情',
            'parent_module_name' => '定向送券',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'giveCouponRecordDetail',
            'module' => 'admin'
        ],
    );

    public function __construct()
    {
        parent::__construct();
        $this->assign('couponListUrl', __URL(call_user_func('addons_url_' . $this->module, 'coupontype://Coupontype/couponTypeList')));
        $this->assign('giftListUrl', __URL(call_user_func('addons_url_' . $this->module, 'giftvoucher://Giftvoucher/giftvoucherlist')));
        $this->assign('saveBasicSettingUrl', __URL(call_user_func('addons_url_' . $this->module, 'appointgivecoupon://Appointgivecoupon/saveBasicSetting')));
        $this->assign('messagePushUrl', __URL(call_user_func('addons_url_' . $this->module, 'appointgivecoupon://Appointgivecoupon/messagePush')));
        $this->assign('saveMessagePushUrl', __URL(call_user_func('addons_url_' . $this->module, 'appointgivecoupon://Appointgivecoupon/saveMessagePush')));
        $this->assign('giveCouponRecordUrl', __URL(call_user_func('addons_url_' . $this->module, 'appointgivecoupon://Appointgivecoupon/giveCouponRecord')));
        $this->assign('giveCouponRecordDetailUrl', __URL(call_user_func('addons_url_' . $this->module, 'appointgivecoupon://Appointgivecoupon/giveCouponRecordDetail')));
    }

    /**
     * 基础设置
     */
    public function appointgivecouponBaseSetting()
    {
        //判断有没有开启优惠券应用
        if (getAddons('coupontype', $this->website_id, $this->instance_id)) {
            $has_coupon = 1;
        } else {
            $has_coupon = 0;
        }
        //判断有没有开启礼品券应用
        if (getAddons('giftvoucher', $this->website_id, $this->instance_id)) {
            $has_gift = 1;
        } else {
            $has_gift = 0;
        }
        //获取赠送方式对应的会员数据
        $appointgivecoupon = new \addons\appointgivecoupon\server\Appointgivecoupon();
        $typeList = $appointgivecoupon->getUserSendTypesCombineList();

        $this->assign('has_coupon', $has_coupon);
        $this->assign('has_gift', $has_gift);
        $this->assign('list', $typeList);
        $this->assign('distribution', getAddons('distribution', $this->website_id));
        $this->fetch('template/' . $this->module . '/appointgivecouponBaseSetting');
    }

    /**
     * 送券记录
     */
    public function giveCouponRecord()
    {
        $give_type = [
            ['id' => 1, 'value' => '按会员等级赠送'],
            ['id' => 2, 'value' => '按会员标签赠送'],
            ['id' => 4, 'value' => '按会员ID赠送'],
            ['id' => 5, 'value' => '赠送所有会员'],
        ];
        if (getAddons('distribution', $this->website_id)) {
            array_push($give_type, ['id' => 3, 'value' => '按分销商等级赠送']);
        }
        $this->assign('give_type', $give_type);
        $this->fetch('template/' . $this->module . '/giveCouponRecord');
    }

    /**
     * 送券记录详情
     */
    public function giveCouponRecordDetail()
    {
        $record_id = request()->get('record_id');
        $this->assign('record_id', $record_id);
        $this->fetch('template/' . $this->module . '/giveCouponRecordDetail');
    }
    /**
     * 添加优惠券的弹窗
     */
    public function addCoupon()
    {
        
        $this->fetch('template/' . $this->module . '/selectModal');
    }

    /**
     * 添加礼品券的弹窗
     */
    public function appointAddGift()
    {
        $this->fetch('template/' . $this->module . '/selectGiftModal');
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