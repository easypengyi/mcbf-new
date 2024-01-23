<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/18 0018
 * Time: 17:37
 */

namespace addons\miniprogram\controller;

use addons\miniprogram\Miniprogram as BaseMiniProgram;
use addons\miniprogram\model\WeixinAuthModel;
use addons\miniprogram\service\MiniProgram as miniProgramService;
use addons\qlkefu\server\Qlkefu as QlkefuService;
use data\extend\WchatOpen;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\Config as configServer;
use data\service\AddonsConfig;
use data\service\Customtemplate as CustomtemplateSer;
use data\service\User as UserSer;
use data\service\WebSite as WebSiteSer;
use think\Db;
use think\Config;
use think\Exception;
use think\Request;
use think\Session;

class Miniprogram extends BaseMiniProgram
{
    protected $authorizer_access_token;
    protected $app_id;
    protected $auth_id;
    protected $template_id;
    protected $access_token;
    protected $nick_name;
    public $wchat_open;
    public $mini_program_service;
    public $weixin_auth_model;
    protected $packageV3;

    public function __construct()
    {
        parent::__construct();
        $this->weixin_auth_model = new WeixinAuthModel();
        $this->mini_program_service = new miniProgramService();
        $mp_info = $this->mini_program_service->miniProgramInfo(['website_id' => $this->website_id]);
        $this->authorizer_access_token = $mp_info['authorizer_access_token'];
        $this->app_id = $mp_info['authorizer_appid'];
        $this->template_id = $mp_info['template_id'];
        $this->auth_id = $mp_info['auth_id'];
        $this->nick_name = $mp_info['nick_name'];
        $this->packageV3 = $this->website_id.'_isMpPackageV3';
    }

    /******************************************* wap use start **************************************************************************/
    /**
     * [wap|addons]
     * 小程序调用返回商家名、logo
     * @return false|string
     */
    public function getMpBaseInfo()
    {
        $website_id = request()->post('website_id');

        # 获取名字
        $webSiteSer = new WebSiteSer();
        $mall_name = $webSiteSer->getWebInfo(['website_id' => $website_id], 'mall_name');

        # 获取logo
        $logo = $this->weixin_auth_model->getInfo(['website_id' => $website_id],  'head_img');

        $returnArr = [
            'name' => isset($mall_name['mall_name']) ? $mall_name['mall_name'] : '',
            'logo' => isset($logo['head_img']) ? $logo['head_img']: ''
        ];

        return json(['code' => 1, 'message' => '成功获取', 'data' => $returnArr]);
    }

    /**
     * [wap|addons]
     * 生成对应太阳码 (带场景值的暂时弃用)
     * @param $authorizer_access_token
     * @param array $params
     * @param int $type 1有限 2无限
     * @param bool $is_buffer 是否不转成图片直接返回buffer
     * @return bool|string|void
     */
    public function createSunCodeUrl($authorizer_access_token, $params = [], $type = 1, $is_buffer = false)
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        # 带场景值的暂时弃用！因为用base64返回
        $imgRes = $this->wchat_open->getSunCodeApi($authorizer_access_token, $params, $type);
        if (empty($imgRes)) {
            return ;
        }
        if ($is_buffer) {return $imgRes;}
        // 图片处理
        try{
            // eg:  type = 1  'upload/sunCode/1/26    | type = 2  'upload/sunCode/2/0'
            $website_id = !empty($this->website_id) ? $this->website_id: 0;
            $path = 'upload/sunCode/' . $type .'/'. $website_id;
            $imgName = !empty($this->nick_name) ? md5($this->nick_name) : time();
            /**
             * 如果是场景值二维码（临时），为了防止云端存储多余临时图片，每次生成图片名都为1.jpeg,这样就覆盖上一次的图片
             * 保证每次请求都是新的场景值小程序码同时，云端都只有1.png的图片
             */
            if ($type == 2) {
                $imgName = $this->uid ? : 1;
                $scene_arr = explode('_', $params['scene']);
                if(strstr($params['scene'], '_poster')){
                    $arr_len = count($scene_arr);
                    if(is_array($scene_arr)){
                        $poster_id = $scene_arr[$arr_len - 2];
                    }
                    $imgName = $this->uid ? $this->uid.'_'.$poster_id : 1;
                }
                if(strstr($params['page'], 'goods') && strstr($params['scene'], '_poster')){
                    if(is_array($scene_arr)){
                        $goods_id = $scene_arr[1];
                    }
                    $imgName = $this->uid ? $this->uid.'_'.$goods_id.'_'.$poster_id : 1;
                }
            }

            // 上传云端
            $sunUrlFromYun = getImageFromYun($imgRes, $path, $imgName);
            if ($type == 1) {
                if ($sunUrlFromYun) {
                    // 图片链接写入数据库
                    $this->weixin_auth_model->update([
                        'sun_code_url' => $sunUrlFromYun
                    ],
                        [
                            'auth_id' => $this->auth_id
                        ]);
                }
            }

            return $sunUrlFromYun;
        } catch (\Exception $e) {
            $log = [
                'content' => $this->auth_id.'的太阳码存储错误:'.$e->getMessage(),
                'time' => date('Y-m-d H:i:s', time())
            ];
            Db::table('sys_log')->insert($log);
        }
    }

    /**
     * [wap|addons]
     * 获取太阳码（无限个，带场景值）
     * @param array $data 场景值参数
     * @param bool $is_buffer 是否不转成图片直接返回buffer格式
     * @param int $from 1前端调用 2后端调用
     * @return \think\response\Json
     */
    public function getUnLimitMpCode($data = [], $is_buffer = false)
    {
        // 是否3.0.0版本
        $request    = Request::instance()->post();
        $scene      = request()->post('scene/a');//object
        $page       = $request['page'] ?: Config::get('mp_route')[0];
        $website_id = $request['website_id'] ?: $this->website_id;
        $width      = $request['width'] ?: 280;
        if (empty($website_id) && empty($data)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $params = [
            'scene' => $scene,
            'page'  => $page,
            'width' => $width
        ];
        if (!empty($data)) {
            $data['width'] = $data['width'] ?: 280;
            $params = $data;
        }
        # 处理场景值：装为小于等于32位字符（微信该接口scene最大32位）
        $curr_scene         = is_array($params['scene']) ? json_encode($params['scene']) : $params['scene'];//value
        $md5_scene          = md5($this->website_id.$this->instance_id.$curr_scene);//key
        $sql_secen_id       = $this->mini_program_service->saveMysqlChangeScene($md5_scene, $curr_scene);
        $params['scene']    = $sql_secen_id;

        $mp_info = $this->weixin_auth_model->getInfo(['website_id' => $website_id],'authorizer_access_token');
        if (empty($mp_info)) {
            return AjaxWXReturn(FAIL);
        }
        $sun_code_url = $this->createSunCodeUrl($mp_info['authorizer_access_token'], $params, 2, $is_buffer);
        if ($sun_code_url) {
            if ($is_buffer) {
                return AjaxReturn(SUCCESS, $sun_code_url);
            }
            return AjaxReturn(SUCCESS,$sun_code_url .'?'. time() );
        } else {
            return AjaxReturn(FAIL,[],'没有小程序二维码');
        }
    }

    /**
     * [wap|addons]
     * 获取太阳码 （有限个）
     */
    public function getLimitMpCode()
    {
        $path = request()->post('path') ?:Config::get('mp_route')[0];
        $width = request()->post('width') ?:280;
        $params = [
            "path" => $path,
            'width' => $width
        ];

        // 封装生成太阳码的方法，修改new_auth_state = 1
        $sun_code_url = $this->createSunCodeUrl($this->authorizer_access_token, $params, 1);

        return $sun_code_url;
    }

    /**
     * [wap]
     * 获取帐号下的模板列表
     * @return mixed
     */
    public function getTemplateList($website_id=0)
    {
        $result = $this->mini_program_service->getTemplateList($website_id);
        if ($result['code'] < 0) {
            return $result;
        }
        return $result;
    }

    /**
     * [wap]
     * 小程序 - 订阅消息 - 获取模板ID
     * @return mixed|\multitype|void
     * @throws \think\Exception\DbException
     */
    public function getMpTemplateId()
    {
        try{
            $types = request()->post('type');
            if (!$types) {
                return AjaxReturn(LACK_OF_PARAMETER);
            }
            $types = explode(',', $types);
            $html_ids = '';
            foreach ($types as $type) {
                switch ($type) {
                    case 1:
                        $html_ids .= 'pay_success,';
                        break;
                    case 2:
                        $html_ids .= 'order_close,';
                        break;
                    case 3:
                        $html_ids .= 'balance_change,';
                        break;
                    case 4:
                        $html_ids .= 'refund_info,';
                        break;
                    default :
                        break;
                }
            }
            $html_ids = trim($html_ids, ',');
            if (!$html_ids) {
                return AjaxReturn(-1);
            }

            $condition = [
                'sys_mp_template_relation.website_id' => $this->website_id,
                'sys_mp_template_relation.shop_id' => $this->instance_id,
                'sys_mp_message_template.html_id' => ['in', $html_ids]
            ];
            $results = $this->mini_program_service->getMpTemplateInfo($condition);
            $return = [];
            $template_array = $this->getTemplateList($this->website_id);
            if ($template_array['code'] < 0) {
                debugLog($this->website_id.' 小程序订阅消息查询微信模板失败');
                return $template_array;
            }
            foreach ($results as $key => $result) {
                if (in_array($result['mp_template_id'], $template_array) && $result['message']) {
                    $type = 0;
                    switch ($result['message']['html_id']) {
                        case 'pay_success':
                            $type = 1;
                            break;
                        case 'order_close':
                            $type = 2;
                            break;
                        case 'balance_change':
                            $type = 3;
                            break;
                        case 'refund_info':
                            $type = 4;
                            break;
                        default :
                            break;
                    }
                    $return[$key] = [
                        'type' => $type,
                        'template_id' => $result['mp_template_id'],
                        'status' => $result['status']
                    ];
                }
            }
            return AjaxReturn(SUCCESS, $return);
        }catch (\Exception $e){
            debugLog($this->website_id.' 小程序订阅模板消息获取模板失败:'.$e->getMessage());
        }

    }

    /**
     * [wap]
     * 前端用户点击了订阅消息弹窗后，传入的数据
     * @return array|\multitype
     * @throws Exception\DbException
     */
    public function postUserMpTemplateInfo()
    {
        $uid = request()->post('uid');
        $template_list = request()->post('list/a');

        /*        $template_id = request()->post('template_id');//模板id
                $action = request()->post('action');//用户操作类型 accept：接收； reject：拒绝； ban:禁止
                $uid = request()->post('uid');*/

        if (!$uid || !$template_list) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $templates = '';
        foreach ($template_list as $key => $list)
        {
            $templates .= $list['template_id'] . ',';
        }
        $templates = trim($templates, ',');
        //通过模板id查询该模板的类型
        $condition = [
            'sys_mp_template_relation.website_id' => $this->website_id,
            'sys_mp_template_relation.shop_id' => $this->instance_id,
            'sys_mp_template_relation.mp_template_id' => ['in', $templates],
        ];
        $results = $this->mini_program_service->getMpTemplateInfo($condition);
        $new_template = [];
        foreach ($results as $k => $v)
        {
            foreach ($template_list as $kk => $vv)
            {
                if (($vv['template_id'] == $v['mp_template_id']) && $v['message']) {
                    $new_template[$v['message']['html_id']] = [
                        'template_id' => $vv['template_id'],
                        'action' => $vv['action'],
                    ];
                }
            }
        }

        Db::startTrans();
        try {
            $array = [];
            $userSer = new UserSer();
            $result = $userSer->getUserInfo($uid, 'mp_sub_message');
            if ($result['mp_sub_message']) {
                $array = json_decode($result['mp_sub_message'], true);
            }
            $array = array_merge($array, $new_template);
            // 写入user表
            $userSer->updateUserBaseInfo(['mp_sub_message' => json_encode($array, JSON_UNESCAPED_UNICODE)],['uid' => $uid]);
            Db::commit();
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }

    /**
     * MD5的太阳码场景值换取原始值
     * @return string
     */
    public function exchangeMpOriginalScene()
    {
        $scene = request()->post('scene');
        if (!$scene) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $orig_scene = $this->mini_program_service->getMysqlChangeScene($scene);//前端通过场景值id查
        if ($orig_scene) {
            return AjaxReturn(SUCCESS, json_decode($orig_scene));
        } else{
            return AjaxReturn(FAIL,[],'该码信息已失效，请重新生成');
        }
    }

    /**
     * 临时判断小程序包是否是3.0.0+
     * @return array|bool
     */
    public function tempIsMpPackageVersion3()
    {
        if (Session::get($this->packageV3)) {
            return Session::get($this->packageV3);
        }
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->templateList();

        if ($result->errcode){
            return AjaxWXReturn($result->errcode,[], $result->errmsg);
        }
        $resultArr = objToArr($result);
        $templateList = arrSortByValue($resultArr['template_list'], 'create_time');

        // 该小程序最新发布template_id
        $nowMpTemplateId = $this->mini_program_service->getNewestMpTemplateId();
        $user_version = 0;
        foreach ($templateList as $template) {
            if ($template['template_id'] == $nowMpTemplateId) {
                $user_version = $template['user_version'];
            }
        }
        if ($user_version) {
            $version_arr = explode('.',$user_version);
            if ($version_arr[0] >=3 ){
                Session::set($this->packageV3, true, 3600*24*365);
                return true;
            }
        }
        Session::set($this->packageV3, false, 3600*2);

        return false;
    }
    /******************************************* wap use end   **************************************************************************/

    /**
     * 小程序设置
     */
    public function miniProgramSetting()
    {

        try {
            $post_data = request()->post();
            $is_mini_program = $post_data['is_mini_program'];
            // 发布中不能关闭
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'submit_uid' => $this->uid
            ];
            $mini_status = $this->mini_program_service->getLastSumitStatus($condition);
            if ($mini_status == 2 && $is_mini_program ==0) {
                return ['code' => -1, 'message' => '小程序发布中，不能关闭！'];
            }
            unset($post_data['is_mini_program']);

            // 保存用户提交的类目
            $commit_category = '';
            if ($post_data['second_id']) {
                $commit_category = $this->mini_program_service->getMpCategoryForCommit($post_data['second_id']);
                unset($post_data['second_id']);
            }
            if ($post_data['authorizer_secret']) {
                // 写入小程序授权表
                $this->mini_program_service->saveWeixinAuth([
                    'authorizer_secret' => $post_data['authorizer_secret'],
                    'category' => $commit_category
                ],
                [
                    'website_id' => $this->website_id,
                    'shop_id' => $this->instance_id
                ]);
                unset($post_data['authorizer_secret']);
            }
            // 关闭原因，写入addons
            $ConfigService = new AddonsConfigService();
            $mini_program_info = $ConfigService->getAddonsConfig(parent::$addons_name, $this->website_id);
            // 商城关闭，修改关闭原因
            if (!empty($mini_program_info)) {
                $res = $ConfigService->updateAddonsConfig($post_data, "小程序设置", $is_mini_program, "miniprogram");
            } else {
                $res = $ConfigService->addAddonsConfig($post_data, "小程序设置", $is_mini_program, "miniprogram");
            }
            setAddons('miniprogram', $this->website_id, $this->instance_id);
            return ['code' => $res, 'message' => '修改成功'];
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }

    /**
     * 体验者二维码
     */
    public function testerQrCode()
    {
        $weixin_auth = $this->weixin_auth_model->getInfo(['auth_id' => $this->auth_id], 'qr_code_url');
        if ($weixin_auth && $weixin_auth['qr_code_url']) {
            return $weixin_auth['qr_code_url'];
        }

        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->getQrCode();
        if (stripos($result,'errcode')) {
            //使用默认图片
            return request()->domain().'/public/platform/images/mappqr.jpg';
        } else {
           //压缩图片
            // 上传云端
            $path = 'upload/qrCode/'. $this->website_id;
            $qrCodeUrlFromYun = getImageFromYun($result, $path, 'qrCode');
            if ($qrCodeUrlFromYun) {
                // 图片链接写入数据库
                $this->weixin_auth_model->update([
                    'qr_code_url' => $qrCodeUrlFromYun
                ],
                [
                    'auth_id' => $this->auth_id
                ]);
            }
            return $qrCodeUrlFromYun;
        }

    }

    /**
     * 提交小程序代码
     */
    public function commitMp()
    {
        $just_commit = request()->get('test');
        $type = request()->post('type', 2);//1发布直播 2非直播
        $experience = request()->post('experience');//1发布直播 2非直播
        if ($just_commit) {
            $type = $just_commit;
        }
        if($experience){
            $type = $just_commit;
        }
        $ext_data = $this->getCommitMpExtData();
        # 获取最新template_id
        // 查询template_id
        $template_id = $this->getTemplateByLiveType($type);
        if ($template_id['code']) {
            return $template_id;
        }
        $this->weixin_auth_model->where(['auth_id' => $this->auth_id])->update(['template_id' => $template_id]);
        $ext_data = str_replace('\\','',$ext_data);
        # 发布提交数据
        $data['ext_json'] = json_encode($ext_data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $data['template_id'] = $template_id;
        // 提交版本信息
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        $new_submit_record = $this->mini_program_service->getNewMpSubmitRecord($condition);
        if ($new_submit_record) {
            $versionRes = $this->autoAddVersionForMiniprogram($new_submit_record['version']);
            if ($versionRes['code'] > 0){
                $data['user_version'] = $versionRes['message'];
                $data['user_desc'] = '版本v'.$data['user_version'];
            } else {
                return $versionRes;
            }
        } else {
            $data['user_version'] = '1.0.0';
            $data['user_desc'] = '版本v'.$data['user_version'];
        }

        // 设置第三方域名
        $result = $this->postDomainToMp($type);
        $result = is_object($result) ? objToArr($result) : $result;
        if ($result['errcode']) {
            # 只提交,用于体验码体验
            if ($just_commit) {/*这里请保留，前端提交小程序会能掉这个接口来查看提交审核的代码*/
                p($result);
                echo '域名提交有误！';exit;
            }
            return AjaxWXReturn($result['errcode'], [], $result->message);
        }
        // 域名设置好之后提交代码
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->commitMpCode($data);
	    # 只提交,用于体验码体验
        if ($just_commit) {/*这里请保留，前端提交小程序会能掉这个接口来查看提交审核的代码*/
            p($data);
            p($result);
            echo '已提交代码但未提交审核，可以体验码体验！';exit;
        }
        if ($experience){
            //只添加体验，不发布
            return AjaxWXReturn($result->errcode, $result->errmsg);
        }

        if ($result->errcode) {
            return AjaxWXReturn($result->errcode, [], $result->errmsg);
        }

        sleep(10);

        // 类目获取
//        $category_result = $this->wchat_open->getMpCategory($this->authorizer_access_token);
        $category_result = $this->weixin_auth_model->getInfo(['website_id' => $this->website_id, 'shop_id' => $this->instance_id], 'category');
        $categoryList = json_decode($category_result['category'], true);
        if (empty($categoryList)) {
            $category_list = $this->mini_program_service->getMpCategoryForCommit();
            $this->weixin_auth_model->save(['category' => $category_list], ['auth_id' => $this->auth_id]);
            $categoryList = json_decode($category_list, true);
        }
        $category = $categoryList[0];
        $submit_data['item_list'][0]['address'] = Config::get('mp_route')[0];
        $submit_data['item_list'][0]['tag'] = '首页';
        $submit_data['item_list'][0]['title'] = '首页';
        $submit_data['item_list'][0]['first_id'] = $category['first_id'];
        $submit_data['item_list'][0]['first_class'] = $category['first_class'];
        $submit_data['item_list'][0]['second_id'] = $category['second_id'];
        $submit_data['item_list'][0]['second_class'] = $category['second_class'];
        $audit_result = $this->wchat_open->submitAudit($this->authorizer_access_token, $submit_data);

        // 记录提交审核和失败记录
        $submit_history['auth_id'] = $this->auth_id;
        $submit_history['submit_time'] = time();
        $submit_history['submit_uid'] = $this->uid;
        $submit_history['shop_id'] = $this->instance_id;
        $submit_history['website_id'] = $this->website_id;
        $submit_history['audit_id'] = $audit_result->auditid;
        $submit_history['version'] = $data['user_version'];
        $submit_history['template_id'] = $template_id;
        // 失败
        if ($audit_result->errcode) {
            return AjaxWXReturn($audit_result->errcode, [], $audit_result->errmsg);
        }
        // 成功
        $submit_history['status'] = 2;//审核中
        $this->mini_program_service->addSubmit($submit_history);

        return ['code' => 0, 'message' => '提交成功'];
    }

    /**
     * 获取草稿箱列表
     */
    public function draftList()
    {
        $page_index = request()->post('page_index');
        $page_size = request()->post('page_size');
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->draftList();
        $result = objToArr($result);
        if($result['errcode']){
            return AjaxWXReturn($result['errcode'], [], $result['errmsg']);
        }
        $app_id = $this->mini_program_service->getMpAuthorizerInfo(['website_id'=>$this->website_id],'authorizer_appid')['authorizer_appid'];
        $draft_list = [];
        foreach ($result['draft_list'] as $draft){
            if ($app_id != $draft['source_miniprogram_appid']){continue;}
            $draft_list[] = $draft;
        }
        $draft_list = arrSortByValue($draft_list,'create_time');
        $total_count = count($draft_list);
        $start = ($page_index -1)*$page_size;
        $draft_list = array_slice($draft_list,$start,$page_size);
        return ['code'=>0,'total_count' => $total_count,'data' => $draft_list];
    }

    /**
     * 添加草稿到模板库
     */
    public function addToTemplate()
    {
        $data['draft_id'] = request()->post('draft_id');
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->addToTemplate($data);
        if($result->errcode){
            return AjaxWXReturn($result->errcode, [], $result->errmsg);
        }
        return AjaxReturn(SUCCESS);
    }

    /**
     * 获取模板库列表
     */
    public function templateList()
    {
        $page_index = request()->post('page_index');
        $page_size = request()->post('page_size');
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->templateList();
        $result = objToArr($result);
        if($result['errcode']){
            return AjaxWXReturn($result['errcode'], [], $result['errmsg']);
        }
        $app_id = $this->mini_program_service->getMpAuthorizerInfo(['website_id'=>$this->website_id],'authorizer_appid')['authorizer_appid'];
        $template_list = [];
        foreach ($result['template_list'] as $template){
            if ($app_id != $template['source_miniprogram_appid']){continue;}
            $template_list[] = $template;
        }
        $template_list = arrSortByValue($template_list,'create_time');
        $total_count = count($template_list);
        $start = ($page_index -1)*$page_size;
        $template_list = array_slice($template_list,$start,$page_size);
        return ['code'=>0,'total_count' => $total_count,'data' => $template_list];
    }

    /**
     * 删除模板(模板库)
     */
    public function deleteTemplate()
    {
        $data['template_id'] = request()->post('template_id');
        $result = $this->wchat_open->deleteTemplate($data);
        $this->wchat_open = new WchatOpen($this->website_id);
        if($result->errcode){
            return AjaxWXReturn($result->errcode, [], $result->errmsg);
        }
        return AjaxReturn(SUCCESS);
    }

    /**
     * 获取最新模板
     */
    public function newTemplate()
    {
        $result = $this->wchat_open->templateList();
        $this->wchat_open = new WchatOpen($this->website_id);
        if ($result->errcode){
            return AjaxWXReturn($result->errcode,[], $result->errmsg);
        }
        $resultArr = objToArr($result);
        $templateList = arrSortByValue($resultArr['template_list'], 'create_time');
        $new_template_id = $templateList[0]['template_id'];

        return $new_template_id;
    }

    /**
     * 绑定小程序体验者
     */
    public function bindMpTester()
    {
        $wchat_id = request()->post('wchat_id');
        if (empty($wchat_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $data['wechatid'] = $wchat_id;
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->bindTester($data);
        if ($result->errcode) {
            return AjaxWXReturn($result->errcode, [], $this->errmsg);
        } else {
            return ['code' => 1, 'message' => '绑定成功'];
        }
    }

    /**
     * 解绑小程序体验者
     */
    public function unBindTester()
    {
        $user_str = request()->post('user_str');
        if (empty($user_str)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $data['userstr'] = $user_str;
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->unBindTester($data);

        if ($result->errcode) {
            return ['code' => -1, 'message' => $result->errmsg];
        } else {
            return ['code' => 1, 'message' => '解绑成功'];
        }
    }

    /**
     * 获取小程序可选类目
     * @return array
     */
    public function getCategory()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->getMpCategory();
        if ($result->errcode) {
            return ['code' => -1, 'message' => $result->errmsg];
        } else {
            return ['code' => 1, 'message' => '获取成功', 'data' => $result->category_list];
        }
    }

    /**
     * 提交审核
     */
    public function submit()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $data['item_list'][0]['address'] = 'pages/index/index';
        $data['item_list'][0]['tag'] = '首页';
        $data['item_list'][0]['title'] = '首页';
        $data['item_list'][0]['first_id'] = 287;
        $data['item_list'][0]['first_class'] = '工具';
        $data['item_list'][0]['second_id'] = 620;
        $data['item_list'][0]['second_class'] = '企业管理';
        $result = $this->wchat_open->submitAudit($this->authorizer_access_token, $data);
        if ($result->errcode) {
            return ['code' => -1, 'message' => $result->errmsg];
        } else {
            $submit_history['auth_id'] = $this->auth_id;
            $submit_history['submit_time'] = time();
            $submit_history['submit_uid'] = $this->uid;
            $submit_history['status'] = 2;//审核中
            $submit_history['shop_id'] = $this->instance_id;
            $submit_history['website_id'] = $this->website_id;
            $submit_history['audit_id'] = $result->auditid;
            $this->mini_program_service->addSubmit($submit_history);
            return ['code' => 1, 'message' => '提交成功'];
        }
    }

    public function submitList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;

        $list = $this->mini_program_service->submitList($page_index, $page_size, $condition, 'submit_time DESC');
        return $list;
    }

    /**
     * 小程序支付信息
     */
    public function saveMpPay()
    {
        $config_service = new configServer();
        $post_data = request()->post();
        $data['value'] = json_encode($post_data, JSON_UNESCAPED_UNICODE);
        $data['desc'] = '小程序支付信息';
        $data['is_use'] = 1;
        $data['key'] = 'MPPAY';
        $data['website_id'] = $this->website_id;
        $data['instance_id'] = $this->instance_id;
        $result = $config_service->setConfigOne($data);
        if ($result) {
            return ['code' => 1, 'message' => '保存成功'];
        } else {
            return ['code' => -1, 'message' => '保存失败'];
        }
    }

    /**
     * 启用消息模板
     */
    public function addMessageTemplateRelation()
    {
        $template_id = request()->post('template_id');
        // 如果存在就不去调用微信接口获取
        $wx_template_list = $this->getTemplateIdList();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'template_id' => $template_id
        ];
        $result = $this->mini_program_service->relationTemplateId($condition);
        if ($result) {
            $mp_template_id = $result['mp_template_id'] ?: '';
            if ($wx_template_list && in_array($mp_template_id, $wx_template_list)) {
                $up_data = [
                    'status' => 1
                ];
                $this->mini_program_service->changeTemplateRelationState($up_data, $condition);
                return ['code' => 1, 'message' => '启用成功', 'data' => ['mp_template_id' => $mp_template_id]];
            }
        }

        $template_detail = $this->mini_program_service->mpTemplateDetail(['template_id' => $template_id]);

        if (empty($template_detail)) {
            return ['code' => -1, 'message' => '模板数据为空'];
        }
        $data['id'] = $template_detail['template_code'];
        $data['keyword_id_list'] = $template_detail['key_id'] ? explode(',', $template_detail['key_id']) : [];
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->addMessageTemplate($data);

        if ($result->errcode) {
            return ['code' => -1, 'message' => '启用失败,' . $result->errmsg];
        } else {
            // 添加启用关系
            $relation_data['shop_id'] = $this->instance_id;
            $relation_data['website_id'] = $this->website_id;
            $relation_data['mp_template_id'] = $result->template_id;
            $relation_data['template_id'] = $template_id;
            $relation_data['status'] = 1;
            $this->mini_program_service->addTemplateRelation($relation_data);
            return ['code' => 1, 'message' => '启用成功', 'data' => ['mp_template_id' => $result->template_id]];
        }
    }

    /**
     * 取消模板消息
     * @return array
     */
    public function deleteMessageTemplateRelation()
    {
        $mp_template_id = request()->post('mp_template_id');
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'mp_template_id' => $mp_template_id
        ];
        $result = $this->mini_program_service->relationTemplateId($condition);
        if ($result) {
            $up_data = [
                'status' => 0
            ];
            $this->mini_program_service->changeTemplateRelationState($up_data, $condition);
            return ['code' => 1, 'message' => '取消成功'];
        }

        $data['template_id'] = $mp_template_id;
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->deleteMessageTemplate($data);
        if ($result->errcode) {
            return ['code' => -1, 'message' => '取消失败,' . $result->errmsg];
        } else {
            $condition['mp_template_id'] = $mp_template_id;
            $this->mini_program_service->deleteTemplateRelation($condition);
            return ['code' => 1, 'message' => '取消成功'];
        }
    }

    public function downSunCode()
    {
        $auth_id = input('auth_id');
        $wxAuthRes = $this->weixin_auth_model->getInfo(['auth_id' => $auth_id]);
        $filename = $wxAuthRes['sun_code_url'];

        header("Content-Type: application/force-download");
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        $img = file_get_contents($filename);
        echo $img;
    }

    /**
     * 获取提交最新状态(ajax调用)
     * @return mixed
     */
    public function getPublicStatus()
    {

        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $condition['submit_uid'] = $this->uid;
        $status = $this->mini_program_service->getLastSumitStatus($condition);

        return $status;
    }

    /**
     *  调试获取最新审核状态
     */
    public function lastStatus()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $res = $this->wchat_open->getLasAudistatus();
        $res = is_object($res) ? objToArr($res) : $res;
        $status = $res['status'];
        if($res['errcode'] == 0){
            switch ($res['status']) {
                case 0:
                    $status = '审核成功';
                    break;
                case 1:
                    $status = '审核被拒绝';
                    break;
                case 2:
                    $status = '审核中';
                    break;
                case 3:
                    $status = '已撤回';
                    break;
            }
        }
        $res['status'] = $status;
        p($res);
    }

    /**
     * 调试获指定版本审核状态
     */
    public function lastStatusById()
    {
        $auditid = \request()->get('id');
        $data = [
            'auditid' => $auditid
        ];
        $this->wchat_open = new WchatOpen($this->website_id);
        $res = $this->wchat_open->getAuditStatus($data);
        p($res, '测试：获取'. $auditid.'最新审核状态=》');
    }

    /***
     * 获取addons数据
     */
    public function getMpSetting(){

        $addonsConfigSer = new AddonsConfig();
        $addons_info = $addonsConfigSer->getAddonsConfig(parent::$addons_name, $this->website_id);
        $addons_data = $addons_info['value'] ?: [];
        $addons_data['is_use'] = $addons_info['is_use'] ?: 0;
        return $addons_data;
    }

    /**
     * 获取小程序基本信息（名称、头像、认证、类目、小程序码）
     */
    public function getNewMpBaseInfo()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $this->wchat_open->deleteRedis();
        $auth_base_info = $this->wchat_open->get_authorizer_info($this->app_id);
        $authorizer_info = $auth_base_info->authorizer_info;
        // 获取类目
        // 获取类目（默认第一个）
        $category_list = $this->mini_program_service->getMpCategoryList(true);
        if (isset($category_list['code'])&&$category_list['code']<0){
            return $category_list;
        }
        $auth_data = [];
        if ($authorizer_info) {
            $auth_data['nick_name'] = $authorizer_info->nick_name;
            $auth_data['head_img'] = $authorizer_info->head_img;
            $auth_data['category'] = $category_list;
            $auth_data['real_name_status'] = $authorizer_info->verify_type_info->id;
            $auth_data['new_auth_state'] = 0;

            $result = $this->weixin_auth_model->save($auth_data, ['auth_id' => $this->auth_id]);
        }
        if ($result) {
            // 太阳码
            $mp_config = Config::get('mp_route');
            $params = [
                "path" => $mp_config[0]
            ];
            $sun_code_url = $this->createSunCodeUrl($this->authorizer_access_token, $params, 1);
            $auth_data['sun_code_url'] = $sun_code_url;
        }

        return json(['code' => 1, 'message' => '更新基本信息成功！', 'data' => $auth_data]);
    }

    /**
     * 设置小程序用户隐私保护指引
     */
    public function postSetPrivacySetting()
    {
//        $this->tempToRelease();
//        die;

        $this->wchat_open = new WchatOpen($this->website_id);
        $this->wchat_open->deleteRedis();

        $setting_list = [
            ["privacy_key"=> "UserInfo", "privacy_text"=> "获取用户信息（微信昵称、头像）"],
            ["privacy_key"=> "Location", "privacy_text"=> "获取位置信息"],
            ["privacy_key"=> "Address", "privacy_text"=> "获取地址"],
            ["privacy_key"=> "Record", "privacy_text"=> "打开麦克风"],
            ["privacy_key"=> "Album", "privacy_text"=> "获取选中的照片或视频信息"],
            ["privacy_key"=> "Camera", "privacy_text"=> "打开摄像头"],
            ["privacy_key"=> "PhoneNumber", "privacy_text"=> "获取手机号码"],
            ["privacy_key"=> "AlbumWriteOnly", "privacy_text"=> "获取相册（仅写入）权限"]
        ];

        $params = [
            "privacy_ver" => 2,
            "setting_list"=> $setting_list,
            "owner_setting"=> [
                "contact_email"=> "meixing2021@sina.com",
                "notice_method"=>"通过弹窗",
                "store_expire_timestamp"=> "30天",
            ]
        ];
        $res = $this->wchat_open->setPrivacySetting($this->authorizer_access_token, $params, 1);

        return json(['code' => 1, 'message' => '设置小程序用户隐私保护指引！']);
    }

    /***
     * 是否开启小程序商城，类目是否添加
     * @return  code
     */
    public function isUseAndHasCategory()
    {
        $is_shop_open = $this->mini_program_service->getMiniProgramUseStatus();
        $is_has_category = $this->mini_program_service->isExistCategory();

        $data = [
            'is_shop_open' => $is_shop_open ?: 0,
            'is_has_category' => $is_has_category ?: 0
        ];
        return json(['code' => -1, 'data' => $data]);
    }

    /**
     * 获取帐号下已存在的消息模板ID列表
     * @param string $authorizer_access_token [小程序access_token]
     * @param int $offset [用于分页，表示从offset开始]
     * @param int $count [用于分页，表示拉取count条记录。最大为 20]
     * @return array
     */
    public function getTemplateIdList()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->getTemplateListOfSub();
        if ($result->errcode != 0) {
            return AjaxWXReturn($result->errcode);
        }

        $template_id_list = [];
        foreach ($result->data as $key => $v) {
            $template_id_list[$key] = $v->priTmplId;
        }
        return $template_id_list;
    }

    /**
     * 小程序开发者配置
     */
    public function getMiniProgramAppId()
    {
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $mini_program_info = $this->weixin_auth_model->getInfo($condition, 'authorizer_appid');
        if ($mini_program_info['authorizer_appid']) {
            return ['code' =>1, 'message' =>  $mini_program_info['authorizer_appid']];
        }

        return ['code' => -1];
    }

    /**
     * 保存小程序appSecret
     */
    public function saveMpAppSecret()
    {
        $app_secret = request()->post('app_secret');
        if ($app_secret) {
            $condition['shop_id'] = $this->instance_id;
            $condition['website_id'] = $this->website_id;
            $res = $this->weixin_auth_model->save(['authorizer_secret' => $app_secret], $condition);

            if ($res) {
                return ['code' => 1, 'message' => '授权成功'];
            }
        }
        return ['code' => -1, 'message' => '授权成功'];
    }

    public function payConfigMir() {
        $config = new configServer();
        $list['pay_list'] = $config->getPayConfigMir($this->instance_id);
        $list['b_set'] = $config->getConfig(0, 'BPAYMP', $this->website_id);
        $list['d_set'] = $config->getConfig(0, 'DPAYMP', $this->website_id);
        $list['wx_set'] = $config->getConfig(0, 'MPPAY', $this->website_id);
	$list['gp_set'] = $config->getConfig(0, 'GPPAY', $this->website_id);
        return $list;
    }

    /**
     * 余额支付
     */
    public function payBConfigMir()
    {
        $web_config = new configServer();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setBpayConfigMir($this->instance_id,$is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 美丽分支付
     */
    public function payPConfigMir()
    {
        $web_config = new configServer();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setPpayConfigMir($this->instance_id,$is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 到货付款
     */
    public function payDConfigMir()
    {
        $web_config = new configServer();
        if (request()->isAjax()) {
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setDpayConfigMir($this->instance_id,$is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 微信配置
     */
    public function payWxConfigMir()
    {
        $web_config = new configServer();
        if (request()->isAjax()) {
            // 微信支付
            $appkey     = str_replace(' ', '', request()->post('appkey', ''));
            $MCHID      = str_replace(' ', '', request()->post('MCHID', ''));
            $paySignKey = str_replace(' ', '', request()->post('paySignKey', ''));
            $cert       = request()->post('cert', '');
            $certkey    = request()->post('certkey', '');
            $is_use     = request()->post('is_use', 0);
            $wx_tw      = request()->post('wx_tw', 0);
            // 获取数据
//            $retval = $web_config->setWpayConfigMir($this->instance_id, $appkey,$MCHID,$MCH_KEY,$is_use);
            $retval = $web_config->setWpayConfigMir($this->instance_id, $appkey, $MCHID, $paySignKey, $is_use, $certkey, $cert,$wx_tw);
            return AjaxReturn($retval);
        }
    }

	/**
     * GlobePay
     */
    public function payGpConfigMir()
    {
        $web_config = new configServer();
        if (request()->isAjax()) {
            // GlobePay
            $appid = str_replace(' ', '', request()->post('appid', $this->app_id));
            $partner_code = str_replace(' ', '', request()->post('partner_code', ''));
            $credential_code = str_replace(' ', '', request()->post('credential_code', ''));
			$currency = str_replace(' ', '', request()->post('currency', ''));
            $is_use = request()->post('is_use', 0);
            // 获取数据
            $retval = $web_config->setGpayConfigMir($this->instance_id, $appid,$partner_code,$credential_code,$currency,$is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 通联支付配置
     */
    public function payTLConfigMir() {
        $web_config = new configServer();
        if (request()->isAjax()) {
            $tl_key = str_replace(' ', '', request()->post('tl_key', ''));
            $tl_appid = str_replace(' ', '', request()->post('tl_appid', ''));
            $tl_cusid = str_replace(' ', '', request()->post('tl_cusid', ''));
            $tl_id = str_replace(' ', '', request()->post('tl_id', ''));
            $tl_username = str_replace(' ', '', request()->post('tl_username', ''));
            $tl_password = str_replace(' ', '', request()->post('tl_password', ''));
            $tl_public = str_replace(' ', '', request()->post('tl_public', ''));
            $tl_private = str_replace(' ', '', request()->post('tl_private', ''));
            $is_use = request()->post('is_use', 0);
            $tl_tw = request()->post('tl_tw', 0);
            // 获取数据
            $retval = $web_config->setTlConfigMir($this->instance_id,$tl_id, $tl_cusid, $tl_appid, $tl_key,$tl_username,$tl_password,$tl_public, $tl_private,$tl_tw,$is_use);
            return AjaxReturn($retval);
        }
    }

    /**
     * 临时发布小程序:用户定时没跑，但是微信审核过了的情况下
     */
    public function tempToRelease()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        // 查询最新一次提交审核状态
        $lastRes = $this->wchat_open->getLasAudistatus();
        $lastId = 0;
        if ($lastRes->errcode == 0) {
            $lastId = $lastRes->auditid;
        }

        $res = $this->wchat_open->release();
        if ($res->errcode == 0) {
            $submit_data['review_message'] = '发布成功!';
            $submit_data['status'] = 4;
        } else {
            $submit_data['review_message'] = '发布失败：'.$res->errmsg;
            $submit_data['status'] = 3;
        }
        $this->mini_program_service->saveSubmit($submit_data, ['audit_id' => $lastId]);

        echo '最后的auditid：'.$lastId ."\r\n";
        echo json_encode($res);
    }

    /**
     * 版本叠加
     * @param string $version 小程序版本
     * @return string
     */
    public function autoAddVersionForMiniprogram($version = '')
    {
        return $this->mini_program_service->autoAddVersionForMiniprogram($version);
    }

    /**
     * 小程序审核撤回
     * 【单个帐号每天审核撤回次数最多不超过1次，一个月不超过10次。】
     */
    public function recallcommitMp()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $res = $this->wchat_open->recallcommitMp();
        if ($res->errcode) {
            return AjaxWXReturn($res->errcode, [], $res->errmsg);
        }
        // 修改状态
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        $new_submit_record = $this->mini_program_service->getNewMpSubmitRecord($condition);
        $new_data = [
            'status' => 1,
            'review_message' => '撤回发布！撤回时间：'.date("Y-m-d H:i:s" ,time())
        ];
        $this->mini_program_service->saveSubmit($new_data, ['id' => $new_submit_record['id']]);

        return AjaxWXReturn(SUCCESS);
    }

    /**
     * 是否填写AppSecret
     */
    public function isHasAppSecret()
    {
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        $secret = $this->mini_program_service->getMpAuthorizerInfo($condition, 'authorizer_secret');

        if ($secret['authorizer_secret']) {
            return json(['code' => 1, 'data' => $secret['authorizer_secret']]);
        } else {
            return json(['code' => -1, 'data' => '未填写AppSecret!']);
        }
    }

    /**
     * 添加域名到小程序服务器域名
     * $add_url  需要添加到小程序服务器的域名
     */
    public function postDomainToMp($type = 2)
    {
        //$type 1直播 2非直播
        $url = request()->post('add_url');
        $domain_data = [];
        if ($url) {
            array_push($domain_data, $url);
        }
        //第三方域名
        $third_domain_name = getIndependentDomain($this->website_id);
        if (empty($third_domain_name)) {//不含 https
            return ['errcode' => -1, 'message' => '独立域名不存在！'];
        }
        array_push($domain_data, $third_domain_name);
        // 云服务器域名
        $config = new configServer();
        $upload_type = $config->getConfigMaster(0, 'UPLOAD_TYPE', 0, 1);
        if ($upload_type == 2) {
            $configSer = new configServer();
            $mp_info = $configSer->getConfigMaster(0, 'ALIOSS_CONFIG', 0, 1);
			$mp_info = json_decode($mp_info,true);
            if ($mp_info['AliossUrl']) {
                array_push($domain_data, $mp_info['AliossUrl']);
            }

        }
        //客服域名
        if (getAddons('qlkefu', $this->website_id)) {
            $kf = new QlkefuService();
            $kfResult = $kf->qlkefuConfig($this->website_id, $this->instance_id);
            if ($kfResult['is_use'] == 1) {
                array_push($domain_data, $kfResult['ql_domain']);
            }
        }
        //设置服务域名
        $result = $this->mini_program_service->modifyDomain($domain_data, 'add',$type);
        if (!$result) {
            //设置业务域名
            $webResult = $this->mini_program_service->postWebDominaToMp();
            if($webResult){
                return $webResult;
            }
            return ['errcode' => 0, 'message' => '刷新成功'];
        }else{
            return $result;
        }
    }

    /**
     * 启用消息模板
     */
    public function addMessageTemplateRelationKey()
    {
        $this->mini_program_service->delCacheMpTemplateList();
        $template_data = json_decode($_POST['data'],true);
        $template_id = $template_data['template_id'];
        $state = $template_data['state'];
        $template_no = $template_data['template_no'];

        $key_list = [];
        $index = 1;
        foreach($template_data['key'] as $val) {
            $val['key_id'] = $index;
            $val['value'] = trim($val['value']);
            $val['content'] = trim($val['content']);
            $key_list[$index] = $val;
            $index++;
        }
        $relation_data = [
            'template_id' => $template_id,
            'mp_template_id' => $template_no,
            'status' => $state,
            'key_list' => json_encode($key_list)
        ];
        $relation_condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'template_id' => $template_id
        ];

        Db::startTrans();
        try {
            $relationRes = $this->mini_program_service->relationTemplateId($relation_condition);
            if ($relationRes) {
                $this->mini_program_service->changeTemplateRelationState($relation_data, $relation_condition);
            } else {
                $relation_data['website_id'] = $this->website_id;
                $relation_data['shop_id'] = $this->instance_id;
                $this->mini_program_service->addTemplateRelation($relation_data);
            }
            Db::commit();

            $this->mini_program_service->putMpTemplateIdOfRedis($relation_data);
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            Db::rollback();
            return ['code' => -1, 'message' => '保存失败:'.$e->getMessage()];
        }
    }

    /**
     * 添加小程序订阅消息 - 商城变量
     */
    public function postMpSubKeys()
    {
        $template_id = request()->post('category');
        $name = request()->post('name');

        $condition = [];
         if ($name) {
             $condition['name'] = ['LIKE', '%' . $name . '%'];
         }
         if ($template_id) {
             $condition['template_id'] = $template_id;
         }

        $data = [
            'template_id' => $template_id,
            'name' => $name,
        ];
        return $this->mini_program_service->postMpSubKeys($condition, $data);
    }

    /**
     * 手动发布小程序
     */
    public function releaseMp()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $release_result = $this->wchat_open->release();
        p($release_result);
    }

    /**
     * 手动查询发布审核状态并发布
     */
    public function updateCommitStatus()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->getLasAudistatus();
        $submit_data = [];
        $condition = [
            'audit_id' => $result->auditid,
            'website_id' => $this->website_id
        ];
        Db::startTrans();
        try{
            if ($result->errcode == 0) {
                if ($result->status == 0) {// 通过审核
                    $submit_data['status'] = 0;
                    $submit_data['review_message'] = '审核成功';
                    $this->mini_program_service->saveSubmit($submit_data, $condition);

                    // 发布小程序
                    $release_result = $this->wchat_open->release();
                    if ($release_result->errcode == 0 || $release_result->errcode == 85052) {
                        $submit_data['review_message'] = '发布成功!';
                        $submit_data['status'] = 4;
                    } else {
                        $submit_data['review_message'] = '发布失败：'.$release_result->errmsg;
                        $submit_data['status'] = 3;
                    }
                } elseif ($result->status == 2){
                    $submit_data['status'] = $result->status;
                    $submit_data['review_message'] = '审核中';
                } else {
                    $submit_data['status'] = 1;
                    $submit_data['review_message'] = $result->reason;
                }
            } else {//审核失败
                $submit_data = [
                    'status' => 1,
                    'review_message' => $result->reason
                ];
            }
            $res = $this->mini_program_service->saveSubmit($submit_data, $condition);
            p($res);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
            p($e->getMessage());
        }
    }

    /**
     * 查询服务商的当月提审限额（quota）和加急次数
     */
    public function getQuata()
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->queryQuota();
        p($result);
    }

    /**
     * 查询直播|非直播对应template_id
     * @param $type int [1直播 2非直播]
     * @return array
     */
    public function getTemplateByLiveType($type)
    {
        $this->wchat_open = new WchatOpen($this->website_id);
        $result = $this->wchat_open->templateList();
        if ($result->errcode){
            return AjaxWXReturn($result->errcode,[], $result->errmsg);
        }
        $resultArr = objToArr($result);
        $templateList = arrSortByValue($resultArr['template_list'], 'create_time');
        $new_template_id = $templateList[0]['template_id'];
        $new_template_desc = $templateList[0]['user_desc'];
        $search_text = $type == 1 ?  'hasLive' : 'noLive';
        if (!strpos($new_template_desc, $search_text)){
            return $new_template_id;
        }
        if ($templateList){
            foreach ($templateList as $template) {
                if (strpos($template['user_desc'], $search_text)) {
                    $new_template_id = $template['template_id'];
                    break;
                }
            }
        }
        return $new_template_id;
    }

    /**
     * 小程序提交的ext_json文件信息
     * @param int $type
     * @return mixed
     */
    public function getCommitMpExtData()
    {
        $ext_data['extEnable'] = true;
        $ext_data['extAppid'] = $this->app_id;
        # 获取tabBar相关参数
        $customSer = new CustomtemplateSer();
        $result = $customSer->getTarBarTemplateForMp();
        if (!$result && !$result['template_data']){
            return [];
        }
        $templateData = json_decode($result['template_data'], true);
        $list = [];
        $i=0;
        foreach ($templateData['data'] as $key => $v) {
            if ($i == 0) {
                $list[] = [
                    'pagePath' => 'pages/mall/index',
                    'text' => $v['text']
                ];
            }else {
                // 处理old链接
                if (substr($v['path'],0,1) == '/'){
                    $v['path'] = substr($v['path'],1);
                }
                $v['path'] = $this->replaceOldLink($v['path']);
                $list[] = [
                    'pagePath' => $v['path'],
                    'text' => $v['text']
                ];
            }
            $i++;
        }
        $ext_data['tabBar']['list'] = $list;
        # 传入ext文件
        $ext_data['ext']['domain'] = getIndependentDomain($this->website_id);//主域名
        $ext_data['ext']['domain_wap'] = getIndependentDomain($this->website_id, true);//独立域名
        $ext_data['ext']['website_id'] = $this->website_id;
        $ext_data['ext']['project_name'] = Config::get('project');
        $ext_data['ext']['auth_key'] = API_KEY;

        if (getAddons('mplive',$this->website_id)){
            $configSer = new AddonsConfigService();
            $config_info = $configSer->getAddonsConfig('mplive',$this->website_id,0);
            if ($config_info && $config_info['is_use'] == 1){
                $ext_data['plugins'] = [
                    'live-player-plugin' => [
                        'version' =>  MPLIVE_VERSION,
                        'provider' => 'wx2b03c6e691cd7370'
                    ]
                ];
            }
        }
        return $ext_data;
    }

    /**
     * 打包下载miniprogram.zip
     * 需要对比文件
     * (1) miniprogram\config.js  [ext.json部分数据覆盖]
     * (2) project.config.json  [appid]
     * (3) miniprogram\utils\requestData.js  [key]
     * (4) miniprogram\app.json [ext.json部分数据覆盖]
     * (5) miniprogram\ext.json  [ext.json数据全部去除]
     */
    public function replaceCodeAndPackageDownload()
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');

        $srcPath = ROOT_PATH.'template/miniprogram/';
        $dstPath = ROOT_PATH.'upload/'.$this->website_id .'/mppackagecopy/';
        $zipPath = ROOT_PATH.'upload/'.$this->website_id .'/mppackagezip/';
        // 文件名
        if (!$this->nick_name) {
            $websiteSer = new WebSiteSer();
            $mall_name = $websiteSer->getWebInfo(['website_id' => $this->website_id], 'mall_name')['mall_name'];
            $mall_name = $mall_name ?: $this->website_id;
        }
        $mall_name = $this->nick_name ?: $mall_name;
        $zipName = 'miniprogram';

        # copy #
        copy_file($srcPath, $dstPath);
        // src mp path
        $mpBasePath = $dstPath.'miniprogram/';
        $project_config_json = $dstPath.'project.config.json';
        $requestData_js = $mpBasePath . 'utils/requestData.js';
        $config_js = $mpBasePath . 'config.js';
        $app_json = $mpBasePath . 'app.json';
        $ext_json = $mpBasePath . 'ext.json';

        if (!file_exists($requestData_js) && !file_exists($project_config_json) && !file_exists($app_json)) {
            unlink($dstPath);
            return;
        }
        // ext_json组装数据
        $type = isExistAddons('mplive', $this->website_id) ? 1 : 2;// 1有直播 2无直播
        $ext_data = $this->getCommitMpExtData();

        // 1、处理config.js
        $config_js_arr = $ext_data['ext'];
        $replace_str = 'module.exports = '.json_encode($config_js_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($config_js, $replace_str);

        // 2、处理project.config.json
        $project_config_json_arr = json_decode(file_get_contents($project_config_json), true);
        $project_config_json_arr['appid'] = $ext_data['extAppid'];
        $project_config_json_arr['projectname'] = urlencode($mall_name);
        file_put_contents($project_config_json, json_encode($project_config_json_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 3、处理utils/requestData.js
         $replace_text = " let key = '". API_KEY . "'";
         replaceTarget($requestData_js, $replace_text, "let key");
         unset($replace_text);

         // 4、处理app.json
         $app_json_arr = json_decode(file_get_contents($app_json), true);
         $ext_merge_arr = [
             'window' => $ext_data['window'],
             'tabBar' => $ext_data['tabBar']
         ];
        $merge_app_json_arr =  array_merge($app_json_arr, $ext_merge_arr);
        if ($type == 1){
            $merge_app_json_arr['plugins'] = [
                 'live-player-plugin' => [
                     'version' =>  MPLIVE_VERSION,
                     'provider' => 'wx2b03c6e691cd7370'
                 ]
             ];
         } else {
            unset($merge_app_json_arr['plugins']);
        }
        file_put_contents($app_json, json_encode($merge_app_json_arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        // 5、删除ext.json文件
        @unlink($ext_json);
        // 6、压缩文件
        files2Zip($dstPath, $zipPath, $zipName);
        // 7、删除不需要文件
        @VslDelDir($dstPath, true);
        @VslDelDir($zipPath, true);
    }

    /*
     * 替换旧的装修链接
     */
    public function replaceOldLink ($old_link)
    {
       $link_arr = [
            'pages/index/index'                 => 'pages/mall/index',
            'pages/category/index'              => 'pages/goods/category',
            'pages/commission/centre/index'     => 'pages/distribute/index',
            'pages/goodlist/index'              => 'pages/goods/list',
            'pages/shopcart/index'              => 'pages/mall/cart',
            'pages/member/index'                => 'pages/member/index',
            'pages/shop/list/index'             => 'pages/shop/list',
            'pages/order/list/index'            => 'pages/order/list',
            'pages/shop/home/index?shopId=0'    => 'packages/shop/home',
        ];

        if (in_array($old_link, array_keys($link_arr))) {
            return $link_arr[$old_link];
        }
        return $old_link;
    }

    public function comToken ()
    {
        $res = cache('component_access_token');
        p($res);
    }
}
