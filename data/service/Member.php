<?php

namespace data\service;

/**
 * 前台会员服务层
 */
use addons\abroadreceivegoods\model\VslCountryListModel;
use addons\abroadreceivegoods\model\VslCountryShopRelationModel;
use addons\areabonus\service\AreaBonus;
use addons\bonus\model\VslAgentLevelModel;
use addons\bonus\model\VslBonusAccountModel;
use addons\bonus\model\VslAgentAccountRecordsModel;
use addons\globalbonus\service\GlobalBonus;
use addons\poster\model\PosterModel;
use addons\poster\model\PosterRecordModel;
use addons\teambonus\service\TeamBonus;
use addons\channel\server\Channel as ChannelServer;
use addons\microshop\service\MicroShop as MicroShopService;
use data\model\AlbumPictureModel;
use data\model\MsgReminderModel;
use data\model\VslBankModel;
use data\model\VslGoodsDeletedModel;
use data\model\VslMemberAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberAccountRecordsViewModel;
use data\model\VslMemberBalanceWithdrawModel;
use data\model\VslMemberEsignModel;
use data\model\VslMemberExpressAddressModel;
use data\model\VslMemberFavoritesModel;
use data\model\VslMemberGroupModel;
use data\model\VslMemberLevelModel;
use data\model\VslMemberModel as VslMemberModel;
use data\model\VslMemberRechargeModel;
use data\model\VslMemberRefereeLogModel;
use data\model\VslOrderModel;
use addons\shop\model\VslShopApplyModel;
use addons\shop\model\VslShopModel;
use data\model\UserModel as UserModel;
use data\model\WebSiteModel;
use data\service\eqb\EsignService;
use data\service\Member\MemberAccount;
use data\service\User as User;
use data\model\VslMemberBankAccountModel;
use data\model\VslMemberViewModel;
use data\model\VslPointConfigModel;
use think\Cookie;
use think\Session;
use think\Log;
use data\service\Order\OrderAccount;
use data\service\AddonsConfig as AddonsConfigService;
use data\model\VslNvRecordModel;
use addons\customform\server\Custom as CustomServer;
use data\model\VslOrderGoodsModel;
use data\model\VslGoodsSkuModel;
use data\model\VslGoodsSpecValueModel;
use data\service\Order\OrderStatus;
use data\model\VslMemberPrizeModel;
use addons\coupontype\server\Coupon as CouponServer;
use addons\giftvoucher\server\GiftVoucher as VoucherServer;
use addons\gift\server\Gift as GiftServer;
use data\service\Order\Order as OrderBusiness;
use addons\distribution\service\Distributor;
use addons\store\server\Store;
use data\model\VslGoodsTicketModel;
use data\service\Order as ServiceOrder;
use addons\paygift\model\VslPayGiftModel;
use data\model\VslAccountRecordsModel;
use addons\bonus\model\VslOrderBonusLogModel;
use think\Db;

class Member extends User
{

    private $upload_type = 1;
    function __construct()
    {
        parent::__construct();
    }

    /*
     * 前台添加会员(non-PHPdoc)
     * @see \data\api\IMember::registerMember()
     * nickname 新用户注册推送上级所需的会员昵称 edit@2020/04/21
     */
    public function registerMember($extend_code,$user_name, $password, $email, $mobile, $user_qq_id, $qq_info, $wx_openid, $wx_info, $wx_unionid,$mp_open_id = '', $pcwx_open_id = '',
        $app_wx_openid = '', $user_from = 0, $mall_port = 0,$country_code = '',$nickname = '',$headimgurl='', $sex='')
    {
        try{
            $this->website_id = $this->website_id ?: (Session::get('shopwebsite_id') ? : checkUrl());
            //$user_from
            $params = [
                'user_name'             => $user_name,
                'password'              => $password,
                'email'                 => $email,
                'mobile'                => $mobile,
                'is_system'             => 0,
                'is_member'             => 1,
                'qq_openid'             => $user_qq_id,
                'qq_info'               => $qq_info,
                'wx_openid'             => $wx_openid,
                'wx_info'               => $wx_info,
                'wx_unionid'            => $wx_unionid,
                'instance_id'           => 0,
                'website_id'            => $this->website_id,
                'mp_open_id'            => $mp_open_id,
                'pcwx_open_id'          => $pcwx_open_id,
                'app_wx_openid'         => $app_wx_openid,
                'mall_port'             => $mall_port,
                'country_code'          => $country_code,
                'nickname'              => $nickname,
                'user_headimg'          => $headimgurl,
                'sex'                   => $sex,
            ];
            $res = parent::add($params);//0-无来源 联合登录 (1-公众号 2-小程序 3-移动H5  4-PC  5-APP)
            if ($res > 0) {
                // 获取默认会员等级id
                $member_level = new VslMemberLevelModel();
                $level_info = $member_level->getInfo([
                    'is_default' => 1,
                    'website_id' => $this->website_id,
                ], 'level_id');
                $member_level_id = $level_info['level_id'];
                $member = new VslMemberModel();
                $referee_id = $member->getInfo(['extend_code'=>$extend_code,'website_id' => $this->website_id],'uid')['uid'];

                if(!$referee_id || !$extend_code){
                    //不存在推荐人
                    $data = array(
                        'uid' => $res,
                        'referee_id' => 0,
                        'member_level' => $member_level_id,
                        'mobile' => $mobile,
                        'reg_time' => time(),
                        'website_id' => $this->website_id
                    );
                }else{
                    $data = array(
                        'uid' => $res,
                        'member_level' => $member_level_id,
                        'mobile' => $mobile,
                        'reg_time' => time(),
                        'website_id' => $this->website_id
                    );
                }

                $member->save($data);
                // 查看是否是海报/任务的推荐场景
                // 添加会员账户
                $member_account = new VslMemberAccountModel();
                $data1 = array(
                    'uid' => $res,
                    'website_id' => $this->website_id
                );
                $member_account->save($data1);
                $this->subEvent($res, $wx_openid, $this->website_id);
                $distributionStatus = getAddons('distribution', $this->website_id);
                if($distributionStatus == 1 && $referee_id && $res){
                    $this->updateMemberInfo($referee_id,$res,$nickname);
                }
                //注册成功后短信与邮箱提醒
                $params['shop_id'] = $this->instance_id;
                $params['user_id'] = $res;
                $params['website_id'] = $this->website_id;
                $params['notify_type'] = 'user';
                $params['template_code'] = 'after_register';
                runhook('Notify', 'registAfterBySms', $params);
                runhook('Notify', 'emailSend', $params);
                // 直接登录
                if (!empty($user_name) && !Session::has('oa_login_type')) {
                    $this->login($user_name, $password);
                } elseif (!empty($mobile)) {
                    $this->login($mobile, $password, '', '', $mall_port);
                } elseif (!empty($email)) {
                    $this->login($email, $password);
                } elseif (!empty($user_qq_id)) {
                    $this->qqLoginNew($user_qq_id, $this->website_id);
                } elseif (!empty($wx_unionid)) {
//                $this->wchatUnionLogin($wx_unionid);
                } elseif (!empty($wx_openid)) {
//                $this->wchatLogin($wx_openid);
                } elseif (!empty($mp_open_id)){
//                $this->loginNew(['mp_open_id' => $mp_open_id, 'website_id' => $this->website_id]);
                }
            }
            return $res;
        }catch(\Exception $e){
            return $e->getMessage();
        }
        // TODO Auto-generated method stub
    }
    public function bUserInfo($type,$uid,$password,$mobile,$info,$unionid,$pcwx_open_id,$country_code=''){
        $user = new UserModel();
        $userService = new User();
        $user_headimg = $user->getInfo(['uid' => $uid],'user_headimg')['user_headimg'];
        if($type==1){
            $wx_info_array = json_decode($info);
            $nick_name = $this->filterStr($wx_info_array->nickname);
            $wx_openid = $wx_info_array->openid;
            $user_head_img = $wx_info_array->headimgurl;
            $wx_info = $userService->filterStr($info);
            if($user_headimg==''){
                $result = $user->save([
                    'user_tel' => $mobile,
                    'user_headimg'=>$user_head_img,
                    'nick_name'=>$nick_name,
                    'wx_info' => $wx_info,
                    'user_tel_bind'=>1,
                    'wx_unionid' => $unionid,
                    "current_login_time" => time(),
                    "pcwx_open_id" => $pcwx_open_id,
                    "country_code" => $country_code?:86,
                ], [
                    'uid' => $uid
                ]);
            }else{
                $result = $user->save([
                    'user_tel' => $mobile,
                    'nick_name'=>$nick_name,
                    'wx_info' => $wx_info,
                    'user_tel_bind'=>1,
                    'wx_unionid' => $unionid,
                    "current_login_time" => time(),
                    "pcwx_open_id" => $pcwx_open_id,
                    "country_code" => $country_code?:86,
                ], [
                    'uid' => $uid
                ]);
            }
            if($result==1){
                $user_info = $user->getInfo(['uid'=>$uid],'*');
                $member = new VslMemberModel();
                $member->save(['mobile'=>$mobile],['uid'=>$uid]);
                $userService->initLoginInfo($user_info);
            }
            return $result;
        }
        if($type==2){
            $qq_info_array = json_decode($info);
            $nick_name = $userService->filterStr($qq_info_array->nickname);
            $user_head_img = $qq_info_array->figureurl_qq_2;
            $qq_info = $userService->filterStr($info);
            if($user_headimg==''){
                $result = $user->save([
                    'user_tel' => $mobile,
                    'nick_name'=>$nick_name,
                    'user_headimg'=>$user_head_img,
                    'qq_info' => $qq_info,
                    'user_tel_bind'=>1,
                    'qq_openid' => $unionid,
                    "current_login_time" => time(),
                    "country_code" => $country_code?:86,
                ], [
                    'uid' => $uid
                ]);
            }else{
                $result = $user->save([
                    'user_tel' => $mobile,
                    'nick_name'=>$nick_name,
                    'qq_info' => $qq_info,
                    'user_tel_bind'=>1,
                    'qq_openid' => $unionid,
                    "current_login_time" => time(),
                    "country_code" => $country_code?:86,
                ], [
                    'uid' => $uid
                ]);
            }
            if($result==1){
                $member = new VslMemberModel();
                $member->save(['mobile'=>$mobile],['uid'=>$uid]);
                $user_info = $user->getInfo(['uid'=>$uid],'*');
                $userService->initLoginInfo($user_info);
            }
            return $result;
        }
    }

    /**
     * 通过用户id绑定用户的手机号
     */
    public function setMobile($id,$mobile)
    {
        $user = new UserModel();
        $member = new VslMemberModel();
        $res = $user->save([
            'user_tel' => $mobile,
            "user_tel_bind" => 1
        ], [
            'uid' => $id
        ]);
        if($res){
            $result = $member->save([
                'mobile' => $mobile
            ], [
                'uid' => $id
            ]);
        }
        return $result;
    }
    /**
     * 通过用户id绑定用户的手机号
     */
    public function setEmail($id,$email)
    {
        $user = new UserModel();
        $result = $user->save([
            'user_email' => $email,
            "user_email_bind" => 1
        ], [
            'uid' => $id
        ]);
        return $result;
    }

    /**
     * 删除用户信息
     * @param $uid int [需要删除的用户id]
     * @param int $bind_uid int [需要查询绑定referee_id的用户id]
     * @return int|string
     */
    public function deleteMember($uid, $bind_uid = 0)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $member = new VslMemberModel();
        $user->startTrans();
        try {
            // 判断是否有referee_id需要更新再删除 by sgw
            if ($bind_uid) {
                $referee_id = $member->getInfo(['website_id' => $this->website_id, 'uid' => $uid], 'referee_id')['referee_id'];
                $member->save(['referee_id' => $referee_id], ['website_id' => $this->website_id, 'uid' => $bind_uid]);
            }

            // 删除user信息
            $user->destroy($uid);
            // 删除member信息
            $retval = $member->destroy($uid);
            $member_account = new VslMemberAccountModel();
            // 删除会员账户信息
            $member_account->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员账户记录信息
            $member_account_records = new VslMemberAccountRecordsModel();
            $member_account_records->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员取现记录表
            $member_balance_withdraw = new VslMemberBalanceWithdrawModel();
            $member_balance_withdraw->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员银行账户表
            $member_bank_account = new VslMemberBankAccountModel();
            $member_bank_account->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            // 删除会员地址表
            $member_express_address = new VslMemberExpressAddressModel();
            $member_express_address->destroy([
                'uid' => array(
                    'in',
                    $uid
                )
            ]);
            //删除平台流水
            $accountRecordModel = new VslAccountRecordsModel();
            $accountRecordModel->destroy([
                'user_id' => array(
                    'in',
                    $uid
                )
            ]);
            $user->commit();
            return 1;
        } catch (\Exception $e) {
            $user->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 会员列表
     *
     * @param number $page_index
     * @param number $page_size
     * @param string $condition
     * @param string $order
     * @param string $field
     */
    public function getMemberList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $member_view = new VslMemberViewModel();
        $result = $member_view->getViewList($page_index, $page_size, $condition, $order);
        $member_account = new MemberAccount();
        $group_name = new VslMemberGroupModel();
        $country_code_model = new \data\model\SysCountryCodeModel();
        $order_account = new OrderAccount();
        foreach ($result['data'] as $k => $v) {
            $group_names= '';

            if($v['group_id']){
                $group_id = explode(',',$v['group_id']);
                foreach ($group_id as $value){
                    $group_names .=  $group_name->getInfo(['group_id'=>$value],'group_name')['group_name'].'  ';
                }
                unset($value);
            }
            $country_code = $country_code_model->getInfo(['country_code' => $v['country_code']],'country,country_code_long');
            $re_country_code = $country_code_model->getInfo(['country_code' => $v['refree_country_code']],'country,country_code_long');
            $result['data'][$k]['group_name'] =  $group_names;
            $result['data'][$k]['point'] = $member_account->getMemberPoint($v['uid']);
            $result['data'][$k]['beautiful_point'] = $member_account->getMemberBeautifulPoint($v['uid']);
            $result['data'][$k]['balance'] = $member_account->getMemberBalance($v['uid']);

            $result['data'][$k]['order_num'] = $order_account->getShopSaleNumSum(['buyer_id' => $v['uid'], 'order_status' => [['>', '0'], ['<', '5']]]);
            $result['data'][$k]['order_money'] = $order_account->getMemberSaleMoney(['buyer_id' => $v['uid'], 'order_status' => [['>', '0'], ['<', '5']]]);
            $result['data'][$k]['reg_time'] = date('Y-m-d H:i:s', $v['reg_time']);
            $result['data'][$k]['country'] = $country_code['country'];
            $result['data'][$k]['country_code_long'] = $country_code['country_code_long'];
            $result['data'][$k]['refree_country_code_long'] = $re_country_code['country_code_long'];
            $result['data'][$k]['refree_country'] = $re_country_code['country'];
        }
        unset($v);
        return $result;
    }

    /**
     * 获取积分列表
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return multitype:number unknown
     */
    public function getPointList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new VslMemberAccountRecordsViewModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if($v['account_type'] == 3){
                    $list['data'][$k]['number'] = $v['number'] * $v['sign'];
                }
                $list['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            }
            unset($v);
        }
        return $list;
    }

    /**
     * 后台余额流水
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return multitype:number unknown
     */
    public function getAccountList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $configSer = new Config();
        $config_style = $configSer->getConfig(0, 'COPYSTYLE', $this->website_id, 1);
        if($config_style){
            $balance_style = $config_style['balance_style'];
            $point_style = $config_style['point_style'];
        }else{
            $balance_style = '余额';
            $point_style = '积分';
        }
        $member_account = new VslMemberAccountRecordsViewModel();
        $member_balance = new VslMemberBalanceWithdrawModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $v['nick_name'];
                }
                if($v['from_type']==8){
                    $withdraw_info = $member_balance->getInfo(['withdraw_no' => $v['data_id']],'type');
                    if(empty($withdraw_info)){
                        $withdraw_info = $member_balance->getInfo(['id' => $v['data_id']],'type');
                    }
                    $payment_type = $withdraw_info['type'];
                    switch($payment_type){
                        case '1':
                            $pay_name = '银行卡';
                            break;
                        case '2':
                            $pay_name = '微信';
                            break;
                        case '3':
                            $pay_name = '支付宝';
                            break;
                        case '4':
                            $pay_name = '银行卡';
                            break;
                    }
                    if($v['status']==3){
                        $status_name = $pay_name.'提现（成功）';
                    }elseif( $v['status']==5){
                        $status_name = $pay_name.'提现（重新打款）';
                    } elseif($v['status']==4 ){
                        $status_name = $pay_name.'提现（拒绝打款）';
                    }elseif($v['status']==-1){
                        $status_name = $pay_name.'提现（审核失败）';
                    }elseif($v['status']==1){
                        $status_name = $pay_name.'提现（待审核）';
                    }elseif($v['status']==2){
                        $status_name = $pay_name.'提现（待打款）';
                    }
                    $list['data'][$k]['type_name'] = $status_name;
                }else{
                    $list['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                }
                $list['data'][$k]['text'] = str_replace("余额",$balance_style,$list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("积分",$point_style,$list['data'][$k]['text']);
                $list['data'][$k]['type_name'] = str_replace("余额",$balance_style,$list['data'][$k]['type_name']);
                $list['data'][$k]['type_name'] = str_replace("积分",$point_style,$list['data'][$k]['type_name']);
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            }
            unset($v);
        }
        return $list;
    }
    /**
     * 前台接口余额流水
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return multitype:number unknown
     */
    public function getAccountLists($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $configSer = new Config();
        $config_style = $configSer->getConfig(0, 'COPYSTYLE', $this->website_id, 1);
        if($config_style){
            $balance_style = $config_style['balance_style'];
            $point_style = $config_style['point_style'];
        }else{
            $balance_style = '余额';
            $point_style = '积分';
        }
        $member_account = new VslMemberAccountRecordsViewModel();
        $member_balance = new VslMemberBalanceWithdrawModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $v['nick_name'];
                }
                if($v['from_type']==8){
                    $withdraw_info = $member_balance->getInfo(['withdraw_no' => $v['data_id']],'type,service_charge');
                    if(empty($withdraw_info)){
                        $withdraw_info = $member_balance->getInfo(['id' => $v['data_id']],'type,service_charge');
                    }
                    $payment_type = $withdraw_info['type'];
                    switch($payment_type){
                        case '1':
                            $pay_name = '银行卡';
                            break;
                        case '2':
                            $pay_name = '微信';
                            break;
                        case '3':
                            $pay_name = '支付宝';
                            break;
                        case '4':
                            $pay_name = '银行卡';
                            break;
                    }
                    if($v['status']==3){
                        $status_name = $pay_name.'提现（成功）';
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    }elseif( $v['status']==5){
                        $status_name = $pay_name.'提现（处理中）';
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    } elseif($v['status']==4 ){
                        $status_name = $pay_name.'提现（失败）';
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    }elseif($v['status']==-1){
                        $status_name = $pay_name.'提现（失败）';
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    }elseif($v['status']==1){
                        $status_name = $pay_name.'提现（处理中）';
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    }elseif($v['status']==2){
                        $status_name = $pay_name.'提现（处理中）';
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    }
                    $list['data'][$k]['charge'] = (-1)*abs($v['charge']);
                    $list['data'][$k]['change_money'] = (-1)*(abs($v['charge'])+abs($v['number']));
                    $list['data'][$k]['type_name'] = $status_name;
                    if($v['balance'] == 0){ //全部提现 修正显示
                        $list['data'][$k]['change_money'] = (-1)*abs($v['number']);
                        $list['data'][$k]['number'] = (-1)*abs($withdraw_info['service_charge']);
                    }
                }else{
                    if($v['number']>0){
                        $list['data'][$k]['number'] = '+'.abs($v['number']);
                    }else{
                        $list['data'][$k]['number'] = (-1)*abs($v['number']);
                    }
                    $list['data'][$k]['change_money'] = $list['data'][$k]['number'];
                    $list['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                }
                $list['data'][$k]['text'] = str_replace("余额",$balance_style,$list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("积分",$point_style,$list['data'][$k]['text']);
                $list['data'][$k]['type_name'] = str_replace("余额",$balance_style,$list['data'][$k]['type_name']);
                $list['data'][$k]['type_name'] = str_replace("积分",$point_style,$list['data'][$k]['type_name']);
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            }
            unset($v);
        }
        return $list;
    }
    /**
     * 获取分红流水详情
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return multitype:number unknown
     */
    public function getBonusRecordList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new VslAgentAccountRecordsModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                if( empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                if($list['data'][$k]['bonus_type']==1){
                    $list['data'][$k]['bonus_type']='全球分红';
                }
                if($list['data'][$k]['bonus_type']==2){
                    $list['data'][$k]['bonus_type']='区域分红';
                }
                if($list['data'][$k]['bonus_type']==3){
                    $list['data'][$k]['bonus_type']='团队分红';
                }
                if($list['data'][$k]['from_type']==3){
                    $list['data'][$k]['from_type']='订单支付';
                }
                if($list['data'][$k]['from_type']==1){
                    $list['data'][$k]['from_type']='订单完成';
                }
                if($list['data'][$k]['from_type']==2){
                    $list['data'][$k]['from_type']='订单退款';
                }
                if($list['data'][$k]['from_type']==4){
                    $list['data'][$k]['from_type']='分红发放';
                }
                $list['data'][$k]['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            }
            unset($v);
        }
        return $list;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getDefaultExpressAddress()
     */
    public function getDefaultExpressAddress($uid = 0)
    {
        $uid = !empty($uid)?$uid:$this->uid;
        $express_address = new VslMemberExpressAddressModel();
        $data = $express_address->getInfo([
            'uid' => $uid,
            'is_default' => 1
        ], '*');
        // 处理地址信息
        if (!empty($data)) {
            $address = new Address();
            $address_info = $address->getAddress($data['province'], $data['city'], $data['district']);
            $data['address_info'] = $address_info;
        }

        return $data;
    }

    /**
     * 获取一条收货地址数据
     * @param array $condition
     * @param array $with
     * @return mixed
     */
    public function getMemberExpressAddress(array $condition, array $with)
    {
        $express_address = new VslMemberExpressAddressModel();
        $address_info = $express_address::get($condition, $with);
        if(!empty($address_info['type'])) {
            //国外地址
            $country_list_model = new VslCountryListModel();
            $country_info = $country_list_model->getInfo(['id' => $address_info['country_id']]);
            $address_info['chinese_country_name'] = $country_info['chinese_country_name'];
            $address_info['english_country_name'] = $country_info['english_country_name'];
        }

        return $address_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberInfo()
     */
    public function getMemberInfo($field='*')
    {
        $member = new VslMemberModel();
        if (!empty($this->uid)) {
            $data = $member->getInfo([
                'uid' => $this->uid,
                'website_id' => $this->website_id
            ], $field);
        } else {
            $data = '';
        }
        return $data;
    }
    /**
     * (non-PHPdoc)
     * 查询当前邀请码是否存在
     */
    public function getUidInfo($extend_code,$website_id){
        $member = new VslMemberModel();
        $uid = $member->getInfo(['extend_code'=>$extend_code,'website_id'=>$website_id],'uid')['uid'];
        return $uid;
    }
    /**
     * (non-PHPdoc)
     * 查询当前用户是否是分销商
     */
    public function getDistributionInfo(){
        $status = getAddons('distribution',$this->website_id);
        if($status==1){
            $config = new AddonsConfigService();
            $distribution_info = $config->getAddonsConfig( 'distribution', $this->website_id);
            $info = $distribution_info['value'];
            $info['is_use'] = $distribution_info['is_use'];
            if($info['is_use']){
                $member = new VslMemberModel();
                $member_info = $member->getInfo(['uid'=>$this->uid],'isdistributor,referee_id');
                $info['isdistributor'] = $member_info['isdistributor'];
                $info['referee_id'] = $member_info['referee_id'];
                return $info;
            }
        }
    }
    /**
     * (non-PHPdoc)
     * 添加推荐人
     */
    public function updateMemberInfo($referee_id,$retval=0,$nickname=''){
        if($retval){
            $uid = $retval;
        }else{
            $uid = $this->uid;
        }

        if($uid && $referee_id!=$uid) {
            $member = new VslMemberModel();
            $lower_id = $member->Query(['referee_id'=>$uid],'*');
            if($lower_id && in_array($referee_id,$lower_id)){
                return 2;
            }
            $distributor = new Distributor();
            $list = $distributor->getDistributionSite($this->website_id);
            $distribution = $this->getDistributionInfo();
            $member_info =$member->getInfo(['uid'=>$uid],'referee_id,isdistributor,default_referee_id');
            if ($member_info['referee_id'] === null) {

                if ($distribution['lower_condition'] == 1 && ($member_info['isdistributor'] != 2 || $list['distributor_condition']==3) && $member_info['referee_id'] == null && $member_info['default_referee_id'] == null) {//首次分享链接
                    $data = array(
                        "referee_id" => $referee_id
                    );
                    $res = $member->save($data, ['uid' => $uid]);
                    runhook("Notify", "sendCustomMessage", ['messageType'=>'new_offline',"uid" => $uid,"add_time" => time(),'referee_id'=>$referee_id,'nickname'=>$nickname]);//成为下线通知
                    if($res){
                        $distribution = new Distributor();
                        $distribution->addRefereeLog($uid,$referee_id,$this->website_id,$this->instance_id, 0,0,2);
                        $distribution->updateDistributorLevelInfo($referee_id);
                        if(getAddons('globalbonus', $this->website_id)){
                            $global = new GlobalBonus();
                            $global->updateAgentLevelInfo($referee_id);
                        }
                        if(getAddons('areabonus', $this->website_id)){
                            $area = new AreaBonus();
                            $area->updateAgentLevelInfo($referee_id);
                        }
                        if(getAddons('teambonus', $this->website_id)){
                            $team = new TeamBonus();
                            $team->updateAgentLevelInfo($referee_id);
                        }
                    }
                } elseif ($distribution['lower_condition'] == 2 && ($member_info['isdistributor'] != 2 || $list['distributor_condition']==3)) {//必须购买商品才能成为下线先保存推荐人
                    $data = array(
                        "default_referee_id" => $referee_id
                    );
                    $res = $member->save($data, ['uid' => $uid]);
                }
                return $res;
            }else{
                return 2;
            }
        }else{
            return 3;
        }
    }
    /**
     *  增加扫描纪录
     */
    public function addScanRecords($uid, $referee_id, $poster_id, $poster_type)
    {
        $poster_record_mdl = new PosterRecordModel();
        $poster_mdl = new PosterModel();
        if($poster_type == 1){
            //先查询该用户是否扫描过该海报
            $condition['be_reco_uid'] = $uid;
            $condition['poster_id'] = $poster_id;
            $condition['website_id'] = $this->website_id;
                $is_scan_record = $poster_record_mdl->where($condition)->field('poster_record_id')->find();
            if(!$is_scan_record && $referee_id != $uid){
                //将该海报扫描次数+1
                $scan_times_obj = $poster_mdl->field('scan_times')->where(['poster_id' => $poster_id, 'website_id' => $this->website_id])->find();
                $scan_times_obj->scan_times = $scan_times_obj->scan_times + 1;
                $scan_times_obj->save();
                $poster_data['poster_type'] = 1;
                $poster_data['poster_id'] = $poster_id;
                $poster_data['reco_uid'] = $referee_id;
                $poster_data['be_reco_uid'] = $uid;
                $poster_data['scan_time'] = time();
                $poster_data['shop_id'] = $this->instance_id;
                $poster_data['website_id'] = $this->website_id;
                $poster_record_mdl->save($poster_data);
            }
        }else{
            //先查询该用户是否扫描过该海报
            $condition['be_reco_uid'] = $uid;
            $condition['poster_id'] = $poster_id;
            $condition['website_id'] = $this->website_id;
                $is_scan_record = $poster_record_mdl->where($condition)->field('poster_record_id')->find();
            if(!$is_scan_record && $referee_id != $uid){
                //将该海报扫描次数+1
                $scan_times_obj = $poster_mdl->field('scan_times')->where(['poster_id' => $poster_id, 'website_id' => $this->website_id])->find();
                $scan_times_obj->scan_times = $scan_times_obj->scan_times + 1;
                $scan_times_obj->save();
                $poster_data['poster_type'] = 2;
                $poster_data['poster_id'] = $poster_id;
                $poster_data['reco_uid'] = $referee_id;
                $poster_data['be_reco_uid'] = $uid;
                $poster_data['scan_time'] = time();
                $poster_data['shop_id'] = $this->instance_id;
                $poster_data['website_id'] = $this->website_id;
                $poster_record_mdl->save($poster_data);
            }
        }

    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberDetail()
     */
    public function getMemberDetail()
    {
        // 获取基础信息
        if (!empty($this->uid)) {
            $member_info = $this->getMemberInfo();
            if (empty($member_info)) {
                $member_info = array(
                    'level_id' => 0
                );
            }
            // 获取user信息
            $user_info = $this->getUserDetail();
            if (empty($user_info)){
                return '';
            }

            $member_info['user_info'] = $user_info;
            $member_info['user_info']['birthday'] = date('Y-m-d',$member_info['user_info']['birthday']);
            $member_info['uid'] = $user_info['uid'];
            $member_account = new MemberAccount();
            $member_info['point'] = $member_account->getMemberPoint($this->uid);
            $member_info['beautiful_point'] = $member_account->getMemberBeautifulPoint($this->uid);
            $member_info['balance'] = $member_account->getMemberBalance($this->uid);
            $member_info['coin'] = $member_account->getMemberCoin($this->uid);
            // 会员等级名称
            $member_level = new VslMemberLevelModel();
            $level_name = $member_level->getInfo([
                'level_id' => $member_info['member_level']
            ], 'level_name,is_label');
            $member_info['level_name'] = $level_name['level_name'];
            $member_info['member_discount_label'] = $level_name['is_label'];
        } else {
            $member_info = '';
        }

        return $member_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberInformation()
     */
    public function updateMemberInformation($user_name,$nick_name, $user_qq, $real_name, $sex, $birthday, $province_id,$city_id,$district_id,$user_headimg,$post_data)
    {
        $useruser = new UserModel();
        $data = array(
            // 修改为user_name 昵称
            "user_qq" => $user_qq,
            "real_name" => $real_name,
            "sex" => $sex,
            "birthday" => getTimeTurnTimeStamp($birthday),
            "province_id" => $province_id,
            "city_id" => $city_id,
            "district_id" => $district_id,
            "user_name" => $user_name,
            "nick_name" => $nick_name,
            "custom_person" => $post_data,
        );
        $data2 = array(
            "user_headimg" => $user_headimg
        );
        $member = new VslMemberModel();
        $data3 = array(
            "member_name" => $user_name,
            "real_name" => $real_name,
        );
        $member->save($data3, ['uid' => $this->uid]);
        if ($user_headimg == "") {
            $result = $useruser->save($data, [
                'uid' => $this->uid
            ]);
        } else {
            $result = $useruser->save($data2, [
                'uid' => $this->uid
            ]);
        }
        return $result;
    }
    public function updateMemberHeading($user_headimg)
    {
        //获取图片base64字符串
        $new_file = changeFile($user_headimg, $this->website_id, 'avator');
        $user = new UserModel();
        $data = array(
            // 修改为user_name 头像
            "user_headimg" => $new_file,
        );
        $result = $user->save($data, [
            'uid' => $this->uid
        ]);
        if($result){
            $image = $user->getInfo(['uid' => $this->uid],'user_headimg')['user_headimg'];
            return $image;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberExpressAddress()
     */
    public function addMemberExpressAddress($consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias,$is_default)
    {
        $express_address = new VslMemberExpressAddressModel();
        if($is_default==1){
            $express_address->save([
                'is_default' => 0
            ], [
                'uid' => $this->uid
            ]);
        }

        $data = array(
            'uid' => $this->uid,
            'consigner' => $consigner,
            'mobile' => $mobile,
            'phone' => $phone,
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'address' => $address,
            'zip_code' => $zip_code,
            'alias' => $alias,
            'is_default' => $is_default,
            'website_id' => $this->website_id
        );
        $express_address->save($data);
        $this->updateAddressDefault($express_address->id);
        return $express_address->id;
    }

    public function addMemberExpressAddressNew($data)
    {
        $express_address_model = new VslMemberExpressAddressModel();
        if($data['is_default']==1 && $data['uid']){
            $express_address_model->save([
                'is_default' => 0
            ], [
                'uid' => $data['uid']
            ]);
        }
        $id = $express_address_model->isUpdate(false)->save($data);

        return $id;
    }

    /**
     * 修改会员收货地址
     */
    public function updateMemberExpressAddress($id, $consigner, $mobile, $phone, $province, $city, $district, $address, $zip_code, $alias,$is_default)
    {
        $express_address = new VslMemberExpressAddressModel();
        $data = array(
            'uid' => $this->uid,
            'consigner' => $consigner,
            'mobile' => $mobile,
            'phone' => $phone,
            'province' => $province,
            'city' => $city,
            'district' => $district,
            'address' => $address,
            'zip_code' => $zip_code,
            'alias' => $alias,
            'is_default'=>$is_default
        );
        $retval = $express_address->save($data, [
            'id' => $id
        ]);
        if($is_default==1){
            $retval = $this->updateAddressDefault($id);
        }
        return $retval;
    }

    public function updateMemberExpressAddressNew($data, $id)
    {
        $express_address_model = new VslMemberExpressAddressModel();
        $retval = $express_address_model->save($data, [
            'id' => $id
        ]);
        if ($data['is_default'] == 1) {
            $retval = $this->updateAddressDefault($id);
        }

        return  $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberExpressAddressList()
     */
    public function getMemberExpressAddressList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $express_address = new VslMemberExpressAddressModel();
        $data = $express_address->pageQuery($page_index, $page_size, [
            'uid' => $this->uid
        ], 'id desc', '*');
        // 处理地址信息
        if (!empty($data)) {
            $address = new Address();
            if(getAddons('abroadreceivegoods',$this->website_id)){
				$country_list_model = new VslCountryListModel();
			}
            foreach ($data['data'] as $key => $val) {
                if(empty($val['type'])) {
                    $address_info = $address->getAddress($val['province'], $val['city'], $val['district']);
                    $data['data'][$key]['address_info'] = $address_info;
                    $data['data'][$key]['country_info'] = [];
                }else{
                    $data['data'][$key]['country_info'] = $country_list_model->getInfo(['id' => $val['country_id']]);
                    $data['data'][$key]['address_info'] = '';
                }
            }
            unset($val);
        }
        return $data;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberExpressAddressDetail()
     */
    public function getMemberExpressAddressDetail($id, $uid=0)
    {
        if(empty($uid)){
            $uid = $this->uid;
        }
        $express_address = new VslMemberExpressAddressModel();
        $data = $express_address->get($id);
        if ($data['uid'] == $uid) {
            if(empty($data['type'])) {
                $address = new Address();
                $address_info = $address->getAddress($data['province'], $data['city'], $data['district']);
                $data['address_info'] = $address_info;
                $data['country_info'] = [];
            }else{
                $country_list_model = new VslCountryListModel();
                $data['country_info'] = $country_list_model->getInfo(['id' => $data['country_id']]);
                $data['address_info'] = '';
            }

            return $data;
        } else {
            return '';
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberAddressDelete()
     */
    public function memberAddressDelete($id)
    {
        $express_address = new VslMemberExpressAddressModel();
        $count = $express_address->where(array(
            "uid" => $this->uid
        ))->count();
        if ($count == 1) {
            return USER_ADDRESS_DELETE_ERROR;
        } else {
            $express_address_info = $express_address->getInfo([
                'id' => $id,
                'uid' => $this->uid
            ]);

            $res = $express_address->destroy($id);

            if ($express_address_info['is_default'] == 1) {
                $express_address_info = $express_address->where(array(
                    "uid" => $this->uid
                ))
                    ->order("id desc")
                    ->limit(0, 1)
                    ->select();
                $res = $this->updateAddressDefault($express_address_info[0]['id']);
            }

            return $res;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateAddressDefault()
     */
    public function updateAddressDefault($id)
    {
        $express_address = new VslMemberExpressAddressModel();
        $res = $express_address->save([
            'is_default' => 0
        ], [
            'uid' => $this->uid,
            'is_default' => 1
        ]);
        $res = $express_address->save([
            'is_default' => 1
        ], [
            'id' => $id
        ]);
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberPointCount()
     */
    public function getMemberPointCount()
    {
        $member_account = new VslMemberAccountModel();
        $condition = array(
            'website_id' => $this->website_id
        );
        $point = $member_account->where($condition)->sum('point');
        if ($point) {
            return $point;
        } else {
            return 0;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberPointCount()
     */
    public function getMemberBalanceCount($condition)
    {
        $member_account = new VslMemberAccountModel();
        if (empty($condition)) {
            $condition = array(
                'website_id' => $this->website_id
            );
        }
        $balance = $member_account->where($condition)->sum('balance');
        if ($balance) {
            return $balance;
        } else {
            return 0;
        }

    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getShopNameByShopId()
     */
    public function getShopNameByShopId($shop_id)
    {
        $member_account = new VslShopModel();
        $condition = array(
            "shop_id" => array(
                "EQ",
                $shop_id
            )
        );
        return $member_account->getInfo($condition, "shop_name")['shop_name'];
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberFavorites()
     */
    public function getMemberGoodsFavoritesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $fav = new VslMemberFavoritesModel();
        $list = $fav->getGoodsFavouitesViewList($page_index, $page_size, $condition, $order);
        $goodsSer = new Goods();
        $goods_del = new VslGoodsDeletedModel();
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $list['data'][$key]['del_status'] = 0;
                $goods = $goodsSer->getGoodsDetailById($val['fav_id']);
                $list['data'][$key]['status'] = $goods['goods_id']?1:0;
                $del_status = $goods_del->getInfo(['goods_id'=>$val['fav_id']],'goods_id');
                if($del_status){
                    $list['data'][$key]['del_status'] = 1;
                }
            }
            unset($val);
        }
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberFavorites()
     */
    public function getMemberShopsFavoritesList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $fav = new VslMemberFavoritesModel();
        $list = $fav->getShopsFavouitesViewList($page_index, $page_size, $condition, $order);
        return $list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberFavouites()
     */
    public function addMemberFavouites($fav_id, $fav_type, $log_msg, $seckill_id = 0)
    {
        $member_favorites = new VslMemberFavoritesModel();
        $count = $member_favorites->where(array(
            "fav_id" => $fav_id,
            "uid" => $this->uid,
            "fav_type" => $fav_type,
            "website_id" => $this->website_id,
        ))->count("log_id");
        // 检查数据表中，防止用户重复收藏
        if ($count > 0) {
            if($fav_type == 'goods' && $seckill_id){//是秒杀商品，则更新秒杀id
                $retval = $member_favorites->where(
                    ["fav_id" => $fav_id,
                    "uid" => $this->uid,
                    "fav_type" => $fav_type,
                    "website_id" => $this->website_id,]
                )->update(['seckill_id'=>$seckill_id]);
                return $retval;
            }else{
                return 0;
            }
        }
        if ($fav_type == 'goods') {
            // 收藏商品
            $goodsSer = new Goods();
            $goods_info = $goodsSer->getGoodsDetailById($fav_id, 'goods_id,goods_name,price,collects,supplier_goods_id,picture', 1);
            $shop_name = "";
            $shop_logo = "";
            $shop_id = 0;
            $data = array(
                'uid' => $this->uid,
                'fav_id' => $fav_id,
                'fav_type' => $fav_type,
                'fav_time' => time(),
                'shop_id' => $shop_id,
                'shop_name' => $shop_name,
                'shop_logo' => $shop_logo,
                'goods_name' => $goods_info['goods_name'],
                'goods_image' => $goods_info['album_picture']['pic_cover_small'],
                'log_price' => $goods_info['price'],
                'log_msg' => $log_msg,
                'seckill_id' => $seckill_id,
                "website_id" => $this->website_id
            );
            $retval = $member_favorites->save($data);
            $goodsSer->updateGoods([
                "goods_id" => $fav_id
            ], [
                "collects" => $goods_info["collects"] + 1
            ], $fav_id);
            return $retval;
        } elseif ($fav_type == 'shop') {
            $shop = new VslShopModel();
            $shop_info = $shop->getInfo([
                'shop_id' => $fav_id,
                'website_id'=>$this->website_id
            ], 'shop_name,shop_logo,shop_collect');
            $data = array(
                'uid' => $this->uid,
                'fav_id' => $fav_id,
                'fav_type' => $fav_type,
                'fav_time' => time(),
                'shop_id' => $fav_id,
                'shop_name' => $shop_info['shop_name'],
                'shop_logo' => empty($shop_info['shop_logo']) ? ' ' : $shop_info['shop_logo'],
                'goods_name' => '',
                'goods_image' => '',
                'log_price' => 0,
                'log_msg' => $log_msg,
                "website_id" => $this->website_id
            );
            $retval = $member_favorites->save($data);
            $shop->save(array(
                "shop_collect" => $shop_info["shop_collect"] + 1
            ), [
                "shop_id" => $fav_id
            ]);
            return $retval;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::deleteMemberFavorites()
     */
    public function deleteMemberFavorites($fav_id, $fav_type)
    {
        $member_favorites = new VslMemberFavoritesModel();
        if (!empty($this->uid)) {
            if ($fav_type == 'goods') {
                // 收藏商品
                $goodsSer = new Goods();
                $goods_info = $goodsSer->getGoodsDetailById($fav_id);
                $condition = array(
                    'fav_id' => $fav_id,
                    'fav_type' => $fav_type,
                    'uid' => $this->uid
                );
                $retval = $member_favorites->destroy($condition);
                $collect = empty($goods_info["collects"]) ? 0 : $goods_info["collects"];
                $collect--;
                if ($collect < 0) {
                    $collect = 0;
                }
                $goodsSer->updateGoods([
                    "goods_id" => $fav_id
                ], [
                    "collects" => $collect
                ], $fav_id);
                return $retval;
            } elseif ($fav_type == 'shop') {
                $shop = new VslShopModel();
                $shop_info = $shop->getInfo([
                    'shop_id' => $fav_id
                ], 'shop_name,shop_logo,shop_collect');
                $condition = array(
                    'fav_id' => $fav_id,
                    'fav_type' => $fav_type,
                    'uid' => $this->uid
                );
                $retval = $member_favorites->destroy($condition);
                $shop_collect = empty($shop_info["shop_collect"]) ? 0 : $shop_info["shop_collect"];
                $shop_collect--;
                if ($shop_collect < 0) {
                    $shop_collect = 0;
                }
                $shop->save([
                    "shop_collect" => $shop_collect
                ], [
                    "shop_id" => $fav_id
                ]);
                return $retval;
            }
        }
    }

    /**
     *
     * {@inheritdoc}
     *
     * @see \data\api\IMember::getIsMemberFavorites()
     */
    public function getIsMemberFavorites($uid, $fav_id, $fav_type)
    {
        $member_favorites = new VslMemberFavoritesModel();
        $condition = array(
            'uid' => $uid,
            'fav_id' => $fav_id,
            'fav_type' => $fav_type,
            'website_id' => $this->website_id
        );
        $res = $member_favorites->where($condition)->count();
        return $res;
    }

    /**
     * 获取浏览历史
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberViewHistory()
     */
    public function getMemberViewHistory()
    {
        $has_history = Cookie::has('goodshistory');

        if ($has_history) {
            $goods_id_array = Cookie::get('goodshistory');
            $goodsSer = new Goods();
            $goods_list = $goodsSer->getGoodsListByCondition([
                'goods_id' => array(
                    'in',
                    $goods_id_array
                )
            ],'goods_id,goods_name,price,picture');
            $list = array();
            for ($i = 0; $i < 8; $i++) {
                if (!empty($goods_list[$i])) {
                    $goods_list[$i]['picture_info'] = $goods_list[$i]['album_picture'];
                    $list[] = $goods_list[$i];
                }
            }
            return $list;
        } else {
            return '';
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberViewHistory()
     */
    public function addMemberViewHistory($goods_id)
    {
        $has_history = Cookie::has('goodshistory');
        if ($has_history) {
            $goods_id_array = Cookie::get('goodshistory');
            Cookie::set('goodshistory', $goods_id_array . ',' . $goods_id, 3600);
        } else {
            Cookie::set('goodshistory', $goods_id, 3600);
        }
        return 1;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::deleteMemberViewHistory()
     */
    public function deleteMemberViewHistory()
    {
        if (Cookie::has('goodshistory')) {
            Session::set('goodshistory', Cookie::get('goodshistory'));
        }
        Cookie::set('goodshistory', null);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberIsApplyShop()
     */
    public function getMemberIsApplyShop($uid)
    {
        if ($this->is_system == 1) {
            return 'is_system';
        } else {
            // 是否正在申请
            $shop_apply = new VslShopApplyModel();
            $apply = $shop_apply->getInfo([
                'uid' => $uid
            ],'apply_state');
            if (!empty($apply)) {
                if ($apply['apply_state'] == -1) {
                    // 已被拒绝
                    return 'refuse_apply';
                } else
                    if ($apply['apply_state'] == 2) {
                        // 已同意
                        return 'is_system';
                    } else {
                        // 存在正在申请
                        return 'is_apply';
                    }
            } else {
                // 可以申请
                return 'apply';
            }
        }
    }

    /**
     * 猜你喜欢(non-PHPdoc)
     *
     * @see \data\api\IMember::getGuessMemberLikes()
     */
    public function getGuessMemberLikes()
    {
        $history = Cookie::has('goodshistory') ? Cookie::get('goodshistory') : Session::get('goodshistory');
        if (!empty($history)) {
            $history_array = explode(",", $history);
            $goods_id = $history_array[count($history_array) - 1];
            $goodsSer = new Goods();
            $category_id = $goodsSer->getGoodsDetailById($goods_id);
        } else {
            $category_id['category_id'] = 0;
        }
        $goods = new Goods();
        $goods_list = $goods->getSearchGoodsList(1, 18, [
            'category_id' => $category_id['category_id'],
            'website_id' => $this->website_id,
            'state' => 1
        ], 'sort desc,create_time desc','goods_id,shop_id,goods_name,collects,price,picture');
        return $goods_list['data'];
    }

    /**
     * 用户信息
     * @param int $uid
     *
     * @return array $account_info
     * @see \data\api\IMember::getMemberAccount()
     */
    public function getMemberAccount($uid,$website_id='')
    {
        if(!$website_id)$website_id = $this->website_id;
        $member_account = new VslMemberAccountModel();
        $account_info = $member_account->getInfo([
            'uid' => $uid,
            'website_id' => $website_id
        ], 'point,balance,coin');
        if (empty($account_info)) {
            $account_info["point"] = 0;
        }

        return $account_info;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberPointToBalance()
     */
    public function memberPointToBalance($uid, $point)
    {
        $member_account_model = new VslMemberAccountModel();
        $member_account_model->startTrans();
        try {
            $member_account_info = $this->getMemberAccount($uid);

            if ($point > $member_account_info['point']) {
                $member_account_model->commit();
                return LOW_POINT;
            } else {

                $point_config = new VslPointConfigModel();
                $point_info = $point_config->getInfo([
                    'website_id' => $this->website_id
                ], 'is_open, convert_rate');
                if ($point_info['is_open'] == 0 || empty($point_info)) {
                    $member_account_model->rollback();
                    return "积分兑换功能关闭";
                } else {
                    $member_account = new MemberAccount();
                    $exchange_balance = $member_account->pointToBalance($point);
                    $retval = $member_account->addMemberAccountData(1, $uid, 0, $point * (-1), 3, 0, '积分兑换余额');
                    if ($retval < 0) {
                        $member_account_model->rollback();
                        return $retval;
                    }
                    $retval = $member_account->addMemberAccountData(2, $uid, 1, $exchange_balance, 3, 0, '积分兑换余额');
                    if ($retval < 0) {
                        $member_account_model->rollback();
                        return $retval;
                    }
                    $member_account_model->commit();
                    return 1;
                }
            }
        } catch (\Exception $e) {
            $member_account_model->rollback();
            return $e->getMessage();
        }
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::getMemberAll()
     */
    public function getMemberAll($condition)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $user_data = $user->getQuery($condition,'uid','');
        return $user_data;
    }

    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::getMemberCount()
     */
    public function getMemberCount($condition)
    {
        // TODO Auto-generated method stub
        $user = new VslMemberModel();
        $user_sum = $user->where($condition)->count();
        if ($user_sum) {
            return $user_sum;
        } else {
            return 0;
        }
    }
    public function getUserCount($condition)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $user_sum = $user->where($condition)->count();
        if ($user_sum) {
            return $user_sum;
        } else {
            return 0;
        }
    }
    public function getMemberWithdrawalCount($condition)
    {
        // TODO Auto-generated method stub
        $user = new VslMemberBalanceWithdrawModel();
        $user_sum = $user->where($condition)->count();
        if ($user_sum) {
            return $user_sum;
        } else {
            return 0;
        }
    }
    /*
     * (non-PHPdoc)
     * @see \data\api\IMember::getMemberMonthCount()
     */
    public function getMemberMonthCount($begin_date, $end_date)
    {
        // TODO Auto-generated method stub
        $user = new UserModel();
        $condition["reg_time"] = [
            [
                ">",
                strtotime($begin_date)
            ],
            [
                "<",
                strtotime($end_date)
            ]
        ];
        $condition["website_id"] = $this->website_id;
        $condition["is_member"] = 1;
        // 一段时间内的注册用户
        $user_list = $user->getQuery($condition,'reg_time','');
        $begintime = strtotime($begin_date);
        $endtime = strtotime($end_date);
        $list = array();
        for ($start = $begintime; $start <= $endtime; $start += 24 * 3600) {
            $list[date("Y-m-d", $start)] = array();
            $user_num = 0;
            foreach ($user_list as $v) {
                if (date("Y-m-d", $v["reg_time"]) == date("Y-m-d", $start)) {
                    $user_num = $user_num + 1;
                }
            }
            unset($v);
            $list[date("Y-m-d", $start)] = $user_num;
        }
        return $list;
    }

    /**
     * (non-PHPdoc)余额转账
     *
     * @see \data\api\IMember::addMemberAccount()
     */
    public function addMemberAccount2($type, $uid, $num, $text,$types,$charge=0)
    {
        $member_account = new MemberAccount();
        $retval = $member_account->addMemberAccountData($type, $uid, 1, $num, $types, 0, $text,'', '', '', '', '', '',0,'',$charge);
        if($retval>0){
            $user_service = new User();
            $user_service->updateUserLabel($uid,$this->website_id);
        }
        return $retval;
    }

    /**
     * 用户退出
     */
    public function Logout($isChangeShop = false)
    {
        parent::Logout($isChangeShop);
        $_SESSION['order_tag'] = ""; // 清空订单
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberLevelList()
     */
    public function getMemberLevelList($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $member_level = new VslMemberLevelModel();
        return $member_level->pageQuery($page_index, $page_size, $condition, $order, $field);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberLevelDetail()
     */
    public function getMemberLevelDetail($level_id)
    {
        $member_level = new VslMemberLevelModel();
        return $member_level->get($level_id);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberLevel()
     */
    public function addMemberLevel($shop_id, $level_name, $growth_num, $goods_discount,$is_label)
    {
        $member_level = new VslMemberLevelModel();
        $where['website_id'] = $this->website_id;
        $where['level_name'] = $level_name;
        $count = $member_level->where($where)->count();
        if ($count > 0) {
            return -2;
        }
        $data = array(
            'shop_id' => $shop_id,
            'level_name' => $level_name,
            'goods_discount' => $goods_discount,
            'growth_num' => $growth_num,
            'is_label'=>$is_label,
            'create_time' => time(),
            'website_id' => $this->website_id
        );
        $res = $member_level->save($data);
        $data['level_id'] = $res;
        hook("memberLevelSaveSuccess", $data);
        return $res;
    }
    /**
     * 获取会员等级权重
     */
    public function getMemberHeight()
    {
        $member_level = new VslMemberLevelModel();
        $list = $member_level->Query(['website_id' => $this->website_id],'growth_num');
        return $list;
    }
    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberLevel()
     */
    public function updateMemberLevel($level_id, $shop_id,$level_name, $growth_num, $goods_discount,$is_label)
    {
        $member_level = new VslMemberLevelModel();
        $data = array(
            'shop_id' => $shop_id,
            'level_name' => $level_name,
            'goods_discount' => $goods_discount,
            'growth_num' => $growth_num,
            'modify_time'=>time(),
            'is_label'=>$is_label,
            'website_id' => $this->website_id
        );
        $res = $member_level->save($data, [
            'level_id' => $level_id
        ]);
        $data['level_id'] = $level_id;
        hook("memberLevelSaveSuccess", $data);
        if ($res == 0) {
            return 1;
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::deleteMemberLevel()
     */
    public function deleteMemberLevel($level_id)
    {
        $member_level = new VslMemberLevelModel();
        $member_count = $this->getMemberLevelUserCount($level_id);
        if ($member_count > 0) {
            return MEMBER_LEVEL_DELETE;
        } else {
            return $member_level->destroy($level_id);
        }
    }
    public function adjustMemberLevel($level_id,$uid)
    {
        $member = new VslMemberModel();
        $member_level_model = new VslMemberLevelModel();
        $memberLevel =  $member_level_model->getInfo(['level_id'=>$level_id],'growth_num');
        if(!$memberLevel){
            return LEVEL_NO_FOUND;
        }
        return $member->save(['member_level'=>$level_id,'growth_num'=>$memberLevel['growth_num']],['uid'=>$uid]);
    }

    public function adjustMemberPu($is_pu,$uid)
    {
        $member = new VslMemberModel();
        return $member->save(['is_pu'=>$is_pu],['uid'=>$uid]);
    }

    /**
     * 查询会员的等级下是否有会员
     *
     * @param unknown $level_id
     */
    private function getMemberLevelUserCount($level_id)
    {
        $member_model = new VslMemberModel();
        $member_count = $member_model->getCount([
            'member_level' => $level_id
        ]);
        return $member_count;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::modifyMemberLevelField()
     */
    public function modifyMemberLevelField($level_id, $field_name, $field_value)
    {
        $member_level = new VslMemberLevelModel();
        return $member_level->save([
            "$field_name" => $field_value
        ], [
            'level_id' => $level_id
        ]);
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::createMemberRecharge()
     */
    public function createMemberRecharge($recharge_money, $uid, $out_trade_no, $type,$give_money = 0)
    {
        $member_recharge = new VslMemberRechargeModel();
        $data = array(
            'recharge_money' => $recharge_money,
            'uid' => $uid,
            'out_trade_no' => $out_trade_no,
            'website_id' => $this->website_id,
            'create_time' => time(),
            'give_money' => $give_money,
        );
        $res = $member_recharge->save($data);
        if ($res) {
            $pay = new UnifyPay();
            if($type == 5) {
                //货款充值
                $pay->createPayment(0, $out_trade_no, '货款充值', '用户通知货款', $recharge_money, 5, $member_recharge->id,'');
            }elseif($type == 7){
                //会员卡充值
                $pay->createPayment(0, $out_trade_no, '会员卡充值', '会员卡充值', $recharge_money, 7, $member_recharge->id,'');
            }else{
                $pay->createPayment(0, $out_trade_no, '余额充值', '用户通知余额', $recharge_money, 4, $member_recharge->id,'');
            }
        }
        return $res;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::payMemberRecharge()
     */
    public function payMemberRecharge($out_trade_no, $pay_type, $is_proceeds = 0)
    {
        $member_recharge_model = new VslMemberRechargeModel();
        $pay = new UnifyPay();
        $pay_info = $pay->getPayInfo($out_trade_no);
        if (!empty($pay_info)) {
            $pay_status = $pay_info["pay_status"];
            if ($pay_status == 1) {
                // 支付成功
                $racharge_obj = $member_recharge_model->getInfo(['out_trade_no'=>$out_trade_no],'*');
                if (!empty($racharge_obj)) {
                    $user = new User();
                    $user->updateUserGrowthNum(2,$racharge_obj['uid'],0,$racharge_obj['id']);
                    $user->updateUserLabel($racharge_obj['uid'],$racharge_obj['website_id']);
                    $data = array(
                        "is_pay" => 1,
                        "status" => 1
                    );
                    $member_recharge_model->save($data, [
                        "id" => $racharge_obj["id"]
                    ]);

                    $member_account = new MemberAccount();
                    if($is_proceeds) {
                        //货款充值
                        $member_account->addMemberAccountData(5, $racharge_obj["uid"], 1, $racharge_obj["recharge_money"], 53, $racharge_obj["id"],'货款充值','','','','', $pay_type);
                    }else{
                        //余额充值
                        $member_account->addMemberAccountData(2, $racharge_obj["uid"], 1, $racharge_obj["recharge_money"], 4, $racharge_obj["id"],'余额充值','','','','', $pay_type);
                    }

                    //用户余额充值成功商家提醒
                    runhook("Notify", "successfulRechargeByTemplate", ["website_id" => $racharge_obj["website_id"], "out_trade_no" => $out_trade_no, "uid" => $racharge_obj["uid"], "pay_money" => $racharge_obj["recharge_money"]]);//用户余额充值成功用户提醒
                    runhook("MpMessage", "successfulRechargeByMpTemplate", ["website_id" => $racharge_obj["website_id"] ?:$this->website_id, "out_trade_no" => $out_trade_no, "uid" => $racharge_obj["uid"], "pay_money" => $racharge_obj["recharge_money"]]);//小程序用户余额充值成功用户提醒
                    runhook("Notify", "rechargeSuccessUserBySms", ["shop_id" => 0, "website_id" => $racharge_obj["website_id"], "out_trade_no" => $out_trade_no, "uid" => $racharge_obj["uid"]]);//用户余额充值成功用户提醒
                    runhook('Notify', 'emailSend', ['shop_id' => 0, 'website_id' => $racharge_obj["website_id"], 'notify_type' => 'user', 'template_code' => 'recharge_success', 'out_trade_no' => $out_trade_no, 'uid' => $racharge_obj['uid']]);
                }
            }
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::memberBankAccount()
     */
    public function getMemberBankAccount($is_default = 0,$condition=[])
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $uid = $this->uid;
        $bank_account_list = '';
        if (!empty($uid)) {
            $condition['uid'] = $uid;
            if($is_default) {
                $condition['is_default'] = 1;
            }
            $bank_account_list = $member_bank_account->getQuery($condition, '*', '');
        }
        return $bank_account_list;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::addMemberBankAccount()
     */
    public function addMemberBankAccount($uid, $type, $account_number,$bank_code,$bank_type,$bank_username,$bank_card,$bank_name,$mobile,$validdate,$cvv2)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $member_bank_account->startTrans();
        if($bank_code){
            $bank = new VslBankModel();
            $open_bank = $bank->getInfo(['bank_code'=>$bank_code],'bank_name')['bank_name'];
        }else{
            $open_bank = $bank_name;
        }
        try {
            $data = array(
                'uid' => $uid,
                'type' => $type,
                'realname' => $bank_username,
                'account_number' => $account_number,
                'open_bank' => $open_bank,
                'bank_code'=>$bank_code,
                'bank_type'=>$bank_type,
                'bank_card'=>$bank_card,
                'mobile'=>$mobile,
                'valid_date'=>$validdate,
                'cvv2'=>$cvv2,
                'create_date' => time(),
                'website_id' => $this->website_id
            );

            $member_bank_account->save($data);
            $account_id = $member_bank_account->id;
            $this->setMemberBankAccountDefault($uid, $account_id);
            $member_bank_account->commit();
            return $account_id;
        } catch (\Exception $e) {
            $member_bank_account->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::updateMemberBankAccount()
     */

    public function updateMemberBankAccount($account_id, $type, $account_number,$bank_code,$bank_type,$bank_username,$bank_card,$bank_name,$mobile,$validdate,$cvv2)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $member_bank_account->startTrans();
        try {
            if($bank_code){
                $bank = new VslBankModel();
                $open_bank = $bank->getInfo(['bank_code'=>$bank_code],'bank_name')['bank_name'];
            }else{
                $open_bank = $bank_name;
            }
            $data = array(
                'realname' => $bank_username,
                'account_number' => $account_number,
                'type' => $type,
                'open_bank' => $open_bank,
                'bank_code'=>$bank_code,
                'bank_card'=>$bank_card,
                'bank_type'=>$bank_type,
                'mobile'=>$mobile,
                'valid_date'=>$validdate,
                'cvv2'=>$cvv2,
                'modify_date' => time()
            );
            $member_bank_account->save($data, [
                'id' => $account_id
            ]);
            $this->setMemberBankAccountDefault($this->uid, $account_id);
            $member_bank_account->commit();
            return $account_id;
        } catch (\Exception $e) {
            $member_bank_account->rollback();
            return $e->getMessage();
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::delMemberBankAccount()
     */
    public function delMemberBankAccount($account_id)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $uid = $this->uid;
        $retval = $member_bank_account->destroy([
            'uid' => $uid,
            'id' => $account_id
        ]);
        return $retval;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::setMemberBankAccountDefault()
     */
    public function setMemberBankAccountDefault($uid, $account_id)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $member_bank_account->update([
            'is_default' => 0
        ], [
            'uid' => $uid,
            'is_default' => 1
        ]);
        $member_bank_account->update([
            'is_default' => 1
        ], [
            'uid' => $uid,
            'id' => $account_id
        ]);
        return $account_id;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \data\api\IMember::getMemberBankAccountDetail()
     */
    public function getMemberBankAccountDetail($id)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $bank_account_info = $member_bank_account->getInfo([
            'id' => $id,
            'uid' => $this->uid
        ], '*');
        return $bank_account_info;
    }

    /**
     * (non-PHPdoc)
     */
    public function getMemberBalanceWithdraw($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $member_balance_withdraw = new VslMemberBalanceWithdrawModel();
        $list = $member_balance_withdraw->getViewList($page_index, $page_size, $condition, $order);
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                if( abs($v['service_charge']) != abs($v['cash'])){ //全部提现 修正显示 增加实际扣款  -$records_info['balance'] == 0- 提完了，有剩余 但是可以不够手续费 这种情况不需要加
                    $list['data'][$k]['real_cash'] =  $list['data'][$k]['cash'];
                    $list['data'][$k]['cash'] =  -1*$list['data'][$k]['service_charge'];
                }else{
                    $list['data'][$k]['real_cash'] =  $list['data'][$k]['cash'] + $list['data'][$k]['charge'];
                }
                if(empty($list['data'][$k]['user_name'])){
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['ask_for_date'] = date('Y-m-d H:i:s', $v['ask_for_date']);
                if($list['data'][$k]['payment_date']>0){
                    $list['data'][$k]['payment_date'] = date('Y-m-d H:i:s', $v['payment_date']);
                }else{
                    $list['data'][$k]['payment_date'] = '未到账';
                }
                $list['data'][$k]['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            }
            unset($v);
        }
        return $list;
    }

/*--------------------------------------------------------  提现过程  -------------------------------------------------*/
    /**
     * (non-PHPdoc)余额提现
     */
    public function addMemberBalanceWithdraw($shop_id, $withdraw_no, $uid, $bank_account_id, $cash, $type)
    {
        $member = new UserModel();
        $member_info = $member->getInfo(['uid'=>$uid],'wx_openid,user_tel,real_name');
        $wx_openid = $member_info['wx_openid'];
        // 得到本店的提现设置
        $config = new Config();
        $withdraw_info = $config->getConfig($shop_id, 'WITHDRAW_BALANCE');
        // 判断是否余额提现设置是否为空 是否启用
        if (empty($withdraw_info) || $withdraw_info['is_use'] == 0) {
            return USER_WITHDRAW_NO_USE;
        }
        // 最小提现额判断
        if ($cash < $withdraw_info['value']["withdraw_cash_min"]) {
            return USER_WITHDRAW_MIN;
        }
        // 判断会员当前余额
        $member_account = new MemberAccount();
        $balance = $member_account->getMemberBalance($uid);
        if ($balance <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        if ($balance < $cash || $cash <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        $charge = 0;
        //提现手续费
        if($withdraw_info['value']['member_withdraw_poundage']) {
            $charge = abs($cash) * $withdraw_info['value']['member_withdraw_poundage']/100;//手续费
            if($withdraw_info['value']['withdrawals_end'] && $withdraw_info['value']['withdrawals_begin']){
                if (abs($cash) <= $withdraw_info['value']['withdrawals_end'] && abs($cash) >=  $withdraw_info['value']['withdrawals_begin']) {
                    $charge = 0;//免手续费区间
                }
            }
        }
        if($cash+$charge<=$balance){
            $service_charge = $cash;
        }else if($cash-$charge>=0){
            $service_charge = $cash-$charge;
        }else{
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        // 获取 提现账户
        $member_bank_account = new VslMemberBankAccountModel();
        $bank_account_info = $member_bank_account->getInfo([
            'id' => $bank_account_id
        ], '*');
        // 查询是否是自动打款不用审核
        $is_examine = $withdraw_info['value']['is_examine'];
        $make_money = $withdraw_info['value']['make_money'];
        if($type==2){
            $bank_account_info['account_number'] = $member_info['user_tel'];
            $bank_account_info['realname'] = $member_info['real_name'];
            $bank_account_info['type'] = 2;
        }
        if($is_examine==1 ){//自动审核
            $status= 2;
        }
        if($is_examine!=1){//手动审核
            $status= 1;
        }
        if($type==1 || $type ==4){
            if($withdraw_info['value']['withdraw_message']){
                $withdraw_message = explode(',',$withdraw_info['value']['withdraw_message']);
                if(in_array(4,$withdraw_message)){
                    $bank_account_info['type'] = 4;
                }
            }
        }
        // 添加提现记录
        $balance_withdraw = new VslMemberBalanceWithdrawModel();
        $data = array(
                'shop_id' => $shop_id,
                'withdraw_no' => $withdraw_no,
                'uid' => $uid,
                'account_number' => $bank_account_info['account_number'],
                'realname' => $bank_account_info['realname'],
                'type' => $bank_account_info['type'],
                'cash' => $cash*(-1),
                'service_charge'=>abs($service_charge),
                'charge'=>abs($charge)*(-1),
                'ask_for_date' => time(),
                'status' => $status,
                'modify_date' => time(),
                'website_id' => $this->website_id,
        );
        $id=$balance_withdraw->save($data);
        $data['id'] = $id;
        if ($id) {
            // 添加账户流水
            $member_account = new MemberAccount();
            $res = $member_account->addMemberAccountData(2, $uid, 0, $cash*(-1), 8, $id,'', $is_examine,$make_money,$wx_openid,$balance_withdraw->withdraw_no,$type,$bank_account_info['account_number'],$service_charge,$bank_account_id,$charge,$bank_account_info['realname']);
        }
        return $res;
    }
    /*
   * (non-PHPdoc)平台审核
   */
    public function userCommissionWithdraw($shop_id, $id, $status, $remark)
    {
        // TODO Auto-generated method stub
        $config = new Config();
        $withdraw_info = $config->getConfig($shop_id, 'WITHDRAW_BALANCE');
        $make_money = $withdraw_info['value']['make_money'];
        $member_balance_withdraw = new VslMemberBalanceWithdrawModel();
        $member_account = new MemberAccount();
        $member_balance_withdraw_info = $member_balance_withdraw->getInfo([
            'id' => $id
        ], 'status,uid,type,cash,withdraw_no,account_number,service_charge,realname');
         //此处添加校验 避免重复审核
         if($member_balance_withdraw_info['status'] == -1 || $member_balance_withdraw_info['status'] == 2){
            //重复审核
            $results['is_success'] = -1;
            $results['msg'] = "已审核，请勿重复操作";
            return $results;
        }
        $member = new UserModel();
        $member_info = $member->getInfo(['uid'=>$member_balance_withdraw_info['uid']],'wx_openid,uid');
        $wx_openid = $member_info['wx_openid'];
        if ($status == 1 && $make_money==1) {
            // 平台审核提现通过，自动打款
            if($member_balance_withdraw_info['type']==2 &&  $make_money==1){//微信提现且自动打款
               return $member_account->addAuditMemberAccountData($id, $member_info['uid'],  -$member_balance_withdraw_info["cash"],$wx_openid,$member_balance_withdraw_info['withdraw_no'],2,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['service_charge'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==3 &&  $make_money==1){//支付宝提现且自动打款
               return $member_account->addAuditMemberAccountData($id, $member_info['uid'], -$member_balance_withdraw_info["cash"],$wx_openid,$member_balance_withdraw_info['withdraw_no'],3,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['service_charge'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==1 &&  $make_money==1){//银行卡提现且自动打款
                return $member_account->addAuditMemberAccountData($id, $member_info['uid'], -$member_balance_withdraw_info["cash"],$wx_openid,$member_balance_withdraw_info['withdraw_no'],1,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['service_charge'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==4 &&  $make_money==1){
                // 修改会员提现状态
                $withdraw_data = array(
                    'status' => 2,
                    'modify_date' => time(),
                );
                $res = $member_balance_withdraw->save($withdraw_data, ['id' => $id]);
                return $res;
            }
        }
        if ($status == 1 && $make_money==2) {
            // 平台审核通过,待打款
            // 修改会员提现状态
            $withdraw_data = array(
                'status' => 2,
                'modify_date' => time(),
            );
            $member_account->addMemberAccountRecords($member_balance_withdraw_info["cash"],$id,$member_info['uid'],7,'');
            $res = $member_balance_withdraw->save($withdraw_data, ['id' => $id]);
            return $res;
        }
        if ($status == -1) {
            // 平台审核不通过
            return $member_account->addMemberAccountRecords(-$member_balance_withdraw_info["cash"],$id,$member_info['uid'],-1,$remark);
        }
    }
    /*
     * (non-PHPdoc)后台打款
     */
    public function memberBalanceWithdraw($shop_id, $id, $status,$remark)
    {
        $member_account = new MemberAccount();
        $member_balance_withdraw = new VslMemberBalanceWithdrawModel();
        $member_balance_withdraw_info = $member_balance_withdraw->getInfo([
            'id' => $id
        ], 'status,uid,type,cash,withdraw_no,account_number,ask_for_date,realname');
        $member = new UserModel();
        $member_info = $member->getInfo(['uid'=>$member_balance_withdraw_info['uid']],'wx_openid,mp_open_id,uid');
        if($member_info['wx_openid']){
            $wx_openid = $member_info['wx_openid'];
        }else{
            $wx_openid = $member_info['mp_open_id'];
        }
        //此处添加校验 避免重复打款
        if($status == 4 && $member_balance_withdraw_info['status'] == 4){
            return -1;
        }
        if(($status == 3 || $status == 5) && $member_balance_withdraw_info['status'] == 3){
            return -1;
        }
        if ($status == 4) {//平台拒绝打款
            // 平台拒绝打款，给会员打回一笔金额
            $retval=$member_account->addMemberAccountRecords(-$member_balance_withdraw_info["cash"],$id,$member_info['uid'],4,$remark);
        }
        if ($status == 3) {//平台同意在线打款
            // 平台审核提现通过，在线打款
            if($member_balance_withdraw_info['type']==2){//微信提现且在线打款
                $retval= $member_account->addAgreeMemberAccountData($id, $member_info['uid'],  -$member_balance_withdraw_info["cash"],'余额微信提现，在线打款成功',$wx_openid,$member_balance_withdraw_info['withdraw_no'],2,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==3){//支付宝提现且在线打款
                $retval= $member_account->addAgreeMemberAccountData($id, $member_info['uid'], -$member_balance_withdraw_info["cash"],'余额支付宝提现，在线打款成功',$wx_openid,$member_balance_withdraw_info['withdraw_no'],3,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==1){//银行卡提现且在线打款
                $retval= $member_account->addAgreeMemberAccountData($id, $member_info['uid'], -$member_balance_withdraw_info["cash"],'余额银行卡提现，在线打款成功',$wx_openid,$member_balance_withdraw_info['withdraw_no'],1,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['realname']);
            }
            //会员提现审核通过钩子
            hook('memberWithdrawAuditAgree', ['id' => $id]);
        }
        if ($status == 5) {//线下转账打款
            if($member_balance_withdraw_info['type']==2){//微信提现且手动打款
                $retval= $member_account->addAgreeMemberAccountData($id, $member_info['uid'],  -$member_balance_withdraw_info["cash"],'余额微信提现，手动打款成功',$wx_openid,$member_balance_withdraw_info['withdraw_no'],5,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==3){//支付宝提现且手动打款
                $retval= $member_account->addAgreeMemberAccountData($id, $member_info['uid'], -$member_balance_withdraw_info["cash"],'余额支付宝提现，手动打款成功',$wx_openid,$member_balance_withdraw_info['withdraw_no'],5,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['realname']);
            }
            if($member_balance_withdraw_info['type']==1 || $member_balance_withdraw_info['type']==4){//银行卡提现且手动打款
                $retval= $member_account->addAgreeMemberAccountData($id, $member_info['uid'], -$member_balance_withdraw_info["cash"],'余额银行卡提现，手动打款成功',$wx_openid,$member_balance_withdraw_info['withdraw_no'],5,$member_balance_withdraw_info['account_number'],$member_balance_withdraw_info['realname']);
            }
        }
        $params = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'takeoutmoney' => $member_balance_withdraw_info['cash'],
            'out_trade_no' => $member_balance_withdraw_info['withdraw_no'],
            'uid' => $member_balance_withdraw_info['uid'],
            'create_time' => $member_balance_withdraw_info['ask_for_date'],
            'status' => $member_balance_withdraw_info['status'],//1待审核2待打款3已打款4拒绝打款 -1审核不通过
        ];

        runhook('MpMessage', 'successfulWithdrawalsByMpTemplate', $params);

        return $retval;
    }

    /*--------------------------------------------------------  提现过程  -------------------------------------------------*/
    /**
     * (non-PHPdoc)
     */
    public function getMemberWithdrawalsDetails($id)
    {
        $member_balance_withdraw = new VslMemberBalanceWithdrawModel();
        $retval = $member_balance_withdraw->getInfo([
            'id' => $id
        ], '*');
        if (!empty($retval)) {
                 if ($retval['type'] == 1 || $retval['type'] == 4){
                     $member_bank_account = new VslMemberBankAccountModel();
                     $open_bank = $member_bank_account->getInfo([
                         'account_number' => $retval['account_number'],
                         'uid' => $retval['uid']
                     ], 'open_bank')['open_bank'];
                     if (!empty($open_bank)){
                         $retval['open_bank'] = $open_bank;
                     }
                 }
                 $member_account = new VslMemberAccountRecordsViewModel();
                $records_info = $member_account->getInfo(['data_id' => $id,'from_type'=>8],'balance');
                if( abs($retval['service_charge']) != abs($retval['cash'])){ //全部提现 修正显示 增加实际扣款  -$records_info['balance'] == 0- 提完了，有剩余 但是可以不够手续费 这种情况不需要加
                    $retval['real_cash'] =  $retval['cash'];
                    $retval['cash'] =  -1*$retval['service_charge'];
                }else{
                    $retval['real_cash'] =  $retval['cash'] + $retval['charge'];
                }
                $retval['ask_for_date'] = date('Y-m-d H:i:s', $retval['ask_for_date']);
                if($retval['payment_date']>0){
                    $retval['payment_date'] = date('Y-m-d H:i:s', $retval['payment_date']);
                }else{
                    $retval['payment_date'] = '未到账';
                }
        }
        return $retval;
    }

    /**
     * 设置用户支付密码
     *
     * {@inheritdoc}
     *
     * @see \data\api\IMember::setUserPaymentPassword()
     */
    public function setUserPaymentPassword($uid, $payment_password)
    {
        $user = new UserModel();
        $retval = $user->save([
            'payment_password' => md5($payment_password),
            'plain_password' => $payment_password,
        ], [
            'uid' => $uid
        ]);
        return $retval;
    }
    /**
     * 获取用户支付密码
     */
    public function getPaymentPassword()
    {
        $user = new UserModel();
        // 获取支付密码
        return $user->getInfo([
            "uid" => $this->uid
        ],'payment_password')['payment_password'];
    }
    /**
     * 修改用户支付密码
     */
    public function updateUserPaymentPassword($uid,$new_payment_password)
    {
        $user = new UserModel();
        // 修改支付密码
        return $user->save([
                "payment_password" => md5($new_payment_password),
                'plain_password' => $new_payment_password,
            ], [
                "uid" => $uid
            ]);
    }

    /*
    * 分红中心
    */
    public function getBonusConfig()
    {
        $base_config = new Config();
        $ConfigService = new AddonsConfigService();
        $global_bonus_info = $ConfigService->getAddonsConfig('globalbonus');
        $area_bonus_info = $ConfigService->getAddonsConfig('areabonus');
        $team_bonus_info = $ConfigService->getAddonsConfig('teambonus');
        $info['global_bonus_agreement'] = $base_config->getConfig(0,'GLOBALAGREEMENT', $this->website_id, 1);
        $info['area_bonus_agreement'] = $base_config->getConfig(0,'AREAAGREEMENT', $this->website_id, 1);
        $info['team_bonus_agreement'] = $base_config->getConfig(0,'TEAMAGREEMENT', $this->website_id, 1);
        if (getAddons('globalbonus', $this->website_id)) {
            $info['global_bonus'] = $global_bonus_info['value'];
            $info['global_bonus']['is_use'] = $global_bonus_info['is_use'];
        } else {
            $info['global_bonus'] = [];
        }
        if (getAddons('areabonus', $this->website_id)) {
            $info['area_bonus'] = $area_bonus_info['value'];
            $info['area_bonus']['is_use'] = $area_bonus_info['is_use'];
        } else {
            $info['area_bonus'] = [];
        }
        if (getAddons('teambonus', $this->website_id)) {
            $info['team_bonus'] = $team_bonus_info['value'];
            $info['team_bonus']['is_use'] = $team_bonus_info['is_use'];
        } else {
            $info['team_bonus'] = [];
        }
            $bonus = new VslBonusAccountModel();
            $info['global_account_bonus'] = $bonus->getInfo(['uid' => $this->uid, 'from_type' => 1], '*');
            if ($info['global_account_bonus']) {
                $global_freezing_bonus = $info['global_account_bonus']['freezing_bonus'];
                $global_grant_bonus = $info['global_account_bonus']['grant_bonus'];
                $global_ungrant_bonus = $info['global_account_bonus']['ungrant_bonus'];
            } else {
                $global_freezing_bonus = 0;
                $global_grant_bonus = 0;
                $global_ungrant_bonus = 0;
            }
            $info['area_account_bonus'] = $bonus->getInfo(['uid' => $this->uid, 'from_type' => 2], '*');
            if ($info['area_account_bonus']) {
                $area_freezing_bonus = $info['area_account_bonus']['freezing_bonus'];
                $area_grant_bonus = $info['area_account_bonus']['grant_bonus'];
                $area_ungrant_bonus = $info['area_account_bonus']['ungrant_bonus'];
            } else {
                $area_freezing_bonus = 0;
                $area_grant_bonus = 0;
                $area_ungrant_bonus = 0;
            }
            $info['team_account_bonus'] = $bonus->getInfo(['uid' => $this->uid, 'from_type' => 3], '*');
            if ($info['team_account_bonus']) {
                $team_freezing_bonus = $info['team_account_bonus']['freezing_bonus'];
                $team_grant_bonus = $info['team_account_bonus']['grant_bonus'];
                $team_ungrant_bonus = $info['team_account_bonus']['ungrant_bonus'];
            } else {
                $team_freezing_bonus = 0;
                $team_grant_bonus = 0;
                $team_ungrant_bonus = 0;
            }
            $member = new VslMemberModel();
            $info['member_info'] = $member->getInfo(['uid' => $this->uid], '*');
            $level = new VslAgentLevelModel();
            $info['member_info']['global_level_name'] = $level->getInfo(['id' => $info['member_info']['global_agent_level_id']], 'level_name')['level_name'];
            $info['member_info']['area_level_name'] = $level->getInfo(['id' => $info['member_info']['area_agent_level_id']], 'level_name')['level_name'];
            $info['member_info']['team_level_name'] = $level->getInfo(['id' => $info['member_info']['team_agent_level_id']], 'level_name')['level_name'];
            $info['member_info']['freezing_bonus'] = $global_freezing_bonus + $area_freezing_bonus + $team_freezing_bonus;
            $info['member_info']['ungrant_bonus'] = $global_ungrant_bonus + $area_ungrant_bonus + $team_ungrant_bonus;
            $info['member_info']['grant_bonus'] = $global_grant_bonus + $area_grant_bonus + $team_grant_bonus;
            $info['member_info']['freezing_bonus'] = round($info['member_info']['freezing_bonus'],2);
            $info['member_info']['ungrant_bonus'] = round($info['member_info']['ungrant_bonus'],2);
            $info['member_info']['grant_bonus'] = round($info['member_info']['grant_bonus'],2);
            $info['member_info']['total_bonus'] = $info['member_info']['freezing_bonus'] + $info['member_info']['ungrant_bonus'] + $info['member_info']['grant_bonus'];
            $info['member_info']['total_bonus'] = round($info['member_info']['total_bonus'],2);
        return $info;
    }
    /*
      * 获取分红自定义表单
      */
    public function getCustomForm($website_id){
        if($website_id){
            $website_ids = $website_id;
        }else{
            $website_ids = $this->website_id;
        }
        $add_config = new AddonsConfigService();
        $customform =$add_config->getAddonsConfig("customform",$website_ids, 0, 1);
        $custom_server = getAddons('customform', $this->website_id) ? new CustomServer() : '';
        $custom_form['globalbonus'] = [];
        $custom_form['areabonus'] = [];
        $custom_form['teambonus'] = [];
        if($customform['shareholder']==1 && getAddons('customform', $this->website_id)){
            $custom_form_id =  $customform['shareholder_id'];
            $custom_form_info = $custom_server->getCustomFormDetail($custom_form_id)['value'];
            $custom_form['globalbonus'] =  json_decode($custom_form_info,true);
            if(empty($custom_form['globalbonus'])){
                $custom_form['globalbonus'] = [];
            }
        }
        if($customform['region']==1 && getAddons('customform', $this->website_id)){
            $custom_form_id =  $customform['region_id'];
            $custom_form_info = $custom_server->getCustomFormDetail($custom_form_id)['value'];
            $custom_form['areabonus'] =  json_decode($custom_form_info,true);
            if(empty($custom_form['areabonus'])){
                $custom_form['areabonus'] = [];
            }
        }
        if($customform['captain']==1 && getAddons('customform', $this->website_id)){
            $custom_form_id =  $customform['captain_id'];
            $custom_form_info = $custom_server->getCustomFormDetail($custom_form_id)['value'];
            $custom_form['teambonus'] =  json_decode($custom_form_info,true);
            if(empty($custom_form['teambonus'])){
                $custom_form['teambonus'] = [];
            }
        }
        return $custom_form;
    }
    /*
      * 获取会员自定义表单
      */
    public function getMemberCustomForm($website_id=0){
        $add_config = new AddonsConfigService();
        $customform =$add_config->getAddonsConfig("customform",$website_id, 0, 1);
        $custom_server =  getAddons('customform', $this->website_id) ? new CustomServer() : '';
        $custom_form= [];
        if($customform['member']==1 && getAddons('customform', $this->website_id)){
            $custom_form_id =  $customform['member_id'];
            $custom_form_info = $custom_server->getCustomFormDetail($custom_form_id)['value'];
            if($custom_form_info){
                $custom_form =  json_decode($custom_form_info,true);
            }
        }
        return $custom_form;
    }
    /*
     * 获取订单自定义表单
     */
    public function getOrderCustomForm($website_id=null){
        $add_config = new AddonsConfigService();
        $customform =$add_config->getAddonsConfig("customform",$website_id, 0, 1);
        $custom_server = getAddons('customform', $this->website_id) ? new CustomServer() : '';
        $custom_form=[];
        if($customform['order_coupon']==1 && getAddons('customform', $this->website_id)){
            $custom_form_id =  $customform['order_id'];
            $custom_form_info = $custom_server->getCustomFormDetail($custom_form_id)['value'];
            if($custom_form_info){
                $custom_form =  json_decode($custom_form_info,true);
            }
        }
        return $custom_form;
    }
    /**
     * 分红明细列表
     */
    public function getBonusGrantList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $Bonus_withdraw = new VslAgentAccountRecordsModel();
        $condition['nmar.uid'] = $this->uid;
        $list = $Bonus_withdraw->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            }
            unset($v);
        }
        return $list;
    }

    /**
     * 分红订单列表
     */
    public function getBonusOrderList($page_index, $page_size, $condition, $order = '')
    {
        $bonus = new VslOrderBonusLogModel();
        $order_model = new VslOrderModel();
        $uid = $condition['buyer_id'];
        unset($condition['buyer_id']);
        $order_id = $bonus->Query(['website_id'=>$condition['website_id'],'team_bonus_details|area_bonus_details|global_bonus_details'=>['like','%_'.$uid.'_%']],'order_id');//分红订单
        if($order_id){
            $condition['order_id'] = ['in', implode(',',$order_id)];
            $order_list = $order_model->pageQuery($page_index, $page_size, $condition, $order, '*');
            if (!empty($order_list['data'])) {
                $goods_sku = new VslGoodsSkuModel();
                $picture = new AlbumPictureModel();
                $order_item = new VslOrderGoodsModel();
                foreach ($order_list['data'] as $k => $v) {
                    //查询订单分红
                    $orders = $bonus->Query(['order_id' => $v['order_id']], 'team_bonus_details,area_bonus_details,global_bonus_details');
                    $order_list['data'][$k]['bonus']= 0;
                    foreach ($orders as $key1 => $value) {
                        $team_bonus = json_decode(htmlspecialchars_decode($value['team_bonus_details']),true);
                        $area_bonus = json_decode(htmlspecialchars_decode($value['area_bonus_details']),true);
                        $global_bonus = json_decode(htmlspecialchars_decode($value['global_bonus_details']),true);
                        if(isset($team_bonus[$uid]['bonus'])){
                            $order_list['data'][$k]['bonus'] += $team_bonus[$uid]['bonus'];
                        }
                        if(isset($area_bonus[$uid]['bonus'])){
                            $order_list['data'][$k]['bonus'] += $area_bonus[$uid]['bonus'];
                        }
                        if(isset($global_bonus[$uid]['bonus'])){
                            $order_list['data'][$k]['bonus'] += $global_bonus[$uid]['bonus'];
                        }
                    }
                    unset($value);
                    // 查询订单项表
                    $order_item_list = $order_item->where([
                        'order_id' => $v['order_id']
                    ])->select();
                    foreach ($order_item_list as $key_item => $v_item) {
                        //订单商品的分红
                        $bonus_goods_info = $bonus->getInfo(['website_id'=>$condition['website_id'],'buyer_id'=>$uid,'order_goods_id'=>$v_item['order_goods_id'],'order_id'=>$v['order_id']],'(team_bonus+area_bonus+global_bonus) as bonus');//自购订单
                        $order_item_list[$key_item]['bonus'] = $bonus_goods_info['bonus'];
                        // 查询商品sku表开始
                        $goods_sku_info = $goods_sku->getInfo([
                            'sku_id' => $v_item['sku_id']
                        ], 'code,attr_value_items');
                        $order_item_list[$key_item]['code'] = $goods_sku_info['code'];
                        $order_item_list[$key_item]['spec'] = [];
                        if ($goods_sku_info['attr_value_items']) {
                            $goods_spec_value = new VslGoodsSpecValueModel();
                            $spec_info = [];
                            $sku_spec_info = explode(';', $goods_sku_info['attr_value_items']);
                            foreach ($sku_spec_info as $k_spec => $v_spec) {
                                $spec_value_id = explode(':', $v_spec)[1];
                                $sku_spec_value_info = $goods_spec_value::get($spec_value_id, ['goods_spec']);
                                $spec_info[$k_spec]['spec_value_name'] = $sku_spec_value_info['spec_value_name'];
                                $spec_info[$k_spec]['spec_name'] = $sku_spec_value_info['goods_spec']['spec_name'];
                            }
                            unset($v_spec);
                            $order_item_list[$key_item]['spec'] = $spec_info;
                            unset($sku_spec_value_info, $goods_sku_info, $sku_spec_info, $spec_info);
                        }
                        // 查询商品sku结束

                        $goods_picture = $picture->get($v_item['goods_picture']);
                        if (empty($goods_picture)) {
                            $goods_picture = array(
                                'pic_cover' => '',
                                'pic_cover_big' => '',
                                'pic_cover_mid' => '',
                                'pic_cover_small' => '',
                                'pic_cover_micro' => '',
                                'upload_type' => 1,
                                'domain' => ''
                            );
                        }
                        $order_item_list[$key_item]['picture'] = $goods_picture;
                        if ($v_item['refund_status'] != 0) {
                            $order_refund_status = OrderStatus::getRefundStatus();
                            foreach ($order_refund_status as $k_status => $v_status) {

                                if ($v_status['status_id'] == $v_item['refund_status']) {
                                    if ($v_item['refund_type'] == 1 && $v_status['status_id'] == 1) {
                                        //去除处理退货申请
                                        unset($v_status['new_refund_operation'][1]);
                                    } elseif ($v_item['refund_type'] == 2 && $v_status['status_id'] == 1) {
                                        //去除处理退款申请
                                        unset($v_status['new_refund_operation'][0]);
                                    }
                                    $order_item_list[$key_item]['refund_operation'] = $v_status['refund_operation'];
                                    $order_item_list[$key_item]['new_refund_operation'] = $v_status['new_refund_operation'];
                                    $order_item_list[$key_item]['status_name'] = $v_status['status_name'];
                                }
                            }
                            unset($v_status);
                        } else {
                            $order_item_list[$key_item]['refund_operation'] = '';
                            $order_item_list[$key_item]['new_refund_operation'] = '';
                            $order_item_list[$key_item]['status_name'] = '';
                        }
                        $order_item_list[$key_item]['refund_type'] = $v_item['refund_type'];
                    }
                    unset($v_item);
                    $order_list['data'][$k]['order_item_list'] = $order_item_list;
                    $order_list['data'][$k]['operation'] = '';
                    // 订单来源名称
                    $order_from = OrderStatus::getOrderFrom($v['order_from']);
                    $order_list['data'][$k]['order_from_name'] = $order_from['type_name'];
                    $order_list['data'][$k]['order_from_tag'] = $order_from['tag'];
                    $order_list['data'][$k]['pay_type_name'] = OrderStatus::getPayType($v['payment_type']);
                    if(getAddons('shop', $this->website_id)){
                        $shop_model = new VslShopModel();
                        $shop_info = $shop_model->getInfo(['shop_id' => $order_list['data'][$k]['shop_id']], 'shop_name');
                        $order_list['data'][$k]['shop_name'] = $shop_info['shop_name'];
                    }else{
                        $order_list['data'][$k]['shop_name'] = $this->mall_name;
                    }
                    if ($order_list['data'][$k]['shipping_type'] == 1) {
                        $order_list['data'][$k]['shipping_type_name'] = '商家配送';
                    } elseif ($order_list['data'][$k]['shipping_type'] == 2) {
                        $order_list['data'][$k]['shipping_type_name'] = '门店自提';
                    } else {
                        $order_list['data'][$k]['shipping_type_name'] = '';
                    }
                    // 根据订单类型判断订单相关操作
                    if ($order_list['data'][$k]['payment_type'] == 6 || $order_list['data'][$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus();
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus();
                    }

                    // 查询订单操作
                    foreach ($order_status as $k_status => $v_status) {

                        if ($v_status['status_id'] == $v['order_status']) {
                            $order_list['data'][$k]['operation'] = $v_status['operation'];
                            $order_list['data'][$k]['member_operation'] = $v_status['member_operation'];
                            $order_list['data'][$k]['status_name'] = $v_status['status_name'];
                            $order_list['data'][$k]['is_refund'] = $v_status['is_refund'];
                        }
                    }
                    unset($v_status);
                }
                unset($v);
            }
            return $order_list;
        }else{
            $order_list['data'] = [];
            return $order_list;
        }
    }

    /*
     * 验证支付密码
     */
    public function checkPayPw($uid,$payPw){
        if(!$uid || !$payPw){
            return -1006;
        }
        $user = new UserModel();
        $userInfo = $user->getInfo(['uid'=>$uid],'payment_password');
        if(!$userInfo){
            return 0;
        }
        if($userInfo['payment_password']!=$payPw){
            return -8000;
        }
        return 1;
    }

    /*
     * 访客记录
     */
    public function uvRecord($visitor,$shopId = 0){
        if(!$visitor){
            return;
        }
        $uid = Cookie::get('uv-'.$this->website_id.'-'.$shopId. '-visitor'.$visitor);
        if($uid){
            return;
        }
        $endTime = strtotime(date('Ymd')) + 86400;
        $nowTime = strtotime(date('YmdHis'));
        Cookie::set('uv-'.$this->website_id.'-'.$shopId. '-visitor'.$visitor,$visitor,intval($endTime-$nowTime));
        $nsNvRecordModel = new VslNvRecordModel();
        $startTime = strtotime(date('Ymd'));//当天0点时间戳
        //当天24点时间戳
        $data = [
            'website_id' => $this->website_id,
            'shop_id' => $shopId,
            'visitor' => $visitor
        ];
        $condition = $data;
        $condition['create_time'] = [['>=', $startTime],['<', $endTime]];
        $checkRecord = $nsNvRecordModel->getInfo($condition);
        if($checkRecord){
            return;
        }
        $data['create_time'] = time();
        return $nsNvRecordModel->save($data);
    }
    public function getMemberGroupList($page_index, $page_size, $condition, $order, $field='*')
    {
        $member_group = new VslMemberGroupModel();
        $member_fans = new VslMemberModel();
        $list = $member_group->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach ($list ['data'] as $k => $v) {
            $list['data'][$k]['count'] = $member_fans->where('website_id='.$this->website_id.' and FIND_IN_SET('.$v['group_id'].',group_id)')->count();
        }
        unset($v);
        return $list;
    }
    public function updateGroupName($group_id,$group_name,$is_label,$label_condition,$order_money,$order_pay,$point,$balance,$goods_id,$labelconditions,$website_id)
    {
        $member_group = new VslMemberGroupModel();
        $res = $member_group->save(['group_name'=>$group_name,'is_label'=>$is_label,'label_condition'=>$label_condition,'order_money'=>$order_money,'order_pay'=>$order_pay,'point'=>$point,'balance'=>$balance,'goods_id'=>$goods_id,'labelconditions'=>$labelconditions],['group_id'=>$group_id,'website_id'=>$website_id]);
        return $res;
    }
    public function addGroup($group_name,$is_label,$label_condition,$order_money,$order_pay,$point,$balance,$goods_id,$labelconditions, $website_id)
    {
        $member_group = new VslMemberGroupModel();
        $res = $member_group->save(['group_name'=>$group_name,'is_label'=>$is_label,'label_condition'=>$label_condition,'order_money'=>$order_money,'order_pay'=>$order_pay,'point'=>$point,'balance'=>$balance,'goods_id'=>$goods_id,'labelconditions'=>$labelconditions,'website_id'=>$website_id]);
        return $res;
    }
    public function checkLabel($group_name)
    {
        $member_group = new VslMemberGroupModel();
        $res = $member_group->getInfo(['group_name'=>$group_name,'website_id'=>$this->website_id],'group_id');
        return $res;
    }
    public function addMemberGrowthNum($pay_num,$complete_num,$recharge_num,$recharge_money,$order_money,$recharge_multiple,$order_multiple,$website_id)
    {
        $member_group = new WebSiteModel();
        $res = $member_group->save(['pay_num'=>$pay_num,'complete_num'=>$complete_num,'recharge_num'=>$recharge_num,'recharge_money'=>$recharge_money,'order_money'=>$order_money,'recharge_multiple'=>$recharge_multiple,'order_multiple'=>$order_multiple],['website_id'=>$website_id]);
        return $res;
    }
    public function getMemberGrowthNum($website_id)
    {
        $member_group = new WebSiteModel();
        $info = $member_group->getInfo(['website_id'=>$website_id],'*');
        return $info;
    }
    public function updateMemberGroup($check_uid,$group_id,$website_id)
    {
        $member = new VslMemberModel();
        $res = $member->save(['group_id'=>$group_id],['uid'=>['in',$check_uid],'website_id'=>$website_id]);
        return $res;
    }
    public function getMemberGroupInfo($group_id)
    {
        $member = new VslMemberGroupModel();
        $res = $member->getInfo(['group_id'=>$group_id],'*');
        if($res['goods_id']){
            $goodsSer = new Goods();
            $goodsInfo = $goodsSer->getGoodsDetailById($res['goods_id'],'*',1);
            $res['goods_name'] = $goodsInfo['goods_name'];
            $res['pic'] = $goodsInfo['album_picture']['pic_cover'];
        }
        return $res;
    }
    public function delGroup($id)
    {
        $group = new VslMemberGroupModel();
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['group_id'=>[['=', $id], ['like', '%'.$id],['like', '%'.$id.'%'],['like', $id.'%'], 'or']]);
        if($member_info){
            return -2;
        }
        $res = $group->delData(['group_id'=>$id]);
        return $res;
    }
    /**
     * 我的奖品
     */
    public function getMemberPrize($page_index, $page_size, $condition, $order = '')
    {
        $member_prize = new VslMemberPrizeModel();
        $goodsSer = new Goods();
        $uid = $this->uid;
        $list = [];
        if (!empty($uid)) {
            $state = $condition['state'];
            if ($state == 3) {
                $condition['state']  = ['neq',2];
                $condition['expire_time'] = ['elt', time()];
            } else if($state == 1 || $state == 2){
                $condition['expire_time'] = ['egt', time()];
            }
            $prize_list = $member_prize->pageQuery($page_index, $page_size, $condition, $order, '*');
            $list['data'] = [];
            if(!empty($prize_list['data'])){
                $order_model = new VslOrderModel();
                foreach ($prize_list['data'] as $k => $v) {
                    $list['data'][$k]['member_prize_id'] = $v['member_prize_id'];
                    $list['data'][$k]['shop_id'] = $v['shop_id'];
                    $list['data'][$k]['state'] = $state ?$state: $v['state'];
                    $list['data'][$k]['prize_time'] = $v['prize_time'];
                    $list['data'][$k]['expire_time'] = $v['expire_time'];
                    $list['data'][$k]['prize_name'] = $v['prize_name'];
                    $list['data'][$k]['term_name'] = $v['term_name'];
                    $list['data'][$k]['point'] = $v['point'];
                    $list['data'][$k]['money'] = $v['money'];
                    $list['data'][$k]['pic'] = __IMG($v['pic']);
                    $list['data'][$k]['type'] = $v['type'];
                    $list['data'][$k]['activity_type'] = $v['activity_type'];
                    $list['data'][$k]['receive_id'] = $v['receive_id'];
                    $list['data'][$k]['is_receive'] = ($state==1)?1:0;
                    $activity_name = '';
                    if($v['activity_type']==1)$activity_name = '砸金蛋';
                    if($v['activity_type']==2)$activity_name = '大转盘';
                    if($v['activity_type']==3)$activity_name = '刮刮乐';
                    if($v['activity_type']==4)$activity_name = '支付有礼';
                    if($v['activity_type']==5)$activity_name = '关注有礼';
                    if($v['activity_type']==6)$activity_name = '节日关怀';
                    $list['data'][$k]['activity_name'] = $activity_name;
                    if($v['activity_order_id']>0 && $v['activity_type']==4){
                        $list['data'][$k]['is_receive'] = 0;
                        $order_info = $order_model->getInfo(['order_id' => $v['activity_order_id']], "order_id,order_status,pay_gift_status");
                        if($order_info['order_status']==4 && $order_info['pay_gift_status']==1){
                            $list['data'][$k]['is_receive'] = 1;
                        }
                    }
                    if($v['type'] == 5) {
                        //如果赠送的是商品，就查出商品类型返回给前端
                        $list['data'][$k]['goods_type'] = $goodsSer->getGoodsDetailById($v['type_id'], 'goods_id,goods_name,price,collects,goods_type')['goods_type'];
                    }
                }
                unset($v);
            }
            $list['total_count'] = $prize_list['total_count'];
            $list['page_count'] = $prize_list['page_count'];
        }
        return $list;
    }
    /**
     * 我的奖品详情
     */
    public function prizeDetail($member_prize_id)
    {
        $condition['member_prize_id'] = $member_prize_id;
        $condition['uid'] = $this->uid;
        $condition['website_id'] = $this->website_id;
        $member_prize = new VslMemberPrizeModel();
        $prize_info = $member_prize->getInfo($condition);
        $info = [];
        if(!empty($prize_info)){
            $info['member_prize_id'] = $prize_info['member_prize_id'];
            $info['shop_id'] = $prize_info['shop_id'];
            if($prize_info['expire_time']>=time() && $prize_info['state']==1){
                $info['state'] = $prize_info['state'];
            }else{
                $info['state'] = 3;
            }
            $info['prize_time'] = $prize_info['prize_time'];
            $info['expire_time'] = $prize_info['expire_time'];
            $info['prize_name'] = $prize_info['prize_name'];
            $info['term_name'] = $prize_info['term_name'];
            $info['name'] = $prize_info['name'];
            $info['point'] = $prize_info['point'];
            $info['money'] = $prize_info['money'];
            $info['pic'] = __IMG($prize_info['pic']);
            $info['type'] = $prize_info['type'];
            $info['type_id'] = $prize_info['type_id'];
            $info['activity_type'] = $prize_info['activity_type'];
            $activity_name = '';
            if($prize_info['activity_type']==1)$activity_name = '砸金蛋';
            if($prize_info['activity_type']==2)$activity_name = '大转盘';
            if($prize_info['activity_type']==3)$activity_name = '刮刮乐';
            if($prize_info['activity_type']==4)$activity_name = '支付有礼';
            if($prize_info['activity_type']==5)$activity_name = '关注有礼';
            if($prize_info['activity_type']==6)$activity_name = '节日关怀';
            $info['activity_name'] = $activity_name;
        }
        return $info;
    }
    /**
     * 领奖
     */
    public function acceptPrize($member_prize_id)
    {
        $member_prize = new VslMemberPrizeModel();
        $condition = [];
        $condition['member_prize_id'] = $member_prize_id;
        $info = $member_prize->getInfo($condition,'uid,website_id,type,type_id,point,money,state,expire_time,shop_id,member_prize_id,activity_id,activity_type,activity_order_id');
        $member_prize->startTrans();
        try {
        if(!empty($info)){
            $uid = $info['uid'];
            $website_id = $info['website_id'];
            $user_model = new UserModel();
            $user_info = $user_model->getInfo(['uid' => $uid],'user_status,nick_name');
            if ($user_info['user_status'] == 0) {
                return ['code' => -1, 'message' => '当前用户状态不能领取'];
            }
            if($info['activity_type']==4){
                $vsl_paygift = new VslPayGiftModel();
                $paygift_info = $vsl_paygift->getInfo(['pay_gift_id'=>$info['activity_id']],'grant_node');
                if(!empty($paygift_info)){
                    $order_model = new VslOrderModel();
                    $order_info = $order_model->getInfo(['order_id' => $info['activity_order_id']], "order_status,order_no");
                    if($order_info['order_status']==5){
                        return ['code' => -1, 'message' => '订单'.$order_info['order_no'].'已关闭，不能领取'];
                    }
                    if($paygift_info['grant_node']==1 && $order_info['order_status']==0){
                        return ['code' => -1, 'message' => '订单'.$order_info['order_no'].'未支付，不能领取'];
                    }
                    if($paygift_info['grant_node']==2 && $order_info['order_status']!=4){
                        return ['code' => -1, 'message' => '订单'.$order_info['order_no'].'未完成，不能领取'];
                    }
                }
            }
            $activity_name = '';
            if($info['activity_type']==1)$activity_name = '砸金蛋';
            if($info['activity_type']==2)$activity_name = '大转盘';
            if($info['activity_type']==3)$activity_name = '刮刮乐';
            if($info['activity_type']==4)$activity_name = '支付有礼';
            if($info['activity_type']==5)$activity_name = '关注有礼';
            if($info['activity_type']==6)$activity_name = '节日关怀';
            if($info['state']==1 && $info['expire_time']>=time()){
                if($info['type']==1){//余额
                    $member_account = new VslMemberAccountModel();
                    $where = [];
                    $where['uid'] = $uid;
                    $where['website_id'] = $website_id;
                    $result = $member_account->where($where)->setInc('balance', $info['money']);
                    if($result){
                        $records = new VslMemberAccountRecordsModel();
                        $data['uid'] = $uid;
                        $data['shop_id'] = 0;
                        $data['account_type'] = 2;
                        $data['sign'] = 0;
                        $data['number'] = $info['money'];
                        $data['from_type'] = 18;
                        $data['data_id'] = $member_prize_id;
                        $data['text'] = $activity_name.'获得余额';
                        $data['create_time'] = time();
                        $data['website_id'] = $website_id;
                        $data['records_no'] = 'Ac'.getSerialNo();
                        $result = $records->save($data);
                        $params = ['uid'=>$uid,'records_no'=>$data['records_no'],'money'=>$info['money']];
                        runhook('Notify', 'successacceptPrizeByTemplate', $params);
                    }
                }
                if($info['type']==2){//积分
                    $member_account = new VslMemberAccountModel();
                    $where = [];
                    $where['uid'] = $uid;
                    $where['website_id'] = $website_id;
                    $result = $member_account->where($where)->setInc('point', $info['point']);
                    if($result){
                        $records = new VslMemberAccountRecordsModel();
                        $data['uid'] = $uid;
                        $data['shop_id'] = 0;
                        $data['account_type'] = 1;
                        $data['sign'] = 0;
                        $data['number'] = $info['point'];
                        $data['from_type'] = 17;
                        $data['data_id'] = $member_prize_id;
                        $data['text'] = $activity_name.'获得积分';
                        $data['create_time'] = time();
                        $data['website_id'] = $website_id;
                        $data['records_no'] = 'Ac'.getSerialNo();
                        $result = $records->save($data);
                    }
                }
                if($info['type']==3){//优惠券
                    $coupon = new CouponServer();
                    $result = $coupon->getUserThaw($uid,$info['type_id']);
                }
                if($info['type']==4){//礼品券
                    $voucher = new VoucherServer();
                    $result = $voucher->getUserThaw($uid,$info['type_id']);
                }
                if($info['type']==5){//单个商品
                    $order_business = new OrderBusiness();
                    $sku_model = new VslGoodsSkuModel();
                    $address_id = (int)input('address_id');
                    $card_store_id = (int)input('card_store_id');
                    $order_from = input('order_from',2);
                    // 订单来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
                    $order_from = $order_from?$order_from: 2;
                    $ip = get_ip();
                    $shipping_time = time();
                    $address = $this->getMemberExpressAddressDetail($address_id);
                    $shop_info = [];
                    if(getAddons('shop', $website_id)){
                        $shop_model = new VslShopModel();
                        $shop_info = $shop_model->getInfo(['shop_id' => $info['shop_id'], 'website_id' => $website_id],'shop_name');
                    }
                    $goodsSer = new Goods();
                    $goods_info = $goodsSer->getGoodsDetailById($info['type_id']);
                    $goods_type = ($goods_info)?$goods_info['goods_type']:0;
                    $order_info = [];
                    $sku_info = [];
                    $sku_info['goods_type'] = $goods_type;
                    if($goods_type==1){
                        $order_info['shipping_type'] = 1;
                        if ($address_id==0) {
                            return ['code' => -1, 'message' => '请选择收货地址'];
                        }
                    }else if($goods_type==0){//计时计次商品
                        $order_info['shipping_type'] = 2;
                        $order_info['card_store_id'] = $card_store_id;
                        if ($card_store_id==0) {
                            return ['code' => -1, 'message' => '请选择门店'];
                        }
                        $store = new Store();
                        $store_info = $store->storeDetail($card_store_id);
                        $address = [];
                        $address['province'] = $store_info['province_id'];
                        $address['city'] = $store_info['city_id'];
                        $address['district'] = $store_info['district_id'];
                        $address['address'] = $store_info['address'];
                        $sku_info['card_store_id'] = $card_store_id;
                        $sku_info['cancle_times'] = $goods_info['cancle_times'];
                        $sku_info['cart_type'] = $goods_info['cart_type'];
                        if($goods_info['valid_type']==1){
                            $sku_info['invalid_time'] = time()+$goods_info['valid_days']*24*60*60;
                        }else{
                            $sku_info['invalid_time'] = $goods_info['invalid_time'];
                        }
                        if($goods_info['is_wxcard']==1){
                            $sku_info['wx_card_id'] = $goods_info['wx_card_id'];
                            $ticket = new VslGoodsTicketModel();
                            $ticket_info = $ticket->getInfo(['goods_id'=>$goods_info['goods_id']],'card_title');
                            $sku_info['card_title'] = $ticket_info['card_title'];
                        }
                    }else if($goods_type==3){
                        $order_info['shipping_type'] = 1;
                    }
                    $sku = $sku_model::get(['goods_id'=>$info['type_id']]);
                    if (empty($sku['sku_id'])) {
                        return ['code' => -1, 'message' => '暂无商品信息'];
                    }
                    if ($sku['stock']==0) {
                        return ['code' => -1, 'message' => '领取失败'];
                    }
                    $sku_db_info = $sku_model::get($sku['sku_id'], ['goods']);
                    $sku_info['sku_id'] = $sku_db_info['sku_id'];
                    $sku_info['goods_id'] = $sku_db_info['goods_id'];
                    $sku_info['price'] = 0;
                    $sku_info['member_price'] = 0;
                    $sku_info['num'] = 1;
                    $sku_info['shop_id'] = $sku_db_info->goods->shop_id;
                    //自定义表单数据
                    $order_info['custom_order'] = '';
                    $order_info['website_id'] = $website_id;
                    $order_info['shop_id'] = $info['shop_id'];
                    $order_info['shop_name'] = $shop_info['shop_name'] ? :$this->mall_name;
                    $order_info['gift_id'] = 0;
                    $order_info['order_from'] = $order_from;
                    $order_info['order_no'] = $order_business->createOrderNo($info['shop_id']);
                    $order_info['sku_info'][] = $sku_info;
                    $order_info['pay_type'] = 0;
                    $order_info['order_type'] = 9;
                    $order_info['ip'] = $ip;
                    $order_info['buyer_invoice'] = '';
                    $order_info['shipping_time'] = $shipping_time;
                    $order_info['receiver_mobile'] = (!empty($address['mobile']))?$address['mobile']:'';
                    $order_info['receiver_province'] = (!empty($address['province']))?$address['province']:'';
                    $order_info['receiver_city'] = (!empty($address['city']))?$address['city']:'';
                    $order_info['receiver_district'] = (!empty($address['district']))?$address['district']:'';
                    $order_info['receiver_address'] = (!empty($address['address']))?$address['address']:'';
                    $order_info['receiver_zip'] = (!empty($address['zip_code']))?$address['zip_code']:'';
                    $order_info['receiver_name'] = (!empty($address['consigner']))?$address['consigner']:'';
                    $order_info['create_time'] = time();
                    $order_info['buyer_id'] = $uid;
                    $order_info['nick_name'] = $user_info['nick_name'];
                    $order_business = new OrderBusiness();
                    $result = $order_business->orderCreateReceive($order_info);
                    if($result>0){
                        //创建成功后，判断当前订单是否是计时/次商品
                        if ($order_info['card_store_id']>0) {
                            //消费卡发放
                            $member_card = new MemberCard();
                            $rs = $member_card->saveData($result);
                            if($rs){
                                // 修改订单状态
                                $order = new VslOrderModel();
                                $order->save(['order_status'=>4,'card_ids'=>$rs], ['order_id' => $result]);
                                $ServiceOrder = new ServiceOrder();
                                $ServiceOrder->orderComplete($result);
                            }
                        }
                    }
                    Log::write($result);
                }
                if($info['type']==6){//单个赠品
                    $order_business = new OrderBusiness();
                    $gift_server = new GiftServer();
                    $address_id = (int)input('address_id');
                    $order_from = input('order_from',2);
                    if ($address_id==0) {
                        return ['code' => -1, 'message' => '缺少收货地址'];
                    }
                    // 订单来源,1 微信浏览器,4 ios,5 Android,6 小程序,2 手机浏览器,3 PC
                    $order_from = $order_from?$order_from: 2;
                    $ip = get_ip();
                    $shipping_time = time();
                    $address = $this->getMemberExpressAddressDetail($address_id);
                    $order_no = $order_business->createOrderNo($info['shop_id']);
                    if(getAddons('shop', $this->website_id)){
                        $shop_model = new VslShopModel();
                        $shop_info = $shop_model->getInfo(['shop_id' => $info['shop_id'], 'website_id' => $website_id],'shop_name');
                    }else{
                        $shop_info['shop_name'] = $this->mall_name;
                    }
                    $gift = $gift_server->giftDetail($info['type_id']);
                    if (empty($gift['promotion_gift_id'])) {
                        return ['code' => -1, 'message' => '暂无赠品信息'];
                    }
                    if ($gift['sended']>=$gift['stock']) {
                        return ['code' => -1, 'message' => '领取失败'];
                    }
                    $input = [];
                    $input['uid'] = $uid;
                    $input['type'] = 3;
                    $input['num'] = 1;
                    $input['no'] = $order_no;
                    $input['promotion_gift_id'] = $gift['promotion_gift_id'];
                    $giftId = $gift_server->addGiftRecord($input);
                    if($giftId==false){
                        return ['code' => -1, 'message' => '领取失败'];
                    }
                    $order_info = [];
                    //自定义表单数据
                    $order_info['custom_order'] = '';
                    $order_info['website_id'] = $website_id;
                    $order_info['shop_id'] = $info['shop_id'];
                    $order_info['shop_name'] = $shop_info['shop_name'];
                    $order_info['gift_id'] = $giftId;
                    $order_info['order_from'] = $order_from;
                    $order_info['order_no'] = $order_no;
                    $order_info['pay_type'] = 0;
                    $order_info['shipping_type'] = 1;
                    $order_info['order_type'] = 9;
                    $order_info['ip'] = $ip;
                    $order_info['buyer_invoice'] = '';
                    $order_info['shipping_time'] = $shipping_time;
                    $order_info['receiver_mobile'] = $address['mobile'];
                    $order_info['receiver_province'] = $address['province'];
                    $order_info['receiver_city'] = $address['city'];
                    $order_info['receiver_district'] = $address['district'];
                    $order_info['receiver_address'] = $address['address'];
                    $order_info['receiver_zip'] = $address['zip_code'];
                    $order_info['receiver_name'] = $address['consigner'];
                    $order_info['create_time'] = time();
                    $order_info['buyer_id'] = $uid;
                    $order_info['nick_name'] = $user_info['nick_name'];
                    $order_business = new OrderBusiness();
                    $result = $order_business->orderCreateReceive($order_info);
                    Log::write($result);
                }
                if($result>0){
                    $member_prize->where($condition)->update(['state' => 2,'receive_id'=>$result]);
                    $member_prize->commit();
                    return ['code' => 1, 'message' => '领取成功'];
                }
            }
        }
        } catch (\Exception $e) {
            $member_prize->rollback();
            return $e->getMessage();
        }
        return ['code' => -1, 'message' => '领取失败!'];
    }
    public function updateMemberDistributor($uid)
    {
        $member = new Distributor();
        $res = $member->updateMemberDistributor($uid);
        return $res;
    }
    //设为股东
    public function updateMemberGlobal($uid)
    {
        $member = new GlobalBonus();
        $res = $member->setStatus($uid,2);
        return $res;
    }
    //设为区代
    public function updateMemberArea($uid)
    {
        $member = new AreaBonus();
        $res = $member->setStatus($uid,2);
        return $res;
    }
    //设为队长
    public function updateMemberTeam($uid)
    {
        $member = new TeamBonus();
        $res = $member->setStatus($uid,2);
        return $res;
    }
    //设为渠道商
    public function updateMemberChannel($uid)
    {
        $member = new ChannelServer();
        $res = $member->setStatus($uid);
        return $res;
    }
    //设为店长
    public function updateMemberMicroshop($uid)
    {
        $member = new MicroShopService();
        $res = $member->setStatus($uid);
        return $res;
    }

    /**
     * 获取小程序充值模板消息fomr_id
     * @param $out_trade_no [支付流水号]
     * @return string
     */
    public function getRechargeFormIdByOutTradeNo($out_trade_no)
    {
        $member_recharge = new VslMemberRechargeModel();
        $result = $member_recharge->getInfo(['website_id' => $this->website_id, 'out_trade_no' => $out_trade_no], 'form_id');
        if ($result['form_id']) {
            return $result['form_id'];
        }
        return '';
    }
    /*
     * 后台添加会员(non-PHPdoc)
     */
    public function registerPlaMember($data = array())
    {
        #导入写死 1

        try{
            $this->website_id = $this->website_id ?: Session::get('shopwebsite_id');
             $params = [
                 'password'              => $data['password'],
                 'mobile'                => $data['mobile'],
                 'is_system'             => 0,
                 'is_member'             => 1,
                 'instance_id'           => 0,
                 'website_id'            => $this->website_id,
                 'pic'                   => $data['pic'],
                 'nickname'              => $data['nickname'],
             ];
            $res = parent::add($params);

            if ($res > 0) {
                // 获取默认会员等级id
                $member_level_id = $data['level_id'];
                $member = new VslMemberModel();
                $referee_id = $data['referee_id'];
                $data = array(
                    'uid' => $res,
                    'member_level' => $member_level_id,
                    'referee_id' => $referee_id,
                    'mobile' => $data['mobile'],
                    'reg_time' => time(),
                    'website_id' => $this->website_id
                );
                $member->save($data);
                // 查看是否是海报/任务的推荐场景
                // 添加会员账户
                $member_account = new VslMemberAccountModel();
                $data1 = array(
                    'uid' => $res,
                    'website_id' => $this->website_id
                );
                $member_account->save($data1);

                $distributionStatus = getAddons('distribution', $this->website_id);

                if($distributionStatus == 1 && $referee_id && $res){

                    //更新推荐人分销分红信息
                    $distribution = new Distributor();
                    $distribution->updateDistributorLevelInfo($referee_id);
                    if(getAddons('globalbonus', $this->website_id)){
                        $global = new GlobalBonus();
                        $global->updateAgentLevelInfo($referee_id);
                    }
                    if(getAddons('areabonus', $this->website_id)){
                        $area = new AreaBonus();
                        $area->updateAgentLevelInfo($referee_id);
                    }
                    if(getAddons('teambonus', $this->website_id)){
                        $team = new TeamBonus();
                        $team->updateAgentLevelInfo($referee_id);
                    }
                }
                //注册成功后短信与邮箱提醒
                $params['shop_id'] = $this->instance_id;
                $params['user_id'] = $res;
                $params['website_id'] = $this->website_id;
                $params['notify_type'] = 'user';
                $params['template_code'] = 'after_register';
                runhook('Notify', 'registAfterBySms', $params);
                runhook('Notify', 'emailSend', $params);
                // 直接登录
            }
            return $res;
        }catch(\Exception $e){
            debugFile($e->getFile() . '__' . $e->getCode().$e->getLine() . '__' . $e->getMessage(), 'memberImport-手机号注册失败-e', 1111113);
            return $e->getMessage();
        }
    }

    /**
     * 查询会员信息
     * @param array  $condition
     * @param string $field
     * @param string $order
     * @return array
     */
    public function getMemberData ($condition =[], $field='*', $order='')
    {
        $member = new VslMemberModel();
        $data = $member->getQuery($condition, $field, $order);
        return objToArr($data);
    }

    /**
     * 获取对应会员等级下所有用户信息
     * @param  string  $member_level_id [会员等级] eg:  1,2,3
     * @param string $user_field [查询会员信息字段]
     * @param bool   $can_not_empty [是否可以查询的字段为空false可以  true不可以]
     * @param string  $website_id [商家实例]
     * @return array 查询的字段数组
     */
    public function getMemberLevelOfUsers ($member_level_id, $user_field='*',$can_not_empty = false, $website_id ='', $offset=0, $limit=0)
    {

        $website_id = $website_id ?: $this->website_id;
        $condition = [
            'website_id'        => $website_id,
            'member_level'      => ['in', $member_level_id]
        ];
        $uidsRes        = $this->getMemberData($condition, 'uid');
        //循环uid查询用户信息
        $uidsArr        = array_column($uidsRes, 'uid');
        $uids_str       = implode($uidsArr,',');
        // 查询会员信息
        $userSer = new User();
        $fieldArr = explode(',',$user_field);
        if ($can_not_empty) {
            foreach ($fieldArr as $field){
                $user_condition[$field] = ['<>', ''];
            }
            unset($field);
            $user_condition['website_id'] = $website_id;
            $user_condition['uid'] = ['in', $uids_str];
        } else {
            $user_condition = [
                'website_id'        => $website_id,
                'uid'               => ['in', $uids_str]
            ];
        }
        $userFieldsRes = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
        return $userFieldsRes;//查询的字段数组
    }

    /**
     * 获取对应会员等级下所有用户数量
     * @param  string  $member_level_id [会员等级] eg:  1,2,3
     * @param string $user_field [查询会员信息字段]
     * @param bool   $can_not_empty [是否可以查询的字段为空false可以  true不可以]
     * @param string  $website_id [商家实例]
     * @return array 查询的字段数组
     */
    public function getMemberLevelOfUsersCount ($member_level_id, $user_field='*',$can_not_empty = false, $website_id ='')
    {

        $website_id = $website_id ?: $this->website_id;
        $condition = [
            'website_id'        => $website_id,
            'member_level'      => ['in', $member_level_id]
        ];
        $uidsRes        = $this->getMemberData($condition, 'uid');
        //循环uid查询用户信息
        $uidsArr        = array_column($uidsRes, 'uid');
        $uids_str       = implode($uidsArr,',');
        // 查询会员信息
        $userSer = new User();
        $fieldArr = explode(',',$user_field);
        if ($can_not_empty) {
            foreach ($fieldArr as $field){
                $user_condition[$field] = ['<>', ''];
            }
            unset($field);
            $user_condition['website_id'] = $website_id;
            $user_condition['uid'] = ['in', $uids_str];
        } else {
            $user_condition = [
                'website_id'        => $website_id,
                'uid'               => ['in', $uids_str]
            ];
        }
        $user_count = $userSer->getUserCount($user_condition);
        return $user_count;//查询的字段数组
    }

    /**
     * 获取对应会员标签下所有用户信息（一个用户有多个标签）
     * @param  int  $group_id eg: 1,2,3
     * @param string $user_field
     * @param bool   $can_not_empty [是否可以查询的字段为空false可以  true不可以]
     * @param string  $website_id [商家实例]
     * @return array
     */
    public function getMemberGroupIdOfUsers ($group_id, $user_field='*',$can_not_empty = false,$website_id='', $offset=0, $limit=0)
    {
        $website_id = $website_id ?: $this->website_id;
        $condition['website_id'] = $website_id;
        $condition[] = ['exp', "concat(',',group_id,',') regexp concat(',$group_id,')"];//正则匹配有交集的数据

        $uidsRes        = $this->getMemberData($condition, 'uid');
        //循环uid查询用户信息
        $uidsArr        = array_column($uidsRes, 'uid');
        $uids_str       = implode($uidsArr,',');
        // 查询会员信息
        $userSer = new User();
        $fieldArr = explode(',',$user_field);
        if ($can_not_empty) {
            foreach ($fieldArr as $field){
                $user_condition[$field] = ['<>', ''];
            }
            unset($field);
            $user_condition['website_id'] = $website_id;
            $user_condition['uid'] = ['in', $uids_str];
        } else {
            $user_condition = [
                'website_id'        => $website_id,
                'uid'               => ['in', $uids_str]
            ];
        }

        $userFieldsRes = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
        return $userFieldsRes;//查询的字段数组
    }
    /**
     * 获取对应会员标签下所有用户数量（一个用户有多个标签）
     * @param  int  $group_id eg: 1,2,3
     * @param string $user_field
     * @param bool   $can_not_empty [是否可以查询的字段为空false可以  true不可以]
     * @param string  $website_id [商家实例]
     * @return array
     */
    public function getMemberGroupIdOfUsersCount ($group_id, $user_field='*',$can_not_empty = false,$website_id='')
    {
        $website_id = $website_id ?: $this->website_id;
        $condition['website_id'] = $website_id;
        $condition[] = ['exp', "concat(',',group_id,',') regexp concat(',$group_id,')"];//正则匹配有交集的数据

        $uidsRes        = $this->getMemberData($condition, 'uid');
        //循环uid查询用户信息
        $uidsArr        = array_column($uidsRes, 'uid');
        $uids_str       = implode($uidsArr,',');
        // 查询会员信息
        $userSer = new User();
        $fieldArr = explode(',',$user_field);
        if ($can_not_empty) {
            foreach ($fieldArr as $field){
                $user_condition[$field] = ['<>', ''];
            }
            unset($field);
            $user_condition['website_id'] = $website_id;
            $user_condition['uid'] = ['in', $uids_str];
        } else {
            $user_condition = [
                'website_id'        => $website_id,
                'uid'               => ['in', $uids_str]
            ];
        }
        $user_count = $userSer->getUserCount($user_condition);
        return $user_count;//查询的字段数组
    }

    /**
     * 获取对应分销商等级下所有用户信息
     * @param  int  $distributor_level_id [分销商等级] eg:1,2,3
     * @param string $user_field
     * @param bool   $can_not_empty [是否可以查询的字段为空false可以  true不可以]
     * @param string  $website_id [商家实例]
     * @return array
     */
    public function getMemberDistributorLevelOfUser ($distributor_level_id, $user_field='*',$can_not_empty = false, $website_id='', $offset=0, $limit=0)
    {
        $website_id = $website_id ?: $this->website_id;
        $condition = [
            'website_id'            => $website_id,
            'distributor_level_id'  => ['in', $distributor_level_id]
        ];
        $uidsRes        = $this->getMemberData($condition, 'uid');
        //循环uid查询用户信息
        $uidsArr        = array_column($uidsRes, 'uid');
        $uids_str       = implode($uidsArr,',');
        // 查询会员信息
        $userSer = new User();
        $fieldArr = explode(',',$user_field);
        if ($can_not_empty) {
            foreach ($fieldArr as $field){
                $user_condition[$field] = ['<>', ''];
            }
            unset($field);
            $user_condition['website_id'] = $website_id;
            $user_condition['uid'] = ['in', $uids_str];
        } else {
            $user_condition = [
                'website_id'        => $website_id,
                'uid'               => ['in', $uids_str]
            ];
        }
        $userFieldsRes = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
        return $userFieldsRes;//查询的字段数组

    }
    /**
     * 获取对应分销商等级下所有用户数量
     * @param  int  $distributor_level_id [分销商等级] eg:1,2,3
     * @param string $user_field
     * @param bool   $can_not_empty [是否可以查询的字段为空false可以  true不可以]
     * @param string  $website_id [商家实例]
     * @return array
     */
    public function getMemberDistributorLevelOfUserCount ($distributor_level_id, $user_field='*',$can_not_empty = false, $website_id='')
    {
        $website_id = $website_id ?: $this->website_id;
        $condition = [
            'website_id'            => $website_id,
            'distributor_level_id'  => ['in', $distributor_level_id]
        ];
        $uidsRes        = $this->getMemberData($condition, 'uid');
        //循环uid查询用户信息
        $uidsArr        = array_column($uidsRes, 'uid');
        $uids_str       = implode($uidsArr,',');
        // 查询会员信息
        $userSer = new User();
        $fieldArr = explode(',',$user_field);
        if ($can_not_empty) {
            foreach ($fieldArr as $field){
                $user_condition[$field] = ['<>', ''];
            }
            unset($field);
            $user_condition['website_id'] = $website_id;
            $user_condition['uid'] = ['in', $uids_str];
        } else {
            $user_condition = [
                'website_id'        => $website_id,
                'uid'               => ['in', $uids_str]
            ];
        }
        $user_count = $userSer->getUserCount($user_condition);
        return $user_count;//查询的字段数组

    }

     /*
     * 获取提现状态名称
     */
    public function getWithdrawStatusName($status = 0){
        $name = '';
        switch ($status) {
            case 1:
                $name = '待审核';
                break;
            case 2:
                $name = '待打款';
                break;
            case 3:
                $name = '已打款';
                break;
            case 4:
                $name = '已拒绝';
                break;
            case 5:
                $name = '打款失败';
                break;
            case -1:
                $name = '审核不通过';
                break;
            default:
                break;
        }
        return $name;
    }
    public function getMsg($page_index, $page_size, $condition, $order)
    {
        $msg_model = new MsgReminderModel();
        $res = $msg_model->getThingCircleMsgList($page_index, $page_size, $condition, $order);
        return $res;
    }
    /**
     *  消息已读
     */
    public function getReadMsg($input)
    {
        $model = new MsgReminderModel();
        $model->startTrans();
        try {
            $res = $model->update(['is_check'=>1],$input);
            $model->commit();
            return $res;
        } catch (\Exception $e) {
            $model->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取国家列表
     */
    public function getCountryList()
    {
        //启用的国家
        $country_shop_relation_mdl = new VslCountryShopRelationModel();
        $enable_list = $country_shop_relation_mdl->Query(['is_enable' => 1, 'website_id' => $this->website_id], 'country_id');

        if($enable_list) {
            $country_list_model = new VslCountryListModel();
            $list = $country_list_model->getQuery(['id' => ['IN',$enable_list]],'*','id asc');
        }else{
            $list = [];
        }

        return $list;
    }

    /**
     * 过滤文本中的emoji表情包（输出到excel文件中会导致问题）
     * @param string $text 原文本
     * @return string 过滤emoji表情包后的文本
     */
    function removeEmoji($text){
        $len = mb_strlen($text);
        $newText = '';
        for($i=0;$i<$len;$i++){
            $str = mb_substr($text, $i, 1, 'utf-8');
            if(strlen($str) >= 4) continue;//emoji表情为4个字节
            $newText .= $str;
        }
        return $newText;
    }

    /**
     *余额提现列表导出Excel
     */
    public function getMemberBalanceWithdrawToExcel($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $member_balance_withdraw = new VslMemberBalanceWithdrawModel();
        $data = $member_balance_withdraw->getViewList($page_index, $page_size, $condition, $order);
        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $member_balance_withdraw->getViewList($i, $page_size, $condition, $order)['data'];
                if($res['data']) {
                    foreach ($res['data'] as $k => $v) {
                        if(abs($v['service_charge']) != abs($v['cash'])){ //全部提现 修正显示 增加实际扣款  -$records_info['balance'] == 0- 提完了，有剩余 但是可以不够手续费 这种情况不需要加
                            $res['data'][$k]['real_cash'] =  $res['data'][$k]['cash'];
                            $res['data'][$k]['cash'] =  -1*$res['data'][$k]['service_charge'];
                        }else{
                            $res['data'][$k]['real_cash'] =  $res['data'][$k]['cash'] + $res['data'][$k]['charge'];
                        }
                        $res['data'][$k]['ask_for_date'] = date('Y-m-d H:i:s', $v['ask_for_date']);
                        if($res['data'][$k]['payment_date']>0){
                            $res['data'][$k]['payment_date'] = date('Y-m-d H:i:s', $v['payment_date']);
                        }else{
                            $res['data'][$k]['payment_date'] = '未到账';
                        }
                        $res['data'][$k]['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));

                        switch ($v['type']) {
                            case 1:
                                $res['data'][$k]['type']= '银行卡';
                                break;
                            case 2:
                                $res['data'][$k]['type']= '微信';
                                break;
                            case 3:
                                $res['data'][$k]['type']= '支付宝';
                                break;
                            case 4:
                                $res['data'][$k]['type']= '银行卡';
                                break;
                            default:
                                break;
                        }
                        $res['data'][$k]['status']= $this->getWithdrawStatusName($v['status']);
                        $res['data'][$k]['withdraw_no'] = $v['withdraw_no']."\t";
                        $res['data'][$k]['cash'] = '¥'.$v['cash'];
                        $v['real_name'] = $v['realname'] ?: ($v['user_name'] ?: $v['nick_name']);
                        $res['data'][$k]["real_name"] = $this->removeEmoji($v["realname"]);
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'],$res['data']);
                }
            }
        }

        return $list;
    }

    /**
     * 余额流水列表导出Excel
     */
    public function getAccountListToExcel($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new VslMemberAccountRecordsViewModel();
        $member_balance = new VslMemberBalanceWithdrawModel();
        $data = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $member_account->getViewList($i, $page_size, $condition, 'nmar.create_time desc')['data'];
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $k => $v) {
                        if($v['from_type']==8){
                            $withdraw_info = $member_balance->getInfo(['withdraw_no' => $v['data_id']],'type');
                            if(empty($withdraw_info)){
                                $withdraw_info = $member_balance->getInfo(['id' => $v['data_id']],'type');
                            }
                            $payment_type = $withdraw_info['type'];
                            switch($payment_type){
                                case '1':
                                    $pay_name = '银行卡';
                                    break;
                                case '2':
                                    $pay_name = '微信';
                                    break;
                                case '3':
                                    $pay_name = '支付宝';
                                    break;
                                case '4':
                                    $pay_name = '银行卡';
                                    break;
                            }
                            switch($v['status']){
                                case '3':
                                    $status_name = $pay_name.'提现（成功）';
                                    break;
                                case '5':
                                    $status_name = $pay_name.'提现（重新打款）';
                                    break;
                                case '4':
                                    $status_name = $pay_name.'提现（拒绝打款）';
                                    break;
                                case '-1':
                                    $status_name = $pay_name.'提现（审核失败）';
                                    break;
                                case '1':
                                    $status_name = $pay_name.'提现（待审核）';
                                    break;
                                case '2':
                                    $status_name = $pay_name.'提现（待打款）';
                                    break;
                            }
                            $res['data'][$k]['type_name'] = $status_name;
                        }else{
                            $res['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                        }
                        $res['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                        $v["nick_name"] = $v["nick_name"] ? $v["nick_name"] : ( $v["user_name"] ? $v["user_name"] : $v["user_tel"]);
                        $res['data'][$k]["number"] = '¥'.$v["number"];
                        $res['data'][$k]["nick_name"] = $this->removeEmoji($v["nick_name"]);
                        $res['data'][$k]['records_no'] = $v['records_no']."\t";
                    }
                    $list['data'] = array_merge($list['data'],$res['data']);
                    unset($v);
                }
            }
        }

        return $list;
    }

    /**
     * 积分流水列表导出Excel
     */
    public function getPointListToExcel($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new VslMemberAccountRecordsViewModel();
        $data = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $member_account->getViewList($i, $page_size, $condition, 'nmar.create_time desc')['data'];
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $k => $v) {
                        $res['data'][$k]['type_name'] = MemberAccount::getMemberAccountRecordsName($v['from_type']);
                        $res['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                        $v["nick_name"] = $v["nick_name"] ? $v["nick_name"] : ( $v["user_name"] ? $v["user_name"] : $v["user_tel"]);
                        $res['data'][$k]["nick_name"] = $this->removeEmoji($v["nick_name"]);
                        $res['data'][$k]['records_no'] = $v['records_no']."\t";
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'],$res['data']);
                }
            }
        }
        return $list;
    }

    /**
     * 分红流水列表导出Excel
     */
    public function getBonusRecordListToExcel($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new VslAgentAccountRecordsModel();
        $data = $member_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $member_account->getViewList($i, $page_size, $condition, 'nmar.create_time desc')['data'];
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $k => $v) {
                        $res['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                        switch ($v['bonus_type']) {
                            case 1:
                                $res['data'][$k]['bonus_type']='全球分红';
                                break;
                            case 2:
                                $res['data'][$k]['bonus_type']='区域分红';
                                break;
                            case 3:
                                $res['data'][$k]['bonus_type']='团队分红';
                                break;
                        }
                        switch ($v['from_type']) {
                            case 1:
                                $res['data'][$k]['from_type']='订单完成';
                                break;
                            case 2:
                                $res['data'][$k]['from_type']='订单退款';
                                break;
                            case 3:
                                $res['data'][$k]['from_type']='订单支付';
                                break;
                            case 4:
                                $res['data'][$k]['from_type']='分红发放';
                                break;
                        }

                        $res['data'][$k]['records_no'] = $v['records_no']."\t";
                        $res['data'][$k]['nick_name'] = $v['nick_name'] ? $v['nick_name'] : ($v['user_name'] ? $v['user_name'] : $v['user_tel']);
                        $res['data'][$k]["nick_name"] = $this->removeEmoji($res['data'][$k]['nick_name']);
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'],$res['data']);
                }
            }
        }

        return $list;
    }

    /**
     * 会员列表导出Excel
     */
    public function getMemberListToExcel($page_index = 1, $page_size = 0, $condition = '', $order = '', $field = '*')
    {
        $member_view = new VslMemberViewModel();
        $field = 'nm.uid, nml.level_name,nm.group_id,nm.reg_time,su.user_name,su.user_tel,su.nick_name';
        $data = $member_view->getViewList($page_index, $page_size, $condition, $order, $field);
        $member_account = new MemberAccount();
        $group_name = new VslMemberGroupModel();
        $order_account = new OrderAccount();

        $list['data'] = [];
        if($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $member_view->getViewList($i, $page_size, $condition, $order, $field)['data'];
                if($res['data']) {
                    foreach ($res['data'] as $k => $v) {
                        $group_names= '';
                        if($v['group_id']){
                            $group_id = explode(',',$v['group_id']);
                            foreach ($group_id as $value){
                                $group_names .=  $group_name->getInfo(['group_id'=>$value],'group_name')['group_name'].'  ';
                            }
                            unset($value);
                        }
                        $res['data'][$k]['group_name'] =  $group_names;
                        $res['data'][$k]['point'] = $member_account->getMemberPoint($v['uid']);
                        $res['data'][$k]['balance'] = '¥'. $member_account->getMemberBalance($v['uid']);
                        $res['data'][$k]['order_num'] = $order_account->getShopSaleNumSum(['buyer_id' => $v['uid'], 'order_status' => [['>', '0'], ['<', '5']]]);
                        $res['data'][$k]['order_money'] = '¥'. $order_account->getMemberSaleMoney(['buyer_id' => $v['uid'], 'order_status' => [['>', '0'], ['<', '5']]]);
                        $res['data'][$k]['reg_time'] = date('Y-m-d H:i:s', $v['reg_time']);
                        $res[$k]["nick_name"] = iconv('gb2312//ignore', 'utf-8', iconv('utf-8', 'gb2312//ignore', $v['nick_name']));
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'],$res['data']);
                }
            }
        }

        return $list;
    }

    /**
     * 获取推荐人修改记录
     *
     * @param unknown $page_index
     * @param unknown $page_size
     * @param unknown $condition
     * @param string $order
     * @param string $field
     * @return multitype:number unknown
     */
    public function getRefereeLogList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $member_account = new VslMemberRefereeLogModel();
        $list = $member_account->getViewList($page_index, $page_size, $condition, 'l.update_time desc');
        return $list;
    }

    /**
     * 获取签约信息
     *
     * @return VslMemberEsignModel
     * @throws \think\Exception\DbException
     */
    public function getEsignDetail($uid, $type){
        $express_address = new VslMemberEsignModel();
        if($type == -1){
            $data = $express_address->get(['uid'=> $uid, 'type'=> 1]);
            if(is_null($data)){
                $data = $express_address->get(['uid'=> $uid, 'type'=> 2]);
            }
        }else{
            $data = $express_address->get(['uid'=> $uid, 'type'=> $type]);
        }

        if(is_null($data)){
            $data = [
                'type'=> $type > 0 ? $type : 1
            ];
        }
        return $data;
    }

    /**
     * 获取签约链接
     *
     * @param $data
     * @return array
     * @throws \think\Exception\DbException
     */
    public function getEsignUrl($uid, $data){

        $data['uid'] = $uid;
        $where = [
            'uid' => $uid, 'type'=> $data['type']
        ];
        $member_esign = new VslMemberEsignModel();
        $user = $member_esign->get($where);
        if(is_null($user)){
            $data['create_time'] = time();
            $member_esign->isUpdate(false)->save($data);
        }else{
            $data['update_time'] = time();
            $member_esign->save($data, $where);
        }
//        $signService = new EsignService();
//        $res = $signService->personsAuthorizedInfo('b4a1f2c4dc2a4a2a96822f63abb6a445');
//        var_dump($res);die;
        //签约文件已生成
        if(!empty($user['sign_url'])){
            return [true, ['url'=> $user['sign_url']]];
        }
        $signService = new EsignService();
        if(!empty($user['file_id'])){
            $res = $signService->fileStatus($user['file_id']);
            if($res['code'] == 0){
                $file_id = $user['file_id'];
                $position = json_decode(html_entity_decode($user['esign_position']), true);
            }else{
                return [false, '文件信息有误，请联系客服处理'];
            }
        }else {
            $date = date('Y-m-d');
            $data['a_date'] = $date;
            $data['b_date'] = $date;
            $res = $signService->createTpl($data, $data['type']);
//            if($uid == 17545){
//                var_dump($res);die;
//            }

            if ($res['code'] == 0) {
                $member_esign->save(['file_id' => $res['data']['fileId'],
                    'file_download_url' => $res['data']['fileDownloadUrl'],
                    'esign_position' => json_encode($res['esign_position'])], $where);
                $file_id = $res['data']['fileId'];
                $position = $res['esign_position'];
            } else {
                $member_esign->save([
                    'result' => json_encode($res)], $where);
                return [false, '签署文件创建失败，请联系客服处理！'];
            }
        }

        if(!empty($user['sign_flow_id'])){
            $signFlowId = $user['sign_flow_id'];
        }else{
            //查询文件盖章位置
//        $position = $signService->fileKeywordPositions($file_id);
            $title = $data['type'] == 1 ? '无痕功肤幼态脸-纯然至尊会员卡会员合同' : '无痕三分钟美鼻-纯然至尊会员卡会员合同';

            //获取签署id
            $res = $signService->createByFile($file_id, $position, $data['mobile'], $data['name'], $title);

            if($res['code'] !=0){
                return [false, '签署流程创建失败，请联系客服处理！'];
            }
            $signFlowId = $res['data']['signFlowId'];
        }
        
        $url = $signService->getSignUrl($signFlowId, $data['mobile']);
        $member_esign->save(['sign_flow_id'=> $signFlowId, 'sign_url'=> $url], $where);

        return [true, ['url'=> $url]];
    }


}
