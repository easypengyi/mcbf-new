<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008
 * Time: 11:16
 */

namespace addons\seckill;

use addons\Addons;
use addons\seckill\server\Seckill as SeckServer;
use addons\distribution\model\VslDistributorLevelModel;
use addons\bonus\model\VslAgentLevelModel;
class Seckill extends Addons
{
    public $info = array(
        'name' => 'seckill',//插件名称标识
        'title' => '每日秒杀',//插件中文名
        'description' => '整点秒杀、限时抢购',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'secKillList',//
        'config_admin_hook' => 'secKillList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/1554197081.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1563782133.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1563782256.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '每日秒杀',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '整点秒杀、限时抢购',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'secKillList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '秒杀列表',
            'parent_module_name' => '每日秒杀', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '每日秒杀以超低的价格限时限量抢购商品，引流更多的会员，带动店铺的整体销量', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'secKillList',
            'module' => 'platform'
        ],
        [
            'module_name' => '进行中活动商品',
            'parent_module_name' => '每日秒杀', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 0, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '当前正在秒杀当中的活动商品。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'todaySeckillList',
            'module' => 'platform'
        ],
        [
            'module_name' => '待审核列表',
            'parent_module_name' => '每日秒杀', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 0, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '入驻店铺报名的活动商品需要在该页面进行审核，也可以在【秒杀设置】设置为自动审核。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'uncheckSeckillList',
            'module' => 'platform'
        ],
        [
            'module_name' => '已审核列表',
            'parent_module_name' => '每日秒杀', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 0, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '入驻店已审核的活动商品在该页面进行查看。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'checkedSeckillList',
            'module' => 'platform'
        ],
        [
            'module_name' => '秒杀统计',
            'parent_module_name' => '每日秒杀', //上级模块名称 确定上级目录
            'sort' => 1, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '可指定查看某个时段秒杀商品的销售统计。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'secKillCount',
            'module' => 'platform'
        ],
        [
            'module_name' => '修改秒杀',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'updateSecKill',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加秒杀',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '商品添加秒杀活动之后，不能对活动里商品进行编辑，请谨慎填写。',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addSecKill',
            'module' => 'platform'
        ],
        [
            'module_name' => '删除秒杀',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'deleteSecKill',
            'module' => 'platform'
        ],
        [
            'module_name' => '秒杀使用记录',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'historySeck',
            'module' => 'platform'
        ],
        [
            'module_name' => '秒杀详情',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'secKillInfo',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'seckSetting',
            'module' => 'platform'
        ],
        [
            'module_name' => '商品选择',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'seckillGoodsDialog',
            'module' => 'platform'
        ],
        [
            'module_name' => '删除商品记录日志',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'modalSeckillDelGoodsRecord',
            'module' => 'platform'
        ],
        [
            'module_name' => '商品sku详情',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'seckillGoodsDetailDialog',
            'module' => 'platform'
        ],

        //admin
        [
            'module_name' => '每日秒杀',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '整点秒杀、限时抢购',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'secKillList',
            'module' => 'admin',
            'is_admin_main' => 1//c端应用页面主入口标记
        ],
        [
            'module_name' => '秒杀列表',
            'parent_module_name' => '每日秒杀', //上级模块名称 确定上级目录
            'sort' => 0, // 菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '每日秒杀以超低的价格限时限量抢购商品，引流更多的会员，带动店铺的整体销量', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'secKillList',
            'module' => 'admin'
        ],
        [
            'module_name' => '修改秒杀',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'updateSecKill',
            'module' => 'admin'
        ],
        [
            'module_name' => '我要报名',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addSecKill',
            'module' => 'admin'
        ],
        [
            'module_name' => '删除秒杀',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'deleteSecKill',
            'module' => 'admin'
        ],
        [
            'module_name' => '秒杀使用记录',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'historySeck',
            'module' => 'admin'
        ],
        [
            'module_name' => '秒杀详情',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'secKillInfo',
            'module' => 'admin'
        ],
        [
            'module_name' => '我的秒杀',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'seckList',
        ],
        [
            'module_name' => '报名条件',
            'parent_module_name' => '每日秒杀',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'seckillRequirementsDialog',
            'module' => 'admin'
        ],
    );

    public function __construct()
    {
        parent::__construct();
        $this->assign('website_id', $this->website_id);
        $this->assign('instance_id', $this->instance_id);
        $this->assign("pageshow", PAGESHOW);
        $this->assign('seckListUrl', __URL(addons_url('seckill://Seckill/seckList')));
        if ($this->module == 'platform' || $this->module == 'platform_new') {
            $this->assign('getSecKillInfo', __URL(addons_url_platform('seckill://Seckill/getSecKillInfo')));
            $this->assign('secKillListUrl', __URL(addons_url_platform('seckill://Seckill/seckillAllList')));
            $this->assign('addSecKillUrl', __URL(addons_url_platform('seckill://Seckill/addSecKill')));
            $this->assign('updateSecKillUrl', __URL(addons_url_platform('seckill://Seckill/updateSecKill')));
            $this->assign('historySeckUrl', __URL(addons_url_platform('seckill://Seckill/historySeck')));
            $this->assign('saveSeckSettingUrl', __URL(addons_url_platform('seckill://Seckill/secSetting')));
            //今日活动商品
            $this->assign('todaySeckillList', __URL(addons_url_platform('seckill://Seckill/todaySeckillList')));
            //秒杀列表
            $this->assign('modalSeckillGoodsList', __URL(addons_url_platform('seckill://Seckill/modalSeckillGoodsList')));
            //单个商品的sku详情
            $this->assign('seckillGoodsDetailDialog', __URL(addons_url_platform('seckill://Seckill/seckillGoodsDetailDialog')));
            //删除秒杀记录 弹出框
            $this->assign('modalSeckillDelGoodsRecord', __URL(addons_url_platform('seckill://Seckill/modalSeckillDelGoodsRecord')));
            //执行存储删除秒杀记录，并移除对应商品及sku
            $this->assign('delSeckillGoods', __URL(addons_url_platform('seckill://Seckill/delSeckillGoods')));
            //商品通过审核接口
            $this->assign('passSeckillGoods', __URL(addons_url_platform('seckill://Seckill/passSeckillGoods')));
            //ajax获取秒杀点的未审核、已审核商品
            $this->assign('getAjaxSeckNameGoodsList', __URL(addons_url_platform('seckill://Seckill/getAjaxSeckNameGoodsList')));
            //统计秒杀商品列表
            $this->assign('getSecGoodsInfoCount', __URL(addons_url_platform('seckill://Seckill/getSecGoodsInfoCount')));
        } else if ($this->module == 'admin' || $this->module == 'admin_new') {
            $this->assign('getAdminSecKillList', __URL(addons_url_admin('seckill://Seckill/getAdminSecKillList')));
            //秒杀列表
            $this->assign('modalSeckillGoodsList', __URL(addons_url_admin('seckill://Seckill/modalSeckillGoodsList')));
            $this->assign('addSecKillUrl', __URL(addons_url_admin('seckill://Seckill/addSecKill')));
        }
    }

    public function secKillList()
    {
        if($this->module == 'admin' || $this->module == 'admin_new'){
//            var_dump(request()->get());exit;
            $seckill_server = new SeckServer();
            $apply_condition = $seckill_server->getShopSeckillRequirements();
            $status = request()->get('status','going');
            $this->assign('is_reach',$apply_condition['status']);
            $this->assign('status',$status);
        }
        $this->fetch('template/' . $this->module . '/secKillList');
    }
    public function secKillCount(){
        //取出秒杀活动场次
        $addonsConfigSer = new \data\service\AddonsConfig();
        $value = $addonsConfigSer->getAddonsConfig('seckill', $this->website_id, 0, 1);
        $sk_quantum_str = $value['sk_quantum_str'];
        $sk_quantum_arr = explode(',',$sk_quantum_str);

//        echo '<pre>';
//        var_dump($seck_info);echo '</pre>';exit;
        $this->assign('sk_quantum_arr', $sk_quantum_arr);
        $this->fetch('template/' . $this->module . '/secKillCount');
    }
    /*
     * 今日活动商品
     * **/
    public function todaySeckillList(){
        //获取当前的时间段
        $seckill_name = request()->get('seckill_name',0);
        $act = request()->get('act','');
        $this->assign('seckill_name', $seckill_name);
        $this->fetch('template/' . $this->module . '/todaySeckillList');
    }
    /*
     * 未审核列表
     * **/
    public function uncheckSeckillList(){
        $seckillServer = new SeckServer();
        //获取请求字符串
        $seckill_name = request()->get('seckill_name');
        //获取秒杀配置中的可配置区间，并获取该天开始后的7天字符串，改动从今天开始了
        $check_date_arr = $seckillServer->getSeckillCheckTime();
        //获取每个日期的商品数量 已审核
        $check_date_goods_count = $seckillServer->getDateGoodsCount($check_date_arr,1, $seckill_name);
        array_pop($check_date_goods_count);
        //获取每个日期的商品数量 未审核
        $uncheck_date_goods_count = $seckillServer->getDateGoodsCount($check_date_arr,0, $seckill_name);
        $this->assign('check_date_arr', $check_date_arr);
        $this->assign('check_date_goods_count', $check_date_goods_count);
        $this->assign('uncheck_date_goods_count', $uncheck_date_goods_count);
        $this->assign('seckill_name', $seckill_name);
        $now_uncheck_date = request()->get('seckill_time_str', $check_date_arr[0]);
        $this->assign('now_uncheck_date', $now_uncheck_date);
        $this->fetch('template/' . $this->module . '/uncheckSeckillList');
    }
    /*
     * 已审核活动列表
     * **/
    public function checkedSeckillList(){
        $seckillServer = new SeckServer();
        //获取请求字符串
        $seckill_name = request()->get('seckill_name');
        //获取秒杀配置中的可配置区间，并获取该天开始后的7天字符串
        $check_date_arr = $seckillServer->getSeckillCheckTime();
        //获取每个日期的商品数量 已审核
        $check_date_goods_count = $seckillServer->getDateGoodsCount($check_date_arr,1, $seckill_name);
//        echo '<pre>';print_r($check_date_goods_count);exit;
        //获取每个日期的商品数量 未审核
        $uncheck_date_goods_count = $seckillServer->getDateGoodsCount($check_date_arr,0, $seckill_name);
        array_pop($uncheck_date_goods_count);
        $this->assign('check_date_arr', $check_date_arr);
        $this->assign('check_date_goods_count', $check_date_goods_count);
        $this->assign('uncheck_date_goods_count', $uncheck_date_goods_count);
        $this->assign('seckill_name', $seckill_name);
        $now_check_date = request()->get('seckill_time_str', $check_date_arr[0]);
        $this->assign('now_check_date', $now_check_date);
        $this->fetch('template/' . $this->module . '/checkedSeckillList');
    }
    /*
     * 添加秒杀商品页面
     * **/
    public function addSecKill()
    {
//        $seckillServer = new SeckServer();
        //取出秒杀活动场次
        $addonsConfigSer = new \data\service\AddonsConfig();
        $value = $addonsConfigSer->getAddonsConfig('seckill', $this->website_id, 0, 1);
        $sk_quantum_str = $value['sk_quantum_str'];
        $sk_quantum_arr = explode(',',$sk_quantum_str);
//        $apply_days_arr = $seckillServer->getCanApplyDate();
//        $apply_start_days = $apply_days_arr[0];
//        $apply_end_days = $apply_days_arr[1];
//        if(!$apply_start_days){
//            $apply_start_days = 1;
//        }
//        if(!$apply_end_days){
//            $apply_start_days = 2;
//        }
        //组好区间开始的日期
        $apply_start_time = time();
//        $apply_start_time = time()+$apply_start_days*3600*24;
//        $apply_end_time = time()+$apply_end_days*3600*24;
        //变更为前一天
        $apply_start_time_str = date('Y-m-d',$apply_start_time);
        $apply_start_date = date('Y-m-d',strtotime("$apply_start_time_str-1day"));
        // $apply_start_date = date('Y-m-d',$apply_start_time);
//        $apply_end_date = date('Y-m-d',$apply_end_time);
        $this->assign('apply_start_date', $apply_start_date);
//        $this->assign('apply_end_date', $apply_end_date);
        $this->assign('sk_quantum_arr', $sk_quantum_arr);
        $survive_time = getSeckillSurviveTime($this->website_id);
        $this->assign('survive_time', $survive_time);
        $this->fetch('template/' . $this->module . '/updateSecKill');
    }
    /*
     * 添加秒杀页面
     * **/
    public function updateSecKill()
    {
        /*$seck_type_id = $_GET['seck_type_id'];
        $seck_model = new SeckServer();
        $seck_type_info = $seck_model->getSecKillDetail($seck_type_id);*/
        $survive_time = getSeckillSurviveTime($this->website_id);
        $this->assign('seck_type_info', '');
        $this->assign('survive_time', $survive_time);
        $this->fetch('template/' . $this->module . '/updateSecKill');
    }
    //秒杀设置页面
    public function seckSetting()
    {
        $website_id = $this->website_id;
        //判断分销、全球分红、区域分红、团队分红是否开启
            //分销
        $addonsConfigSer = new \data\service\AddonsConfig();
        $distribution_value = $addonsConfigSer->getAddonsConfig('distribution', $this->website_id, 0, 1);
        $distribution_is_open = getAddons('distribution', $website_id);

        //是否开启了分销
        $this->assign('distribution_is_open', $distribution_is_open);
        //开启的是几级分销
        $this->assign('distribution_pattern', $distribution_value['distribution_pattern']);
        //团队分红
        $teambonus_is_open = getAddons('teambonus', $website_id);
        //是否开启了团队分红
        $this->assign('teambonus_is_open', $teambonus_is_open);
        //区域分红
        $areabonus_is_open = getAddons('areabonus', $website_id);
        //是否开启了区域分红
        $this->assign('areabonus_is_open', $areabonus_is_open);
        //全球分红
        $globalbonus_is_open = getAddons('globalbonus', $website_id);
        //是否开启了全球分红
        $this->assign('globalbonus_is_open', $globalbonus_is_open);
        if($distribution_is_open){
            $dis_level = new VslDistributorLevelModel();
            $level_ids = $dis_level->Query(['website_id' => $this->website_id], 'id');
            $this->assign("level_ids", implode(',', $level_ids));
            $level_list = $dis_level->getQuery(['website_id' => $this->website_id], 'level_name,id', 'id asc');
            $this->assign("level_list", objToArr($level_list));
        }
        $agent_level = new VslAgentLevelModel();
        if($globalbonus_is_open){
            // $bonusLevelModel = new VslAgentLevelModel();
            //获取分红等级列表
            $global_level_ids = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>1], 'id');
            $this->assign("global_level_ids", implode(',', $global_level_ids));
            $global_cond['from_type'] = 1;
            $global_cond['website_id'] = $this->website_id;
            $global_level_list = $agent_level->getQuery($global_cond, 'level_name,id', '');
            $this->assign('global_level_list', $global_level_list);
        }   
        if($areabonus_is_open){
            //获取分红等级列表
            $area_level_ids = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>2], 'id');
            $this->assign("area_level_ids", implode(',', $area_level_ids));
            $area_cond['from_type'] = 2;
            $area_cond['website_id'] = $this->website_id;
            $area_level_list = $agent_level->getQuery($area_cond, 'level_name,id', '');
            $this->assign('area_level_list', $area_level_list);
        }
        if($teambonus_is_open){
            //获取分红等级列表
            $team_level_ids = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>3], 'id');
            $this->assign("team_level_ids", implode(',', $team_level_ids));
            $this->assign("bonus_level_ids", implode(',', $team_level_ids));
            $agent_cond['from_type'] = 3;
            $agent_cond['website_id'] = $this->website_id;
            $agent_level_list = $agent_level->getQuery($agent_cond, '', '');
            $this->assign('agent_level_list', $agent_level_list);
        }
        //获取分红等级列表
        $agent_level = new VslAgentLevelModel();
        $agent_cond['from_type'] = 3;
        $agent_cond['website_id'] = $this->website_id;
        $agent_level_list = $agent_level->getQuery($agent_cond, '', '');
        $this->assign('agent_level_list', $agent_level_list);
        $seck_info = $addonsConfigSer->getAddonsConfig('seckill', $this->website_id);
        $is_open = $seck_info['is_use'];
        $value = $seck_info['value'];
        // var_dump($value);exit;
        
        //秒杀时间段
        $sk_quantum_arr = explode(',' ,$value['sk_quantum_str']);
        //报名条件值
        $condition_check_val = json_decode($value['condition_check_val'],true);
        //可报名日期区间
        $can_apply_date = explode('-',$value['can_apply_date']);
        //分销值
        $distribution_val = json_decode($value['distribution_val'],true);
        //分红值
        $bonus_val = json_decode($value['bonus_val'],true);
        
        if ($bonus_val['distribution_val']) {
            $distribution_rule_val = json_decode(htmlspecialchars_decode($bonus_val['distribution_val']), true);
        }else{
            $distribution_rule_val = [];
        }
        $this->assign('distribution_rule_val', $distribution_rule_val);
        
        if ($bonus_val['buyagain_distribution_val']) {
            $buyagain_distribution_val = json_decode(htmlspecialchars_decode($bonus_val['buyagain_distribution_val']), true);
        }else{
            $buyagain_distribution_val = [];
        }
        $this->assign('buyagain_distribution_val', $buyagain_distribution_val);
        if ($bonus_val['area_bonus_val']) {
            $area_bonus_val = json_decode(htmlspecialchars_decode($bonus_val['area_bonus_val']), true);
        }else{
            $area_bonus_val = [];
        }
        $this->assign('area_bonus_val', $area_bonus_val);
        if ($bonus_val['global_bonus_val']) {
            $global_bonus_val = json_decode(htmlspecialchars_decode($bonus_val['global_bonus_val']), true);
        }else{
            $global_bonus_val = [];
        }
        
        $this->assign('global_bonus_val', $global_bonus_val);
        if ($bonus_val['bonus_rule_val']) {
            $bonus_rule_val_val = json_decode(htmlspecialchars_decode($bonus_val['bonus_rule_val']), true);
        }
        if ($bonus_val['teambonus_rule_val']) {
            $bonus_val['teambonus_rule_val'] = json_decode(htmlspecialchars_decode($bonus_val['teambonus_rule_val']), true);
            $level_bonus = $bonus_val['teambonus_rule_val']['team_bonus'];
            $level_bonus_arr = explode(';', $level_bonus);
            $level_bonus_val = [];
            foreach($level_bonus_arr as $level_bonus_info){
                $level_bonus_val[] = (float)explode(':', $level_bonus_info)[1];
            }
            $this->assign('level_bonus_val', $level_bonus_val);
        }else{
            $bonus_val['teambonus_rule_val'] = [];
            
        }
        if($bonus_val['teambonus_val']){
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($bonus_val['teambonus_val']), true);
            $level_bonus = $teambonus_rule_val['team_bonus'];
            $level_bonus_arr = explode(';', $level_bonus);
            $level_bonus_val = [];
            foreach($level_bonus_arr as $level_bonus_info){
                $level_bonus_val[] = (float)explode(':', $level_bonus_info)[1];
            }
            $this->assign('level_bonus_val', $level_bonus_val);
        }else{
            $teambonus_rule_val = [];
        }
        // var_dump($bonus_val);
        // exit;
        $this->assign('teambonus_rule_val', $teambonus_rule_val);
        $is_distribution = $bonus_val['is_distribution'];
        $is_global_bonus = $bonus_val['is_global_bonus'];
        $is_area_bonus = $bonus_val['is_area_bonus'];
        $is_team_bonus = $bonus_val['is_team_bonus'];
        $rule_commission = $value['rule_commission'];
        $rule_bonus = $value['rule_bonus'];
        
        // var_dump($distribution_rule_val);exit;
        $this->assign('is_distribution', $is_distribution);
        $this->assign('rule_bonus', $rule_bonus);
        $this->assign('is_global_bonus', $is_global_bonus);
        $this->assign('rule_commission', $rule_commission);
        $this->assign('is_area_bonus', $is_area_bonus);
        $this->assign('is_team_bonus', $is_team_bonus);

        $this->assign('is_open', $is_open);
        $this->assign('condition_check_val', $condition_check_val);
        $this->assign('can_apply_date_start', $can_apply_date[0]);
        $this->assign('can_apply_date_end', $can_apply_date[1]);
        $this->assign('distribution_val', $distribution_val);
        $this->assign('bonus_val', $bonus_val);
        $this->assign('sk_quantum_arr', $sk_quantum_arr);
        $this->assign('value', $value);
        $this->fetch('template/' . $this->module . '/seckSetting'); 
    }
    public function seckillGoodsDialog()
    {
        $this->fetch('template/' . $this->module . '/seckillGoodsDialog');
    }
    /*
     * 记录删除秒杀商品的删除原因
     * **/
    public function modalSeckillDelGoodsRecord()
    {
        $this->fetch('template/' . $this->module . '/seckillDelGoodsRecordDialog');
    }

    public function seckillGoodsDetailDialog()
    {
        $condition['ns.website_id'] = $this->website_id;
        $goods_id = request()->get('goods_id',0);
        $seckill_id = request()->get('seckill_id',0);
        $condition['nsg.goods_id'] = $goods_id;
        $condition['ns.seckill_id'] = $seckill_id;
        $condition['nsg.del_status'] = 1;
        $goods = new \addons\seckill\server\Seckill();
        $field = 'nsg.sku_id, ng.goods_name, ngs.attr_value_items, ngs.sku_name, nsg.goods_id, nsg.seckill_num, nsg.seckill_price, nsg.seckill_limit_buy, nsg.remain_num';
        $seckill_goods_sku_list = $goods->getGoodsDetail($condition, $field);
        $this->assign('seckill_goods_sku_list', $seckill_goods_sku_list);
        $this->fetch('template/' . $this->module . '/seckillGoodsDetailDialog');
    }

    public function seckillRequirementsDialog()
    {
        $this->fetch('template/' . $this->module . '/seckillRequirementsDialog');
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