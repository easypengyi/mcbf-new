<?php
namespace addons\presell;
use addons\Addons as Addo;
use data\service\BaseService;
use addons\presell\service\Presell as PresellService;
use addons\distribution\model\VslDistributorLevelModel;
use addons\bonus\model\VslAgentLevelModel;

class Presell extends Addo
{
    public $shopfinfo = array();

    public $info = array(
        'name' => 'presell', // 插件名称标识
        'title' => '商品预售', // 插件中文名
        'description' => '先付定金提前开卖，饥饿营销', // 插件概述
        'status' => 1, // 状态 1启用 0禁用
        'author' => 'vslaishop', // 作者
        'version' => '1.0', // 版本号
        'has_addonslist' => 1, // 是否有下级插件 例如：第三方登录插件下有 qq登录，微信登录
        'content' => 'presellconfig', // 插件的详细介绍或使用方法
        'config_hook' => 'presellList',
        'config_admin_hook' => 'presellList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/1554197124.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1563782153.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1563782303.png',
    ); // 设置文件单独的钩子

    //platform
    public $menu_info = array(
        [
            'module_name' => '商品预售',
            'parent_module_name' => '应用', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '先付定金提前开卖，饥饿营销', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'presellList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '预售列表',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '预售采用“定金+尾款”的模式，下单先支付定金，到指定时间再支付尾款，参加预售的商品不能参与“限时折扣、秒杀、拼团、砍价”等活动。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'presellList',
            'module' => 'platform',
        ],
        [
            'module_name' => '预售设置',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '关闭后，所有预售活动均不生效，可设置独立的分销分红规则，优先级为：商品独立>活动独立>分销/分红设置。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'presellconfig',
            'module' => 'platform',
        ],
        [
            'module_name' => '添加预售',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'addpresell',
            'module' => 'platform',
        ],
        [
            'module_name' => '订购记录',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'orderrecord',
            'module' => 'platform',
        ],
        [
            'module_name' => '预售编辑',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'editpresell',
            'module' => 'platform',
        ],
        [
            'module_name' => '选择商品',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 0, // 是否有控制权限
            'hook_name' => 'presellGoodsList',
            'module' => 'platform',
        ],

        //admin
        [
            'module_name' => '商品预售',
            'parent_module_name' => '应用', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '先付定金提前开卖，饥饿营销', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'presellList',
            'module' =>'admin',
            'is_admin_main' => 1//c端应用页面主入口标记
        ],
        [
            'module_name' => '预售列表',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '预售采用“定金+尾款”的模式，参加预售的商品不能参与“限时折扣、秒杀、拼团、砍价”等活动。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'presellList',
            'module' => 'admin'
        ],
        [
            'module_name' => '添加预售',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'addpresell',
            'module' => 'admin',
        ],
        [
            'module_name' => '订购记录',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'orderrecord',
            'module' => 'admin',
        ],
        [
            'module_name' => '预售编辑',
            'parent_module_name' => '商品预售', // 上级模块名称 用来确定上级目录
            'last_module_name' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'editpresell',
            'module' => 'admin',
        ]
    ); // 钩子名称（需要该钩子调用的页面）
    


     public function __construct(){
         parent::__construct();
         if ($this->module == 'platform' || $this->module == 'admin') {
             $this->assign('orderrecord', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/orderRecord')));
             $this->assign('presellListUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/presellList')));
             $this->assign('addPresellUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/addPresell')));
             $this->assign('presellGoodsListUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/presellGoodsList')));
             $this->assign('presellConfigUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/presellConfig')));
             $this->assign('delPresellUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/delPresell')));
             $this->assign('closePresellUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/closePresell')));
             $this->assign('getSkuListUrl', __URL(call_user_func('addons_url_' . $this->module, 'presell://Presell/getSkuList')));
         } 
     }

    /**
     * 预售列表
     *
     * @param array $params
     **/
    public function presellList()
    {
        $presell_service = new PresellService();
        //获取状态
        $count = $presell_service->getStatusCount();  //全部
        $count_1 = $presell_service->getStatusCount('1');  //进行
        $count_2 = $presell_service->getStatusCount('2');  //未开始
        $count_3 = $presell_service->getStatusCount('3');  //已结束
        $this->assign('count',$count);
        $this->assign('count_1',$count_1);
        $this->assign('count_2',$count_2);
        $this->assign('count_3',$count_3);
        //获取二维码，太阳码链接
        $base_service = new BaseService();
        $link_info = $base_service->getQrLinkInfo();
        $this->assign('mp_url', $link_info['mp_url']);
        $this->assign('wap_url', $link_info['wap_url']);
        $this->assign('path_prex',$link_info['path_prex']);
        $this->fetch('template/'.$this->module.'/presellList');

    }

    //基础配置
    public function presellConfig(){
        $distributionStatus = getAddons('distribution', $this->website_id);
        $globalStatus = getAddons('globalbonus', $this->website_id);
        $areaStatus = getAddons('areabonus', $this->website_id);
        $teamStatus = getAddons('teambonus', $this->website_id);
        $this->assign('has_distribution', $distributionStatus);
        $this->assign('has_global', $globalStatus);
        $this->assign('has_area', $areaStatus);
        $this->assign('has_team', $teamStatus);

        $addonsConfigSer = new \data\service\AddonsConfig();
        $addons_info = $addonsConfigSer->getAddonsConfig(strtolower($this->info['name']), $this->website_id);
        $addons_data = $addons_info['value'] ?: [];
        if(empty($addons_data['is_distribution'])){
            $addons_data['is_distribution'] = 0;
        }
        $addons_data['is_use'] = $addons_info['is_use'] ?: 0;
        $this->assign('addons_data', $addons_data);

        if($distributionStatus){
            $dis_level = new VslDistributorLevelModel();
            $level_ids = $dis_level->Query(['website_id' => $this->website_id], 'id');
            $this->assign("level_ids", implode(',', $level_ids));
            $level_list = $dis_level->getQuery(['website_id' => $this->website_id], 'level_name,id', 'id asc');
            $this->assign("level_list", objToArr($level_list));
        }
        $agent_level = new VslAgentLevelModel();
        if($globalStatus){
            $global_level_ids = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>1], 'id');
            $this->assign("global_level_ids", implode(',', $global_level_ids));
            $global_cond['from_type'] = 1;
            $global_cond['website_id'] = $this->website_id;
            $global_level_list = $agent_level->getQuery($global_cond, 'level_name,id', '');
            $this->assign('global_level_list', $global_level_list);
        }
        if($areaStatus){
            $area_level_ids = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>2], 'id');
            $this->assign("area_level_ids", implode(',', $area_level_ids));
            $area_cond['from_type'] = 2;
            $area_cond['website_id'] = $this->website_id;
            $area_level_list = $agent_level->getQuery($area_cond, 'level_name,id', '');
            $this->assign('area_level_list', $area_level_list);
        }
        if($teamStatus){
            $team_level_ids = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>3], 'id');
            $this->assign("team_level_ids", implode(',', $team_level_ids));
            $this->assign("bonus_level_ids", implode(',', $team_level_ids));
            $agent_cond['from_type'] = 3;
            $agent_cond['website_id'] = $this->website_id;
            $agent_level_list = $agent_level->getQuery($agent_cond, '', '');
            $this->assign('agent_level_list', $agent_level_list);
        }
        $bonus_val = json_decode($addons_data['bonus_val'],true);
       
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
        $this->assign('is_distribution', $is_distribution);
        $this->assign('rule_bonus', $rule_bonus);
        $this->assign('is_global_bonus', $is_global_bonus);
        $this->assign('rule_commission', $rule_commission);
        $this->assign('is_area_bonus', $is_area_bonus);
        $this->assign('is_team_bonus', $is_team_bonus);
        $this->assign('bonus_val', $bonus_val);

        $this->fetch('template/'.$this->module.'/presellConfig');
    }

    //编辑
    public function editPresell(){
        $id = $_REQUEST['id'];
        $presell = new PresellService();
        $info = $presell->getPresellInfo($id);
        $info = objToArr($info);
        $goodsSer = new \data\service\Goods();
        $goods_info = $goodsSer->getGoodsDetailById($info[0]['goods_id'], 'goods_id,goods_name');
        foreach ($info as $k=>$v){
            $info[$k]['start_time'] = date('Y-m-d', $v['start_time']);
            $info[$k]['end_time'] = date('Y-m-d', $v['end_time']);
            $info[$k]['pay_start_time'] = date('Y-m-d', $v['pay_start_time']);
            $info[$k]['pay_end_time'] = date('Y-m-d', $v['pay_end_time']);
            $info[$k]['send_goods_time'] = date('Y-m-d', $v['send_goods_time']);
        }
        $this->assign('goods_name',$goods_info['goods_name']);
        $this->assign('info',$info);
        $this->assign('sku_count', count($info));//如果大于1说明多规格
        if($_REQUEST['type']=='edit'){
            $this->fetch('template/'.$this->module.'/editPresell');
        }else{
            $this->fetch('template/'.$this->module.'/presellDetail');
        }
    }

    //增加
    public function addPresell(){
        $this->fetch('template/'.$this->module.'/addPresell');
    }

    //订购记录
    public function orderRecord(){
        $this->assign('presell_id',$_REQUEST['id']);
        $this->fetch('template/'.$this->module.'/presellRecord');
    }
    /**
     * 预购商品选择
     */
    public function presellGoodsList()
    {
        $this->fetch('template/' . $this->module . '/groupShoppingGoodsDialog');
    }


    public function run(){

    }

    /*
     * 安装方法
     */
    public function install()
    {
        // TODO: Implement install() method.

        return true;
    }

    /*
     * 卸载方法
     */
    public function uninstall()
    {

        return true;
        // TODO: Implement uninstall() method.
    }

}