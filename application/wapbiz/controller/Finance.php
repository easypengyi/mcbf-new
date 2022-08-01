<?php

namespace app\wapbiz\controller;

use addons\shop\service\Shop;
use data\service\Config as WebConfig;
use data\model\VslBankModel;
use data\service\Member;
use addons\shop\model\VslShopModel;
use data\service\Order;

/**
 * 系统模块控制器
 *
 * @author  www.vslai.com
 *
 */
class Finance extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }


    /**
     * 账户列表
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:>|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function shopAccountList()
    {
        $pageindex = (int)request()->post('page_index', 1);
        $page_size = (int)request()->post('page_size', 0);
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $shop = new Shop();
        $bank = new VslBankModel();
        $list = $shop->getShopBankAccountAll($pageindex, $page_size, $condition, 'is_default desc');
        $list['shop_account_list'] = [];
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $list['shop_account_list'][$key]['id'] = $val['id'];
                $list['shop_account_list'][$key]['type'] = $val['type'];
                $list['shop_account_list'][$key]['realname'] = $val['realname'];
                $list['shop_account_list'][$key]['account_number'] = $val['account_number'];
                $list['shop_account_list'][$key]['remark'] = $val['remark'];
                $list['shop_account_list'][$key]['icon'] = '';
                $list['shop_account_list'][$key]['name'] = '';
                if(($val['type'] == 1 || $val['type'] == 4) && $val['bank_code']){
                    $bankInfo = $bank->getInfo(['bank_code' => $val['bank_code']], 'bank_iocn,bank_short_name');
                    $list['shop_account_list'][$key]['icon'] = $bankInfo['bank_iocn'];
                    $list['shop_account_list'][$key]['name'] = $bankInfo['bank_short_name'];
                    $list['shop_account_list'][$key]['bank_type'] = $val['bank_type'] == '00' ? '储蓄卡' : '信用卡';
                }
                if($val['type'] == 3){
                    $list['shop_account_list'][$key]['name'] = '支付宝';
                }
                if($val['type'] == 2){
                    $list['shop_account_list'][$key]['name'] = '微信';
                }
            }
            unset($val);
        }
        unset($list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

    /**
     * 店铺销售订单/店铺提现列表
     *
     * @return multitype:unknown Ambigous <multitype:number unknown , unknown> |Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function shopOrderAccountList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $type = request()->post('type', 0);
        $condition = [];
        if(!$type){
            $condition["shop_id"] = $this->instance_id;
            $shop = new Order();
            $list = $shop->getOrderList($page_index, $page_size, $condition, 'create_time desc');
            $list['order_list'] = [];
            if($list['data']){
                foreach($list['data'] as $key => $val){
                    $list['order_list'][$key]['order_id'] = $val['order_id'];
                    $list['order_list'][$key]['order_no'] = $val['order_no'];
                    $list['order_list'][$key]['pay_money'] = $val['pay_money'] > 0 ? $val['pay_money'] : $val['user_platform_money'];
                    $list['order_list'][$key]['create_time'] = date('Y-m-d H:i:s', $val['create_time']);
                    $list['order_list'][$key]['status'] = $val['order_status'] == 4 ? '已结算' : '待结算';
                }
                unset($val);
            }
            unset($list['data']);
        }else{
            $condition['sp.shop_id'] = $this->instance_id;
            $shop = new Shop();
            $list = $shop->getShopAccountWithdrawList($page_index, $page_size, $condition, '', 'ask_for_date desc');
            $list['withdraw_list'] = array_columns($list['data'], 'status,withdraw_no,type,cash,platform_money,charge,ask_for_date,status_name,id');
            unset($list['data']);
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

    /**
     * 店铺申请提现
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function applyShopAccountWithdraw()
    {
        $shop = new Shop();
        $cash = request()->post("cash", 0);
        $bank_account_id = request()->post("bank_account_id", 0);
        $retval = $shop->applyShopAccountWithdraw($this->instance_id, $bank_account_id, $cash);
        return json(ajaxReturn($retval));
    }

    /**
     * 添加银行账户
     */
    public function addShopAccount()
    {
        $type = request()->post('type', 0);
        
        $real_name = request()->post('real_name', '');
        $account_number = request()->post('account_number', '');
        $remark = request()->post('remark', '');
        $bank_name = request()->post('bank_name', '');
        $bank_type = request()->post('bank_type', '');
        $bank_card = request()->post('bank_card', '');
        if(!$type || !$real_name || !$account_number){
            return json(AjaxReturn(-1006));
        }
        if(($type == 1 || $type == 4) && (!$bank_card || !$bank_name || !$bank_type)){
            return json(AjaxReturn(-1006));
        }
        $shop = new Shop();
        $retval = $shop->addShopBankAccount($this->instance_id, $type, $real_name, $account_number, $remark, $bank_name, $bank_type, $bank_card);
        return json(ajaxReturn($retval));
    }

    /**
     * 修改银行账户
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function updateShopAccount()
    {
        $shop = new Shop();
        $id = request()->post('id', 0);
        $type = request()->post('type', '');
        $real_name = request()->post('real_name', '');
        $account_number = request()->post('account_number', '');
        $remark = request()->post('remark', '');
        $bank_name = request()->post('bank_name', '');
        $bank_type = request()->post('bank_type', '');
        $bank_card = request()->post('bank_card', '');
        if(!$id || !$type || !$real_name || !$account_number){
            return json(AjaxReturn(-1006));
        }
        if(($type == 1 || $type == 4) && (!$bank_card || !$bank_name || !$bank_type)){
            return json(AjaxReturn(-1006));
        }
        $retval = $shop->updateShopBankAccount($this->instance_id, $type, $real_name, $account_number, $remark, $bank_name, $bank_type, $bank_card, $id);
        return json(ajaxReturn($retval));
    }

    /*
     * 获取账号信息
     */

    public function getShopAccount()
    {
        $shop = new Shop();
        $id = request()->post('id', 0);
        if(!$id){
            return json(AjaxReturn(-1006));
        }
        $info = $shop->getShopBankAccountDetail($this->instance_id, $id);
        if($info){
            unset($info['create_date'],$info['modify_date'],$info['is_default'],$info['bank_prop'],$info['bank_username']);
            $info['real_name'] = $info['realname'];
            unset($info['realname']);
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $info]);
    }

    /*
     * 提现信息
     */

    public function shopAccountWithdraw()
    {
        if(!$this->instance_id){
            return json(AjaxReturn(LOGIN_EXPIRE));
        }
        $shopSer = new Shop();
        $shop_account_info = $shopSer->getShopAccount($this->instance_id);
        $Config = new WebConfig();
        $witndraw_type = $Config->getConfig(0, 'WITHDRAW_BALANCE');
        $data = [];
        $shop = new VslShopModel();
        $rate = $shop->getInfo(['shop_id' => $this->instance_id], 'shop_platform_commission_rate')['shop_platform_commission_rate'];
        $data['shop_rate'] = $rate;
        $data['is_use'] = $witndraw_type['is_use'];
        $data['poundage'] = $witndraw_type['value']['withdraw_poundage'];
        $data['withdraw_cash_min'] = $witndraw_type['value']['withdraw_cash_min'];
        $data['shop_total_money'] = $shop_account_info['shop_total_money'];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
 
    /**
     * 店铺收入
     */
    public function shopAccountBiz()
    {
        if(!$this->instance_id){
            return json(AjaxReturn(LOGIN_EXPIRE));
        }
        $shop = new Shop();
        // 得到店铺的账户情况
        $shop_account_info = $shop->getShopAccount($this->instance_id);
        $data = [
            'shop_profit' => $shop_account_info['shop_profit'],//营业额
            'shop_total_money' => $shop_account_info['shop_total_money'],//可提现
            'shop_entry_money' => $shop_account_info['shop_entry_money'],//待结算
            'shop_withdraw' => $shop_account_info['shop_withdraw'],//已提现
            'shop_platform_commission' => $shop_account_info['shop_platform_commission'],//平台抽成
        ];
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
    /*
     * 账户相关配置信息
     */
    public function getAccountConfig(){
        $config_service = new WebConfig();
        $list = $config_service->getConfig(0, 'WITHDRAW_BALANCE');
        $data = [
            'manual_bank' => 0,
            'wx_type' => 0,
            'ali_type' => 0,
            'auto_bank' => 0,
        ];
        if($list['value']['withdraw_message']){
            $withdraw_message = explode(',',$list['value']['withdraw_message']);
            if(in_array(1,$withdraw_message)){
                $data['auto_bank'] = 1;
            }
            if(in_array(2,$withdraw_message)){
                $data['wx_type'] = 1;
            }
            if(in_array(3,$withdraw_message)){
                $data['ali_type'] = 1;
            }
            if(in_array(4,$withdraw_message)){
                $data['manual_bank'] = 1;
            }
        }
        $bank = new VslBankModel();
        $data['bank_list'] = $bank->getQuery([],'id,bank_code,bank_name,bank_short_name','');
        return json(['code' => 1, 'message' => '获取成功', 'data' => $data]);
    }
    
    /*
     * 微信类型,选择关联会员
     */
    public function selectMemberList() {
        $page_index = (int)request()->post('page_index', 1);
        $page_size = (int)request()->post('page_size', PAGESIZE);
        $search_text = trim(request()->post('search_text', ''));
        if ($search_text) {
            if(is_numeric($search_text)){
                $condition['user_tel'] = $search_text;
            }else{
                $condition['real_name'] = $search_text;
            }
        }
        $condition['website_id'] = $this->website_id;
        $condition['is_member'] = 1;
        $condition['wx_openid'] = ['neq',''];
        $member = new Member();
        $list = $member->getUserLists($page_index,$page_size, $condition, '');
        $list['member_list'] = [];
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $list['member_list'][$key]['nick_name'] = $val['nick_name'];
                $list['member_list'][$key]['real_name'] = $val['real_name'];
                $list['member_list'][$key]['wx_openid'] = $val['wx_openid'];
            }
            unset($val);
        }
        unset($list['data']);
        return json(['code' => 1, 'message' => '获取成功', 'data' => $list]);
    }

}
