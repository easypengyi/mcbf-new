<?php
namespace addons\paygrade\server;

use addons\paygrade\model\VslPayGradeModel;
use addons\paygrade\model\VslPayGradeConfigModel;
use addons\paygrade\model\VslPayGradeSetmealModel;
use addons\paygrade\model\VslPayGradeRecordsModel;
use addons\paygrade\model\VslPayGradeOrderModel;
use data\service\AddonsConfig;
use data\service\BaseService;
use data\model\VslMemberLevelModel as MemberLevelModel;
use addons\distribution\model\VslDistributorLevelModel as DistributorLevelModel;
use addons\bonus\model\VslAgentLevelModel as AgentLevelModel;
use addons\channel\model\VslChannelLevelModel as ChannelLevelModel;
use data\model\VslMemberModel;
use data\service\UnifyPay;
use think\Log;
use think\Db;
use data\service\Member as MemberService;
use addons\distribution\service\Distributor as DistributorService;
use addons\globalbonus\service\GlobalBonus as GlobalService;
use addons\areabonus\service\AreaBonus as AreaService;
use addons\teambonus\service\TeamBonus as TeamService;
use addons\channel\server\Channel as ChannelServer;
use data\model\VslOrderPaymentModel;
class PayGrade extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }
    
    /**
     * 获取等级列表
     * @param array $condition
     * @param string $order
     */
    public function getPayGradeList($condition, $order = 'create_time desc')
    {
        $paygrade = new VslPayGradeModel();
        $list = $paygrade->getPayGradeList($condition,'pay_grade_id,grade_type,is_use,is_putaway,demotion_id,shop_id,sort,create_time,update_time,website_id', $order);
        if($list){
            $setmeal = new VslPayGradeSetmealModel();
            foreach ($list as $k => $v) {
                $list[$k]['grade_name'] = $this->getGradeName($v['grade_type']);
                $list[$k]['setmeal_list'] = $setmeal->getSetmealList(['pay_grade_id'=>$v['pay_grade_id'], 'website_id'=>$condition['website_id']],'*','sort desc');
                if($list[$k]['setmeal_list']){
                    foreach ($list[$k]['setmeal_list'] as $k2 => $v2) {
                        $list[$k]['setmeal_list'][$k2]['grade_level_name'] = $this->getGradeInfo($v['grade_type'],$v2['grade_type_id'])['level_name'];
                    }
                }
            }
        }
        return $list;
    }
    /**
     * 获取等级详情
     */
    public function getPayGradeDetail($condition,$field = '*')
    { 
        $paygrade = new VslPayGradeModel();
        $info = $paygrade->getDetail($condition,$field);
        if($info){
            $info['grade_name'] = $this->getGradeName($info['grade_type']);
        }else{
            $info = array();
        }
        // $info['grade_name'] = $this->getGradeName($info['grade_type']);
        return $info;
    }
    /**
     * 获取对应的等级名称
     */
    public function getGradeName($grade_type)
    {
        $name = '';
        if($grade_type == 0){
            $name = '会员等级';
        }else if($grade_type == 1){
            $name = '分销商等级';
        }else if($grade_type == 2){
            $name = '股东等级';
        }else if($grade_type == 3){
            $name = '区代等级';
        }else if($grade_type == 4){
            $name = '队长等级';
        }else if($grade_type == 5){
            $name = '渠道商等级';
        }else if($grade_type == 6){
            $name = '店长等级';
        }else if($grade_type == 7){
            $name = '入驻商家等级';
        }
        return $name;
    }
    /**
     * 获取对应的等级列表
     */
    public function getGradeList($grade_type,$is_default = 1)
    {
        $list = [];
        $where = [];
        if($grade_type == 0){
            $member_level = new MemberLevelModel();
            $where['website_id'] = $this->website_id;
            if($is_default == 0)$where['is_default'] = ['neq',1];
            $member_level_list = $member_level->getQuery($where, '*', '');
            if($member_level_list){
                foreach ($member_level_list as $k => $v) {
                    $list[$k]['level_id'] = $v['level_id'];
                    $list[$k]['level_name'] = $v['level_name'];
                    $list[$k]['growth_num'] = $v['growth_num'];
                    $list[$k]['goods_discount'] = $v['goods_discount'];
                }
            }
        }else if($grade_type == 1 && getAddons('distribution', $this->website_id)){
            $distributor_level = new DistributorLevelModel();
            $where['website_id'] = $this->website_id;
            $distributor_level_this = $distributor_level->where($where);
            if($is_default == 0)$distributor_level_this->where('is_default', null);//is_default默认为null
            $distributor_level_list = $distributor_level_this->order('weight asc')->select();
            if($distributor_level_list){
                foreach ($distributor_level_list as $k => $v) {
                    $list[$k]['level_id'] = $v['id'];
                    $list[$k]['level_name'] = $v['level_name'];
                    $list[$k]['recommend_type'] = $v['recommend_type'];
                    $list[$k]['commission1'] = $v['commission1'];
                    $list[$k]['commission2'] = $v['commission2'];
                    $list[$k]['commission3'] = $v['commission3'];
                    $list[$k]['commission_point1'] = $v['commission_point1'];
                    $list[$k]['commission_point2'] = $v['commission_point2'];
                    $list[$k]['commission_point3'] = $v['commission_point3'];
                    $list[$k]['commission11'] = $v['commission11'];
                    $list[$k]['commission22'] = $v['commission22'];
                    $list[$k]['commission33'] = $v['commission33'];
                    $list[$k]['commission_point11'] = $v['commission_point11'];
                    $list[$k]['commission_point22'] = $v['commission_point22'];
                    $list[$k]['commission_point33'] = $v['commission_point33'];
                    $list[$k]['recommend1'] = $v['recommend1'];
                    $list[$k]['recommend2'] = $v['recommend2'];
                    $list[$k]['recommend3'] = $v['recommend3'];
                    $list[$k]['recommend_point1'] = $v['recommend_point1'];
                    $list[$k]['recommend_point2'] = $v['recommend_point2'];
                    $list[$k]['recommend_point3'] = $v['recommend_point3'];
                }
            }
        }else if($grade_type == 2 && getAddons('globalbonus', $this->website_id) || $grade_type == 3 && getAddons('areabonus', $this->website_id) || $grade_type == 4 && getAddons('teambonus', $this->website_id)){
            if($grade_type == 2)$from_type = 1;
            if($grade_type == 3)$from_type = 2;
            if($grade_type == 4)$from_type = 3;
            $agent_level = new AgentLevelModel();
            $where['website_id'] = $this->website_id;
            $where['from_type'] = $from_type;
            if($is_default == 0)$where['is_default'] = ['neq',1];
            $agent_level_list = $agent_level->getQuery($where, '*', 'weight asc');
            if($agent_level_list){
                foreach ($agent_level_list as $k => $v) {
                    $list[$k]['level_id'] = $v['id'];
                    $list[$k]['level_name'] = $v['level_name'];
                    $list[$k]['ratio'] = $v['ratio'].'%';
                }
            }
        }else if($grade_type == 5 && getAddons('channel', $this->website_id)){
            $channel_level = new ChannelLevelModel();
            $where['website_id'] = $this->website_id;
            if($is_default == 0)$where['is_default'] = ['neq',1];
            $channel_level_list = $channel_level->getQuery($where, '*', 'weight asc');
            if($channel_level_list){
                foreach ($channel_level_list as $k => $v) {
                    $list[$k]['level_id'] = $v['channel_grade_id'];
                    $list[$k]['level_name'] = $v['channel_grade_name'];
                    $list[$k]['purchase_discount'] = ($v['purchase_discount']*100).'%';
                }
            }
        }
        return $list;
    }
    /**
     * 获取对应的等级信息
     */
    public function getGradeInfo($grade_type,$grade_id)
    {
        
        $data = [];
        $data['level_id'] = 0;
        $data['level_name'] = '无';
        $data['weight'] = 0;
        $where = [];
        $data['top'] = true;
        if($grade_type == 0){
            $member_level = new MemberLevelModel();
            $where['level_id'] = $grade_id;
            $where['website_id'] = $this->website_id;
            $member_level_info = $member_level->getInfo($where,'*');
            if($member_level_info){
                $data['level_id'] = $member_level_info['level_id'];
                $data['level_name'] = $member_level_info['level_name'];
                $data['weight'] = $member_level_info['growth_num'];
                //查询是否为最高级别
                $condition_up['website_id'] = $this->website_id;
                $condition_up['growth_num'] = ['>', $data['weight']];
                $up_level_info = $member_level->getInfo($condition_up,'*');
                if($up_level_info){
                    $data['top'] = false;
                }
            }
        }else if($grade_type == 1 && getAddons('distribution', $this->website_id)){
            $distributor_level = new DistributorLevelModel();
            $where['id'] = $grade_id;
            $where['website_id'] = $this->website_id;
            $distributor_level_info = $distributor_level->getInfo($where,'*');
            if($distributor_level_info){
                $data['level_id'] = $distributor_level_info['id'];
                $data['level_name'] = $distributor_level_info['level_name'];
                $data['weight'] = $distributor_level_info['weight'];
                //查询是否为最高级别
                $condition_up['website_id'] = $this->website_id;
                $condition_up['weight'] = ['>', $data['weight']];
                $up_level_info = $distributor_level->getInfo($condition_up,'*');
                if($up_level_info){
                    $data['top'] = false;
                }
            }
        }else if($grade_type == 2 && getAddons('globalbonus', $this->website_id) || $grade_type == 3 && getAddons('areabonus', $this->website_id) || $grade_type == 4 && getAddons('teambonus', $this->website_id)){
            if($grade_type == 2)$from_type = 1;
            if($grade_type == 3)$from_type = 2;
            if($grade_type == 4)$from_type = 3;

            $agent_level = new AgentLevelModel();
            $where['id'] = $grade_id;
            $where['website_id'] = $this->website_id;
            $where['from_type'] = $from_type;
            
            $agent_level_info = $agent_level->getInfo($where,'*');
            if($agent_level_info){
                $data['level_id'] = $agent_level_info['id'];
                $data['level_name'] = $agent_level_info['level_name'];
                $data['weight'] = $agent_level_info['weight'];
                //查询是否为最高级别
                $condition_up['website_id'] = $this->website_id;
                $condition_up['from_type'] = $from_type;
                $condition_up['weight'] = ['>', $data['weight']];
                $up_level_info = $agent_level->getInfo($condition_up,'*');
                if($up_level_info){
                    $data['top'] = false;
                }
            }
        }else if($grade_type == 5 && getAddons('channel', $this->website_id)){
            $channel_level = new ChannelLevelModel();
            $where['channel_grade_id'] = $grade_id;
            $where['website_id'] = $this->website_id;
            $channel_level_info = $channel_level->getInfo($where,'*');
            if($channel_level_info){
                $data['level_id'] = $channel_level_info['channel_grade_id'];
                $data['level_name'] = $channel_level_info['channel_grade_name'];
                $data['weight'] = $channel_level_info['weight'];
                //查询是否为最高级别
                $condition_up['website_id'] = $this->website_id;
                $condition_up['weight'] = ['>', $data['weight']];
                $up_level_info = $channel_level->getInfo($condition_up,'*');
                if($up_level_info){
                    $data['top'] = false;
                }
            }
            
        }
        return $data;
    }
    /**
     * 获取配置
     */
    public function getConfigDetail($condition)
    {
        $config = new VslPayGradeConfigModel();
        $info = $config->getDetail($condition,'config_id,introduce_name,introduce_content,agreement_name,agreement_content');
        return $info;
    }
    /**
     * @param array $input
     * @return int
     */
    public function addPayGrade($input)
    {
        $paygrade = new VslPayGradeModel();
        $paygrade->startTrans();
        try {
            $input['create_time'] = time();
            $res = $paygrade->save($input);
            $paygrade->commit();
            return $res;
        } catch (\Exception $e) {
            $paygrade->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * @param array $input
     * @return int
     */
    public function updatePayGrade($input, $where)
    {
        $paygrade = new VslPayGradeModel();
        $paygrade->startTrans();
        try {
            $input['update_time'] = time();
            $paygrade->save($input,$where);
            $paygrade->commit();
            return 1;
        } catch (\Exception $e) {
            $paygrade->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 获取套餐列表
     * @param array $condition
     * @param string $order
     */
    public function getSetmealList($condition, $order = 'create_time desc')
    {
        $setmeal = new VslPayGradeSetmealModel();
        $list = $setmeal->getSetmealList($condition,'*', $order);
        return $list;
    }
    
    /**
     * 修改套餐
     */
    public function updateSetmeal($input, $pay_grade_id)
    {
        $setmeal = new VslPayGradeSetmealModel();
        $website_id = $this->website_id;
        $inputs = $input['list'];
        $setmeal->startTrans();
        try {
            $data = $where = [];
            $ids = $setmeal->Query(['pay_grade_id'=>$pay_grade_id],'set_meal_id');
            foreach ($inputs as $k=>$v){
                $where['set_meal_id'] = $v['set_meal_id'];
                $data['grade_type_id'] = $v['grade_type_id'];
                $data['granularity'] = $v['granularity'];
                $data['effective_time'] = $v['effective_time'];
                $data['price'] = $v['price'];
                $data['sort'] = $k;
                if($v['set_meal_id']>0){
                    $data['update_time'] = time();
                    $setmeal->where($where)->update($data);
                    $ids = array_diff($ids, [$v['set_meal_id']]);
                }else{
                    $data['create_time'] = time();
                    $data['pay_grade_id'] = $pay_grade_id;
                    $data['website_id'] = $website_id;
                    $setmeal->insert($data);
                    $ids = [];
                }
            }
            if($ids){
                foreach ($ids as $v2){
                    $setmeal->delData(['set_meal_id'=>$v2]);
                }
            }
            $setmeal->commit();
            return 1;
        } catch (\Exception $e) {
            $setmeal->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 添加配置
     * @param array $input
     * @return int
     */
    public function addConfig($input)
    {
        $config = new VslPayGradeConfigModel();
        $config->startTrans();
        try {
            $input['create_time'] = time();
            $res = $config->save($input);
            $config->commit();
            return $res;
        } catch (\Exception $e) {
            $config->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 修改配置
     * @param array $input
     * @return int
     */
    public function updateConfig($input ,$where)
    {
        $config = new VslPayGradeConfigModel();
        $config->startTrans();
        try {
            $input['update_time'] = time();
            $config->save($input,$where);
            $config->commit();
            return 1;
        } catch (\Exception $e) {
            $config->rollback();
            return $e->getMessage();
        }
    }
    
    /**
     * 获取购买记录分页列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     * @param string $order
     */
    public function getRecordList($page_index, $page_size, $condition, $order = 'create_time desc')
    {
        $records = new vslPayGradeRecordsModel();
        $list = $records->getRecordViewList($page_index, $page_size, $condition, $order);
        if ($list['data']) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['pay_grade_name'] = $this->getGradeName($v['grade_type']);
                $list['data'][$k]['grade_type_name'] = $this->getGradeInfo($v['grade_type'],$v['grade_type_id'])['level_name'];
                $list['data'][$k]['demotion_name'] = $this->getGradeInfo($v['grade_type'],$v['demotion_id'])['level_name'];
                $list['data'][$k]['user_name'] = ($v['nick_name']) ? $v['nick_name'] : ($v['user_name'] ? $v['user_name'] : ($v['user_tel'] ? $v['user_tel'] : $v['uid']));
                $list['data'][$k]['user_headimg'] = __IMG($v['user_headimg']);
                unset($list['data'][$k]['nick_name']);
            }
        }
        return $list;
    }
    
    /**
     * 获取购买记录详情
     * @param array $condition
     * @param string $field
     */
    public function getRecordDetail($condition, $field, $order = 'create_time desc')
    {
        $records = new vslPayGradeRecordsModel();
        $detail = $records->getRecordDetail($condition, $field, $order);
        if($detail){
            $info = $this->getGradeInfo($detail['grade_type'],$detail['grade_type_id']);
            $detail['weight'] = $info['weight'];
            $detail['grade_type_name'] = $info['level_name'];
        }
        return $detail;
    }
    
    public function saveConfig($is_paygrade)
    {
        $AddonsConfig = new AddonsConfig(); 
        $info = $AddonsConfig->getAddonsConfig("paygrade");
        debugLog($info,'==>saveConfig2<==');
        if (!empty($info)) {
            debugLog('==>saveConfig1<==');
            $res = $AddonsConfig->updateAddonsConfig('', '付费等级设置', $is_paygrade, 'paygrade');
        } else {
            debugLog('==>saveConfig2<==');
            $res = $AddonsConfig->addAddonsConfig('', '付费等级设置', $is_paygrade, 'paygrade');
        }
        return $res;
    }
    /**
     * 创建购买等级支付订单 
     */
    public function createPurchaseOrder($set_meal_id,$pay_grade_id,$uid,$website_id,$out_trade_no,$t_type=1){
        Db::startTrans();
        try {
            if(empty($set_meal_id) || empty($pay_grade_id) || empty($uid) || empty($website_id) || empty($out_trade_no)){
                return -2;//缺少参数
            }
            //开始查找对应等级套餐信息
            $setmeal_list = $this->getSetmealList(['pay_grade_id'=>$pay_grade_id],'sort desc');
            if(empty($setmeal_list)){
                return -3;//套餐已不存在或者已变更
            }
            $data = array();
            foreach ($setmeal_list as $key => $value) {
                if($value['set_meal_id'] == $set_meal_id){
                    $data = $setmeal_list[$key];
                    break;
                }
            }
            if(empty($data)){
                return -3;//详情套餐已不存在或者已变更
            }
            //pay_grade_id 换取grade_type
            $payGradeModel = new VslPayGradeModel();
            $payGradeInfo = $payGradeModel->getInfo(['pay_grade_id'=>$pay_grade_id],'grade_type');
            if(empty($payGradeInfo)){
                return -3;//详情套餐已不存在或者已变更
            }
            $insert_data = array(
                'uid' => $uid,
                'website_id' => $website_id,
                'pay_grade_id' => $pay_grade_id,
                'grade_type' => $payGradeInfo['grade_type'], // 0会员等级 1分销商等级 2股东等级 3区代等级 4队长等级 5渠道商等级 6店长等级 7入驻商家等级
                'grade_type_id' => $set_meal_id,
                'price' => $data['price'],
                'create_time' => time(),
            );
            if($t_type == 1){
                $insert_data['out_trade_no'] = $out_trade_no;
                $payGradeOrderModel = new VslPayGradeOrderModel();
                $res = $payGradeOrderModel->save($insert_data);
                if($res){ 
                    $pay = new UnifyPay();
                    $name = $this->getGradeName($payGradeInfo['grade_type']);
                    $pay->createPayment(0, $out_trade_no, '购买'.$name.'等级', '购买'.$name.'等级', $insert_data['price'], 99, $res,'');
                }
            }else{
                $payGradeOrderModel = new VslPayGradeOrderModel();
                $res = $payGradeOrderModel->save($insert_data,['out_trade_no'=>$out_trade_no,'uid'=>$uid]);
                $payment_data = array(
                    'pay_body'      => '购买'.$name.'等级',
                    'pay_detail'    => '购买'.$name.'等级',
                    'pay_money'     => $insert_data['price'],
                    'create_time'   => time(),
                );
                $paymentModel = new VslOrderPaymentModel();
                $paymentModel->save($payment_data,['out_trade_no'=>$out_trade_no]);
            }
            Db::commit();
            return $res;
        } catch (\Throwable $th) {
            Db::rollback();
            return $th->getMessage();
        }
        
    }
    /**
     * 检测团队分红等级
     */
    public function checkStatus($uid,$set_meal_id){
       
        if(empty($uid) || empty($set_meal_id)){
            return -1;
        }
        return 1;
        //改处需要变更  检测各种类型
        $memberModel = new VslMemberModel();
        $groupTicketLevelModel = new VslGroupTicketLevelModel();
        $users = $memberModel->getInfo(['uid'=>$uid],'is_groupticket,groupticket_level_id');
        //set_meal_id换取等级Id
        $setmeal = new VslPayGradeSetmealModel();
        $up_info = $setmeal->getInfo(['set_meal_id'=>$set_meal_id],'grade_type_id');
        
        if(!$up_info){
            return -1;
        }
        if($users['is_groupticket'] == 1){
            $this_level_weight = $groupTicketLevelModel->getInfo(['id'=>$users['groupticket_level_id']],'weight')['weight'];
            $up_level_weight = $groupTicketLevelModel->getInfo(['id'=>$up_info['grade_type_id']],'weight')['weight'];
            if($this_level_weight >= $up_level_weight){
                return -1;
            }
        }else{
            return 1;
        }
        return 1;
    }
    /**
     * 购买等级 支付成功后处理
     */
    public function orderOnLinePay($out_trade_no='', $pay_type=1,$pay_from=3){
        debugLog($out_trade_no, $pay_type.'==>购买等级 支付成功后处理<==');
        if(empty($out_trade_no)){
            return false;
        }
        //订单号换取相应等级信息
        $payGradeOrderModel = new VslPayGradeOrderModel();
        $order_info = $payGradeOrderModel->getInfo(['out_trade_no'=>$out_trade_no],'*');
        if(empty($order_info)){
            return;
        }
        if($order_info['pay_status'] == 1){
            return;//已支付处理
        }
        $pyGradeSetmealModel = new VslPayGradeSetmealModel();
        $setmeal_info = $pyGradeSetmealModel->getInfo(['set_meal_id'=>$order_info['grade_type_id']],'*');
        if(empty($setmeal_info)){
            return;
        }
        //获取该类型降级信息
        $payGradeModel = new VslPayGradeModel();
        $payGrade_info = $payGradeModel->getInfo(['pay_grade_id'=>$order_info['pay_grade_id']],'demotion_id');
        
        //edit for 2929/11/13 重复购买同等级 累计时效
        $payGradeRecordsModel = new VslPayGradeRecordsModel();
        $check_info = $payGradeRecordsModel->getFirstData(['website_id'=>$order_info['website_id'],'uid'=>$order_info['uid'],'pay_grade_id'=>$order_info['pay_grade_id'],'grade_type_id'=>$setmeal_info['grade_type_id'],'grade_type'=>$order_info['grade_type']]);
        if($check_info){
            //更新时效，并使低等级时间失效  时长累加 -- 记录保留
            //起始时间变更为上一次购买的起始时间 --  end_time
            $start_time =  $check_info['end_time'];
            //粒度 1年 2季 3月 获取到期时间
            $effective_time = $setmeal_info['effective_time'];
            if($setmeal_info['granularity'] == 1){
                $end_time = strtotime("+$effective_time year",$start_time);
            }else if($setmeal_info['granularity'] == 2){
                $effective_time = $effective_time*3;
                $end_time = strtotime("+$effective_time month",$start_time);
            }else{
                $end_time = strtotime("+$effective_time month",$start_time);
            }
            // return;//已存在
        }else{
            //粒度 1年 2季 3月 获取到期时间
            $effective_time = $setmeal_info['effective_time'];
            if($setmeal_info['granularity'] == 1){
                $end_time = strtotime("+$effective_time year");
            }else if($setmeal_info['granularity'] == 2){
                $effective_time = $effective_time*3;
                $end_time = strtotime("+$effective_time month");
            }else{
                $end_time = strtotime("+$effective_time month");
            }
        }
        
        $payGradeRecordsModel->startTrans();
        try {
            $datas = array(
                'website_id' => $order_info['website_id'],
                'uid' => $order_info['uid'],
                'pay_grade_id' => $order_info['pay_grade_id'],//付费ID
                'grade_type' => $order_info['grade_type'],//等级类型8团队分红
                'grade_type_id' => $setmeal_info['grade_type_id'],//等级类型ID
                'granularity' => $setmeal_info['granularity'], //粒度 年月季
                'effective_time' => $setmeal_info['effective_time'], //有效时间
                'price' => $order_info['price'], //售价
                'demotion_id' => $payGrade_info['demotion_id'], //到期降级id
                'create_time' => time(), //创建时间
                'end_time' => $end_time, //到期时间
            );
            if($check_info){
                $datas['start_time'] = $start_time;
                $datas['type_type'] = 1;
            }
            $payGradeRecordsModel->save($datas);
            //开始执行升级流程 0会员等级 1分销商等级 2股东等级 3区代等级 4队长等级 5渠道商等级 6店长等级 7入驻商家等级
            $member = new VslMemberModel();
            $member_info = $member->getInfo(['uid'=>$order_info['uid']],'member_level,isdistributor,is_global_agent,is_team_agent,is_area_agent');
            switch ($order_info['grade_type']) {
                case 0://会员等级
                    $memberService = new MemberService();
                    $memberService->adjustMemberLevel($setmeal_info['grade_type_id'],$order_info['uid']);
                break;
                case 1://分销商等级
                    debugLog($setmeal_info, $pay_type.'==>购买等级分销商等级 支付成功后处理<==');
                    $distributorService = new DistributorService();
                    if($member_info['isdistributor'] == 2){
                        $distributorService->pay_updateDistributorLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }else{
                        //先成为，再执行升级
                        $distributorService->pay_becomeLower($order_info['uid']);
                        $distributorService->pay_updateDistributorLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }
                break;
                case 2://股东等级
                    $globalService = new GlobalService();
                    if($member_info['is_global_agent'] == 2){
                        $globalService->pay_updateAgentLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }else{
                        //先成为，再执行升级
                        $globalService->pay_becomeLower($order_info['uid']);
                        $globalService->pay_updateAgentLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }
                break;
                case 3://区代等级 
                    $areaService = new AreaService();
                    if($member_info['is_area_agent'] == 2){
                        $areaService->pay_updateAgentLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }else{
                        //先成为，再执行升级
                        $areaService->pay_becomeLower($order_info['uid']);
                        $areaService->pay_updateAgentLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }
                break;
                case 4://队长等级 
                    $teamService = new TeamService();
                    if($member_info['is_team_agent'] == 2){
                        $teamService->pay_updateAgentLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }else{
                        //先成为，再执行升级
                        $teamService->pay_becomeLower($order_info['uid']);
                        $teamService->pay_updateAgentLevelInfo($order_info['uid'],$setmeal_info['grade_type_id']);
                    }
                break;
                case 5://渠道商等级
                    //查询当前会员是否是渠道商
                    $channelServer = new ChannelServer();
                    $condition_channel['c.website_id'] = $v['website_id'];
                    $condition_channel['c.uid'] = $v['uid'];
                    $channel_info = $channelServer->getMyChannelInfo($condition_channel); 
                    if($channel_info == 2){
                        $channelServer->pay_updateChannelLevel($order_info['uid'],$setmeal_info['grade_type_id']);
                    }else{
                        //先成为，再执行升级
                        $channelServer->setStatus($order_info['uid']);
                        $channelServer->pay_updateChannelLevel($order_info['uid'],$setmeal_info['grade_type_id']);
                    }
                break;
                case 8://团队分红升级
                    //标准版没有该应用，暂时去掉
                    
                break;
                default:
                break;
            }
            //更新订单支付状态
            $payGradeOrderModel->save(['pay_status'=>1],['out_trade_no'=>$out_trade_no]);
            $orderPaymentModel = new VslOrderPaymentModel();
            $orderPaymentModel->save(['pay_type' => $pay_type,'pay_status' => 1], ['out_trade_no' => $out_trade_no,'type' => 99]);
            $payGradeRecordsModel->commit();
        } catch (\Exception $e) {
            $payGradeRecordsModel->rollback();
            debugLog($e->getMessage(), $pay_type.'==>购买等级 支付成功后处理2<==');
            return $e->getMessage();
        }
        
    }
    /**
     * 付费升级的，到期后进行降级
     * 无差别降级 暂不考虑其他情况，到期后直接降级到购买前设定的降级等级
     */
    public function downLevels($website_id){
        $payGradeRecordsModel = new VslPayGradeRecordsModel();
        $condition['website_id'] = $website_id;
        $condition['status'] = 0;
        $condition['end_time'] = ['<',time()]; //测试暂时注释 测试完需要解除
        $list = $payGradeRecordsModel->getQuery($condition, '*', 'create_time asc');
        
        if($list){
           
            foreach ($list as $key => $value) {
                //开始处理到期降级，规则是当前等级到期后，如果当前等级或者更高等级并没有续费记录，则失效本记录，并执行降级，其他情况直接失效，不执行降级
                switch ($value['grade_type']) {
                    case 0://会员等级
                        $where['website_id'] = $website_id;
                        $where['status'] = 0;
                        $where['end_time'] = ['>',$value['end_time']];
                        $where['grade_type_id'] = $value['grade_type_id'];
                        $where['grade_type'] = $value['grade_type'];
                        $check_info = $payGradeRecordsModel->getInfo($where,'record_id');
                        if($check_info){
                            //直接失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                        }else{
                            //执行降级 并且失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                            $memberService = new MemberService();
                            $memberService->adjustMemberLevel($value['demotion_id'],$value['uid']);
                        }
                    break;
                    case 1://分销商等级
                        $where['website_id'] = $website_id;
                        $where['status'] = 0;
                        $where['end_time'] = ['>',$value['end_time']];
                        $where['grade_type_id'] = $value['grade_type_id'];
                        $where['grade_type'] = $value['grade_type'];
                        $check_info = $payGradeRecordsModel->getInfo($where,'record_id');
                        if($check_info){
                            //直接失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                        }else{
                            //执行降级 并且失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                            $distributorService = new DistributorService();
                            $distributorService->pay_updateDistributorLevelInfo($value['uid'],$value['demotion_id']);
                        }
                    break;
                    case 2://股东等级
                        $where['website_id'] = $website_id;
                        $where['status'] = 0;
                        $where['end_time'] = ['>',$value['end_time']];
                        $where['grade_type_id'] = $value['grade_type_id'];
                        $where['grade_type'] = $value['grade_type'];
                        $check_info = $payGradeRecordsModel->getInfo($where,'record_id');
                        if($check_info){
                            //直接失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                        }else{
                            //执行降级 并且失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                            $globalService = new GlobalService();
                            $globalService->pay_updateAgentLevelInfo($value['uid'],$value['demotion_id']);
                        }
                    break;
                    case 3://区代等级 
                        $where['website_id'] = $website_id;
                        $where['status'] = 0;
                        $where['end_time'] = ['>',$value['end_time']];
                        $where['grade_type_id'] = $value['grade_type_id'];
                        $where['grade_type'] = $value['grade_type'];
                        $check_info = $payGradeRecordsModel->getInfo($where,'record_id');
                        if($check_info){
                            //直接失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                        }else{
                            //执行降级 并且失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                            $areaService = new AreaService();
                            $areaService->pay_updateAgentLevelInfo($value['uid'],$value['demotion_id']);
                        }
                    break;
                    case 4://队长等级 
                        $where['website_id'] = $website_id;
                        $where['status'] = 0;
                        $where['end_time'] = ['>',$value['end_time']];
                        $where['grade_type_id'] = $value['grade_type_id'];
                        $where['grade_type'] = $value['grade_type'];
                        $check_info = $payGradeRecordsModel->getInfo($where,'record_id');
                        if($check_info){
                            //直接失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                        }else{
                            //执行降级 并且失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                            $teamService = new TeamService();
                            $teamService->pay_updateAgentLevelInfo($value['uid'],$value['demotion_id']);
                        }
                    break;
                    case 5://渠道商等级
                        $where['website_id'] = $website_id;
                        $where['status'] = 0;
                        $where['end_time'] = ['>',$value['end_time']];
                        $where['grade_type_id'] = $value['grade_type_id'];
                        $where['grade_type'] = $value['grade_type'];
                        $check_info = $payGradeRecordsModel->getInfo($where,'record_id');
                        if($check_info){
                            //直接失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                        }else{
                            //执行降级 并且失效
                            $payGradeRecordsModel->save(['status'=>1],['record_id'=>$value['record_id']]);
                            $channelServer = new ChannelServer();
                            $channelServer->pay_updateChannelLevel($value['uid'],$value['demotion_id']);
                        }
                    break;
                    case 8://团队分红升级
                        //标准版没有该应用，暂时去掉
                        
                    break;
                    default:
                    break;
                }
                
            }
        }
        return;
    }
    //获取旧的订单号 
    //要更新支付号，不然会支付失败--待处理
    public function checkOutTradeNo(){
        $uid = $this->uid;
        $website_id = $this->website_id;
        $sql = "select o.out_trade_no,o.create_time,o.record_id from vsl_pay_grade_order as o left join vsl_order_payment as p on p.type_alis_id = o.record_id where o.uid = $uid and o.website_id = $website_id and p.pay_status = 0 and p.type = 99";
        $result = Db::query($sql);
        if($result){
            if((time() - $result[0]['create_time']) <= 5){
                return -2;
            }
            //更新支付单号
            $pay = new UnifyPay();
            $out_trade_no = $pay->createOutTradeNo();
            $this->updatePayment($out_trade_no,$result[0]['create_time'],$result[0]['record_id']);
            return $out_trade_no;
        }else{
            return -1;
        }
    }
    /**
     * 更新支付单号
     */
    public function updatePayment($out_trade_no,$old_out_trade_no,$record_id){
        $orderPaymentModel = new VslOrderPaymentModel();
        $orderPaymentModel->save(['out_trade_no'=>$out_trade_no],['out_trade_no'=>$old_out_trade_no]);
        $payGradeOrderModel = new VslPayGradeOrderModel();
        $payGradeOrderModel->save(['out_trade_no'=>$out_trade_no],['record_id'=>$record_id,'out_trade_no'=>$old_out_trade_no]);
    }
}