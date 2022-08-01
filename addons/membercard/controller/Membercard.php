<?php

namespace addons\membercard\controller;

use addons\membercard\Membercard as baseMembercard;
use addons\membercard\model\VslBuyMembercardRecordsModel;
use addons\membercard\model\VslMembercardSpecModel;
use addons\membercard\server\Membercard as membercardServer;
use data\model\VslOrderPaymentModel;
use data\service\Member as MemberService;
use data\service\UnifyPay;
use data\service\WeixinCard;

class Membercard extends baseMembercard
{
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 保存基础设置
     */
    public function saveSetting()
    {
        try {
            $post_data = request()->post();

            //组装数据
            $save_data = [];
            $is_use = $post_data['is_use'];
            $save_data['membercard_logo'] = $post_data['membercard_logo'];
            $save_data['membercard_prefix'] = $post_data['membercard_prefix'];
            $save_data['content_title'] = $post_data['content_title'];
            $save_data['content'] = htmlspecialchars_decode($post_data['content']);
            $save_data['membercard'] = $post_data['membercard'];
            $save_data['plus'] = $post_data['plus'];
            $save_data['is_wxcard'] = $post_data['is_wxcard'];
            if ($post_data['wxcard_info']) {
                $save_data['wxcard_info'] = $post_data['wxcard_info'];
            }
            $AddonsConfig = new \data\service\AddonsConfig();
            $config_info = $AddonsConfig->getAddonsConfig('membercard', $this->website_id);
            if (!empty($config_info)) {
                $res = $AddonsConfig->updateAddonsConfig($save_data, "会员卡设置", $is_use, "membercard");
            } else {
                $res = $AddonsConfig->addAddonsConfig($save_data, "会员卡设置", $is_use, "membercard");
            }

            if ($res) {
                //如果开启了微信会员卡，把没有推送到微信的会员卡都推送到微信
                $membercard_mdl = new \addons\membercard\model\VslMembercardModel();
                $membercard_list = $membercard_mdl->getQuery(['website_id' => $this->website_id, 'wx_membercard_id' => ['=', '']], 'id,membercard_name', 'id asc');
                if ($membercard_list) {
                    foreach ($membercard_list as $k => $v) {
                        $membercard = new membercardServer();
                        $membercard->membercardPushToWx($v['id'], $v['membercard_name']);
                    }
                }
                $this->addUserLog('会员卡设置', $res);
                setAddons('membercard', $this->website_id, $this->instance_id);
            }

            return AjaxReturn($res);
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }

    /**
     * 新增或编辑充值送
     */
    public function addOrUpdateRechargeLevel()
    {
        $data = request()->post();

        $membercard = new membercardServer();
        $res = $membercard->addOrUpdateRechargeLevel($data);

        return AjaxReturn($res);
    }

    /**
     * 充值送列表
     */
    public function rechargeLevelList()
    {
        $page_index = request()->post('page_index', 1);

        $condition['website_id'] = $this->website_id;
        $order = 'create_time desc';

        $membercard = new membercardServer();
        $list = $membercard->rechargeLevelList($page_index, PAGESIZE, $condition, $order);

        return $list;
    }

    /**
     * 删除充值送
     */
    public function delRechargeLevel()
    {
        $id = request()->post('id', 0);

        $membercard = new membercardServer();
        $res = $membercard->delRechargeLevel($id);

        return AjaxReturn($res);
    }

    /**
     * 新增或编辑会员卡
     */
    public function addOrUpdateMembercard()
    {
        $data = request()->post();

        $membercard = new membercardServer();
        $res = $membercard->addOrUpdateMembercard($data);

        return AjaxReturn($res);
    }


    /**
     * 会员卡列表
     */
    public function membercardList()
    {
        $page_index = request()->post('page_index', '1');

        $condition = [
            'website_id' => $this->website_id
        ];

        $order = 'create_time desc';

        $membercard = new membercardServer();
        $list = $membercard->membercardList($page_index, PAGESIZE, $condition, $order);

        return $list;
    }

    /**
     * 删除会员卡
     */
    public function delMembercard()
    {
        $id = request()->post('id', 0);

        $membercard = new membercardServer();
        $res = $membercard->delMembercard($id);

        if($res < 0) {
            return AjaxReturn($res,[],'该卡已有会员使用，不能删除');
        }else{
            return AjaxReturn($res);
        }
    }

    /**
     * 领卡记录
     */
    public function memberMembercard()
    {
        $page_index = request()->post('page_index', 1);
        $user = request()->post('user', '');
        $membercard_no = request()->post('membercard_no', '');

        if ($membercard_no) {
            $condition['mu.membercard_no'] = $membercard_no;
        }

        if ($user) {
            if (is_numeric($user)) {
                $condition['u.uid|u.user_tel|m.mobile'] = array('like', '%' . $user . '%');
            } else {
                $condition['u.user_name|u.nick_name|m.member_name'] = array('like', '%' . $user . '%');
            }
        }

        $condition['mu.website_id'] = $this->website_id;
        $order = 'mu.id desc';

        $membercard = new membercardServer();
        $list = $membercard->memberMembercard($page_index, PAGESIZE, $condition, $order);

        return $list;
    }

    /**
     * 后台调整余额
     */
    public function adjustBalance()
    {
        $uid = request()->post('uid', 0);
        $num = request()->post('num', 0);
        $text = request()->post('text', '');

        if (empty($text)) {
            $text = '后台调整';
        }

        $membercard = new membercardServer();
        $res = $membercard->adjustBalance($uid, $num, $text, 63);
        $this->addUserLog("会员卡余额调整", $res);

        return AjaxReturn($res);
    }

    /**
     * 领卡记录导出Excel
     */
    public function memberMembercardDataExcel()
    {
        $page_index = request()->get('page_index', 1);
        $user = request()->get('user', '');
        $membercard_no = request()->get('membercard_no', '');
        $ids = request()->get('ids', '');

        if ($membercard_no) {
            $condition['mu.membercard_no'] = $membercard_no;
        }

        if ($user) {
            if (is_numeric($user)) {
                $condition['u.uid|u.user_tel|m.mobile'] = array('like', '%' . $user . '%');
            } else {
                $condition['u.user_name|u.nick_name|m.member_name'] = array('like', '%' . $user . '%');
            }
        }

        $condition['mu.website_id'] = $this->website_id;
        $order = 'mu.id desc';

        $membercard = new membercardServer();
        $list = $membercard->memberMembercard($page_index, PAGESIZE, $condition, $order);

        $xlsName = "领卡记录数据列表";
        $xlsCells = [
            1 => ['uid', '会员id'],
            2 => ['user_name', '用户名'],
            3 => ['user_tel', '手机号码'],
            4 => ['membercard_no', '会员卡号'],
            5 => ['membercard_balance', '会员卡余额'],
            6 => ['membercard_name', '会员卡名称'],
            7 => ['reg_time', '注册时间'],
        ];

        $xlsCell = [];
        if ($ids) {
            $ids = explode(',', $ids);
            foreach ($ids as $v) {
                if (!empty($xlsCells[$v])) $xlsCell[] = $xlsCells[$v];
            }
        }

        $data = [];
        $key = 0;
        foreach ($list["data"] as $k => $v) {
            $data[$key]["uid"] = $v['uid'];
            $data[$key]["user_name"] = $v["user_name"];
            $data[$key]["user_tel"] = $v["user_tel"];
            $data[$key]["membercard_no"] = $v["membercard_no"];
            $data[$key]["membercard_balance"] = $v["membercard_balance"] . '元';
            $data[$key]["membercard_name"] = $v["membercard_name"];
            $data[$key]["reg_time"] = date('Y-m-d H:i:s', $v["reg_time"]);
            $key += 1;
        }
        dataExcel($xlsName, $xlsCell, $data);
    }


    /***********************************************前端接口************************************************************/
    /**
     * 会员卡首页
     */
    public function membercardIndex()
    {
        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $membercard = new membercardServer();
        $data = $membercard->membercardIndex($uid);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * 明细
     */
    public function membercardBalanceList()
    {
        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);

        $condition['nmar.uid'] = $uid;
        $condition['nmar.account_type'] = 6;

        $member = new MemberService();
        $list = $member->getAccountLists($page_index, $page_size, $condition);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $list
        ]);
    }

    /**
     * 升级会员卡
     */
    public function upgradeMembercard()
    {
        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $membercard = new membercardServer();
        $data = $membercard->upgradeMembercard($uid);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * 立即开通
     */
    public function buyMembercard()
    {
        $membercard_id = request()->post('membercard_id', 0);
        $membercard_spec_id = request()->post('membercard_spec_id', 0);

        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $membercard_spec_mdl = new VslMembercardSpecModel();
        $membercard_spec_info = $membercard_spec_mdl->getInfo(['spec_id' => $membercard_spec_id, 'membercard_id' => $membercard_id]);
        if ($membercard_spec_info) {
            //判断是否是首次购买，如果是，则取首次购买价
            $buy_membercard_records_mdl = new VslBuyMembercardRecordsModel();
            $is_first_buy = 1;
            $buy_log = $buy_membercard_records_mdl->getInfo(['membercard_id' => $membercard_id,'membercard_spec_id' => $membercard_spec_id,'pay_status' => 2,'uid' => $uid,'website_id' => $this->website_id]);
            if($buy_log) {
                $is_first_buy = 0;
            }

            $buy_membercard_records_data = [
                'membercard_id' => $membercard_id,
                'membercard_spec_id' => $membercard_spec_id,
                'pay_status' => 0,
                'uid' => $uid,
                'create_time' => time(),
                'website_id' => $this->website_id,
            ];
            $type_alias_id = $buy_membercard_records_mdl->save($buy_membercard_records_data);

            $pay = new UnifyPay();
            $out_trade_no = $pay->createOutTradeNo();

            if ($out_trade_no) {
                $pay = new VslOrderPaymentModel();
                $pay_money = $membercard_spec_info['price'];
                if($is_first_buy && $membercard_spec_info['is_first_buy']) {
                    $pay_money = $membercard_spec_info['first_buy_price'];
                }
                $pay_status = 0;
                if($pay_money <= 0) {
                    $pay_status = 1;
                }
                $data = array(
                    'shop_id' => 0,
                    'out_trade_no' => $out_trade_no,
                    'type' => 6,
                    'type_alis_id' => $type_alias_id,
                    'pay_body' => '购买会员卡',
                    'pay_detail' => '购买会员卡',
                    'pay_money' => $pay_money,
                    'pay_status' => $pay_status,
                    'create_time' => time(),
                    'website_id' => $this->website_id,
                );
                $pay->save($data);

                if($pay_status == 1) {
                    $membercard = new membercardServer();
                    $membercard-> buyMembercard($out_trade_no,0);
                }

                return json([
                    'code' => 1,
                    'message' => '获取成功',
                    'data' => ['out_trade_no' => $out_trade_no]
                ]);

            } else {
                return json([
                    'code' => -1,
                    'message' => '系统繁忙'
                ]);
            }
        } else {
            return json([
                'code' => -1,
                'message' => '此套餐不存在'
            ]);
        }
    }

    /**
     * 充值，返回外部交易号
     */
    public function recharge()
    {
        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $membercard = new membercardServer();
        $data = $membercard->getRechargeList($uid);

        $pay = new UnifyPay();
        $data['out_trade_no'] = $pay->createOutTradeNo();

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * 创建充值订单
     */
    public function createRechargeOrder()
    {
        $recharge_id = request()->post('recharge_id', 0);
        $recharge_money = request()->post('recharge_money', 0);//充值金额
        $give_money = request()->post('give_money', 0);//赠送金额
        $out_trade_no = request()->post('out_trade_no', '');

        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        if (empty($out_trade_no)) {
            return json([
                'code' => -1,
                'message' => '外部交易号不能为空'
            ]);
        }

        $membercard = new membercardServer();
        $res = $membercard->createRechargeOrder($uid, $recharge_id, $recharge_money, $give_money, $out_trade_no);

        if ($res > 0) {
            $data['code'] = 1;
            $data['message'] = "充值订单创建成功";
            $data['data']['out_trade_no'] = $out_trade_no;
        } elseif ($res == -2) {
            $data['code'] = -1;
            $data['message'] = "赠送金额错误";
            $data['data'] = '';
        } else {
            $data['code'] = -1;
            $data['data'] = '';
            $data['message'] = "系统繁忙";
        }

        return json($data);
    }

    /**
     * 会员卡领取到微信卡包
     */
    public function addMembercardToWx()
    {
        $membercard_id = request()->post('membercard_id', 0);
        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $weixin_card = new WeixinCard();
        $membercard = new membercardServer();
        $membercard_info = $membercard->getMembercardInfo($membercard_id, $uid);
        $info = $weixin_card->addMembercard($membercard_info);

        if ($info) {
            $data['data'] = $info;
            $data['code'] = 1;
            $data['message'] = "获取成功";
        } else {
            $data['code'] = -1;
            $data['message'] = "获取失败";
        }

        return json($data);
    }

    /**
     * 微信会员卡领取成功
     */
    public function membercardSuccessToWx()
    {
        $membercard_id = request()->post('membercard_id', 0);
        $uid = $this->uid;

        if (empty($uid)) {
            return json(['code' => -1, 'message' => '请先登录']);
        }

        $membercard = new membercardServer();
        $res = $membercard->membercardSuccessToWx($membercard_id, $uid);

        return json(AjaxReturn($res));
    }

}