<?php

namespace addons\wxmembermsg\server;

use addons\wxmembermsg\model\WxMassMsgRecordModel;
use addons\wxmembermsg\model\WxMassMsgTemplateModel;
use addons\wxmembermsg\model\WxMassMsgTypeModel;
use data\extend\WchatOauth;
use data\service\BaseService;
use data\service\Member as MembserSer;
use data\service\User as UserSer;
use addons\distribution\service\Distributor as DistributorSer;
use think\Db;

/**
 * 微信会员群发消息
 * Class WxMembermsg
 * @package addons\wxmembermsg\server
 */
class WxMemberMsg extends BaseService {

    protected $templateModel;//模板消息
    protected $typeModel;//推送方式
    protected $recordModel;//群发记录
    
    public function __construct() {
        $this->templateModel        = new WxMassMsgTemplateModel();
        $this->typeModel            = new WxMassMsgTypeModel();
        $this->recordModel          = new WxMassMsgRecordModel();
        parent::__construct();
    }

    /**************************** 模板方式 ****************************************/
    /*
     * 获取模板方式
     */
    public function getMsgType ($condition, $field = '*')
    {
        return $this->typeModel->getInfo($condition, $field);
    }
    
    /*
     * 获取当前组合所有数据
     * 会员等级、会员标签、分销商等级
     */
    public function getUserSendTypesCombineList ()
    {
        $list = [];
        $page_index = 1;
        $page_size  = 0;
        //1、会员等级
        $memberSer = new MembserSer();
        $level_condition = [
            'website_id'        => $this->website_id,
        ];
        $levelRes = $memberSer->getMemberLevelList($page_index,$page_size, $level_condition, '', $field = 'level_id, level_name');
        $list['user_level'] = $levelRes['data'];
        //2、会员标签
        $group_condition = [
            'website_id'        => $this->website_id,
        ];
        $groupRes = $memberSer->getMemberGroupList($page_index, $page_size, $group_condition, 'group_id desc','group_id,group_name');
        $list['user_group'] = $groupRes['data'];
        //3、分销商等级
        $disRes = [];
        if (getAddons('distribution', $this->website_id)) {
            $disSer = new DistributorSer();
            $disRes = $disSer->getDistributorLevel();
        }
        $list['dis_level'] = $disRes;
    
        return $list;
    }

    /*
     * 修改推送方式组合
     */
    public function saveMsgType ($data, $condition =[])
    {
        return $this->typeModel->saveAndUpdate($data, $condition);
    }
    
    /**************************** 模板信息 ****************************************/
    
    /*
     * 获取单条模板信息
     */
    public function getMsgTemplate ($condition, $field = '*', $conditionOr='')
    {
        return $this->templateModel->getOrInfo($condition, $conditionOr, $field);
    }
    /*
     * 获取条件下模板
     */
    public function getMsgTemplates ($condition =[], $field='*')
    {
        return $this->templateModel->getQuery($condition, $field);
    }
    
    /*
     * 获取模板列表
     */
    public function getMsgTemplateList($page_index, $page_size, $condition, $order = '')
    {
        $list = $this->templateModel->getMsgTemplateList($page_index, $page_size, $condition, $order);
        return $list;
    }
    
    /*
     * 新建或修改模板信息
     */
    public function saveMsgTemplate ($data, $condition  =[])
    {
        return $this->templateModel->saveAndUpdate($data, $condition);
    }
    
    /*
     * 删除模板
     */
    public function deleteMsgTemplate ($condition)
    {
        return $this->templateModel->delData($condition);
    }
    
    /**************************** 模板记录 ****************************************/
    
    /*
     * 获取模板记录
     */
    public function getMsgRecord ($condition, $field = '*')
    {
        return $this->recordModel->getInfo($condition, $field);
    }
    
    public function delMsgRecord ($condition)
    {
        return $this->recordModel->delData($condition);
    }
    
    /*
     * 获取群发记录列表
     */
    public function getMsgRecordList($page_index, $page_size, $condition, $order = '')
    {
        $list = $this->recordModel->getMsgRecordList($page_index, $page_size, $condition, $order);
        return $list;
    }
    
    /**************************** 综合 ****************************************/
    
    
    /*
     * 模板消息关联推送方式
     */
    public function getMsgTemplateRelationType($data)
    {
        $templateModel = $this->templateModel;
        return $templateModel::get($data, ['msg_type']);
    }
    
    /*
     * 模板消息关联记录
     */
    public function getMsgTemplateRelationRecord($data)
    {
        $templateModel = $this->templateModel;
        return $templateModel::get($data, ['msg_record']);
    }

    /**
     * 添加模板发送信息（模板&推送方式）
     * @param $msg_template_id int [模板id]
     * @param $data array [推送的方式组合]
     * @return \multitype
     */
    public function saveMsgTemplateRelationType ($msg_template_id, $data)
    {
        $templateRes = $this->templateModel->getInfo(
            [
                'id' => $msg_template_id
            ],
            'id');
        if (!$templateRes) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
    
        //添加推送方式
        $typeRes = $this->saveMsgType($data, ['msg_template_id' => $msg_template_id]);
    
        if (!$typeRes) {
            return AjaxReturn(FAIL);
        }
        // 添加模板信息
        $this->saveMsgTemplate(
            [
            'send_type'         => $data['send_type'],
            'send_type_id'      => $typeRes,
            'status'            => 2/*请求群发*/
            ],
            [
                'id' => $msg_template_id
            ]);
        return AjaxReturn(SUCCESS);
    }
    
    /**
     * 获取推送类型对应的所有用户微信openid
     * @param $type_id int [推送方式]
     * @param $website_id string [商家实例]
     * @return array|void
     */
    public function getTypeMapUseWxOpenids ($type_id,$website_id='', $offset=0, $limit=0)
    {
        // 推送方式 1按会员等级推送 2按会员标签推送 3按分销商等级推送 4按会员ID推送  5推送所有会员
        // 查询推送类型
        $typeRes        = $this->typeModel->getInfo(['id' => $type_id, 'website_id' => $website_id]);
        $website_id     = $website_id ?: ($typeRes['website_id'] ?: $this->website_id);
        $memberSer      = new MembserSer();
        $userSer        = new UserSer();
        $user_field     = 'wx_openid,uid';//默认获取用户的会员openid
        $openidsArr     = [];
        $uid_openid     = [];
        switch ($typeRes['send_type'])
        {
            case 1:
                $member_level       = $typeRes['send_sub_type'];
                if (!$member_level) {return;}
                $getArr             = $memberSer->getMemberLevelOfUsers($member_level, $user_field, true,$website_id, $offset, $limit);
                $uid_openid         = array_merge($uid_openid, $getArr);
                $openids            = array_column($getArr,'wx_openid');
                $openidsArr         = array_merge($openidsArr, $openids);
                break;
            case 2:

                $typeRes['send_sub_type'] = explode(',', $typeRes['send_sub_type']);
                $group_id    = implode(',|,', $typeRes['send_sub_type']);//这里为了正则匹配
                $getArr      = $memberSer->getMemberGroupIdOfUsers($group_id, $user_field, true, $website_id, $offset, $limit);
                $uid_openid  = array_merge($uid_openid, $getArr);
                $openids     = array_column($getArr,'wx_openid');
                $openidsArr  = array_merge($openidsArr, $openids);
                break;
            case 3:
                $distributor_level = $typeRes['send_sub_type'];
                if (!$distributor_level) {return;}
                $getArr            = $memberSer->getMemberDistributorLevelOfUser($distributor_level, $user_field, true, $website_id, $offset, $limit);
                $uid_openid        = array_merge($uid_openid, $getArr);
                $openids           = array_column($getArr,'wx_openid');
                $openidsArr        = array_merge($openidsArr, $openids);
                break;
            case 4:
                $uids = $typeRes['send_sub_type'];//string
                if (!$uids) {
                    return;
                }
                $user_condition = [
                    'website_id'    => $website_id,
                    'uid'           => ['in', $uids],
                    'wx_openid'     => ['<>', '']
                ];
                $getArr            = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
                $uid_openid        = array_merge($uid_openid, $getArr);
                $openids           = array_column($getArr,'wx_openid');
                $openidsArr        = array_merge($openidsArr, $openids);
                break;
            case 5:
                $user_condition = [
                    'website_id'    => $website_id,
                    'wx_openid'     => ['<>', '']
                ];
                $getArr            = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
                $uid_openid        = array_merge($uid_openid, $getArr);
                $openids           = array_column($getArr,'wx_openid');
                $openidsArr        = array_merge($openidsArr, $openids);
                break;
        }
        $returnArr = [
            'openid'        => $openidsArr,
            'uid_openid'    => $uid_openid
        ];
        return $returnArr;
    }


    /**
     * 获取推送类型对应的所有用户数量
     * @param $type_id int [推送方式]
     * @param $website_id string [商家实例]
     * @return array|void
     */
    public function getTypeMapUserCount ($type_id,$website_id='')
    {
        // 推送方式 1按会员等级推送 2按会员标签推送 3按分销商等级推送 4按会员ID推送  5推送所有会员
        // 查询推送类型
        $typeRes        = $this->typeModel->getInfo(['id' => $type_id, 'website_id' => $website_id]);
        $website_id     = $website_id ?: ($typeRes['website_id'] ?: $this->website_id);
        $memberSer      = new MembserSer();
        $userSer        = new UserSer();
        $user_field     = 'wx_openid,uid';//默认获取用户的会员openid
        switch ($typeRes['send_type'])
        {
            case 1:
                $member_level       = $typeRes['send_sub_type'];
                if (!$member_level) {return;}
                $count             = $memberSer->getMemberLevelOfUsersCount($member_level, $user_field, true,$website_id);
                break;
            case 2:

                $typeRes['send_sub_type'] = explode(',', $typeRes['send_sub_type']);
                $group_id    = implode(',|,', $typeRes['send_sub_type']);//这里为了正则匹配
                $count      = $memberSer->getMemberGroupIdOfUsersCount($group_id, $user_field, true, $website_id);
                break;
            case 3:
                $distributor_level = $typeRes['send_sub_type'];
                if (!$distributor_level) {return;}
                $count            = $memberSer->getMemberDistributorLevelOfUserCount($distributor_level, $user_field, true, $website_id);
                break;
            case 4:
                $uids = $typeRes['send_sub_type'];//string
                if (!$uids) {
                    return;
                }
                $user_condition = [
                    'website_id'    => $website_id,
                    'uid'           => ['in', $uids],
                    'wx_openid'     => ['<>', '']
                ];
                $count            = $userSer->getUserCount($user_condition, $user_field);
                break;
            case 5:
                $user_condition = [
                    'website_id'    => $website_id,
                    'wx_openid'     => ['<>', '']
                ];
                $count            = $userSer->getUserCount($user_condition, $user_field);
                break;
        }
        return $count;
    }
    
    /**
     * 组装数据并发送
     * @param $msg_template_id
     * @return \multitype|void
     */
    public function createDataAndSendTemplateMsg ($msg_template_id)
    {
        Db::startTrans();
        try{
            $templateInfo = $this->getMsgTemplateRelationType(['vsl_weixin_mass_msg_template.id' => $msg_template_id]);
            $website_id = $templateInfo['website_id'] ?: $this->website_id;
            if (!$website_id) {return;}
            // 1、查询用户openid
            $getArr         = $this->getTypeMapUseWxOpenids($templateInfo['msg_type']['id'],$website_id);
            $openidsArr     = $getArr['openid'];
            $uidOpenidArr   = $getArr['uid_openid'];//uid及openid
            $uidsArr        = array_column($uidOpenidArr, 'uid');
            $uidCount = count($uidsArr);//用户总数
    
            // 2、组装数据
            $weixin = new WchatOauth($website_id);
            if ($openidsArr) {
            foreach ($openidsArr as $openid) {
                $postData = $this->createData($templateInfo, $openid);
                // 3、调取微信接口发送数据并记录发送数量
                $wxRes = $weixin->templateMessageSend($postData, $website_id);
                if ($wxRes->errcode) {
                    debugLog($wxRes->errmsg, '群发消息:$openid ==>');
                }
                }
            }
            // 3、写入记录
            $recordData = [
                'website_id'            => $templateInfo['website_id'],
                'msg_id'                => $msg_template_id,
                'wx_template_name'      => $templateInfo['wx_template_name'],
                'send_user_ids'         => implode(',', $uidsArr),/*逗号分割的用户*/
                'send_uid_openids'      => json_encode($uidOpenidArr),
                'send_user_count'       => $uidCount,
                'send_type'             => $templateInfo['send_type'],
                'send_sub_type'         => $templateInfo['msg_type']['send_sub_type']
            ];
            $where['msg_id'] = $msg_template_id;
            $this->recordModel->saveAndUpdate($recordData,$where);
            // 4、修改模板数据
            $this->templateModel->where(['id' => $msg_template_id])->setInc('send_count');
            $this->templateModel->where(['id' => $msg_template_id])->setInc('user_count', $uidCount);
            $this->templateModel->saveAndUpdate(['status' => 1], ['id' => $msg_template_id]);
            
            Db::commit();
            return AjaxReturn(SUCCESS);
            
        } catch (\Exception $e) {
            debugLog($e, '群发错误=》 ');
            Db::rollback();
            return AjaxReturn(FAIL,[], $e->getMessage());
        }
    }
    /**
     * 组装POST数据
     * @param array $templateInfo [模板信息]
     * @param string $openid [用户openid]
     * @return array|void
     */
    public function createData ($templateInfo, $openid)
    {
        //todo... url如果是小程序
        if (!$openid) {return;}
        $value = json_decode($templateInfo['value'], true);
        $data = [];
        # 头部描述
        if ($value['head'] && $value['head']['value']){
            $data['first'] = [
                'value'         => $value['head']['value'],
                'color'         => $value['head']['color'],
            ];
        }
        # 内容
        foreach($value['content'] as $content) {
            $str = trim($content['name'], "{{ }}");
            $key = explode('.', $str)[0];
            $data[$key] = [
                'value'     => $content['value'],
                'color'     => $content['color']
            ];
        }
        # 备注
        if ($value['remark'] && $value['remark']['value']){
            $data['remark'] = [
                'value'         => $value['remark']['value'],
                'color'         => $value['remark']['color'],
            ];
        }
        // 最后组装数据
        $post_data = [
            'touser'                => $openid,
            'template_id'           => $templateInfo['wx_template_id'],
            'url'                   => $templateInfo['url'],
            'data'                  => $data
        ];
        
        return $post_data;
    }
    
    /**
     * 微信发送模板消息后，用户接受状态
     * status:success（成功）failed(失败原因)
     * @param $data
     */
    public function userAcceptStatue ($data)
    {
        // todo... 暂时可以不用接收！
        $openid = $data['openid'][0];
        $status = strtoupper($data['status'][0]);
        
        debug($data, '微信回调群发');
    }
}
