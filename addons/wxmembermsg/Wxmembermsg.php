<?php

namespace addons\wxmembermsg;

use addons\Addons;
use addons\wxmembermsg\server\WxMemberMsg as WxMemberMsgSer;
use data\service\Member as MemberService;

/**
 * 微信会员群发
 * Class Wxmembermsg
 * @package addons\wxmembermsg
 */
class Wxmembermsg extends Addons
{
    protected static $addons_name = 'wxmembermsg';
    protected $templateSer;
    
    public $info = array(
        'name'                  => 'wxmembermsg',//插件名称标识
        'title'                 => '会员群发',//插件中文名
        'description'           => '微信模版消息通知群发',//插件描述
        'status'                => 1,//状态 1使用 0禁用
        'author'                => 'vslaishop',// 作者
        'version'               => '1.0',//版本号
        'has_addonslist'        => 1,//是否有下级插件
        'no_set'                => 1,//不需要应用设置
        'content'               => '',//插件的详细介绍或者使用方法
        'config_hook'           => 'wxMemberMsgList',
        'logo'                  => 'https://pic.vslai.com.cn/upload/common/qf170.png',
        'logo_small'            => 'https://pic.vslai.com.cn/upload/common/qf48.png',
        'logo_often'            => 'https://pic.vslai.com.cn/upload/common/qf48c.png',
    );//设置文件单独的钩子
    
    public $menu_info = array(
        //platform
        [
            'module_name'               => '会员群发',
            'parent_module_name'        => '应用',//上级模块名称 用来确定上级目录
            'sort'                      => 0,//菜单排序
            'is_menu'                   => 1,//是否为菜单
            'is_dev'                    => 0,//是否是开发模式可见
            'desc'                      => '微信模版消息通知群发',//菜单描述
            'module_picture'            => '',//图片（一般为空）
            'icon_class'                => '',//字体图标class（一般为空）
            'is_control_auth'           => 1,//是否有控制权限
            'hook_name'                 => 'wxMemberMsgList',
            'module'                    => 'platform',
            'is_main'                   => 1
        ],
        # 1
        [
            'module_name'               => '模板列表',
            'parent_module_name'        => '会员群发', //上级模块名称 确定上级目录
            'sort'                      => 0, // 菜单排序
            'is_menu'                   => 1, // 是否为菜单
            'is_dev'                    => 0,  // 是否为开发者模式可见
            'desc'                      => '', //菜单描述
            'module_picture'            => '', //图片，一般为空
            'icon_class'                => '', //字体图标class
            'is_control_auth'           => 1, //是否有控制权限
            'hook_name'                 => 'wxMemberMsgList',
            'module'                    => 'platform'
        ],
        [
            'module_name'               => '添加群发模板',
            'parent_module_name'        => '模板列表',//上级模块名称 用来确定上级目录
            'sort'                      => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu'                   => 0,//是否为菜单
            'is_dev'                    => 0,//是否是开发模式可见
            'desc'                      => '',//菜单描述
            'module_picture'            => '',//图片（一般为空）
            'icon_class'                => '',//字体图标class（一般为空）
            'is_control_auth'           => 1,//是否有控制权限
            'hook_name'                 => 'addWxMemberMsgTemplate',
            'module'                    => 'platform'
        ],
        [
            'module_name'               => '编辑群发模板',
            'parent_module_name'        => '模板列表',//上级模块名称 用来确定上级目录
            'sort'                      => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu'                   => 0,//是否为菜单
            'is_dev'                    => 0,//是否是开发模式可见
            'desc'                      => '',//菜单描述
            'module_picture'            => '',//图片（一般为空）
            'icon_class'                => '',//字体图标class（一般为空）
            'is_control_auth'           => 1,//是否有控制权限
            'hook_name'                 => 'editWxMemberMsgTemplate',
            'module'                    => 'platform'
        ],
        [
            'module_name'               => '群发',
            'parent_module_name'        => '模板列表',//上级模块名称 用来确定上级目录
            'sort'                      => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu'                   => 0,//是否为菜单
            'is_dev'                    => 0,//是否是开发模式可见
            'desc'                      => '每种推送方式的会员人数不同，群发时间会有细微的偏差！<br/>过度使用模版消息群发有可能会受到微信的处罚，请谨慎使用！',//菜单描述
            'module_picture'            => '',//图片（一般为空）
            'icon_class'                => '',//字体图标class（一般为空）
            'is_control_auth'           => 1,//是否有控制权限
            'hook_name'                 => 'sendWxMemberMsgTemplate',
            'module'                    => 'platform'
        ],
        # 2
        [
            'module_name'               => '群发记录',
            'parent_module_name'        => '会员群发',//上级模块名称 用来确定上级目录
            'sort'                      => 1,//菜单排序
            'is_menu'                   => 1,//是否为菜单
            'is_dev'                    => 0,//是否是开发模式可见
            'desc'                      => '',//菜单描述
            'module_picture'            => '',//图片（一般为空）
            'icon_class'                => '',//字体图标class（一般为空）
            'is_control_auth'           => 1,//是否有控制权限
            'hook_name'                 => 'wxMemberMsgRecord',
            'module'                    => 'platform'
        ],
        [
            'module_name'               => '群发记录详情',
            'parent_module_name'        => '群发记录',//上级模块名称 用来确定上级目录
            'sort'                      => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu'                   => 0,//是否为菜单
            'is_dev'                    => 0,//是否是开发模式可见
            'desc'                      => '',//菜单描述
            'module_picture'            => '',//图片（一般为空）
            'icon_class'                => '',//字体图标class（一般为空）
            'is_control_auth'           => 1,//是否有控制权限
            'hook_name'                 => 'wxMemberMsgRecordDetail',
            'module'                    => 'platform'
        ],
        
        //admin
    );

    public function __construct()
    {
        $this->templateSer = new WxMemberMsgSer();
        parent::__construct();
        if ($this->module == 'platform' || $this->module == 'admin') {
            $this->assign('wxMemberMsgListUrl', __URL(call_user_func('addons_url_' . $this->module, 'wxmembermsg://WxMemberMsg/wxMemberMsgList')));
            $this->assign('addWxMemberMsgTemplateUrl', __URL(call_user_func('addons_url_' . $this->module, 'wxmembermsg://WxMemberMsg/addWxMemberMsgTemplate')));
            $this->assign('delWxMemberMsgTemplateUrl', __URL(call_user_func('addons_url_' . $this->module, 'wxmembermsg://WxMemberMsg/delWxMemberMsgTemplate')));
            $this->assign('sendWxMemberMsgTemplateUrl', __URL(call_user_func('addons_url_' . $this->module, 'wxmembermsg://WxMemberMsg/sendWxMemberMsgTemplate')));
            $this->assign('wxMemberMsgRecordUrl', __URL(call_user_func('addons_url_' . $this->module, 'wxmembermsg://WxMemberMsg/wxMemberMsgRecord')));
            $this->assign('wxMemberMsgRecordDetailUrl', __URL(call_user_func('addons_url_' . $this->module, 'wxmembermsg://WxMemberMsg/wxMemberMsgRecordDetail')));
        }
    }
    
    /*
     * 模板列表
     */
    public function wxMemberMsgList()
    {
        $this->fetch('template/' . $this->module . '/wxMemberMsgList');
    }
    
    /*
     * 模板记录
     */
    public function wxMemberMsgRecord()
    {
        $send_type = [
            ['id' => 1, 'value' => '按会员等级推送'],
            ['id' => 2, 'value' => '按会员标签推送'],
            ['id' => 4, 'value' => '按会员ID推送'],
            ['id' => 5, 'value' => '推送所有会员'],
        ];
        if (getAddons('distribution',$this->website_id)) {
            array_push($send_type,['id' => 3, 'value' => '按分销商等级推送']);
        }
        $this->assign('send_type', $send_type);
        $this->fetch('template/' . $this->module . '/wxMemberMsgRecord');
    }
    
    /*
     * 添加模板
     */
    public function addWxMemberMsgTemplate()
    {
        $domian = getDomain($this->website_id);
        $this->assign('real_ip', $domian);
        
        $this->fetch('template/' . $this->module . '/addWxMemberMsgTemplate');
    }
    
    /*
 * 编辑群发消息模板
 */
    public function editWxMemberMsgTemplate ()
    {
        $id             = request()->get('id');
        if (!$id) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        
        $condition = [
            'id'        => $id
        ];
        $template = $this->templateSer->getMsgTemplate($condition);
        if (!$template) {
            return AjaxReturn(FAIL, [] , '信息不存在!');
        }
        
        $template['value']          = json_decode($template['value'], true);
        $template['key_count']      = count($template['value']['content']);
        $domian = getDomain($this->website_id);
        $this->assign('is_edit', 1);
        $this->assign('real_ip', $domian);
        $this->assign('template_info', $template);
        $this->fetch('template/' . $this->module . '/addWxMemberMsgTemplate');
    }
    
    /*
     * 群发模板
     */
    public function sendWxMemberMsgTemplate()
    {

        $id             = request()->get('id');
        if (!$id) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        // 查询模板信息
        $condition  = [
            'id'            => $id,
            'website_id'    => $this->website_id
        ];
        // 模板信息
        $template_info = $this->templateSer->getMsgTemplate($condition, 'wx_template_name,id,send_type_id,status');
        $msg_type_list = [];
        if ($template_info['send_type_id']) {
            $msg_type_list = $this->templateSer->getMsgType(['id' => $template_info['send_type_id']]);
            if (in_array($msg_type_list['send_type'], [1,2,3])) {//这3个是数组格式
                //$msg_type_list['send_sub_type'] = json_decode($msg_type_list['send_sub_type'], true);
                $msg_type_list['send_sub_type'] = explode(',',$msg_type_list['send_sub_type']);
            }
        }
        
        // 查询推送方式
        $typeList = $this->templateSer->getUserSendTypesCombineList();
        $this->assign('template_info', $template_info);
        $this->assign('distribution', getAddons('distribution', $this->website_id));
        $this->assign('list', $typeList);
        $this->assign('msg_type_list', $msg_type_list);
        $this->fetch('template/' . $this->module . '/sendWxMemberMsgTemplate');
    }
    
    /*
     * 模板详情
     */
    public function wxMemberMsgRecordDetail()
    {
        $record_id = request()->get('record_id');
        $this->assign('record_id', $record_id);
        $this->fetch('template/' . $this->module . '/wxMemberMsgRecordDetail');
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