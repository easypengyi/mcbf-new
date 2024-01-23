<?php

namespace addons\appointgivecoupon\server;

use addons\appointgivecoupon\model\VslAppointgivecouponModel;
use addons\appointgivecoupon\model\VslAppointgivecouponRecordModel;
use addons\coupontype\model\VslCouponTypeModel;
use addons\coupontype\server\Coupon;
use addons\distribution\model\SysMessagePushModel;
use addons\distribution\service\Distributor as DistributorSer;
use addons\giftvoucher\model\VslGiftVoucherModel;
use addons\giftvoucher\server\GiftVoucher;
use data\model\MsgReminderModel;
use data\service\BaseService;
use data\service\Member as MembserSer;
use data\service\User as UserSer;
use think\Db;

class Appointgivecoupon extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /*
     * 获取当前组合所有数据
     * 会员等级、会员标签、分销商等级
     */
    public function getUserSendTypesCombineList()
    {
        $list = [];
        $page_index = 1;
        $page_size = 0;
        //1、会员等级
        $memberSer = new MembserSer();
        $level_condition = [
            'website_id' => $this->website_id,
        ];
        $levelRes = $memberSer->getMemberLevelList($page_index, $page_size, $level_condition, '', $field = 'level_id, level_name');
        $list['user_level'] = $levelRes['data'];
        //2、会员标签
        $group_condition = [
            'website_id' => $this->website_id,
        ];
        $groupRes = $memberSer->getMemberGroupList($page_index, $page_size, $group_condition, 'group_id desc', 'group_id,group_name');
        $list['user_group'] = $groupRes['data'];
        //3、分销商等级
        $disRes = [];
        if (getAddons('distribution', $this->website_id)) {
            $disSer = new DistributorSer();
            $disRes = $disSer->getDistributorLevel();
        }
        $list['dis_level'] = $disRes;

        return $list;
    }

    /**
     * 添加任务
     */
    public function addAppointgivecouponTask($coupon_arr, $gift_arr, $type, $sub_type)
    {
        try {
            //组装优惠券、礼品券名称
            $coupon_name_str = '';
            $gift_name_str = '';
            $coupon_name_list = '';
            if ($coupon_arr) {
                $coupon_type_mdl = new VslCouponTypeModel();
                foreach ($coupon_arr as $k => $v) {
                    $v = explode(',', $v);
                    $coupon_name = $coupon_type_mdl->Query(['coupon_type_id' => $v[0]], 'coupon_name')[0];
                    $coupon_name_str .= $coupon_name . '*' . $v[1] . ',';
                }
                $coupon_name_str = trim($coupon_name_str, ',');
            }
            if ($gift_arr) {
                $gift_voucher_mdl = new VslGiftVoucherModel();
                foreach ($gift_arr as $k => $v) {
                    $v = explode(',', $v);
                    $gift_name = $gift_voucher_mdl->Query(['gift_voucher_id' => $v[0]], 'giftvoucher_name')[0];
                    $gift_name_str .= $gift_name . '*' . $v[1] . ',';
                }
                $gift_name_str = trim($gift_name_str, ',');
            }

            if ($coupon_name_str && $gift_name_str) {
                $coupon_name_list = $coupon_name_str . ';' . $gift_name_str;
            } else if ($coupon_name_str) {
                $coupon_name_list = $coupon_name_str;
            } else {
                $coupon_name_list = $gift_name_str;
            }

            $appoint_give_coupon_mdl = new VslAppointgivecouponModel();
            $data = [
                'coupon_list' => $coupon_arr ? json_encode($coupon_arr, true) : '',
                'gift_list' => $gift_arr ? json_encode($gift_arr, true) : '',
                'give_type' => $type,
                'give_type_value' => json_encode($sub_type, true),
                'coupon_name_str' => $coupon_name_list,
                'status' => 0,
                'create_time' => time(),
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id
            ];
            $res = $appoint_give_coupon_mdl->save($data);
            if ($res) {
                //rabbitmq队列送券
                $type_value = json_encode($sub_type, true);
                $this->addQueue($coupon_arr, $gift_arr, $res, $type, $type_value);
                return ['code' => 1, 'message' => '已添加到执行队列，等待执行'];
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
    public function addQueue($coupon_arr, $gift_arr, $give_coupon_id, $type, $type_value)
    {
        if(config('is_high_powered')){
            $redis = connectRedis();
            $redis->set('total_give_user_num_'.$give_coupon_id, 0);
            $redis->set('page_count_'.$give_coupon_id, 0);
            //按照配置中的分页条数来归纳队列数据，如一个队列处理1000， 则第一次为limit(1, 1000)，依次类推
            $appoint_arr = [];
            $config_limit = config('appoint_give_coupon_num') ? : 1000;
            $coupon_type = new VslCouponTypeModel();
            //获取用户数量
            // 1、查询uid、openid
            $user_count = $this->getTypeMapUserCount($type, $type_value, $this->website_id);
            if($coupon_arr){
                foreach($coupon_arr as $k => $coupon_info){
                    $coupon_id = 0;
                    $num = 0;
                    $sub_coupon_arr = explode(',', $coupon_info);
                    $coupon_id = $sub_coupon_arr[0];
                    $per_num = $sub_coupon_arr[1];//每个人领取的数量
                    //总优惠券数量
                    $coupon_type_info = $coupon_type->getInfo(['coupon_type_id' => $coupon_id], 'count, max_fetch');
                    $per_num = $coupon_type_info['max_fetch'] > $per_num ? $per_num : $coupon_type_info['max_fetch'];//后台设置的每个人可以领取张数
                    if($per_num == 0){
                        continue;
                    }
                    $total_num = $coupon_type_info['count'];
                    $num = ceil($total_num/$per_num);//总张数除以每个人领取的张数 得到多少个人可以领取。 $num 为人数
                    //用户数 和 发放的优惠券次数对比，哪个少取哪个，优惠券再多，会员不够也不够人领取。
                    $num = $num > $user_count ? $user_count :$num;
                    $per = ceil($num/$config_limit);//队列数  $config_limit参数为配置配置的一个队列处理多少个用户
                    if($per == 1){
                        $limit = $num;
                        $appoint_arr[0][$k]['coupon_info']['coupon_id'] = $coupon_id;
                        $appoint_arr[0][$k]['coupon_info']['per_num'] = $per_num;
                        $appoint_arr[0][$k]['coupon_info']['offset'] = 0;
                        $appoint_arr[0][$k]['coupon_info']['limit'] = $limit;
                        $limit_arr[0][] = $limit;
                    }else{
                        for($i = 0; $i < $per; $i++){
                            $offset = $i * $config_limit;
                            if($i == $per - 1){
                                $limit = $num - $config_limit * $i;;
                            }else{
                                $limit = $config_limit;
                            }
                            $appoint_arr[$i][$k]['coupon_info']['coupon_id'] = $coupon_id;
                            $appoint_arr[$i][$k]['coupon_info']['per_num'] = $per_num;
                            $appoint_arr[$i][$k]['coupon_info']['offset'] = $offset;
                            $appoint_arr[$i][$k]['coupon_info']['limit'] = $limit;
                            $limit_arr[$i][] = $limit;
                        }
                    }
                }
            }

            $config_limit = config('appoint_give_coupon_num') ? : 1000;
            $gift_voucher = new VslGiftVoucherModel();
            if($gift_arr){
                foreach($gift_arr as $k => $gift_info){
                    $gift_id = 0;
                    $num = 0;
                    $sub_gift_arr = explode(',', $gift_info);
                    $gift_id = $sub_gift_arr[0];
                    $per_num = $sub_gift_arr[1];//每个人领取的数量
                    //获取总张数
                    $gift_voucher_info = $gift_voucher->getInfo(['gift_voucher_id' => $gift_id], 'count, max_fetch');
                    //总礼品券数量
                    $per_num = $gift_voucher_info['max_fetch'] > $per_num ? $per_num : $gift_voucher_info['max_fetch'];//后台设置的每个人可以领取张数
                    if($per_num == 0){
                        continue;
                    }
                    $total_num = $gift_voucher_info['count'];
                    $num = ceil($total_num/$per_num);//总张数除以每个人领取的张数 得到多少个人可以领取。 $num 为人数
                    //用户数 和 发放的优惠券次数对比，哪个少取哪个，优惠券再多，会员不够也不够人领取。
                    $num = $num > $user_count ? $user_count :$num;
                    $per = ceil($num/$config_limit);
                    if($per == 1){
                        $limit = $num;
                        $appoint_arr[0][$k]['gift_info']['gift_id'] = $gift_id;
                        $appoint_arr[0][$k]['gift_info']['per_num'] = $per_num;
                        $appoint_arr[0][$k]['gift_info']['offset'] = 0;
                        $appoint_arr[0][$k]['gift_info']['limit'] = $limit;
                        $limit_arr[0][] = $limit;
                    }else{
                        for($i = 0; $i < $per; $i++){
                            $offset = $i * $config_limit;
                            if($i == $per - 1){
                                $limit = $num - $config_limit * $i;
                            }else{
                                $limit = $config_limit;
                            }
                            $appoint_arr[$i][$k]['gift_info']['gift_id'] = $gift_id;
                            $appoint_arr[$i][$k]['gift_info']['per_num'] = $per_num;
                            $appoint_arr[$i][$k]['gift_info']['offset'] = $offset;
                            $appoint_arr[$i][$k]['gift_info']['limit'] = $limit;
                            $limit_arr[$i][] = $limit;
                        }
                    }
                }
            }

//            p($appoint_arr);
//            p($limit_arr);
            if(!empty($appoint_arr)){
                $data['page_count'] = count($appoint_arr);
                foreach($appoint_arr as $k => $appoint_info){
                    $offset = $k * $config_limit;
                    $data['appoint_info'] = $appoint_info;
                    $data['offset'] = $offset;
                    $data['limit'] = max($limit_arr[$k]);
                    $data['give_coupon_id'] = $give_coupon_id;
                    $exchange_name = config('rabbit_appoint_give_coupon.exchange_name');
                    $queue_name = config('rabbit_appoint_give_coupon.queue_name');
                    $routing_key = config('rabbit_appoint_give_coupon.routing_key');
                    $url = config('rabbit_interface_url.url');
                    $give_coupon_url = $url.'/rabbitTask/appointGiveCoupon';
                    $request_data = json_encode($data);
                    $push_data = [
                        "customType" => "appoint_give_coupon",//标识什么业务场景
                        "data" => $request_data,//请求数据
                        "requestMethod" => "POST",
                        "timeOut" => 20,
                        "url" => $give_coupon_url,
                    ];
                    $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                    $push_arr = json_decode($push_res, true);
                    if($push_arr['code'] == 103){//未创建队列
                        $create_res = createQueue($exchange_name, $queue_name, $routing_key);
                        $create_arr = json_decode($create_res, true);
                        if($create_arr['code'] != 200){
                            return ['code' => -1, 'message' => '未知错误：'.$create_arr];
                        }elseif($create_arr['code'] == 200){
                            $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                        }
                    }
                }
            }
        }
    }
    /**
     * 更新任务状态
     */
    public function updateAppointgivecoupon($data, $condition)
    {
        $appoint_give_coupon_mdl = new VslAppointgivecouponModel();
        $res = $appoint_give_coupon_mdl->isUpdate(true)->save($data, $condition);
        return $res;
    }

    /**
     * 执行任务
     */
    public function executeAppointgivecoupon($id)
    {
        Db::startTrans();
        try {
            $appoint_give_coupon_mdl = new VslAppointgivecouponModel();
            $task_info = $appoint_give_coupon_mdl->getInfo(['id' => $id], '*');

            // 1、查询uid、openid
            $getArr = $this->getTypeMapUseWxOpenids($task_info['give_type'], $task_info['give_type_value'], $task_info['website_id']);
            $openidsArr = $getArr['openid'];//openid
            $uidOpenidArr = $getArr['uid_openid'];//uid及openid
            $uidsArr = array_column($uidOpenidArr, 'uid');//uid
            $coupon_num = 0;//总共送出多少张优惠券
            $gift_num = 0;//总共送出多少张礼品券
            $coupon_success_uids = '';
            $gift_success_uids = '';

            if (empty($uidsArr)) {
                return;
            }

            //2、执行送券
            if ($task_info['coupon_list']) {
                //赠送优惠券
                if (getAddons('coupontype', $task_info['website_id'], $task_info['shop_id'])) {
                    $task_info['coupon_list'] = json_decode($task_info['coupon_list'], true);
                    $coupon = new Coupon();
                    foreach ($task_info['coupon_list'] as $k => $v) {
                        $v = explode(',', $v);
                        foreach ($uidsArr as $k1 => $v1) {
                            $coupon_res = 0;
                            for ($i = 0; $i < $v[1]; $i++) {
                                $coupon_res += $coupon->userAchieveCoupon($v1, $v[0], 13, 1);
                            }
                            if ($coupon_res > 0) {
                                //记录发放成功的用户
                                $coupon_success_uids .= $v1 . ',';
                            }
                        }
                        $coupon_num += $v[1];
                    }
                    if ($coupon_success_uids) {
                        $coupon_success_uids = array_unique(explode(',', trim($coupon_success_uids, ',')));
                    }
                }
            }
            if ($task_info['gift_list']) {
                //赠送礼品券
                if (getAddons('giftvoucher', $task_info['website_id'], $task_info['shop_id'])) {
                    $task_info['gift_list'] = json_decode($task_info['gift_list'], true);
                    $gift_voucher = new GiftVoucher();
                    foreach ($task_info['gift_list'] as $k => $v) {
                        $v = explode(',', $v);
                        foreach ($uidsArr as $k1 => $v1) {
                            $gift_res = 0;
                            for ($i = 0; $i < $v[1]; $i++) {
                                $giftvoucher_num = $gift_voucher->isGiftVoucherReceive(['gift_voucher_id' => $v[0], 'website_id' => $task_info['website_id']], $v1, 1);
                                if ($giftvoucher_num > 0) {
                                    $gift_res += $gift_voucher->getUserReceive($v1, $v[0], 8);
                                }
                            }
                            if ($gift_res > 0) {
                                //记录发放成功的用户
                                $gift_success_uids .= $v1 . ',';
                            }
                        }
                        $gift_num += $v[1];
                    }
                }
                if ($gift_success_uids) {
                    $gift_success_uids = array_unique(explode(',', trim($gift_success_uids, ',')));
                }
            }

            $give_uids = '';//赠送的会员id集合
            $total_give_user_num = 0;//赠送成功的会员数
            if ($coupon_success_uids && $gift_success_uids) {
                //既赠送了优惠券又送了礼品券,哪种券成功送出的数量多就取哪种的
                $coupon_success_uids_num = count($coupon_success_uids);
                $gift_success_uids_num = count($gift_success_uids);
                if ($coupon_success_uids_num >= $gift_success_uids_num) {
                    $give_uids = implode(',', $coupon_success_uids);
                    $total_give_user_num = $coupon_success_uids_num;
                } else {
                    $give_uids = implode(',', $gift_success_uids);
                    $total_give_user_num = $gift_success_uids_num;
                }
            } elseif ($coupon_success_uids) {
                $coupon_success_uids_num = count($coupon_success_uids);
                $give_uids = implode(',', $coupon_success_uids);
                $total_give_user_num = $coupon_success_uids_num;
            } else {
                $gift_success_uids_num = count($gift_success_uids);
                $give_uids = implode(',', $gift_success_uids);
                $total_give_user_num = $gift_success_uids_num;
            }

            //3、写入记录
            if ($give_uids) {
                $save_data = [
                    'appoint_give_coupon_task_id' => $id,
                    'total_give_user_num' => $total_give_user_num,
                    'give_uids' => $give_uids,
                    'give_time' => time(),
                    'shop_id' => $task_info['shop_id'],
                    'website_id' => $task_info['website_id'],
                ];
                $appoint_give_coupon_record_mdl = new VslAppointgivecouponRecordModel();
                $res = $appoint_give_coupon_record_mdl->save($save_data);
            }

            //4、执行消息推送
            if ($res) {
                //更改任务状态
                $this->updateAppointgivecoupon(['status' => 1], ['id' => $id]);

                $give_uids = explode(',', $give_uids);
                $push_message_mdl = new SysMessagePushModel();
                //站内通知
                $station_notice_info = $push_message_mdl->getInfo(['template_type' => 'appointgivecoupon_station_notice', 'website_id' => $task_info['website_id']], '*');
                if ($station_notice_info['is_enable']) {
                    $message = str_replace('${couponnum}', $coupon_num, $station_notice_info['template_content']);
                    $message = str_replace('${giftnum}', $gift_num, $message);
                    foreach ($give_uids as $k => $v) {
                        $save_data = [
                            'title' => '送券通知',
                            'content' => $message,
                            'status' => 1,
                            'to_uid' => $v,
                            'is_check' => 0,
                            'create_time' => time(),
                        ];
                        if (getAddons('thingcircle', $this->website_id)) {
							$msg_reminder = new MsgReminderModel();
							$msg_reminder->save($save_data);
						}
                    }
                }

                //微信公众号客服消息
                $wx_notice_info = $push_message_mdl->getInfo(['template_type' => 'appointgivecoupon_wx_notice', 'website_id' => $task_info['website_id']], '*');
                if ($wx_notice_info['is_enable']) {
                    $message_str = str_replace('${couponnum}', $coupon_num, $wx_notice_info['template_content']);
                    $message_str = str_replace('${giftnum}', $gift_num, $message_str);
                    foreach ($give_uids as $k => $v) {
                        runhook("Notify", "sendCustomMessage", ['messageType' => 'appointgivecoupon_wx_notice', "uid" => $v, "message_str" => $message_str]);
                    }
                }

                //发送短信
                foreach ($give_uids as $k => $v) {
                    runhook("Notify", "sendGiveCouponMsgBySms", ["uid" => $v, "couponnum" => $coupon_num, "giftnum" => $gift_num, "shop_id" => $task_info['shop_id']]);
                }
            }
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
    /**
     * 队列执行定向送券
     */
    public function rabbitExecuteAppointgivecoupon($appoint_arr)
    {
        Db::startTrans();
        try {
            $appoint_give_coupon_mdl = new VslAppointgivecouponModel();
            $task_info = $appoint_give_coupon_mdl->getInfo(['id' => $appoint_arr['give_coupon_id']], '*');
            $offset = $appoint_arr['offset'];
            $limit = $appoint_arr['limit'];
            $getArr = $this->getTypeMapUseWxOpenids($task_info['give_type'], $task_info['give_type_value'], $task_info['website_id'], $offset, $limit);
            if(empty($getArr)){
                return;
            }
            $uidOpenidArr = $getArr['uid_openid'];//uid及openid
            $uidsArr = array_column($uidOpenidArr, 'uid');//uid
            $coupon_num = 0;//总共送出多少张优惠券
            $gift_num = 0;//总共送出多少张礼品券
            $coupon_success_uids = '';
            $gift_success_uids = '';
            if (empty($uidsArr)) {
                return;
            }
            if($appoint_arr['appoint_info']){
                foreach($appoint_arr['appoint_info'] as $k => $appoint_info){
                    foreach($appoint_info as $type => $appoint_val){
                        $limit = $appoint_val['limit'] > count($uidsArr) ? count($uidsArr) : $appoint_val['limit'];
                        $per_num = $appoint_val['per_num'];
                        if($type == 'coupon_info'){
                            $coupon_type_id = $appoint_val['coupon_id'];
                            $coupon = new Coupon();
                            for($i=0; $i<$limit; $i++){
                                $coupon_res = 0;
                                for($i0=0;$i0<$per_num;$i0++){//优惠券发放数量
                                    $coupon_res += $coupon->userAchieveCoupon($uidsArr[$i], $coupon_type_id, 13, 1);
                                }
                                if($coupon_res > 0){
                                    $coupon_success_uids .= $uidsArr[$i] . ',';
                                }
                            }
                            $coupon_num += $per_num;//每个用户领取的券的数量
                        }elseif($type == 'gift_info'){
                            $gift_voucher = new GiftVoucher();
                            $gift_voucher_id = $appoint_val['gift_id'];
                            for($i=0; $i<$limit; $i++){
                                $gift_res = 0;
                                for($i0=0;$i0<$per_num;$i0++){//优惠券发放数量
                                    $giftvoucher_num = $gift_voucher->isGiftVoucherReceive(['gift_voucher_id' => $gift_voucher_id, 'website_id' => $task_info['website_id']], $uidsArr[$i], 1);
                                    if($giftvoucher_num > 0){
                                        $gift_res += $gift_voucher->getUserReceive($uidsArr[$i], $gift_voucher_id, 8);
                                    }
                                }
                                if($gift_res > 0){
                                    $gift_success_uids .= $uidsArr[$i] . ',';
                                }
                            }
                        }
                    }
                }
            }
            if ($coupon_success_uids) {
                $coupon_success_uids = array_unique(explode(',', trim($coupon_success_uids, ',')));
            }
            if ($gift_success_uids) {
                $gift_success_uids = array_unique(explode(',', trim($gift_success_uids, ',')));
            }
            $give_uids = '';//赠送的会员id集合
            $total_give_user_num = 0;//赠送成功的会员数
            if ($coupon_success_uids && $gift_success_uids) {
                //既赠送了优惠券又送了礼品券,哪种券成功送出的数量多就取哪种的
                $coupon_success_uids_num = count($coupon_success_uids);
                $gift_success_uids_num = count($gift_success_uids);
                if ($coupon_success_uids_num >= $gift_success_uids_num) {
                    $give_uids = implode(',', $coupon_success_uids);
                    $total_give_user_num = $coupon_success_uids_num;
                } else {
                    $give_uids = implode(',', $gift_success_uids);
                    $total_give_user_num = $gift_success_uids_num;
                }
            } elseif ($coupon_success_uids) {
                $coupon_success_uids_num = count($coupon_success_uids);
                $give_uids = implode(',', $coupon_success_uids);
                $total_give_user_num = $coupon_success_uids_num;
            } elseif ($gift_success_uids) {
                $gift_success_uids_num = count($gift_success_uids);
                $give_uids = implode(',', $gift_success_uids);
                $total_give_user_num = $gift_success_uids_num;
            }
            //3、写入记录，用redis是为了解决并发
            if ($give_uids) {
                //4、执行消息推送
                $give_uids = explode(',', $give_uids);
                $push_message_mdl = new SysMessagePushModel();
                //站内通知
                $station_notice_info = $push_message_mdl->getInfo(['template_type' => 'appointgivecoupon_station_notice', 'website_id' => $task_info['website_id']], '*');
                if ($station_notice_info['is_enable']) {
                    $message = str_replace('${couponnum}', $coupon_num, $station_notice_info['template_content']);
                    $message = str_replace('${giftnum}', $gift_num, $message);
                    foreach ($give_uids as $k => $v) {
                        $save_data = [
                            'title' => '送券通知',
                            'content' => $message,
                            'status' => 1,
                            'to_uid' => $v,
                            'is_check' => 0,
                            'create_time' => time(),
                        ];
                        if (getAddons('thingcircle', $this->website_id)) {
                            $msg_reminder = new MsgReminderModel();
                            $msg_reminder->save($save_data);
                        }
                    }
                }

                //微信公众号客服消息
                $wx_notice_info = $push_message_mdl->getInfo(['template_type' => 'appointgivecoupon_wx_notice', 'website_id' => $task_info['website_id']], '*');
                if ($wx_notice_info['is_enable']) {
                    $message_str = str_replace('${couponnum}', $coupon_num, $wx_notice_info['template_content']);
                    $message_str = str_replace('${giftnum}', $gift_num, $message_str);
                    foreach ($give_uids as $k => $v) {
                        runhook("Notify", "sendCustomMessage", ['messageType' => 'appointgivecoupon_wx_notice', "uid" => $v, "message_str" => $message_str]);
                    }
                }

                //发送短信
                foreach ($give_uids as $k => $v) {
                    runhook("Notify", "sendGiveCouponMsgBySms", ["uid" => $v, "couponnum" => $coupon_num, "giftnum" => $gift_num, "shop_id" => $task_info['shop_id']]);
                }
                //改为redis原子性解决并发
                $redis = connectRedis();
                $give_uids = implode(',', $give_uids);
                $give_uids_key = 'give_uids_'.$appoint_arr['give_coupon_id'];
                if($give_uids){
                    $redis->rpush($give_uids_key, $give_uids);
                }
                for($i=0;$i<$total_give_user_num;$i++){
                    $redis->incr('total_give_user_num_'.$appoint_arr['give_coupon_id']);
                }
                $redis->incr('page_count_'.$appoint_arr['give_coupon_id']);
                if($redis->get('page_count_'.$appoint_arr['give_coupon_id']) == $appoint_arr['page_count']){
                    $appoint_give_coupon_record_mdl = new VslAppointgivecouponRecordModel();
                    $users_len = $redis->llen($give_uids_key);
                    $give_uids_str = '';
                    for($i=0;$i<$users_len;$i++){
                        $give_uids_key = $redis->lpop($give_uids_key);
                        $give_uids_str .= $give_uids_key.',';
                    }
                    $give_uids_str = trim($give_uids_str, ',');
                    $add_data = [
                        'appoint_give_coupon_task_id' => $appoint_arr['give_coupon_id'],
                        'total_give_user_num' => $redis->get('total_give_user_num_'.$appoint_arr['give_coupon_id']),
                        'give_uids' => $give_uids_str,
                        'give_time' => time(),
                        'shop_id' => $task_info['shop_id'],
                        'website_id' => $task_info['website_id'],
                    ];
                    $res = $appoint_give_coupon_record_mdl->save($add_data);
                }
            }
            if($res){
                //更改任务状态
                $this->updateAppointgivecoupon(['status' => 1], ['id' => $appoint_arr['give_coupon_id']]);
                $redis->del('page_count_'.$appoint_arr['give_coupon_id']);
                $redis->del('total_give_user_num_'.$appoint_arr['give_coupon_id']);
            }
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }
    /**
     * 获取赠送类型对应的所有用户微信openid
     * @param $type_id int [赠送方式]
     * @param $type_value string [赠送方式对应的值]
     * @param $website_id string [商家实例]
     * @return array|void
     */
    public function getTypeMapUseWxOpenids($type_id, $type_value, $website_id, $offset = 0, $limit = 0)
    {
        // 赠送方式 1按会员等级推送 2按会员标签推送 3按分销商等级推送 4按会员ID推送  5推送所有会员
        $memberSer = new MembserSer();
        $userSer = new UserSer();
        $user_field = 'wx_openid,uid';//默认获取用户的会员openid
        $openidsArr = [];
        $uid_openid = [];
        debugLog($type_id, '$type_id');
        switch ($type_id) {
            case 1:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $member_level = implode(',', $type_value);
                $getArr = $memberSer->getMemberLevelOfUsers($member_level, $user_field, false, $website_id, $offset, $limit);
                $uid_openid = array_merge($uid_openid, $getArr);
                $openids = array_column($getArr, 'wx_openid');
                $openidsArr = array_merge($openidsArr, $openids);
                break;
            case 2:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $group_id = implode(',|,', $type_value);//这里为了正则匹配
                $getArr = $memberSer->getMemberGroupIdOfUsers($group_id, $user_field, false, $website_id, $offset, $limit);
                $uid_openid = array_merge($uid_openid, $getArr);
                $openids = array_column($getArr, 'wx_openid');
                $openidsArr = array_merge($openidsArr, $openids);
                break;
            case 3:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $distributor_level = implode(',', $type_value);
                $getArr = $memberSer->getMemberDistributorLevelOfUser($distributor_level, $user_field, false, $website_id, $offset, $limit);
                $uid_openid = array_merge($uid_openid, $getArr);
                $openids = array_column($getArr, 'wx_openid');
                $openidsArr = array_merge($openidsArr, $openids);
                break;
            case 4:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $uids = implode(',', $type_value);
                $user_condition = [
                    'website_id' => $website_id,
                    'uid' => ['in', $uids],
                ];
                $getArr = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
                $uid_openid = array_merge($uid_openid, $getArr);
                $openids = array_column($getArr, 'wx_openid');
                $openidsArr = array_merge($openidsArr, $openids);
                break;
            case 5:
                $user_condition = [
                    'website_id' => $website_id,
                ];
                $getArr = $userSer->getUserData($user_condition, $user_field, '', $offset, $limit);
                $uid_openid = array_merge($uid_openid, $getArr);
                $openids = array_column($getArr, 'wx_openid');
                $openidsArr = array_merge($openidsArr, $openids);
                break;
        }
        $returnArr = [
            'openid' => $openidsArr,
            'uid_openid' => $uid_openid
        ];
        return $returnArr;
    }

    /**
     * 获取赠送类型对应的所有用户微信openid
     * @param $type_id int [赠送方式]
     * @param $type_value string [赠送方式对应的值]
     * @param $website_id string [商家实例]
     * @return array|void
     */
    public function getTypeMapUserCount($type_id, $type_value, $website_id)
    {
        // 赠送方式 1按会员等级推送 2按会员标签推送 3按分销商等级推送 4按会员ID推送  5推送所有会员
        $memberSer = new MembserSer();
        $userSer = new UserSer();
        $user_field = 'wx_openid,uid';//默认获取用户的会员openid
        $openidsArr = [];
        $uid_openid = [];
        switch ($type_id) {
            case 1:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $member_level = implode(',', $type_value);
                $count = $memberSer->getMemberLevelOfUsersCount($member_level, $user_field, false, $website_id);
                break;
            case 2:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $group_id = implode(',|,', $type_value);//这里为了正则匹配
                $count = $memberSer->getMemberGroupIdOfUsersCount($group_id, $user_field, false, $website_id);
                break;
            case 3:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $distributor_level = implode(',', $type_value);
                $count = $memberSer->getMemberDistributorLevelOfUserCount($distributor_level, $user_field, false, $website_id);
                break;
            case 4:
                $type_value = json_decode($type_value, true);
                if (!is_array($type_value)) {
                    return;
                }
                $uids = implode(',', $type_value);
                $user_condition = [
                    'website_id' => $website_id,
                    'uid' => ['in', $uids],
                ];
                $count = $userSer->getUserCount($user_condition, $user_field, '');
                break;
            case 5:
                $user_condition = [
                    'website_id' => $website_id,
                ];
                $count = $userSer->getUserCount($user_condition, $user_field, '');
                break;
        }
        return $count;
    }

    /**
     * 送券记录
     */
    public function giveCouponRecord($page_index, $page_size, $condition, $order)
    {
        $appoint_give_coupon_record_mdl = new VslAppointgivecouponRecordModel();
        $view_obj = $appoint_give_coupon_record_mdl->alias('agcr')
            ->join('vsl_appoint_give_coupon agc', 'agcr.appoint_give_coupon_task_id = agc.id', 'left')
            ->field('agcr.id as record_id,agcr.total_give_user_num,agcr.give_uids,agcr.give_time,agc.*');
        $queryList = $appoint_give_coupon_record_mdl->viewPageQuery($view_obj, $page_index, $page_size, $condition, $order);

        $queryCount = $appoint_give_coupon_record_mdl->alias('agcr')
            ->join('vsl_appoint_give_coupon agc', 'agcr.appoint_give_coupon_task_id = agc.id', 'left')
            ->field('agcr.id')
            ->where($condition)
            ->count();

        $list = $appoint_give_coupon_record_mdl->setReturnList($queryList, $queryCount, $page_size);
        if ($list['data']) {
            $typeList = $this->getUserSendTypesCombineList();//当前组合所有数据
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['give_time'] = date('Y-m-d H:i:s', $v['give_time']);
                switch ($v['give_type']) {
                    case 1:
                        $typeArr = json_decode($v['give_type_value'], true);
                        $name = '会员等级:';
                        foreach ($typeList['user_level'] as $user) {
                            if (in_array($user['level_id'], $typeArr)) {
                                $name .= $user['level_name'] . ',';
                            }
                        }
                        $name = rtrim($name, ',');
                        break;
                    case 2:
                        $name = '会员标签:';
                        $typeArr = json_decode($v['give_type_value'], true);
                        foreach ($typeList['user_group'] as $user) {
                            if (in_array($user['group_id'], $typeArr)) {
                                $name .= $user['group_name'] . ',';
                            }
                        }
                        $name = rtrim($name, ',');
                        break;
                    case 3:
                        $name = '分销商等级:';
                        $typeArr = json_decode($v['give_type_value'], true);
                        foreach ($typeList['dis_level'] as $user) {
                            if (in_array($user['id'], $typeArr)) {
                                $name .= $user['level_name'] . ',';
                            }
                        }
                        $name = rtrim($name, ',');
                        break;
                    case 4:
                        $name = '会员ID:';
                        break;
                    case 5:
                        $name = '所有会员';
                        break;
                }
                $list['data'][$k]['give_type_name'] = $name;

                $v['coupon_name_str'] = explode(';', $v['coupon_name_str']);
                if ($v['coupon_list'] && $v['gift_list']) {
                    $list['data'][$k]['coupon_name'] = $v['coupon_name_str'][0];
                    $list['data'][$k]['gift_name'] = $v['coupon_name_str'][1];
                } elseif ($v['coupon_list']) {
                    $list['data'][$k]['coupon_name'] = $v['coupon_name_str'][0];
                    $list['data'][$k]['gift_name'] = '';
                } else {
                    $list['data'][$k]['coupon_name'] = '';
                    $list['data'][$k]['gift_name'] = $v['coupon_name_str'][0];
                }
            }
        }

        return $list;
    }

}