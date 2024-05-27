<?php
namespace addons\teambonus\service;
/**
 * 团队分红服务层
 */
use addons\bonus\model\VslAgentLevelModel;
use addons\customform\server\Custom as CustomSer;
use addons\distribution\model\VslDistributorLevelModel;
use addons\distribution\model\VslOrderDistributorCommissionModel;
use addons\distribution\service\Distributor as DistributorService;
use data\model\VslAccountModel;
use data\model\VslGoodsDiscountModel as GoodsDiscount;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberModel;
use data\model\VslMemberViewModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use data\model\VslOrderGoodsExpressModel;
use data\model\AlbumPictureModel;
use data\model\VslOrderTeamLogModel;
use data\service\AddonsConfig as AddonsConfigSer;
use data\service\Address;
use data\service\BaseService as BaseService;
use data\model\UserModel;
use think;
use addons\bonus\model\VslAgentLevelModel as AgentLevelModel;
use data\service\Config as ConfigService;
use data\service\Order\OrderStatus;
use data\model\VslMemberAccountModel;
use addons\bonus\model\VslBonusAccountModel;
use addons\bonus\model\VslAgentAccountRecordsModel;
use addons\bonus\model\VslBonusGrantModel;
use addons\bonus\model\VslUnGrantBonusOrderModel;
use addons\bonus\model\VslGrantTimeModel;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\ShopAccount;
use addons\bonus\model\VslOrderBonusLogModel;
use data\service\Goods;

class TeamBonus extends BaseService
{
    private $fre_bonus;
    private $wit_bonus;
    private $wits_bonus;
    private $bonus;
    protected $goods_ser;
    function __construct()
    {
        parent::__construct();
        $set = $this->getAgreementSite($this->website_id);
        if($set && $set['frozen_team_bonus']){
            $this->fre_bonus = $set['frozen_team_bonus'];
        }else{
            $this->fre_bonus = '冻结分红';
        }
        if($set &&  $set['withdrawable_team_bonus']){
            $this->wit_bonus = $set['withdrawable_team_bonus'];
        }else{
            $this->wit_bonus = '待发放分红';
        }
        if($set &&  $set['withdrawals_team_bonus']){
            $this->wits_bonus = $set['withdrawals_team_bonus'];
        }else{
            $this->wits_bonus = '已发放分红';
        }
        $this->goods_ser = new Goods();
    }

    /**
     * 获取队长列表
     */
    public function getAgentList($page_index = 1, $page_size = 0, $where = [], $order = '')
    {
        $where['nm.website_id'] = $this->website_id;
        $user = new UserModel();
        $agent_view = new VslMemberViewModel();
        $result = $agent_view->getTeamAgentViewList($page_index, $page_size, $where, $order);
        $condition['website_id'] = $this->website_id;
        $condition['is_team_agent'] = ['in','1,2,-1'];
        $result['count'] = $agent_view->getCount($condition);
        $condition['is_team_agent'] = 2;
        $result['count1'] = $agent_view->getCount($condition);
        $condition['is_team_agent'] = 1;
        $result['count2'] = $agent_view->getCount($condition);
        $condition['is_team_agent'] = -1;
        $result['count3'] = $agent_view->getCount($condition);
        $bonus_account = new VslBonusAccountModel();
        foreach ($result['data'] as $k => $v) {
            if(empty($result['data'][$k]['user_name'])){
                $result['data'][$k]['user_name'] = $result['data'][$k]['nick_name'];
            }
            $user_info = $user->getInfo(['uid'=>$v['referee_id']],'user_name,nick_name,user_headimg');
            if($user_info['user_name']){
                $result['data'][$k]['referee_name'] = $user_info['user_name'];//推荐人
            }else{
                $result['data'][$k]['referee_name'] = $user_info['nick_name'];//推荐人
            }
            $result['data'][$k]['referee_headimg'] = $user_info['user_headimg'];
            $result['data'][$k]['account'] = $bonus_account->getInfo(['uid'=>$v['uid'],'from_type'=>3],'*');
        }
        return $result;
    }
    /**
     * 获取队长等级列表
     */
    public function getAgentLevelList($page_index = 1, $page_size = 0, $where = '', $order = '')
    {
        $agent_level = new AgentLevelModel();
        $distributor_level = new VslDistributorLevelModel();
        $list = $agent_level->pageQuery($page_index, $page_size, $where, $order, '*');
        foreach ($list['data'] as $k=>$v){
            if ($list['data'][$k]['goods_id']) {
                $list['data'][$k]['goods_name'] = $this->goods_ser->getGoodsDetailById($list['data'][$k]['goods_id'])['goods_name'];
            }
            if ($list['data'][$k]['upgrade_level']) {
                $list['data'][$k]['upgrade_level_name'] = $distributor_level->getInfo(['id' => $list['data'][$k]['upgrade_level']], 'level_name')['level_name'];
            }
        }
        return $list;
    }
    /**
     * 获取当前队长等级
     */
    public function getAgentLevel()
    {
        $agent_level = new AgentLevelModel();
        $list = $agent_level->pageQuery(1,0,['website_id' => $this->website_id,'from_type'=>3],'','id,level_name');
        return $list['data'];
    }
    /**
     * 获取当前队长等级权重
     */
    public function getAgentWeight()
    {
        $agent_level = new AgentLevelModel();
        $list = $agent_level->Query(['website_id' => $this->website_id,'from_type'=>3],'weight');
        return $list;
    }
    /**
     * 添加队长等级
     */
    public function addAgentLevel($level_name,$ratio,$upgradetype,$pay_money,$number,$one_number,$two_number,$three_number,$order_money,$downgradetype,$team_number,$team_money,$self_money,$weight,$downgradeconditions,$upgradeconditions,$goods_id,$downgrade_condition,$upgrade_condition,$team_number_day,$team_money_day,$self_money_day,$upgrade_level,$level_number,$group_number,$up_team_money,$level_award1,$level_award2,$level_award3,$level_money1,$level_money2,$level_money3,$bonus_type,$money)
    {
        $Agent_level = new AgentLevelModel();
        $base_set = $this->getTeamBonusSite($this->website_id);
        if($base_set && $base_set['gradation_status']==1){

        }else{
            $ratio_used = $Agent_level->getSum(['website_id'=>$this->website_id,'from_type'=>3],'ratio');
            if($ratio_used){
                $ratio_total = $ratio_used+$ratio;
//                if($ratio_total>100){
//                    return -3;
//                }
            }
        }
        $where['website_id'] = $this->website_id;
        $where['level_name'] = $level_name;
        $where['from_type'] = 3;
        $count = $Agent_level->where($where)->count();
        if ($count > 0) {
            return -2;
        }
        $data = array(
            'website_id' => $this->website_id,
            'level_award1' => $level_award1,
            'level_award2' => $level_award2,
            'level_award3' => $level_award3,
            'level_money1' => $level_money1,
            'level_money2' => $level_money2,
            'level_money3' => $level_money3,
            'level_name' => $level_name,
            'ratio' => $ratio,
            'upgradetype' => $upgradetype,
            'number' => $number,
            'order_money' => $order_money,
            'pay_money' => $pay_money,
            'one_number' => $one_number,
            'two_number' => $two_number,
            'three_number' => $three_number,
            'downgradetype' => $downgradetype,
            'team_number' => $team_number,
            'team_money' => $team_money,
            'up_team_money' => $up_team_money,
            'self_money' => $self_money,
            'team_number_day' => $team_number_day,
            'team_money_day' => $team_money_day,
            'self_money_day' => $self_money_day,
            'weight' => $weight,
            'downgradeconditions' => $downgradeconditions,
            'upgradeconditions' => $upgradeconditions,
            'downgrade_condition' => $downgrade_condition,
            'upgrade_condition' => $upgrade_condition,
            'goods_id' => $goods_id,
            'from_type' => 3,
            'level_number' => $level_number,
            'upgrade_level' => $upgrade_level,
            'group_number'=>$group_number,
            'create_time' => time(),
            'bonus_type' => $bonus_type,
            'money' => $money,
        );
        $res = $Agent_level->save($data);
        return $res;
    }

    /**
     * 修改队长等级
     */
    public function updateAgentLevel($id, $level_name,$ratio,$upgradetype,$pay_money,$number,$one_number,$two_number,$three_number,$order_money,$downgradetype,$team_number,$team_money,$self_money,$weight,$downgradeconditions,$upgradeconditions,$goods_id,$downgrade_condition,$upgrade_condition,$team_number_day,$team_money_day,$self_money_day,$upgrade_level,$level_number,$group_number,$up_team_money,$level_award1,$level_award2,$level_award3,$level_money1,$level_money2,$level_money3,$bonus_type,$money)
    {
        try {
            $Agent_level = new AgentLevelModel();
            $base_set = $this->getTeamBonusSite($this->website_id);
            if($base_set && $base_set['gradation_status']==1){

            }else{
                $ratio_used = $Agent_level->getSum(['website_id'=>$this->website_id,'id'=>['neq',$id],'from_type'=>3],'ratio');
                if($ratio_used){
                    $ratio_total = $ratio_used+$ratio;
//                    if($ratio_total>100){
//                        return -3;
//                    }
                }
            }
            $where['website_id'] = $this->website_id;
            $where['level_name'] = $level_name;
            $where['from_type'] = 3;
            $where['id'] = ['neq',$id];
            $count = $Agent_level->where($where)->count();
            if ($count > 0) {
                return -2;
            }
            $Agent_level->startTrans();
            $data = array(
                'level_award1' => $level_award1,
                'level_award2' => $level_award2,
                'level_award3' => $level_award3,
                'level_money1' => $level_money1,
                'level_money2' => $level_money2,
                'level_money3' => $level_money3,
                'level_name' => $level_name,
                'ratio' => $ratio,
                'upgradetype' => $upgradetype,
                'number' => $number,
                'order_money' => $order_money,
                'pay_money' => $pay_money,
                'one_number' => $one_number,
                'two_number' => $two_number,
                'three_number' => $three_number,
                'downgradetype' => $downgradetype,
                'team_number' => $team_number,
                'team_money' => $team_money,
                'up_team_money' => $up_team_money,
                'self_money' => $self_money,
                'team_number_day' => $team_number_day,
                'team_money_day' => $team_money_day,
                'self_money_day' => $self_money_day,
                'weight' => $weight,
                'downgradeconditions' => $downgradeconditions,
                'upgradeconditions' => $upgradeconditions,
                'downgrade_condition' => $downgrade_condition,
                'upgrade_condition' => $upgrade_condition,
                'goods_id' => $goods_id,
                'level_number' => $level_number,
                'upgrade_level' => $upgrade_level,
                'group_number'=>$group_number,
                'modify_time' => time(),
                'bonus_type' => $bonus_type,
                'money' => $money,
            );
            $retval= $Agent_level->save($data, [
                'id' => $id,
                'website_id' => $this->website_id
            ]);
            $Agent_level->commit();
            return $retval;
        } catch (\Exception $e) {
            $Agent_level->rollback();
            $retval = $e->getMessage();
            return 0;
        }
    }
    /*
     * 删除分红商等级
     */
    public function deleteAgentLevel($id)
    {
        // TODO Auto-generated method stub
        $level = new AgentLevelModel();
        $level->startTrans();
        try {
            // 删除等级信息
            $retval = $level->destroy($id);
            $level->commit();
            return $retval;
        }catch (\Exception $e) {
            $level->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 获得队长等级详情
     */
    public function getAgentLevelInfo($id)
    {
        $level_type = new AgentLevelModel();
        $level_info = $level_type->getInfo(['id'=>$id]);
        $goods_list = array();

        if($level_info['goods_id']){
            $goods_ids = explode(",", $level_info['goods_id']);
            foreach ($goods_ids as $key => $value) {
                $goods_info = $this->goods_ser->getGoodsDetailById($value, 'goods_id,price,max_buy,stock,picture', 1);
                $goods_info['goods_price'] = $goods_info['price'];
                $goods_info['goods_stock'] = $goods_info['stock'];
                $goods_info['goods_id'] = $value;
                $goods_info['pic'] = $goods_info['album_picture']['pic_cover_mid'];
                array_push($goods_list,$goods_info);
            }
        }

        $level_info['goods_info'] = $goods_list;
        return $level_info;
    }

    /**
     * 修改队长申请状态
     */
    public function setStatus($uid, $status){
        $member = new VslMemberModel();
        $level = new AgentLevelModel();
        $level_info = $level->getInfo(['website_id'=>$this->website_id,'is_default'=>1,'from_type'=>3],'*');
        $level_id = $level_info['id'];
        if($status==2){
            $data = array(
                'is_team_agent' => $status,
                'become_team_agent_time' => time(),
                'team_agent_level_id' => $level_id
            );
        }else{
            $data = array(
                'is_team_agent' => $status
            );
        }
        if($status==2){
            $account = new VslBonusAccountModel();
            $account_info = $account->getInfo(['website_id'=>$this->website_id,'from_type'=>3,'uid' => $uid]);
            if(empty($account_info)){
                $account->save(['website_id'=>$this->website_id,'from_type'=>3,'uid' => $uid]);
            }
            $ratio = $level_info['ratio'].'%';
            runhook("Notify", "sendCustomMessage", ["messageType"=>"become_team","uid" => $uid,"become_time" => time(),'ratio'=>$ratio,'level_name'=>$level_info['level_name']]);//用户成为团队队长提醒
        }
        $res =$member->save($data,[
            'uid'=>$uid
        ]);
        return $res;
    }
    /**
     * 队长详情
     */
    public function getAgentInfo($uid)
    {
        $agent = new VslMemberModel();
        $result = $agent->getInfo(['uid' => $uid],"*");
        $pic = new UserModel();
        $result['pic'] = $pic->getInfo(['uid'=>$uid],'user_headimg')['user_headimg'];
        $result['user_name'] = $pic->getInfo(['uid'=>$uid],'user_name')['user_name'];
        $account = new VslBonusAccountModel();
        $bonus_info = $account->getInfo(['uid'=>$uid,'from_type'=>3],'*');
        $result['total_bonus'] = $bonus_info['total_bonus'];
        $result['grant_bonus'] = $bonus_info['grant_bonus'];
        $referee_id = $result['referee_id'];
        $result['apply_team_agent_time'] = $result['apply_team_agent_time'] ? date('Y-m-d H:i:s',$result['apply_team_agent_time']) :  date('Y-m-d H:i:s',$result['become_team_agent_time']);
        $result['become_team_agent_time'] = date('Y-m-d H:i:s',$result['become_team_agent_time']);
        $referee_info = $pic->getInfo(['uid' => $referee_id],"user_name,nick_name");
        if(empty($result['user_name'])){
            $result['user_name'] =$result['nick_name'];
        }
        if($referee_info['user_name']){
            $result['referee_name'] =$referee_info['user_name'];
        }else{
            $result['referee_name'] =$referee_info['nick_name'];
        }
        //系统表单
        if (getAddons('customform', $this->website_id)) {
            $addConSer = new AddonsConfigSer();
            $addinfo = $addConSer->getAddonsConfig('customform',$this->website_id);
            if ($addinfo['value']){
                $customSer = new CustomSer();
                $info = $customSer->getCustomData(1,10,6,'',['nm.uid'=>$uid]);
                $result['custom_list'] = $info;
            }
        }
        return $result;
    }
    /**
     * 修改队长资料
     */
    public function updateAgentInfo($data, $uid)
    {
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid'=>$uid]);
        if($data['team_agent_level_id']){
            $agent_level = new VslAgentLevelModel();
            $level_team_weight = $agent_level->getInfo(['id'=>$member_info['team_agent_level_id']],'weight')['weight'];
            $level_team_weights = $agent_level->getInfo(['id'=>$data['team_agent_level_id']],'weight')['weight'];
            if($level_team_weight){
                if($level_team_weights>$level_team_weight){
                    $data['up_team_level_time'] = time();
                    $data['down_up_team_level_time'] = '';
                }
            }
        }
        $retval = $member->save($data, [
            'uid' => $uid
        ]);
        return $retval;
    }

    /**
     * 申请成为队长
     */
    public function addAgentInfo($website_id,$uid,$post_data,$real_name)
    {
        $user = new VslMemberModel();
        $info = $this->getTeamBonusSite($website_id);

        $level = new AgentLevelModel();
        $level_info = $level->getInfo(['website_id'=>$website_id,'is_default'=>1,'from_type'=>3],'*');
        $level_id = $level_info['id'];
        $user_info = new UserModel();
        if(empty($real_name)){
            $real_name = $user_info->getInfo(['uid'=>$uid],'real_name')['real_name'];
        }
        $member_info = $user->getInfo(['uid'=>$uid]);
        //判断是否符合条件 而且主动申请
        if($info['teamagent_condition'] == 2 || $info['teamagent_condition'] == 1){
            if($info['teamagent_data'] != 1){
                return -1; //未开启主动申请
            }else if($info['teamagent_data'] == 1 && $member_info['is_team_agent'] !=3){
                //检测是否符合条件
                #客户需求 非主动申请的，提交资料也需要接受，所以当前检测去掉，但是不影响后续申请的操作判断
                // return -1; //未开启主动申请
            }
        }
        if($member_info['is_team_agent']==3){
            $data = array(
                "real_name"=>$real_name,
                "is_team_agent" => 2,
                "team_agent_level_id" => $level_id,
                "apply_team_agent_time" => time(),
                "become_team_agent_time" => time(),
                "custom_team"=>$post_data,
                'complete_datum_team'=>1
            );
            $ratio = $level_info['ratio'].'%';
            runhook("Notify", "sendCustomMessage", ["messageType"=>"become_team","uid" => $uid,"become_time" => time(),'ratio'=>$ratio,'level_name'=>$level_info['level_name']]);//用户成为团队队长提醒
        }else if($member_info['is_team_agent']==2){
            $data = array(
                "real_name"=>$real_name,
                "custom_team"=>$post_data,
                'complete_datum_team'=>1
            );
        }else{
            if($info['teamagent_check']==1){
                $data = array(
                    "real_name"=>$real_name,
                    "is_team_agent" => 2,
                    "team_agent_level_id" => $level_id,
                    "apply_team_agent_time" => time(),
                    "become_team_agent_time" => time(),
                    "custom_team"=>$post_data,
                    'complete_datum_team'=>1
                );
                $ratio = $level_info['ratio'].'%';
                runhook("Notify", "sendCustomMessage", ["messageType"=>"become_team","uid" => $uid,"become_time" => time(),'ratio'=>$ratio,'level_name'=>$level_info['level_name']]);//用户成为团队队长提醒
            }else{
                $data = array(
                    "real_name"=>$real_name,
                    "is_team_agent" => 1,
                    "team_agent_level_id" => $level_id,
                    "apply_team_agent_time" => time(),
                    "custom_team"=>$post_data,
                    'complete_datum_team'=>1
                );
                runhook("Notify", "sendCustomMessage", ["messageType"=>"apply_team","uid" => $uid,"apply_time" => time(),'level_name'=>$level_info['level_name']]);//用户申请成为团队队长提醒
            }
        }

        $result = $user->save($data, [
            'uid' => $uid
        ]);
        $account = new VslBonusAccountModel();
        $account_info = $account->getInfo(['website_id'=>$website_id,'from_type'=>3,'uid' => $uid]);
        if(empty($account_info)){
            $account->save(['website_id'=>$website_id,'from_type'=>3,'uid' => $uid]);
        }
        if($real_name && $result==1){
            $user = new UserModel();
            $user->save(['real_name'=>$real_name], ['uid' => $uid]);
        }
        return $result;
    }
    /**
     * 查询队长状态
     */
    public function getAgentStatus($uid)
    {
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid],"*");
        $result['status'] = $member_info['is_team_agent'];
        if($result['status']==2){
            $level = new AgentLevelModel();
            $result['level_name'] = $level->getInfo(['id'=>$member_info['team_agent_level_id']],'level_name')['level_name'];
        }
        return $result;
    }

    /**
     * 团队分红设置
     */
    public function setTeamBonusSite($teambonus_status,$agent_condition, $agent_conditions, $pay_money,$number,$one_number,$two_number,$three_number, $order_money, $agent_check, $agent_grade, $goods_id,$purchase_type,$gradation_status,$agent_data,$up_team_money,$level_award, $teamagent_delivery,$teambonus_admin_status)
    {
        $account = new VslBonusAccountModel();
        $user_account = $account->getInfo(['website_id'=>$this->website_id,'from_type'=>3,'ungrant_bonus'=>['>',0]])['ungrant_bonus'];
        if($user_account>0 && $teambonus_status==0){
            return -3;
        }
        $ConfigService = new AddonsConfigService();
        $value = array(
            'website_id' => $this->website_id,
            'teamagent_condition' => $agent_condition,
            'teamagent_conditions' => $agent_conditions,
            'pay_money' => $pay_money,
            'number' => $number,
            'one_number' => $one_number,
            'two_number' => $two_number,
            'three_number' => $three_number,
            'order_money' => $order_money,
            'teamagent_check' => $agent_check,
            'teamagent_grade' => $agent_grade,
            'goods_id' => $goods_id,
            'purchase_type' => $purchase_type,
            'gradation_status' => $gradation_status,
            'teamagent_data' => $agent_data,
            'up_team_money' => $up_team_money,
            'level_award' => $level_award,
            'teamagent_delivery' => $teamagent_delivery,
            'teambonus_admin_status' => $teambonus_admin_status,
        );

        $teambonus_info = $ConfigService->getAddonsConfig("teambonus");
        if (! empty($teambonus_info)) {
            $res = $ConfigService->updateAddonsConfig($value, "团队分红设置", $teambonus_status,"teambonus");
        } else {
            $res = $ConfigService->addAddonsConfig($value, "团队分红设置", $teambonus_status,"teambonus");
        }
        return $res;
    }
    /*
     * 获取团队分红基本设置
     *
     */
    public function getTeamBonusSite($website_id){
        $config = new AddonsConfigService();
        $teambonus = $config->getAddonsConfig("teambonus",$website_id);
        $teambonus_info = $teambonus['value'];
        $teambonus_info['is_use'] = $teambonus['is_use'];
        $goods_list = array();
        if($teambonus_info['goods_id']){
            $goods_ids = explode(",", $teambonus_info['goods_id']);
            foreach ($goods_ids as $key => $value) {
                $goods_info = $this->goods_ser->getGoodsDetailById($value, 'goods_id,price,max_buy,stock,picture', 1);
                $goods_info['goods_price'] = $goods_info['price'];
                $goods_info['goods_stock'] = $goods_info['stock'];
                $goods_info['goods_id'] = $value;
                $goods_info['pic'] = $goods_info['album_picture']['pic_cover_mid'];
                array_push($goods_list,$goods_info);
            }
            unset($value);
        }
        $teambonus_info['goods_list'] = $goods_list;
        return $teambonus_info;
    }
    /**
     * 分红结算设置
     */
    public function setSettlementSite($bonus_calculation, $limit_time,$withdrawals_check, $bonus_poundage,$poundage,$withdrawals_begin,$withdrawals_end,$limit_date)
    {
        $ConfigService = new ConfigService();
        $value = array(
            'website_id' => $this->website_id,
            'withdrawals_check' => $withdrawals_check,
            'bonus_calculation' => $bonus_calculation,
            'limit_time' => $limit_time,
            'limit_date' => $limit_date,
            'bonus_poundage' => $bonus_poundage,
            'poundage' => $poundage,
            'withdrawals_begin' => $withdrawals_begin,
            'withdrawals_end' => $withdrawals_end
        );
        $param = [
            'value' => $value,
            'website_id' => $this->website_id,
            'instance_id' => 0,
            'key' => "TEAMSETTLEMENT",
            'desc' => "团队分红结算设置",
            'is_use' => 1,
        ];
        $res = $ConfigService->setConfigOne($param);
        // TODO Auto-generated method stub
        return $res;
    }
    /*
      * 获取分红结算设置
      *
      */
    public function getSettlementSite($website_id){
        $config = new ConfigService();
        $teambonus_info = $config->getConfig(0,"TEAMSETTLEMENT",$website_id, 1);
        return $teambonus_info;
    }

    /**
     * 团队分红申请协议设置
     */
    public function setAgreementSite($type,$logo,$content,$withdrawals_team_bonus,$withdrawal_team_bonus,$frozen_team_bonus,$apply_team,$team_agreement)
    {
        $ConfigService = new ConfigService();
        $agreement_infos = $ConfigService->getConfig(0,"TEAMAGREEMENT", $this->website_id, 1);

        if($agreement_infos && $type==1){//文案
            $value = array(
                'logo' => $logo,
                'content' =>  $agreement_infos['content'],
                'withdrawals_team_bonus' => $withdrawals_team_bonus,
                'withdrawal_team_bonus' => $withdrawal_team_bonus,
                'frozen_team_bonus' => $frozen_team_bonus,
                'apply_team' => $apply_team,
                'team_agreement' => $team_agreement
            );
        }else if($agreement_infos && $type==2){
            $value = array(
                'logo' => $agreement_infos['logo'],
                'content' => $content,
                'withdrawals_team_bonus' => $agreement_infos['withdrawals_team_bonus'],
                'withdrawal_team_bonus' => $agreement_infos['withdrawal_team_bonus'],
                'frozen_team_bonus' => $agreement_infos['frozen_team_bonus'],
                'apply_team' => $agreement_infos['apply_team'],
                'team_agreement' => $agreement_infos['team_agreement']
            );
        }else{
            $value = array(
                'logo' => $logo,
                'content' =>  $content,
                'withdrawals_team_bonus' => $withdrawals_team_bonus,
                'withdrawal_team_bonus' => $withdrawal_team_bonus,
                'frozen_team_bonus' => $frozen_team_bonus,
                'apply_team' => $apply_team,
                'team_agreement' => $team_agreement
            );
        }

        if (! empty($agreement_infos)) {
            $data = array(
                "value" => json_encode($value),
                "instance_id" => 0,
                "website_id" => $this->website_id,
                "key" => "TEAMAGREEMENT",
                'is_use' => 1
            );

            $res = $ConfigService->setConfigOne($data);
        } else {

            $res = $ConfigService->addConfig(0, "TEAMAGREEMENT", $value, "团队分红申请协议", 1,$this->website_id);
        }
        return $res;
    }
    /*
      * 获取团队分红申请协议
      */
    public function getAgreementSite($website_id){
        $config = new ConfigService();
        $teambonus_info = $config->getConfig(0,"TEAMAGREEMENT",$website_id, 1);
        return $teambonus_info;
    }

    /*
     * 删除队长
     */
    public function deleteAgent($uid)
    {
        // TODO Auto-generated method stub
        $member = new VslMemberModel();
        $member->startTrans();
        try {
            // 删除队长信息
            $data = [
                'is_team_agent'=>0
            ];
            $member->save($data,['uid'=>$uid]);
            $member->commit();
            return 1;
        } catch (\Exception $e) {
            $member->rollback();
            return $e->getMessage();
        }
    }

    /*
     * 订单商品团队分红计算
     */
    public function orderAgentBonus($params)
    {

        $base_info = $this->getTeamBonusSite($params['website_id']);
        $set_info = $this->getSettlementSite($params['website_id']);
        $order_goods = new VslOrderGoodsModel();
        $order = new VslOrderModel();
        $order_info = $order->getInfo(['order_id'=>$params['order_id']],'order_no,bargain_id,group_id,presell_id,shop_id,shop_order_money,luckyspell_id');
        $order_goods_info = $order_goods->getInfo(['order_goods_id'=>$params['order_goods_id'],'order_id'=>$params['order_id']]);
        $cost_price = $order_goods_info['cost_price']*$order_goods_info['num'];//商品成本价
        $price = $order_goods_info['real_money'];//商品实际支付金额
        $amount = $order_goods_info['actual_price']*$order_goods_info['num'];//商品金额
        $promotion_price = $order_goods_info ['price']*$order_goods_info['num'];//商品销售价
        $original_price = $order_goods_info ['market_price']*$order_goods_info['num'];//商品原价
        // $profit_price = $promotion_price-$cost_price-$order_goods_info['profile_price']*1+$order_goods_info['adjust_money'];//商品利润价
        $profit_price = $price-$cost_price;//商品利润价
        if($profit_price<0){
            $profit_price = 0;
        }
        $goods_info = $this->goods_ser->getGoodsDetailById($order_goods_info['goods_id']);


        $addonsConfigService = new AddonsConfigService();
        $seckill = getAddons('seckill',$params['website_id']);
        $seckill_value =  $addonsConfigService ->getAddonsConfig("seckill",$params['website_id'], 0, 1);
        $seckill_bonus_val = json_decode($seckill_value['bonus_val'], true);
        $bargain = getAddons('bargain',$goods_info['website_id']);
        $bargain_value =  $addonsConfigService ->getAddonsConfig("bargain", $goods_info['website_id'], 0, 1);
        $bargain_bonus_val = json_decode($bargain_value['bonus_val'], true);
        $order_bargain_id = $order_info['bargain_id'];
        $groupshopping = getAddons('groupshopping',$params['website_id']);
        $groupshopping_value =  $addonsConfigService ->getAddonsConfig("groupshopping",$params['website_id'], 0, 1);
        $groupshopping_bonus_val = json_decode($groupshopping_value['bonus_val'], true); //拼图独立规则
        $groupshopping_goods_info = $order_info['group_id'];
        $presell_goods_info = $order_goods_info['presell_id'];
        $presell = getAddons('presell',$goods_info['website_id']);
        $presell_value =  $addonsConfigService ->getAddonsConfig("presell",$goods_info['website_id'], 0, 1);
        $presell_bonus_val = json_decode($presell_value['bonus_val'], true); //预售独立规则
        $team_bonus = '';
        $bargain_goods  = 0;
        $seckill_goods  = 0;
        $groupshopping_goods  = 0;
        $presell_goods  = 0;
        //edit by 2020/07/02 活动独立规则变更为与商品独立规则一致
        $luckyspell_id = $order_info['luckyspell_id'];
        $luckyspell = getAddons('luckyspell',$params['website_id']);
        $luckyspell_rule =  $addonsConfigService ->getAddonsConfig("luckyspell",$params['website_id']);
        $luckyspell_value = $luckyspell_rule['value'];
        $luckyspell_bonus_val = json_decode($luckyspell_value['bonus_val'],true); //拼图独立规则

        if($luckyspell==1 && $luckyspell_bonus_val && $luckyspell_bonus_val['is_team_bonus']==1 && $luckyspell_id > 0){//该商品参与幸运拼
            $groupshopping_goods  = 1;
            $groupshopping_value_rules = 0;
            $luckyspell_rule_val = json_decode(htmlspecialchars_decode($luckyspell_bonus_val['teambonus_val']), true);
            if($luckyspell_rule_val && $luckyspell_rule_val['teambonus_rule']==1){//有独立分红规则
                $goods_info['teambonus_rule_val'] = 1;
                $groupshopping_value_rules = $luckyspell_rule_val['teambonus_rule'];
                $level_bonus = $luckyspell_rule_val['team_bonus'];
                $level_bonus_arr = explode(';', $level_bonus);
                $level_bonus_val = [];
                foreach($level_bonus_arr as $bonus_ratio_info){
                    $bonus_ratio_arr = explode(':', $bonus_ratio_info);
                    $level_bonus_val[$bonus_ratio_arr[0]] = $bonus_ratio_arr[1];
                }
            }
            if($groupshopping_value_rules==1){//有独立分销规则
                $teambonus_rule_arr = $level_bonus_val;
            }
            //查询是否有活动独立结算节点
            if($luckyspell_bonus_val['team_bonus_choose'] == 1){
                $set_info['bonus_calculation'] = $luckyspell_bonus_val['team_bonus_calculation'];
            }
        }

        if($bargain==1 && $bargain_bonus_val && $bargain_bonus_val['is_team_bonus']==1 && $order_bargain_id){//砍价是否参与分销分红、分销分红规则
            $bargain_goods = 1;
            $bargain_value_rules = 0;
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($bargain_bonus_val['teambonus_val']), true);
            if($teambonus_rule_val && $teambonus_rule_val['teambonus_rule'] == 1){
                $goods_info['teambonus_rule_val'] = 1;
                $bargain_value_rules = $teambonus_rule_val['teambonus_rule'];
                $level_bonus = $teambonus_rule_val['team_bonus'];
                $level_bonus_arr = explode(';', $level_bonus);
                $level_bonus_val = [];
                foreach($level_bonus_arr as $bonus_ratio_info){
                    $bonus_ratio_arr = explode(':', $bonus_ratio_info);
                    $level_bonus_val[$bonus_ratio_arr[0]] = $bonus_ratio_arr[1];
                }
            }
            if($bargain_value_rules==1){//有独立分销规则
                $teambonus_rule_arr = $level_bonus_val;
            }
            //查询是否有活动独立结算节点
            if($bargain_bonus_val['team_bonus_choose'] == 1){
                $set_info['bonus_calculation'] = $bargain_bonus_val['team_bonus_calculation'];
            }
        }

        if($seckill==1 && $seckill_bonus_val && $seckill_bonus_val['is_team_bonus']==1 && $order_goods_info['seckill_id']){//该商品参与秒杀
            $seckill_goods  = 1;
            $seckill_value_rules = 0;
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($seckill_bonus_val['teambonus_val']), true);
            if($teambonus_rule_val && $teambonus_rule_val['teambonus_rule']==1){//有独立分红规则
                $goods_info['teambonus_rule_val'] = 1;
                $seckill_value_rules = $teambonus_rule_val['teambonus_rule'];
                $level_bonus = $teambonus_rule_val['team_bonus'];
                $level_bonus_arr = explode(';', $level_bonus);
                $level_bonus_val = [];
                foreach($level_bonus_arr as $bonus_ratio_info){
                    $bonus_ratio_arr = explode(':', $bonus_ratio_info);
                    $level_bonus_val[$bonus_ratio_arr[0]] = $bonus_ratio_arr[1];
                }
            }
            if($seckill_value_rules==1){//有独立分销规则
                $teambonus_rule_arr = $level_bonus_val;
            }
            //查询是否有活动独立结算节点
            if($seckill_bonus_val['team_bonus_choose'] == 1){
                $set_info['bonus_calculation'] = $seckill_bonus_val['team_bonus_calculation'];
            }
        }
        //groupshopping_bonus_val
        if($groupshopping==1 && $groupshopping_bonus_val && $groupshopping_bonus_val['is_team_bonus']==1 && $groupshopping_goods_info){//该商品参与拼团
            $groupshopping_goods  = 1;
            $groupshopping_value_rules = 0;
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($groupshopping_bonus_val['teambonus_val']), true);
            if($teambonus_rule_val && $teambonus_rule_val['teambonus_rule']==1){//有独立分红规则
                $goods_info['teambonus_rule_val'] = 1;
                $groupshopping_value_rules = $teambonus_rule_val['teambonus_rule'];
                $level_bonus = $teambonus_rule_val['team_bonus'];
                $level_bonus_arr = explode(';', $level_bonus);
                $level_bonus_val = [];
                foreach($level_bonus_arr as $bonus_ratio_info){
                    $bonus_ratio_arr = explode(':', $bonus_ratio_info);
                    $level_bonus_val[$bonus_ratio_arr[0]] = $bonus_ratio_arr[1];
                }
            }
            if($groupshopping_value_rules==1){//有独立分销规则
                $teambonus_rule_arr = $level_bonus_val;
            }
            //查询是否有活动独立结算节点
            if($groupshopping_bonus_val['team_bonus_choose'] == 1){
                $set_info['bonus_calculation'] = $groupshopping_bonus_val['team_bonus_calculation'];
            }
        }
        //presell_bonus_val
        if($presell==1 && $presell_bonus_val && $presell_bonus_val['is_team_bonus']==1 && $presell_goods_info){//该商品参与预售
            $presell_goods  = 1;
            $presell_value_rules = 0;
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($presell_bonus_val['teambonus_val']), true);
            if($teambonus_rule_val && $teambonus_rule_val['teambonus_rule']==1){//有独立分红规则
                $goods_info['teambonus_rule_val'] = 1;
                $presell_value_rules = $teambonus_rule_val['teambonus_rule'];
                $level_bonus = $teambonus_rule_val['team_bonus'];
                $level_bonus_arr = explode(';', $level_bonus);
                $level_bonus_val = [];
                foreach($level_bonus_arr as $bonus_ratio_info){
                    $bonus_ratio_arr = explode(':', $bonus_ratio_info);
                    $level_bonus_val[$bonus_ratio_arr[0]] = $bonus_ratio_arr[1];
                }
            }
            if($presell_value_rules==1){//有独立分销规则
                $teambonus_rule_arr = $level_bonus_val;
            }
            //查询是否有活动独立结算节点
            if($presell_bonus_val['team_bonus_choose'] == 1){
                $set_info['bonus_calculation'] = $presell_bonus_val['team_bonus_calculation'];
            }
        }

        if($goods_info['is_bonus_team']==1){//该商品参与团队分红
            //查询是否启用独立结算节点 $set_info['bonus_calculation']
            if($goods_info['team_bonus_choose'] == 1){
                $set_info['bonus_calculation'] = $goods_info['team_bonus_calculation'];
            }
            $teambonus_rule_val = json_decode(htmlspecialchars_decode($goods_info['teambonus_rule_val']), true);

            if($teambonus_rule_val && $teambonus_rule_val['teambonus_rule']==1){//有独立分红规则
                $team_bonus = '';
                $teambonus_rule_arr = getBonusRatio($teambonus_rule_val);
            }
        }
        //如果该商品是店铺独立商品 ，由于默认是不开启 ，之前已开启参与产品如果没有设置独立分销则默认为0
        //获取是否开启店铺佣金
        $teamStatusAdmin = $this->getTeamBonusSite($goods_info['website_id']);
        $teambonus_admin_status = $teamStatusAdmin['teambonus_admin_status'];

        if($teambonus_admin_status == 0 && $goods_info['shop_id']){
            $goods_info['bonus_rule'] = 0;
            $goods_info['is_bonus_team'] = 1;
            $team_bonus = '';
            // return;//未开启直接返回
        }

        if($teambonus_admin_status == 1 && $goods_info['shop_id'] > 0 && $teambonus_rule_val['bonus_rule'] == 2){
            return 1; //开启店铺分红，但是没有设置独立规则
        }
        if($teambonus_admin_status == 1){
            $shop_id = $goods_info['shop_id'] ? $goods_info['shop_id'] : $order_info['shop_id'];
        }else{
            $shop_id = 0;
        }


        $poundage = $set_info['bonus_poundage']/100;//分红比例
        $member = new VslMemberModel();
        $buyer_info = $member->getInfo(['uid'=>$params['buyer_id'],'isdistributor'=>2],'*');
        $arr =[];
        $agent_data = $this->get_parent_id($arr,$params['buyer_id'],1);//一条线上的队长信息
        $level = new AgentLevelModel();
        if($agent_data){
            if($goods_info['is_bonus_team']==1 || $seckill_goods==1 || $groupshopping_goods==1 || $presell_goods==1 || $bargain_goods==1) {
                $team_bonus_arr = [];
                $all_bonus = 0;
                if ($base_info['is_use'] == 1 && $base_info['gradation_status'] == 2) {//是否开启团队分红并且没有开启级差
                    $bonus_calculation = $set_info['bonus_calculation'];//计算节点（商品价格）
                    foreach ($agent_data['agent_list'] as $k => $v) {
                        if ($v == $params['buyer_id']) {
                            if ($base_info['purchase_type'] == 2) {//未开启内购但当前购买者是队长
                                continue;
                            }
                        }
                        $level_id = $member->getInfo(['uid' => $v, 'is_team_agent' => 2], 'team_agent_level_id')['team_agent_level_id'];//等级id
                        $number = $member->getCount(['team_agent_level_id' => $level_id,'isdistributor'=>2, 'is_team_agent' => 2, 'uid' => ['in', implode(',', $agent_data['agent_list'])]]);//对应等级的人数
                        if($number>1 && $level_id==$buyer_info['team_agent_level_id'] && $buyer_info['is_team_agent']==2 && $base_info['purchase_type'] == 2){
                            $number = $number-1;
                        }
                        $level_res = $level->getInfo(['id' => $level_id], 'ratio, bonus_type, money');
                        if($goods_info['is_bonus_team']==1 && !empty($goods_info['teambonus_rule_val'])){//该商品参与团队分红
                            if($teambonus_rule_val['teambonus_rule'] == 1){//开启了商品团队独立分红
                                if($teambonus_rule_val['bonus_type'] == 1){
                                    $real_ratio = $teambonus_rule_arr[$level_id] / 100;
                                    if ($bonus_calculation == 1) {//实际付款金额
                                        if($presell_goods_info){
                                            $price = $promotion_price;
                                        }
                                        $data['bonus'] = twoDecimal($price * $real_ratio * $poundage / $number);
                                    }
                                    if ($bonus_calculation == 2) {//商品原价
                                        $data['bonus'] = twoDecimal($original_price * $real_ratio* $poundage / $number);
                                    }
                                    if ($bonus_calculation == 3) {//商品销售价
                                        $data['bonus'] = twoDecimal($promotion_price * $real_ratio * $poundage / $number);
                                    }
                                    if ($bonus_calculation == 4) {//商品成本价
                                        $data['bonus'] = twoDecimal($cost_price * $real_ratio * $poundage / $number);
                                    }
                                    if ($bonus_calculation == 5) {//商品利润价
                                        $data['bonus'] = twoDecimal($profit_price * $real_ratio * $poundage / $number);
                                    }
                                }else{
                                    $real_money = $teambonus_rule_arr[$level_id];
                                    $data['bonus'] = twoDecimal($real_money / $number);
                                }
                            }
                        }else{
                            if($level_res['bonus_type'] == 1){ //第一种是按照比例来算
                                $ratio = $level_res['ratio'] / 100;
                                if($team_bonus!=''){
                                    $real_ratio = $team_bonus/100;//分红比例
                                }else{
                                    $real_ratio = $ratio;
                                }
                                if ($bonus_calculation == 1) {//实际付款金额
                                    if($presell_goods_info){
                                        $price = $promotion_price;
                                    }
                                    $data['bonus'] = twoDecimal($price * $real_ratio * $poundage / $number);
                                }
                                if ($bonus_calculation == 2) {//商品原价
                                    $data['bonus'] = twoDecimal($original_price * $real_ratio* $poundage / $number);
                                }
                                if ($bonus_calculation == 3) {//商品销售价
                                    $data['bonus'] = twoDecimal($promotion_price * $real_ratio * $poundage / $number);
                                }
                                if ($bonus_calculation == 4) {//商品成本价
                                    $data['bonus'] = twoDecimal($cost_price * $real_ratio * $poundage / $number);
                                }
                                if ($bonus_calculation == 5) {//商品利润价
                                    $data['bonus'] = twoDecimal($profit_price * $real_ratio * $poundage / $number);
                                }
                            }elseif($level_res['bonus_type'] == 2){//第二种按照 固定金额来算
                                $data['bonus'] = twoDecimal($level_res['money']/$number);
                            }
                        }
                        $team_bonus_arr[$v]['bonus'] = !$team_bonus_arr[$v] ? $data['bonus'] : $team_bonus_arr[$v]['bonus'] + $data['bonus'];
                        $team_bonus_arr[$v]['level_award'] = 0;
                        $team_bonus_arr[$v]['tips'] = '_'.$v.'_';//加个标记方便后面查询用户分红
                        $all_bonus += $data['bonus'];
//                        $data1 = [
//                            'order_id' => $params['order_id'],
//                            'order_goods_id' => $params['order_goods_id'],
//                            'buyer_id' => $order_goods_info['buyer_id'],
//                            'website_id' => $params['website_id'],
//                            'bonus' => $data['bonus'],
//                            'from_type' => 3,
//                            'uid' => $v,
//                            'shop_id' => $shop_id
//                        ];
//                        array_push($insert_data,$data1);
                    }
                    unset($v);
                }
                if ($base_info['is_use'] == 1 && $base_info['gradation_status'] == 1) {//是否开启团队分红并且开启级差
                    //新增价格极差分红逻辑
                    if($teambonus_rule_val['bonus_type'] == 3){
                        //开启级差 并且未开启内购 并且本人是队长 需要去除队长本人
                        if(count($agent_data['level_info']) > 1 && $base_info['purchase_type'] == 2 && $buyer_info['is_team_agent'] == 2){
                            array_shift($agent_data['level_info']);
                            array_shift($agent_data['level_id']);
                            array_shift($agent_data['weight']);
                        }

                        $team_bonus_list = [];
                        $insertData = [];
                        if(!empty($goods_info['teambonus_rule_val'])){
                            $goods_info['teambonus_rule_val'] = json_decode(htmlspecialchars_decode($goods_info['teambonus_rule_val']), true);
                            $level_bonus = $goods_info['teambonus_rule_val']['team_bonus'];
                            $level_bonus_arr = explode(';', $level_bonus);
                            $level_bonus_val = [];
                            foreach($level_bonus_arr as $level_bonus_info){
                                $agent_level_arr = explode(':', $level_bonus_info);
                                $level_bonus_val[$agent_level_arr[0]] = $agent_level_arr[1];
                            }
//                            $amount = $price;

                            $curr_rate = 0;
                            $all_bonus = 0;
                            foreach ($agent_data['level_info'] as $member){
                                if(!isset($level_bonus_val[$member['team_agent_level_id']]) || $level_bonus_val[$member['team_agent_level_id']] <= 0){
                                    continue;
                                }
                                $r = $level_bonus_val[$member['team_agent_level_id']];
                                $ur = $r - $curr_rate;
                                $commission = round(($amount * $ur / 100), 2);
                                if($commission > 0){
                                    $team_bonus_list[] = [
                                        'uid'=> $member['uid'],
                                        'amount'=> $amount,
                                        'distributor_amount'=> $r,
                                        'distributor_level_id'=> $member['team_agent_level_id'],
                                        'distributor_level_name'=> $member['team_agent_level_name'],
                                        'commission'=> $commission
                                    ];
                                    $all_bonus += $commission;

                                    $records_no = 'TBS' . time() . rand(111, 999);
                                    //添加团队分红日志
                                    $data_records = array(
                                        'uid' => $member['uid'],
                                        'data_id' => $order_info['order_no'],
                                        'website_id' => 1,
                                        'records_no' => $records_no,
                                        'bonus' => abs($commission),
                                        'text' => '订单支付,冻结极差分红增加',
                                        'create_time' => time(),
                                        'bonus_type' => 3, //团队分红
                                        'from_type' => 3, //订单支付成功
                                    );
                                    array_push($insertData, $data_records);

                                    $curr_rate = $r;
                                }
                            }
                        }

                        // 查询分红
//                        $goodDiscount = new GoodsDiscount();
//                        $d_condition = [
//                            'goods_id' => $order_goods_info['goods_id'],
//                            'type' => 1,
//                            'website_id' => $params['website_id'],
//                        ];
//                        $discountRes = $goodDiscount->getInfo($d_condition);
//                        $good_discount = json_decode($discountRes['value'], true);
//                        $member_level_price = $good_discount['distributor_obj']['d_level_data'];
//
//                        //商品价格
//                        $team_bonus_list = [];
//                        $insertData = [];
//                        $amount = $price;
//                        $all_bonus = 0;
//                        foreach ($agent_data['level_info'] as $member){
//                            $distributor_amount = $member_level_price[$member['distributor_level_id']]['val'];
//                            $commission =  $amount - $distributor_amount;
//                            if($commission > 0){
//                                $team_bonus_list[] = [
//                                    'uid'=> $member['uid'],
//                                    'amount'=> $amount,
//                                    'distributor_amount'=> $member_level_price[$member['distributor_level_id']]['val'],
//                                    'distributor_level_id'=> $member['distributor_level_id'],
//                                    'distributor_level_name'=> $member_level_price[$member['distributor_level_id']]['name'],
//                                    'commission'=> $amount - $distributor_amount
//                                ];
//                                $amount = $distributor_amount;
//                                $all_bonus += $commission;
//
//                                $records_no = 'TBS' . time() . rand(111, 999);
//                                //添加团队分红日志
//                                $data_records = array(
//                                    'uid' => $member['uid'],
//                                    'data_id' => $order_info['order_no'],
//                                    'website_id' => $params['website_id'],
//                                    'records_no' => $records_no,
//                                    'bonus' => abs($commission),
//                                    'text' => '订单支付,冻结极差分红增加',
//                                    'create_time' => time(),
//                                    'bonus_type' => 3, //团队分红
//                                    'from_type' => 3, //订单支付成功
//                                );
//                                array_push($insertData, $data_records);
//                            }
//                        }

                        //创建对应数据
                        if(count($team_bonus_list) > 0){
                            $time = time();
                            $bonus = new VslOrderTeamLogModel();
                            $agentAccountRecordsModel = new VslAgentAccountRecordsModel();
                            $bonus->startTrans();
                            try{
                                //添加检验已写入则不能重复写入 已uid，uid，from_type，order_id，order_goods_id，bonus
                                $where['order_goods_id'] = $params['order_goods_id'];
                                $where['website_id'] = $params['website_id'];
                                $checkBonus = $bonus->getInfo($where);
                                if($checkBonus && $checkBonus['team_bonus_details']){
                                    $bonus->commit();
                                    return 1; //有重复数据 跳出本次循环
                                }

                                if(!$checkBonus){
                                    //添加执行记录
                                    $insert_log = [
                                        'order_id'=> $params['order_id'],
                                        'order_goods_id'=> $params['order_goods_id'],
                                        'buyer_id'=> $params['buyer_id'],
                                        'website_id'=> $params['website_id'],
                                        'team_bonus'=> $all_bonus,
                                        'team_bonus_details'=> json_encode($team_bonus_list,true),
                                        'team_pay_status'=> 1,
                                        'team_cal_status'=> 0,
                                        'create_time'=> $time
                                    ];
                                    $bonus->save($insert_log);
                                    $agentAccountRecordsModel->saveAll($insertData);
                                }else{
                                    $bonus->save(['team_bonus' => $all_bonus,'team_bonus_details' => json_encode($team_bonus_list,true), 'update_time' => time()],['id' => $checkBonus['id']]);
                                }
                                $bonus->commit();
                                return 1;
                            }catch (\Exception $e) {
                                $bonus->rollback();
                                return $e->getMessage();
                            }
                        }
                    }else{
                        //开启级差 并且未开启内购 并且本人是队长 需要去除队长本人
                        if(count($agent_data['level_info']) > 1 && $base_info['purchase_type'] == 2 && $buyer_info['is_team_agent'] == 2){

                            array_shift($agent_data['level_info']);
                            array_shift($agent_data['level_id']);
                            array_shift($agent_data['weight']);
                        }
                        $bonus_calculation = $set_info['bonus_calculation'];//计算节点（商品价格）
                        $arr = [];
                        foreach ($agent_data['level_id'] as $k1 => $v1){
                            $arr[] = $v1;
                        }
                        $arr = array_unique($arr); //去重
                        $arr1 = array_values($arr);
                        $key = array_keys($arr);
                        $top = 1;
                        $arr2 = array(); //平级奖数组
                        $new_array = array();
                        $checks_weight = 9999;
                        $next_level_id = '';
                        foreach ($agent_data['level_info'] as $k => $v) {
                            if(in_array($k,$key)){
                                $real_uid = $v['uid'];

                                if ($v['uid'] == $params['buyer_id']) {
                                    if ($base_info['purchase_type'] == 2) {//未开启内购
                                        continue;
                                    }
                                }
                                $level_info = $level->getInfo(['id' => $v['team_agent_level_id'], 'from_type' => 3], '*');
                                $ratio = $level_info['ratio'];//当前比例
                                $weight = $level_info['weight'];//当前比例权重

                                if($team_bonus!=''){
                                    $ratio = $team_bonus;//分红比例
                                }
                                $now_key = array_search($arr[$k],$arr1);
                                $prev = 0;
                                if($now_key>=1){
                                    //下级id应变更为上一次获取级差分红的用户 edit for 2020/05/28
                                    $prev = $next_level_id ? $next_level_id :  $arr1[$now_key-1];
                                    // $prev = $arr1[$now_key-1];
                                }
                                $bonus_type1 = '';
                                $bonus_type2 = '';
                                $bonus_money = 0;
                                $bonus_type = 0;
                                if($prev){
                                    $lower_ratio = '';
                                    $lower_ratio = $level->getInfo(['id'=>$prev]);//下级比例
                                    $lower_weight = $lower_ratio['weight'];//当前下级比例权重
                                    if($weight>$lower_weight){
                                        $checks_weight = $weight;
                                        //判断是否有商品独立分红方式
                                        if($goods_info['is_bonus_team']==1 && !empty($goods_info['teambonus_rule_val'])){//该商品参与团队分红
                                            if($teambonus_rule_val['teambonus_rule'] == 1){//开启了商品团队独立分红
                                                $prev_level_id = $prev;
                                                $level_id = $level_info['id'];
                                                $goods_teambonus_money1 = 0;
                                                $goods_teambonus_money2 = 0;
                                                $goods_teambonus_ratio1 = 0;
                                                $goods_teambonus_ratio2 = 0;
                                                if($teambonus_rule_val['bonus_type'] == 2){
                                                    $bonus_type1 = 2;
                                                    $bonus_type2 = 2;
                                                    $goods_teambonus_money1 = (float)$teambonus_rule_arr[$level_id];
                                                    $goods_teambonus_money2 = (float)$teambonus_rule_arr[$prev_level_id];
                                                }else{
                                                    $goods_teambonus_ratio1 = (float)$teambonus_rule_arr[$level_id];
                                                    $goods_teambonus_ratio2 = (float)$teambonus_rule_arr[$prev_level_id];
                                                }
                                            }
                                        }else{
                                            //判断等级分红方式
                                            $bonus_type1 = $level_info['bonus_type'];
                                            $bonus_type2 = $lower_ratio['bonus_type'];
                                        }
                                        //判断是否
                                        if($bonus_type1 == 2 || $bonus_type2 == 2){//只要有一个比例是固定金额方式，固定金额 - 百分比转换的金额
                                            $bonus_type = 2;
                                            if($bonus_type1 == 2){
                                                if($goods_teambonus_money1 != '' || $goods_teambonus_money1 === (float)0){
                                                    $money1 = $goods_teambonus_money1;
                                                }else{
                                                    $money1 = $level_info['money'];
                                                }
                                            }else{
                                                if($goods_teambonus_ratio1 != '' || $goods_teambonus_ratio1 === (float)0){
                                                    $grade_ratio = $goods_teambonus_ratio1 / 100;
                                                }else{
                                                    $grade_ratio = $level_info['ratio'] / 100;
                                                }
                                                if ($bonus_calculation == 1) {//实际付款金额
                                                    if($presell_goods_info){
                                                        $price = $promotion_price;
                                                    }
                                                    $money1 = twoDecimal($price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 2) {//商品原价
                                                    $money1 = twoDecimal($original_price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 3) {//商品销售价
                                                    $money1 = twoDecimal($promotion_price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 4) {//商品成本价
                                                    $money1 = twoDecimal($cost_price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 5) {//商品利润价
                                                    $money1 = twoDecimal($profit_price * $grade_ratio * $poundage);
                                                }
                                            }

                                            if($bonus_type2 == 2){
                                                if($goods_teambonus_money2 != '' || $goods_teambonus_money2 === (float)0){
                                                    $money2 = $goods_teambonus_money2;
                                                }else{
                                                    $money2 = $lower_ratio['money'];
                                                }
                                            }else{
                                                if($goods_teambonus_ratio2 != '' || $goods_teambonus_ratio2 === (float)0){
                                                    $grade_ratio = $goods_teambonus_ratio2 / 100;
                                                }else{
                                                    $grade_ratio = $lower_ratio['ratio'] / 100;
                                                }
                                                if ($bonus_calculation == 1) {//实际付款金额
                                                    if($presell_goods_info){
                                                        $price = $promotion_price;
                                                    }
                                                    $money2 = twoDecimal($price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 2) {//商品原价
                                                    $money2 = twoDecimal($original_price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 3) {//商品销售价
                                                    $money2 = twoDecimal($promotion_price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 4) {//商品成本价
                                                    $money2 = twoDecimal($cost_price * $grade_ratio * $poundage);
                                                }
                                                if ($bonus_calculation == 5) {//商品利润价
                                                    $money2 = twoDecimal($profit_price * $grade_ratio * $poundage);
                                                }
                                            }
                                            if($money1 > $money2){
                                                $bonus_money = $money1 - $money2;
                                            }else{
                                                $bonus_money = 0;
                                            }
                                        }else{
                                            if($goods_teambonus_ratio2 != '' || $goods_teambonus_ratio2 === (float)0){
                                                $ratio = $goods_teambonus_ratio1;
                                            }
                                            if($goods_teambonus_ratio1 != '' || $goods_teambonus_ratio1 === (float)0){
                                                $lower_ratio['ratio'] = $goods_teambonus_ratio2;
                                            }
                                            if($ratio> $lower_ratio['ratio']){//只要上级比下级小 就不拿
                                                $real_ratio = ($ratio-$lower_ratio['ratio'])/100;//当前比例减去下级比例
                                            }else{
                                                $real_ratio = 0;
                                            }
                                            if($team_bonus!=''){
                                                $real_ratio = 0;//存在独立分红比例
                                            }
                                        }
                                    }else{
                                        continue;
                                    }
                                }else{
                                    $checks_weight = $weight; //当本人是团队分红的时候
                                    //判断是否有商品独立分红方式
                                    if($goods_info['is_bonus_team']==1 && !empty($goods_info['teambonus_rule_val'])){//该商品参与团队分红
                                        if($teambonus_rule_val['teambonus_rule'] == 1){//开启了商品团队独立分红
                                            $level_id = $level_info['id'];
                                            $goods_teambonus_money1 = 0;
                                            $goods_teambonus_ratio1 = 0;
                                            if($teambonus_rule_val['bonus_type'] == 2){
                                                $bonus_type1 = 2;
                                                $goods_teambonus_money2 = (float)$teambonus_rule_arr[$level_id];
                                            }else{
                                                $goods_teambonus_ratio2 = (float)$teambonus_rule_arr[$level_id];
                                            }
                                        }
                                    }else{
                                        //判断等级分红方式
                                        $bonus_type1 = $level_info['bonus_type'];
                                    }
                                    $bonus_type = '';
                                    if($bonus_type1 == 2){
                                        $bonus_type = 2;
                                        if($goods_teambonus_money2 != '' || $goods_teambonus_money2 === (float)0){
                                            $bonus_money = $goods_teambonus_money2 > 0 ? $goods_teambonus_money2 : 0;
                                        }else{
                                            $bonus_money = $level_info['money'] > 0 ? $level_info['money'] : 0;
                                        }
                                    }else{
                                        if($goods_teambonus_ratio2 != '' || $goods_teambonus_ratio2 === (float)0){
                                            $real_ratio = $goods_teambonus_ratio2/100;//下级比例不存在
                                        }else{
                                            $real_ratio = $ratio/100;//下级比例不存在
                                        }
                                    }
                                    if(max($agent_data['weight'])==$weight){
                                        //最后一级权重 获取剩余会员
                                        if($k != end($agent_data['level_info'])) {
                                            // 不是最后一项
                                            //拆分数组
                                            $new_array = array_chunk($agent_data['level_info'], $k+1);

                                        }
                                        $top = 2;
                                    }
                                }
                                $next_level_id = $v['team_agent_level_id'];
                                if($bonus_type == 2){
                                    $data['bonus'] = $bonus_money;
                                }else{
                                    if ($bonus_calculation == 1) {//实际付款金额
                                        if($presell_goods_info){
                                            $price = $promotion_price;
                                        }
                                        $data['bonus'] = twoDecimal($price * $real_ratio * $poundage);
                                    }
                                    if ($bonus_calculation == 2) {//商品原价
                                        $data['bonus'] = twoDecimal($original_price * $real_ratio * $poundage);
                                    }
                                    if ($bonus_calculation == 3) {//商品销售价
                                        $data['bonus'] = twoDecimal($promotion_price * $real_ratio * $poundage);
                                    }
                                    if ($bonus_calculation == 4) {//商品成本价
                                        $data['bonus'] = twoDecimal($cost_price * $real_ratio * $poundage);
                                    }
                                    if ($bonus_calculation == 5) {//商品利润价
                                        $data['bonus'] = twoDecimal($profit_price * $real_ratio * $poundage);
                                    }
                                }
                                $team_bonus_arr[$real_uid]['bonus'] = !$team_bonus_arr[$real_uid] ? $data['bonus'] : $team_bonus_arr[$real_uid]['bonus'] + $data['bonus'];
                                $team_bonus_arr[$real_uid]['level_award'] = 0;
                                $team_bonus_arr[$real_uid]['tips'] = '_'.$real_uid.'_';//加个标记方便后面查询用户分红
                                $all_bonus += $data['bonus'];
    //                            $data1 = [
    //                                'order_id' => $params['order_id'],
    //                                'order_goods_id' => $params['order_goods_id'],
    //                                'buyer_id' => $order_goods_info['buyer_id'],
    //                                'website_id' => $params['website_id'],
    //                                'bonus' => $data['bonus'],
    //                                'from_type' => 3,
    //                                'uid' => $real_uid,
    //                                'shop_id' => $shop_id
    //                            ];
    //                            array_push($insert_data,$data1);
                                // $bonus->save($data1);
                                // $bonus->commit();
                                if($top == 2){
                                   break;
                                }
                            }else{
                                //开启内购 而且本人是队长 上级跟本人同级 需先去除该上级
                                $check_uid = 0;
                                //获取购买者本人信息
                                $buyer_info2 = $member->getInfo(['uid'=>$params['buyer_id']],'referee_id,team_agent_level_id');

                                $weight1 = -1;
                                $weight2 = -1;
                                if($buyer_info2['referee_id'] && $buyer_info2['team_agent_level_id']){
                                    //获取本人级别
                                    $check_info1 = $level->getInfo(['id' => $buyer_info2['team_agent_level_id'], 'from_type' => 3], 'weight');
                                    $weight1 = $check_info1['weight'];
                                    $check_user_info2 = $member->getInfo(['uid'=>$buyer_info2['referee_id']],'team_agent_level_id');
                                    if($check_user_info2 && $check_user_info2['team_agent_level_id']){
                                        $check_info2 = $level->getInfo(['id' => $check_user_info2['team_agent_level_id'], 'from_type' => 3], 'weight');
                                        $weight2 = $check_info2['weight'];
                                    }
                                }
                                if($weight1 >= 0 && $weight2 >= 0 && $weight1 == $weight2 && $base_info['purchase_type'] == 2){
                                    $check_uid = $buyer_info2['referee_id'];
                                }



                                //组装平级奖待发放人员
                                $arr_data = array(
                                    'uid'=>$v['uid'],
                                    'weight'=>$agent_data['weight'][$k],
                                    'level_id'=>$agent_data['level_id'][$k],
                                );
                                //edit for 2020/04/26 连续跟上级等级一致才写入，否则不写入

                                if($check_uid != $v['uid'] && $agent_data['weight'][$k] >= $checks_weight){
                                    array_push($arr2,$arr_data);
                                }
                                //级差团队外 开始统计3级平级奖 -- 仅限3级内 --  团队id? 权重？
                                continue;
                            }
                        }
                        unset($v);

                        if($new_array && count($new_array) > 1){

                            foreach ($new_array as $key_f => $value_f) {
                                if($key_f == 0){
                                    continue;
                                }
                                foreach ($value_f as $keys => $values) {
                                    //获取对应等级信息
                                    $check_user_info1 = $level->getInfo(['id' => $values['team_agent_level_id'], 'from_type' => 3], 'weight');
                                    $arr_data = array(
                                        'uid'=>$values['uid'],
                                        'weight'=>$check_user_info1['weight'],
                                        'level_id'=>$values['team_agent_level_id'],
                                    );

                                    if($check_user_info1['weight'] >= $weight){
                                        array_push($arr2,$arr_data);
                                    }
                                }
                            }

                        }

                        if($arr2 && count($arr2) > 0 && $base_info['level_award']){ //开始处理平级奖
                            //获取当前价格
                            if ($bonus_calculation == 1) {//实际付款金额
                                if($presell_goods_info){
                                    $price = $promotion_price;
                                }
                                $price = $price;
                            }
                            if ($bonus_calculation == 2) {//商品原价
                                $price = $original_price;
                            }
                            if ($bonus_calculation == 3) {//商品销售价
                                $price = $promotion_price;
                            }
                            if ($bonus_calculation == 4) {//商品成本价
                                $price = $cost_price;
                            }
                            if ($bonus_calculation == 5) {//商品利润价
                                $price = $profit_price;
                            }
                            //重新组装数组,每个等级保留前3个
                            $res = array();
                            foreach($arr2 as $v) {
                                    $res[$v['level_id']][] = $v;
                            }
                            $send_array = array();

                            foreach ($res as $key => $value) {
                                $save_array = array_chunk($value, 3);
                                array_push($send_array,$save_array[0]);
                            }

                            foreach ($send_array as $key_s => $value_s) {
                                foreach ($value_s as $key_i => $value_i) {
                                    $this_level_info = $level->getInfo(['id' => $value_i['level_id'], 'from_type' => 3], 'level_award1,level_award2,level_award3,level_money1,level_money2,level_money3,bonus_type');


                                    if($this_level_info['bonus_type'] == 1){//第一种方式比例方式
                                        $level_award_ratio = $key_i == 0 ? $this_level_info['level_award1'] : ( $key_i == 1 ? $this_level_info['level_award2'] : $this_level_info['level_award3'] );
                                        $peer_bonus = twoDecimal($price * $level_award_ratio / 100); //平级奖奖励
                                    }else{
                                        $peer_bonus = $key_i == 0 ? ($this_level_info['level_money1'] > 0 ? $this_level_info['level_money1'] : 0) : ( $key_i == 1 ? ($this_level_info['level_money2'] > 0 ? $this_level_info['level_money2'] : 0) : ($this_level_info['level_money3'] > 0 ? $this_level_info['level_money3'] : 0) ); //平级奖奖励
                                    }
                                    $check_level_award = $key_i + 1;
                                    if($base_info['level_award'] < $check_level_award){
                                        continue;
                                    }
                                    if($peer_bonus > 0){
                                        $all_bonus += $peer_bonus;
                                    }
                                    // $team_bonus_arr[$value_i['uid']]['bonus'] = !$team_bonus_arr[$value_i['uid']] ? $data['bonus'] : $team_bonus_arr[$value_i['uid']]['bonus'] + $data['bonus'];
                                    //edit for 拉登 这个peer_bonus为平级奖金额
                                    $team_bonus_arr[$value_i['uid']]['bonus'] = $peer_bonus;
                                    $team_bonus_arr[$value_i['uid']]['level_award'] = $key_i + 1;
                                    $team_bonus_arr[$value_i['uid']]['tips'] = '_'.$value_i['uid'].'_';//加个标记方便后面查询用户分红
                                    //加多一个标识，标识是否平级奖
                                    //                                $data4 = [
                                    //                                    'order_id' => $params['order_id'],
                                    //                                    'order_goods_id' => $params['order_goods_id'],
                                    //                                    'buyer_id' => $order_goods_info['buyer_id'],
                                    //                                    'website_id' => $params['website_id'],
                                    //                                    'bonus' => $peer_bonus,
                                    //                                    'from_type' => 3,
                                    //                                    'uid' => $value_i['uid'],
                                    //                                    'level_award' => $key_i + 1,
                                    //                                    'shop_id' => $shop_id
                                    //                                ];
                                    //                                array_push($insert_data,$data4);
                                }
                                unset($value_i);
                            }
                            unset($value_s);

                        }
                    }
                }
            }
        }

        //开始批处理 ---  旧逻辑分红
        if($team_bonus_arr && $teambonus_rule_val['bonus_type'] < 3){
            $bonus = new VslOrderBonusLogModel();
            $bonus->startTrans();
            try{
                //添加检验已写入则不能重复写入 已uid，uid，from_type，order_id，order_goods_id，bonus
                $where['order_goods_id'] = $params['order_goods_id'];
                $where['website_id'] = $params['website_id'];
                $checkBonus = $bonus->getInfo($where);
                if($checkBonus && $checkBonus['team_bonus_details']){
                    $bonus->commit();
                    return 1; //有重复数据 跳出本次循环
                }

                if($shop_id > 0){
                    //店铺订单如果产生的分销分红金额超出店铺收入则终止写入 && 变更为批量统计、处理
                    //$all_bonus = array_sum(array_column($insert_data, 'bonus')); //本次全球分红总金额
                    $gat_bonus = $bonus->getSum(['order_id'=>$params['order_id']],'team_bonus+global_bonus+area_bonus');//已计算分红汇总
                    $order_commission = new VslOrderDistributorCommissionModel();
                    $all_commission = $order_commission->getSum(['order_id' => $params['order_id']], 'commission'); //分销总金额
                    $all_point = $order_commission->getSum(['order_id' => $params['order_id']], 'point'); //分销总金额
                    $point_money = changePoints($all_point,$params['website_id']);
                    debugLog($order_info['shop_order_money'].'-'.$all_commission.'+'.$all_bonus.'+'.$gat_bonus.'+'.$point_money.'==团队分红对比金额<==');
                    //不作写入
                    if($order_info['shop_order_money'] < ($all_commission + $all_bonus + $gat_bonus + $point_money)){
                        debugLog($params['order_id'], $params['order_goods_id'].'==order_goods_id>店铺收入低于团队分红金额_不作写入<==');
                        $bonus->commit();
                        return 1;
                    }
                }
                if(!$checkBonus){
                    $insert_data = [
                        'order_id' => $params['order_id'],
                        'order_goods_id' => $params['order_goods_id'],
                        'buyer_id' => $order_goods_info['buyer_id'],
                        'website_id' => $params['website_id'],
                        'team_bonus' => $all_bonus,
                        'team_bonus_details' => json_encode($team_bonus_arr,true),
                        'shop_id' => $shop_id,
                        'create_time' => time()
                    ];
                    $bonus->save($insert_data);
                }else{
                    $bonus->save(['team_bonus' => $all_bonus,'team_bonus_details' => json_encode($team_bonus_arr,true), 'update_time' => time()],['id' => $checkBonus['id']]);
                }
                $bonus->commit();
                return 1;
            }catch (\Exception $e) {
                $bonus->rollback();
                return $e->getMessage();
            }
        }




    }

    public function get_parent_id($arr,$cid,$type=0,$tops = []){
        $member = new VslMemberModel();
        $level = new VslAgentLevelModel();
        if($type==1){
            $member_info = $member->getInfo(['uid'=>$cid],'*');
        }else{
            $member_info = $member->getInfo(['uid'=>$cid,'isdistributor'=>2],'*');
        }
        $level_info = $level->getInfo(['id'=>$member_info['team_agent_level_id']],'level_name,weight');
        $level_weight = $level_info['weight'];
        $member_info['team_agent_level_name'] = $level_info['level_name'];
        if($member_info['is_team_agent']==2){
            if(empty($arr['agent_list'])){
                $arr['agent_list'][] = $member_info['uid'];
            }else{
                if(is_array($arr['agent_list'])){
                    array_push($arr['agent_list'],$member_info['uid']);
                }
            }
            if (empty($arr['level_id'])){
                $arr['weight'][] = $level_weight;
                $arr['level_id'][] = $member_info['team_agent_level_id'];
                $arr['level_info'][] = $member_info;
            }else{
                if(is_array($arr['level_id'])){
                    array_push($arr['weight'],$level_weight);
                    array_push($arr['level_id'],$member_info['team_agent_level_id']);
                    array_push($arr['level_info'],$member_info);
                }
            }
        }
		array_push($tops,$cid);
		if(in_array($member_info['referee_id'], $tops)){
			debugLog($member_info['referee_id'],'重复上级==>');
		}
        if($member_info['referee_id'] && $cid!=$member_info['referee_id'] && !in_array($member_info['referee_id'], $tops)){
            return $this->get_parent_id($arr,$member_info['referee_id'],$type,$tops);
        }else{
            return $arr;
        }
    }
    /*
     * 添加分红账户流水表
     */
    public function addTeamBonus($params)
    {
        if (!$params['team_bonus_details']) {
            return true;
        }
        $team_bonus_details = json_decode(htmlspecialchars_decode($params['team_bonus_details']), true); //转成数组处理分红数据
        if (!$team_bonus_details || !is_array($team_bonus_details)) {
            return true;
        }
        $agentAccountRecordsModel = new VslAgentAccountRecordsModel();
        $accountModel = new VslAccountModel();
        $unGrantBonusOrderModel = new VslUnGrantBonusOrderModel();
        $orderBonusLogModel = new VslOrderBonusLogModel();
        $shop = new ShopAccount();
        $old_order_id = $params['order_id'];
        if ($params['order_id']) {
            $order = new VslOrderModel();
            $params['order_id'] = $order->getInfo(['order_id' => $params['order_id']], 'order_no')['order_no'];
        }
        $agentAccountRecordsModel->startTrans();
        try {
            $insertData = array();
            $insertDataUnGrant = array();
            foreach ($team_bonus_details as $key => $val) {
                if (!$val['bonus']) {
                    continue;
                }
                $records_no = 'TBS' . time() . rand(111, 999);
                if ($params['status'] == 1) {
                    $data_records = array(
                        'uid' => $key,
                        'data_id' => $params['order_id'],
                        'records_no' => $records_no,
                        'bonus' => abs($val['bonus']),
                        'from_type' => 1, //订单完成
                        'bonus_type' => 3, //团队分红
                        'website_id' => $params['website_id'],
                        'text' => '订单完成,待发放分红增加,冻结分红减少',
                        'create_time' => time(),
                    );
                    array_push($insertData, $data_records);
                }
                if ($params['status'] == 2) {
                    $records_count = $agentAccountRecordsModel->getInfo(['data_id' => $params['order_id']], '*');
                    if ($records_count) {
                        $data_records = array(
                            'uid' => $key,
                            'data_id' => $params['order_id'],
                            'website_id' => $params['website_id'],
                            'records_no' => $records_no,
                            'bonus' => (-1) * ($val['bonus']),
                            'text' => '订单退款,冻结分红减少',
                            'create_time' => time(),
                            'bonus_type' => 3, //团队分红
                            'from_type' => 2, //订单退款
                        );
                        array_push($insertData, $data_records);
                    }
                }
                if ($params['status'] == 3) {
                    $data_records = array(
                        'uid' => $key,
                        'data_id' => $params['order_id'],
                        'website_id' => $params['website_id'],
                        'records_no' => $records_no,
                        'bonus' => abs($val['bonus']),
                        'text' => '订单支付,冻结分红增加',
                        'create_time' => time(),
                        'bonus_type' => 3, //团队分红
                        'from_type' => 3, //订单支付成功
                    );
                    array_push($insertData, $data_records);
                }
                $bonusAccountModel = new VslBonusAccountModel();
                //更新对应分红账户和平台账户余额
                $bonusAccount = $bonusAccountModel->getInfo(['uid' => $key, 'from_type' => 3], '*'); //分红账户
                if (empty($bonusAccount)) {
                    $bonusAccountModel->save(['website_id' => $params['website_id'], 'uid' => $key, 'from_type' => 3]);
                    $bonusAccount = $bonusAccountModel->getInfo(['uid' => $key, 'from_type' => 3], '*'); //分红账户;
                }

                if ($params['status'] == 1) {//订单完成，添加分红
                    //分红账户分红改变
                    if ($bonusAccount) {
                        $account_data = array(
                            'ungrant_bonus' => $bonusAccount['ungrant_bonus'] + abs($val['bonus']),
                            'freezing_bonus' => $bonusAccount['freezing_bonus'] - abs($val['bonus']),
                            'total_bonus' => $bonusAccount['total_bonus']
                        );
                        $bonusAccountModel->isUpdate(true)->save($account_data, ['uid' => $key, 'from_type' => 3]);
                    }
                    $account = $accountModel->getInfo(['website_id' => $params['website_id']], 'bonus'); //平台账户
                    //平台账户分红改变
                    if ($account) {
                        $bonus_data = array(
                            'bonus' => $account['bonus'] + abs($val['bonus']),
                        );
                        $accountModel->isUpdate(true)->save($bonus_data, ['website_id' => $params['website_id']]);
                    }
                    //添加对应的待分红相关的订单金额
                    $order_ungrant_bonus = array(
                        'grant_status' => 1, //未发放
                        'order_id' => $params['order_id'],
                        'uid' => $key,
                        'bonus' => abs($val['bonus']),
                        'from_type' => 3, //团队分红
                        'website_id' => $params['website_id']
                    );
                    array_push($insertDataUnGrant, $order_ungrant_bonus);
                }
                if ($params['status'] == 2) {//订单退款完成，修改分红
                    if ($bonusAccount) {
                        $bonus_data = array(
                            'freezing_bonus' => $bonusAccount['freezing_bonus'] - abs($val['bonus']),
                            'total_bonus' => $bonusAccount['total_bonus'] - abs($val['bonus'])
                        );
                        $bonusAccountModel->isUpdate(true)->save($bonus_data, ['uid' => $key, 'from_type' => 3]);
                    }
                }
                if ($params['status'] == 3) {//订单支付完成，分红改变
                    //队长分红账户改变
                    if ($bonusAccount) {
                        $bonus_data = array(
                            'freezing_bonus' => $bonusAccount['freezing_bonus'] + abs($val['bonus']),
                            'total_bonus' => $bonusAccount['total_bonus'] + abs($val['bonus'])
                        );
                        $bonusAccountModel->isUpdate(true)->save($bonus_data, ['uid' => $key, 'from_type' => 3]);
                        //平台账户流水表

                        $shop->addAccountRecords(0, $key, '订单支付完成团队分红', $val['bonus'], 22, $params['order_id'], '订单支付完成，账户分红增加', $params['website_id']);
                        runhook("Notify", "sendCustomMessage", ["messageType" => "freezing_teamlbonus", "uid" => $key, "order_time" => time(), 'bonus_money' => $val['bonus']]);
                    }
                }
            }
            $agentAccountRecordsModel->saveAll($insertData);
            $unGrantBonusOrderModel->saveAll($insertDataUnGrant);
            //变更该条记录状态
            if ($params['status'] == 3) {
                $orderBonusLogModel->isUpdate(true)->save(['team_pay_status' => 1, 'update_time' => time()], ['order_id' => $old_order_id, 'order_goods_id' => $params['order_goods_id']]);
            }
            if ($params['status'] == 1) {
                $orderBonusLogModel->isUpdate(true)->save(['team_cal_status' => 1, 'update_time' => time()], ['order_id' => $old_order_id]);
            }
            if ($params['status'] == 2) {
                $orderBonusLogModel->isUpdate(true)->save(['team_return_status' => 1, 'update_time' => time()], ['order_id' => $old_order_id, 'order_goods_id' => $params['order_goods_id']]);
            }
            $agentAccountRecordsModel->commit();
            return 1;
        } catch (\Exception $e) {
            $agentAccountRecordsModel->rollback();
            return $e->getMessage();
        }
    }

    /*
    * 团队分红自动发放
    */
    public function autoGrantTeamBonus($params){
        $basic_config = $this->getTeamBonusSite($params['website_id']);
        $config = $this->getSettlementSite($params['website_id']);
        if($basic_config['is_use']==1 && $config['withdrawals_check'] == 1){
            $order_grant = new VslUnGrantBonusOrderModel();
            $uids = array_unique($order_grant->Query(['from_type'=>3,'grant_status'=>1,'website_id'=>$params['website_id']],'uid'));
            $grant_time = time();
            $sn =  md5(uniqid(rand()));
            $up_grant = new VslGrantTimeModel();
            $up_grant_time = $up_grant->getInfo(['website_id'=>$params['website_id'],'from_type'=>3],'time,id');
            if($config['limit_time'] && $config['limit_time']!=100){
                $limit_time = $config['limit_time']*24*3600;
                $now_time = strtotime(date('Y-m-d',time()));
                $time = $up_grant_time['time']+$limit_time;
                if($up_grant_time && $up_grant_time['time']){//如果存在上次发放时间
                    $rel_time = strtotime(date('Y-m-d',$time));
                }else{
                    $rel_time = 0;
                }
                foreach($uids as $k=>$v) {
                    $bonus = new VslBonusAccountModel();
                    $grant = new VslBonusGrantModel();
                    $bonus_info = $bonus->getInfo(['uid' => $v,'from_type'=>3], '*');
                    //自动分红
                    if ($rel_time == $now_time) {
                        //添加分红发放流水
                        $data = array(
                            "grant_no" =>'tb'.getSerialNo(),
                            "uid" => $v,
                            "bonus" => $bonus_info['ungrant_bonus'],
                            "grant_time" => $grant_time,
                            "website_id" => $bonus_info['website_id'],
                            "from_type" => 3,
                            "type" => $params['type'],
                            "sn" => $sn
                        );
                        $grant->save($data);
                        $data_info = array(
                            "uid" => $v,
                            "data_id" => $data['grant_no'],
                            "bonus" => $bonus_info['ungrant_bonus'],
                            "website_id" => $bonus_info['website_id'],
                        );
                        //分红发放到账户余额
                        $data_info['ungrant_bonus'] = $data_info['bonus'];
                        $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                        if($config['poundage']){
                            $data_info['real_ungrant_bonus'] =abs($data_info['ungrant_bonus'])-twoDecimal($config['poundage']*abs($data_info['ungrant_bonus'])/100);
                            if($config['withdrawals_end'] && $config['withdrawals_begin']){
                                if(abs($data_info['ungrant_bonus'])<=$config['withdrawals_end'] && abs($data_info['ungrant_bonus'])>=$config['withdrawals_begin'] ){
                                    $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                                }
                            }
                        }
                        $res = $this->addGrantBonus($data_info);
                        if ($res) {//分红发放完成后更新未发放订单状态
                            $order_grant = new VslUnGrantBonusOrderModel();
                            $order_grant->save(['grant_status' => 2], ['uid' => $v,'from_type'=>3]);
                        }
                    }else if(empty($up_grant_time['time'])){
                        //添加分红发放流水
                        $data = array(
                            "grant_no" => getSerialNo(),
                            "uid" => $v,
                            "bonus" => $bonus_info['ungrant_bonus'],
                            "grant_time" => $grant_time,
                            "website_id" => $bonus_info['website_id'],
                            "from_type" => 3,
                            "type" => 2,
                            "sn" => $sn
                        );
                        $grant->save($data);
                        $data_info = array(
                            "uid" => $v,
                            "data_id" => $data['grant_no'],
                            "bonus" => $bonus_info['ungrant_bonus'],
                            "website_id" => $bonus_info['website_id'],
                        );
                        //分红发放到账户余额
                        $data_info['ungrant_bonus'] = $data_info['bonus'];
                        $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                        if($config['poundage']){
                            $data_info['real_ungrant_bonus'] =abs($data_info['ungrant_bonus'])-twoDecimal($config['poundage']*abs($data_info['ungrant_bonus'])/100);
                            if($config['withdrawals_end'] && $config['withdrawals_begin']){
                                if(abs($data_info['ungrant_bonus'])<=$config['withdrawals_end'] && abs($data_info['ungrant_bonus'])>=$config['withdrawals_begin'] ){
                                    $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                                }
                            }
                        }
                        $res = $this->addGrantBonus($data_info);
                        if ($res) {//分红发放完成后更新未发放订单状态
                            $order_grant = new VslUnGrantBonusOrderModel();
                            $order_grant->save(['grant_status' => 2], ['uid' => $v,'from_type'=>3]);
                        }
                    }
                }
            }
            if($config['limit_time'] && $config['limit_date'] && $config['limit_time']==100){
                $date = date('d');
                $firstday = date('Y-m-01', strtotime(date("Y-m-d")));
                $lastday = date('d', strtotime("$firstday +1 month -1 day"));
                if($date==$config['limit_date'] || $lastday<=$config['limit_date']){
                    foreach($uids as $k=>$v) {
                        $bonus = new VslBonusAccountModel();
                        $grant = new VslBonusGrantModel();
                        $bonus_info = $bonus->getInfo(['uid' => $v,'from_type'=>3], '*');
                        //添加分红发放流水
                        $data = array(
                                "grant_no" =>'tb'.getSerialNo(),
                                "uid" => $v,
                                "bonus" => $bonus_info['ungrant_bonus'],
                                "grant_time" => $grant_time,
                                "website_id" => $bonus_info['website_id'],
                                "from_type" => 3,
                                "type" => $params['type'],
                                "sn" => $sn
                            );
                        $grant->save($data);
                        $data_info = array(
                                "uid" => $v,
                                "data_id" => $data['grant_no'],
                                "bonus" => $bonus_info['ungrant_bonus'],
                                "website_id" => $bonus_info['website_id'],
                            );
                        //分红发放到账户余额
                        $data_info['ungrant_bonus'] = $data_info['bonus'];
                        $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                        if($config['poundage']){
                                $data_info['real_ungrant_bonus'] =abs($data_info['ungrant_bonus'])-twoDecimal($config['poundage']*abs($data_info['ungrant_bonus'])/100);
                                if($config['withdrawals_end'] && $config['withdrawals_begin']){
                                    if(abs($data_info['ungrant_bonus'])<=$config['withdrawals_end'] && abs($data_info['ungrant_bonus'])>=$config['withdrawals_begin'] ){
                                        $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                                    }
                                }
                            }
                        $res = $this->addGrantBonus($data_info);
                        if ($res) {//分红发放完成后更新未发放订单状态
                            $order_grant = new VslUnGrantBonusOrderModel();
                            $order_grant->save(['grant_status' => 2], ['uid' => $v,'from_type'=>3]);
                        }
                    }
                }
            }
            if ($res) {
                //添加发放时间记录表
                $data_time = array(
                    "time" => $grant_time,
                    "website_id" => $params['website_id'],
                    "from_type" => 3
                );
                if ($up_grant_time && $up_grant_time['id']) {
                    $up_grant->save($data_time, ['id' => $up_grant_time['id']]);
                } else {
                    $up_grant->save($data_time);
                }
                return $res;
            }
        }
    }
    /*
    * 团队分红手动发放
    */
    public function grantTeamBonus($type){
        $config = $this->getTeamBonusSite($this->website_id);
        $set_config = $this->getSettlementSite($this->website_id);
        if($config['is_use']==1 && $type==1){
            $order_grant = new VslUnGrantBonusOrderModel();
            $uids = array_unique($order_grant->Query(['from_type'=>3,'grant_status'=>1,'website_id'=>$this->website_id],'uid'));
            $grant_time = time();
            $sn =  md5(uniqid(rand()));
            $up_grant = new VslGrantTimeModel();
            $up_grant_time = $up_grant->getInfo(['website_id'=>$this->website_id,'from_type'=>3],'time,id');
            foreach($uids as $k=>$v){
                $bonus = new VslBonusAccountModel();
                $grant = new VslBonusGrantModel();
                $bonus_info = $bonus->getInfo(['uid'=>$v,'from_type'=>3],'*');
                //手动分红
                //添加分红流水
                $data = array(
                    "grant_no"=>'tb'.getSerialNo(),
                    "uid"=>$v,
                    "bonus"=>$bonus_info['ungrant_bonus'],
                    "grant_time"=>$grant_time,
                    "website_id"=>$bonus_info['website_id'],
                    "from_type"=>3,
                    "type"=>$type,
                    "sn" => $sn
                );
                $grant->save($data);
                $data_info = array(
                    "uid"=>$v,
                    "data_id"=>$data['grant_no'],
                    "bonus"=>$bonus_info['ungrant_bonus'],
                    "website_id"=>$bonus_info['website_id'],
                );
                //分红发放到账户余额(扣除个人所得税)
                $data_info['ungrant_bonus'] = $data_info['bonus'];
                $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                if($set_config['poundage']){
                    $data_info['real_ungrant_bonus'] =abs($data_info['ungrant_bonus'])-twoDecimal($set_config['poundage']*abs($data_info['ungrant_bonus'])/100);
                    if($set_config['withdrawals_end'] && $set_config['withdrawals_begin']){
                        if(abs($data_info['ungrant_bonus'])<=$set_config['withdrawals_end'] && abs($data_info['ungrant_bonus'])>=$set_config['withdrawals_begin'] ){
                            $data_info['real_ungrant_bonus'] = $data_info['ungrant_bonus'];
                        }
                    }
                }
                $res = $this->addGrantBonus($data_info);
                if($res){//手动分红发放完成后改变未发放订单状态
                    $order_grant = new VslUnGrantBonusOrderModel();
                    $order_grant->save(['grant_status'=>2],['uid'=>$v,'from_type'=>3]);
                }
            }
            if($res){
                //添加发放时间记录表
                $data_time = array(
                    "time"=>$grant_time,
                    "website_id"=>$this->website_id,
                    "from_type"=>3
                );
                if($up_grant_time && $up_grant_time['id']){
                    $up_grant->save($data_time,['id'=>$up_grant_time['id']]);
                }else{
                    $up_grant->save($data_time);
                }
                return $res;
            }
        }
    }
    /**
     * 分红发放到账户余额
     */
    public function addGrantBonus($data_info){
        $bonus_withdraw = new VslMemberAccountRecordsModel();
        try{
            $data1 = array(
                'records_no' => getSerialNo(),
                'uid' => $data_info['uid'],
                'account_type' => 2,
                'number'   => $data_info['real_ungrant_bonus'],
                'data_id' => $data_info['data_id'],
                'from_type' => 13,
                'text' => '队长分红提现到余额',
                'create_time' => time(),
                'website_id' => $data_info['website_id']
            );
            $res = $bonus_withdraw->save($data1);//添加会员流水
            $acount = new ShopAccount();
            $income_tax =$data_info['ungrant_bonus']-$data_info['real_ungrant_bonus'];
            $acount->addAccountRecords(0, $data_info['uid'], "团队分红发放，个人所得税!",$income_tax, 24, $data_info['data_id'], '团队分红发放，个人所得税增加',$data_info['website_id']);//添加平台流水
            if($res){
                $member_account = new VslMemberAccountModel();
                $account_info = $member_account->getInfo(['uid'=>$data_info['uid']],'*');
                try{
                    if($account_info){
                        $data2 = array(
                            'uid' => $data_info['uid'],
                            'balance' => $data_info['real_ungrant_bonus']+$account_info['balance']
                        );
                        $res1 = $member_account->save($data2,['uid'=>$data_info['uid']]);//更新会员账户余额
                    }else{
                        $data2 = array(
                            'uid' => $data_info['uid'],
                            'balance' => $data_info['real_ungrant_bonus'],
                            'website_id' => $data_info['website_id']
                        );
                        $res2 = $member_account->save($data2);//添加会员账户余额
                    }
                    if($res1 || $res2){//更新分红账户
                        //添加分红账户流水
                        $records_no = 'TBS'.time() . rand(111, 999);
                        $agent_account = new VslAgentAccountRecordsModel();
                        $data_account = array(
                            'uid' => $data_info['uid'],
                            'data_id' => $data_info['data_id'],
                            'records_no' => $records_no,
                            'website_id' => $data_info['website_id'],
                            'bonus' => abs($data_info['ungrant_bonus']),
                            'text' => '队长分红发放到账户余额，已发放分红增加，待发放分红减少',
                            'create_time' => time(),
                            'bonus_type' => 3,//团队分红
                            'from_type' => 4,//分红发放成功
                        );
                        $agent_account->save($data_account);
                        $bonus_account = new VslBonusAccountModel();
                        $bonus_account_info = $bonus_account->getInfo(['uid'=>$data_info['uid'],'from_type'=>3],'*');
                        try{
                            $data3 = array(
                                'ungrant_bonus'=>$bonus_account_info['ungrant_bonus']-abs($data_info['ungrant_bonus']),
                                'grant_bonus'=>$bonus_account_info['grant_bonus']+abs($data_info['ungrant_bonus']),
                                'tax' => $income_tax,
                            );
                            $bonus_account->save($data3,['uid'=>$data_info['uid'],'from_type'=>3]);//更新分红账户
                            runhook("Notify", "sendCustomMessage", ["messageType"=>"teambonus_payment","uid" =>$data_info['uid'],"pay_time" => time(),'bonus_money'=>$data_info['ungrant_bonus']]);
                            $bonus_account->commit();
                            return 1;
                        }catch (\Exception $e)
                        {
                            $bonus_account->rollback();
                            return $e->getMessage();
                        }
                    }
                    $member_account->commit();
                }catch (\Exception $e)
                {
                    $member_account->rollback();
                    return $e->getMessage();
                }
            }
            $bonus_withdraw->commit();
        }catch (\Exception $e)
        {
            $bonus_withdraw->rollback();
            return $e->getMessage();
        }
    }
    /*
     * 订单完成后队长等级升级
     */
    public function updateAgentLevelInfo($uid)
    {
        $member = new VslMemberModel();
        $level = new AgentLevelModel();
        $agent = $member->getInfo(['uid'=>$uid],'*');
        $base_info = $this->getTeamBonusSite($agent['website_id']);
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        if($base_info['teamagent_grade']==1){//开启跳级
            if($agent['is_team_agent']==2){
                $getAgentInfo = $this->getAgentLowerInfo($uid);//当前队长的详情信息
                $default_level_name = $level->getInfo(['id'=>$agent['team_agent_level_id']],'level_name')['level_name'];
                $level_weight = $level->Query(['id'=>$agent['team_agent_level_id']],'weight');//当前队长的等级权重
                $level_weights = $level->Query(['weight'=>['>',implode(',',$level_weight)],'from_type'=>3,'website_id'=>$agent['website_id']],'weight');//当前队长的等级权重的上级权重
                if ($level_weights) {
                    sort($level_weights);
                    foreach ($level_weights as $k => $v) {
                        $level_infos = $level->getInfo(['weight' => $v,'from_type'=>3,'website_id'=>$agent['website_id']]);//比当前队长等级的权重高的等级信息
                        $ratio = $level_infos['ratio'].'%';
                        //判断是否购买过指定商品
                        $goods_info = [];
                        if ($level_infos['goods_id']) {
                            $goods_id = $order_goods->Query(['goods_id' => ['IN',$level_infos['goods_id']], 'buyer_id' => $uid], 'order_id');
                            if ($goods_id && $agent['down_up_team_level_time']) { //发生降级后 订单完成时间需大于降级时间
                                $goods_info = $order->getInfo(['order_id' => ['IN',implode(',',$goods_id)], 'order_status' => 4,'finish_time'=>[">",$agent['down_up_team_level_time']]], '*');
                            }else if($goods_id){
                                $goods_info = $order->getInfo(['order_id' => ['IN',implode(',',$goods_id)], 'order_status' => 4], '*');
                            }

                        }
                        if($level_infos && $level_infos['upgrade_level']){
                            if($level_infos['down_up_team_level_time']){
                                $low_number = $member->getCount(['distributor_level_id'=>$level_infos['upgrade_level'],'referee_id'=>$uid,'website_id'=>$agent['website_id'],'reg_time'=>[">",$agent['down_up_team_level_time']]]);//该等级指定推荐等级人数
                            }else{
                                $low_number = $member->getCount(['distributor_level_id'=>$level_infos['upgrade_level'],'referee_id'=>$uid,'website_id'=>$agent['website_id']]);//该等级指定推荐等级人数
                            }

                        }else{
                            $low_number = 0;
                        }
                        if ($level_infos['upgradetype'] == 1) {//是否开启自动升级
                            $conditions = explode(',', $level_infos['upgradeconditions']);
                            $result = [];
                            foreach ($conditions as $k1 => $v1) {
                                switch ($v1) {
                                    case 1:
                                        $selforder_money = $level_infos['pay_money'];
                                        if ($getAgentInfo['selforder_money'] >= $selforder_money) {
                                            $result[] = 1;//自购订单金额
                                        }
                                        break;
                                    case 2:
                                        $group_number= $level_infos['group_number'];
                                        if ($getAgentInfo['agentcount'] >= $group_number) {
                                            $result[] = 2;//团队人数
                                        }
                                        break;
                                    case 3:
                                        $one_number = $level_infos['one_number'];
                                        if ($getAgentInfo['one_number1'] >= $one_number) {
                                            $result[] = 3;//一级分销商满
                                        }
                                        break;
                                    case 4:
                                        $two_number = $level_infos['two_number'];
                                        if ($getAgentInfo['two_number1'] >= $two_number) {
                                            $result[] = 4;//二级分销商满
                                        }
                                        break;
                                    case 5:
                                        $three_number = $level_infos['three_number'];
                                        if ($getAgentInfo['three_number1'] >= $three_number) {
                                            $result[] = 5;//三级分销商满
                                        }
                                        break;
                                    case 6:
                                        $order_money = $level_infos['order_money'];
                                        if ($getAgentInfo['order_money'] >= $order_money) {
                                            $result[] = 6;//分销订单金额达
                                        }
                                        break;
                                    case 7:
                                        if ($goods_info) {
                                            $result[] = 7;//指定商品
                                        }
                                        break;
                                    case 8:
                                        $offline_number = $level_infos['number'];
                                        if ($getAgentInfo['agentcount1'] >= $offline_number) {
                                            $result[] = 8;//客户人数
                                        }
                                        break;
                                    case 9:
                                        $level_number = $level_infos['level_number'];
                                        if ($low_number>= $level_number) {
                                            $result[] = 9;//指定等级人数
                                        }
                                        break;
                                    case 11:
                                        $up_order_money = $level_infos['up_team_money'];
                                        if ($getAgentInfo['up_team_money']>= $up_order_money) {
                                            $result[] = 11;//指定等级人数
                                        }
                                        break;
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 1) {//升级条件类型（满足所有勾选条件）
                                if (count($result) == count($conditions)) {
                                    runhook("Notify", "sendCustomMessage", ['messageType'=>'team_upgrade_notice','uid' => $uid,'present_grade'=>$level_infos['level_name'],'primary_grade'=>$default_level_name,'ratio'=>$ratio,'upgrade_time' => time()]);//升级
                                    $member = new VslMemberModel();
                                    $member->save(['team_agent_level_id' => $level_infos['id'], 'up_team_level_time' => time(),'down_up_team_level_time'=>''], ['uid' => $uid]);
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 2) {//升级条件类型（满足勾选条件任意一个即可）
                                if (count($result) >= 1) {
                                    runhook("Notify", "sendCustomMessage", ['messageType'=>'team_upgrade_notice','uid' => $uid,'present_grade'=>$level_infos['level_name'],'primary_grade'=>$default_level_name,'ratio'=>$ratio,'upgrade_time' => time()]);//升级
                                    $member = new VslMemberModel();
                                    $member->save(['team_agent_level_id' => $level_infos['id'], 'up_team_level_time' => time(),'down_up_team_level_time'=>''], ['uid' => $uid]);
                                }
                            }
                        }
                    }
                }
            }
        }
        if($base_info['teamagent_grade']==2){//未开启跳级
            if($agent['is_team_agent']==2){
                $getAgentInfo = $this->getAgentLowerInfo($uid);//当前队长的详情信息
                $default_level_name = $level->getInfo(['id'=>$agent['team_agent_level_id']],'level_name')['level_name'];
                $level_weight = $level->Query(['id'=>$agent['team_agent_level_id']],'weight');//当前队长的等级权重
                $level_weights = $level->Query(['weight'=>['>',implode(',',$level_weight)],'from_type'=>3,'website_id'=>$agent['website_id']],'weight');//当前队长的等级权重的上级权重
                if ($level_weights) {
                    sort($level_weights);
                    foreach ($level_weights as $k => $v) {
                        if($k > 0){
                            break;
                        }
                        $level_infos = $level->getInfo(['weight' => $v,'from_type'=>3,'website_id'=>$agent['website_id']]);//比当前队长等级的权重高的等级信息
                        $ratio = $level_infos['ratio'].'%';
                        //判断是否购买过指定商品
                        $goods_info = [];
                        if ($level_infos['goods_id']) {
                            $goods_id = $order_goods->Query(['goods_id' => ['IN',$level_infos['goods_id']], 'buyer_id' => $uid], 'order_id');
                            if ($goods_id && $agent['down_up_team_level_time']) { //发生降级后 订单完成时间需大于降级时间
                                $goods_info = $order->getInfo(['order_id' => ['IN',implode(',',$goods_id)], 'order_status' => 4,'finish_time'=>[">",$agent['down_up_team_level_time']]], '*');
                            }else if($goods_id){
                                $goods_info = $order->getInfo(['order_id' => ['IN',implode(',',$goods_id)], 'order_status' => 4], '*');
                            }
                        }
                        if($level_infos && $level_infos['upgrade_level']){
                            if($level_infos['down_up_team_level_time']){
                                $low_number = $member->getCount(['distributor_level_id'=>$level_infos['upgrade_level'],'referee_id'=>$uid,'website_id'=>$agent['website_id'],'reg_time'=>[">",$agent['down_up_team_level_time']]]);//该等级指定推荐等级人数
                            }else{
                                $low_number = $member->getCount(['distributor_level_id'=>$level_infos['upgrade_level'],'referee_id'=>$uid,'website_id'=>$agent['website_id']]);//该等级指定推荐等级人数
                            }
                        }else{
                            $low_number = 0;
                        }
                        if ($level_infos['upgradetype'] == 1) {//是否开启自动升级
                            $conditions = explode(',', $level_infos['upgradeconditions']);
                            $result = [];
                            foreach ($conditions as $k1 => $v1) {
                                switch ($v1) {
                                    case 1:
                                        $selforder_money = $level_infos['pay_money'];
                                        if ($getAgentInfo['selforder_money'] >= $selforder_money) {
                                            $result[] = 1;//自购订单金额
                                        }
                                        break;
                                    case 2:
                                        $group_number= $level_infos['group_number'];
                                        if ($getAgentInfo['agentcount'] >= $group_number) {
                                            $result[] = 2;//团队人数
                                        }
                                        break;
                                    case 3:
                                        $one_number = $level_infos['one_number'];
                                        if ($getAgentInfo['one_number1'] >= $one_number) {
                                            $result[] = 3;//一级分销商满
                                        }
                                        break;
                                    case 4:
                                        $two_number = $level_infos['two_number'];
                                        if ($getAgentInfo['two_number1'] >= $two_number) {
                                            $result[] = 4;//二级分销商满
                                        }
                                        break;
                                    case 5:
                                        $three_number = $level_infos['three_number'];
                                        if ($getAgentInfo['three_number1'] >= $three_number) {
                                            $result[] = 5;//三级分销商满
                                        }
                                        break;
                                    case 6:
                                        $order_money = $level_infos['order_money'];
                                        if ($getAgentInfo['order_money'] >= $order_money) {
                                            $result[] = 6;//分销订单金额达
                                        }
                                        break;
                                    case 7:
                                        if ($goods_info) {
                                            $result[] = 7;//指定商品
                                        }
                                        break;
                                    case 8:
                                        $offline_number = $level_infos['number'];
                                        if ($getAgentInfo['agentcount1'] >= $offline_number) {
                                            $result[] = 8;//客户人数
                                        }
                                        break;
                                    case 9:
                                        $level_number = $level_infos['level_number'];
                                        if ($low_number>= $level_number) {
                                            $result[] = 9;//指定等级人数
                                        }
                                        break;
                                    case 11:
                                        $up_order_money = $level_infos['up_team_money'];
                                        if ($getAgentInfo['up_team_money']>= $up_order_money) {
                                            $result[] = 11;//指定等级人数
                                        }
                                        break;
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 1) {//升级条件类型（满足所有勾选条件）
                                if (count($result) == count($conditions)) {
                                    runhook("Notify", "sendCustomMessage", ['messageType'=>'team_upgrade_notice','uid' => $uid,'present_grade'=>$level_infos['level_name'],'primary_grade'=>$default_level_name,'ratio'=>$ratio,'upgrade_time' => time()]);//升级
                                    $member = new VslMemberModel();
                                    $member->save(['team_agent_level_id' => $level_infos['id'], 'up_team_level_time' => time(),'down_up_team_level_time'=>''], ['uid' => $uid]);
                                    break;
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 2) {//升级条件类型（满足勾选条件任意一个即可）
                                if (count($result) >= 1) {
                                    runhook("Notify", "sendCustomMessage", ['messageType'=>'team_upgrade_notice','uid' => $uid,'present_grade'=>$level_infos['level_name'],'primary_grade'=>$default_level_name,'ratio'=>$ratio,'upgrade_time' => time()]);//升级
                                    $member = new VslMemberModel();
                                    $member->save(['team_agent_level_id' => $level_infos['id'], 'up_team_level_time' => time(),'down_up_team_level_time'=>''], ['uid' => $uid]);
                                    break;
                                }
                            }
                        }
                    }
                }
            }
        }
    }
    /**
     * 队长详情(降级条件)
     */
    public function getAgentInfos($uid,$time)
    {
        $distributor = new VslMemberModel();
        if($this->website_id){
            $website_id = $this->website_id;
        }else{
            $website_id =  $distributor->getInfo(['uid'=>$uid],'website_id')['website_id'];
        }
        $order_model = new VslOrderModel();
        $config = new AddonsConfigService();
        $list = $config->getAddonsConfig('distribution',$website_id, 0, 1);
        $result = $distributor->getInfo(['uid' => $uid],"*");
        if($uid && $time){
            $order_commission = new VslOrderDistributorCommissionModel();
            $commission_order_id = implode(',',$order_commission->Query(['website_id'=>$result['website_id']],'order_id'));
            $result['agentordercount'] = 0;
            $result['order_money'] = 0;
            $result['selforder_money'] = 0;
            $result['selforder_number'] = 0;
            $up_time = $distributor->getInfo(['uid'=>$uid],'up_team_level_time')['up_team_level_time'];
            $limit_time = $up_time+$time*24*3600;
            $order_ids = $order_model->Query(['order_status'=>[['>',0],['<',5]],'buyer_id'=>$uid,'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_id');
            $order_pay_money = $order_model->Query(['order_status'=>[['>',0],['<',5]],'buyer_id'=>$uid,'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_money');
            $result['selforder_money'] = array_sum($order_pay_money);//自购订单金额
            $result['selforder_number'] = count($order_ids);//自购订单数
            if(1 <= $list['distribution_pattern']){
                $idslevel1 = $distributor->Query(['referee_id'=>$uid],'uid');
                if($idslevel1){
                    $order_ids1 = $order_model->Query(['order_status'=>[['>',0],['<',5]],'buyer_id'=>['in',implode(',',$idslevel1)],'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_id');
                    $order1_money1 = $order_model->Query(['order_status'=>[['>',0],['<',5]],'buyer_id'=>['in',implode(',',$idslevel1)],'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_money');
                    $result['order1'] = count($order_ids1);//一级分销商订单总数
                    $result['number1'] = count($idslevel1);//一级分销商总人数
                    $result['order1_money'] = array_sum($order1_money1);//一级分销商订单总金额
                    $result['agentcount'] += $result['number1'];
                    $result['agentordercount'] += $result['order1'];
                    $result['order_money'] += $result['order1_money'];
                }
            }
            if(2 <= $list['distribution_pattern']){
                if($result['number1']>0){
                    $idslevel2 = $distributor->Query(['referee_id'=>['in',implode(',',$idslevel1)]],'uid');
                    if($idslevel2){
                        $order_ids2 = $order_model->Query(['buyer_id'=>['in',implode(',',$idslevel2)],'order_status'=>[['>',0],['<',5]],'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_id');
                        $order2_money1 = $order_model->Query(['order_status'=>[['>',0],['<',5]],'buyer_id'=>['in',implode(',',$idslevel2)],'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_money');
                        $result['order2'] = count($order_ids2);//二级分销商订单总数
                        $result['number2'] = count($idslevel2);//二级分销商总人数
                        $result['order2_money'] = array_sum($order2_money1);//二级分销商订单总金额
                        $result['agentcount'] += $result['number2'];
                        $result['agentordercount'] += $result['order2'];
                        $result['order_money'] += $result['order2_money'];
                    }
                }
            }
            if(3 <= $list['distribution_pattern']){
                if($result['number2']>0){
                    $idslevel3 = $distributor->Query(['referee_id'=>['in',implode(',',$idslevel2)]],'uid');
                    if($idslevel3){
                        $order_ids3 = $order_model->Query(['buyer_id'=>['in',implode(',',$idslevel3)],'order_status'=>[['>',0],['<',5]],'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_id');
                        $order3_money1 = $order_model->Query(['order_status'=>[['>',0],['<',5]],'buyer_id'=>['in',implode(',',$idslevel3)],'create_time'=>[[">", $up_time], ["<", $limit_time]],'order_id'=>['in',$commission_order_id]],'order_money');
                        $result['order2'] = count($order_ids3);//三级分销商订单总数
                        $result['number3'] = count($idslevel3);//三级分销商总人数
                        $result['order3_money'] = array_sum($order3_money1);//三级分销商订单总金额
                        $result['agentcount'] += $result['number3'];
                        $result['agentordercount'] += $result['order3'];
                        $result['order_money'] += $result['order3_money'];
                    }
                }
            }
            if($list['purchase_type']==1){
                $result['agentordercount'] += count($order_ids);
                $result['order_money'] += array_sum($order_pay_money);
            }
        }
        return $result;
    }

    /*
     * 队长自动降级
     */
    public function autoDownAgentLevel($website_id){
        $level = new AgentLevelModel();
        $base_info = $this->getTeamBonusSite($website_id);
        $member = new VslMemberModel();
        $agents = $member->Query(['website_id'=>$website_id,'is_team_agent'=>2],'*');
        $default_weight = $level->getInfo(['website_id'=>$website_id,'is_default'=>1,'from_type'=>3],'weight')['weight'];//默认等级信息
        foreach ($agents as $k=>$v){
            $level_info_default = $level->getInfo(['id'=>$v['team_agent_level_id']],'*');
            $level_weight = $level_info_default ['weight'];//分红商的等级权重
            $level_name_default = $level_info_default['level_name'];
            if($level_weight>$default_weight){
                if($base_info['teamagent_grade']==1){//开启跳降级
                    $level_weights = $level->Query(['weight'=>['<=',$level_weight],'from_type'=>3,'website_id'=>$website_id],'weight');//分红商的等级权重的下级权重
                    rsort($level_weights);
                    foreach ($level_weights as $k1=>$v1){
                        if($v1!=$default_weight){
                        $level_info_desc = $level->getFirstData(['weight' => ['<', $v1], 'website_id' => $website_id, 'from_type' =>3], 'weight desc');//比当前等级的权重低的等级信息
                        $level_infos = $level->getInfo(['weight' => $v1, 'from_type' => 3, 'website_id' => $website_id], '*');
                        $ratio = $level_info_desc['ratio'].'%';
                        if($level_infos['downgradetype']==1 && $level_infos['downgradeconditions']){//是否开启自动降级并且有降级条件
                                $conditions = explode(',',$level_infos['downgradeconditions']);
                                $result = [];
                                $reason = '';
                                foreach ($conditions as $k2=>$v2){
                                    switch ($v2){
                                        case 1:
                                            $team_number_day = $level_infos['team_number_day'];
                                            $real_level_time = $member->getInfo(['uid'=>$v['uid']],'up_team_level_time')['up_team_level_time']+$team_number_day*24*3600;
                                            if($real_level_time<=time()){
                                                $getAgentInfo1 = $this->getAgentInfos($v['uid'],$team_number_day);
                                                $limit_number =  $getAgentInfo1['agentordercount'];//限制时间段内团队分红订单数
                                                if($limit_number <=$level_infos['team_number']){
                                                    $result[] = 1;
                                                    $reason .= '团队分红订单数小于'.$level_infos['team_number'];
                                                }
                                            }
                                            break;
                                        case 2:
                                            $team_money_day = $level_infos['team_money_day'];
                                            $real_level_time = $member->getInfo(['uid'=>$v['uid']],'up_team_level_time')['up_team_level_time']+$team_money_day*24*3600;
                                            if($real_level_time<=time()){
                                                $getAgentInfo2 = $this->getAgentInfos($v['uid'],$team_money_day);
                                                $limit_money1 =  $getAgentInfo2['order_money'];//限制时间段内团队分红订单金额
                                                if($limit_money1 <=$level_infos['team_money']){
                                                    $result[] = 2;
                                                    $reason .= '团队分红订单金额小于'.$level_infos['team_number'];
                                                }
                                            }
                                            break;
                                        case 3:
                                            $self_money_day = $level_infos['self_money_day'];
                                            $real_level_time = $member->getInfo(['uid'=>$v['uid']],'up_team_level_time')['up_team_level_time']+$self_money_day*24*3600;
                                            if($real_level_time<=time()){
                                                $getAgentInfo3 = $this->getAgentInfos($v['uid'],$self_money_day);
                                                $limit_money2 = $getAgentInfo3['selforder_money'];//限制时间段内自购分红订单金额
                                                if($limit_money2 <=$level_infos['self_money']){
                                                    $result[] = 3;
                                                    $reason .= '自购分红订单金额小于'.$level_infos['team_number'];
                                                }
                                            }
                                            break;
                                    }
                                }
                                if($level_infos['downgrade_condition']==1){//降级条件类型（满足所有勾选条件）
                                    if(count($result)==count($conditions)){
                                        runhook("Notify", "sendCustomMessage", ['messageType'=>'team_down_notice','uid' => $v['uid'],'present_grade'=>$level_info_desc['level_name'],'primary_grade'=>$level_name_default,'ratio'=>$ratio,'down_reason'=>$reason,'down_time' => time()]);//降级
                                        $member = new VslMemberModel();
                                        $member->save(['team_agent_level_id'=>$level_info_desc['id'],'down_team_level_time'=>time(),'down_up_team_level_time'=>time()],['uid'=>$v['uid']]);
                                    }
                                }
                                if($level_infos['downgrade_condition']==2){//降级条件类型（满足勾选条件任意一个即可）
                                    if(count($result)>=1){
                                        runhook("Notify", "sendCustomMessage", ['messageType'=>'team_down_notice','uid' => $v['uid'],'present_grade'=>$level_info_desc['level_name'],'primary_grade'=>$level_name_default,'ratio'=>$ratio,'down_reason'=>$reason,'down_time' => time()]);//降级
                                        $member = new VslMemberModel();
                                        $member->save(['team_agent_level_id'=>$level_info_desc['id'],'down_team_level_time'=>time(),'down_up_team_level_time'=>time()],['uid'=>$v['uid']]);
                                    }
                                }
                            }
                        }
                    }
                }
                if($base_info['teamagent_grade']==2){//未开启跳降级
                    $level_weights = $level->Query(['weight'=>['<=',$level_weight],'from_type'=>3,'website_id'=>$website_id],'weight');//分红商的等级权重的下级权重
                    rsort($level_weights);
                    foreach ($level_weights as $k1=>$v1){
                        if($k1 > 0){
                            break;
                        }
                        if($v1!=$default_weight){
                        $level_info_desc = $level->getFirstData(['weight' => ['<', $v1], 'website_id' => $website_id, 'from_type' =>3], 'weight desc');//比当前等级的权重低的等级信息
                        $level_infos = $level->getInfo(['weight' => $v1, 'from_type' => 3, 'website_id' => $website_id], '*');
                        $ratio = $level_info_desc['ratio'].'%';
                        if($level_infos['downgradetype']==1 && $level_infos['downgradeconditions']){//是否开启自动降级并且有降级条件
                                $conditions = explode(',',$level_infos['downgradeconditions']);
                                $result = [];
                                foreach ($conditions as $k2=>$v2){
                                    switch ($v2){
                                        case 1:
                                            $team_number_day = $level_infos['team_number_day'];
                                            $real_level_time = $member->getInfo(['uid'=>$v['uid']],'up_team_level_time')['up_team_level_time']+$team_number_day*24*3600;
                                            if($real_level_time<=time()){
                                                $getAgentInfo1 = $this->getAgentInfos($v['uid'],$team_number_day);
                                                $limit_number =  $getAgentInfo1['agentordercount'];//限制时间段内团队分红订单数
                                                if($limit_number <=$level_infos['team_number']){
                                                    $result[] = 1;
                                                    $reason .= '团队分红订单数小于'.$level_infos['team_number'];
                                                }
                                            }
                                            break;
                                        case 2:
                                            $team_money_day = $level_infos['team_money_day'];
                                            $real_level_time = $member->getInfo(['uid'=>$v['uid']],'up_team_level_time')['up_team_level_time']+$team_money_day*24*3600;
                                            if($real_level_time<=time()){
                                                $getAgentInfo2 = $this->getAgentInfos($v['uid'],$team_money_day);
                                                $limit_money1 =  $getAgentInfo2['order_money'];//限制时间段内团队分红订单金额
                                                if($limit_money1 <=$level_infos['team_money']){
                                                    $result[] = 2;
                                                    $reason .= '团队分红订单金额小于'.$level_infos['team_number'];
                                                }
                                            }
                                            break;
                                        case 3:
                                            $self_money_day = $level_infos['self_money_day'];
                                            $real_level_time = $member->getInfo(['uid'=>$v['uid']],'up_team_level_time')['up_team_level_time']+$self_money_day*24*3600;
                                            if($real_level_time<=time()){
                                                $getAgentInfo3 = $this->getAgentInfos($v['uid'],$self_money_day);
                                                $limit_money2 = $getAgentInfo3['selforder_money'];//限制时间段内自购分红订单金额
                                                if($limit_money2 <=$level_infos['self_money']){
                                                    $result[] = 3;
                                                    $reason .= '自购分红订单金额小于'.$level_infos['team_number'];
                                                }
                                            }
                                            break;
                                    }
                                }
                                if($level_infos['downgrade_condition']==1){//降级条件类型（满足所有勾选条件）
                                    if(count($result)==count($conditions)){
                                        runhook("Notify", "sendCustomMessage", ['messageType'=>'team_down_notice','uid' => $v['uid'],'present_grade'=>$level_info_desc['level_name'],'primary_grade'=>$level_name_default,'ratio'=>$ratio,'down_reason'=>$reason,'down_time' => time()]);//降级
                                        $member = new VslMemberModel();
                                        $member->save(['team_agent_level_id'=>$level_info_desc['id'],'down_team_level_time'=>time(),'down_up_team_level_time'=>time()],['uid'=>$v['uid']]);
                                        break;
                                    }
                                }
                                if($level_infos['downgrade_condition']==2){//降级条件类型（满足勾选条件任意一个即可）
                                    if(count($result)>=1){
                                        runhook("Notify", "sendCustomMessage", ['messageType'=>'team_down_notice','uid' => $v['uid'],'present_grade'=>$level_info_desc['level_name'],'primary_grade'=>$level_name_default,'ratio'=>$ratio,'down_reason'=>$reason,'down_time' => time()]);//降级
                                        $member = new VslMemberModel();
                                        $member->save(['team_agent_level_id'=>$level_info_desc['id'],'down_team_level_time'=>time(),'down_up_team_level_time'=>time()],['uid'=>$v['uid']]);
                                        break;
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /*
     * 成为队长的条件
     */
    public function becomeAgent($uid){

        $member = new VslMemberModel();
        $agent = $member->getInfo(['uid'=>$uid],'*');
        $base_info = $this->getTeamBonusSite($agent['website_id']);
        $order = new VslOrderModel();
        //判断是否购买过指定商品
        $goods_info = [];
        if($base_info['goods_id']){
            $order_goods = new VslOrderGoodsModel();
            $goods_id = $order_goods->Query(['goods_id'=>['IN',$base_info['goods_id']],'buyer_id'=>$uid],'order_id');
            if($goods_id){
                $goods_info = $order->getInfo(['order_id'=>['IN',implode(',',$goods_id)],'order_status'=>4],'*');
            }
        }
        $agent_level = new AgentLevelModel();
        $level_info = $agent_level->getInfo(['website_id' => $agent['website_id'],'is_default'=>1,'from_type'=>3],'*');
        $level_id = $level_info['id'];
        $ratio = $level_info['ratio'].'%';
        $member_info = $this->getAgentLowerInfo($uid);

        if($agent['is_team_agent']!=2){//判断是否是队长
            if($base_info['is_use']==1){//判断是否开启团队分红
                if($base_info['teamagent_conditions']){//判断是否有成为队长的条件
                    $result = [];
                    $conditions = explode(',',$base_info['teamagent_conditions']);
                    foreach ($conditions as $k=>$v) {
                            switch ($v) {
                                case 1:
                                    $order_money = $member_info['selforder_money'];
                                    if ($order_money >= $base_info['pay_money']) {
                                        $result[] = 1;//满足自购订单金额
                                    }
                                    break;
                                case 2:
                                    $number = $member_info['agentcount'];
                                    if ($number >= $base_info['number']) {
                                        $result[] = 2;//下级分销商数
                                    }
                                    break;
                                case 3:
                                    $one_number = $member_info['one_number1'];
                                    if ($one_number >= $base_info['one_number']) {
                                        $result[] = 3;//一级分销商
                                    }
                                    break;
                                case 4:
                                    $two_number = $member_info['two_number1'];
                                    if ($two_number >= $base_info['two_number']) {
                                        $result[] = 4;//二级分销商
                                    }
                                    break;
                                case 5:
                                    $three_number = $member_info['three_number1'];
                                    if ($three_number >= $base_info['three_number']) {
                                        $result[] = 5;//三级分销商
                                    }
                                    break;
                                case 6:
                                    $one_number = $member_info['order_money'];
                                    if ($one_number >= $base_info['order_money']) {
                                        $result[] = 6;//下级订单总额
                                    }
                                    break;
                                case 7:
                                    if ($goods_info) {
                                        $result[] = 7;//满足购买指定商品
                                    }
                                    break;
                                case 11:
                                    $up_order_money = $member_info['up_team_money'];
                                    if ($up_order_money>= $base_info['up_team_money']) {
                                        $result[] = 11;//指定等级人数
                                    }
                                    break;
                            }
                        }
                        if($base_info['teamagent_condition']==1){//满足所有勾选条件
                            if(count($conditions)==count($result)) {
                                if ($base_info['teamagent_check'] == 1 && $base_info['teamagent_data'] == 2) {
                                    $data = array(
                                        "is_team_agent" => 2,
                                        "team_agent_level_id" => $level_id,
                                        "apply_team_agent_time" => time(),
                                        "become_team_agent_time" => time(),
                                    );
                                    $member->save($data, ['uid' => $uid]);
                                    $account = new VslBonusAccountModel();
                                    $account_info = $account->getInfo(['website_id' => $agent['website_id'], 'from_type' => 3, 'uid' => $uid]);
                                    if (empty($account_info)) {
                                        $account->save(['website_id' => $agent['website_id'], 'from_type' => 3, 'uid' => $uid]);
                                    }
                                    runhook("Notify", "sendCustomMessage", ["messageType"=>"become_team","uid" => $uid,"become_time" => time(),'ratio'=>$ratio,'level_name'=>$level_info['level_name']]);//用户成为全球股东提醒
                                } else if ($base_info['teamagent_check'] == 2 && $base_info['teamagent_data'] == 2) {
                                    $member->save(['is_team_agent' => 1], ['uid' => $uid]);
                                } else {
                                    $member->save(['is_team_agent' => 3], ['uid' => $uid]);
                                }
                            }
                        }
                        if($base_info['teamagent_condition']==2){//满足所有勾选条件之一
                            if(count($result)>=1){
                                if ($base_info['teamagent_check'] == 1 && $base_info['teamagent_data'] == 2) {
                                    $data = array(
                                        "is_team_agent" => 2,
                                        "team_agent_level_id" => $level_id,
                                        "apply_team_agent_time" => time(),
                                        "become_team_agent_time" => time(),
                                    );
                                    $member->save($data, ['uid' => $uid]);
                                    $account = new VslBonusAccountModel();
                                    $account_info = $account->getInfo(['website_id' => $agent['website_id'], 'from_type' => 3, 'uid' => $uid]);
                                    if (empty($account_info)) {
                                        $account->save(['website_id' => $agent['website_id'], 'from_type' => 3, 'uid' => $uid]);
                                    }
                                    runhook("Notify", "sendCustomMessage", ["messageType"=>"become_team","uid" => $uid,"become_time" => time(),'ratio'=>$ratio,'level_name'=>$level_info['level_name']]);//用户成为全球股东提醒
                                } else if ($base_info['teamagent_check'] == 2 && $base_info['teamagent_data'] == 2) {
                                    $member->save(['is_team_agent' => 1], ['uid' => $uid]);
                                } else {
                                    $member->save(['is_team_agent' => 3], ['uid' => $uid]);
                                }
                            }
                        }
                }

            }
        }
        if($agent['referee_id']){
            $referee_info =  $member->getInfo(['uid'=>$agent['referee_id']],'*');
            if($referee_info['is_team_agent']!=2){
                $this->becomeAgent($agent['referee_id']);
            }
        }
    }
    /*
      * 队长详情(升级条件)
      */
    public function getAgentLowerInfo($uid){
        $agent = new VslMemberModel();
        if($this->website_id){
            $website_id = $this->website_id;
        }else{
            $website_id =  $agent->getInfo(['uid'=>$uid],'website_id')['website_id'];
        }
        $order_model = new VslOrderModel();
        $config = new AddonsConfigService();
        $list = $config->getAddonsConfig('distribution',$website_id, 0, 1);
        $order_commission = new VslOrderDistributorCommissionModel();
        $commission_order_id = implode(',',$order_commission->Query(['website_id'=>$website_id],'order_id'));
        $result = [];
        $result['agentcount'] = 0;//团队数
        $result['agentcount1'] = 0;//客户数
        $result['one_number1'] = 0;//一级团队人数
        $result['two_number1'] = 0;//二级团队人数
        $result['three_number1'] = 0;//三级团队人数
        $result['one_number2'] = 0;//一级客户人数
        $result['two_number2'] = 0;//二级客户人数
        $result['three_number2'] = 0;//三级客户人数
        $result['selforder_money'] = 0;//自购订单金额
        $result['order_money'] = 0;//直属下级订单金额

        $result['up_team_money'] = 0;//团队分销订单金额

        //是否发生过降级 产生降级后 统计条件发生改变 down_up_team_level_time
        $resMember = $agent->getInfo(['uid' => $uid],"down_up_team_level_time");
        if($resMember['down_up_team_level_time']){
            $order_ids = $order_model->Query(['order_status'=>4,'buyer_id'=>$uid,'website_id'=>$website_id,'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_id');
            $order_pay_money = $order_model->Query(['order_status'=>4,'buyer_id'=>$uid,'website_id'=>$website_id,'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_money');
            $result['selforder_money'] = array_sum($order_pay_money);//自购订单金额
            $result['selforder_number'] = count($order_ids);//自购订单数
            if(1 <= $list['distribution_pattern']){
                $idslevel1 = $agent->Query(['referee_id'=>$uid,'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                $idslevel_1 = $agent->Query(['referee_id'=>$uid,'isdistributor'=>2,'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                $idslevel_2 = $agent->Query(['referee_id'=>$uid,'isdistributor'=>['neq',2],'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                //edit by 2019/12/03 订单统计范围为所有 下级统计不变 按指定时间查询
                $oldidslevel1 = $agent->Query(['referee_id'=>$uid],'uid');
                $oldidslevel_1 = $agent->Query(['referee_id'=>$uid,'isdistributor'=>2],'uid');
                if($oldidslevel1){
                    $order_ids1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$oldidslevel_1)],'order_id'=>['in',$commission_order_id],'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_id');
                    $order1_money1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$oldidslevel_1)],'order_id'=>['in',$commission_order_id],'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_money');
                    $result['order1'] = count($order_ids1);//一级分销订单总数
                    $result['one_number1'] = count($idslevel_1);//一级团队人数
                    $result['one_number2'] = count($idslevel_2);//一级客户人数
                    $result['order1_money'] = array_sum($order1_money1);//一级分销商订单总金额
                    $result['up_team_money'] += $result['order1_money'];
                    $result['agentcount'] += $result['one_number1'];
                    $result['agentcount1'] += $result['one_number2'];
                    $result['order_money'] += $result['order1_money'];

                }
            }
            if(2 <= $list['distribution_pattern']){
                if($result['one_number1']>0 || count($oldidslevel_1) > 0){
                    $idslevel2 = $agent->Query(['referee_id'=>['in',implode(',',$oldidslevel1)],'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                    $idslevel_2 = $agent->Query(['referee_id'=>['in',implode(',',$oldidslevel1)],'isdistributor'=>2,'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                    $idslevel2_2 = $agent->Query(['referee_id'=>['in',implode(',',$oldidslevel1)],'isdistributor'=>['neq',2],'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                    //edit by 2019/12/03 订单统计范围为所有 下级统计不变 按指定时间查询
                    $oldidslevel2 = $agent->Query(['referee_id'=>['in',implode(',',$oldidslevel1)]],'uid');
                    $oldidslevel_2 = $agent->Query(['referee_id'=>['in',implode(',',$oldidslevel1)],'isdistributor'=>2],'uid');
                    if($oldidslevel2){
                        $order_ids2 = $order_model->Query(['buyer_id'=>['in',implode(',',$oldidslevel_2)],'order_status'=>4,'order_id'=>['in',$commission_order_id],'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_id');
                        $order2_money1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$oldidslevel_2)],'order_id'=>['in',$commission_order_id],'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_money');
                        $result['order2'] = count($order_ids2);//二级分销订单总数
                        $result['two_number1'] = count($idslevel_2);//一级团队人数
                        $result['two_number2'] = count($idslevel2_2);//一级客户人数
                        $result['order2_money'] = array_sum($order2_money1);//二级分销商订单总金额
                        $result['up_team_money'] += $result['order2_money'];
                        $result['agentcount'] += $result['two_number1'];
                        $result['agentcount1'] += $result['two_number2'];
                    }
                }
            }
            if(3 <= $list['distribution_pattern']){
                if($result['two_number1']>0 || count($oldidslevel_2) > 0){
                    $idslevel3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)],'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                    $idslevel_3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)],'isdistributor'=>2,'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                    $idslevel3_3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)],'isdistributor'=>['neq',2],'reg_time'=>[">",$resMember['down_up_team_level_time']]],'uid');
                     //edit by 2019/12/03 订单统计范围为所有 下级统计不变 按指定时间查询
                     $oldidslevel3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)]],'uid');
                     $oldidslevel_3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)],'isdistributor'=>2],'uid');
                     if($oldidslevel3){
                         $order_ids3 = $order_model->Query(['buyer_id'=>['in',implode(',',$oldidslevel_3)],'order_status'=>4,'order_id'=>['in',$commission_order_id],'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_id');
                         $order3_money1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$oldidslevel_3)],'order_id'=>['in',$commission_order_id],'finish_time'=>[">",$resMember['down_up_team_level_time']]],'order_money');
                        $result['order2'] = count($order_ids3);//三级分销商订单总数
                        $result['three_number1'] = count($idslevel_3);//一级团队人数
                        $result['three_number2'] = count($idslevel3_3);//一级客户人数
                        $result['order3_money'] = array_sum($order3_money1);//三级分销商订单总金额
                        $result['up_team_money'] += $result['order3_money'];
                        $result['agentcount'] += $result['three_number1'];
                        $result['agentcount1'] += $result['three_number2'];
                    }
                }
            }
        }else{  //没有发生过降级
            $order_ids = $order_model->Query(['order_status'=>4,'buyer_id'=>$uid,'website_id'=>$website_id],'order_id');
            $order_pay_money = $order_model->Query(['order_status'=>4,'buyer_id'=>$uid,'website_id'=>$website_id],'order_money');
            $result['selforder_money'] = array_sum($order_pay_money);//自购订单金额
            $result['selforder_number'] = count($order_ids);//自购订单数
            if(1 <= $list['distribution_pattern']){
                $idslevel1 = $agent->Query(['referee_id'=>$uid],'uid');
                $idslevel_1 = $agent->Query(['referee_id'=>$uid,'isdistributor'=>2],'uid');
                $idslevel_2 = $agent->Query(['referee_id'=>$uid,'isdistributor'=>['neq',2]],'uid');
                if($idslevel1){
                    $order_ids1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$idslevel_1)],'order_id'=>['in',$commission_order_id]],'order_id');
                    $order1_money1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$idslevel_1)],'order_id'=>['in',$commission_order_id]],'order_money');
                    $result['order1'] = count($order_ids1);//一级分销订单总数
                    $result['one_number1'] = count($idslevel_1);//一级团队人数
                    $result['one_number2'] = count($idslevel_2);//一级客户人数
                    $result['order1_money'] = array_sum($order1_money1);//一级分销商订单总金额
                    $result['up_team_money'] += $result['order1_money'];
                    $result['agentcount'] += $result['one_number1'];
                    $result['agentcount1'] += $result['one_number2'];
                    $result['order_money'] += $result['order1_money'];

                }
            }
            if(2 <= $list['distribution_pattern']){
                if($result['one_number']>0){
                    $idslevel2 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel1)]],'uid');
                    $idslevel_2 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel1)],'isdistributor'=>2],'uid');
                    $idslevel2_2 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel1)],'isdistributor'=>['neq',2]],'uid');
                    if($idslevel2){
                        $order_ids2 = $order_model->Query(['buyer_id'=>['in',implode(',',$idslevel_2)],'order_status'=>4,'order_id'=>['in',$commission_order_id]],'order_id');
                        $order2_money1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$idslevel_2)],'order_id'=>['in',$commission_order_id]],'order_money');
                        $result['order2'] = count($order_ids2);//二级分销订单总数
                        $result['two_number1'] = count($idslevel_2);//一级团队人数
                        $result['two_number2'] = count($idslevel2_2);//一级客户人数
                        $result['order2_money'] = array_sum($order2_money1);//二级分销商订单总金额
                        $result['up_team_money'] += $result['order2_money'];
                        $result['agentcount'] += $result['two_number1'];
                        $result['agentcount1'] += $result['two_number2'];
                    }
                }
            }
            if(3 <= $list['distribution_pattern']){
                if($result['two_number']>0){
                    $idslevel3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)]],'uid');
                    $idslevel_3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)],'isdistributor'=>2],'uid');
                    $idslevel3_3 = $agent->Query(['referee_id'=>['in',implode(',',$idslevel2)],'isdistributor'=>['neq',2]],'uid');
                    if($idslevel3){
                        $order_ids3 = $order_model->Query(['buyer_id'=>['in',implode(',',$idslevel_3)],'order_status'=>4,'order_id'=>['in',$commission_order_id]],'order_id');
                        $order3_money1 = $order_model->Query(['order_status'=>4,'buyer_id'=>['in',implode(',',$idslevel_3)],'order_id'=>['in',$commission_order_id]],'order_money');
                        $result['order2'] = count($order_ids3);//三级分销商订单总数
                        $result['three_number1'] = count($idslevel_3);//一级团队人数
                        $result['three_number2'] = count($idslevel3_3);//一级客户人数
                        $result['order3_money'] = array_sum($order3_money1);//三级分销商订单总金额
                        $result['up_team_money'] += $result['order3_money'];
                        $result['agentcount'] += $result['three_number1'];
                        $result['agentcount1'] += $result['three_number2'];
                    }
                }
            }
        }


        return $result;
    }
    /**
     * 获得团队分红统计
     */
    public function getAgentCount($website_id)
    {
        $start_date = strtotime(date("Y-m-d"),time());
        $end_date = strtotime(date('Y-m-d',strtotime('+1 day')));
        $member = new VslMemberModel();
        $data['agent_total'] = $member->getCount(['website_id'=>$website_id,'is_team_agent'=>2]);
        $data['agent_today'] = $member->getCount(['website_id'=>$website_id,'is_team_agent'=>2,'become_team_agent_time'=>[[">",$start_date],["<",$end_date]]]);
        $account = new VslBonusAccountModel();
        $bonus_total = $account->Query(['website_id'=>$website_id,'from_type'=>3],'total_bonus');
        $data['total_bonus'] = array_sum($bonus_total);
        $grant_bonus = $account->Query(['website_id'=>$website_id,'from_type'=>3],'grant_bonus');
        $data['grant_bonus'] = array_sum($grant_bonus);
        return $data;
    }

    /**
     * 获得近七天的分红订单分红金额
     */
    public function getPayMoneySum($condition)
    {
        $order = new VslOrderModel();
        $orderids = $order->Query($condition,'order_id');
        $orderids = implode(',',$orderids);
        $order_bonus = new VslOrderBonusLogModel();
        $count = $order_bonus->getSum(['order_id'=>['in',$orderids],'website_id'=>$condition['website_id']],'team_bonus');
        return $count;
    }
    /**
     * 获得近七天的分红订单金额
     */
    public function getOrderMoneySum($condition)
    {
        $order = new VslOrderBonusLogModel();
        $orderids = array_unique($order->Query(['website_id'=>$condition['website_id']],'order_id'));
        $orderids = implode(',',$orderids);
        $condition['order_id'] = ['in',$orderids];
        $order_model = new VslOrderModel();
        $money_sum = $order_model->where($condition)->sum('order_money');
        return $money_sum;
    }
    /**
     * 分红发放列表
     */
    public function getBonusGrantList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $Bonus_withdraw = new VslBonusGrantModel();
        $list = $Bonus_withdraw->getViewList2($page_index, $page_size, $condition, 'nmar.grant_time desc');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['grant_time'] = date('Y-m-d H:i:s',$v['grant_time']);
            }
        }
        return $list;
    }
    /**
     * 分红未发放列表
     */
    public function getUnGrantBonus($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $bonus = new VslBonusAccountModel();
        $ungrant_order = new VslUnGrantBonusOrderModel();
        $list = $bonus->getViewList2($page_index, $page_size, $condition,'');
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
            }
        }
        $list['ungrant_bonus'] = array_sum($bonus->Query(['from_type'=>3,'website_id'=>$this->website_id],'ungrant_bonus'));
        $list['total_agent'] = $bonus->getCount(['from_type'=>3,'ungrant_bonus'=>['>',0],'website_id'=>$this->website_id]);
        $order_nos = array_unique($ungrant_order->Query(['grant_status'=>1,'from_type'=>3,'website_id'=>$this->website_id],'order_id'));
        $order = new VslOrderModel();
        $list['order_money'] = array_sum($order->Query(['order_no'=>['in',implode(',',$order_nos)],'website_id'=>$this->website_id],'order_money'));
        return $list;
    }

    /**
     * 分红明细列表
     */
    public function getBonusDetailList($page_index, $page_size,$condition,$group)
    {
        $bonus_grant = new VslBonusGrantModel();
        $list = $bonus_grant->getViewLists($page_index, $page_size, $condition, 'nmar.grant_time desc',$group);
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['grant_time'] = date('Y-m-d H:i:s',$v['grant_time']);
                $list['data'][$k]['bonus_number'] = count($bonus_grant->where(['sn'=>$v['sn']])->group('uid')->select());
            }
        }
        return $list;
    }
    /**
     * 分红详情列表
     */
    public function getBonusInfoList($page_index, $page_size, $condition, $order = '', $where)
    {
        $bonus_grant = new VslBonusGrantModel();
        $list = $bonus_grant->getViewListInfo2($page_index, $page_size, $condition, 'nmar.grant_time desc');
        $level = new VslAgentLevelModel();
        if (! empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['grant_time'] = date('Y-m-d H:i:s',$v['grant_time']);
                $list['data'][$k]['level_name'] = $level->getInfo(['id'=>$list['data'][$k]['team_agent_level_id']])['level_name'];
            }
        }
        return $list;
    }
    /**
     * 分红流水列表
     */
    public function getBonusRecords($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $Bonus_withdraw = new VslAgentAccountRecordsModel();
        $list = $Bonus_withdraw->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['text'] = str_replace("待发放分红",$this->wit_bonus,$list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("冻结分红",$this->fre_bonus,$list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("已发放分红",$this->wits_bonus,$list['data'][$k]['text']);
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            }
        }
        return $list;
    }

    /**
     * 前端团队队长发货
     * @param $uid
     */
    public function teamCaptainDelivery($uid)
    {
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid], 'is_team_agent');
        $is_team_agent = $member_info['is_team_agent'];
        if($is_team_agent == 2){
            //获取下线直到下线为团队代理商
            $sub_uid_gather = [];
            $this->getDownTeamMember($uid, $member, $sub_uid_gather);
            return $sub_uid_gather;
        }
    }

    /**
     * 团队队长获取所有下级成员，直到成员为队长
     * @param $uid
     */
    public function getDownTeamMember($uid, $member, &$sub_uid_gather)
    {
        $member_list = $member->getQuery(['referee_id' => $uid], 'uid', '');
        if($member_list){
            foreach($member_list as $k=>$sub_uid){
                $sub_uid_gather[] = $sub_uid['uid'];
                //判断当前这条线的用户是否是队长，如果是队长则跳出循环
                $team_info = $member->getInfo(['uid' => $sub_uid['uid']]);
                $is_down_team_agent = $team_info['is_team_agent'];
                if(isset($team_info)){
                    if($is_down_team_agent != 2){
                        $this->getDownTeamMember($sub_uid['uid'], $member,$sub_uid_gather );
                    }else{
                        continue;
                    }
                }
            }
        }
    }

    /**
     * 得到团队队长发货订单列表
     * @param $page_index
     * @param $page_size
     * @param $condition
     * @param $fields
     * @return array
     */
    public function getOrderList($page_index, $page_size, $condition, $fields)
    {
        if(think\Cache::get('new_goods_list_'.$page_index.$condition['o.order_status'])){
            return think\Cache::get('new_goods_list_'.$page_index.$condition['o.order_status']);
        }
        $order = new VslOrderModel();
        $address_service = new Address();
//        $total_count = $order->alias('o')->where($condition)->count();
//        var_dump($total_count);
        $count_info = $order->alias('o')->field("count('distinct og.order_id') as total_count")->join('vsl_order_goods og', 'o.order_id = og.order_id', 'left')->where($condition)->find();
//        echo $order->getLastSql();exit;
        $total_count = $count_info['total_count'];
        $page_count = ceil($total_count/$page_size);
        $offset = ($page_index -1)* $page_size;
        $order_list = $order->alias('o')
            ->field($fields)
            ->join('vsl_order_goods og', 'o.order_id = og.order_id', 'left')
            ->where($condition)
            ->limit($offset, $page_size)
            ->order('o.create_time desc')
            ->select();
        $new_goods_list = [];
        foreach($order_list as $k => $order_info){
            if($k != 0){
                if($order_list[$k-1]['order_id'] != $order_list[$k]['order_id']){
                    $i = 0;
                }
            }else{
                $i = 0;
            }
            //处理商品
            $goods_id = $order_info['goods_id'];
            $goods_info = $this->goods_ser->getGoodsDetailById($goods_id, 'goods_id,price,goods_type,stock,picture', 1);
            $goods_img = getApiSrc($goods_info['album_picture']['pic_cover']);
            //获取省/市/区
            $province_name = $address_service->getProvinceName($order_info['receiver_province']);
            $city_name = $address_service->getCityName($order_info['receiver_city']);
            $district_name = $address_service->getDistrictName($order_info['receiver_district']);
            //获取地址
            $address = $address_service->getAddress($order_info['receiver_province'], $order_info['receiver_city'], $order_info['receiver_district']);
            $status_arr = OrderStatus::getOrderCommonStatus($order_list[$k]['order_type'], '', '', $goods_info['goods_type'])[$order_info['order_status']];
            //未发货时，团队队长可以操作发货
            if($order_info['order_status'] == 1){
                $member_operation = [
                    '0' => array(
                        'no' => 'delivery',
                        'name' => '发货',
                        'icon_class' => 'icon icon-pay-l',
                    ),
                ];
            }elseif($order_info['order_status'] == 2){
                $member_operation = [
                    '0' => array(
                        'no' => 'update_delivery',
                        'name' => '修改发货信息',
                        'icon_class' => 'icon icon-edit-l',
                    ),
                    '1' => array(
                        'no' => 'logistics',
                        'name' => '查看物流',
                        'icon_class' => 'icon icon-preview-l',
                    ),
                ];
            }
            $status_name = $status_arr['status_name'];
            $new_goods_list[$order_info['order_id']]['order_id'] = $order_info['order_id'];
            $new_goods_list[$order_info['order_id']]['order_no'] = $order_info['order_no'];
            $new_goods_list[$order_info['order_id']]['status_name'] = $status_name;
            $new_goods_list[$order_info['order_id']]['member_operation'] = $member_operation;
            $new_goods_list[$order_info['order_id']]['province_name'] = $province_name;
            $new_goods_list[$order_info['order_id']]['city_name'] = $city_name;
            $new_goods_list[$order_info['order_id']]['district_name'] = $district_name;
            $new_goods_list[$order_info['order_id']]['pcd_address'] = $address;
            $new_goods_list[$order_info['order_id']]['receiver_name'] = $order_info['receiver_name'];
            $new_goods_list[$order_info['order_id']]['receiver_mobile'] = $order_info['receiver_mobile'];
            $new_goods_list[$order_info['order_id']]['receiver_address'] = $order_info['receiver_address'];
            $new_goods_list[$order_info['order_id']]['order_item_list'][$i]['goods_id'] = $order_info['goods_id'];
            $new_goods_list[$order_info['order_id']]['order_item_list'][$i]['pic_cover'] = $goods_img;
            $new_goods_list[$order_info['order_id']]['order_item_list'][$i]['goods_name'] = $order_info['goods_name'];
            $new_goods_list[$order_info['order_id']]['order_item_list'][$i]['sku_name'] = $order_info['sku_name'];
            $new_goods_list[$order_info['order_id']]['order_item_list'][$i]['price'] = $order_info['price'];
            $new_goods_list[$order_info['order_id']]['order_item_list'][$i]['num'] = $order_info['num'];
            $i++;
        }
        $new_goods_list = array_values($new_goods_list);
        $return_data = [
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'delivery_order_list' => $new_goods_list,
                'page_count' => $page_count,
                'total_count' => $total_count,
            ]
        ];
        think\Cache::set('new_goods_list_'.$page_index.$condition['o.order_status'], $return_data, 5);
        return $return_data;
    }

    /**
     * 得到发货页面订单数据
     * @param $order_id
     * @param $condition
     * @param $fields
     * @return array
     */
    public function getDeliveryData($order_id, $condition, $fields)
    {
        $order = new VslOrderModel();
        $order_goods_express = new VslOrderGoodsExpressModel();
        $address_service = new Address();
        $order_list = $order->alias('o')
            ->field($fields)
            ->join('vsl_order_goods og', 'o.order_id = og.order_id', 'left')
            ->where(['o.order_id' => $order_id])
            ->order('o.create_time desc')
            ->select();
        $shipping_type = 1;
        $new_goods_list = [];
        foreach($order_list as $k => $order_info){
            //处理商品
            $goods_id = $order_info['goods_id'];
            $goods_info = $this->goods_ser->getGoodsDetailById($goods_id, 'goods_id,price,goods_type,stock,picture', 1);
            if($goods_info['goods_type'] == 3){
                $shipping_type = 0;
            }
            $goods_img = getApiSrc($goods_info['album_picture']['pic_cover']);
            //获取省/市/区
            $province_name = $address_service->getProvinceName($order_info['receiver_province']);
            $city_name = $address_service->getCityName($order_info['receiver_city']);
            $district_name = $address_service->getDistrictName($order_info['receiver_district']);
            //获取地址
            $address = $address_service->getAddress($order_info['receiver_province'], $order_info['receiver_city'], $order_info['receiver_district']);
            $new_goods_list[$order_id]['order_id'] = $order_info['order_id'];
            $new_goods_list[$order_id]['shipping_type'] = $shipping_type;//虚拟商品不需要物流信息
            $new_goods_list[$order_id]['province_name'] = $province_name;
            $new_goods_list[$order_id]['city_name'] = $city_name;
            $new_goods_list[$order_id]['district_name'] = $district_name;
            $new_goods_list[$order_id]['pcd_address'] = $address;
            $new_goods_list[$order_id]['receiver_address'] = $order_info['receiver_address'];
            $new_goods_list[$order_id]['receiver_name'] = $order_info['receiver_name'];
            $new_goods_list[$order_id]['receiver_mobile'] = $order_info['receiver_mobile'];
            $new_goods_list[$order_id]['order_goods'][$k]['pic_cover'] = $goods_img;
            $new_goods_list[$order_id]['order_goods'][$k]['goods_name'] = $order_info['goods_name'];
            $new_goods_list[$order_id]['order_goods'][$k]['order_goods_id'] = $order_info['order_goods_id'];
            $new_goods_list[$order_id]['order_goods'][$k]['goods_id'] = $order_info['goods_id'];
            $new_goods_list[$order_id]['order_goods'][$k]['sku_name'] = $order_info['sku_name'];
        }
        if($new_goods_list){
            $new_goods_list = array_values($new_goods_list);
        }
        $order_id = $new_goods_list[0]['order_id'];
        $express_cond['order_id'] = $order_id;
        $express_cond['website_id'] = $this->website_id;
        $express_info = $order_goods_express->getInfo($express_cond, 'id, express_company_id, express_company, express_no, uid, order_goods_id_array');
        if($order_list){
            if($express_info){
                $new_goods_list[0]['express_info'] = $express_info;
            }else{
                $new_goods_list[0]['express_info'] = (object)[];
            }
        }
        $res_data = $new_goods_list[0];
        //获取当前订单是否有物流信息
        if(!$new_goods_list[0]){
            $res_data = (object)[];
        }
        return $res_data;
    }
    /*
     * 购买成为队长
     */
    public function pay_becomeAgent($uid){
        $member = new VslMemberModel();
        $agent = $member->getInfo(['uid'=>$uid],'*');
        $base_info = $this->getTeamBonusSite($agent['website_id']);
        $agent_level = new AgentLevelModel();
        $level_info = $agent_level->getInfo(['website_id' => $agent['website_id'],'is_default'=>1,'from_type'=>3],'*');
        $level_id = $level_info['id'];
        $ratio = $level_info['ratio'].'%';
        $member_info = $this->getAgentLowerInfo($uid);
        if($agent['is_team_agent']!=2){//判断是否是队长
            if($base_info['is_use']==1){//判断是否开启团队分红
                $data = array(
                    "is_team_agent" => 2,
                    "team_agent_level_id" => $level_id,
                    "apply_team_agent_time" => time(),
                    "become_team_agent_time" => time(),
                );
                $member->save($data, ['uid' => $uid]);
                $account = new VslBonusAccountModel();
                $account_info = $account->getInfo(['website_id' => $agent['website_id'], 'from_type' => 3, 'uid' => $uid]);
                if (empty($account_info)) {
                    $account->save(['website_id' => $agent['website_id'], 'from_type' => 3, 'uid' => $uid]);
                }
                runhook("Notify", "sendCustomMessage", ["messageType"=>"become_team","uid" => $uid,"become_time" => time(),'ratio'=>$ratio,'level_name'=>$level_info['level_name']]);//用户成为全球股东提醒
            }
        }
        if($agent['referee_id']){
            $referee_info =  $member->getInfo(['uid'=>$agent['referee_id']],'*');
            if($referee_info['is_team_agent']!=2){
                $this->becomeAgent($agent['referee_id']);
            }
        }
    }
    /**
     * 购买升级等级
     */
    public function pay_updateAgentLevelInfo($uid,$team_agent_level_id)
    {
        $member = new VslMemberModel();
        $level = new AgentLevelModel();
        $agent = $member->getInfo(['uid'=>$uid],'*');
        $base_info = $this->getTeamBonusSite($agent['website_id']);
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        if($agent['is_team_agent']==2){
            $getAgentInfo = $this->getAgentLowerInfo($uid);//当前队长的详情信息
            $default_level_name = $level->getInfo(['id'=>$agent['team_agent_level_id']],'level_name')['level_name'];
            $level_infos = $level->getInfo(['id' => $team_agent_level_id,'from_type'=>3,'website_id'=>$agent['website_id']]);//比当前队长等级的权重高的等级信息
            $ratio = $level_infos['ratio'].'%';
            runhook("Notify", "sendCustomMessage", ['messageType'=>'team_upgrade_notice','uid' => $uid,'present_grade'=>$level_infos['level_name'],'primary_grade'=>$default_level_name,'ratio'=>$ratio,'upgrade_time' => time()]);//升级
            $member = new VslMemberModel();
            $member->save(['team_agent_level_id' => $level_infos['id'], 'up_team_level_time' => time(),'down_up_team_level_time'=>''], ['uid' => $uid]);
        }
    }
    /**
     * 升级条件
     */
    public function levelTeamConditions($uid='', $level_infos=[]){
        $result = [];
        if (empty($uid)) {
            return $result;
        }
        //团队分红
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        $distributor = new DistributorService();
        $member = new VslMemberModel();
        $conditions = explode(',', $level_infos['upgradeconditions']);
        $result = [];
        $getAgentInfo = $this->getAgentLowerInfo($uid); //当前队长的详情信息
        $agent = $member->getInfo(['uid' => $uid], '*');
        //获取分销文案设置
        $text = $distributor->getAgreementSite($agent['website_id']);
        //判断是否购买过指定商品
        $goods_info = [];
        $goods_name = '';
        if ($level_infos['goods_id']) {
            //获取商品名称
            $goods_info = $this->goods_ser->getGoodsDetailById($level_infos['goods_id'], 'goods_id,price,goods_name,stock');
            if ($goods_info) {
                $goods_name = $goods_info['goods_name'];
            }
            $goods_id = $order_goods->Query(['goods_id' => $level_infos['goods_id'], 'buyer_id' => $uid], 'order_id');
            if ($goods_id && $agent['down_up_team_level_time']) { //发生降级后 订单完成时间需大于降级时间
                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4, 'finish_time' => [">", $agent['down_up_team_level_time']]], '*');
            } elseif ($goods_id) {
                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4], '*');
            }
        }
        $distributor_name = '';
        if ($level_infos && $level_infos['upgrade_level']) {
            //获取指定分销等级名称
            $distributor_info = $distributor->getDistributorLevelInfo($level_infos['upgrade_level']);
            if ($distributor_info) {
                $distributor_name = $distributor_info['level_name'];
            }
            if ($agent['down_up_team_level_time']) {
                $low_number = $member->getCount(['distributor_level_id' => $level_infos['upgrade_level'], 'referee_id' => $uid, 'website_id' => $agent['website_id'], 'reg_time' => [">", $agent['down_up_team_level_time']]]); //该等级指定推荐等级人数
            } else {
                $low_number = $member->getCount(['distributor_level_id' => $level_infos['upgrade_level'], 'referee_id' => $uid, 'website_id' => $agent['website_id']]); //该等级指定推荐等级人数
            }
        } else {
            $low_number = 0;
        }
        foreach ($conditions as $k1 => $v1) {
            switch ($v1) {
                case 1:
                    $edata = array();
                    $edata['condition_name'] = "商城消费订单满";
                    $edata['condition_type'] = 1;
                    $edata['unit'] = "元";
                    $edata['up_number'] = $level_infos['pay_money'];
                    $edata['number'] = $getAgentInfo['selforder_money'];
                    array_push($result, $edata);
                    break;
                case 2:
                    $edata = array();
                    $edata['condition_name'] = "下线会员数满";
                    $edata['condition_type'] = 2;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['group_number'];
                    $edata['number'] = $getAgentInfo['agentcount'];
                    array_push($result, $edata);
                    break;
                case 3:
                    $edata = array();
                    $edata['condition_name'] = $text['team1'] . "满";
                    $edata['condition_type'] = 3;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['one_number'];
                    $edata['number'] = intval($getAgentInfo['one_number']);
                    array_push($result, $edata);
                    break;
                case 4:
                    $edata = array();
                    $edata['condition_name'] = $text['team2'] . "满";
                    $edata['condition_type'] = 4;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['two_number'];
                    $edata['number'] = intval($getAgentInfo['two_number']);
                    array_push($result, $edata);
                    break;
                case 5:
                    $edata = array();
                    $edata['condition_name'] = $text['team3'] . "满";
                    $edata['condition_type'] = 5;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['three_number'];
                    $edata['number'] = intval($getAgentInfo['three_number']);
                    array_push($result, $edata);
                    break;
                case 6:
                    $edata = array();
                    $edata['condition_name'] = $text['team1'] . "订单金额满";
                    $edata['condition_type'] = 6;
                    $edata['unit'] = "元";
                    $edata['up_number'] = $level_infos['order_money'];
                    $edata['number'] = $getAgentInfo['order_money'];
                    array_push($result, $edata);
                    break;
                case 7:
                    $edata = array();
                    $edata['condition_name'] = $goods_name;
                    $edata['condition_type'] = 7;
                    $edata['up_number'] = 1;
                    $edata['unit'] = "件";
                    $edata['number'] = 0;
                    if ($goods_info) {
                        $edata['number'] = 1;
                    }
                    array_push($result, $edata);
                    break;
                case 8:
                    $edata = array();
                    $edata['condition_name'] = "下线总人数满";
                    $edata['condition_type'] = 8;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['number'];
                    $edata['number'] = $getAgentInfo['agentcount1'];
                    array_push($result, $edata);
                    break;
                case 9:
                    $edata = array();
                    $edata['condition_name'] = "下级";
                    $edata['distributor_name'] = $distributor_name;
                    $edata['condition_type'] = 9;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['level_number'];
                    $edata['number'] = $low_number;
                    array_push($result, $edata);
                    break;
                case 11:
                    $edata = array();
                    $edata['condition_name'] = " 团队订单金额满";
                    $edata['condition_type'] = 11;
                    $edata['up_number'] = $level_infos['up_team_money'];
                    $edata['number'] = $getAgentInfo['up_team_money'];
                    $edata['unit'] = "元";
                    array_push($result, $edata);
                    break;
            }
        }

        $return['upgrade_condition'] = $level_infos['upgrade_condition'];
        $return['result'] = $result;
        return $return;
    }
    /**
     * 降级条件
     */
    public function downlevelTeamConditions($uid='', $level_infos=[]){
        $result = array();
        if (empty($uid)) {
            return $result;
        }
        $member = new VslMemberModel();
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        //团队分红
        $conditions = explode(',', $level_infos['downgradeconditions']);
        $result = array();
        $getAgentInfo = $this->getAgentLowerInfo($uid); //当前队长的详情信息
        //获取最长时间天数
        $maxdays = max($level_infos['team_number_day'], $level_infos['team_money_day'], $level_infos['self_money_day']);
        //获取会员升级时间
        $agent = $member->getInfo(['uid' => $uid], '*');
        $starttimes = $agent['up_team_level_time'] ? $agent['up_team_level_time'] : $agent['become_team_agent_time'];

        $starttime = date("m-d", $starttimes);
        $endtime = date("m-d", $starttimes + $maxdays * 24 * 60 * 60);
        //降级类型
        foreach ($conditions as $k1 => $v1) {
            switch ($v1) {
                case 1:
                    $team_number_day = $level_infos['team_number_day'];
                    $real_level_time = $member->getInfo(['uid' => $uid], 'up_team_level_time')['up_team_level_time'] + $team_number_day * 24 * 3600;
                    $getAgentInfo1 = $this->getAgentInfos($uid, $team_number_day);
                    $limit_number = $getAgentInfo1['agentordercount']; //限制时间段内团队分红订单数
                    $edata = array();
                    $edata['condition_name'] = "团队分红订单数小于";
                    $edata['condition_type'] = 1;
                    $edata['down_number'] = $level_infos['team_number'];
                    $edata['number'] = $getAgentInfo1['agentordercount'] ? $getAgentInfo1['agentordercount'] : 0;
                    $edata['days'] = $team_number_day;
                    array_push($result, $edata);
                    break;
                case 2:
                    $team_money_day = $level_infos['team_money_day'];
                    $real_level_time = $member->getInfo(['uid' => $uid], 'up_team_level_time')['up_team_level_time'] + $team_money_day * 24 * 3600;
                    $getAgentInfo2 = $this->getAgentInfos($uid, $team_money_day);
                    $limit_money1 = $getAgentInfo2['order_money']; //限制时间段内团队分红订单金额
                    $edata = array();
                    $edata['condition_name'] = "团队分红订单金额小于";
                    $edata['condition_type'] = 2;
                    $edata['down_number'] = $level_infos['team_money'];
                    $edata['number'] = $getAgentInfo2['order_money'] ? $getAgentInfo2['order_money'] : 0;
                    $edata['days'] = $team_money_day;
                    array_push($result, $edata);
                    break;
                case 3:
                    $self_money_day = $level_infos['self_money_day'];
                    $real_level_time = $member->getInfo(['uid' => $uid], 'up_team_level_time')['up_team_level_time'] + $self_money_day * 24 * 3600;
                    $getAgentInfo3 = $this->getAgentInfos($uid, $self_money_day);
                    $limit_money2 = $getAgentInfo3['selforder_money']; //限制时间段内自购分红订单金额
                    $edata = array();
                    $edata['condition_name'] = "自购分红订单金额小于";
                    $edata['condition_type'] = 3;
                    $edata['down_number'] = $level_infos['self_money'];
                    $edata['number'] = $getAgentInfo3['selforder_money'] ? $getAgentInfo3['selforder_money'] : 0;
                    $edata['days'] = $self_money_day;
                    array_push($result, $edata);
                    break;
            }
        }
        $return['starttime'] = $starttime;
        $return['endtime'] = $endtime;
        $return['downgrade_condition'] = $level_infos['downgrade_condition'];
        $return['result'] = $result;
        return $return;
    }
    /**
     * 团队分红升级详情
     */
    public function upTeamLevelDetail($uid,$website_id,$member){
        //获取所有团队等级
        $level = new VslAgentLevelModel();
        $teamlist = $this->getagentLevelList(1, '', ['website_id' => $website_id, 'from_type' => 3], 'weight asc');
        $tlist = array();
        foreach ($teamlist['data'] as $key => $value) {
            $rdata['level_name'] = $value['level_name'];
            $rdata['ratio'] = $value['ratio'];
            array_push($tlist, $rdata);
            if ($value['id'] == $member['team_agent_level_id']) {
                $user['level_name'] = $value['level_name'];
            }
        }
        //获取高等级
        $level_weight = $level->Query(['id' => $member['team_agent_level_id']], 'weight'); //当前队长的等级权重
        $level_weights = $level->Query(['weight' => ['>', implode(',', $level_weight)], 'from_type' => 3, 'website_id' => $website_id], 'weight'); //当前队长的等级权重的上级权重
        if ($level_weights) {
            sort($level_weights);
            $level_infos = $level->getInfo(['weight' => $level_weights[0], 'from_type' => 3, 'website_id' => $website_id]); //比当前队长等级的权重高的等级信息
            if ($level_infos['upgradetype'] == 1) {//是否开启自动升级
                //获取当前升级进度
                $levelCondition = $this->levelTeamConditions($uid, $level_infos, 1);
                if ($levelCondition) {
                    $levelCondition['levelname'] = $level_infos['level_name'];
                }
            } else {
                //没有开启自动升级,不显示升级条件
                $levelCondition = [];
            }
        } else {
            //本人是最高级,不显示升级条件
            $levelCondition = [];
        }
        //获取当降级进度
        $down_level_weights = $level->Query(['weight' => ['<', implode(',', $level_weight)], 'from_type' => 3, 'website_id' => $website_id], 'weight'); //分红商的等级权重的下级权重

        if ($down_level_weights) {
            //存在低等级 获取当前等级降级信息
            $level_infos = $level->getInfo(['weight' => $level_weight[0], 'from_type' => 3, 'website_id' => $website_id], '*');
            if ($level_infos['downgradetype'] == 1 && $level_infos['downgradeconditions']) {//是否开启自动降级并且有降级条件
                $downlevelCondition = $this->downlevelTeamConditions($uid, $level_infos, 1);
                if ($downlevelCondition) {
                    $down_level_infos = $level->getInfo(['weight' => $down_level_weights[0], 'from_type' => 3, 'website_id' => $website_id], 'level_name');
                    $downlevelCondition['levelname'] = $down_level_infos['level_name'];
                }
            } else {
                $downlevelCondition = [];
            }
        } else {
            $downlevelCondition = [];
        }
        return ['levelCondition'=>$levelCondition,'downlevelCondition'=>$downlevelCondition,'user'=>$user,'tlist'=>$tlist];
    }
}
