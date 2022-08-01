<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008
 * Time: 11:16
 */

namespace addons\mplive;

use addons\Addons;

class Mplive extends Addons
{
    public $info = array(
        'name' => 'mplive',//插件名称标识
        'title' => '小程序直播',//插件中文名
        'description' => '属于小程序的购物直播',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'mplive_list',//
        'config_admin_hook' => 'mpliveList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/mzb48.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/xcx48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/mzb48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '小程序直播',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '属于小程序的购物直播',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'mpliveList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '直播列表',
            'parent_module_name' => '小程序直播', //上级模块名称 确定上级目录
            'sort' => 1, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'mpliveList',
            'module' => 'platform'
        ],
        [
            'module_name' => '创建直播间',
            'parent_module_name' => '小程序直播', //上级模块名称 确定上级目录
            'sort' => 1, // 菜单排序
            'is_menu' => 0, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'createMplive',
            'module' => 'platform'
        ],
        [
            'module_name' => '直播商品库',
            'parent_module_name' => '小程序直播', //上级模块名称 确定上级目录
            'sort' => 1, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'mpliveGoodsList',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '小程序直播', //上级模块名称 确定上级目录
            'sort' => 9, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'mpliveSetting',
            'module' => 'platform'
        ],
        [
            'module_name' => '商品选择',
            'parent_module_name' => '小程序直播',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'modalMpliveGoodsList',
            'module' => 'platform'
        ],
        [
            'module_name' => '选择直播间商品',
            'parent_module_name' => '小程序直播',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'modalGoods',
            'module' => 'platform'
        ],
    );

    public function __construct()
    {
        parent::__construct();
        $this->assign('website_id', $this->website_id);
        $this->assign('instance_id', $this->instance_id);
        $this->assign("pageshow", PAGESHOW);
        if ($this->module == 'platform' || $this->module == 'platform_new') {
            $this->assign('saveMpliveBasicSetting', __URL(addons_url_platform('mplive://Mplive/saveMpliveBasicSetting')));
            $this->assign('createMplive', __URL(addons_url_platform('mplive://Mplive/createMplive')));
            $this->assign('modalGoods', __URL(addons_url_platform('mplive://Mplive/modalGoods')));
            $this->assign('getRoomCode', __URL(addons_url_platform('mplive://Mplive/getRoomCode')));
            $this->assign('selectedGoods', __URL(addons_url_platform('mplive://Mplive/selectedGoods')));
            $this->assign('uploadMpliveGoods', __URL(addons_url_platform('mplive://Mplive/uploadMpliveGoods')));
            $this->assign('getMpLiveList', __URL(addons_url_platform('mplive://Mplive/getMpLiveList')));
            $this->assign('isPlatformUpdateData', __URL(addons_url_platform('mplive://Mplive/isPlatformUpdateData')));
            $this->assign('modalMpliveGoodsList', __URL(addons_url_platform('mplive://Mplive/modalMpliveGoodsList')));
            $this->assign('submitGoods', __URL(addons_url_platform('mplive://Mplive/submitGoods')));
            $this->assign('getMpLiveGoodsList', __URL(addons_url_platform('mplive://Mplive/getMpLiveGoodsList')));
            $this->assign('getGoodsLibraryInfo', __URL(addons_url_platform('mplive://Mplive/getGoodsLibraryInfo')));
            $this->assign('updateMpliveGoods', __URL(addons_url_platform('mplive://Mplive/updateMpliveGoods')));
            $this->assign('updateGoodsStatus', __URL(addons_url_platform('mplive://Mplive/updateGoodsStatus')));
            $this->assign('deleteMpliveGoods', __URL(addons_url_platform('mplive://Mplive/deleteMpliveGoods')));
        }
    }
    /*
     * 基础设置
     * **/
    public function mpliveList()
    {

        $this->fetch('template/'.$this->module.'/mpliveList');
    }
    public function mpliveGoodsList()
    {

        $this->fetch('template/'.$this->module.'/mpliveGoodsList');
    }
    /*
     * 基础设置
     * **/
    public function mpliveSetting()
    {
        //获取基础设置
        $addonsConfigSer = new \data\service\AddonsConfig();
        $config_info = $addonsConfigSer->getAddonsConfig('mplive', $this->website_id);
        $config_info_arr = $config_info['value'];
        $config_info_arr['is_mplive_use'] = $config_info['is_use'];
        $this->assign('basic_setting', $config_info_arr);
        $this->fetch('template/'.$this->module.'/mpliveSetting');
    }
    /**
     * 获取商品供小程序商品选择
     * @return
     */
    public function modalMpliveGoodsList()
    {
        $this->fetch('template/' . $this->module . '/mpliveGoodsDialog');
    }

    /**
     * 创建直播间
     */
    public function createMplive()
    {
        //获取当前到6个月的时间。
        $start_date = date('Y-m-d H:i:s');
        $end_date = date('Y-m-d H:i:s', strtotime("+6 month"));
        $this->assign('start_date', $start_date);
        $this->assign('end_date', $end_date);
        $this->fetch('template/' . $this->module . '/updateMplive');
    }
    // 商品弹窗
    public function modalGoods() {
        $roomid = request()->get('roomid');
        $this->assign('roomid', $roomid);
        $this->assign('getMpLiveGoodsLabriry', __URL(addons_url_platform('mplive://Mplive/getMpLiveGoodsListForPick')));
        $this->fetch('template/'. $this->module . '/popupGoodsDialog');
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