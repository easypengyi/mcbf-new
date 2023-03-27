<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/18 0018
 * Time: 17:35
 */

namespace addons\miniprogram;

use addons\Addons;
use addons\miniprogram\model\WeixinAuthModel;
use data\extend\WchatOpen;
use data\service\Config as ConfigService;
use think\Request;
use think\Session;
use addons\miniprogram\service\MiniProgram as miniProgramService;
use data\service\WebSite as WebSiteSer;

class MiniProgram extends Addons
{
    protected static $addons_name = 'miniprogram';

    public $info = [
        'name' => 'miniprogram',//插件名称标识
        'title' => '小程序',//插件中文名
        'description' => '在线生成发布，唾手可得',//插件描述
        'status' => 1,//状态 1使用 0禁用
        'author' => 'vslaishop',// 作者
        'version' => '1.0',//版本号
        'has_addonslist' => 1,//是否有下级插件
        'content' => '',//插件的详细介绍或者使用方法
        'config_hook' => 'miniprogram',//
        'config_admin_hook' => 'miniProgramManage', //
        'logo' => 'https://pic.vslai.com.cn/upload/common/1554197114.png',
        'logo_small' => 'https://pic.vslai.com.cn/upload/common/1563782173.png',
        'logo_often' => 'https://pic.vslai.com.cn/upload/common/1563782293.png',
    ];//设置文件单独的钩子

    public $menu_info = [
        //platform
        [
            'module_name' => '小程序',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 8,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '在线生成发布，唾手可得',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => 'icon-mini-program',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'miniProgramManage',
            'module' => 'platform',
            'is_main' => 1
        ],
        [
            'module_name' => '小程序管理',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 1,//子菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '任何时候您都可以通过绑定体验者来查看小程序最新效果。请务必先体验，效果满意后再保存发布！',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'miniProgramManage',
            'module' => 'platform'
        ],
        [
            'module_name' => '代码库',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 1,//子菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '小程序模板库',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'templateManage',
            'module' => 'platform'
        ],
        [
            'module_name' => '基础设置',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 3,//菜单排序
            'is_menu' => 1,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '可对小程序授权、微信支付配置、微信消息模版通知配置。',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'miniProgramSetting',
            'module' => 'platform'
        ],
        [
            'module_name' => '授权小程序',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '小程序授权',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'miniProgramAuth',
            'module' => 'platform'
        ],
        [
            'module_name' => '回调小程序',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 0,//上一个菜单名称 用来确定菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '小程序回调',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'miniProgramCallback',
            'module' => 'platform'
        ],
        [
            'module_name' => 'testerList',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'testerList',
            'module' => 'platform'
        ],
        [
            'module_name' => 'submitModal',
            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
            'sort' => 2,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 0,//是否有控制权限
            'hook_name' => 'submitModal',
            'module' => 'platform'
        ],
//        [
//            'module_name' => '装修小程序',
//            'parent_module_name' => '小程序',//上级模块名称 用来确定上级目录
//            'sort' => 0,//菜单排序
//            'is_menu' => 0,//是否为菜单
//            'is_dev' => 0,//是否是开发模式可见
//            'desc' => '',//菜单描述
//            'module_picture' => '',//图片（一般为空）
//            'icon_class' => '',//字体图标class（一般为空）
//            'is_control_auth' => 1,//是否有控制权限
//            'hook_name' => 'miniProgramCustom',
//            'module' => 'platform'
//        ],
        //admin
        [
            'module_name' => '小程序',
            'parent_module_name' => '应用',//上级模块名称 用来确定上级目录
            'sort' => 0,//菜单排序
            'is_menu' => 0,//是否为菜单
            'is_dev' => 0,//是否是开发模式可见
            'desc' => '在线生成发布，唾手可得',//菜单描述
            'module_picture' => '',//图片（一般为空）
            'icon_class' => '',//字体图标class（一般为空）
            'is_control_auth' => 1,//是否有控制权限
            'hook_name' => 'customtemplatelist',
            'module' => 'admin',
            'is_admin_main' => 1,//c端应用页面主入口标记
            'self_url' => 'Customtemplate/customtemplatelist'//自定义路由
        ],
    ];
    public $mini_program_service;
    public $wchat_open;

    public function __construct()
    {
        parent::__construct();
        $this->mini_program_service = new miniProgramService();
        $this->wchat_open = new WchatOpen($this->website_id);
        $this->instance_id = $this->instance_id ? $this->instance_id : $this->user->getSessionInstanceId();
    }

    public function miniProgramManage()
    {

        //$auth_id = request()->get('auth_id');
        // 目前一个website_id对象只有一个小程序
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $mini_program_info = $this->mini_program_service->miniProgramInfo($condition);
        $this->assign('mplive', isExistAddons('mplive', $this->website_id));

        if($this->module=='platform' || $this->module == 'admin'){
        if (empty($mini_program_info)) {
            // 绑定新的小程序
            $this->wchat_open->deleteRedis();
            $callback = Request::instance()->domain() . '/' . $this->module . '/Menu/addonmenu?addons=miniProgramCallback';
            $auth_url = $this->wchat_open->auth($callback); //授权认证
            $this->assign('auth_url', $auth_url);
            $this->assign('miniProgramManageUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniProgram/miniProgramManage')));
            $this->assign('miniProgramAuth', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniProgram/miniProgramAuth')));
            $this->assign('commitUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/commitMp')));
            $this->fetch('template/' . $this->module . '/miniProgramAuth');
        } else {
            $this->assign('mp_info', $mini_program_info);
            $this->assign('bindMpTesterUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/bindMpTester')));
            $this->assign('testQrCodeUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/testerQrCode')));
            $this->assign('commitUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/commitMp')));
            $this->assign('submitModalUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/submitModal')));
            $this->assign('submitUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/submit')));
            $this->assign('editMpUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniProgram/editMpTemplateName')));
            $this->assign('sunCodeUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/getLimitMpCode')));
            $this->assign('downSunCodeUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/downSunCode')));
            $this->assign('submitListUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/submitList')));
            $this->assign('getPublicStatusUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/getPublicStatus')));
            $this->assign('isUseMiniProgramUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/isUseAndHasCategory')));
            $this->assign('isHasAppSecretUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/isHasAppSecret')));
            $this->assign('recallcommitMpUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/recallcommitMp')));
            $this->assign('replaceCodeAndPackageDownloadUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/replaceCodeAndPackageDownload')));
            $this->assign('status', $this->mini_program_service->getLastSumitStatus($condition));

            $websiteSer = new WebSiteSer();
            $isFree = $websiteSer->checkCurrentVersionIsFree();
            $this->assign('isFree', $isFree);
            $this->fetch('template/' . $this->module . '/miniProgramManage');
            }

        }
    }


    public function miniProgramSetting()
    {
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $mini_program_info = $this->mini_program_service->miniProgramInfo($condition);
        if (empty($mini_program_info)) {
            // 绑定新的小程序
            $callback = Request::instance()->domain() . '/' . $this->module . '/Menu/addonmenu?addons=miniProgramCallback';
            $auth_url = $this->wchat_open->auth($callback);
            $this->assign('auth_url', $auth_url);
            $this->assign('miniProgramManageUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniProgram/miniProgramManage')));
            $this->assign('miniProgramAuth', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniProgram/miniProgramAuth')));
            $this->assign('commitUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/commitMp')));
            $this->assign('is_new_code', 0);
            $this->fetch('template/' . $this->module . '/miniProgramAuth');
        } else {
            $addonsConfigSer = new \data\service\AddonsConfig();
            $addons_setting = $addonsConfigSer->getAddonsConfig(self::$addons_name, $this->website_id);
            $mini_program_info['is_use'] = $addons_setting['is_use'] ?: 0;
            // 调接口获取类目
            $category_list = $this->mini_program_service->getMpCategoryForCommit();
            $weixin_auth_model = new WeixinAuthModel();
            $weixin_auth_model->save(['category' => $category_list],['website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
            $mini_program_info['category_array'] = json_decode($category_list, true);
            $is_new_code = $weixin_auth_model->getInfo(['website_id' => $this->website_id],'is_new_code')['is_new_code'];
            $this->assign('is_new_code', $is_new_code);
            $this->assign('base_info', $mini_program_info);
            $this->assign('mp_info', $mini_program_info);
            $callback = Request::instance()->domain() . '/' . $this->module . '/Menu/addonmenu?addons=miniProgramCallback';
            $auth_url = $this->wchat_open->auth($callback);
            $this->assign('auth_url', $auth_url);

            $config_service = new ConfigService();
            $wx_set = $config_service->getConfig(0, 'WPAY', $this->website_id, 1);
            $this->assign("wx",$wx_set);
            $pay_info = $config_service->getConfig($this->instance_id,"MPPAY",$this->website_id);
            $pay_info['value_array'] = $pay_info['value'] ? : [];
            $this->assign('pay_info', $pay_info);
            $this->assign('saveMpPayUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/saveMpPay')));
            $this->assign('addMtRelationUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/addMessageTemplateRelation')));
            $this->assign('addMtRelationKeyUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/addMessageTemplateRelationKey')));
            $this->assign('deleteMtRelationUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/deleteMessageTemplateRelation')));
            $this->assign('downSunCodeUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/downSunCode')));
            $this->assign('saveMiniProgramSettingUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/miniProgramSetting')));
            $this->assign('getMpSettingUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/getMpSetting')));
            $this->assign('getNewMpBaseInfoUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/getNewMpBaseInfo')));
            $this->assign('postDomainToMpUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/postDomainToMp')));
            $this->assign('postSetPrivacySettingUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/postSetPrivacySetting')));

            $template_data = $this->mini_program_service->mpTemplateList(['shop_id' => 0, 'website_id' => 0]);
            $this->assign('template_list', $template_data);
            $this->assign('website_id', $this->website_id);
            $relation_list = $this->mini_program_service->relation(['shop_id' => $this->instance_id, 'website_id' => $this->website_id]);
            $this->assign('relation_list', $relation_list);
            $this->assign('sunCodeUrl', $mini_program_info['sun_code_url']);
            $this->assign('payConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payConfigMir')));
            $this->assign('payWxConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payWxConfigMir')));
            $this->assign('payBConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payBConfigMir')));
            $this->assign('payDConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payDConfigMir')));
			$this->assign('payGpConfigMirUrl', __URL(call_user_func('addons_url_' . $this->module,'miniprogram://Miniprogram/payGpConfigMir')));
            $this->fetch('template/' . $this->module . '/miniProgramSetting');
        }

    }

    public function miniProgramCallback()
    {
        $auth_code = request()->get('auth_code');
        $expires_in = request()->get('expires_in');
        Session::set('auth_code', $auth_code, $expires_in);
        // 获取接口调用凭据和授权信息
        $auth_info = $this->wchat_open->get_query_auth($auth_code);
        $authorization_info = $auth_info->authorization_info;
        if ($authorization_info) {
            $auth_data['shop_id'] = $this->instance_id;
            $auth_data['authorizer_appid'] = $authorization_info->authorizer_appid;
            $auth_data['authorizer_refresh_token'] = $authorization_info->authorizer_refresh_token;
            $auth_data['authorizer_access_token'] = $authorization_info->authorizer_access_token;
            $auth_data['func_info'] = json_encode($authorization_info->func_info, true);
            $auth_data['update_token_time'] = time();
            $auth_data['uid'] = $this->uid;
            $auth_data['website_id'] = $this->website_id;

            // 基本信息（授权流程）
            $auth_base_info = $this->wchat_open->get_authorizer_info($auth_data['authorizer_appid']);
            $authorizer_info = $auth_base_info->authorizer_info;
            if ($authorizer_info) {
                $auth_data['nick_name'] = $authorizer_info->nick_name;
                $auth_data['head_img'] = $authorizer_info->head_img;
                $auth_data['user_name'] = $authorizer_info->user_name;
                $auth_data['alias'] = $authorizer_info->alias;
                $auth_data['qrcode_url'] = $authorizer_info->qrcode_url;
                $auth_data['signature'] = $authorizer_info->signature;
                $auth_data['category'] = json_encode($authorizer_info->MiniProgramInfo->categories, JSON_UNESCAPED_UNICODE);
                $auth_data['real_name_status'] = $authorizer_info->verify_type_info->id;
            }

            // 获取类目
            if (empty($authorizer_info->MiniProgramInfo->categories)) {
                $category_list = $this->mini_program_service->getMpCategoryList();
                $auth_data['category'] = $category_list;
            }
            // 小程序授权时需要添加的一些额外参数
            $auth_data['new_auth_state'] = 1;// 授权就设置1，生成太阳码后改成0，为了标记新太阳码生成

            $app_id_data = $this->mini_program_service->miniProgramInfo(['authorizer_appid' => $auth_data['authorizer_appid'],'website_id'=>$this->website_id]);
            if ($app_id_data) {
                $this->mini_program_service->saveWeixinAuth($auth_data, ['authorizer_appid' => $auth_data['authorizer_appid'],'website_id' => $this->website_id]);
            } else {
                $auth_data['auth_time'] = time();
                // 只存在一个小程序,当授权新的小程序时先删除旧的小程序
                $this->mini_program_service->weixinAuthCheck(['shop_id' => $this->instance_id, 'website_id' => $this->website_id]);
                $this->mini_program_service->saveWeixinAuth($auth_data);
            }
            //重置authorize_access_token redis
            $this->wchat_open->setMpAuthorizerAccessToken($auth_data['authorizer_access_token']);

            //获取太阳码
            $sun_code_url = $this->mini_program_service->getLimitMpCode();
            if (!isset($sun_code_url['code'])){
                // 封装生成太阳码的方法，修改new_auth_state = 1
                $this->mini_program_service->saveWeixinAuth([
                    'sun_code_url' => $sun_code_url,
                    'new_auth_state' => 0,
                ],['website_id' => $this->website_id]);
            }

//            // 添加域名到小程序
//            $mini_program_controller->postDomainToMp();

            // 开启小程序商城
            $this->mini_program_service->openMpShop();
            setAddons('miniprogram', $this->website_id, $this->instance_id);
            // 删除session里的access_token and app_id
            Session::delete(['authorizer_access_token', 'app_id']);
            if ($this->module == 'platform') {
                $this->assign('getMpAppIdUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/getMiniProgramAppId')));
                $this->assign('saveMpAppSecretUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/saveMpAppSecret')));
                $this->fetch('template/' . $this->module . '/miniProgramConfigurate');

            } elseif ($this->module == 'admin') {
                header('location:' . __URL('ADDONS_ADMIN_MAINminiProgramSetting'));
            }
        }
    }

    public function install()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }
    /**
     * 体验者列表
     */
    public function testerList()
    {
        $return = $this->wchat_open->testerList();
        $list = [];
        $error_message = '';
        if (!$return->errcode) {
            if (is_array($return->members)) {
                foreach ($return->members as $v) {
                    $temp_tester = [];
                    $temp_tester['user_str'] = $v->userstr;

                    $list[] = $temp_tester;
                }
            }
        } else {
            $error_message = $return->errmsg;
        }
        $this->assign('error_message', $error_message);
        $this->assign('list', $list);
        $this->assign('unBindTesterUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://Miniprogram/unbindtester')));
        return $this->fetch('/template/' . $this->module . '/' . 'testerList');
    }
    /**
     * 提交审核的modal框
     * @return mixed
     * @throws \Exception
     */
    public function submitModal()
    {
        $category_result = $this->wchat_open->getMpCategory($this->authorizer_access_token);
        $page_result = $this->wchat_open->getMpPage($this->authorizer_access_token);
        $category_list = $page_list = [];
        if ($category_result->errcode == 0) {
            $category_list = $category_result->category_list;
        }
        if ($page_result->errcode == 0) {
            $page_list = $page_result->page_list;
        }
        $this->assign('page_list', objToArr($page_list));
        $this->assign('category_list', objToArr($category_list));
        return $this->fetch('/template/' . $this->module . '/' . 'submitModal');
    }

    /**
     * 代码库
     */
    public function templateManage ()
    {
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $mini_program_info = $this->mini_program_service->miniProgramInfo($condition,'auth_id');
        if ($mini_program_info){
            if(file_exists(ROOT_PATH.'version.php')){
                $this->assign('draftListUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/draftList')));
                $this->assign('templateListUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/templateList')));
                $this->assign('addToTemplateUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/addToTemplate')));
                $this->assign('deleteTemplateUrl', __URL(call_user_func('addons_url_' . $this->module, 'miniprogram://miniprogram/deleteTemplate')));
                $this->assign('is_view',1);
                $this->fetch('template/' . $this->module . '/templateManage');
            }
        }
    }
}
