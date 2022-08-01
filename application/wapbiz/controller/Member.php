<?php

namespace app\wapbiz\controller;

use data\service\Member as MemberService;
use data\model\VslMemberBalanceWithdrawModel;

/**
 * 会员管理
 *
 * @author  www.vslai.com
 *
 */
class Member extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员列表主页
     */
    public function memberList()
    {
        $member = new MemberService();
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $search_text = trim(request()->post('search_text', ''));
        $condition['su.is_member'] = 1;
        if ($search_text) {
            $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $condition['su.website_id'] = $this->website_id;
        $list = $member->getMemberList($page_index, $page_size, $condition, 'su.reg_time desc','uid,user_name,nick_name,level_name,user_tel,point,balance,user_headimg');
        $list['member_list'] = [];
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $list['member_list'][$key]['uid'] = $val['uid'];
                $list['member_list'][$key]['user_name'] = $val['user_name'] ? :$val['nick_name'];
                $list['member_list'][$key]['level_name'] = $val['level_name'];
                $list['member_list'][$key]['user_tel'] = $val['user_tel'];
                $list['member_list'][$key]['point'] = $val['point'];
                $list['member_list'][$key]['balance'] = $val['balance'];
                $list['member_list'][$key]['user_headimg'] = getApiSrc($val['user_headimg']);
            }
            
        }
        unset($list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

    /**
     * 会员详情
     */
    public function memberDetail()
    {
        $member = new MemberService();
        $member_id = request()->post('member_id');
        $condition['su.uid'] = $member_id;
        $condition['su.website_id'] = $this->website_id;
        $list = $member->getMemberList(1, 0, $condition, '');
        $member_info = [];
        if($list['data']){
            $result = $list['data'][0];
            $member_info['uid'] = $result['uid'];
            $member_info['level_name'] = $result['level_name'];
            $member_info['reg_time'] = $result['reg_time'];
            $member_info['user_headimg'] = getApiSrc($result['user_headimg']);
            $member_info['user_name'] = $result['user_name']?:$result['nick_name'];
            $member_info['user_tel'] = $result['user_tel'];
            $member_info['growth_num'] = $result['growth_num'];
            $member_info['point'] = $result['point'];
            $member_info['balance'] = $result['balance'];
            $member_info['order_num'] = $result['order_num'];
            $member_info['order_money'] = $result['order_money'];
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $member_info]);
    }

    /**
     * 积分、余额调整
     */
    public function addMemberAccount()
    {
        $member = new MemberService();
        $uid = (int)request()->post('id', 0);
        $type = (int)request()->post('type', '');//1积分,2余额
        $num = (float)request()->post('num', '');
        $text = request()->post('text', '');
        if(!$uid || !$type || !$num || ($type != 1 && $type != 2)){
            return json(AjaxReturn(-1006));
        }
        if (empty($text)) {
            $text = '后台调整';
        }
        $retval = $member->addMemberAccount2($type, $uid, $num, $text, 10);
        $this->addUserLogByParam($type =="积分余额调整", $retval);
        return json(AjaxReturn($retval));
    }

    /**
     * 会员等级列表
     */
    public function memberLevelList()
    {
        $member = new MemberService();
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['website_id'] = $this->website_id;
        $list = $member->getMemberLevelList($page_index, $page_size, $condition);
        $member_list = [];
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $member_list[$key]['level_id'] = $val['level_id'];
                $member_list[$key]['level_name'] = $val['level_name'];
                $member_list[$key]['is_default'] = $val['is_default'];
            }
            unset($val);
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $member_list]);
    }

    /**
     * 修改 当前会员的等级
     */
    public function adjustMemberLevel()
    {
        $member = new MemberService();
        $level_id = request()->post("level_id", 0);
        $uid = request()->post("uid", 0);
        if(!$level_id || !$uid){
            return json(AjaxReturn(-1006));
        }
        $ids = explode(',', $uid);
        if (count($ids) > 1) {
            foreach ($ids as $v) {
                $res = $member->adjustMemberLevel($level_id, $v);
            }
        } else {
            $res = $member->adjustMemberLevel($level_id, $uid);
        }
        $this->addUserLogByParam("修改用户会员等级", $res);
        return json(AjaxReturn($res));
    }

    /**
     * 会员提现列表
     */
    public function userCommissionWithdrawList()
    {
        $member = new MemberService();
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $status = (int)request()->post('status', 0);
        if (request()->post('search_text', '')) {
            $condition["su.nick_name|su.user_tel|su.user_name|su.uid"] = array(
                "like",
                "%" . request()->post('search_text', '') . "%"
            );
        }
        $condition["nmar.website_id"] = $this->website_id;
        if ($status) {
            switch ($status) {
                case 1:
                    $condition["nmar.status"] = [in,[1,2]];
                    break;
                case 2:
                    $condition["nmar.status"] = [in,[3,4,-1]];
                    break;
                case 3:
                    $condition["nmar.status"] = 5;
                    break;
                default:
                    break;
            }
        }
        $list = $member->getMemberBalanceWithdraw($page_index, $page_size, $condition, 'ask_for_date desc');
        $list['withdraw_list'] = [];
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $list['withdraw_list'][$key]['id'] = $val['id'];
                $list['withdraw_list'][$key]['withdraw_no'] = $val['withdraw_no'];
                $list['withdraw_list'][$key]['withdraw_type'] = ($val['type'] == 1 || $val['type'] == 4) ? '银行卡' : ($val['type'] == 2 ? '微信' : ($val['type'] == 3 ? '支付宝' : '未知方式'));
                $list['withdraw_list'][$key]['uid'] = $val['uid'];
                $list['withdraw_list'][$key]['status'] = $val['status'];
                $list['withdraw_list'][$key]['account_number'] = $val['account_number'];
                $list['withdraw_list'][$key]['user_info'] = $val['user_info'];
                $list['withdraw_list'][$key]['cash'] = $val['cash'];
                $list['withdraw_list'][$key]['charge'] = $val['charge'];
                $list['withdraw_list'][$key]['ask_for_date'] = $val['ask_for_date'];
                $list['withdraw_list'][$key]['payment_date'] = $val['payment_date'];
                $list['withdraw_list'][$key]['status_name'] = $member->getWithdrawStatusName($val['status']);
            }
            unset($val);
        }
        unset($list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }
    
   
    /**
     * 获取提现详情
     */
    public function getWithdrawalsInfo()
    {
        $id = (int)request()->post('id', 0);
        if(!$id){
            return json(AjaxReturn(-1006));
        }
        $member = new MemberService();
        $retval = $member->getMemberWithdrawalsDetails($id);
        if(!$retval){
            return json(['code' => -1, 'message' => '提现记录不存在']);
        }
        $uid = $retval['uid'];
        $user_info = $member->getUserInfo($uid, 'nick_name,user_name,user_tel,uid');
        $retval['realname'] = $retval['realname'] ? : (($user_info['nick_name'])?$user_info['nick_name']:($user_info['user_name']?$user_info['user_name']:($user_info['user_tel']?$user_info['user_tel']:$user_info['uid'])));
        $retval['withdraw_type'] = ($retval['type'] == 1 || $retval['type'] == 4) ? '银行卡' : ($retval['type'] == 2 ? '微信' : ($retval['type'] == 3 ? '支付宝' : '未知方式'));
        $retval['status_name'] = $member->getWithdrawStatusName($retval['status']);
        $retval['open_bank'] = $retval['open_bank']?:'';
        unset($retval['shop_id'],$retval['modify_date'],$retval['service_charge'],$retval['form_id']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $retval]);
    }
    
    /**
     * 用户提现审核
     */
    public function userCommissionWithdraw()
    {
        $id = (int)request()->post('id', 0);
        $status = request()->post('status', 0);
        $remark = request()->post('memo', '');
        if(!$id || !$status){
            return json(AjaxReturn(-1006));
        }
        
        $member = new MemberService();
        $ids = explode(',',$id);
        if(count($ids)>1){
            foreach ($ids as $v) {
                $retval = $member->userCommissionWithdraw($this->instance_id, $v, $status, $remark);
            }
        }else{
            $retval = $member->userCommissionWithdraw($this->instance_id, $id, $status, $remark);
        }
        $this->addUserLogByParam("用户提现审核",$id);
        if($retval){
            $this->addUserLogByParam('用户提现审核', $retval);
        }

        if (isset($retval['is_success'])) {
            return json([
                'code' => $retval['is_success'],
                'message' => $retval['msg']
            ]);
        }

        return json(AjaxReturn($retval));
    }
    
    /**
     *用户提现失败原因
     */
    public function withdrawFailReason()
    {
        $id = (int)request()->post('id', 0);
        if(!$id){
            return json(AjaxReturn(-1006));
        }
        $member = new MemberService();
        $retval = $member->getMemberWithdrawalsDetails($id);
        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => ['reason' => $retval['memo']]
        ]);
    }
    
    /**
     * 打款
     */
    public function userWithdrawMake()
    {
        $id = (int)request()->post('id', 0);
        $status = request()->post('status', 0);//3同意并在线,4拒绝,5手动打款
        $remark = request()->post('memo', '');
        if(!$id || !$status){
            return json(AjaxReturn(-1006));
        }
        $member = new MemberService();
        $ids = explode(',',$id);
        if(count($ids)>1){
            foreach ($ids as $v) {
                $retval = $member->memberBalanceWithdraw($this->instance_id,$v, $status,$remark);
            }
        }else{
            $retval = $member->memberBalanceWithdraw($this->instance_id,$id, $status,$remark);
        }
        $this->addUserLogByParam("打款",$retval);
        if($retval){
            $this->addUserLogByParam('打款状态', $retval);
        }
        if($retval==-9000){
            $balance = new VslMemberBalanceWithdrawModel();
            $msg = $balance->getInfo(['id'=>$id],'memo')['memo'];
        }else if($retval>0){
            $msg = '打款成功';
        }else{
            $msg = '打款失败';
        }
        return AjaxReturn($retval,$msg);
    }
}
