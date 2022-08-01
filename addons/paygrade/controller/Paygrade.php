<?php
namespace addons\paygrade\controller;

use addons\paygrade\Paygrade as basePaygrade;
use addons\paygrade\server\PayGrade as PayGradeServer;
use data\model\VslMemberModel;
use addons\channel\model\VslChannelModel;
use data\service\UnifyPay;
use think\Db;
class Paygrade extends basePaygrade
{
    public function __construct()
    {
        parent::__construct();

    }
    /**
     * 等级列表
     */
    public function paygradeList() 
    {
        $PayGradeServer = new PayGradeServer();
        $condition = $list = [];
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['is_use'] = 1;
        $list['data'] = $PayGradeServer->getPayGradeList($condition,'sort desc');
        return $list;
    }
    /**
     * 编辑等级
     */
    public function updatePaygrade() 
    {
        $data = input('post.');
        $PayGradeServer = new PayGradeServer();
        $input = [];
        $input['is_putaway'] = $data['is_putaway'];
        $input['agreement_name'] = $data['agreement_name'];
        $input['agreement_content'] = htmlspecialchars(stripslashes($_POST['agreement_content']));
        if ($input['agreement_content']) {
            $input['agreement_content'] = htmlspecialchars_decode($input['agreement_content']);
        }
        $input['introduce_name'] = $data['introduce_name'];
        $input['introduce_content'] = htmlspecialchars(stripslashes($_POST['introduce_content']));
        if ($input['introduce_content']) {
            $input['introduce_content'] = htmlspecialchars_decode($input['introduce_content']);
        }
        $input['demotion_id'] = $data['demotion_id'];
        $res = $PayGradeServer->updatePayGrade($input,['pay_grade_id'=>$data['pay_grade_id']]);
        if($res){
            $input['list'] = $data['setmeal_list'];
            $PayGradeServer->updateSetmeal($input, $data['pay_grade_id']);
        }
        return AjaxReturn($res);
    }
    /**
     * 购买记录列表
     */
    public function recordList()
    {
        $page_index = input('post.page_index',1);
        $page_size = input('post.page_size',PAGESIZE);
        $user = input('post.user');
        $grade_type = input('post.grade_type');
        $grade_type_id = input('post.grade_type_id');
        $demotion_id = input('post.demotion_id');
        $start_time = input('post.start_time');
        $end_time = input('post.end_time');
        $start_time2 = input('post.start_time2');
        $end_time2 = input('post.end_time2');
        $condition = [];
        $condition['pgr.website_id'] = $this->website_id;
        if($user!=''){
            $condition['u.user_name|u.user_tel|u.nick_name'] = ['LIKE', '%' . $user . '%'];
        }
        if($grade_type!=''){
            $condition['pgr.grade_type'] = $grade_type;
        }
        if($grade_type_id!=''){
            $condition['pgr.grade_type_id'] = $grade_type_id;
        }
        if($demotion_id!=''){
            $condition['pgr.demotion_id'] = $demotion_id;
        }
        if($start_time && $end_time){
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time) + (86400 - 1);
            $condition['pgr.create_time'] = array(['egt',$start_time],['elt',$end_time],'and');
        }
        if($start_time2 && $end_time2){
            $start_time2 = strtotime($start_time2);
            $end_time2 = strtotime($end_time2) + (86400 - 1);
            $condition['pgr.end_time'] = array(['egt',$start_time2],['elt',$end_time2],'and');
        }
        $PayGradeServer = new PayGradeServer();
        $list = $PayGradeServer->getRecordList($page_index, $page_size, $condition, 'create_time desc');
        return $list;
    }
    /**
     * 获取对应的等级列表
     */
    public function getGrade()
    {
        $grade_type = input('post.grade_type');
        $PayGradeServer = new PayGradeServer();
        $list = $PayGradeServer->getGradeList($grade_type,0);
        return $list;
    }
    /**
     * 导出购买记录
     */
    public function recordDataExcel()
    {
        $page_index = input('post.page_index',1);
        $page_size = input('post.page_size',PAGESIZE);
        $user = input('post.user');
        $grade_type = input('post.grade_type');
        $grade_type_id = input('post.grade_type_id');
        $demotion_id = input('post.demotion_id');
        $start_time = input('post.start_time');
        $end_time = input('post.end_time');
        $start_time2 = input('post.start_time2');
        $end_time2 = input('post.end_time2');
        $condition = [];
        $condition['pgr.website_id'] = $this->website_id;
        if($user!=''){
            $condition['u.user_name|u.user_tel|u.nick_name'] = ['LIKE', '%' . $user . '%'];
        }
        if($grade_type!=''){
            $condition['pgr.grade_type'] = $grade_type;
        }
        if($grade_type_id!=''){
            $condition['pgr.grade_type_id'] = $grade_type_id;
        }
        if($demotion_id!=''){
            $condition['pgr.demotion_id'] = $demotion_id;
        }
        if($start_time && $end_time){
            $start_time = strtotime($start_time);
            $end_time = strtotime($end_time) + (86400 - 1);
            $condition['pgr.create_time'] = array(['egt',$start_time],['elt',$end_time],'and');
        }
        if($start_time2 && $end_time2){
            $start_time2 = strtotime($start_time2);
            $end_time2 = strtotime($end_time2) + (86400 - 1);
            $condition['pgr.end_time'] = array(['egt',$start_time2],['elt',$end_time2],'and');
        }
        $PayGradeServer = new PayGradeServer();
        $list = $PayGradeServer->getRecordList($page_index, $page_size, $condition, 'create_time desc');
        $xlsName = "付费等级购买记录";
        $xlsCell = [
            0=>['user_name','会员昵称'],
            1=>['user_tel','会员手机号码'],
            2=>['pay_grade_name','等级类型'],
            3=>['grade_type_name','购买等级'],
            4=>['price','售价'],
            5=>['effective_time','有效期'],
            6=>['demotion_name','到期降级'],
            7=>['create_time','购买时间'],
            8=>['end_time','到期时间']
        ];
        $data = [];
        if($list["data"]){
            foreach ($list["data"] as $k => $v) {
                $data[$k]['user_name'] = $v['user_name'];
                $data[$k]['user_tel'] = $v['user_tel'];
                $data[$k]['pay_grade_name'] = $v['pay_grade_name'];
                $data[$k]['grade_type_name'] = $v['grade_type_name'];
                $data[$k]['price'] = $v['price'];
                $data[$k]['effective_time'] = $v['effective_time'].($v['granularity']==1?'年':($v['granularity']==2?'季':($v['granularity']==3?'月':'')));
                $data[$k]['demotion_name'] = $v['demotion_name'];
                $data[$k]['create_time'] = getTimeStampTurnTime($v['create_time']);
                $data[$k]['end_time'] = getTimeStampTurnTime($v['end_time']);
            }
        }
        try {
            dataExcel($xlsName, $xlsCell, $data);
        } catch (\Exception $e){
            var_dump($e->getMessage());
        }
    }
    /**
     * 设置
     */
    public function saveSetting()
    {
        $PayGradeServer = new PayGradeServer();
        $is_paygrade = (int)input('post.is_paygrade'); 
        $result = $PayGradeServer->saveConfig($is_paygrade);
        if($result){
            $config_id = (int)input('post.config_id');
            $input = [];
            $input['introduce_name'] = input('post.introduce_name');
            $input['introduce_content'] = input('post.introduce_content');
            $input['agreement_name'] = input('post.agreement_name');
            $input['agreement_content'] = htmlspecialchars(stripslashes(input('post.agreement_content')));
            
            if($config_id>0){
                $PayGradeServer->updateConfig($input,['config_id'=>$config_id]);
            }else{
                $input['shop_id'] = $this->instance_id;
                $input['website_id'] = $this->website_id;
                $PayGradeServer->addConfig($input);
            }
            $data = input('post.');
            $pay_grade_list = $data['pay_grade_list'];
            if($pay_grade_list){
                foreach ($pay_grade_list as $k => $v) {
                    $input = [];
                    $input['is_use'] = $v['is_use'];
                    $input['sort'] = $k;
                    $PayGradeServer->updatePayGrade($input,['pay_grade_id'=>$v['pay_grade_id']]);
                }
            }
            $this->addUserLog('修改支付有礼设置', $result);
        }
        setAddons('paygrade', $this->website_id);
        return AjaxReturn($result);
    }
    
    /**
     * wab获取付费等级介绍和协议
     */
    public function getPayGrade()
    {
        if (empty($this->uid)) {
            return json(['code' => LOGIN_EXPIRE, 'message' => '登录信息已过期，请重新登录']);
        }
        $PayGradeServer = new PayGradeServer();
        $config_info = $PayGradeServer->getConfigDetail(['shop_id'=>$this->instance_id,'website_id'=>$this->website_id]);
        if($config_info){
            $config_info['introduce_content'] = str_replace('"//', '"https://', $config_info['introduce_content']);
            $config_info['agreement_content'] = str_replace('"//', '"https://', $config_info['agreement_content']);
            unset($config_info['config_id']);
        }
        return json(['code' => 1,'message' => '获取成功','data' => $config_info]);
    }
    /**
     * wab获取等级类型列表
     */
    public function getPayGradeList()
    {
        $PayGradeServer = new PayGradeServer();
        $is_distribution = getAddons('distribution', $this->website_id);
        $is_globalbonus = getAddons('globalbonus', $this->website_id);
        $is_areabonus = getAddons('areabonus', $this->website_id);
        $is_teambonus = getAddons('teambonus', $this->website_id);
        $is_channel = getAddons('channel', $this->website_id);
        //获取会员信息
        $grade_list = $PayGradeServer->getPayGradeList(['shop_id'=>$this->instance_id,'website_id'=>$this->website_id,'is_use'=>1,'is_putaway'=>1],'sort desc');
        $memberModel = new VslMemberModel();
        $member_info = $memberModel->getInfo(['uid'=>$this->uid],'isdistributor,is_global_agent,is_area_agent,is_team_agent,isshopkeeper');
        if($is_distribution){
            $is_distribution = $member_info['isdistributor'] == 2 ? $is_distribution : false;
        }
        if($is_globalbonus){
            $is_globalbonus = $member_info['is_global_agent'] == 2 ? $is_globalbonus : false;
        }
        if($is_areabonus){
            $is_areabonus = $member_info['is_area_agent'] == 2 ? $is_areabonus : false;
        }
        if($is_teambonus){
            $is_teambonus = $member_info['is_team_agent'] == 2 ? $is_teambonus : false;
        }
        if($is_channel){
            //获取渠道商等级
            $channel = new VslChannelModel();
            $channel_info = $channel->getInfo(['uid' => $uid,'website_id' => $this->website_id], 'channel_grade_id,status');
            if($channel_info){
                $is_channel = $channel_info['status'] == 1 ? $is_channel : false;
            }
        }
        if($grade_list){
            foreach ($grade_list as $k => $v) {
                if($v['grade_type']==1 && !$is_distribution)unset($grade_list[$k]);
                if($v['grade_type']==2 && !$is_globalbonus)unset($grade_list[$k]);
                if($v['grade_type']==3 && !$is_areabonus)unset($grade_list[$k]);
                if($v['grade_type']==4 && !$is_teambonus)unset($grade_list[$k]);
                if($v['grade_type']==5 && !$is_channel)unset($grade_list[$k]);
                unset($grade_list[$k]['setmeal_list'],$grade_list[$k]['is_use'],$grade_list[$k]['is_putaway'],$grade_list[$k]['demotion_id'],$grade_list[$k]['shop_id']);
                unset($grade_list[$k]['sort'],$grade_list[$k]['create_time'],$grade_list[$k]['update_time'],$grade_list[$k]['website_id']);
            }
        }
        if($grade_list){
            $grade_list = array_values($grade_list);
        }
        $rs = [
            'code' => 1,
            'message' => '获取成功',
            'data' => $grade_list
        ];
        return json($rs);
    }
    /**
     * wab获取对应等级类型信息
     */
    public function getPayGradeInfo()
    {
        $pay_grade_id = (int)input('post.pay_grade_id');
        $uid = $this->uid;
        $PayGradeServer = new PayGradeServer();
        
        $info = $PayGradeServer->getPayGradeDetail(['pay_grade_id'=>$pay_grade_id]);
        if(!isset($info['grade_type'])){
            return json(['code' => -1,'message' => '获取失败']);
        }
        $data = [];
        $data['agreement_content'] = $info['agreement_content'];
        $data['agreement_name'] = $info['agreement_name'];
        $data['introduce_content'] = $info['introduce_content'];
        $data['introduce_name'] = $info['introduce_name'];
        $data['equity_list'] = $PayGradeServer->getGradeList($info['grade_type']);
        $data['level_info'] = [];
        $data['level_info'] = $data['paygrade_info'] = ['level_id'=>0,'level_name'=>"",'weight'=>0,'grade_type'=>0];
        $data['paygrade_info']['end_time'] = "";
        $data['setmeal_list'] = [];
        $setmeal_list = $PayGradeServer->getSetmealList(['pay_grade_id'=>$pay_grade_id],'sort desc');
        
        if($setmeal_list){
            foreach ($setmeal_list as $k => $v) {
                $list = [];
                $list['set_meal_id'] = $v['set_meal_id'];
                $grade_type_info = $PayGradeServer->getGradeInfo($info['grade_type'],$v['grade_type_id']);
                $list['grade_type_name'] = $grade_type_info['level_name'];
                $list['grade_type_weight'] = $grade_type_info['weight'];
                $list['granularity'] = $v['granularity'];
                $list['effective_time'] = $v['effective_time'];
                $list['price'] = $v['price'];
                $list['pay_grade_id'] = $pay_grade_id;
                $list['demotion_name'] = $PayGradeServer->getGradeInfo($info['grade_type'],$info['demotion_id'])['level_name'];
                $data['setmeal_list'][] = $list;
            }
        }
        
        if(!empty($uid)){
            $member = new VslMemberModel();
            $member_info = $member->getInfo(['uid' => $uid,'website_id' => $this->website_id], '*');
            if($member_info){
                //1全球2区域3团队 修正错误 0会员等级 1分销商等级 2股东等级 3区代等级 4队长等级 5渠道商等级 6店长等级 7入驻商家等级
                if($info['grade_type'] == 0){
                    $data['level_info'] = $PayGradeServer->getGradeInfo($info['grade_type'],$member_info['member_level']);
                }else if($info['grade_type'] == 1 && $member_info['distributor_level_id']>0){
                    $data['level_info'] = $PayGradeServer->getGradeInfo($info['grade_type'],$member_info['distributor_level_id']);
                }else if($info['grade_type'] == 3 && $member_info['area_agent_level_id']>0){
                    $data['level_info'] = $PayGradeServer->getGradeInfo($info['grade_type'],$member_info['area_agent_level_id']);
                }else if($info['grade_type'] == 4 && $member_info['team_agent_level_id']>0){
                    $data['level_info'] = $PayGradeServer->getGradeInfo($info['grade_type'],$member_info['team_agent_level_id']);
                }else if($info['grade_type'] == 2 && $member_info['global_agent_level_id']>0){
                    $data['level_info'] = $PayGradeServer->getGradeInfo($info['grade_type'],$member_info['global_agent_level_id']);
                }else if($info['grade_type'] == 5 && getAddons('channel', $this->website_id)){
                    $channel = new VslChannelModel();
                    $channel_info = $channel->getInfo(['uid' => $uid,'website_id' => $this->website_id], 'channel_grade_id');
                    if($channel_info){
                        $data['level_info'] = $PayGradeServer->getGradeInfo($info['grade_type'],$channel_info['channel_grade_id']);
                    }
                }
                $data['level_info']['grade_type'] = $info['grade_type'];
            }
            $paygrade_info = $PayGradeServer->getRecordDetail(['status'=>0,'pay_grade_id'=>$pay_grade_id,'uid' => $uid,'website_id' => $this->website_id,'end_time'=>['egt', time()]], '*');
            if($data['setmeal_list']){
                
                foreach ($data['setmeal_list'] as $k => $v) {
                    if(empty($paygrade_info)){
                        if($v['grade_type_weight'] && $v['grade_type_weight']<$data['level_info']['weight']){
                            unset($data['setmeal_list'][$k]);
                        }else if($v['growth_num'] && $v['growth_num']<$data['level_info']['weight']){
                            unset($data['setmeal_list'][$k]);
                        }
                    }else{
                        $data['paygrade_info']['level_id'] = $paygrade_info['grade_type_id'];
                        $data['paygrade_info']['level_name'] = $paygrade_info['grade_type_name'];
                        $data['paygrade_info']['weight'] = $paygrade_info['weight'];
                        $data['paygrade_info']['grade_type'] = $paygrade_info['grade_type'];
                        $data['paygrade_info']['end_time'] = $paygrade_info['end_time'];
                        if($v['grade_type_weight'] < $paygrade_info['weight']){
                            unset($data['setmeal_list'][$k]);
                        }
                    }
                }
                if($data['setmeal_list']){
                    $data['setmeal_list'] = array_values($data['setmeal_list']);
                }
            }
        }
        return json(['code' => 1,'message' => '获取成功','data' => $data]);
    }
    /**
     * 创建购买等级支付订单
     * set_meal_id 购买套餐id
     * pay_grade_id 等级类型ID
     */
    public function createPurchaseOrder(){
        $set_meal_id = request()->post('set_meal_id', '3'); //购买等级列表Id 
        $pay_grade_id = request()->post('pay_grade_id', '3'); //购买等级类型Id
        $payGradeServer = new PayGradeServer();
        $checkOutTradeNo = $payGradeServer->checkOutTradeNo();
        //查询是否有未支付订单 有则继续使用该订单 后续加并发锁，防止短时间内多次提交 
        $t_type = 1;//正常提交
        if($checkOutTradeNo == -1){
            $pay = new UnifyPay();
            $out_trade_no = $pay->createOutTradeNo();
        }else if($checkOutTradeNo == -2){
            $data['message'] = "请勿短时间内重复提交,请稍后再提交！";
            $data['code'] = -1;
            return json($data);
        }else{
            $out_trade_no = $checkOutTradeNo;
            $t_type = 2;//已有支付单号，更新信息
        }
        if(empty($set_meal_id) || empty($pay_grade_id)){
            $data['message'] = "请选择购买套餐或等级类型！";
            $data['code'] = -1;
            return json($data);
        }
        //检测是否开启相应设置权限
        $addonsConfigSer = new \data\service\AddonsConfig();
        $addonsConfig = $addonsConfigSer->getAddonsConfig('paygrade', $this->website_id);
        if($addonsConfig['is_use'] != 1){
            $data['message'] = "未开启等级购买权限,请按正常流程升级！";
            $data['code'] = -1;
            return json($data);
        }
        
        $condition['pay_grade_id'] = (int)$pay_grade_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        
        $info = $payGradeServer->getPayGradeDetail($condition);
        if(!$info){
            $data['message'] = "等级类型不存在,请重新选择！";
            $data['code'] = -1;
            return json($data);
        }
        if($info['is_use'] != 1){
            $data['message'] = "该等级类型已关闭购买权限,请重新选择！";
            $data['code'] = -1;
            return json($data);
        }
        
        //查询购买等级是否低于用户当前等级 
        $check_status = $payGradeServer->checkStatus($this->uid,$set_meal_id);
        if($check_status == -1){
            $data['message'] = "该用户不能购买当前套餐，请重新选择！";
            $data['code'] = -1;
            return json($data);
        }
        //等级类型换取购买套餐信息
        $retval = $payGradeServer->createPurchaseOrder($set_meal_id,$pay_grade_id,$this->uid,$this->website_id,$out_trade_no,$t_type);
        
        if ($retval > 0) {
            //查询支付金额是否为0 是就订单状态变更为已完成
            $pay = new UnifyPay();
            $pay_value = $pay->getPayInfo($out_trade_no);
            if($pay_value['pay_money'] == 0){
                $payGradeServer->orderOnLinePay($out_trade_no, 5);
            }
            $data['code'] = 0;
            $data['message'] = "订单创建成功";
            $data['data']['out_trade_no'] = $out_trade_no;
            return json($data);
        }else {
            $data['code'] = '-1';
            $data['data'] = "";
            $data['message'] = "提交失败";
            return json($data);
        }
    }
}