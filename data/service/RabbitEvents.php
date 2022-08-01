<?php

namespace data\service;

use addons\bargain\model\VslBargainModel;
use addons\channel\model\VslChannelOrderModel;
use addons\channel\server\Channel;
use addons\distribution\model\SysMessagePushModel;
use addons\liveshopping\model\AnchorModel;
use addons\liveshopping\model\LiveModel;
use addons\liveshopping\model\LiveRecordModel;
use addons\liveshopping\model\LiveRemindModel;
use addons\liveshopping\service\Liveshopping;
use addons\presell\service\Presell;
use addons\seckill\model\VslSeckGoodsModel;
use addons\seckill\model\VslSeckillModel;
use data\model\RabbitOrderRecordModel;
use data\model\VslGoodsModel;
use data\model\VslIncreMentOrderModel;
use data\model\VslOrderModel;
use addons\discount\model\VslPromotionDiscountModel;
use addons\presell\model\VslPresellModel;
use addons\smashegg\model\VslSmashEggModel;
use addons\scratchcard\model\VslScratchCardModel;
use addons\wheelsurf\model\VslWheelsurfModel;
use addons\paygift\model\VslPayGiftModel;
use addons\followgift\model\VslFollowGiftModel;
use data\model\VslMemberPrizeModel;
use think\Db;
use data\model\ActiveListModel;
use addons\luckyspell\server\Luckyspell as luckySpellServer;
/*
 * rabbitmq延迟计划任务
 */
class RabbitEvents extends BaseService {
    /**
     * 秒杀结束修改商品促销类型
     * @param $seckill_id
     * @param $website_id
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function rabbitUpdateSeckillGoodsPromotionType($seckill_id, $website_id) {
        if(!getAddons('seckill', $website_id)){
            return true;
        }
        $seckill_goods_mdl = new VslSeckGoodsModel();
        $seckill_mdl = new VslSeckillModel();
        $goodsSer = new Goods();
        $survive_time = getSeckillSurviveTime($website_id);
        //查找出过期的活动和商品
        $condition['s.seckill_id'] = $seckill_id;
        $condition['s.seckill_now_time'] = ['<', time() - $survive_time * 3600];
        $condition['sg.past_change_goods_promotion_status'] = 0;
        $past_seckill_info = $seckill_mdl->alias('s')
            ->where($condition)
            ->join('vsl_seckill_goods sg', 's.seckill_id=sg.seckill_id')
            ->group('sg.goods_id')
            ->find();
        //判断如果这个商品存在于未过期的活动中，则不修改
        $condition2['s.website_id'] = $website_id;
        $condition2['s.seckill_now_time'] = ['>=', time() - $survive_time * 3600];
        $condition2['sg.goods_id'] = $past_seckill_info['goods_id'];
        $condition2['sg.del_status'] = 1;
        $is_goods_seckill_info = $seckill_mdl->alias('s')
            ->where($condition2)
            ->join('vsl_seckill_goods sg', 's.seckill_id=sg.seckill_id')
            ->group('sg.goods_id')
            ->find();
        if ($past_seckill_info && !$is_goods_seckill_info) {//如果这个商品确实是过期了，并且没有在未过期的活动中。
            $res = $goodsSer->updateGoods(['goods_id' => $past_seckill_info['goods_id'], 'website_id' => $website_id, 'promotion_type' => 1], ['promotion_type' => 0]);
            $activeListServer = new ActiveList();
            $activeListServer->changeActive($seckill_id, 2,2, $website_id);//2是秒杀
        }
        if ($res) {//用于筛选那些过期的没有更新商品状态的活动。
            $seckill_goods_mdl->where(['seckill_id' => $past_seckill_info['seckill_id'], 'goods_id' => $past_seckill_info['goods_id']])->update(['past_change_goods_promotion_status' => 1]);
        }
    }
    /**
     * 砍价结束修改商品促销类型
     * @param $bargain_id
     * @param $website_id
     * @throws \think\Exception
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     */
    public function rabbitUpdateBargainGoodsPromotionType($bargain_id, $website_id) {
        if(!getAddons('bargain', $website_id)){
            return true;
        }
        $bargain_mdl = new VslBargainModel();
        $goodsSer = new Goods();
        //查找出过期的活动和商品
        $condition['bargain_id'] = $bargain_id;
        $condition['past_change_goods_promotion_status'] = 0;
        $condition['end_bargain_time'] = ['<', time()];
        $past_bargain_info = $bargain_mdl->where($condition)->find();
        if($past_bargain_info){
            //执行修改goods表
            $res = $goodsSer->updateGoods(['goods_id' => $past_bargain_info['goods_id'], 'website_id' => $website_id, 'promotion_type' => 4], ['promotion_type' => 0]);
            if ($res) {
                $bargain_mdl->where(['bargain_id' => $past_bargain_info['bargain_id']])->update(['past_change_goods_promotion_status' => 1]);
            }
            $activeListServer = new ActiveList();
            $activeListServer->changeActive($bargain_id, 5,2, $website_id);//5是砍价
        }
    }
    /**
     * 预售结束修改商品促销类型
     * @param $presell_id
     * @param $website_id
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public function rabbitUpdatePresellGoodsPromotionType($presell_id, $website_id) {
        if(!getAddons('presell', $website_id)){
            return true;
        }
        $presell_mdl = new VslPresellModel();
        $goodsSer = new Goods();
        //查找出过期的活动和商品
        $condition['end_time'] = ['<', time()];
        $condition['id'] = $presell_id;
        $presell_goods_info = $presell_mdl->where($condition)->find();
        //执行修改goods表
        if (!empty($presell_goods_info)) {
            $presell = new Presell();
            //判断该商品是否有重新添加活动
            $new_cond['goods_id'] = $presell_goods_info['goods_id'];
            $is_presell = $presell->getPresellInfoByGoodsId($presell_goods_info['goods_id']);
            if(!$is_presell){
                $goodsSer->updateGoods(['goods_id' => $presell_goods_info['goods_id'], 'website_id' => $website_id, 'promotion_type' => 3], ['promotion_type' => 0]);
                $activeListServer = new ActiveList();
                $activeListServer->changeActive($presell_id, 4,2, $website_id);//4是预售
            }
        }
    }
    /**
     * 限时折扣结束修改商品类型
     * @param $website_id
     * @return bool
     */
    public function rabbitUpdateDiscountGoodsPromotionType($discount_id, $website_id) {
        if(!getAddons('discount', $website_id)){
            return true;
        }
        $discount_model = new VslPromotionDiscountModel();
        $goodsSer = new Goods();
        //查找出过期的活动和商品
        $condition['pd.discount_id'] = $discount_id;
        $condition['pdg.end_time'] = ['<', time()];
        $discount_goods_info = $discount_model
            ->alias('pd')
            ->join('vsl_promotion_discount_goods pdg','pdg.discount_id = pd.discount_id', 'left')
            ->where($condition)
            ->find();
        //执行修改goods表
        if (!empty($discount_goods_info)) {
            $goodsSer->updateGoods(['goods_id' => $discount_goods_info['goods_id'], 'website_id' => $website_id, 'promotion_type' => 5], ['promotion_type' => 0]);
            $activeListServer = new ActiveList();
            $activeListServer->changeActive($discount_id, 1,2, $website_id);//1是限时抢购
        }
    }

    /*
     * 检查直播间是否还剩10分钟开播
     * **/
    public function rabbitCheckLiveCountDown($condition, $website_id)
    {
        try{
            Db::startTrans();
            $live = new LiveModel();
            $anchor = new AnchorModel();
            $live_shopping = new Liveshopping();
            $message_push = new SysMessagePushModel();
            $live_remind = new LiveRemindModel();
            $live_info = $live->getInfo($condition);
            if($live_info){
                $anchor_id = $live_info['anchor_id'];
                $anchor_info = $anchor->getInfo(['anchor_id' => $anchor_id], 'uid');
                $uid = $anchor_info['uid'];
                //主播提醒
                $message_cond['template_type'] = 'advance_remaind';
                $message_cond['website_id'] = $website_id;
                $message_cond['type'] = 5;
                $push_info = $message_push->getInfo($message_cond, 'template_content, is_enable, template_type, template_title');
                $live_shopping->sendMessage($push_info, $anchor_id, $uid);
                $remind_cond['live_id'] = $condition['live_id'];
                $remind_list = $live_remind->getQuery($remind_cond, 'uid', 'create_time desc');
                if($remind_list){
                    //获取推送的信息
                    $message_cond2['template_type'] = 'live_play_notice';
                    $message_cond2['website_id'] = $website_id;
                    $message_cond2['type'] = 5;
                    $push_info2 = $message_push->getInfo($message_cond2, 'template_content, is_enable, template_type, template_title');
                    foreach($remind_list as $k1=>$remind_info){
                        $uid = $remind_info['uid'];
                        $live_shopping->sendMessage($push_info2, 0, $uid);
                    }
                    unset($remind_info);
                    //将这条直播信息更新为已通知用户的状态
                    $live_data['has_remind'] = 1;
                    $live->save($live_data, ['live_id' => $live_info['live_id'], 'has_remind' => 0]);
                    unset($live_info);
                    Db::commit();
                }
            }
        }catch(\Exception $e){
            Db::rollback();
            echo $e->getMessage();exit;
        }
    }
    /*
     * 每分钟检查是否有直播间断开的没有重新连接的
     * **/
    public function rabbitActDisconnectLiveStatus($live_id)
    {
        $live_cond['status'] = 2;
        $live_cond['live_id'] = $live_id;
        $live_cond['disconnect_time'] = [
            ['<=', time()-10*60],
            ['neq', 0]
        ];
        $live_cond['is_leaving'] = 1;
        $live = new LiveModel();
        $anchor = new AnchorModel();
        $live_shopping = new Liveshopping();
        $live_record = new LiveRecordModel();
        $live_info = $live->getInfo($live_cond);
        if($live_info){
            $website_id = $live_info['website_id'] ? : 0;
            $data['status'] = 4;
            $data['end_time'] = time();
            $res = $live->save($data, ['live_id' => $live_info['live_id'], 'status' => 2, 'is_leaving' => 1]);
            $live_record->save($data, ['play_time' => $live_info['play_time'], 'website_id' => $live_info['website_id']]);
            if($res){
                //解散群组
                $im_group_id = $anchor->getInfo(['anchor_id'=>$live_info['anchor_id']], 'im_group_id')['im_group_id'];
                if($im_group_id) {
                    $uname = 'administrator';
                    $sign_data = $live_shopping->getUserSign($uname, $website_id);
                    $sdkappid = $sign_data['sdkAppid'];
                    $usersig = $sign_data['userSig'];
                    $request_url = 'https://console.tim.qq.com/v4/group_open_http_svc/destroy_group?sdkappid=' . $sdkappid . '&identifier=' . $uname . '&usersig=' . $usersig . '&random=99999999&contenttype=json';
                    $request_data['GroupId'] = $im_group_id;
                    $request_str = json_encode($request_data);
                    $res = $live_shopping->curlRequest($request_url, 'POST', '', $request_str);
                }
            }
            unset($live_info);
        }
    }
    /*
     * rabbit延时队列解禁主播
     * **/
    public function rabbitUnforbidAnchor($anchor_id)
    {
        $anchor = new AnchorModel();
        $condition['status'] = 0;
        $condition['forbid_end_time'] = [['neq', 0],['<=', time()]];
        $condition['anchor_id'] = $anchor_id;
        $data['status'] = 1;//改为正常
        $anchor_info = $anchor->getInfo($condition, 'anchor_id');
        if($anchor_info){
            $live_shopping = new Liveshopping();
            $live_shopping->forbidOrResumeStreamPlay($anchor_id, 0, 1);
            $anchor_info = $live_shopping->getAnchor(['anchor_id' => $anchor_id], 'uid, website_id');
            $uid = $anchor_info['uid'];
            $website_id = $anchor_info['website_id'];
            $message_push_info = $live_shopping->getMessagePush(['template_type' => 'unforbid_live', 'website_id' => $website_id, 'type'=>5]);
            $live_shopping->sendMessage($message_push_info, $anchor_id, $uid);
            $anchor->save($data, ['anchor_id' => $anchor_info['anchor_id']]);
        }
    }
    /**
     * 砸金蛋活动
     */
    public function rabbitSmashEgg($smash_egg_id) {
        if(!is_dir('addons/smashegg')){
            return true;
        }
        $smashegg = new VslSmashEggModel();
        try {
            $time = time();
            $start = array(
                'smash_egg_id' => $smash_egg_id,
                'start_time' => array('elt', $time),
                'state' => 1
            );
            $end = array(
                'smash_egg_id' => $smash_egg_id,
                'end_time' => array('lt', $time),
            );
            $smashegg->save(['state' => 2], $start);
            $smashegg->save(['state' => 3], $end);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 大转盘活动
     */
    public function rabbitWheelSurf($wheelsurf_id) {
        if(!is_dir('addons/wheelsurf')){
            return true;
        }
        $wheelsurf = new VslWheelsurfModel();
        try {
            $time = time();
            $start = array(
                'wheelsurf_id' => $wheelsurf_id,
                'start_time' => array('elt', $time),
                'state' => 1
            );
            $end = array(
                'wheelsurf_id' => $wheelsurf_id,
                'end_time' => array('lt', $time),
            );
            $wheelsurf->save(['state' => 2], $start);
            $wheelsurf->save(['state' => 3], $end);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 刮刮卡活动
     */
    public function rabbitScratchCard($scratch_card_id) {
        if(!is_dir('addons/scratchcard')){
            return true;
        }
        $scratchcard = new VslScratchCardModel();
        try {
            $time = time();
            $start = array(
                'scratch_card_id' => $scratch_card_id,
                'start_time' => array('elt', $time),
                'state' => 1
            );
            $end = array(
                'scratch_card_id' => $scratch_card_id,
                'end_time' => array('lt', $time),
            );
            $scratchcard->save(['state' => 2], $start);
            $scratchcard->save(['state' => 3], $end);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 奖品领取过期
     */
    public function rabbitMemberPrize($member_prize_id) {
        $prize = new VslMemberPrizeModel();
        try {
            $time = time();
            $end = array(
                'member_prize_id' => $member_prize_id,
                'expire_time' => array('lt', $time),
                'state' => 1
            );
            $prize->save(['state' => 3], $end);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 支付有礼活动
     */
    public function rabbitPayGift($pay_gift_id) {
        if(!is_dir('addons/paygift')){
            return true;
        }
        $paygift = new VslPayGiftModel();
        try {
            $time = time();
            $start = array(
                'pay_gift_id' => $pay_gift_id,
                'start_time' => array('elt', $time),
                'state' => 1
            );
            $end = array(
                'pay_gift_id' => $pay_gift_id,
                'end_time' => array('lt', $time),
            );
            $paygift->save(['state' => 2], $start);
            $paygift->save(['state' => 3], $end);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 关注有礼活动
     */
    public function rabbitFollowGift($follow_gift_id) {
        if(!is_dir('addons/followgift')){
            return true;
        }
        $followgift = new VslFollowGiftModel();
        try {
            $time = time();
            $start = array(
                'follow_gift_id' => $follow_gift_id,
                'start_time' => array('elt', $time),
                'state' => 1
            );
            $end = array(
                'follow_gift_id' => $follow_gift_id,
                'end_time' => array('lt', $time),
            );
            $followgift->save(['state' => 2], $start);
            $followgift->save(['state' => 3], $end);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * rabbit渠道商订单关闭
     */
    public function rabbitChannelOrdersClose($order_id, $website_id) {
        try {
            $channel_status = getAddons('channel', $website_id);
            if ($channel_status) {
                $config = new Channel();
                $config_info = $config->getChannelConfig($website_id);
            }
            if (!empty($config_info)) {
                $close_time = $config_info['channel_order_close_time'];
            } else {
                $close_time = 1; //默认1分钟
            }
            $time = time() - $close_time * 60; //订单自动关闭
            $condition2 = array(
                'order_status' => 0,
                'create_time' => array('LT', $time),
                'payment_type' => array('neq', 6),
                'order_id' => $order_id,
            );
            if ($channel_status) {
                $channel_order_model = new VslChannelOrderModel();
                $channel_order_info = $channel_order_model->getInfo($condition2);
            }
            if (!empty($channel_order_info)) {
                $order = new Order();
                //渠道商采购/出货
                $order->channelOrderClose($channel_order_info['order_id']);
                unset($v);
            }
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 增值应用订单关闭
     */
    public function rabbitIncrementOrdersClose($order_id) {
        $order_model = new VslIncreMentOrderModel();
        try {
            $close_time = 30; //默认30分钟
            $time = time() - $close_time * 60; //订单自动关闭
            $condition = array(
                'order_status' => 0,
                'create_time' => array('LT', $time),
                'order_id' => $order_id
            );
            $order_info = $order_model->getInfo($condition, 'order_id');
            if (!empty($order_info)) {
                $order_model = new VslIncreMentOrderModel();
                $order_model->save(['order_status' => 1], ['order_id' => $order_info['order_id']]);
            }
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * (non-PHPdoc)
     * @see \data\api\IEvents::ordersComment()
     * 订单完成后自动评论
     */
    public function rabbitOrdersComment($order_id, $website_id = 0){
        $order_model = new VslOrderModel();
        try {
            $config = new Config();
            $translation_text = $config->getConfig(0, 'TRANSLATION_TEXT', $website_id);
            $ror_mdl = new RabbitOrderRecordModel();
            $ror_info = $ror_mdl->getInfo(['order_id' => $order_id], 'order_comment_time');
            if($ror_info){
                $order_comment_time = $ror_info['order_comment_time'] ? : 0;
            }else{
                $translation_time = $config->getConfig(0, 'TRANSLATION_TIME',$website_id);
                if ($translation_time['value'] !== '') {
                    $order_comment_time = $translation_time['value'];
                } else {
                    $order_comment_time = 7; //7天
                }
            }
            $time = time() - 3600 * 24 * $order_comment_time;
            $condition = array(
                'is_evaluate' => 0,
                'order_status' => 4,
                'finish_time' => array('LT', $time),
                'order_id' => $order_id
            );
            $order_info = $order_model->getInfo($condition, 'order_id,buyer_id,order_no,shop_id,store_id,user_name');
            if (!empty($order_info)) {
                $order = new Order();
                $order->ordersComment($order_info, $website_id,$translation_text['value']);
            }
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            return $e->getMessage();
        }
    }
    /**
     * 团开奖
     */
    public function openLuckyspell($record_id){
        debugLog($record_id, '==>openLuckyspell-event<==');
        $luckySpellServer = new luckySpellServer();
        $luckySpellServer->openLuckyspell($record_id);
    }
}
