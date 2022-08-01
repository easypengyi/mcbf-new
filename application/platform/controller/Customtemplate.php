<?php

namespace app\platform\controller;

use addons\cpsunion\server\Cpsunion;
use data\model\WebSiteModel;
use data\service\Customtemplate as CustomtemplateSer;
use data\service\Goods as GoodsSer;
use data\service\Addons as AddonsSer;
use think\Db;

/**
 *
 * Class Customtemplate
 * @package app\platform\controller
 *  //type: 1:商城首页 2：店铺首页 3：商品详情中心 4：会员中心 5：分销中心 6：自定义页 9:积分商城首页
 */
class Customtemplate extends BaseController{

    protected $custom_template_model;
    protected $custom_template_service;
    
    public function __construct(){
        parent::__construct();
        $this->custom_template_service = new CustomtemplateSer();
        $this->intCustomTemplate();//初始化模板
        $this->checkModule();
    }
    
    /**
     * 初始化装修模板
     */
    public function intCustomTemplate ()
    {
        $this->custom_template_service->initAllCustomTemplate();
    }
    
    /**
     * 我的店铺
     * @return \think\response\View
     */
    public function customCenter ()
    {
        $list = $this->custom_template_service->getCustomCenter();
        $this->assign('list',$list);
        //初始数据
        return view($this->style . 'Customtemplate/customCenter');
    }
    
    /**
     * WAP二维码
     */
    public function getWapCode ()
    {
        $web_url = $this->custom_template_service->getCodeUrl(1);
        $logo = '';
        getQRcodeNotSave($web_url,$logo);
    }
    /**
     * APP二维码
     */
    public function getAppCode ()
    {
        $app_url = $this->custom_template_service->getCodeUrl(2);
        $logo = '';
        getQRcodeNotSave($app_url,$logo);
    }

    /**
     * 获取模板页面类型
     * @return array
     */
    public function getTemplateType ()
    {
        $condition['is_system_default'] = 1;
        $type = $this->custom_template_service->getDefaultBaseTypeArr();
        if(!$this->shopStatus){
            $type = array_merge(array_diff($type, [2]));
        }
        if(!$this->integralStatus){
            $type = array_merge(array_diff($type, [9]));
        }
        $condition['type'] = ['IN', $type];
        $condition['shop_id'] = 0;
        $condition['website_id'] = 0;
        $custom_template_list = $this->custom_template_service->getTemplateType($condition,'type,template_name');

        return $custom_template_list;
    }
    
    /**
     * 新增装修页面（新装修）
     */
    public function createCustomTemplate() {
        $id = request()->post('id');//使用系统模板id
        $type = request()->post('type');//页面类型 1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面, 9:积分商城首页
        $ports = request()->post('ports');//1wap 2mp 3app   多个端用，分割 【店大师只有2】
        $template_name = request()->post('template_name');
        //$ports先从小到大排序，再拼接存入
        $ports = explode(',',$ports);
        asort($ports);
        $ports = implode(',',$ports);

        if (!$ports) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $res = $this->custom_template_service->createCustomTemplate($type, $ports,$template_name,$id);
        if ($res['code']>0){
            return $res;
        }else{
            return AjaxReturn(FAIL);
        }
    }
    
    /**
     * 获取单条模板数据
     */
    public function getCustomTemplateInfoById($id) {

        $info = $this->custom_template_service->getCustomTemplateInfo([
            'id'        =>  $id,
            'website_id'=>  $this->website_id,
            'shop_id'   =>  $this->instance_id
        ]);
        return $info;
    }
    
    /**
     * 保存装修数据（新模板）
     */
    public function saveCustomTemplate() {
        $template_data_temp = request()->post('template_data/a', '');
        $id = request()->post('id', ''); // 模板id
        $preview_img= request()->post('previewImg'); // 模板数据前小半部分截图base64字符串（为了列表预览使用）
        # 如果不是所属id不能保存
        $custom_template_info = $this->getCustomTemplateInfoById($id);
        if (!$custom_template_info) {return AjaxReturn(FAIL, [], '模板不正确！');}
        if(!isset($template_data_temp['items']) || !$template_data_temp['items']){
            return ['code' => -1,'message' => '空白模板无法保存'];
        }
        $type = $template_data_temp['page']['type'];
        
        # 1、模板主要数据
        $data['template_data'] = json_encode($template_data_temp, JSON_UNESCAPED_UNICODE);// 模板数据
        if ($preview_img){
            $target_path = $this->custom_template_service->getPreviewImgUrl(1,$type);
            $preview_img_url = base64Img2Img($preview_img,$target_path,$id,375,667);
            if ($preview_img_url){
                $data['preview_img'] = $preview_img_url;
            }
        }
        Db::startTrans();
        try {
            $return = $this->custom_template_service->saveCustomTemplate($data, $id);
            if($return) {
                if(getAddons('cpsunion',$this->website_id)) {
                    $cpsunion_server = new Cpsunion();
                    $cpsunion_server->saveCpsGoods($template_data_temp);
                }
            }
            //公众号
            if ($wechat_set = request()->post('wechat_set/a', '')){
                $wechatRes = $this->custom_template_service->getUsefulTemplateInfoByType(1,10);
                if ($wechatRes['id']){
                    $return = $this->custom_template_service->saveCustomTemplate(['template_data' => json_encode($wechat_set, JSON_UNESCAPED_UNICODE)], $wechatRes['id']);
                }
            }
            Db::commit();
            return AjaxReturn(SUCCESS);
        }catch (\Exception $e){
            Db::rollback();
            return AjaxReturn(FAIL,[],$e->getMessage());
        }
    }
    
    /**
     * 使用该模板
     * @return int|\multitype
     */
    public function useCustomTemplate ()
    {
        $post = request()->post();
        $id         = $post['id'];
        $ports      = $post['ports'];//所属端口 1或者1,2或者1,2,3等
        $type       = $post['type'];//1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 11:弹窗

        if (!$ports){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        //1、新模板in_use=1; a.新模板in_use=0 b.旧模板in_use=0
        $data = $this->getCustomTemplateInfoById($id);
        if(!$data){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $templateData = json_decode($data['template_data'],true);
        if(!isset($templateData['items']) || !$templateData['items']){
            return AjaxReturn(FAIL,[],'空白模板无法使用');
        }
        $result = $this->custom_template_service->useCustomTemplateForNew($id, $type, $ports, $this->instance_id, $this->website_id);

        return $result;
    }
    
    /**
     * 编辑应用端口(针对新模板)
     */
    public function setCustomTemplatePorts ()
    {
        $post = request()->post();
        $id             = $post['id'];
        $ports          = $post['ports'];//所属端口
        $type           = $post['type'];//1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 11:弹窗
        $in_use         = $post['in_use'];//1使用 0未使用
        $template_name  = $post['template_name'];//页面名称

        if (!$ports){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        
        $data = $this->getCustomTemplateInfoById($id);
        if(!$data){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        if ($in_use) {
            //查询该ports中其他in_use=1的该状态，且去除相应ports
            $result = $this->custom_template_service->useCustomTemplateForNew($id, $type, $ports,$data['shop_id'],$data['website_id']);
            if ($result['code'] <0) {
                return $result;
            }
        }
        unset($data);
        $data['ports'] = $ports;
        if ($template_name) {
            $data['template_name'] = $template_name;
        }
        $this->custom_template_service->saveCustomTemplate($data,$id);
        
        return AjaxReturn(SUCCESS);
    }

    /**
     * 删除模板(新/旧)
     * @return int|\multitype
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function deleteCustomTemplate ()
    {
        $post = request()->post();
        $id         = $post['id'];
        $is_new     = $post['is_new'];//1新模板 0旧模板
        $ports      = $post['ports'];//所属端口
        $type       = $post['type'];//1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 11:弹窗
        if (!$is_new && !$ports){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
    
        if($is_new == 1){
            $result = $this->custom_template_service->deleteNotUseNewCustomTemplateById($id);
        }

        return $result;
        
    }
    
    /**
     * 获取营销活动列表数据
     * @return \multitype
     */
    public function modalPromotionGoodsList ()
    {
        $promotion_type = request()->post('promotion_type');//装修（活动类型：2拼团3预售4砍价5限时抢购）
        if (!$promotion_type){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $search_text = request()->post('search_text');
        $goods_type = request()->post('goods_type');//0自营 1全平台 2店铺
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        
        $condition = [
            'promotion_type' => $promotion_type,
            'page_index' => $page_index,
            'page_size' => $page_size,
            'search_text' => $search_text,
            'goods_type' => $goods_type,
        ];
        $goods_service = new GoodsSer();
        $list = $goods_service->getCustomPromotionGoodsList($condition);
        
        return AjaxReturn(SUCCESS,$list);
    }
    
    /**
     * 保存公共的底部数据
     */
    public function saveCommonTypeTemplate ()
    {

        # Tabbar
        $wapTabbar = request()->post('wapTabbar/a','');
        $mpTabbar  = request()->post('mpTabbar/a','');
        $appTabbar = request()->post('appTabbar/a','');
    
        # Copyright
        $wapCopyright = request()->post('wapCopyright/a','');
        $mpCopyright  = request()->post('mpCopyright/a','');
        $appCopyright = request()->post('appCopyright/a','');
    
        # PopupAdv
        $wapPopupAdv = request()->post('wapPopupAdv/a','');
        $mpPopupAdv  = request()->post('mpPopupAdv/a','');
        $appPopupAdv = request()->post('appPopupAdv/a','');
        
        $common_data = [
            'wap' => [
                'tabbar' => $wapTabbar,
                'copyright' => $wapCopyright,
                'popupadv' => $wapPopupAdv,
            ],
            'mp' => [
                'tabbar' => $mpTabbar,
                'copyright' => $mpCopyright,
                'popupadv' => $mpPopupAdv,
            ],
            'app' => [
                'tabbar' => $appTabbar,
                'copyright' => $appCopyright,
                'popupadv' => $appPopupAdv,
            ]
        ];
        //都保存在新模板中
        $res = $this->custom_template_service->saveCommonTypeTemplate($common_data);
        return $res;
    }
    
    /**
     * 模板市场
     */
    public function customTemplateMarket ()
    {
        if (request()->isAjax()){
            $post               = request()->post();
            $page_index         = $post['page_inde'] ?: 1;
            $page_size          = $post['page_size'] ?: PAGESIZE;
            $template_type      = $post['template_type'] ?:0;
            $port               = $post['port'];//[端口 1移动端 2小程序 3APP 4电脑端]

            $sysTemlplateList = [
                'data' => [],
                'total_count' => 0,
                'page_count' => 0,
            ];
            $sysPcTemlplateList = [
                'data' => [],
                'total_count' => 0,
                'page_count' => 0,
            ];
            if ($port == 0) {
                $sysTemlplateList = $this->custom_template_service->getSysCustomTemplateList($page_index,$page_size,$template_type,$port);
                $sysPcTemlplateList = $this->custom_template_service->getPcSysCustomTemplateList($template_type);
            } else {
                if ($port == 4) {
                    $sysPcTemlplateList = $this->custom_template_service->getPcSysCustomTemplateList($template_type);
                } else {
                    $sysTemlplateList = $this->custom_template_service->getSysCustomTemplateList($page_index,$page_size,$template_type,$port);
                }
            }
    
            $list['data'] = array_merge($sysTemlplateList['data'], $sysPcTemlplateList['data']);
            $list['total_count'] = $sysTemlplateList['total_count'] + $sysPcTemlplateList['total_count'];
            $list['page_count'] = 1;

            return $list;
            
        }
        $project = $this->custom_template_service->getCurrentEnvPortsValue();
        $searchTypes = $this->custom_template_service->getSysCustomTemplateCount(false);
        $pcSearchTypes = $this->custom_template_service->getPcSerchTypes(false);
        $searchPorts = $this->custom_template_service->getAllSearchPorts();
        
        $this->assign('searchTypes', $searchTypes);
        $this->assign('pcSearchTypes', $pcSearchTypes);
        $this->assign('searchPorts', $searchPorts);
        $this->assign('port_project', $project);
        return view($this->style . 'Customtemplate/customTemplateMarket');
    }
    
    /*********************  重新处理 ******************************/
    /********************** 重新处理 *****************************/
    
    public function ini ()
    {
        $this->custom_template_service->cliIniTemplate();
    }
    
    /**
     * 装修模板列表
     */
    public function customtemplatelist ()
    {
        if (request()->isAjax()){
            $post               = request()->post();
            $page_index         = $post['page_index'] ?: 1;
            $page_size          = $post['page_size'] ?: PAGESIZE;
            $template_name      = $post['template_name'];
            $template_type      = $post['template_type'];
            $ports              = $post['ports'] ?: 0;//0全部 1公众号/H5 2小程序 3App
            $field              = '*';

            //$ports先从小到大排序
            $ports = explode(',',$ports);
            asort($ports);
            $ports = implode(',',$ports);
            //1、查询新模板
            $newTemlplateList = $this->custom_template_service->getNewCustomTemplateList($page_index,$page_size,$field,$template_name,$template_type,$ports);
            
            //处理模板展示顺序问题
            $newTemlplateList['data'] = $this->custom_template_service->rearrangeCustomTemplateList($newTemlplateList['data'],$template_type);
            return $newTemlplateList;

        }
        $project = $this->custom_template_service->getCurrentEnvPortsValue();
        $searchPorts = $this->custom_template_service->getPortOfBaseCustomTemplateCount();
        $searchTypes = $this->custom_template_service->getSerchTypes();
        
        $this->assign("port_project", $project);
        $this->assign("instance_id", $this->instance_id);
        $this->assign('shopStatus', getAddons('wapport', $this->website_id, 0, true));
        $this->assign('integralStatus', getAddons('integral', $this->website_id));
        $this->assign('searchPorts', $searchPorts);
        $this->assign('searchTypes', $searchTypes);
        
        return view($this->style . 'Customtemplate/customTemplateList');
    }
    
    /**
     * 装修（编辑）页面
     * type: 1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗
     */
    public function customTemplate() {
        $id = request()->get('id'); //模板id
        $website_model = new WebSiteModel();
        
        if ($id) {
            $custom_template_info = $this->getCustomTemplateInfoById($id);
            $template_data = $custom_template_info['template_data'];
            $type = $custom_template_info['type'];
            $template_name = $custom_template_info['template_name'];
            $ports = $custom_template_info['ports'];
            $in_use = $custom_template_info['in_use'];
            //获取公共部分数据（所有端口的数据）
            $common_customRes = $this->custom_template_service->getCommonTemplateDataToDeal();
            $tab_bar = $common_customRes['tab_bar'];
            $copyright = $common_customRes['copyright'];
            $popadv = $common_customRes['popadv'];
            $wechat_set = $common_customRes['wechat_set'];
        }
        
        //列表数据
        $addonsSer = new AddonsSer();
        $allAddons = $addonsSer->getAddonsList(['status' => 1], 'name');
        $addonsIsUse = [];
        if($allAddons){
            foreach($allAddons as $val){
                $addonsIsUse[$val['name']] = getAddons($val['name'], $this->website_id, $this->instance_id);
            }
            unset($val);
        }
        $project = $this->custom_template_service->getCurrentEnvPortsValue(explode(',',$ports));
        $addons_info = $this->custom_template_service->getAllModuleList();//应用权限情况
        $ports_base_template = $this->custom_template_service->getSortAllBaseCustomplates();//已开启的端口基本装修数据列表
        $searchPorts = $this->custom_template_service->getSearchPorts(false);//端口数据
        
        $this->assign('type', $type);
        $this->assign('type_name', $this->custom_template_service->getTrueTypeName($type));
        $this->assign('id', $id);
        $this->assign('ports', $ports);
        $this->assign('ports_arr', explode(',',$ports));
        $this->assign('port_project', $project);
        $this->assign('in_use', $in_use);
        $this->assign('shop_id', $this->instance_id);
        $this->assign('template_data', $template_data);
        $this->assign('template_name', $template_name);
        $this->assign('waptabbar', $tab_bar[1]?:[]);
        $this->assign('mptabbar', $tab_bar[2]?:[]);
        $this->assign('apptabbar', $tab_bar[3]?:[]);
        $this->assign('wapcopyright', $copyright[1]?:[]);
        $this->assign('mpcopyright', $copyright[2]?:[]);
        $this->assign('appcopyright', $copyright[3]?:[]);
        $this->assign('wechat_set', $wechat_set);
        $this->assign('wappopadv', $popadv[1]?:[]);
        $this->assign('mppopadv', $popadv[2]?:[]);
        $this->assign('apppopadv', $popadv[3]?:[]);
        $this->assign('addonsIsUse', json_encode($addonsIsUse));
        $this->assign('shopStatus', $this->shopStatus);
        $this->assign('default_version',$website_model::get($this->website_id,['merchant_version'])['merchant_version']['is_default']);
        $this->assign('template_version',1);//新模板
        $this->assign('addons_info',json_encode($addons_info));
        $this->assign('ports_base_template',$ports_base_template);
        $this->assign('searchPorts',$searchPorts);
        return view($this->style . 'Customtemplate/customTemplate');
    }
    
    /**
     * 模板风格
     * @return \think\response\View
     */
    public function templateStyle ()
    {
        $theme_data = $this->custom_template_service->getAllThemeColorData();
        $theme_id = $this->custom_template_service->getThemeId();
        $theme_color = $this->custom_template_service->getThemeColorByThemeId($theme_id);
        $this->assign('theme_data', $theme_data);
        $this->assign('theme_id', $theme_id);
        $this->assign('theme_color', $theme_color['color']);
        return view($this->style . 'Customtemplate/templateStyle');
    }
    
    /**
     * 模板风格
     * @return \think\response\View
     */
    public function pcTemplateStyle ()
    {
        $theme_data = $this->custom_template_service->getPcAllThemeColorData();
        $theme_color = $this->custom_template_service->getPcThemeColor();
        $this->assign('theme_color', $theme_color);
        $this->assign('theme_data', $theme_data);
        return view($this->style . 'Customtemplate/pcTemplateStyle');
    }
    
    public function saveThemeId ()
    {
        $theme_id = request()->post('theme_id');
        if (!$theme_id) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $this->custom_template_service->saveThemeInfo($theme_id);
        
        return AjaxReturn(SUCCESS);
    }
    
    public function savePcTheme ()
    {
        $style = request()->post('style');
        if (!$style) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $this->custom_template_service->savePcThemeInfo($style);
        
        return AjaxReturn(SUCCESS);
    }
    
    /**
     * 获取商城商品分类
     */
    public function getShopCategory()
    {
        $categoryRes = $this->custom_template_service->getShopCategory();
        return $categoryRes;
    }
    
    private function checkModule(){
        $moduleModel = new \data\model\ModuleModel();
        $check = $moduleModel->getInfo(['url' => 'Customtemplate/customtemplatelist', 'module' => 'platform'], 'module_id');
        if($check){//已经更新过菜单
            return;
        }
        $topModule = $moduleModel->getInfo(['url' => 'config/customtemplatelist', 'module' => 'platform'], 'module_id');
        $adminTopModule = $moduleModel->getInfo(['url' => 'shop/shopconfig', 'module' => 'admin'], 'module_id');
        if($topModule){
            $moduleModel->delData(['pid' => $topModule['module_id']]);
            $this->website->addSytemModule('移动端页面', 'Customtemplate', 'customtemplatelist', $topModule['module_id'], 'Customtemplate/customtemplatelist', 1, 0, 1, '', '', '', 1, 'platform', '',1);
            $this->website->addSytemModule('移动端风格', 'Customtemplate', 'templateStyle', $topModule['module_id'], 'Customtemplate/templateStyle', 1, 0, 3, '', '', '', 0, 'platform', '',1);
            $this->website->addSytemModule('我的店铺', 'Customtemplate', 'customCenter', $topModule['module_id'], 'Customtemplate/customCenter', 1, 0, 0, '', '', '', 1, 'platform', '',1);
            $this->website->addSytemModule('模版市场', 'Customtemplate', 'customTemplateMarket', $topModule['module_id'], 'Customtemplate/customTemplateMarket', 1, 0, 5, '', '', '', 0, 'platform', '',1);
            if(getAddons('pcport', $this->website_id)){
                 $this->website->addSytemModule('PC端风格', 'Customtemplate', 'pcTemplateStyle', $topModule['module_id'], 'Customtemplate/pcTemplateStyle', 1, 0, 4, '', '', '', 1, 'platform', '',1);
                 $this->website->addSytemModule('PC端页面', 'addonslist', 'pcCustomTemplateList', $topModule['module_id'], 'addonslist/menu_addonslist?addons=pcCustomTemplateList', 1, 0, 2, '', '', '', 1, 'platform', '',1);
            }
        }
        if($adminTopModule){
            $moduleModel->delData(['pid' => $adminTopModule['module_id']]);
            $this->website->addSytemModule('移动端页面', 'Customtemplate', 'customtemplatelist', $adminTopModule['module_id'], 'Customtemplate/customtemplatelist', 1, 0, 0, '', '', '', 1, 'admin', '',0,1);
            $this->website->addSytemModule('店铺信息', 'shop', 'shopconfig', $adminTopModule['module_id'], 'shop/shopconfig', 1, 0, 2, '', '', '', 1, 'admin', '',0,1);
            $this->website->addSytemModule('模板市场', 'Customtemplate', 'customTemplateMarket', $adminTopModule['module_id'], 'Customtemplate/customTemplateMarket', 1, 0, 3, '', '', '', 1, 'admin', '',0,1);
            if(getAddons('pcport', $this->website_id)){
                $this->website->addSytemModule('PC端页面', 'addonslist', 'pcCustomTemplateList', $adminTopModule['module_id'], 'addonslist/menu_addonslist?addons=pcCustomTemplateList', 1, 0, 1, '', '', '', 1, 'admin', '',0,1);
            }
        }
        $system = new System();
        $system->deleteCache();
        $redirect = __URL(__URL__ . '/' . $this->module . "/Customtemplate/customTemplateList");
        $this->redirect($redirect);
        return;
    }
}