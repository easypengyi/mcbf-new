<?php
namespace addons\membercard;

use addons\Addons;
use addons\distribution\model\VslDistributorLevelModel;
use addons\membercard\model\VslMembercardUserModel;

class Membercard extends Addons
{
    public $info = array(
        'name' => 'membercard',//插件名称标识
        'title' => '会员卡',//插件中文名
        'description' => '可领取至微信卡包的',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'membercardList',//
        'logo' => 'https://pic.vslai.com.cn/upload/common/hyk170.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/hyk48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/hyk48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '会员卡',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'membercardList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '会员卡',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'membercardList',
            'module' => 'platform'
        ],
        [
            'module_name' => '充值送',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'rechargeLevelList',
            'module' => 'platform'
        ],
        [
            'module_name' => '领卡记录',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'memberMembercard',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'membercardSetting',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加或编辑会员卡',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addOrUpdateMembercard',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加或编辑充值送',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可领取至微信卡包的',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addOrUpdateRechargeLevel',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加优惠券',
            'parent_module_name' => '会员卡',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'addCouponMemberCard',
            'module' => 'platform'
        ],
    );

    public function __construct()
    {
        parent::__construct();
        if ($this->module == 'platform') {
            $this->assign('saveSettingUrl', __URL(addons_url_platform('membercard://Membercard/saveSetting')));
            $this->assign('addOrUpdateRechargeLevelUrl', __URL(addons_url_platform('membercard://Membercard/addOrUpdateRechargeLevel')));
            $this->assign('rechargeLevelListUrl', __URL(addons_url_platform('membercard://Membercard/rechargeLevelList')));
            $this->assign('delRechargeLevelUrl', __URL(addons_url_platform('membercard://Membercard/delRechargeLevel')));
            $this->assign('addOrUpdateMembercardUrl', __URL(addons_url_platform('membercard://Membercard/addOrUpdateMembercard')));
            $this->assign('couponListUrl', __URL(addons_url_platform('coupontype://Coupontype/couponTypeList')));
            $this->assign('membercardListUrl', __URL(addons_url_platform('membercard://Membercard/membercardList')));
            $this->assign('delMembercardUrl', __URL(addons_url_platform('membercard://Membercard/delMembercard')));
            $this->assign('memberMembercardUrl', __URL(addons_url_platform('membercard://Membercard/memberMembercard')));
            $this->assign('adjustBalanceUrl', __URL(addons_url_platform('membercard://Membercard/adjustBalance')));
            $this->assign('memberMembercardDataExcelUrl', __URL(addons_url_platform('membercard://Membercard/memberMembercardDataExcel')));
        }
    }

    /**
     * 会员卡列表
     */
    public function membercardList()
    {
        return $this->fetch('/template/' . $this->module . '/membercardList');
    }

    /**
     * 添加或编辑会员卡
     */
    public function addOrUpdateMembercard()
    {
        $id = $_GET['id'];
        if ($id) {
            $membercard = new \addons\membercard\server\Membercard();
            $data = $membercard->getMembercardDetail($id);
            if($data['commission_val']) {
                $data['commission_val'] = json_decode($data['commission_val'],true);
                foreach ($data['commission_val'] as $k => $v) {
                    $data['commission_val'][$k] = explode(',',$v);
                }
            }
            if($data['commission_buy_val']) {
                $data['commission_buy_val'] = json_decode($data['commission_buy_val'],true);
                foreach ($data['commission_buy_val'] as $k => $v) {
                    $data['commission_buy_val'][$k] = explode(',',$v);
                }
            }
            if($data['commission_renew_val']) {
                $data['commission_renew_val'] = json_decode($data['commission_renew_val'],true);
                foreach ($data['commission_renew_val'] as $k => $v) {
                    $data['commission_renew_val'][$k] = explode(',',$v);
                }
            }
        }

        $this->assign('id', $id);
        $this->assign('data', $data);
        $this->assign('spec_length', count($data['spec_list']));
        $level_list = '';
        if(getAddons('distribution',$this->website_id)) {
            $dis_level = new VslDistributorLevelModel();
            $level_list = $dis_level->getQuery(['website_id' => $this->website_id], 'level_name,id', 'id asc');
        }
        $this->assign("level_list", objToArr($level_list));
        return $this->fetch('template/' . $this->module . '/addOrUpdateMembercard');
    }

    /**
     * 充值送
     */
    public function rechargeLevelList()
    {
        return $this->fetch('/template/' . $this->module . '/rechargeLevelList');
    }

    /**
     * 添加或编辑充值送
     */
    public function addOrUpdateRechargeLevel()
    {
        $id = $_GET['id'];
        if ($id) {
            $membercard = new \addons\membercard\server\Membercard();
            $data = $membercard->getrechargeLevelDetail($id);
        }
        $this->assign('id', $id);
        $this->assign('website', $data);
        return $this->fetch('template/' . $this->module . '/addOrUpdateRechargeLevel');
    }

    /**
     * 基础设置
     */
    public function membercardSetting()
    {
        $addonsConfigSer = new \data\service\AddonsConfig();
        $info = $addonsConfigSer->getAddonsConfig('membercard', $this->website_id);
        if ($info) {
            $data = $info['value'];
            if($data['wxcard_info']['store_service']) {
            $data['wxcard_info']['store_service'] = implode(',',$data['wxcard_info']['store_service']);
            }
            $data['is_use'] = $info['is_use'];
        }
        $this->assign('info', $data);
        return $this->fetch('/template/' . $this->module . '/membercardSetting');
    }

    /**
     * 领卡记录
     */
    public function memberMembercard()
    {
        $membercard_user_mdl = new VslMembercardUserModel();
        $user_count_num = $membercard_user_mdl->getCount(['website_id' => $this->website_id]);
        $user_membercard_balance = $membercard_user_mdl->getSum(['website_id' => $this->website_id],'membercard_balance');
        $this->assign('user_count_num', $user_count_num);
        $this->assign('user_membercard_balance', $user_membercard_balance);
        return $this->fetch('/template/' . $this->module . '/memberMembercard');
    }
    /**
     * 添加优惠券的弹窗
     */
    public function addCouponMemberCard()
    {
        $this->fetch('template/' . $this->module . '/selectModal');
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