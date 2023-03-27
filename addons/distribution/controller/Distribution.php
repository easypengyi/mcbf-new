<?php

namespace addons\distribution\controller;

use addons\distribution\Distribution as baseDistribution;
use addons\distribution\model\SysMessageItemModel;
use addons\distribution\model\SysMessagePushModel;
use addons\distribution\model\VslDistributorCommissionWithdrawModel;
use addons\distribution\service\Distributor as DistributorService;
use data\model\VslMemberModel;
use data\service\ExcelsExport;
use data\service\Member;
use think\helper\Time;
use data\model\UserModel;
use data\service\Address;
use addons\distribution\model\VslDistributorLevelModel;
use addons\distribution\model\VslOrderDistributorCommissionModel;
use data\model\VslAccountRecordsModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslExcelsModel;
use addons\teambonus\service\TeamBonus;


/**
 * 分销商设置控制器
 *
 * @author  www.vslai.com
 *
 */
class Distribution extends baseDistribution
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 获取省列表
     */
    public function getProvince()
    {
        $address = new Address();
        $province_list = $address->getProvinceList();
        return $province_list;
    }

    /**
     * 获取城市列表
     */
    public function getCity()
    {
        $address = new Address();
        $province_id = request()->post('province_id', 0);
        $city_list = $address->getCityList($province_id);
        return $city_list;
    }

    /**
     * 获取区地址
     */
    public function getDistrict()
    {
        $address = new Address();
        $city_id = request()->post('city_id', 0);
        $district_list = $address->getDistrictList($city_id);
        return $district_list;
    }

    /**
     * 分销商列表
     */
    public function distributorList()
    {
        $index = request()->post('page_index', 1);
        $iphone = request()->post('iphone', "");
        $search_text = request()->post('search_text', '');
        $referee_name = request()->post('referee_name', '');
        $distributor_level_id = request()->post('level_id', '');
        $isdistributor = request()->post('isdistributor', '5');
        if ($search_text) {
            $condition['us.user_name|us.nick_name'] = array('like', '%' . $search_text . '%');
        }
        if ($referee_name) {
            //推荐人姓名换取推荐人uid
            $member = new UserModel();
            $referee_info = $member->getInfo(['user_name|nick_name' => ['like', '%' . $referee_name . '%'], 'website_id' => $this->website_id], 'uid');
            if ($referee_info) {
                $condition['nm.referee_id'] = $referee_info['uid'];
            }
        }
        if ($iphone) {
            $condition['nm.mobile'] = $iphone;
        }
        if ($isdistributor != 5) {
            $condition['nm.isdistributor'] = $isdistributor;
        } else {
            $condition['nm.isdistributor'] = ['in', '1,2,-1,-3'];
        }
        if ($distributor_level_id) {
            $condition['nm.distributor_level_id'] = $distributor_level_id;
        }
        $condition['nm.website_id'] = $this->website_id;
        $distributor = new DistributorService();
        $uid = 0;
        $list = $distributor->getDistributorList($uid, $index, PAGESIZE, $condition, 'become_distributor_time desc');
        return $list;
    }

    /**
     * 修改上级分销商
     */
    public function updateRefereeDistributor()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $distributor = new DistributorService();
        $uid = isset($_POST['uid']) ? $_POST['uid'] : '';

        $referee_id = isset($_POST['referee_id']) ? $_POST['referee_id'] : '';
        $retval = $distributor->updateRefereeDistributor($uid, $referee_id);
        if ($retval) {
            $this->addUserLog('修改上级分销商', $referee_id);
        }
        return AjaxReturn($retval);
    }

    /**
     * 修改直属下级分销商
     */
    public function updateLowerRefereeDistributor()
    {
        $distributor = new DistributorService();
        $uid = isset($_POST['uid']) ? $_POST['uid'] : '';
        $referee_id = isset($_POST['referee_id']) ? $_POST['referee_id'] : '';
        $retval = $distributor->updateLowerRefereeDistributor($uid, $referee_id);
        if ($retval) {
            $this->addUserLog('修改直属下级分销商', $referee_id);
        }
        return AjaxReturn($retval);
    }

    /**
     * 修改分销商上级列表
     */
    public function refereeDistributorLists()
    {
        $distributor = new DistributorService();
        $condition['nm.isdistributor'] = 2;
        $uid = request()->post('uid', "");
        $search_text = request()->post('search_text', '');
        $page_index = request()->post('page_index', 1);
        $lower_id = request()->post('lower_id', '');
        if ($lower_id) {
            $condition['us.uid'] = ['not in', $lower_id];
        }
        if ($search_text) {
            $condition['us.user_name|us.nick_name|us.user_tel'] = array('like', '%' . $search_text . '%');
        }
        $condition['us.website_id'] = $this->website_id;
        $list = $distributor->getDistributorList($uid, $page_index, PAGESIZE, $condition, 'become_distributor_time desc');
        return $list;
    }

    /**
     * 修改分销商上级列表
     */
    public function refereeDistributorList()
    {
        $distributor = new DistributorService();
        $condition['nm.isdistributor'] = 2;
        $uid = request()->post('uid', "");
        $search_text = request()->post('search_text', '');
        $page_index = request()->post('page_index', 1);
        $lower_id = request()->post('lower_id', '');
        if ($lower_id) {
            $condition['us.uid'] = ['not in', $lower_id];
        }
        if ($search_text) {
            $condition['us.user_name|us.nick_name|us.user_tel'] = array('like', '%' . $search_text . '%');
        }
        $condition['us.website_id'] = $this->website_id;
        $list = $distributor->getDistributorList($uid, $page_index, PAGESIZE, $condition, 'become_distributor_time desc');
        return $list;
    }


    /**
     * 下级分销商列表
     */
    public function lowerDistributorList()
    {
        $uid = request()->post('uid', "");
        $commission_levels = request()->post('commission_levels', 4); //分销测级 全部 1-3
        $index = request()->post('page_index', 1);
        $iphone = request()->post('iphone', "");
        $search_text = request()->post('search_text', '');
        $distributor_level_id = request()->post('level_id', '');
        $isdistributor = request()->post('isdistributor', '');
        //获取客户列表
        $types = request()->post('types', 1);
        $iphone2 = request()->post('iphone2', "");
        $search_text2 = request()->post('search_text2', '');
        if ($types == 2) { //客户列表
            if ($search_text2) {
                $condition['us.user_name|us.nick_name'] = array('like', '%' . $search_text2 . '%');
            }
            if ($iphone2) {
                $condition['nm.mobile'] = $iphone2;
            }
            if ($uid) {
                $condition['nm.uid'] = ['neq', $uid];
            }
            $condition['nm.isdistributor'] = ['neq', 2];
            $condition['nm.website_id'] = $this->website_id;
            $distributor = new DistributorService();
            $list = $distributor->getDistributorList2($uid, $index, PAGESIZE, $condition, 'nm.reg_time desc', $commission_levels);
        } else {
            if ($search_text) {
                $condition['us.user_name|us.nick_name'] = array('like', '%' . $search_text . '%');
            }
            if ($iphone) {
                $condition['nm.mobile'] = $iphone;
            }
            if ($isdistributor) {
                $condition['nm.isdistributor'] = $isdistributor;
            } else {
                $condition['nm.isdistributor'] = 2;
            }
            if ($distributor_level_id) {
                $condition['nm.distributor_level_id'] = $distributor_level_id;
            }
            if ($uid) {
                $condition['nm.uid'] = ['neq', $uid];
            }
            $condition['nm.website_id'] = $this->website_id;
            $distributor = new DistributorService();
            $list = $distributor->getDistributorList($uid, $index, PAGESIZE, $condition, 'become_distributor_time desc', $commission_levels);
        }

        return $list;
    }

    /**
     * 修改分销商状态
     */
    public function setStatus()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $uid = request()->post('uid', '');
        $status = request()->post('status', '');
        $distributor = new DistributorService();
        $retval = $distributor->setStatus($uid, $status);
        if ($retval) {
            $this->addUserLog('修改分销商状态', $uid);
        }
        return AjaxReturn($retval);
    }

    /**
     * 检查当前分销商是否有下级
     */
    public function checkDistributor()
    {
        $uid = request()->post('uid', '');
        $distributor = new DistributorService();
        $retval = $distributor->checkDistributor($uid);
        return AjaxReturn($retval);
    }

    /**
     * 移除分销商
     */
    public function delDistributor()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $member = new DistributorService();
        $uid = request()->post("uid", '');
        $res = $member->deleteDistributor($uid);
        if ($res) {
            $this->addUserLog('移除分销商', $uid);
        }
        return AjaxReturn($res);
    }

    /**
     * 分销商详情
     */
    public function updateDistributorInfo()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $member = new DistributorService();
        $uid = request()->post("uid", '');
        $distributor_level_id = request()->post("level", '');
        $team_agent = request()->post("team_agent", '');
        $status = request()->post("status", '');
        $is_pu = request()->post("is_pu", false);
        $is_auth = request()->post("is_auth", false);
        $real_name = request()->post("real_name", '');
        $area_agent = request()->post("area_agent", '');
        $global_agent = request()->post("global_agent", '');
        $province_id = request()->post("province_id", '');
        $city_id = request()->post("city_id", '');
        $district_id = request()->post("district_id", '');
        $area_id = request()->post("area_id", '');
        $data = [];
        if ($area_id == 1) {
            $agent_area_id = $province_id . ',p';
        }
        if ($area_id == 2) {
            $agent_area_id = $province_id . ',' . $city_id . ',c';
        }
        if ($area_id == 3) {
            $agent_area_id = $province_id . ',' . $city_id . ',' . $district_id . ',d';
        }
        if ($team_agent == -1) {
            $data['is_team_agent'] = 0;
        }
        if ($global_agent == -1) {
            $data['is_global_agent'] = 0;
        }
        if ($area_agent == -1) {
            $data['is_area_agent'] = 0;
        }
        if ($global_agent > 0) {
            $data['is_global_agent'] = 2;
            $data['global_agent_level_id'] = $global_agent;
            $data['become_global_agent_time'] = time();
            $data['apply_global_agent_time'] = time();
        }
        if ($area_agent > 0) {
            $data['is_area_agent'] = 2;
            $data['area_agent_level_id'] = $area_agent;
            $data['become_area_agent_time'] = time();
            $data['apply_area_agent_time'] = time();
        }
        if ($team_agent > 0) {
            $data['is_team_agent'] = 2;
            $data['team_agent_level_id'] = $team_agent;
            $data['become_team_agent_time'] = time();
            $data['apply_team_agent_time'] = time();
        }
        if ($distributor_level_id) {
            $data['distributor_level_id'] = $distributor_level_id;
            if($distributor_level_id == 10){
                $user_model = new UserModel();
                $user_model->save(['port'=> 'platform'], ['uid'=> $uid]);
            }
        }
        if ($status) {
            $data['isdistributor'] = $status;
        }
        if ($area_id) {
            $data['area_type'] = $area_id;
        }
        if ($agent_area_id) {
            $data['agent_area_id'] = $agent_area_id;
        }
        if ($real_name) {
            $data['real_name'] = $real_name;
        }
        if ($is_pu !== false) {
            $data['is_pu'] = $is_pu;
        }
        if ($is_auth !== false) {
            $data['is_auth'] = $is_auth;
        }

        $res = $member->updateDistributorInfo($data, $uid);
        if ($res) {
            $this->addUserLog('修改分销商详情', $res);
        }
        return AjaxReturn($res);
    }

    /**
     * 分销商等级列表
     */
    public function distributorLevelList()
    {
        $index = isset($_POST["page_index"]) ? $_POST["page_index"] : 1;
        $search_text = isset($_POST['search_text']) ? $_POST['search_text'] : '';
        $distributor = new DistributorService();
        $list = $distributor->getDistributorLevelList($index, PAGESIZE, ['level_name' => array('like', '%' . $search_text . '%'), 'website_id' => $this->website_id], 'weight asc');
        return json($list);
    }

    /**
     * 添加分销商等级
     */
    public function addDistributorLevel()
    {
        $data['level_name'] = isset($_POST['level_name']) ? $_POST['level_name'] : ''; //等级名称
        $data['recommend_type'] = isset($_POST['recommend_type']) ? $_POST['recommend_type'] : '1'; //返佣类型
        $data['commission1'] = isset($_POST['commission1']) ? $_POST['commission1'] : '0'; //一级返佣比例
        $data['commission2'] = isset($_POST['commission2']) ? $_POST['commission2'] : '0'; //二级返佣比例
        $data['commission3'] = isset($_POST['commission3']) ? $_POST['commission3'] : '0'; //三级返佣比例
        $data['commission_point1'] = isset($_POST['commission_point1']) ? $_POST['commission_point1'] : '0'; //一级返佣积分比例
        $data['commission_point2'] = isset($_POST['commission_point2']) ? $_POST['commission_point2'] : '0'; //二级返佣积分比例
        $data['commission_point3'] = isset($_POST['commission_point3']) ? $_POST['commission_point3'] : '0'; //三级返佣积分比例
        $data['commission11'] = isset($_POST['commission11']) ? $_POST['commission11'] : '0'; //一级返佣
        $data['commission22'] = isset($_POST['commission22']) ? $_POST['commission22'] : '0'; //二级返佣
        $data['commission33'] = isset($_POST['commission33']) ? $_POST['commission33'] : '0'; //三级返佣
        $data['commission_point11'] = isset($_POST['commission_point11']) ? $_POST['commission_point11'] : '0'; //一级返佣积分
        $data['commission_point22'] = isset($_POST['commission_point22']) ? $_POST['commission_point22'] : '0'; //二级返佣积分
        $data['commission_point33'] = isset($_POST['commission_point33']) ? $_POST['commission_point33'] : '0'; //三级返佣积分
        $data['recommend1'] = isset($_POST['recommend1']) ? $_POST['recommend1'] : '0'; //一级奖励金
        $data['recommend2'] = isset($_POST['recommend2']) ? $_POST['recommend2'] : '0'; //二级奖励金
        $data['recommend3'] = isset($_POST['recommend3']) ? $_POST['recommend3'] : '0'; //三级奖励金
        $data['recommend_point1'] = isset($_POST['recommend_point1']) ? $_POST['recommend_point1'] : '0'; //一级奖励积分
        $data['recommend_point2'] = isset($_POST['recommend_point2']) ? $_POST['recommend_point2'] : '0'; //二级奖励积分
        $data['recommend_point3'] = isset($_POST['recommend_point3']) ? $_POST['recommend_point3'] : '0'; //三级奖励积分
        $data['upgradetype'] = isset($_POST['upgradetype']) ? $_POST['upgradetype'] : 2; //自动升级
        $data['offline_number'] = isset($_POST['offline_number']) ? $_POST['offline_number'] : '0'; //满足下线总人数
        $data['order_money'] = isset($_POST['order_money']) ? $_POST['order_money'] : '0'; //满足分销订单金额
        $data['order_number'] = isset($_POST['order_number']) ? $_POST['order_number'] : '0'; //满足分销订单数
        $data['selforder_money'] = isset($_POST['selforder_money']) ? $_POST['selforder_money'] : '0'; //自购订单金额
        $data['selforder_number'] = isset($_POST['selforder_number']) ? $_POST['selforder_number'] : '0'; //自购订单数
        $data['downgradetype'] = isset($_POST['downgradetype']) ? $_POST['downgradetype'] : 2; //自动降级
        $data['team_number'] = isset($_POST['team_number']) ? $_POST['team_number'] : '0'; //团队人数
        $data['team_money'] = isset($_POST['team_money']) ? $_POST['team_money'] : '0'; //团队订单金额
        $data['self_money'] = isset($_POST['self_money']) ? $_POST['self_money'] : '0'; //自购订单金额
        $data['team_number_day'] = isset($_POST['team_number_day']) ? $_POST['team_number_day'] : '0'; //时间段：团队人数
        $data['team_money_day'] = isset($_POST['team_money_day']) ? $_POST['team_money_day'] : '0'; //时间段：团队订单金额
        $data['self_money_day'] = isset($_POST['self_money_day']) ? $_POST['self_money_day'] : '0'; //时间段：自购订单金额
        $data['weight'] = isset($_POST['weight']) ? $_POST['weight'] : ''; //权重
        $data['downgrade_condition'] = isset($_POST['downgrade_condition']) ? $_POST['downgrade_condition'] : ''; //升级条件
        $data['upgrade_condition'] = isset($_POST['upgrade_condition']) ? $_POST['upgrade_condition'] : ''; //降级条件
        $data['downgradeconditions'] = isset($_POST['downgradeconditions']) ? $_POST['downgradeconditions'] : ''; //升级条件
        $data['upgradeconditions'] = isset($_POST['upgradeconditions']) ? $_POST['upgradeconditions'] : ''; //降级条件
        $data['goods_id'] = isset($_POST['goods_id']) ? $_POST['goods_id'] : ''; //指定商品id
        $data['upgrade_level'] = isset($_POST['upgrade_level']) ? $_POST['upgrade_level'] : '0'; //推荐等级
        $data['level_number'] = isset($_POST['level_number']) ? $_POST['level_number'] : '0'; //推荐等级人数
        $data['number1'] = isset($_POST['number1']) ? $_POST['number1'] : '0'; //一级分销商
        $data['number2'] = isset($_POST['number2']) ? $_POST['number2'] : '0'; //二级分销商
        $data['number3'] = isset($_POST['number3']) ? $_POST['number3'] : '0'; //三级分销商
        $data['number4'] = isset($_POST['number4']) ? $_POST['number4'] : '0'; //团队人数
        $data['number5'] = isset($_POST['number5']) ? $_POST['number5'] : '0'; //下线客户
        //复购设置
        $data['buyagain'] = isset($_POST['buyagain']) ? $_POST['buyagain'] : 0; //是否开启复购
        $data['buyagain_recommendtype'] = isset($_POST['buyagain_recommendtype']) ? $_POST['buyagain_recommendtype'] : 1; //复购类型
        $data['buyagain_commission1'] = isset($_POST['buyagain_commission1']) ? $_POST['buyagain_commission1'] : '0'; //一级返佣比例
        $data['buyagain_commission2'] = isset($_POST['buyagain_commission2']) ? $_POST['buyagain_commission2'] : '0'; //二级返佣比例
        $data['buyagain_commission3'] = isset($_POST['buyagain_commission3']) ? $_POST['buyagain_commission3'] : '0'; //三级返佣比例
        $data['buyagain_commission_point1'] = isset($_POST['buyagain_commission_point1']) ? $_POST['buyagain_commission_point1'] : '0'; //一级返佣积分比例
        $data['buyagain_commission_point2'] = isset($_POST['buyagain_commission_point2']) ? $_POST['buyagain_commission_point2'] : '0'; //二级返佣积分比例
        $data['buyagain_commission_point3'] = isset($_POST['buyagain_commission_point3']) ? $_POST['buyagain_commission_point3'] : '0'; //三级返佣积分比例
        $data['buyagain_commission11'] = isset($_POST['buyagain_commission11']) ? $_POST['buyagain_commission11'] : '0'; //一级返佣
        $data['buyagain_commission22'] = isset($_POST['buyagain_commission22']) ? $_POST['buyagain_commission22'] : '0'; //二级返佣
        $data['buyagain_commission33'] = isset($_POST['buyagain_commission33']) ? $_POST['buyagain_commission33'] : '0'; //三级返佣
        $data['buyagain_commission_point11'] = isset($_POST['buyagain_commission_point11']) ? $_POST['buyagain_commission_point11'] : '0'; //一级返佣积分
        $data['buyagain_commission_point22'] = isset($_POST['buyagain_commission_point22']) ? $_POST['buyagain_commission_point22'] : '0'; //二级返佣积分
        $data['buyagain_commission_point33'] = isset($_POST['buyagain_commission_point33']) ? $_POST['buyagain_commission_point33'] : '0'; //三级返佣积分

        $data['commission_beautiful_point1'] = isset($_POST['commission_beautiful_point1']) ? $_POST['commission_beautiful_point1'] : '0'; //美丽积分
        $data['commission_beautiful_point2'] = isset($_POST['commission_beautiful_point2']) ? $_POST['commission_beautiful_point2'] : '0'; //美丽积分
        $data['commission_beautiful_point3'] = isset($_POST['commission_beautiful_point3']) ? $_POST['commission_beautiful_point3'] : '0'; //美丽积分
        $data['commission_beautiful_point11'] = isset($_POST['commission_beautiful_point11']) ? $_POST['commission_beautiful_point11'] : '0'; //美丽积分
        $data['commission_beautiful_point22'] = isset($_POST['commission_beautiful_point22']) ? $_POST['commission_beautiful_point22'] : '0'; //美丽积分
        $data['commission_beautiful_point33'] = isset($_POST['commission_beautiful_point33']) ? $_POST['commission_beautiful_point33'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point1'] = isset($_POST['buyagain_commission_beautiful_point1']) ? $_POST['buyagain_commission_beautiful_point1'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point2'] = isset($_POST['buyagain_commission_beautiful_point2']) ? $_POST['buyagain_commission_beautiful_point2'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point3'] = isset($_POST['buyagain_commission_beautiful_point3']) ? $_POST['buyagain_commission_beautiful_point3'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point11'] = isset($_POST['buyagain_commission_beautiful_point11']) ? $_POST['buyagain_commission_beautiful_point11'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point22'] = isset($_POST['buyagain_commission_beautiful_point22']) ? $_POST['buyagain_commission_beautiful_point22'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point33'] = isset($_POST['buyagain_commission_beautiful_point33']) ? $_POST['buyagain_commission_beautiful_point33'] : '0'; //美丽积分
        $data['recommend_beautiful_point1'] = isset($_POST['recommend_beautiful_point1']) ? $_POST['recommend_beautiful_point1'] : '0'; //美丽积分
        $data['recommend_beautiful_point2'] = isset($_POST['recommend_beautiful_point2']) ? $_POST['recommend_beautiful_point2'] : '0'; //美丽积分
        $data['recommend_beautiful_point3'] = isset($_POST['recommend_beautiful_point3']) ? $_POST['recommend_beautiful_point3'] : '0'; //美丽积分


        $distributor = new DistributorService();
        $retval = $distributor->addDistributorLevel($data);
        if ($retval) {
            $this->addUserLog('添加分销商等级', $retval);
        }
        return AjaxReturn($retval);
    }

    /**
     * 修改分销商等级
     */
    public function updateDistributorLevel()
    {
        $distributor = new DistributorService();
        $id = isset($_POST['id']) ? $_POST['id'] : ''; //等级id
        $data['level_name'] = isset($_POST['level_name']) ? $_POST['level_name'] : ''; //等级名称
        $data['recommend_type'] = isset($_POST['recommend_type']) ? $_POST['recommend_type'] : '1'; //返佣类型
        $data['commission1'] = isset($_POST['commission1']) ? $_POST['commission1'] : '0'; //一级返佣比例
        $data['commission2'] = isset($_POST['commission2']) ? $_POST['commission2'] : '0'; //二级返佣比例
        $data['commission3'] = isset($_POST['commission3']) ? $_POST['commission3'] : '0'; //三级返佣比例
        $data['commission_point1'] = isset($_POST['commission_point1']) ? $_POST['commission_point1'] : '0'; //一级返佣积分比例
        $data['commission_point2'] = isset($_POST['commission_point2']) ? $_POST['commission_point2'] : '0'; //二级返佣积分比例
        $data['commission_point3'] = isset($_POST['commission_point3']) ? $_POST['commission_point3'] : '0'; //三级返佣积分比例
        $data['commission11'] = isset($_POST['commission11']) ? $_POST['commission11'] : '0'; //一级返佣
        $data['commission22'] = isset($_POST['commission22']) ? $_POST['commission22'] : '0'; //二级返佣
        $data['commission33'] = isset($_POST['commission33']) ? $_POST['commission33'] : '0'; //三级返佣
        $data['commission_point11'] = isset($_POST['commission_point11']) ? $_POST['commission_point11'] : '0'; //一级返佣积分
        $data['commission_point22'] = isset($_POST['commission_point22']) ? $_POST['commission_point22'] : '0'; //二级返佣积分
        $data['commission_point33'] = isset($_POST['commission_point33']) ? $_POST['commission_point33'] : '0'; //三级返佣积分
        $data['recommend1'] = isset($_POST['recommend1']) ? $_POST['recommend1'] : '0'; //一级奖励金
        $data['recommend2'] = isset($_POST['recommend2']) ? $_POST['recommend2'] : '0'; //二级奖励金
        $data['recommend3'] = isset($_POST['recommend3']) ? $_POST['recommend3'] : '0'; //三级奖励金
        $data['recommend_point1'] = isset($_POST['recommend_point1']) ? $_POST['recommend_point1'] : '0'; //一级奖励积分
        $data['recommend_point2'] = isset($_POST['recommend_point2']) ? $_POST['recommend_point2'] : '0'; //二级奖励积分
        $data['recommend_point3'] = isset($_POST['recommend_point3']) ? $_POST['recommend_point3'] : '0'; //三级奖励积分
        $data['upgradetype'] = isset($_POST['upgradetype']) ? $_POST['upgradetype'] : 2; //自动升级
        $data['offline_number'] = isset($_POST['offline_number']) ? $_POST['offline_number'] : '0'; //满足下线总人数
        $data['order_money'] = isset($_POST['order_money']) ? $_POST['order_money'] : '0'; //满足分销订单金额
        $data['order_number'] = isset($_POST['order_number']) ? $_POST['order_number'] : '0'; //满足分销订单数
        $data['selforder_money'] = isset($_POST['selforder_money']) ? $_POST['selforder_money'] : '0'; //自购订单金额
        $data['selforder_number'] = isset($_POST['selforder_number']) ? $_POST['selforder_number'] : '0'; //自购订单数
        $data['downgradetype'] = isset($_POST['downgradetype']) ? $_POST['downgradetype'] : 2; //自动降级
        $data['team_number'] = isset($_POST['team_number']) ? $_POST['team_number'] : '0'; //团队人数
        $data['team_money'] = isset($_POST['team_money']) ? $_POST['team_money'] : '0'; //团队订单金额
        $data['self_money'] = isset($_POST['self_money']) ? $_POST['self_money'] : '0'; //自购订单金额
        $data['team_number_day'] = isset($_POST['team_number_day']) ? $_POST['team_number_day'] : '0'; //时间段：团队人数
        $data['team_money_day'] = isset($_POST['team_money_day']) ? $_POST['team_money_day'] : '0'; //时间段：团队订单金额
        $data['self_money_day'] = isset($_POST['self_money_day']) ? $_POST['self_money_day'] : '0'; //时间段：自购订单金额
        $data['weight'] = isset($_POST['weight']) ? $_POST['weight'] : ''; //权重
        $data['downgrade_condition'] = isset($_POST['downgrade_condition']) ? $_POST['downgrade_condition'] : ''; //升级条件
        $data['upgrade_condition'] = isset($_POST['upgrade_condition']) ? $_POST['upgrade_condition'] : ''; //降级条件
        $data['downgradeconditions'] = isset($_POST['downgradeconditions']) ? $_POST['downgradeconditions'] : ''; //升级条件
        $data['upgradeconditions'] = isset($_POST['upgradeconditions']) ? $_POST['upgradeconditions'] : ''; //降级条件
        $data['goods_id'] = isset($_POST['goods_id']) ? $_POST['goods_id'] : ''; //指定商品id
        $data['upgrade_level'] = isset($_POST['upgrade_level']) ? $_POST['upgrade_level'] : '0'; //推荐等级
        $data['level_number'] = isset($_POST['level_number']) ? $_POST['level_number'] : '0'; //推荐等级人数
        $data['number1'] = isset($_POST['number1']) ? $_POST['number1'] : '0'; //一级分销商
        $data['number2'] = isset($_POST['number2']) ? $_POST['number2'] : '0'; //二级分销商
        $data['number3'] = isset($_POST['number3']) ? $_POST['number3'] : '0'; //三级分销商
        $data['number4'] = isset($_POST['number4']) ? $_POST['number4'] : '0'; //团队人数
        $data['number5'] = isset($_POST['number5']) ? $_POST['number5'] : '0'; //下线客户
        //复购设置
        $data['buyagain'] = isset($_POST['buyagain']) ? $_POST['buyagain'] : 0; //是否开启复购
        $data['buyagain_recommendtype'] = isset($_POST['buyagain_recommendtype']) ? $_POST['buyagain_recommendtype'] : 1; //复购类型
        $data['buyagain_commission1'] = isset($_POST['buyagain_commission1']) ? $_POST['buyagain_commission1'] : '0'; //一级返佣比例
        $data['buyagain_commission2'] = isset($_POST['buyagain_commission2']) ? $_POST['buyagain_commission2'] : '0'; //二级返佣比例
        $data['buyagain_commission3'] = isset($_POST['buyagain_commission3']) ? $_POST['buyagain_commission3'] : '0'; //三级返佣比例
        $data['buyagain_commission_point1'] = isset($_POST['buyagain_commission_point1']) ? $_POST['buyagain_commission_point1'] : '0'; //一级返佣积分比例
        $data['buyagain_commission_point2'] = isset($_POST['buyagain_commission_point2']) ? $_POST['buyagain_commission_point2'] : '0'; //二级返佣积分比例
        $data['buyagain_commission_point3'] = isset($_POST['buyagain_commission_point3']) ? $_POST['buyagain_commission_point3'] : '0'; //三级返佣积分比例
        $data['buyagain_commission11'] = isset($_POST['buyagain_commission11']) ? $_POST['buyagain_commission11'] : '0'; //一级返佣
        $data['buyagain_commission22'] = isset($_POST['buyagain_commission22']) ? $_POST['buyagain_commission22'] : '0'; //二级返佣
        $data['buyagain_commission33'] = isset($_POST['buyagain_commission33']) ? $_POST['buyagain_commission33'] : '0'; //三级返佣
        $data['buyagain_commission_point11'] = isset($_POST['buyagain_commission_point11']) ? $_POST['buyagain_commission_point11'] : '0'; //一级返佣积分
        $data['buyagain_commission_point22'] = isset($_POST['buyagain_commission_point22']) ? $_POST['buyagain_commission_point22'] : '0'; //二级返佣积分
        $data['buyagain_commission_point33'] = isset($_POST['buyagain_commission_point33']) ? $_POST['buyagain_commission_point33'] : '0'; //三级返佣积分

        $data['commission_beautiful_point1'] = isset($_POST['commission_beautiful_point1']) ? $_POST['commission_beautiful_point1'] : '0'; //美丽积分
        $data['commission_beautiful_point2'] = isset($_POST['commission_beautiful_point2']) ? $_POST['commission_beautiful_point2'] : '0'; //美丽积分
        $data['commission_beautiful_point3'] = isset($_POST['commission_beautiful_point3']) ? $_POST['commission_beautiful_point3'] : '0'; //美丽积分
        $data['commission_beautiful_point11'] = isset($_POST['commission_beautiful_point11']) ? $_POST['commission_beautiful_point11'] : '0'; //美丽积分
        $data['commission_beautiful_point22'] = isset($_POST['commission_beautiful_point22']) ? $_POST['commission_beautiful_point22'] : '0'; //美丽积分
        $data['commission_beautiful_point33'] = isset($_POST['commission_beautiful_point33']) ? $_POST['commission_beautiful_point33'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point1'] = isset($_POST['buyagain_commission_beautiful_point1']) ? $_POST['buyagain_commission_beautiful_point1'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point2'] = isset($_POST['buyagain_commission_beautiful_point2']) ? $_POST['buyagain_commission_beautiful_point2'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point3'] = isset($_POST['buyagain_commission_beautiful_point3']) ? $_POST['buyagain_commission_beautiful_point3'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point11'] = isset($_POST['buyagain_commission_beautiful_point11']) ? $_POST['buyagain_commission_beautiful_point11'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point22'] = isset($_POST['buyagain_commission_beautiful_point22']) ? $_POST['buyagain_commission_beautiful_point22'] : '0'; //美丽积分
        $data['buyagain_commission_beautiful_point33'] = isset($_POST['buyagain_commission_beautiful_point33']) ? $_POST['buyagain_commission_beautiful_point33'] : '0'; //美丽积分
        $data['recommend_beautiful_point1'] = isset($_POST['recommend_beautiful_point1']) ? $_POST['recommend_beautiful_point1'] : '0'; //美丽积分
        $data['recommend_beautiful_point2'] = isset($_POST['recommend_beautiful_point2']) ? $_POST['recommend_beautiful_point2'] : '0'; //美丽积分
        $data['recommend_beautiful_point3'] = isset($_POST['recommend_beautiful_point3']) ? $_POST['recommend_beautiful_point3'] : '0'; //美丽积分

        $retval = $distributor->updateDistributorLevel($id, $data);
        if ($retval) {
            $this->addUserLog('修改分销商等级', $id);
        }
        return AjaxReturn($retval);
    }

    /**
     * 删除 分销商等级
     */
    public function deleteDistributorLevel()
    {
        $distributor = new DistributorService();
        $id = request()->post("id", "");
        $res = $distributor->deleteDistributorLevel($id);
        if ($res) {
            $this->addUserLog('删除 分销商等级', $id);
        }
        return AjaxReturn($res);
    }

    /**
     * 分销订单概况
     */
    public function distributionOrderProfile()
    {
        $website_id = isset($_POST['website_id']) ? $_POST['website_id'] : $this->website_id;
        $order_distributor = new DistributorService();
        list($start, $end) = Time::dayToNow(6, true);
        $orderType = ['订单金额', '订单佣金'];
        $data = array();
        $data['ordertype'] = $orderType;
        for ($i = 0; $i < count($orderType); $i++) {
            switch ($orderType[$i]) {
                case '订单金额':
                    $status = 1;
                    break;
                case '订单佣金':
                    $status = 2;
                    break;
            }
            for ($j = 0; $j < ($end + 1 - $start) / 86400; $j++) {
                $data['day'][$j] = date("Y-m-d", $start + 86400 * $j);
                $date_start = strtotime(date("Y-m-d H:i:s", $start + 86400 * $j));
                $date_end = strtotime(date("Y-m-d H:i:s", $start + 86400 * ($j + 1)));
                if ($status == 1) {
                    $count = $order_distributor->getOrderMoneySum(['order_status' => ['between', [1, 4]], 'website_id' => $website_id, 'create_time' => ['between', [$date_start, $date_end]]]);
                }
                if ($status == 2) {
                    $count = $order_distributor->getPayMoneySum(['order_status' => ['between', [1, 4]], 'website_id' => $website_id, 'create_time' => ['between', [$date_start, $date_end]]]);
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
     * 分销概况
     */
    public function distributionProfile()
    {
        $website_id = isset($_POST['website_id']) ? $_POST['website_id'] : $this->website_id;
        $agent_level = new VslDistributorLevelModel();
        $level_info = $agent_level->getInfo(['website_id' => $website_id, 'is_default' => 1], '*');
        if ($level_info) {
        } else {
            $data = array(
                'level_name' => '默认分销等级',
                'is_default' => 1,
                'commission1' => 0,
                'commission2' => 0,
                'commission3' => 0,
                'weight' => 1,
                'create_time' => time(),
                'website_id' => $website_id
            );
            $agent_level->save($data);
        }
        $distributor = new DistributorService();
        $data = $distributor->getDistributorCount($website_id);
        return $data;
    }

    /**
     * 基本设置
     */
    public function basicSetting()
    {
        $config = new DistributorService();
        if (request()->isPost()) {
            // 基本设置
            $distribution_status = request()->post('distribution_status', ''); // 是否开启分销
            $distribution_admin_status = request()->post('distribution_admin_status', ''); // 是否开启店铺分销
            $distribution_pattern = request()->post('distribution_pattern', ''); // 分销模式
            $purchase_type = request()->post('purchase_type', ''); // 分销内购是否开启
            $distributor_condition = request()->post('distributor_condition', ''); // 成为分销商的条件
            $distributor_conditions = request()->post('distributor_conditions', ''); // 条件选择
            $pay_money = request()->post('pay_money', ''); // 消费金额额度
            $order_number = request()->post('order_number', ''); // 订单数
            $distributor_check = request()->post('distributor_check', ''); // 是否开启自动审核
            $referee_check = request()->post('referee_check', ''); // 是否开启强制绑定上级
            $distributor_grade = request()->post('distributor_grade', ''); // 是否开启跳级降级设置
            $goods_id = request()->post('goods_id', ''); // 指定商品
            $lower_condition = request()->post('lower_condition', ''); // 成为下线条件
            $distributor_datum = request()->post('distributor_datum', ''); // 分销必须完整资料
            $order_status = request()->post('order_status', ''); // 分销必须完整资料
            $distribution_piece = request()->post('distribution_piece', 0); // 固定佣金按件计算

            $retval = $config->setDistributionSite($distribution_status, $distribution_pattern, $purchase_type, $distributor_condition, $distributor_conditions, $pay_money, $order_number, $distributor_check, $distributor_grade, $goods_id, $lower_condition, $distributor_datum, $distribution_admin_status, $order_status, $distribution_piece,$referee_check);
            if ($retval) {
                $this->addUserLog('保存分销商基本设置', $retval);
            }
            setAddons('distribution', $this->website_id, $this->instance_id);
            setAddons('distribution', $this->website_id, $this->instance_id, true);
            return AjaxReturn($retval);
        }
    }

    /**
     * 结算设置
     */
    public function settlementSetting()
    {
        $config = new DistributorService();
        if (request()->isPost()) {
            // 结算设置
            $withdrawals_type = request()->post('withdrawals_type', ''); // 提现方式
            $make_money = request()->post('make_money', ''); // 打款方式
            $commission_calculation = request()->post('commission_calculation', ''); // 佣金计算节点
            $commission_arrival = request()->post('commission_arrival', ''); // 佣金到账节点
            $withdrawals_check = request()->post('withdrawals_check', ''); // 佣金提现免审核
            $withdrawals_min = request()->post('withdrawals_min', ''); // 佣金最低提现金额
            $withdrawals_cash = request()->post('withdrawals_cash', ''); // 佣金免审核提现金额
            $withdrawals_begin = request()->post('withdrawals_begin', ''); // 佣金提现免手续费区间
            $withdrawals_end = request()->post('withdrawals_end', ''); //佣金提现免手续费区间
            $poundage = request()->post('poundage', ''); // 佣金提现手续费
            $settlement_type = request()->post('settlement_type', ''); // 佣金提现手续费
            $retval = $config->setSettlementSite($withdrawals_type, $make_money, $commission_calculation, $commission_arrival, $withdrawals_check, $withdrawals_min, $withdrawals_cash, $withdrawals_begin, $withdrawals_end, $poundage, $settlement_type);
            if ($retval) {
                $this->addUserLog('分销商结算设置', $retval);
            }
            return AjaxReturn($retval);
        }
    }

    /**
     * 申请协议
     */
    public function applicationAgreement()
    {
        $config = new DistributorService();
        if (request()->isPost()) {
            // 基本设置
            $type = request()->post('type', 2);
            $logo = request()->post('image', '');
            $content = htmlspecialchars(stripslashes($_POST['content']));
            $distribution_label = request()->post('distribution_label', ''); // 分销标识
            $distribution_name = request()->post('distribution_name', ''); // 分销中心
            $distributor_name = request()->post('distributor_name', ''); // 分销商名称
            $distribution_commission = request()->post('distribution_commission', ''); //分销佣金
            $commission = request()->post('commission', ''); // 累积佣金
            $total_commission = request()->post('total_commission', ''); // 累积佣金
            $commission_details = request()->post('commission_details', ''); // 佣金明细
            $withdrawable_commission = request()->post('withdrawable_commission', ''); // 可提现佣金
            $withdrawals_commission = request()->post('withdrawals_commission', ''); //已提现佣金
            $withdrawal = request()->post('withdrawal', ''); // 提现中
            $frozen_commission = request()->post('frozen_commission', ''); // 冻结佣金
            $distribution_order = request()->post('distribution_order', ''); // 分销订单
            $my_team = request()->post('my_team', ''); // 我的团队
            $team1 = request()->post('team1', ''); // 一级团队
            $team2 = request()->post('team2', ''); // 二级团队
            $team3 = request()->post('team3', ''); // 三级团队
            $my_customer = request()->post('my_customer', ''); // 我的客户
            $extension_code = request()->post('extension_code', ''); // 推广码
            $distribution_tips = request()->post('distribution_tips', ''); // 分销小提示
            $become_distributor = request()->post('become_distributor', ''); // 成为分销商

            if ($content) {
                $content = htmlspecialchars_decode($content);
            }
            $retval = $config->setAgreementSite($type, $logo, $content, $distribution_label, $distribution_name, $distributor_name, $distribution_commission, $commission, $commission_details, $withdrawable_commission, $withdrawals_commission, $withdrawal, $frozen_commission, $distribution_order, $my_team, $team1, $team2, $team3, $my_customer, $extension_code, $distribution_tips, $become_distributor, $total_commission);
            if ($retval) {
                $this->addUserLog('分销商申请协议', $retval);
            }
            return AjaxReturn($retval);
        }
    }
    /**
     * 佣金提现列表
     */
    public function commissionWithdrawList()
    {
        $page_index = request()->post("page_index", 1);
        $withdraw_no = request()->post('withdraw_no', '');
        $status = request()->post('status', '');
        $website_id = request()->post('website_id', $this->website_id);
        $commission = new DistributorService();
        $condition = array('nmar.website_id' => $website_id);
        $search_text = request()->post('search_text', '');
        $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
            'like',
            '%' . $search_text . '%'
        ];
        if ($status != '' && $status != 9) {
            $condition['nmar.status'] = $status;
        }
        if ($withdraw_no != '') {
            $condition['nmar.withdraw_no'] = $withdraw_no;
        }
        if (empty($_POST['start_date'])) {
            $start_date = strtotime('2018-1-1');
        } else {
            $start_date = strtotime($_POST['start_date']);
        }
        if (empty($_POST['end_date'])) {
            $end_date = strtotime('2038-1-1');
        } else {
            $end_date = strtotime($_POST['end_date']);
        }
        $condition["nmar.ask_for_date"] = [[">", $start_date], ["<", $end_date]];
        $list = $commission->getCommissionWithdrawList($page_index, PAGESIZE, $condition, 'ask_for_date desc');
        return $list;
    }

    public function getWithdrawCount()
    {
        $order = new DistributorService();
        $order_count_array = array();
        $order_count_array['countall'] = $order->getMemberWithdrawalCount(['website_id' => $this->website_id]);
        $order_count_array['waitcheck'] = $order->getMemberWithdrawalCount(['status' => 1, 'website_id' => $this->website_id]);
        $order_count_array['waitmake'] = $order->getMemberWithdrawalCount(['status' => 2, 'website_id' => $this->website_id]);
        $order_count_array['make'] = $order->getMemberWithdrawalCount(['status' => 3, 'website_id' => $this->website_id]);
        $order_count_array['makefail'] = $order->getMemberWithdrawalCount(['status' => 5, 'website_id' => $this->website_id]);
        $order_count_array['nomake'] = $order->getMemberWithdrawalCount(['status' => 4, 'website_id' => $this->website_id]);
        $order_count_array['nocheck'] = $order->getMemberWithdrawalCount(['status' => -1, 'website_id' => $this->website_id]);
        return $order_count_array;
    }

    /**
     * 佣金提现列表导出
     */
    public function commissionWithdrawListDataExcel()
    {
        $xlsName = "佣金提现流水列表";
        $xlsCell = array(
            array(
                'withdraw_no',
                '流水号'
            ),
            array(
                'user_name',
                '会员信息'
            ),
            array(
                'type',
                '提现方式'
            ),
            array(
                'account_number',
                '提现账户'
            ),
            array(
                'realname',
                '账户真实姓名'
            ),
            array(
                'cash',
                '提现金额'
            ),
            array(
                'tax',
                '个人所得税'
            ),
            array(
                'status',
                '提现状态'
            ),
            array(
                'ask_for_date',
                '申请时间'
            ),
            array(
                'payment_date',
                '打款时间'
            ),
            array(
                'memo',
                '备注'
            )
        );
        $withdraw_no = request()->get('withdraw_no', '');
        $status = request()->get('status', '');
        $website_id = request()->get('website_id', $this->website_id);
        $commission = new DistributorService();
        $condition = array('nmar.website_id' => $website_id);
        $search_text = request()->get('search_text', '');
        $condition['su.nick_name|su.user_tel|su.user_name'] = [
            'like',
            '%' . $search_text . '%'
        ];
        if ($status != '' && $status != 9) {
            $condition['nmar.status'] = $status;
        }
        if ($withdraw_no != '') {
            $condition['nmar.withdraw_no'] = $withdraw_no;
        }
        if (empty($_POST['start_date'])) {
            $start_date = strtotime('2018-1-1');
        } else {
            $start_date = strtotime($_POST['start_date']);
        }
        if (empty($_POST['end_date'])) {
            $end_date = strtotime('2038-1-1');
        } else {
            $end_date = strtotime($_POST['end_date']);
        }
        $condition["nmar.ask_for_date"] = [[">", $start_date], ["<", $end_date]];

        //edit for 2020/04/26 导出操作移到到计划任务统一执行
        $insert_data = array(
            'type' => 9,
            'status' => 0,
            'exname' => $xlsName,
            'website_id' => $website_id,
            'addtime' => time(),
            'ids' => serialize($xlsCell),
            'conditions' => serialize($condition),
        );
        $excels_export = new ExcelsExport();
        $res = $excels_export->insertData($insert_data);
        return $res;
    }

    /**
     * 佣金提现详情
     */
    public function commissionWithdrawInfo()
    {
        $commission = new DistributorService();
        $id = $_GET['id'];
        $retval = $commission->commissionWithdrawDetail($id);
        return $retval;
    }

    /**
     * 佣金提现审核
     */
    public function commissionWithdrawAudit()
    {
        $commission = new DistributorService();
        $id = $_POST['id'];
        $status = $_POST['status'];
        $memo = $_POST['memo'];
        $is_biz = request()->post('is_biz', 0);
        $ids = explode(',', $id);
        if (count($ids) > 1) {
            foreach ($ids as $v) {
                $retval = $commission->commissionWithdrawAudit($v, $status, $memo);
            }
        } else {
            $retval = $commission->commissionWithdrawAudit($id, $status, $memo);
        }
        if ($retval == -9000) {
            $balance = new VslDistributorCommissionWithdrawModel();
            $msg = $balance->getInfo(['id' => $id], 'memo')['memo'];
        } elseif ($retval > 0) {
            $msg = '打款成功';
        } else {
            $msg = '打款失败';
        }
        if ($retval) {
            $this->addUserLog('佣金提现审核', $id);
        }
        if ($is_biz) {
            return json(AjaxReturn($retval));
        }
        return AjaxReturn($retval, $msg);
    }

    /**
     * 佣金流水
     */
    public function commissionRecordsList()
    {
        if (request()->isAjax()) {
            $commission = new DistributorService();
            $page_index = request()->post("page_index", 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $search_text = request()->post('search_text', '');
            $records_no = request()->post('records_no', '');
            $from_type = request()->post('from_type', '');
            $start_date = request()->post('start_date') == "" ? '2010-1-1' : request()->post('start_date');
            $end_date = request()->post('end_date') == "" ? '2038-1-1' : request()->post('end_date');
            $condition['nmar.website_id'] = request()->post('website_id', $this->website_id);
            $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
                'like',
                '%' . $search_text . '%'
            ];
            $condition["nmar.create_time"] = [
                    [
                    ">",
                    strtotime($start_date)
                ],
                    [
                    "<",
                    strtotime($end_date)
                ]
            ];
            if ($from_type == 4) {
                $condition['nmar.from_type'] = [['>', 3], ['<=', 24]];
            } elseif ($from_type != '') {
                $condition['nmar.from_type'] = $from_type;
            }
            if ($records_no != '') {
                $condition['nmar.records_no'] = $records_no;
            }
            $condition['nmar.commission'] = ['neq', 0];
            $list = $commission->getAccountList($page_index, $page_size, $condition, $order = '', $field = '*');
            return $list;
        }
    }

    /**
     * 佣金流水详情
     */
    public function commissionInfo()
    {
        $commission = new DistributorService();
        $id = request()->get('id');
        $condition['nmar.id'] = $id;
        $list = $commission->getAccountList(1, 0, $condition, $order = '', $field = '*');
        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]["commission"] = '¥' . $list['data'][$k]["commission"];
            if ($list['data'][$k]["from_type"] == 1) {
                $list['data'][$k]["from_type"] = '订单完成';
            }
            if ($list['data'][$k]["from_type"] == 2) {
                $list['data'][$k]["from_type"] = '订单退款成功';
            }
            if ($list['data'][$k]["from_type"] == 3) {
                $list['data'][$k]["from_type"] = '订单支付成功';
            }
            if ($list['data'][$k]["from_type"] == 4) {
                $list['data'][$k]["from_type"] = '成功提现到账户余额';
            }
            if ($list['data'][$k]["from_type"] == 5) {
                $list['data'][$k]["from_type"] = '提现到微信待打款';
            }
            if ($list['data'][$k]["from_type"] == 6) {
                $list['data'][$k]["from_type"] = '提现到账户余额审核中';
            }
            if ($list['data'][$k]["from_type"] == 7) {
                $list['data'][$k]["from_type"] = '提现到支付宝待打款';
            }
            if ($list['data'][$k]["from_type"] == 8) {
                $list['data'][$k]["from_type"] = '提现到银行卡待打款';
            }
            if ($list['data'][$k]["from_type"] == 9) {
                $list['data'][$k]["from_type"] = '成功提现到银行卡';
            }
            if ($list['data'][$k]["from_type"] == -9) {
                $list['data'][$k]["from_type"] = '提现到银行卡打款失败';
            }
            if ($list['data'][$k]["from_type"] == 10) {
                $list['data'][$k]["from_type"] = '成功提现到微信';
            }
            if ($list['data'][$k]["from_type"] == -10) {
                $list['data'][$k]["from_type"] = '提现到微信打款失败';
            }
            if ($list['data'][$k]["from_type"] == 11) {
                $list['data'][$k]["from_type"] = '成功提现到支付宝';
            }
            if ($list['data'][$k]["from_type"] == -11) {
                $list['data'][$k]["from_type"] = '提现到支付宝打款失败';
            }
            if ($list['data'][$k]["from_type"] == 12) {
                $list['data'][$k]["from_type"] = '提现到银行卡，审核中';
            }
            if ($list['data'][$k]["from_type"] == 13) {
                $list['data'][$k]["from_type"] = '提现到微信，审核中';
            }
            if ($list['data'][$k]["from_type"] == 14) {
                $list['data'][$k]["from_type"] = '提现到支付宝，审核中';
            }
            if ($list['data'][$k]["from_type"] == 15) {
                $list['data'][$k]["from_type"] = '提现到账户余额，待打款';
            }
            if ($list['data'][$k]["from_type"] == 16) {
                $list['data'][$k]["from_type"] = '提现到微信，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 17) {
                $list['data'][$k]["from_type"] = '提现到支付宝，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 18) {
                $list['data'][$k]["from_type"] = '提现到账户余额，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 19) {
                $list['data'][$k]["from_type"] = '提现到微信，审核不通过';
            }
            if ($list['data'][$k]["from_type"] == 20) {
                $list['data'][$k]["from_type"] = '提现到支付宝，审核不通过';
            }
            if ($list['data'][$k]["from_type"] == 21) {
                $list['data'][$k]["from_type"] = '提现到账户余额，不通过';
            }
            if ($list['data'][$k]["from_type"] == 22) {
                $list['data'][$k]["from_type"] = '分销商等级升级获得推荐奖';
            }
            if ($list['data'][$k]["from_type"] == 23) {
                $list['data'][$k]["from_type"] = '提现到银行卡，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 24) {
                $list['data'][$k]["from_type"] = '提现到银行卡，审核不通过';
            }
        }
        return $list['data'];
    }

    /**
     * 佣金流水数据excel导出
     */
    public function commissionRecordsDataExcel()
    {
        $xlsName = "佣金流水列表";
        $xlsCell = array(
            array(
                'records_no',
                '流水号'
            ),
            array(
                'data_id',
                '外部交易号'
            ),
            array(
                'user_name',
                '会员信息'
            ),
            array(
                'type_name',
                '变动类型'
            ),
            array(
                'change_money',
                '变动金额'
            ),
            array(
                'tax',
                '个人所得税'
            ),
            array(
                'create_time',
                '变动时间'
            ),
            array(
                'text',
                '备注'
            )
        );
        $commission = new DistributorService();
        $search_text = request()->get('search_text', '');
        $from_type = request()->get('from_type', '');
        $records_no = request()->get('records_no', '');
        $start_date = request()->get('start_date') == "" ? '2010-1-1' : request()->get('start_date');
        $end_date = request()->get('end_date') == "" ? '2038-1-1' : request()->get('end_date');
        $condition['nmar.website_id'] = request()->get('website_id', $this->website_id);
        $shop_id = request()->get('shop_id', '');
        if ($shop_id) {
            $condition['nmar.shop_id'] = $shop_id;
        }

        if ($search_text) {
            $condition['su.nick_name|su.user_tel|su.user_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $condition["nmar.create_time"] = [
                [
                ">",
                strtotime($start_date)
            ],
                [
                "<",
                strtotime($end_date)
            ]
        ];
        if ($from_type != '') {
            if ($from_type == 4) {
                $condition['nmar.from_type'] = ['>', 3];
            } else {
                $condition['nmar.from_type'] = $from_type;
            }
        }
        if ($records_no != '') {
            $condition['nmar.records_no'] = $records_no;
        }

        //edit for 2020/04/26 导出操作移到到计划任务统一执行
        //查询是否为店铺请求，是则直接导出
        if (!$shop_id) {
            $insert_data = array(
                'type' => 10,
                'status' => 0,
                'exname' => $xlsName,
                'website_id' => request()->get('website_id', $this->website_id),
                'addtime' => time(),
                'ids' => serialize($xlsCell),
                'conditions' => serialize($condition),
            );
            $excels_export = new ExcelsExport();
            $res = $excels_export->insertData($insert_data);
            return $res;
            exit;
        }


        $list = $commission->getAccountList(1, 0, $condition, $order = '', $field = '*');

        foreach ($list['data'] as $k => $v) {
            $list['data'][$k]["commission"] = '¥' . $list['data'][$k]["commission"];
            $list['data'][$k]["tax"] = '¥' . $list['data'][$k]["tax"];
            if ($list['data'][$k]["from_type"] == 1) {
                $list['data'][$k]["from_type"] = '订单完成';
            }
            if ($list['data'][$k]["from_type"] == 2) {
                $list['data'][$k]["from_type"] = '订单退款成功';
            }
            if ($list['data'][$k]["from_type"] == 3) {
                $list['data'][$k]["from_type"] = '订单支付成功';
            }
            if ($list['data'][$k]["from_type"] == 4) {
                $list['data'][$k]["from_type"] = '成功提现到账户余额';
            }
            if ($list['data'][$k]["from_type"] == 5) {
                $list['data'][$k]["from_type"] = '提现到微信待打款';
            }
            if ($list['data'][$k]["from_type"] == 6) {
                $list['data'][$k]["from_type"] = '提现到账户余额审核中';
            }
            if ($list['data'][$k]["from_type"] == 7) {
                $list['data'][$k]["from_type"] = '提现到支付宝待打款';
            }
            if ($list['data'][$k]["from_type"] == 8) {
                $list['data'][$k]["from_type"] = '提现到银行卡待打款';
            }
            if ($list['data'][$k]["from_type"] == 9) {
                $list['data'][$k]["from_type"] = '成功提现到银行卡';
            }
            if ($list['data'][$k]["from_type"] == 10) {
                $list['data'][$k]["from_type"] = '成功提现到微信';
            }
            if ($list['data'][$k]["from_type"] == 11) {
                $list['data'][$k]["from_type"] = '成功提现到支付宝';
            }
            if ($list['data'][$k]["from_type"] == 12) {
                $list['data'][$k]["from_type"] = '提现到银行卡，审核中';
            }
            if ($list['data'][$k]["from_type"] == 13) {
                $list['data'][$k]["from_type"] = '提现到微信，审核中';
            }
            if ($list['data'][$k]["from_type"] == 14) {
                $list['data'][$k]["from_type"] = '提现到支付宝，审核中';
            }
            if ($list['data'][$k]["from_type"] == 15) {
                $list['data'][$k]["from_type"] = '提现到账户余额，待打款';
            }
            if ($list['data'][$k]["from_type"] == 16) {
                $list['data'][$k]["from_type"] = '提现到微信，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 17) {
                $list['data'][$k]["from_type"] = '提现到支付宝，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 18) {
                $list['data'][$k]["from_type"] = '提现到账户余额，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 19) {
                $list['data'][$k]["from_type"] = '提现到微信，审核不通过';
            }
            if ($list['data'][$k]["from_type"] == 20) {
                $list['data'][$k]["from_type"] = '提现到支付宝，审核不通过';
            }
            if ($list['data'][$k]["from_type"] == 21) {
                $list['data'][$k]["from_type"] = '提现到账户余额，不通过';
            }
            if ($list['data'][$k]["from_type"] == 22) {
                $list['data'][$k]["from_type"] = '分销商等级升级获得推荐奖';
            }
            if ($list['data'][$k]["from_type"] == 23) {
                $list['data'][$k]["from_type"] = '提现到银行卡，已拒绝';
            }
            if ($list['data'][$k]["from_type"] == 24) {
                $list['data'][$k]["from_type"] = '提现到银行卡，审核不通过';
            }

            $this->addUserLog('佣金流水数据excel导出', 1);
            dataExcel($xlsName, $xlsCell, $list['data']);
        }
    }
    /**
     * 前台申请成为分销商接口
     */
    public function distributorApply($params = array())
    {
        $uid = $this->uid;
        $website_id = $this->website_id;
        $post_data = request()->post('post_data', '');
        $real_name = request()->post('real_name', '');
        $member = new DistributorService();
        $res = $member->addDistributorInfo($website_id, $uid, $post_data, $real_name);
        if ($res > 0) {
            $data['code'] = 0;
            $data['message'] = "提交成功";
        } else {
            $data['code'] = -1;
            $data['message'] = "提交失败";
        }
        return json($data);
    }

    /**
     * 前台完善资料修改
     */
    public function dataComplete()
    {
        $uid = $this->uid;
        $post_data = request()->post('post_data', '');
        $real_name = request()->post('real_name', '');
        $member = new DistributorService();
        $res = $member->addDistributorInfos($uid, $post_data, $real_name);
        if ($res > 0) {
            $data['code'] = 0;
            $data['message'] = "提交成功";
        } else {
            $data['code'] = -1;
            $data['message'] = "提交失败";
        }
        return json($data);
    }

    /**
     * 前台查询申请分销商状态接口
     */
    public function distributorStatus($params = array())
    {
        $uid = request()->post('uid', '');
        $member = new DistributorService();
        $res = $member->getDistributorStatus($uid);
        return json($res['isdistributor']);
    }

    /**
     * 前台分销中心接口
     */
    public function distributionIndex($params = array())
    {
        $member = new DistributorService();
        $uid = request()->post("uid", '');
        $res = $member->getDistributorInfo($uid);
        return json($res);
    }

    /**
     * 前台分销订单
     */
    public function distributionOrder($params = array())
    {
        $uid = $this->uid;
        $page_index = request()->post('page_index', 1);
        $website_id = $this->website_id;
        $page_size = request()->post('page_size', PAGESIZE);
        if (request()->post('order_status', '')) {
            $condition['order_status'] = request()->post('order_status', '');
        }
        $condition['is_deleted'] = 0; // 未删除订单
        $condition['website_id'] = $website_id;
        $condition['buyer_id'] = $uid;
        $order_service = new DistributorService();
        $list = $order_service->getOrderList($page_index, $page_size, $condition, 'nm.create_time desc');
        if (count($list) > 0) {
            $data['code'] = 0;
            $data['data'] = $list;
            $data['data']['page_index'] = $page_index;
        } else {
            $data['code'] = -1;
            $data['message'] = "";
        }
        return json($data);
    }

    /**
     * 前台分销提现详情
     */
    public function withdrawDetail($params = array())
    {
        $uid = request()->post('uid', '');
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition = array();
        if (request()->post('status', '') != 0) {
            $condition['nmar.status'] = request()->post('status', '');
        }
        if (request()->post('status', '') == 3) {
            $condition['nmar.status'] = [
                    [
                    ">",
                    5
                ],
                    [
                    "<",
                    8
                ]
            ];
        }
        if (request()->post('status', '') == 4) {
            $condition['nmar.status'] = [
                    [
                    ">",
                    3
                ],
                    [
                    "<",
                    6
                ]
            ];
        }
        $condition['nmar.uid'] = $uid;
        $commission = new DistributorService();
        $list = $commission->withdrawDetail($page_index, $page_size, $condition, '');
        return json($list);
    }

    /**
     * pc前台分销佣金明细
     */
    public function commissionDetails($params = array())
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['nmar.uid'] = $this->uid;
        $commission = new DistributorService();
        $list = $commission->getAccountLists($page_index, $page_size, $condition, '');
        return $list;
    }

    /**
     * 前台分销佣金明细
     */
    public function commissionDetail($params = array())
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['nmar.uid'] = $this->uid;
        $commission = new DistributorService();
        $list = $commission->getAccountLists($page_index, $page_size, $condition, '');
        $data['code'] = 0;
        $data['data'] = $list;
        $data['data']['page_index'] = $page_index;
        $data['data']['page_size'] = $page_size;
        return json($data);
    }

    /**
     * 前台分销佣金明细详情
     */
    public function commissionRecordDetail($params = array())
    {
        $id = request()->post('id', '');
        $commission = new DistributorService();
        $list = $commission->getAccountLists(1, 0, ['nmar.id' => $id], '');
        $data['code'] = 0;
        $data['data'] = $list['data'][0];
        return json($data);
    }

    /**
     * 前台我的客户
     */
    public function customerList($params = array())
    {
        $uid = $this->uid;
        $index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $distributor = new DistributorService();
        $list = $distributor->getCustomerList($uid, $index, $page_size, ['nm.website_id' => $this->website_id], 'nm.reg_time desc');
        $list = $this->object2array($list);
        $new_array = array();
        if ($list) {
            $data['data'] = $list;
        } else {
            $data['data'] = $new_array;
        }
        $data['data']['total_count'] = $list['total_count'];
        $data['data']['page_count'] = $list['page_count'];
        $data['code'] = 0;
        return json($data);
    }

    /**
     * 前台我的团队
     */
    public function teamList($params = array())
    {
        $uid = $this->uid;
        $website_id = $this->website_id;
        $type = request()->post('type', 1);
        $index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $distributor = new DistributorService();
        $list = $distributor->getTeamList($type, $uid, $website_id, $index, $page_size);
        $myinfos = $distributor->getmyinfos($uid, $website_id);
        // $myinfos2 = $distributor->getDistributorTeam($uid,$website_id);

        $user = new UserModel();
        if (empty($list['data'])) {
            $data['code'] = 0;
            $data['data']['data'] = array();
            $data['data']['number1'] = $myinfos['number1'];
            $data['data']['number2'] = $myinfos['number2'];
            $data['data']['number3'] = $myinfos['number3'];
            $data['data']['page_index'] = 1;
            $data['data']['page_count'] = 1;
            return json($data);
        } else {
            foreach ($list['data'] as $k => $v) {
                if (empty($v['distributor_level_name'])) {
                    $list['data'][$k]['distributor_level_name'] = "暂无";
                }
                $head = $user->getInfo(["uid" => $v['uid']], 'user_headimg');
                $list['data'][$k]['user_headimg'] = $head['user_headimg'];
            }
        }

        $data['data'] = $list;
        $data['data']['page_index'] = $index;
        $data['data']['number1'] = $myinfos['number1'];
        $data['data']['number2'] = $myinfos['number2'];
        $data['data']['number3'] = $myinfos['number3'];
        $data['data']['page_size'] = $page_size;
        $data['code'] = 0;
        return json($data);
    }

    /**
     * 前台佣金提现
     */
    public function commissionWithdraw($params = array())
    {
        $uid = $this->uid;
        $withdraw_no = 'CW' . time() . rand(111, 999);
        $account_id = request()->post('account_id', '');
        $cash = request()->post('cash', '');
        $distributor = new DistributorService();
        $retval = $distributor->addDistributorCommissionWithdraw($withdraw_no, $uid, $account_id, $cash);
        if ($retval > 0) {
            $data['code'] = 0;
            $data['message'] = "申请成功";
        } else {
            $data['code'] = -1;
            $data['message'] = getErrorInfo($retval);
        }
        return json($data);
    }

    /**
     * 手动结算佣金
     *
     * @param array $params
     * @return \think\response\Json
     */
    public function commissionToMember($params = array())
    {
        $uid = $this->uid;
        $distributor = new DistributorService();
        $retval = $distributor->addDistributorCommissionMember($uid);
        if ($retval > 0) {
            $data['code'] = 0;
            $data['message'] = "操作成功";
        } else {
            $data['code'] = -1;
            $data['message'] = getErrorInfo($retval);
        }
        return json($data);
    }

    /**
     * 佣金提现表单页
     */
    public function commissionWithdraw_show($params = array())
    {
        $uid = $this->uid;
        $user = new usermodel();
        $user_info = $user->getInfo(['uid' => $this->uid], 'payment_password,wx_openid');
        $commission = new DistributorService();
        $my_commission = $commission->getCommissionWithdrawConfig($uid);
        $data = array();
        if ($my_commission) {
            $data['data'] = $my_commission;
        }
        $data['data']['is_datum'] = $commission->checkDatum();
        //可提现佣金
        if ($my_commission['commission']) {
            $data['data']['commission'] = $my_commission['commission'];
        } else {
            $data['data']['commission'] = '0.00';
        }
        //设置密码
        if (empty($user_info['payment_password'])) {
            $data['data']['set_password'] = 1;
        } else {
            $data['data']['set_password'] = 0;
        }
        if (empty($user_info['wx_openid'])) {
            $data['data']['wx_openid'] = 0;
        } else {
            $data['data']['wx_openid'] = 1;
        }
        $data['code'] = 0;
        return json($data);
    }

    public function messagePushList()
    {
        $message = new SysMessagePushModel();
        $list = $message->getQuery(['website_id' => $this->website_id, 'type' => 1], '*', 'template_id asc');
        if (empty($list)) {
            $list = $message->getQuery(['type' => 1, 'website_id' => 0], '*', 'template_id asc');
            foreach ($list as $k => $v) {
                $array = [
                    'template_type' => $v['template_type'],
                    'template_title' => $v['template_title'],
                    'sign_item' => $v['sign_item'],
                    'sample' => $v['sample'],
                    'type' => 1,
                    'website_id' => $this->website_id,
                ];
                $message = new SysMessagePushModel();
                $message->save($array);
            }
            $list = $message->getQuery(['website_id' => $this->website_id, 'type' => 1], '*', 'template_id asc');
        }
        return $list;
    }

    public function editMessage()
    {
        $id = request()->post('id', '');
        $message = new SysMessagePushModel();
        $list = $message->getInfo(['template_id' => $id], '*');
        $item = new SysMessageItemModel();
        $list['sign'] = $item->getQuery(['id' => ['in', $list['sign_item']]], '*', '');
        return $list;
    }

    public function addMessage()
    {
        $is_enable = request()->post('is_enable', '');
        $id = request()->post('id', '');
        $template_content = request()->post('template_content', '');
        $message = new SysMessagePushModel();
        $res = $message->save(['is_enable' => $is_enable, 'template_content' => $template_content], ['template_id' => $id]);
        return AjaxReturn($res);
    }

    /* -----------------------------------------------------------------接口------------------------------------ */

    /**
     * 前台分销中心
     */
    public function distributionCenter()
    {
        $params['uid'] = $this->uid;
        //查询是否有分销应用
        $member = new DistributorService();
        $member_info = $member->getDistributorInfo($params['uid']);
        $data['code'] = 0;
        $data['data'] = $member_info;
        return json($data);
    }

    /**
     * 前台分销设置
     */
    public function distributionSet()
    {
        $member = new DistributorService();
        $member_info = $member->getAgreementSite($this->website_id);
        $data['code'] = 0;
        if ($member_info) {
            $data['data'] = $member_info;
        } else {
            $data['data'] = (object) [];
        }
        return json($data);
    }

    /**
     * 前台申请分销商
     */
    public function distributorApply_show()
    {
        $uid = $this->uid;
        $user = new UserModel();
        $user_info = $user->getInfo(['uid' => $uid], 'real_name,user_tel');
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid], 'isdistributor');
        $member_info['isdistributor'] = empty($member_info['isdistributor']) ? 0 : $member_info['isdistributor'];
        $config = new DistributorService();
        $list = $config->getDistributionSite($this->website_id);
        $agreement = $config->getAgreementSite($this->website_id);
        $customform = $config->getCustomForm($this->website_id);
        $data['data']['condition'] = $list;
        $data['data']['customform'] = $customform; //自定义表单
        $data['data']['xieyi'] = $agreement;
        $data['data']['user_tel'] = $user_info['user_tel'];
        $data['data']['real_name'] = $user_info['real_name'];
        $data['data']['isdistributor'] = $member_info['isdistributor'];
        $data['code'] = 0;
        return json($data);
    }

    /**
     * 分销佣金
     */
    public function myCommissiona()
    {
        $commission = new DistributorService();
        $uid = $this->uid;
        $my_commission = $commission->getCommissionWithdrawConfig($uid);
        $data = array();
        //可提现佣金
        if ($my_commission['commission']) {
            $data['data']['commission'] = $my_commission['commission'];
        } else {
            $data['data']['commission'] = '0.00';
        }

        //累积佣金
        if ($my_commission['commission']) {
            $data['data']['total_money'] = $my_commission['commission'] + $my_commission['withdrawals'] + $my_commission['freezing_commission'];
        } else {
            $data['data']['total_money'] = '0.00';
        }

        //已提现佣金
        if ($my_commission['withdrawals']) {
            $data['data']['withdrawals'] = $my_commission['withdrawals'];
        } else {
            $data['data']['withdrawals'] = '0.00';
        }

        //提现中
        if ($my_commission['apply_withdraw'] || $my_commission['make_withdraw']) {
            $data['data']['apply_withdraw'] = $my_commission['apply_withdraw'] + $my_commission['make_withdraw'];
        } else {
            $data['data']['apply_withdraw'] = '0.00';
        }

        //冻结中
        if ($my_commission['freezing_commission']) {
            $data['data']['freezing_commission'] = $my_commission['freezing_commission'];
        } else {
            $data['data']['freezing_commission'] = '0.00';
        }

        $data['code'] = 0;
        return json($data);
    }

    /**
     * 分销排行榜
     * types 1推荐榜 2佣金榜 3积分榜
     * times month year ''
     * psize 排名个数 默认10
     * operation 1 后台 0移动端
     */
    public function ranking()
    {
        $user_model = new UserModel();
        $order = new VslOrderDistributorCommissionModel();
        $tRecords = new VslAccountRecordsModel();
        $mRecords = new VslMemberAccountRecordsModel();
        $types = request()->post('types', 1);
        $times = request()->post('times', 'month');
        $psize = request()->post('psize', 10);
        $operation = request()->post('operation', 0);
        //变更
        $commissionService = new DistributorService();
        $result = $commissionService->rangking($types, $times, $psize, $operation);
        return json($result);
    }
    /**
     * 获取分红升下一级详情
     * types 1 团队分红 2 区域分红 3 全球分红 4分销
     */
    public function upbonusLevel()
    {
        $types = request()->post('types', 4);
        $uid = $this->uid;

        $website_id = $this->website_id ? $this->website_id : 26;
        $member = new VslMemberModel();

        $agent = $member->getInfo(['uid' => $uid], '*');

        $users = new UserModel();
        $member_info = $users->getInfo(['uid' => $uid, 'website_id' => $website_id], '*');

        //组装个人信息
        $user['user_name'] = $member_info['nick_name'];
        $user['user_headimg'] = $member_info['user_headimg'];

        if (getAddons('teambonus', $this->website_id)) {
            $agent = new TeamBonus();
            $member = $agent->getAgentInfo($uid);
        } else {
            $member = $member->getInfo(['uid' => $uid], '*');
        }
        $distributorServer = new DistributorService();
        $result = $distributorServer->upLevelDetail($types,$uid,$website_id,$member);
         //组装个人信息
        $result['user']['user_name'] = $member_info['nick_name'];
        $result['user']['user_headimg'] = $member_info['user_headimg'];
        //组装信息 返回
        $data['code'] = 1;
        $data['data']['downlevelCondition'] = $result['downlevelCondition'];
        $data['data']['levelCondition'] = $result['levelCondition'];
        $data['data']['user'] = $result['user'];
        $data['data']['levels'] = $result['tlist'];
        $data['message'] = "获取成功";
        return json($data);
    }
    /**
     * 佣金提现详情
     */
    public function commissionWithdrawInfoBiz()
    {
        $commission = new DistributorService();
        $id = (int)request()->post('id', 0);
        if (!$id) {
            return json(AjaxReturn(-1006));
        }
        $retval = $commission->commissionWithdrawDetail($id);
        $retval['status_name'] = $retval['status'];
        $retval['status'] = $retval['status_before'];
        $retval['realname'] = $retval['realname'] ? : $retval['user_name'];
        unset($retval['modify_date'],$retval['income_tax'], $retval['status_before'],$retval['user_name']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $retval]);
    }
    /**
     * 佣金提现列表
     */
    public function commissionWithdrawListBiz()
    {
        $page_index = (int)request()->post("page_index", 1);
        $page_size = (int)request()->post("page_size", PAGESIZE);
        $status = (int)request()->post('status', '');
        $commission = new DistributorService();
        $member = new Member();
        $condition = array('nmar.website_id' => $this->website_id);
        $search_text = trim(request()->post('search_text', ''));
        $condition['su.nick_name|su.user_tel|su.user_name|su.uid'] = [
            'like',
            '%' . $search_text . '%'
        ];
        if ($status) {
            switch ($status) {
                case 1:
                    $condition["nmar.status"] = ['in',[1,2]];
                    break;
                case 2:
                    $condition["nmar.status"] = ['in',[3,4,-1]];
                    break;
                case 3:
                    $condition["nmar.status"] = 5;
                    break;
                default:
                    break;
            }
        }
        $list = $commission->getCommissionWithdrawList($page_index, $page_size, $condition, 'ask_for_date desc');
        $list['withdraw_list'] = [];
        if ($list['data']) {
            foreach ($list['data'] as $key => $val) {
                $list['withdraw_list'][$key]['id'] = $val['id'];
                $list['withdraw_list'][$key]['withdraw_no'] = $val['withdraw_no'];
                $list['withdraw_list'][$key]['withdraw_type'] = ($val['type'] == 1 || $val['type'] == 5) ? '银行卡' : ($val['type'] == 2 ? '微信' : ($val['type'] == 3 ? '支付宝' : '账户余额'));
                $list['withdraw_list'][$key]['uid'] = $val['uid'];
                $list['withdraw_list'][$key]['status'] = $val['status'];
                $list['withdraw_list'][$key]['account_number'] = $val['account_number'];
                $list['withdraw_list'][$key]['user_info'] = $val['user_info'];
                $list['withdraw_list'][$key]['cash'] = $val['cash'];
                $list['withdraw_list'][$key]['tax'] = $val['tax'];
                $list['withdraw_list'][$key]['ask_for_date'] = $val['ask_for_date'];
                $list['withdraw_list'][$key]['payment_date'] = $val['payment_date'];
                $list['withdraw_list'][$key]['status_name'] = $member->getWithdrawStatusName($val['status']);
            }
            unset($val);
        }
        unset($list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }
}
