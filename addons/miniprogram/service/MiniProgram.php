<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/9/18 0018
 * Time: 17:39
 */

namespace addons\miniprogram\service;

use addons\credential\controller\Credential;
use addons\miniprogram\model\MpMessageTemplateModel;
use addons\miniprogram\model\MpSubKeysModel;
use addons\miniprogram\model\MpSubmitModel;
use addons\miniprogram\model\MpTemplateRelationModel;
use addons\miniprogram\model\WeixinAuthModel;
use data\extend\WchatOpen;
use data\service\BaseService;
use data\service\User as UserSer;
use data\service\Weixin;
use think\Cache as cache;
use think\Config;
use think\Db;
use data\service\Merchant as MerchantSer;
use data\service\AddonsConfig as AddonsConfigSer;


class MiniProgram extends BaseService
{
    const MP_TEMPLATE_CONFIG = 'mp_template_config';//配置redis缓存
    public $wchat_open;
    public $wx_auth;
    public $template_config;
    protected $error_log_url = 'public/ErrorLog/mp/wchatopen_err.txt';

    function __construct()
    {
        parent::__construct();
        $this->template_config = $this->website_id .'_'.$this->instance_id .'_'.self::MP_TEMPLATE_CONFIG;//eg 2_0_mp_template_config
        $this->wx_auth = new WeixinAuthModel();
        $this->wchat_open = new WchatOpen($this->website_id);
        
    }

    /** 保存微信授权微信公众号小程序的信息
     * @param array $data
     * @param array $condition
     *
     * @return mixed
     */
    public function saveWeixinAuth($data, array $condition = [])
    {
        $retval = $this->wx_auth->save($data, $condition);
        return $retval;
    }

    /**
     * 一个shop_id,website_id组合 只存在一个小程序,当授权新的小程序时先删除旧的小程序
     */
    public function weixinAuthCheck(array $condition)
    {
        $weixin_auth_model = new WeixinAuthModel();
        $return = $weixin_auth_model::all($condition);
        if (count($return) >= 1) {
            // 删除小程序信息
            $weixin_auth_model->destroy($condition);
            // 删除小程序模板消息关联
            MpTemplateRelationModel::destroy($condition);
        }
    }

    /**
     * 小程序详情
     */
    public function miniProgramInfo($condition,$field='*')
    {
        $weixin_auth_model = new WeixinAuthModel();
        $mini_program_info = $weixin_auth_model->getInfo($condition,$field);
        return $mini_program_info;
    }
    
    /**
     * 小程序模板消息列表
     * @param array $condition
     * @return array
     * @throws \think\Exception\DbException
     */
    public function mpTemplateList(array $condition = [])
    {
        $mp_template_model = new MpMessageTemplateModel();
        $list = $mp_template_model::all($condition);
        $return_list = [];
        foreach ($list as $v) {
            switch ($v['html_id']) {
                case 'pay_success':
                    $temp_tem = [];
                    $temp_tem['template_id'] = $v['template_id'];
                    $temp_tem['html_id'] = $v['html_id'];
                    $temp_tem['template_title'] = '付款成功通知';
                    $temp_tem['template_name'] = $v['template_name'];
                    $temp_tem['template_code'] = $v['template_code'];
                    $temp_tem['editor_title'] = '支付成功';

                    $temp_tem['list'][0]['notice_title'] = '付款成功通知';
                    $temp_tem['list'][0]['message'] = '7月1日 12:00';
                    $temp_tem['list'][0]['detail'][0] = '支付金额：145.25元';
                    $temp_tem['list'][0]['detail'][1] = '商品名称：七匹狼正品 牛皮男士钱包 真皮钱…';
                    $temp_tem['list'][0]['detail'][2] = '订单编号：123658635455';
                    $temp_tem['list'][0]['foot'] = '进入小程序查看';
                    $temp_tem['list'][0]['foot2'] = '拒收通知';
                    break;
                case 'order_close':
                    $temp_tem = [];
                    $temp_tem['template_id'] = $v['template_id'];
                    $temp_tem['html_id'] = $v['html_id'];
                    $temp_tem['template_title'] = '订单取消通知';
                    $temp_tem['template_name'] = $v['template_name'];
                    $temp_tem['template_code'] = $v['template_code'];
                    $temp_tem['editor_title'] = '用户取消订单、订单超时';

                    $temp_tem['list'][0]['notice_title'] = '订单取消通知';
                    $temp_tem['list'][0]['message'] = '7月1日 12:00';
                    $temp_tem['list'][0]['detail'][0] = '取消原因：用户自己取消';
                    $temp_tem['list'][0]['detail'][1] = '订单金额：44.20元';
                    $temp_tem['list'][0]['detail'][2] = '商品详情：七匹狼正品 牛皮男士钱包 真皮钱…';
                    $temp_tem['list'][0]['detail'][3] = '收件地址：深圳市宝安区海秀路23号';
                    $temp_tem['list'][0]['detail'][4] = '订单编号：123658635455';
                    $temp_tem['list'][0]['foot'] = '进入小程序查看';
                    $temp_tem['list'][0]['foot2'] = '拒收通知';
                    break;
                case 'balance_change':
                    $temp_tem = [];
                    $temp_tem['template_id'] = $v['template_id'];
                    $temp_tem['html_id'] = $v['html_id'];
                    $temp_tem['template_title'] = '账户余额提醒';
                    $temp_tem['template_name'] = $v['template_name'];
                    $temp_tem['template_code'] = $v['template_code'];
                    $temp_tem['editor_title'] = '充值、提现等';

                    $temp_tem['list'][0]['notice_title'] = '账户余额提醒';
                    $temp_tem['list'][0]['message'] = '7月1日 12:00';
                    $temp_tem['list'][0]['detail'][0] = '温馨提示：您的账户余额发生变动，信息如下';
                    $temp_tem['list'][0]['detail'][1] = '变动类型：流水号2015163443230008';
                    $temp_tem['list'][0]['detail'][2] = '变动原因 ：<span class="text-success">充值成功</span>';
                    $temp_tem['list'][0]['detail'][3] = '变动额度：+200.00元';
                    $temp_tem['list'][0]['detail'][4] = '当前余额：568.00元';
                    $temp_tem['list'][0]['foot'] = '进入小程序查看';
                    $temp_tem['list'][0]['foot2'] = '拒收通知';

                    $temp_tem['list'][1]['notice_title'] = '账户余额提醒';
                    $temp_tem['list'][1]['message'] = '7月1日 13:00';
                    $temp_tem['list'][1]['detail'][0] = '温馨提示：您的账户余额发生变动，信息如下';
                    $temp_tem['list'][1]['detail'][1] = '变动类型：流水号2015163443230008';
                    $temp_tem['list'][1]['detail'][2] = '变动原因 ：<span class="text-success">提现成功</span>';
                    $temp_tem['list'][1]['detail'][3] = '变动额度：-200.00元';
                    $temp_tem['list'][1]['detail'][4] = '当前余额：368.00元';
                    $temp_tem['list'][1]['foot'] = '进入小程序查看';
                    $temp_tem['list'][1]['foot2'] = '拒收通知';

                    $temp_tem['list'][2]['notice_title'] = '账户余额提醒';
                    $temp_tem['list'][2]['message'] = '7月1日 14:00';
                    $temp_tem['list'][2]['detail'][0] = '温馨提示：您的账户余额发生变动，信息如下';
                    $temp_tem['list'][2]['detail'][1] = '变动类型：流水号2015163443230008';
                    $temp_tem['list'][2]['detail'][2] = '变动原因 ：<span class="text-danger">提现失败</span>';
                    $temp_tem['list'][2]['detail'][3] = '变动额度：0元';
                    $temp_tem['list'][2]['detail'][4] = '当前余额：568.00元';
                    $temp_tem['list'][2]['foot'] = '进入小程序查看';
                    $temp_tem['list'][2]['foot2'] = '拒收通知';
                    break;
                case 'refund_info':
                    $temp_tem = [];
                    $temp_tem['template_id'] = $v['template_id'];
                    $temp_tem['html_id'] = $v['html_id'];
                    $temp_tem['template_name'] = $v['template_name'];
                    $temp_tem['template_title'] = '售后服务进度通知';
                    $temp_tem['template_code'] = $v['template_code'];
                    $temp_tem['editor_title'] = '退货、退款';

                    $temp_tem['list'][0]['notice_title'] = '售后通知';
                    $temp_tem['list'][0]['message'] = '7月1日 12:00';
                    $temp_tem['list'][0]['detail'][0] = '售后类型：仅退款';
                    $temp_tem['list'][0]['detail'][1] = '状态：<span class="text-success">退款成功</span>';
                    $temp_tem['list'][0]['detail'][2] = '商品名称：富川脐橙';
                    $temp_tem['list'][0]['detail'][3] = '订单号：32160910125478';
                    $temp_tem['list'][0]['detail'][4] = '售后说明：点击“详情”查看商家收货信息，如有疑问请联系客服。';
                    $temp_tem['list'][0]['foot'] = '进入小程序查看';
                    $temp_tem['list'][0]['foot2'] = '拒收通知';

                    $temp_tem['list'][1]['notice_title'] = '售后通知';
                    $temp_tem['list'][1]['message'] = '7月1日 13:00';
                    $temp_tem['list'][1]['detail'][0] = '售后类型：退款';
                    $temp_tem['list'][1]['detail'][1] = '状态：<span class="text-danger">退款失败</span>';
                    $temp_tem['list'][1]['detail'][2] = '商品名称：富川脐橙';
                    $temp_tem['list'][1]['detail'][3] = '订单号：32160910125478';
                    $temp_tem['list'][1]['detail'][4] = '售后说明：失败原因：XXXXXXXXXXXXXXXXXXXX，如有疑问请联系客服。';
                    $temp_tem['list'][1]['foot'] = '进入小程序查看';
                    $temp_tem['list'][1]['foot2'] = '拒收通知';

                    $temp_tem['list'][2]['notice_title'] = '售后通知';
                    $temp_tem['list'][2]['message'] = '7月1日 14:00';
                    $temp_tem['list'][2]['detail'][0] = '售后类型：退货退款';
                    $temp_tem['list'][2]['detail'][1] = '状态：<span class="text-success">退货成功</span>';
                    $temp_tem['list'][2]['detail'][2] = '商品名称：富川脐橙';
                    $temp_tem['list'][2]['detail'][3] = '订单号：32160910125478';
                    $temp_tem['list'][2]['detail'][4] = '售后说明：点击“详情”查看商家收货信息，如有疑问请联系客服。';
                    $temp_tem['list'][2]['foot'] = '进入小程序查看';
                    $temp_tem['list'][2]['foot2'] = '拒收通知';

                    $temp_tem['list'][3]['notice_title'] = '售后通知';
                    $temp_tem['list'][3]['message'] = '7月1日 13:00';
                    $temp_tem['list'][3]['detail'][0] = '售后类型：退货退款';
                    $temp_tem['list'][3]['detail'][1] = '状态：<span class="text-danger">退货失败</span>';
                    $temp_tem['list'][3]['detail'][2] = '商品名称：富川脐橙';
                    $temp_tem['list'][3]['detail'][3] = '订单号：32160910125478';
                    $temp_tem['list'][3]['detail'][4] = '售后说明：失败原因：XXXXXXXXXXXXXXXXXXXX，如有疑问请联系客服。';
                    $temp_tem['list'][3]['foot'] = '进入小程序查看';
                    $temp_tem['list'][3]['foot2'] = '拒收通知';
                    break;
                default:
                    $temp_tem = [];
            }
            $return_list[] = $temp_tem;
        }
        return $return_list;
    }

    /**
     * 小程序模板消息详情
     * @param array $condition
     * @return MpMessageTemplateModel
     * @throws \think\Exception\DbException
     */
    public function mpTemplateDetail(array $condition)
    {
        $mp_template_model = new MpMessageTemplateModel();
        return $mp_template_model::get($condition);
    }

    /**
     * 获取商家启用小程序消息模板的列表
     * @param array $condition
     * @return array
     * @throws \think\Exception\DbException
     */
    public function relation(array $condition)
    {
        $relation_lists = $this->getMpRelationList($condition);//关联数据
        //查询 message的模板id
        $message_ids = $this->getMpMessageTemplateColumn();//模板ids
        $return_list = [];//默认数据
        if (count($relation_lists) == count($message_ids)) {
            foreach($relation_lists as $list) {
                $return_list[$list['template_id']] = [
                    'template_id' => $list['template_id'],
                    'mp_template_id' => $list['mp_template_id'],
                    'status' => $list['status'],
                    'key' => json_decode($list['key_list'], true),//解析key_list
                ];
            }
            return $return_list;
        }

        $new_ids = array_flip($message_ids);
        foreach ($relation_lists as $key => $list) {
            if (in_array($list['template_id'], $message_ids)) {
                unset($new_ids[$list['template_id']]);//删除ids中的id
                $return_list[$list['template_id']] = [
                    'template_id' => $list['template_id'],
                    'mp_template_id' => $list['mp_template_id'],
                    'status' => $list['status'],
                    'key' => json_decode($list['key_list'], true),//解析key_list
                ];
            }
        }
        // message_ids未被修改的
        foreach (array_keys($new_ids) as $un_key) {
            $return_list[$un_key] = [
                'template_id' => $un_key,
                'mp_template_id' => '',
                'status' => 0,
                'key' => [
                    'new_default' => [
                        'key_id' => 'new_default',
                        'is_default' => 1
                    ],
                ]
            ];
        }
        return $return_list;
    }
    /**
     * 获取商家启用小程序消息模板id
     * @param array $condition
     * @return array
     * @throws \think\Exception\DbException
     */
    public function relationTemplateId(array $condition)
    {
        $mp_template_relation_model = new MpTemplateRelationModel();
        $relation = $mp_template_relation_model->getFirstData($condition, 'relation_id DESC');
        return $relation;
    }

    /**
     * 用户启用模板消息
     * @param array $condition
     * @return array
     * @throws \think\Exception\DbException
     */
    public function userMpTemplateList(array $condition)
    {
        $mp_relation_model = new MpTemplateRelationModel();
        $list = $mp_relation_model::all($condition);
        $return_list = [];
        foreach ($list as $v) {
            $return_list[$v['template_id']] = $v;
        }
        return $return_list;
    }

    /**
     * 商家启用消息模板
     * @param array $data
     *
     * @return mixed
     */
    public function addTemplateRelation(array $data)
    {
        $mp_relation_model = new MpTemplateRelationModel();
        return $mp_relation_model->save($data);
    }

    /**
     * 新关闭消息模板
     * @param array $data
     * @param array $condition
     * @return false|int
     */
    public function changeTemplateRelationState(array $data, array $condition)
    {
        $mp_relation_model = new MpTemplateRelationModel();
        return $mp_relation_model->save($data, $condition);
    }

    /**
     * 关闭启用消息模板
     * @param array $condition
     * @return int
     */
    public function deleteTemplateRelation(array $condition)
    {
        $mp_relation_model = new MpTemplateRelationModel();
        return $mp_relation_model->destroy($condition);
    }

    /**
     * 写入提交审核历史
     * @param array $data
     */
    public function addSubmit(array $data)
    {
        $mp_submit_model = new MpSubmitModel();
        $mp_submit_model->save($data);
    }
    
    /**
     * 保存小程序提交审核数据
     */
    public function saveSubmit($data,$conditon)
    {
        $mp_submit_model = new MpSubmitModel();
        $mp_submit_model->save($data, $conditon);
    }

    /**
     * 小程序提交审核列表
     */
    public function submitList($page_index = 1, $page_size = 0, array $condition = [], $order = '', $field = '*')
    {
        $weixin_auth_model = new MpSubmitModel();
        $lists = $weixin_auth_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        if(!$lists){return [];}
        $userSer = new UserSer();
        foreach ($lists['data'] as $k=> $list) {
            $str_new = preg_replace('/<br\\s*?\/??>/i', "\r", stripslashes($list['review_message']));
            $lists['data'][$k]['review_message'] = trim($str_new);
            $lists['data'][$k]['submit_date'] = date('Y-m-d H:i:s', $list['submit_time']);
            $user_info = $userSer->getUserInfo($list['submit_uid'], 'user_tel, user_name');
            $lists['data'][$k]['user'] = $user_info['user_name'] ?: $user_info['user_tel'];
        }
        return $lists;
    }

    /**
     * 获取最新状态
     */
    public function getLastSumitStatus($condition = '')
    {
        $status = Db::table('sys_mp_submit')->field('status')
            ->where($condition)
            ->order('submit_time desc')
            ->limit(1)
            ->select();

        return $status[0]['status'];
    }

    /**
     * 获取最新版本信息
     * @param array $condition
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getNewMpSubmitRecord($condition = [])
    {
        $submit = new MpSubmitModel();
        $new_submit_record = $submit->getFirstData($condition, 'submit_time desc');

        return $new_submit_record;
    }

    /***
     * 修改版本信息
     */
    public function setNewMpSubmitRecord($condition, $website_id)
    {
        $submit = new MpSubmitModel();
        $new_submit_record = $this->getNewMpSubmitRecord($website_id);
        $result = $submit->save($condition, ['id' => $new_submit_record->id]);

        return $result;
    }

    /***
     * 获取该实例状态
     */
    public function getMiniProgramUseStatus()
    {
        $addonsConfigSer = new AddonsConfigSer();
        $result = $addonsConfigSer->getAddonsConfig('miniprogram', $this->website_id);
        return $result['is_use'];
    }

    /**
     * 是否类目存在
     */
    public function isExistCategory()
    {
        // 先检查库
        $database_category = $this->wx_auth->getInfo(['website_id' => $this->website_id, 'shop_id' => $this->instance_id], 'category,authorizer_access_token, auth_id');
        if (!empty($database_category)) {
            return TRUE;
        }

        // 再看接口是否正确
        $result = $this->wchat_open->getMpCategory();
        if ($result->errcode == 0) {
            $wx_category = $result->category_list;
        }
        // 如果数据库没有，公众平台有，就重新获取写入
        if (!empty($wx_category)) {
            $category = $this->getMpCategoryList();
            $this->wx_auth->save(['category' => $category], ['auth_id' => $database_category['auth_id']]);
            return TRUE;
        }
        return FALSE;
    }

    /**
     * 授权时，获取类目
     * @return string|json_encode
     */
    public function getMpCategoryList($return_err = false)
    {
        $result = $this->wchat_open->getMpCategory();

        if ($result->errcode == 0) {
            $category_list = $result->category_list;
            // 处理取到二级分类,去重
            $return_category = [];
            foreach ($category_list as $key => $v) {
                $temp = objToArr($v);
                unset($temp['third_class'], $temp['third_id']);// 去除第三层
                $return_category[$key] = $temp;
            }
            $filter_category = array_unique($return_category, SORT_REGULAR);
            return json_encode($filter_category, JSON_UNESCAPED_UNICODE);
        }elseif($return_err){
            return AjaxWXReturn($result->errcode, [], $result->errmsg);
        }
    }

    /**
     * 发布时候提交类目：默认第一个，否则为用户选择保存的
     * return json
     */
    public function getMpCategoryForCommit($second_id = '')
    {
        $result = $this->getMpCategoryList();
        $category_list = json_decode($result, true);

        // 先从数据库查显示
        if (empty($second_id)) {
            // 先从表中取出
            $category_result = $this->wx_auth->getInfo(['website_id' => $this->website_id, 'shop_id' => $this->instance_id], 'category')['category'];

            if(!empty($category_result)) {
                $categoryArr = json_decode($category_result, true);
                // 因为之前这里数据库存的是重新抽出来的用户选择的单一分类sting，现在是想把微信获取的数组（包含多个）存到数据库，但是以前客户数据库已经存在单一分类sting，所以这样去做判断区分
                if ($categoryArr[0] && $categoryArr[0]['second_id']) {//说明是从微信获取新的
                    return $category_result;
                }
            }
        }

        if (!empty($second_id)) {
            // 根据id筛选对应类目,并置为第一个（用于列表显示）
            $tempArr = [];
            foreach ($category_list as $key => $v) {

                if ($v['second_id'] == $second_id) {
                    $tempArr = $category_list[$key];
                    unset($category_list[$key]);
                }
            }
            array_unshift($category_list, $tempArr);
        }
        if (is_object($category_list)) {
            $category_list = objToArr($category_list);
        }

        return json_encode($category_list, JSON_UNESCAPED_UNICODE);
    }

    /**
     * 开放小程序商城
     */
    public function openMpShop()
    {
        $AddonsConfig = new AddonsConfigSer();
        $mini_program_info = $AddonsConfig->getAddonsConfig('miniprogram', $this->website_id);
        if (!empty($mini_program_info)) {
            $AddonsConfig->updateAddonsConfig('', '小程序设置', 1, 'miniprogram');
        } else {
            $AddonsConfig->addAddonsConfig('', '小程序设置', 1, 'miniprogram');
        }
    }

    /**
     * （针对源码）通过appid查询到授权小程序websiteId
     * @param $string [string] 字符，可能是含有'/'开头的appid
     * @return $website_id [string]
     */
    public function getWebsiteIdByAppId($string)
    {
        if (!is_string($string)) {return false;}
        if (substr($string, 0, 1) == '/') {
            $appId = substr($string, 1);
            $weixin_info = $this->wx_auth->getInfo(['authorizer_appid' => $appId], 'website_id');
        }else{
            $pattern = "/^[a-z,A-Z]|\d$/";
            if (preg_match($pattern, $string)) { // 以字母开头
                $weixin_info = $this->wx_auth->getInfo(['authorizer_appid' => $string], 'website_id');
            }
        }

        if ($weixin_info['website_id']) {
            return $weixin_info['website_id'];
        }
        return false;
    }
    /**
     * 版本叠加
     * @param string $version 小程序版本
     * @return string
     */
    function autoAddVersionForMiniprogram($version=''){
        if (empty($version)) {
            // 从数据取
            $version = '1.0.0';//默认
            $submitRes = $this->getNewMpSubmitRecord(['website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
            $version = $submitRes['version'] ?: $version;
        }
        $list = explode('.', $version);
        $count = count($list);
        $change_val = $list[$count -1];
        if (!is_numeric($change_val)) {
            // 版本号只能为数字
            return ['code' => -1, 'message' => '版本号只能为数字'];
        }
        $change_val += 1;
        $list[$count -1] = $change_val;
        $new_version = implode('.', $list);
        if ($count < 3) {
            $new_version = '1.'. $new_version;
        }
        return ['code' => 1, 'message' => $new_version];
    }
    /**
     * 获取授权信息
     * @param $condition
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    function getMpAuthorizerInfo($condition, $field = '*')
    {
        return $this->wx_auth->getInfo($condition, $field);
    }

    /**
     * 修改小程序域名地址
     * @param array $domain_data 需要设置的服务器域名
     * @param string $action [操作方法]
     * @param int $type [发布类型1直播 2非直播]
     * @return string
     * @throws \think\Exception\DbException
     */
    public function modifyDomain(array $domain_data, $action = 'add',$type =2)
    {
        $confit_domain = config('mp_domain');
        $domain_data = array_merge($domain_data, $confit_domain);
        //$type 1直播 2非直播
        // 先从开放平台取回配置域名
        $result = $this->wchat_open->modifyDomain(['action' => 'get']);
        if ($result->errcode) {
            return $result;
        }

        // 域名是不带https的
        if (empty($domain_data)) {
            $third_domain_name = getIndependentDomain($this->website_id);//独立域名
            if (empty($third_domain_name)) {
                return [ 'errcode'=> -1, 'message' => '独立域名不存在！'];
            }
            $domain_data[] = $third_domain_name;
        }
        $arr_temp = [
          "action" => $action,
          "requestdomain" => [],
          "wsrequestdomain" =>  [],
          "uploaddomain" =>  [],
          "downloaddomain" => []
        ];
        var_dump($domain_data);die;
        if (!empty($domain_data)) {
            $flag = 0;
            foreach ($domain_data as $key => $url) {
                // 去掉https或者http
                $url = removeUrlHttp($url);
                //不存在，再上传
                if (!in_array('https://'.$url, $result->requestdomain)){
                    array_push($arr_temp['requestdomain'], 'https://'.$url);
                    array_push($arr_temp['wsrequestdomain'], 'wss://'.$url);
                    array_push($arr_temp['uploaddomain'], 'https://'.$url);
                    array_push($arr_temp['downloaddomain'], 'https://'.$url);
                    $flag +=1;
                }
            }
            if (empty($arr_temp['requestdomain'])){return;}
            if ($flag == 0){
                if ($type==2 || ($type == 1 && in_array(Config::get('mp_im.requestdomain')[0], $result->requestdomain))){
                    return;
                }
            }
            //即时通信
            if ($type == 1 && Config::get('mp_im')){
                $arr_temp['requestdomain'] = array_merge($arr_temp['requestdomain'], Config::get('mp_im.requestdomain'));
                $arr_temp['uploaddomain'] = array_merge($arr_temp['uploaddomain'], Config::get('mp_im.uploaddomain'));
                $arr_temp['downloaddomain'] = array_merge($arr_temp['downloaddomain'], Config::get('mp_im.downloaddomain'));
            }
            $result = $this->wchat_open->modifyDomain($arr_temp);
            if ($result->errcode) {
                return $result;
            }
        }
    }
    /**
     * 设置业务域名
     * @param
     * @param string $action
     * @return mixed|void
     */
    public function postWebDominaToMp ($action='add')
    {
        try {
            $domain_data = config('mp_domain_web');
            if(!$domain_data){return;}
            // 先从开放平台取回配置域名
            $result = $this->wchat_open->setWebViewDomain(['action' => 'get']);
            $result = objToArr($result);
            if ($result['errcode']) {
                return $result;
            }
            $arr_temp = [
                "action" => $action,
                "webviewdomain" => [],
            ];
            foreach ($domain_data as $url){
                // 去掉https或者http
                $url = removeUrlHttp($url);
                //不存在，再上传
                if (!$result['webviewdomain'] || !in_array('https://'.$url, $result['webviewdomain'])){
                    array_push($arr_temp['webviewdomain'], 'https://'.$url);
                }
            }
            if (empty($arr_temp['webviewdomain'])){return;}
            $result = $this->wchat_open->setWebViewDomain($arr_temp);
            if ($result->errcode) {
                return $result;
            }
        }catch (\Exception $e){
            $msg = '行号:'.$e->getMessage().' 错误:'.$e->getMessage();
            return AjaxWXReturn(FAIL,[],$msg);
        }
    }
    
    /**
     * 获取小程序模板关联信息
     * @param $condition
     * @return MpTemplateRelationModel
     * @throws \think\Exception\DbException
     */
    public function getMpTemplateInfo($condition)
    {
        $relation = new MpTemplateRelationModel();
        $result = $relation::all($condition, ['message']);
        return objToArr($result);
    }

    /**
     * 小程序模板全部消息( sys_mp_message_template 表)
     * @param $condition
     * @param string $field
     * @param string $order
     * @return mixed
     */
    public function getMpMessageTemplateColumn($condition = [], $field = 'template_id')
    {
        $messageModel = new MpMessageTemplateModel();
        $result = $messageModel->Query($condition, $field);
        return $result;
    }

    /**
     * 小程序模板关联消息（sys_mp_template_relation表 ）
     * @param $condition
     * @return mixed
     * @throws \think\Exception\DbException
     */
    public function getMpRelationList($condition = [])
    {
        $relationModel = new MpTemplateRelationModel();
        $result = $relationModel::all($condition);
        return objToArr($result);
    }

    /**
     * 添加小程序订阅消息 - 商城变量
     * @param $condition
     * @param $data
     * @return false|int|void
     */
    public function postMpSubKeys($condition, $data)
    {
        $subKeyModel = new MpSubKeysModel();
        $result = $subKeyModel->getInfo($condition);
        if ($result) {
            return;
        }
        return $subKeyModel->save($data);
    }

    /**
     * 获取小程序订阅消息 - 商城变量
     * @param array $condition
     * @return mixed
     * @throws \think\Exception\DbException
     */
    public function getMpSubKeys($condition = [])
    {
        $subKeyModel = new MpSubKeysModel();
        $result = $subKeyModel::all($condition);
        return objToArr($result);
    }

    /**
     * 小程序 - 模板配置
     */
    public function putMpTemplateIdOfRedis($data)
    {
        $redis = connectRedis();
        $redisRes = $redis->get($this->template_config);
        $template_array = unserialize($redisRes);

        $template_array[$data['template_id']] = $data;

        foreach ($template_array as $key => $template) {
            $condition = [
                'sys_mp_template_relation.website_id' => $this->website_id,
                'sys_mp_template_relation.shop_id' => $this->instance_id,
                'sys_mp_message_template.template_id' => $template['template_id']
            ];
            $result = $this->getMpTemplateInfo($condition);
            $template_type = '';
            if ($result){
                $template_type = $result[0]['message']['html_id'];//默认取全部
            }
            $template_array[$key]['html_id'] = $template_type;
        }
        $redis->set($this->template_config, serialize($template_array));
        $redis->get($this->template_config);

        return true;
    }

    /**
     * 模板消息 - 信息组装
     * @param $content
     * @param $ids
     * @param $template_type
     * @param $data
     * @return mixed|string
     */
    public function matchSubMessageVariable($content, $ids, $template_type, $data)
    {
        // 1、先查询变量池的对应
        $result = '';
        switch ($template_type)
        {
            case 'pay_success':
                // 被替换值要与替换值对应
                $search_replace = [
                    "[订单编号]" => $data['order_no'],
                    "[订单金额]" => $data['order_money'],
                    "[商品名称]" => $data['goods_name'].'...',//默认取第一个
                    "[收货信息]" => $data['address'],
                    "[下单时间]" => date('Y-m-d H:i:s', $data['create_time'] ?: time()),
                    "[订单状态]" => $data['order_status'],
                ];
                $result = str_replace(array_keys($search_replace), array_values($search_replace), $content);
                break;
            case 'order_close':
                // 被替换值要与替换值对应
                $search_replace = [
                    "[订单编号]" => $data['order_no'],
                    "[订单金额]" => $data['order_money'],
                    "[商品名称]" => $data['goods_name'].'...',//默认取第一个
                    "[收货信息]" => $data['address'],
                    "[下单时间]" => date('Y-m-d H:i:s', $data['create_time'] ?: time()),
                    "[订单状态]" => $data['order_status'],
                ];
                $result = str_replace(array_keys($search_replace), array_values($search_replace), $content);
                break;
            case 'balance_change':
                // 被替换值要与替换值对应
                $search_replace = [
                    "[变动类型]" => $data['type'],
                    "[变动原因]" => $data['reason'],
                    "[变动金额]" => $data['money'],
                    "[剩余金额]" => $data['balance'],
                    "[变动时间]" => date('Y-m-d H:i:s', $data['create_time'] ?: time()),
                    "[当前进度]" => $data['state'],
                    "[订单状态]" => $data['order_status'],
                ];
                $result = str_replace(array_keys($search_replace), array_values($search_replace), $content);
                break;
            case 'refund_info':
                // 被替换值要与替换值对应
                $search_replace = [
                    "[订单号]" => $data['order_no'],
                    "[售后类型]" => $data['type'],
                    "[当前进度]" => $data['state'],
                    "[商品名称]" => $data['goods_name'],
                    "[处理时间]" => date('Y-m-d H:i:s', $data['time'] ?: time()),
                    "[售后原因]" => $data['reason'],
                    "[退款金额]" => $data['refund_money'],
                    "[订单状态]" => $data['order_status'],
                ];
                $result = str_replace(array_keys($search_replace), array_values($search_replace), $content);
                break;
            default:
                break;
        }

        return $result;
    }

    /**
     * 获取帐号下的模板列表
     * @return mixed
     */
    public function getTemplateList($website_id=0)
    {
        if (cache::get($website_id.'mp_template_list') !=  false) {
            return cache::get($website_id.'mp_template_list');
        }
        $results = $this->wchat_open->getTemplateListOfSub();
        $results = objToArr($results);
        if ($results['errcode']) {
            return AjaxWXReturn($results['errcode']);
        }
        $template_list = [];
        foreach ($results['data'] as $key => $result)
        {
            $template_list[$key] = $result['priTmplId'];
        }
        cache::set($website_id.'mp_template_list', $template_list, 3600*24*30);

        return $template_list;
    }

    /**
     * 获取最新模板id
     * @return mixed
     */
    public function getNewestMpTemplateId()
    {
        $result = $this->wx_auth->getInfo(
            [
                'website_id' => $this->website_id
            ],
            'template_id'
        );
        return $result['template_id'];
    }
    
    /*
     * 设置新的太阳码生成方式
     */
    public function setNewCode ($website_id = '')
    {
        $website_id = $website_id ?: $this->website_id;
        return $this->wx_auth->save(['is_new_code' => 1], ['website_id' => $website_id]);
    }
    
      /*
     * 设置新的太阳码生成方式
     */
    public function getNewCode ($website_id = '')
    {
        return true;
        $website_id = $website_id ?: $this->website_id;
        return $this->wx_auth->getInfo(['website_id' => $website_id],'is_new_code')['is_new_code'];
    }
    
    /*
     * 小程序V3版本设置为新版本
     */
    public function tempSetV3Version ($website_id=0, $submit_time=0)
    {
        if (!$website_id) {return;}
        $v3_mp_time = 1595409720;
        $submit_time = $submit_time;
        try {
            if ($submit_time > $v3_mp_time) {
                $this->setNewCode($website_id);
                //发布成功清除缓存
                $weixin_service = new Weixin();
                $weixin_service->deletePoster($website_id);
                if(getAddons('credential', $website_id)){
                    $credential = new Credential();
                    //清除证书缓存
                    $credential->deleteCredCache();
                }
            }
        } catch (\Exception $e){
            debugFile(tryCachErrorMsg($e),'21、小程序V3版本设置为新版本'.$website_id.' ' ,$this->error_log_url);
        }
    }
    
    
    /**
     * 获取前端永久存储值
     * @param $change_scene 【key】
     * @param $curr_scene 【value】
     */
    public function getMysqlChangeScene ($change_scene)
    {
        $condition = [
            'id'   => $change_scene
        ];
        $info = Db::table('vsl_mp_scene')->where($condition)->field('value')->find();
        if ($info) {
            return $info['value'];
        }
    }
    
    /**
     * 永久存储
     * @param $change_scene 【key】
     * @param $curr_scene 【value】
     */
    public function saveMysqlChangeScene ($change_scene, $curr_scene)
    {
        $info = Db::table('vsl_mp_scene')->where(['key' => $change_scene])->find();
        if ($info){
            return $info['id'];
        }
        
        $data = [
            'key'   => $change_scene,
            'value' => $curr_scene,
            'create_time' => time()
        ];
        return Db::table('vsl_mp_scene')->insert($data, false, true);
    }
    
    /**
     * 获取后端临时存储
     * @param $change_scene 【key】
     * @param $curr_scene 【value】
     */
    public function getRedisChangeScene ($change_scene)
    {
        if (empty($change_scene)) {return;}
        $redis = connectRedis();
        $orig_scene = $redis->get($change_scene);
        return $orig_scene;
    }
    
    /**
     * 后端临时存储
     * @param $change_scene 【key】
     * @param $curr_scene 【value】
     */
    public function saveRedisChangeScene ($change_scene, $curr_scene)
    {
        if (empty($change_scene)) {return;}
        $redis = connectRedis();
        $redis->set($change_scene, $curr_scene);
        $redis->expire($change_scene, 3600);
    }
    
    /*
     * 小程序直播应用是否存在
     */
    public function isExistMplive ()
    {
        $merchantSer = new MerchantSer();
        return $merchantSer->isExistAddons('mplive');
    }
    
    /**
     * 清空订阅消息的cache文件
     */
    public function delCacheMpTemplateList ()
    {
        cache::set($this->website_id.'mp_template_list',null);
    }
    
    /**
     * 取出最新状态记录，以auth_id分组
     * @param int $status [提交状态0为审核成功，1为审核失败，2为审核中 3发布失败 4发布成功]
     * @return mixed
     */
    public function getMpSubmitNewestListGroupOfAuthId ($status=2)
    {
        try{
            $mp_submit_model = new MpSubmitModel();
            $list = $mp_submit_model->getMpSubmitNewestListGroupOfAuthId($status);
            $this->wchat_open->deleteRedis();
            foreach ($list as $v) {
                $this->wchat_open = new WchatOpen($v['website_id']);
                $data['auditid'] = $v['audit_id'];
                $result = $this->wchat_open->getAuditStatus($data);
                // 获取接口成功返回结果
                $submit_data = [];
                if ($result->errcode == 0) {
                    if ($result->status == 0) {// 通过审核
                        $submit_data = [];
                        $submit_data['status'] = 0;
                        $submit_data['review_message'] = '审核成功，发布中';
                        $mp_submit_model = new MpSubmitModel();
                        $mp_submit_model->save($submit_data, ['id'=>$v['id']]);
                        // 发布小程序(审核中状态下去发布)
                        $wchatOpen = new WchatOpen($v['website_id']);
                        //重新获取token
//                        $wxAuth = new WeixinAuthModel();
//                        $authorizer_access_token = $wxAuth->getInfo(['auth_id' => $v['auth_id']],'authorizer_access_token')['authorizer_access_token'];
                        $release_result = $wchatOpen->release();
                        if ($release_result->errcode == 0) {
                            $submit_data['review_message'] = '发布成功!';
                            $submit_data['status'] = 4;
                                $mp_submit_model = new MpSubmitModel();
    //                        $mp_submit_model->save($submit_data, ['audit_id' => $v['audit_id']]);
                                $mp_submit_model->save($submit_data, ['id'=>$v['id']]);
                            // 更新前端新包标识（新的获取太阳码方式）
                            $this->tempSetV3Version($v['website_id'], $v['submit_time']);
                            continue;
                        } else {
                            $submit_data['review_message'] = '发布失败：'.$result->errmsg;
                            $submit_data['status'] = 3;
                        }
                    } elseif($result->status == 2){
                        $submit_data['status'] = $result->status;
                        $submit_data['review_message'] = '审核中';
                    } else {
                        $submit_data['status'] = 1;
                        $submit_data['review_message'] = '错误码：'.$result->errcode.'审核状态'.$result->status.' 错误信息：'.$result->errmsg.' '.$result->reason;
                    }
                } else {//审核失败
                    $error_msg = '错误码：'.$result->errcode;
                    $error_msg .= $result->status ? ' 错误状态：'.$result->status : ' 错误状态：0';
                    $error_msg .= ' 错误信息：'.$result->errmsg.' '.$result->reason;
                    $submit_data = [
                        'status' => 1,
                        'review_message' => $error_msg
                    ];
                }
                $mp_submit_model = new MpSubmitModel();
                $mp_submit_model->save($submit_data, ['id'=>$v['id']]);
            }
        }catch (\Exception $e){
            debugFile(tryCachErrorMsg($e),'16、查询指定发布审核单的审核状态(报错)'.$this->website_id.' ' ,$this->error_log_url);
        }
    }
    
    /****************** 生成太阳码 START ************************/
    
    /**
     * 生成太阳码
     * @param string $authorizer_access_token 小程序token
     * @param array $params 生成码所需参数
     * @param int   $type [生成码方式：1有限 2无限]
     * @param bool  $is_buffer [是否返回微信返回的码图片流buttfer 1是 2否]
     * @return array|mixed|string
     */
    public function createSunCodeUrl ($authorizer_access_token, $params = [], $type = 1, $is_buffer = false)
    {
        //返回的图片 Buffer
        $imgRes = $this->wchat_open->getSunCodeApi($authorizer_access_token, $params, $type);
        if(isset($imgRes->errcode)){
            return AjaxWXReturn(FAIL,[],$imgRes->errmsg);
        }
        if($is_buffer==true){return $imgRes;}
        //图片处理
        try{
            $mp_auth_info = $this->miniProgramInfo(['website_id'=>$this->website_id]);
            $path = 'upload/sunCode/' . $type .'/'. $this->website_id;
            $imgName = $mp_auth_info['nick_name'] ? md5($mp_auth_info['nick_name']) : time();
            /**
             * 如果是type=2场景值二维码（临时），为了防止云端存储多余临时图片，每次生成图片名都为1.jpeg,这样就覆盖上一次的图片
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
            // 存储本地文件
            $sunCodePath = saveBuffer2Img($imgRes, $path, $imgName);
            return $sunCodePath;
        }catch (\Exception $e){
            debugFile($e->getMessage(),'9.1、存储太阳码错误'.$this->website_id.' ' ,$this->error_log_url);
        }
    }
    
    /**
     * 获取太阳码 （有限个）
     * @param string $path
     * @param int    $width
     * @return \think\response\Json
     */
    public function getLimitMpCode($path='',$width=280)
    {
        $path = $path?:Config::get('mp_route')[0];
        $params = [
            "path" => $path,
            'width' => $width
        ];
        $authorizer_access_token = $this->miniProgramInfo(['website_id' => $this->website_id],'authorizer_access_token');
        $sun_code_url = $this->createSunCodeUrl($authorizer_access_token, $params, 1);
        
        return $sun_code_url;
    }
    
    
    /****************** 生成太阳码 END/ ************************/
}
