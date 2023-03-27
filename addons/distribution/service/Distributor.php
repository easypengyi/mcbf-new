<?php

namespace addons\distribution\service;

/**
 * 分销商服务层
 */

use addons\areabonus\service\AreaBonus;
use addons\bonus\model\VslAgentLevelModel;
use addons\bonus\model\VslBonusAccountModel;
use addons\bonus\model\VslOrderBonusLogModel;
use addons\customform\server\Custom as CustomSer;
use addons\distribution\model\VslDistributorAccountRecordsModel;
use addons\globalbonus\service\GlobalBonus;
use addons\groupshopping\server\GroupShopping;
use addons\teambonus\service\TeamBonus;
use data\model\VslAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberModel;
use data\model\VslMemberRefereeLogModel;
use data\model\VslMemberViewModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use data\model\AlbumPictureModel;
use data\model\VslGoodsSkuModel;
use data\service\AddonsConfig as AddonsConfigSer;
use data\service\BaseService as BaseService;
use data\model\UserModel;
use addons\distribution\model\VslDistributorLevelModel as DistributorLevelModel;
use data\service\Config as ConfigService;
use data\service\Events;
use data\service\Member;
use data\service\Order\OrderStatus;
use addons\distribution\model\VslOrderDistributorCommissionModel;
use addons\distribution\model\VslDistributorAccountModel;
use addons\distribution\model\VslDistributorAccountRecordsViewModel;
use addons\distribution\model\VslDistributorCommissionWithdrawModel;
use addons\distribution\model\VslDistributorRefereeLogModel;
use data\service\Order\OrderAccount;
use data\service\Pay\tlPay;
use data\service\ShopAccount;
use data\model\VslMemberBankAccountModel;
use data\model\VslMemberAccountModel;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\Pay\AliPay;
use data\service\Pay\WeiXinPay;
use addons\customform\server\Custom as CustomServer;
use data\model\VslOrderGoodsViewModel;
use addons\channel\server\Channel;
use think\Db;
use data\service\Goods;

class Distributor extends BaseService
{
    private $fre_commission;
    private $wit_commission;
    private $wits_commission;
    private $distributor;
    protected $goods_ser = '';
    public function __construct()
    {
        parent::__construct();
        $set = $this->getAgreementSite($this->website_id);
        if ($set && $set['distributor_name']) {
            $this->distributor = $set['distributor_name'];
        } else {
            $this->distributor = '分销商';
        }
        if ($set && $set['frozen_commission']) {
            $this->fre_commission = $set['frozen_commission'];
        } else {
            $this->fre_commission = '冻结佣金';
        }
        if ($set &&  $set['withdrawable_commission']) {
            $this->wit_commission = $set['withdrawable_commission'];
        } else {
            $this->wit_commission = '可提现佣金';
        }
        if ($set &&  $set['withdrawals_commission']) {
            $this->wits_commission = $set['withdrawals_commission'];
        } else {
            $this->wits_commission = '已提现佣金';
        }
        $this->goods_ser = new Goods();
    }

    /**
     * 修改分销商申请状态
     */
    public function setStatus($uid, $status)
    {
        $level = new DistributorLevelModel();
        $level_info = $level->getInfo(['website_id' => $this->website_id, 'is_default' => 1], '*');
        $level_id = $level_info['id'];
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid]);
        if ($status == 2) {
            $extend_code = $this->create_extend();
            $data = array(
                'isdistributor' => $status,
                'extend_code' => $extend_code,
                'apply_distributor_time' => time(),
                'become_distributor_time' => time(),
                'distributor_level_id' => $level_id
            );
        } else {
            $data = array(
                'isdistributor' => $status
            );
        }
        $res = $member->save($data, [
            'uid' => $uid
        ]);
        if ($res && $status == 2) {
            $config = new AddonsConfigService();
            $base_info = $config->getAddonsConfig("distribution", $this->website_id, 0, 1); //基本设置
            $distribution_pattern = $base_info['distribution_pattern'];
            $ratio = 0;
            if ($distribution_pattern >= 1) {
                $ratio .= '一级返佣比例' . $level_info['commission1'] . '%';
            }
            if ($distribution_pattern >= 2) {
                $ratio .= ',二级返佣比例' . $level_info['commission2'] . '%';
            }
            if ($distribution_pattern >= 3) {
                $ratio .= ',三级返佣比例' . $level_info['commission3'] . '%';
            }

            if ($base_info['distribution_pattern'] >= 1) {
                if ($member_info['referee_id']) {
                    $recommend1_info = $member->getInfo(['uid' => $member_info['referee_id']], '*');
                    if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                        $level_info1 = $level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                        $recommend1 = $level_info1['recommend1']; //一级推荐奖
                        $recommend_point1 = $level_info1['recommend_point1']; //一级推荐积分
                        $recommend_beautiful_point1 = $level_info1['recommend_beautiful_point1']; //一级推荐美丽分
                        $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $member_info['website_id'],$recommend_beautiful_point1);
                    }
                }
            }
            if ($base_info['distribution_pattern'] >= 2) {
                $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                    $level_info2 = $level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                    $recommend2 = $level_info2['recommend2']; //二级推荐奖
                    $recommend_point2 = $level_info2['recommend_point2']; //二级推荐积分
                    $recommend_beautiful_point2 = $level_info2['recommend_beautiful_point2']; //二级推荐美丽分
                    $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $member_info['website_id'],$recommend_beautiful_point2);
                }
            }
            if ($base_info['distribution_pattern'] >= 3) {
                $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                    $level_info3 = $level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                    $recommend3 = $level_info3['recommend3']; //三级推荐奖
                    $recommend_point3 = $level_info3['recommend_point3']; //三级推荐积分
                    $recommend_beautiful_point3 = $level_info3['recommend_beautiful_point3']; //三级推荐美丽分
                    $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $member_info['website_id'],$recommend_beautiful_point2);
                }
            }
            runhook("Notify", "sendCustomMessage", ["messageType" => "become_distributor", "uid" => $uid, "become_time" => time(), 'ratio' => $ratio, 'level_name' => $level_info['level_name']]); //用户成为分销商提醒
            runhook("Notify", "successfulDistributorByTemplate", ["uid" => $uid, "website_id" => $this->website_id]); //用户成为分销商提醒
        }
        return $res;
    }
    public function updateMemberDistributor($uid)
    {
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid], '*');
        $level = new DistributorLevelModel();
        $level_info = $level->getInfo(['website_id' => $this->website_id, 'is_default' => 1], '*');
        $level_id = $level_info['id'];
        $extend_code = $this->create_extend();
        $data = array(
            'isdistributor' => 2,
            'extend_code' => $extend_code,
            'become_distributor_time' => time(),
            'distributor_level_id' => $level_id
        );
        $account = new VslDistributorAccountModel();
        $account->save(['website_id' => $this->website_id, 'uid' => $uid]);
        $referee_id = $member->getInfo(['uid' => $uid], 'referee_id')['referee_id'];
        if ($referee_id) {
            $this->updateDistributorLevelInfo($referee_id);
            if (getAddons('globalbonus', $this->website_id)) {
                $global = new GlobalBonus();
                $global->updateAgentLevelInfo($referee_id);
                $global->becomeAgent($referee_id);
            }
            if (getAddons('areabonus', $this->website_id)) {
                $area = new AreaBonus();
                $area->updateAgentLevelInfo($referee_id);
            }
            if (getAddons('teambonus', $this->website_id)) {
                $team = new TeamBonus();
                $team->updateAgentLevelInfo($referee_id);
                $team->becomeAgent($referee_id);
            }
        }
        $res = $member->save($data, [
            'uid' => $uid
        ]);
        $config = new AddonsConfigService();
        $base_info = $config->getAddonsConfig("distribution", $this->website_id, 0, 1); //基本设置
        $distribution_pattern = $base_info['distribution_pattern'];
        $ratio = 0;
        if ($distribution_pattern >= 1) {
            $ratio .= '一级返佣比例' . $level_info['commission1'] . '%';
        }
        if ($distribution_pattern >= 2) {
            $ratio .= ',二级返佣比例' . $level_info['commission2'] . '%';
        }
        if ($distribution_pattern >= 3) {
            $ratio .= ',三级返佣比例' . $level_info['commission3'] . '%';
        }
        if ($base_info['distribution_pattern'] >= 1) {
            if ($member_info['referee_id']) {
                $recommend1_info = $member->getInfo(['uid' => $member_info['referee_id']], '*');
                if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                    $level_info1 = $level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                    $recommend1 = $level_info1['recommend1']; //一级推荐奖
                    $recommend_point1 = $level_info1['recommend_point1']; //一级推荐积分
                    $recommend_beautiful_point1 = $level_info1['recommend_beautiful_point1']; //一级推荐美丽分
                    $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $member_info['website_id'],$recommend_beautiful_point1);
                }
            }
        }
        if ($base_info['distribution_pattern'] >= 2) {
            $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
            if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                $level_info2 = $level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                $recommend2 = $level_info2['recommend2']; //二级推荐奖
                $recommend_point2 = $level_info2['recommend_point2']; //二级推荐积分
                $recommend_beautiful_point2 = $level_info2['recommend_beautiful_point2']; //二级推荐美丽分
                $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $member_info['website_id'],$recommend_beautiful_point2);
            }
        }
        if ($base_info['distribution_pattern'] >= 3) {
            $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
            if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                $level_info3 = $level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                $recommend3 = $level_info3['recommend3']; //三级推荐奖
                $recommend_point3 = $level_info3['recommend_point3']; //三级推荐积分
                $recommend_beautiful_point3 = $level_info3['recommend_beautiful_point3']; //三级推荐积分
                $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $member_info['website_id'],$recommend_beautiful_point3);
            }
        }
        runhook("Notify", "sendCustomMessage", ["messageType" => "become_distributor", "uid" => $uid, "become_time" => time(), 'ratio' => $ratio, 'level_name' => $level_info['level_name']]); //用户成为分销商提醒
        return $res;
    }
    /**
     * 修改推荐人
     */
    public function updateRefereeDistributor($uid, $referee_id, $admin_uid = 0)
    {
        if ($uid != $referee_id) {
            $shop = new VslMemberModel();
            $data = array(
                "referee_id" => $referee_id
            );
            $user_info = $shop->getInfo(['uid' => $uid], 'referee_id');
            $res = $shop->save($data, [
                'uid' => $uid
            ]);
            $this->addRefereeLog($uid, $referee_id, $this->website_id, $this->instance_id, $user_info['referee_id'], $admin_uid, 1);
            if ($referee_id) {
                $this->updateDistributorLevelInfo($referee_id);
            }
            if (getAddons('globalbonus', $this->website_id) && $referee_id) {
                $global = new GlobalBonus();
                $global->becomeAgent($referee_id);
                $global->updateAgentLevelInfo($referee_id);
            }
            if (getAddons('areabonus', $this->website_id) && $referee_id) {
                $area = new AreaBonus();
                $area->updateAgentLevelInfo($referee_id);
            }
            if (getAddons('teambonus', $this->website_id) && $referee_id) {
                $team = new TeamBonus();
                $team->becomeAgent($referee_id);
                $team->updateAgentLevelInfo($referee_id);
            }
            return $res;
        } else {
            return -1;
        }
    }
    /**
     * 修改推荐人
     */
    public function updateLowerRefereeDistributor($uid, $referee_id)
    {
        if ($uid != $referee_id) {
            $member = new VslMemberModel();
            $uids = $member->Query(['referee_id' => $uid], 'uid');
            $data = array(
                "referee_id" => $referee_id
            );
            $res = $member->save($data, [
                'uid' => ['in', implode(',', $uids)]
            ]);
            $this->updateDistributorLevelInfo($referee_id);
            if (getAddons('globalbonus', $this->website_id)) {
                $global = new GlobalBonus();
                $global->updateAgentLevelInfo($referee_id);
                $global->becomeAgent($referee_id);
            }
            if (getAddons('areabonus', $this->website_id)) {
                $area = new AreaBonus();
                $area->updateAgentLevelInfo($referee_id);
            }
            if (getAddons('teambonus', $this->website_id)) {
                $team = new TeamBonus();
                $team->updateAgentLevelInfo($referee_id);
                $team->becomeAgent($referee_id);
            }
            return $res;
        } else {
            return -1;
        }
    }
    /**
     * 检查是否存在直属下级
     */
    public function checkDistributor($uid)
    {
        $shop = new VslMemberModel();
        $id = $shop->getInfo(["referee_id" => $uid], '*');
        $res = -1;
        if ($id) {
            $res = 1;
        }
        return $res;
    }
    /**
     * 判断是否开启自定义模版
     *
     */
    public function check_start_template($condition)
    {
        return Db::table('sys_custom_template')->where($condition)->value('id');
    }

    //获取所有下级
    public function getAllChild($uid = 0, $website_id = 0)
    {
        if (empty($uid)) {
            return [];
        }
        $distributor = new VslMemberModel();
        $allchild = array();

        $level1_agentids = $distributor->Query(['referee_id' => $uid, 'website_id' => $website_id], 'uid');

        if (!empty($level1_agentids)) {
            $ids = array_values($level1_agentids);

            $allchild = array_merge($allchild, $ids);

            $idss = implode(",", $ids);

            $temp = $this->getAllChildByIn($idss, $website_id);

            $allchild = array_merge($allchild, $temp);
        }
        return $allchild;
    }
    public function getAllChildByIn($idss, $website_id)
    {
        $distributor = new VslMemberModel();
        $level1_agentids = $distributor->Query(['referee_id' => ['in', $idss], 'website_id' => $website_id], 'uid');
        $ids = array_values($level1_agentids);
        if (!empty($ids)) {
            $idss = implode(",", $ids);
            $temp = $this->getAllChildByIn($idss, $website_id);
            $ids = array_merge($ids, $temp);
        }
        return $ids;
    }
    /**
     * 后台客户列表
     */
    public function getDistributorList2($uid, $page_index = 1, $page_size = 0, $where = '', $order = '', $commission_levels = 4)
    {
        $distributor = new VslMemberModel();
        $user = new UserModel();
        $website_id = $where['nm.website_id'];
        $distributor_view = new VslMemberViewModel();
        $list = $this->getDistributionSite($website_id);
        if ($uid &&  $list['distribution_pattern'] >= 1 && $commission_levels >= 1) {
            $id1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => ['neq', 2], 'website_id' => $website_id], 'uid');
            if ($id1) {
                $where['nm.uid'] = ['in', implode(',', $id1)];
            } else {
                $where['nm.uid'] = ['in', ''];
            }
        }
        $result = $distributor_view->getDistributorViewList($page_index, $page_size, $where, $order);

        $condition['website_id'] = $website_id;
        $condition['isdistributor'] = ['in', '1,0,-1'];
        $result['count'] = $distributor_view->getCount($condition);
        $condition['isdistributor'] = 2;
        $result['count1'] = $distributor_view->getCount($condition);
        $condition['isdistributor'] = 1;
        $result['count2'] = $distributor_view->getCount($condition);
        $condition['isdistributor'] = -1;
        $result['count3'] = $distributor_view->getCount($condition);
        if ($result['data']) {
            foreach ($result['data'] as $k => $v) {
                //是否拥有设为股东 设为区代 设为队长 设为渠道商 设为店长权限 查询会员是否已经是已有权限

                $result['data'][$k]['global_status'] = getAddons('globalbonus', $website_id);
                $result['data'][$k]['area_status'] = getAddons('areabonus', $website_id);
                $result['data'][$k]['team_status'] = getAddons('teambonus', $website_id);
                $result['data'][$k]['microshop_status'] = getAddons('microshop', $website_id);
                $result['data'][$k]['channel_status'] = getAddons('channel', $website_id);
                if ($v['is_global_agent'] == 2) {
                    $result['data'][$k]['global_status'] = 0;
                }
                if ($v['is_area_agent'] == 2) {
                    $result['data'][$k]['area_status'] = 0;
                }
                if ($v['is_team_agent'] == 2) {
                    $result['data'][$k]['team_status'] = 0;
                }
                if ($v['isshopkeeper'] == 2) {
                    $result['data'][$k]['microshop_status'] = 0;
                }
                if ($result['data'][$k]['channel_status']) {
                    //查询当前会员是否是渠道商
                    $channel = new Channel();

                    $condition_channel['c.website_id'] = $v['website_id'];
                    $condition_channel['c.uid'] = $v['uid'];

                    $channel_info = $channel->getMyChannelInfo($condition_channel);

                    if ($channel_info) {
                        $result['data'][$k]['channel_status'] = 0;
                    }
                }

                $agentcount = 0;
                $ids1 = 0;
                $ids2 = 0;
                $result['data'][$k]['commission'] = 0;
                $user_info = $user->getInfo(['uid' => $v['referee_id']], 'user_name,nick_name,user_headimg');
                $result['data'][$k]['withdrawals'] = 0;
                if ($user_info['user_name']) {
                    $result['data'][$k]['referee_name'] = $user_info['user_name']; //推荐人
                } else {
                    $result['data'][$k]['referee_name'] = $user_info['nick_name']; //推荐人
                }
                if (empty($result['data'][$k]['user_name'])) {
                    $result['data'][$k]['user_name'] =  $result['data'][$k]['nick_name'];
                }
                $result['data'][$k]['referee_headimg'] = $user_info['user_headimg']; //推荐人
                if (1 <= $list['distribution_pattern'] && $commission_levels >= 1) {
                    $ids1 = $distributor->Query(['referee_id' => $v['uid']], 'uid');
                    if ($ids1) {
                        $number1 = count($ids1); //一级人数
                        $agentcount += $number1;
                    }
                }
                if (2 <= $list['distribution_pattern'] && $commission_levels >= 2) {
                    if ($ids1) {
                        $ids2 = $distributor->Query(['referee_id' => ['in', implode(',', $ids1)]], 'uid');
                        if ($ids2) {
                            $number2 = count($ids2); //二级人数
                            $agentcount += $number2;
                        }
                    }
                }
                if (3 <= $list['distribution_pattern'] && $commission_levels >= 3) {
                    if ($ids2) {
                        $ids3 = $distributor->Query(['referee_id' => ['in', implode(',', $ids2)]], 'uid');
                        if ($ids3) {
                            $number3 = count($ids3); //三级人数
                            $agentcount += $number3;
                        }
                    }
                }
                $result['data'][$k]['lower_id'] = $distributor->Query(['referee_id' => $v['uid']], 'uid'); //当前用户是否有下级
                $result['data'][$k]['distributor_number'] = $agentcount; //下级总人数
                $commission = new VslDistributorAccountModel();
                $commission_info = $commission->getInfo(['uid' => $v['uid']], '*');
                if ($commission_info) {
                    $result['data'][$k]['commission'] = $commission_info['commission']; //可用佣金
                    $result['data'][$k]['withdrawals'] = $commission_info['withdrawals']; //提现佣金
                }

            }
        }
        if ($uid) {
            $result['commission'] = 0;
            $result['withdrawals'] = 0;
            $result['number1'] = 0;
            $result['user_count'] = 0;
            $result['number2'] = 0;
            $result['number3'] = 0;
            $result['agentcount'] = 0;
            $result['all_child'] = 0;
            //获取所有下级
            $all_child = $this->getAllChild($uid, $website_id);
            if ($all_child) {
                $total_child = $distributor->Query(['isdistributor' => 2, 'uid' => ['in', implode(',', $all_child)]], 'uid');
                if ($total_child) {
                    $result['all_child'] = count($total_child);
                }
            }

            $user = new UserModel();
            $user_info = $user->getInfo(['uid' => $uid], 'user_headimg,user_name,nick_name');
            $result['user_headimg'] = $user_info['user_headimg']; //获取分销商头像
            if ($user_info['user_name']) {
                $result['member_name'] = $user_info['user_name']; //获取分销商名称
            } else {
                $result['member_name'] = $user_info['nick_name']; //获取分销商名称
            }
            $info = $distributor->getInfo(['uid' => $uid], '*'); //获取分销商信息
            $result['real_name'] = $info['real_name']; //获取分销商真实名称
            $result['mobile'] = $info['mobile']; //获取分销商手机号
            $commission = new VslDistributorAccountModel();
            $commission_info = $commission->getInfo(['uid' => $uid], '*');
            if ($commission_info) {
                $result['commission'] = $commission_info['commission']; //累积佣金
                $result['withdrawals'] = $commission_info['withdrawals']; //提现佣金
            }
            $distributor_level_id = $info['distributor_level_id'];
            $level = new DistributorLevelModel();
            $result['level_name'] = $level->getInfo(['id' => $distributor_level_id], 'level_name')['level_name']; //等级名称
            if (1 <= $list['distribution_pattern']) {
                $idslevel1 = $distributor->Query(['isdistributor' => 2, 'referee_id' => $uid], 'uid');
                if ($idslevel1) {
                    $result['number1'] = count($idslevel1); //一级分销商总人数
                    $result['agentcount'] += $result['number1'];
                }
                //获取1级客户数
                $id1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => ['neq', 2]], 'uid');
                if ($id1) {
                    $result['user_count'] = count($id1); //一级分销商总人数
                }
            }
            if (2 <= $list['distribution_pattern']) {
                if ($result['number1'] > 0) {
                    $idslevel2 = $distributor->Query(['isdistributor' => 2, 'referee_id' => ['in', implode(',', $idslevel1)]], 'uid');
                    if ($idslevel2) {
                        $result['number2'] = count($idslevel2); //二级分销商总人数
                        $result['agentcount'] += $result['number2'];
                    }
                }
            }
            if (3 <= $list['distribution_pattern']) {
                if ($result['number2'] > 0) {
                    $idslevel3 = $distributor->Query(['isdistributor' => 2, 'referee_id' => ['in', implode(',', $idslevel2)]], 'uid');
                    if ($idslevel3) {
                        $result['number3'] = count($idslevel3); //三级分销商总人数
                        $result['agentcount'] += $result['number3'];
                    }
                }
            }
        }
        return $result;
    }
    /**
     * 获取分销商列表
     */
    public function getDistributorList($uid, $page_index = 1, $page_size = 0, $where = '', $order = '', $commission_levels = 4)
    {
        $distributor = new VslMemberModel();
        $user = new UserModel();
        $website_id = $where['nm.website_id'];
        $distributor_view = new VslMemberViewModel();
        $list = $this->getDistributionSite($website_id);
        if ($uid &&  $list['distribution_pattern'] >= 1 && $commission_levels >= 1) {
            $id1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => 2, 'website_id' => $website_id], 'uid');
            if ($id1) {
                $where['nm.uid'] = ['in', implode(',', $id1)];
                if ($id1 && $list['distribution_pattern'] >= 2 && $commission_levels >= 2) {
                    $id2 = $distributor->Query(['referee_id' => ['in', implode(',', $id1)], 'isdistributor' => 2, 'website_id' => $website_id], 'uid');
                    if ($id2) {
                        $id3 = implode(',', $id1) . ',' . implode(',', $id2);
                        $where['nm.uid'] = ['in', $id3];
                    }
                    if ($id3 && $list['distribution_pattern'] >= 3  && $commission_levels >= 3) {
                        $id4 = $distributor->Query(['referee_id' => ['in', implode(',', $id2)], 'isdistributor' => 2, 'website_id' => $website_id], 'uid');
                        if ($id4) {
                            $id5 = $id3 . ',' . implode(',', $id4);
                            $where['nm.uid'] = ['in', $id5];
                        }
                    }
                }
            } else {
                $where['nm.uid'] = ['in', ''];
            }
        }
        $result = $distributor_view->getDistributorViewList($page_index, $page_size, $where, $order);
        $condition['website_id'] = $website_id;
        $condition['isdistributor'] = ['in', '1,2,-1'];
        $result['count'] = $distributor_view->getCount($condition);
        $condition['isdistributor'] = 2;
        $result['count1'] = $distributor_view->getCount($condition);
        $condition['isdistributor'] = 1;
        $result['count2'] = $distributor_view->getCount($condition);
        $condition['isdistributor'] = -1;
        $result['count3'] = $distributor_view->getCount($condition);
        if ($result['data']) {
            foreach ($result['data'] as $k => $v) {
                //是否拥有设为股东 设为区代 设为队长 设为渠道商 设为店长权限 查询会员是否已经是已有权限

                $result['data'][$k]['global_status'] = getAddons('globalbonus', $website_id);
                $result['data'][$k]['area_status'] = getAddons('areabonus', $website_id);
                $result['data'][$k]['team_status'] = getAddons('teambonus', $website_id);
                $result['data'][$k]['microshop_status'] = getAddons('microshop', $website_id);
                $result['data'][$k]['channel_status'] = getAddons('channel', $website_id);
                if ($v['is_global_agent'] == 2) {
                    $result['data'][$k]['global_status'] = 0;
                }
                if ($v['is_area_agent'] == 2) {
                    $result['data'][$k]['area_status'] = 0;
                }
                if ($v['is_team_agent'] == 2) {
                    $result['data'][$k]['team_status'] = 0;
                }
                if ($v['isshopkeeper'] == 2) {
                    $result['data'][$k]['microshop_status'] = 0;
                }
                if ($result['data'][$k]['channel_status']) {
                    //查询当前会员是否是渠道商
                    $channel = new Channel();

                    $condition_channel['c.website_id'] = $v['website_id'];
                    $condition_channel['c.uid'] = $v['uid'];

                    $channel_info = $channel->getMyChannelInfo($condition_channel);

                    if ($channel_info) {
                        $result['data'][$k]['channel_status'] = 0;
                    }
                }

                $agentcount = 0;
                $ids1 = 0;
                $ids2 = 0;
                $result['data'][$k]['commission'] = 0;
                $user_info = $user->getInfo(['uid' => $v['referee_id']], 'user_name,nick_name,user_headimg');
                $result['data'][$k]['withdrawals'] = 0;
                if ($user_info['user_name']) {
                    $result['data'][$k]['referee_name'] = $user_info['user_name']; //推荐人
                } else {
                    $result['data'][$k]['referee_name'] = $user_info['nick_name']; //推荐人
                }
                if (empty($result['data'][$k]['user_name'])) {
                    $result['data'][$k]['user_name'] =  $result['data'][$k]['nick_name'];
                }
                $result['data'][$k]['referee_headimg'] = $user_info['user_headimg']; //推荐人
                if (1 <= $list['distribution_pattern']) {
                    $ids1 = $distributor->Query(['referee_id' => $v['uid']], 'uid');
                    if ($ids1) {
                        $number1 = count($ids1); //一级人数
                        $agentcount += $number1;
                    }
                }
                if (2 <= $list['distribution_pattern']) {
                    if ($ids1) {
                        $ids2 = $distributor->Query(['referee_id' => ['in', implode(',', $ids1)]], 'uid');
                        if ($ids2) {
                            $number2 = count($ids2); //二级人数
                            $agentcount += $number2;
                        }
                    }
                }
                if (3 <= $list['distribution_pattern']) {
                    if ($ids2) {
                        $ids3 = $distributor->Query(['referee_id' => ['in', implode(',', $ids2)]], 'uid');
                        if ($ids3) {
                            $number3 = count($ids3); //三级人数
                            $agentcount += $number3;
                        }
                    }
                }
                $result['data'][$k]['lower_id'] = $distributor->Query(['referee_id' => $v['uid']], 'uid'); //当前用户是否有下级
                $result['data'][$k]['distributor_number'] = $agentcount; //下级总人数
                $commission = new VslDistributorAccountModel();
                $commission_info = $commission->getInfo(['uid' => $v['uid']], '*');
                if ($commission_info) {
                    $result['data'][$k]['commission'] = $commission_info['commission']; //可用佣金
                    $result['data'][$k]['withdrawals'] = $commission_info['withdrawals']; //提现佣金
                }
                if ($this->website_id == 4794 || $this->website_id == 1086 || $this->website_id == 18) {
                    $result['data'][$k]['mobile'] = '演示系统手机已加密';
                }
            }
        }
        if ($uid) {
            $result['commission'] = 0;
            $result['withdrawals'] = 0;
            $result['number1'] = 0;
            $result['user_count'] = 0;
            $result['number2'] = 0;
            $result['number3'] = 0;
            $result['agentcount'] = 0;
            $result['all_child'] = 0;
            //获取所有下级
            $all_child = $this->getAllChild($uid, $website_id);
            if ($all_child) {
                $total_child = $distributor->Query(['isdistributor' => 2, 'uid' => ['in', implode(',', $all_child)]], 'uid');
                if ($total_child) {
                    $result['all_child'] = count($total_child);
                }
            }

            $user = new UserModel();
            $user_info = $user->getInfo(['uid' => $uid], 'user_headimg,user_name,nick_name');
            $result['user_headimg'] = $user_info['user_headimg']; //获取分销商头像
            if ($user_info['user_name']) {
                $result['member_name'] = $user_info['user_name']; //获取分销商名称
            } else {
                $result['member_name'] = $user_info['nick_name']; //获取分销商名称
            }
            $info = $distributor->getInfo(['uid' => $uid], '*'); //获取分销商信息
            $result['real_name'] = $info['real_name']; //获取分销商真实名称
            $result['mobile'] = $info['mobile']; //获取分销商手机号
            $commission = new VslDistributorAccountModel();
            $commission_info = $commission->getInfo(['uid' => $uid], '*');
            if ($commission_info) {
                $result['commission'] = $commission_info['commission']; //累积佣金
                $result['withdrawals'] = $commission_info['withdrawals']; //提现佣金
            }
            $distributor_level_id = $info['distributor_level_id'];
            $level = new DistributorLevelModel();
            $result['level_name'] = $level->getInfo(['id' => $distributor_level_id], 'level_name')['level_name']; //等级名称
            if (1 <= $list['distribution_pattern']) {
                $idslevel1 = $distributor->Query(['isdistributor' => 2, 'referee_id' => $uid], 'uid');
                if ($idslevel1) {
                    $result['number1'] = count($idslevel1); //一级分销商总人数
                    $result['agentcount'] += $result['number1'];
                }
                //获取1级客户数
                $id1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => ['neq', 2]], 'uid');
                if ($id1) {
                    $result['user_count'] = count($id1); //一级分销商总人数
                }
            }
            if (2 <= $list['distribution_pattern']) {
                if ($result['number1'] > 0) {
                    $idslevel2 = $distributor->Query(['isdistributor' => 2, 'referee_id' => ['in', implode(',', $idslevel1)]], 'uid');
                    if ($idslevel2) {
                        $result['number2'] = count($idslevel2); //二级分销商总人数
                        $result['agentcount'] += $result['number2'];
                    }
                }
            }
            if (3 <= $list['distribution_pattern']) {
                if ($result['number2'] > 0) {
                    $idslevel3 = $distributor->Query(['isdistributor' => 2, 'referee_id' => ['in', implode(',', $idslevel2)]], 'uid');
                    if ($idslevel3) {
                        $result['number3'] = count($idslevel3); //三级分销商总人数
                        $result['agentcount'] += $result['number3'];
                    }
                }
            }
        }
        return $result;
    }

    /**
     * 获取我的客户列表
     */
    public function getCustomerList($uid, $page_index = 1, $page_size = 0, $where = '', $order = '')
    {
        $distributor = new VslMemberModel();
        $where['nm.website_id'] = $this->website_id;
        $distributor_view = new VslMemberViewModel();
        if ($uid) {
            $id1 = $distributor->Query(['referee_id' => $uid, 'website_id' => $this->website_id, 'isdistributor' => ['neq', 2]], 'uid');
            $where['nm.uid'] = ['in', implode(',', $id1)];
        }
        $result = $distributor_view->getCustomerViewList($page_index, $page_size, $where, $order);
        foreach ($result['data'] as $k => $v) {
            $v1 = objToArr($v);
            $order = new VslOrderModel();
            $result['data'][$k]['order_count'] =  $order->getCount(['website_id' => $this->website_id, 'order_status' => 4, 'buyer_id' => $v1['uid']]);
        }
        return $result;
    }
    /**
     * 获取分销商等级列表
     */
    public function getDistributorLevelList($page_index = 1, $page_size = 0, $where = '', $order = '')
    {
        $distributor_level = new DistributorLevelModel();
        $list = $distributor_level->pageQuery($page_index, $page_size, $where, $order, '*');
        foreach ($list['data'] as $k => $v) {
            if ($list['data'][$k]['goods_id']) {
                $list['data'][$k]['goods_name'] = $this->goods_ser->getGoodsDetailById($list['data'][$k]['goods_id'], 'goods_name')['goods_name'];
            }
            if ($list['data'][$k]['upgrade_level']) {
                $list['data'][$k]['upgrade_level_name'] = $distributor_level->getInfo(['id' => $list['data'][$k]['upgrade_level']], 'level_name')['level_name'];
            }
        }
        return $list;
    }
    /**
     * 获取当前分销商等级
     */
    public function getDistributorLevel()
    {
        $distributor_level = new DistributorLevelModel();
        $list = $distributor_level->pageQuery(1, 0, ['website_id' => $this->website_id], '', 'id,level_name');
        return $list['data'];
    }
    /**
     * 获取当前分销商是否是代理商
     */
    public function getAgentInfo()
    {
        $agent_level = new VslAgentLevelModel();
        $list = [];
        $list1 = $agent_level->pageQuery(1, 0, ['website_id' => $this->website_id, 'from_type' => 1], '', 'id,level_name');
        $list2 = $agent_level->pageQuery(1, 0, ['website_id' => $this->website_id, 'from_type' => 2], '', 'id,level_name');
        $list3 = $agent_level->pageQuery(1, 0, ['website_id' => $this->website_id, 'from_type' => 3], '', 'id,level_name');
        $list['global'] = $list1['data'];
        $list['area'] = $list2['data'];
        $list['team'] = $list3['data'];
        return $list;
    }
    /**
     * 添加分销商等级
     */
    public function addDistributorLevel($data)
    {
        $distributor_level = new DistributorLevelModel();
        $where['website_id'] = $this->website_id;
        $where['level_name'] = $data['level_name'];
        $count = $distributor_level->where($where)->count();
        if ($count > 0) {
            return -2;
        }
        $data['website_id'] = $this->website_id;
        $res = $distributor_level->save($data);
        return $res;
    }

    /**
     * 修改分销商等级
     */
    public function updateDistributorLevel($id, $data)
    {
        try {
            $distributor_level = new DistributorLevelModel();
            $distributor_level->startTrans();
            $data['modify_time'] = time();
            $retval = $distributor_level->save($data, [
                'id' => $id,
                'website_id' => $this->website_id
            ]);
            $distributor_level->commit();
            return $retval;
        } catch (\Exception $e) {
            $distributor_level->rollback();
            $retval = $e->getMessage();
            return 0;
        }
    }
    /*
     * 删除分销商等级
     */
    public function deleteDistributorLevel($id)
    {
        $level = new DistributorLevelModel();
        $level->startTrans();
        try {
            // 删除等级信息
            $retval = $level->destroy($id);
            $level->commit();
            return $retval;
        } catch (\Exception $e) {
            $level->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 获得分销商等级详情
     */
    public function getDistributorLevelInfo($id)
    {
        $level_type = new DistributorLevelModel();
        $level_info = $level_type->getInfo(['id' => $id]);
        $goods_list = array();
        if ($level_info['goods_id']) {
            $goods_ids = explode(",", $level_info['goods_id']);
            foreach ($goods_ids as $key => $value) {
                if ($value <= 0) {
                    continue;
                }
                $goods_info = $this->goods_ser->getGoodsDetailById($value, 'price,stock,picture', 1);
                $goods_info['goods_price'] = $goods_info['price'];
                $goods_info['goods_stock'] = $goods_info['stock'];
                $goods_info['goods_id'] = $value;
                $goods_info['pic'] = $goods_info['album_picture']['pic_cover_mid'];
                array_push($goods_list, $goods_info);
            }
        }

        $level_info['goods_info'] = $goods_list;
        return $level_info;
    }
    /**
     * 获得分销商等级比重
     */
    public function getLevelWeight()
    {
        $level_type = new DistributorLevelModel();
        $level_weight = $level_type->Query(['website_id' => $this->website_id], 'weight');
        return $level_weight;
    }
    /**
     * 更新分销商资料
     */
    public function updateDistributorInfo($data, $uid)
    {
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid]);
        if ($data['distributor_level_id']) {
            $level_type = new DistributorLevelModel();
            $level_weight = $level_type->getInfo(['id' => $member_info['distributor_level_id']], 'weight')['weight'];
            $level_weights = $level_type->getInfo(['id' => $data['distributor_level_id']], 'weight')['weight'];
            if ($level_weights > $level_weight) {
                $data['up_level_time'] = time();
            }
        }
        $areaStatus = getAddons('areabonus', $this->website_id);
        $globalStatus = getAddons('globalbonus', $this->website_id);
        $teamStatus = getAddons('teambonus', $this->website_id);
        if ($areaStatus || $globalStatus || $teamStatus) {
            $agent_level = new VslAgentLevelModel();
            if ($data['global_agent_level_id'] && $data['is_global_agent'] && $globalStatus) {
                $level_global_weight = $agent_level->getInfo(['id' => $member_info['global_agent_level_id']], 'weight')['weight'];
                $level_global_weights = $agent_level->getInfo(['id' => $data['global_agent_level_id']], 'weight')['weight'];
                if ($level_global_weight) {
                    if ($level_global_weights > $level_global_weight) {
                        $data['up_global_level_time'] = time();
                    }
                }
                if ($member_info['is_global_agent'] != 2) {
                    $data['apply_global_agent_time'] = time();
                    $data['become_global_agent_time'] = time();
                    $account = new VslBonusAccountModel();
                    $account_info = $account->getInfo(['website_id' => $this->website_id, 'from_type' => 1, 'uid' => $uid]);
                    if (empty($account_info)) {
                        $account->save(['website_id' => $this->website_id, 'from_type' => 1, 'uid' => $uid]);
                    }
                }
            }
            if ($data['area_agent_level_id'] && $data['is_area_agent'] && $areaStatus) {
                $level_area_weight = $agent_level->getInfo(['id' => $member_info['area_agent_level_id']], 'weight')['weight'];
                $level_area_weights = $agent_level->getInfo(['id' => $data['area_agent_level_id']], 'weight')['weight'];
                if ($data['agent_area_id'] && $member_info['area_type']) {
                    if ($member_info['is_area_agent'] != 2) {
                        $data['apply_area_agent_time'] = time();
                        $data['become_area_agent_time'] = time();
                        $account = new VslBonusAccountModel();
                        $account_info = $account->getInfo(['website_id' => $this->website_id, 'from_type' => 2, 'uid' => $uid]);
                        if (empty($account_info)) {
                            $account->save(['website_id' => $this->website_id, 'from_type' => 2, 'uid' => $uid]);
                        }
                    }
                    $member_infos['area_type'] = explode(',', $member_info['area_type']);
                    if ($member_infos['area_type'][0] == 3) {
                        $index = strpos($member_info['agent_area_id'], "d");
                        $data['agent_area_id'] = substr_replace($member_info['agent_area_id'], $data['agent_area_id'], 0, $index + 1);
                    }
                    if ($member_infos['area_type'][0] == 2) {
                        $index = strpos($member_info['agent_area_id'], "c");
                        $data['agent_area_id'] = substr_replace($member_info['agent_area_id'], $data['agent_area_id'], 0, $index + 1);
                    }
                    if ($member_infos['area_type'][0] == 1) {
                        $index = strpos($member_info['agent_area_id'], "a");
                        $data['agent_area_id'] = substr_replace($member_info['agent_area_id'], $data['agent_area_id'], 0, $index + 1);
                    }
                }
                if ($data['area_type'] && $member_info['area_type']) {
                    $member_info['area_type'] = explode(',', $member_info['area_type']);
                    $member_info['area_type'][0] = $data['area_type'];
                    $data['area_type'] = implode(',', $member_info['area_type']);
                }
                if (!$member_info['area_leg']) {
                    $data['area_leg'] = 0;
                }
                if ($level_area_weight) {
                    if ($level_area_weights > $level_area_weight) {
                        $data['up_area_level_time'] = time();
                    }
                }
            }
            if ($data['team_agent_level_id'] && $data['is_team_agent'] && $teamStatus) {
                $level_team_weight = $agent_level->getInfo(['id' => $member_info['team_agent_level_id']], 'weight')['weight'];
                $level_team_weights = $agent_level->getInfo(['id' => $data['team_agent_level_id']], 'weight')['weight'];
                if ($level_team_weight) {
                    if ($level_team_weights > $level_team_weight) {
                        $data['up_team_level_time'] = time();
                    }
                }
                if ($member_info['is_team_agent'] != 2) {
                    $data['apply_team_agent_time'] = time();
                    $data['become_team_agent_time'] = time();
                    $account = new VslBonusAccountModel();
                    $account_info = $account->getInfo(['website_id' => $this->website_id, 'from_type' => 3, 'uid' => $uid]);
                    if (empty($account_info)) {
                        $account->save(['website_id' => $this->website_id, 'from_type' => 3, 'uid' => $uid]);
                    }
                }
            }
        }
        $retval = $member->save($data, [
            'uid' => $uid,
            'website_id' => $this->website_id
        ]);
        if ($data['distributor_level_id']) {
            if ($member_info['referee_id']) {
                if ($member_info['distributor_level_id'] != $data['distributor_level_id']) {
                    $this->updateDistributorLevelInfo($member_info['referee_id']);
                    if (getAddons('globalbonus', $this->website_id)) {
                        $global = new GlobalBonus();
                        $global->updateAgentLevelInfo($member_info['referee_id']);
                    }
                    if (getAddons('areabonus', $this->website_id)) {
                        $area = new AreaBonus();
                        $area->updateAgentLevelInfo($member_info['referee_id']);
                    }
                    if (getAddons('teambonus', $this->website_id)) {
                        $team = new TeamBonus();
                        $team->updateAgentLevelInfo($member_info['referee_id']);
                    }
                }
            }
        }
        return $retval;
    }
    /**
     * 获得近七天的分销订单佣金
     */
    public function getPayMoneySum($condition)
    {
        $order = new VslOrderModel();
        $orderids = $order->Query($condition, 'order_id');
        $order_commission = new VslOrderDistributorCommissionModel();
        if (!$orderids) {
            return 0;
        }
        $count = $order_commission->getSum(['order_id' => ['in', $orderids]], 'commission');
        return $count;
    }
    /**
     * 获得近七天的分销订单金额
     */
    public function getOrderMoneySum($condition)
    {
        $order = new VslOrderModel();
        $condition['is_distribution'] = 1;
        $count = $order->getSum($condition, 'order_money');
        return $count;
    }
    /**
     * 获得分销统计
     */
    public function getDistributorCount($website_id)
    {
        $start_date = strtotime(date("Y-m-d"), time());
        $end_date = strtotime(date('Y-m-d', strtotime('+1 day')));
        $member = new VslMemberModel();
        $data['distributor_total'] = $member->getCount(['website_id' => $website_id, 'isdistributor' => 2]);
        $data['distributor_today'] = $member->getCount(['website_id' => $website_id, 'isdistributor' => 2, 'become_distributor_time' => [[">", $start_date], ["<", $end_date]]]);
        $commission = new VslDistributorAccountModel();
        $commission_total = $commission->Query(['website_id' => $website_id], 'commission');
        $data['commission_total'] = array_sum($commission_total);
        $withdrawals_total = $commission->Query(['website_id' => $website_id], 'withdrawals');
        $data['withdrawals_total'] = array_sum($withdrawals_total);
        return $data;
    }

    /*
      * 获取分销订单
      */
    public function getOrderList($page_index = 1, $page_size = 0, $condition = [], $order = '')
    {
        $uid = $condition['buyer_id'];
        unset($condition['buyer_id']);
        $order_model = new VslOrderModel();
        $goods_sku = new VslGoodsSkuModel();
        $order_item = new VslOrderGoodsModel();
        //如果有订单表以外的字段，则先按条件查询其他表的orderid，并取出数据的交集，组装到原有查询条件里
        // 查询主表
        $order_commission = new VslOrderDistributorCommissionModel();
        if ($uid) {
            $ids = '';
            $ids1 = $order_commission->Query(['website_id' => $condition['website_id'], 'commissionA_id' => $uid], 'distinct order_id'); //一级佣金订单
            if ($ids1 && !empty($ids)) {
                $ids = $ids . ',' . implode(',', $ids1);
            } elseif ($ids1) {
                $ids = implode(',', $ids1);
            }
            $ids2 = $order_commission->Query(['website_id' => $condition['website_id'], 'commissionB_id' => $uid], 'distinct order_id'); //二级佣金订单
            if ($ids2 && !empty($ids)) {
                $ids = $ids . ',' . implode(',', $ids2);
            } elseif ($ids2) {
                $ids = implode(',', $ids2);
            }
            $ids3 = $order_commission->Query(['website_id' => $condition['website_id'], 'commissionC_id' => $uid], 'distinct order_id'); //三级佣金订单
            if ($ids3 && !empty($ids)) {
                $ids = $ids . ',' . implode(',', $ids3);
            } elseif ($ids3) {
                $ids = implode(',', $ids3);
            }
            $condition['order_id'] = ['in', $ids];
        }
        if ($condition['order_id']) {
            // 重组订单条件
            $new_condition = array();
            if ($condition) {
                foreach ($condition as $keys => $values) {
                    if ($keys == 'user_type') {
                        if ($values == 2) {
                            $new_condition['su.isdistributor'] = 2;
                        } else {
                            $new_condition['su.isdistributor'] = ['<', 2];
                        }
                    } else {
                        $new_condition['nm.' . $keys] = $values;
                    }
                }
            }
            $order_list = $order_model->getViewList2($page_index, $page_size, $new_condition, $order);
            $user = new UserModel();
            if (!empty($order_list['data'])) {
                foreach ($order_list['data'] as $k => $v) {
                    $user_info = $user->getInfo(['uid' => $order_list['data'][$k]['buyer_id']], 'nick_name,user_name,user_tel,user_headimg');
                    if ($user_info['user_name']) {
                        $order_list['data'][$k]['buyer_name'] = $user_info['user_name'];
                    } elseif ($user_info['nick_name']) {
                        $order_list['data'][$k]['buyer_name'] = $user_info['nick_name'];
                    } elseif ($user_info['user_tel']) {
                        $order_list['data'][$k]['buyer_name'] = $user_info['user_tel'];
                    }
                    $order_list['data'][$k]['buyer_headimg'] = $user_info['user_headimg'];
                    $order_list['data'][$k]['buyer_nick_name'] = $user_info['nick_name'];
                    $order_list['data'][$k]['buyer_user_tel'] = $user_info['user_tel'];
                    $order_list['data'][$k]['buyer_user_name'] = $user_info['user_name'];
                    //查询订单佣金和积分
                    $order_list['data'][$k]['commission'] = 0;
                    $orders = $order_commission->Query(['order_id' => $v['order_id']], '*');
                    foreach ($orders as $key1 => $value) {
                        if ($value['commissionA_id'] ==  $uid) {
                            $order_list['data'][$k]['commission'] += $value['commissionA'];
                        }
                        if ($value['commissionB_id'] ==  $uid) {
                            $order_list['data'][$k]['commission'] += $value['commissionB'];
                        }
                        if ($value['commissionC_id'] ==  $uid) {
                            $order_list['data'][$k]['commission'] += $value['commissionC'];
                        }
                    }
                    // 查询订单项表

                    $order_item_list = $order_item->where([
                        'order_id' => $v['order_id']
                    ])->select();
                    // 根据订单类型判断订单相关操作
                    if ($this->groupshopping) {
                        $group_server = $this->groupshopping ? new GroupShopping() : '';
                        $isGroupSuccess = $group_server->groupRecordDetail($v['group_record_id'])['status'];
                    }
                    if ($order_list['data'][$k]['payment_type'] == 6 || $order_list['data'][$k]['shipping_type'] == 2) {
                        $order_status = OrderStatus::getSinceOrderStatus($order_list['data'][$k]['order_type'], $isGroupSuccess);
                    } else {
                        $order_status = OrderStatus::getOrderCommonStatus($order_list['data'][$k]['order_type'], $isGroupSuccess, $order_list['data'][$k]['card_store_id'], $order_item_list ? $order_item_list[0]['goods_type'] : 0);
                    }
                    // 查询订单操作
                    foreach ($order_status as $k_status => $v_status) {
                        if ($v_status['status_id'] == $v['order_status']) {
                            //代付定金
                            if ($v['presell_id'] != 0 && $v['pay_status'] == 0 && $v['money_type'] == 0 && $v['order_status'] != 5) {
                                $v_status['status_name'] = "待付定金";
                                unset($v_status['operation'][1]); //调整价格 去掉
                            }
                            //待付尾款
                            if ($v['presell_id'] != 0 && $v['pay_status'] == 0 && $v['money_type'] == 1 && $v['order_status'] != 5) {
                                $v_status['status_name'] = "待付尾款";
                                unset($v_status['operation'][1]); //调整价格 去掉
                            }

                            //已付定金，去掉定金退款按钮
                            if ($v['presell_id'] != 0 && $v['pay_status'] == 0 && $v['money_type'] == 1) {
                                $v_status['refund_member_operation'] = '';
                            }
                            //积分订单没有支付、退款
                            if ($v['order_type'] == 10) {
                                $v_status['refund_member_operation'] = '';
                            }
                            $order_list['data'][$k]['status_name'] = $v_status['status_name'];
                        }
                    }

                    foreach ($order_item_list as $key_item => $v_item) {
                        //订单商品的佣金
                        $commission_goods_info = $order_commission->getInfo(['website_id' => $condition['website_id'], 'order_goods_id' => $v_item['order_goods_id'], 'order_id' => $v['order_id']], '*'); //自购订单

                        if ($commission_goods_info['commissionA_id'] == $uid) {
                            $order_item_list[$key_item]['commission'] = $commission_goods_info['commissionA'];
                            $order_item_list[$key_item]['point'] = $commission_goods_info['pointA'];
                        }
                        if ($commission_goods_info['commissionB_id'] == $uid) {
                            $order_item_list[$key_item]['commission'] = $commission_goods_info['commissionB'];
                            $order_item_list[$key_item]['point'] = $commission_goods_info['pointB'];
                        }
                        if ($commission_goods_info['commissionC_id'] == $uid) {
                            $order_item_list[$key_item]['commission'] = $commission_goods_info['commissionC'];
                            $order_item_list[$key_item]['point'] = $commission_goods_info['pointC'];
                        }
                        // 查询商品sku表开始

                        $goods_sku_info = $goods_sku->getInfo([
                            'sku_id' => $v_item['sku_id']
                        ], 'code');
                        $order_item_list[$key_item]['code'] = $goods_sku_info['code'];
                        $goods_info = $this->goods_ser->getGoodsDetailById($v_item['goods_id'], 'cost_price,code,item_no,picture', 1);
                        $order_item_list[$key_item]['cost_price'] = $goods_info['cost_price'];
                        $order_item_list[$key_item]['goods_code'] = $goods_info['code'];
                        $order_item_list[$key_item]['item_no'] = $goods_info['item_no'];
                        $order_item_list[$key_item]['spec'] = [];
                        if ($v_item['sku_attr']) {
                            $order_item_list[$key_item]['spec'] = json_decode(html_entity_decode($v_item['sku_attr']), true);
                        }
                        // 查询商品sku结束
                        if (empty($goods_info['album_picture'])) {
                            $goods_picture = array(
                                'pic_cover' => '',
                                'pic_cover_mid' => '',
                                'pic_cover_micro' => '',
                            );
                        } else {
                            $goods_picture['pic_cover'] = getApiSrc($goods_info['album_picture']['pic_cover']);
                            $goods_picture['pic_cover_mid'] = getApiSrc($goods_info['album_picture']['pic_cover_mid']);
                            $goods_picture['pic_cover_micro'] = getApiSrc($goods_info['album_picture']['pic_cover_micro']);
                        }

                        $order_item_list[$key_item]['picture'] = $goods_picture;
                        $order_item_list[$key_item]['refund_type'] = $v_item['refund_type'];
                        $order_item_list[$key_item]['refund_operation'] = [];
                        $order_item_list[$key_item]['new_refund_operation'] = [];
                        $order_item_list[$key_item]['member_operation'] = [];
                        $order_item_list[$key_item]['status_name'] = '';
                    }

                    $order_item_list = array_columns($order_item_list, 'picture,goods_name,num,goods_id,commission');
                    $order_list['data'][$k]['order_item_list'] = $order_item_list;
                }
            }
            $order_list['data'] = array_columns($order_list['data'], 'order_id,buyer_nick_name,buyer_user_tel,buyer_user_name,buyer_id,order_no,create_time,commission,status_name,buyer_headimg,order_item_list');
        } else {
            $order_list['data'] = [];
        }
        return $order_list;
    }

    /**
     * 分销商详情(降级条件)
     */
    public function getDistributorInfos($uid, $time)
    {
        $distributor = new VslMemberModel();
        $order_model = new VslOrderModel();
        $result = $distributor->getInfo(['uid' => $uid], "*");
        $website_id =  $result['website_id'];
        $list = $this->getDistributionSite($website_id);
        if ($uid && $time) {
            $result['agentordercount'] = 0;
            $result['order_money'] = 0;
            $result['selforder_money'] = 0;
            $result['selforder_number'] = 0;
            $up_time = $distributor->getInfo(['uid' => $uid], 'up_level_time')['up_level_time'];
            $limit_time = $up_time + $time * 24 * 3600;
            $order_ids = $order_model->Query(['buyer_id' => $uid, 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_id');
            $order_money = $order_model->Query(['buyer_id' => $uid, 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_money');
            $result['selforder_money'] = array_sum($order_money); //自购订单金额
            $result['selforder_number'] = count($order_ids); //自购订单
            if (1 <= $list['distribution_pattern']) {
                $idslevel1 = $distributor->Query(['referee_id' => $uid], 'uid');
                if ($idslevel1) {
                    $order_ids1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel1)], 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_id');
                    $order1_money1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel1)], 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_money');
                    $result['order1'] = count($order_ids1); //一级订单总数
                    $result['order1_money'] = array_sum($order1_money1); //一级订单总金额
                    $result['agentordercount'] += $result['order1'];
                    $result['order_money'] += $result['order1_money'];
                }
            }
            if (2 <= $list['distribution_pattern']) {
                if ($result['number1'] > 0) {
                    $idslevel2 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel1)]], 'uid');
                    if ($idslevel2) {
                        $order_ids2 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel2)], 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_id');
                        $order2_money1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel2)], 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_money');
                        $result['order2'] = count($order_ids2); //二级订单总数
                        $result['order2_money'] = array_sum($order2_money1); //二级订单总金额
                        $result['agentordercount'] += $result['order2'];
                        $result['order_money'] += $result['order2_money'];
                    }
                }
            }
            if (3 <= $list['distribution_pattern']) {
                if ($result['number2'] > 0) {
                    $idslevel3 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel2)]], 'uid');
                    if ($idslevel3) {
                        $order_ids3 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel3)], 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_id');
                        $order3_money1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel3)], 'order_status' => [['>', 0], ['<', 5]], 'create_time' => [[">", $up_time], ["<", $limit_time]], 'is_distribution' => 1], 'order_money');
                        $result['order3'] = count($order_ids3); //三级订单总数
                        $result['order3_money'] = array_sum($order3_money1); //三级订单总金额
                        $result['agentordercount'] += $result['order3'];
                        $result['order_money'] += $result['order3_money'];
                    }
                }
            }
            if ($list['purchase_type'] == 1) {
                $result['agentordercount'] += count($order_ids);
                $result['order_money'] += array_sum($order_money);
            }
        }
        return $result;
    }
    /**
     * 分销商详情(升级条件)
     */
    public function getDistributorLowerInfo($uid)
    {
        $distributor = new VslMemberModel();
        $order_model = new VslOrderModel();
        $result = $distributor->getInfo(['uid' => $uid], "*");
        $list = $this->getDistributionSite($result['website_id']);
        $order_commission = new VslOrderDistributorCommissionModel();
        $commission_order_id = implode(',', $order_commission->Query(['website_id' => $result['website_id']], 'distinct order_id'));
        $result['agentordercount'] = 0;
        $result['agentcount'] = 0;
        $result['agentcount1'] = 0;
        $result['agentcount2'] = 0;
        $result['order_money'] = 0;
        $result['number1'] = 0;
        $result['number_1'] = 0;
        $result['number2'] = 0;
        $result['number_2'] = 0;
        $result['number3'] = 0;
        $result['number_3'] = 0;

        if ($result['down_up_level_time']) { //发生过降级 条件限制条件变更为大于降级时间 'become_distributor_time'=>[">=",$result['down_up_level_time']]
            $order_ids = $order_model->Query(['order_status' => 4, 'buyer_id' => $uid, 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_id');
            $order_money = $order_model->Query(['order_status' => 4, 'buyer_id' => $uid, 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_money');
            $result['selforder_money'] = array_sum($order_money); //自购订单金额
            $result['selforder_number'] = count($order_ids); //自购订单数

            if (1 <= $list['distribution_pattern']) {
                $idslevels1 = $distributor->Query(['referee_id' => $uid, 'reg_time' => [">", $result['down_up_level_time']]], 'uid'); //是一级下级
                $idslevel_1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => ['neq', 2], 'reg_time' => [">", $result['down_up_level_time']]], 'uid'); //不是一级分销商的下级
                $idslevel1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => 2, 'reg_time' => [">", $result['down_up_level_time']]], 'uid'); //是一级分销商的下级

                //edit by 2019/12/03
                $oldidslevels1 = $distributor->Query(['referee_id' => $uid], 'uid'); //是一级下级
                $oldidslevel1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => 2], 'uid'); //是一级分销商的下级

                if ($oldidslevels1) {
                    $order_ids1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $oldidslevels1)], 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_id');
                    $order1_money1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $oldidslevels1)], 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_money');
                    $result['number1'] = count($idslevel1); //一级分销商人数
                    $result['number_1'] = count($idslevel_1); //一级非分销商人数
                    $result['order1'] = count($order_ids1); //一级分销订单数
                    $result['order1_money'] = array_sum($order1_money1); //一级分销商订单总金额
                    $result['agentcount'] += $result['number1'] + $result['number_1']; //下线总人数
                    $result['agentcount1'] += $result['number_1']; //下线客户数（非分销商）
                    $result['agentcount2'] += $result['number1']; //团队人数（分销商）
                    $result['agentordercount'] += $result['order']; //分销订单数
                    $result['order_money'] +=  $result['order1_money']; //分销订单金额
                }
            }
            if (2 <= $list['distribution_pattern']) {
                if ($result['number1'] > 0) {
                    $idslevels2 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel1)], 'reg_time' => [">", $result['down_up_level_time']]], 'uid');
                    $idslevel2 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel1)], 'isdistributor' => 2, 'reg_time' => [">", $result['down_up_level_time']]], 'uid');
                    $idslevel_2 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel1)], 'isdistributor' => ['neq', 2], 'reg_time' => [">", $result['down_up_level_time']]], 'uid');
                    //edit by 2019/12/03
                    $oldidslevels2 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel1)]], 'uid');
                    $oldidslevel2 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel1)], 'isdistributor' => 2], 'uid');
                    if ($oldidslevels2) {
                        $order_ids2 = $order_model->Query(['buyer_id' => ['in', implode(',', $oldidslevels2)], 'order_status' => 4, 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_id');
                        $order2_money1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $oldidslevels2)], 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_money');
                        $result['number2'] = count($idslevel2); //二级分销商人数
                        $result['number_2'] = count($idslevel_2); //二级非分销商人数
                        $result['order2'] = count($order_ids2); //二级分销商订单总数
                        $result['order2_money'] = array_sum($order2_money1); //二级分销商订单总金额
                        $result['agentcount'] += $result['number2'] + $result['number_2']; //下线总人数
                        $result['agentcount1'] += $result['number_2']; //下线客户数（非分销商）
                        $result['agentcount2'] += $result['number2']; //团队人数（分销商）
                        $result['agentordercount'] += $result['order2']; //分销订单数
                        $result['order_money'] +=  $result['order2_money']; //分销订单金额
                    }
                }
            }
            if (3 <= $list['distribution_pattern']) {
                if ($result['number2'] > 0) {
                    $idslevels3 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel2)], 'reg_time' => [">", $result['down_up_level_time']]], 'uid');
                    $idslevel3 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel2)], 'isdistributor' => 2, 'reg_time' => [">", $result['down_up_level_time']]], 'uid');
                    $idslevel_3 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel2)], 'isdistributor' => ['neq', 2], 'reg_time' => [">", $result['down_up_level_time']]], 'uid');
                    //edit by 2019/12/03
                    $oldidslevels3 = $distributor->Query(['referee_id' => ['in', implode(',', $oldidslevel2)]], 'uid');
                    if ($oldidslevels3) {
                        $order_ids3 = $order_model->Query(['buyer_id' => ['in', implode(',', $oldidslevels3)], 'order_status' => 4, 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_id');
                        $order3_money1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $oldidslevels3)], 'is_distribution' => 1, 'finish_time' => [">", $result['down_up_level_time']]], 'order_money');
                        $result['number3'] = count($idslevel3); //三级分销商人数
                        $result['number_3'] = count($idslevel_3); //三级非分销商人数
                        $result['order3'] = count($order_ids3); //三级分销商订单总数
                        $result['order3_money'] = array_sum($order3_money1); //三级分销商订单总金额
                        $result['agentcount'] += $result['number3'] + $result['number_3']; //下线总人数
                        $result['agentcount1'] += $result['number_3']; //下线客户数（非分销商）
                        $result['agentcount2'] += $result['number3']; //团队人数（分销商）
                        $result['agentordercount'] += $result['order3']; //分销订单数
                        $result['order_money'] +=  $result['order3_money']; //分销订单金额
                    }
                }
            }
        } else { //未发生过降级
            $order_ids = $order_model->Query(['order_status' => 4, 'buyer_id' => $uid, 'is_distribution' => 1], 'order_id');
            $order_money = $order_model->Query(['order_status' => 4, 'buyer_id' => $uid, 'is_distribution' => 1], 'order_money');
            $result['selforder_money'] = array_sum($order_money); //自购订单金额
            $result['selforder_number'] = count($order_ids); //自购订单数
            if (1 <= $list['distribution_pattern']) {
                $idslevels1 = $distributor->Query(['referee_id' => $uid], 'uid'); //是一级下级
                $idslevel_1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => ['neq', 2]], 'uid'); //不是一级分销商的下级
                $idslevel1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => 2], 'uid'); //是一级分销商的下级
                if ($idslevels1) {
                    $order_ids1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $idslevels1)], 'is_distribution' => 1], 'order_id');
                    $order1_money1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $idslevels1)], 'is_distribution' => 1], 'order_money');
                    $result['number1'] = count($idslevel1); //一级分销商人数
                    $result['number_1'] = count($idslevel_1); //一级非分销商人数
                    $result['order1'] = count($order_ids1); //一级分销订单数
                    $result['order1_money'] = array_sum($order1_money1); //一级分销商订单总金额
                    $result['agentcount'] += $result['number1'] + $result['number_1']; //下线总人数
                    $result['agentcount1'] += $result['number_1']; //下线客户数（非分销商）
                    $result['agentcount2'] += $result['number1']; //团队人数（分销商）
                    $result['agentordercount'] += $result['order']; //分销订单数
                    $result['order_money'] +=  $result['order1_money']; //分销订单金额
                }
            }
            if (2 <= $list['distribution_pattern']) {
                if ($result['number1'] > 0) {
                    $idslevels2 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel1)]], 'uid');
                    $idslevel2 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel1)], 'isdistributor' => 2], 'uid');
                    $idslevel_2 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel1)], 'isdistributor' => ['neq', 2]], 'uid');
                    if ($idslevels2) {
                        $order_ids2 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevels2)], 'order_status' => 4, 'is_distribution' => 1], 'order_id');
                        $order2_money1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $idslevels2)], 'is_distribution' => 1], 'order_money');
                        $result['number2'] = count($idslevel2); //二级分销商人数
                        $result['number_2'] = count($idslevel_2); //二级非分销商人数
                        $result['order2'] = count($order_ids2); //二级分销商订单总数
                        $result['order2_money'] = array_sum($order2_money1); //二级分销商订单总金额
                        $result['agentcount'] += $result['number2'] + $result['number_2']; //下线总人数
                        $result['agentcount1'] += $result['number_2']; //下线客户数（非分销商）
                        $result['agentcount2'] += $result['number2']; //团队人数（分销商）
                        $result['agentordercount'] += $result['order2']; //分销订单数
                        $result['order_money'] +=  $result['order2_money']; //分销订单金额
                    }
                }
            }
            if (3 <= $list['distribution_pattern']) {
                if ($result['number2'] > 0) {
                    $idslevels3 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel2)]], 'uid');
                    $idslevel3 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel2)], 'isdistributor' => 2], 'uid');
                    $idslevel_3 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel2)], 'isdistributor' => ['neq', 2]], 'uid');
                    if ($idslevels3) {
                        $order_ids3 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevels3)], 'order_status' => 4, 'is_distribution' => 1], 'order_id');
                        $order3_money1 = $order_model->Query(['order_status' => 4, 'buyer_id' => ['in', implode(',', $idslevels3)], 'is_distribution' => 1], 'order_money');
                        $result['number3'] = count($idslevel3); //三级分销商人数
                        $result['number_3'] = count($idslevel_3); //三级非分销商人数
                        $result['order3'] = count($order_ids3); //三级分销商订单总数
                        $result['order3_money'] = array_sum($order3_money1); //三级分销商订单总金额
                        $result['agentcount'] += $result['number3'] + $result['number_3']; //下线总人数
                        $result['agentcount1'] += $result['number_3']; //下线客户数（非分销商）
                        $result['agentcount2'] += $result['number3']; //团队人数（分销商）
                        $result['agentordercount'] += $result['order3']; //分销订单数
                        $result['order_money'] +=  $result['order3_money']; //分销订单金额
                    }
                }
            }
        }

        return $result;
    }

    /**
     * post 请求
     *
     * @param $url
     * @param array $data
     * @return mixed
     */
    public function getRequest($url){
        $header = [
            'Content-Type: application/json'
        ];

        $ch = curl_init ();
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_POST, 0 );
        curl_setopt ( $ch, CURLOPT_HEADER, 0 );
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
//        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $data );
        curl_setopt ($ch, CURLOPT_HTTPHEADER, $header);
//        var_dump($ch);die;
        $res = curl_exec ( $ch );
        curl_close ( $ch );
        return json_decode($res, true, 512, JSON_BIGINT_AS_STRING);
    }

    public function checkContract($mobile, $type = 6){
        //验证是否已签约
        $url = "https://esign.meicbf.com/ht/app/contract/getContractStatus?mobile=".$mobile.'&type='.$type;
        $res = $this->getRequest($url);

        if(!isset($res['code']) || $res['code'] != 0 || $res['data'] == false){
            return false;
        }
        return true;
    }

    /**
     * 分销商详情(订单已完成)
     */
    public function getDistributorInfo($uid)
    {
        $distributor = new VslMemberModel();
        $user = new UserModel();
        if ($this->website_id) {
            $website_id = $this->website_id;
        } else {
            $website_id =  $distributor->getInfo(['uid' => $uid], 'website_id')['website_id'];
        }
        $order_model = new VslOrderModel();
        $list = $this->getDistributionSite($website_id);
        $result = $distributor->getInfo(['uid' => $uid], "*");
        $result['distribution_pattern'] = $list['distribution_pattern'];
        $commission = new VslDistributorAccountModel();
        $commission_info = $commission->getInfo(['uid' => $uid], '*');
        $result['commission'] = $commission_info['commission'];

        $check_mx = false;
        if($this->checkContract($result['mobile'], 6)){
            $check_mx = true;
        }

        $check_cc = false;
        if($this->checkContract($result['mobile'], 7)){
            $check_cc = true;
        }
        $result['check_mx'] = $check_mx;
        $result['check_cc'] = $check_cc;
        //是否是对接人
        if($result['is_pu'] == 1){
            $result['com_commission'] = $this->comCommission($uid, $website_id);
        }else{
            $result['com_commission'] = 0;
        }
        $result['withdrawals'] = $commission_info['withdrawals'];
        $result['freezing_commission'] = $commission_info['freezing_commission'];
        $result['total_commission'] = sprintf("%.2f", $commission_info['freezing_commission'] + $commission_info['commission'] + $commission_info['withdrawals']);
        if ($uid) {
            $result['agentordercount'] = 0;
            $result['order1'] = 0;
            $result['order2'] = 0;
            $result['order3'] = 0;
            $result['order1_money'] = 0;
            $result['order2_money'] = 0;
            $result['order3_money'] = 0;
            $result['order_money'] = 0;
            $result['agentcount'] = 0;
            $result['customcount'] = $distributor->getCount(['referee_id' => $uid, 'website_id' => $this->website_id, 'isdistributor' => ['neq', 2]]);
            $result['selforder_money'] = $order_model->getSum(['buyer_id' => $uid, 'order_status' => 4, 'is_distribution' => 1], 'order_money'); //自购分销订单金额
            $result['selforder_number'] = $order_model->getCount(['buyer_id' => $uid, 'order_status' => 4, 'is_distribution' => 1], 'order_id'); //自购分销订单数

            $user_info = $user->getInfo(['uid' => $uid], 'user_headimg,user_name,nick_name,real_name,user_tel');
            $result['user_headimg'] = $user_info['user_headimg']; //获取分销商头像
            $info = $distributor->getInfo(['uid' => $uid], '*'); //获取分销商信息
            if ($user_info['user_name']) {
                $result['member_name'] = $user_info['user_name']; //获取会员名称
            } else {
                $result['member_name'] = $user_info['nick_name']; //获取会员名称
            }
            $result['real_name'] = $user_info['real_name'];
            $result['mobile'] = $user_info['user_tel']; //获取分销商手机号
            $distributor_level_id = $info['distributor_level_id'];
            $level = new DistributorLevelModel();
            $result['level_name'] = $level->getInfo(['id' => $distributor_level_id], 'level_name')['level_name']; //等级名称
            if (1 <= $list['distribution_pattern']) {
                $idslevel1 = $distributor->Query(['referee_id' => $uid], 'uid');
                $idslevel11 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => 2], 'uid');
                if ($idslevel1) {
                    $order_ids1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel1)], 'order_status' => 4, 'is_distribution' => 1], 'order_id');
                    $order1_money1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel1)], 'order_status' => 4, 'is_distribution' => 1], 'order_money');
                    $result['order1'] = count($order_ids1); //一级分销订单总数
                    $result['number1'] = count($idslevel1); //一级总人数
                    $result['number11'] = count($idslevel11); //一级总人数(分销商)
                    $result['order1_money'] = array_sum($order1_money1); //一级分销订单总金额
                    $result['agentcount'] += $result['number11'];
                    $result['agentordercount'] += $result['order1'];
                    $result['order_money'] += $result['order1_money'];
                }
            }
            if (2 <= $list['distribution_pattern']) {
                if ($result['number1'] > 0) {
                    $idslevel2 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel1)]], 'uid');
                    $idslevel22 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel1)], 'isdistributor' => 2], 'uid');
                    if ($idslevel2) {
                        $order_ids2 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel2)], 'order_status' => 4, 'is_distribution' => 1], 'order_id');
                        $order2_money1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel2)], 'order_status' => 4, 'is_distribution' => 1], 'order_money');
                        $result['order2'] = count($order_ids2); //二级分销商订单总数
                        $result['number2'] = count($idslevel2); //二级总人数
                        $result['number22'] = count($idslevel22); //二级总人数(分销商)
                        $result['order2_money'] = array_sum($order2_money1); //二级分销商订单总金额
                        $result['agentcount'] += $result['number22'];
                        $result['agentordercount'] += $result['order2'];
                        $result['order_money'] += $result['order2_money'];
                    }
                }
            }
            if (3 <= $list['distribution_pattern']) {
                if ($result['number2'] > 0) {
                    $idslevel3 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel2)]], 'uid');
                    $idslevel33 = $distributor->Query(['referee_id' => ['in', implode(',', $idslevel2)], 'isdistributor' => 2], 'uid');
                    if ($idslevel3) {
                        $order_ids3 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel3)], 'order_status' => 4, 'is_distribution' => 1], 'order_id');
                        $order3_money1 = $order_model->Query(['buyer_id' => ['in', implode(',', $idslevel2)], 'order_status' => 4, 'is_distribution' => 1], 'order_money');
                        $result['order3'] = count($order_ids3); //三级分销商订单总数
                        $result['number3'] = count($idslevel3); //三级总人数
                        $result['number33'] = count($idslevel33); //三级总人数(分销商)
                        $result['order3_money'] = array_sum($order3_money1); //三级分销商订单总金额
                        $result['agentcount'] += $result['number33']; //下级分总人数
                        $result['agentordercount'] += $result['order3']; //下级分销订单数
                        $result['order_money'] += $result['order3_money']; //下级分销金额
                    }
                }
            }
        }
        if ($list['purchase_type'] == 1) {
            $result['agentordercount'] += $result['selforder_number'];
            $result['order_money'] += $result['selforder_money'];
        }
        $result['extensionordercount'] = $result['agentordercount'];
        $result['extensionmoney'] = $result['order_money'];
        if ($result['apply_distributor_time']) {
            $result['apply_distributor_time'] = date('Y-m-d H:i:s', $result['apply_distributor_time']);
        } else {
            $result['apply_distributor_time'] = date('Y-m-d H:i:s', $result['become_distributor_time']);
        }

        $result['become_distributor_time'] = date('Y-m-d H:i:s', $result['become_distributor_time']);
        if (!empty($result['referee_id'])) {
            $user_info = $user->getInfo(['uid' => $result['referee_id']], 'user_name,nick_name');
            if ($user_info['user_name']) {
                $result['referee_name'] = $user_info['user_name']; //获取会员名称
            } else {
                $result['referee_name'] = $user_info['nick_name']; //获取会员名称
            }
        } else {
            $result['referee_name'] = '总店';
        }
        $result['is_datum'] = $this->checkDatum();
        //系统表单
        if (getAddons('customform', $this->website_id)) {
            $addConSer = new AddonsConfigSer();
            $addinfo = $addConSer->getAddonsConfig('customform', $this->website_id);
            if ($addinfo['value']) {
                $customSer = new CustomSer();
                $info = $customSer->getCustomData(1, 10, 3, '', ['nm.uid' => $uid]);
                $result['custom_list'] = $info;
            }
        }

        return $result;
    }

    /**
     * 获取对接人的可发放佣金
     *
     * @param $uid
     */
    public function comCommission($uid, $website_id)
    {
        $amount = 0;
        //获取下面的所有人
        $uids = $this->sort($uid);
//        $ids = [];
//        foreach ($uids as $i){
//            $ids[] = $i['uid'];
//        }
//        sort($ids);
//        var_dump(json_encode($ids));
        $lists = $this->sort_data($uids,'uid', 'referee_id', 'children', $uid);
        $res = $this->rec_list_files($uid, $lists);
        $ids = [];
        foreach ($res as $i){
            $ids[] = $i['uid'];
        }
//        sort($ids);
//        var_dump(json_encode($ids));die;
        if(count($ids)){
            //获取一级佣金 二级佣金 三级佣金
            $order_commission = new VslOrderDistributorCommissionModel();
            $order_model = new VslOrderModel();
            $order_lists = $order_model->getQuery(['website_id'=>1, 'order_status' => 3, 'buyer_id'=> array('in', $ids)], 'order_id');
            $order_ids = [];
            foreach ($order_lists as $item){
                $order_ids[] = $item['order_id'];
            }
            if(count($order_ids)){
                $order_amount =  $order_commission->getSum(['website_id'=>1, 'cal_status' => 0, 'order_id'=> array('in', $order_ids)], 'commission');
                //获取团队佣金
                $bonusLogModel = new VslOrderBonusLogModel();
                $bonus_amount = $bonusLogModel->getSum(['website_id'=>1, 'order_id'=> array('in', $order_ids),'team_return_status'=>0, 'team_cal_status' => 0], 'team_bonus');
                $amount = $order_amount + $bonus_amount;
            }
        }

        return $amount;
    }

    /**
     * 过滤同个等级多个对接人的情况
     *
     * @param $pid
     * @param $from
     * @return array
     *
     */
    public function rec_list_files($pid, $from)
    {
        $arr = [];
        foreach($from as $key=> $item) {
            if($item['is_pu'] == 1 && $item['uid'] != $pid) {
                continue;
            }
            if(!isset($item['children'])){
                $arr[] = $item;
            }
            if(isset($item['children'])){
                $children = $item['children'];
                unset($item['children']);
                $arr[] = $item;
                $arr = array_merge($arr, $this->rec_list_files($pid, $children));
            }
        }
        return $arr;
    }

    /**
     * 生成树结构
     *
     * @param $data
     * @param string $pk
     * @param string $pid
     * @param string $child
     * @param int $root
     * @return array|bool
     */
    public function sort_data($data, $pk = 'id', $pid = 'pid', $child = 'children', $root = 0)
    {
        // 创建Tree
        $tree = [];
        if (!is_array($data)) {
            return false;
        }

        //创建基于主键的数组引用
        $refer = [];
        foreach ($data as $key => $value_data) {
            $refer[$value_data[$pk]] = &$data[$key];
        }
        foreach ($data as $key => $value_data) {
            // 判断是否存在parent
            $parentId = $value_data[$pid];
            if ($root == $parentId) {
                $tree[] = &$data[$key];
            } else {
                if (isset($refer[$parentId])) {
                    $parent = &$refer[$parentId];
                    $parent[$child][] = &$data[$key];
                }
            }
        }

        return $tree;
    }

    /**
     *
     *
     * @param $id
     * @param $data
     * @return array
     */
    public static function sort1($id, $data)
    {
        $arr = [];
        foreach($data as $k => $v){
            //从小到大 排列
            if($v['referee_id'] == $id){
                $arr[] = $v;
                $arr = array_merge(self::sort1($v['uid'], $data), $arr);
            }
        }
        return $arr;
    }

    /**
     * 获取代理下的所有用户
     *
     * @param $top_id
     * @return array
     */
    public function sort($top_id)
    {
        $memberModel = new VslMemberModel();
        $lists = $memberModel->getQuery(['referee_id'=>array('>', 0)], 'uid,referee_id,is_pu,distributor_level_id');
        $users = [];
        foreach ($lists as $item){
            $users[] = [
                'uid'=> $item['uid'],
                'referee_id'=> $item['referee_id'],
                'is_pu'=> $item['is_pu'],
                'distributor_level_id'=> $item['distributor_level_id']
            ];
        }
        $arr = self::sort1($top_id, $users);
        return $arr;
    }

    /**
     * 申请成为分销商
     */
    public function addDistributorInfo($website_id, $uid, $post_data, $real_name)
    {

        $info = $this->getDistributionSite($website_id);
        if ($info['distributor_condition'] == 2 || $info['distributor_condition'] == 1) {
            return -1; //未开启主动申请
        }
        $level = new DistributorLevelModel();
        $level_info = $level->getInfo(['website_id' => $website_id, 'is_default' => 1], '*');
        $level_id = $level_info['id'];
        $distribution_pattern = $info['distribution_pattern'];
        $ratio = 0;
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid], '*');
        if ($distribution_pattern >= 1) {
            $ratio .= '一级返佣比例' . $level_info['commission1'] . '%';
        }
        if ($distribution_pattern >= 2) {
            $ratio .= ',二级返佣比例' . $level_info['commission2'] . '%';
        }
        if ($distribution_pattern >= 3) {
            $ratio .= ',三级返佣比例' . $level_info['commission3'] . '%';
        }
        $extend_code = $this->create_extend();
        $user_info = new UserModel();
        if (empty($real_name)) {
            $real_name = $user_info->getInfo(['uid' => $uid], 'real_name')['real_name'];
        }
        if ($info['distributor_check'] == 1 || $info['distributor_condition'] == 3) {
            $data = array(
                "isdistributor" => 2,
                "real_name" => $real_name,
                "distributor_level_id" => $level_id,
                "apply_distributor_time" => time(),
                "become_distributor_time" => time(),
                "extend_code" => $extend_code,
                "distributor_apply" => $post_data
            );
        } else {
            $data = array(
                "isdistributor" => 1,
                "real_name" => $real_name,
                "distributor_level_id" => $level_id,
                "apply_distributor_time" => time(),
                "distributor_apply" => $post_data
            );
        }
        $member = new VslMemberModel();
        $result = $member->save($data, [
            'uid' => $uid
        ]);
        if ($real_name && $result == 1) {
            $user = new UserModel();
            $user->save(['real_name' => $real_name], ['uid' => $uid]);
        }
        if ($info['distributor_check'] == 1 || $info['distributor_condition'] == 3) {
            $referee_id = $member->getInfo(['uid' => $uid], 'referee_id')['referee_id'];
            if ($referee_id) {
                $this->updateDistributorLevelInfo($referee_id);
                if (getAddons('globalbonus', $this->website_id)) {
                    $global = new GlobalBonus();
                    $global->updateAgentLevelInfo($referee_id);
                    $global->becomeAgent($referee_id);
                }
                if (getAddons('areabonus', $this->website_id)) {
                    $area = new AreaBonus();
                    $area->updateAgentLevelInfo($referee_id);
                }
                if (getAddons('teambonus', $this->website_id)) {
                    $team = new TeamBonus();
                    $team->updateAgentLevelInfo($referee_id);
                    $team->becomeAgent($referee_id);
                }
            }
            if ($info['distribution_pattern'] >= 1) {
                if ($member_info['referee_id']) {
                    $recommend1_info = $member->getInfo(['uid' => $member_info['referee_id']], '*');
                    if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                        $level_info1 = $level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                        $recommend1 = $level_info1['recommend1']; //一级推荐奖
                        $recommend_point1 = $level_info1['recommend_point1']; //一级推荐积分
                        $recommend_beautiful_point1 = $level_info1['recommend_beautiful_point1']; //一级推荐美丽分
                        $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $member_info['website_id'],$recommend_beautiful_point1);
                    }
                }
            }
            if ($info['distribution_pattern'] >= 2) {
                $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                    $level_info2 = $level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                    $recommend2 = $level_info2['recommend2']; //二级推荐奖
                    $recommend_point2 = $level_info2['recommend_point2']; //二级推荐积分
                    $recommend_beautiful_point2 = $level_info2['recommend_beautiful_point2']; //二级推荐美丽分
                    $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $member_info['website_id'],$recommend_beautiful_point2);
                }
            }
            if ($info['distribution_pattern'] >= 3) {
                $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                    $level_info3 = $level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $member_info['website_id']]);
                    $recommend3 = $level_info3['recommend3']; //三级推荐奖
                    $recommend_point3 = $level_info3['recommend_point3']; //三级推荐积分
                    $recommend_beautiful_point3 = $level_info3['recommend_beautiful_point3']; //三级推荐美丽分
                    $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $member_info['website_id'],$recommend_beautiful_point3);
                }
            }
            $account = new VslDistributorAccountModel();
            $account->save(['website_id' => $website_id, 'uid' => $uid]);
            runhook("Notify", "successfulDistributorByTemplate", ["uid" => $uid, "website_id" => $this->website_id]); //用户成为分销商提醒
            runhook("Notify", "sendCustomMessage", ["messageType" => "become_distributor", "ratio" => $ratio, "uid" => $uid, "become_time" => time(), 'level_name' => $level_info['level_name']]); //用户成为分销商提醒
        } else {
            runhook("Notify", "sendCustomMessage", ['messageType' => 'apply_distributor', "uid" => $uid, "apply_time" => time()]); //用户申请成为分销商提醒
        }
        return $result;
    }
    /**
     * 提现填写资料
     */
    public function addDistributorInfos($uid, $post_data, $real_name)
    {
        $user_info = new UserModel();
        if (empty($real_name)) {
            $real_name = $user_info->getInfo(['uid' => $uid], 'real_name')['real_name'];
        }
        $data = array(
            "complete_datum" => 1,
            "real_name" => $real_name,
            "distributor_apply" => $post_data
        );
        $member = new VslMemberModel();
        $result = $member->save($data, [
            'uid' => $uid
        ]);
        if ($real_name && $result == 1) {
            $user = new UserModel();
            $user->save(['real_name' => $real_name], ['uid' => $uid]);
        }
        return $result;
    }
    public function create_extend()
    {
        $randcode = '';
        for ($i = 0; $i < 10; $i++) {
            $randcode .= chr(mt_rand(48, 57));
        }
        return $randcode;
    }

    /**
     * 查询分销商状态
     */
    public function getDistributorStatus($uid)
    {
        $user = new VslMemberModel();
        $result = $user->getInfo(['uid' => $uid], "isdistributor");
        return $result;
    }

    /**
     * 分销设置
     */
    public function setDistributionSite($distribution_status, $distribution_pattern, $purchase_type, $distributor_condition, $distributor_conditions, $pay_money, $order_number, $distributor_check, $distributor_grade, $goods_id, $lower_condition, $distributor_datum, $distribution_admin_status, $order_status, $distribution_piece,$referee_check)
    {
        // $account = new VslDistributorAccountModel();
        // $user_account = $account->getInfo(['website_id'=>$this->website_id,'commission'=>['>',0]],'commission');
        // if($user_account>0 && $distribution_status==0){
        //     return -3;
        // }
        $ConfigService = new AddonsConfigService();
        $value = array(
            'website_id' => $this->website_id,
            'distribution_admin_status' => $distribution_admin_status,
            'distribution_pattern' => $distribution_pattern,
            'purchase_type' => $purchase_type,
            'distributor_datum' => $distributor_datum,
            'distributor_condition' => $distributor_condition,
            'distributor_conditions' => $distributor_conditions,
            'pay_money' => $pay_money,
            'order_number' => $order_number,
            'distributor_check' => $distributor_check,
            'referee_check' => $referee_check,
            'distributor_grade' => $distributor_grade,
            'goods_id' => $goods_id,
            'lower_condition' => $lower_condition,
            'order_status' => $order_status,
            'distribution_piece' => $distribution_piece
        );
        $distribution_info = $ConfigService->getAddonsConfig("distribution", $this->website_id);
        if (!empty($distribution_info)) {
            $res = $ConfigService->updateAddonsConfig($value, "分销设置", $distribution_status, "distribution");
        } else {
            $res = $ConfigService->addAddonsConfig($value, "分销设置", $distribution_status, "distribution");
        }
        return $res;
    }
    /*
     * 获取分销基本设置
     *
     */
    public function getDistributionSite($website_id)
    {
        if ($website_id) {
            $websiteid =  $website_id;
        } else {
            $websiteid =  $this->website_id;
        }

        $config = new AddonsConfigService();
        $distribution = $config->getAddonsConfig("distribution", $websiteid);

        $distribution_info = $distribution['value'];

        $goods_list = array();
        //条件为满足所有勾选条件或者满足勾选条件之一即可才需要查
        if ($distribution_info['goods_id'] && ($distribution_info['distributor_condition'] ==1 || $distribution_info['distributor_condition'] ==2)) {
            $goods_ids = explode(",", $distribution_info['goods_id']);
            try{
                foreach ($goods_ids as $key => $value) {
                $goods_info = $this->goods_ser->getGoodsDetailById($value, 'price,stock,picture', 1);
                    if($goods_info){
                        $goods_info['goods_price'] = $goods_info['price'];
                        $goods_info['goods_stock'] = $goods_info['stock'];
                        $goods_info['goods_id'] = $value;
                        $goods_info['pic'] = $goods_info['album_picture']['pic_cover_mid'];
                        array_push($goods_list, $goods_info);
                    }

                }
                unset($value);
            } catch (\Exception $e) {
                debugFile($e->getMessage(), '分销重新佣金结算createLuckySpell-0-7-1-2-3-1-1-0-1-0-2', 1111112);
            }
        }
        $distribution_info['goods_list'] = $goods_list;
        $distribution_info['is_use'] = $distribution['is_use'];

        return $distribution_info;
    }
    public function checkDatum()
    {
        $is_datum = 0;
        $config = new AddonsConfigService();
        $distributor = new VslMemberModel();
        $distribution_info = $config->getAddonsConfig("distribution", $this->website_id, 0, 1);
        $result = $distributor->getInfo(['uid' => $this->uid], "*");
        if ($distribution_info['distributor_condition'] == 1 || $distribution_info['distributor_condition'] == 2 || $distribution_info['distributor_condition'] == 3) {
            if ($distribution_info['distributor_datum'] == 1 && $result['complete_datum'] == 1) {
                $is_datum = 1;
            } elseif ($distribution_info['distributor_datum'] == 1 && $result['complete_datum'] != 1) {
                $is_datum = 2;
            }
        }
        return $is_datum;
    }
    /**
     * 分销结算设置
     */
    public function setSettlementSite($withdrawals_type, $make_money, $commission_calculation, $commission_arrival, $withdrawals_check, $withdrawals_min, $withdrawals_cash, $withdrawals_begin, $withdrawals_end, $poundage, $settlement_type)
    {
        $ConfigService = new ConfigService();
        $value = array(
            'website_id' => $this->website_id,
            'settlement_type' => $settlement_type,
            'withdrawals_type' => $withdrawals_type,
            'commission_calculation' => $commission_calculation,
            'commission_arrival' => $commission_arrival,
            'withdrawals_check' => $withdrawals_check,
            'make_money' => $make_money,
            'withdrawals_min' => $withdrawals_min,
            'withdrawals_cash' => $withdrawals_cash,
            'withdrawals_begin' => $withdrawals_begin,
            'withdrawals_end' => $withdrawals_end,
            'poundage' => $poundage,
        );
        $param = [
            'value' => $value,
            'website_id' => $this->website_id,
            'instance_id' => 0,
            'key' => "SETTLEMENT",
            'desc' => "分销结算设置",
            'is_use' => 1,
        ];
        $res = $ConfigService->setConfigOne($param);
        // TODO Auto-generated method stub
        return $res;
    }
    /*
      * 获取分销结算设置
      *
      */
    public function getSettlementSite($website_id)
    {
        if ($website_id) {
            $website_ids = $website_id;
        } else {
            $website_ids = $this->website_id;
        }
        $config = new ConfigService();
        $distributionInfo = $config->getConfig(0,"SETTLEMENT",$website_ids, 1);
        return $distributionInfo;
    }
    /**
     * 分销申请协议设置
     */
    public function setAgreementSite($type, $logo, $content, $distribution_label, $distribution_name, $distributor_name, $distribution_commission, $commission, $commission_details, $withdrawable_commission, $withdrawals_commission, $withdrawal, $frozen_commission, $distribution_order, $my_team, $team1, $team2, $team3, $my_customer, $extension_code, $distribution_tips, $become_distributor, $total_commission)
    {
        $ConfigService = new ConfigService();
        $agreement_infos = $ConfigService ->getConfig(0,"AGREEMENT",$this->website_id, 1);
        if($agreement_infos && $type==1){//文案
            $value = array(
                'website_id' => $this->website_id,
                'logo' => $logo,
                'content' =>  $agreement_infos['content'],
                'distribution_label' => $distribution_label,
                'distribution_name' => $distribution_name,
                'distributor_name' => $distributor_name,
                'distribution_commission' => $distribution_commission,
                'total_commission' => $total_commission,
                'commission' => $commission,
                'commission_details' => $commission_details,
                'withdrawable_commission' => $withdrawable_commission,
                'withdrawals_commission' => $withdrawals_commission,
                'withdrawal' => $withdrawal,
                'frozen_commission' => $frozen_commission,
                'distribution_order' => $distribution_order,
                'my_team' => $my_team,
                'team1' => $team1,
                'team2' => $team2,
                'team3' => $team3,
                'my_customer' => $my_customer,
                'extension_code' => $extension_code,
                'distribution_tips' => $distribution_tips,
                'become_distributor' => $become_distributor,
            );
        } elseif ($agreement_infos && $type == 2) {
            $value = array(
                'website_id' => $this->website_id,
                'logo' => $agreement_infos['logo'],
                'content' => $content,
                'distribution_label' => $agreement_infos['distribution_label'],
                'distribution_name' => $agreement_infos['distribution_name'],
                'distributor_name' => $agreement_infos['distributor_name'],
                'distribution_commission' => $agreement_infos['distribution_commission'],
                'commission' => $agreement_infos['commission'],
                'total_commission' => $agreement_infos['total_commission'],
                'commission_details' => $agreement_infos['commission_details'],
                'withdrawable_commission' => $agreement_infos['withdrawable_commission'],
                'withdrawals_commission' => $agreement_infos['withdrawals_commission'],
                'withdrawal' => $agreement_infos['withdrawal'],
                'frozen_commission' => $agreement_infos['frozen_commission'],
                'distribution_order' => $agreement_infos['distribution_order'],
                'my_team' => $agreement_infos['my_team'],
                'team1' => $agreement_infos['team1'],
                'team2' => $agreement_infos['team2'],
                'team3' => $agreement_infos['team3'],
                'my_customer' => $agreement_infos['my_customer'],
                'extension_code' => $agreement_infos['extension_code'],
                'distribution_tips' => $agreement_infos['distribution_tips'],
                'become_distributor' => $agreement_infos['become_distributor'],
            );
        } else {
            $value = array(
                'website_id' => $this->website_id,
                'logo' => $logo,
                'content' =>  $content,
                'distribution_label' => $distribution_label,
                'distribution_name' => $distribution_name,
                'distributor_name' => $distributor_name,
                'distribution_commission' => $distribution_commission,
                'commission' => $commission,
                'total_commission' => $total_commission,
                'commission_details' => $commission_details,
                'withdrawable_commission' => $withdrawable_commission,
                'withdrawals_commission' => $withdrawals_commission,
                'withdrawal' => $withdrawal,
                'frozen_commission' => $frozen_commission,
                'distribution_order' => $distribution_order,
                'my_team' => $my_team,
                'team1' => $team1,
                'team2' => $team2,
                'team3' => $team3,
                'my_customer' => $my_customer,
                'extension_code' => $extension_code,
                'distribution_tips' => $distribution_tips,
                'become_distributor' => $become_distributor,
            );
        }
        $param = [
            'value' => $value,
            'website_id' => $this->website_id,
            'instance_id' => 0,
            'key' => "AGREEMENT",
            'desc' => "申请协议",
            'is_use' => 1,
        ];
        $res = $ConfigService->setConfigOne($param);
        return $res;
    }
    /*
      * 获取分销申请协议
      */
    public function getAgreementSite($website_id)
    {
        if ($website_id) {
            $website_ids = $website_id;
        } else {
            $website_ids = $this->website_id;
        }
        $ConfigService = new ConfigService();
        $distribution_info = $ConfigService ->getConfig(0,"AGREEMENT",$website_ids, 1);
        if($distribution_info){
            if(!isset($distribution_info['become_distributor'])){
                $distribution_info['become_distributor'] =  '成为分销商';
                $distribution_info['distribution_tips'] =  '分销小提示：可以通过二维码、邀请链接或者邀请码发展下线客户，下线客户购买商品后你可以获得相应的佣金奖励';
                $distribution_info['extension_code'] =  '推广码';
                $distribution_info['team3'] =  '三级';
                $distribution_info['team2'] =  '二级';
                $distribution_info['team1'] =  '一级';
                $distribution_info['my_team'] =  '我的团队';
                $distribution_info['my_customer'] =  '我的客户';
                $distribution_info['distribution_order'] =  '分销订单';
                $distribution_info['frozen_commission'] =  '冻结佣金';
                $distribution_info['withdrawals_commission'] =  '已提现佣金';
                $distribution_info['withdrawal'] =  '提现中';
                $distribution_info['withdrawable_commission'] =  '可提现佣金';
                $distribution_info['commission_details'] =  '佣金明细';
                $distribution_info['commission'] =  '佣金';
                $distribution_info['total_commission'] =  '累积佣金';
                $distribution_info['distribution_commission'] =  '分销佣金';
                $distribution_info['distributor_name'] =  '分销商';
                $distribution_info['distribution_name'] =  '分销中心';
            }
        } else {
            $distribution_info['become_distributor'] =  '成为分销商';
            $distribution_info['distribution_tips'] =  '分销小提示：可以通过二维码、邀请链接或者邀请码发展下线客户，下线客户购买商品后你可以获得相应的佣金奖励';
            $distribution_info['extension_code'] =  '推广码';
            $distribution_info['team3'] =  '三级';
            $distribution_info['team2'] =  '二级';
            $distribution_info['team1'] =  '一级';
            $distribution_info['my_team'] =  '我的团队';
            $distribution_info['my_customer'] =  '我的客户';
            $distribution_info['distribution_order'] =  '分销订单';
            $distribution_info['frozen_commission'] =  '冻结佣金';
            $distribution_info['withdrawals_commission'] =  '已提现佣金';
            $distribution_info['withdrawal'] =  '提现中';
            $distribution_info['withdrawable_commission'] =  '可提现佣金';
            $distribution_info['commission_details'] =  '佣金明细';
            $distribution_info['commission'] =  '佣金';
            $distribution_info['total_commission'] =  '累积佣金';
            $distribution_info['distribution_commission'] =  '分销佣金';
            $distribution_info['distributor_name'] =  '分销商';
            $distribution_info['distribution_name'] =  '分销中心';
        }
        return $distribution_info;
    }
    /*
      * 获取自定义表单
      */
    public function getCustomForm($website_id)
    {
        if ($website_id) {
            $website_ids = $website_id;
        } else {
            $website_ids = $this->website_id;
        }
        $add_config = new AddonsConfigService();
        $distribution_info = $add_config->getAddonsConfig("customform", $website_ids, 0, 1);
        $custom_form = [];
        if ($distribution_info['distributor'] == 1) {
            $custom_form_id =  $distribution_info['distributor_id'];
            $coupon_model = new CustomServer();
            $custom_form_info = $coupon_model->getCustomFormDetail($custom_form_id)['value'];
            if ($custom_form_info) {
                $custom_form = json_decode($custom_form_info, true);
            }
        }
        return $custom_form;
    }

    /*
     * 删除分销商
     */
    public function deleteDistributor($uid)
    {
        $member = new VslMemberModel();
        $member->startTrans();
        try {
            // 删除分销商信息
            $data = [
                'isdistributor' => 0
            ];
            $member->save($data, ['uid' => $uid]);
            $member->commit();
            return 1;
        } catch (\Exception $e) {
            $member->rollback();
            return $e->getMessage();
        }
    }

    /*
     * 订单商品佣金计算
     * +佣金计算优先级： 商品独立规则 > 活动独立规则 > 分销应用等级规则
     */
    public function orderDistributorCommission($params)
    {

        $ConfigService = new ConfigService();
        $order_goods = new VslOrderGoodsModel();
        $order = new VslOrderModel();
        $order_info = $order->getInfo(['order_id' => $params['order_id']], 'bargain_id,group_id,presell_id,shop_id,shop_order_money,luckyspell_id');

        $order_goods_info = $order_goods->getInfo(['order_goods_id' => $params['order_goods_id'], 'order_id' => $params['order_id']]);
        $goods_info = $this->goods_ser->getGoodsDetailById($params['goods_id']);
        if (empty($order_info) || empty($order_goods_info)) {
            return;
        }

        $shop_order_money = $order_info['shop_order_money'];
        $addonsConfigService = new AddonsConfigService();
        $info1 = $addonsConfigService ->getAddonsConfig("distribution",$goods_info['website_id']);//基本设置
        $set_info = $ConfigService ->getConfig(0,"SETTLEMENT",$goods_info['website_id'], 1);
        $base_info = $info1['value'];

        $goods_num = $base_info['distribution_piece'] == 1 ? $base_info['distribution_piece'] * $order_goods_info['num'] : 1; //是否开启固定返佣将按照商品下单数量计算
        $commission1 = 0;
        $commission2 = 0;
        $commission3 = 0;
        $commission11 = 0;
        $commission22 = 0;
        $commission33 = 0;
        $point1 = 0;
        $point2 = 0;
        $point3 = 0;
        $point11 = 0;
        $point22 = 0;
        $point33 = 0;
        $beautiful_point1 = 0;
        $beautiful_point2 = 0;
        $beautiful_point3 = 0;
        $beautiful_point11 = 0;
        $beautiful_point22 = 0;
        $beautiful_point33 = 0;
        $commissionB2 = 0;
        $pointB2 = 0;
        $beautiful_pointB2 = 0;
        $commissionB22 = 0;
        $pointB22 = 0;
        $beautiful_pointB22 = 0;
        $commissionA11 = 0;
        $pointA11 = 0;
        $beautiful_pointA11 = 0;
        $commissionA1 = 0;
        $pointA1 = 0;
        $beautiful_pointA1 = 0;
        $commissionC33 = 0;
        $pointC33 = 0;
        $beautiful_pointC33 = 0;
        $commissionC3 = 0;
        $pointC3 = 0;
        $beautiful_pointC3 = 0;
        $level_rule_ids = [];
        $distribution_bonus_val = '';
        $buyagain_bonus_val = '';
        //先查看是否启用商品独立规则
        //如果该商品是店铺独立商品 ，由于默认是不开启 ，之前已开启参与产品如果没有设置独立分销则默认为0
        //获取是否开启店铺佣金
        $distribution_admin_status = $base_info['distribution_admin_status'];
        if ($distribution_admin_status == 0 && $goods_info['shop_id']) {
            $goods_info['distribution_rule'] = 0;
            $goods_info['is_distribution'] = 1;
            $goods_info['buyagain'] = 0;
            $goods_info['distribution_bonus_choose'] = 0;
        }

        if (intval($goods_info['is_distribution']) == 1) {
            if ($goods_info['distribution_rule'] == 1) {
                $distribution_bonus_val = json_decode(htmlspecialchars_decode($goods_info['distribution_rule_val']), true); //商品独立分销规则
            }
            if ($goods_info['buyagain'] == 1) {
                $buyagain_bonus_val = json_decode(htmlspecialchars_decode($goods_info['buyagain_distribution_val']), true); //商品独立复购规则
            }
            if ($goods_info['distribution_bonus_choose'] == 1) {
                $set_info['commission_calculation'] = $goods_info['distribution_bonus_calculation']; //商品独立结算节点
            }
        } else {
            return; //商品不参与分销
        }

        //往上查找开启的分销层级人员 有符合的就继续执行
        if (intval($base_info['distribution_pattern']) < 1) {
            return; //没有开启分销
        } else {
            $commission_uid_list = $this->getPatternCommissionUid(intval($base_info['distribution_pattern']), $params['buyer_id'], intval($base_info['purchase_type']));
        }

        if ($commission_uid_list && $commission_uid_list['has_commission'] == 0) {
            return; //上三级不存在分销商
        }
        //查询订单商品是否为活动商品 distribution_bonus_val商品分销规则 buyagain_bonus_val商品复购规则
        $order_bargain_id = $order_info['bargain_id']; //砍价id
        $luckyspell_id = $order_info['luckyspell_id']; //砍价id
        $order_groupshopping_id = $order_info['group_id']; //拼团id
        $order_presell_id = $order_goods_info['presell_id']; //预售
        $order_seckill_id = $order_goods_info['seckill_id']; //秒杀
        if ($order_bargain_id && ($distribution_bonus_val == '' || $buyagain_bonus_val == '')) {
            $active_rule = $this->getActiveRule('bargain', $goods_info['website_id']);
        } elseif ($order_groupshopping_id && ($distribution_bonus_val == '' || $buyagain_bonus_val == '')) {
            $active_rule = $this->getActiveRule('groupshopping', $goods_info['website_id']);
        } elseif ($order_presell_id && ($distribution_bonus_val == '' || $buyagain_bonus_val == '')) {
            $active_rule = $this->getActiveRule('presell', $goods_info['website_id']);
        } elseif ($order_seckill_id && ($distribution_bonus_val == '' || $buyagain_bonus_val == '')) {
            $active_rule = $this->getActiveRule('seckill', $goods_info['website_id']);
        } elseif ($luckyspell_id && ($distribution_bonus_val == '' || $buyagain_bonus_val == '')) {
            $active_rule = $this->getActiveRule('luckyspell', $goods_info['website_id']);
        }

        //存在活动独立规则 如果商品没有启用独立商品规则 则可以启用
        $bonus_val = '';
        if($active_rule['bonus_val']){
            $bonus_val = json_decode(htmlspecialchars_decode($active_rule['bonus_val']), true);
        }
        if ($active_rule && $bonus_val && $bonus_val['is_distribution'] == 1) {
            if ($distribution_bonus_val == '' && $bonus_val['distribution_rule'] == 1) {
                $distribution_bonus_val = json_decode(htmlspecialchars_decode($bonus_val['distribution_val']), true);
            }
            if ($buyagain_bonus_val == '' && $bonus_val['buyagain'] == 1) {
                $buyagain_bonus_val = json_decode(htmlspecialchars_decode($bonus_val['buyagain_distribution_val']), true);
            }
            if ($goods_info['distribution_bonus_choose'] != 1 && $bonus_val['distribution_bonus_choose'] == 1) {
                $set_info['commission_calculation'] = $bonus_val['distribution_bonus_calculation'];
            }
        }

        //获取当前商品 是否重复购买
        // 查询是否已购买过该商品
        $countOrderGoods = new VslOrderGoodsViewModel();
        $goodscondition['website_id'] = $order_goods_info['website_id'];
        $goodscondition['buyer_id'] = $order_goods_info['buyer_id'];
        $goodscondition['goods_id'] = $order_goods_info['goods_id'];
        $resCount = $countOrderGoods->getAllGoodsOrders($goodscondition);
        $countGoods = 0;
        if ($resCount > 1) {
            $countGoods = 1;
        }
        //开始处理分销规则跟复购规则
        if ($buyagain_bonus_val && $countGoods == 1) {
            $distribution_bonus_val = $buyagain_bonus_val;
            $level_rule = $distribution_bonus_val['buyagain_level_rule']; //独立等级设置
            $recommend_type = $distribution_bonus_val['buyagain_recommend_type']; //返佣类型 1比例佣金 其他固定佣金
            if ($level_rule && $level_rule == 1) {
                $level_rule_ids = $distribution_bonus_val['level_ids']; //独立等级ids 1,2,3
                if ($recommend_type == 1) {
                    $level_first_rebate = $distribution_bonus_val['buyagain_first_rebate'];
                    $level_second_rebate = $distribution_bonus_val['buyagain_second_rebate'];
                    $level_third_rebate = $distribution_bonus_val['buyagain_third_rebate'];
                    $level_first_point = $distribution_bonus_val['buyagain_first_point'];
                    $level_second_point = $distribution_bonus_val['buyagain_second_point'];
                    $level_third_point = $distribution_bonus_val['buyagain_third_point'];
                    $level_first_beautiful_point = $distribution_bonus_val['buyagain_first_beautiful_point'];
                    $level_second_beautiful_point = $distribution_bonus_val['buyagain_second_beautiful_point'];
                    $level_third_beautiful_point = $distribution_bonus_val['buyagain_third_beautiful_point'];
                } else {
                    $level_first_rebate1 = $distribution_bonus_val['buyagain_first_rebate1'];
                    $level_second_rebate1 = $distribution_bonus_val['buyagain_second_rebate1'];
                    $level_third_rebate1 = $distribution_bonus_val['buyagain_third_rebate1'];
                    $level_first_point1 = $distribution_bonus_val['buyagain_first_point1'];
                    $level_second_point1 = $distribution_bonus_val['buyagain_second_point1'];
                    $level_third_point1 = $distribution_bonus_val['buyagain_third_point1'];
                    $level_first_beautiful_point1 = $distribution_bonus_val['buyagain_first_beautiful_point1'];
                    $level_second_beautiful_point1 = $distribution_bonus_val['buyagain_second_beautiful_point1'];
                    $level_third_beautiful_point1 = $distribution_bonus_val['buyagain_third_beautiful_point1'];
                }
            } else {
                if ($recommend_type == 1) {
                    $commission1 = $distribution_bonus_val['buyagain_first_rebate'];
                    $commission2 = $distribution_bonus_val['buyagain_second_rebate'];
                    $commission3 = $distribution_bonus_val['buyagain_third_rebate'];
                    $point1 = $distribution_bonus_val['buyagain_first_point'];
                    $point2 = $distribution_bonus_val['buyagain_second_point'];
                    $point3 = $distribution_bonus_val['buyagain_third_point'];
                    $beautiful_point1 = $distribution_bonus_val['buyagain_first_beautiful_point'];
                    $beautiful_point2 = $distribution_bonus_val['buyagain_second_beautiful_point'];
                    $beautiful_point3 = $distribution_bonus_val['buyagain_third_beautiful_point'];
                } else {
                    $commission11 = $distribution_bonus_val['buyagain_first_rebate1'];
                    $commission22 = $distribution_bonus_val['buyagain_second_rebate1'];
                    $commission33 = $distribution_bonus_val['buyagain_third_rebate1'];
                    $point11 = $distribution_bonus_val['buyagain_first_point1'];
                    $point22 = $distribution_bonus_val['buyagain_second_point1'];
                    $point33 = $distribution_bonus_val['buyagain_third_point1'];
                    $beautiful_point11 = $distribution_bonus_val['buyagain_first_beautiful_point1'];
                    $beautiful_point22 = $distribution_bonus_val['buyagain_second_beautiful_point1'];
                    $beautiful_point33 = $distribution_bonus_val['buyagain_third_beautiful_point1'];
                }
            }
        } elseif ($distribution_bonus_val) { //独立规则

            $level_rule = $distribution_bonus_val['level_rule']; //独立等级设置
            $recommend_type = $distribution_bonus_val['recommend_type']; //返佣类型 1比例佣金 其他固定佣金
            if ($level_rule && $level_rule == 1) {
                $level_rule_ids = $distribution_bonus_val['level_ids']; //独立等级ids 1,2,3
                if ($recommend_type == 1) {
                    $level_first_rebate = $distribution_bonus_val['first_rebate'];
                    $level_second_rebate = $distribution_bonus_val['second_rebate'];
                    $level_third_rebate = $distribution_bonus_val['third_rebate'];
                    $level_first_point = $distribution_bonus_val['first_point'];
                    $level_second_point = $distribution_bonus_val['second_point'];
                    $level_third_point = $distribution_bonus_val['third_point'];
                    $level_first_beautiful_point = $distribution_bonus_val['first_beautiful_point'];
                    $level_second_beautiful_point = $distribution_bonus_val['second_beautiful_point'];
                    $level_third_beautiful_point = $distribution_bonus_val['third_beautiful_point'];
                } else {
                    $level_first_rebate1 = $distribution_bonus_val['first_rebate1'];
                    $level_second_rebate1 = $distribution_bonus_val['second_rebate1'];
                    $level_third_rebate1 = $distribution_bonus_val['third_rebate1'];
                    $level_first_point1 = $distribution_bonus_val['first_point1'];
                    $level_second_point1 = $distribution_bonus_val['second_point1'];
                    $level_third_point1 = $distribution_bonus_val['third_point1'];
                    $level_first_beautiful_point1 = $distribution_bonus_val['first_beautiful_point1'];
                    $level_second_beautiful_point1 = $distribution_bonus_val['second_beautiful_point1'];
                    $level_third_beautiful_point1 = $distribution_bonus_val['third_beautiful_point1'];
                }
            } else {
                if ($recommend_type == 1) {
                    $commission1 = $distribution_bonus_val['first_rebate'];
                    $commission2 = $distribution_bonus_val['second_rebate'];
                    $commission3 = $distribution_bonus_val['third_rebate'];
                    $point1 = $distribution_bonus_val['first_point'];
                    $point2 = $distribution_bonus_val['second_point'];
                    $point3 = $distribution_bonus_val['third_point'];
                    $beautiful_point1 = $distribution_bonus_val['first_beautiful_point'];
                    $beautiful_point2 = $distribution_bonus_val['second_beautiful_point'];
                    $beautiful_point3 = $distribution_bonus_val['third_beautiful_point'];
                } else {
                    $commission11 = $distribution_bonus_val['first_rebate1'];
                    $commission22 = $distribution_bonus_val['second_rebate1'];
                    $commission33 = $distribution_bonus_val['third_rebate1'];
                    $point11 = $distribution_bonus_val['first_point1'];
                    $point22 = $distribution_bonus_val['second_point1'];
                    $point33 = $distribution_bonus_val['third_point1'];
                    $beautiful_point11 = $distribution_bonus_val['first_beautiful_point1'];
                    $beautiful_point22 = $distribution_bonus_val['second_beautiful_point1'];
                    $beautiful_point33 = $distribution_bonus_val['third_beautiful_point1'];
                }
            }
        }
        $cost_price = $order_goods_info['cost_price']; //商品成本价
        $price = $order_goods_info['real_money'] / $order_goods_info['num']; //商品实际支付金额
        $promotion_price = $order_goods_info['price']; //商品销售价
        $original_price = $order_goods_info['market_price']; //商品原价
        $profit_price = $price - $cost_price; //商品利润价
        if ($profit_price < 0) {
            $profit_price = 0;
        }
        $member = new VslMemberModel();
        $distributor = $member->getInfo(['uid' => $params['buyer_id']]);
        $distributor_level_id = $distributor['distributor_level_id'];
        $level = new DistributorLevelModel();
        $commissionA_id = 0;
        $commissionA = 0; //一级佣金和对应的id
        $pointA = 0; //一级积分和对应的id
        $commissionB_id = 0;
        $commissionB = 0; //二级佣金和对应的id
        $pointB = 0; //二级积分和对应的id
        $commissionC_id = 0;
        $commissionC = 0; //三级佣金和对应的id
        $pointC = 0; //三级积分和对应的id
        $commission_calculation = $set_info['commission_calculation']; //计算节点（商品价格）
        //固定不需要x数量，比例需要x数量 变更 后台可选固定*数量

        $real_price = 0;
        if ($commission_calculation == 1) { //实际付款金额
            $real_price = $price;
        } elseif ($commission_calculation == 2) { //商品原价
            $real_price = $original_price;
        } elseif ($commission_calculation == 3) { //商品销售价
            $real_price = $promotion_price;
        } elseif ($commission_calculation == 4) { //商品成本价
            $real_price = $cost_price;
        } elseif ($commission_calculation == 5) { //商品利润价
            $real_price = $profit_price;
        }

        //开始计算各分销商应得佣金
        $level_infoA_id = 0;
        $level_infoB_id = 0;
        $level_infoC_id = 0;

        if ($commission_uid_list && $commission_uid_list['distributorA_info']) { //1级分销商
            if ($commission_uid_list['distributorA_info'] && $commission_uid_list['distributorA_info']['isdistributor'] == 2) {
                $level_infoA = $level->getInfo(['id' => $commission_uid_list['distributorA_info']['distributor_level_id']]);
                $level_infoA_id = $commission_uid_list['distributorA_info']['distributor_level_id'];
                $commissionA_id = $commission_uid_list['distributorA_info']['uid']; //获得二级佣金的用户id
            }
        }
        if ($commission_uid_list && $commission_uid_list['distributorB_info']) { //2级分销商
            if ($commission_uid_list['distributorB_info'] && $commission_uid_list['distributorB_info']['isdistributor'] == 2) {
                $level_infoB = $level->getInfo(['id' => $commission_uid_list['distributorB_info']['distributor_level_id']]);
                $level_infoB_id = $commission_uid_list['distributorB_info']['distributor_level_id'];
                $commissionB_id = $commission_uid_list['distributorB_info']['uid']; //获得二级佣金的用户id
            }
        }
        if ($commission_uid_list && $commission_uid_list['distributorC_info']) { //3级分销商
            if ($commission_uid_list['distributorC_info'] && $commission_uid_list['distributorC_info']['isdistributor'] == 2) {
                $level_infoC = $level->getInfo(['id' => $commission_uid_list['distributorC_info']['distributor_level_id']]);
                $level_infoC_id = $commission_uid_list['distributorC_info']['distributor_level_id'];
                $commissionC_id = $commission_uid_list['distributorC_info']['uid']; //获得三级佣金的用户id
            }
        }


        if ($level_rule_ids) { //有特定等级返佣设置
            foreach ($level_rule_ids as $k => $v) {
                if ($level_infoA_id > 0 && $v == $level_infoA_id) {
                    if ($recommend_type == 1) { //比例返佣
                        $commissionA11 = 0;
                        $pointA11 = 0;
                        $beautiful_pointA11 = 0;
                        $commissionA1 =  $level_first_rebate[$k] / 100; //1级佣金
                        $pointA1 = $level_first_point[$k] / 100; //1级积分
                        $beautiful_pointA1 = $level_first_beautiful_point[$k] / 100; //1级积分
                    } else {
                        $commissionA1 = 0;
                        $pointA1 = 0;
                        $beautiful_pointA1 = 0;
                        $commissionA11 =  $level_first_rebate1[$k]; //1级佣金
                        $pointA11 = $level_first_point1[$k]; //1级积分
                        $beautiful_pointA11 = $level_first_beautiful_point1[$k]; //1级积分
                    }
                    if ($commissionA1 != '') {
                        $commissionA = twoDecimal($real_price * $commissionA1 * $order_goods_info['num']); //当前分销商的推荐人获得1级佣金
                    }
                    if ($commissionA11 != '') {
                        $commissionA = $commissionA11 * $goods_num; //当前分销商的推荐人获得1级固定佣金
                    }
                    if ($pointA1 != '') { //比例1级返积分
                        $pointA = floor($real_price * $pointA1 * $order_goods_info['num']); //开启内购之后当前分销商获得1级积分
                    }
                    if ($pointA11 != '') { //固定1级返积分
                        $pointA =  $pointA11 * $goods_num; //开启内购之后当前分销商获得1级积分
                    }
                    if ($beautiful_pointA1 != '') { //比例1级返积分
                        $beautiful_pointA = floor($real_price * $beautiful_pointA1 * $order_goods_info['num']); //开启内购之后当前分销商获得1级积分
                    }
                    if ($beautiful_pointA11 != '') { //固定1级返积分
                        $beautiful_pointA =  $beautiful_pointA11 * $goods_num; //开启内购之后当前分销商获得1级积分
                    }
                }

                if ($level_infoB_id > 0 && $v == $level_infoB_id) {
                    if ($recommend_type == 1) { //比例返佣
                        $commissionB11 = 0;
                        $pointB11 = 0;
                        $beautiful_pointB11 = 0;
                        $commissionB1 =  $level_second_rebate[$k] / 100; //2级佣金
                        $pointB1 = $level_second_point[$k] / 100; //2级积分
                        $beautiful_pointB1 = $level_second_beautiful_point[$k] / 100; //2级积分
                    } else {
                        $commissionB1 = 0;
                        $pointB1 = 0;
                        $beautiful_pointB1 = 0;
                        $commissionB11 =  $level_second_rebate1[$k]; //2级佣金
                        $pointB11 = $level_second_point1[$k]; //2级积分
                        $beautiful_pointB11 = $level_second_beautiful_point1[$k]; //2级积分
                    }
                    if ($commissionB1 != '') {
                        $commissionB = twoDecimal($real_price * $commissionB1 * $order_goods_info['num']); //当前分销商的推荐人获得二级佣金
                    }
                    if ($commissionB11 != '') {
                        $commissionB = $commissionB11 * $goods_num; //当前分销商的推荐人获得二级固定佣金
                    }
                    if ($pointB1 != '') { //比例二级返积分
                        $pointB = floor($real_price * $pointB1 * $order_goods_info['num']); //开启内购之后当前分销商获得二级积分
                    }
                    if ($pointB11 != '') { //固定二级返积分
                        $pointB =  $pointB11 * $goods_num; //开启内购之后当前分销商获得二级积分
                    }
                    if ($beautiful_pointB1 != '') { //比例二级返积分
                        $beautiful_pointB = floor($real_price * $beautiful_pointB1 * $order_goods_info['num']); //开启内购之后当前分销商获得二级积分
                    }
                    if ($beautiful_pointB11 != '') { //固定二级返积分
                        $beautiful_pointB =  $beautiful_pointB11 * $goods_num; //开启内购之后当前分销商获得二级积分
                    }
                }
                if ($level_infoC_id > 0 && $v == $level_infoC_id) {
                    if ($recommend_type == 1) { //比例返佣
                        $commissionC11 = 0;
                        $pointC11 = 0;
                        $beautiful_pointC11 = 0;
                        $commissionC1 =  $level_third_rebate[$k] / 100; //3级佣金
                        $pointC1 = $level_third_point[$k] / 100; //3级积分
                        $beautiful_pointC1 = $level_third_beautiful_point[$k] / 100; //3级积分
                    } else {
                        $commissionC1 = 0;
                        $pointC1 = 0;
                        $beautiful_pointC1 = 0;
                        $commissionC11 =  $level_third_rebate1[$k]; //3级佣金
                        $pointC11 = $level_third_point1[$k]; //3级积分
                        $beautiful_pointC11 = $level_third_beautiful_point1[$k]; //3级积分
                    }
                    if ($commissionC1 != '') {
                        $commissionC = twoDecimal($real_price * $commissionC1 * $order_goods_info['num']); //当前分销商的推荐人的上级获得三级佣金
                    }
                    if ($commissionC11 != '') {
                        $commissionC =  $commissionC11 * $goods_num; //当前分销商的推荐人的上级获得固定佣金
                    }
                    if ($pointC1 != '') { //比例三级返积分
                        $pointC = floor($real_price * $pointC1 * $order_goods_info['num']); //开启内购之后当前分销商获得三级积分
                    }
                    if ($pointC11 != '') { //固定三级返积分
                        $pointC =  $pointC11 * $goods_num; //开启内购之后当前分销商获得三级积分
                    }
                    if ($beautiful_pointC1 != '') { //比例三级返积分
                        $beautiful_pointC = floor($real_price * $beautiful_pointC1 * $order_goods_info['num']); //开启内购之后当前分销商获得三级积分
                    }
                    if ($beautiful_pointC11 != '') { //固定三级返积分
                        $beautiful_pointC =  $beautiful_pointC11 * $goods_num; //开启内购之后当前分销商获得三级积分
                    }
                }
            }

        } else { //独立规则 or 应用等级规则

            if ($level_infoA_id > 0) { //1级分销商
                //等级设置的复购
                if($level_infoA['buyagain'] == 1 && $countGoods == 1){
                    $level_infoA['recommend_type'] = $level_infoA['buyagain_recommendtype'];
                    $level_infoA['commission1'] = $level_infoA['buyagain_commission1'];
                    $level_infoA['commission2'] = $level_infoA['buyagain_commission2'];
                    $level_infoA['commission3'] = $level_infoA['buyagain_commission3'];
                    $level_infoA['commission_point1'] = $level_infoA['buyagain_commission_point1'];
                    $level_infoA['commission_point2'] = $level_infoA['buyagain_commission_point2'];
                    $level_infoA['commission_point3'] = $level_infoA['buyagain_commission_point3'];
                    $level_infoA['commission_beautiful_point1'] = $level_infoA['buyagain_commission_beautiful_point1'];
                    $level_infoA['commission_beautiful_point2'] = $level_infoA['buyagain_commission_beautiful_point2'];
                    $level_infoA['commission_beautiful_point3'] = $level_infoA['buyagain_commission_beautiful_point3'];

                    $level_infoA['commission11'] = $level_infoA['buyagain_commission11'];
                    $level_infoA['commission22'] = $level_infoA['buyagain_commission22'];
                    $level_infoA['commission33'] = $level_infoA['buyagain_commission33'];
                    $level_infoA['commission_point11'] = $level_infoA['buyagain_commission_point11'];
                    $level_infoA['commission_point22'] = $level_infoA['buyagain_commission_point22'];
                    $level_infoA['commission_point33'] = $level_infoA['buyagain_commission_point33'];
                    $level_infoA['commission_beautiful_point11'] = $level_infoA['buyagain_commission_beautiful_point11'];
                    $level_infoA['commission_beautiful_point22'] = $level_infoA['buyagain_commission_beautiful_point22'];
                    $level_infoA['commission_beautiful_point33'] = $level_infoA['buyagain_commission_beautiful_point33'];
                }
                //佣金
                if ($commission1) {
                    $commissionA1 = $commission1 / 100;
                }
                if ($commission11) {
                    $commissionA11 = $commission11;
                }
                if ($commission1 == '' && $commission11 == '') {
                    if ($level_infoA['recommend_type'] == 1) { //等级比例一级返佣
                        $commissionA1 = $level_infoA['commission1'] / 100;
                    } else { //等级固定一级返佣
                        $commissionA11 = $level_infoA['commission11'];
                    }
                }

                //积分
                if ($point1) {
                    $pointA1 = $point1 / 100;
                }
                if ($point11) {
                    $pointA11 = $point11;
                }
                if ($point1 == '' && $point11 == '') {
                    if ($level_infoA['recommend_type'] == 1) { //等级比例一级返佣
                        $pointA1 = $level_infoA['commission_point1'] / 100;
                    } else { //等级固定一级返佣
                        $pointA11 = $level_infoA['commission_point11'];
                    }
                }
                if ($beautiful_point1) {
                    $beautiful_pointA1 = $beautiful_point1 / 100;
                }
                if ($beautiful_point11) {
                    $beautiful_pointA11 = $beautiful_point11;
                }
                if ($beautiful_point1 == '' && $beautiful_point11 == '') {
                    if ($level_infoA['recommend_type'] == 1) { //等级比例一级返佣
                        $beautiful_pointA1 = $level_infoA['commission_beautiful_point1'] / 100;
                    } else { //等级固定一级返佣
                        $beautiful_pointA11 = $level_infoA['commission_beautiful_point11'];
                    }
                }
                if ($commissionA1 != '') {
                    $commissionA = twoDecimal($real_price * $commissionA1 * $order_goods_info['num']); //当前分销商的推荐人获得1级佣金
                }

                if ($commissionA11 != '') {
                    $commissionA = $commissionA11 * $goods_num; //当前分销商的推荐人获得1级固定佣金
                }

                if ($pointA1 != '') { //比例1级返积分
                    $pointA = floor($real_price * $pointA1 * $order_goods_info['num']); //开启内购之后当前分销商获得1级积分
                }
                if ($pointA11 != '') { //固定1级返积分
                    $pointA =  $pointA11 * $goods_num; //开启内购之后当前分销商获得1级积分
                }
                if ($beautiful_pointA1 != '') { //比例1级返积分
                    $beautiful_pointA = floor($real_price * $beautiful_pointA1 * $order_goods_info['num']); //开启内购之后当前分销商获得1级积分
                }
                if ($beautiful_pointA11 != '') { //固定1级返积分
                    $beautiful_pointA =  $beautiful_pointA11 * $goods_num; //开启内购之后当前分销商获得1级积分
                }
            }
            if ($level_infoB_id > 0) { //2级分销商
                //如果开启复购 等级规则变更为复购规则
                if($level_infoB['buyagain'] == 1 && $countGoods == 1){
                    $level_infoB['recommend_type'] = $level_infoB['buyagain_recommendtype'];
                    $level_infoB['commission1'] = $level_infoB['buyagain_commission1'];
                    $level_infoB['commission2'] = $level_infoB['buyagain_commission2'];
                    $level_infoB['commission3'] = $level_infoB['buyagain_commission3'];
                    $level_infoB['commission_point1'] = $level_infoB['buyagain_commission_point1'];
                    $level_infoB['commission_point2'] = $level_infoB['buyagain_commission_point2'];
                    $level_infoB['commission_point3'] = $level_infoB['buyagain_commission_point3'];
                    $level_infoB['commission_beautiful_point1'] = $level_infoB['buyagain_commission_beautiful_point1'];
                    $level_infoB['commission_beautiful_point2'] = $level_infoB['buyagain_commission_beautiful_point2'];
                    $level_infoB['commission_beautiful_point3'] = $level_infoB['buyagain_commission_beautiful_point3'];

                    $level_infoB['commission11'] = $level_infoB['buyagain_commission11'];
                    $level_infoB['commission22'] = $level_infoB['buyagain_commission22'];
                    $level_infoB['commission33'] = $level_infoB['buyagain_commission33'];
                    $level_infoB['commission_point11'] = $level_infoB['buyagain_commission_point11'];
                    $level_infoB['commission_point22'] = $level_infoB['buyagain_commission_point22'];
                    $level_infoB['commission_point33'] = $level_infoB['buyagain_commission_point33'];
                    $level_infoB['commission_beautiful_point11'] = $level_infoB['buyagain_commission_beautiful_point11'];
                    $level_infoB['commission_beautiful_point22'] = $level_infoB['buyagain_commission_beautiful_point22'];
                    $level_infoB['commission_beautiful_point33'] = $level_infoB['buyagain_commission_beautiful_point33'];
                }
                //佣金
                if ($commission2) {
                    $commissionB2 = $commission2 / 100;
                }
                if ($commission22) {
                    $commissionB22 = $commission22;
                }
                if ($commission2 == '' && $commission22 == '') {
                    if ($level_infoB['recommend_type'] == 1) { //等级比例2级返佣
                        $commissionB2 = $level_infoB['commission2'] / 100;
                    } else { //等级固定一级返佣
                        $commissionB22 = $level_infoB['commission22'];
                    }
                }
                //积分
                if ($point2) {
                    $pointB2 = $point2 / 100;
                }
                if ($point22) {
                    $pointB22 = $point22;
                }
                if ($point2 == '' && $point22 == '') {
                    if ($level_infoB['recommend_type'] == 1) { //等级比例2级返佣
                        $pointB2 = $level_infoB['commission_point2'] / 100;
                    } else { //等级固定一级返佣
                        $pointB22 = $level_infoB['commission_point22'];
                    }
                }
                if ($beautiful_point2) {
                    $beautiful_pointB2 = $beautiful_point2 / 100;
                }
                if ($beautiful_point22) {
                    $beautiful_pointB22 = $beautiful_point22;
                }
                if ($beautiful_point2 == '' && $beautiful_point22 == '') {
                    if ($level_infoB['recommend_type'] == 1) { //等级比例2级返佣
                        $beautiful_pointB2 = $level_infoB['commission_beautiful_point2'] / 100;
                    } else { //等级固定一级返佣
                        $beautiful_pointB22 = $level_infoB['commission_beautiful_point22'];
                    }
                }
                if ($commissionB2 != '') {
                    $commissionB = twoDecimal($real_price * $commissionB2 * $order_goods_info['num']); //当前分销商的推荐人获得二级佣金
                }
                if ($commissionB22 != '') {
                    $commissionB = $commissionB22 * $goods_num; //当前分销商的推荐人获得二级固定佣金
                }
                if ($pointB2 != '') { //比例二级返积分
                    $pointB = floor($real_price * $pointB2 * $order_goods_info['num']); //开启内购之后当前分销商获得二级积分
                }
                if ($pointB22 != '') { //固定二级返积分
                    $pointB =  $pointB22 * $goods_num; //开启内购之后当前分销商获得二级积分
                }
                if ($beautiful_pointB2 != '') { //比例二级返积分
                    $beautiful_pointB = floor($real_price * $beautiful_pointB2 * $order_goods_info['num']); //开启内购之后当前分销商获得二级积分
                }
                if ($beautiful_pointB22 != '') { //固定二级返积分
                    $beautiful_pointB =  $beautiful_pointB22 * $goods_num; //开启内购之后当前分销商获得二级积分
                }
            }

            if ($level_infoC_id > 0) { //3级分销商
                //如果开启复购 等级规则变更为复购规则
                if($level_infoC['buyagain'] == 1 && $countGoods == 1){
                    $level_infoC['recommend_type'] = $level_infoC['buyagain_recommendtype'];
                    $level_infoC['commission1'] = $level_infoC['buyagain_commission1'];
                    $level_infoC['commission2'] = $level_infoC['buyagain_commission2'];
                    $level_infoC['commission3'] = $level_infoC['buyagain_commission3'];
                    $level_infoC['commission_point1'] = $level_infoC['buyagain_commission_point1'];
                    $level_infoC['commission_point2'] = $level_infoC['buyagain_commission_point2'];
                    $level_infoC['commission_point3'] = $level_infoC['buyagain_commission_point3'];
                    $level_infoC['commission_beautiful_point1'] = $level_infoC['buyagain_commission_beautiful_point1'];
                    $level_infoC['commission_beautiful_point2'] = $level_infoC['buyagain_commission_beautiful_point2'];
                    $level_infoC['commission_beautiful_point3'] = $level_infoC['buyagain_commission_beautiful_point3'];

                    $level_infoC['commission11'] = $level_infoC['buyagain_commission11'];
                    $level_infoC['commission22'] = $level_infoC['buyagain_commission22'];
                    $level_infoC['commission33'] = $level_infoC['buyagain_commission33'];
                    $level_infoC['commission_point11'] = $level_infoC['buyagain_commission_point11'];
                    $level_infoC['commission_point22'] = $level_infoC['buyagain_commission_point22'];
                    $level_infoC['commission_point33'] = $level_infoC['buyagain_commission_point33'];
                    $level_infoC['commission_beautiful_point11'] = $level_infoC['buyagain_commission_beautiful_point11'];
                    $level_infoC['commission_beautiful_point22'] = $level_infoC['buyagain_commission_beautiful_point22'];
                    $level_infoC['commission_beautiful_point33'] = $level_infoC['buyagain_commission_beautiful_point33'];
                }
                //佣金
                if ($commission3) {
                    $commissionC3 = $commission3 / 100;
                }
                if ($commission33) {
                    $commissionC33 = $commission33;
                }

                if ($commission3 == '' && $commission33 == '') {
                    if ($level_infoC['recommend_type'] == 1) { //等级比例3级返佣
                        $commissionC3 = $level_infoC['commission3'] / 100;
                    } else { //等级固定一级返佣
                        $commissionC33 = $level_infoC['commission33'];
                    }
                }
                //积分
                if ($point3) {
                    $pointC3 = $point3 / 100;
                }
                if ($point33) {
                    $pointC33 = $point33;
                }
                if ($point3 == '' && $point33 == '') {
                    if ($level_infoC['recommend_type'] == 1) { //等级比例3级返佣
                        $pointC3 = $level_infoC['commission_point3'] / 100;
                    } else { //等级固定一级返佣
                        $pointC33 = $level_infoC['commission_point33'];
                    }
                }
                if ($beautiful_point3) {
                    $beautiful_pointC3 = $beautiful_point3 / 100;
                }
                if ($beautiful_point33) {
                    $beautiful_pointC33 = $beautiful_point33;
                }
                if ($beautiful_point3 == '' && $beautiful_point33 == '') {
                    if ($level_infoC['recommend_type'] == 1) { //等级比例3级返佣
                        $beautiful_pointC3 = $level_infoC['commission_beautiful_point3'] / 100;
                    } else { //等级固定一级返佣
                        $beautiful_pointC33 = $level_infoC['commission_beautiful_point33'];
                    }
                }
                if ($commissionC3 != '') {
                    $commissionC = twoDecimal($real_price * $commissionC3 * $order_goods_info['num']); //当前分销商的推荐人的上级获得三级佣金
                }
                if ($commissionC33 != '') {
                    $commissionC =  $commissionC33 * $goods_num; //当前分销商的推荐人的上级获得固定佣金
                }
                if ($pointC3 != '') { //比例三级返积分
                    $pointC = floor($real_price * $pointC3 * $order_goods_info['num']); //开启内购之后当前分销商获得三级积分
                }
                if ($pointC33 != '') { //固定三级返积分
                    $pointC =  $pointC33 * $goods_num; //开启内购之后当前分销商获得三级积分
                }
                if ($beautiful_pointC3 != '') { //比例三级返积分
                    $beautiful_pointC = floor($real_price * $beautiful_pointC3 * $order_goods_info['num']); //开启内购之后当前分销商获得三级积分
                }
                if ($beautiful_pointC33 != '') { //固定三级返积分
                    $beautiful_pointC =  $beautiful_pointC33 * $goods_num; //开启内购之后当前分销商获得三级积分
                }
            }
        }

        $commission_total = $commissionA + $commissionB + $commissionC;
        $point_total = $pointA + $pointB + $pointC;
        $beautiful_point_total = $beautiful_pointA + $beautiful_pointB + $beautiful_pointC;


        if ($commissionA_id || $commissionB_id || $commissionC_id) {
            $commission = new VslOrderDistributorCommissionModel();
            $order_info =  $commission->getInfo(['order_goods_id' => $params['order_goods_id'], 'order_id' => $params['order_id']]);
            if($order_info){
                return 1;
            }
            if ($distribution_admin_status == 1) {
                $shop_id = $goods_info['shop_id'] ? $goods_info['shop_id'] : $order_info['shop_id'];
            } else {
                $shop_id = 0;
            }
            if ($distribution_admin_status == 1 && $goods_info['distribution_rule'] == 2 && $goods_info['shop_id'] > 0) {
                //旧商品 开启
                $commissionA = 0;
                $pointA = 0;
                $commissionB = 0;
                $pointB = 0;
                $commissionC = 0;
                $pointC = 0;
                $commission_total = 0;
                $point_total = 0;
                $shop_id = 0;
            }
            $commission->startTrans();
            try {
                //判断会员有没有额外赠送佣金的权益  todo---暂时是自营店商品才参与
                if ($shop_id == 0) {
                    if (getAddons('membercard', $params['website_id'])) {
                        $membercard = new \addons\membercard\server\Membercard();
                        $membercard_data = $membercard->checkMembercardStatus($params['buyer_id'], $params['website_id']);


                        if ($membercard_data['status'] && $membercard_data['membercard_info']['is_give_commission']) {
                            //有额外赠送佣金的权益
                            $membercard_data['membercard_info']['commission_val'] = json_decode($membercard_data['membercard_info']['commission_val'], true);
                            if ($membercard_data['membercard_info']['commission_type'] == 1) {
                                //固定类型返佣
                                if ($commissionA_id) { //一级返佣
                                    $commissionA_level_id = $this->getDistributorLevelId($commissionA_id);
                                    foreach ($membercard_data['membercard_info']['commission_val'] as $k => $v) {
                                        $v = explode(',', $v);
                                        if ($commissionA_level_id == $v[0]) {
                                            $commissionA = $commissionA + $v[1];
                                            break;
                                        }
                                    }
                                }
                                if ($commissionB_id) { //二级返佣
                                    $commissionB_level_id = $this->getDistributorLevelId($commissionB_id);
                                    foreach ($membercard_data['membercard_info']['commission_val'] as $k => $v) {
                                        $v = explode(',', $v);
                                        if ($commissionB_level_id == $v[0]) {
                                            $commissionB = $commissionB + $v[2];
                                            break;
                                        }
                                    }
                                }
                                if ($commissionC_id) { //三级返佣
                                    $commissionC_level_id = $this->getDistributorLevelId($commissionC_id);
                                    foreach ($membercard_data['membercard_info']['commission_val'] as $k => $v) {
                                        $v = explode(',', $v);
                                        if ($commissionC_level_id == $v[0]) {
                                            $commissionC = $commissionC + $v[3];
                                            break;
                                        }
                                    }
                                }
                            } elseif ($membercard_data['membercard_info']['commission_type'] == 2) {
                                //比例类型返佣
                                if ($commissionA_id) { //一级返佣
                                    $commissionA_level_id = $this->getDistributorLevelId($commissionA_id);
                                    foreach ($membercard_data['membercard_info']['commission_val'] as $k => $v) {
                                        $v = explode(',', $v);
                                        if ($commissionA_level_id == $v[0]) {
                                            $commissionA = twoDecimal($commissionA + $commissionA * $v[1] / 100);
                                            break;
                                        }
                                    }
                                }
                                if ($commissionB_id) { //二级返佣
                                    $commissionB_level_id = $this->getDistributorLevelId($commissionB_id);
                                    foreach ($membercard_data['membercard_info']['commission_val'] as $k => $v) {
                                        $v = explode(',', $v);
                                        if ($commissionB_level_id == $v[0]) {
                                            $commissionB = twoDecimal($commissionB + $commissionB * $v[2] / 100);
                                            break;
                                        }
                                    }
                                }
                                if ($commissionC_id) { //三级返佣
                                    $commissionC_level_id = $this->getDistributorLevelId($commissionC_id);
                                    foreach ($membercard_data['membercard_info']['commission_val'] as $k => $v) {
                                        $v = explode(',', $v);
                                        if ($commissionC_level_id == $v[0]) {
                                            $commissionC = twoDecimal($commissionC + $commissionC * $v[3] / 100);
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                $data = [
                    'order_id' => $params['order_id'],
                    'order_goods_id' => $params['order_goods_id'],
                    'buyer_id' => $params['buyer_id'],
                    'website_id' => $params['website_id'],
                    'commissionA_id' => $commissionA_id,
                    'commissionA' => $commissionA,
                    'pointA' => $pointA,
                    'commissionB_id' => $commissionB_id,
                    'commissionB' => $commissionB,
                    'pointB' => $pointB,
                    'commissionC_id' => $commissionC_id,
                    'commissionC' => $commissionC,
                    'pointC' => $pointC,
                    'commission' => $commission_total,
                    'point' => $point_total,
                    'shop_id' => $shop_id,
                    'beautiful_pointA' => $beautiful_pointA,
                    'beautiful_pointB' => $beautiful_pointB,
                    'beautiful_pointC' => $beautiful_pointC,
                    'beautiful_point' => $beautiful_point_total
                ];

                //edit for 2020/07/06 店铺订单如果产生的分销分红金额超出店铺收入则终止写入
                if ($shop_id > 0) {
                    $point_money1 = changePoints($point_total, $params['website_id']);
                    //需要补充已计算的商品佣金 以及把积分换成相应的资金
                    $order_commission = new VslOrderDistributorCommissionModel();
                    $all_commission = $order_commission->getSum(['order_id' => $params['order_id']], 'commission'); //分销总金额
                    $all_point = $order_commission->getSum(['order_id' => $params['order_id']], 'point'); //分销总金额
                    $point_money2 = changePoints($all_point, $params['website_id']);
                    if ($shop_order_money < ($commission_total + $point_money1 + $point_money2 + floatval($all_commission))) {
                        //不作写入
                        debugLog($params['order_id'], '==>店铺收入低于佣金金额_不作写入<==');
                        debugLog($shop_order_money, '==>店铺收入低于佣金金额_不作写入_shop_order_money<==');
                        debugLog($commission_total . '+,' .  $point_money1 . '+,' .  $point_money2 . '+,' .  $all_commission . '==>店铺收入低于佣金金额_不作写入_total_money<==');
                        $commission->commit();
                        return 1;
                    }
                }

                $order->isUpdate(true)->save(['is_distribution' => 1], ['order_id' => $params['order_id'], 'is_distribution' => 0]);

                $commission->save($data);
                $commission->commit();
                return 1;
            } catch (\Exception $e) {

                debugLog($e->getMessage(), '==计算商品分销异常结果<==');
                $commission->rollback();
                return $e->getMessage();
            }
        }

    }

    /*
     * 已支付定金，未支付尾款关闭的预售订单退款 添加佣金账户流水表
     * orderid 订单id
     */
    public function addCommissionDistributionPresell_nal($orderid)
    {
        $distributor_account = new VslDistributorAccountRecordsModel();
        $account_statistics = new VslDistributorAccountModel();
        $order = new VslOrderModel();
        $data_records = array();
        $update_records = [];
        $distributor_account->startTrans();
        $order_info = $order->getInfo(['order_id' => $orderid], 'order_no');

        try {
            $order_commission = new VslOrderDistributorCommissionModel();
            $orders = $order_commission->Query(['order_id' => $orderid], '*');
            $up_data = array();
            foreach ($orders as $key => $value) {

                if (floatval($value['commissionA']) > 0) {
                    //1级分销解除冻结佣金
                    $records_no_A = 'CR' . time() . rand(111, 999);
                    $countA = $account_statistics->getInfo(['uid' => $value['commissionA_id']], '*'); //佣金账户
                    $commission_data_A = array(
                        'freezing_commission' => $countA['freezing_commission'] - abs($value['commissionA']),
                    );
                    $account_statistics->save($commission_data_A, ['uid' => $value['commissionA_id']]);
                    //写入记录
                    $data_records_A = array(
                        'uid' => $value['commissionA_id'],
                        'records_no' => $records_no_A,
                        'balance' => $countA['commission'],
                        'data_id' => $order_info['order_no'],
                        'website_id' => $value['website_id'],
                        'commission' => $value['commissionA'],
                        'text' => '已支付定金预售订单关闭,冻结佣金减少',
                        'create_time' => time(),
                        'from_type' => 2,
                        'shop_id' => intval($value['shop_id']),
                    );
                    array_push($up_data, $data_records_A);
                    // $distributor_account->save($data_records_A);
                }
                if (floatval($value['commissionB']) > 0) {
                    //2级分销解除冻结佣金
                    $records_no_B = 'CR' . time() . rand(111, 999);
                    $countB = $account_statistics->getInfo(['uid' => $value['commissionB_id']], '*'); //佣金账户
                    $commission_data_B = array(
                        'freezing_commission' => $countB['freezing_commission'] - abs($value['commissionB']),
                    );
                    $account_statistics->save($commission_data_B, ['uid' => $value['commissionB_id']]);
                    //写入记录
                    $data_records_B = array(
                        'uid' => $value['commissionB_id'],
                        'records_no' => $records_no_B,
                        'balance' => $countB['commission'],
                        'data_id' => $order_info['order_no'],
                        'website_id' => $value['website_id'],
                        'commission' => $value['commissionB'],
                        'text' => '已支付定金预售订单关闭,冻结佣金减少',
                        'create_time' => time(),
                        'from_type' => 2,
                        'shop_id' => intval($value['shop_id']),
                    );
                    array_push($up_data, $data_records_B);
                    // $distributor_account->save($data_records_B);
                }
                if (floatval($value['commissionC']) > 0) {
                    //3级分销解除冻结佣金
                    $records_no_C = 'CR' . time() . rand(111, 999);
                    $countC = $account_statistics->getInfo(['uid' => $value['commissionC_id']], '*'); //佣金账户
                    $commission_data_C = array(
                        'freezing_commission' => $countC['freezing_commission'] - abs($value['commissionC']),
                    );
                    $account_statistics->save($commission_data_C, ['uid' => $value['commissionC_id']]);
                    //写入记录
                    $data_records_C = array(
                        'uid' => $value['commissionC_id'],
                        'records_no' => $records_no_C,
                        'balance' => $countB['commission'],
                        'data_id' => $order_info['order_no'],
                        'website_id' => $value['website_id'],
                        'commission' => $value['commissionC'],
                        'text' => '已支付定金预售订单关闭,冻结佣金减少',
                        'create_time' => time(),
                        'from_type' => 2,
                        'shop_id' => intval($value['shop_id']),
                    );
                    array_push($up_data, $data_records_C);
                    // $distributor_account->save($data_records_C);
                }
            }
            if ($up_data) {
                $distributor_account->saveAll($up_data);
            }
            $distributor_account->commit();
            return 1;
        } catch (\Exception $e) {
            $distributor_account->rollback();
            return $e->getMessage();
        }
    }
    public function addRefereeLogs(){
        echo 1;
    }
    /*
     * 添加佣金账户流水表
     */
    public function addCommissionDistribution($params)
    {
        $distributor_account = new VslDistributorAccountRecordsModel();
        $data_records = array();
        $update_records = [];
        $distributor_account->startTrans();
        $order_id = $params['order_id'];
        $shop_id = 0;
        if ($params['order_id']) {
            $order = new VslOrderModel();
            $order_info = $order->getInfo(['order_id' => $params['order_id']], '*');
            $params['order_id'] = $order_info['order_no'];
            $buyer_id = $order_info['buyer_id'];
            $shop_id = $order_info['shop_id'];
        }
        $records_no = 'CR' . time() . rand(111, 999);
        $records_info = $distributor_account->getInfo(['data_id' => $params['data_id']]);
        try {
            //前期检测
            //更新对应佣金流水
            $account_statistics = new VslDistributorAccountModel();
            $account = new VslAccountModel();
            $member_account = new VslMemberAccountModel();
            $member_account_record = new VslMemberAccountRecordsModel();
            //更新对应佣金账户和平台账户
            $count = $account_statistics->getInfo(['uid' => $params['uid']], '*'); //佣金账户
            $account_count = $account->getInfo(['website_id' => $params['website_id']], '*'); //平台账户
            if ($params['status'] == 1) { //订单完成，添加佣金
                //积分流水
                $this->addMemberPoint($params['point'], $params['uid'], $params['order_id'], $params['website_id'], $member_account, $member_account_record);
                $this->addBeautifulMemberPoint($params['beautiful_point'], $params['uid'], $params['order_id'], $params['website_id'], $member_account, $member_account_record);
                //判断佣金结算方式 1：分销佣金  2：商城余额
                $config = new ConfigService();
                $info = $config->getConfig(0, 'SETTLEMENT', $params['website_id']);
                $settle_arr = $info['value'];
                if (isset($info)) { //分销佣金提现方式
                    if ($settle_arr['settlement_type'] == 2) { //余额直接到账方式
                        $cash = abs($params['commission']);
                        if ($cash != 0 || $params['uid'] != 0) {
                            $withdraw_no = 'CW' . time() . rand(111, 999);
                            $member = new VslMemberModel();
                            $member_info = $member->getInfo(['uid' => $params['uid']], '*');
                            // 判断当前分销商的可提现佣金
                            $account = new VslDistributorAccountModel();
                            $commission_info = $account->getInfo(['uid' => $params['uid']], '*');
                            $commission1 = $commission_info['commission'];
                            $config = new ConfigService();
                            $commission_withdraw_set = $config->getConfig(0,'SETTLEMENT',$member_info['website_id'], 1);
                            $tax = 0; //佣金个人所得税
                            if ($commission_withdraw_set['poundage']) {
                                $tax = twoDecimal(abs($cash) * $commission_withdraw_set['poundage'] / 100); //佣金个人所得税
                                if ($commission_withdraw_set['withdrawals_end'] && $commission_withdraw_set['withdrawals_begin']) {
                                    if (abs($cash) <= $commission_withdraw_set['withdrawals_end'] && abs($cash) >= $commission_withdraw_set['withdrawals_begin']) {
                                        $tax = 0; //免打税区间
                                    }
                                }
                            }
                            $income_tax = 0;
                            if ($cash + $tax <= $commission1) {
                                $income_tax = $cash;
                            } else if ($cash - $tax >= 0) {
                                $income_tax = $cash - $tax;
                            }
                            $ti_cash = $cash;
                            $ti_tax = $tax;
                            $cash = (-1) * $cash;
                            $tax = (-1) * $tax;
                            $data1['data_id'] = $withdraw_no;
                            $data1['commission'] = $params['commission'];
                            $data1['website_id'] = $params['website_id'];
                            $data1['cash'] = $cash;
                            $data1['ti_cash'] = $ti_cash;
                            $data1['ti_tax'] = $ti_tax;
                            $data1['income_tax'] = $income_tax;
                            $data1['uid'] = $params['uid'];
                            $data1['tax'] = $tax;
                            $data1['text'] = '提现到账户余额成功,可提现佣金减少,已提现佣金增加';
                            runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $params['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]); //提现成功
                            $res = $this->addCommissionWithdrawForBalance($data1);
                        }
                    } else {
                        //佣金账户佣金改变
                        if ($count) {
                            $account_data = array(
                                'commission' => $count['commission'] + abs($params['commission']),
                                'freezing_commission' => $count['freezing_commission'] - abs($params['commission'])
                            );
                            $account_statistics->save($account_data, ['uid' => $params['uid']]);
                        }
                        if ($buyer_id != $params['uid']) {
                            runhook("Notify", "sendCustomMessage", ["messageType" => "subordinate_order_fulfillment", "uid" => $buyer_id, 'freezing_commission' => abs($params['commission']), "order_id" => $order_id, 'referee_id' => $params['uid']]); //下线付款
                        }
                        //平台账户佣金改变
                        if ($account_count) {
                            $commission_data = array(
                                'commission' => $account_count['commission'] + abs($params['commission']),
                            );
                            $account->save($commission_data, ['website_id' => $params['website_id']]);
                        }
                    }
                }
            }
            if ($params['status'] == 2) { //订单退款完成，冻结佣金改变
                if ($count) {
                    $commission_data = array(
                        'freezing_commission' => $count['freezing_commission'] - abs($params['commission']),
                    );
                    $account_statistics->save($commission_data, ['uid' => $params['uid']]);
                }
            }
            if ($params['status'] == 3) { //订单支付完成，冻结佣金改变
                if (empty($records_info)) {
                    //分销商佣金账户改变
                    if ($count) {
                        $commission_data = array(
                            'freezing_commission' => $count['freezing_commission'] + abs($params['commission']),
                        );
                        $account_statistics->save($commission_data, ['uid' => $params['uid']]);
                    } else {
                        $commission_data = array(
                            'uid' => $params['uid'],
                            'website_id' => $params['website_id'],
                            'freezing_commission' => abs($params['commission']),
                        );
                        $account_statistics->save($commission_data);
                    }

                    if ($buyer_id != $params['uid']) {
                        runhook("Notify", "sendCustomMessage", ["messageType" => "subordinate_payment", "uid" => $buyer_id, 'freezing_commission' => abs($params['commission']), "order_id" => $order_id, 'referee_id' => $params['uid']]); //下线付款
                    }
                    //平台账户流水表
                    $shop = new ShopAccount();
                    $shop->addAccountRecords(0, $params['uid'], '订单支付完成佣金', $params['commission'], 5, $params['order_id'], '订单支付完成，账户佣金增加', $params['website_id']);
                }
            }
            if ($params['status'] == 1) {
                if (isset($info) && $settle_arr['settlement_type'] != 2) { //分销佣金提现方式
                    //查询是否已经插入,有的话不再插入流水
                    $checkRecord = $distributor_account->getInfo(['data_id' => $params['order_id'], 'from_type' => 1, 'uid' => $params['uid'], 'website_id' => $params['website_id']]);
                    if (!$checkRecord) {
                        $data_records = array( //订单完成
                            'uid' => $params['uid'],
                            'records_no' => $records_no,
                            'data_id' => $params['order_id'],
                            'commission' => $params['commission'],
                            'balance' => $count['commission'] + abs($params['commission']),
                            'from_type' => 1,
                            'website_id' => $params['website_id'],
                            'text' => '订单完成,冻结佣金减少,可提现佣金增加',
                            'create_time' => time(),
                        );
                    }
                }
            }
            if ($params['status'] == 2) { //订单退款
                $records_count = $distributor_account->getInfo(['data_id' => $params['order_id']], '*');
                if ($records_count) {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['order_id'],
                        'website_id' => $params['website_id'],
                        'commission' => $params['commission'],
                        'text' => '订单退款,冻结佣金减少',
                        'create_time' => time(),
                        'from_type' => 2,
                    );
                }
            }
            if ($params['status'] == 3) { //订单支付成功
                if (empty($records_info)) {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'data_id' => $params['order_id'],
                        'balance' => $count['commission'],
                        'website_id' => $params['website_id'],
                        'commission' => $params['commission'],
                        'text' => '订单支付,冻结佣金增加',
                        'create_time' => time(),
                        'from_type' => 3,
                    );
                } else {
                    $update_records = array(
                        'text' => '订单支付,冻结佣金增加',
                        'from_type' => 3
                    );
                }
            }
            if ($params['status'] == 4) { //佣金成功提现到账户余额
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到账户余额成功,可提现佣金减少',
                        'from_type' => 4,
                        'status' => 3
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * abs($params['tax']),
                        'text' => $params['text'],
                        'create_time' => time(),
                        'from_type' => 4, //佣金提现到账户余额成功
                    );
                }
            }
            if ($params['status'] == 6) { //佣金提现账户余额审核中
                $data_records = array(
                    'uid' => $params['uid'],
                    'records_no' => $records_no,
                    'data_id' => $params['data_id'],
                    'balance' => $count['commission'],
                    'website_id' => $params['website_id'],
                    'commission' => (-1) * abs($params['cash']),
                    'tax' => (-1) * $params['tax'],
                    'text' => '提现到余额待审核,可提现佣金减少,冻结佣金增加',
                    'create_time' => time(),
                    'from_type' => 6,
                    'status' => 1
                );
            }
            if ($params['status'] == 15) { //佣金提现账户余额待打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到余额待打款,可提现佣金减少,冻结佣金增加',
                        'from_type' => 15,
                        'status' => 2
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'data_id' => $params['data_id'],
                        'balance' => $count['commission'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到余额待打款,可提现佣金减少,冻结佣金增加',
                        'create_time' => time(),
                        'from_type' => 15,
                        'status' => 2
                    );
                }
            }
            if ($params['status'] == 5) { //佣金提现到微信待打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到微信待打款,可提现佣金减少,冻结佣金增加',
                        'from_type' => 5,
                        'status' => 2
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'website_id' => $params['website_id'],
                        'text' => '提现到微信待打款,可提现佣金减少,冻结佣金增加',
                        'create_time' => time(),
                        'from_type' => 5,
                        'status' => 2
                    );
                }
            }
            if ($params['status'] == 7) { //佣金提现到支付宝待打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到支付宝待打款,可提现佣金减少,冻结佣金增加',
                        'status' => 2,
                        'from_type' => 7,
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'data_id' => $params['data_id'],
                        'balance' => $count['commission'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到支付宝待打款,可提现佣金减少,冻结佣金增加',
                        'create_time' => time(),
                        'from_type' => 7,
                        'status' => 2
                    );
                }
            }
            if ($params['status'] == 8) { //佣金提现到银行卡待打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到银行卡待打款,可提现佣金减少,冻结佣金增加',
                        'from_type' => 8,
                        'status' => 2
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'data_id' => $params['data_id'],
                        'balance' => $count['commission'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到银行卡待打款,可提现佣金减少,冻结佣金增加',
                        'create_time' => time(),
                        'from_type' => 8,
                        'status' => 2
                    );
                }
            }
            if ($params['status'] == 9) { //佣金成功提现到到银行卡
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到银行卡成功,可提现佣金减少',
                        'from_type' => 9,
                        'status' => 3
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到银行卡成功',
                        'create_time' => time(),
                        'from_type' => 9,
                    );
                }
            }
            if ($params['status'] == -9) { //佣金提现到到银行卡失败
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到银行卡打款失败,等待商家重新打款',
                        'from_type' => -9,
                        'msg' => $params['msg'],
                        'status' => 4
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'msg' => $params['msg'],
                        'text' => '提现到银行卡打款失败,等待商家重新打款',
                        'create_time' => time(),
                        'from_type' => -9,
                        'status' => 4
                    );
                }
            }
            if ($params['status'] == 10) { //佣金成功提现到到微信
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到微信成功,冻结佣金减少,已提现佣金增加',
                        'from_type' => 10,
                        'status' => 3
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到微信成功',
                        'create_time' => time(),
                        'from_type' => 10,
                    );
                }
            }
            if ($params['status'] == -10) { //佣金提现到微信失败
                if ($records_info) {
                    $update_records = array(
                        'from_type' => -10,
                        'msg' => $params['msg'],
                        'text' => '提现到微信打款失败,等待商家重新打款',
                        'status' => 4
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到微信打款失败,等待商家重新打款',
                        'msg' => $params['msg'],
                        'create_time' => time(),
                        'from_type' => -10,
                        'status' => 4
                    );
                }
            }
            if ($params['status'] == 11) { //佣金成功提现到支付宝
                if ($records_info) {
                    $update_records = array(
                        'from_type' => 11,
                        'text' => '提现到支付宝成功,冻结佣金减少,已提现佣金增加',
                        'status' => 3
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到支付宝成功',
                        'create_time' => time(),
                        'from_type' => 11,
                    );
                }
            }
            if ($params['status'] == -11) { //佣金提现到支付宝失败
                if ($records_info) {
                    $update_records = array(
                        'from_type' => -11,
                        'msg' => $params['msg'],
                        'text' => '提现到支付宝打款失败,等待商家重新打款',
                        'status' => 4
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'msg' => $params['msg'],
                        'website_id' => $params['website_id'],
                        'commission' => (-1) * abs($params['cash']),
                        'tax' => (-1) * $params['tax'],
                        'text' => '提现到支付宝打款失败,等待商家重新打款',
                        'create_time' => time(),
                        'from_type' => -11,
                        'status' => 4
                    );
                }
            }
            if ($params['status'] == 12) { //佣金提现到银行卡审核中
                $data_records = array(
                    'uid' => $params['uid'],
                    'records_no' => $records_no,
                    'balance' => $count['commission'],
                    'data_id' => $params['data_id'],
                    'website_id' => $params['website_id'],
                    'commission' => (-1) * abs($params['cash']),
                    'tax' => (-1) * $params['tax'],
                    'text' => '提现到银行卡审核中,可提现佣金减少,冻结佣金增加',
                    'create_time' => time(),
                    'from_type' => 12,
                    'status' => 1
                );
            }
            if ($params['status'] == 13) { //佣金提现到微信审核中
                $data_records = array(
                    'uid' => $params['uid'],
                    'records_no' => $records_no,
                    'balance' => $count['commission'],
                    'data_id' => $params['data_id'],
                    'website_id' => $params['website_id'],
                    'commission' => (-1) * abs($params['cash']),
                    'tax' => (-1) * $params['tax'],
                    'text' => '提现到微信审核中,可提现佣金减少,冻结佣金增加',
                    'create_time' => time(),
                    'from_type' => 13,
                    'status' => 1
                );
            }
            if ($params['status'] == 14) { //佣金提现到支付宝审核中
                $data_records = array(
                    'uid' => $params['uid'],
                    'records_no' => $records_no,
                    'balance' => $count['commission'],
                    'data_id' => $params['data_id'],
                    'website_id' => $params['website_id'],
                    'commission' => (-1) * abs($params['cash']),
                    'tax' => (-1) * $params['tax'],
                    'text' => '提现到支付宝审核中,可提现佣金减少,冻结佣金增加',
                    'create_time' => time(),
                    'from_type' => 14,
                    'status' => 1
                );
            }
            if ($params['status'] == 16) { //平台拒绝微信打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到微信平台拒绝,冻结佣金减少,可提现佣金增加',
                        'from_type' => 16,
                        'msg' => $params['msg'],
                        'status' => 5
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'balance' => $count['commission'],
                        'msg' => $params['msg'],
                        'records_no' => $records_no,
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'text' => '提现到微信平台拒绝,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 16,
                        'status' => 5,
                    );
                }
            }
            if ($params['status'] == 19) { //佣金提现到微信审核不通过
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到微信审核不通过,冻结佣金减少,可提现佣金增加',
                        'from_type' => 19,
                        'msg' => $params['msg'],
                        'status' => -1
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'msg' => $params['msg'],
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'text' => '提现到微信审核不通过,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 19,
                        'status' => -1,
                    );
                }
            }
            if ($params['status'] == 24) { //佣金提现到银行卡不通过
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到银行卡审核不通过,冻结佣金减少,可提现佣金增加',
                        'from_type' => 24,
                        'msg' => $params['msg'],
                        'status' => -1
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'msg' => $params['msg'],
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'text' => '提现到银行卡审核不通过,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 24,
                        'status' => 5,
                    );
                }
            }
            if ($params['status'] == 23) { //平台拒绝银行卡打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到银行卡平台拒绝,冻结佣金减少,可提现佣金增加',
                        'from_type' => 23,
                        'msg' => $params['msg'],
                        'status' => 5
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'msg' => $params['msg'],
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'text' => '提现到银行卡平台拒绝,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 23,
                        'status' => 5,
                    );
                }
            }
            if ($params['status'] == 17) { //平台拒绝支付宝打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到支付宝平台拒绝,冻结佣金减少,可提现佣金增加',
                        'from_type' => 17,
                        'msg' => $params['msg'],
                        'status' => 5
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'msg' => $params['msg'],
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'text' => '提现到支付宝平台拒绝,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 17,
                        'status' => 5,
                    );
                }
            }
            if ($params['status'] == 18) { //平台拒绝账户余额打款
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到账户余额平台拒绝,冻结佣金减少,可提现佣金增加',
                        'from_type' => 18,
                        'msg' => $params['msg'],
                        'status' => 5
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'msg' => $params['msg'],
                        'text' => '提现到账户余额平台拒绝,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 18,
                        'status' => 5,
                    );
                }
            }
            if ($params['status'] == 20) { //佣金提现到支付宝审核不通过
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到支付宝审核不通过,冻结佣金减少,可提现佣金增加',
                        'from_type' => 20,
                        'msg' => $params['msg'],
                        'status' => -1
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'data_id' => $params['data_id'],
                        'balance' => $count['commission'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'msg' => $params['msg'],
                        'text' => '提现到支付宝审核不通过,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 20,
                        'status' => -1,
                    );
                }
            }
            if ($params['status'] == 21) { //佣金提现到账户余额审核不通过
                if ($records_info) {
                    $update_records = array(
                        'text' => '提现到账户余额审核不通过,冻结佣金减少,可提现佣金增加',
                        'from_type' => 21,
                        'msg' => $params['msg'],
                        'status' => -1
                    );
                } else {
                    $data_records = array(
                        'uid' => $params['uid'],
                        'records_no' => $records_no,
                        'balance' => $count['commission'],
                        'data_id' => $params['data_id'],
                        'website_id' => $params['website_id'],
                        'commission' => abs($params['cash']),
                        'tax' => $params['tax'],
                        'msg' => $params['msg'],
                        'text' => '提现到账户余额审核不通过,冻结佣金减少,可提现佣金增加',
                        'create_time' => time(),
                        'from_type' => 21,
                        'status' => -1,
                    );
                }
            }
            if ($data_records) {
                $data_records['shop_id'] = $shop_id;
                $distributor_account->save($data_records);
            }
            if ($update_records) {
                $distributor_account->save($update_records, ['data_id' => $params['data_id']]);
            }
            $distributor_account->commit();
            return 1;
        } catch (\Exception $e) {
            debugFile($e->getMessage(), 'addCommissionDistribution_1', 1111);
            $distributor_account->rollback();
            return $e->getMessage();
        }
    }

    /*
     * 订单完成和修改推荐人后分销商等级升级
     */
    public function updateDistributorLevelInfo($uid)
    {
        $member = new VslMemberModel();
        $level = new DistributorLevelModel();
        $config = new AddonsConfigService();
        $distributor = $member->getInfo(['uid' => $uid], '*');
        $default_level_name =  $level->getInfo(['id' => $distributor['distributor_level_id']], 'level_name')['level_name'];
        $base_info = $config->getAddonsConfig("distribution", $distributor['website_id'], 0, 1); //基本设置
        $distribution_pattern = $base_info['distribution_pattern'];
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        if ($base_info['distributor_grade'] == 1) { //开启跳级
            if ($distributor['isdistributor'] == 2) {
                $getDistributorInfo = $this->getDistributorLowerInfo($uid); //当前分销商的详情信息
                $level_weight = $level->Query(['id' => $distributor['distributor_level_id']], 'weight'); //当前分销商的等级权重
                $level_weights = $level->Query(['weight' => ['>', implode(',', $level_weight)], 'website_id' => $distributor['website_id']], 'weight'); //当前分销商的等级权重的上级权重
                if ($level_weights) {
                    sort($level_weights);
                    foreach ($level_weights as $k => $v) {
                        $ratio = 0;
                        $level_infos = $level->getInfo(['weight' => $v, 'website_id' => $distributor['website_id']]); //当前等级信息
                        if ($level_infos && $level_infos['upgrade_level']) {
                            $low_number = $member->getCount(['distributor_level_id' => $level_infos['upgrade_level'], 'referee_id' => $uid, 'website_id' => $distributor['website_id']]); //该等级指定推荐等级人数
                        } else {
                            $low_number = 0;
                        }
                        //判断是否购买过指定商品
                        $goods_info = [];
                        if ($level_infos['goods_id']) {
                            $goods_id = $order_goods->Query(['goods_id' => ['IN', $level_infos['goods_id']], 'buyer_id' => $uid], 'order_id');
                            if ($goods_id) {
                                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4], '*');
                            }
                        }
                        if ($level_infos['upgradetype'] == 1) { //是否开启自动升级
                            $conditions = explode(',', $level_infos['upgradeconditions']);
                            $result = [];
                            foreach ($conditions as $k1 => $v1) {
                                switch ($v1) {
                                    case 7:
                                        if ($getDistributorInfo['number1'] >= $level_infos['number1']) {
                                            $result[] = 7; //一级分销商
                                        }
                                        break;
                                    case 8:
                                        if ($getDistributorInfo['number2'] >= $level_infos['number2']) {
                                            $result[] = 8; //二级分销商
                                        }
                                        break;
                                    case 9:
                                        if ($getDistributorInfo['number3'] >= $level_infos['number3']) {
                                            $result[] = 9; //三级分销商
                                        }
                                        break;
                                    case 10:
                                        if ($getDistributorInfo['agentcount2'] >= $level_infos['number4']) {
                                            $result[] = 10; //团队人数
                                        }
                                        break;
                                    case 11:
                                        if ($getDistributorInfo['agentcount1'] >= $level_infos['number5']) {
                                            $result[] = 11; //客户人数
                                        }
                                        break;
                                    case 12:
                                        if ($low_number >= $level_infos['level_number']) {
                                            $result[] = 12; //指定等级人数
                                        }
                                        break;
                                    case 1:
                                        $offline_number = $level_infos['offline_number'];
                                        if ($getDistributorInfo['agentcount'] >= $offline_number) {
                                            $result[] = 1; //下线总人数
                                        }
                                        break;
                                    case 2:
                                        $order_money = $level_infos['order_money'];
                                        if ($getDistributorInfo['order_money'] >= $order_money) {
                                            $result[] = 2; //分销订单金额达
                                        }
                                        break;
                                    case 3:
                                        $order_number = $level_infos['order_number'];
                                        if ($getDistributorInfo['agentordercount'] >= $order_number) {
                                            $result[] = 3; //分销订单数达
                                        }
                                        break;

                                    case 4:
                                        $selforder_money = $level_infos['selforder_money'];
                                        if ($getDistributorInfo['selforder_money'] >= $selforder_money) {
                                            $result[] = 4; //自购订单金额
                                        }
                                        break;
                                    case 5:
                                        $selforder_number = $level_infos['selforder_number'];
                                        if ($getDistributorInfo['selforder_number'] >= $selforder_number) {
                                            $result[] = 5; //自购订单数
                                        }
                                        break;
                                    case 6:
                                        if ($goods_info) {
                                            $result[] = 6; //指定商品
                                        }
                                        break;
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 1) { //升级条件类型（满足所有勾选条件）
                                if (count($result) == count($conditions)) {
                                    $member = new VslMemberModel();
                                    $member->save(['distributor_level_id' => $level_infos['id'], 'up_level_time' => time(), 'down_up_level_time' => ''], ['uid' => $uid]);
                                    if ($distribution_pattern >= 1) {
                                        $ratio .= '一级返佣比例' . $level_infos['commission1'] . '%';
                                    }
                                    if ($distribution_pattern >= 2) {
                                        $ratio .= ',二级返佣比例' . $level_infos['commission2'] . '%';
                                    }
                                    if ($distribution_pattern >= 3) {
                                        $ratio .= ',三级返佣比例' . $level_infos['commission3'] . '%';
                                    }
                                    runhook("Notify", "sendCustomMessage", ['messageType' => 'upgrade_notice', 'uid' => $uid, 'present_grade' => $level_infos['level_name'], 'primary_grade' => $default_level_name, 'ratio' => $ratio, 'upgrade_time' => time()]); //升级
                                    if ($distributor['referee_id']) {
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($distributor['referee_id']);
                                    }
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 2) { //升级条件类型（满足勾选条件任意一个即可）
                                if (count($result) >= 1) {
                                    $member = new VslMemberModel();
                                    $member->save(['distributor_level_id' => $level_infos['id'], 'up_level_time' => time(), 'down_up_level_time' => ''], ['uid' => $uid]);
                                    if ($distribution_pattern >= 1) {
                                        $ratio .= '一级返佣比例' . $level_infos['commission1'] . '%';
                                    }
                                    if ($distribution_pattern >= 2) {
                                        $ratio .= ',二级返佣比例' . $level_infos['commission2'] . '%';
                                    }
                                    if ($distribution_pattern >= 3) {
                                        $ratio .= ',三级返佣比例' . $level_infos['commission3'] . '%';
                                    }
                                    runhook("Notify", "sendCustomMessage", ['messageType' => 'upgrade_notice', 'uid' => $uid, 'present_grade' => $level_infos['level_name'], 'primary_grade' => $default_level_name, 'ratio' => $ratio, 'upgrade_time' => time()]); //升级
                                    if ($distributor['referee_id']) {
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($distributor['referee_id']);
                                    }
                                }
                            }
                        }
                    }
                }
                $member_info = $member->getInfo(['uid' => $uid], '*');
                if ($distributor['distributor_level_id'] != $member_info['distributor_level_id']) {
                    if ($base_info['distribution_pattern'] >= 1) {
                        if ($member_info['referee_id']) {
                            $recommend1_info = $member->getInfo(['uid' => $member_info['referee_id']], '*');
                            if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                                $level_infos = $level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                                $recommend1 = $level_infos['recommend1']; //一级推荐奖
                                $recommend_point1 = $level_infos['recommend_point1']; //一级推荐积分
                                $recommend_beautiful_point1 = $level_infos['recommend_beautiful_point1']; //一级推荐美丽分
                                $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $member_info['website_id'],$recommend_beautiful_point1);
                            }
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 2) {
                        $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                        if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                            $level_infos = $level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend2 = $level_infos['recommend2']; //二级推荐奖
                            $recommend_point2 = $level_infos['recommend_point2']; //二级推荐积分
                            $recommend_beautiful_point2 = $level_infos['recommend_beautiful_point2']; //二级推荐美丽分
                            $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $member_info['website_id'],$recommend_beautiful_point2);
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 3) {
                        $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                        if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                            $level_infos = $level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend3 = $level_infos['recommend3']; //三级推荐奖
                            $recommend_point3 = $level_infos['recommend_point3']; //三级推荐积分
                            $recommend_beautiful_point3 = $level_infos['recommend_beautiful_point3']; //三级推荐美丽分
                            $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $member_info['website_id'],$recommend_beautiful_point3);
                        }
                    }
                }
            }
        }
        if ($base_info['distributor_grade'] == 2) { //未开启跳级
            if ($distributor['isdistributor'] == 2) {
                $getDistributorInfo = $this->getDistributorLowerInfo($uid); //当前分销商的详情信息
                $level_weight = $level->Query(['id' => $distributor['distributor_level_id']], 'weight'); //当前分销商的等级权重
                $level_weights = $level->Query(['weight' => ['>', implode(',', $level_weight)], 'website_id' => $distributor['website_id']], 'weight'); //当前分销商的等级权重的上级权重
                if ($level_weights) {
                    sort($level_weights);
                    //为防止跳级 1次终止

                    foreach ($level_weights as $k => $v) {
                        if ($k > 0) {
                            break;
                        }
                        $ratio = 0;
                        $level_infos = $level->getInfo(['weight' => $v, 'website_id' => $distributor['website_id']]); //当前等级信息

                        if ($level_infos && $level_infos['upgrade_level']) {
                            $low_number = $member->getCount(['distributor_level_id' => $level_infos['upgrade_level'], 'referee_id' => $uid, 'website_id' => $distributor['website_id']]); //该等级指定推荐等级人数
                        } else {
                            $low_number = 0;
                        }
                        //判断是否购买过指定商品
                        $goods_info = [];
                        if ($level_infos['goods_id']) {
                            $goods_id = $order_goods->Query(['goods_id' => ['IN', $level_infos['goods_id']], 'buyer_id' => $uid], 'order_id');
                            if ($goods_id) {
                                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4], '*');
                            }
                        }
                        if ($level_infos['upgradetype'] == 1) { //是否开启自动升级
                            $conditions = explode(',', $level_infos['upgradeconditions']);
                            $result = [];
                            foreach ($conditions as $k1 => $v1) {
                                switch ($v1) {
                                    case 7:
                                        if ($getDistributorInfo['number1'] >= $level_infos['number1']) {
                                            $result[] = 7; //一级分销商
                                        }
                                        break;
                                    case 8:
                                        if ($getDistributorInfo['number2'] >= $level_infos['number2']) {
                                            $result[] = 8; //二级分销商
                                        }
                                        break;
                                    case 9:
                                        if ($getDistributorInfo['number3'] >= $level_infos['number3']) {
                                            $result[] = 9; //三级分销商
                                        }
                                        break;
                                    case 10:
                                        if ($getDistributorInfo['agentcount2'] >= $level_infos['number4']) {
                                            $result[] = 10; //团队人数
                                        }
                                        break;
                                    case 11:
                                        if ($getDistributorInfo['agentcount1'] >= $level_infos['number5']) {
                                            $result[] = 11; //客户人数
                                        }
                                        break;
                                    case 12:
                                        if ($low_number >= $level_infos['level_number']) {
                                            $result[] = 12; //指定等级人数
                                        }
                                        break;
                                    case 1:
                                        $offline_number = $level_infos['offline_number'];
                                        if ($getDistributorInfo['agentcount'] >= $offline_number) {
                                            $result[] = 1; //下线总人数
                                        }
                                        break;
                                    case 2:
                                        $order_money = $level_infos['order_money'];
                                        if ($getDistributorInfo['order_money'] >= $order_money) {
                                            $result[] = 2; //分销订单金额达
                                        }
                                        break;
                                    case 3:
                                        $order_number = $level_infos['order_number'];
                                        if ($getDistributorInfo['agentordercount'] >= $order_number) {
                                            $result[] = 3; //分销订单数达
                                        }
                                        break;

                                    case 4:
                                        $selforder_money = $level_infos['selforder_money'];
                                        if ($getDistributorInfo['selforder_money'] >= $selforder_money) {
                                            $result[] = 4; //自购订单金额
                                        }
                                        break;
                                    case 5:
                                        $selforder_number = $level_infos['selforder_number'];
                                        if ($getDistributorInfo['selforder_number'] >= $selforder_number) {
                                            $result[] = 5; //自购订单数
                                        }
                                        break;
                                    case 6:
                                        if ($goods_info) {
                                            $result[] = 6; //指定商品
                                        }
                                        break;
                                }
                            }

                            if ($level_infos['upgrade_condition'] == 1) { //升级条件类型（满足所有勾选条件）
                                if (count($result) == count($conditions)) {
                                    $member = new VslMemberModel();
                                    $member->save(['distributor_level_id' => $level_infos['id'], 'up_level_time' => time(), 'down_up_level_time' => ''], ['uid' => $uid]);
                                    if ($distribution_pattern >= 1) {
                                        $ratio .= '一级返佣比例' . $level_infos['commission1'] . '%';
                                    }
                                    if ($distribution_pattern >= 2) {
                                        $ratio .= ',二级返佣比例' . $level_infos['commission2'] . '%';
                                    }
                                    if ($distribution_pattern >= 3) {
                                        $ratio .= ',三级返佣比例' . $level_infos['commission3'] . '%';
                                    }
                                    runhook("Notify", "sendCustomMessage", ['messageType' => 'upgrade_notice', 'uid' => $uid, 'present_grade' => $level_infos['level_name'], 'ratio' => $ratio, 'primary_grade' => $default_level_name, 'upgrade_time' => time()]); //升级
                                    if ($distributor['referee_id']) {
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($distributor['referee_id']);
                                    }
                                    break;
                                }
                            }
                            if ($level_infos['upgrade_condition'] == 2) { //升级条件类型（满足勾选条件任意一个即可）
                                if (count($result) >= 1) {
                                    $member = new VslMemberModel();
                                    $member->save(['distributor_level_id' => $level_infos['id'], 'up_level_time' => time(), 'down_up_level_time' => ''], ['uid' => $uid]);
                                    if ($distribution_pattern >= 1) {
                                        $ratio .= '一级返佣比例' . $level_infos['commission1'] . '%';
                                    }
                                    if ($distribution_pattern >= 2) {
                                        $ratio .= ',二级返佣比例' . $level_infos['commission2'] . '%';
                                    }
                                    if ($distribution_pattern >= 3) {
                                        $ratio .= ',三级返佣比例' . $level_infos['commission3'] . '%';
                                    }
                                    runhook("Notify", "sendCustomMessage", ['messageType' => 'upgrade_notice', 'uid' => $uid, 'present_grade' => $level_infos['level_name'], 'ratio' => $ratio, 'primary_grade' => $default_level_name, 'upgrade_time' => time()]); //升级
                                    if ($distributor['referee_id']) {
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($distributor['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($distributor['referee_id']);
                                    }
                                    break;
                                }
                            }
                        }
                    }
                }
                $member_info = $member->getInfo(['uid' => $uid], '*');
                if ($distributor['distributor_level_id'] != $member_info['distributor_level_id']) {
                    if ($base_info['distribution_pattern'] >= 1) {
                        if ($member_info['referee_id']) {
                            $recommend1_info = $member->getInfo(['uid' => $member_info['referee_id']], '*');
                            if ($recommend1_info['isdistributor'] == 2) {
                                $level_infos = $level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                                $recommend1 = $level_infos['recommend1']; //一级推荐奖
                                $recommend_point1 = $level_infos['recommend_point1']; //一级推荐积分
                                $recommend_beautiful_point1 = $level_infos['recommend_beautiful_point1']; //一级推荐美丽分
                                $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $member_info['website_id'],$recommend_beautiful_point1);
                            }
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 2) {
                        $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                        if ($recommend2_info['isdistributor'] == 2) {
                            $level_infos = $level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend2 = $level_infos['recommend2']; //二级推荐奖
                            $recommend_point2 = $level_infos['recommend_point2']; //二级推荐积分
                            $recommend_beautiful_point2 = $level_infos['recommend_beautiful_point2']; //二级推荐美丽分
                            $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $member_info['website_id'],$recommend_beautiful_point2);
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 3) {
                        $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                        if ($recommend3_info['isdistributor'] == 2) {
                            $level_infos = $level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend3 = $level_infos['recommend3']; //三级推荐奖
                            $recommend_point3 = $level_infos['recommend_point3']; //三级推荐积分
                            $recommend_beautiful_point3 = $level_infos['recommend_beautiful_point3']; //三级推荐美丽分
                            $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $member_info['website_id'],$recommend_beautiful_point3);
                        }
                    }
                }
            }
        }
    }
    /*
    * 订单完成和修改推荐人后分销商等级升级后获得推荐奖
    */
    public function addRecommed($uid, $recommend_uid, $recommend, $point, $website_id,$beautiful_point=0)
    {
        $account = new VslAccountModel();
        $member = new VslMemberAccountModel();
        $account_statistics = new VslDistributorAccountModel();
        $distributor_account = new VslDistributorAccountRecordsModel();
        //更新对应佣金账户和平台账户和会员账户
        $point_count = $member->getInfo(['uid' => $recommend_uid], '*'); //会员账户
        $count = $account_statistics->getInfo(['uid' => $recommend_uid], '*'); //佣金账户
        $account_count = $account->getInfo(['website_id' => $website_id], '*'); //平台账户
        $member_point = new VslMemberAccountRecordsModel();
        if($beautiful_point > 0){
            $this->ajustBeautifulPoint($recommend_uid,$uid,$beautiful_point,$website_id,29,'下级分销商等级升级，推荐人获得推荐奖积分增加');
        }
        //会员账户积分改变
        if ($point_count) {
            $account_data1 = array(
                'point' => $point_count['point'] + $point,
                'member_sum_point' => $point_count['member_sum_point'] + $point
            );
            $member->save($account_data1, ['uid' => $recommend_uid]);
        } else {
            $account_data2 = array(
                'point' => $point_count['point'] + $point,
                'member_sum_point' => $point_count['member_sum_point'] + $point,
                'uid' => $recommend_uid
            );
            $member->save($account_data2);
        }
        $data_point = array(
            'records_no' => getSerialNo(),
            'uid' => $recommend_uid,
            'account_type' => 1,
            'number'   => $point,
            'data_id' => $uid,
            'from_type' => 29,
            'text' => '下级分销商等级升级，推荐人获得推荐奖积分增加',
            'create_time' => time(),
            'website_id' => $website_id
        );
        $member_point->save($data_point); //添加会员积分流水
        //佣金账户佣金改变
        if ($count) {
            $account_data = array(
                'commission' => $count['commission'] + $recommend
            );
            $account_statistics->save($account_data, ['uid' => $recommend_uid]);
        } else {
            $account_data = array(
                'commission' => $count['commission'] + $recommend,
                'uid' => $recommend_uid
            );
            $account_statistics->save($account_data);
        }
        $records_no = 'LR' . time() . rand(111, 999);
        $data_records = array(
            'uid' => $recommend_uid,
            'records_no' => $records_no,
            'data_id' => $uid,
            'website_id' => $website_id,
            'commission' => $recommend,
            'text' => '下级分销商等级升级，推荐人获得推荐奖佣金增加',
            'create_time' => time(),
            'from_type' => 22,
        );
        $distributor_account->save($data_records);
        //平台账户佣金改变
        if ($account_count) {
            $commission_data = array(
                'commission' => $account_count['commission'] + $recommend,
            );
            $account->save($commission_data, ['website_id' => $website_id]);
            //平台账户流水表
            $shop = new ShopAccount();
            $shop->addAccountRecords(0, $recommend_uid, '会员分销等级升级，推荐奖', $recommend, 5, $uid, '会员分销等级升级，推荐奖，账户佣金增加', $website_id);
        }
    }
    /*
   * 订单完成获得积分
   */
    public function addMemberPoint($point, $uid, $data_id, $website_id, $member, $member_point)
    {
        $point_count = $member->getInfo(['uid' => $uid], '*'); //会员账户
        //会员账户积分改变
        if ($point_count) {
            $account_data1 = array(
                'point' => $point_count['point'] + $point,
                'member_sum_point' => $point_count['member_sum_point'] + $point
            );
            $member->save($account_data1, ['uid' => $uid]);
        } else {
            $account_data2 = array(
                'point' => $point_count['point'] + $point,
                'member_sum_point' => $point_count['member_sum_point'] + $point,
                'uid' => $uid
            );
            $member->save($account_data2);
        }
        $data_point = array(
            'records_no' => getSerialNo(),
            'uid' => $uid,
            'account_type' => 1,
            'number'   => $point,
            'data_id' => $data_id,
            'from_type' => 30,
            'text' => '分销订单完成，积分增加',
            'create_time' => time(),
            'website_id' => $website_id
        );
        $member_point->save($data_point); //添加会员积分流水
    }
    /*
     * 分销商自动降级
     */
    public function autoDownDistributorLevel($website_id)
    {
        $config = new AddonsConfigService();
        $level = new DistributorLevelModel();
        $base_info = $config->getAddonsConfig('distribution', $website_id, 0, 1);
        $distribution_pattern = $base_info['distribution_pattern'];
        $member = new VslMemberModel();
        $distributors = $member->Query(['website_id' => $website_id, 'isdistributor' => 2], '*');
        $default_weight = $level->getInfo(['website_id' => $website_id, 'is_default' => 1], 'weight')['weight']; //默认等级权重，也是最低等级
        foreach ($distributors as $k => $v) {
            $level_info = $level->getInfo(['id' => $v['distributor_level_id']], 'weight,level_name');
            $default_level_name = $level_info['level_name'];
            $level_weight =  $level_info['weight']; //分销商的等级权重

            if ($level_weight > $default_weight) {
                if ($base_info['distributor_grade'] == 1) { //开启跳降级
                    $level_weights = $level->Query(['weight' => ['<=', $level_weight], 'website_id' => $website_id], 'weight'); //分销商的等级权重的下级权重
                    rsort($level_weights);
                    foreach ($level_weights as $k1 => $v1) {
                        $level_infos = $level->getInfo(['weight' => $v1, 'website_id' => $website_id], '*');
                        $level_info_desc = $level->getFirstData(['weight' => ['<', $v1], 'website_id' => $website_id], 'weight desc'); //比当前等级的权重低的等级信息
                        if ($v1 != $default_weight) {
                            if ($level_infos['downgradetype'] == 1 && $level_infos['downgradeconditions']) { //是否开启自动降级并且有降级条件
                                $conditions = explode(',', $level_infos['downgradeconditions']);
                                $members_info = $member->getInfo(['uid' => $v['uid']], 'up_level_time,referee_id');
                                $result = [];
                                $reason = '';
                                $ratio = 0;
                                foreach ($conditions as $k2 => $v2) {
                                    switch ($v2) {
                                        case 1:
                                            $team_number_day = $level_infos['team_number_day'];
                                            $real_level_time = $members_info['up_level_time'] + $team_number_day * 24 * 3600;
                                            if ($real_level_time <= time()) {
                                                $getDistributorInfo1 = $this->getDistributorInfos($v['uid'], $team_number_day);
                                                $limit_number =  $getDistributorInfo1['agentordercount']; //限制时间段内团队分销订单数
                                                if ($limit_number <= $level_infos['team_number']) {
                                                    $result[] = 1;
                                                    $reason .= '团队分销订单数小于' . $level_infos['team_number'];
                                                }
                                            }
                                            break;
                                        case 2:
                                            $team_money_day = $level_infos['team_money_day'];
                                            $real_level_time = $members_info['up_level_time'] + $team_money_day * 24 * 3600;
                                            if ($real_level_time <= time()) {
                                                $getDistributorInfo2 = $this->getDistributorInfos($v['uid'], $team_money_day);
                                                $limit_money1 =  $getDistributorInfo2['order_money']; //限制时间段内团队分销订单金额
                                                if ($limit_money1 <= $level_infos['team_money']) {
                                                    $result[] = 2;
                                                    $reason .= ',团队分销订单金额小于' . $level_infos['team_money'];
                                                }
                                            }
                                            break;
                                        case 3:
                                            $self_money_day = $level_infos['self_money_day'];
                                            $real_level_time = $members_info['up_level_time'] + $self_money_day * 24 * 3600;
                                            if ($real_level_time <= time()) {
                                                $getDistributorInfo3 = $this->getDistributorInfos($v['uid'], $self_money_day);
                                                $limit_money2 = $getDistributorInfo3['selforder_money']; //限制时间段内自购分销订单金额
                                                if ($limit_money2 <= $level_infos['self_money']) {
                                                    $result[] = 3;
                                                    $reason .= ',自购分销订单金额小于' . $level_infos['self_money'];
                                                }
                                            }
                                            break;
                                    }
                                }
                                if ($level_infos['downgrade_condition'] == 1) { //降级条件类型（满足所有勾选条件）
                                    if (count($result) == count($conditions)) {
                                        $member = new VslMemberModel();
                                        $member->save(['distributor_level_id' => $level_info_desc['id'], 'down_level_time' => time(), 'down_up_level_time' => time()], ['uid' => $v['uid']]);
                                        if ($distribution_pattern >= 1) {
                                            $ratio .= '一级返佣比例' . $level_info_desc['commission1'] . '%';
                                        }
                                        if ($distribution_pattern >= 2) {
                                            $ratio .= ',二级返佣比例' . $level_info_desc['commission2'] . '%';
                                        }
                                        if ($distribution_pattern >= 3) {
                                            $ratio .= ',三级返佣比例' . $level_info_desc['commission3'] . '%';
                                        }
                                        runhook("Notify", "sendCustomMessage", ['messageType' => 'down_notice', 'uid' => $v['uid'], 'present_grade' => $level_info_desc['level_name'], 'primary_grade' => $default_level_name, 'ratio' => $ratio, 'down_reason' => $reason, 'down_time' => time()]); //降级
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($members_info['referee_id']);
                                    }
                                }
                                if ($level_infos['downgrade_condition'] == 2) { //降级条件类型（满足勾选条件任意一个即可）
                                    if (count($result) >= 1) {
                                        $member = new VslMemberModel();
                                        $member->save(['distributor_level_id' => $level_info_desc['id'], 'down_level_time' => time(), 'down_up_level_time' => time()], ['uid' => $v['uid']]);
                                        if ($distribution_pattern >= 1) {
                                            $ratio .= '一级返佣比例' . $level_info_desc['commission1'] . '%';
                                        }
                                        if ($distribution_pattern >= 2) {
                                            $ratio .= ',二级返佣比例' . $level_info_desc['commission2'] . '%';
                                        }
                                        if ($distribution_pattern >= 3) {
                                            $ratio .= ',三级返佣比例' . $level_info_desc['commission3'] . '%';
                                        }
                                        runhook("Notify", "sendCustomMessage", ['messageType' => 'down_notice', 'uid' => $v['uid'], 'present_grade' => $level_info_desc['level_name'], 'primary_grade' => $default_level_name, 'ratio' => $ratio, 'down_reason' => $reason, 'down_time' => time()]); //降级
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($members_info['referee_id']);
                                    }
                                }
                            }
                        }
                    }
                }
                if ($base_info['distributor_grade'] == 2) { //未开启跳降级
                    $level_weights = $level->Query(['weight' => ['<=', $level_weight], 'website_id' => $website_id], 'weight'); //分销商的等级权重的下级权重
                    rsort($level_weights);
                    foreach ($level_weights as $k1 => $v1) {
                        if ($k1 > 0) {
                            break;
                        }
                        $level_infos = $level->getInfo(['weight' => $v1, 'website_id' => $website_id], '*');
                        $level_info_desc = $level->getFirstData(['weight' => ['<', $v1], 'website_id' => $website_id], 'weight desc'); //比当前等级的权重低的等级信息
                        if ($v1 != $default_weight) {
                            if ($level_infos['downgradetype'] == 1 && $level_infos['downgradeconditions']) { //是否开启自动降级并且有降级条件
                                $conditions = explode(',', $level_infos['downgradeconditions']);
                                $members_info = $member->getInfo(['uid' => $v['uid']], 'up_level_time,referee_id');
                                $result = [];
                                $reason = '';
                                $ratio = 0;
                                foreach ($conditions as $k2 => $v2) {
                                    switch ($v2) {
                                        case 1:
                                            $team_number_day = $level_infos['team_number_day'];
                                            $real_level_time = $members_info['up_level_time'] + $team_number_day * 24 * 3600;

                                            if ($real_level_time <= time()) {
                                                $getDistributorInfo1 = $this->getDistributorInfos($v['uid'], $team_number_day);
                                                $limit_number =  $getDistributorInfo1['agentordercount']; //限制时间段内团队分销订单数

                                                if ($limit_number <= $level_infos['team_number']) {
                                                    $result[] = 1;
                                                    $reason .= '团队分销订单数小于' . $level_infos['team_number'];
                                                }
                                            }
                                            break;
                                        case 2:
                                            $team_money_day = $level_infos['team_money_day'];
                                            $real_level_time = $members_info['up_level_time'] + $team_money_day * 24 * 3600;
                                            if ($real_level_time <= time()) {
                                                $getDistributorInfo2 = $this->getDistributorInfos($v['uid'], $team_money_day);
                                                $limit_money1 =  $getDistributorInfo2['order_money']; //限制时间段内团队分销订单金额
                                                if ($limit_money1 <= $level_infos['team_money']) {
                                                    $result[] = 2;
                                                    $reason .= ',团队分销订单金额小于' . $level_infos['team_money'];
                                                }
                                            }
                                            break;
                                        case 3:
                                            $self_money_day = $level_infos['self_money_day'];
                                            $real_level_time = $members_info['up_level_time'] + $self_money_day * 24 * 3600;
                                            if ($real_level_time <= time()) {
                                                $getDistributorInfo3 = $this->getDistributorInfos($v['uid'], $self_money_day);
                                                $limit_money2 = $getDistributorInfo3['selforder_money']; //限制时间段内自购分销订单金额
                                                if ($limit_money2 <= $level_infos['self_money']) {
                                                    $result[] = 3;
                                                    $reason .= ',自购分销订单金额小于' . $level_infos['self_money'];
                                                }
                                            }
                                            break;
                                    }
                                }
                                if ($level_infos['downgrade_condition'] == 1) { //降级条件类型（满足所有勾选条件）
                                    if (count($result) == count($conditions)) {
                                        $member = new VslMemberModel();
                                        $member->save(['distributor_level_id' => $level_info_desc['id'], 'down_level_time' => time(), 'down_up_level_time' => time()], ['uid' => $v['uid']]);
                                        if ($distribution_pattern >= 1) {
                                            $ratio .= '一级返佣比例' . $level_info_desc['commission1'] . '%';
                                        }
                                        if ($distribution_pattern >= 2) {
                                            $ratio .= ',二级返佣比例' . $level_info_desc['commission2'] . '%';
                                        }
                                        if ($distribution_pattern >= 3) {
                                            $ratio .= ',三级返佣比例' . $level_info_desc['commission3'] . '%';
                                        }
                                        runhook("Notify", "sendCustomMessage", ['messageType' => 'down_notice', 'uid' => $v['uid'], 'present_grade' => $level_info_desc['level_name'], 'primary_grade' => $default_level_name, 'ratio' => $ratio, 'down_reason' => $reason, 'down_time' => time()]); //降级
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($members_info['referee_id']);
                                        break;
                                    }
                                }
                                if ($level_infos['downgrade_condition'] == 2) { //降级条件类型（满足勾选条件任意一个即可）
                                    if (count($result) >= 1) {
                                        $member = new VslMemberModel();
                                        $member->save(['distributor_level_id' => $level_info_desc['id'], 'down_level_time' => time(), 'down_up_level_time' => time()], ['uid' => $v['uid']]);
                                        if ($distribution_pattern >= 1) {
                                            $ratio .= '一级返佣比例' . $level_info_desc['commission1'] . '%';
                                        }
                                        if ($distribution_pattern >= 2) {
                                            $ratio .= ',二级返佣比例' . $level_info_desc['commission2'] . '%';
                                        }
                                        if ($distribution_pattern >= 3) {
                                            $ratio .= ',三级返佣比例' . $level_info_desc['commission3'] . '%';
                                        }
                                        runhook("Notify", "sendCustomMessage", ['messageType' => 'down_notice', 'uid' => $v['uid'], 'present_grade' => $level_info_desc['level_name'], 'primary_grade' => $default_level_name, 'ratio' => $ratio, 'down_reason' => $reason, 'down_time' => time()]); //降级
                                        if (getAddons('globalbonus', $this->website_id)) {
                                            $global = new GlobalBonus();
                                            $global->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('areabonus', $this->website_id)) {
                                            $area = new AreaBonus();
                                            $area->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        if (getAddons('teambonus', $this->website_id)) {
                                            $team = new TeamBonus();
                                            $team->updateAgentLevelInfo($members_info['referee_id']);
                                        }
                                        $this->updateDistributorLevelInfo($members_info['referee_id']);
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
     * 成为分销商的条件
     */
    public function becomeDistributor($uid, $order_id)
    {
        $member = new VslMemberModel();
        $order_money_model = new OrderAccount();
        $distributor = $member->getInfo(['uid' => $uid], '*');
        $config = new AddonsConfigService();
        $base_info = $config->getAddonsConfig("distribution", $distributor['website_id'], 0, 1);
        $distribution_pattern = $base_info['distribution_pattern'];
        $order = new VslOrderModel();
        //判断是否购买过指定商品
        $goods_info = [];
        if ($base_info['goods_id']) {
            $order_goods = new VslOrderGoodsModel();
            $goods_id = $order_goods->Query(['goods_id' => ['IN', $base_info['goods_id']], 'buyer_id' => $uid], 'order_id');
            if ($goods_id) {
                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4], '*');
            }
        }
        $distributor_level = new DistributorLevelModel();
        $level_info = $distributor_level->getInfo(['website_id' => $distributor['website_id'], 'is_default' => 1], '*');
        $level_id = $level_info['id'];
        if ($distributor['isdistributor'] != 2 && $base_info['distributorcondition'] != -1 && $base_info['distributor_conditions']) { //判断是否是分销商
            $result = [];
            $ratio = 0;
            $conditions = explode(',', $base_info['distributor_conditions']);
            foreach ($conditions as $k => $v) {
                switch ($v) {
                    case 2:
                        $order_money = $order_money_model->getMemberSaleMoney(['order_status' => 4, 'buyer_id' => $uid]);
                        if ($order_money >= $base_info['pay_money']) {
                            $result[] = 2; //满足消费金额
                        }
                        break;
                    case 3:
                        $order_number = $order_money_model->getShopSaleNumSum(['order_status' => 4, 'buyer_id' => $uid]);
                        if ($order_number >= $base_info['order_number']) {
                            $result[] = 3; //满足订单数
                        }
                        break;
                    case 4:
                        $order_status = $base_info['order_status'];

                        if ($order_status == 1) { //购买商品，并完成订单
                            $orders = $order->getInfo(['buyer_id' => $uid, 'order_status' => 4], '*');
                            if ($orders) {
                                $result[] = 4; //满足订单完成
                            }
                        } else if ($order_status == 2) { //购买商品，并完成付款（售后不会取消身份）
                            $orders = $order->getInfo(['buyer_id' => $uid, 'order_status' => ['between', [1, 4]]], '*');
                            if ($orders) {
                                $result[] = 4; //满足订单支付完成
                            }
                        }

                        break;
                    case 5:
                        if ($goods_info) {
                            $result[] = 5; //满足购买指定商品
                        }
                        break;
                }
            }
            $extend_code = $this->create_extend();
            if ($base_info['distributor_condition'] == 1) { //满足所有勾选条件
                if (count($conditions) == count($result)) {
                    $member->save(['isdistributor' => 2, 'distributor_level_id' => $level_id, 'extend_code' => $extend_code, "apply_distributor_time" => time(), 'become_distributor_time' => time()], ['uid' => $uid]);
                    $referee_id = $member->getInfo(['uid' => $uid], 'referee_id')['referee_id'];
                    if ($referee_id) {
                        $this->updateDistributorLevelInfo($referee_id);
                    }
                    if ($distribution_pattern >= 1) {
                        $ratio .= '一级返佣比例' . $level_info['commission1'] . '%';
                    }
                    if ($distribution_pattern >= 2) {
                        $ratio .= ',二级返佣比例' . $level_info['commission2'] . '%';
                    }
                    if ($distribution_pattern >= 3) {
                        $ratio .= ',三级返佣比例' . $level_info['commission3'] . '%';
                    }
                    if ($base_info['distribution_pattern'] >= 1) {
                        if ($distributor['referee_id']) {
                            $recommend1_info = $member->getInfo(['uid' => $distributor['referee_id']], '*');
                            if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                                $level_info1 = $distributor_level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                                $recommend1 = $level_info1['recommend1']; //一级推荐奖
                                $recommend_point1 = $level_info1['recommend_point1']; //一级推荐积分
                                $recommend_beautiful_point1 = $level_info1['recommend_beautiful_point1']; //一级推荐美丽分
                                $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $distributor['website_id'],$recommend_beautiful_point1);
                            }
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 2) {
                        $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                        if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                            $level_info2 = $distributor_level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend2 = $level_info2['recommend2']; //二级推荐奖
                            $recommend_point2 = $level_info2['recommend_point2']; //二级推荐积分
                            $recommend_beautiful_point2 = $level_info2['recommend_beautiful_point2']; //二级推荐美丽分
                            $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $distributor['website_id'],$recommend_beautiful_point2);
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 3) {
                        $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                        if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                            $level_info3 = $distributor_level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend3 = $level_info3['recommend3']; //三级推荐奖
                            $recommend_point3 = $level_info3['recommend_point3']; //三级推荐积分
                            $recommend_beautiful_point3 = $level_info3['recommend_beautiful_point3']; //三级推荐美丽分
                            $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $distributor['website_id'],$recommend_beautiful_point3);
                        }
                    }
                    runhook("Notify", "sendCustomMessage", ["messageType" => "become_distributor", "uid" => $uid, "become_time" => time(), 'ratio' => $ratio, 'level_name' => $level_info['level_name']]); //用户成为分销商提醒
                    runhook("Notify", "successfulDistributorByTemplate", ["uid" => $uid, "website_id" => $distributor['website_id']]); //用户成为分销商提醒
                }
            }
            if ($base_info['distributor_condition'] == 2) { //满足所有勾选条件之一
                if (count($result) >= 1) {
                    $member->save(['isdistributor' => 2, 'distributor_level_id' => $level_id, 'extend_code' => $extend_code, "apply_distributor_time" => time(), 'become_distributor_time' => time()], ['uid' => $uid]);
                    $referee_id = $member->getInfo(['uid' => $uid], 'referee_id')['referee_id'];
                    if ($referee_id) {
                        $this->updateDistributorLevelInfo($referee_id);
                    }
                    if ($distribution_pattern >= 1) {
                        $ratio .= '一级返佣比例' . $level_info['commission1'] . '%';
                    }
                    if ($distribution_pattern >= 2) {
                        $ratio .= ',二级返佣比例' . $level_info['commission2'] . '%';
                    }
                    if ($distribution_pattern >= 3) {
                        $ratio .= ',三级返佣比例' . $level_info['commission3'] . '%';
                    }
                    if ($base_info['distribution_pattern'] >= 1) {
                        if ($distributor['referee_id']) {
                            $recommend1_info = $member->getInfo(['uid' => $distributor['referee_id']], '*');
                            if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                                $level_info1 = $distributor_level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                                $recommend1 = $level_info1['recommend1']; //一级推荐奖
                                $recommend_point1 = $level_info1['recommend_point1']; //一级推荐积分
                                $recommend_beautiful_point1 = $level_info1['recommend_beautiful_point1']; //一级推荐美丽分
                                $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $distributor['website_id'],$recommend_beautiful_point1);
                            }
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 2) {
                        $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                        if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                            $level_info2 = $distributor_level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend2 = $level_info2['recommend2']; //二级推荐奖
                            $recommend_point2 = $level_info2['recommend_point2']; //二级推荐积分
                            $recommend_beautiful_point2 = $level_info2['recommend_beautiful_point2']; //二级推荐美丽分
                            $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $distributor['website_id'],$recommend_beautiful_point2);
                        }
                    }
                    if ($base_info['distribution_pattern'] >= 3) {
                        $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                        if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                            $level_info3 = $distributor_level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend3 = $level_info3['recommend3']; //三级推荐奖
                            $recommend_point3 = $level_info3['recommend_point3']; //三级推荐积分
                            $recommend_beautiful_point3 = $level_info3['recommend_beautiful_point3']; //三级推荐美丽分
                            $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $distributor['website_id'],$recommend_beautiful_point3);
                        }
                    }
                    runhook("Notify", "sendCustomMessage", ["messageType" => "become_distributor", "uid" => $uid, "become_time" => time(), 'ratio' => $ratio, 'level_name' => $level_info['level_name']]); //用户成为分销商提醒
                    runhook("Notify", "successfulDistributorByTemplate", ["uid" => $uid, "website_id" => $distributor['website_id']]); //用户成为分销商提醒
                }
            }
        }
    }
    /*
     * 成为分销商的下线
     */
    public function becomeLower($uid)
    {
        $member = new VslMemberModel();
        $distributor = $member->getInfo(['uid' => $uid], '*');

        $info = $this->getDistributionSite($distributor['website_id']);
        //变更  购买商品，并付款 可变更上下级  && $distributor['referee_id']==null
        if ($info['is_use'] == 1 && $info['lower_condition'] == 2 && $distributor['default_referee_id']) {

            if ($distributor['default_referee_id'] != $uid) {
                $lower_id = $member->Query(['referee_id' => $uid], '*');
                if ($lower_id && in_array($distributor['default_referee_id'], $lower_id)) {
                    return 1;
                }

                runhook("Notify", "sendCustomMessage", ['messageType' => 'new_offline', "uid" => $uid, "add_time" => time(), 'referee_id' => $distributor['default_referee_id']]); //成为下线通知
                $member->save(['referee_id' => $distributor['default_referee_id'], 'default_referee_id' => null], ['uid' => $uid]);
                $this->addRefereeLog($uid, $distributor['default_referee_id'], $distributor['website_id'], $distributor['shop_id'], 0, 0, 3);
                $this->updateDistributorLevelInfo($distributor['default_referee_id']);
                if (getAddons('globalbonus', $distributor['website_id'])) {
                    $global = new GlobalBonus();
                    $global->updateAgentLevelInfo($distributor['default_referee_id']);
                    $global->becomeAgent($distributor['default_referee_id']);
                }
                if (getAddons('areabonus', $distributor['website_id'])) {
                    $area = new AreaBonus();
                    $area->updateAgentLevelInfo($distributor['default_referee_id']);
                }
                if (getAddons('teambonus', $distributor['website_id'])) {
                    $team = new TeamBonus();
                    $team->updateAgentLevelInfo($distributor['default_referee_id']);
                    $team->becomeAgent($distributor['default_referee_id']);
                }
            }
        }
    }
    /**
     * 提现详情
     */
    public function withdrawDetail($page_index = 1, $page_size = 0, $where = '', $order = '')
    {
        $list = $this->getCommissionWithdrawList($page_index = 1, $page_size = 0, $where, $order);
        return $list;
    }
    /**
     * 佣金提现设置
     */
    public function getCommissionWithdrawConfig($uid)
    {
        $config = new ConfigService();
        $account = new VslDistributorAccountModel();
        $account_info = $account->getInfo(['uid'=>$uid],'*');
        $list = $config->getConfig(0,"SETTLEMENT",$account_info['website_id'], 1);
        $config_set = $config->getConfig(0,"WITHDRAW_BALANCE",$account_info['website_id']);
        if($account_info){
            $list['withdraw_money'] = $account_info['commission'] - $account_info['withdrawals'];
            $list['commission'] = $account_info['commission'];
            $list['withdrawals'] = $account_info['withdrawals'];
            $list['freezing_commission'] = $account_info['freezing_commission'];
            $list['tax'] = $account_info['tax'];
        }
        if ($list['withdrawals_type'] && $config_set['is_use']) {
            $list['withdrawals_type'] = explode(',', $list['withdrawals_type']);
            //强行把4余额提现 5银行卡手动提现类型转换
            if (in_array(4, $list['withdrawals_type']) && in_array(5, $list['withdrawals_type'])) {
            } elseif (in_array(4, $list['withdrawals_type']) || in_array(5, $list['withdrawals_type'])) {
                foreach ($list['withdrawals_type'] as $key => $value) {
                    if ($value == 4) {
                        $list['withdrawals_type'][$key] = 5;
                    } elseif ($value == 5) {
                        $list['withdrawals_type'][$key] = 4;
                    }
                }
            }


            $info = $config->getConfig(0, 'WPAY', $account_info['website_id']);
            $wx_tw = $info['value']['wx_tw'];
            $user = new UserModel();
            $user_info = $user->getInfo(['uid' => $uid], 'payment_password,wx_openid,mp_open_id');
            if ($wx_tw == 0 || $info['is_use'] == 0) {
                $list['withdrawals_type'] = array_merge(array_diff($list['withdrawals_type'], [2]));
            } elseif (empty($user_info['wx_openid']) && empty($user_info['mp_open_id'])) {
                $list['withdrawals_type'] = array_merge(array_diff($list['withdrawals_type'], [2]));
            }
            $info1 = $config->getConfig(0, 'TLPAY', $this->website_id);
            $tl_tw = $info1['value']['tl_tw'];
            if ($tl_tw == 0 || $info1['is_use'] == 0) {
                $list['withdrawals_type'] = array_merge(array_diff($list['withdrawals_type'], [1]));
            }
            $info2 = $config->getConfig(0, 'ALIPAY', $account_info['website_id']);
            if ($info2['is_use'] == 0) {
                $list['withdrawals_type'] = array_merge(array_diff($list['withdrawals_type'], [3]));
            }
        } else {
            $list['withdrawals_type'] = [];
        }
        $withdraw_account = new VslDistributorCommissionWithdrawModel();
        $list['make_withdraw'] = abs(array_sum($withdraw_account->Query(['uid' => $uid, 'status' => 2], 'cash'))); //待打款
        $list['apply_withdraw'] = abs(array_sum($withdraw_account->Query(['uid' => $uid, 'status' => 1], 'cash'))); //审核中
        $list['account_list'] = $this->getMemberBankAccount($is_default = 0, $uid);
        return $list;
    }
    /**
     * 佣金提现账户类型
     */
    public function getMemberBankAccount($is_default = 0, $uid)
    {
        $member_bank_account = new VslMemberBankAccountModel();
        $bank_account_list = '';
        if (!empty($uid)) {
            if (empty($is_default)) {
                $bank_account_list = $member_bank_account->getQuery([
                    'uid' => $uid
                ], '*', '');
            } else {
                $bank_account_list = $member_bank_account->getQuery([
                    'uid' => $uid,
                    'is_default' => 1
                ], '*', '');
            }
        }
        return $bank_account_list;
    }
    /**
     * 佣金提现
     */
    public function addDistributorCommissionWithdraw($withdraw_no, $uid, $account_id, $cash)
    {
        // 平台的提现设置
        $fail = 0;
        $member = new VslMemberModel();
        $member_info = $member->getInfo(['uid' => $uid], '*');
        $website_id = $this->website_id;
        $real_name = $member_info['real_name'];
        $config = new ConfigService();
        $config_set = $config->getConfig(0,"WITHDRAW_BALANCE",$member_info['website_id']);
        $commission_withdraw_set = $config->getConfig(0,'SETTLEMENT',$member_info['website_id'], 1);
//        var_dump($commission_withdraw_set);die;
        // 判断是否提现设置是否为空 是否启用
        if (empty($config_set) || $config_set['is_use'] == 0) {
            return USER_WITHDRAW_NO_USE;
        }
        // 最小提现额判断
        if ($cash < $commission_withdraw_set["withdrawals_min"]) {
            return USER_WITHDRAW_MIN;
        }
        // 判断当前分销商的可提现佣金
        $account = new VslDistributorAccountModel();
        $commission_info = $account->getInfo(['uid' => $uid], '*');
        $commission = $commission_info['commission'];
        if ($commission <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        if ($commission < $cash || $cash <= 0) {
            return ORDER_CREATE_LOW_PLATFORM_MONEY;
        }
        $member_account = new VslMemberBankAccountModel();
        if ($account_id == -1) {
            //提现到账户余额
            $account_number = -1;
            $type = 4;
        } elseif ($account_id == -2) {
            //提现到微信
            $account_number = $member_info['mobile'];
            $type = 2;
        } else {
            // 获取 提现账户
            $account_info = $member_account->getInfo([
                'id' => $account_id
            ], '*');
            $account_number = $account_info['account_number'];
            $type = $account_info['type'];
            if ($type == 4) { //会员提现账户的类型4相当于佣金提现里面的类型5
                $type = 5;
            }
        }
        if ($type == 1 || $type == 5) {
            if ($commission_withdraw_set['withdraw_message']) {
                $withdraw_message = explode(',', $commission_withdraw_set['withdraw_message']);
                if (in_array(5, $withdraw_message)) {
                    $type = 5;
                }
            }
        }

        // 添加佣金提现记录
        $commission_withdraw = new VslDistributorCommissionWithdrawModel();
        try {
            // 查询提现审核方式
            if ($commission_withdraw_set['withdrawals_check'] == 2 && abs($cash) <= $commission_withdraw_set['withdrawals_cash']) { //关闭免审核，提现金额小于免审核区间
                $is_examine = 1;
            } else {
                $is_examine =  $commission_withdraw_set['withdrawals_check'];
            }
            $tax = 0;
            //佣金个人所得税
            if ($commission_withdraw_set['poundage']) {
                $tax = twoDecimal(abs($cash) * $commission_withdraw_set['poundage'] / 100); //佣金个人所得税
                if ($commission_withdraw_set['withdrawals_end'] && $commission_withdraw_set['withdrawals_begin']) {
                    if (abs($cash) <= $commission_withdraw_set['withdrawals_end'] && abs($cash) >= $commission_withdraw_set['withdrawals_begin']) {
                        $tax = 0; //免打税区间
                    }
                }
            }
            if ($cash + $tax <= $commission) {
                $income_tax = $cash;
            } elseif ($cash - $tax >= 0) {
                $income_tax = $cash - $tax;
            } else {
                return ORDER_CREATE_LOW_PLATFORM_MONEY;
            }
            // 查询提现打款方式
//            $make_money = $commission_withdraw_set['make_money'];
            $make_money = $config_set['value']['make_money'];
//            var_dump($config_set);
//            var_dump($make_money);die;

            if ($is_examine == 1 && $make_money == 1) { //自动审核自动打款
                if ($account_id == -1) {
                    $data = array(
                        'withdraw_no' => $withdraw_no,
                        'uid' => $uid,
                        'account_number' => -1,
                        'realname' => $real_name,
                        'payment_date' => time(),
                        'type'   => $type,
                        'cash' => (-1) * $cash,
                        'tax' => (-1) * $tax,
                        'income_tax' => $income_tax, //实际到账金额
                        'ask_for_date' => time(),
                        'status' => 3, //直接提现到账户余额
                        'website_id' => $website_id
                    );
                } else {
                    $data = array(
                        'withdraw_no' => $withdraw_no,
                        'uid' => $uid,
                        'account_number' => $account_number,
                        'income_tax' => $income_tax, //实际到账金额
                        'realname' => $real_name,
                        'type' => $type,
                        'cash' => (-1) * $cash,
                        'tax' => (-1) * $tax,
                        'ask_for_date' => time(),
                        'status' => 2, //审核通过
                        'website_id' => $website_id
                    );
                }
                $res = $commission_withdraw->save($data);
                if ($res) {
                    if ($account_id == -1) {
                        // 更新佣金账户情况
                        $data_commission['uid'] = $data['uid'];
                        $data_commission['commission'] = $data['income_tax']; //扣除佣金总额（包括手续费）
                        $data_commission['cash'] = $data['cash'];
                        $data_commission['tax'] = $tax;
                        $data_commission['income_tax'] = $data['income_tax'];
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['website_id'] = $data['website_id'];
                        $data_commission['text'] = '提现到账户余额成功';
                        $this->updateAccountWithdraw(15, $data_commission);
                        $this->addCommissionWithdraw($data_commission); //审核通过直接提现到账户余额
                        runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $data['uid'], 'withdraw_money' => $data['cash'], "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]); //提现成功
                    } elseif ($type == 1 || $type == 2 || $type == 3 || $type == 5) {
                        $withdraw_info = $commission_withdraw->getInfo(['id' => $res], '*');
                        if ($type == 2) {
                            $params['shop_id'] = 0;
                            $params['takeoutmoney'] = abs($withdraw_info['cash']);
                            $params['uid'] = $uid;
                            $params['website_id'] = $this->website_id;
                            $data_commission['data_id'] = $data['withdraw_no'];
                            $data_commission['status'] = 5;
                            $data_commission['uid'] = $withdraw_info['uid'];
                            $data_commission['website_id'] = $withdraw_info['website_id'];
                            $data_commission['commission'] = $withdraw_info['income_tax']; //扣除佣金总额（包括手续费）
                            $data_commission['cash'] = $withdraw_info['cash'];
                            $data_commission['tax'] = $tax;
                            $data_commission['text'] = '提现到微信待打款';
                            $this->updateAccountWithdraw(5, $data_commission);
                            $user_info = new UserModel();
                            $wx_openid = $user_info->getInfo(['uid' => $withdraw_info['uid']], 'wx_openid')['wx_openid'];
                            $weixin_pay = new WeiXinPay();
                            $retval = $weixin_pay->EnterprisePayment($wx_openid, $withdraw_info['withdraw_no'], '', abs($withdraw_info['income_tax']), '佣金微信提现', $this->website_id);
                            if ($retval['is_success'] == 1) { //自动打款成功
                                runhook('Notify', 'withdrawalSuccessBySms', $params);
                                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $withdraw_info['uid'], 'withdraw_money' => $withdraw_info['cash'], "withdraw_type" => '提现到微信', 'withdraw_time' => time()]); //提现成功
                                $data_commission['status'] = 10;
                                $data_commission['text'] = '提现成功到微信'; //微信
                                $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金微信提现，打款成功。");
                                $commission_withdraw->where(array("id" => $res))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                            } else { //自动打款失败
                                $data_commission['status'] = -10;
                                $data_commission['msg'] = $retval['msg'];
                                $data_commission['text'] = '提现到微信打款失败，等待商家重新打款'; //微信
                                $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金微信提现，打款失败。");
                                $commission_withdraw->where(array("id" => $res))->update(array("status" => 5, "memo" => '打款失败'));
                                $fail = 1;
                            }
                        }
                        if ($type == 3) {
                            $data_commission['data_id'] = $withdraw_info['withdraw_no'];
                            $data_commission['status'] = 7;
                            $data_commission['uid'] = $withdraw_info['uid'];
                            $data_commission['website_id'] = $withdraw_info['website_id'];
                            $data_commission['commission'] = $withdraw_info['income_tax'];
                            $data_commission['cash'] = $withdraw_info['cash'];
                            $data_commission['text'] = '提现到支付宝待打款';
                            $data_commission['tax'] = $tax;
                            $this->updateAccountWithdraw(7, $data_commission);
                            $alipay_pay = new AliPay();
                            $retval = $alipay_pay->aliPayTransferNew($withdraw_info['withdraw_no'], $withdraw_info['account_number'], abs($withdraw_info['income_tax']), $withdraw_info['realname']);
                            if ($retval['is_success'] == 1) {
                                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $withdraw_info['uid'], 'withdraw_money' => $withdraw_info['cash'], "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]); //提现成功
                                runhook('Notify', 'withdrawalSuccessBySms', $data_commission);
                                $data_commission['status'] = 11;
                                $data_commission['text'] = '提现成功到支付宝'; //支付宝
                                $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金支付宝提现，打款成功。");
                                $commission_withdraw->where(array("id" => $res))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                            } else { //自动打款失败
                                $data_commission['status'] = -11;
                                $data_commission['msg'] = $retval['msg'];
                                $data_commission['text'] = '提现到支付宝打款失败，等待商家重新打款'; //支付宝
                                $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金支付宝提现，打款失败。");
                                $commission_withdraw->where(array("id" => $res))->update(array("status" => 5, "memo" => '打款失败'));
                                $fail = 1;
                            }
                        }
                        if ($type == 1) {
                            $data_commission['data_id'] = $withdraw_info['withdraw_no'];
                            $data_commission['status'] = 8;
                            $data_commission['uid'] = $withdraw_info['uid'];
                            $data_commission['website_id'] = $withdraw_info['website_id'];
                            $data_commission['commission'] = $withdraw_info['income_tax'];
                            $data_commission['cash'] = $withdraw_info['cash'];
                            $data_commission['text'] = '提现到银行卡待打款';
                            $data_commission['tax'] = $tax;
                            $this->updateAccountWithdraw(8, $data_commission);
                            $bank = new VslMemberBankAccountModel();
                            $bank_id = $bank->getInfo(['account_number' => $withdraw_info['account_number'], 'uid' => $withdraw_info['uid']], 'id')['id'];
                            $tlpay_pay = new tlPay();
                            $retval = $tlpay_pay->tlWithdraw($withdraw_info['withdraw_no'], $withdraw_info['uid'], $bank_id, abs($withdraw_info['income_tax']));
                            if ($retval['is_success'] == 1) {
                                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $withdraw_info['uid'], 'withdraw_money' => $withdraw_info['cash'], "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]); //提现成功
                                runhook('Notify', 'withdrawalSuccessBySms', $data_commission);
                                $data_commission['status'] = 9;
                                $data_commission['text'] = '提现成功到银行卡'; //银行卡
                                $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金银行卡提现，打款成功。");
                                $commission_withdraw->where(array("id" => $res))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                            } else { //自动打款失败
                                $data_commission['status'] = -9;
                                $data_commission['msg'] = $retval['msg'];
                                $data_commission['text'] = '提现到银行卡打款失败，等待商家重新打款'; //支付宝
                                $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金银行卡提现，打款失败。");
                                $commission_withdraw->where(array("id" => $res))->update(array("status" => 5, "memo" => '打款失败'));
                                $fail = 1;
                            }
                        }
                        if ($type == 5) {
                            $data_commission['data_id'] = $withdraw_info['withdraw_no'];
                            $data_commission['status'] = 8;
                            $data_commission['uid'] = $withdraw_info['uid'];
                            $data_commission['website_id'] = $withdraw_info['website_id'];
                            $data_commission['commission'] = $withdraw_info['income_tax'];
                            $data_commission['cash'] = $withdraw_info['cash'];
                            $data_commission['text'] = '提现到银行卡待打款';
                            $data_commission['tax'] = $tax;
                            $this->updateAccountWithdraw(8, $data_commission);
                            runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $withdraw_info['uid'], 'withdraw_money' => $withdraw_info['cash'], "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]); //提现成功
                            runhook('Notify', 'withdrawalSuccessBySms', $data_commission);
                            $data_commission['status'] = 9;
                            $data_commission['text'] = '提现成功到银行卡'; //银行卡
                            $this->addAccountWithdrawUserRecords($data_commission, 2, $res, "佣金银行卡提现，打款成功。");
                            $commission_withdraw->where(array("id" => $res))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                        }
                    }
                }
            }
            if ($is_examine == 2 && $make_money == 1) { //手动审核自动打款
                $data = array(
                    'withdraw_no' => $withdraw_no,
                    'uid' => $uid,
                    'account_number' => $account_number,
                    'realname' => $real_name,
                    'income_tax' => $income_tax, //税后金额
                    'type'   => $type,
                    'cash' => (-1) * $cash,
                    'tax' => (-1) * $tax,
                    'ask_for_date' => time(),
                    'status' => 1, //提现审核中
                    'website_id' => $website_id
                );
                $rel = $commission_withdraw->save($data);
                if ($rel > 0) {
                    if ($account_id == -1) {
                        $data_commission = array(
                            'status' => 6,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到账户余额待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 1 || $type == 5) {
                        $data_commission = array(
                            'status' => 12,
                            'cash' => $cash,
                            'uid' => $uid,
                            'commission' => $income_tax,
                            'text' => '提现到银行卡待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 2) {
                        $data_commission = array(
                            'status' => 13,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到微信待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['tax'] = $tax;
                        $data_commission['data_id'] = $data['withdraw_no'];
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到微信', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 3) {
                        $data_commission = array(
                            'status' => 14,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到支付宝待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['tax'] = $tax;
                        $data_commission['data_id'] = $data['withdraw_no'];
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                }
            }
            if ($is_examine == 1 && $make_money == 2) { //自动审核待打款
                $data = array(
                    'withdraw_no' => $withdraw_no,
                    'uid' => $uid,
                    'account_number' => $account_number,
                    'realname' => $real_name,
                    'income_tax' => $income_tax, //税后金额
                    'type' => $type,
                    'cash' => (-1) * $cash,
                    'tax' => (-1) * $tax,
                    'ask_for_date' => time(),
                    'status' => 2, //审核通过，待打款
                    'website_id' => $website_id
                );
                $rel = $commission_withdraw->save($data);
                if ($rel) {
                    if ($type == 4) {
                        $data_commission = array(
                            'status' => 15,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到账户余额待打款', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 2) {
                        $data_commission = array(
                            'status' => 5,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到微信待打款', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到微信', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 3) {
                        $data_commission = array(
                            'status' => 7,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到支付宝待打款', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]);
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 1 || $type == 5) {
                        $data_commission = array(
                            'status' => 8,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到银行卡待打款', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                }
            }
            if ($is_examine == 2 && $make_money == 2) { //手动审核手动打款
                $data = array(
                    'withdraw_no' => $withdraw_no,
                    'uid' => $uid,
                    'account_number' => $account_number,
                    'realname' => $real_name,
                    'income_tax' => $income_tax, //税后金额
                    'type'   => $type,
                    'tax' => (-1) * $tax,
                    'cash' => (-1) * $cash,
                    'ask_for_date' => time(),
                    'status' => 1, //提现审核中
                    'website_id' => $website_id
                );
                $rel = $commission_withdraw->save($data);
                if ($rel) {
                    if ($account_id == -1) {
                        $data_commission = array(
                            'status' => 6,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到账户余额待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 1 || $type == 5) {
                        $data_commission = array(
                            'status' => 12,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到银行卡待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 2) {
                        $data_commission = array(
                            'status' => 13,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到微信待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到微信', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                    if ($type == 3) {
                        $data_commission = array(
                            'status' => 14,
                            'uid' => $uid,
                            'cash' => $cash,
                            'commission' => $income_tax,
                            'text' => '提现到支付宝待审核', //提现审核中
                            'website_id' => $website_id
                        );
                        $data_commission['data_id'] = $data['withdraw_no'];
                        $data_commission['tax'] = $tax;
                        runhook("Notify", "sendCustomMessage", ["messageType" => "application_cash", "uid" => $data_commission['uid'], 'withdraw_money' => $cash, "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]);
                        $this->addAccountWithdrawUserRecords($data_commission, 2, $rel, $data_commission['text']);
                    }
                }
            }
            $commission_withdraw->commit();
            if ($fail == 1) {
                return -9000;
            }
            return $commission_withdraw->id;
        } catch (\Exception $e) {
            $commission_withdraw->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 佣金成功提现到账户余额
     */
    public function addCommissionWithdraw($data)
    {
        $commission_withdraw = new VslMemberAccountRecordsModel();
        $member_account = new VslMemberAccountModel();
        $account_info = $member_account->getInfo(['uid' => $data['uid']], '*');
        try {
            $data1 = array(
                'records_no' => getSerialNo(),
                'uid' => $data['uid'],
                'account_type' => 2,
                'number'   => $data['income_tax'], //实际到账金额
                'data_id' => $data['data_id'],
                'from_type' => 15,
                'balance' => abs($data['income_tax']) + $account_info['balance'],
                'text' => '佣金成功提现到余额',
                'create_time' => time(),
                'website_id' => $data['website_id']
            );
            $res1 = $commission_withdraw->save($data1); //添加会员流水
            $data_commission = array(
                'uid' => $data['uid'],
                'commission' => $data['income_tax'],
                'data_id' => $data['data_id'],
                'tax' => $data['tax'],
                'cash' => $data['cash'],
                'status' => 4,
                'text' => $data['text'],
                'website_id' => $data['website_id']
            );
            $res2 = $this->addCommissionDistribution($data_commission); //更新佣金账户流水
            if ($res1 && $res2) {
                $data2 = array(
                    'balance' => abs($data['income_tax']) + $account_info['balance']
                );
                $member_account->save($data2, ['uid' => $data['uid']]); //更新会员账户余额
                // 添加平台的整体资金流水
                $acount = new ShopAccount();
                if (abs($data['tax']) > 0) {
                    $acount->addAccountRecords(0, $data['uid'], "佣金提现成功，个人所得税!", abs($data['tax']), 24, $data['data_id'], '佣金提现到账户余额，个人所得税增加');
                }
                $acount->addAccountRecords(0, $data['uid'], "佣金提现到账户余额成功!", abs($data['cash']), 34, $data['data_id'], '佣金提现到账户余额');
                $commission_account = new VslDistributorAccountModel();
                $commission_account_info = $commission_account->getInfo(['uid' => $data['uid']], '*');
                try {
                    $data3 = array(
                        'tax' => $commission_account_info['tax'] + abs($data['tax']),
                        'freezing_commission' => $commission_account_info['freezing_commission'] - abs($data['income_tax']) - abs($data['tax']), //冻结佣金减少
                        'withdrawals' => $commission_account_info['withdrawals'] + abs($data['income_tax']) + abs($data['tax']), //已提现佣金增加
                    );
                    $commission_account->save($data3, ['uid' => $data['uid']]); //更新佣金账户
                    $withdraw = new VslDistributorCommissionWithdrawModel();
                    $res = $withdraw->save(['payment_date' => time(), 'status' => 3], ['withdraw_no' => $data['data_id']]); //更新佣金提现状态
                    $commission_account->commit();
                    return $res;
                } catch (\Exception $e) {
                    $commission_account->rollback();
                    return $e->getMessage();
                }
            }
            $commission_withdraw->commit();
        } catch (\Exception $e) {
            $commission_withdraw->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 佣金成功提现到账户余额
     */
    public function addCommissionWithdrawForBalance($data)
    {
        $commission_withdraw = new VslMemberAccountRecordsModel();
        $member_account = new VslMemberAccountModel();
        $account_info = $member_account->getInfo(['uid' => $data['uid']], '*');
        try {
            $data1 = array(
                'records_no' => getSerialNo(),
                'uid' => $data['uid'],
                'account_type' => 2,
                'number'   => $data['income_tax'], //实际到账金额
                'data_id' => $data['data_id'],
                'from_type' => 15,
                'balance' => abs($data['income_tax']) + $account_info['balance'],
                'text' => '佣金成功提现到余额',
                'create_time' => time(),
                'website_id' => $data['website_id']
            );
            $res1 = $commission_withdraw->save($data1); //添加会员流水
            $data_commission = array(
                'uid' => $data['uid'],
                'commission' => $data['income_tax'],
                'data_id' => $data['data_id'],
                'tax' => $data['tax'],
                'cash' => $data['cash'],
                'status' => 4,
                'text' => $data['text'],
                'website_id' => $data['website_id']
            );
            $res2 = $this->addCommissionDistribution($data_commission); //更新佣金账户流水
            if ($res1 && $res2) {
                $data2 = array(
                    'balance' => abs($data['income_tax']) + $account_info['balance']
                );
                $member_account->save($data2, ['uid' => $data['uid']]); //更新会员账户余额
                // 添加平台的整体资金流水
                $acount = new ShopAccount();
                if (abs($data['tax']) > 0) {
                    $acount->addAccountRecords(0, $data['uid'], "佣金提现成功，个人所得税!", abs($data['tax']), 24, $data['data_id'], '佣金提现到账户余额，个人所得税增加');
                }
                $acount->addAccountRecords(0, $data['uid'], "佣金提现到账户余额成功!", abs($data['cash']), 34, $data['data_id'], '佣金提现到账户余额');
                $commission_account = new VslDistributorAccountModel();
                $commission_account_info = $commission_account->getInfo(['uid' => $data['uid']], '*');
                $commission = $commission_account_info['commission'];
                if ($commission_account_info['commission'] >= abs($data['ti_tax'])) {
                    $commission = $commission_account_info['commission'] - abs($data['tax']);
                }
                try {
                    $data3 = array(
                        'tax' => $commission_account_info['tax'] + abs($data['tax']),
                        'freezing_commission' => $commission_account_info['freezing_commission'] - abs($data['commission']), //冻结佣金减少
                        'commission' => $commission,
                        'withdrawals' => $commission_account_info['withdrawals'] + abs($data['income_tax']) + abs($data['tax']), //已提现佣金增加
                    );
                    $commission_account->save($data3, ['uid' => $data['uid']]); //更新佣金账户
                    $withdraw = new VslDistributorCommissionWithdrawModel();
                    $res = $withdraw->save(['payment_date' => time(), 'status' => 3], ['withdraw_no' => $data['data_id']]); //更新佣金提现状态
                    return $res;
                } catch (\Exception $e) {
                    return $e->getMessage();
                }
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 修改佣金提现状态
     */
    public function commissionWithdrawAudit($id, $status, $memo)
    {
        $distributor_commission_withdraw = new VslDistributorCommissionWithdrawModel();
        $commission_info = $distributor_commission_withdraw->getInfo(['id' => $id], "*");
        $res = 0;
        $config = new ConfigService();
        $commission_withdraw_set = $config->getConfig(0,'SETTLEMENT',$commission_info ['website_id'], 1);
        $make_money = $commission_withdraw_set['make_money'];
        if ($commission_info  && $status == 2 && $make_money == 2) { // 平台手动审核通过提现待打款，更新提现状态
            $res = $distributor_commission_withdraw->save(['status' => $status], ['id' => $id]);
        }
        if ($commission_info  && $status == 2 && $make_money == 1) { // 平台手动审核通过提现自动打款，更新提现状态
            if ($commission_info['type'] == 5) {
                $params['shop_id'] = 0;
                $params['takeoutmoney'] = abs($commission_info['cash']);
                $params['uid'] =  $commission_info['uid'];
                $params['website_id'] = $commission_info['website_id'];
                $data_commission['data_id'] = $commission_info['withdraw_no'];
                $data_commission['website_id'] = $commission_info['website_id'];
                $data_commission['commission'] = $commission_info['income_tax'];
                $data_commission['cash'] = $commission_info['cash'];
                $data_commission['tax'] = $commission_info['tax'];
                $data_commission['uid'] = $commission_info['uid'];
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]); //提现成功
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                $data_commission['status'] = 9;
                $data_commission['text'] = '提现成功到银行卡'; //银行卡
                $data_commission['data_id'] = $commission_info['withdraw_no'];
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，打款成功。");
                $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
            }
            if ($commission_info['type'] == 1) { //银行卡
                $params['shop_id'] = 0;
                $params['takeoutmoney'] = abs($commission_info['cash']);
                $params['uid'] =  $commission_info['uid'];
                $params['website_id'] = $commission_info['website_id'];
                $data_commission['data_id'] = $commission_info['withdraw_no'];
                $data_commission['website_id'] = $commission_info['website_id'];
                $data_commission['commission'] = $commission_info['income_tax'];
                $data_commission['cash'] = $commission_info['cash'];
                $data_commission['tax'] = $commission_info['tax'];
                $data_commission['uid'] = $commission_info['uid'];
                $bank = new VslMemberBankAccountModel();
                $bank_id = $bank->getInfo(['uid' => $commission_info['uid'], 'account_number' => $commission_info['account_number']], 'id')['id'];
                $weixin_pay = new tlPay();
                $retval = $weixin_pay->tlWithdraw($commission_info['withdraw_no'], $commission_info['uid'], $bank_id, abs($commission_info['income_tax']));
                if ($retval['is_success'] == 1) { //自动打款成功
                    runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]); //提现成功
                    runhook('Notify', 'withdrawalSuccessBySms', $params);
                    $data_commission['status'] = 9;
                    $data_commission['text'] = '提现成功到银行卡'; //银行卡
                    $data_commission['data_id'] = $commission_info['withdraw_no'];
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，打款成功。");
                    $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                } else { //自动打款失败
                    $data_commission['status'] = -9;
                    $data_commission['msg'] = $retval['msg'];
                    $data_commission['text'] = '提现到银行卡打款失败'; //银行卡
                    $data_commission['data_id'] = $commission_info['withdraw_no'];
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，打款失败。");
                    $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => 5, "memo" => $retval['msg']));
                    return -9000;
                }
            }
            if ($commission_info['type'] == 2) { //微信
                $params['shop_id'] = 0;
                $params['takeoutmoney'] = abs($commission_info['cash']);
                $params['uid'] =  $commission_info['uid'];
                $params['website_id'] = $commission_info['website_id'];
                $data_commission['data_id'] = $commission_info['withdraw_no'];
                $data_commission['website_id'] = $commission_info['website_id'];
                $data_commission['commission'] = $commission_info['income_tax'];
                $data_commission['cash'] = $commission_info['cash'];
                $data_commission['tax'] = $commission_info['tax'];
                $data_commission['uid'] = $commission_info['uid'];
                $user_info = new UserModel();
                $wx_openid = $user_info->getInfo(['uid' => $commission_info['uid']], 'wx_openid')['wx_openid'];
                $weixin_pay = new WeiXinPay();
                $retval = $weixin_pay->EnterprisePayment($wx_openid, $commission_info['withdraw_no'], '', abs($commission_info['income_tax']), '佣金微信提现', $this->website_id);
                if ($retval['is_success'] == 1) { //自动打款成功
                    runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到微信', 'withdraw_time' => time()]); //提现成功
                    runhook('Notify', 'withdrawalSuccessBySms', $params);
                    $data_commission['status'] = 10;
                    $data_commission['text'] = '提现成功到微信'; //微信
                    $data_commission['data_id'] = $commission_info['withdraw_no'];
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金微信提现，打款成功。");
                    $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                } else { //自动打款失败
                    $data_commission['status'] = -10;
                    $data_commission['msg'] = $retval['msg'];
                    $data_commission['text'] = '微信提现打款失败'; //微信
                    $data_commission['data_id'] = $commission_info['withdraw_no'];
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金微信提现，打款失败。");
                    $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => 5, "memo" => $retval['msg']));
                    return -9000;
                }
            }
            if ($commission_info['type'] == 3) { //支付宝
                $data_commission['data_id'] = $commission_info['withdraw_no'];
                $data_commission['status'] = 7;
                $data_commission['uid'] = $commission_info['uid'];
                $data_commission['website_id'] = $commission_info['website_id'];
                $data_commission['commission'] = $commission_info['income_tax'];
                $data_commission['cash'] = $commission_info['cash'];
                $data_commission['tax'] = $commission_info['tax'];
                $data_commission['uid'] = $commission_info['uid'];
                $alipay_pay = new AliPay();
                $retval = $alipay_pay->aliPayTransferNew($commission_info['withdraw_no'], $commission_info['account_number'], abs($commission_info['income_tax']), $commission_info['realname']);
                if ($retval['is_success'] == 1) {
                    runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]); //提现成功
                    runhook('Notify', 'withdrawalSuccessBySms', $params);
                    $data_commission['status'] = 11;
                    $data_commission['text'] = '提现成功到支付宝'; //支付宝
                    $data_commission['data_id'] = $commission_info['withdraw_no'];
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金支付宝提现，打款成功。");
                    $res  = $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '打款成功'));
                } else { //自动打款失败
                    $data_commission['status'] = -11;
                    $data_commission['msg'] = $retval['msg'];
                    $data_commission['text'] = '支付宝提现打款失败'; //支付宝
                    $data_commission['data_id'] = $commission_info['withdraw_no'];
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金支付宝提现，打款失败。");
                    $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => 5, "memo" => $retval['msg']));
                    return -9000;
                }
            }
            if ($commission_info['type'] == 4) { //直接到账户余额
                $commission_info['data_id'] = $commission_info['withdraw_no'];
                $commission_info['text'] = '提现到账户余额成功,可提现佣金减少,已提现佣金增加';
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]); //提现成功
                $res =  $this->addCommissionWithdraw($commission_info); //审核通过直接提现到账户余额;
            }
        }
        if ($commission_info  && $status == 3) { // 平台同意打款，更新提现状态（在线打款）
            $data_commission['data_id'] = $commission_info['withdraw_no'];
            $data_commission['uid'] = $commission_info["uid"];
            $data_commission['cash'] = $commission_info["cash"];
            $data_commission['commission'] = $commission_info["income_tax"];
            $data_commission['tax'] = $commission_info['tax'];
            $data_commission['website_id'] = $commission_info['website_id'];
            $params['shop_id'] = 0;
            $params['takeoutmoney'] = abs($commission_info['cash']);
            $params['uid'] = $data_commission['uid'];
            $params['website_id'] = $this->website_id;
            if ($commission_info['type'] == 1) { //银行卡
                $bank = new VslMemberBankAccountModel();
                $bank_id = $bank->getInfo(['uid' => $commission_info['uid'], 'account_number' => $commission_info['account_number']], 'id')['id'];
                $weixin_pay = new tlPay();
                $retval = $weixin_pay->tlWithdraw($commission_info['withdraw_no'], $commission_info['uid'], $bank_id, abs($commission_info['income_tax']));
                if ($retval['is_success'] == 1) {
                    $data_commission['status'] = 9;
                    $data_commission['text'] = '提现成功到银行卡';
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，在线打款成功。");
                    $res =  $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '在线打款成功'));
                } else {
                    $data_commission['status'] = -9;
                    $data_commission['msg'] = $retval['msg'];
                    $data_commission['text'] = '银行卡提现打款失败'; //银行卡
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，在线打款失败。");
                    $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => 5, "memo" => $retval['msg']));
                    return -9000;
                }
            }
            if ($commission_info['type'] == 2) { //微信
                $user_info = new UserModel();
                $wx_openid = $user_info->getInfo(['uid' => $commission_info['uid']], 'wx_openid')['wx_openid'];
                $weixin_pay = new WeiXinPay();
                $retval = $weixin_pay->EnterprisePayment($wx_openid, $commission_info['withdraw_no'], '', abs($commission_info['income_tax']), '佣金微信提现', $this->website_id);
                if ($retval['is_success'] == 1) { //自动打款成功
                    runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到微信', 'withdraw_time' => time()]); //提现成功
                    runhook('Notify', 'withdrawalSuccessBySms', $params);
                    $data_commission['status'] = 10;
                    $data_commission['text'] = '提现成功到微信'; //微信
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金微信提现，在线打款成功。");
                    $res =  $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '在线打款成功'));
                } else { //自动打款失败
                    $data_commission['status'] = -10;
                    $data_commission['msg'] = $retval['msg'];
                    $data_commission['text'] = '微信提现打款失败'; //微信
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金微信提现，在线打款失败。");
                    $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => 5, "memo" => $retval['msg']));
                    return -9000;
                }
            }
            if ($commission_info['type'] == 3) { //支付宝
                $alipay_pay = new AliPay();
                $retval = $alipay_pay->aliPayTransferNew($commission_info['withdraw_no'], $commission_info['account_number'], abs($commission_info['income_tax']), $commission_info['realname']);
                if ($retval['is_success'] == 1) {
                    runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]); //提现成功
                    runhook('Notify', 'withdrawalSuccessBySms', $params);
                    $data_commission['status'] = 11;
                    $data_commission['text'] = '提现成功到支付宝'; //支付宝
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金支付宝提现，在线打款成功。");
                    $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '在线打款成功'));
                } else { //自动打款失败
                    $data_commission['status'] = -11;
                    $data_commission['msg'] = $retval['msg'];
                    $data_commission['text'] = '支付宝提现打款失败'; //支付宝
                    $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金支付宝提现，在线打款失败。");
                    $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => 5, "memo" => $retval['msg']));
                    return -9000;
                }
            }
            if ($commission_info['type'] == 4) { //余额
                $data['data_id'] = $commission_info['withdraw_no'];
                $data['website_id'] = $commission_info['website_id'];
                $data['commission'] = $commission_info['income_tax'];
                $data['uid'] = $commission_info['uid'];
                $data['tax'] = $commission_info['tax'];
                $data['cash'] = $commission_info["cash"];
                $data['income_tax'] = $commission_info["income_tax"];
                $data['text'] = '提现到账户余额成功,可提现佣金减少,已提现佣金增加';
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]); //提现成功
                $res = $this->addCommissionWithdraw($data);
            }
            if ($commission_info['type'] == 5) { //银行卡
                $data_commission['status'] = 9;
                $data_commission['text'] = '提现成功到银行卡';
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，在线打款成功。");
                $res =  $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '在线打款成功'));
            }
        }
        if ($commission_info  && $status == 5) { // 平台同意打款，更新提现状态（线下打款）
            $data_commission['data_id'] = $commission_info['withdraw_no'];
            $data_commission['uid'] = $commission_info["uid"];
            $data_commission['cash'] = $commission_info["cash"];
            $data_commission['commission'] = $commission_info["income_tax"];
            $data_commission['tax'] = $commission_info["tax"];
            $data_commission['website_id'] = $commission_info['website_id'];
            $params['shop_id'] = 0;
            $params['takeoutmoney'] = abs($commission_info['cash']);
            $params['uid'] = $data_commission['uid'];
            $params['website_id'] = $this->website_id;
            if ($commission_info['type'] == 1 || $commission_info['type'] == 5) { //银行卡
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到银行卡', 'withdraw_time' => time()]); //提现成功
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                $data_commission['status'] = 9;
                $data_commission['text'] = '提现成功到银行卡';
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金银行卡提现，手动打款成功。");
                $res =  $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '手动打款成功'));
            }
            if ($commission_info['type'] == 2) { //微信
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到微信', 'withdraw_time' => time()]); //提现成功
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                $data_commission['status'] = 10;
                $data_commission['text'] = '提现成功到微信'; //微信
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金微信提现，手动打款成功。");
                $res =  $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '手动打款成功'));
            }
            if ($commission_info['type'] == 3) { //支付宝
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到支付宝', 'withdraw_time' => time()]); //提现成功
                runhook('Notify', 'withdrawalSuccessBySms', $params);
                $data_commission['status'] = 11;
                $data_commission['text'] = '提现成功到支付宝'; //支付宝
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, "佣金支付宝提现，手动打款成功。");
                $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("payment_date" => time(), "status" => 3, "memo" => '手动打款成功'));
            }
            if ($commission_info['type'] == 4) { //余额
                $data['data_id'] = $commission_info['withdraw_no'];
                $data['website_id'] = $commission_info['website_id'];
                $data['cash'] = $commission_info['cash'];
                $data['income_tax'] = $commission_info['income_tax'];
                $data['uid'] = $commission_info['uid'];
                $data['tax'] = $data_commission['tax'];
                $data['text'] = '提现到账户余额成功,可提现佣金减少,已提现佣金增加';
                runhook("Notify", "sendCustomMessage", ["messageType" => "commission_payment", "uid" => $commission_info['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到账户余额', 'withdraw_time' => time()]); //提现成功
                $res = $this->addCommissionWithdraw($data);
            }
        }
        if ($commission_info  && $status == 4) { // 平台拒绝打款，更新提现状态
            $data_commission['data_id'] = $commission_info['withdraw_no'];
            $data_commission['uid'] = $commission_info['uid'];
            $data_commission['website_id'] = $commission_info['website_id'];
            $data_commission['cash'] = $commission_info["cash"];
            $data_commission['commission'] = $commission_info["income_tax"];
            $data_commission['tax'] = $commission_info["tax"];
            $data_commission['msg'] = $memo;
            if ($commission_info['type'] == 1 || $commission_info['type'] == 5) {
                $data_commission['status'] = 23;
                $data_commission['text'] = '提现到银行卡，平台拒绝';
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到银行卡', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, $data_commission['text']);
                $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => $status, "memo" => $memo));
            }
            if ($commission_info['type'] == 2) {
                $data_commission['status'] = 16;
                $data_commission['text'] = '提现到微信，平台拒绝';
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到微信', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, $data_commission['text']);
                $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => $status, "memo" => $memo));
            }
            if ($commission_info['type'] == 3) {
                $data_commission['status'] = 17;
                $data_commission['text'] = '提现到支付宝，平台拒绝';
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到支付宝', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, $data_commission['text']);
                $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => $status, "memo" => $memo));
            }
            if ($commission_info['type'] == 4) {
                $data_commission['status'] = 18;
                $data_commission['text'] = '提现到余额，平台拒绝';
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到账户余额', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
                $this->addAccountWithdrawUserRecords($data_commission, 2, $id, $data_commission['text']);
                $res = $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => $status, "memo" => $memo));
            }
        }
        if ($commission_info  && $status == -1) { // 平台审核不通过，更新提现状态
            $data_commission['data_id'] = $commission_info['withdraw_no'];
            $data_commission['uid'] = $commission_info['uid'];
            $data_commission['website_id'] = $commission_info['website_id'];
            $data_commission['cash'] = $commission_info["cash"];
            $data_commission['commission'] = $commission_info["income_tax"];
            $data_commission['tax'] = $commission_info["tax"];
            $data_commission['msg'] = $memo;
            if ($commission_info['type'] == 1 || $commission_info['type'] == 5) {
                $data_commission['status'] = 24;
                $data_commission['text'] = '提现到银行卡，平台审核不通过'; //微信
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到银行卡', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
            }
            if ($commission_info['type'] == 2) {
                $data_commission['status'] = 19;
                $data_commission['text'] = '提现到微信，平台审核不通过'; //微信
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到账户余额', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
            }
            if ($commission_info['type'] == 3) {
                $data_commission['status'] = 20;
                $data_commission['text'] = '提现到支付宝，平台审核不通过'; //支付宝
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到微信', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
            }
            if ($commission_info['type'] == 4) {
                $data_commission['status'] = 21;
                $data_commission['text'] = '提现到账户余额，平台审核不通过'; //账户余额
                runhook("Notify", "sendCustomMessage", ["messageType" => "cash_withdrawal", "uid" => $data_commission['uid'], 'withdraw_money' => $commission_info['cash'], "withdraw_type" => '提现到支付宝', 'handle_status' => $memo, 'handle_time' => time(), 'withdraw_time' => $commission_info['ask_for_date']]);
            }
            $this->addAccountWithdrawUserRecords($data_commission, 2, $id, $data_commission['text']);
            $res =  $distributor_commission_withdraw->where(array("id" => $id))->update(array("status" => $status, "memo" => $memo));
        }
        return $res;
    }
    /**
     * 平台审核提现
     */
    public function addAccountWithdrawUserRecords($data_commission, $account_type, $type_alis_id, $remark)
    {

        if ($data_commission['status'] == 5) { //自动审核通过微信待打款
            // 更新佣金账户情况
            $this->updateAccountWithdraw(5, $data_commission);
            //添加佣金账户流水
            $this->addCommissionDistribution($data_commission);
        }
        if ($data_commission['status'] == 7) { //自动审核通过支付宝待打款
            // 更新佣金账户情况
            $this->updateAccountWithdraw(7, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 8) { //自动审核通过银行卡待打款
            // 更新佣金账户情况
            $this->updateAccountWithdraw(8, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 15) { //自动审核通过余额待打款
            // 更新佣金账户情况
            $this->updateAccountWithdraw(15, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 6) { //自动提现到账户余额待审核
            // 更新佣金账户情况
            $this->updateAccountWithdraw(6, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 12) { //银行卡提现待审核
            // 更新佣金账户情况
            $this->updateAccountWithdraw(12, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 13) { //微信提现待审核
            // 更新佣金账户情况
            $this->updateAccountWithdraw(13, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 14) { //支付宝提现待审核
            // 更新佣金账户情况
            $this->updateAccountWithdraw(14, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 10) { //微信打款成功
            // 更新佣金账户情况
            $this->updateAccountWithdraw(10, $data_commission);
            //添加佣金账户流水
            $this->addCommissionDistribution($data_commission);
            $acount = new ShopAccount();
            // 更新提现总额的字段
            $acount->updateAccountUserWithdraw($data_commission['cash']);
            // 添加平台的整体资金流水
            $acount = new ShopAccount();
            if (abs($data_commission['tax']) > 0) {
                $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现成功，个人所得税!", abs($data_commission['tax']), 24, $type_alis_id, '佣金提现到微信，个人所得税增加');
            }
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现成功!", abs($data_commission['cash']), 1, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == -10) { //微信打款失败
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 11) { //支付宝打款成功
            // 更新佣金账户情况
            $this->updateAccountWithdraw(11, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            $acount = new ShopAccount();
            // 更新提现总额的字段
            $acount->updateAccountUserWithdraw($data_commission['cash']);
            // 添加平台的整体资金流水
            if (abs($data_commission['tax']) > 0) {
                $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现成功，个人所得税!", abs($data_commission['tax']), 24, $type_alis_id, '佣金提现到支付宝，个人所得税增加');
            }
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现成功!", abs($data_commission['cash']), 2, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == -11) { //支付宝打款失败
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 9) { //银行卡打款成功
            // 更新佣金账户情况
            $this->updateAccountWithdraw(9, $data_commission);
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            $acount = new ShopAccount();
            // 更新提现总额的字段
            $acount->updateAccountUserWithdraw($data_commission['cash']);
            // 添加平台的整体资金流水
            if (abs($data_commission['tax']) > 0) {
                $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现成功，个人所得税!", abs($data_commission['tax']), 24, $type_alis_id, '佣金提现到银行卡，个人所得税增加');
            }
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现成功!", abs($data_commission['cash']), 38, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == -9) { //银行卡打款失败
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
        }
        if ($data_commission['status'] == 16) { //平台拒绝微信打款
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-16, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现拒绝!", $data_commission['cash'], 3, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 23) { //平台拒绝银行卡打款
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-23, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现拒绝!", $data_commission['cash'], 3, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 17) { //平台拒绝支付宝打款
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-17, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现拒绝!", $data_commission['cash'], 3, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 24) { //银行卡提现平台审核不通过
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-24, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现审核不通过!", $data_commission['cash'], 6, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 19) { //微信提现平台审核不通过
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-19, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现审核不通过!", $data_commission['cash'], 6, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 20) { //支付宝提现平台审核不通过
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-20, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现审核不通过!", $data_commission['cash'], 6, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 21) { //提现到余额审核不通过
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-21, $data_commission);
            // 添加平台的整体资金流水
            $acount = new ShopAccount();
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现审核不通过!", $data_commission['cash'], 6, $type_alis_id, $remark);
        }
        if ($data_commission['status'] == 18) { //平台拒绝余额打款
            $this->addCommissionDistribution($data_commission); //添加佣金账户流水
            // 更新佣金账户情况
            $this->updateAccountWithdraw(-18, $data_commission);
            $acount = new ShopAccount();
            // 添加平台的整体资金流水
            $acount->addAccountRecords(0, $data_commission['uid'], "佣金提现拒绝!", $data_commission['cash'], 3, $type_alis_id, $remark);
        }
    }
    /**
     * 平台审核提现，更新佣金账户
     */
    public function updateAccountWithdraw($status, $data_commission)
    {
        $commission_account = new VslDistributorAccountModel();
        $commission_account_info = $commission_account->getInfo(['uid' => $data_commission['uid']], '*');
        try {
            if ($status == 5 || $status == 6 || $status == 7 || $status == 13 || $status == 14 || $status == 15 || $status == 12 || $status == 8) { //微信支付宝余额提现手动审核和自动审核
                $data3 = array(
                    'commission' => $commission_account_info['commission'] - abs($data_commission['commission']) - abs($data_commission['tax']), //可提现佣金减少
                    'freezing_commission' => $commission_account_info['freezing_commission'] + abs($data_commission['commission']) + abs($data_commission['tax']), //冻结佣金增加
                );
            }
            if ($status == 9 || $status == 10 || $status == 11) { //微信支付宝提现成功
                $data3 = array(
                    'withdrawals' => $commission_account_info['withdrawals'] + abs($data_commission['commission']) + abs($data_commission['tax']), //已提现佣金增加
                    'freezing_commission' => $commission_account_info['freezing_commission'] - abs($data_commission['commission']) - abs($data_commission['tax']), //冻结佣金减少
                    'tax' => $commission_account_info['tax'] + abs($data_commission['tax'])
                );
            }
            if ($status == -10 || $status == -11 || $status == -16 || $status == -17 || $status == -19 || $status == -20 || $status == -21 || $status == -18 || $status == -23 || $status == -24) { //微信支付宝提现失败或者拒绝打款审核不通过
                $data3 = array(
                    'commission' => $commission_account_info['commission'] + abs($data_commission['commission']) + abs($data_commission['tax']), //可提现佣金增加
                    'freezing_commission' => $commission_account_info['freezing_commission'] - abs($data_commission['commission']) - abs($data_commission['tax']) //冻结佣金减少
                );
            }
            $commission_account->save($data3, ['uid' => $data_commission['uid']]); //更新佣金账户
            $commission_account->commit();
            return 1;
        } catch (\Exception $e) {
            $commission_account->rollback();
            return $e->getMessage();
        }
    }
    /**
     * 后台佣金流水列表
     */
    public function getAccountList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $commission_account = new VslDistributorAccountRecordsViewModel();
        $list = $commission_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['type_name'] = $this->getStatusName($v["from_type"]);
                if ($v['from_type'] != 1 && $v['from_type'] != 2 && $v['from_type'] != 3 && $v['from_type'] != 22 && $v['from_type'] != 25 && $v['from_type'] != 26) {
                    $list['data'][$k]['change_money'] = (-1) * (abs($list['data'][$k]['commission']) + abs($list['data'][$k]['tax']));
                } else {
                    if ($v['from_type'] == 1) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 2) {
                        $list['data'][$k]['commission'] = '-' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 3) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 22) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 25) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 26) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    $list['data'][$k]['change_money'] = $list['data'][$k]['commission'];
                }
                if (empty($list['data'][$k]['user_name'])) {
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['text'] = str_replace("冻结佣金", $this->fre_commission, $list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("可提现佣金", $this->wit_commission, $list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("已提现佣金", $this->wits_commission, $list['data'][$k]['text']);
                $list['data'][$k]['user_info'] = ($v['nick_name']) ? $v['nick_name'] : ($v['user_name'] ? $v['user_name'] : ($v['user_tel'] ? $v['user_tel'] : $v['uid']));
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            }
        }
        return $list;
    }

    /**
     * 获取用户佣金流水列表
     */
    public function getMemberCommissionList($page_index, $page_size, $condition)
    {
        $commission_account = new VslDistributorAccountRecordsViewModel();
        $list = $commission_account->getViewList1($page_index, $page_size, $condition);
        if (!empty($list['data'])) {
            $order_no = [];
            foreach ($list['data'] as $k => $v) {
                $str_name = '团队佣金';
                if(strrpos($v['records_no'], 'CR') !== false){
                    $str_name = '一级佣金';
                }
                $list['data'][$k]['commission_type'] = $str_name;
                $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $order_no[] = $v['data_id'];
            }
            //获取订单详情
            $order_model = new VslOrderModel();
            $where['order_no'] = array('in', array_unique($order_no));
            $orders = $order_model->getViewList4(0, 0, $where,'');
            $order_list = objToArr($orders);


            $order_data = [];
            foreach($order_list['data'] as $v){
                $v['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
                $order_data[$v['order_no']] = $v;
            }

            foreach ($list['data'] as $k => &$item) {
                $item['user_info'] = '';
                if(isset($order_data[$item['data_id']])){
                    $item['user_info'] = $order_data[$item['data_id']]['user_info'];
                    $item['uid'] = $order_data[$item['data_id']]['uid'];
                }
            }

        }
        return $list;
    }
    /**
     * 前台佣金流水列表
     */
    public function getAccountLists($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $commission_account = new VslDistributorAccountRecordsViewModel();
        $list = $commission_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        if (!empty($list['data'])) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['type_name'] = $this->getStatusName($v["from_type"]);
                if ($v['from_type'] != 1 && $v['from_type'] != 2 && $v['from_type'] != 3 && $v['from_type'] != 22 && $v['from_type'] != 25 && $v['from_type'] != 26) {
                    $list['data'][$k]['type'] = 1;
                    $list['data'][$k]['change_money'] = (-1) * (abs($list['data'][$k]['commission']) + abs($list['data'][$k]['tax']));
                    if ($list['data'][$k]['balance'] == 0) {
                        $list['data'][$k]['change_money'] = (-1) * abs($list['data'][$k]['commission']);
                        $list['data'][$k]['real_cash'] =  $list['data'][$k]['commission'];
                    } else {
                        $list['data'][$k]['real_cash'] =  $list['data'][$k]['commission'] + $list['data'][$k]['tax'];
                        $list['data'][$k]['commission'] =  (-1) * (abs($list['data'][$k]['commission']) + abs($list['data'][$k]['tax']));
                    }
                } else {
                    $list['data'][$k]['type'] = 0;
                    if ($v['from_type'] == 1) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 2) {
                        $list['data'][$k]['commission'] = '-' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 3) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 22) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 25) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    if ($v['from_type'] == 26) {
                        $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                    }
                    $list['data'][$k]['change_money'] = $list['data'][$k]['commission'];
                }
                if (empty($list['data'][$k]['user_name'])) {
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['type_name'] = str_replace("分销商", $this->distributor, $list['data'][$k]['type_name']);
                $list['data'][$k]['text'] = str_replace("冻结佣金", $this->fre_commission, $list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("可提现佣金", $this->wit_commission, $list['data'][$k]['text']);
                $list['data'][$k]['text'] = str_replace("已提现佣金", $this->wits_commission, $list['data'][$k]['text']);
                $list['data'][$k]['user_info'] = ($v['nick_name']) ? $v['nick_name'] : ($v['user_name'] ? $v['user_name'] : ($v['user_tel'] ? $v['user_tel'] : $v['uid']));
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
            }
        }
        return $list;
    }
    /**
     * 佣金提现列表
     */
    public function getCommissionWithdrawList($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $commission_withdraw = new VslDistributorCommissionWithdrawModel();
        $list = $commission_withdraw->getViewList($page_index, $page_size, $condition, 'nmar.ask_for_date desc');
        if (!empty($list['data'])) {

            foreach ($list['data'] as $k => $v) {
                //剩余为0需变更为余额一致
                if (abs($v['cash']) == abs($v['income_tax'])) {
                    $list['data'][$k]['real_cash'] =  $list['data'][$k]['cash'] + $list['data'][$k]['tax'];
                } else {
                    $list['data'][$k]['real_cash'] =  $list['data'][$k]['cash'];
                    $list['data'][$k]['cash'] =  -1 * $list['data'][$k]['income_tax'];
                }
                if (empty($list['data'][$k]['user_name'])) {
                    $list['data'][$k]['user_name'] = $list['data'][$k]['nick_name'];
                }
                $list['data'][$k]['ask_for_date'] = date('Y-m-d H:i:s', $v['ask_for_date']);
                if ($v['payment_date']) {
                    $list['data'][$k]['payment_date'] = date('Y-m-d H:i:s', $v['payment_date']);
                } else {
                    $list['data'][$k]['payment_date'] = '未到账';
                }
                $list['data'][$k]['user_info'] = ($v['nick_name']) ? $v['nick_name'] : ($v['user_name'] ? $v['user_name'] : ($v['user_tel'] ? $v['user_tel'] : $v['uid']));
            }
        }
        return $list;
    }
    public function getMemberWithdrawalCount($condition)
    {
        $commission_withdraw = new VslDistributorCommissionWithdrawModel();
        $user_sum = $commission_withdraw->where($condition)->count();
        if ($user_sum) {
            return $user_sum;
        } else {
            return 0;
        }
    }
    /**
     * 佣金提现详情
     */
    public function commissionWithdrawDetail($id)
    {
        $commission_withdraw = new VslDistributorCommissionWithdrawModel();
        $info = $commission_withdraw->getInfo(['id' => $id], '*');
        $user = new userModel();
        $user_info = $user->getInfo(['uid' => $info['uid']], 'user_name,nick_name');
        if ($user_info['user_name']) {
            $info['user_name'] = $user_info['user_name']; //获取会员名称
        } else {
            $info['user_name'] = $user_info['nick_name']; //获取会员名称
        }
        $info['ask_for_date'] = date('Y-m-d H:i:s', $info['ask_for_date']);
        if ($info['payment_date'] > 0) {
            $info['payment_date'] = date('Y-m-d H:i:s', $info['payment_date']);
        } else {
            $info['payment_date'] = '未到账';
        }
        $info['status_before'] = $info['status'];
        if ($info['status'] == -1) {
            $info['status'] = '审核不通过';
        } elseif ($info['status'] == 1) {
            $info['status'] = '待审核';
        } elseif ($info['status'] == 2) {
            $info['status'] = '待打款';
        } elseif ($info['status'] == 3) {
            $info['status'] = '已打款';
        } elseif ($info['status'] == 4) {
            $info['status'] = '拒绝打款';
        } elseif ($info['status'] == 5) {
            $info['status'] = '打款失败';
        }
        $info['bank_name'] = '';
        if ($info['type'] == 1 || $info['type'] == 5) {
            $info['type_name'] = '银行卡';
            //获取银行名称
            $memberBankAccountModel = new VslMemberBankAccountModel();
            $bank_info = $memberBankAccountModel->getInfo(['account_number' => $info['account_number']], 'open_bank');
            $info['bank_name'] = $bank_info['open_bank'] ? $bank_info['open_bank'] : '';
        } elseif ($info['type'] == 2) {
            $info['type_name'] = '微信';
        } elseif ($info['type'] == 3) {
            $info['type_name'] = '支付宝';
        } elseif ($info['type'] == 4) {
            $info['type_name'] = '账户余额';
            $info['account_number'] = '账户余额';
        }
        if (abs($info['cash']) == abs($info['income_tax'])) {
            $info['real_cash'] =  $info['cash'] + $info['tax'];
        } else {
            $info['real_cash'] =  $info['cash'];
            $info['cash'] =  $info['income_tax'];
        }
        return $info;
    }
    /**
     * 获取本人信息
     */
    public function getmyinfos($uid, $website_id)
    {
        $list = [];
        $list['number1'] = 0;
        $list['number2'] = 0;
        $list['number3'] = 0;
        $user_infos = $this->getDistributorUser($uid, $website_id);

        if ($user_infos) {
            $list['number1'] = $user_infos['number1'];
            $list['number2'] = $user_infos['number2'];
            $list['number3'] = $user_infos['number3'];
        }
        return $list;
    }
    /**
     * 获取当前分销商团队
     */
    public function getTeamList($type, $uid, $website_id, $index, $page_size)
    {
        $member = new VslMemberModel();
        $user = new UserModel();
        $distributor_level = new DistributorLevelModel();
        $account = new VslDistributorAccountModel();
        //        $pattern=$this->getDistributionSite($website_id)['distribution_pattern'];//分销模式
        $list = [];
        if ($type == 1) {
            $first_uid = $member->Query(['referee_id' => $uid, 'isdistributor' => 2, 'website_id' => $website_id], 'uid'); //获取一级团队
            if ($first_uid) {
                $list = $member->pageQuery($index, $page_size, ['website_id' => $website_id, 'uid' => ['in', implode(',', $first_uid)], 'isdistributor' => 2], 'become_distributor_time desc', 'member_name,distributor_level_id,uid');
                foreach ($list['data'] as $k => $v) {
                    $list['data'][$k]['teamcount'] = $this->getDistributorTeam($v['uid'], $website_id);
                    //获取团队所有分销商
                    //获取所有下级
                    $list['data'][$k]['all_child'] = 0;
                    $all_child = $this->getAllChild($v['uid'], $website_id);
                    if ($all_child) {
                        $total_child = $member->Query(['isdistributor' => 2, 'uid' => ['in', implode(',', $all_child)]], 'uid');
                        if ($total_child) {
                            $list['data'][$k]['all_child'] = count($total_child);
                        }
                    }
                    //获取团队三级内分销商
                    //获取下线客户一级
                    $list['data'][$k]['user_count'] = 0;
                    $list['data'][$k]['user_count2'] = 0;
                    $list['data'][$k]['user_count3'] = 0;
                    $list['data'][$k]['agentcount'] = 0;
                    $user_list = $this->getDistributorUser($v['uid'], $website_id);
                    if ($user_list) {
                        $list['data'][$k]['user_count'] = $user_list['user_count'];
                        $list['data'][$k]['user_count2'] = $user_list['user_count2'];
                        $list['data'][$k]['user_count3'] = $user_list['user_count3'];
                        $list['data'][$k]['agentcount'] = $user_list['agentcount'];
                    }
                    $user_info = $user->getInfo(['uid' => $v['uid']], 'user_name,nick_name');
                    if ($user_info['user_name']) {
                        $list['data'][$k]['member_name'] = $user_info['user_name'];
                    } else {
                        $list['data'][$k]['member_name'] = $user_info['nick_name'];
                    }
                    $distributor_level_name = $distributor_level->getInfo(['id' => $v['distributor_level_id']], 'level_name');
                    $list['data'][$k]['distributor_level_name'] = $distributor_level_name['level_name'];
                    $commission_account = $account->getInfo(['uid' => $v['uid']], '*');
                    $list['data'][$k]['commission'] = sprintf("%.2f", $commission_account['commission'] + $commission_account['withdrawals'] + $commission_account['freezing_commission']);
                }
            }
            return $list;
        }
        if ($type == 2) {
            $first_uid = $member->Query(['referee_id' => $uid, 'isdistributor' => 2, 'website_id' => $website_id], 'uid'); //获取一级团队
            if ($first_uid) {
                $second_uid = $member->Query(['referee_id' => ['in', implode(',', $first_uid)], 'isdistributor' => 2, 'website_id' => $website_id], 'uid'); //获取二级团队
                if ($second_uid) {
                    $list = $member->pageQuery($index, $page_size, ['website_id' => $website_id, 'uid' => ['in', implode(',', $second_uid)], 'isdistributor' => 2], 'become_distributor_time desc', 'member_name,distributor_level_id,uid');
                    foreach ($list['data'] as $k => $v) {
                        $list['data'][$k]['teamcount'] = $this->getDistributorTeam($v['uid'], $website_id);
                        //获取团队所有分销商
                        //获取所有下级
                        $list['data'][$k]['all_child'] = 0;
                        $all_child = $this->getAllChild($v['uid'], $website_id);
                        if ($all_child) {
                            $total_child = $member->Query(['isdistributor' => 2, 'uid' => ['in', implode(',', $all_child)]], 'uid');
                            if ($total_child) {
                                $list['data'][$k]['all_child'] = count($total_child);
                            }
                        }
                        //获取团队三级内分销商
                        //获取下线客户一级
                        $list['data'][$k]['user_count'] = 0;
                        $list['data'][$k]['user_count2'] = 0;
                        $list['data'][$k]['user_count3'] = 0;
                        $list['data'][$k]['agentcount'] = 0;
                        $user_list = $this->getDistributorUser($v['uid'], $website_id);
                        if ($user_list) {
                            $list['data'][$k]['user_count'] = $user_list['user_count'];
                            $list['data'][$k]['user_count2'] = $user_list['user_count2'];
                            $list['data'][$k]['user_count3'] = $user_list['user_count3'];
                            $list['data'][$k]['agentcount'] = $user_list['agentcount'];
                        }
                        $user_info = $user->getInfo(['uid' => $v['uid']], 'user_name,nick_name');
                        if ($user_info['user_name']) {
                            $list['data'][$k]['member_name'] = $user_info['user_name'];
                        } else {
                            $list['data'][$k]['member_name'] = $user_info['nick_name'];
                        }
                        $distributor_level_name = $distributor_level->getInfo(['id' => $v['distributor_level_id']], 'level_name');
                        $list['data'][$k]['distributor_level_name'] = $distributor_level_name['level_name'];
                        $commission_account = $account->getInfo(['uid' => $v['uid']], '*');
                        $list['data'][$k]['commission'] = $commission_account['commission'] + $commission_account['withdrawals'] + $commission_account['freezing_commission'];
                    }
                }
            }
            return $list;
        }
        if ($type == 3) {
            $first_uid = $member->Query(['referee_id' => $uid, 'isdistributor' => 2, 'website_id' => $website_id], 'uid'); //获取一级团队
            if ($first_uid) {
                $second_uid = $member->Query(['referee_id' => ['in', implode(',', $first_uid)], 'isdistributor' => 2, 'website_id' => $website_id], 'uid'); //获取二级团队
                if ($second_uid) {
                    $third_uid = $member->Query(['referee_id' => ['in', implode(',', $second_uid)], 'isdistributor' => 2, 'website_id' => $website_id], 'uid'); //获取三级团队
                    if ($third_uid) {
                        $list = $member->pageQuery($index, $page_size, ['website_id' => $website_id, 'uid' => ['in', implode(',', $third_uid)]], 'become_distributor_time desc', 'member_name,distributor_level_id,uid');
                        foreach ($list['data'] as $k => $v) {
                            $list['data'][$k]['teamcount'] = $this->getDistributorTeam($v['uid'], $website_id);
                            //获取团队所有分销商
                            //获取所有下级
                            $list['data'][$k]['all_child'] = 0;
                            $all_child = $this->getAllChild($v['uid'], $website_id);
                            if ($all_child) {
                                $total_child = $member->Query(['isdistributor' => 2, 'uid' => ['in', implode(',', $all_child)]], 'uid');
                                if ($total_child) {
                                    $list['data'][$k]['all_child'] = count($total_child);
                                }
                            }
                            //获取团队三级内分销商
                            //获取下线客户一级
                            $list['data'][$k]['user_count'] = 0;
                            $list['data'][$k]['user_count2'] = 0;
                            $list['data'][$k]['user_count3'] = 0;
                            $list['data'][$k]['agentcount'] = 0;
                            $user_list = $this->getDistributorUser($v['uid'], $website_id);
                            if ($user_list) {
                                $list['data'][$k]['user_count'] = $user_list['user_count'];
                                $list['data'][$k]['user_count2'] = $user_list['user_count2'];
                                $list['data'][$k]['user_count3'] = $user_list['user_count3'];
                                $list['data'][$k]['agentcount'] = $user_list['agentcount'];
                            }
                            $user_info = $user->getInfo(['uid' => $v['uid']], 'user_name,nick_name');
                            if ($user_info['user_name']) {
                                $list['data'][$k]['member_name'] = $user_info['user_name'];
                            } else {
                                $list['data'][$k]['member_name'] = $user_info['nick_name'];
                            }
                            $distributor_level_name = $distributor_level->getInfo(['id' => $v['distributor_level_id']], 'level_name'); {
                                $list['data'][$k]['distributor_level_name'] = $distributor_level_name['level_name'];
                                $commission_account = $account->getInfo(['uid' => $v['uid']], '*');
                                $list['data'][$k]['commission'] = $commission_account['commission'] + $commission_account['withdrawals'] + $commission_account['freezing_commission'];
                            }
                        }
                    }
                }
            }
            return $list;
        }
        return $list;
    }
    /**
     * 获取当前分销商的团队分销商人数（3级内）
     */
    public function getDistributorUser($uid, $website_id)
    {
        $list = $this->getDistributionSite($website_id);
        $distributor = new VslMemberModel();
        $result['number1'] = 0;
        $result['agentcount'] = 0;
        $result['user_count'] = 0;
        $result['user_count2'] = 0;
        $result['user_count3'] = 0;
        $result['number2'] = 0;
        $result['number3'] = 0;
        if (1 <= $list['distribution_pattern']) {
            $lower_id = $distributor->Query(['website_id' => $website_id, 'referee_id' => $uid], 'uid'); //一级
            $idslevel1 = $distributor->Query(['isdistributor' => 2, 'referee_id' => $uid], 'uid');
            if ($idslevel1) {
                $result['number1'] = count($idslevel1); //一级分销商总人数
                $result['agentcount'] += $result['number1'];
            }
            //获取1级客户数
            $id1 = $distributor->Query(['referee_id' => $uid, 'isdistributor' => ['neq', 2]], 'uid');
            if ($id1) {
                $result['user_count'] = count($id1); //一级分销商总人数
            }
        }
        if (2 <= $list['distribution_pattern']) {
            if ($lower_id) {
                $lower_id1 = $distributor->Query(['website_id' => $website_id, 'referee_id' => ['in', implode(',', $lower_id)]], 'uid'); //二级
                $idslevel2 = $distributor->Query(['isdistributor' => 2, 'referee_id' => ['in', implode(',', $lower_id)]], 'uid');
                if ($idslevel2) {
                    $result['number2'] = count($idslevel2); //二级分销商总人数
                    $result['agentcount'] += $result['number2'];
                }
                //获取2级客户数
                $id2 = $distributor->Query(['referee_id' => ['in', implode(',', $lower_id)], 'isdistributor' => ['neq', 2]], 'uid');
                if ($id2) {
                    $result['user_count2'] = count($id2); //一级分销商总人数
                }
            }
        }
        if (3 <= $list['distribution_pattern']) {
            if ($lower_id1) {
                $lower_id2 = $distributor->Query(['website_id' => $website_id, 'referee_id' => ['in', implode(',', $lower_id1)]], 'uid'); //三级
                $idslevel3 = $distributor->Query(['isdistributor' => 2, 'referee_id' => ['in', implode(',', $lower_id1)]], 'uid');
                if ($idslevel3) {
                    $result['number3'] = count($idslevel3); //三级分销商总人数
                    $result['agentcount'] += $result['number3'];
                }
                //获取2级客户数
                $id3 = $distributor->Query(['referee_id' => ['in', implode(',', $lower_id1)], 'isdistributor' => ['neq', 2]], 'uid');
                if ($id3) {
                    $result['user_count3'] = count($id3); //一级分销商总人数
                }
            }
        }
        return $result;
    }
    /**
     * 获取当前分销商的团队人数
     */
    public function getDistributorTeam($uid, $website_id)
    {
        $member = new VslMemberModel();
        $config = $this->getDistributionSite($website_id);
        if ($config['distribution_pattern'] == 1) { //一级分销
            $lower_id = $member->Query(['website_id' => $website_id, 'referee_id' => $uid], 'uid'); //一级
            if ($lower_id) {
                $number1 = count($lower_id);
                return $number1;
            } else {
                return 0;
            }
        }
        if ($config['distribution_pattern'] == 2) { //二级分销
            $lower_id = $member->Query(['website_id' => $website_id, 'referee_id' => $uid], 'uid'); //一级
            if ($lower_id) {
                $number1 = count($lower_id);
                $lower_id1 = $member->Query(['website_id' => $website_id, 'referee_id' => ['in', implode(',', $lower_id)]], 'uid'); //二级
                if ($lower_id1) {
                    $number2 = count($lower_id1);
                    return $number1 + $number2;
                } else {
                    return $number1;
                }
            } else {
                return 0;
            }
        }
        if ($config['distribution_pattern'] == 3) { //三级分销
            $lower_id = $member->Query(['website_id' => $website_id, 'referee_id' => $uid], 'uid'); //一级
            if ($lower_id) {
                $number1 = count($lower_id);
                $lower_id1 = $member->Query(['website_id' => $website_id, 'referee_id' => ['in', implode(',', $lower_id)]], 'uid'); //二级
                if ($lower_id1) {
                    $number2 = count($lower_id1);
                    $lower_id2 = $member->Query(['website_id' => $website_id, 'referee_id' => ['in', implode(',', $lower_id1)]], 'uid'); //三级
                    if ($lower_id2) {
                        $number3 = count($lower_id2);
                        return $number1 + $number2 + $number3;
                    } else {
                        return $number1 + $number2;
                    }
                } else {
                    return $number1;
                }
            } else {
                return 0;
            }
        }
    }
    /**
     * 获取商品的分销佣金
     */
    public function getGoodsCommission($website_id, $goods_id, $uid, $price)
    {
        $addonsConfigService = new AddonsConfigService();
        $base_info = $addonsConfigService->getAddonsConfig("distribution", $website_id, 0, 1); //基本设置
        $ConfigService = new ConfigService();
        $set_infos = $ConfigService ->getConfig(0,"SETTLEMENT",$website_id, 1);
        $commission_calculation = $set_infos['commission_calculation']; //计算节点（商品价格）
        $set_info = $this->getAgreementSite($website_id);
        $goods_info = $this->goods_ser->getGoodsDetailById($goods_id);
        $level_id = 0;
        if($uid){
            $member = new VslMemberModel();
            $level_id = $member->getInfo(['uid' => $uid], 'distributor_level_id')['distributor_level_id'];
        }
        if(!$uid || empty($level_id)){
            $levels = new DistributorLevelModel();
            $level_id = $levels->getInfo(['website_id'=>$website_id,'is_default'=>1],'id')['id'];//默认等级权重，也是最低等级
        }


        $level = new DistributorLevelModel();
        $level_info = $level->getInfo(['id' => $level_id]);
        $distribution_rule = getAddons('distribution', $website_id);
        $cost_price = $goods_info['cost_price']; //商品成本价
        $promotion_price = $goods_info['promotion_price']; //商品销售价
        $original_price = $goods_info['market_price']; //商品原价
        $profit_price = $price - $cost_price; //商品利润价
        if ($profit_price < 0) {
            $profit_price = 0;
        }
        $real_price = 0;
        if ($commission_calculation == 1) { //实际付款金额
            $real_price = $price;
        } elseif ($commission_calculation == 2) { //商品原价
            $real_price = $original_price;
        } elseif ($commission_calculation == 3) { //商品销售价
            $real_price = $promotion_price;
        } elseif ($commission_calculation == 4) { //商品成本价
            $real_price = $cost_price;
        } elseif ($commission_calculation == 5) { //商品利润价
            $real_price = $profit_price;
        }
        if ($distribution_rule && $base_info['purchase_type'] == 1 && $set_info && $set_info['distribution_label'] && $set_info['distribution_label'] == 1) {
            if ($goods_info['is_distribution'] == 1) { //该商品参与分销
                $commission1 = 0;
                $commission11 = 0;
                $commissionA = 0;
                $commissionA1 = 0;
                $commissionA11 = 0;
                $point1 = 0;
                $point11 = 0;
                $pointA = 0;
                $pointA1 = 0;
                $pointA11 = 0;
                if ($goods_info['distribution_rule'] == 1) { //有独立分销规则
                    $goods_info['distribution_rule_val'] = json_decode(htmlspecialchars_decode($goods_info['distribution_rule_val']), true);
                    if ($goods_info['distribution_rule_val']['level_rule'] && $goods_info['distribution_rule_val']['level_rule'] == 1) { //固定等级比例
                        $level_rule_ids = $goods_info['distribution_rule_val']['level_ids'];
                        if ($goods_info['distribution_rule_val']['recommend_type'] == 1) { //等级佣金比例设置
                            $level_first_rebate = $goods_info['distribution_rule_val']['first_rebate'];
                            $level_first_point = $goods_info['distribution_rule_val']['first_point'];
                        } else { //固定佣金
                            $level_first_rebate1 = $goods_info['distribution_rule_val']['first_rebate1'];
                            $level_first_point1 = $goods_info['distribution_rule_val']['first_point1'];
                        }
                        if ($level_rule_ids && in_array($level_id, $level_rule_ids)) { //有特定等级返佣设置
                            foreach ($level_rule_ids as $k => $v) {
                                if ($v == $level_id) {
                                    if ($goods_info['distribution_rule_val']['recommend_type'] == 1) { //比例返佣
                                        $commission1 =  $level_first_rebate[$k];
                                        $point1 = $level_first_point[$k];
                                        $commission11 = 0;
                                        $point11 = 0;
                                    } else {
                                        $commission1 = 0;
                                        $point1 = 0;
                                        $commission11 =  $level_first_rebate1[$k];
                                        $point11 = $level_first_point1[$k];
                                    }
                                }
                            }
                        }
                    } else {
                        if ($goods_info['distribution_rule_val']['recommend_type'] == 1) { //佣金比例设置
                            $commission1 = $goods_info['distribution_rule_val']['first_rebate'];
                            $point1 = $goods_info['distribution_rule_val']['first_point'];
                            $commission11 = 0;
                            $point11 = 0;
                        } else { //固定佣金
                            $commission1 = 0;
                            $point1 = 0;
                            $commission11 = $goods_info['distribution_rule_val']['first_rebate1'];
                            $point11 = $goods_info['distribution_rule_val']['first_point1'];
                        }
                    }
                }
                if ($commission11 == '' && $commission1 != '') { //活动比例一级返佣
                    $commissionA1 = $commission1 / 100;
                }
                if ($commission1 == '' && $commission11 != '') { //活动固定一级返佣
                    $commissionA11 = $commission11;
                }
                if ($commission1 == '' && $commission11 == '') {
                    if ($level_info['recommend_type'] == 1) { //等级比例一级返佣
                        $commissionA1 = $level_info['commission1'] / 100;
                    } else { //等级固定一级返佣
                        $commissionA11 = $level_info['commission11'];
                    }
                }
                if ($point11 == '' && $point1 != '') { //活动比例一级返积分
                    $pointA1 = $point1 / 100;
                }
                if ($point1 == '' && $point11 != '') { //活动固定一级返积分
                    $pointA11 = $point11;
                }
                if ($point1 == '' && $point11 == '') {
                    if ($level_info['recommend_type'] == 1) { //等级比例一级返积分
                        $pointA1 = $level_info['commission_point1'] / 100;
                    } else { //等级固定一级返积分
                        $pointA11 = $level_info['commission_point11'];
                    }
                }
                if ($commissionA1 != '') { //比例一级返佣
                    $commissionA = twoDecimal($real_price * $commissionA1);
                }
                if ($commissionA11 != '') { //固定一级返佣
                    $commissionA =  $commissionA11;
                }
                if ($pointA1 != '') { //比例一级返积分
                    $pointA = floor($real_price * $pointA1);
                }
                if ($pointA11 != '') { //固定一级返积分
                    $pointA =  $pointA11; //开启内购之后当前分销商获得一级积分
                }
                $data['commission'] = $commissionA;
                $data['point'] = $pointA;
                return $data;
            }
        } else {
            $data['commission'] = 0;
            $data['point'] = 0;
            return $data;
        }
    }

    /*
     * 获取分销商数量
     */
    public function getCountForDistributor()
    {
        $member = new VslMemberModel();
        $count = $member->getCount(['website_id' => $this->website_id, 'isdistributor' => 2]);
        return $count;
    }
    /**
     * 购买成为分销商
     */
    public function pay_becomeLower($uid)
    {
        $member = new VslMemberModel();
        $distributor = $member->getInfo(['uid' => $uid], '*');
        $config = new AddonsConfigService();
        $base_info = $config->getAddonsConfig("distribution", $distributor['website_id'], 0, 1);
        $distribution_pattern = $base_info['distribution_pattern'];
        $distributor_level = new DistributorLevelModel();
        $level_info = $distributor_level->getInfo(['website_id' => $distributor['website_id'], 'is_default' => 1], '*');
        $level_id = $level_info['id'];
        if ($distributor['isdistributor'] != 2) { //判断是否是分销商
            $result = [];
            $ratio = 0;
            $extend_code = $this->create_extend();
            $member->save(['isdistributor' => 2, 'distributor_level_id' => $level_id, 'extend_code' => $extend_code, "apply_distributor_time" => time(), 'become_distributor_time' => time()], ['uid' => $uid]);
            $referee_id = $member->getInfo(['uid' => $uid], 'referee_id')['referee_id'];
            if ($referee_id) {
                $this->updateDistributorLevelInfo($referee_id);
            }
            if ($distribution_pattern >= 1) {
                $ratio .= '一级返佣比例' . $level_info['commission1'] . '%';
            }
            if ($distribution_pattern >= 2) {
                $ratio .= ',二级返佣比例' . $level_info['commission2'] . '%';
            }
            if ($distribution_pattern >= 3) {
                $ratio .= ',三级返佣比例' . $level_info['commission3'] . '%';
            }
            if ($base_info['distribution_pattern'] >= 1) {
                if ($distributor['referee_id']) {
                    $recommend1_info = $member->getInfo(['uid' => $distributor['referee_id']], '*');
                    if ($recommend1_info && $recommend1_info['isdistributor'] == 2) {
                        $level_info1 = $distributor_level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                        $recommend1 = $level_info1['recommend1']; //一级推荐奖
                        $recommend_point1 = $level_info1['recommend_point1']; //一级推荐积分
                        $recommend_beautiful_point1 = $level_info1['recommend_beautiful_point1']; //一级推荐美丽分
                        $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $distributor['website_id'],$recommend_beautiful_point1);
                    }
                }
            }
            if ($base_info['distribution_pattern'] >= 2) {
                $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                if ($recommend2_info && $recommend2_info['isdistributor'] == 2) {
                    $level_info2 = $distributor_level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                    $recommend2 = $level_info2['recommend2']; //二级推荐奖
                    $recommend_point2 = $level_info2['recommend_point2']; //二级推荐积分
                    $recommend_beautiful_point2 = $level_info2['recommend_beautiful_point2']; //二级推荐美丽分
                    $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $distributor['website_id'],$recommend_beautiful_point2);
                }
            }
            if ($base_info['distribution_pattern'] >= 3) {
                $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                if ($recommend3_info && $recommend3_info['isdistributor'] == 2) {
                    $level_info3 = $distributor_level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                    $recommend3 = $level_info3['recommend3']; //三级推荐奖
                    $recommend_point3 = $level_info3['recommend_point3']; //三级推荐积分
                    $recommend_beautiful_point3 = $level_info3['recommend_beautiful_point3']; //三级推荐美丽分
                    $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $distributor['website_id'],$recommend_beautiful_point3);
                }
            }
            runhook("Notify", "sendCustomMessage", ["messageType" => "become_distributor", "uid" => $uid, "become_time" => time(), 'ratio' => $ratio, 'level_name' => $level_info['level_name']]); //用户成为分销商提醒
            runhook("Notify", "successfulDistributorByTemplate", ["uid" => $uid, "website_id" => $distributor['website_id']]); //用户成为分销商提醒
        }
    }
    /**
     * 购买升级分销等级
     */
    public function pay_updateDistributorLevelInfo($uid, $distributor_level_id)
    {
        $member = new VslMemberModel();
        $level = new DistributorLevelModel();
        $config = new AddonsConfigService();
        $distributor = $member->getInfo(['uid' => $uid], '*');
        $default_level_name =  $level->getInfo(['id' => $distributor['distributor_level_id']], 'level_name')['level_name'];
        $base_info = $config->getAddonsConfig("distribution", $distributor['website_id'], 0, 1); //基本设置
        $distribution_pattern = $base_info['distribution_pattern'];
        if ($distributor['isdistributor'] == 2) {
            $level_infos = $level->getInfo(['id' => $distributor_level_id, 'website_id' => $distributor['website_id']]);
            $member->save(['distributor_level_id' => $distributor_level_id, 'up_level_time' => time(), 'down_up_level_time' => ''], ['uid' => $uid]);
            $ratio = '';
            if ($distribution_pattern >= 1) {
                $ratio .= '一级返佣比例' . $level_infos['commission1'] . '%';
            }
            if ($distribution_pattern >= 2) {
                $ratio .= ',二级返佣比例' . $level_infos['commission2'] . '%';
            }
            if ($distribution_pattern >= 3) {
                $ratio .= ',三级返佣比例' . $level_infos['commission3'] . '%';
            }
            runhook("Notify", "sendCustomMessage", ['messageType' => 'upgrade_notice', 'uid' => $uid, 'present_grade' => $level_infos['level_name'], 'ratio' => $ratio, 'primary_grade' => $default_level_name, 'upgrade_time' => time()]); //升级
            if ($distributor['referee_id']) {
                if (getAddons('globalbonus', $this->website_id)) {
                    $global = new GlobalBonus();
                    $global->updateAgentLevelInfo($distributor['referee_id']);
                }
                if (getAddons('areabonus', $this->website_id)) {
                    $area = new AreaBonus();
                    $area->updateAgentLevelInfo($distributor['referee_id']);
                }
                if (getAddons('teambonus', $this->website_id)) {
                    $team = new TeamBonus();
                    $team->updateAgentLevelInfo($distributor['referee_id']);
                }
                $this->updateDistributorLevelInfo($distributor['referee_id']);
            }
            $member_info = $member->getInfo(['uid' => $uid], '*');
            if ($distributor['distributor_level_id'] != $member_info['distributor_level_id']) {
                if ($base_info['distribution_pattern'] >= 1) {
                    if ($member_info['referee_id']) {
                        $recommend1_info = $member->getInfo(['uid' => $member_info['referee_id']], '*');
                        if ($recommend1_info['isdistributor'] == 2) {
                            $level_infos = $level->getInfo(['id' => $recommend1_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                            $recommend1 = $level_infos['recommend1']; //一级推荐奖
                            $recommend_point1 = $level_infos['recommend_point1']; //一级推荐积分
                            $recommend_beautiful_point1 = $level_infos['recommend_beautiful_point1']; //一级推荐美丽分
                            $this->addRecommed($uid, $recommend1_info['uid'], $recommend1, $recommend_point1, $member_info['website_id'],$recommend_beautiful_point1);
                        }
                    }
                }
                if ($base_info['distribution_pattern'] >= 2) {
                    $recommend2_info = $member->getInfo(['uid' => $recommend1_info['referee_id']], '*');
                    if ($recommend2_info['isdistributor'] == 2) {
                        $level_infos = $level->getInfo(['id' => $recommend2_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                        $recommend2 = $level_infos['recommend2']; //二级推荐奖
                        $recommend_point2 = $level_infos['recommend_point2']; //二级推荐积分
                        $recommend_beautiful_point2 = $level_infos['recommend_beautiful_point2']; //二级推荐美丽分
                        $this->addRecommed($uid, $recommend2_info['uid'], $recommend2, $recommend_point2, $member_info['website_id'],$recommend_beautiful_point2);
                    }
                }
                if ($base_info['distribution_pattern'] >= 3) {
                    $recommend3_info = $member->getInfo(['uid' => $recommend2_info['referee_id']], '*');
                    if ($recommend3_info['isdistributor'] == 2) {
                        $level_infos = $level->getInfo(['id' => $recommend3_info['distributor_level_id'], 'website_id' => $distributor['website_id']]);
                        $recommend3 = $level_infos['recommend3']; //三级推荐奖
                        $recommend_point3 = $level_infos['recommend_point3']; //三级推荐积分
                        $recommend_beautiful_point3 = $level_infos['recommend_beautiful_point3']; //三级推荐美丽分
                        $this->addRecommed($uid, $recommend3_info['uid'], $recommend3, $recommend_point3, $member_info['website_id'],$recommend_beautiful_point3);
                    }
                }
            }
        }
    }
    /**
     * 佣金提现列表导出Excel
     */
    public function getCommissionWithdrawListToExcel($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $commission_withdraw = new VslDistributorCommissionWithdrawModel();
        $member = new Member();
        $data = $commission_withdraw->getViewList($page_index, $page_size, $condition, 'nmar.ask_for_date desc');
        $list['data'] = [];
        if ($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $commission_withdraw->getViewList($i, $page_size, $condition, 'nmar.ask_for_date desc')['data'];
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $k => $v) {
                        //剩余为0需变更为余额一致
                        if (abs($v['cash']) == abs($v['income_tax'])) {
                            $res['data'][$k]['real_cash'] =  $res['data'][$k]['cash'] + $res['data'][$k]['tax'];
                        } else {
                            $res['data'][$k]['real_cash'] =  $res['data'][$k]['cash'];
                            $res['data'][$k]['cash'] =  -1 * $res['data'][$k]['income_tax'];
                        }
                        if (empty($res['data'][$k]['user_name'])) {
                            $res['data'][$k]['user_name'] = $res['data'][$k]['nick_name'];
                        }
                        $res['data'][$k]['ask_for_date'] = date('Y-m-d H:i:s', $v['ask_for_date']);
                        if ($v['payment_date']) {
                            $res['data'][$k]['payment_date'] = date('Y-m-d H:i:s', $v['payment_date']);
                        } else {
                            $res['data'][$k]['payment_date'] = '未到账';
                        }
                        $res['data'][$k]['cash'] = '¥' . $res['data'][$k]['cash'];
                        $res['data'][$k]['user_name'] = $member->removeEmoji($res['data'][$k]['user_name']);
                        switch ($v['type']) {
                            case '1':
                                $res['data'][$k]['type'] = '银行卡';
                                break;
                            case '2':
                                $res['data'][$k]['type'] = '微信';
                                break;
                            case '3':
                                $res['data'][$k]['type'] = '支付宝';
                                break;
                            case '4':
                                $res['data'][$k]['type'] = '账户余额';
                                $res['data'][$k]['account_number'] = '账户余额';
                                break;
                            case '5':
                                $res['data'][$k]['type'] = '银行卡';
                                break;
                        }
                        $res['data'][$k]['status'] = $member->getWithdrawStatusName($v['status']);
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'], $res['data']);
                }
            }
        }
        return $list;
    }
    /**
     * 佣金流水列表导出Excel
     */
    public function getAccountListToExcel($page_index, $page_size, $condition, $order = '', $field = '*')
    {
        $commission_account = new VslDistributorAccountRecordsViewModel();
        $member = new Member();
        $data = $commission_account->getViewList($page_index, $page_size, $condition, 'nmar.create_time desc');
        $list['data'] = [];
        if ($data['page_count'] >= 1) {
            for ($i = 1; $i <= $data['page_count']; $i++) {
                $res['data'] = [];
                $res['data'] = $commission_account->getViewList($i, $page_size, $condition, 'nmar.create_time desc')['data'];
                if (!empty($res['data'])) {
                    foreach ($res['data'] as $k => $v) {
                        if ($v['from_type'] != 1 && $v['from_type'] != 2 && $v['from_type'] != 3 && $v['from_type'] != 22 && $v['from_type'] != 25 && $v['from_type'] != 26) {
                            if ($v["from_type"] == 10) {
                                $status_name = '提现到微信，成功';
                            }
                            if ($v["from_type"] == 9) {
                                $status_name = '提现到银行卡，成功';
                            }
                            if ($v["from_type"] == 11) {
                                $status_name = '提现到支付宝，成功';
                            }
                            if ($v["from_type"] == -11) {
                                $status_name = '提现到支付宝，打款失败';
                            }
                            if ($v["from_type"] == -9) {
                                $status_name = '提现到银行卡，打款失败';
                            }
                            if ($v["from_type"] == -10) {
                                $status_name = '提现到微信，打款失败';
                            }
                            if ($v["from_type"] == 12) {
                                $status_name = '提现到银行卡，待审核';
                            }
                            if ($v["from_type"] == 13) {
                                $status_name = '提现到微信，待审核';
                            }
                            if ($v["from_type"] == 14) {
                                $status_name = '提现到支付宝，待审核';
                            }
                            if ($v["from_type"] == 15) {
                                $status_name = '提现到账户余额，待打款';
                            }
                            if ($v["from_type"] == 6) {
                                $status_name = '提现到账户余额，待审核';
                            }
                            if ($v["from_type"] == 5) {
                                $status_name = '提现到微信，待打款';
                            }
                            if ($v["from_type"] == 4) {
                                $status_name = '提现到账户余额，成功';
                            }
                            if ($v["from_type"] == 7) {
                                $status_name = '提现到银行卡，待打款';
                            }
                            if ($v["from_type"] == 8) {
                                $status_name = '提现到支付宝，待打款';
                            }
                            if ($v["from_type"] == 16) {
                                $status_name = '提现到微信，已拒绝';
                            }
                            if ($v["from_type"] == 17) {
                                $status_name = '提现到支付宝，已拒绝';
                            }
                            if ($v["from_type"] == 18) {
                                $status_name = '提现到账户余额，已拒绝';
                            }
                            if ($v["from_type"] == 19) {
                                $status_name = '提现到微信，审核不通过';
                            }
                            if ($v["from_type"] == 20) {
                                $status_name = '提现到支付宝，审核不通过';
                            }
                            if ($v["from_type"] == 21) {
                                $status_name = '提现到账户余额，不通过';
                            }
                            if ($v["from_type"] == 23) {
                                $status_name = '提现到银行卡，已拒绝';
                            }
                            if ($v["from_type"] == 24) {
                                $status_name = '提现到银行卡，审核不通过';
                            }
                            $res['data'][$k]['type_name'] = $status_name;
                            $res['data'][$k]['change_money'] = (-1) * (abs($res['data'][$k]['commission']) + abs($res['data'][$k]['tax']));
                        } else {
                            if ($v['from_type'] == 1) {
                                $res['data'][$k]['type_name'] = '订单完成';
                                $res['data'][$k]['commission'] = '+' . abs($res['data'][$k]['commission']);
                            }
                            if ($v['from_type'] == 2) {
                                $res['data'][$k]['type_name'] = '订单退款完成';
                                $res['data'][$k]['commission'] = '-' . abs($res['data'][$k]['commission']);
                            }
                            if ($v['from_type'] == 3) {
                                $res['data'][$k]['type_name'] = '订单支付完成';
                                $res['data'][$k]['commission'] = '+' . abs($res['data'][$k]['commission']);
                            }
                            if ($v['from_type'] == 22) {
                                $res['data'][$k]['type_name'] = '下级分销商升级，获得推荐奖';
                                $res['data'][$k]['commission'] = '+' . abs($res['data'][$k]['commission']);
                            }
                            if ($v['from_type'] == 25) {
                                $list['data'][$k]['type_name'] = '下级购买会员卡';
                                $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                            }
                            if ($v['from_type'] == 26) {
                                $list['data'][$k]['type_name'] = '下级续费会员卡';
                                $list['data'][$k]['commission'] = '+' . abs($list['data'][$k]['commission']);
                            }
                            $res['data'][$k]['change_money'] = $res['data'][$k]['commission'];
                        }
                        if (empty($res['data'][$k]['user_name'])) {
                            $res['data'][$k]['user_name'] = $res['data'][$k]['nick_name'];
                        }
                        $res['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                        $res['data'][$k]['user_name'] = $member->removeEmoji($res['data'][$k]['user_name']);
                        $res['data'][$k]["change_money"] = '¥' . $res['data'][$k]["change_money"];
                        $res['data'][$k]["tax"] = '¥' . $res['data'][$k]["tax"];
                    }
                    unset($v);
                    $list['data'] = array_merge($list['data'], $res['data']);
                }
            }
        }
        return $list;
    }
    /**
     * 根据uid获取分销商等级id
     */
    public function getDistributorLevelId($uid)
    {
        $member = new VslMemberModel();
        $distributor_level_id = $member->Query(['uid' => $uid], 'distributor_level_id')[0];
        return $distributor_level_id;
    }
    /**
     * 获取活动分销设置
     * type 类型 seckill  bargain  groupshopping presell
     */
    public function getActiveRule($type = '', $website_id = '')
    {

        if (empty($type) || empty($website_id)) {
            return false;
        }
        $active_status = getAddons($type, $website_id);
        if ($active_status != 1) {
            return false; //应用未开启
        }
        $addonsConfigService = new AddonsConfigService();
        $active_value =  $addonsConfigService->getAddonsConfig($type, $website_id, 0, 1);
        return $active_value;
    }
    /**
     * 获取分销上级列表
     * purchase_type 1开启内购
     * buyer_id 购买者uid
     * pattern 分销层级 1-3
     */
    public function getPatternCommissionUid($pattern, $buyer_id, $purchase_type)
    {
        $memberModel = new VslMemberModel();
        $distributorA_info = array();
        $distributorB_info = array();
        $distributorC_info = array();
        $member_info = $memberModel->getInfo(['uid' => $buyer_id], 'uid,isdistributor,referee_id,distributor_level_id');

        $has_commission = 0;
        if ($purchase_type == 1 && $member_info['isdistributor'] == 2) {
            //本人是分销商 则本人是一级，不是则上级才是一级
            $distributorA_info = $member_info;
        }else{
            if ($member_info['referee_id'] > 0) {
                $distributorA_info = $memberModel->getInfo(['uid' => $member_info['referee_id']], 'uid,isdistributor,referee_id,distributor_level_id');
            }
        }
        if ($distributorA_info && $distributorA_info['isdistributor'] == 2) {
            $has_commission = 1;
        }
        //获取2级
        if ($pattern >= 2 && $distributorA_info['referee_id'] > 0) {
            $distributorB_info = $memberModel->getInfo(['uid' => $distributorA_info['referee_id']], 'uid,isdistributor,referee_id,distributor_level_id');
            if ($distributorB_info && $distributorB_info['isdistributor'] == 2) {
                $has_commission = 1;
            }
        }
        //获取3级
        if ($pattern >= 3 && $distributorB_info && $distributorB_info['referee_id'] > 0) {
            $distributorC_info = $memberModel->getInfo(['uid' => $distributorB_info['referee_id']], 'uid,isdistributor,referee_id,distributor_level_id');
            if ($distributorC_info && $distributorC_info['isdistributor'] == 2) {
                $has_commission = 1;
            }
        }
        return ['distributorA_info' => $distributorA_info, 'distributorB_info' => $distributorB_info, 'distributorC_info' => $distributorC_info, 'has_commission' => $has_commission];
    }
    /**
     * 增加绑定上下级记录
     */
    public function addRefereeLog($uid, $referee_id, $website_id, $shop_id = 0, $org_referee_id = 0, $admin_uid = 0, $source = 1)
    {
        $distributorRefereeLogModel = new VslDistributorRefereeLogModel();
        $check_info = $distributorRefereeLogModel->getInfo(['uid' => $uid, 'website_id' => $website_id], '*');
        // 上三级关系链需要记录与否 --  获取上2级 上3级并记录 --
        $referee_id2 = 0;
        $referee_id3 = 0;
        $memberModel = new VslMemberModel();
        $user_info = $memberModel->getInfo(['uid' => $referee_id], 'referee_id');
        if ($user_info && $user_info['referee_id']) {
            $referee_id2 = $user_info['referee_id'];
            $user_info3 = $memberModel->getInfo(['uid' => $referee_id2], 'referee_id');
            if ($user_info3 && $user_info3['referee_id']) {
                $referee_id3 = $user_info3['referee_id'];
            }
        }

        if ($check_info) {
            $data = [
                'uid' => $uid,
                'referee_id' => $referee_id,//一级关系
                 'referee_id2' => $referee_id . ',' . $referee_id2, //二级关系
                 'referee_id3' => $referee_id . ',' . $referee_id2 . ',' . $referee_id3, //三级关系
                'website_id' => $website_id,
                'shop_id' => $shop_id,
                'add_time' => time(),
            ];
            if ($check_info['referee_id'] != $referee_id) {
                $distributorRefereeLogModel->save($data, ['uid' => $uid, 'website_id' => $website_id]);
            }
        } else {
            $data = [
                'uid' => $uid,
                'referee_id' => $referee_id,
                'referee_id2' => $referee_id . ',' . $referee_id2,
                'referee_id3' => $referee_id . ',' . $referee_id2 . ',' . $referee_id3,
                'website_id' => $website_id,
                'shop_id' => $shop_id,
                'add_time' => time(),
            ];
            $distributorRefereeLogModel->save($data);
        }
        //添加修改记录
        $memberRefereeLogModel = new VslMemberRefereeLogModel();
        //添加修改记录
        $log = [
            'uid' => $uid,
            'org_referee_id'=> $org_referee_id,
            'referee_id' => $referee_id,
            'update_time' => date('Y-m-d H:i:s'),
            'create_uid'=> $admin_uid,
            'source'=> $source
        ];
        $memberRefereeLogModel->save($log);

        return;
    }
    /**
     * 分销数据排序信息查询
     */
    public function rangking($types, $times, $psize, $operation)
    {

        $rankinglists = [];
        switch ($types) {
            case 1:
                # 推荐榜
                $rankinglists = $this->getRefereeRang($times, $psize, $operation);
                break;
            case 2:
                # 佣金榜
                $rankinglists = $this->getCommissionRang($times, $psize, $operation);
                break;
            case 3:
                # 积分榜
                $rankinglists = $this->getPointRang($times, $psize, $operation);
                break;
        }

        //获取当前用户信息
        $member = new Member();
        $member_info = $member->getMemberDetail();
        if (empty($member_info)) {
            $data['code'] = -1000;
            $data['message'] = '登录信息已过期，请重新登录!';
            return json($data);
        }
        // 头像
        if (!empty($member_info['user_info']['user_headimg'])) {
            $member_img = getApiSrc($member_info['user_info']['user_headimg']);
        } else {
            $member_img = '0';
        }
        $user = array(); //本人信息

        if ($rankinglists) {
            $user_model = new UserModel();
            foreach ($rankinglists as $key => $value) {
                //后台 需要3种数据
                if ($operation == 1 && $types == 1) {
                    $user_commission = $this->getCommissionRang($times, $psize, $operation, $value['uid']);
                    $value['commissions'] =  $user_commission ? $user_commission[0]['commissions'] : 0;
                    $user_points = $this->getPointRang($times, $psize, $operation, $value['uid']);
                    $value['points'] =  $user_points ? $user_points[0]['points'] : 0;
                } elseif ($operation == 1 && $types == 2) {
                    $user_children_count = $this->getRefereeRang($times, $psize, $operation, $value['uid']);
                    $value['children_count'] =  $user_children_count ? $user_children_count[0]['children_count'] : 0;
                    $user_points = $this->getPointRang($times, $psize, $operation, $value['uid']);
                    $value['points'] =  $user_points ? $user_points[0]['points'] : 0;
                } elseif ($operation == 1 && $types == 3) {
                    $user_commission = $this->getCommissionRang($times, $psize, $operation, $value['uid']);
                    $value['commissions'] =  $user_commission ? $user_commission[0]['commissions'] : 0;
                    $user_children_count =  $this->getRefereeRang($times, $psize, $operation, $value['uid']);
                    $value['children_count'] =  $user_children_count ? $user_children_count[0]['children_count'] : 0;
                }
                $user_info = $user_model->getInfo(['uid' => $value['uid']], 'user_headimg,user_name,nick_name');
                $rankinglists[$key]['ranking'] = $key + 1; //排名
                $rankinglists[$key]['uid'] = $value['uid']; //排名
                $rankinglists[$key]['user_headimg'] = getApiSrc($user_info['user_headimg']); //排名
                $rankinglists[$key]['user_name'] = $user_info['user_name']; //排名
                $rankinglists[$key]['nick_name'] = $user_info['nick_name']; //排名
                if($operation == 0){
                    $rankinglists[$key]['total'] = $types == 1 ? intval($value['children_count']) : 0; //总数
                    $rankinglists[$key]['points'] = $types == 3 ? $value['points'] : 0; //总数
                    $rankinglists[$key]['commissions'] = $types == 2 ? $value['commissions'] : 0; //总数
                }else{
                    $rankinglists[$key]['total'] = intval($value['children_count']); //总数
                    $rankinglists[$key]['points'] = $value['points']; //总数
                    $rankinglists[$key]['commissions'] = $value['commissions']; //总数
                }

                if ($value['uid'] == $this->uid) {
                    $user = $rankinglists[$key];
                }
                if (($rankinglists[$key]['total'] <= 0 && $types == 1) || ($rankinglists[$key]['commissions'] <= 0 && $types == 2) || ($rankinglists[$key]['points'] <= 0 && $types == 3)) {
                    unset($rankinglists[$key]);
                }
            }
            if (empty($user)) {
                $user = $user_model->getInfo([
                    'uid' => $this->uid
                ], 'user_headimg,user_name,nick_name');
                $my_commission = $this->getCommissionRang($times, $psize, $operation, $this->uid);
                $rankingusers['commissions'] =  $my_commission ? $my_commission[0]['commissions'] : 0;
                $my_points = $this->getPointRang($times, $psize, $operation, $this->uid);
                $rankingusers['points'] =  $my_points ? $my_points[0]['points'] : 0;
                $my_children_count =  $this->getRefereeRang($times, $psize, $operation, $this->uid);
                $rankingusers['children_count'] =  $my_children_count ? $my_children_count[0]['children_count'] : 0;
                $user['ranking'] = '10+'; //排名
                $user['user_headimg'] = getApiSrc($user['user_headimg']); //排名
                $user['ranking'] = 0;
                $user['total'] = $types == 1 ? intval($rankingusers['children_count']) : 0;
                $user['points'] = $types == 3 ? $rankingusers['points'] : 0;
                $user['commissions'] = $types == 2 ? $rankingusers['commissions'] : 0;
                //获取本人当前信息
            }
            if (empty($rankinglists)) {
                $data['code'] = 1;
                $data['data']['rankinglists'] = [];
                $data['data']['user'] = $user;
                $data['message'] = "获取成功,暂未有排名信息";
            } else {
                $data['data']['rankinglists'] = $rankinglists; //列表
                $data['data']['user'] = $user; //本人
                $data['code'] = 1;
                $data['message'] = "获取成功";
            }
        } else {
            $data['code'] = 1;
            $data['data']['rankinglists'] = [];
            $data['data']['user'] = $user;
            $data['message'] = "获取成功,暂未有排名信息";
        }
        return $data;
    }
    //推荐信息 新 按照绑定关系记录表查询 按直推一级推荐人数排序
    public function getRefereeRang($times, $psize, $operation = 0, $uid = 0)
    {
        //获取本月起始时间戳和结束时间戳
        $beginThismonth = mktime(0, 0, 0, date('m'), 1, date('Y'));
        $endThismonth = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
        //获取本年的起始时间:
        $begin_year = strtotime(date("Y", time()) . "-1" . "-1"); //本年开始
        $end_year = strtotime(date("Y", time()) . "-12" . "-31"); //本年结束
        if ($times == "month") {
            $map['reg_time'] = ['between', [$beginThismonth, $endThismonth]];
        } elseif ($times == "year") {
            $map['reg_time'] = ['between', [$begin_year, $end_year]];
        }
        $map['referee_id'] = ['>', 0];
        $map['website_id'] = $this->website_id;
        $map['isdistributor'] = 2;
        if ($operation == 1 && $uid > 0) {
            $map['referee_id'] = $uid;
        }
        $Query = Db::table('vsl_member')
            ->field('count(1) as children_count,referee_id as uid')
            ->where($map)
            ->group('referee_id')
            ->order('children_count desc')
            ->limit($psize)
            ->select();
        return $Query;
    }
    //佣金信息 是否包含支付完成的

    // 增加佣金的途径

    #1代表订单完成  22分销商等级升级获得推荐奖   新增 25下级购买会员卡26下级续费会员�����
    #2代表订单退款成功 3代表订单支付成功

    public function getCommissionRang($times, $psize, $operation = 0, $uid = 0)
    {
        //获取本月起始时间戳和结束时间戳
        $beginThismonth = mktime(0, 0, 0, date('m'), 1, date('Y'));
        $endThismonth = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
        //获取本年的起始时间:
        $begin_year = strtotime(date("Y", time()) . "-1" . "-1"); //本年开始
        $end_year = strtotime(date("Y", time()) . "-12" . "-31"); //本年结束
        $condition = "website_id = " . $this->website_id;
        if ($operation == 1 && $uid > 0) {
            $condition .= " AND `uid` = " . $uid;
        }
        if ($times == "month") {
            $condition .= ' AND create_time > ' . $beginThismonth . ' AND create_time <= ' . $endThismonth;
        } elseif ($times == "year") {
            $condition .= ' AND create_time > ' . $begin_year . ' AND create_time <= ' . $end_year;
        }
        $c_sql = "select
        uid,(from_type_2 + from_type_3 + from_type_5 + from_type_6 - from_type_4) as commissions FROM
        (
        select
        uid,
        sum(case when from_type=3 then `commission` else 0 end) as from_type_2,
        sum(case when from_type=22 then `commission` else 0 end) as from_type_3,
        sum(case when from_type=25 then `commission` else 0 end) as from_type_5,
        sum(case when from_type=26 then `commission` else 0 end) as from_type_6,
        sum(case when from_type=2 then `commission` else 0 end) as from_type_4
        from vsl_distributor_account_records where " . $condition . " GROUP BY uid
        ) as t2 ORDER BY commissions desc LIMIT 0 " . "," . $psize;


        $rankinglists = Db::query($c_sql); //排行信息
        return $rankinglists;
    }
    //积分信息
    public function getPointRang($times, $psize, $operation = 0, $uid = 0)
    {
        //获取本月起始时间戳和结束时间戳
        $beginThismonth = mktime(0, 0, 0, date('m'), 1, date('Y'));
        $endThismonth = mktime(23, 59, 59, date('m'), date('t'), date('Y'));
        //获取本年的起始时间:
        $begin_year = strtotime(date("Y", time()) . "-1" . "-1"); //本年开始
        $end_year = strtotime(date("Y", time()) . "-12" . "-31"); //本年结束
        $mRecords = new VslMemberAccountRecordsModel();
        $orderby = 'points desc';
        $group = 'uids';
        $field = 'sum(number) as points,uid as uids';
        $condition = array();
        $condition['website_id'] = $this->website_id;
        if ($operation == 1 && $uid > 0) {
            $condition['uid'] = $uid;
        }
        // $condition['no.from_type'] = 30; //订单完成积分记录
        $condition['account_type'] = 1; //订单完成积分记录
        if ($times == "month") {
            $condition["create_time"][] = [
                ">",
                $beginThismonth
            ];
            $condition["create_time"][] = [
                "<=",
                $endThismonth
            ];
        } elseif ($times == "year") {
            $condition["create_time"][] = [
                ">",
                $begin_year
            ];
            $condition["create_time"][] = [
                "<=",
                $end_year
            ];
        }
        $rankinglists_all = $mRecords->getRecordsPonitQuery(1, $psize, $condition, $orderby, $group, $field);
        $rankinglists = array();
        foreach ($rankinglists_all['data'] as $value) {
            $rdata = array(
                'points' => $value['points'],
                'uid' => $value['uids'],
            );
            array_push($rankinglists, $rdata);
        }
        return $rankinglists;
    }
    /**
     * 获取流水状态名称
     */
    public function getStatusName($from_type)
    {
        switch ($from_type) {
            case 10:
                $status_name = '提现到微信，成功';
                break;
            case 9:
                $status_name = '提现到银行卡，成功';
                break;
            case 11:
                $status_name = '提现到支付宝，成功';
                break;
            case -11:
                $status_name = '提现到支付宝，打款失败';
                break;
            case -9:
                $status_name = '提现到银行卡，打款失败';
                break;
            case -10:
                $status_name = '提现到微信，打款失败';
                break;
            case 12:
                $status_name = '提现到银行卡，待审核';
                break;
            case 13:
                $status_name = '提现到微信，待审核';
                break;
            case 14:
                $status_name = '提现到支付宝，待审核';
                break;
            case 15:
                $status_name = '提现到账户余额，待打款';
                break;
            case 6:
                $status_name = '提现到账户余额，待审核';
                break;
            case 5:
                $status_name = '提现到微信，待打款';
                break;
            case 4:
                $status_name = '提现到账户余额，成功';
                break;
            case 7:
                $status_name = '提现到银行卡，待打款';
                break;
            case 8:
                $status_name = '提现到支付宝，待打款';
                break;
            case 16:
                $status_name = '提现到微信，已拒绝';
                break;
            case 17:
                $status_name = '提现到支付宝，已拒绝';
                break;
            case 18:
                $status_name = '提现到账户余额，已拒绝';
                break;
            case 19:
                $status_name = '提现到微信，审核不通过';
                break;
            case 20:
                $status_name = '提现到支付宝，审核不通过';
                break;
            case 21:
                $status_name = '提现到账户余额，不通过';
                break;
            case 23:
                $status_name = '提现到银行卡，已拒绝';
                break;
            case 24:
                $status_name = '提现到银行卡，审核不通过';
                break;
            case 1:
                $status_name = '订单完成';
                break;
            case 2:
                $status_name = '订单退款完成';
                break;
            case 3:
                $status_name = '订单支付完成';
                break;
            case 22:
                $status_name = '下级分销商升级，获得推荐奖';
                break;
            case 25:
                $status_name = '下级购买会员卡';
                break;
            case 26:
                $status_name = '下级续费会员卡';
                break;
        }
        return $status_name;
    }
    /**
     * 获取分红升下一级详情
     * types 1 团队分红 2 区域分红 3 全球分红 4分销
     */
    public function upLevelDetail($types, $uid, $website_id, $member)
    {
        switch ($types) {
            case 1:
                $teamServer = new TeamBonus();
                $result = $teamServer->upTeamLevelDetail($uid, $website_id, $member);
                break;
            case 2:
                $areaServer = new AreaBonus();
                $result = $areaServer->upAreaLevelDetail($uid, $website_id, $member);
                break;
            case 3:
                $globalServer = new GlobalBonus();
                $result = $globalServer->upGlobalLevelDetail($uid, $website_id, $member);
                break;
            case 4:
                $result = $this->upCommissionLevelDetail($uid, $website_id, $member);
                break;
        }
        return $result;
    }
    /**
     * 升级条件
     */
    public function levelConditions($uid = '', $level_infos = [])
    {
        //全网分销
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        $member = new VslMemberModel();
        $conditions = explode(',', $level_infos['upgradeconditions']);
        $result = [];
        $getDistributorInfo = $this->getDistributorLowerInfo($uid); //当前队长的详情信息
        $agent = $member->getInfo(['uid' => $uid], '*');
        $distributor_name = '';
        //获取分销文案设置
        $text = $this->getAgreementSite($agent['website_id']);
        if ($level_infos && $level_infos['upgrade_level']) {
            //获取指定分销等级名称
            $distributor_info = $this->getDistributorLevelInfo($level_infos['upgrade_level']);
            if ($distributor_info) {
                $distributor_name = $distributor_info['level_name'];
            }
            //查看是否降级 变更条件为降级时间之后开始计算 'become_distributor_time'=>[">=",$distributor['down_level_time']]
            if ($agent['down_level_time']) { //发生过降级
                $low_number = $member->getCount(['distributor_level_id' => $level_infos['upgrade_level'], 'referee_id' => $uid, 'website_id' => $agent['website_id'], 'become_distributor_time' => [">", $agent['down_level_time']]]); //该等级指定推荐等级人数
            } else {
                $low_number = $member->getCount(['distributor_level_id' => $level_infos['upgrade_level'], 'referee_id' => $uid, 'website_id' => $agent['website_id']]); //该等级指定推荐等级人数
            }
        } else {
            $low_number = 0;
        }
        //判断是否购买过指定商品
        $goods_info = [];
        $goods_name = '';
        if ($level_infos['goods_id']) {
            //获取商品名称
            $goods_info = $this->goods_ser->getGoodsDetailById($level_infos['goods_id'], 'goods_name');
            if ($goods_info) {
                $goods_name = $goods_info['goods_name'];
            }
            $goods_id = $order_goods->Query(['goods_id' => $level_infos['goods_id'], 'buyer_id' => $uid], 'order_id');

            if ($goods_id && $agent['down_level_time']) { //发生降级后 订单完成时间需大于降级时间
                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4, 'finish_time' => [">", $agent['down_level_time']]], '*');
            } elseif ($goods_id) {
                $goods_info = $order->getInfo(['order_id' => ['IN', implode(',', $goods_id)], 'order_status' => 4], '*');
            }
        }
        foreach ($conditions as $k1 => $v1) {
            switch ($v1) {
                case 7:
                    $edata = array();
                    $edata['condition_name'] = $text['team1'] . "满";
                    $edata['condition_type'] = 7;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['number1'];
                    $edata['number'] = $getDistributorInfo['number1'];
                    array_push($result, $edata);
                    break;
                case 8:
                    $edata = array();
                    $edata['condition_name'] = $text['team2'] . "满";
                    $edata['condition_type'] = 8;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['number2'];
                    $edata['number'] = $getDistributorInfo['number2'];
                    array_push($result, $edata);
                    break;
                case 9:
                    $edata = array();
                    $edata['condition_name'] = $text['team3'] . "满";
                    $edata['condition_type'] = 9;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['number3'];
                    $edata['number'] = $getDistributorInfo['number3'];
                    array_push($result, $edata);
                    break;
                case 10:
                    $edata = array();
                    $edata['condition_name'] = "团队分销商满";
                    $edata['condition_type'] = 10;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['number4'];
                    $edata['number'] = $getDistributorInfo['agentcount2'];
                    array_push($result, $edata);
                    break;
                case 11:
                    $edata = array();
                    $edata['condition_name'] = "下线会员数满";
                    $edata['condition_type'] = 11;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['number5'];
                    $edata['number'] = $getDistributorInfo['agentcount1'];
                    array_push($result, $edata);
                    break;
                case 12:
                    $edata = array();
                    $edata['condition_name'] = "下级";
                    $edata['condition_type'] = 12;
                    $edata['unit'] = "人";
                    $edata['distributor_name'] = $distributor_name;
                    $edata['up_number'] = $level_infos['level_number'];
                    $edata['number'] = $low_number;
                    array_push($result, $edata);
                    break;
                case 1:
                    $edata = array();
                    $edata['condition_name'] = "下线总人数满";
                    $edata['condition_type'] = 1;
                    $edata['unit'] = "人";
                    $edata['up_number'] = $level_infos['offline_number'];
                    $edata['number'] = $getDistributorInfo['agentcount'];
                    array_push($result, $edata);
                    break;
                case 2:
                    $edata = array();
                    $edata['condition_name'] = "分销订单金额满";
                    $edata['condition_type'] = 2;
                    $edata['unit'] = "元";
                    $edata['up_number'] = $level_infos['order_money'];
                    $edata['number'] = $getDistributorInfo['order_money'];
                    array_push($result, $edata);
                    break;
                case 3:
                    $edata = array();
                    $edata['condition_name'] = "分销订单数满";
                    $edata['condition_type'] = 3;
                    $edata['unit'] = "单";
                    $edata['up_number'] = $level_infos['order_number'];
                    $edata['number'] = $getDistributorInfo['agentordercount'];
                    array_push($result, $edata);
                    break;
                case 4:
                    $edata = array();
                    $edata['condition_name'] = "商城消费金额满";
                    $edata['condition_type'] = 4;
                    $edata['unit'] = "元";
                    $edata['up_number'] = $level_infos['selforder_money'];
                    $edata['number'] = $getDistributorInfo['selforder_money'];
                    array_push($result, $edata);
                    break;
                case 5:
                    $edata = array();
                    $edata['condition_name'] = "商城消费订单满";
                    $edata['condition_type'] = 5;
                    $edata['unit'] = "元";
                    $edata['up_number'] = $level_infos['selforder_number'];
                    $edata['number'] = $getDistributorInfo['selforder_number'];
                    array_push($result, $edata);
                    break;
                case 6:
                    $edata = array();
                    $edata['condition_name'] = $goods_name;
                    $edata['condition_type'] = 6;
                    $edata['up_number'] = 1;
                    $edata['unit'] = "件";
                    $edata['number'] = 0;
                    if ($goods_info) {
                        $edata['number'] = 1;
                    }
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
    public function downlevelConditions($uid = '', $level_infos = [])
    {
        //全网分销
        $member = new VslMemberModel();
        $order = new VslOrderModel();
        $order_goods = new VslOrderGoodsModel();
        $conditions = explode(',', $level_infos['downgradeconditions']);
        $result = array();
        //获取最长时间天数
        $maxdays = max($level_infos['team_number_day'], $level_infos['team_money_day'], $level_infos['self_money_day']);
        //获取会员升级时间
        $agent = $member->getInfo(['uid' => $uid], '*');
        $starttimes = $agent['up_level_time'] ? $agent['up_level_time'] : $agent['become_distributor_time'];
        $starttime = date("m-d", $starttimes);
        $endtime = date("m-d", $starttimes + $maxdays * 24 * 60 * 60);
        //降级类型
        foreach ($conditions as $k1 => $v1) {
            switch ($v1) {
                case 1:
                    $team_number_day = $level_infos['team_number_day'];
                    $real_level_time = $member->getInfo(['uid' => $uid], 'up_level_time')['up_level_time'] + $team_number_day * 24 * 3600;

                    $getAgentInfo1 = $this->getDistributorInfos($uid, $team_number_day);

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
                    $real_level_time = $member->getInfo(['uid' => $uid], 'up_level_time')['up_level_time'] + $team_money_day * 24 * 3600;

                    $getAgentInfo2 = $this->getDistributorInfos($uid, $team_money_day);
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
                    $real_level_time = $member->getInfo(['uid' => $uid], 'up_level_time')['up_level_time'] + $self_money_day * 24 * 3600;

                    $getAgentInfo3 = $this->getDistributorInfos($uid, $self_money_day);
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
     * 分销升级详情
     */
    public function upCommissionLevelDetail($uid, $website_id, $member)
    {
        //获取所有团队等级
        $teamlist = $this->getDistributorLevelList(1, '', ['website_id' => $website_id], 'weight asc');
        $tlist = [];
        foreach ($teamlist['data'] as $key => $value) {
            //佣金+积分
            $rdata['level_name'] = $value['level_name'];
            $rdata['recommend_type'] = $value['recommend_type'];
            $rdata['commission1'] = $value['commission1'];
            $rdata['commission2'] = $value['commission2'];
            $rdata['commission3'] = $value['commission3'];
            $rdata['commission_point1'] = $value['commission_point1'];
            $rdata['commission_point2'] = $value['commission_point2'];
            $rdata['commission_point3'] = $value['commission_point3'];
            $rdata['commission11'] = $value['commission11'];
            $rdata['commission22'] = $value['commission22'];
            $rdata['commission33'] = $value['commission33'];
            $rdata['commission_point11'] = $value['commission_point11'];
            $rdata['commission_point22'] = $value['commission_point22'];
            $rdata['commission_point33'] = $value['commission_point33'];
            //推荐奖 佣金+积分
            $rdata['recommend1'] = $value['recommend1'];
            $rdata['recommend2'] = $value['recommend2'];
            $rdata['recommend3'] = $value['recommend3'];
            $rdata['recommend_point1'] = $value['recommend_point1'];
            $rdata['recommend_point2'] = $value['recommend_point2'];
            $rdata['recommend_point3'] = $value['recommend_point3'];
            array_push($tlist, $rdata);
            if ($value['id'] == $member['distributor_level_id']) {
                $user['level_name'] = $value['level_name'];
            }
        }
        //获取升级进度
        $level = new DistributorLevelModel();
        //获取高等级
        $level_weight = $level->Query(['id' => $member['distributor_level_id']], 'weight'); //当前队长的等级权重
        $level_weights = $level->Query(['weight' => ['>', implode(',', $level_weight)], 'website_id' => $website_id], 'weight'); //当前队长的等级权重的上级权重
        if ($level_weights) {
            sort($level_weights);
            $level_infos = $level->getInfo(['weight' => $level_weights[0], 'website_id' => $website_id]); //比当前队长等级的权重高的等级信息
            if ($level_infos['upgradetype'] == 1) { //是否开启自动升级
                //获取当前升级进度
                $levelCondition = $this->levelConditions($uid, $level_infos, 4);
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

        //获取降级进度
        $down_level_weights = $level->Query(['weight' => ['<', implode(',', $level_weight)], 'website_id' => $website_id], 'weight'); //分红商的等级权重的下级权重

        if ($down_level_weights) {
            //存在低等级 获取当前等级降级信息
            $level_infos = $level->getInfo(['weight' => $level_weight[0], 'website_id' => $website_id], '*');
            if ($level_infos['downgradetype'] == 1 && $level_infos['downgradeconditions']) { //是否开启自动降级并且有降级条件
                $downlevelCondition = $this->downlevelConditions($uid, $level_infos, 4);
                if ($downlevelCondition) {
                    $down_level_infos = $level->getInfo(['weight' => $down_level_weights[0], 'website_id' => $website_id], 'level_name');
                    $downlevelCondition['levelname'] = $down_level_infos['level_name'];
                }
            } else {
                $downlevelCondition = [];
            }
        } else {
            $downlevelCondition = [];
        }
        return ['levelCondition' => $levelCondition, 'downlevelCondition' => $downlevelCondition, 'user' => $user, 'tlist' => $tlist];
    }
    /*
    * 订单完成获得积分
    */
    public function addBeautifulMemberPoint($beautiful_point, $uid, $data_id, $website_id, $member, $member_point)
    {
        $point_count = $member->getInfo(['uid' => $uid], '*'); //会员账户
        //会员账户积分改变
        if ($point_count) {
            $account_data1 = array(
                'beautiful_point' => $point_count['beautiful_point'] + $beautiful_point,
                // 'member_sum_point' => $point_count['member_sum_point'] + $beautiful_point
            );
            $member->save($account_data1, ['uid' => $uid]);
        } else {
            $account_data2 = array(
                'beautiful_point' => $point_count['beautiful_point'] + $beautiful_point,
                // 'member_sum_point' => $point_count['member_sum_point'] + $beautiful_point,
                'uid' => $uid
            );
            $member->save($account_data2);
        }
        $data_point = array(
            'records_no' => getSerialNo(),
            'uid' => $uid,
            'account_type' => 3,
            'number'   => $beautiful_point,
            'data_id' => $data_id,
            'from_type' => 130,
            'text' => '分销订单完成，美丽分增加',
            'create_time' => time(),
            'website_id' => $website_id
        );
        $member_point->save($data_point); //添加会员积分流水
    }
    /*
    * 订单完成获得积分
    */
    public function ajustBeautifulPoint($recommend_uid,$uid,$beautiful_point,$website_id,$from_type, $text='')
    {
        $member = new VslMemberAccountModel();
        $member_point = new VslMemberAccountRecordsModel();
        $point_count = $member->getInfo(['uid' => $recommend_uid], '*'); //会员账户
        //会员账户积分改变
        if ($point_count) {
            $account_data1 = array(
                'beautiful_point' => $point_count['beautiful_point'] + $beautiful_point,
                // 'member_sum_point' => $point_count['member_sum_point'] + $beautiful_point
            );
            $member->save($account_data1, ['uid' => $recommend_uid]);
        } else {
            $account_data2 = array(
                'beautiful_point' => $point_count['beautiful_point'] + $beautiful_point,
                // 'member_sum_point' => $point_count['member_sum_point'] + $beautiful_point,
                'uid' => $recommend_uid
            );
            $member->save($account_data2);
        }
        $data_point = array(
            'records_no' => getSerialNo(),
            'uid' => $recommend_uid,
            'account_type' => 3,
            'number'   => $beautiful_point,
            'data_id' => $uid,
            'from_type' => $from_type,
            'text' => $text,
            'create_time' => time(),
            'website_id' => $website_id
        );
        $member_point->save($data_point); //添加会员积分流水
        return;
    }
    /**
     * 导入成为分销商等级
     */
    public function memberImportCom($uid,$level_id=0,$bonus_level_id=0)
    {
        $member = new VslMemberModel();
        $distributor = $member->getInfo(['uid' => $uid], 'isdistributor,website_id,is_team_agent');
        $website_id = $distributor['website_id'];
        if ($distributor['isdistributor'] != 2 && $level_id > 0) { //判断是否是分销商
            $result = [];
            $ratio = 0;
            $extend_code = $this->create_extend();
            $member->save(['isdistributor' => 2, 'distributor_level_id' => $level_id, 'extend_code' => $extend_code, "apply_distributor_time" => time(), 'become_distributor_time' => time()], ['uid' => $uid]);
            $account = new VslDistributorAccountModel();
            $account->save(['website_id' => $website_id, 'uid' => $uid]);
        }
        if($distributor['is_team_agent']!=2 && $bonus_level_id > 0 ){//判断是否是队长
            $member = new VslMemberModel();
            $data = array(
                "is_team_agent" => 2,
                "team_agent_level_id" => $bonus_level_id,
                "apply_team_agent_time" => time(),
                "become_team_agent_time" => time(),
            );
            $member->save($data, ['uid' => $uid]);
            $account = new VslBonusAccountModel();
            $account->save(['website_id' => $website_id, 'uid' => $uid, 'from_type' => 3]);
        }
    }
    /*
     * 无条件成为分销商的下线
     */
    public function becomeLowerByOrder($uid,$referee_id)
    {
        $member = new VslMemberModel();
        $distributor = $member->getInfo(['uid' => $uid], 'website_id,isdistributor');
        if(!$distributor){
            return -1;
        }
        if($distributor['isdistributor'] == 2){
            return -2;
        }
        $distributor['default_referee_id'] = $referee_id;
        $info = $this->getDistributionSite($distributor['website_id']);
        //变更  购买商品，并付款 可变更上下级  && $distributor['referee_id']==null
        if ($info['is_use'] == 1 && $info['referee_check'] == 1 ) {
            $lower_id = $member->Query(['referee_id' => $uid], '*');
            if ($lower_id && in_array($distributor['default_referee_id'], $lower_id)) {
                return 1;
            }
            runhook("Notify", "sendCustomMessage", ['messageType' => 'new_offline', "uid" => $uid, "add_time" => time(), 'referee_id' => $distributor['default_referee_id']]); //成为下线通知
            $member->save(['referee_id' => $distributor['default_referee_id'], 'default_referee_id' => null], ['uid' => $uid]);
            $this->addRefereeLog($uid, $distributor['default_referee_id'], $distributor['website_id'], 0,0,0,4);
            $this->updateDistributorLevelInfo($distributor['default_referee_id']);
            if (getAddons('globalbonus', $distributor['website_id'])) {
                $global = new GlobalBonus();
                $global->updateAgentLevelInfo($distributor['default_referee_id']);
                $global->becomeAgent($distributor['default_referee_id']);
            }
            if (getAddons('areabonus', $distributor['website_id'])) {
                $area = new AreaBonus();
                $area->updateAgentLevelInfo($distributor['default_referee_id']);
            }
            if (getAddons('teambonus', $distributor['website_id'])) {
                $team = new TeamBonus();
                $team->updateAgentLevelInfo($distributor['default_referee_id']);
                $team->becomeAgent($distributor['default_referee_id']);
            }
            return 2;
        }
        return 0;
    }

    /**
     * 完成订单，并结算订单佣金
     *
     * @param $uid
     * @return int|string
     */
    public function addDistributorCommissionMember($uid){
        $website_id = $this->website_id;
        $uids = $this->sort($uid);
        $lists = $this->sort_data($uids,'uid', 'referee_id', 'children', $uid);
        $res = $this->rec_list_files($uid, $lists);
        $ids = [];
        foreach ($res as $i){
            $ids[] = $i['uid'];
        }
        $event = new Events();
        $res = $event->checkOrdersComplete($website_id, $ids);
        return $res;
    }

    /**
     * 获取我的团队总业绩
     *
     * @param $uid
     * @return int
     */
    public function getTotalAmount($uid, $start_finish_date = null, $end_finish_date = null){
        $uids = $this->sort($uid);
        $lists = $this->sort_data($uids,'uid', 'referee_id', 'children', $uid);
        $res = $this->rec_list_member($uid, $lists);
        $amount = 0;
        if(count($res)){
            $ids = [];
            foreach ($res as $i){
                $ids[] = $i['uid'];
            }
            $condition['buyer_id'] = array('in', $ids);
            $condition['order_status'] = array('in', [3, 4]);

            if ($start_finish_date) {
                $condition['finish_time'][] = ['>=', $start_finish_date];
            }
            if ($end_finish_date) {
                $condition['finish_time'][] = ['<=', $end_finish_date];
            }
            $order_model = new VslOrderModel();
            $amount = $order_model->getSum($condition, 'order_money');
//            foreach ($order_lists as $value){
//                $amount += $value['order_money'];
//            }
        }
        return sprintf("%01.2f", $amount);
    }

    /**
     *  获取我的平级业绩
     *
     * @param $uid
     * @return int
     */
    public function getLevelAmount($uid, $start_finish_date = null, $end_finish_date= null){
        $uids = $this->sort($uid);
        $lists = $this->sort_data($uids,'uid', 'referee_id', 'children', $uid);
        $res = $this->rec_list_level($uid, $lists);
        $amount = 0;
        if(count($res)){
            foreach ($res as $level_uid){
                $amount += $this->getTotalAmount($level_uid, $start_finish_date, $end_finish_date);
            }
        }
        return sprintf("%01.2f", $amount);
    }

    /**
     * 过滤同个等级多个战略等级的情况
     *
     * @param $pid
     * @param $from
     * @return array
     *
     */
    public function rec_list_member($pid, $from)
    {
        $arr = [];
        foreach($from as $key=> $item) {
            if($item['distributor_level_id'] == 5 && $item['uid'] != $pid) {
                continue;
            }
            if(!isset($item['children'])){
                $arr[] = $item;
            }
            if(isset($item['children'])){
                $children = $item['children'];
                unset($item['children']);
                $arr[] = $item;
                $arr = array_merge($arr, $this->rec_list_member($pid, $children));
            }
        }
        return $arr;
    }

    /**
     * 获取同个等级下多个战略
     *
     * @param $pid
     * @param $from
     * @return array
     *
     */
    public function rec_list_level($pid, $from)
    {
        $arr = [];
        foreach($from as $key=> $item) {
            if($item['distributor_level_id'] == 5 && $item['uid'] != $pid) {
                $arr[] = $item['uid'];
                continue;
            }
            if(isset($item['children'])){
                $arr = array_merge($arr, $this->rec_list_level($pid, $item['children']));
            }
        }
        return $arr;
    }
}
