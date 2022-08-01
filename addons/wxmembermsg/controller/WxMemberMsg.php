<?php

namespace addons\wxmembermsg\controller;

use addons\wxmembermsg\Wxmembermsg as baseWxMemberMsg;
use data\model\UserModel;
use data\model\VslMemberModel as VslMemberModel;
use data\service\Member as MemberService;
use think\Db;

/**
 * 微信会员群发消息
 * Class WxMemberMsg
 * @package addons\wxmembermsg\controller
 */
class WxMemberMsg extends baseWxMemberMsg {

    public function __construct() {
        parent::__construct();
    }
    
    public function wxMemberMsgList()
    {
        $post   = request()->post();
        $pageIndex           = $post['pageIndex'] ?: 1;
        $pageSize            = $post['pageSize'] ?: PAGESIZE;
        $condition = [
            'mt.website_id'  => $this->website_id
        ];
        return $this->templateSer->getMsgTemplateList($pageIndex, $pageSize, $condition);
    }
    
    /*
     * 添加微信群发模板消息
     */
    public function addWxMemberMsgTemplate ()
    {
        $data = json_decode($_POST['data'],true);
        $is_edit                    = $data['is_edit'];//是否是修改
        $template_name              = $data['template_name'];//模版名称
        $template_id                = $data['template_id'];//模版id
        $url                        = $data['url'];//跳转链接
        $head_des                   = $data['head_des'];//头部描述
        $head_des_color             = $data['head_des_color'];//头部描述颜色
        $remark                     = $data['remark'];//备注
        $remark_color               = $data['remark_color'];//备注颜色
        $keys                       = $data['key'];//详细内容键值颜色
        $save_id                    = $data['save_id'];//编辑id
        
        //1、新加是否模板名和模板id重复
        if (!$is_edit) {
            $condition = [
                'wx_template_id'        => $template_id,
            ];
            $conditionOr = [
                'wx_template_name'      => $template_name
            ];
            $templateRes = $this->templateSer->getMsgTemplate($condition, 'id', $conditionOr);
            if ($templateRes) {
                return AjaxReturn(FAIL, [], '模板id或模板名重复!');
            }
        }

        Db::startTrans();
        try {
            //2、新建模板信息
            # 重置除默认外键值
            $index = 1;
            $new_keys = [];
            foreach ($keys as $k => &$key) {
                if ($key['key_id'] != 'key_default') {
                    $key['key_id'] = 'key_' . $index;
                    $new_keys['key_'.$index] = $key;
                    $index++;
                } else {
                    $new_keys[$k] = $key;
                }
                
            }
            $value = [
                'head'          => [
                    'value'         => $head_des,
                    'color'         => $head_des_color,
                ],
                'remark'        => [
                    'value'         => $remark,
                    'color'         => $remark_color,
                ],
                'content'       => $new_keys
            ];
            $insertData = [
                'wx_template_id'            => $template_id,
                'wx_template_name'          => $template_name,
                'website_id'                => $this->website_id,
                'url'                       => $url,
                'value'                     => json_encode($value, JSON_UNESCAPED_UNICODE)
            ];
            if($save_id){
                $where['id'] = $save_id;
            }else{
                $where = [];
            }

            $this->templateSer->saveMsgTemplate($insertData,$where);
            
            Db::commit();
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            Db::rollback();
            return AjaxReturn(FAIL, [], $e->getMessage());
        }
    }
    
    /*
     * 删除模板消息
     */
    public function delWxMemberMsgTemplate ()
    {
        $id       = request()->post('id');
        if (!$id) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        Db::startTrans();
        try{
            // 1、删除模板
            $condition = [
                'id' => $id
            ];
            $this->templateSer->deleteMsgTemplate($condition);
            Db::commit();
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            Db::rollback();
            return AjaxReturn(FAIL, [], $e->getMessage());
        }
    }
    
    /*
     * 群发
     */
    public function sendWxMemberMsgTemplate ()
    {
        $post           = request()->post();
        $msg_id         = $post['id'];
        $type           = $post['type'];//1会员等级 2会员标签 3分销商等级 4会员ID 5所有会员
        $sub_type       = $post['sub_type'];
        if (!$msg_id || !$type || empty($sub_type)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $insertData = [
            'website_id'        => $this->website_id,
            'send_type'         => $type,
            'send_sub_type'     => trim($sub_type, ',')
        ];
        switch ($type) {
            case 1:
                $insertData['send_type_name']       = '按会员等级推送';
//                $insertData['send_sub_type']        = json_encode($sub_type);
                break;
            case 2:
                $insertData['send_type_name']       = '按会员标签推送';
//                $insertData['send_sub_type']        = json_encode($sub_type);
                break;
            case 3:
                $insertData['send_type_name']       = '按分销商等级推送';
//                $insertData['send_sub_type']        = json_encode($sub_type);
                break;
            case 4:
                $insertData['send_type_name']       = '按会员ID推送';
                // 去重，去空
//                $sub_type =  array_reverse(array_reverse(array_unique(array_filter($sub_type))));
//                $insertData['send_sub_type']        = implode(',', $sub_type);
                break;
            case 5:
                $insertData['send_type_name']       = '推送所有会员';
                break;
        }
        // 保存模板信息和推送方式
        $saveRes = $this->saveMsgTemplateRelationType($msg_id, $insertData);
        return $saveRes;
    }
    
    /*
     * 保存模板信息和推送方式
     */
    public function  saveMsgTemplateRelationType($msg_id, $data)
    {
        Db::startTrans();
        try {
            $saveRes = $this->templateSer->saveMsgTemplateRelationType($msg_id, $data);
            if ($saveRes['code'] < 0) {
                Db::rollback();
                return $saveRes;
            }
            Db::commit();
            return AjaxReturn(SUCCESS);
        } catch (\Exception $e) {
            Db::rollback();
            return AjaxReturn(FAIL,[], $e->getMessage());
        }
        
    }
    
    /**
     * 测试
     * @return \multitype
     */
    public function testGetTypeMapUseWxOpenids ()
    {
        //todo... 测试
        $msg_template = $this->templateSer->getMsgTemplates(['status' => 8],'id,website_id');
        foreach ($msg_template as $template) {
            $id = $template['id'];
            $res = $this->templateSer->createDataAndSendTemplateMsg($id);
        }
        return AjaxReturn(SUCCESS);
    }
    
    /*
     * 群发记录
     */
    public function wxMemberMsgRecord ()
    {
        $post = request()->post();
        $pageIndex           = $post['pageIndex'] ?: 1;
        $pageSize            = $post['pageSize'] ?: PAGESIZE;
        $template_name       = $post['template_name'];
        $send_type           = $post['send_type'];
        $condition = [
            'mr.website_id'  => $this->website_id
        ];
        if ($template_name) {
            $condition['mr.wx_template_name'] = $template_name;
        }
        if ($send_type) {
            $condition['mr.send_type'] = $send_type;
        }
        $data= $this->templateSer->getMsgRecordList($pageIndex, $pageSize, $condition);
        $typeList = $this->templateSer->getUserSendTypesCombineList();//当前组合所有数据

        if ($data['data']) {
            foreach ($data['data'] as &$v) {
                $v['send_sub_type'] = trim($v['send_sub_type'], ',');
                $v['send_time'] = date('Y-m-d H:i:s', $v['update_time']);
                switch ($v['send_type']) {
                    case 1:
                        $typeArr = explode(',',$v['send_sub_type']);
                        $name = '会员等级:';
                        foreach($typeList['user_level'] as $user) {
                            if (in_array($user['level_id'], $typeArr)){
                                $name .= $user['level_name'].',';
                            }
                        }
                        $name = rtrim($name,',');
                        break;
                    case 2:
                        $name = '会员标签:';
                        $typeArr = explode(',',$v['send_sub_type']);
                        foreach($typeList['user_group'] as $user) {
                            if (in_array($user['group_id'],$typeArr)){
                                $name .= $user['group_name'].',';
                            }
                        }
                        $name = rtrim($name,',');
                        break;
                    case 3:
                        $name = '分销商等级:';
                        $typeArr = explode(',',$v['send_sub_type']);
                        foreach($typeList['dis_level'] as $user) {
                            if (in_array($user['id'],$typeArr)){
                                $name .= $user['level_name'].',';
                            }
                        }
                        $name = rtrim($name,',');
                        break;
                    case 4:
                        $name = '会员ID:';
                        break;
                    case 5:
                        $name = '所有会员:';
                        break;
                }
                $v['send_type_name'] = $name;
            }
        }
        return AjaxReturn(SUCCESS, $data);
    }
    
    /*
     * 记录详情
     */
    public function wxMemberMsgRecordDetail ()
    {
        if (request()->post()) {
            $post = request()->post();
            $record_id              = $post['record_id'];
            $page_index             = $post['page_index'] ?:1;
            $page_size              = $post['page_size'] ?: PAGESIZE;
            $user_info              = $post['user_info'];
    
            if (!$record_id) {
                return AjaxReturn(LACK_OF_PARAMETER);
            }
            //查询对应记录
            $recordRes = $this->templateSer->getMsgRecord(['id' => $record_id], 'send_user_ids, website_id');
            
            $condition = [
                'su.website_id'     => $recordRes['website_id'],
                'su.uid'            => ['in', $recordRes['send_user_ids']]
            ];
            if ($user_info) {
                $condition['su.nick_name | su.user_tel | su.user_name'] = ['like', '%'.$user_info.'%'];
            }
            $member = new MemberService();
            $list = $member->getMemberList($page_index, $page_size, $condition, 'su.reg_time desc');

            return $list;
        }
    }
}
