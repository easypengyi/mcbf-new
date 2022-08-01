<?php
namespace addons\paygrade;

use addons\Addons;
use addons\paygrade\server\PayGrade as PayGradeServer;

class Paygrade extends Addons
{
    public $info = array(
        'name' => 'paygrade',//插件名称标识
        'title' => '付费等级',//插件中文名
        'description' => '付费等级',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'paygradeList',//
        'config_admin_hook' => 'paygradeList', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/ffdj170.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/ffdj48.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/ffdj48c.png',
    );//设置文件单独的钩子

    public $menu_info = array(
        //platform
        [
            'module_name' => '付费等级',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'paygradeList',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '等级列表',
            'parent_module_name' => '付费等级', //上级模块名称 确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1, // 是否为菜单
            'is_dev' => 0,  // 是否为开发者模式可见
            'desc' => '', //菜单描述
            'module_picture' => '', //图片，一般为空
            'icon_class' => '', //字体图标class
            'is_control_auth' => 1, //是否有控制权限
            'hook_name' => 'paygradeList',
            'module' => 'platform'
        ],
        [
            'module_name' => '修改等级',
            'parent_module_name' => '付费等级',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'updatePaygrade',
            'module' => 'platform'
        ],
        [
            'module_name' => '购买记录',
            'parent_module_name' => '付费等级',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'historyPaygrade',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '付费等级',//上级模块名称 用来确定上级目录
            'sort' => 1,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '关闭后，所有付费等级均不生效。',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'paygradeSetting',
            'module' => 'platform'
        ]
    );

    public function __construct()
    {
        parent::__construct();
        $this->assign("pageshow", PAGESHOW);
        if ($this->module == 'platform') {
            $this->assign('paygradeListUrl', __URL(addons_url_platform('paygrade://Paygrade/paygradeList')));
            $this->assign('updatePaygradelUrl', __URL(addons_url_platform('paygrade://Paygrade/updatePaygrade')));
            $this->assign('recordListUrl', __URL(addons_url_platform('paygrade://Paygrade/recordList')));
            $this->assign('getGradeUrl', __URL(addons_url_platform('paygrade://Paygrade/getGrade')));
            $this->assign('recordDataExcelUrl', __URL(addons_url_platform('paygrade://Paygrade/recordDataExcel')));
            $this->assign('saveSettingUrl', __URL(addons_url_platform('paygrade://Paygrade/saveSetting')));
        }
    }
    
    public function paygradeList()
    {
        $this->fetch('template/' . $this->module . '/paygradeList');
    }
    
    public function updatePaygrade()
    {
        $condition = [];
        $condition['pay_grade_id'] = (int)input('get.pay_grade_id');
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $PayGradeServer = new PayGradeServer();
        $info = $PayGradeServer->getPayGradeDetail($condition);
        $grade_list = $PayGradeServer->getGradeList($info['grade_type']);
        $setmeal_grade_list = $PayGradeServer->getGradeList($info['grade_type'],0);
        unset($condition['shop_id']);
        $setmeal_list = $PayGradeServer->getSetmealList($condition,'sort desc');
        $this->assign('info', $info);
        $this->assign('grade_list', $grade_list);
        $this->assign('setmeal_grade_list', $setmeal_grade_list);
        $this->assign('setmeal_list', $setmeal_list);
        $this->fetch('template/' . $this->module . '/updatePaygrade');
    }
    
    public function historyPaygrade()
    {
        $PayGradeServer = new PayGradeServer();
        $is_distribution = getAddons('distribution', $this->website_id);
        $is_globalbonus = getAddons('globalbonus', $this->website_id);
        $is_areabonus = getAddons('areabonus', $this->website_id);
        $is_teambonus = getAddons('teambonus', $this->website_id);
        $is_channel = getAddons('channel', $this->website_id);
        $grade_list = $PayGradeServer->getPayGradeList(['shop_id'=>$this->instance_id,'website_id'=>$this->website_id,'is_use'=>1],'sort desc');
        if($grade_list){
            foreach ($grade_list as $k => $v) {
                if($v['grade_type']==1 && !$is_distribution)unset($grade_list[$k]);
                if($v['grade_type']==2 && !$is_globalbonus)unset($grade_list[$k]);
                if($v['grade_type']==3 && !$is_areabonus)unset($grade_list[$k]);
                if($v['grade_type']==4 && !$is_teambonus)unset($grade_list[$k]);
                if($v['grade_type']==5 && !$is_channel)unset($grade_list[$k]);
            }
        }
        $this->assign('grade_list', $grade_list);
        $this->fetch('template/' . $this->module . '/historyPaygrade');
    }
    
    public function paygradeSetting()
    {
        // 0会员等级 1分销商等级 2股东等级 3区代等级 4队长等级 5渠道商等级 6店长等级 7入驻商家等级

        $addonsConfigSer = new \data\service\AddonsConfig();
        $info = $addonsConfigSer->getAddonsConfig('paygrade', $this->website_id);
        $PayGradeServer = new PayGradeServer();
        $is_distribution = getAddons('distribution', $this->website_id);
        $is_globalbonus = getAddons('globalbonus', $this->website_id);
        $is_areabonus = getAddons('areabonus', $this->website_id);
        $is_teambonus = getAddons('teambonus', $this->website_id);
        $is_channel = getAddons('channel', $this->website_id); 
        $grade_info = $PayGradeServer->getPayGradeDetail(['grade_type'=>0,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        debugLog($grade_info,'<-paygradeSetting1->');
        if(empty($grade_info)){//会员等级
            $res = $PayGradeServer->addPayGrade(['grade_type'=>0,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
            debugLog($res,'<-paygradeSetting2->');
        }
        $grade_info = $PayGradeServer->getPayGradeDetail(['grade_type'=>1,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        if(empty($grade_info) && $is_distribution){//分销商等级
            $PayGradeServer->addPayGrade(['grade_type'=>1,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        }
        $grade_info = $PayGradeServer->getPayGradeDetail(['grade_type'=>2,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        if(empty($grade_info) && $is_globalbonus){//股东等级
            $PayGradeServer->addPayGrade(['grade_type'=>2,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        }
        $grade_info = $PayGradeServer->getPayGradeDetail(['grade_type'=>3,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        if(empty($grade_info) && $is_areabonus){//区代等级
            $PayGradeServer->addPayGrade(['grade_type'=>3,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        }
        $grade_info = $PayGradeServer->getPayGradeDetail(['grade_type'=>4,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        if(empty($grade_info) && $is_teambonus){//队长等级
            $PayGradeServer->addPayGrade(['grade_type'=>4,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        }
        $grade_info = $PayGradeServer->getPayGradeDetail(['grade_type'=>5,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        if(empty($grade_info) && $is_channel){//渠道商等级
            $PayGradeServer->addPayGrade(['grade_type'=>5,'shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        }
        $grade_list = $PayGradeServer->getPayGradeList(['shop_id'=>$this->instance_id,'website_id'=>$this->website_id],'sort desc');
        if($grade_list){
            foreach ($grade_list as $k => $v) {
                if($v['grade_type']==1 && !$is_distribution)unset($grade_list[$k]);
                if($v['grade_type']==2 && !$is_globalbonus)unset($grade_list[$k]);
                if($v['grade_type']==3 && !$is_areabonus)unset($grade_list[$k]);
                if($v['grade_type']==4 && !$is_teambonus)unset($grade_list[$k]);
                if($v['grade_type']==5 && !$is_channel)unset($grade_list[$k]);
            }
        }
        $config_info = $PayGradeServer->getConfigDetail(['shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        $this->assign('is_use', $info['is_use']);
        $this->assign('grade_list', $grade_list);
        $this->assign('config_info', $config_info);
        $this->fetch('template/' . $this->module . '/paygradeSetting');
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