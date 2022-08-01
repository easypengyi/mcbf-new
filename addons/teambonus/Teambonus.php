<?php
namespace addons\teambonus;
use addons\Addons as Addo;
use addons\teambonus\service\TeamBonus as  teamBonusService;
use addons\distribution\service\Distributor as  DistributorService;
use data\model\VslOrderModel;
use addons\bonus\model\VslOrderBonusLogModel;
use data\service\Config;

class teamBonus extends Addo
{
    public $info = array(
        'name' => 'teambonus', // 插件名称标识
        'title' => '团队分红', // 插件中文名
        'description' => '团队队长分红下线的所有订单', // 插件概述
        'status' => 1, // 状态 1启用 0禁用
        'author' => 'vslaishop', // 作者
        'version' => '1.0', // 版本号
        'has_addonslist' => 1, // 是否有下级插件 例如：第三方登录插件下有 qq登录，微信登录
        'content' => '', // 插件的详细介绍或使用方法
        'config_hook' => 'teamBonusProfile',
        'logo' => 'https://pic.vslai.com.cn/upload/common/1554197101.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1563782158.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1563782281.png',
    ); // 设置文件单独的钩子

    public $menu_info = array(
        [
            'module_name' => '团队分红',
            'parent_module_name' => '应用', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队队长分红下线的所有订单', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBonusProfile',
            'is_main' => 1
        ],

        [
            'module_name' => '分红概况',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => 0, // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '分红概况', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBonusProfile'
        ],

        [
            'module_name' => '队长等级',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => 2, // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '队长可设置不同等级享受不同分红返利，权重越大等级越高。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamAgentLevelList'
        ],

        [
            'module_name' => '添加队长等级',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '队长可设置不同等级享受不同分红返利，权重越大等级越高，可在商品（分销分红）或活动（基础设置）单独设置返佣比例，优先级为 商品>活动>队长等级。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'addTeamAgentLevel'
        ],

        [
            'module_name' => '修改队长等级',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '队长可设置不同等级享受不同分红返利，权重越大等级越高，可在商品（分销分红）或活动（基础设置）单独设置返佣比例，优先级为 商品>活动>队长等级。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'updateTeamAgentLevel'
        ],
        [
            'module_name' => '删除队长等级',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '删除队长等级', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'deleteTeamAgentlevel'
        ],
        [
            'module_name' => '修改队长状态',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '修改队长状态', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'setTeamAgentStatus'
        ],
        [
            'module_name' => '队长列表',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => 1, // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '队长可获得团队下线所有订单一定比例的分红奖励。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamAgentList'
        ],
        [
            'module_name' => '队长详情',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamAgentInfo'
        ],
        [
            'module_name' => '团队分红下级分销商',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '可查看该团队队长所有下线的信息。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'lowerAgentList'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => 5, // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队分红的基础相关设置，可设置成为队长条件、内购、极差、跳降级、结算、申请协议等设置。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBonusSetting'
        ],
        [
            'module_name' => '分红结算单',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => 3, // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队队长待分红的结算单，分红默认为手动发放，可在“基础设置》结算设置”设置为按周期自动发放。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBonusBalance'
        ],
        [
            'module_name' => '分红明细',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => 4, // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 1, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '可查看团队分红打款的数据信息。', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBonusDetail'
        ],
        [
            'module_name' => '团队分红明细详情',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBonusInfo'
        ],
        [
            'module_name' => '分红基本设置',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '分红基本设置', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamBasicSetting'
        ],
        [
            'module_name' => '分红结算设置',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' =>0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队分红结算设置', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamSettlementSetting'
        ],
        [
            'module_name' => '团队代理申请协议',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队代理申请协议', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'teamApplicationAgreement'
        ],
        [
            'module_name' => '队长自动降级',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '队长自动降级', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'autoDownTeamAgentLevel',
            'is_member'=>1
        ],
        [
            'module_name' => '团队代理订单支付成功',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队代理订单支付成功', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'orderTeamPayCalculate',
            'is_member'=>1
        ],
        [
            'module_name' => '团队代理订单创建成功',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队代理订单创建成功', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'orderTeamBonusCalculate',
            'is_member'=>1
        ],
        [
            'module_name' => '团队代理订单完成分红计算',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队代理订单完成分红计算', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'updateOrderTeamBonus',
            'is_member'=>1
        ],
        [
            'module_name' => '团队代理退款完成分红计算',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队代理退款完成分红计算', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'updateTeamBonusMoney',
            'is_member'=>1
        ],
        [
            'module_name' => '团队分红自动发放',
            'parent_module_name' => '团队分红', // 上级模块名称 用来确定上级目录
            'sort' => '', // 上一个菜单名称 用来确定菜单排序
            'is_menu' => 0, // 是否是菜单
            'is_dev' => 0, // 是否是开发模式可见
            'desc' => '团队分红自动发放', // 菜单描述
            'module_picture' => '', // 图片（一般为空）
            'icon_class' => '', // 字体图标class（一般为空）
            'is_control_auth' => 1, // 是否有控制权限
            'hook_name' => 'autoGrantTeamBonus',
            'is_member'=>1
        ]
    ); // 钩子名称（需要该钩子调用的页面）

     public function __construct(){
        parent::__construct();
         $config= new teamBonusService();
         $list = $config->getteamBonusSite($this->website_id);
         if($this->merchant_expire==1){
             $this->assign('merchant_expire',$this->merchant_expire);
         }
         $this->assign("website", $list);
         $this->assign('deleteTeamAgentLevelUrl', __URL(addons_url_platform('teambonus://teambonus/deleteTeamAgentLevel')));
        $this->assign('teamAgentListUrl', __URL(addons_url_platform('teambonus://teambonus/teamAgentList')));
        $this->assign('teamBonusProfileUrl', __URL(addons_url_platform('teambonus://teambonus/teamBonusProfile')));
        $this->assign('teamBonusOrderProfileUrl', __URL(addons_url_platform('teambonus://teambonus/teamBonusOrderProfile')));
        $this->assign('teamBonusSettingUrl', __URL(addons_url_platform('teambonus://teamBonus/teamBonusSetting')));
        $this->assign('addTeamAgentLevelUrl', __URL(addons_url_platform('teambonus://teambonus/addTeamAgentLevel')));
        $this->assign('updateTeamAgentLevelUrl', __URL(addons_url_platform('teambonus://teambonus/updateTeamAgentLevel')));
        $this->assign('teamAgentLevelListUrl', __URL(addons_url_platform('teambonus://teambonus/teamAgentLevelList')));
         $this->assign('teamMessagePushListUrl', __URL(addons_url_platform('teambonus://teambonus/teamMessagePushList')));
         $this->assign('teamEditMessageUrl', __URL(addons_url_platform('teambonus://teambonus/teamEditMessage')));
         $this->assign('addTeamMessageUrl', __URL(addons_url_platform('teambonus://teambonus/addTeamMessage')));
        $this->assign('website_id', $this->website_id);
        $this->assign('pageshow','10');
        $this->assign('teamBasicSettingUrl', __URL(addons_url_platform('teambonus://teambonus/teamBasicSetting')));
        $this->assign('teamSettlementSettingUrl', __URL(addons_url_platform('teambonus://teambonus/teamSettlementSetting')));
        $this->assign('teamApplicationAgreementUrl', __URL(addons_url_platform('teambonus://teambonus/teamApplicationAgreement')));
        $this->assign('deleteTeamAgentlevelUrl', __URL(addons_url_platform('teambonus://teambonus/deleteTeamAgentlevel')));
        $this->assign('teamAgentInfoUrl', __URL(addons_url_platform('teambonus://teambonus/teamAgentInfo')));
        $this->assign('setTeamAgentStatusUrl', __URL(addons_url_platform('teambonus://teambonus/setTeamAgentStatus')));
        $this->assign('delTeamAgentUrl', __URL(addons_url_platform('teambonus://teambonus/delTeamAgent')));
        $this->assign('updateTeamAgentInfoUrl', __URL(addons_url_platform('teambonus://teambonus/updateTeamAgentInfo')));
        $this->assign('teamBonusBalanceUrl', __URL(addons_url_platform('teambonus://teambonus/teamBonusBalance')));
        $this->assign('teamBonusListUrl', __URL(addons_url_platform('teambonus://teambonus/teamBonusList')));
        $this->assign('teamBonusGrantUrl', __URL(addons_url_platform('teambonus://teambonus/teamBonusGrant')));
        $this->assign('teamBonusInfoUrl', __URL(addons_url_platform('teambonus://teambonus/teamBonusInfo')));
         $this->assign('lowerAgentListUrl', __URL(addons_url_platform('teambonus://teambonus/lowerAgentList')));
    }

    /**
     * 实现第三方钩子
     *
     * @param array $params            
     */


    /**
     * 队长列表
     */
    public function teamAgentList()
    {
        $level = new teamBonusService();
        $agent_level = $level->getAgentLevel();
        $this->assign('agent_level',$agent_level);
        $this->fetch('template/platform/teamAgentList');
    }
    /**
     * 团队分红结算单
     */
    public function teamBonusBalance()
    {
        $this->fetch('template/platform/teamBonusBalance');
    }
    /**
     * 团队分红明细
     */
    public function teamBonusDetail()
    {
        $this->fetch('template/platform/teamBonusDetail');
    }
    /**
     * 团队分红明细
     */
    public function teamBonusInfo()
    {
        $member = new teamBonusService();
        $agent_level = $member->getAgentLevel();
        $this->assign('agent_level',$agent_level);
        $this->assign('sn',$_GET['sn']);
        $this->fetch('template/platform/teamBonusInfo');
    }
    /**
     * 下级分销商列表
     */
    public function lowerAgentList()
    {
        $level = new DistributorService();
        $distributor_level = $level->getDistributorLevel();
        $this->assign('distributor_level',$distributor_level);
        $this->assign('distributor_id',$_GET['distributor_id']); 
        $types = request()->get('types', '');
        $this->assign('types',$_GET['types']);
        $this->fetch('template/platform/lowerAgentList');
    }
    /**
     * 队长详情页面
     */
    public function teamAgentInfo(){
        $member = new teamBonusService();
        $uid = $_GET['agent_id'];
        $res= $member->getAgentInfo($uid);
        $agent_level = $member->getAgentLevel();
        $this->assign('agent_level',$agent_level);
        $this->assign('info',$res);
        $this->fetch('template/platform/teamAgentInfo');
    }
    
    /**
     * 团队分红概况
     */
    public function teamBonusProfile(){
        $month_begin = date('Y-m-01', strtotime(date("Y-m-d")));
        $month_end = date('Y-m-d', strtotime("$month_begin +1 week -1 day"));
        $this->assign("start_date", $month_begin);
        $this->assign("end_date", $month_end);
        $this->fetch('template/platform/teamBonusProfile');
    }

    /**
     * 队长等级
     */
    public function teamAgentLevelList(){
        $this->fetch('template/platform/teamAgentLevelList');
    }


    /**
     * 团队分红设置
     */
    public function teamBonusSetting()
    {
        $this->teamBasicSetting();
    }
    /**
     * 基本设置
     */
    public function teamBasicSetting()
    {
        $config= new teamBonusService();
        $list = $config->getTeamBonusSite($this->website_id);
        $this->assign("website", $list);
        $this->fetch('template/platform/teamBasicSetting');
    }
    /**
     * 结算设置
     */
    public function teamSettlementSetting()
    {
        $config= new teamBonusService();
        $list = $config->getSettlementSite($this->website_id);
        $this->assign("website", $list);
        $this->fetch('template/platform/teamSettlementSetting');
    }
    /**
     * 申请协议
     */
    public function teamApplicationAgreement()
    {
        $config= new teamBonusService();
        $list = $config->getAgreementSite($this->website_id);
        $this->assign("website", $list);
        $configs = new Config();
        $lists= $configs->getConfig(0,"BONUSCOPYWRITING",$this->website_id, 1);
        $this->assign("websites", $lists);
        $type=isset($_GET['type'])?$_GET['type']:'0';
        $this->assign("type", $type);
        $this->fetch('template/platform/teamApplicationAgreement');
    }

    /**
     * 添加队长等级
     */
    public function addTeamAgentLevel(){
        $agent = new teamBonusService();
        $agent_level = $agent->getAgentWeight();
        $level = new DistributorService();
        $level_info =  $level->getDistributorLevel();
        $list = $agent->getTeamBonusSite($this->website_id);
        $this->assign("website", $list);
        $this->assign("level_info", $level_info);
        $this->assign('level_weight',implode(',',$agent_level));
        $this->fetch('template/platform/addTeamAgentLevel');
    }
    /**
     * 修改队长等级
     */
    public function updateTeamAgentLevel(){
        $agent = new teamBonusService();
        $id=isset($_GET['id'])?$_GET['id']:'';
        $agent_level_info=$agent->getAgentLevelInfo($id);
        $this->assign('id',$id);
        $this->assign('list',$agent_level_info);
        $level = new DistributorService();
        $level_info =  $level->getDistributorLevel();
        $this->assign("level_info", $level_info);
        $agent_level = $agent->getAgentWeight();
        $this->assign('level_weight',implode(',',$agent_level));
        $this->fetch('template/platform/updateTeamAgentLevel');
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
    
/*-------------------------------------------------------------------前端钩子开始-----------------------------------------------------------------------*/

     /**
     * 订单创建成功后团队分红计算
     */
    public function orderTeamBonusCalculate($params)
    {
        $bonusCalculate = new teamBonusService();
        $bonusCalculate->orderAgentBonus($params);
    }
    /**
     * 订单支付成功后分红计算
     */
    public function orderTeamPayCalculate($params)
    {
        $team_service = new teamBonusService();
        $list = $team_service->getTeamBonusSite($params['website_id']);
        if(!$list || !$list['is_use']){
            return true;
        }
        $bonusLogModel = new VslOrderBonusLogModel();
        $orderSer = new \data\service\Order();
        $check = $bonusLogModel->getInfo(['order_id' => $params['order_id']], 'id');
        if(!$check){
            $orderSer->addBonusToLog($params['order_id']);
        }
        $bonusLog = $bonusLogModel->getInfo(['website_id'=>$params['website_id'],'order_id'=>$params['order_id'],'order_goods_id'=>$params['order_goods_id']]);
        if(!$bonusLog){
            return true;
        }
        if($bonusLog['team_pay_status'] == 1){
            return true;
        }
        $bonusLog['status'] = 3;
        $team_service->addTeamBonus($bonusLog);
    }
    /**
     * 订单退款成功后需要重新计算订单的分红 
     */
    public function updateTeamBonusMoney($params)
    {
        $team_service = new teamBonusService();
        $list = $team_service->getTeamBonusSite($params['website_id']);
        if(!$list || !$list['is_use']){
            return true;
        }
        $bonusLogModel = new VslOrderBonusLogModel();
        $orderSer = new \data\service\Order();
        $check = $bonusLogModel->getInfo(['order_id' => $params['order_id']], 'id');
        if(!$check){
            $orderSer->addBonusToLog($params['order_id']);
        }
        $bonusLog = $bonusLogModel->getInfo(['website_id'=>$params['website_id'],'order_id'=>$params['order_id'],'order_goods_id'=>$params['order_goods_id']]);
        if(!$bonusLog){
            return true;
        }
        if($bonusLog['team_return_status'] == 1){
            return true;
        }
        $bonusLog['status'] = 2;
        $team_service->addTeamBonus($bonusLog); 
    }

    /**
     * 分红结算(订单完成)
     */
    public function updateOrderTeamBonus($params)
    {
        $team_service = new teamBonusService();
        $order = new VslOrderModel();
        $buyer_id = $order->getInfo(['website_id'=>$params['website_id'],'order_id'=>$params['order_id']],'buyer_id')['buyer_id'];
        $list = $team_service->getTeamBonusSite($params['website_id']);
        if(!$list || !$list['is_use']){
            return true;
        }
        $bonusLogModel = new VslOrderBonusLogModel();
        $orderSer = new \data\service\Order();
        $check = $bonusLogModel->getInfo(['order_id' => $params['order_id']], 'id');
        if(!$check){
            $orderSer->addBonusToLog($params['order_id']);
        }
        $bonusLog = $bonusLogModel->getQuery(['website_id'=>$params['website_id'],'order_id'=>$params['order_id'],'team_return_status'=>0, 'team_cal_status' => 0]);
        
        if($bonusLog){
            $newLog = ['status' => 1];
            $team_bonus_arr = []; //合并多个订单商品的分红明细为一条操作
            $uid_arr = [];//取出用户列表用来更新相关代理等级
            foreach($bonusLog as $val){
                $newLog['order_id'] = $val['order_id'];
                $newLog['website_id'] = $val['website_id'];
                $newLog['team_bonus'] += $val['team_bonus'];
                $team_bonus_details = json_decode(htmlspecialchars_decode($val['team_bonus_details']),true);
                
                if(!$team_bonus_details || !is_array($team_bonus_details)){
                    continue;
                }
                foreach($team_bonus_details as $team_bonus_key => $team_bonus_val){
                    if(!isset($team_bonus_arr[$team_bonus_key])){
                        $uid_arr[] = $team_bonus_key;
                        $team_bonus_arr[$team_bonus_key]['bonus'] = $team_bonus_val['bonus'];
                    }else{
                        $team_bonus_arr[$team_bonus_key]['bonus'] += $team_bonus_val['bonus'];
                    }
                }
            }
            unset($val);
            unset($team_bonus_val);
            // if(!$team_bonus_arr){
            //     debugLog($buyer_id, '不够条件成为队长');
            //     return true;
            // }
            if($team_bonus_arr){
                $newLog['team_bonus_details'] = json_encode($team_bonus_arr,true);
                $team_service->addTeamBonus($newLog);
                foreach($uid_arr as $uid_val){
                    // 更新相关代理等级
                    $team_service->updateAgentLevelInfo($uid_val);
                }
                unset($uid_val);
            }
            
        }
        debugLog($buyer_id, '成为队长');
        $team_service->becomeAgent($buyer_id);//成为队长
    }

    /**
     * 队长自动降级
     */
    public function autoDownTeamAgentLevel($params){
        $config= new teamBonusService();
        $list = $config->getTeamBonusSite($params['website_id']);
        if($list && $list['is_use']==1){
            $config->autoDownagentLevel($params['website_id']);
        }
    }
    /**
     * 团队分红自动发放
     */
    public function autoGrantTeamBonus($params){
        $config= new teamBonusService();
        $config->autoGrantTeamBonus($params);
    }
}