<?php

namespace addons\appointgivecoupon\controller;

use addons\appointgivecoupon\Appointgivecoupon as baseAppointgivecoupon;
use addons\appointgivecoupon\model\VslAppointgivecouponRecordModel;
use addons\distribution\model\SysMessageItemModel;
use addons\distribution\model\SysMessagePushModel;
use data\service\Member as MemberService;

class Appointgivecoupon extends baseAppointgivecoupon
{
    public function __construct()
    {
        parent::__construct();

    }

    

    /**
     * 保存基础设置
     */
    public function saveBasicSetting()
    {
        $coupon_arr = request()->post('coupon_arr/a', '');
        $gift_arr = request()->post('gift_arr/a', '');
        $type = request()->post('type', 0);
        $sub_type = request()->post('sub_type/a', 0);

        if (empty($coupon_arr) && empty($gift_arr)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        if (empty($type) || empty($sub_type)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        $appointgivecoupon = new \addons\appointgivecoupon\server\Appointgivecoupon();
        $res = $appointgivecoupon->addAppointgivecouponTask($coupon_arr, $gift_arr, $type, $sub_type);

        return $res;
    }

    /**
     * 推送通知
     */
    public function messagePush()
    {
        $message = new SysMessagePushModel();
        $list = $message->getQuery(['website_id' => $this->website_id, 'type' => 7], '*', 'template_id asc');
        if (empty($list)) {
            $item = new SysMessageItemModel();
            $coupon_item_id = $item->Query(['item_name' => '优惠券数量'],'id')[0];
            if(empty($coupon_item_id)) {
                $coupon_data = [
                    'item_name' => '优惠券数量',
                    'show_name' => '[优惠券数量]',
                    'replace_name' => '${couponnum}',
                ];
                $item = new SysMessageItemModel();
                $coupon_item_id = $item->save($coupon_data);
            }
            $gift_item_id = $item->Query(['item_name' => '礼品券数量'],'id')[0];
            if(empty($gift_item_id)) {
                $gift_data = [
                    'item_name' => '礼品券数量',
                    'show_name' => '[礼品券数量]',
                    'replace_name' => '${giftnum}',
                ];
                $item = new SysMessageItemModel();
                $gift_item_id = $item->save($gift_data);
            }

            $arr = [
                [
                    'template_type' => 'appointgivecoupon_station_notice',
                    'template_title' => '定向送券站内通知',
                    'template_content' => '有${couponnum}张优惠券，${giftnum}张礼品券已偷偷放入了你的钱包，赶紧前往会员中心查看吧~',
                    'is_enable' => 1,
                    'website_id' => $this->website_id,
                    'sign_item' => $coupon_item_id.','.$gift_item_id,
                    'type' => 7,
                ],
                [
                    'template_type' => 'appointgivecoupon_wx_notice',
                    'template_title' => '定向送券公众号客服消息',
                    'template_content' => '有${couponnum}张优惠券，${giftnum}张礼品券已偷偷放入了你的钱包，赶紧前往会员中心查看吧~',
                    'is_enable' => 0,
                    'website_id' => $this->website_id,
                    'sign_item' => $coupon_item_id.','.$gift_item_id,
                    'type' => 7,
                ]
            ];
            $message = new SysMessagePushModel();
            $message->saveAll($arr,true);
            $list = $message->getQuery(['website_id' => $this->website_id, 'type' => 7], '*', 'template_id asc');
        }

        $item = new SysMessageItemModel();
        foreach ($list as $k => $v) {
            $list[$k]['sign'] = $item->getQuery(['id' => ['in', $v['sign_item']]], '*', '');
        }

        return $list;
    }

    /**
     * 保存消息推送
     */
    public function saveMessagePush()
    {
        $data = request()->post();

        foreach ($data['list'] as $k => $v) {
            $message = new SysMessagePushModel();
            $res = $message->isUpdate(true)->save(['is_enable' => $v[1], 'template_content' => $v[2]], ['template_id' => $v[0]]);
        }

        if ($res) {
            $this->addUserLog('定向送券设置', $res);
        }

        return AjaxReturn($res);
    }

    /**
     * 送券记录
     */
    public function giveCouponRecord()
    {
        $page_index = request()->post('page_index', 1);
        $coupon_name = request()->post('coupon_name', '');
        $give_type = request()->post('give_type', '');

        if ($give_type) {
            $condition['agc.give_type'] = $give_type;
        }
        if ($coupon_name) {
            $condition['agc.coupon_name_str'] = array('like', '%' . $coupon_name . '%');
        }

        $condition['agc.status'] = 1;
        $condition['agcr.shop_id'] = $this->instance_id;
        $condition['agcr.website_id'] = $this->website_id;

        $order = 'agcr.give_time desc';

        $appointgivecoupon = new \addons\appointgivecoupon\server\Appointgivecoupon();
        $list = $appointgivecoupon->giveCouponRecord($page_index, PAGESIZE, $condition, $order);

        return AjaxReturn(SUCCESS, $list);
    }


    /**
     * 送券记录详情
     */
    public function giveCouponRecordDetail()
    {
        $page_index = request()->post('page_index', 1);
        $record_id = request()->post('record_id', 0);
        $user_info = request()->post('user_info', '');

        if (!$record_id) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        $appoint_give_coupon_record_mdl = new VslAppointgivecouponRecordModel();
        $uids = $appoint_give_coupon_record_mdl->Query(['id' => $record_id], 'give_uids')[0];

        $condition = [
            'su.website_id' => $this->website_id,
            'su.uid' => ['in', $uids]
        ];
        if ($user_info) {
            $condition['su.nick_name | su.user_tel | su.user_name'] = ['like', '%' . $user_info . '%'];
        }
        $member = new MemberService();
        $list = $member->getMemberList($page_index, PAGESIZE, $condition, 'su.reg_time desc');

        return $list;
    }

}