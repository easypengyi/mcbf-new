<?php
namespace addons\discount;
use addons\Addons as Addo;
use addons\discount\server\Discount as discountSer;
use data\service\AddonsConfig as AddonsConfigService;


class Discount extends Addo
{

    public $info = array(
        'name' => 'discount', // 插件名称标识
        'title' => '限时抢购', // 插件中文名
        'description' => '限时打折轻松提升销量', // 插件概述
        'status' => 1, // 状态 1启用 0禁用
        'author' => 'vslaishop', // 作者
        'version' => '1.0', // 版本号
        'has_addonslist' => 1, // 是否有下级插件 例如：第三方登录插件下有 qq登录，微信登录
        'content' => '', // 插件的详细介绍或使用方法
        'config_hook' => 'discountList',
        'config_admin_hook' => 'discountList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/1554197110.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1563782168.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1563782290.png',
    ); // 设置文件单独的钩子

    public $menu_info = array(
        [
            'module_name' => '限时抢购',
            'parent_module_name' => '应用', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '限时打折轻松提升销量', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountList',
            'is_main' => 1,
            'module' =>'platform'
        ],
        [
            'module_name' => '限时抢购列表',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '可设定一款或多款商品，在指定时间段内以指定抢购出售。刺激消费者迅速下单，增加卖家的短期销售额和销售利润。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountList',
            'module' =>'platform'
        ]  ,
        [
            'module_name' => '限时抢购设置',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '限时抢购列表', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '关闭后，所有限时抢购的活动均不生效。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountSet',
            'module' =>'platform'
        ]  ,
        [
        'module_name' => '添加限时抢购',
        'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
        'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
        'is_menu' => 0, // 是否是菜单
        'is_dev' => 0, // 是否是开发模式可见
        'desc' => '参加限时抢购的商品不能参与“拼团、秒杀、砍价、预售”等活动。活动开始后不可修改，请谨慎填写。', // 菜单描述
        'module_picture' => '', // 图片（一般为空）
        'icon_class' => '', // 字体图标class（一般为空）
        'is_control_auth' => 1, // 是否有控制权限
        'hook_name' => 'addDiscount',
            'module' =>'platform'
        ],

        [
        'module_name' => '编辑限时抢购',
        'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
        'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
        'is_menu' => 0, // 是否是菜单
        'is_dev' => 0, // 是否是开发模式可见
        'desc' => '添加限时抢购', // 菜单描述
        'module_picture' => '', // 图片（一般为空）
        'icon_class' => '', // 字体图标class（一般为空）
        'is_control_auth' => 1, // 是否有控制权限
        'hook_name' => 'editdiscount',
            'module' =>'platform'
        ],

        [
            'module_name' => '限时抢购详情',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountDetail',
            'module' =>'platform'
        ],

        //admin
        [
            'module_name' => '限时抢购',
            'parent_module_name' => '应用', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '添加限时抢购', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountList',
            'module' =>'admin',
            'is_admin_main' => 1//c端应用页面主入口标记
        ],

        [
            'module_name' => '添加限时抢购',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '添加限时抢购', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'addDiscount',
            'module' =>'admin'
        ],
        [
            'module_name' => '限时抢购列表',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '添加限时抢购', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountList',
            'module' =>'admin'
        ],
        [
            'module_name' => '编辑限时抢购',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '添加限时抢购', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'editdiscount',
            'module' =>'admin'
        ],

        [
            'module_name' => '限时抢购详情',
            'parent_module_name' => '限时抢购', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'discountDetail',
            'module' =>'admin'
        ],



    ) // 钩子名称（需要该钩子调用的页面）
    ;


     public function __construct(){

         parent::__construct();
         $this->assign('website_id', $this->website_id);
         if($this->module=='platform' || $this->module == 'admin'){
            $this->assign('discountList', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/discountList')));
            $this->assign('addDiscount', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/addDiscount')));
            $this->assign('editdiscount', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/editDiscount')));
            $this->assign('canclePromotionStatus', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/canclePromotionStatus')));
            $this->assign('getCurrDiscountGoodsId', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/getCurrDiscountGoodsId')));
            $this->assign('delDiscount', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/delDiscount')));
            $this->assign('getSerchGoodsList', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/getSerchGoodsList')));
            $this->assign('getAllSelectedGoods', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/getAllSelectedGoods')));
            $this->assign('closediscount', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/closeDiscount')));
            $this->assign('gettailPage', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/gettailPage')));
            $this->assign('discountSetUrl', __URL(call_user_func('addons_url_' . $this->module, 'discount://discount/discountSet')));
        }

    }
    static function run(){

    }
    public function discountSet()
    {
        $config = new AddonsConfigService();
        $discountSet = $config->getAddonsConfig("discount");
        $this->assign('is_use', $discountSet['is_use']);
        $this->fetch('template/'.$this->module.'/discountSetting');
    }

    /**
     * 实现第三方钩子
     *
     * @param array $params
     */
    public function discountList()
    {
        $this->fetch('template/'.$this->module.'/getDiscountList');
    }



    /**
     * 添加限时抢购
     */
    public function addDiscount()
    {
        $this->fetch('template/'.$this->module.'/addDiscount');
    }

    /**
     * 显示抢购详情
     */
    public function discountDetail()
    {
        $discount_id = $_REQUEST['discount_id']?$_REQUEST['discount_id']:'';
        $discountSer = new discountSer();
        $info = $discountSer->getPromotionDiscountDetail($discount_id, false);
        $this->assign("info", $info);
        $this->fetch('template/'.$this->module.'/discountInfo');
    }


    /**
     * 修改限时抢购
     */
    public function editdiscount()
    {
        $discount_id = $_REQUEST['discount_id']?$_REQUEST['discount_id']:'';
        $discountSer = new discountSer();
        $info = $discountSer->getPromotionDiscountDetail($discount_id);
        $goods_id_array = [];
        if (!empty($info['goods_list'])) {
            foreach ($info['goods_list'] as $k => $v) {
                $goods_id_array[] = $v['goods_id'];
            }
        }
        $info['goods_id_array'] = $goods_id_array;
        $this->assign("info", $info);
        $this->assign("discount_id", $discount_id);
        $this->assign("shop_id",$this->instance_id);
        $this->assign("seleted_goods", json_encode($info['goods_id_array']));
        $this->fetch('template/'.$this->module.'/updateDiscount');
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