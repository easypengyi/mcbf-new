<?php

namespace data\service;

/**
 * 装修业务层
 */
use addons\appshop\model\AppCustomTemplate;
use addons\pcport\controller\Pcport as pcportController;
use addons\miniprogram\model\MpCustomTemplateModel;
use addons\miniprogram\model\WeixinAuthModel;
use data\model\CustomTemplateAllModel;
use data\model\CustomTemplateModel;
use data\model\SysPcCustomStyleConfigModel;
use data\service\Addons as AddonsService;
use data\service\BaseService as BaseService;
use data\service\Addons as AddonsSer;
use think\Db;

class Customtemplate extends BaseService
{
    protected $custom_template_model;
    protected $config_module;
    protected $template_port_num = 3;//现有模板的端口数量
    protected $template_base_type = [1,2,3,4,5,6,9];//基本模板type[1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
    protected $pc_template_base_type = [1,2,3,6];//基本模板type[1:商城首页 2:店铺首页 3:商品详情页 6:自定义页面]
    protected $template_common_type = [7,8,10,11];//7:底部 8:版权信息 10:公众号 11:弹窗
    protected $bottom_type = 7;
    protected $base_ports_arr; #环境基本端口
    public $error_url = 'public/ErrorLog/custom/custom_err.txt';
    protected $transfer_success = '.transfer_success';
    protected $transfer_fail = 'public/ErrorLog/transfer_fail.txt';
    public $port_mini_ico = [
        'wap' => '/public/platform/static/images/custom-icon-wx-mini.png',/*公众号*/
        'h5' => '/public/platform/static/images/custom-icon-h5-mini.png',/*h5*/
        'mp' => '/public/platform/static/images/custom-icon-mp-mini.png',/*小程序*/
        'app' => '/public/platform/static/images/custom-icon-app-mini.png',/*APP*/
    ];
    
    function __construct()
    {
        parent::__construct();
        $this->custom_template_model = new CustomTemplateAllModel();
        # 微商来3  店大师1
        if(currentEnv() == 1){
          $this->template_port_num = 2;
          $this->base_ports_arr = [1,2];//1wap 2mp 3app
        }elseif (currentEnv() == 2){
            $this->template_port_num = 1;
            $this->base_ports_arr = [2];//2mp
        }
        $this->initAllCustomTemplate();
    }
    
    /******************* 初始化相关 START***************************/
    /**
     * 初始化模板数据
     * @param int $ports_arr [1wap 2mp 3app]
     * @param int $website_id
     * @param int $shop_id
     * @throws \Exception
     */
    public function iniCustomTemplate ($ports_arr,$website_id=0, $shop_id=0)
    {
        $condition['is_system_default'] = 1;
        $condition['is_default'] = 1;
        if (!getAddons('wapport', $website_id)) {
            $condition['type'] = ['<>', 2];// 不开启店铺应用
        }
        if (!getAddons('integral',$website_id)){
            $condition[] = ['exp','type != 9'];//开启积分商城才会初始化
        }
        //先剔除公共部分，后面再重新处理
        $template_common_type_str = '('.implode(',',$this->template_common_type).')';
        $condition[] = ['exp', ' type not in '.$template_common_type_str];
        //判断店铺
        if ($shop_id > 0) {
            $condition['type'] = ['IN', [2, 3]];// 店铺的初始化可装修的页面只有店铺首页和商品详情页
        }
        $system_default_list = $this->custom_template_model->getQuery($condition, '*', 'type asc');
        $data = [];
        $is_admin = $this->instance_id;
        foreach ($system_default_list as $k => $v) {
            $data[$k]['template_name'] = $v['template_name'];
            if ($is_admin) {
                $v['template_data'] =  str_replace('"goodstype":"0"', '"goodstype":"2"', $v['template_data']);//C端默认goodstype=2 店铺
            }
            $data[$k]['template_data'] = $v['template_data'];
            $data[$k]['website_id'] = $website_id;
            $data[$k]['shop_id'] = $shop_id;
            $data[$k]['type'] = $v['type'];
            $data[$k]['is_default'] = $v['is_default'];
            $data[$k]['in_use'] = 1;
            $data[$k]['ports'] = implode(',',$ports_arr);
            if ($v['type'] == 1){
                $data[$k]['preview_img'] = $v['preview_img'];
            }
        }
        
        //处理底部
        $common_data = [];
        $check_condition = [
            'website_id' => $this->website_id,
            'type' => ['IN',$this->template_common_type],
            'ports' => ['LIKE', "%" . implode(',', $ports_arr) . "%"]
        ];
        $isExist = $this->custom_template_model->getInfo($check_condition);
        if (!$isExist && $this->instance_id == 0){
            $common_condition = [
                'is_system_default' => 1,
                'is_default' => 1,
                'type' => ['in',$this->template_common_type]
            ];
            $common_type_list = $this->custom_template_model->getQuery($common_condition, '*', 'type asc');
            //每个端口需要一份公共的模板
            foreach ($ports_arr as $port){
                $common_data_key = [];
                foreach ($common_type_list as $kk => $v) {
                    if ($port != 1 && $v['type'] == 10) {
                        continue;
                    }
                    $common_data_key['template_name'] = $v['template_name'];
                    $common_data_key['template_data'] = $v['template_data'];
                    $common_data_key['website_id'] = $website_id;
                    $common_data_key['shop_id'] = $shop_id;
                    $common_data_key['type'] = $v['type'];
                    $common_data_key['is_default'] = $v['is_default'];
                    $common_data_key['in_use'] = 1;
                    $common_data_key['ports'] = $port;
                    $common_data[] = $common_data_key;
                }
            }
        }
        $all_data = array_merge($data,$common_data);
        $all_data = sortArrByManyField($all_data,'type',SORT_ASC);
        if (!empty($data)) {
            $this->custom_template_model->saveAll($all_data);
        }
    }
    /**
     * 初始化店铺(平台)装修模板 - 统一三个端
     * @param     $ports_arr
     * @param int $website_id
     * @param int $shop_id
     * @throws \Exception
     */
    public function initCustomTemplateOfAll($ports_arr,$website_id=0, $shop_id=0)
    {
        
        //1、根据需要初始化的type进行初始化
        $website_id = $website_id?:$this->website_id;
        $shop_id = $shop_id?:$this->instance_id;
        $check_condition = [
            'website_id' => $website_id,
            'shop_id' => $shop_id,
        ];
        $exist = $this->custom_template_model->getCount($check_condition);
        if($exist>0){return;}
        $condition['is_system_default'] = 1;
        $condition['is_default'] = 1;
        if ($shop_id > 0) {
            // 店铺的初始化可装修的页面只有店铺首页和商品详情页
            $condition['type'] = ['IN', [2, 3]];
        }
        if (!getAddons('wapport', $website_id)) {
            $condition['type'] = ['<>', 2];// 不开启店铺应用
        }
        if (!getAddons('integral',$website_id)){
            $condition[] = ['exp','type != 9'];//开启积分商城才会初始化
        }
        //先剔除公共部分，后面再重新处理
        $condition['type'] = ['not in', $this->template_common_type];
        $template_common_type_str = '('.implode(',',$this->template_common_type).')';
        $condition[] = ['exp', ' type not in '.$template_common_type_str];
        
        $system_default_list = $this->custom_template_model->getQuery($condition, '*', 'type ASC');
        $data = [];
        $is_admin = $this->instance_id;
        foreach ($system_default_list as $k => $v) {
            $data[$k]['template_name'] = $v['template_name'];
            if ($is_admin) {
                $v['template_data'] =  str_replace('"goodstype":"0"', '"goodstype":"2"', $v['template_data']);//C端默认goodstype=2 店铺
            }
            $data[$k]['template_data'] = $v['template_data'];
            $data[$k]['website_id'] = $website_id;
            $data[$k]['shop_id'] = $shop_id;
            $data[$k]['type'] = $v['type'];
            $data[$k]['is_default'] = $v['is_default'];
            $data[$k]['in_use'] = 1;
            $data[$k]['ports'] = implode(',',$ports_arr);
            if ($v['type'] == 1){
                $data[$k]['preview_img'] = $v['preview_img'];
            }
        }
        
        //处理底部
        $common_data = [];
        $check_condition = [
            'website_id' => $this->website_id,
            'type' => ['in',$this->template_common_type]
        ];
        $isExist = $this->custom_template_model->getInfo($check_condition);
        /*平台且为空*/
        if (!$isExist && $this->instance_id==0){
            //处理底部
            $common_condition = [
                'is_system_default' => 1,
                'type' => ['in',$this->template_common_type]
            ];
            $common_type_list = $this->custom_template_model->getQuery($common_condition, '*', 'type ASC');
            //每个端口需要一份公共的模板
            foreach ($ports_arr as $port){
                $common_data_key = [];
                foreach ($common_type_list as $kk => $v) {
                    if ($port != 1 && $v['type'] == 10) {
                        continue;
                    }
                    $common_data_key['template_name'] = $v['template_name'];
                    $common_data_key['template_data'] = $v['template_data'];
                    $common_data_key['website_id'] = $website_id;
                    $common_data_key['shop_id'] = $shop_id;
                    $common_data_key['type'] = $v['type'];
                    $common_data_key['is_default'] = $v['is_default'];
                    $common_data_key['in_use'] = 1;
                    $common_data_key['ports'] = $port;
                    $common_data[] = $common_data_key;
                }
            }
        }
        
        $all_data = array_merge($data,$common_data);
        if (!empty($all_data)) {
            $this->custom_template_model->saveAll($all_data);
        }
        return;
    }
    
    /**
     * 初始化店铺端模板
     */
    public function iniAdminBaseCustomTemplate ($website_id, $shop_id,$ports)
    {
        $this->custom_template_model->startTrans();
        try {
            $condition = [
                'is_system_default' => 1,
                'is_default' => 1,
                'type' => ['IN', [2,3]],
            ];
            $system_default_list = $this->custom_template_model->getQuery($condition, '*', 'type asc');
            $data = [];
            foreach ($system_default_list as $k => $v) {
                $data[$k]['template_name'] = $v['template_name'];
                $data[$k]['template_data'] = $v['template_data'];
                $data[$k]['website_id'] = $website_id;
                $data[$k]['shop_id'] = $shop_id;
                $data[$k]['type'] = $v['type'];
                $data[$k]['is_default'] = 1;
                $data[$k]['in_use'] = 1;
                $data[$k]['ports'] = $ports;
            }
            $this->custom_template_model->saveAll($data);
            $this->custom_template_model->commit();
    
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            $this->custom_template_model->rollback();
            return AjaxReturn(FAIL);
        }
    
    }
    /******************* 初始化相关 END /***************************/
    
    /******************* 基本方法 START***************************/
    /**
     * 获取PreviewImg 路径
     * @param        $template_version [1wap 2mp]
     * @param        $type [模板类型]
     * @param string $port [旧模板才有 1wap 2mp 3app]
     * @return string
     */
    public function getPreviewImgUrl ($template_version,$type,$port='')
    {
        if ($port){
            if ($port == 1){
                $port = 'wap';
            }elseif ($port == 2){
                $port = 'mp';
            }elseif ($port == 3){
                $port = 'app';
            }
            return UPLOAD.DS.'custom'.DS.$this->website_id.DS.$this->instance_id.DS.$port.DS.$template_version.DS.$type;
        }
        return UPLOAD.DS.'custom'.DS.$this->website_id.DS.$this->instance_id.DS.$template_version.DS.$type;
    }
    /**
     * 获取基本域名
     */
    public function getBaseDomain ($web_info = '')
    {
        $website = new WebSite();
        $web_info = $web_info ?:$website->getWebsiteInfo();
        $url = '';
        if($web_info){
            if($web_info['realm_ip']){
                $url = "https://".$web_info['realm_ip'];
            }else{
                $ip = top_domain($_SERVER['HTTP_HOST']);
                $web_info['realm_two_ip'] = $web_info['realm_two_ip'].'.'.$ip;
                $url = "https://".$web_info['realm_two_ip'];
            }
        }
        return $url;
    }
    
    /**
     * 获取生成二维码的url
     * @param $port 【暂时只有 wap、app端】
     * @return string|void
     */
    public function getCodeUrl ($port)
    {
        $website = new WebSite();
        $web_info = $website->getWebsiteInfo();
        if (!$web_info) {return;}
        if ($port == 1) {
            $suffix = '/wap/pages/mall/index';
        } else if ($port == 2) {
            $suffix = '/wap/packages/mall/download';
        }
        if (!$suffix) {return;}
        if($web_info['realm_ip']){
            $url = "https://".$web_info['realm_ip'].$suffix;
        }else{
            $ip = top_domain($_SERVER['HTTP_HOST']);
            $web_info['realm_two_ip'] = $web_info['realm_two_ip'].'.'.$ip;
            $url = "https://".$web_info['realm_two_ip'].$suffix;
        }
        return $url;
    }
    
    /***
     * 获取小程序太阳码
     */
    public function getMpCode()
    {
        $wx_auth_model = new WeixinAuthModel();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        $sun_code = $wx_auth_model->getInfo($condition, 'sun_code_url');
        
        return $sun_code['sun_code_url'];
    }
    /**
     * 存在的端口(，分割字符串)
     * @return string
     */
    public function existPort ()
    {

        $port = '';
        if (currentEnv() == 1){
            //1wap 2mp 3app
//            if (getAddons('wapport', $this->website_id)) {
//                $port = '1';
//            }
            $port = '1';

//            if (getAddons('miniprogram',$this->website_id,0)){
            if (getAddons('miniprogram',$this->website_id,$this->instance_id)){
                $port .= ',2';
            }
//            if (getAddons('appshop',$this->website_id,0)){
            if (getAddons('appshop',$this->website_id,$this->instance_id)){
                $port .= ',3';
            }
        }elseif (currentEnv() == 2){
            $port = 2;
        }
        $port = trim($port,',');
        return $port;
    }
    /**
     * 存在的端口(数组)
     * @return string
     */
    public function existPortArr ()
    {
        $ports = $this->existPort();
        if (!$ports) {return [];}
        $portsArr = explode(',', $ports);
        
        return $portsArr;
    }
    
    /**
     * 获取当前项目环境的已存在装修端口（1wap 2mp 3app）【字符串 逗号隔开】
     */
    public function getCurrentEnvPortsString ()
    {
        $ports = $this->existPort();
        return $ports;
    }
    /**
     * 获取当前项目环境的已存在装修端口(1wap 2mp 3app)【数组】
     */
    public function getCurrentEnvPortsArr ()
    {
        $ports = $this->existPort();
        $ports_arr = explode(',',$ports);
        return $ports_arr;
    }
    /**
     * 获取当前端口对应的值
     * @return array
     */
    public function getCurrentEnvPortsValue(array $ports_arr=[])
    {
        $ports_arr = $ports_arr ?: $this->getCurrentEnvPortsArr();
        $port_value_arr = [];
        foreach ($ports_arr as $port){
            if($port == 1){
                $port_value_arr[$port] = [
                    'name' => '公众号、H5',
                    'ico' => __IMG($this->port_mini_ico['wap']),//公众号
                    'ico1' => __IMG($this->port_mini_ico['h5']),//h5
                ];
            }elseif ($port == 2){
                $port_value_arr[$port] = [
                    'name' => '小程序',
                    'ico' => __IMG($this->port_mini_ico['mp']),
                ];
            }elseif ($port == 3){
                $port_value_arr[$port] = [
                    'name' => 'APP',
                    'ico' => __IMG($this->port_mini_ico['app'])
                ];
            }
        }
        return $port_value_arr;
    }
    
    /**
     * 当前环境默认端口【数组】（1wap 2mp 3app）
     * @return array
     */
    public function getDefaultPortsArr ()
    {
        return $this->base_ports_arr;
    }
    /**
     * 当前环境默认端口【字符串】（1wap 2mp 3app）
     * @return array
     */
    public function getDefaultPortsString ()
    {
        $base_ports_arr = $this->base_ports_arr;
        return implode(',',$base_ports_arr);
    }
    /**
     * 默认基础模板【数组】
     * @return array
     */
    public function getDefaultBaseTypeArr ()
    {
        return $this->template_base_type;
    }
    
    /**
     * 默认基础模板【数组】 电脑端
     * @return array
     */
    public function getPcDefaultBaseTypeArr ()
    {
        return $this->pc_template_base_type;
    }
    
    /**
     * 默认基础模板【字符串】
     * @return array
     */
    public function getDefaultBaseTypeString ()
    {
        $template_base_type_arr = $this->template_base_type;
        return implode(',',$template_base_type_arr);
    }
    /**
     * 把$port合并到$ports_close中（去重、升序、逗号分隔）
     * @param $ports_close
     * @param $port
     * @return string [逗号分隔端口]
     */
    public function mergePortsClose ($ports_close,$port)
    {
        $ports_close_arr = explode(',',$ports_close);
        array_push($ports_close_arr, $port);
        $ports_close_arr = array_unique($ports_close_arr);
        sort($ports_close_arr);//升序排序
        $ports_close = implode(',',$ports_close_arr);
        $ports_close = trim($ports_close,',');
        
        return $ports_close;
    }
    /**
     * 把$ports中的$port去除（去重、升序、逗号分隔）
     * @param $ports
     * @param $port
     * @return string
     */
    public function delPorts($ports,$port)
    {
        $ports_arr = explode(',',$ports);
        $ports_arr = array_unique($ports_arr);
        $ports_arr = array_diff($ports_arr, [$port]);
        sort($ports_arr);//升序排序
        $ports = implode(',',$ports_arr);
        $ports = trim($ports, ',');
        
        return $ports;
    }
    /**
     * 处理ports 升序
     * @param $ports
     * @return string
     */
    public function sortPorts ($ports)
    {
        $ports_arr = explode(',',$ports);
        $ports_arr = array_unique($ports_arr);
        sort($ports_arr);//升序排序
        $ports = implode(',',$ports_arr);
        $ports = trim($ports,',');
        
        return $ports;
    }
    /**
     * 获取模板页面类型
     * @param array $condition
     * @param string $field
     * @param string $order
     * @return mixed\
     */
    public function getTemplateType ($condition, $field='*',$order='type ASC')
    {
        return $this->custom_template_model->getQuery($condition,$field,$order);
    }
    /**
     * 获取当前端（platform/admin）基本的type数组
     * @return array
     */
    public function getCurrentBaseTemplateType ()
    {
        $type_arr = $this->template_base_type;
        if ($this->instance_id >0){
            $type_arr = [2,3];// 店铺的初始化可装修的页面只有店铺首页和商品详情页
        }
        if (!getAddons('shop', $this->website_id)){
            $type_arr = array_merge(array_diff($type_arr, [2]));
        }
        if(!getAddons('integral', $this->website_id)){
            $type_arr = array_merge(array_diff($type_arr, [9]));
        }
        
        return $type_arr;
    }
    
    /**
     * 是否该端口的模板存在新模板表中
     * @param $port [1wap 2mp 3app]
     * @return bool
     */
    public function isInNewTemplateOfPort ($port)
    {
        $type_arr = $this->getCurrentBaseTemplateType();
        $condition = [
            'type'  => ['IN',$type_arr],
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'ports' => ['LIKE', "%" . $port . "%"]
        ];
        $result = $this->custom_template_model->getInfo($condition);
        if($result){
            return true;
        }
        return false;
    }
    /**
     * @param int $type[type: 1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，
     *                        6:自定义页面,7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getSystemCustomTemplateInfoByType($type, $field='*')
    {
        $condition = [
            'is_system_default' => 1,
            'is_default' => 1,
            'type' => $type
        ];
        $template = $this->custom_template_model->getInfo($condition, $field);
        return $template;
    }
    
    /**
     * 查询系统模板id
     * @param        $id
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getSystemCustomTemplateInfoById ($id, $field='*')
    {
        $condition = [
            'is_system_default' => 1,
            'id' => $id
        ];
        $template = $this->custom_template_model->getInfo($condition, $field);
        return $template;
        
    }
   
    /**
     * 模板数据排序
     * @param array $original_template_data [原始模板数据数组]
     * @return mixed|null
     * @throws \Exception
     */
    public function rearrangeCustomTemplateList(array $original_template_data,$template_type='')
    {
        if ($template_type == 'diy') {
            $new_template_data = sortArrByManyField($original_template_data,'update_time',SORT_DESC, 'exist_id', SORT_ASC);
        } else {
            $new_template_data = sortArrByManyField($original_template_data,'in_use',SORT_DESC, 'type', SORT_ASC,'update_time', SORT_DESC);
        }
        return $new_template_data;
    }
    /******************* 基本方法 END /***************************/
    
    
    /****************** 查询模板相关 START****************************/
    /**
     * 【最重要！！！】查询端口中type模板数据
     * @param      $port [1wap 2mp 3app]
     * @param      $type [1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
     * @param bool $is_shop_id [默认查询条件含有shop_id  true是 false否]
     * @param int  $shop_id [店铺id]
     * @param int  $id [模板id]
     * @param bool $is_sys_template [如果没查询到是否需要系统模板数据]
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getUsefulTemplateInfoByType($port,$type,$is_shop_id = true, $shop_id=0,$id=0)
    {
        if ($id) {
            $new_condition = ['id' => $id];
        }else {
            $new_condition = [
                'type'  => $type,
                'website_id' => $this->website_id,
                'shop_id' => $shop_id ?: $this->instance_id,
                'in_use' => 1,
            ];
            if (in_array($type, $this->template_common_type)) {
                unset($new_condition['in_use']);
            }
            if (!$is_shop_id){
                unset($new_condition['shop_id']);
            }
            $new_condition['ports'] = ['LIKE', '%'.$port.'%'];
        }
        $templateData = $this->custom_template_model->getInfo($new_condition);

        return $templateData;
    }
    
    /**
     * 【最重要！！！】查询PC端口中type模板数据
     * @param $type
     * @return array
     */
    public function getPcUsefulTemplateInfoByType ($type)
    {
        $pcportCon = new pcportController();
        $pc_template = $pcportCon->pcUsefulCustomTemplateList($type);
        if ($pc_template['code']>0) {
            return $pc_template['data'];
        }
        return [];
    }
    
    /**
     * 获取模板的对象表  //TODO...注册树
     * @param $port [1WAP 2MP 3APP]
     * @return MpCustomTemplateModel|CustomTemplateModel|void
     */
    public function getTemplateModelObj ($port)
    {
        if (currentEnv() == 1){
            if ($port ==1){
                $custom_template_model = new CustomTemplateModel();
            }elseif ($port ==2) {
                if (!getAddons('miniprogram',$this->website_id, 0)){
                    return;
                }
                $custom_template_model = new MpCustomTemplateModel();
            }elseif ($port ==3){
                if (!getAddons('appshop',$this->website_id, 0)){
                    return;
                }
                $custom_template_model = new AppCustomTemplate();
            }
        }elseif (currentEnv() == 2){
            $custom_template_model = new MpCustomTemplateModel();
        }
        return $custom_template_model;
    }
    /**
     * 获取模板的对象表
     * @param $port [1WAP 2MP 3APP]
     * @return MpCustomTemplateModel|CustomTemplateModel|void
     */
    public function getAllTemplateModelObj ($port)
    {
        if ($port ==1){
            $custom_template_model = new CustomTemplateModel();
        }elseif ($port ==2) {
            $custom_template_model = new MpCustomTemplateModel();
        }elseif ($port ==3){
            $custom_template_model = new AppCustomTemplate();
        }
        return $custom_template_model;
    }
    
    /**
     * 删除模板(新模板)
     * @param $id
     * @return int
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function deleteNewCustomTemplateById ($id)
    {
        $res = $this->custom_template_model->delData(['id'=> $id]);
        if ($res){
            return AjaxReturn(SUCCESS);
        }
        return AjaxReturn(FAIL);
    }
    /**
     * 删除未使用模板(新模板)
     * @param $id
     * @return int
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function deleteNotUseNewCustomTemplateById ($id)
    {
        $res = $this->custom_template_model->delData(['id'=> $id, 'in_use' => 0]);
        if ($res){
            return AjaxReturn(SUCCESS);
        }
        return AjaxReturn(FAIL);
    }
    /**
     * 删除模板 （旧模板）
     * @param $id
     * @param $port
     * @return \multitype
     */
    public function deleteOldCustomTemplateById($id, $port)
    {
        $condition = ['id' => $id];
        $custom_template_model = $this->getTemplateModelObj($port);
        $res = $custom_template_model->destroy($condition);
        if ($res){
            return AjaxReturn(SUCCESS);
        }
        return AjaxReturn(FAIL);
        
    }

    /**
     * 获取含有端口的模板数量（新模板）
     */
    public function getNewTemplateCount ($port,array $condition=[])
    {
        if (!$condition){
            $type_arr = $this->getCurrentBaseTemplateType();
            $condition = [
                'type'  => ['IN',$type_arr],
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'port' => ['LIKE', "%" . $port . "%"]
            ];
        }
        $new_count = $this->custom_template_model->getCount($condition);
        return $new_count;
    }
    
    /**
     * 获取pc模板总数
     */
    public function getPcTemplateCount ()
    {
        $pcportCon = new pcportController();
        $pc_count = $pcportCon->pcCustomTemplateCount();
        return $pc_count;
    }

    /**
     * 模板列表（获取新模板）
     * @param int    $page_index
     * @param mixed  $page_size
     * @param string $field
     * @param string $template_name
     * @param string    $template_type[1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,
     *                              7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
     * @param int    $ports    【请注意： ports为0用自定义组合端口查询； port有值就查询包含有该端口的数据】
     * @return array
     */
    public function getNewCustomTemplateList ($page_index=1, $page_size=PAGESIZE, $field='*',$template_name='',$template_type='', $ports=0)
    {
        if ($template_type == 'diy') {
            $template_type = 6;
        }
        # 如果ports存在且不是单个端返回错误
        if ($ports && strstr($ports,',')){
            return  [
                'data' => [],
                'total_count' => 0,
                'page_count' => 0,
            ];
        }
        $condition = [
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id
        ];
        $base_template_type = $this->template_base_type;
        if($this->instance_id>0){
            $base_template_type = [2,3,6];
        }
        if ($template_type){
            $template_type = explode(',',trim($template_type,','));
            $base_template_type = $template_type;
        }
        if(!isExistAddons('integral', $this->website_id)){
            $base_template_type = array_diff($base_template_type, [9]);
        }
        if ($base_template_type){
            $condition['type'] = ['IN', $base_template_type];
        }
        if ($template_name) {
            $condition['template_name'] = ['like', "%" . $template_name . "%"];
        }
        $order = 'in_use DESC,type ASC';
        if ($ports == 0) {
            $ports_arr = $this->existPortArr();
        } else {
            $ports_arr = explode(',', $ports);
        }
        $list = [];
        $temp_id_arr = [];
        //统计所有查询条件下模板count
        $port_list = [];
        $allPorts = $this->existPortArr();
        if ($allPorts) {
            $count_condition = $condition;
            foreach ($allPorts as $count_port) {
                $count_condition['ports'] = ['LIKE', '%'.$count_port.'%'];
                $count = $this->getNewTemplateCount($count_port,$count_condition);
                $port_list[$count_port] = $count;
            }
        }
        //查询所有端口的模板数量
        foreach ($ports_arr as $k => $port) {
            $condition['ports'] = ['LIKE', '%'.$port.'%'];
            $template_lists = $this->getCustomTemplateList($page_index,$page_size,$condition,$order,$field);
            $template_lists = objToArr($template_lists);
            foreach ($template_lists['data'] as $template) {
                $template['is_new'] = 1;//表示新模板
                $template['preview_img'] = $template['preview_img']? __IMG($template['preview_img']).'?'.time():'';
                $list[] = $template;
            }
        }
        $list_arr['data'] = $list;
        $list_arr['count'] = $port_list;
        $list_arr['total_count'] = $template_lists['total_count'];
        $list_arr['page_count'] = $template_lists['page_count'];
        
        
        return $list_arr;
    }
    
    /**
     * 新建装修模板
     * @param int $type [type: 1:商城首页 2：店铺首页 3：商品详情中心 4：会员中心 5：分销中心 6：自定义页 9:积分商城首页]
     * @param string $ports [1wap 2mp 3app 多个端用，分割]
     * @param string $template_name [模板名]
     * @return \multitype
     */
    public function createCustomTemplate ($type,$ports,$template_name='',$id=0)
    {

        if(!$ports){
            return AjaxReturn(FAIL,'','未选择应用端');
        }
        Db::startTrans();
        try {
            
            //1、根据type查询默认模板数据 todo...查询之前判断新模板是否有type=7,8,11的公共部分
            if ($id) {
                $template = $this->getSystemCustomTemplateInfoById($id);
                if ($template['type'] != $type){
                    return AjaxReturn(PARAMETER_ERROR);
                }
            } else {
                $template = $this->getSystemCustomTemplateInfoByType($type);
            }
            if(!$template){
                return AjaxReturn(FAIL,[],'数据不存在');
            }
            $template_data = json_decode($template['template_data'], true);
            
            //2、组装模板数据进行插入
            $data['template_name'] = $template_name ? : ( isset($template_data['page']['title']) ? $template_data['page']['title'] : '新建模板');
            $data['type'] = $type;
            $data['shop_id'] = $this->instance_id;
            $data['website_id'] = $this->website_id;
            $data['template_data'] = json_encode($template_data, JSON_UNESCAPED_UNICODE);
            $data['ports'] = $ports;
            if ($type == 1){
                $data['preview_img'] = $template['preview_img'];
            }
            $id =$this->custom_template_model->save($data);
            Db::commit();
            return AjaxReturn(SUCCESS,$id);
        }catch (\Exception $e){
            Db::rollback();
            debugFile(tryCachErrorMsg($e), '新建装修模板', $this->error_url);
            return AjaxReturn(FAIL,[],$e->getMessage());
        }
    }
    /**
     * 获取装修模板列表[type:1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
     * @return array
     */
    public function getCustomTemplateList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        return $this->custom_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
    }
    /**
     * 获取模板(单条)
     */
    public function getCustomTemplateInfo($condition,$field='*')
    {
        $res = $this->custom_template_model->getInfo($condition, $field);
        return $res;
    }
    
    /**
     * 获取模板数据（多条）
     */
    public function getCustomTemplateInfos ($condition,$field='*',$order='')
    {
        $res = $this->custom_template_model->getQuery($condition,$field,$order);
        return $res;
    }
    
    /**
     * 获取新模板中包含该端口类型的所有模板数据
     */
    public function getNewCustomTemplateInfoOfPortType ($port,$type, $field='*')
    {
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'type' => $type,
            'ports' => ['LIKE', "%" . $port . "%"]
        ];
        $res = $this->getCustomTemplateInfos($condition,$field);
        return $res;
    }
    
    /**
     * 获取当前商城下所有端的基础模板类型
     * 1:wap 2:mp 3:app
     */
    public function getNewBaseCustomTemplateInfoOfPort ($field='*',$order='id asc')
    {
        if (strpos($field,'ports') === false) {
            $field .= ',ports';
        }
        
        $type_arr = $this->getCurrentBaseTemplateType();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'type' => ['in', $type_arr],
        ];
        $template_lists = $this->getCustomTemplateInfos($condition,$field,$order);
        $ports_arr = $this->existPortArr();
        $list = [];
        foreach ($ports_arr as $key => $port)
        {
            foreach ($template_lists as $template)
            {
                if (strpos($template['ports'], $port) !== false) {
                    $list[$port][] = $template;
                }
            }
        }

        return $list;
    }
    
    /**
     * 获取新模板中包含该端口类型的模板数据（使用中单条）
     */
    public function getInUseNewCustomTemplateInfoOfPortType ($port,$type, $field='*')
    {
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'type' => $type,
            'in_use' => 1,
            'ports' => ['LIKE', "%" . $port . "%"]
        ];
        $res = $this->getCustomTemplateInfo($condition,$field);
        return $res;
    }
    
    /**
     * 添加模板
     */
    public function saveCustomTemplate(array $data, $id=0)
    {
        if (!$id) {
            $return = $this->custom_template_model->save($data);
        } else {
            $return = $this->custom_template_model->save($data, ['id' => $id]);
        }
        return $return;
    }

    /**
     * 查询所需的底部模板数据 (小程序发布)
     * @param array $condition
     * @return array|false|mixed|\PDOStatement|string|\think\Model|void
     */
    public function getTarBarTemplateForMp ()
    {
        $template_infos = $this->getCommonTemplateData(2);
        if ($template_infos[2]){
            foreach ($template_infos[2] as $template_info){
                if ($template_info['type'] == 7){
                    return $template_info;
                }
            }
        }
        return [];
    }
    /**
     * 获取公共底部
     * @param string $ports [默认获取版本已有端口的公共部分模板数据。否则即对应端口的公共部分数据]
     * @return array
     */
    public function getCommonTemplateData ($ports='')
    {
        //先查询新模板，没有再查询回原有的
        $ports = $ports ?: $ports = $this->existPort();
        $ports_arr = explode(',',$ports);
        $all_template_common_data = [];
        foreach ($ports_arr as $port){
            $new_condition = [
                'website_id' => $this->website_id,
                'ports' => $port,
            ];
            $common_type_data = [];
            $no_common_type = [];
            foreach ($this->template_common_type as $common_type){
                if ($port != 1 && $common_type == 10) {
                    continue;
                }
                $new_condition['type'] = $common_type;
                $template_common_info =  $this->custom_template_model->getInfo($new_condition);
                if (!$template_common_info){
                    $no_common_type[] = $common_type;
                    continue;
                }
                $common_type_data[] = $template_common_info;
            }
            if (count($common_type_data)>0 && count($no_common_type)>0){
                //如果新模板中，其中一些common_type确实，取系统默认值
                foreach ($no_common_type as $no_type){
                    $no_sys_info = $this->getSystemCustomTemplateInfoByType($no_type);
                    $no_sys_info['id'] = 0;
                    $no_sys_info['website_id'] = $this->website_id;
                    $no_sys_info['shop_id'] = $this->instance_id;
                    $no_sys_info['is_system_default'] = 0;
                    $no_sys_info['ports'] = $port;
                    $common_type_data[] = $no_sys_info;
                }
            }
            
            if (!$common_type_data){/*查询完新模板都没有响应数据*/
                $old_condition = [
                    'website_id' => $this->website_id,
                ];
                $custom_template_model = $this->getTemplateModelObj($port);
                $no_common_type = [];
                foreach ($this->template_common_type as $common_type) {
                    $old_condition['type'] = $common_type;
                    $template_common_info = $custom_template_model->getInfo($old_condition);
                    if (!$template_common_info){
                        $no_common_type[] = $common_type;
                        continue;
                    }
                    $common_type_data[] = $template_common_info;
                }
            
                if (count($no_common_type)>0){
                    //如果新模板中，其中一些common_type确实，取系统默认值
                    foreach ($no_common_type as $no_type){
                        $no_sys_info = $this->getSystemCustomTemplateInfoByType($no_type);
                        $common_type_data[] = $no_sys_info;
                    }
                }
            }
            $all_template_common_data[$port] = $common_type_data;
        }
        return $all_template_common_data;

    }
    /**
     * 处理公共部分模板返回数据
     * @param string $ports [默认获取版本已有端口的公共部分模板数据。否则即对应端口的公共部分数据]
     * @return array
     */
    public function getCommonTemplateDataToDeal ($ports='')
    {
        $common_customRes = $this->getCommonTemplateData($ports);
        $common_arr = [
            'tab_bar' => [],
            'copyright' => [],
            'popadv' => [],
            'wechat_set' => [],
        ];
        foreach ($common_customRes as $k => $common_custom){
            foreach ($common_custom as $v){
                if ($v['type'] == 7){
                    $common_arr['tab_bar'][$k] = $v['template_data'];
                }elseif($v['type'] == 8){
                    $common_arr['copyright'][$k] = $v['template_data'];
                }elseif($v['type'] == 11){
                    $common_arr['popadv'][$k] = $v['template_data'];
                }
            }
        }
        if (currentEnv() == 1){
            $wechat_info = $this->getUsefulTemplateInfoByType(1,10,false);
            if ($wechat_info){
                $common_arr['wechat_set'] = $wechat_info['template_data'];
            }
        }
        
        return $common_arr;
    }
    /***
     * 保存公共部分的模板数据
     * @param $common_data
     * @return bool|\multitype|void
     */
    public function saveCommonTypeTemplate (array $common_data)
    {
        Db::startTrans();
        try {
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'is_system_default' => 0,
            ];
            
            foreach ($common_data as $port => $data){
                if ($port == 'wap') {
                    $condition['ports'] = 1;
                } elseif ($port == 'mp') {
                    $condition['ports'] = 2;
                } elseif ($port == 'app') {
                    $condition['ports'] = 3;
                }
                //type 7:底部 8:版权信息 11:弹窗
                foreach ($data as $post_type => $template_data){
                    if (!$template_data){continue;}
                    if ($post_type == 'tabbar') {
                        $type = 7;
                    } elseif ($post_type == 'copyright') {
                        $type = 8;
                    } elseif ($post_type == 'popupadv') {
                        $type = 11;
                    }
                    $condition['type'] = $type;
                    $info = $this->getCustomTemplateInfo($condition,'id');
                    $custom_template_model = new CustomTemplateAllModel();
                    if ($info['id']) {/*更新*/
                        $update_data = $condition;
                        $update_data['template_data'] = json_encode($template_data,JSON_UNESCAPED_UNICODE);
                        $custom_template_model->save($update_data,['id' => $info['id']]);
                    } else {/*新建*/
                        $sysInfo = $this->getSystemCustomTemplateInfoByType($type,'template_name');
                        $template_name = $sysInfo['template_name'];
                        $insert_data = $condition;
                        $insert_data['template_data'] = json_encode($template_data,JSON_UNESCAPED_UNICODE);
                        $insert_data['template_name'] = $template_name;
                        $insert_data['is_default'] = 1;
                        $custom_template_model->save($insert_data);
                    }

                }
            }
            Db::commit();
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            Db::rollback();
            debugFile(tryCachErrorMsg($e), '装修保存公共数据',$this->error_url);
            return AjaxReturn(FAIL);
        }
    }
    
    /**
     * 旧模板保存为新模板
     * @param $data
     * @param $old_port
     * @param $type
     * @return \multitype
     */
    public function saveOldTemplate2NewTemplate ($data,$old_port,$type)
    {
        try {
            if ($data['in_use'] == 1){
                # 查询all中in_use&type=$type的模板去掉该ports
                $in_use_tempalte = $this->getInUseNewCustomTemplateInfoOfPortType($old_port,$type,'id,ports');
                if($in_use_tempalte){
                    if (currentEnv()==1){/*微商来*/
                        // 去除新模板ports中该port
                        $ports = $this->delPorts($in_use_tempalte['ports'],$old_port);
                        if (empty($ports)){
                            $save_data['in_use'] = 0;//如果ports为空，则in_use=0
                        }
                        //不是最后一个ports,可删除ports；否则保留最后一个port值
                        if (strstr($in_use_tempalte['ports'],',')){
                            $save_data['ports'] = $ports;
                        }
                    }elseif (currentEnv()==2){/*店大师*/
                        $save_data['in_use'] = 0;
                    }
                    $this->custom_template_model->save($save_data,['id'=>$in_use_tempalte['id']]);
                }
            }
            # 新建数据
            //获取系统template_logo
            $sysTempalte = $this->getSystemCustomTemplateInfoByType($type,'template_logo');
            if($sysTempalte){
                $data['template_logo'] = $sysTempalte['template_logo'];
                $data['ports'] = $old_port;
            }
            $this->custom_template_model->save($data);
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e){
            return AjaxReturn(FAIL,[],$e->getMessage());
        }
        
    }
    
    /**
     *  查询自定义模板数据【兼容新旧模板】
     * @param     $port
     * @param     $shop_id
     * @param int $id
     * @return array|false|mixed|\PDOStatement|string|\think\Model|null
     * @throws \Exception
     */
    public function getDiyTemplateInfoById($port, $shop_id, $id=0)
    {
        //1、先查询新表
        $new_condition = [
            'type' => 6,
            'website_id' => $this->website_id,
            'ports' => ['LIKE', "%" . $port . "%"]
        ];
        if ($shop_id) {
            $new_condition['shop_id'] = $shop_id;
        }
        $new_condition['id|exist_id'] = $id;
        $templateData = $this->custom_template_model->getQuery($new_condition,'*','id asc');
        if ($templateData) {
            //以exist_id存在优先
            $templateData = sortArrByManyField(objToArr($templateData),'exist_id',SORT_DESC);
            return reset($templateData);
        }
        
    }
    /****************** 查询模板相关 END /****************************/
    
    /****************** 其他 START ****************************/
    /**
     * 我的店铺数据
     * @return array
     */
    public function getCustomCenter ()
    {
        $base_url = $this->getBaseDomain();
        $type = $this->instance_id == 0 ? 1 : 2;
        $common_preview_img = 'https://vslai-com-cn.oss-cn-hangzhou.aliyuncs.com/upload/26/2021/01/29/11/1611891239308.png';
        $list = [
            'wap'   => [
                'title' => '移动端(H5、公众号端)',
                'name' => '移动端',
                'pic' => '/public/platform/static/images/custom-icon-wap.png',
                'ico1' => '/public/platform/static/images/custom-icon-wx-mini.png',
                'ico2' => '/public/platform/static/images/custom-icon-h5-mini.png',
            ],
            'mp'   => [
                'title' => '小程序端',
                'name' => '小程序端',
                'pic' => '/public/platform/static/images/custom-icon-mp.png',
                'ico1' => '/public/platform/static/images/custom-icon-mp-mini.png',
                'code' => '',
            ],
            // 'app'   => [
            //     'title' => 'APP端',
            //     'name' => 'APP端',
            //     'pic' => '/public/platform/static/images/custom-icon-app.png',
            //     'ico1' => '/public/platform/static/images/custom-icon-app-mini.png',
            // ],
            // 'pc'   => [
            //     'title' => '电脑端',
            //     'name' => '电脑端',
            //     'pic' => '/public/platform/static/images/custom-icon-pc.png',
            //     'ico1' => '/public/platform/static/images/custom-icon-pc.png',
            // ],
        ];
        if (currentEnv() == 1){
            $checkAddons = $this->checkAddonsInfo("wapport,miniprogram,appshop,pcport",'id,is_value_add,up_status');
            //WAP
            if($checkAddons['wapport']['is_exist']){
                $templateData = $this->getUsefulTemplateInfoByType(1,$type);
                $list['wap']['data'] = $templateData;
                $list['wap']['url'] = $this->getCodeUrl(1);
                $list['wap']['count'] = $this->getAllTemplateCount(1);
                $list['wap']['data']['preview_img'] = $templateData['preview_img'] ? __IMG($templateData['preview_img']).'?'.time() :$common_preview_img;
                $list['wap']['data']['update_time'] = date('Y-m-d H:i:s', $templateData['update_time']);
                $list['wap']['custom_url'] = $templateData['id']?__URL('PLATFORM_MAIN/Customtemplate/customTemplate?id='.$templateData['id']):'';
            } else {
                $list['wap']['info'] = $checkAddons['wapport']['info'];
            }
            //小程序
            if ($checkAddons['miniprogram']['is_exist']){
                $templateData = $this->getUsefulTemplateInfoByType(2,$type);
                $list['mp']['data'] = $templateData;
                $list['mp']['url'] = $base_url;
                $list['mp']['code'] = $this->getMpCode();
                $list['mp']['count'] = $this->getAllTemplateCount(2);
                $list['mp']['data']['preview_img'] = $templateData['preview_img'] ? __IMG($templateData['preview_img']).'?'.time() :$common_preview_img;
                $list['mp']['data']['update_time'] = date('Y-m-d H:i:s', $templateData['update_time']);
                $list['mp']['custom_url'] = $templateData['id']?__URL('PLATFORM_MAIN/Customtemplate/customTemplate?id='.$templateData['id']):'';
            } else {
                $list['mp']['info'] = $checkAddons['miniprogram']['info'];
            }
            //APP
            // if ($checkAddons['appshop']['is_exist']){
            //     $templateData = $this->getUsefulTemplateInfoByType(3,$type);
            //     $list['app']['data'] = $templateData;
            //     $list['app']['url'] = $base_url;
            //     $list['app']['count'] = $this->getAllTemplateCount(3);
            //     $list['app']['data']['preview_img'] = $templateData['preview_img'] ? __IMG($templateData['preview_img']).'?'.time() :$common_preview_img;
            //     $list['app']['data']['update_time'] = date('Y-m-d H:i:s', $templateData['update_time']);
            //     $list['app']['custom_url'] = $templateData['id']?__URL('PLATFORM_MAIN/Customtemplate/customTemplate?id='.$templateData['id']):'';
            // } else {
            //     $list['app']['info'] = $checkAddons['appshop']['info'];
            // }
            //电脑端
            // if ($checkAddons['pcport']['is_exist'] && getAddons('pcport',$this->website_id)){
            //     $pc_type = $this->instance_id==0? 1 : 2;
            //     $templateData = $this->getPcUsefulTemplateInfoByType($pc_type);
            //     $list['pc']['url'] = $base_url;
            //     $list['pc']['count'] = $this->getPcTemplateCount();
            //     $list['pc']['data']['preview_img'] = __IMG('public/static/custompc/data/default/templates/home_templates/home_templates_tpl_1/screenshot.jpg');
            //     $list['pc']['data']['update_time'] = $templateData['updatetime'];
            //     $list['pc']['custom_url'] = $templateData['code']?__URL("ADDONS_MAINpcCustomTemplate&code=".$templateData['code']."&type=".$templateData['type']):'';
            // } else {
            //     $list['pc']['info'] = $checkAddons['pcport']['info'];
            // }
        }elseif(currentEnv() == 2){
            $list = $list['mp'];//店大师只有小程序装修
            //小程序
            $sun_code = $this->getMpCode();
            if ($sun_code){
                $templateData = $this->getUsefulTemplateInfoByType(2,$type);
                $list['mp']['data'] = $templateData;
                $list['mp']['url'] = $base_url;
                $list['mp']['code'] = $sun_code;
                $list['mp']['count'] = $this->getAllTemplateCount(2);
                $list['mp']['data']['preview_img'] = $templateData['preview_img'] ? __IMG($templateData['preview_img']).'?'.time() :$common_preview_img;
                $list['mp']['data']['update_time'] = date('Y-m-d H:i:s', $templateData['update_time']);
                $list['mp']['custom_url'] = $templateData['id']?__URL('PLATFORM_MAIN/Customtemplate/customTemplate?id='.$templateData['id']):'';
            }
        }
        return $list;
    }
    
    /**
     * 筛选列表数据（端口）
     */
    public function getSearchPorts ($only_ports = true)
    {
        $portsArr = $this->existPortArr();
        if ($only_ports) {
            $port_data[] = [
                'value' => 0,
                'name' => '全部',
            ];
        }
        foreach ($portsArr as $port) {
            if ($port == 1){
                $port_data[] = [
                    'value' => $port,
                    'name' => '移动端',
                ];
            } else if ($port == 2) {
                $port_data[] = [
                    'value' => $port,
                    'name' => '小程序端',
                ];
            } else if ($port == 3) {
                $port_data[] = [
                    'value' => $port,
                    'name' => 'APP端',
                ];
            }
        }
        
        return $port_data;
    }
    
    /**
     * 筛选列表数据（端口）
     */
    public function getAllSearchPorts ($only_ports = true)
    {
        
        $portsArr = $this->existPortArr();
        if (isExistAddons('pcport',$this->website_id,$this->instance_id)) {
            array_push($portsArr, 4);
        }
        if ($only_ports) {
            $port_data[] = [
                'value' => 0,
                'name' => '全部',
            ];
        }
        foreach ($portsArr as $port) {
            if ($port == 1){
                $port_data[] = [
                    'value' => $port,
                    'name' => '移动端（公众号、H5）',
                ];
            } else if ($port == 2) {
                $port_data[] = [
                    'value' => $port,
                    'name' => '小程序端',
                ];
            } else if ($port == 3) {
                $port_data[] = [
                    'value' => $port,
                    'name' => 'APP端',
                ];
            } else if ($port == 4) {
                $port_data[] = [
                    'value' => $port,
                    'name' => '电脑端',
                ];
            }
        }
        return $port_data;
    }
    /**
     * 筛选列表数据（页面类型）
     */
    public function getSerchTypes ($only_types = true)
    {
        $typesArr = $this->getDefaultBaseTypeArr();
        if($this->instance_id>0){
            $typesArr = [2,3,6];
        }
        if (!getAddons('shop',$this->website_id)){
            $typesArr = array_diff($typesArr, [2]);
        }
        if(!getAddons('integral', $this->website_id)){
            $typesArr = array_diff($typesArr, [9]);
        }
        if ($only_types) {
            $type_data[] = [
                'value' => 0,
                'name' => '全部',
            ];
        }
        foreach ($typesArr as $type) {
            switch ($type)
            {
                case 1:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '商城首页',
                    ];
                    break;
                case 2:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '店铺首页',
                    ];
                    break;
                case 3:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '商品详情页',
                    ];
                    break;
                case 4:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '会员中心',
                    ];
                    break;
                case 5:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '分销中心',
                    ];
                    break;
                case 6:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '自定义页面',
                    ];
                    break;
                case 9:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '积分商城首页',
                    ];
                    break;
            }
        }
        
        return $type_data;
    }
    
    /**
     * 筛选列表数据（页面类型）电脑端
     */
    public function getPcSerchTypes ($only_types = true)
    {
        $typesArr = $this->getPcDefaultBaseTypeArr();
        if($this->instance_id>0){
            $typesArr = array_diff($typesArr, [1]);
        }
        if (!getAddons('shop',$this->website_id)){
            $typesArr = array_diff($typesArr, [2]);
        }
        if(!getAddons('integral', $this->website_id)){
            $typesArr = array_diff($typesArr, [9]);
        }
        if ($only_types) {
            $type_data[] = [
                'value' => 0,
                'name' => '全部',
            ];
        }
        foreach ($typesArr as $type) {
            switch ($type)
            {
                case 1:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '商城首页',
                    ];
                    break;
                case 2:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '店铺首页',
                    ];
                    break;
                case 3:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '商品详情页',
                    ];
                    break;
                case 4:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '会员中心',
                    ];
                    break;
                case 5:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '分销中心',
                    ];
                    break;
                case 6:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '自定义页面',
                    ];
                    break;
                case 9:
                    $type_data[] = [
                        'value' => $type,
                        'name' => '积分商城首页',
                    ];
                    break;
            }
        }
    
        return $type_data;
    }
    
    
    /**
     * 获取类型名（int => string）
     * @param $type
     * @return string
     */
    public function getTrueTypeName ($type)
    {
        $type = (int)$type;
        switch ($type)
        {
            case 1:
                $name = '商城首页';
                break;
            case 2:
                $name = '店铺首页';
                break;
            case 3:
                $name = '商品详情页';
                break;
            case 4:
                $name = '会员中心';
                break;
            case 5:
                $name = '分销中心';
                break;
            case 6:
                $name = '自定义页面';
                break;
            case 7:
                $name = '底部';
                break;
            case 8:
                $name = '版权信息';
                break;
            case 9:
                $name = '积分商城首页';
                break;
            case 10:
                $name = '公众号';
                break;
            case 11:
                $name = '弹窗';
                break;
        }
        return $name;
    }
    
    /**
     * 获取端口名（string|int => string）
     * @param $port [int或以‘，’分割的字符串]
     * @return string
     */
    public function getTruePortName ($port)
    {
        $port_arr = explode(',',$port);
        
        $name_str = '';
        foreach ($port_arr as $port)
        {
            $port = (int)$port;
            switch ($port)
            {
                case  1:
                    $name_str .= '公众号、H5';
                    break;
                case  2:
                    $name_str .= '小程序';
                    break;
                case  3:
                    $name_str .= 'APP';
                    break;
                case  4:
                    $name_str .= '电脑端';
                    break;
            }
        }
    
        $name_str = trim($name_str,',');
        return $name_str;
    }
    /**
     * 获取系统模板
     * @param int    $page_index
     * @param mixed  $page_size
     * @param string $template_type[1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,
     *                              7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
     * @param mixed  $port [端口 1移动端 2小程序 3APP 4电脑端]
     * @return mixed
     */
    public function getSysCustomTemplateList ($page_index=1, $page_size=PAGESIZE, $template_type=0,$port=0)
    {
        $condition = [
            'is_system_default' => 1
        ];
        $base_template_type = $this->template_base_type;
        if($this->instance_id>0){
            $base_template_type = [2,3,6];
        }
        if ($template_type){
            $template_type = explode(',',trim($template_type,','));
            $base_template_type = $template_type;
        }
        if(!isExistAddons('integral', $this->website_id)){
            $base_template_type = array_diff($base_template_type, [9]);
        }
        if ($base_template_type){
            $condition['type'] = ['IN', $base_template_type];
        }
        if ($port) {
            $condition['ports'] = $port;
        }
        
        $order = 'id ASC';
        $list = $this->getCustomTemplateList($page_index,$page_size,$condition,$order);
        return $list;
    }
    
    /**
     * 获取PC系统模板
     * @param int    $page_index
     * @param mixed  $page_size
     * @param string $template_type[1:商城首页 2:店铺首页  3:商品详情页 6:自定义页面]
     * @return mixed
     */
    public function getPcSysCustomTemplateList ($template_type=0)
    {
        if (!getAddons('pcport',$this->website_id)){
            return [
                'data' => [],
                'total_count' => 0,
                'page_count' => 0,
            ];
        }
        $pcportCon = new pcportController();
        $sys_list = $pcportCon->getPcSysCustomTemplateList($template_type);

        $list['data'] = $sys_list;
        //字段类型转和移动端一致
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]['template_logo'] = __IMG($v['screenshot']);
            $list['data'][$k]['template_name'] = $v['name'];
            $list['data'][$k]['id'] = $v['code'];
            $list['data'][$k]['ports'] = 4;
        }
        $list['total_count'] = count($list['data']);
        $list['page_count'] = 1;

        return $list;
    }
    
    /**
     * pc端type类型转成移动端
     * @param $pc_type
     * @return int
     */
    public function pcTemplateExchangeType2Wap ($pc_type)
    {
        if ($pc_type == 'goods_templates') {
            $type = 1;
        } else if ($pc_type == 'shop_templates') {
            $type = 2;
        } else if ($pc_type == 'home_templates') {
            $type = 3;
        } else if ($pc_type == 'custom_templates') {
            $type = 6;
        }
    
        return $type;
}
    
    /**
     * 获取商城存在的系统类型模板数据
     * @return array
     */
    public function getSysCustomTemplateCount ($only_types = true)
    {
        $searchTypes = $this->getSerchTypes($only_types);
        foreach ($searchTypes as $key => $type)
        {
            if ( $type['value'] ==0) {continue;}
            if ( $type['value'] == 6) {
                $count = 0;
            } else {
                $sys_condition = [
                    'is_system_default' => 1,
                    'type' => $type['value']
                ];
                $count = $this->custom_template_model->getCount($sys_condition);
            }
            $searchTypes[$key]['count'] = $count ?: 0;
        }
        return $searchTypes;
    }
    
    /**
     * 查询当前商城端口模板数量信息
     * @param int $type
     * @return array
     */
    public function getPortOfBaseCustomTemplateCount ($type=0)
    {
        $searchPorts = $this->getSearchPorts(false);
        if (!$searchPorts) {
            return [];
        }
        foreach ($searchPorts as $key => $port)
        {
            $type_arr = $this->getCurrentBaseTemplateType();
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'is_system_default' => ['neq', 1],
                'type' => ['in', $type_arr],
                'ports' => ['LIKE', "%" . $port['value'] . "%"]
            ];
            if ($type) {
                $condition['type'] = $type;
            }
            $count = $this->custom_template_model->getCount($condition);
            $searchPorts[$key]['count'] = $count ?: 0;
        }
        return $searchPorts;
    }
    
    /**
     * 装修页面获取所有端装修模板数据排序列表
     * @return array
     * @throws \Exception
     */
    public function getSortAllBaseCustomplates ()
    {
        $field = 'id,template_name,update_time,ports,in_use';
        $all_template_list = $this->getNewBaseCustomTemplateInfoOfPort($field);
        $list = [];
        foreach ($all_template_list as $port => $template_list)
        {
            $template_list = sortArrByManyField($template_list,'in_use',SORT_DESC, 'update_time', SORT_DESC);
            $list[$port] = $template_list;
        }
        return $list;
    }
    
    /**
     * 检测是否端口有至少一个基础模板是使用中
     * @param $current_id
     * @param $port  当前
     * @param $type
     * @param $website_id
     * @param $shop_id
     * @return \multitype
     */
    public function leastOneBaseTemplateInUse ($current_id, $port, $type, $website_id, $shop_id)
    {
        $base_type_arr = array_merge(array_diff($this->template_base_type, [6,9]));
        if (!in_array($type, $base_type_arr)){
            return AjaxReturn(SUCCESS);
        }
        $port_arr = explode(',',$port);
        $un_ports = array_diff($this->existPortArr(), $port_arr);//未提交端口
        if (!$un_ports) {
            return AjaxReturn(SUCCESS);
        }
        $msg = '';
        foreach ($un_ports as $un_port)
        {
            $un_condition = [
                'id' => ['NEQ', $current_id],
                'type' => $type, 'in_use' => 1,
                'shop_id' => $shop_id,
                'website_id' => $website_id,
                'ports' => ['LIKE', "%" . $un_port . "%"]
            ];
            $un_res = $this->custom_template_model->getInfo($un_condition,'id');
            if ($un_res) {
                continue;
            }
            $port_name = $this->getTruePortName($un_port);
            $msg .= '<span style="color: #008800">'.$port_name.'</span>, ';
        }
        if ($msg){
            $msg = '当前页面正在线上使用中，无法取消 '.trim($msg,',').' 端口。如部分端口需要替换装修内容，可重新创建新的页面并选择对应的应用端口。';
            return AjaxReturn(FAIL,[],$msg);
        }
        return AjaxReturn(SUCCESS);
    }
    
    /**
     * 现有主题信息
     * @return array
     */
    public function getAllThemeColorData ()
    {
        $theme_list = [
            1 => [
                'color' => 'red',
                'name' => '热情红'
            ],
            2 => [
                'color' => 'green',
                'name' => '翡翠绿'
            ],
            3 => [
                'color' => 'pink',
                'name' => '雅致粉'
            ],
            4 => [
                'color' => 'golden',
                'name' => '高端金'
            ],
            5 => [
                'color' => 'black',
                'name' => '雅酷黑'
            ],
            6 => [
                'color' => 'orange',
                'name' => '活力橙'
            ],
            7 => [
                'color' => 'blue',
                'name' => '天空蓝'
            ],
            8 => [
                'color' => 'violet',
                'name' => '紫幽兰'
            ]
        ];
        
        return $theme_list;
    }
    
    /**
     * 现有主题信息
     * @return array
     */
    public function getPcAllThemeColorData ()
    {
        $theme_list = [
            'default' => [
                'name' => '天蓝色'
            ],
            'style1' => [
                'name' => '风铃紫'
            ],
            'style2' => [
                'name' => '活力橙'
            ],
            'style3' => [
                'name' => '热情红'
            ],
            'style4' => [
                'name' => '浪漫粉'
            ],
            'style5' => [
                'name' => '釉底红'
            ],
            'style6' => [
                'name' => '紫幽兰'
            ],
            'style7' => [
                'name' => '森林绿'
            ],
            'style8' => [
                'name' => '深灰蓝'
            ],
            'style9' => [
                'name' => '浅灰蓝'
            ],
            'style10' => [
                'name' => '宝石蓝'
            ]
        ];
        
        return $theme_list;
    }
    /**
     * 获取当前主题颜色
     * @param $theme_id [主题颜色id]
     * @return string
     */
    public function getThemeColorByThemeId ($theme_id=1)
    {
        $theme_id = (int)$theme_id;
        $theme_all = $this->getAllThemeColorData();
        return $theme_all[$theme_id];
    }
    
    /**
     * 获取当前主题颜色
     * @return string
     */
    public function getPcThemeColor ()
    {
        $pcThemeConfig = new SysPcCustomStyleConfigModel();
        $styleRes = $pcThemeConfig->getInfo(['website_id' => $this->website_id],'style');
        return $styleRes ? $styleRes['style'] : 'default';
    }
    /**
     * 查询商城装修主题id
     */
    public function getThemeId ($website_id=0, $shop_id=0)
    {
        $website_id = $website_id?:$this->website_id;
        $shop_id = $shop_id?:$this->instance_id;
        $condition = [
            'website_id' => $website_id,
            'shop_id' => $shop_id,
        ];
        $result = $this->custom_template_model->getThemeInfo($condition,'theme_id');
        return isset($result['theme_id']) ? $result['theme_id'] : 1;
    }
    
    /**
     * 获取当前商城装修主题颜色
     * @return string
     */
    public function getCurrentThemeColor ()
    {
        $theme_id = $this->getThemeId();
        $res = $this->getThemeColorByThemeId($theme_id);
        
        return $res['color'];
    }
    
    /**
     * 保存主题颜色
     * @param $theme_id
     */
    public function saveThemeInfo ($theme_id)
    {
        $data = [
            'theme_id' => (int)$theme_id
        ];
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
        ];
        $this->custom_template_model->saveThemeInfo($data, $condition);
    }
    
    /**
     * 保存PC主题颜色
     * @param $style
     */
    public function savePcThemeInfo ($style)
    {
        $data = [
            'style' => $style
        ];
        $condition = [
            'website_id' => $this->website_id,
        ];
        $pcThemeConfig = new SysPcCustomStyleConfigModel();
        return $pcThemeConfig->saveAndUpdate($data,$condition);
    }
    /****************** 其他 END /****************************/
    
    /*********************  重新处理 ******************************/
    /********************** 重新处理 *****************************/
    
    /**
     * 新装修第一次同步代码后，需要先执行该方法
     * @throws \Exception
     */
    public function cliIniTemplate ()
    {
       
        $t1 = microtime(true);
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '500M');
        $exist_id = $this->custom_template_model->getFirstData([
            'is_system_default' => 1,
            'is_default' => 1,
            'type' => 1,
            'id' => ['lt', 12]
        ],'','exist_id');
        if(!$exist_id){
            $this->addSystemDefault();
            $exist_id = $this->custom_template_model->getFirstData([
                'is_system_default' => 1,
                'is_default' => 1,
                'type' => 1,
                'id' => ['lt', 12]
            ],'','exist_id');
        }
        //如果迁移了就有标记
        if ($exist_id['exist_id']) {return;}
        //系统模板，普通模板
        //1、先获取系统默认模板（模板市场）
        $sys_condition = [
            'is_system_default' => 1,
            'is_default' => ['neq', 1],
            'type' => ['in', [1,2,3,4,5,9]]
        ];
        $general_type_6_list = [];//旧数据--自定义模板（type=6） 有id字段
        $currentEnv = currentEnv();//当前环境
        $all_sys_old_template_list = [];
        $this->custom_template_model->startTrans();
        $temp_sys_ini_id_start = 12;
        $temp_sys_ini_id_end = 54;
        $temp_sys_ini = [];
        for($i=0; $i<=$temp_sys_ini_id_end; $i++)
        {
            $temp_sys_ini[] = [];
        }
        unset($i);
        $this->custom_template_model->saveAll($temp_sys_ini);//临时插入12 -- 63
        foreach ($this->base_ports_arr as $port)
        {
            if ($currentEnv == 1) {
                $custom_template_model = $this->getAllTemplateModelObj($port);
            } else if ($currentEnv == 2) {
                $custom_template_model = $this->getAllTemplateModelObj($port);
            }
            if (!$custom_template_model) {continue;}
            //获取表名
            $table_name = $this->getObjOfProperty($custom_template_model, 'table');
            //系统默认
            if (!$custom_template_model) {continue;}
            $sysTemplateList = $custom_template_model->getQuery($sys_condition);
            $sysTemplateList = array_map(function($item){
                unset($item['id']);
                unset($item['create_time']);
                unset($item['modify_time']);
                return  $item;
            },$sysTemplateList);
    
            $sysTemplateList = objToArr($sysTemplateList);
            $add_field = ['ports' => $port];
            array_walk($sysTemplateList, function (&$value, $key, $add_field) {
                $value = array_merge($value, $add_field);
            },$add_field);
            $all_sys_old_template_list = array_merge($all_sys_old_template_list, $sysTemplateList);
            //普通
            // type=6的自定义要单独处理
            $general_condition = [
                'is_system_default' => ['<>', 1],
            ];
    
            $custom_template_model = $this->getAllTemplateModelObj($port);

            //循环查询
            $count = $custom_template_model->getCount($general_condition);
            $page_size = 1000;
            $page_count = ceil($count / $page_size);
            for ($i=1; $i<=$page_count; $i++)
            {
                try {
                    $start_row = $page_size * ($i - 1);
                    $limit = $start_row . "," . $page_size;
                    $field = 'id,website_id,shop_id,template_name,template_data,type,is_default,is_system_default,in_use,template_logo';
                    $sql = 'select '.$field.' from '.$table_name.' where is_system_default != 1 limit '.$limit;
                    $general_old_template_list = Db::query($sql);
                    foreach ($general_old_template_list as $key => $oldTemplate) {
                        if ($oldTemplate['type'] == 6) {
                            $oldTemplate['ports'] = $port;
                            $general_type_6_list[] = $oldTemplate;
                            unset($general_old_template_list[$key]);//type = 6单独处理(有id)
                            continue;
                        } else {
                            unset($general_old_template_list[$key]['id']);
                            $general_old_template_list[$key]['ports'] = $port;
                        }
                    }
                    
                    debugFile($i,$port.'_查询次数:', 'public/ErrorLog/select_num.txt');
                    //插入新表 -- 默认、普通（type!=6）
                    $this->custom_template_model->saveAll($general_old_template_list);
                    # 处理type=6的数据
                    $type_6_old_template_list = [];
                    if ($general_type_6_list){
                        foreach ($general_type_6_list as $key => $general_type_6)
                        {
                            $general_type_6['exist_id'] = $general_type_6['id'];
                            unset($general_type_6['id']);
                            $type_6_old_template_list[] = $general_type_6;
                        }
                    }
                    $this->custom_template_model->saveAll($type_6_old_template_list);
                    unset($general_old_template_list,$general_type_6_list);
                } catch (\Exception $e){
                    debugFile(tryCachErrorMsg($e),'同步数据错误',$this->transfer_fail);
                    $this->custom_template_model->rollback();
                }
            }
        }
        unset($sysTemplateList,$custom_template_model);
        $newCustom = new CustomTemplateAllModel();
        /*标记*/
        $newCustom->save([
            'exist_id' => 1
        ],[
            'is_system_default' => 1,
            'is_default' => 1,
            'type' => 1,
            'id' => ['lt', 12]
        ]);
        //处理插入的系统模板id;把系统模板插入80前
        if ($all_sys_old_template_list) {
            foreach($all_sys_old_template_list as &$value){
                $value['id'] = $temp_sys_ini_id_start;
                $temp_sys_ini_id_start++;
            }
            $this->custom_template_model->saveAll($all_sys_old_template_list);
        }
        $this->custom_template_model->commit();
        $t2 = microtime(true);
        $time = $t2 - $t1;
        debugFile($time,'（每次查询'.$page_size.'条）耗时:', 'public/ErrorLog/select_time.txt');
        debugFile('迁移旧模板数据文件成功: '.$port,'',$this->transfer_success);
    }
    /**
     * 新装修初始化
     * @param int $website_id
     * @param int $shop_id
     * @param int $type
     * @return \multitype|void
     * @throws \Exception
     */
    public function initAllCustomTemplate ($website_id=0, $shop_id=0)
    {
        if (!file_exists($this->transfer_success)) {
            $this->cliIniTemplate();
            return;
        }
        $need_init_port = [];//需要初始化端口
        $open_port_arr = $this->existPortArr();//已存在的端（开启）
        foreach ($open_port_arr as $port)
        {
            //先查询新模板中是否已存在该端口的模板
            $count = $this->isInNewTemplateOfPort($port);
            if ($count>0){continue;}
            array_push($need_init_port,$port);
        }
        if (!$need_init_port){return;}
        Db::startTrans();
        try{
    
            $website_id = $website_id?:$this->website_id;
            $shop_id = $shop_id?:$this->instance_id;
            //新用户，三个端都没有的，就初始化统一一套模板，否则只要其中一个有就初始化其对应端的一套
            if (count($need_init_port) == $this->template_port_num){
                //初始化 - sys_custom_template_all 表
                $this->initCustomTemplateOfAll($open_port_arr,$website_id,$shop_id);
            }else{
                $this->iniCustomTemplate($need_init_port,$website_id,$shop_id);
            }
            Db::commit();
            return AjaxReturn(SUCCESS);
        }catch (\Exception $e){
            Db::rollback();
            debugFile(tryCachErrorMsg($e),'新装修初始化',$this->error_url);
        }
    }
    
    /**
     * 使用模板(新模板)
     * @param        $id [模板id]
     * @param        $type [模板类型1:商城首页 2:店铺首页  3:商品详情页 4:会员中心 5:分销中心，6:自定义页面,7:底部 8:版权信息 9:积分商城首页 10:公众号 11:弹窗]
     * @param        $ports [模板所属端口1h5 2mp 3app]
     * @param int    $shop_id
     * @param int $website_id
     * @return int
     */
    public function useCustomTemplateForNew($id, $type, $ports, $shop_id =0, $website_id = 0)
    {
        $website_id = $website_id ?: $this->website_id;
        $shop_id = $shop_id ?: $this->instance_id;
        $ports = $this->sortPorts($ports);
        $ports_arr = explode(',',$ports);
        $this->custom_template_model->startTrans();
        try{
            
            //检测未提交的端口中该类型是否有正在使用中的
            $chekRes = $this->leastOneBaseTemplateInUse($id, $ports, $type, $website_id, $shop_id);
            if ($chekRes['code']<0) {
                return $chekRes;
            }
            # 判断A,B两套模板端口是否一样
            $equal_ports_condition = [
                'id' => ['neq', $id],
                'type' => $type,
                'in_use' => 1,
                'shop_id' => $shop_id,
                'website_id' => $website_id,
                'ports' => $ports
            ];
            $equal_data = $this->custom_template_model->getInfo($equal_ports_condition, 'id');
            if ($equal_data) {
                $this->custom_template_model->save(['in_use' => 0],['id' => $equal_data['id']]);
                $this->custom_template_model->save(['in_use' => 1],['id' => $id]);// 新模板开启
            } else {
                # 关闭相关模板
                foreach ($ports_arr as $port){
                    # 新模板使用的关闭
                    $new_condition = [
                        'id'=>['neq',$id],
                        'type' => $type,
                        'in_use' => 1,
                        'shop_id' => $shop_id,
                        'website_id' => $website_id,
                        'ports' => ['LIKE', "%" . $port . "%"]
                    ];
                    $data = $this->custom_template_model->getInfo($new_condition);
                    if ($data){
                        if (currentEnv()==1){/*微商来*/
                            // 去除新模板ports中该port
                            $ports = $this->delPorts($data['ports'], $port);
                            if (empty($ports)){
                                $save_data['in_use'] = 0;//如果ports为空，则in_use=0
                            }
                            //不是最后一个ports,可删除ports；否则保留最后一个port值
                            if (strstr($data['ports'],',')){
                                $save_data['ports'] = $ports;
                            }
                        }elseif (currentEnv()==2){/*店大师*/
                            $save_data['in_use'] = 0;
                        }
                        $this->custom_template_model = new CustomTemplateAllModel();
                        $this->custom_template_model->save($save_data,['id'=>$data['id']]);
                    }
                }
                # 新模板开启
                $this->custom_template_model = new CustomTemplateAllModel();
                $this->custom_template_model->save(['in_use' => 1], ['id' => $id]);
            }
            $this->custom_template_model->commit();
    
        }catch (\Exception $e){
            debugFile(tryCachErrorMsg($e), '使用模板(新模板)', $this->error_url);
            $this->custom_template_model->rollback();
            return AjaxReturn(UPDATA_FAIL);
        }
        
        return AjaxReturn(SUCCESS);
    }
    
    /**
     * 获取所有模板的数量（新/旧）
     * @param       $port
     * @param array $condition
     * @return \data\model\unknown|mixed|\multitype
     */
    public function getAllTemplateCount ($port, array $condition=[])
    {
        try {
            $new_count = $this->getNewTemplateCount($port, $condition);
            return $new_count;
            
        }catch (\Exception $e){
            debugFile(tryCachErrorMsg($e), '获取所有模板的数量（新/旧）', $this->error_url);
            return AjaxReturn(FAIL,[], $e->getMessage());
        }
    }
    
    /**
     * 检测获取应用信息
     * @param $addons_string [应用名用','分割]
     * @param $fields string [需要查询sys_addons表字段]
     * @return array
     */
    public function checkAddonsInfo ($addons_string, $fields='id')
    {
        $addons_arr = explode(',', $addons_string);
        $return_data = [];
        $addonsSer = new AddonsSer();
        foreach ($addons_arr as $addons) {
            $isExist = isExistAddons($addons, $this->website_id);
            $return_data[$addons]['is_exist'] = $isExist;
            if (!$isExist) {
                $addonsRes = $addonsSer->getAddonsInfo(['name' => $addons], $fields);
                $return_data[$addons]['info'] = $addonsRes;
            }
        }
        return $return_data;
    }
    
    /**
     * 获取所有应用的情况
     * @return array [type: 1增值 2未订购 3即将上线]
     */
    public function getAllModuleList ()
    {
        $addons = new AddonsService();
        $original_lists = $addons->getModuleList();
        $addons_list_all = [];
        foreach ($original_lists as $key => $value)
        {
            $addons_list_all = array_merge($addons_list_all, $value['addons']);
        }
        unset($original_lists,$key,$value);
        $list = [];
        foreach ($addons_list_all as $key => $addons)
        {
            if ($addons['permission']) {
                if ($addons['up_status'] == 2) {
                    $type = 3;
                } else {
                    $type = 0;
                }
            } else {
                $type = 2;
                if ($addons['is_value_add'] == 1 && $addons['up_status'] != 2) {
                    $type = 1;
                } else if ($addons['up_status'] == 2) {
                    $type = 3;
                }
            }

            $list[$key] = [
                'name' => $addons['name'],
                'id' => $addons['id'],
                'type' => $type,
                'permission' => $addons['permission'],
            ];
        }
        
        return $list;
    }
    
    /**
     * 获取商城商品分类
     */
    public function getShopCategory()
    {
        $goodscate = new GoodsCategory();
        $category_list = $goodscate->getGoodsCategoryTree(0); //商品分类
        if(empty($category_list)){
            return AjaxReturn(SUCCESS);
        }
        
        //将所有的分类拼装
        foreach ($category_list as $k=>$v){
            foreach ($v['child_list'] as $key=>$value){
                if($value['is_parent']=='1'){
                    $a = $goodscate->getGoodsCategoryTree($value['category_id']);
                    $category_list[$k]['child_list'][$key]['child_list'] = objToArr($a);
                }
            }
        }
        return AjaxReturn(SUCCESS,$category_list);
    }
    private function addSystemDefault(){
        $servername = config('database.hostname');
    $username = config('database.username');
    $password = config('database.password');
    $dbname = config('database.database');
    $conn = new \mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        die("连接失败: " . $conn->connect_error);
    }
    $sql = <<<EOF
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES ('1', '0', '0', '商城首页', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M1540365853480\\":{\\"data\\":{\\"C1540365853480\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c1.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540365853481\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c1.jpg\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540365857319\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1548748646225\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1548748646228\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-4.png\\",\\"linkurl\\":\\"\\/packages\\/seckill\\/list\\",\\"text\\":\\"秒杀列表\\",\\"color\\":\\"#666666\\"},\\"C1548748646225\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-1.png\\",\\"linkurl\\":\\"\\/packages\\/assemble\\/list\\",\\"text\\":\\"拼团列表\\",\\"color\\":\\"#666666\\"},\\"C1548748646226\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-2.png\\",\\"linkurl\\":\\"\\/packages\\/bargain\\/list\\",\\"text\\":\\"砍价列表\\",\\"color\\":\\"#666666\\"},\\"C1548748646227\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-3.png\\",\\"linkurl\\":\\"\\/pages\\/coupon\\/index\\",\\"text\\":\\"领券中心\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"},\\"M1540365937849\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365937849\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c6.jpg\\",\\"linkurl\\":\\"\\/pages\\/goods\\/list\\"}},\\"id\\":\\"picture\\"},\\"M1540365984087\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365984087\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c7.jpg\\",\\"linkurl\\":\\"\\/pages\\/goods\\/list\\"},\\"C1540365984088\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c8.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540365984089\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c12.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540365984090\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c10.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540366004883\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c7.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540366005961\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c8.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540366006833\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c12.jpg\\",\\"linkurl\\":\\"\\"},\\"C1540366007706\\":{\\"imgurl\\":\\"\\/public\\/static\\/images\\/customwap\\/c10.jpg\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1548927822502\\":{\\"params\\":{\\"title\\":\\"精选店铺\\",\\"recommendtype\\":\\"0\\",\\"recommendcondi\\":\\"0\\",\\"recommendnum\\":\\"6\\"},\\"id\\":\\"shop\\"},\\"M1540366245463\\":{\\"params\\":{\\"title\\":\\"---  全国优选 优先体验  ---\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#e53b3b\\",\\"textalign\\":\\"center\\",\\"fontsize\\":\\"16\\",\\"paddingtop\\":\\"15\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540366313377\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"30\\",\\"style\\":\\"\\",\\"show_title\\":\\"1\\",\\"show_sub_title\\":\\"1\\",\\"show_coupon\\":\\"1\\",\\"show_price\\":\\"1\\",\\"show_market_price\\":\\"1\\",\\"show_commission\\":\\"1\\",\\"show_sales\\":\\"1\\",\\"show_tag\\":\\"0\\"},\\"style\\":{\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"goods\\",\\"data\\":[]}}}', '0', '1615866015', '1', '1', '1', '0', 'https://vslai-com-cn-shop.oss-cn-hangzhou.aliyuncs.com/upload/fm/mb0.png', '', 'https://vslai-com-cn.oss-cn-hangzhou.aliyuncs.com/upload/26/2021/01/29/11/1611891239308.png', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('2', '0', '0', '店铺首页', '{\\"page\\":{\\"type\\":\\"2\\",\\"title\\":\\"店铺首页\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M012345678901\\":{\\"id\\":\\"shop_head\\",\\"style\\":{\\"backgroundimage\\":\\"\\"},\\"params\\":{\\"styletype\\":\\"1\\"}},\\"M1553937494650\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1553937486573\\":{\\"data\\":{\\"C1553937486573\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853551.png\\",\\"linkurl\\":\\"\\"},\\"C1553937486574\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853554.png\\",\\"linkurl\\":\\"\\"},\\"C1553937488179\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853557.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1553937521428\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1553937521428\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/seckill/list\\",\\"text\\":\\"秒杀\\",\\"color\\":\\"#666666\\"},\\"C1553937521429\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853590.png\\",\\"linkurl\\":\\"/packages/assemble/list\\",\\"text\\":\\"拼团\\",\\"color\\":\\"#666666\\"},\\"C1553937521430\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853577.png\\",\\"linkurl\\":\\"/packages/bargain/list\\",\\"text\\":\\"砍价\\",\\"color\\":\\"#666666\\"},\\"C1553937521431\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853657.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"预售\\",\\"color\\":\\"#666666\\"},\\"C1553937522968\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853649.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"限时折扣\\",\\"color\\":\\"#666666\\"},\\"C1553937524237\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853560.png\\",\\"linkurl\\":\\"/pages/shop/list\\",\\"text\\":\\"店铺街\\",\\"color\\":\\"#666666\\"},\\"C1553937525741\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853575.png\\",\\"linkurl\\":\\"/pages/integral/index\\",\\"text\\":\\"积分商城\\",\\"color\\":\\"#666666\\"},\\"C1553937526523\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853579.png\\",\\"linkurl\\":\\"/pages/coupon/index\\",\\"text\\":\\"领券中心\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"},\\"M1553937746852\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553937755757\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937755758\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916562.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937773707\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937773708\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853592.png\\",\\"linkurl\\":\\"\\"},\\"C1553937773709\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853594.png\\",\\"linkurl\\":\\"\\"},\\"C1553937773710\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853595.png\\",\\"linkurl\\":\\"\\"},\\"C1553937801566\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853597.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553937841539\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553937856228\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937856228\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916601.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937873887\\":{\\"params\\":{\\"row\\":\\"1\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937873887\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553938211.png\\",\\"linkurl\\":\\"\\"},\\"C1553937873888\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853653.png\\",\\"linkurl\\":\\"\\"},\\"C1553937873889\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553938197.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553937921667\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553937930274\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937930274\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916735.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937943525\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937943525\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853612.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937960259\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937960260\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916826.png\\",\\"linkurl\\":\\"\\"},\\"C1553937960261\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916872.png\\",\\"linkurl\\":\\"\\"},\\"C1553937960262\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916884.png\\",\\"linkurl\\":\\"\\"},\\"C1553938002798\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916903.png\\",\\"linkurl\\":\\"\\"},\\"C1553938004205\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916905.png\\",\\"linkurl\\":\\"\\"},\\"C1553938005286\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916907.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553938027158\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553938034724\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938034724\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916932.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553938047130\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938047130\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916942.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553938061645\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938061645\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916965.png\\",\\"linkurl\\":\\"\\"},\\"C1553938061646\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916967.png\\",\\"linkurl\\":\\"\\"},\\"C1553938061647\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916969.png\\",\\"linkurl\\":\\"\\"},\\"C1553938061648\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916971.png\\",\\"linkurl\\":\\"\\"},\\"C1553938099605\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916973.png\\",\\"linkurl\\":\\"\\"},\\"C1553938100435\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916974.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553938116753\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553938123142\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938123142\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553917250.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553938132517\\":{\\"shoptype\\":\\"2\\",\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"0\\",\\"recommendnum\\":\\"4\\",\\"style\\":\\"\\",\\"show_title\\":\\"1\\",\\"show_sub_title\\":\\"1\\",\\"show_coupon\\":\\"1\\",\\"show_price\\":\\"1\\",\\"show_market_price\\":\\"1\\",\\"show_commission\\":\\"1\\",\\"show_sales\\":\\"1\\",\\"show_tag\\":\\"0\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\"},\\"data\\":{\\"C1867\\":{\\"goods_id\\":\\"1867\\",\\"goods_name\\":\\"[测试商品]Vero Moda春季职场风七分袖宽松西服外套女|318308530\\",\\"price\\":\\"324.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1860\\":{\\"goods_id\\":\\"1860\\",\\"goods_name\\":\\"[测试商品]Vero Moda夏季新款翻领一粒扣中长款休闲西装外套|318208505\\",\\"price\\":\\"239.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1881\\":{\\"goods_id\\":\\"1881\\",\\"goods_name\\":\\"Vero Moda新款不规则裙摆收腰连衣裙|31827C534\\",\\"price\\":\\"260.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1871\\":{\\"goods_id\\":\\"1871\\",\\"goods_name\\":\\"江疏影明星同款Vero Moda2019夏季新款V领连衣裙女|31927B519\\",\\"price\\":\\"699.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"}},\\"id\\":\\"goods\\"}}}', '0', '0', '2', '1', '1', '0', 'https://pic.vslai.com.cn/upload/default/20190411153916.jpg', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('3', '0', '0', '商品详情页', '{\\"page\\":{\\"type\\":\\"3\\",\\"title\\":\\"请输入页面标题\\",\\"name\\":\\"商品详情页\\",\\"background\\":\\"#f8f8f8\\",\\"readonly\\":\\"true\\"},\\"items\\":{\\"M012345678901\\":{\\"id\\":\\"detail_banner\\",\\"max\\":\\"1\\",\\"style\\":{\\"shape\\":\\"round\\",\\"position\\":\\"center\\",\\"color\\":\\"#1989fa\\"}},\\"M012345678902\\":{\\"id\\":\\"detail_info\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"10\\",\\"marginbottom\\":\\"10\\",\\"pricecolor\\":\\"#ff454e\\",\\"pricelightcolor\\":\\"#909399\\",\\"promotecolor\\":\\"#ffffff\\",\\"promotelightcolor\\":\\"#ffffff\\",\\"titlecolor\\":\\"#323233\\"}},\\"M012345678903\\":{\\"id\\":\\"detail_promote\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"10\\",\\"marginbottom\\":\\"10\\",\\"titlecolor\\":\\"#606266\\"},\\"data\\":{\\"C0123456789101\\":{\\"key\\":\\"fullcut\\",\\"text\\":\\"促销\\"},\\"C0123456789102\\":{\\"key\\":\\"coupon\\",\\"text\\":\\"优惠\\"},\\"C0123456789103\\":{\\"key\\":\\"rebate\\",\\"text\\":\\"返利\\"}}},\\"M012345678904\\":{\\"id\\":\\"detail_specs\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"0\\",\\"marginbottom\\":\\"0\\",\\"titlecolor\\":\\"#606266\\",\\"currentcolor\\":\\"#323233\\",\\"nocurrentcolor\\":\\"#909399\\"}},\\"M012345678905\\":{\\"id\\":\\"detail_delivery\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"0\\",\\"marginbottom\\":\\"0\\",\\"titlecolor\\":\\"#606266\\",\\"currentcolor\\":\\"#323233\\",\\"nocurrentcolor\\":\\"#909399\\"}},\\"M012345678906\\":{\\"id\\":\\"detail_service\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"0\\",\\"marginbottom\\":\\"0\\",\\"titlecolor\\":\\"#323233\\",\\"desccolor\\":\\"#606266\\"},\\"data\\":{\\"C0123456789101\\":{\\"title\\":\\"7天无理由退货\\",\\"desc\\":\\"商城所有商品均为正品。\\",\\"imgurl\\":\\"/public/platform/images/custom/default/goodsicon-sendfree.png\\"}}},\\"M012345678908\\":{\\"id\\":\\"detail_desc\\",\\"max\\":\\"1\\"}}}', '0', '0', '3', '1', '1', '0', 'https://pic.vslai.com.cn/upload/default/20190411153934.jpg', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('4', '0', '0', '会员中心', '{\\"page\\":{\\"type\\":\\"4\\",\\"title\\":\\"会员中心\\",\\"name\\":\\"会员中心\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M0_member\\":{\\"id\\":\\"member_fixed\\",\\"style\\":{\\"backgroundimage\\":\\"\\"},\\"params\\":{\\"styletype\\":\\"1\\"}},\\"M0_member_bind\\":{\\"id\\":\\"member_bind_fixed\\",\\"style\\":{\\"background\\":\\"#fff\\",\\"iconcolor\\":\\"#ff454e\\",\\"titlecolor\\":\\"#323233\\",\\"desccolor\\":\\"#909399\\"},\\"params\\":{\\"title\\":\\"绑定手机\\",\\"desc\\":\\"为了账号安全、方便购物和订单同步，请绑定手机号码。\\"}},\\"M0_member_order\\":{\\"id\\":\\"member_order_fixed\\",\\"style\\":{\\"background\\":\\"#fff\\",\\"textcolor\\":\\"#323233\\",\\"iconcolor\\":\\"#323233\\",\\"titlecolor\\":\\"#323233\\",\\"titleiconcolor\\":\\"#323233\\",\\"titleremarkcolor\\":\\"#909399\\"},\\"params\\":{\\"title\\":\\"我的订单\\",\\"remark\\":\\"全部订单\\",\\"iconclass\\":\\"v-icon-form\\"},\\"data\\":{\\"C0123456789101\\":{\\"key\\":\\"unpaid\\",\\"name\\":\\"待付款\\",\\"text\\":\\"待付款\\",\\"iconclass\\":\\"v-icon-payment2\\",\\"is_show\\":\\"1\\"},\\"C0123456789102\\":{\\"key\\":\\"unshipped\\",\\"name\\":\\"待发货\\",\\"text\\":\\"待发货\\",\\"iconclass\\":\\"v-icon-delivery2\\",\\"is_show\\":\\"1\\"},\\"C0123456789103\\":{\\"key\\":\\"unreceived\\",\\"name\\":\\"待收货\\",\\"text\\":\\"待收货\\",\\"iconclass\\":\\"v-icon-logistic3\\",\\"is_show\\":\\"1\\"},\\"C0123456789104\\":{\\"key\\":\\"unevaluated\\",\\"name\\":\\"待评价\\",\\"text\\":\\"待评价\\",\\"iconclass\\":\\"v-icon-success1\\",\\"is_show\\":\\"1\\"},\\"C0123456789105\\":{\\"key\\":\\"aftersale\\",\\"name\\":\\"售后\\",\\"text\\":\\"售后\\",\\"iconclass\\":\\"v-icon-sale\\",\\"is_show\\":\\"1\\"}}},\\"M0_member_assets\\":{\\"id\\":\\"member_assets_fixed\\",\\"style\\":{\\"background\\":\\"#fff\\",\\"textcolor\\":\\"#323233\\",\\"iconcolor\\":\\"#323233\\",\\"highlight\\":\\"#ff454e\\",\\"titlecolor\\":\\"#323233\\",\\"titleiconcolor\\":\\"#323233\\",\\"titleremarkcolor\\":\\"#909399\\"},\\"params\\":{\\"title\\":\\"我的资产\\",\\"remark\\":\\"更多\\",\\"iconclass\\":\\"v-icon-assets\\"},\\"data\\":{\\"C0_balance\\":{\\"no_addons\\":\\"0\\",\\"key\\":\\"balance\\",\\"name\\":\\"余额\\",\\"text\\":\\"余额\\",\\"is_show\\":\\"1\\"},\\"C0_points\\":{\\"no_addons\\":\\"0\\",\\"key\\":\\"points\\",\\"name\\":\\"积分\\",\\"text\\":\\"积分\\",\\"is_show\\":\\"1\\"},\\"C0_coupontype\\":{\\"no_addons\\":\\"1\\",\\"key\\":\\"coupontype\\",\\"name\\":\\"优惠券\\",\\"text\\":\\"优惠券\\",\\"is_show\\":\\"1\\"},\\"C0_giftvoucher\\":{\\"no_addons\\":\\"1\\",\\"key\\":\\"giftvoucher\\",\\"name\\":\\"礼品券\\",\\"text\\":\\"礼品券\\",\\"is_show\\":\\"1\\"},\\"C0_store\\":{\\"no_addons\\":\\"0\\",\\"key\\":\\"store\\",\\"name\\":\\"消费卡\\",\\"text\\":\\"消费卡\\",\\"is_show\\":\\"1\\"},\\"C0_blockchain\\":{\\"no_addons\\":\\"1\\",\\"key\\":\\"blockchain\\",\\"name\\":\\"数字钱包\\",\\"text\\":\\"数字钱包\\",\\"is_show\\":\\"1\\"}}},\\"M1548927666181\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1548927666181\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/distribute/index\\",\\"text\\":\\"分销中心\\",\\"color\\":\\"#666666\\"},\\"C1548927762708\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/bonus/index\\",\\"text\\":\\"分红中心\\",\\"color\\":\\"#666666\\"},\\"C1548927767125\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/channel/index\\",\\"text\\":\\"微商中心\\",\\"color\\":\\"#666666\\"},\\"C1548927773213\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/microshop/index\\",\\"text\\":\\"我的微店\\",\\"color\\":\\"#666666\\"},\\"C1548927703430\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/goods/collect\\",\\"text\\":\\"商品收藏\\",\\"color\\":\\"#666666\\"},\\"C1548927704982\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/shop/collect\\",\\"text\\":\\"店铺收藏\\",\\"color\\":\\"#666666\\"},\\"C1548927706653\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/prize/list\\",\\"text\\":\\"我的奖品\\",\\"color\\":\\"#666666\\"},\\"C1548927707941\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/task/index\\",\\"text\\":\\"任务中心\\",\\"color\\":\\"#666666\\"},\\"C1548927732749\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/help/index\\",\\"text\\":\\"关于我们\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"}}}', '0', '0', '4', '1', '1', '0', 'https://pic.vslai.com.cn/upload/common/hy-01.png', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('5', '0', '0', '分销中心', '{\\"page\\":{\\"type\\":\\"5\\",\\"title\\":\\"分销中心\\",\\"name\\":\\"分销中心\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M012345678901\\":{\\"id\\":\\"commission_fixed\\",\\"params\\":{\\"styletype\\":\\"1\\"},\\"style\\":{\\"backgroundimage\\":\\"\\"}},\\"M1542076937387\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1542076937387\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/distribute/order\\",\\"text\\":\\"分销订单\\",\\"color\\":\\"#666666\\"},\\"C1542076937388\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-2.png\\",\\"linkurl\\":\\"/packages/distribute/team\\",\\"text\\":\\"我的团队\\",\\"color\\":\\"#666666\\"},\\"C1542076937389\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-3.png\\",\\"linkurl\\":\\"/packages/distribute/customer\\",\\"text\\":\\"我的客户\\",\\"color\\":\\"#666666\\"},\\"C1542076937390\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-4.png\\",\\"linkurl\\":\\"/packages/distribute/qrcode\\",\\"text\\":\\"推广二维码\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"}}}', '0', '0', '5', '1', '1', '0', 'https://pic.vslai.com.cn/upload/common/fx-01.png', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('6', '0', '0', '自定义页面', '', '0', '0', '6', '1', '1', '0', '', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('7', '0', '0', '底部', '{\\"data\\":{\\"C0123456789101\\":{\\"text\\":\\"首页\\",\\"index\\":\\"0\\",\\"path\\":\\"pages\\/mall\\/index\\",\\"normal\\":\\"\\/public\\/app\\/images\\/tabbar\\/normal\\/home.png\\",\\"active\\":\\"\\/public\\/app\\/images\\/tabbar\\/red\\/home.png\\"},\\"C0123456789102\\":{\\"text\\":\\"分类\\",\\"index\\":\\"1\\",\\"path\\":\\"pages\\/goods\\/category\\",\\"normal\\":\\"\\/public\\/app\\/images\\/tabbar\\/normal\\/category.png\\",\\"active\\":\\"\\/public\\/app\\/images\\/tabbar\\/red\\/category.png\\"},\\"C0123456789103\\":{\\"text\\":\\"分销中心\\",\\"index\\":\\"2\\",\\"path\\":\\"pages\\/distribute\\/index\\",\\"normal\\":\\"\\/public\\/app\\/images\\/tabbar\\/normal\\/distribute.png\\",\\"active\\":\\"\\/public\\/app\\/images\\/tabbar\\/red\\/distribute.png\\"},\\"C0123456789104\\":{\\"text\\":\\"购物车\\",\\"index\\":\\"3\\",\\"path\\":\\"pages\\/mall\\/cart\\",\\"normal\\":\\"\\/public\\/app\\/images\\/tabbar\\/normal\\/cart.png\\",\\"active\\":\\"\\/public\\/app\\/images\\/tabbar\\/red\\/cart.png\\"},\\"C0123456789105\\":{\\"text\\":\\"会员中心\\",\\"index\\":\\"4\\",\\"path\\":\\"pages\\/member\\/index\\",\\"normal\\":\\"\\/public\\/app\\/images\\/tabbar\\/normal\\/member.png\\",\\"active\\":\\"\\/public\\/app\\/images\\/tabbar\\/red\\/member.png\\"}}}', '0', '0', '7', '1', '1', '0', '', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('8', '0', '0', '版权信息', '{\\"style\\":{\\"showtype\\":\\"0\\"},\\"params\\":{\\"showlogo\\":\\"0\\",\\"text\\":\\"请填写版权说明\\",\\"src\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/copyright.png\\",\\"linkurl\\":\\"\\",\\"readonly\\":\\"0\\",\\"is_show\\":\\"1\\"}}', '0', '0', '8', '1', '1', '0', '', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('9', '0', '0', '积分商城首页', '{\\"page\\":{\\"type\\":\\"9\\",\\"title\\":\\"请输入页面标题\\",\\"name\\":\\"积分商城首页\\"},\\"items\\":{\\"M1564646125668\\":{\\"data\\":{\\"C1564646125668\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/20190724174845.jpg\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1564646138292\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1564646138292\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/clothes_pants.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"服装\\",\\"color\\":\\"#323232\\"},\\"C1564646138293\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/mobile_computer.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"数码\\",\\"color\\":\\"#323232\\"},\\"C1564646138294\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/sports_health.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"健康\\",\\"color\\":\\"#323232\\"},\\"C1564646138295\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/commodity_drink.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"食品\\",\\"color\\":\\"#323232\\"},\\"C1564646145249\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/home_appliance.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"电器\\",\\"color\\":\\"#323232\\"},\\"C1564646145936\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/daily_home.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"家居\\",\\"color\\":\\"#323232\\"},\\"C1564646146984\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/beauty_care.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"美妆\\",\\"color\\":\\"#323232\\"},\\"C1564646148321\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/more_categories.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"更多\\",\\"color\\":\\"#323232\\"}},\\"id\\":\\"menu\\"},\\"M1564646140745\\":{\\"shoptype\\":\\"9\\",\\"params\\":{\\"showtype\\":\\"2\\",\\"recommendtype\\":\\"0\\",\\"goodssort\\":\\"0\\",\\"recommendnum\\":\\"4\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"goodsIntegral\\"}}}', '0', '0', '9', '1', '1', '0', 'https://pic.vslai.com.cn/upload/default/Integral%20_Mall.jpg', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('10', '0', '0', '公众号', '{\\"is_show\\":\\"0\\",\\"default_title1\\":\\"您还没有关注公众号！\\",\\"default_title2\\":\\"关注公众号，带你轻松玩转商城。\\",\\"invite_title1\\":\\"\\",\\"invite_title2\\":\\"\\",\\"btn_text\\":\\"立即关注\\",\\"btn_action\\":\\"1\\",\\"concern_code\\":\\"\\",\\"concern_qr\\":\\"\\"}', '0', '0', '10', '1', '1', '0', '', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('11', '0', '0', '弹窗', '{\\"advshow\\":\\"0\\",\\"advimg\\":\\"\\",\\"advlink\\":\\"\\",\\"advrule\\":\\"1\\"}', '0', '0', '11', '1', '1', '0', '', '', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('12', '0', '0', '积分商城首页', '{\\"page\\":{\\"type\\":\\"9\\",\\"title\\":\\"请输入页面标题\\",\\"name\\":\\"积分商城首页\\"},\\"items\\":{\\"M1564646125668\\":{\\"data\\":{\\"C1564646125668\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/20190724174845.jpg\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1564646138292\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1564646138292\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/clothes_pants.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"服装\\",\\"color\\":\\"#323232\\"},\\"C1564646138293\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/mobile_computer.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"数码\\",\\"color\\":\\"#323232\\"},\\"C1564646138294\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/sports_health.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"健康\\",\\"color\\":\\"#323232\\"},\\"C1564646138295\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/commodity_drink.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"食品\\",\\"color\\":\\"#323232\\"},\\"C1564646145249\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/home_appliance.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"电器\\",\\"color\\":\\"#323232\\"},\\"C1564646145936\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/daily_home.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"家居\\",\\"color\\":\\"#323232\\"},\\"C1564646146984\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/beauty_care.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"美妆\\",\\"color\\":\\"#323232\\"},\\"C1564646148321\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/more_categories.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"更多\\",\\"color\\":\\"#323232\\"}},\\"id\\":\\"menu\\"},\\"M1564646140745\\":{\\"shoptype\\":\\"9\\",\\"params\\":{\\"showtype\\":\\"2\\",\\"recommendtype\\":\\"0\\",\\"goodssort\\":\\"0\\",\\"recommendnum\\":\\"4\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"goodsIntegral\\"}}}', '1615866015', '1615866015', '9', '0', '1', '1', 'https://pic.vslai.com.cn/upload/default/Integral%20_Mall.jpg', '3', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('13', '0', '0', '积分商城首页', '{\\"page\\":{\\"type\\":\\"9\\",\\"title\\":\\"请输入页面标题\\",\\"name\\":\\"积分商城首页\\"},\\"items\\":{\\"M1564646125668\\":{\\"data\\":{\\"C1564646125668\\":{\\"imgurl\\":\\"https:\\/\\/pic.vslai.com.cn\\/upload\\/default\\/20190724174845.jpg\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1564646138292\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1564646138292\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/clothes_pants.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"服装\\",\\"color\\":\\"#323232\\"},\\"C1564646138293\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/mobile_computer.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"数码\\",\\"color\\":\\"#323232\\"},\\"C1564646138294\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/sports_health.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"健康\\",\\"color\\":\\"#323232\\"},\\"C1564646138295\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/commodity_drink.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"食品\\",\\"color\\":\\"#323232\\"},\\"C1564646145249\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/home_appliance.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"电器\\",\\"color\\":\\"#323232\\"},\\"C1564646145936\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/daily_home.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"家居\\",\\"color\\":\\"#323232\\"},\\"C1564646146984\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/beauty_care.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"美妆\\",\\"color\\":\\"#323232\\"},\\"C1564646148321\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/more_categories.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"更多\\",\\"color\\":\\"#323232\\"}},\\"id\\":\\"menu\\"},\\"M1564646140745\\":{\\"shoptype\\":\\"9\\",\\"params\\":{\\"showtype\\":\\"2\\",\\"recommendtype\\":\\"0\\",\\"goodssort\\":\\"0\\",\\"recommendnum\\":\\"4\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"goodsIntegral\\"}}}', '1615866015', '1615866015', '9', '0', '1', '0', 'https://pic.vslai.com.cn/upload/default/Integral%20_Mall.jpg', '2', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('915', '0', '0', '弹窗', '{\\"advshow\\":\\"0\\",\\"advimg\\":\\"\\",\\"advlink\\":\\"\\",\\"advrule\\":\\"1\\"}', '0', '0', '11', '0', '1', '0', '', '', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('916', '0', '0', '模板1', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540288974198\\":{\\"data\\":{\\"C1540288974198\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a1.png\\",\\"linkurl\\":\\"\\"},\\"C1540288974199\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540290294866\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540290294866\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a2.png\\",\\"linkurl\\":\\"\\"},\\"C1540290294867\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a3.png\\",\\"linkurl\\":\\"\\"},\\"C1540290294868\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a4.png\\",\\"linkurl\\":\\"\\"},\\"C1540290294869\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a5.png\\",\\"linkurl\\":\\"\\"},\\"C1540290337082\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a6.png\\",\\"linkurl\\":\\"\\"},\\"C1540290338449\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a7.png\\",\\"linkurl\\":\\"\\"},\\"C1540290339537\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a8.png\\",\\"linkurl\\":\\"\\"},\\"C1540290340249\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a9.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540289816608\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540289816608\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a10.png\\",\\"linkurl\\":\\"\\"},\\"C1540289816609\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a11.png\\",\\"linkurl\\":\\"\\"},\\"C1540289816610\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a12.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540290112056\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540289906152\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"2\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540289906152\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a13.png\\",\\"linkurl\\":\\"\\"},\\"C1540289906153\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a14.png\\",\\"linkurl\\":\\"\\"},\\"C1540289906154\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a15.png\\",\\"linkurl\\":\\"\\"},\\"C1540289906155\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a16.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540290030584\\":{\\"style\\":{\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#f3f3f3\\"},\\"data\\":{\\"C1540290030584\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a17.png\\",\\"linkurl\\":\\"\\"},\\"C1540290047041\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a18.png\\",\\"linkurl\\":\\"\\"},\\"C1540290048241\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a19.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540292269025\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"30\\"},\\"style\\":{\\"background\\":\\"#ffffff\\"},\\"id\\":\\"goods\\"}}}', '1614650642', '1614650642', '1', '0', '1', '0', '/public/static/images/customwap/af.png', '1', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('917', '0', '0', '模版2', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540292289458\\":{\\"params\\":{\\"text\\":\\"这个一条公告内容。\\",\\"leftIcon\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/notice-icon.png\\"},\\"style\\":{\\"background\\":\\"#fff7cc\\",\\"color\\":\\"#f60\\"},\\"id\\":\\"notice\\"},\\"M1540292289856\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1540292292296\\":{\\"data\\":{\\"C1540292292297\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b1.png\\",\\"linkurl\\":\\"\\"},\\"C1540292292298\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540292301465\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"8\\",\\"paddingleft\\":\\"15\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292301465\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b2.png\\",\\"linkurl\\":\\"\\"},\\"C1540292301466\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b3.png\\",\\"linkurl\\":\\"\\"},\\"C1540292301467\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b4.png\\",\\"linkurl\\":\\"\\"},\\"C1540292301468\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b4_1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540292330898\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540292341585\\":{\\"params\\":{\\"title\\":\\"尖端商品\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#666666\\",\\"textalign\\":\\"center\\",\\"fontsize\\":\\"14\\",\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540292352432\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"6\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292352432\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b5.png\\",\\"linkurl\\":\\"\\"},\\"C1540292352433\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b6.png\\",\\"linkurl\\":\\"\\"},\\"C1540292352434\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b7.png\\",\\"linkurl\\":\\"\\"},\\"C1540292352435\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b8.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540292394776\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292394776\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b9.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540292405328\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"10\\"},\\"style\\":{\\"background\\":\\"#ffffff\\"},\\"id\\":\\"goods\\"},\\"M1540292436712\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540292437152\\":{\\"params\\":{\\"title\\":\\"人气推荐\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#666666\\",\\"textalign\\":\\"center\\",\\"fontsize\\":\\"14\\",\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540292461768\\":{\\"style\\":{\\"paddingtop\\":\\"9\\",\\"paddingleft\\":\\"15\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292461768\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b10.png\\",\\"linkurl\\":\\"\\"},\\"C1540292463376\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b11.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"}}}', '1614650642', '1614650642', '1', '0', '1', '0', '/public/static/images/customwap/bf.png', '1', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('918', '0', '0', '模版3', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540353447056\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1540353449888\\":{\\"data\\":{\\"C1540353449888\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d1.png\\",\\"linkurl\\":\\"\\"},\\"C1540353449889\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540353472110\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"5\\"},\\"data\\":{\\"C1540353472110\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-1.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字1\\",\\"color\\":\\"#666666\\"},\\"C1540353472111\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-2.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字2\\",\\"color\\":\\"#666666\\"},\\"C1540353472112\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-3.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字3\\",\\"color\\":\\"#666666\\"},\\"C1540353472113\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-4.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字4\\",\\"color\\":\\"#666666\\"},\\"C1540353488145\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-1.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字1\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"},\\"M1540353506112\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540353506112\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d12.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540353523161\\":{\\"params\\":{\\"title\\":\\"新品推荐\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#ff5151\\",\\"textalign\\":\\"left\\",\\"fontsize\\":\\"18\\",\\"paddingtop\\":\\"11\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540353561593\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"4\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540353561593\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d13.png\\",\\"linkurl\\":\\"\\"},\\"C1540353561594\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d14.png\\",\\"linkurl\\":\\"\\"},\\"C1540353561595\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d15.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"}}}', '1614650642', '1614650642', '1', '0', '1', '0', '/public/static/images/customwap/df.png', '1', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('919', '0', '0', '模版4', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540364869288\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1540364872751\\":{\\"data\\":{\\"C1540364872752\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e1.png\\",\\"linkurl\\":\\"\\"},\\"C1540364872753\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540364899993\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"9\\",\\"paddingleft\\":\\"8\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364899993\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e2.png\\",\\"linkurl\\":\\"\\"},\\"C1540364899994\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e3.png\\",\\"linkurl\\":\\"\\"},\\"C1540364899995\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e4.png\\",\\"linkurl\\":\\"\\"},\\"C1540364899996\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e5.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540364942134\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364942134\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e6.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540364970608\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540364979391\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364979391\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e7.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540364990144\\":{\\"params\\":{\\"row\\":\\"1\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364990144\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e8.png\\",\\"linkurl\\":\\"\\"},\\"C1540364990145\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e9.png\\",\\"linkurl\\":\\"\\"},\\"C1540364990146\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e10.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540365061143\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365061143\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e11.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540365077375\\":{\\"params\\":{\\"row\\":\\"1\\"},\\"style\\":{\\"paddingtop\\":\\"3\\",\\"paddingleft\\":\\"3\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365077375\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e12.png\\",\\"linkurl\\":\\"\\"},\\"C1540365077376\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e13.png\\",\\"linkurl\\":\\"\\"},\\"C1540365077377\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e14.png\\",\\"linkurl\\":\\"\\"},\\"C1540365077378\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e15.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540365142766\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365142766\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e17.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540365163448\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"30\\"},\\"style\\":{\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"goods\\"}}}', '1614650642', '1614650642', '1', '0', '1', '0', '/public/static/images/customwap/ef.jpg', '1', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('920', '0', '0', '店铺首页', '{\\"page\\":{\\"type\\":\\"2\\",\\"title\\":\\"店铺首页\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M012345678901\\":{\\"id\\":\\"shop_head\\",\\"style\\":{\\"backgroundimage\\":\\"\\"},\\"params\\":{\\"styletype\\":\\"1\\"}},\\"M1553937494650\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1553937486573\\":{\\"data\\":{\\"C1553937486573\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853551.png\\",\\"linkurl\\":\\"\\"},\\"C1553937486574\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853554.png\\",\\"linkurl\\":\\"\\"},\\"C1553937488179\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853557.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1553937521428\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1553937521428\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/seckill/list\\",\\"text\\":\\"秒杀\\",\\"color\\":\\"#666666\\"},\\"C1553937521429\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853590.png\\",\\"linkurl\\":\\"/packages/assemble/list\\",\\"text\\":\\"拼团\\",\\"color\\":\\"#666666\\"},\\"C1553937521430\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853577.png\\",\\"linkurl\\":\\"/packages/bargain/list\\",\\"text\\":\\"砍价\\",\\"color\\":\\"#666666\\"},\\"C1553937521431\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853657.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"预售\\",\\"color\\":\\"#666666\\"},\\"C1553937522968\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853649.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"限时折扣\\",\\"color\\":\\"#666666\\"},\\"C1553937524237\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853560.png\\",\\"linkurl\\":\\"/pages/shop/list\\",\\"text\\":\\"店铺街\\",\\"color\\":\\"#666666\\"},\\"C1553937525741\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853575.png\\",\\"linkurl\\":\\"/pages/integral/index\\",\\"text\\":\\"积分商城\\",\\"color\\":\\"#666666\\"},\\"C1553937526523\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853579.png\\",\\"linkurl\\":\\"/pages/coupon/index\\",\\"text\\":\\"领券中心\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"},\\"M1553937746852\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553937755757\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937755758\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916562.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937773707\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937773708\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853592.png\\",\\"linkurl\\":\\"\\"},\\"C1553937773709\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853594.png\\",\\"linkurl\\":\\"\\"},\\"C1553937773710\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853595.png\\",\\"linkurl\\":\\"\\"},\\"C1553937801566\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853597.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553937841539\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553937856228\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937856228\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916601.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937873887\\":{\\"params\\":{\\"row\\":\\"1\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937873887\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553938211.png\\",\\"linkurl\\":\\"\\"},\\"C1553937873888\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853653.png\\",\\"linkurl\\":\\"\\"},\\"C1553937873889\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553938197.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553937921667\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553937930274\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937930274\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916735.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937943525\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937943525\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853612.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553937960259\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553937960260\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916826.png\\",\\"linkurl\\":\\"\\"},\\"C1553937960261\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916872.png\\",\\"linkurl\\":\\"\\"},\\"C1553937960262\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916884.png\\",\\"linkurl\\":\\"\\"},\\"C1553938002798\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916903.png\\",\\"linkurl\\":\\"\\"},\\"C1553938004205\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916905.png\\",\\"linkurl\\":\\"\\"},\\"C1553938005286\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916907.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553938027158\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553938034724\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938034724\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916932.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553938047130\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938047130\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916942.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553938061645\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938061645\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916965.png\\",\\"linkurl\\":\\"\\"},\\"C1553938061646\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916967.png\\",\\"linkurl\\":\\"\\"},\\"C1553938061647\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916969.png\\",\\"linkurl\\":\\"\\"},\\"C1553938061648\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916971.png\\",\\"linkurl\\":\\"\\"},\\"C1553938099605\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916973.png\\",\\"linkurl\\":\\"\\"},\\"C1553938100435\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916974.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553938116753\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553938123142\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553938123142\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553917250.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553938132517\\":{\\"shoptype\\":\\"2\\",\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"0\\",\\"recommendnum\\":\\"4\\",\\"style\\":\\"\\",\\"show_title\\":\\"1\\",\\"show_sub_title\\":\\"1\\",\\"show_coupon\\":\\"1\\",\\"show_price\\":\\"1\\",\\"show_market_price\\":\\"1\\",\\"show_commission\\":\\"1\\",\\"show_sales\\":\\"1\\",\\"show_tag\\":\\"0\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\"},\\"data\\":{\\"C1867\\":{\\"goods_id\\":\\"1867\\",\\"goods_name\\":\\"[测试商品]Vero Moda春季职场风七分袖宽松西服外套女|318308530\\",\\"price\\":\\"324.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1860\\":{\\"goods_id\\":\\"1860\\",\\"goods_name\\":\\"[测试商品]Vero Moda夏季新款翻领一粒扣中长款休闲西装外套|318208505\\",\\"price\\":\\"239.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1881\\":{\\"goods_id\\":\\"1881\\",\\"goods_name\\":\\"Vero Moda新款不规则裙摆收腰连衣裙|31827C534\\",\\"price\\":\\"260.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1871\\":{\\"goods_id\\":\\"1871\\",\\"goods_name\\":\\"江疏影明星同款Vero Moda2019夏季新款V领连衣裙女|31927B519\\",\\"price\\":\\"699.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"}},\\"id\\":\\"goods\\"}}}', '0', '0', '2', '0', '1', '0', '/public/static/images/customwap/if.png', '1', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('921', '0', '0', '商品详情页', '{\\"page\\":{\\"type\\":\\"3\\",\\"title\\":\\"请输入页面标题\\",\\"name\\":\\"商品详情页\\",\\"background\\":\\"#f8f8f8\\",\\"readonly\\":\\"true\\"},\\"items\\":{\\"M012345678901\\":{\\"id\\":\\"detail_banner\\",\\"max\\":\\"1\\",\\"style\\":{\\"shape\\":\\"round\\",\\"position\\":\\"center\\",\\"color\\":\\"#1989fa\\"}},\\"M012345678902\\":{\\"id\\":\\"detail_info\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"10\\",\\"marginbottom\\":\\"10\\",\\"pricecolor\\":\\"#ff454e\\",\\"pricelightcolor\\":\\"#909399\\",\\"promotecolor\\":\\"#ffffff\\",\\"promotelightcolor\\":\\"#ffffff\\",\\"titlecolor\\":\\"#323233\\"}},\\"M012345678903\\":{\\"id\\":\\"detail_promote\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"10\\",\\"marginbottom\\":\\"10\\",\\"titlecolor\\":\\"#606266\\"},\\"data\\":{\\"C0123456789101\\":{\\"key\\":\\"fullcut\\",\\"text\\":\\"促销\\"},\\"C0123456789102\\":{\\"key\\":\\"coupon\\",\\"text\\":\\"优惠\\"},\\"C0123456789103\\":{\\"key\\":\\"rebate\\",\\"text\\":\\"返利\\"}}},\\"M012345678904\\":{\\"id\\":\\"detail_specs\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"0\\",\\"marginbottom\\":\\"0\\",\\"titlecolor\\":\\"#606266\\",\\"currentcolor\\":\\"#323233\\",\\"nocurrentcolor\\":\\"#909399\\"}},\\"M012345678905\\":{\\"id\\":\\"detail_delivery\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"0\\",\\"marginbottom\\":\\"0\\",\\"titlecolor\\":\\"#606266\\",\\"currentcolor\\":\\"#323233\\",\\"nocurrentcolor\\":\\"#909399\\"}},\\"M012345678906\\":{\\"id\\":\\"detail_service\\",\\"max\\":\\"1\\",\\"style\\":{\\"margintop\\":\\"0\\",\\"marginbottom\\":\\"0\\",\\"titlecolor\\":\\"#323233\\",\\"desccolor\\":\\"#606266\\"},\\"data\\":{\\"C0123456789101\\":{\\"title\\":\\"7天无理由退货\\",\\"desc\\":\\"商城所有商品均为正品。\\",\\"imgurl\\":\\"/public/platform/images/custom/default/goodsicon-sendfree.png\\"}}},\\"M012345678908\\":{\\"id\\":\\"detail_desc\\",\\"max\\":\\"1\\"}}}', '0', '0', '3', '0', '1', '0', '/public/static/images/customwap/hf.png', '1', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('922', '0', '0', '会员中心', '{\\"page\\":{\\"type\\":\\"4\\",\\"title\\":\\"会员中心\\",\\"name\\":\\"会员中心\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M0_member\\":{\\"id\\":\\"member_fixed\\",\\"style\\":{\\"backgroundimage\\":\\"\\"},\\"params\\":{\\"styletype\\":\\"1\\"}},\\"M0_member_bind\\":{\\"id\\":\\"member_bind_fixed\\",\\"style\\":{\\"background\\":\\"#fff\\",\\"iconcolor\\":\\"#ff454e\\",\\"titlecolor\\":\\"#323233\\",\\"desccolor\\":\\"#909399\\"},\\"params\\":{\\"title\\":\\"绑定手机\\",\\"desc\\":\\"为了账号安全、方便购物和订单同步，请绑定手机号码。\\"}},\\"M0_member_order\\":{\\"id\\":\\"member_order_fixed\\",\\"style\\":{\\"background\\":\\"#fff\\",\\"textcolor\\":\\"#323233\\",\\"iconcolor\\":\\"#323233\\",\\"titlecolor\\":\\"#323233\\",\\"titleiconcolor\\":\\"#323233\\",\\"titleremarkcolor\\":\\"#909399\\"},\\"params\\":{\\"title\\":\\"我的订单\\",\\"remark\\":\\"全部订单\\",\\"iconclass\\":\\"v-icon-form\\"},\\"data\\":{\\"C0123456789101\\":{\\"key\\":\\"unpaid\\",\\"name\\":\\"待付款\\",\\"text\\":\\"待付款\\",\\"iconclass\\":\\"v-icon-payment2\\",\\"is_show\\":\\"1\\"},\\"C0123456789102\\":{\\"key\\":\\"unshipped\\",\\"name\\":\\"待发货\\",\\"text\\":\\"待发货\\",\\"iconclass\\":\\"v-icon-delivery2\\",\\"is_show\\":\\"1\\"},\\"C0123456789103\\":{\\"key\\":\\"unreceived\\",\\"name\\":\\"待收货\\",\\"text\\":\\"待收货\\",\\"iconclass\\":\\"v-icon-logistic3\\",\\"is_show\\":\\"1\\"},\\"C0123456789104\\":{\\"key\\":\\"unevaluated\\",\\"name\\":\\"待评价\\",\\"text\\":\\"待评价\\",\\"iconclass\\":\\"v-icon-success1\\",\\"is_show\\":\\"1\\"},\\"C0123456789105\\":{\\"key\\":\\"aftersale\\",\\"name\\":\\"售后\\",\\"text\\":\\"售后\\",\\"iconclass\\":\\"v-icon-sale\\",\\"is_show\\":\\"1\\"}}},\\"M0_member_assets\\":{\\"id\\":\\"member_assets_fixed\\",\\"style\\":{\\"background\\":\\"#fff\\",\\"textcolor\\":\\"#323233\\",\\"iconcolor\\":\\"#323233\\",\\"highlight\\":\\"#ff454e\\",\\"titlecolor\\":\\"#323233\\",\\"titleiconcolor\\":\\"#323233\\",\\"titleremarkcolor\\":\\"#909399\\"},\\"params\\":{\\"title\\":\\"我的资产\\",\\"remark\\":\\"更多\\",\\"iconclass\\":\\"v-icon-assets\\"},\\"data\\":{\\"C0_balance\\":{\\"no_addons\\":\\"0\\",\\"key\\":\\"balance\\",\\"name\\":\\"余额\\",\\"text\\":\\"余额\\",\\"is_show\\":\\"1\\"},\\"C0_points\\":{\\"no_addons\\":\\"0\\",\\"key\\":\\"points\\",\\"name\\":\\"积分\\",\\"text\\":\\"积分\\",\\"is_show\\":\\"1\\"},\\"C0_coupontype\\":{\\"no_addons\\":\\"1\\",\\"key\\":\\"coupontype\\",\\"name\\":\\"优惠券\\",\\"text\\":\\"优惠券\\",\\"is_show\\":\\"1\\"},\\"C0_giftvoucher\\":{\\"no_addons\\":\\"1\\",\\"key\\":\\"giftvoucher\\",\\"name\\":\\"礼品券\\",\\"text\\":\\"礼品券\\",\\"is_show\\":\\"1\\"},\\"C0_store\\":{\\"no_addons\\":\\"0\\",\\"key\\":\\"store\\",\\"name\\":\\"消费卡\\",\\"text\\":\\"消费卡\\",\\"is_show\\":\\"1\\"},\\"C0_blockchain\\":{\\"no_addons\\":\\"1\\",\\"key\\":\\"blockchain\\",\\"name\\":\\"数字钱包\\",\\"text\\":\\"数字钱包\\",\\"is_show\\":\\"1\\"}}},\\"M1548927666181\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1548927666181\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/distribute/index\\",\\"text\\":\\"分销中心\\",\\"color\\":\\"#666666\\"},\\"C1548927762708\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/bonus/index\\",\\"text\\":\\"分红中心\\",\\"color\\":\\"#666666\\"},\\"C1548927767125\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/channel/index\\",\\"text\\":\\"微商中心\\",\\"color\\":\\"#666666\\"},\\"C1548927773213\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/microshop/index\\",\\"text\\":\\"我的微店\\",\\"color\\":\\"#666666\\"},\\"C1548927703430\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/goods/collect\\",\\"text\\":\\"商品收藏\\",\\"color\\":\\"#666666\\"},\\"C1548927704982\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/shop/collect\\",\\"text\\":\\"店铺收藏\\",\\"color\\":\\"#666666\\"},\\"C1548927706653\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/prize/list\\",\\"text\\":\\"我的奖品\\",\\"color\\":\\"#666666\\"},\\"C1548927707941\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/task/index\\",\\"text\\":\\"任务中心\\",\\"color\\":\\"#666666\\"},\\"C1548927732749\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/pages/help/index\\",\\"text\\":\\"关于我们\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"}}}', '0', '0', '4', '0', '1', '0', '/public/static/images/customwap/jf.png', '1', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('923', '0', '0', '分销中心', '{\\"page\\":{\\"type\\":\\"5\\",\\"title\\":\\"分销中心\\",\\"name\\":\\"分销中心\\",\\"background\\":\\"#f8f8f8\\"},\\"items\\":{\\"M012345678901\\":{\\"id\\":\\"commission_fixed\\",\\"params\\":{\\"styletype\\":\\"1\\"},\\"style\\":{\\"backgroundimage\\":\\"\\"}},\\"M1542076937387\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1542076937387\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-1.png\\",\\"linkurl\\":\\"/packages/distribute/order\\",\\"text\\":\\"分销订单\\",\\"color\\":\\"#666666\\"},\\"C1542076937388\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-2.png\\",\\"linkurl\\":\\"/packages/distribute/team\\",\\"text\\":\\"我的团队\\",\\"color\\":\\"#666666\\"},\\"C1542076937389\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-3.png\\",\\"linkurl\\":\\"/packages/distribute/customer\\",\\"text\\":\\"我的客户\\",\\"color\\":\\"#666666\\"},\\"C1542076937390\\":{\\"imgurl\\":\\"/public/platform/images/custom/default/icon-4.png\\",\\"linkurl\\":\\"/packages/distribute/qrcode\\",\\"text\\":\\"推广二维码\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"}}}', '0', '0', '5', '0', '1', '0', '/public/static/images/customwap/kf.png', '1', '', '0');
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('924', '0', '0', '商城首页(mp系统默认)', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"首页\\",\\"background\\":\\"#f8f8f8\\",\\"navbarcolor\\":\\"black\\",\\"navbarbackground\\":\\"#ffffff\\"},\\"items\\":{\\"M1553483628487\\":{\\"params\\":{\\"placeholder\\":\\"搜索商品\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1553483656818\\":{\\"data\\":{\\"C1553483656818\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853557.png\\",\\"linkurl\\":\\"\\"},\\"C1553483656819\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853554.png\\",\\"linkurl\\":\\"\\"},\\"C1553483737933\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853551.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1553502887488\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553483761620\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"4\\"},\\"data\\":{\\"C1553483761620\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853581.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"秒杀\\",\\"color\\":\\"#666666\\"},\\"C1553483761621\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853590.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"拼团\\",\\"color\\":\\"#666666\\"},\\"C1553483761622\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853577.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"砍价\\",\\"color\\":\\"#666666\\"},\\"C1553483761623\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853657.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"预售\\",\\"color\\":\\"#666666\\"},\\"C1553937373061\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853649.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"限时折扣\\",\\"color\\":\\"#666666\\"},\\"C1553937374147\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853560.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"店铺街\\",\\"color\\":\\"#666666\\"},\\"C1553937375091\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853575.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"积分商城\\",\\"color\\":\\"#666666\\"},\\"C1553937376043\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853579.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"领券中心\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"},\\"M1553502901450\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553485161187\\":{\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553485161187\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916562.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553503800371\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553503800371\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853592.png\\",\\"linkurl\\":\\"\\"},\\"C1553503800372\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853594.png\\",\\"linkurl\\":\\"\\"},\\"C1553503800373\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853595.png\\",\\"linkurl\\":\\"\\"},\\"C1553503800374\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853597.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553504312148\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553486200676\\":{\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553486200676\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916601.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553484479736\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553484479737\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853651.png\\",\\"linkurl\\":\\"\\"},\\"C1553484479738\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553853653.png\\",\\"linkurl\\":\\"\\"},\\"C1553503910779\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553926383.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553494731813\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553494712622\\":{\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494712622\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916735.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553494772637\\":{\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494772638\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916775.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553494755486\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494755486\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916826.png\\",\\"linkurl\\":\\"\\"},\\"C1553494755487\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916872.png\\",\\"linkurl\\":\\"\\"},\\"C1553494755488\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916884.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553494966186\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494966187\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916903.png\\",\\"linkurl\\":\\"\\"},\\"C1553494966188\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916905.png\\",\\"linkurl\\":\\"\\"},\\"C1553494966189\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916907.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553494924693\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553494901371\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494901371\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916932.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553494934492\\":{\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494934492\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916942.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553494951690\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494951691\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916965.png\\",\\"linkurl\\":\\"\\"},\\"C1553494951693\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916967.png\\",\\"linkurl\\":\\"\\"},\\"C1553494951694\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916969.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553494952538\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553494952539\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553927745.png\\",\\"linkurl\\":\\"\\"},\\"C1553494952540\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553927747.png\\",\\"linkurl\\":\\"\\"},\\"C1553494952541\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553916974.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1553500902574\\":{\\"style\\":{\\"height\\":\\"5\\",\\"background\\":\\"#f8f8f8\\"},\\"id\\":\\"blank\\"},\\"M1553501319693\\":{\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1553501319693\\":{\\"imgurl\\":\\"https://pic.vslai.com.cn/upload/default/1553917250.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1553494344972\\":{\\"shoptype\\":\\"1\\",\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"0\\",\\"recommendnum\\":\\"4\\"},\\"style\\":{\\"background\\":\\"#f8f8f8\\"},\\"data\\":{\\"C1867\\":{\\"goods_id\\":\\"1867\\",\\"goods_name\\":\\"[测试商品]Vero Moda春季职场风七分袖宽松西服外套女|318308530\\",\\"price\\":\\"324.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i2/420567757/O1CN0127Akkpc6lSqD2po_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1860\\":{\\"goods_id\\":\\"1860\\",\\"goods_name\\":\\"[测试商品]Vero Moda夏季新款翻领一粒扣中长款休闲西装外套|318208505\\",\\"price\\":\\"239.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1bsucXyAnBKNjSZFvXXaTKXXa_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1871\\":{\\"goods_id\\":\\"1871\\",\\"goods_name\\":\\"江疏影明星同款Vero Moda2019夏季新款V领连衣裙女|31927B519\\",\\"price\\":\\"699.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i3/420567757/O1CN01PKLz1827Akmc6uAuC_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"},\\"C1881\\":{\\"goods_id\\":\\"1881\\",\\"goods_name\\":\\"Vero Moda新款不规则裙摆收腰连衣裙|31827C534\\",\\"price\\":\\"260.00\\",\\"shop_name\\":\\"17665012114\\",\\"pic_cover\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg\\",\\"pic_cover_mid\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_400x400.jpg\\",\\"pic_cover_small\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_200x200.jpg\\",\\"pic_cover_micro\\":\\"https://img.alicdn.com/imgextra/i1/420567757/TB1YvAOk1uSBuNjy1XcXXcYjFXa_!!0-item_pic.jpg_100x100.jpg\\",\\"isselect\\":\\"1\\"}},\\"id\\":\\"goods\\"}}}', '1617088450', '1617088450', '1', '0', '1', '0', '/public/static/images/customwap/cf.png', '2', 'https://vslai-com-cn.oss-cn-hangzhou.aliyuncs.com/upload/26/2021/01/29/11/1611891239308.png', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('925', '0', '0', '模板2', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540288974198\\":{\\"data\\":{\\"C1540288974198\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a1.png\\",\\"linkurl\\":\\"\\"},\\"C1540288974199\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540290294866\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"5\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540290294866\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a2.png\\",\\"linkurl\\":\\"\\"},\\"C1540290294867\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a3.png\\",\\"linkurl\\":\\"\\"},\\"C1540290294868\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a4.png\\",\\"linkurl\\":\\"\\"},\\"C1540290294869\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a5.png\\",\\"linkurl\\":\\"\\"},\\"C1540290337082\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a6.png\\",\\"linkurl\\":\\"\\"},\\"C1540290338449\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a7.png\\",\\"linkurl\\":\\"\\"},\\"C1540290339537\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a8.png\\",\\"linkurl\\":\\"\\"},\\"C1540290340249\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a9.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540289816608\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540289816608\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a10.png\\",\\"linkurl\\":\\"\\"},\\"C1540289816609\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a11.png\\",\\"linkurl\\":\\"\\"},\\"C1540289816610\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a12.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540290112056\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540289906152\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"2\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540289906152\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a13.png\\",\\"linkurl\\":\\"\\"},\\"C1540289906153\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a14.png\\",\\"linkurl\\":\\"\\"},\\"C1540289906154\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a15.png\\",\\"linkurl\\":\\"\\"},\\"C1540289906155\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a16.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540290030584\\":{\\"style\\":{\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#f3f3f3\\"},\\"data\\":{\\"C1540290030584\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a17.png\\",\\"linkurl\\":\\"\\"},\\"C1540290047041\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a18.png\\",\\"linkurl\\":\\"\\"},\\"C1540290048241\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/a19.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540292269025\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"30\\"},\\"style\\":{\\"background\\":\\"#ffffff\\"},\\"id\\":\\"goods\\"}}}', '1617088450', '1617088450', '1', '0', '1', '0', '/public/static/images/customwap/af.png', '2', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('926', '0', '0', '模版2', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540292289458\\":{\\"params\\":{\\"text\\":\\"这个一条公告内容。\\",\\"leftIcon\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/notice-icon.png\\"},\\"style\\":{\\"background\\":\\"#fff7cc\\",\\"color\\":\\"#f60\\"},\\"id\\":\\"notice\\"},\\"M1540292289856\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1540292292296\\":{\\"data\\":{\\"C1540292292297\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b1.png\\",\\"linkurl\\":\\"\\"},\\"C1540292292298\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540292301465\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"8\\",\\"paddingleft\\":\\"15\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292301465\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b2.png\\",\\"linkurl\\":\\"\\"},\\"C1540292301466\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b3.png\\",\\"linkurl\\":\\"\\"},\\"C1540292301467\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b4.png\\",\\"linkurl\\":\\"\\"},\\"C1540292301468\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b4_1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540292330898\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540292341585\\":{\\"params\\":{\\"title\\":\\"尖端商品\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#666666\\",\\"textalign\\":\\"center\\",\\"fontsize\\":\\"14\\",\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540292352432\\":{\\"params\\":{\\"row\\":\\"2\\"},\\"style\\":{\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"6\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292352432\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b5.png\\",\\"linkurl\\":\\"\\"},\\"C1540292352433\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b6.png\\",\\"linkurl\\":\\"\\"},\\"C1540292352434\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b7.png\\",\\"linkurl\\":\\"\\"},\\"C1540292352435\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b8.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540292394776\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292394776\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b9.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540292405328\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"10\\"},\\"style\\":{\\"background\\":\\"#ffffff\\"},\\"id\\":\\"goods\\"},\\"M1540292436712\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540292437152\\":{\\"params\\":{\\"title\\":\\"人气推荐\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#666666\\",\\"textalign\\":\\"center\\",\\"fontsize\\":\\"14\\",\\"paddingtop\\":\\"5\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540292461768\\":{\\"style\\":{\\"paddingtop\\":\\"9\\",\\"paddingleft\\":\\"15\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540292461768\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b10.png\\",\\"linkurl\\":\\"\\"},\\"C1540292463376\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/b11.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"}}}', '1617088450', '1617088450', '1', '0', '1', '0', '/public/static/images/customwap/bf.png', '2', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('927', '0', '0', '模版3', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540353447056\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1540353449888\\":{\\"data\\":{\\"C1540353449888\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d1.png\\",\\"linkurl\\":\\"\\"},\\"C1540353449889\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540353472110\\":{\\"style\\":{\\"background\\":\\"#ffffff\\",\\"rownum\\":\\"5\\"},\\"data\\":{\\"C1540353472110\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-1.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字1\\",\\"color\\":\\"#666666\\"},\\"C1540353472111\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-2.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字2\\",\\"color\\":\\"#666666\\"},\\"C1540353472112\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-3.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字3\\",\\"color\\":\\"#666666\\"},\\"C1540353472113\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-4.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字4\\",\\"color\\":\\"#666666\\"},\\"C1540353488145\\":{\\"imgurl\\":\\"\\/public\\/platform\\/images\\/custom\\/default\\/icon-1.png\\",\\"linkurl\\":\\"\\",\\"text\\":\\"按钮文字1\\",\\"color\\":\\"#666666\\"}},\\"id\\":\\"menu\\"},\\"M1540353506112\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540353506112\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d12.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540353523161\\":{\\"params\\":{\\"title\\":\\"新品推荐\\"},\\"style\\":{\\"background\\":\\"#ffffff\\",\\"color\\":\\"#ff5151\\",\\"textalign\\":\\"left\\",\\"fontsize\\":\\"18\\",\\"paddingtop\\":\\"11\\",\\"paddingleft\\":\\"5\\"},\\"id\\":\\"title\\"},\\"M1540353561593\\":{\\"params\\":{\\"row\\":\\"3\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"4\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540353561593\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d13.png\\",\\"linkurl\\":\\"\\"},\\"C1540353561594\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d14.png\\",\\"linkurl\\":\\"\\"},\\"C1540353561595\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/d15.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"}}}', '1617088450', '1617088450', '1', '0', '1', '0', '/public/static/images/customwap/df.png', '2', '', NULL);
INSERT INTO `sys_custom_template_all` (`id`, `website_id`, `shop_id`, `template_name`, `template_data`, `create_time`, `update_time`, `type`, `is_default`, `is_system_default`, `in_use`, `template_logo`, `ports`, `preview_img`, `exist_id`) VALUES('928', '0', '0', '模版4', '{\\"page\\":{\\"type\\":\\"1\\",\\"title\\":\\"商城首页\\",\\"background\\":\\"#ffffff\\"},\\"items\\":{\\"M1540364869288\\":{\\"params\\":{\\"placeholder\\":\\"请输入关键字进行搜索\\"},\\"style\\":{\\"background\\":\\"#f1f1f2\\",\\"paddingtop\\":\\"10\\",\\"paddingleft\\":\\"10\\"},\\"id\\":\\"search\\"},\\"M1540364872751\\":{\\"data\\":{\\"C1540364872752\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e1.png\\",\\"linkurl\\":\\"\\"},\\"C1540364872753\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e1.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"banner\\"},\\"M1540364899993\\":{\\"params\\":{\\"row\\":\\"4\\"},\\"style\\":{\\"paddingtop\\":\\"9\\",\\"paddingleft\\":\\"8\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364899993\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e2.png\\",\\"linkurl\\":\\"\\"},\\"C1540364899994\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e3.png\\",\\"linkurl\\":\\"\\"},\\"C1540364899995\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e4.png\\",\\"linkurl\\":\\"\\"},\\"C1540364899996\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e5.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540364942134\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364942134\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e6.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540364970608\\":{\\"style\\":{\\"height\\":\\"10\\",\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"blank\\"},\\"M1540364979391\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364979391\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e7.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540364990144\\":{\\"params\\":{\\"row\\":\\"1\\"},\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540364990144\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e8.png\\",\\"linkurl\\":\\"\\"},\\"C1540364990145\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e9.png\\",\\"linkurl\\":\\"\\"},\\"C1540364990146\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e10.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540365061143\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365061143\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e11.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540365077375\\":{\\"params\\":{\\"row\\":\\"1\\"},\\"style\\":{\\"paddingtop\\":\\"3\\",\\"paddingleft\\":\\"3\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365077375\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e12.png\\",\\"linkurl\\":\\"\\"},\\"C1540365077376\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e13.png\\",\\"linkurl\\":\\"\\"},\\"C1540365077377\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e14.png\\",\\"linkurl\\":\\"\\"},\\"C1540365077378\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e15.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picturew\\"},\\"M1540365142766\\":{\\"style\\":{\\"paddingtop\\":\\"0\\",\\"paddingleft\\":\\"0\\",\\"background\\":\\"#ffffff\\"},\\"data\\":{\\"C1540365142766\\":{\\"imgurl\\":\\"/public\\/static\\/images\\/customwap\\/e17.png\\",\\"linkurl\\":\\"\\"}},\\"id\\":\\"picture\\"},\\"M1540365163448\\":{\\"params\\":{\\"recommendtype\\":\\"0\\",\\"goodstype\\":\\"0\\",\\"goodssort\\":\\"3\\",\\"recommendnum\\":\\"30\\"},\\"style\\":{\\"background\\":\\"#f3f3f3\\"},\\"id\\":\\"goods\\"}}}', '1617088450', '1617088450', '1', '0', '1', '0', '/public/static/images/customwap/gf.png', '2', '', NULL);
EOF;
    
    
    
    if ($conn->multi_query($sql) === TRUE) {
        echo "新记录插入成功";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
    }
}
