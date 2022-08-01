<?php
namespace addons\bargain;
use addons\Addons as Addo;
use addons\bargain\model\VslBargainModel;
use \addons\bargain\service\Bargain AS bargainServer;
use data\model\AlbumPictureModel;
use data\service\BaseService;
use addons\distribution\model\VslDistributorLevelModel;
use addons\bonus\model\VslAgentLevelModel;

class Bargain extends Addo
{
    public $info = array(
        'name' => 'bargain',//插件名称标识
        'title' => '砍价',//插件中文名
        'description' => '邀请好友砍价，病毒式宣传',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'bargainList',//
        'config_admin_hook' => 'bargainList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/1554197071.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1563782121.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1563782243.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '砍价',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '邀请好友砍价，病毒式宣传',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'bargainList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '砍价列表',
            'parent_module_name' => '砍价', //上级模块名称 确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '可设置固定砍价和随机砍价，会员可随时以砍价后的现价购买该商品，参加砍价的商品不能参与“限时折扣、秒杀、拼团、预售”等活动。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'bargainList',
            'module' => 'platform'
        ],
        [
            'module_name' => '活动详情',
            'parent_module_name' => '砍价', //上级模块名称 确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 0, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'bargainDetail',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '砍价',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '关闭后，所有砍价活动均不生效，可设置独立的分销分红规则，优先级为：商品独立>活动独立>分销/分红设置。',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'bargainConfig',
            'module' => 'platform'
        ],
        [
            'module_name' => '添加/编辑砍价',
            'parent_module_name' => '砍价',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addBargain',
            'module' => 'platform'
        ],
        [
            'module_name' => '砍价记录',
            'parent_module_name' => '砍价', // 上级模块名称 用来确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'bargainRecord',
            'module' => 'platform',
        ],
        [
            'module_name' => '编辑',
            'parent_module_name' => '砍价', // 上级模块名称 用来确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'editBargain',
            'module' => 'platform',
        ],
        [
            'module_name' => '商品选择',
            'parent_module_name' => '砍价',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'bargainDialogGoodsList',
            'module' => 'platform'
        ],
        //admin
        [
            'module_name' => '砍价',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '邀请好友砍价，病毒式宣传',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'bargainList',
            'module' => 'admin',
            'is_admin_main' => 1
        ],
        [
            'module_name' => '砍价列表',
            'parent_module_name' => '砍价', //上级模块名称 确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '可设置固定砍价和随机砍价，会员可随时以砍价后的现价购买该商品，参加砍价的商品不能参与“限时折扣、秒杀、拼团、预售”等活动。', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'bargainList',
            'module' => 'admin'
        ],
        [
            'module_name' => '活动详情',
            'parent_module_name' => '砍价', //上级模块名称 确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 0, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'bargainDetail',
            'module' => 'admin'
        ],
        [
            'module_name' => '添加砍价',
            'parent_module_name' => '砍价',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'addBargain',
            'module' => 'admin'
        ],
        [
            'module_name' => '砍价记录',
            'parent_module_name' => '砍价', // 上级模块名称 用来确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'bargainRecord',
            'module' => 'admin',
        ],
        [
            'module_name' => '编辑',
            'parent_module_name' => '砍价', // 上级模块名称 用来确定上级目录
            'sort' => 0, //菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'editBargain',
            'module' => 'admin',
        ],

    ); // 钩子名称（需要该钩子调用的页面）

    public function __construct(){
        parent::__construct();
        $this->bargainServer = new bargainServer();
        $this->assign('website_id', $this->website_id);
        $this->assign('instance_id', $this->instance_id);
        $this->assign("pageshow", PAGESHOW);
        if ($this->module == 'platform') {
            $this->assign('addBargainConfig', __URL(addons_url_platform('bargain://Bargain/addBargainConfig')));
            $this->assign('addBargain', __URL(addons_url_platform('bargain://Bargain/addBargain')));
            $this->assign('bargainListUrl', __URL(addons_url_platform('bargain://Bargain/bargainListUrl')));
            $this->assign('bargainRecordUrl', __URL(addons_url_platform('bargain://Bargain/bargainRecordUrl')));
            $this->assign('bargainClose', __URL(addons_url_platform('bargain://Bargain/bargainClose')));
            $this->assign('bargainDelete', __URL(addons_url_platform('bargain://Bargain/bargainDelete')));
            $this->assign('bargainDialogGoodsList', __URL(addons_url_platform('bargain://Bargain/bargainDialogGoodsList')));
        }else if ($this->module == 'admin') {
            $this->assign('addBargainConfig', __URL(addons_url_admin('bargain://Bargain/addBargainConfig')));
            $this->assign('addBargain', __URL(addons_url_admin('bargain://Bargain/addBargain')));
            $this->assign('bargainListUrl', __URL(addons_url_admin('bargain://Bargain/bargainListUrl')));
            $this->assign('bargainRecordUrl', __URL(addons_url_admin('bargain://Bargain/bargainRecordUrl')));
            $this->assign('bargainDialogGoodsList', __URL(addons_url_admin('bargain://Bargain/bargainDialogGoodsList')));
            $this->assign('bargainClose', __URL(addons_url_admin('bargain://Bargain/bargainClose')));
            $this->assign('bargainDelete', __URL(addons_url_admin('bargain://Bargain/bargainDelete')));
            $this->assign('bargainDialogGoodsList', __URL(addons_url_admin('bargain://Bargain/bargainDialogGoodsList')));
        }
    }

    /*
     * 砍价设置
     * **/
    public function bargainConfig()
    {
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
        if($addons_data){
            $addons_data['is_use'] = $addons_info['is_use'] ? : 0;
            $addons_data['bonus_val'] = $addons_data['bonus_val'] ?:[];
            $addons_data['distribution_val'] = $addons_data['distribution_val'] ?:[];
        }
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

        $bonus_val = is_array($addons_data['bonus_val']) ? $addons_data['bonus_val'] : json_decode($addons_data['bonus_val'], true);
        $bonus_val = empty($bonus_val) ? [] : $bonus_val;

        if ($bonus_val['distribution_val']) {
            $distribution_rule_val = json_decode(htmlspecialchars_decode($bonus_val['distribution_val']), true);
        }else{
            $distribution_rule_val = [];
        }
        $this->assign('distribution_rule_val', $distribution_rule_val);
        
        if (isset($bonus_val['buyagain_distribution_val']) && !empty($bonus_val['buyagain_distribution_val'])) {
            $buyagain_distribution_val = json_decode(htmlspecialchars_decode($bonus_val['buyagain_distribution_val']), true);
        }else{
            $buyagain_distribution_val = [];
        }
        $this->assign('buyagain_distribution_val', $buyagain_distribution_val);
        if (isset($bonus_val['area_bonus_val']) && !empty($bonus_val['area_bonus_val'])) {
            $area_bonus_val = json_decode(htmlspecialchars_decode($bonus_val['area_bonus_val']), true);
        }else{
            $area_bonus_val = [];
        }
        $this->assign('area_bonus_val', $area_bonus_val);
        if (isset($bonus_val['global_bonus_val']) && !empty($bonus_val['area_bonus_val'])) {
            $global_bonus_val = json_decode(htmlspecialchars_decode($bonus_val['global_bonus_val']), true);
        }else{
            $global_bonus_val = [];
        }
        
        $this->assign('global_bonus_val', $global_bonus_val);
        if (isset($bonus_val['bonus_rule_val']) && !empty($bonus_val['bonus_rule_val'])) {
            $bonus_rule_val_val = json_decode(htmlspecialchars_decode($bonus_val['bonus_rule_val']), true);
        }
        if (isset($bonus_val['teambonus_rule_val']) && !empty($bonus_val['teambonus_rule_val'])) {
            $bonus_val['teambonus_rule_val'] = json_decode(htmlspecialchars_decode($bonus_val['teambonus_rule_val']), true);
            $level_bonus = $bonus_val['teambonus_rule_val']['team_bonus'];
            $level_bonus_arr = explode(';', $level_bonus);
            $level_bonus_val = [];
            foreach($level_bonus_arr as $level_bonus_info){
                $level_bonus_val[] = (float)explode(':', $level_bonus_info)[1];
            }
            unset($level_bonus_info);
            $this->assign('level_bonus_val', $level_bonus_val);
        }else{
            $bonus_val['teambonus_rule_val'] = [];
            
        }
        if(isset($bonus_val['teambonus_val']) && !empty($bonus_val['teambonus_val'])){
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($bonus_val['teambonus_val']), true);
            $level_bonus = $teambonus_rule_val['team_bonus'];
            $level_bonus_arr = explode(';', $level_bonus);
            $level_bonus_val = [];
            foreach($level_bonus_arr as $level_bonus_info){
                $level_bonus_val[] = (float)explode(':', $level_bonus_info)[1];
            }
            unset($level_bonus_info);
            $this->assign('level_bonus_val', $level_bonus_val);
        }else{
            $teambonus_rule_val = [];
        }
        $this->assign('teambonus_rule_val', $teambonus_rule_val);
        $is_distribution = $bonus_val['is_distribution'];
        $is_global_bonus = $bonus_val['is_global_bonus'];
        $is_area_bonus = $bonus_val['is_area_bonus'];
        $is_team_bonus = $bonus_val['is_team_bonus'];
        $this->assign('is_distribution', $is_distribution);
        $this->assign('is_global_bonus', $is_global_bonus);
        $this->assign('is_area_bonus', $is_area_bonus);
        $this->assign('is_team_bonus', $is_team_bonus);
        $this->assign('bonus_val', $bonus_val);
        $this->fetch('template/' . $this->module . '/bargainConfig');
    }

    /*
     * 砍价列表
     * **/
    public function bargainList()
    {
        $time = time();
        //全部
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $bargain_count['all_num'] = $this->bargainServer->getBargainStatusCount($condition)?:0;
        //未开始
        $condition1['website_id'] = $this->website_id;
        $condition1['shop_id'] = $this->instance_id;
        $condition1['start_bargain_time'] = ['>', $time];
        $bargain_count['unstart_num'] = $this->bargainServer->getBargainStatusCount($condition1)?:0;
        //进行中
        $condition2['website_id'] = $this->website_id;
        $condition2['shop_id'] = $this->instance_id;
        $condition2['start_bargain_time'] = ['<', $time];
        $condition2['end_bargain_time'] = ['>', $time];
        $bargain_count['going_num'] = $this->bargainServer->getBargainStatusCount($condition2)?:0;
       //已结束
        $condition3['website_id'] = $this->website_id;
        $condition3['shop_id'] = $this->instance_id;
        $condition3['end_bargain_time'] = ['<', $time];
        $bargain_count['ended_num'] = $this->bargainServer->getBargainStatusCount($condition3)?:0;
        $this->assign('bargain_count', $bargain_count);
        //获取二维码，太阳码链接
        $base_service = new BaseService();
        $link_info = $base_service->getQrLinkInfo();
        $this->assign('mp_url', $link_info['mp_url']);
        $this->assign('wap_url', $link_info['wap_url']);
        $this->assign('path_prex',$link_info['path_prex']);
        $this->fetch('template/' . $this->module . '/bargainList');
    }

    /*
     *砍价详情
     * **/
    public function bargainDetail()
    {
        //查询出活动内容
        $bargain_id = request()->get('bargain_id');
        $bargain_detail = $this->bargainServer->getBargainDetail($bargain_id);
        if($bargain_detail){
            $bargain_detail['pic_cover'] = getApiSrc($bargain_detail['pic_cover']);
            $bargain_detail['start_bargain_time_format'] = date('Y-m-d H:i:s', $bargain_detail['start_bargain_time']);
            $bargain_detail['end_bargain_time_format'] = date('Y-m-d 23:59:59', $bargain_detail['end_bargain_time']);
            if($bargain_detail['end_bargain_time'] < time()){
                $bargain_detail['bargain_status'] = 3;//已结束
            }elseif($bargain_detail['end_bargain_time']>time() && $bargain_detail['start_bargain_time']<time()){
                $bargain_detail['bargain_status'] = 2;//已开始
            }else{
                $bargain_detail['bargain_status'] = 1;//未开始
            }
        }
        $this->assign('bargain_detail', $bargain_detail);
        $this->fetch('template/' . $this->module . '/bargainDetail');
    }
    /*
     * 砍价记录
     * **/
    public function bargainRecord()
    {
        //获取bargain_id
        $bargain_id = request()->get('bargain_id');
        //获取砍价状态 砍价中
        $condition1['b.website_id'] = $this->website_id;
        $condition1['b.shop_id'] = $this->instance_id;
        $condition1['b.bargain_id'] = $bargain_id;
        $condition1['b.end_bargain_time'] = ['>=',time()];
        $condition1['br.bargain_status'] = 1;
        $going_count = $this->bargainServer->getBargainCount($condition1);
        $bargain_status_arr['going_count'] = $going_count;
        //已支付
        $condition2['b.website_id'] = $this->website_id;
        $condition2['b.shop_id'] = $this->instance_id;
        $condition2['b.bargain_id'] = $bargain_id;
        $condition2['br.bargain_status'] = 2;
        $pay_count = $this->bargainServer->getBargainCount($condition2);
        $bargain_status_arr['pay_count'] = $pay_count;
        //已过期 失败
        $condition3['b.website_id'] = $this->website_id;
        $condition3['b.shop_id'] = $this->instance_id;
        $condition3['b.bargain_id'] = $bargain_id;
        $condition3['b.end_bargain_time'] = ['<',time()];
        $condition3['br.bargain_status'] = 1;
        $fail_count = $this->bargainServer->getBargainCount($condition3);
        $bargain_status_arr['fail_count'] = $fail_count;
        $this->assign('bargain_id',$bargain_id);
        $this->assign('bargain_status_arr',$bargain_status_arr);
        $this->fetch('template/' . $this->module . '/bargainRecord');
    }

    /*
     * 添加砍价
     * **/
    public function addBargain()
    {
        $bargain_id = request()->get('bargain_id',0);
        $batgain_mdl = new VslBargainModel();
        $album_pic_mdl = new AlbumPictureModel();
        $bargain_info = $batgain_mdl->getInfo(['bargain_id'=>$bargain_id],'*');
        $pic_cover = getApiSrc($album_pic_mdl->getInfo(['pic_id'=>$bargain_info['picture']],'pic_cover')['pic_cover']);
        $bargain_info['pic_cover'] = $pic_cover;
        $bargain_info['start_bargain_date'] = date('Y-m-d', $bargain_info['start_bargain_time']);
        $bargain_info['end_bargain_date'] = date('Y-m-d', $bargain_info['end_bargain_time']);
        $this->assign('bargain_id',$bargain_id);
        $this->assign('bargain_info',$bargain_info);
        $this->fetch('template/' . $this->module . '/addBargain');
    }
    /**
     * 砍价商品选择
     */
    public function bargainDialogGoodsList()
    {
        $this->fetch('template/' . $this->module . '/bargainGoodsDialog');
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