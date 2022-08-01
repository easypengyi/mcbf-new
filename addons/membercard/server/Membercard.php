<?php

namespace addons\membercard\server;

use addons\coupontype\model\VslCouponModel;
use addons\coupontype\model\VslCouponTypeModel;
use addons\coupontype\server\Coupon;
use addons\distribution\model\VslDistributorAccountModel;
use addons\distribution\model\VslDistributorAccountRecordsModel;
use addons\distribution\service\Distributor;
use addons\membercard\model\VslBuyMembercardRecordsModel;
use addons\membercard\model\VslMembercardRechargeLevelModel;
use addons\membercard\model\VslMembercardSpecModel;
use addons\membercard\model\VslMembercardUserModel;
use data\model\UserModel;
use data\model\VslAccountModel;
use data\model\VslMemberModel;
use data\model\VslMemberRechargeModel;
use data\model\VslOrderPaymentModel;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\BaseService;
use data\service\Goods;
use data\service\Member as MemberService;
use data\service\Member\MemberAccount;
use data\service\ShopAccount;
use data\service\WeixinCard;
use addons\membercard\model\VslMembercardModel;
use think\Db;
use think\Exception;

class Membercard extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 新增或编辑充值送
     */
    public function addOrUpdateRechargeLevel($data)
    {
        if (empty($data['id'])) {
            //新增
            $save_data = [
                'recharge_money' => $data['recharge_money'],
                'give_money' => $data['give_money'],
                'website_id' => $this->website_id,
                'create_time' => time(),
            ];
            $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();
            $res = $membercard_recharge_level_mdl->save($save_data);
        } else {
            //编辑
            $save_data = [
                'recharge_money' => $data['recharge_money'],
                'give_money' => $data['give_money'],
            ];
            $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();
            $res = $membercard_recharge_level_mdl->isUpdate()->save($save_data, ['id' => $data['id']]);
        }

        return $res;
    }

    /**
     * 充值送列表
     */
    public function rechargeLevelList($page_index, $page_size, $condition, $order)
    {
        $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();
        $list = $membercard_recharge_level_mdl->pageQuery($page_index, $page_size, $condition, $order, '');
        return $list;
    }

    /**
     * 获取充值送详情
     */
    public function getrechargeLevelDetail($id)
    {
        $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();
        $info = $membercard_recharge_level_mdl->getInfo(['id' => $id]);
        return $info;
    }

    /**
     * 删除充值送
     */
    public function delRechargeLevel($id)
    {
        $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();
        $res = $membercard_recharge_level_mdl->delData(['id' => $id]);
        return $res;
    }

    /**
     * 新增或编辑会员卡
     */
    public function addOrUpdateMembercard($data)
    {
        try {
            $membercard_mdl = new VslMembercardModel();
            $membercard_spec_mdl = new VslMembercardSpecModel();

            if ($data['spec_list']) {
                $spec_list = $data['spec_list'];
                unset($data['spec_list']);
            }

            if ($data['coupon_id']) {
                $data['coupon_id'] = implode(',', $data['coupon_id']);
            }

            if ($data['circle_coupon_id']) {
                $data['circle_coupon_id'] = implode(',', $data['circle_coupon_id']);
            }

            //额外送佣金
            if($data['commission_val']) {
                $data['commission_val'] = json_encode($data['commission_val']);
            }else{
                $data['commission_val'] = '';
            }
            //购卡/升级
            if($data['commission_buy_val']) {
                $data['commission_buy_val'] = json_encode($data['commission_buy_val']);
            }else{
                $data['commission_buy_val'] = '';
            }
            //续费
            if($data['commission_renew_val']) {
                $data['commission_renew_val'] = json_encode($data['commission_renew_val']);
            }else{
                $data['commission_renew_val'] = '';
            }

            if (empty($data['membercard_id'])) {
                unset($data['membercard_id']);
                //新增
                if ($data['is_default']) {
                    //判断是否已经有默认的会员卡了
                    $is_default = $membercard_mdl->getInfo(['website_id' => $this->website_id], 'is_default')['is_default'];
                    if ($is_default) {
                        return -2;
                    }
                }

                if ($data['weight']) {
                    //判断权重值是否已经存在
                    $weight = $membercard_mdl->getInfo(['website_id' => $this->website_id, 'weight' => $data['weight']], 'weight')['weight'];
                    if ($weight) {
                        return -3;
                    }
                }

                $data['create_time'] = time();
                $data['website_id'] = $this->website_id;
                $res = $membercard_mdl->save($data);

                if ($res) {
                    //新增售价规格
                    if ($spec_list) {
                        foreach ($spec_list as $k => $v) {
                            unset($spec_list[$k]['spec_id']);
                            $spec_list[$k]['membercard_id'] = $res;
                            $spec_list[$k]['website_id'] = $this->website_id;
                        }
                        $res1 = $membercard_spec_mdl->saveAll($spec_list, true);
                    }
                    //推送到微信
                    $membercard_config = $this->getConfig();
                    if ($membercard_config['is_wxcard'] && $membercard_config['wxcard_info']) {
                        $this->membercardPushToWx($res, $data['membercard_name']);
                    }
                }
            } else {
                //编辑
                $before_data = $membercard_mdl->getInfo(['website_id' => $this->website_id, 'id' => $data['membercard_id']], 'is_default,weight,membercard_name,wx_membercard_id');
                if ($data['is_default']) {
                    //判断是否已经有默认的会员卡了
                    if ($data['is_default'] != $before_data['is_default']) {
                        $is_default = $membercard_mdl->getInfo(['website_id' => $this->website_id], 'is_default')['is_default'];

                        if ($is_default) {
                            return -2;
                        }
                    }
                }

                if ($data['weight']) {
                    //判断权重值是否已经存在
                    if ($data['weight'] != $before_data['weight']) {
                        $weight = $membercard_mdl->getInfo(['website_id' => $this->website_id, 'weight' => $data['weight']], 'weight')['weight'];
                        if ($weight) {
                            return -3;
                        }
                    }
                }

                $membercard_id = $data['membercard_id'];
                unset($data['membercard_id']);
                $res = $membercard_mdl->isUpdate()->save($data, ['website_id' => $this->website_id, 'id' => $membercard_id]);
                if ($res) {
                    //新增或编辑售价规格
                    if ($spec_list) {
                        $before_spec_list = $membercard_spec_mdl->getQuery(['membercard_id' => $membercard_id, 'website_id' => $this->website_id], 'spec_id', '');
                        foreach ($spec_list as $k => $v) {
                            if ($v['spec_id']) {
                                $new_spec_list[] = $v['spec_id'];
                            }
                        }
                        foreach ($before_spec_list as $k => $v) {
                            if (!in_array($v['spec_id'], $new_spec_list)) {
                                $membercard_spec_mdl->delData(['spec_id' => $v['spec_id']]);
                            }
                        }
                        foreach ($spec_list as $k => $v) {
                            $membercard_spec_mdl = new VslMembercardSpecModel();
                            $save_data = [
                                'time_type' => $v['time_type'],
                                'limit_time' => $v['limit_time'],
                                'price' => $v['price'],
                                'market_price' => $v['market_price'],
                                'is_first_buy' => $v['is_first_buy'],
                                'first_buy_price' => $v['first_buy_price'],
                            ];
                            if ($v['spec_id']) {
                                //编辑
                                $res1 = $membercard_spec_mdl->isUpdate()->save($save_data, ['spec_id' => $v['spec_id']]);
                            } else {
                                $save_data['membercard_id'] = $membercard_id;
                                $save_data['website_id'] = $this->website_id;
                                $res1 = $membercard_spec_mdl->save($save_data);
                            }
                        }
                    }else{
                        $membercard_spec_mdl->where(['membercard_id' => $membercard_id,'website_id' => $this->website_id])->delete();
                    }
                    if ($data['membercard_name'] != $before_data['membercard_name']) {
                        if ($before_data['wx_membercard_id']) {
                            //如果修改了会员卡名称，并且这个会员卡有推送到微信，就要更新到微信
                            $card_info['wx_membercard_id'] = $before_data['wx_membercard_id'];
                            $card_info['title'] = $data['membercard_name'];
                            $weixin_card = new WeixinCard();
                            $ticket_result = $weixin_card->updateMembercard($card_info);
                        }
                    }
                }
            }

            return $res;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 会员卡列表
     */
    public function membercardList($page_index, $page_size, $condition, $order)
    {
        $membercard_mdl = new VslMembercardModel();
        $list = $membercard_mdl->pageQuery($page_index, $page_size, $condition, $order, 'id,membercard_name,is_online,is_default,membercard_payment');

        if ($list['data']) {
            $membercard_spec_mdl = new VslMembercardSpecModel();
            foreach ($list['data'] as $k => $v) {
                //售价规格
                $list['data'][$k]['spec_list'] = $membercard_spec_mdl->getQuery(['membercard_id' => $v['id']], '', 'time_type asc');
            }
        }

        return $list;
    }

    /**
     * 删除会员卡
     */
    public function delMembercard($id)
    {
        $membercard_mdl = new VslMembercardModel();
        $membercard_spec_mdl = new VslMembercardSpecModel();
        $membercard_user_mdl = new VslMembercardUserModel();

        //如果有会员领取过该卡则不能删除
        $buy_info = $membercard_user_mdl->getInfo(['membercard_id' => $id]);
        if($buy_info) {
            return -1;
        }else{
            $res = $membercard_mdl->delData(['id' => $id, 'website_id' => $this->website_id]);
            if ($res) {
                //删除售价规格
                $membercard_spec_mdl->delData(['membercard_id' => $id, 'website_id' => $this->website_id]);
            }

            return $res;
        }
    }

    /**
     * 获取会员卡详情
     */
    public function getMembercardDetail($id)
    {
        $membercard_mdl = new VslMembercardModel();
        $membercard_spec_mdl = new VslMembercardSpecModel();
        $coupon_type_mdl = new VslCouponTypeModel();
        $coupon_model = new VslCouponModel();

        $data = $membercard_mdl->getInfo(['id' => $id, 'website_id' => $this->website_id]);

        if ($data['coupon_id']) {
            //查出开通送优惠券的优惠券信息
            $coupon_list = [];
            $data['coupon_id'] = explode(',', $data['coupon_id']);
            foreach ($data['coupon_id'] as $k => $v) {
                $coupon_list[] = $coupon_type_mdl->getInfo(['coupon_type_id' => $v]);
            }
            if ($coupon_list) {
                foreach ($coupon_list as $k => $v) {
                    if ($v['coupon_genre'] == 1) {
                        $coupon_list[$k]['coupon_genre'] = '无门槛券';
                    } elseif ($v['coupon_genre'] == 2) {
                        $coupon_list[$k]['coupon_genre'] = '满减券';
                    } elseif ($v['coupon_genre'] == 3) {
                        $coupon_list[$k]['coupon_genre'] = '折扣券';
                    }

                    $coupon_list[$k]['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
                    $coupon_list[$k]['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
                    $coupon_list[$k]['start_receive_time'] = date('Y-m-d H:i:s', $v['start_receive_time']);
                    $coupon_list[$k]['end_receive_time'] = date('Y-m-d H:i:s', $v['end_receive_time']);

                    //计算已发放数量
                    $received = $coupon_model->where(['coupon_type_id' => $v['coupon_type_id']])->count();
                    $coupon_list[$k]['surplus'] = ($v['count'] - $received > 0) ? $v['count'] - $received : 0;
                }
                $data['coupon_list'] = $coupon_list;
            }
        }

        if ($data['circle_coupon_id']) {
            //查出周期送优惠券的优惠券信息
            $circle_coupon_list = [];
            $data['circle_coupon_id'] = explode(',', $data['circle_coupon_id']);
            foreach ($data['circle_coupon_id'] as $k => $v) {
                $circle_coupon_list[] = $coupon_type_mdl->getInfo(['coupon_type_id' => $v]);
            }
            if ($circle_coupon_list) {
                foreach ($circle_coupon_list as $k => $v) {
                    if ($v['coupon_genre'] == 1) {
                        $circle_coupon_list[$k]['coupon_genre'] = '无门槛券';
                    } elseif ($v['coupon_genre'] == 2) {
                        $circle_coupon_list[$k]['coupon_genre'] = '满减券';
                    } elseif ($v['coupon_genre'] == 3) {
                        $circle_coupon_list[$k]['coupon_genre'] = '折扣券';
                    }

                    $circle_coupon_list[$k]['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
                    $circle_coupon_list[$k]['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
                    $circle_coupon_list[$k]['start_receive_time'] = date('Y-m-d H:i:s', $v['start_receive_time']);
                    $circle_coupon_list[$k]['end_receive_time'] = date('Y-m-d H:i:s', $v['end_receive_time']);

                    //计算已发放数量
                    $received = $coupon_model->where(['coupon_type_id' => $v['coupon_type_id']])->count();
                    $circle_coupon_list[$k]['surplus'] = ($v['count'] - $received > 0) ? $v['count'] - $received : 0;
                }
                $data['circle_coupon_list'] = $circle_coupon_list;
            }
        }

        $data['spec_list'] = $membercard_spec_mdl->getQuery(['membercard_id' => $id, 'website_id' => $this->website_id], '', '');

        return $data;
    }

    /**
     * 领卡记录
     */
    public function memberMembercard($page_index, $page_size, $condition, $order)
    {
        $membercard_user_mdl = new VslMembercardUserModel();
        $viewObj = $membercard_user_mdl->alias('mu')
            ->join('vsl_member m', 'mu.uid = m.uid', 'left')
            ->join('sys_user u', 'mu.uid = u.uid', 'left')
            ->join('vsl_membercard mc', 'mu.membercard_id = mc.id', 'left')
            ->field('mu.*,u.user_headimg,u.user_name,u.nick_name,u.user_tel,u.reg_time,m.member_name, m.mobile,m.referee_id,mc.membercard_name');
        $queryList = $membercard_user_mdl->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);

        $queryCount = $membercard_user_mdl->alias('mu')
            ->join('vsl_member m', 'mu.uid = m.uid', 'left')
            ->join('sys_user u', 'mu.uid = u.uid', 'left')
            ->join('vsl_membercard mc', 'mu.membercard_id = mc.id', 'left')
            ->where($condition)
            ->field('mu.id')
            ->count();

        $list = $membercard_user_mdl->setReturnList($queryList, $queryCount, $page_size);

        if ($list['data']) {
            $user_mdl = new UserModel();
            foreach ($list['data'] as $k => $v) {
                if (empty($list['data'][$k]['user_name'])) {
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'] ?: ($list['data'][$k]['member_name'] ?: ($list['data'][$k]['user_tel'] ?: $list['data'][$k]['uid']));
                }

                if ($v['referee_id']) {
                    $referee_data = $user_mdl->alias('u')
                        ->join('vsl_member m', 'u.uid = m.uid', 'left')
                        ->field('u.user_headimg,u.user_name,u.nick_name,u.user_tel,m.member_name, m.mobile')
                        ->where('u.uid', '=', $v['referee_id'])
                        ->find();
                    if ($referee_data) {
                        if (empty($referee_data['user_name'])) {
                            $referee_data['user_name'] = $referee_data['nick_name'] ?: ($referee_data['member_name'] ?: ($referee_data['user_tel'] ?: $referee_data['uid']));
                        }
                        $list['data'][$k]['referee_data'] = $referee_data;
                    }
                }
            }
        }

        return $list;
    }

    /**
     * 后台调整余额
     */
    public function adjustBalance($uid, $num, $text, $from_type, $data_id = 0)
    {
        $member_account = new MemberAccount();
        $retval = $member_account->addMemberAccountData(6, $uid, 1, $num, $from_type, $data_id, $text);
        return $retval;
    }

    /**
     * 获取基础设置
     */
    public function getConfig()
    {   $AddonsConfig = new \data\service\AddonsConfig();
        $value = $AddonsConfig->getAddonsConfig('membercard', $this->website_id, 0, 1);
        return $value;
    }


    /***********************************************前端接口************************************************************/
    /**
     * 会员卡首页
     */
    public function membercardIndex($uid)
    {
        $this->checkMembercardStatus($uid);
        $membercard_user_mdl = new VslMembercardUserModel();
        $membercard_mdl = new VslMembercardModel();
        $membercard_spec_mdl = new VslMembercardSpecModel();
        $coupon_type_mdl = new VslCouponTypeModel();
        $config = $this->getConfig();
        $have_memebrcard = 0;
        //先判断有没有领取过会员卡
        $membercard_user_info = $membercard_user_mdl->getInfo(['uid' => $uid]);
        if (empty($membercard_user_info)) {
            //没有领取过会员卡，判断平台有没有默认的免费的，如果有，则默认领取
            $default_membercard = $membercard_mdl->getInfo(['is_default' => 1, 'is_online' => 1, 'membercard_payment' => 0, 'website_id' => $this->website_id]);
            if ($default_membercard) {
                //默认领取
                $membercard_prefix_length = strlen($config['membercard_prefix']);
                $suffix_length = 12 - $membercard_prefix_length;
                $suffix = '';
                for ($i = 0; $i < $suffix_length; $i++) {
                    $range_num = mt_rand(0, 9);
                    $suffix .= $range_num;
                }
                $save_data = [
                    'uid' => $uid,
                    'membercard_id' => $default_membercard['id'],
                    'membercard_no' => $config['membercard_prefix'] . $suffix,
                    'membercard_balance' => 0,
                    'status' => 1,
                    'website_id' => $this->website_id,
                    'create_time' => time(),
                ];
                $res = $membercard_user_mdl->save($save_data);
                if ($res) {
                    $save_data['id'] = $res;
                    $have_memebrcard = 1;
                    $membercard_user_info = $save_data;
                    $membercard_user_info['membercard_logo'] = $config['membercard_logo'];
                    $membercard_user_info['membercard_style'] = $default_membercard['membercard_style'];
                    $membercard_user_info['membercard_name'] = $default_membercard['membercard_name'];
                    $membercard_user_info['status'] = 1;

                    //领取成功后，判断领取的会员卡的权益有没有开通送优惠券和成为分销商
                    if ($default_membercard['is_give_coupon'] && $default_membercard['coupon_id']) {
                        //开通送优惠券
                        if (getAddons('coupontype', $this->website_id)) {
                            $default_membercard['coupon_id'] = explode(',', $default_membercard['coupon_id']);
                            foreach ($default_membercard['coupon_id'] as $k => $v) {
                                $coupon = new Coupon();
                                $coupon->userAchieveCoupon($uid, $v, 11);
                            }
                        }
                    }
                    if ($default_membercard['is_become_distributor']) {
                        //成为分销商
                        if (getAddons('distribution', $this->website_id)) {
                            $member_mdl = new VslMemberModel();
                            $is_distributor = $member_mdl->Query(['uid' => $uid], 'isdistributor')[0];
                            if (empty($is_distributor)) {
                                $distributor = new Distributor();
                                $distributor->setStatus($uid, 2);
                            }
                        }
                    }
                }
            } else {
                //没有免费的会员卡,返回所有上架的需要付费的会员卡列表
                $membercard_list = $membercard_mdl->getQuery(['is_online' => 1, 'website_id' => $this->website_id, 'membercard_payment' => 1], '', 'weight asc');
                if ($membercard_list) {
                    foreach ($membercard_list as $k => $v) {
                        $membercard_list[$k]['membercard_logo'] = $config['membercard_logo'];
                        $membercard_list[$k]['spec_list'] = $membercard_spec_mdl->getQuery(['membercard_id' => $v['id']], '', 'time_type desc');
                        //开通送优惠券的优惠券信息
                        if ($v['coupon_id']) {
                            $v['coupon_id'] = explode(',', $v['coupon_id']);
                            foreach ($v['coupon_id'] as $k1 => $v1) {
                                $coupon_list[] = $coupon_type_mdl->getInfo(['coupon_type_id' => $v1]);
                            }
                            $membercard_list[$k]['coupon_list'] = $coupon_list;
                        }
                        //周期送优惠券的优惠券信息
                        if ($v['circle_coupon_id']) {
                            $v['circle_coupon_id'] = explode(',', $v['circle_coupon_id']);
                            foreach ($v['circle_coupon_id'] as $k2 => $v2) {
                                $circle_coupon_list[] = $coupon_type_mdl->getInfo(['coupon_type_id' => $v2]);
                            }
                            $membercard_list[$k]['circle_coupon_list'] = $circle_coupon_list;
                        }
                    }
                }
                $have_memebrcard = 0;
            }
        } else {
            $have_memebrcard = 1;
            $membercard_user_info['membercard_logo'] = $config['membercard_logo'];
            $membercard_info = $membercard_mdl->getInfo(['id' => $membercard_user_info['membercard_id']], 'membercard_name,membercard_style');
            $membercard_user_info['membercard_style'] = $membercard_info['membercard_style'];
            $membercard_user_info['membercard_name'] = $membercard_info['membercard_name'];
            //判断是否有添加充值送
            $condition['website_id'] = $this->website_id;
            $recharge_level_list = $this->rechargeLevelList(1, 20, $condition, '');
            if ($recharge_level_list['data']) {
                $membercard_user_info['show_full_gift'] = 1;
            } else {
                $membercard_user_info['show_full_gift'] = 0;
            }
        }

        return [
            'have_memebrcard' => $have_memebrcard,
            'data' => $membercard_user_info ?: $membercard_list,
            'content_title' => $config['content_title'],
            'content' => $config['content'],
        ];
    }

    /**
     * 升级会员卡
     */
    public function upgradeMembercard($uid)
    {
        $membercard_user_mdl = new VslMembercardUserModel();
        $membercard_mdl = new VslMembercardModel();
        $membercard_spec_mdl = new VslMembercardSpecModel();
        $buy_membercard_records_mdl = new VslBuyMembercardRecordsModel();
        $coupon_type_mdl = new VslCouponTypeModel();
        $config = $this->getConfig();

        //先查出当前用户的会员卡
        $membercard_user_info = $membercard_user_mdl->getInfo(['uid' => $uid]);
        if ($membercard_user_info['status']) {
            //没过期的
            $weight = $membercard_mdl->Query(['id' => $membercard_user_info['membercard_id']], 'weight')[0];
            //再查出所有权重比当前会员卡权重高的其他会员卡
            $membercard_list = $membercard_mdl->getQuery(['is_online' => 1, 'membercard_payment' => 1, 'weight' => ['>=', $weight], 'website_id' => $this->website_id], '', 'weight asc');
        } else {
            //过期的,查出所有
            $membercard_list = $membercard_mdl->getQuery(['is_online' => 1, 'membercard_payment' => 1, 'website_id' => $this->website_id], '', 'weight asc');
        }

        if ($membercard_list) {
            foreach ($membercard_list as $k => $v) {
                $membercard_list[$k]['membercard_logo'] = $config['membercard_logo'];
                $membercard_list[$k]['spec_list'] = $membercard_spec_mdl->getQuery(['membercard_id' => $v['id']], '', 'time_type desc');
                if($membercard_list[$k]['spec_list']) {
                    foreach ($membercard_list[$k]['spec_list'] as $k1 => $v1) {
                        $is_first_buy = $buy_membercard_records_mdl->getInfo(['membercard_id' => $v1['membercard_id'],'membercard_spec_id' => $v1['spec_id'],'pay_status' => 2,'uid' => $uid],'id');
                        if($is_first_buy) {
                            $membercard_list[$k]['spec_list'][$k1]['is_first_buy'] = 0;
                        }
                    }
                }
                //开通送优惠券的优惠券信息
                if ($v['coupon_id']) {
                    $v['coupon_id'] = explode(',', $v['coupon_id']);
                    foreach ($v['coupon_id'] as $k1 => $v1) {
                        $coupon_list[] = $coupon_type_mdl->getInfo(['coupon_type_id' => $v1]);
                    }
                    $membercard_list[$k]['coupon_list'] = $coupon_list;
                }
                //周期送优惠券的优惠券信息
                if ($v['circle_coupon_id']) {
                    $v['circle_coupon_id'] = explode(',', $v['circle_coupon_id']);
                    foreach ($v['circle_coupon_id'] as $k2 => $v2) {
                        $circle_coupon_list[] = $coupon_type_mdl->getInfo(['coupon_type_id' => $v2]);
                    }
                    $membercard_list[$k]['circle_coupon_list'] = $circle_coupon_list;
                }
            }
        }

        return [
            'content_title' => $config['content_title'],
            'content' => $config['content'],
            'membercard_list' => $membercard_list
        ];
    }

    /**
     * 购买会员卡成功
     */
    public function buyMembercard($out_trade_no, $pay_type)
    {
        try {
            $config = $this->getConfig();
            $shop_account = new ShopAccount();
            $pay = new VslOrderPaymentModel();

            if ($pay_type == 5) {
                //余额支付
                $data = array(
                    'pay_status' => 1,
                    'pay_type' => $pay_type,
                    'pay_time' => time(),
                    'trade_no' => ''
                );
                $pay->save($data, ['out_trade_no' => $out_trade_no]);
            }

            $pay_info = $pay->getInfo(['out_trade_no' => $out_trade_no]);

            if ($pay_info['pay_status'] == 1) {
                //购买记录
                $buy_membercard_records_mdl = new VslBuyMembercardRecordsModel();
                $membercard_info = $buy_membercard_records_mdl->getInfo(['id' => $pay_info['type_alis_id']], 'membercard_id,membercard_spec_id,uid,website_id');

                if ($pay_type == 5) {
                    //余额支付
                    $data = array(
                        'pay_status' => 1,
                        'pay_type' => $pay_type,
                        'pay_time' => time(),
                        'trade_no' => ''
                    );
                    $pay->save($data, ['out_trade_no' => $out_trade_no]);

                    //更新余额流水
                    $member_account = new MemberAccount();
                    $retval = $member_account->addMemberAccountData(2, $membercard_info['uid'], 0, $pay_info['pay_money'], 64, $pay_info['type_alis_id'], '购买会员卡，余额支付');

                } elseif ($pay_type == 1) {
                    //微信支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $retval = $shop_account->addAccountRecords(0, $membercard_info['uid'], '购买会员卡', $pay_info['pay_money'], 53, $pay_info['type_alis_id'], "购买会员卡微信支付成功，入账总额增加", $membercard_info['website_id']);
                } elseif ($pay_type == 2) {
                    //支付宝支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $retval = $shop_account->addAccountRecords(0, $membercard_info['uid'], '购买会员卡', $pay_info['pay_money'], 54, $pay_info['type_alis_id'], "购买会员卡支付宝支付成功，入账总额增加", $membercard_info['website_id']);
                } elseif ($pay_type == 3) {
                    //银行卡支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $retval = $shop_account->addAccountRecords(0, $membercard_info['uid'], '购买会员卡', $pay_info['pay_money'], 55, $pay_info['type_alis_id'], "购买会员卡银行卡支付成功，入账总额增加", $membercard_info['website_id']);
                } elseif ($pay_type == 16) {
                    //eth支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $retval = $shop_account->addAccountRecords(0, $membercard_info['uid'], '购买会员卡', $pay_info['pay_money'], 56, $pay_info['type_alis_id'], "购买会员卡eth支付成功，入账总额增加", $membercard_info['website_id']);
                } elseif ($pay_type == 17) {
                    //eos支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $retval = $shop_account->addAccountRecords(0, $membercard_info['uid'], '购买会员卡', $pay_info['pay_money'], 57, $pay_info['type_alis_id'], "购买会员卡eos支付成功，入账总额增加", $membercard_info['website_id']);
                } elseif ($pay_type == 20) {
                    //GlobePay支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $retval = $shop_account->addAccountRecords(0, $membercard_info['uid'], '购买会员卡', $pay_info['pay_money'], 58, $pay_info['type_alis_id'], "购买会员卡GlobePay支付成功，入账总额增加", $membercard_info['website_id']);
                }else{
                    $retval = 1;
                }

                if ($retval) {
                    //更新会员购买记录
                    $buy_membercard_records_mdl->save(['pay_status' => 2], ['id' => $pay_info['type_alis_id']]);

                    //计算过期时间
                    $membercard_spec_mdl = new VslMembercardSpecModel();
                    $membercard_spec_info = $membercard_spec_mdl->getInfo(['membercard_id' => $membercard_info['membercard_id'], 'spec_id' => $membercard_info['membercard_spec_id']], 'time_type,limit_time');

                    $type = 0;
                    //新增或更新到会员的会员卡里
                    $membercard_user_mdl = new VslMembercardUserModel();
                    $membercard_user_info = $membercard_user_mdl->getInfo(['uid' => $membercard_info['uid'], 'website_id' => $membercard_info['website_id']]);
                    if (empty($membercard_user_info)) {
                        $type = 1;
                        if ($membercard_spec_info['time_type'] == 1) {
                            //按年
                            $expire_time = time() + $membercard_spec_info['limit_time'] * 365 * 24 * 60 * 60;
                        } elseif ($membercard_spec_info['time_type'] == 2) {
                            //按季
                            $expire_time = time() + $membercard_spec_info['limit_time'] * 90 * 24 * 60 * 60;
                        } elseif ($membercard_spec_info['time_type'] == 3) {
                            //按月
                            $expire_time = time() + $membercard_spec_info['limit_time'] * 30 * 24 * 60 * 60;
                        } elseif ($membercard_spec_info['time_type'] == 4) {
                            //按天
                            $expire_time = time() + $membercard_spec_info['limit_time'] * 24 * 60 * 60;
                        }
                        //新增
                        $membercard_prefix_length = strlen($config['membercard_prefix']);
                        $suffix_length = 12 - $membercard_prefix_length;
                        $suffix = '';
                        for ($i = 0; $i < $suffix_length; $i++) {
                            $range_num = mt_rand(0, 9);
                            $suffix .= $range_num;
                        }
                        $save_data = [
                            'uid' => $membercard_info['uid'],
                            'membercard_id' => $membercard_info['membercard_id'],
                            'membercard_spec_id' => $membercard_info['membercard_spec_id'],
                            'membercard_no' => $config['membercard_prefix'] . $suffix,
                            'membercard_balance' => 0,
                            'status' => 1,
                            'website_id' => $this->website_id,
                            'create_time' => time(),
                            'expire_time' => $expire_time
                        ];
                        $res = $membercard_user_mdl->save($save_data);
                    } else {
                        //更新
                        //判断是续费还是升级
                        if($membercard_user_info['membercard_id'] == $membercard_info['membercard_id']) {
                            $type = 2;
                            //续费
                            //判断是否已过期
                            if($membercard_user_info['expire_time'] <= time()) {
                                if ($membercard_spec_info['time_type'] == 1) {
                                    //按年
                                    $expire_time = time() + $membercard_spec_info['limit_time'] * 365 * 24 * 60 * 60;
                                } elseif ($membercard_spec_info['time_type'] == 2) {
                                    //按季
                                    $expire_time = time() + $membercard_spec_info['limit_time'] * 90 * 24 * 60 * 60;
                                } elseif ($membercard_spec_info['time_type'] == 3) {
                                    //按月
                                    $expire_time = time() + $membercard_spec_info['limit_time'] * 30 * 24 * 60 * 60;
                                } elseif ($membercard_spec_info['time_type'] == 4) {
                                    //按天
                                    $expire_time = time() + $membercard_spec_info['limit_time'] * 24 * 60 * 60;
                                }
                            }else{
                                if ($membercard_spec_info['time_type'] == 1) {
                                    //按年
                                    $expire_time = $membercard_user_info['expire_time'] + $membercard_spec_info['limit_time'] * 365 * 24 * 60 * 60;
                                } elseif ($membercard_spec_info['time_type'] == 2) {
                                    //按季
                                    $expire_time = $membercard_user_info['expire_time'] + $membercard_spec_info['limit_time'] * 90 * 24 * 60 * 60;
                                } elseif ($membercard_spec_info['time_type'] == 3) {
                                    //按月
                                    $expire_time = $membercard_user_info['expire_time'] + $membercard_spec_info['limit_time'] * 30 * 24 * 60 * 60;
                                } elseif ($membercard_spec_info['time_type'] == 4) {
                                    //按天
                                    $expire_time = $membercard_user_info['expire_time'] + $membercard_spec_info['limit_time'] * 24 * 60 * 60;
                                }
                            }
                            $res = $membercard_user_mdl->save(['expire_time' => $expire_time, 'status' => 1], ['uid' => $membercard_info['uid'], 'website_id' => $this->website_id]);
                        }else{
                            $type = 1;
                            //升级
                            if ($membercard_spec_info['time_type'] == 1) {
                                //按年
                                $expire_time = time() + $membercard_spec_info['limit_time'] * 365 * 24 * 60 * 60;
                            } elseif ($membercard_spec_info['time_type'] == 2) {
                                //按季
                                $expire_time = time() + $membercard_spec_info['limit_time'] * 90 * 24 * 60 * 60;
                            } elseif ($membercard_spec_info['time_type'] == 3) {
                                //按月
                                $expire_time = time() + $membercard_spec_info['limit_time'] * 30 * 24 * 60 * 60;
                            } elseif ($membercard_spec_info['time_type'] == 4) {
                                //按天
                                $expire_time = time() + $membercard_spec_info['limit_time'] * 24 * 60 * 60;
                            }
                            $res = $membercard_user_mdl->save(['membercard_id' => $membercard_info['membercard_id'], 'membercard_spec_id' => $membercard_info['membercard_spec_id'], 'create_time' => time(), 'expire_time' => $expire_time, 'status' => 1], ['uid' => $membercard_info['uid'], 'website_id' => $this->website_id]);
                        }
                    }

                    if ($res) {
                        //购买成功后，判断购买的会员卡的权益有没有开通送优惠券和成为分销商
                        $membercard_mdl = new VslMembercardModel();
                        $membercard_detail = $membercard_mdl->getInfo(['id' => $membercard_info['membercard_id']]);

                        if ($membercard_detail['is_give_coupon'] && $membercard_detail['coupon_id']) {
                            //开通送优惠券
                            if (getAddons('coupontype', $this->website_id)) {
                                $membercard_detail['coupon_id'] = explode(',', $membercard_detail['coupon_id']);
                                foreach ($membercard_detail['coupon_id'] as $k => $v) {
                                    $coupon = new Coupon();
                                    $coupon->userAchieveCoupon($membercard_info['uid'], $v, 11);
                                }
                            }
                        }
                        //是否延时送优惠券
                        if ($membercard_detail['is_circle_coupon'] && $membercard_detail['coupon_id']) {
                            $this->rabbitCreateSendCoupon($membercard_info['uid'], $membercard_info['membercard_id'], $membercard_detail['circle_num']);
                        }
                        if ($membercard_detail['is_become_distributor']) {
                            //成为分销商
                            if (getAddons('distribution', $this->website_id)) {
                                $member_mdl = new VslMemberModel();
                                $is_distributor = $member_mdl->Query(['uid' => $membercard_info['uid']], 'isdistributor')[0];
                                if (empty($is_distributor)) {
                                    $distributor = new Distributor();
                                    $distributor->setStatus($membercard_info['uid'], 2);
                                }
                            }
                        }
                        //购卡/升级返佣
                        if($membercard_detail['is_give_commission_buy'] && getAddons('distribution',$this->website_id) && $type==1){
                            $this->dealReturnCommission($membercard_detail,$type,$membercard_info['uid'],$pay_info['pay_money'],$pay_info['type_alis_id']);
                        }
                        //续费返佣
                        if($membercard_detail['is_give_commission_renew'] && getAddons('distribution',$this->website_id) && $type==2){
                            $this->dealReturnCommission($membercard_detail,$type,$membercard_info['uid'],$pay_info['pay_money'],$pay_info['type_alis_id']);
                        }
                    }
                }

                return $res;
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 创建周期赠送优惠券的延时队列
     * @param $uid
     * @param $membercard_id
     * @param $circle_num
     */
    public function rabbitCreateSendCoupon($uid, $membercard_id, $circle_num)
    {
        if(config('is_high_powered')){
            //订单完成延时队列
            $config['delay_exchange_name'] = config('rabbit_delay_membercard_coupon.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_membercard_coupon.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_membercard_coupon.delay_routing_key');
            //查询出订单配置过期自动关闭时间
            $delay_time = $circle_num * 24 * 3600 * 1000;//毫秒
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'membercard_id' => $membercard_id,
                'uid' => $uid,
            ];
            $data = json_encode($data);
            $url = config('rabbit_interface_url.url');
            $back_url = $url.'/rabbitTask/membercardCircleGiveCoupon';
            $custom_type = 'membercard_coupon';
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }

    /**
     * 充值
     */
    public function getRechargeList($uid)
    {
        $membercard_user_mdl = new VslMembercardUserModel();
        $info = $membercard_user_mdl->getInfo(['uid' => $uid, 'website_id' => $this->website_id], 'membercard_balance,membercard_id');

        $data['membercard_balance'] = $info['membercard_balance'];

        $membercard_mdl = new VslMembercardModel();
        $data['membercard_style'] = $membercard_mdl->Query(['id' => $info['membercard_id']], 'membercard_style')[0];

        $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();
        $data['recharge_list'] = $membercard_recharge_level_mdl->getQuery(['website_id' => $this->website_id], '', 'recharge_money asc');

        $config = $this->getConfig();
        $data['membercard_logo'] = $config['membercard_logo'];

        return $data;
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder($uid, $recharge_id, $recharge_money, $give_money, $out_trade_no)
    {
        try {
            $membercard_recharge_level_mdl = new VslMembercardRechargeLevelModel();

            if ($recharge_id) {
                //固定金额充值
                $recharge_info = $membercard_recharge_level_mdl->getInfo(['id' => $recharge_id, 'website_id' => $this->website_id], 'recharge_money,give_money');
                $recharge_money = $recharge_info['recharge_money'];
                $give_money = $recharge_info['give_money'];
            } elseif ($give_money) {
                //其他金额充值
                //检查赠送金额是否正确
                $recharge_list = $membercard_recharge_level_mdl->getQuery(['website_id' => $this->website_id], '', 'recharge_money desc');
                foreach ($recharge_list as $k => $v) {
                    if ($recharge_money >= $v['recharge_money']) {
                        $check_give_money = $v['give_money'];
                        break;
                    } else {
                        $check_give_money = 0;
                    }
                }
                if ($check_give_money != $give_money) {
                    return -2;
                }
            }

            //创建充值订单
            $member = new MemberService();
            $retval = $member->createMemberRecharge($recharge_money, $uid, $out_trade_no, 7, $give_money);

            return $retval;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 会员卡充值成功
     */
    public function rechargeMembercard($out_trade_no, $pay_type)
    {
        try {
            $member_recharge = new VslMemberRechargeModel();
            $shop_account = new ShopAccount();
            $pay = new VslOrderPaymentModel();
            $member_account = new MemberAccount();

            if ($pay_type == 5) {
                //余额支付
                $data = array(
                    'pay_status' => 1,
                    'pay_type' => $pay_type,
                    'pay_time' => time(),
                    'trade_no' => ''
                );
                $pay->save($data, ['out_trade_no' => $out_trade_no]);
            }

            $pay_info = $pay->getInfo(['out_trade_no' => $out_trade_no]);

            if ($pay_info['pay_status'] == 1) {
                //更新充值记录
                $data = array(
                    "is_pay" => 1,
                    "status" => 1
                );
                $member_recharge->save($data, [
                    "id" => $pay_info["type_alis_id"]
                ]);

                $recharge_info = $member_recharge->getInfo(['out_trade_no' => $out_trade_no]);

                if ($pay_type == 5) {
                    //余额支付
                    $member_account->addMemberAccountData(2, $recharge_info['uid'], 0, $pay_info['pay_money'], 65, $pay_info['type_alis_id'], '会员卡充值，余额支付');
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 66, $pay_info['type_alis_id'], '充值，余额支付');
                } elseif ($pay_type == 1) {
                    //微信支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $shop_account->addAccountRecords(0, $recharge_info['uid'], '会员卡充值', $pay_info['pay_money'], 59, $pay_info['type_alis_id'], "会员卡充值微信支付成功，入账总额增加", $recharge_info['website_id']);
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 67, $pay_info['type_alis_id'], '充值，微信支付');
                } elseif ($pay_type == 2) {
                    //支付宝支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $shop_account->addAccountRecords(0, $recharge_info['uid'], '会员卡充值', $pay_info['pay_money'], 60, $pay_info['type_alis_id'], "会员卡充值支付宝支付成功，入账总额增加", $recharge_info['website_id']);
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 68, $pay_info['type_alis_id'], '充值，支付宝支付');
                } elseif ($pay_type == 3) {
                    //银行卡支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $shop_account->addAccountRecords(0, $recharge_info['uid'], '会员卡充值', $pay_info['pay_money'], 61, $pay_info['type_alis_id'], "会员卡充值银行卡支付成功，入账总额增加", $recharge_info['website_id']);
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 69, $pay_info['type_alis_id'], '充值，银行卡支付');
                } elseif ($pay_type == 16) {
                    //eth支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $shop_account->addAccountRecords(0, $recharge_info['uid'], '会员卡充值', $pay_info['pay_money'], 62, $pay_info['type_alis_id'], "会员卡充值eth支付成功，入账总额增加", $recharge_info['website_id']);
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 73, $pay_info['type_alis_id'], '充值，eth支付');
                } elseif ($pay_type == 17) {
                    //eos支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $shop_account->addAccountRecords(0, $recharge_info['uid'], '会员卡充值', $pay_info['pay_money'], 63, $pay_info['type_alis_id'], "会员卡充值eos支付成功，入账总额增加", $recharge_info['website_id']);
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 71, $pay_info['type_alis_id'], '充值，eos支付');
                } elseif ($pay_type == 20) {
                    //GlobePay支付
                    $shop_account->updateAccountOrderMoney($pay_info['pay_money']);
                    $shop_account->addAccountRecords(0, $recharge_info['uid'], '会员卡充值', $pay_info['pay_money'], 64, $pay_info['type_alis_id'], "会员卡充值GlobePay支付成功，入账总额增加", $recharge_info['website_id']);
                    $retval = $member_account->addMemberAccountData(6, $recharge_info['uid'], 0, $recharge_info['recharge_money'] + $recharge_info['give_money'], 72, $pay_info['type_alis_id'], '充值，GlobePay支付');
                }

                return $retval;
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 检查会员是否有会员卡以及是否还在有效期内
     */
    public function checkMembercardStatus($uid, $website_id = 0)
    {
        if(!$uid){return;}
        if(empty($website_id)) {
            $website_id = $this->website_id;
        }
        $membercard_mdl = new VslMembercardModel();
        $membercard_user_mdl = new VslMembercardUserModel();
        $membercard_user_info = $membercard_user_mdl->getInfo(['uid' => $uid, 'website_id' => $website_id]);

        if (empty($membercard_user_info) || empty($membercard_user_info['status'])) {
            $data['status'] = 0;
        } else {
            $membercard_info = $membercard_mdl->getInfo(['id' => $membercard_user_info['membercard_id']]);
            $membercard_info['membercard_balance'] = $membercard_user_info['membercard_balance'];
            if (empty($membercard_user_info['expire_time'])) {
                //永久有效
                $data['status'] = 1;
                $data['membercard_info'] = $membercard_info;
            } else {
                if (time() > $membercard_user_info['expire_time']) {
                    //已过期
                    //判断是否有默认的并且无需付费的会员卡，如果有，则变更为默认会员卡
                    $default_membercard = $membercard_mdl->getInfo(['is_default' => 1, 'is_online' => 1, 'membercard_payment' => 0, 'website_id' => $website_id],'id');
                    if($default_membercard) {
                        //有默认卡
                        $membercard_user_mdl->isUpdate()->save(['membercard_id' => $default_membercard['id'],'expire_time' => 0], ['uid' => $uid, 'website_id' => $website_id]);
                        $membercard_info = $membercard_mdl->getInfo(['id' => $default_membercard['id']]);
                        $membercard_info['membercard_balance'] = $membercard_user_info['membercard_balance'];
                        $data['status'] = 1;
                    }else{
                        //没有默认卡
                    $membercard_user_mdl->isUpdate()->save(['status' => 0], ['uid' => $uid, 'website_id' => $website_id]);
                        $data['status'] = 0;
                    }
                } else {
                    $data['status'] = 1;
                    $data['membercard_info'] = $membercard_info;
                }
            }
        }

        return $data;
    }

    /**
     * 会员卡领取到微信卡包
     */
    public function getMembercardInfo($membercard_id, $uid)
    {
        $membercard_mdl = new VslMembercardModel();
        $membercard_info = $membercard_mdl->alias('m')
            ->join('vsl_membercard_user mu', 'm.id = mu.membercard_id', 'left')
            ->where('m.id', '=', $membercard_id)
            ->where('mu.uid', '=', $uid)
            ->field('m.id,m.wx_membercard_id,mu.membercard_no,mu.uid')
            ->find();

        return $membercard_info;
    }

    /**
     * 微信会员卡领取成功
     */
    public function membercardSuccessToWx($membercard_id, $uid)
    {
        $membercard_user_mdl = new VslMembercardUserModel();
        $res = $membercard_user_mdl->save(['wx_membercard_status' => 1], ['membercard_id' => $membercard_id, 'uid' => $uid]);

        return $res;
    }

    /**
     *周期送优惠券  一天执行一次
     */
    public function circleGiveCoupon($website_id)
    {
        try {
            $membercard_mdl = new VslMembercardModel();
            $membercard_user_mdl = new VslMembercardUserModel();

            $user_list = $membercard_user_mdl->getQuery(['status' => 1, 'website_id' => $website_id], '*', '');
            foreach ($user_list as $k => $v) {
                if ($v['expire_time']) {
                    //如果不是永久有效的，先判断是否已到期
                    if (time() > $v['expire_time']) {
                        //已过期
                        $membercard_user_mdl = new VslMembercardUserModel();
                        $membercard_user_mdl->isUpdate()->save(['status' => 0], ['uid' => $v['uid'], 'website_id' => $v['website_id']]);
                        continue;
                    }
                }
                //判断会员持有的会员卡有没有周期送优惠券的权益
                $membercard_info = $membercard_mdl->getInfo(['id' => $v['membercard_id'], 'website_id' => $website_id], 'is_circle_coupon,circle_num,circle_coupon_id');
                if ($membercard_info) {
                    if ($membercard_info['is_circle_coupon']) {
                        //有周期送优惠券的权益
                        if ($v['last_give_coupon_time']) {
                            //不是第一次送优惠券，就判断最后一次送优惠券的时间跟当前时间的时间差
                            $time = intval(time() - $v['last_give_coupon_time']) / (60 * 60 * 24);
                            if ($time >= $membercard_info['circle_num']) {
                                if (getAddons('coupontype', $website_id)) {
                                    $membercard_info['circle_coupon_id'] = explode(',', $membercard_info['circle_coupon_id']);
                                    foreach ($membercard_info['circle_coupon_id'] as $k1 => $v1) {
                                        $coupon = new Coupon();
                                        $res = $coupon->userAchieveCoupon($v['uid'], $v1, 12);
                                    }
                                }
                                if ($res) {
                                    $membercard_user_mdl = new VslMembercardUserModel();
                                    $membercard_user_mdl->isUpdate()->save(['last_give_coupon_time' => time()], ['uid' => $v['uid'], 'website_id' => $v['website_id']]);
                                }
                            }
                        } else {
                            //第一次送优惠券，就判断购卡时间跟当前时间的时间差
                            $time = intval(time() - $v['create_time']) / (60 * 60 * 24);
                            if ($time >= $membercard_info['circle_num']) {
                                if (getAddons('coupontype', $website_id)) {
                                    $membercard_info['circle_coupon_id'] = explode(',', $membercard_info['circle_coupon_id']);
                                    foreach ($membercard_info['circle_coupon_id'] as $k1 => $v1) {
                                        $coupon = new Coupon();
                                        $res = $coupon->userAchieveCoupon($v['uid'], $v1, 12);
                                    }
                                }
                                if ($res) {
                                    $membercard_user_mdl = new VslMembercardUserModel();
                                    $membercard_user_mdl->isUpdate()->save(['last_give_coupon_time' => time()], ['uid' => $v['uid'], 'website_id' => $v['website_id']]);
                                }
                            }
                        }
                    }
                }
            }

            return $res;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    /**
     * 延迟队列周期送优惠券, 精确到用户
     */
    public function rabbitCircleGiveCoupon($membercard_id, $uid)
    {
        try {
            $membercard_mdl = new VslMembercardModel();
            $membercard_user_mdl = new VslMembercardUserModel();
            $membercard = new Membercard();
            $user_info = $membercard_user_mdl->getInfo(['status' => 1, 'membercard_id' => $membercard_id, 'uid' => $uid]);
            if ($user_info['expire_time']) {
                //如果不是永久有效的，先判断是否已到期
                if (time() > $user_info['expire_time']) {
                    //已过期
                    $membercard_user_mdl = new VslMembercardUserModel();
                    $membercard_user_mdl->isUpdate()->save(['status' => 0], ['uid' => $user_info['uid'], 'website_id' => $user_info['website_id']]);
                    return;
                }
            }
            //判断会员持有的会员卡有没有周期送优惠券的权益
            $membercard_info = $membercard_mdl->getInfo(['id' => $user_info['membercard_id'], 'website_id' => $user_info['website_id']], 'is_circle_coupon,circle_num,circle_coupon_id');
            if ($membercard_info) {
                if ($membercard_info['is_circle_coupon']) {
                    //有周期送优惠券的权益
                    if ($user_info['last_give_coupon_time']) {
                        //不是第一次送优惠券，就判断最后一次送优惠券的时间跟当前时间的时间差
                        $time = intval(time() - $user_info['last_give_coupon_time']) / (60 * 60 * 24);
                        if ($time >= $membercard_info['circle_num']) {
                            if (getAddons('coupontype', $user_info['website_id'])) {
                                $membercard_info['circle_coupon_id'] = explode(',', $membercard_info['circle_coupon_id']);
                                foreach ($membercard_info['circle_coupon_id'] as $k1 => $v1) {
                                    $coupon = new Coupon();
                                    $res = $coupon->userAchieveCoupon($uid, $v1, 12);
                                }
                            }
                            if ($res) {
                                $membercard_user_mdl = new VslMembercardUserModel();
                                $membercard_user_mdl->isUpdate()->save(['last_give_coupon_time' => time()], ['uid' => $user_info['uid'], 'website_id' => $user_info['website_id']]);
                                //继续加入延时队列
                                $membercard->rabbitCreateSendCoupon($uid, $membercard_id, $membercard_info['circle_num']);
                            }
                        }
                    } else {
                        //第一次送优惠券，就判断购卡时间跟当前时间的时间差
                        $time = intval(time() - $user_info['create_time']) / (60 * 60 * 24);
                        if ($time >= $membercard_info['circle_num']) {
                            if (getAddons('coupontype', $user_info['website_id'])) {
                                $membercard_info['circle_coupon_id'] = explode(',', $membercard_info['circle_coupon_id']);
                                foreach ($membercard_info['circle_coupon_id'] as $k1 => $v1) {
                                    $coupon = new Coupon();
                                    $res = $coupon->userAchieveCoupon($uid, $v1, 12);
                                }
                            }
                            if ($res) {
                                $membercard_user_mdl = new VslMembercardUserModel();
                                $membercard_user_mdl->isUpdate()->save(['last_give_coupon_time' => time()], ['uid' => $user_info['uid'], 'website_id' => $user_info['website_id']]);
                                //继续加入延时队列
                                $membercard->rabbitCreateSendCoupon($uid, $membercard_id, $membercard_info['circle_num']);
                            }
                        }
                    }
                }
            }
            return $res;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    /**
     * 会员卡推送到微信
     */
    public function membercardPushToWx($membercard_id, $membercard_name)
    {
        try {
            //图片要先上传至微信图片库
            //需要将外链图片先存放到服务器，再传入微信后再删除掉
            $membercard_config = $this->getConfig();
            $weixin_card = new WeixinCard();
            $goods_server = new Goods();
            $membercard_mdl = new VslMembercardModel();
            $need_delete = 0;
            $check_url = substr($membercard_config['wxcard_info']['shop_logo'], 0, 4);
            if ($check_url == 'http') {
                $dir = './upload/' . $this->website_id . '/wx_ticket_pic/';
                if (!is_dir($dir)) {
                    $res = mkdir(iconv("UTF-8", "GBK", $dir), 0777, true);
                }
                $file_name = time() . '.jpg';
                $goods_server->saveImage($membercard_config['wxcard_info']['shop_logo'], $dir . $file_name);
                $need_delete = 1;
                $pic_url = '/upload/' . $this->website_id . '/wx_ticket_pic/' . $file_name;
            } else {
                $pic_url = __IMG($membercard_config['wxcard_info']['shop_logo']);
            }
            $card_pic = $weixin_card->uploadLogo($pic_url);
            if ($need_delete == 1) {
                unlink('.' . $pic_url);
            }
            if (!empty($card_pic['url'])) {
                $card_info['shop_logo_url'] = $card_pic['url'];
            }

            //会员卡封面
            if ($membercard_config['wxcard_info']['membercard_cover'] == 2 && $membercard_config['wxcard_info']['background_logo']) {
                $need_delete = 0;
                $check_url = substr($membercard_config['wxcard_info']['background_logo'], 0, 4);
                if ($check_url == 'http') {
                    $dir = './upload/' . $this->website_id . '/wx_ticket_pic/';
                    if (!is_dir($dir)) {
                        $res = mkdir(iconv("UTF-8", "GBK", $dir), 0777, true);
                    }
                    $file_name = time() . '.jpg';
                    $goods_server->saveImage($membercard_config['wxcard_info']['background_logo'], $dir . $file_name);
                    $need_delete = 1;
                    $logo_url = '/upload/' . $this->website_id . '/wx_ticket_pic/' . $file_name;
                } else {
                    $logo_url = __IMG($membercard_config['wxcard_info']['background_logo']);
                }
                $card_pic = $weixin_card->uploadLogo($logo_url);
                if ($need_delete == 1) {
                    unlink('.' . $logo_url);
                }
                if (!empty($card_pic['url'])) {
                    $card_info['background_logo_url'] = $card_pic['url'];
                }

                //如果选择的是用图片做会员卡封面，那么就要默认一种背景颜色
                $card_info['card_color'] = '#63b359';
            }
            $card_info['brand_name'] = $membercard_config['wxcard_info']['shop_name'];//商户名称
            $card_info['title'] = $membercard_name;//会员卡名称
            $card_info['color'] = $membercard_config['wxcard_info']['card_color'] ?: $card_info['card_color'];//会员卡颜色
            $card_info['prerogative'] = $membercard_config['wxcard_info']['prerogative'];//会员卡特权说明
            $card_info['notice'] = '';//会员卡使用提醒
            $card_info['description'] = $membercard_config['wxcard_info']['description'];//会员卡使用说明
            $card_info['quantity'] = 100000000;//会员卡数量
            $card_info['type'] = 'DATE_TYPE_PERMANENT';//会员卡有效时间类型
            $card_info['service_phone'] = $membercard_config['wxcard_info']['shop_phone'] ?: '';//客服电话
            if ($membercard_config['wxcard_info']['store_service']) {//商家服务类型
                $card_info['business_service'] = $membercard_config['wxcard_info']['store_service'];
            }
            $card_info['auto_activate'] = true;//自动激活
            $ticket_result = $weixin_card->createMembercard($card_info);
            if ($ticket_result['card_id']) {
                $membercard_mdl->save(['wx_membercard_id' => $ticket_result['card_id']], ['id' => $membercard_id, 'website_id' => $this->website_id]);
            }
            return 1;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    
    /**
     * 处理会员卡 - 返积分
     * @param $payment_info
     */
    public function dealMembercardReturnPoint ($payment_info)
    {
        if (!$this->uid){return;}
        $membercard_data = $this->checkMembercardStatus($this->uid);
        # 先处理会员卡返积分
        if($membercard_data['status'] && $membercard_data['membercard_info']['is_give_point'] && $membercard_data['membercard_info']['point_type']) {
            $membercard_point_num = $membercard_data['membercard_info']['point_num'];
            if($membercard_data['membercard_info']['point_type'] == 1){/*翻倍*/
                //每一个店铺商品sku积分翻倍
                foreach ($payment_info as $shop_id => $shop_info){
                    foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                        $temp_return_point = $payment_info[$shop_id]['goods_list'][$sku]['return_point'];
                        $payment_info[$shop_id]['goods_list'][$sku]['return_point'] = round($temp_return_point*$membercard_point_num,2);
                        $payment_info[$shop_id]['goods_list'][$sku]['membercard_return_point'] = $payment_info[$shop_id]['goods_list'][$sku]['return_point'] - $temp_return_point;//记录会员卡返的积分
                    }
                }
            }elseif ($membercard_data['membercard_info']['point_type'] == 2){/*固定返回积分*/
                //按sku的price比例来分配总的返的会员卡积分
                $all_goods_price = 0;//统计所有商品price，用于比例计算
                $sku_num = 0;//有多少sku商品数
                foreach ($payment_info as $shop_id => $shop_info){
                    foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                        $all_goods_price = $all_goods_price + $sku_goods['price']*$sku_goods['num'] + $sku_goods['shipping_fee'];//总商品价格+运费
                        $sku_num++;
                    }
                }
                //计算会员卡返积分
                $has_deduct_membercard_point_num = 0;
                $i = 0;
                foreach ($payment_info as $shop_id => $shop_info){
                    foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                        if($i== $sku_num){/*最后一个*/
                            $ratio_point = $membercard_point_num - $has_deduct_membercard_point_num;
                        }else{
                            $ratio_point = round((($sku_goods['price']*$sku_goods['num']+$sku_goods['shipping_fee'])/$all_goods_price)*$membercard_point_num, 2);
                            $has_deduct_membercard_point_num += $ratio_point;
                            $i++;
                        }
                        # 累计
                        $payment_info[$shop_id]['goods_list'][$sku]['membercard_return_point'] = $ratio_point;
                        $payment_info[$shop_id]['goods_list'][$sku]['return_point'] += $ratio_point;
                    }
                }
            }
        }
        return $payment_info;
    }
    
    /**
     * 处理会员卡抵扣商品信息（普通、预售）
     * @param      $payment_info
     * @param bool $is_membercard_deduction
     * @param      $return_data
     * @return mixed
     */
    public function membercardReturnOrderInfo ($payment_info,$is_membercard_deduction=false,&$return_data)
    {
        
        $membercard_data = $this->checkMembercardStatus($this->uid);
        //处理会员卡显示
        $return_data['membercard_info'] = [
            'membercard_balance' => 0,
            'total_memebrcard_deduction' => 0,
        ];
        if($membercard_data['status']) {
            $return_data['membercard_info']['membercard_name'] = $membercard_data['membercard_info']['membercard_name'];
            $return_data['membercard_info']['membercard_balance'] = $membercard_data['membercard_info']['membercard_balance'];
        }
        if (!$this->uid){return $payment_info;}
        $membercard_balance = 0;
        if($membercard_data['status']) {
            $membercard_balance = $membercard_data['membercard_info']['membercard_balance'];
        }

        if ($membercard_balance == 0){return $payment_info;}
        if (!$is_membercard_deduction){return $payment_info;}
    
        //1.先处理real_money
        foreach ($payment_info as $shop_id => $shop_info){
            foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                $payment_info[$shop_id]['goods_list'][$sku]['real_money1'] = $payment_info[$shop_id]['goods_list'][$sku]['real_money']*$sku_goods['num']; 
                $payment_info[$shop_id]['goods_list'][$sku]['real_money'] = $payment_info[$shop_id]['goods_list'][$sku]['all_real_money'];  //这里已经是拿质数前的金额
                //---直接获取总的
            }
            
            if ($shop_info['presell_info']){
                $payment_info[$shop_id]['presell_info']['firstmoney'] = bcmul($payment_info[$shop_id]['presell_info']['firstmoney'],$payment_info[$shop_id]['presell_info']['goods_num'],2);
            }
        }
        
        //从上往下抵扣
        $all_has_deduct_money = 0;
        $presell_first_deduct = 0;//预售定金抵扣
        $is_presell = false;
        foreach ($payment_info as $shop_id => $shop_info){
            $shop_has_deduct_money=0;
            if($shop_info['presell_info']){
                $is_presell = true;
                //预售
                if($membercard_balance<=0){continue;}
                $deduct_moneyShipping = $membercard_balance - ($shop_info['presell_info']['firstmoney']+$shop_info['presell_info']['final_real_money']);//抵扣商品(定金+尾款,运费)
                if ($deduct_moneyShipping>=0){/*可抵扣全部*/
                    $presell_first_deduct = $payment_info[$shop_id]['presell_info']['firstmoney'];
                    $payment_info[$shop_id]['presell_info']['membercard_deduction_money'] = $payment_info[$shop_id]['presell_info']['firstmoney']+$payment_info[$shop_id]['presell_info']['final_real_money'];//会员卡抵扣的费用
                    $membercard_balance = $deduct_moneyShipping;//会员卡剩余余额
                    //                    $payment_info[$shop_id]['presell_info']['firstmoney'] = $payment_info[$shop_id]['presell_info']['final_real_money'] = $payment_info[$shop_id]['presell_info']['shipping_fee'] =0;
                    $payment_info[$shop_id]['presell_info']['firstmoney'] = $payment_info[$shop_id]['presell_info']['final_real_money'] = 0;
                    $payment_info[$shop_id]['presell_info']['membercard_deduct_shipping_fee'] = $payment_info[$shop_id]['presell_info']['shipping_fee'];
                }else{
                    $deduct_money = $membercard_balance - $payment_info[$shop_id]['presell_info']['final_real_money'];//抵扣商品（尾款,运费）
                    if($deduct_money>=0){/*只够抵扣介于定金与（尾款，运费）*/
                        $payment_info[$shop_id]['presell_info']['membercard_deduction_money'] = $payment_info[$shop_id]['presell_info']['final_real_money'];//会员卡抵扣的费用
                        //                        $membercard_balance = $membercard_balance-$payment_info[$shop_id]['presell_info']['final_real_money'];//会员卡剩余余额
                        //抵扣定金
                        $actual_first_money = $payment_info[$shop_id]['presell_info']['firstmoney'] - $deduct_money;//扣除尾款后，扣定金.
                        $presell_first_deduct = $deduct_money;//定金抵扣了多少
                        $payment_info[$shop_id]['presell_info']['firstmoney'] = $actual_first_money;//定金剩余多少
                        $payment_info[$shop_id]['presell_info']['membercard_deduction_money'] += $deduct_money;
                        $membercard_balance = 0;//会员卡剩余余额
                        //                        $payment_info[$shop_id]['presell_info']['final_real_money'] = $payment_info[$shop_id]['presell_info']['shipping_fee'] = 0;
                        $payment_info[$shop_id]['presell_info']['final_real_money'] = 0;
                        $payment_info[$shop_id]['presell_info']['membercard_deduct_shipping_fee'] = $payment_info[$shop_id]['presell_info']['shipping_fee'];
                    }else{
                        $final_money = $payment_info[$shop_id]['presell_info']['final_real_money']-$payment_info[$shop_id]['presell_info']['shipping_fee']>0?$payment_info[$shop_id]['presell_info']['final_real_money']-$payment_info[$shop_id]['presell_info']['shipping_fee']:0;
                        $deduct_final_money = $membercard_balance - $final_money;
                        //优先抵扣尾款
                        if($deduct_final_money>=0){/*如果余额比实际尾款大*/
                            $actual_shipping = $payment_info[$shop_id]['presell_info']['final_real_money'] - $membercard_balance;//是剩余运费，也是总尾款（实际尾款为0）
                            //                            $payment_info[$shop_id]['presell_info']['final_real_money'] = $payment_info[$shop_id]['presell_info']['shipping_fee'] = $actual_shipping;
                            $payment_info[$shop_id]['presell_info']['final_real_money'] = $actual_shipping;
                            $membercard_balance = 0;
                            $payment_info[$shop_id]['presell_info']['membercard_deduct_shipping_fee'] = $deduct_final_money;
                        }else{/*如果余额比实际尾款小*/
                            $payment_info[$shop_id]['presell_info']['final_real_money'] = $payment_info[$shop_id]['presell_info']['final_real_money']-$membercard_balance;
                            $payment_info[$shop_id]['presell_info']['membercard_deduction_money'] = $membercard_balance;//会员卡抵扣的费用
                            $membercard_balance = 0;
                            $payment_info[$shop_id]['presell_info']['membercard_deduct_shipping_fee'] = 0;
                        }
                    }
                }
                $shop_has_deduct_money += $payment_info[$shop_id]['presell_info']['membercard_deduction_money'];
                
                if ($presell_first_deduct){
                    $payment_info[$shop_id]['total_amount'] = $payment_info[$shop_id]['total_amount'] - $presell_first_deduct > 0 ? $payment_info[$shop_id]['total_amount'] - $presell_first_deduct:0;
                }
                $payment_info[$shop_id]['membercard_deduction_money'] = $shop_has_deduct_money;
                foreach ($payment_info[$shop_id]['goods_list'] as $k => $v){
                    $payment_info[$shop_id]['goods_list'][$k]['membercard_deduction_money'] = $shop_has_deduct_money;
                }
            }else{
                $shop_has_deduct_money=0;
                foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                    if($membercard_balance<=0){continue;}
                    $deduct_moneyShipping = $membercard_balance - ($sku_goods['real_money']+$sku_goods['shipping_fee']);//抵扣商品(费用+运费)
                    
                    if ($deduct_moneyShipping>=0){
                        
                        //TODO... 完全抵扣
                        $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduction_money'] = $sku_goods['real_money']+$sku_goods['shipping_fee'];//会员卡抵扣的费用
                        $membercard_balance = $deduct_moneyShipping;//会员卡剩余余额
                        $payment_info[$shop_id]['goods_list'][$sku]['real_money'] = 0;//因为real_money包含运费
                        //                        $payment_info[$shop_id]['goods_list'][$sku]['shipping_fee'] = 0;
                        $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduct_shipping_fee'] = $payment_info[$shop_id]['goods_list'][$sku]['shipping_fee'];//会员卡抵扣的运费
                        
                    }else{
                        
                        //说明余额不足抵扣（费用+运费）
                        $deduct_money = $membercard_balance - $sku_goods['real_money'];//抵扣商品（费用）

                        if($deduct_money>=0){
                            //判断剩余会员卡金额与运费比较
                            if($deduct_money<$sku_goods['shipping_fee']){
                            //                                $payment_info[$shop_id]['goods_list'][$sku]['shipping_fee'] = $sku_goods['shipping_fee'] - $deduct_money;
                                $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduction_money'] = $membercard_balance;//会员卡抵扣的费用
                                $membercard_balance = 0;//会员卡剩余余额
                                $payment_info[$shop_id]['goods_list'][$sku]['real_money'] = 0;
                                $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduct_shipping_fee'] = $deduct_money;//会员卡抵扣的运费
                            }
                            
                        }else{
                            //会员卡不够抵扣real_money
                            //                            $payment_info[$shop_id]['goods_list'][$sku]['shipping_fee'] = $sku_goods['shipping_fee'];
                            $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduction_money'] = $membercard_balance;//会员卡抵扣的费用
                            $payment_info[$shop_id]['goods_list'][$sku]['real_money'] = $payment_info[$shop_id]['goods_list'][$sku]['real_money'] - $membercard_balance>0?$payment_info[$shop_id]['goods_list'][$sku]['real_money'] - $membercard_balance:0;
                            $membercard_balance = 0;//会员卡剩余余额
                            $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduct_shipping_fee'] = 0;//会员卡抵扣的运费
                        }
                    }
                    
                    $shop_has_deduct_money += $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduction_money']; //店铺抵扣的钱
                    $payment_info[$shop_id]['membercard_deduction_money'] = $shop_has_deduct_money;
                    
                }
                $payment_info[$shop_id]['total_amount'] = $payment_info[$shop_id]['total_amount'] - $shop_has_deduct_money > 0 ? $payment_info[$shop_id]['total_amount'] - $shop_has_deduct_money:0;
            }
            $all_has_deduct_money += $shop_has_deduct_money;
        }
        //2、最后处理real_mooney
        foreach ($payment_info as $shop_id => $shop_info){
            if ($shop_info['presell_info']){
                $payment_info[$shop_id]['goods_list'][$sku]['real_money'] = roundLengthNumber($payment_info[$shop_id]['presell_info']['final_real_money']/$payment_info[$shop_id]['presell_info']['goods_num'],2,false);
                $payment_info[$shop_id]['goods_list'][$sku]['membercard_deduct_shipping_fee'] = $payment_info[$shop_id]['presell_info']['membercard_deduct_shipping_fee'];
                $payment_info[$shop_id]['presell_info']['firstmoney'] = roundLengthNumber($payment_info[$shop_id]['presell_info']['firstmoney']/$payment_info[$shop_id]['presell_info']['goods_num'],2,false);
            }else{
                foreach ($shop_info['goods_list'] as $sku => $sku_goods){
                    $check_money = $payment_info[$shop_id]['goods_list'][$sku]['real_money'];
                    
                    $payment_info[$shop_id]['goods_list'][$sku]['real_money'] = roundLengthNumber($payment_info[$shop_id]['goods_list'][$sku]['real_money']/$sku_goods['num'],2,false);
                    if($payment_info[$shop_id]['goods_list'][$sku]['real_money'] * $sku_goods['num'] != $check_money){
                        $remainder = bcsub($check_money,$payment_info[$shop_id]['goods_list'][$sku]['real_money'] * $sku_goods['num'],2);
                        $payment_info[$shop_id]['goods_list'][$sku]['remainder'] = $remainder;
                    }else{
                        $payment_info[$shop_id]['goods_list'][$sku]['remainder'] = 0;
                    }
                }
            }
        }
        //处理会员卡显示余额
        if($membercard_data['status']) {
            $return_data['membercard_info']['membercard_balance'] = $membercard_balance;
            $return_data['membercard_info']['total_memebrcard_deduction'] = $all_has_deduct_money;
            $return_data['total_memebrcard_deduction'] = $all_has_deduct_money;
            if ($is_presell){
                $return_data['presell_first_deduct'] = $presell_first_deduct;
                $return_data['amount'] = $return_data['amount'] - $presell_first_deduct;
            }else{
                $return_data['amount'] = bcsub($return_data['amount'],$all_has_deduct_money,2);
                $return_data['amount'] = floatval($return_data['amount']);
                // $return_data['amount'] = $return_data['amount'] - $all_has_deduct_money;
            }
        }
        return $payment_info;
    }

    /**
     * 处理购卡/升级返佣、续费返佣
     */
    public function dealReturnCommission($membercard_detail,$type,$uid,$pay_money,$data_id)
    {
        Db::startTrans();
        try{
            $commissionA_id = 0;
            $commissionA = 0;//一级佣金和对应的id
            $commissionB_id = 0;
            $commissionB = 0;//二级佣金和对应的id
            $commissionC_id = 0;
            $commissionC = 0;//三级佣金和对应的id

            //分销基本设置
            $addonsConfigService = new AddonsConfigService();
            $base_info = $addonsConfigService ->getAddonsConfig("distribution",$this->website_id, 0, 1);

            if($type == 1) {
                //购卡、升级
                $commission_arr = $membercard_detail['commission_buy_val'];
                $commission_type = $membercard_detail['commission_buy_type'];
            }else{
                //续费
                $commission_arr = $membercard_detail['commission_renew_val'];
                $commission_type = $membercard_detail['commission_renew_type'];
            }
            $commission_arr = json_decode($commission_arr,true);

            $member_mdl = new VslMemberModel();
            $distributor = $member_mdl->getInfo(['uid' => $uid],'referee_id');
            if ($base_info['distribution_pattern'] >= 1 && $distributor['referee_id']) {//一级分销模式
                $distributorA = $member_mdl->getInfo(['uid' => $distributor['referee_id']],'isdistributor,distributor_level_id,referee_id');
                if ($distributorA && $distributorA['isdistributor'] == 2) {
                    $commissionA_id = $distributor['referee_id'];
                    foreach ($commission_arr as $k => $v) {
                        $v = explode(',',$v);
                        if($distributorA['distributor_level_id'] == $v[0]) {
                            if($commission_type == 1) {
                                //固定金额
                                $commissionA = $v[1];
                            }else{
                                //比例
                                $commissionA = twoDecimal($pay_money * $v[1] / 100);
                            }
                            break;
                        }
                    }
                }

                if ($base_info['distribution_pattern'] >= 2 && $distributorA['referee_id']) {//二级分销模式
                    $distributorB = $member_mdl->getInfo(['uid' => $distributorA['referee_id']],'isdistributor,distributor_level_id,referee_id');
                    if ($distributorB && $distributorB['isdistributor'] == 2) {
                        $commissionB_id = $distributorA['referee_id'];
                        foreach ($commission_arr as $k => $v) {
                            $v = explode(',',$v);
                            if($distributorB['distributor_level_id'] == $v[0]) {
                                if($commission_type == 1) {
                                    //固定金额
                                    $commissionB = $v[2];
                                }else{
                                    //比例
                                    $commissionB = twoDecimal($pay_money * $v[2] / 100);
                                }
                                break;
                            }
                        }
                    }

                    if ($base_info['distribution_pattern'] >= 3 && $distributorB['referee_id']) {//三级分销模式
                        $distributorC = $member_mdl->getInfo(['uid' => $distributorB['referee_id']],'isdistributor,distributor_level_id');
                        if ($distributorC && $distributorC['isdistributor'] == 2) {
                            $commissionC_id = $distributorB['referee_id'];
                            foreach ($commission_arr as $k => $v) {
                                $v = explode(',',$v);
                                if($distributorC['distributor_level_id'] == $v[0]) {
                                    if($commission_type == 1) {
                                        //固定金额
                                        $commissionC = $v[3];
                                    }else{
                                        //比例
                                        $commissionC = twoDecimal($pay_money * $v[3] / 100);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
            }

            if($commissionA_id && $commissionA) {
                $res = $this->addCommission($commissionA_id,$commissionA,$type,$data_id);
            }
            if($commissionB_id && $commissionB) {
                $res = $this->addCommission($commissionB_id,$commissionB,$type,$data_id);
            }
            if($commissionC_id && $commissionC) {
                $res = $this->addCommission($commissionC_id,$commissionC,$type,$data_id);
            }

            Db::commit();
            return $res;
        }catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 添加佣金
     */
    public function addCommission($uid,$commission,$type,$data_id)
    {
        Db::startTrans();
        try{
            $account_statistics = new VslDistributorAccountModel();
            $account = new VslAccountModel();
            $count = $account_statistics->getInfo(['uid'=> $uid],'commission');//佣金账户
            $account_count = $account->getInfo(['website_id'=> $this->website_id],'commission');//平台账户

            //更新账户
            if($count) {
                $account_data = array(
                    'commission' => $count['commission'] + abs($commission)
                );
                $account_statistics->isUpdate(true)->save($account_data, ['uid' => $uid]);
            }else{
                $account_data = array(
                    'commission' => abs($commission),
                    'uid' => $uid,
                    'website_id' => $this->website_id
                );
                $account_statistics->save($account_data);
            }
            //平台账户佣金改变
            if ($account_count) {
                $commission_data = array(
                    'commission' => $account_count['commission'] + abs($commission)
                );
                $account->isUpdate(true)->save($commission_data, ['website_id' => $this->website_id]);
            }
            //添加流水
            if($type == 1) {
                $text = '下级购买会员卡，可提现佣金增加';
                $from_type = 25;
            }else{
                $text = '下级续费会员卡，可提现佣金增加';
                $from_type = 26;
            }
            $data_records = array(//订单完成
                'uid' => $uid,
                'records_no'=> 'CR'.time() . rand(111, 999),
                'data_id' => $data_id,
                'commission' => $commission,
                'balance'=> $count['commission'] + abs($commission),
                'from_type' => $from_type,
                'website_id' => $this->website_id,
                'text' => $text,
                'create_time' => time(),
                'shop_id' => $this->instance_id
            );
            $distributor_account = new VslDistributorAccountRecordsModel();
            $res = $distributor_account->save($data_records);

            Db::commit();
            return $res;
        }catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
}