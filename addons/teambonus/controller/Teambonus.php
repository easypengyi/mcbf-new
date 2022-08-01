<?php
namespace addons\teambonus\controller;
use addons\distribution\model\SysMessageItemModel;
use addons\distribution\model\SysMessagePushModel;
use addons\teambonus\Teambonus as baseTeamBonus;
use addons\teambonus\service\TeamBonus as agentService;
use addons\distribution\service\Distributor as DistributorService;
use data\model\VslOrderModel;
use data\service\Config;
use data\service\Order;
use think\helper\Time;
use addons\bonus\model\VslAgentLevelModel as AgentLevelModel;
    /**
     * 队长设置控制器
     *
     * @author  www.vslai.com
     *
     */
    class Teambonus extends baseTeamBonus
    {
        public function __construct(){
            parent::__construct();
        }

    /**
     * 队长列表
     */
    public function teamAgentList(){
        $page_index = request()->post('page_index',1);
        $iphone = request()->post('iphone',"");
        $search_text = request()->post('search_text','');
        $agent_level_id = request()->post('level_id','');
        $isagent = request()->post('is_team_agent','');
        if( $search_text){
            $condition['us.user_name|us.nick_name'] = array('like','%'.$search_text.'%');
        }
        if($iphone ){
            $condition['nm.mobile'] = $iphone;
        }
        if($isagent!=5){
            $condition['nm.is_team_agent'] = $isagent;
        }else{
            $condition['nm.is_team_agent'] = ['in','1,2,-1'];
        }
        if($agent_level_id){
            $condition['nm.team_agent_level_id'] = $agent_level_id;
        }
        $condition['nm.website_id'] = $this->website_id;
        $agent = new agentService();
        $list = $agent->getagentList($page_index, PAGESIZE, $condition,'become_team_agent_time desc');

        return $list;
    }

    /**
     * 修改队长状态
     */
    public function setTeamAgentStatus(){
        if($this->merchant_expire==1){
            return AjaxReturn(-1);
        }
        $uid = request()->post('uid','');
        $status = request()->post('status','');
        $agent = new agentService();
        $retval = $agent->setStatus($uid, $status);
        if($retval){
            $this->addUserLog('修改队长状态', '队长id'.$uid);
        }
        return AjaxReturn($retval);
    }

    /**
     * 移除队长
     */
        public function delTeamAgent(){
        if($this->merchant_expire==1){
             return AjaxReturn(-1);
         }
        $member = new agentService();
        $uid = request()->post("uid", '');
        $res = $member->deleteAgent($uid);
        if($res){
            $this->addUserLog('移除队长', '队长id'.$uid);
        }
        return AjaxReturn($res);
    }

    /**
     * 修改队长信息
     */
    public function updateTeamAgentInfo(){
        if($this->merchant_expire==1){
            return AjaxReturn(-1);
        }
        $member = new agentService();
        $uid = request()->post("uid", '');
        $status = request()->post("status", '');
        $agent_level_id = request()->post("team_agent_level_id", '');
        $data = [
            'team_agent_level_id'=> $agent_level_id,
            'is_team_agent'=>$status
        ];
        $res= $member->updateAgentInfo($data,$uid);
        if($res){
            $this->addUserLog('修改队长信息', '队长id'.$uid);
        }
        return AjaxReturn($res);
    }

    /**
     * 队长等级列表
     */
    public function teamAgentLevelList(){
        $index = isset($_POST["page_index"]) ? $_POST["page_index"] : 1;
        $search_text = isset($_POST['search_text']) ? $_POST['search_text'] : '';
        $agent = new agentService();
        $list =  $agent->getagentLevelList($index, PAGESIZE, ['level_name' => array('like','%'.$search_text.'%'),'website_id'=>$this->website_id,'from_type'=>3],'weight asc');
        return json($list);
    }

    /**
     * 添加队长等级
     */
    public function addTeamAgentLevel(){

            $level_name = isset($_POST['level_name'])?$_POST['level_name']:'';//等级名称
            $ratio = isset($_POST['ratio'])?$_POST['ratio']:0;//分红比例 //edit for 2020/11/09 php 7.+空导致异常
            $upgradetype = isset($_POST['upgradetype'])?$_POST['upgradetype']:2;//自动升级
            $pay_money  = request()->post('pay_money', ''); // 自购订单消费金额额度
            $offline_number  = request()->post('offline_number', ''); // 下级客户人数
            $one_number = request()->post('one_number', '');//一级分销商人数
            $two_number = request()->post('two_number', '');//二级分销商人数
            $three_number = request()->post('three_number', '');//三级分销商人数
            $order_money = request()->post('order_money', ''); // 下级订单总额
            $downgradetype = isset($_POST['downgradetype'])?$_POST['downgradetype']:2;//自动降级
            $team_number = isset($_POST['team_number'])?$_POST['team_number']:'';//团队人数
            $team_money = isset($_POST['team_money'])?$_POST['team_money']:'';//团队订单金额
            $self_money = isset($_POST['self_money'])?$_POST['self_money']:'';//自购订单金额
            $team_number_day = isset($_POST['team_number_day'])?$_POST['team_number_day']:'';//时间段：团队人数
            $team_money_day = isset($_POST['team_money_day'])?$_POST['team_money_day']:'';//时间段：团队订单金额
            $self_money_day = isset($_POST['self_money_day'])?$_POST['self_money_day']:'';//时间段：自购订单金额
            $weight = isset($_POST['weight'])?$_POST['weight']:'';//权重
            $downgrade_condition = isset($_POST['downgrade_condition'])?$_POST['downgrade_condition']:'';//升级条件
            $upgrade_condition = isset($_POST['upgrade_condition'])?$_POST['upgrade_condition']:'';//降级条件
            $downgradeconditions = isset($_POST['downgradeconditions'])?$_POST['downgradeconditions']:'';//升级条件
            $upgradeconditions = isset($_POST['upgradeconditions'])?$_POST['upgradeconditions']:'';//降级条件
            $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:'';//指定商品id
            $upgrade_level = isset($_POST['upgrade_level'])?$_POST['upgrade_level']:'0';//推荐等级
            $level_number = isset($_POST['level_number'])?$_POST['level_number']:'0';//推荐等级人数
            $group_number  = request()->post('group_number', ''); // 团队人数

        $up_team_money = isset($_POST['up_team_money'])?$_POST['up_team_money']:'';//升级团队订单金额

        $level_award1 = isset($_POST['level_award1'])?$_POST['level_award1']:'';//平1
        $level_award2 = isset($_POST['level_award2'])?$_POST['level_award2']:'';//平2
        $level_award3 = isset($_POST['level_award3'])?$_POST['level_award3']:'';//平3
        $level_money1 = isset($_POST['level_money1'])?$_POST['level_money1']:'';//平1
        $level_money2 = isset($_POST['level_money2'])?$_POST['level_money2']:'';//平2
        $level_money3 = isset($_POST['level_money3'])?$_POST['level_money3']:'';//平3
        $bonus_type = isset($_POST['bonus_type'])?$_POST['bonus_type']:2;//分红方式 1-比例 2-固定金额
        $money = isset($_POST['money'])?$_POST['money']:0;//固定金额
        $agent=new agentService();
        $retval=$agent->addAgentLevel($level_name,$ratio,$upgradetype,$pay_money,$offline_number,$one_number,$two_number,$three_number,$order_money,$downgradetype,$team_number,$team_money,$self_money,$weight,$downgradeconditions,$upgradeconditions,$goods_id,$downgrade_condition,$upgrade_condition,$team_number_day,$team_money_day,$self_money_day,$upgrade_level,$level_number,$group_number,$up_team_money,$level_award1,$level_award2,$level_award3,$level_money1,$level_money2,$level_money3,$bonus_type,$money);
        if($retval){
            $this->addUserLog('添加队长等级', $retval);
        }
        return AjaxReturn($retval);
    }
    /**
     * 修改队长等级
     */
    public function updateTeamAgentLevel(){
        $agent=new agentService();
        $id = request()->post('id', '');
        $level_name = isset($_POST['level_name'])?$_POST['level_name']:'';//等级名称
        $ratio = isset($_POST['ratio'])?$_POST['ratio']:'';//分红比例
        $upgradetype = isset($_POST['upgradetype'])?$_POST['upgradetype']:2;//自动升级
        $pay_money  = request()->post('pay_money', ''); // 自购订单消费金额额度
        $offline_number  = request()->post('offline_number', ''); // 下级分销商人数
        $one_number = request()->post('one_number', '');//一级分销商人数
        $two_number = request()->post('two_number', '');//二级分销商人数
        $three_number = request()->post('three_number', '');//三级分销商人数
        $order_money = request()->post('order_money', ''); // 下级订单总额
        $downgradetype = isset($_POST['downgradetype'])?$_POST['downgradetype']:2;//自动降级
        $team_number = isset($_POST['team_number'])?$_POST['team_number']:'';//团队人数
        $team_money = isset($_POST['team_money'])?$_POST['team_money']:'';//团队订单金额
        $self_money = isset($_POST['self_money'])?$_POST['self_money']:'';//自购订单金额
        $team_number_day = isset($_POST['team_number_day'])?$_POST['team_number_day']:'';//时间段：团队人数
        $team_money_day = isset($_POST['team_money_day'])?$_POST['team_money_day']:'';//时间段：团队订单金额
        $self_money_day = isset($_POST['self_money_day'])?$_POST['self_money_day']:'';//时间段：自购订单金额
        $weight = isset($_POST['weight'])?$_POST['weight']:'';//权重
        $downgrade_condition = isset($_POST['downgrade_condition'])?$_POST['downgrade_condition']:'';//升级条件
        $upgrade_condition = isset($_POST['upgrade_condition'])?$_POST['upgrade_condition']:'';//降级条件
        $downgradeconditions = isset($_POST['downgradeconditions'])?$_POST['downgradeconditions']:'';//升级条件
        $upgradeconditions = isset($_POST['upgradeconditions'])?$_POST['upgradeconditions']:'';//降级条件
        $goods_id = isset($_POST['goods_id'])?$_POST['goods_id']:'';//指定商品id
        $upgrade_level = isset($_POST['upgrade_level'])?$_POST['upgrade_level']:'0';//推荐等级
        $level_number = isset($_POST['level_number'])?$_POST['level_number']:'0';//推荐等级人数
        $group_number  = request()->post('group_number', ''); // 团队人数
        $up_team_money = isset($_POST['up_team_money'])?$_POST['up_team_money']:'';//升级团队订单金额
        $level_award1 = isset($_POST['level_award1'])?$_POST['level_award1']:'';//平1级
        $level_award2 = isset($_POST['level_award2'])?$_POST['level_award2']:'';//平2
        $level_award3 = isset($_POST['level_award3'])?$_POST['level_award3']:'';//平3
        $level_money1 = isset($_POST['level_money1'])?$_POST['level_money1']:'';//平1
        $level_money2 = isset($_POST['level_money2'])?$_POST['level_money2']:'';//平2
        $level_money3 = isset($_POST['level_money3'])?$_POST['level_money3']:'';//平3
        $bonus_type = isset($_POST['bonus_type'])?$_POST['bonus_type']:1;//分红方式 1-比例 2-固定金额
        $money = isset($_POST['money'])?$_POST['money']:0;//固定金额
        $retval=$agent->updateAgentLevel($id,$level_name,$ratio,$upgradetype,$pay_money,$offline_number,$one_number,$two_number,$three_number,$order_money,$downgradetype,$team_number,$team_money,$self_money,$weight,$downgradeconditions,$upgradeconditions,$goods_id,$downgrade_condition,$upgrade_condition,$team_number_day,$team_money_day,$self_money_day,$upgrade_level,$level_number,$group_number,$up_team_money,$level_award1,$level_award2,$level_award3,$level_money1,$level_money2,$level_money3,$bonus_type,$money);
        if($retval){
            $this->addUserLog('修改队长等级', '队长等级id'.$id);
        }
        return AjaxReturn($retval);
    }
    /**
     * 删除 队长等级
     */
    public function deleteTeamAgentLevel()
    {
        $agent = new agentService();
        $id = request()->post("id", "");
        $res = $agent->deleteAgentLevel($id);
        if($res){
            $this->addUserLog('删除队长等级', '队长等级id'.$id);
        }
        return AjaxReturn($res);
    }
    /**
     * 分红概况
     */
    public function teamBonusProfile()
        {
            $agent_level = new AgentLevelModel();
            $level_info = $agent_level->getInfo(['website_id' => $this->website_id,'from_type'=>3,'is_default'=>1],'*');
                if($level_info){
                }else{
                    $data = array(
                        'level_name' => '默认团队代理等级',
                        'is_default'=>1,
                        'weight' => 1,
                        'ratio'=>0,
                        'from_type'=>3,
                        'create_time' => time(),
                        'website_id' => $this->website_id
                    );
                    $agent_level->save($data);
                }
            $website_id = $this->website_id;
            $agent = new agentService();
            $data = $agent->getAgentCount($website_id);
            return $data;
        }
        /**
         * 分红订单概况
         */
        public function teamBonusOrderProfile()
        {
            $website_id = isset($_POST['website_id'])?$_POST['website_id']:$this->website_id;
            $order_bonus = new agentService();
            list($start, $end) = Time::dayToNow(6,true);
            $orderType = ['订单金额','订单分红'];
            $data = array();
            $data['ordertype'] = $orderType;
            for($i=0;$i<count($orderType);$i++){
                switch ($orderType[$i]) {
                    case '订单金额':
                        $status = 1;
                        break;
                    case '订单分红':
                        $status = 2;
                        break;
                }
                for($j=0;$j<($end+1-$start)/86400;$j++){
                    $data['day'][$j]= date("Y-m-d",$start+86400*$j);
                    $date_start =  strtotime(date("Y-m-d H:i:s",$start+86400*$j));
                    $date_end =  strtotime(date("Y-m-d H:i:s",$start+86400*($j+1)));
                    if($status ==1){
                        $count = $order_bonus->getOrderMoneySum(['order_status'=>['between',[1,4]],'website_id'=>$website_id,'create_time'=>['between',[$date_start,$date_end]]]);
                    }
                    if($status == 2){
                        $count = $order_bonus->getPayMoneySum(['order_status'=>['between',[1,4]],'website_id'=>$website_id,'create_time'=>['between',[$date_start,$date_end]]]);
                    }
                    $aCount[$j] = $count;
                    $data['all'][$i]['name'] = $orderType[$i];
                    $data['all'][$i]['type'] = 'line';
                    $data['all'][$i]['data'] = $aCount;
                }
            }
            return $data;
        }

    /**
     * 基本设置
     */
    public function teamBasicSetting()
    {
        $config= new agentService();
        if (request()->isPost()) {
            // 基本设置
            $teambonus_status = request()->post('teambonus_status', ''); // 是否开启团队分红
            $level_award = request()->post('level_award', ''); // 是否开启平级
            $agent_condition = request()->post('teamagent_condition', ''); // 成为队长的条件
            $agent_conditions = request()->post('teamagent_conditions', ''); // 条件选择
            $pay_money  = request()->post('pay_money', ''); // 自购订单消费金额额度
            $number  = request()->post('number', ''); // 下级分销商人数
            $one_number = request()->post('one_number', '');//一级分销商人数
            $two_number = request()->post('two_number', '');//二级分销商人数
            $three_number = request()->post('three_number', '');//三级分销商人数
            $goods_id = request()->post('goods_id', ''); // 指定商品
            $order_money = request()->post('order_money', ''); // 下级订单总额
            $agent_check = request()->post('teamagent_check', ''); // 是否开启自动审核
            $agent_grade = request()->post('teamagent_grade', ''); // 是否开启跳级降级设置
            $purchase_type = request()->post('purchase_type', ''); // 是否开启内购分红
            $gradation_status = request()->post('gradation_status', ''); // 是否开启级差设置
            $agent_data = request()->post('teamagent_data', ''); // 开启资料完善

            $up_team_money = request()->post('up_team_money', ''); // 团队订单金额
            $teamagent_delivery = request()->post('teamagent_delivery', ''); // 是否队长发货

            $teambonus_admin_status = request()->post('teambonus_admin_status', 0); // 是否开启店铺分红

            $retval = $config->setTeamBonusSite($teambonus_status,$agent_condition, $agent_conditions, $pay_money,$number,$one_number,$two_number,$three_number, $order_money, $agent_check, $agent_grade, $goods_id,$purchase_type,$gradation_status,$agent_data,$up_team_money,$level_award, $teamagent_delivery,$teambonus_admin_status);
            if($retval){
                $this->addUserLog('基本设置', $retval);
            }
            setAddons('teambonus', $this->website_id, $this->instance_id);
            return AjaxReturn($retval);
        }
    }

    /**
     * 结算设置
     */
    public function teamSettlementSetting()
    {
        $config= new agentService();
        if (request()->isPost()) {
            // 结算设置
            $bonus_calculation = request()->post('bonus_calculation', ''); // 分红计算节点
            $withdrawals_check = request()->post('withdrawals_check', ''); // 自动分红是否开启
            $limit_time = request()->post('limit_time', ''); // 分红发放时间
            $limit_date = request()->post('limit_date', ''); // 分红发放指定日期
            $bonus_poundage = request()->post('bonus_poundage', ''); // 分红比例
            $poundage = request()->post('poundage', ''); // 个人所得税
            $withdrawals_begin = request()->post('withdrawals_begin', ''); // 分红免打税区间
            $withdrawals_end = request()->post('withdrawals_end', ''); // 分红免打税区间
            $retval = $config->setSettlementSite($bonus_calculation, $limit_time,$withdrawals_check, $bonus_poundage,$poundage,$withdrawals_begin,$withdrawals_end,$limit_date);
            if($retval){
                $this->addUserLog('结算设置', $retval);
            }
            return AjaxReturn($retval);
        }
    }
    /**
     * 申请协议
     */
    public function teamApplicationAgreement()
    {
        $config= new agentService();
        if (request()->isPost()) {
            // 基本设置
            $type = request()->post('type', 2);
            $logo = request()->post('image', ''); // 协议内容
            $content = request()->post('content', ''); // 协议内容
            $value = [];
            $value['bonus_name'] = request()->post('bonus_name', ''); // 分红中心
            $value['bonus'] = request()->post('bonus', ''); // 分红
            $value['withdrawals_bonus'] = request()->post('withdrawals_bonus', ''); // 已发放分红
            $value['withdrawal_bonus'] = request()->post('withdrawal_bonus', ''); // 待发放分红
            $value['frozen_bonus'] = request()->post('frozen_bonus', ''); // 冻结分红
            $value['bonus_details'] = request()->post('bonus_details', ''); // 分红明细
            $value['bonus_money'] = request()->post('bonus_money', ''); // 分红金额
            $value['bonus_order'] = request()->post('bonus_order', ''); // 分红订单
            $withdrawals_team_bonus = request()->post('withdrawals_team_bonus', ''); // 已发放分红
            $withdrawal_team_bonus = request()->post('withdrawal_team_bonus', ''); // 待发放分红
            $frozen_team_bonus = request()->post('frozen_team_bonus', ''); // 冻结分红
            $apply_team = request()->post('apply_team', ''); // 申请团队队长
            $team_agreement = request()->post('team_agreement', ''); // 团队队长
            if($type==1){
                $param = [
                    'value' => $value,
                    'instance_id' => 0,
                    'website_id' => $this->website_id,
                    'key' => 'BONUSCOPYWRITING',
                    'is_use' => 1
                ];
                $configs = new Config();
                $configs->setConfigOne($param);
            }
            if($content){
                $content = htmlspecialchars_decode($content);
            }
            $retval = $config->setAgreementSite($type,$logo,$content,$withdrawals_team_bonus,$withdrawal_team_bonus,$frozen_team_bonus,$apply_team,$team_agreement);
            if($retval){
                $this->addUserLog('团队队长申请协议', $retval);
            }
            return AjaxReturn($retval);
        }
    }
    /**
     * 后台团队分红明细
     */
    public function teamBonusList(){
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition = array();
        $condition['nmar.from_type'] = 3;
        $condition['nmar.website_id'] =$this->website_id;
        $group = 'nmar.sn';
        $bonus = new agentService();
        $list = $bonus->getBonusDetailList($page_index, $page_size,$condition,$group);
        return $list;
    }
    /**
     * 后台团队分红详情
     */
    public function teamBonusInfo(){
        if(request()->isPost()){
            $member_name = request()->post('member_name','');
            $agent_level_id = request()->post('level_id','');
            $mobile = request()->post('mobile','');
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $sn= request()->post('sn', '');
            if($member_name){
                $where['us.user_name|us.nick_name'] = array('like','%'.$member_name.'%');
            }
            if($mobile){
                $where['sm.mobile'] = $mobile;
            }
            if($agent_level_id){
                $where['sm.team_agent_level_id'] = $agent_level_id;
            }
            $condition = array();
            $condition['nmar.from_type'] = 3;
            $condition['nmar.sn'] = $sn;
            $condition['nmar.website_id'] =  $this->website_id;
            $bonus = new agentService();
            $list = $bonus->getBonusInfoList($page_index, $page_size,$condition,'',$where);
            return $list;
        }
    }
    /**
     * 下级分销商详情
     */
    public function lowerAgentList(){
        $uid = request()->post('uid',"");
        $index = request()->post('page_index',1);
        $iphone = request()->post('iphone',"");
        $search_text = request()->post('search_text','');
        $distributor_level_id = request()->post('level_id','');
        $isdistributor = request()->post('isdistributor','');
        //获取客户列表
        $types = request()->post('types',1);
        $iphone2 = request()->post('iphone2',"");
        $search_text2 = request()->post('search_text2','');
        if($types == 2){
            if( $search_text2){
                $condition['us.user_name|us.nick_name'] = array('like','%'.$search_text2.'%');
            }
            if($iphone2 ){
                $condition['nm.mobile'] = $iphone2;
            }
            if($uid){
                $condition['nm.uid'] = ['neq',$uid];
            }
            $condition['nm.isdistributor'] = ['neq',2];
            $condition['nm.website_id'] = $this->website_id;
            $distributor = new DistributorService();
            $list = $distributor->getDistributorList2($uid,$index, PAGESIZE, $condition,'nm.reg_time desc');
        }else{
            if( $search_text){
                $condition['nm.member_name'] = array('like','%'.$search_text.'%');
            }
            if($iphone ){
                $condition['nm.mobile'] = $iphone;
            }
            if($isdistributor){
                $condition['nm.isdistributor'] = $isdistributor;
            }
            if($distributor_level_id){
                $condition['nm.distributor_level_id'] = $distributor_level_id;
            }
            $condition['nm.isdistributor'] = ['neq',0];
            $condition['nm.website_id'] = $this->website_id;
            $distributor = new DistributorService();
            $list = $distributor->getDistributorList($uid,$index, PAGESIZE, $condition,'become_distributor_time desc');
        }
        return $list;
    }
    /**
     * 团队分红结算单
     */
    public function teamBonusBalance()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition = array();
        $condition['nmar.from_type'] = 3;
        $condition['nmar.website_id'] = $this->website_id;
        $condition['nmar.ungrant_bonus'] = ['>',0];
        $agent = new agentService();
        $data = $agent->getUnGrantBonus($page_index, $page_size, $condition, $order = '', $field = '*');
        return $data;
    }
    /**
     * 未分红发放
     */
    public function teamBonusGrant()
    {
        $agent = new agentService();
        $data = $agent->grantTeamBonus(1);
        return AjaxReturn($data);
    }
    public function teamMessagePushList(){
        $message = new SysMessagePushModel();
        $list = $message->getQuery(['website_id'=>$this->website_id,'type'=>4],'*','template_id asc');
        if(empty($list)){
            $list = $message->getQuery(['type'=>4,'website_id'=>0],'*','template_id asc');
            foreach ($list as $k=>$v){
                $array = [
                    'template_type' => $v['template_type'],
                    'template_title' => $v['template_title'],
                    'sign_item' => $v['sign_item'],
                    'sample' => $v['sample'],
                    'type'=>4,
                    'website_id' => $this->website_id,
                ];
                $message = new SysMessagePushModel();
                $message->save($array);
            }
            $list = $message->getQuery(['website_id'=>$this->website_id,'type'=>4],'*','template_id asc');
        }
        return $list;
    }
    public function teamEditMessage(){
        $id = request()->post('id', '');
        $message = new SysMessagePushModel();
        $list = $message->getInfo(['template_id'=>$id],'*');
        $item = new SysMessageItemModel();
        $list['sign'] = $item->getQuery(['id'=>['in',$list['sign_item']]],'*','');
        return $list;
    }
    public function addTeamMessage(){
        $is_enable = request()->post('is_enable', '');
        $id = request()->post('id', '');
        $template_content = request()->post('template_content', '');
        $message = new SysMessagePushModel();
        $res = $message->save(['is_enable'=>$is_enable,'template_content'=>$template_content],['template_id'=>$id]);
        return AjaxReturn($res);
    }

    /**
     * 前台申请成为队长接口
     */
    public function teamAgentApply(){
        $uid = $this->uid;
        $website_id =  $this->website_id;
        $post_data = request()->post('post_data','');
        $real_name = request()->post('real_name','');
        $member = new agentService();
        $res= $member->addAgentInfo($website_id,$uid,$post_data, $real_name);
        if($res>0){
            $data['code'] = 0;
            $data['message'] = "申请成功";
        }else{
            $data['code'] = -1;
            $data['message'] = "申请失败";
        }
        if($res){
            $this->addUserLog('前台申请成为股东接口', $uid);
        }
        return json($data);
    }

    /**
     * 前台查询申请队长状态接口
     */
    public function teamAgentStatus(){
        $uid = $this->uid;
        $member = new agentService();
        $info= $member->getAgentStatus($uid);
        return json($info);
    }

    /**
     * 前台团队分红明细
     */
    public function teamBonusDetail(){
        $uid = $this->uid;
        $page_index = request()->post('page', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition = array();
        $condition['nmar.uid'] = $uid;
        $condition['nmar.website_id'] = $this->website_id;
        $condition['nmar.bonus_type'] = 3;
        $bonus = new agentService();
        $list = $bonus->getBonusRecords($page_index, $page_size,$condition,'');
        return json($list);
    }
    /**
     * 团队队长 发货订单列表
     */
    public function teamCaptainDelivery()
    {
        $order_status = request()->post('order_status', 1);
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $search_text = request()->post('search_text', '');
        if(!$order_status){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $uid = $this->uid;
        //判断当前用户是否是队长
        $bonus = new agentService();
        $sub_uid_gather = $bonus->teamCaptainDelivery($uid);
        if(!$sub_uid_gather){
            $return_data = [
                'code' => 1,
                'message' => '获取成功',
                'data' => [
                ]
            ];
            return json($return_data);
        }
        //获取已支付未发货的订单列表
        $condition['o.order_type'] = ['neq', 12];//屏蔽门店订单
        $condition['o.buyer_id'] = ['in', $sub_uid_gather];
        if (!empty($search_text)) {
            $condition['order_no|shop_name|og.goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }
        $condition['o.order_status'] = $order_status;
        $condition['o.website_id'] = $this->website_id;
        $fields = 'o.order_id, o.order_no, o.order_type, o.order_status, og.goods_id, og.goods_name, og.num, og.price, og.sku_name, o.receiver_name, o.receiver_mobile, o.receiver_province, o.receiver_city, o.receiver_district, o.receiver_address, o.buyer_id';
        $delivery_order_list = $bonus->getOrderList($page_index, $page_size, $condition, $fields);
        return json($delivery_order_list);
    }

    /**
     * 发货页面数据
     */
    public function deliveryPageInfo()
    {
        $order_id = request()->post('order_id', 0);
//        $order_id = 100;
        $bonus = new agentService();
        $condition['o.order_id'] = $order_id;
        $fields = 'o.order_id, o.receiver_name, o.receiver_mobile, o.receiver_province, o.receiver_city, o.receiver_district, o.receiver_address, o.buyer_id, og.order_goods_id, og.goods_id, og.goods_name, og.sku_name';
        $delivery_data = $bonus->getDeliveryData($order_id, $condition, $fields);
        return json(['code' => 1, 'message'=>'获取成功', 'data' => [
            'delivery_data' => $delivery_data,
        ]]);
    }
//    /**
//     * 获取快递公司列表
//     */
//    public function getExpressList()
//    {
//        $express_service = new Express();
//        $condition = ['nr.website_id' => $this->website_id, 'nr.shop_id' => $this->instance_id];
//
//        // 快递公司列表
//        $express_company_list = $express_service->getExpressCompanyList(1, 0, $this->website_id, $this->instance_id, $condition, '')['data'];
//        return json(['code' => 1, 'message' => '获取成功', 'data' => [
//            'express_company_list' => $express_company_list
//        ]]);
//    }
    /**
     * 执行队长发货
     */
    public function actCaptainDelivery()
    {
        $order_service = new Order();
        $order_mdl = new VslOrderModel();
        $order_id = request()->post('order_id', '');
        $order_goods_id_array = request()->post('order_goods_id_array', '');//通过逗号分隔
        $express_name = request()->post('express_name', '');
        $shipping_type = request()->post('shipping_type', '');
        $express_company_id = request()->post('express_company_id', '');
        $express_no = trim(request()->post('express_no', ''));
        //虚拟商品是不需要物流信息的
        $order_goods_list = $order_service->getOrderGoods($order_id);
        $goods_type = 0;
        if($order_goods_list){
            $goods_type = $order_goods_list[0]['goods_type'];
        }
        if(!$express_no && $goods_type!=3){
            return AjaxReturn(0);
        }
        if($goods_type == 3){
            $shipping_type = 0; //虚拟商品变更为0，不用物流
        }
        $express_no = str_replace(' ', '', $express_no);
//        $memo = request()->post('seller_memo');
        $order_info = $order_mdl->getInfo(['order_id' => $order_id], 'order_status');
        if($order_info['order_status'] != 1){
            return  json(['code' => -1,'message' => '操作失败，订单状态已改变']);
        }

        if ($shipping_type == 1) {
            $res = $order_service->orderDelivery($order_id, $order_goods_id_array, $express_name, $shipping_type, $express_company_id, $express_no);
            //发货后将待发货状态改成待收获
        } else {
            $res = $order_service->orderGoodsDelivery($order_id, $order_goods_id_array);
        }
//        if (!empty($memo)) {
//            $data['order_id'] = $order_id;
//            $data['memo'] = $memo;
//            $data['uid'] = $this->uid;
//            $data['create_time'] = time();
//            $order_service->addOrderSellerMemoNew($data);
//        }
        if($res){
            return json(['code' => 1, 'message' => '发货成功']);
        }else{
            return json(['code' => -1, 'message' => '发货失败']);
        }
    }

    /**
     * 更新物流信息接口
     * @return \multitype
     */
    public function updateOrderDelivery()
    {
        $order_service = new Order();
        $id = request()->post('id');
//            $order_id = request()->post('order_id');
        $express_company_id = request()->post('express_company_id');
        $express_company = request()->post('express_company');
        $express_no = trim(request()->post('express_no', ''));
        if(!$express_no){
            return AjaxReturn(0);
        }
        $express_no = str_replace(' ', '', $express_no);
        $express_name = request()->post('express_name');
        $data['express_company_id'] = $express_company_id;
        $data['express_company'] = $express_company;
        $data['express_name'] = $express_name;
        $data['express_no'] = $express_no;
        $result = $order_service->updateDelivery($id, $data);
        unset($data);
//            $memo = request()->post('seller_memo');
//            if (!empty($memo)) {
//                $data['order_id'] = $order_id;
//                $data['memo'] = $memo;
//                $data['uid'] = $this->uid;
//                $data['create_time'] = time();
//                $order_service->addOrderSellerMemoNew($data);
//            }
        if($result){
            return json(['code' => 1, 'message' => '修改成功']);
        }else{
            return json(['code' => -1, 'message' => '修改失败']);
        }
    }
}
