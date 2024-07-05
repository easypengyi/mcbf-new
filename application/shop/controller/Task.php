<?php

namespace app\shop\controller;

use addons\distribution\Distribution;
use addons\orderbarrage\model\VslOrderBarrageRuleModel;
use addons\orderbarrage\model\VslOrderBarrageVirtualModel;
use addons\orderbarrage\server\OrderBarrage;
use data\model\VslOrderModel;
use data\service\Events;
use data\service\Order;
use think\Controller;
use think\Cache;
use data\model\websiteModel;
use think\Db;
use addons\seckill\model\VslSeckillModel;
use addons\bargain\model\VslBargainModel;
use addons\presell\model\VslPresellModel;
use data\model\VslMemberModel;
use addons\channel\model\VslChannelModel;
use addons\membercard\model\VslMembercardUserModel;
use addons\bonus\model\VslUnGrantBonusOrderModel;
use addons\groupshopping\model\VslGroupShoppingRecordModel;
use addons\liveshopping\model\AnchorModel;
use addons\liveshopping\model\LiveModel;
use addons\miniprogram\model\WeixinAuthModel;
use data\model\RedPackFailRecordModel;
use addons\festivalcare\model\VslFestivalCareModel;
use addons\discount\model\VslPromotionDiscountModel;
use data\model\UserTaskModel;
use data\model\VslExcelsModel;
use data\model\ConfigModel;
use data\model\VslIncreMentOrderModel;
use addons\channel\model\VslChannelOrderModel;
use addons\paygrade\server\PayGrade as PayGradeServer;
use addons\paygrade\model\VslPayGradeRecordsModel;
\think\Loader::addNamespace('data', 'data/');
/**
 * 执行定时任务
 *
 * @author  www.vslai.com
 *
 */
class Task extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * redis锁
     * @param  [string]  $key    [锁键]
     * @param  integer $expire [过期时间 秒]
     * @return [bool]          [bool]
     */
    public function lock($key, $expire = 5)
    {
        $redis = connectRedis();
        $is_lock = $redis->setnx($key, time() + $expire);
        if (!$is_lock) {
            $lock_time = $redis->get($key);
            //锁已过期，重置
            if ($lock_time < time()) {
                $this->unlock($key);
                $is_lock = $redis->setnx($key, time() + $expire);
            }
        }
        return $is_lock ? true : false;
    }

    /**
     * redis解锁
     * @param  [string] $key [键值]
     * @return [type]      [description]
     */
    public function unlock($key)
    {
        $redis = connectRedis();
        return $redis->del($key);
    }

    /**
     * 加载执行任务
     */
    public function load_task()
    {
        $this->autoTask();
        $this->minutesTask();
        $this->hoursTask();
        $this->twoHoursTask();
        $this->daysTask();
        $this->wholetimeTask();
        echo 'ok';
    }

    /**
     * 加载执行任务2
     */
    public function load_task_two()
    {
        $this->minutesTaskCalculate();
        echo 'ok';
    }

    /**
     * 用户任务发放奖励
     */
    public function load_task_four()
    {
        $event = new Events();
        $user_task_mdl = new UserTaskModel();
        $condition['is_complete'] = 0;
        $condition['get_time'] = ['<=', time()];
        $condition['need_complete_time'] = ['>=', time()];
        $website_id = $user_task_mdl->Query($condition, 'distinct website_id');
        if (!$website_id) {
            exit;
        }
        foreach ($website_id as $k => $v) {
//            if($v == 26){
            $event->userTaskRewards($v);
//            }
        }
        unset($v);
        echo 'ok';
    }

    /**
     * 加载执行任务5 单独独立出来用于计算分销分红
     */
    public function load_task_five()
    {
        $lock = $this->lock('order_calculate_line', 900);//redis分布式锁，防止并发。
        if($lock) {
        // Log::write("定时任务 five------------------------------------" . date('Y-m-d H:i:s',time()));
        $event = new Events();
        $website = new websiteModel();
        $website_id = $website->Query([],'website_id');
        foreach ($website_id as $k=>$v){
            $event->orderCalculate($v);
            }
            $this->unlock('order_calculate_line');//redis分布式锁，防止并发。
            echo 'ok';
        }
        echo 'ok';
    }
    public function load_task_five_rabbitmq()
    {
        $order_id = $_GET['order_id'] ?: 0;
        $event = new Events();
        $event->orderCalculate($order_id);
        echo 'ok';
    }

    /*
     * 每分钟检查主播的直播间是否还差10分钟开播。
     * * */

    public function checkLiveCountDown()
    {
        if(config('is_high_powered')){
            return;
        }
        if(!is_dir('addons/liveshopping')){
            return 1;
        }
        $event = new Events();
        $live = new LiveModel();
        $website_id = $live->Query(['predict_end_time' => [['<=', time() + 10 * 60], ['>', time()]], 'status' => [['eq', 1],], 'has_remind' => 0], 'distinct website_id');
        if (!$website_id) {
            exit;
        }
        foreach ($website_id as $k => $v) {
            //预告开播时间 - 10 * 60 <= time() &&   time() < 预告开播时间
            $cond['advance_time'] = [
                ['<=', time() + 10 * 60],
                ['>', time()]
            ];
            $cond['status'] = [
                    ['eq', 1],
            ];
            $cond['website_id'] = $v;
            $cond['has_remind'] = 0;
            $event->__checkLiveCountDown($cond, $v);
        }
        unset($v);
    }

    /*
     * 每分钟检查是否有直播间断开的
     * * */

    public function actDisconnectLiveStatus()
    {
        if(config('is_high_powered')){
            return;
        }
        if(!is_dir('addons/liveshopping')){
            return 1;
        }
        $event = new Events();
        $live = new LiveModel();
        $website_id = $live->Query(['status' => 2, 'disconnect_time' => [['<=', time() - 10 * 60], ['neq', 0],], 'is_leaving'=>1], 'distinct website_id');
        if (!$website_id) {
            exit;
        }
        foreach ($website_id as $k => $v) {
            $event->__actDisconnectLiveStatus($v);
        }
        unset($v);
    }
    /*

    /*
     * 已经禁播的主播判断时间是否已经到了解禁时间，自动解禁
     * * */

    public function unforbidAnchor()
    {
        if(!is_dir('addons/liveshopping')){
            return 1;
        }
        $event = new Events();
        $anchor = new AnchorModel();
        $website_id = $anchor->Query(['status' => 0, 'forbid_end_time' => [['neq', 0], ['<', time()]]], 'distinct website_id');
        if (!$website_id) {
            exit;
        }
        foreach ($website_id as $k => $v) {
            $event->__unforbidAnchor($v);
        }
        unset($v);
    }

    public function unlockOrderCalcu()
    {
        $this->unlock('orders_complete_line'); //redis分布式锁，防止并发。
        echo '解锁成功!';
    }

    /**
     * 拼团关闭 每秒
     */
    public function load_task_three()
    {
        if(!is_dir('addons/groupshopping') || config('is_high_powered')){
            return 1;
        }
        $event = new Events();
        $groupRecordModel = new VslGroupShoppingRecordModel();
        $website_id = $groupRecordModel->Query(['finish_time' => ['<', time()], 'status' => 0], 'distinct website_id',20);
        if (!$website_id) {
            exit;
        }
        foreach ($website_id as $k => $v) {
            $event->groupShoppingRecordClose($v); //未成团订单自动关闭
        }
        unset($v);
        echo 'ok';
    }

    /**
     * 立即执行事件
     */
    public function autoTask()
    {
        if(!is_dir('addons/bonus')){
            return 1;
        }
        $event = new Events();
        $event->mansongOperation();
        $event->discountOperation();
        $order_grant = new VslUnGrantBonusOrderModel();
        $website_id = $order_grant->Query(['grant_status' => 1], 'distinct website_id',20);
        if (!$website_id) {
            exit;
        }
        foreach ($website_id as $k => $v) {
            $event->autoGrantGlobalBonus($v);
            $event->autoGrantAreaBonus($v);
//            $event->autoGrantTeamBonus($v);
        }
        unset($v);
    }

    /**
     * 每分钟执行事件
     */
    public function minutesTaskCalculate()
    {
        $time = time() - 60;
        $cache = Cache::get("vsl_minutes_cal_task");

        if (!empty($cache) && $time < $cache) {
            return 1;
        } else {
            $event = new Events();
            $website_id1 = [];
            $website_id2 = [];
            if(is_dir('addons/festivalcare')){
                $festivalcare = new VslFestivalCareModel();
                $website_id1 = $festivalcare->Query([], 'distinct website_id');
            }
            if(is_dir('addons/microshop')){
                $member = new VslMemberModel();
                $website_id2 = $member->Query(['isshopkeeper' => 2, 'shopkeeper_level_time' => ['<', time()]], 'distinct website_id');
            }
            $website_id = array_merge($website_id1, $website_id2);
            if (!$website_id) {
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->festivalCare($v);
                $event->autoDownMicLevel($v);//店主等级到期降级为默认
            }
            unset($v);
            Cache::set("vsl_minutes_cal_task", time());
            return 1;
        }
    }

    /**
     * 每分钟执行事件
     */
    public function minutesTask(){
//        $order = new Order();
//        $order->orderComplete(71, 1);
//        echo 'success';
//        die;

        $time = time()-60;
        $cache = Cache::get("vsl_minutes_task");
        if(!empty($cache) && $time<$cache)
        {
            return 1;
        }else{
            $lock = $this->lock('minutes_task', 3600);//redis分布式锁，防止并发。
            if($lock){
            $event = new Events();
            $website = new websiteModel();
            $website_id = $website->Query([],'website_id');
            foreach ($website_id as $k=>$v){
//                $event->ordersComplete($v);
                $event->ordersClose($v);
                $event->channelOrdersClose($v);//渠道商订单关闭
                $event->ordersCloseGroup($v);
//                $event->presell_order_close($v);
                $event->updateSeckillUncheckGoodsPromotionType($v);//将忘记审核的已进行秒杀商品去掉活动类型
                    $event->ordersComment($v); //自动评论设置
//                    $event->deleteExcels($v); //删除失效文件
            }
            Cache::set("vsl_minutes_task", time());
                $this->unlock('minutes_task');
            }
            return 1;
        }
    }
    /**
     * 每分钟执行事件
     */
    public function ordersClose()
    {
        if(config('is_high_powered')){
            return;
        }
        $lock = $this->lock('orders_close_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $website = new websiteModel();
            $website_id = $website->Query([],'website_id');
            foreach ($website_id as $k=>$v){
                $event->ordersClose($v, 1);
                $event->seckillOrderClose($v, 1);
                $event->bargainOrderClose($v, 1);
            }
            unset($v);
            $this->unlock('orders_close_line');
        }
        return 1;
    }


    /**
     * 自动完成
     */
    public function ordersComplete()
    {
        if(config('is_high_powered')){
            return;
        }
        $lock = $this->lock('orders_complete_line', 3600); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $website = new websiteModel();
            $website_id = $website->Query([],'website_id');
            foreach ($website_id as $k=>$v){
                $event->ordersComplete($v);
            }
            unset($v);
            $this->unlock('orders_complete_line');
            return 2;
        }
        return 1;
    }


    public function payDistribution()
    {
        $events = new Events();
        $order_id = $_GET['order_id'];
        $events->orderCalculate($order_id);
        echo $order_id;
    }
    /**
     * 统一处理佣金分红。冻结跟解冻的定时执行两次就行了
     */
    public function allCommitionCalcalate()
    {
        $order_ids = [99839, 99965];
        $website_id = 4461;
        foreach ($order_ids as $order_id) {
            //分销
            hook('updateOrderCommission', ['order_id' => $order_id, 'website_id' => $website_id]);
            //全球
            hook('updateOrderGlobalBonus', ['order_id' => $order_id, 'website_id' => $website_id]);
            //团队
            hook('updateOrderTeamBonus', ['order_id' => $order_id, 'website_id' => $website_id]);
        }
    }




    /*
     * 自动发货
     * ** */
    public function autoDelivery()
    {
        if(config('is_high_powered')){
            return;
        }
        $lock = $this->lock('auto_delivery_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $website = new websiteModel();
            $website_id = $website->Query([],'website_id');
            foreach ($website_id as $k=>$v){
                $event->autoDelivery($v);
            }
            unset($v);
            $this->unlock('auto_delivery_line');
        }
        return 1;
    }
    /**
     * 更新秒杀状态
     */
    public function upSecGoodsProType()
    {
        if(!is_dir('addons/seckill') || config('is_high_powered')){
            return 1;
        }
        $time = time() - 3600;
        $cache = Cache::get("vsl_hours_task_sec_line");
        if (!empty($cache) && $time < $cache) {
            return 1;
        } else {
            $event = new Events();
            $seckill_mdl = new VslSeckillModel();
            $website_id = $seckill_mdl->Query(['seckill_now_time' => ['<', time() - 1 * 3600]], 'distinct website_id');
            if (!$website_id) {
                Cache::set("vsl_hours_task_sec_line", time());
                exit;
            }
            foreach ($website_id as $k => $v) {
                //秒杀结束修改商品promotion_type字段
                $event->updateSeckillGoodsPromotionType($v);
            }
            unset($v);
            Cache::set("vsl_hours_task_sec_line", time());
            return 1;
        }
    }

    /**
     * 每小时执行事件
     */
    public function upBargainProType()
    {
        if(!is_dir('addons/bargain') || config('is_high_powered')){
            return 1;
        }
        $time = time() - 3600;
        $cache = Cache::get("vsl_hours_task_bargain_line");
        if (!empty($cache) && $time < $cache) {
            return 1;
        } else {
            $event = new Events();
            $bargain_mdl = new VslBargainModel();
            $website_id = $bargain_mdl->Query(['past_change_goods_promotion_status' => 0, 'end_bargain_time' => ['<', time()]], 'distinct website_id');
            if (!$website_id) {
                Cache::set("vsl_hours_task_bargain_line", time());
                exit;
            }
            foreach ($website_id as $k => $v) {
                //砍价结束修改商品promotion_type字段
                $event->updateBargainGoodsPromotionType($v);
            }
            unset($v);
            Cache::set("vsl_hours_task_bargain_line", time());
            return 1;
        }
    }

    /**
     * 每小时执行事件
     */
    public function upDiscountProType()
    {
        if(!is_dir('addons/discount') || config('is_high_powered')){
            return 1;
        }
        $time = time() - 3600;
        $cache = Cache::get("vsl_hours_task_discount_line");
        if (!empty($cache) && $time < $cache) {
            return 1;
        } else {
            $event = new Events();
            $discount_model = new VslPromotionDiscountModel();
            $website_id = $discount_model->Query([], 'distinct website_id');
            if (!$website_id) {
                Cache::set("vsl_hours_task_discount_line", time());
                exit;
            }
            foreach ($website_id as $k => $v) {
                //限时折扣结束修改字段
                $event->updateDiscountGoodsPromotionType($v);
            }
            unset($v);
            Cache::set("vsl_hours_task_discount_line", time());
            return 1;
        }
    }

    /**
     * 每小时执行事件
     */
    public function upPresellProType()
    {
        if(!is_dir('addons/presell') || config('is_high_powered')){
            return 1;
        }
        $time = time() - 3600;
        $cache = Cache::get("vsl_hours_task_presell_line");
        if (!empty($cache) && $time < $cache) {
            return 1;
        } else {
            $event = new Events();
            $presell_mdl = new VslPresellModel();
            $website_id = $presell_mdl->Query(['end_time' => ['<', time()]], 'distinct website_id');
            if (!$website_id) {
                Cache::set("vsl_hours_task_presell_line", time());
                exit;
            }
            foreach ($website_id as $k => $v) {
                $event->updatePresellGoodsPromotionType($v);
            }
            unset($v);
            Cache::set("vsl_hours_task_presell_line", time());
            return 1;
        }
    }

    /**
     * 会员卡周期送优惠券
     */
    public function membercardCircleGiveCoupon()
    {
        if(!is_dir('addons/membercard') || config('is_high_powered')){
            return 1;
        }
        $lock = $this->lock('membercardCircleGiveCoupon', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $membercard_user_mdl = new VslMembercardUserModel();
            $website_id = $membercard_user_mdl->Query(['status' => 1], 'distinct website_id',20);
            if (!$website_id) {
                $this->unlock('membercardCircleGiveCoupon');
                exit;
            }
            foreach ($website_id as $k => $v) {
                $event->membercardCircleGiveCoupon($v);
            }
            unset($v);
            $this->unlock('membercardCircleGiveCoupon');
            echo 'success';
        }
    }
    /**
     * 每两小时执行一次
     */
    public function twoHoursTask()
    {
        $time = time() - 7200;
        $cache = Cache::get("vsl_tow_hours_task");
        if (!empty($cache) && $time < $cache) {
            return 1;
        } else {
            $event = new Events();
            $weixin_auth_model = new WeixinAuthModel();
            $website_id = $weixin_auth_model->Query([], 'distinct website_id');
            if (!$website_id) {
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->refreshAuthAccessToken($v);
            }
            unset($v);
            Cache::set("vsl_tow_hours_task", time());
            return 1;
        }
    }

    /**
     * 每天执行事件
     */
    public function daysTask()
    {
        $time = time();
        $start_cache_time = strtotime(date('Y-m-d 03:00:00'));
        $end_cache_time = strtotime(date('Y-m-d 04:00:00'));
        if (($time >= $start_cache_time) && ($time <= $end_cache_time)) {
            $event = new Events();
            $red_pack_fail_record_model = new RedPackFailRecordModel();
            $website_id1 = $red_pack_fail_record_model->Query([], 'distinct website_id');
            $website = new websiteModel();
            $website_id2 = $website->Query(['shop_validity_time' => ['<', time() + 3600 * 24 * 8]], 'website_id');
            $website_id = array_merge($website_id1, $website_id2);
            if (!$website_id) {
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->reSendRedPack($v);
                $event->sendMail($v);
                $event->autoDownMerchantsLevel($v);//招商员自动降级
            }
            unset($v);
            return 1;
        }
    }

    /**
     * 每天整点执行事件
     */
    public function wholetimeTask()
    {
        if(config('is_high_powered')){
            return;
        }
        $event = new Events();
        $website = new websiteModel();
        $website_id = $website->Query([], 'website_id');
        foreach ($website_id as $k => $v) {
            $event->smashEgg($v);
            $event->wheelSurf($v);
            $event->scratchCard($v);
            $event->memberPrize($v);
            $event->payGift($v);
            $event->followGift($v);
        }
        unset($v);
        //            Cache::set("vsl_wholetime_task", strtotime(date('Y-m-d',time())));
        return 1;
        //        }else{
        //            return 1;
        //        }
    }

    //批量写入rctype
    public function updateRctype()
    {
        $website = new websiteModel();
        $website_id = $website->Query([], 'website_id');
        $rpcType = Config::get('blockchain.rpcType');
        foreach ($website_id as $k => $v) {
            $website_model = new WebSiteModel();
            $res = $website_model->save(['rpcType' => $rpcType], ['website_id' => $v]);
        }
        unset($v);
    }

    public function updateAgentLevelInfo()
    {
        if(!is_dir('addons/globalbonus')){
            return 1;
        }
	$event = new Events();
        $website = new websiteModel();
        $website_id = $website->Query([], 'website_id');
	if (!$website_id) {
            exit;
        }
        foreach ($website_id as $v) {
            $event->updateAgentLevelInfo($v);
        }
        unset($v);
        echo ok;
    }

    /**
     * 定时将过期的赠送优惠券返还。
     */
    public function checkSendCouponBack()
    {
        $lock = $this->lock('check_send_coupon_back', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $website = new websiteModel();
            $event = new Events();
            $website_id = $website->Query([], 'website_id');
            foreach ($website_id as $v) {
                $event->checkSendCouponBack($v);
            }
            $this->unlock('check_send_coupon_back');
            echo 'success';
        }
    }
    /**
     * 分销商升降级
     */
    public function autoDownDistributorLevel()
    {
        if(!is_dir('addons/distribution')){
            return 1;
        }
        $lock = $this->lock('autoDownDistributorLevel', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $member = new VslMemberModel();
            $website_id = $member->Query(['isdistributor' => 2], 'distinct website_id',20);
            if (!$website_id) {
                $this->unlock('autoDownDistributorLevel');
                exit;
            }
            foreach ($website_id as $k => $v) {
                //分销、渠道商升降级
                $event->autoDownDistributorLevel($v);
            }
            unset($v);
            $this->unlock('autoDownDistributorLevel');
            echo 'success';
        }
    }

    /**
     * 全球分红升降级
     */
    public function autoDownGlobalAgentLevel()
    {
        if(!is_dir('addons/globalbonus')){
            return 1;
        }
        $lock = $this->lock('autoDownGlobalAgentLevel', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $memberModel = new VslMemberModel();
            $website_id = $memberModel->Query(['is_global_agent' => 2], 'distinct website_id',20);
            if (!$website_id) {
                $this->unlock('autoDownGlobalAgentLevel');
                exit;
            }
            foreach ($website_id as $k => $v) {
                //分销、渠道商升降级
                $event->autoDownGlobalAgentLevel($v);
            }
            unset($v);
            $this->unlock('autoDownGlobalAgentLevel');
            echo 'success';
        }
    }

    /**
     * 区域分红升降级
     */
    public function autoDownAreaAgentLevel()
    {
        if(!is_dir('addons/areabonus')){
            return 1;
        }
        $lock = $this->lock('autoDownAreaAgentLevel', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $memberModel = new VslMemberModel();
            $website_id = $memberModel->Query(['is_area_agent' => 2], 'distinct website_id',20);
            if (!$website_id) {
                $this->unlock('autoDownAreaAgentLevel');
                exit;
            }
            foreach ($website_id as $k => $v) {
                //分销、渠道商升降级
                $event->autoDownAreaAgentLevel($v);
            }
            unset($v);
            $this->unlock('autoDownAreaAgentLevel');
            echo 'success';
        }
    }

    /**
     * 团队分红升降级
     */
    public function autoDownTeamAgentLevel()
    {
        if(!is_dir('addons/teambonus')){
            return 1;
        }
        $lock = $this->lock('autoDownTeamAgentLevel', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $memberModel = new VslMemberModel();
            $website_id = $memberModel->Query(['is_team_agent' => 2], 'distinct website_id',20);
            if (!$website_id) {
                $this->unlock('autoDownTeamAgentLevel');
                exit;
            }
            foreach ($website_id as $k => $v) {
                //分销、渠道商升降级
                $event->autoDownTeamAgentLevel($v);
            }
            unset($v);
            $this->unlock('autoDownTeamAgentLevel');
            echo 'success';
        }
    }

    /**
     * 渠道商升降级
     */
    public function autoDownChannelAgentLevel()
    {
        if(!is_dir('addons/channel')){
            return 1;
        }
        $lock = $this->lock('autoDownChannelAgentLevel', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $channel_mdl = new VslChannelModel();
            $website_id = $channel_mdl->Query(['status' => 1], 'distinct website_id',20);
            if (!$website_id) {
                $this->unlock('autoDownChannelAgentLevel');
                exit;
            }
            foreach ($website_id as $k => $v) {
                //分销、渠道商升降级
                $event->autoDownChannelAgentLevel($v);
            }
            unset($v);
            $this->unlock('autoDownChannelAgentLevel');
            echo 'success';
        }
    }
    /*
     * 渠道商订单关闭
     */
    public function channelOrdersClose(){
        if(!is_dir('addons/channel') || config('is_high_powered')){
            return 1;
        }
        $lock = $this->lock('channel_orders_close_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $channel_order_model = new VslChannelOrderModel();
            $order_model = new VslOrderModel();//批量查询
            $condition1 = array(
                'order_status' => 0,
                'payment_type' => array('neq', 6),
                'buy_type' => 2
            );
            $condition2 = array(
                'order_status' => 0,
                'payment_type' => array('neq', 6)
            );
            $website_id1 = $order_model->Query($condition1, 'distinct website_id');
            $website_id2 = $channel_order_model->Query($condition2, 'distinct website_id');
            $website_id = array_merge($website_id1, $website_id2);
            if (!$website_id) {
                $this->unlock('channel_orders_close_line');
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->channelOrdersClose($v); //渠道商订单关闭
            }
            unset($v);
            $this->unlock('channel_orders_close_line');
        }
        return 1;

    }

    /*
     * 增值应用订单关闭
     */
    public function incrementOrdersClose(){
        if(config('is_high_powered')){
            return 1;
        }
        $lock = $this->lock('increment_orders_close_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $order_model = new VslIncreMentOrderModel();
            $condition = array(
                'order_status' => 0,
            );
            $website_id = $order_model->Query($condition, 'distinct website_id');
            if (!$website_id) {
                $this->unlock('increment_orders_close_line');
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->incrementOrdersClose($v); //渠道商订单关闭
            }
            unset($v);
            $this->unlock('increment_orders_close_line');
        }
        return 1;
    }
    /*
     * 拼团订单关闭
     */
    public function ordersCloseGroup(){
        if(!is_dir('addons/groupshopping')){
            return 1;
        }
        $lock = $this->lock('group_orders_close_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $order_model = new VslOrderModel();//批量查询
            $condition = array(
                'order_status' => 0,
                'payment_type' => array('neq', 6),
                'group_id' => ['>', 0]
            );
            $website_id = $order_model->Query($condition, 'distinct website_id', 20);
            if (!$website_id) {
                $this->unlock('group_orders_close_line');
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->ordersCloseGroup($v); //拼团订单关闭
            }
            unset($v);
            $this->unlock('group_orders_close_line');
        }
        return 1;
    }

    /*
     * 自动评论设置
     */
    public function ordersComment(){
        if(config('is_high_powered')){
            return;
        }
        $lock = $this->lock('orders_comment_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $configModel = new ConfigModel();
            $condition = array(
                'key' => 'IS_TRANSLATION',
                'value' => 1
            );
            $website_id = $configModel->Query($condition, 'distinct website_id');
            if (!$website_id) {
                $this->unlock('orders_comment_line');
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->ordersComment($v); //自动评论设置
            }
            unset($v);
            $this->unlock('orders_comment_line');
        }
        return 1;
    }
    /*
     * 删除失效文件
     */
    public function deleteExcels(){
        $lock = $this->lock('delete_excels_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $excelsModel = new VslExcelsModel();
            $website_id = $excelsModel->Query(['status' => 1], 'distinct website_id', 20);
            if (!$website_id) {
                $this->unlock('delete_excels_line');
                return 1;
            }
            foreach ($website_id as $k => $v) {
                $event->deleteExcels($v); //自动评论设置
            }
            unset($v);
            $this->unlock('delete_excels_line');
        }
        return 1;
    }
    /*
     * 将忘记审核的已进行秒杀商品去掉活动类型
     */
    public function updateSeckillUncheckGoodsPromotionType(){
        if(!is_dir('addons/seckill') || config('is_high_powered')){
            return 1;
        }
        $lock = $this->lock('update_seckill_uncheck_line', 900); //redis分布式锁，防止并发。
        if ($lock) {
            $event = new Events();
            $seckill_mdl = new VslSeckillModel();
            $website_id = $seckill_mdl->Query(['seckill_now_time' => ['<', time()]], 'distinct website_id', 20);
            if (!$website_id) {
                $this->unlock('update_seckill_uncheck_line');
                exit;
            }
            foreach ($website_id as $k => $v) {
                $event->updateSeckillUncheckGoodsPromotionType($v);
            }
            unset($v);
            $this->unlock('update_seckill_uncheck_line');
        }
        return 1;
    }
    /**
     * 手动执行付费等级降级
     * test -- 无视时间规则
     */
    public function downPayGradeLevel(){
        $test = $_GET['test'] ? 1 : 2;
        $uid = $_GET['uid'] ? $_GET['uid'] : '';
        $grade_type = $_GET['grade_type'] ? $_GET['grade_type'] : ''; //逗号拼接
        $payGradeRecordsModel = new VslPayGradeRecordsModel();
        $condition['status'] = 0;
        if($test == 2){
            $condition['end_time'] = ['<',time()];
        }
        if($uid){
            $condition['uid'] = $uid;
        }
        if($grade_type){
            $grade_types = explode(',',$grade_type);
            $condition['grade_type'] = ['in',$grade_types];
        }
        $website_id = $payGradeRecordsModel->Query($condition, 'distinct website_id');
        if (!$website_id) {
            echo 'no';
            exit;
        }
        $PayGradeServer = new PayGradeServer();
        foreach ($website_id as $key => $value) {
            $PayGradeServer->downLevels($value);
        }
        echo 'ok';
    }
    /**
     * 弹幕消息数据
     */
    public function barrageMsg()
    {
        //当前时间 <= end_date    当前时间 >= start_date
        $website_id = request()->param('website_id', 0);
        if(!getAddons('orderbarrage', $website_id)){
            return json(['code' => -1, 'data' =>null, 'message' => '应用已关闭']);
        }
        $virtual_time = 100;
        $now_ymd = date('H') * 3600 + date('i') * 60 + date('s');
        $condition['br.start_date'] = ['<=', $now_ymd];
        $condition['br.end_date'] = ['>=', $now_ymd];
        $condition['br.website_id'] = $website_id;
        $condition['obc.state'] = 1;
        $barrage_rule = new VslOrderBarrageRuleModel();
        $rule_info = $barrage_rule->alias('br')->field('rule_id, show_time, is_circle, type, use_place, space_end_time, virtual_num, start_date, end_date')->join('vsl_order_barrage_config obc', 'br.config_id = obc.id', 'left')->where($condition)->find();
        if($rule_info){
            $barrageServer = new OrderBarrage();
            $redis = connectRedis();
            if(!$redis->get('is_request_2')){
                $rule_id = $rule_info['rule_id'];
                $start_key = $rule_info['start_date'];
                $end_key = $rule_info['end_date'];
                $condition0['website_id'] = $website_id;
//                $condition0['state'] = 0;
                $barrage_arr = [];
                if($rule_info['type'] == 1) {//实际订单
                    //拼接redis
                    //投放成功后，根据类型设置队列，若类型存在虚拟数据的话，入虚拟数据队列 key: $config_id_日开始点数日结束点数
                    $redis_real_key = 'real_'.$rule_id.'_'.$start_key.'_'.$end_key;
                    $barrage_info = $redis->rpop($redis_real_key);
                    if($barrage_info){
                        $barrage_arr = json_decode($barrage_info, true);
                        $barrage_arr['show_time'] = $rule_info['show_time'];
                    }
                }elseif($rule_info['type'] == 2) {//虚拟订单
                    $redis_virt_key = 'virt_'.$rule_id.'_'.$start_key.'_'.$end_key;
                    $barrage_info = $redis->rpop($redis_virt_key);
                    if($barrage_info){
                        $barrage_arr = json_decode($barrage_info, true);
                        $barrage_arr['show_time'] = $rule_info['show_time'];
                    }else{
                        if($rule_info['is_circle'] == 1){
                            $virtual_num = $rule_info['virtual_num'] ? : 100;
                            $barrage_list = $barrageServer->getOrderBarrageVirtualList($condition0, $virtual_num);
                            if($barrage_list){
                                foreach($barrage_list['data'] as $k => $barrage_info){
                                    $barrage_info_str = json_encode($barrage_info);
                                    $redis->lpush($redis_virt_key, $barrage_info_str);
                                    $virtual_arr[$k]['id'] = $barrage_info['id'];
                                    $virtual_arr[$k]['state'] = 2;
                                }
                                if($virtual_arr){
                                    $virtual = new VslOrderBarrageVirtualModel();
                                    $virtual->saveAll($virtual_arr);
                                }
                                $barrage_info = $redis->rpop($redis_virt_key);
                                if($barrage_info){
                                    $barrage_arr = json_decode($barrage_info, true);
                                    $barrage_arr['show_time'] = $rule_info['show_time'];
                                    $barrage_arr['place_order_time'] = rand(1,$virtual_time);
                                }
                            }
                        }
                    }
                }elseif($rule_info['type'] == 3) {//虚拟+实际订单
                    //拼接redis
                    //投放成功后，根据类型设置队列，若类型存在虚拟数据的话，入虚拟数据队列 key: $config_id_日开始点数日结束点数
                    $redis_real_key = 'real_'.$rule_id.'_'.$start_key.'_'.$end_key;
                    $barrage_real_info = $redis->rpop($redis_real_key);
                    $redis_virt_key = 'virt_'.$rule_id.'_'.$start_key.'_'.$end_key;
                    $barrage_virt_info = $redis->rpop($redis_virt_key);
                    if($barrage_real_info){//先pop真实数据
                        $barrage_arr = json_decode($barrage_real_info, true);
                        $barrage_arr['show_time'] = $rule_info['show_time'];
                    }elseif($barrage_virt_info){//无真实数据，抛虚拟数据
                        $barrage_arr = json_decode($barrage_virt_info, true);
                        $barrage_arr['show_time'] = $rule_info['show_time'];
                        $barrage_arr['place_order_time'] = rand(1, $virtual_time);
                    }else{//啥都没有了，则判断是否设置了循环
                        if($rule_info['is_circle'] == 1){
                            $virtual_num = $rule_info['virtual_num'] ? : 100;
                            $barrage_list = $barrageServer->getOrderBarrageVirtualList($condition0, $virtual_num);
                            if($barrage_list){
                                foreach($barrage_list['data'] as $k => $barrage_info){
                                    $barrage_info_str = json_encode($barrage_info);
                                    $redis->lpush($redis_virt_key, $barrage_info_str);
                                    $virtual_arr[$k]['id'] = $barrage_info['id'];
                                    $virtual_arr[$k]['state'] = 2;
                                }
                                if($virtual_arr){
                                    $virtual = new VslOrderBarrageVirtualModel();
                                    $virtual->saveAll($virtual_arr);
                                }
                                $barrage_info = $redis->rpop($redis_virt_key);
                                if($barrage_info){
                                    $barrage_arr = json_decode($barrage_info, true);
                                    $barrage_arr['show_time'] = $rule_info['show_time'];
                                    $barrage_arr['place_order_time'] = rand(1, $virtual_time);
                                }
                            }
                        }
                    }
                }
                $redis->setex('is_request_2', $rule_info['space_end_time'] + $rule_info['show_time'], 1);
                if($barrage_arr){
                    return json(['code' => 1, 'data' =>$barrage_arr]);
                }else{
                    return json(['code' => 1, 'data' =>null]);
                }
            }else{
                return json(['code' => 1, 'data' =>null]);
            }
        }else{
            return json(['code' => 1, 'data' =>null]);
        }
    }
    public function changeOrderBonus_bak()
    {
        $lock = $this->lock('change_order_bonus_line', 600); //redis分布式锁，防止并发。
        if ($lock) {
            $path = ROOT_PATH . '/public';
            $start = !@file_get_contents($path . '/start') ? 0 : @file_get_contents($path . '/start');//从文件获取当前页码
            $now = !@file_get_contents($path . '/now') ? 0 : @file_get_contents($path . '/now');//从文件获取当前页码
            $page_size = 100;
            $bonus = new \addons\bonus\model\VslOrderBonusModel();
            $order = new \data\model\VslOrderModel();//获取数量
            $count = $order->getCount([]);
            if($count - $now < 100){
                echo 'o11k';
                exit;
            }
            $saveStart = ($start+$page_size) > $count ? (int)($count - 1) : (int)($start+$page_size);
            if($start + 1 >= $count){
                $this->unlock('change_order_bonus_line');
                echo 'oaak'.$saveStart;
                exit;
            }
            $limit = $start.','.$page_size;
            $orderids = $order->Query(['order_status' => 4], 'order_id', $limit,'order_id asc');

            if(!$orderids){
                file_put_contents($path . '/start', $saveStart);
                file_put_contents($path . '/now', $start);
                $this->unlock('change_order_bonus_line');
                echo 'obbk'.$saveStart;
                exit;
            }
            $orderBonus = objToArr(Db::table('vsl_order_bonus_copy')->alias('a')->join('vsl_order b','a.order_id=b.order_id')->where(['a.order_id' => ['in', $orderids]])->field('a.*,b.create_time,b.finish_time')->select());
            $insertData = [];
            if(!$orderBonus){
                file_put_contents($path . '/start', $saveStart);
                file_put_contents($path . '/now', $start);
                $this->unlock('change_order_bonus_line');
                echo 'occk'.$saveStart;
                exit;
            }
            foreach($orderBonus as $val){
                $order_goods_id = $val['order_goods_id'];
                if($val['from_type'] == 1){
                    $insertData[$order_goods_id]['global_bonus'] = isset($insertData[$order_goods_id]['global_bonus']) ? ($insertData[$order_goods_id]['global_bonus'] + $val['bonus']) : $val['bonus'];
                }
                if($val['from_type'] == 2){
                    $insertData[$order_goods_id]['area_bonus'] = isset($insertData[$order_goods_id]['area_bonus']) ? ($insertData[$order_goods_id]['area_bonus'] + $val['bonus']) : $val['bonus'];
                }
                if($val['from_type'] == 3){
                    $insertData[$order_goods_id]['team_bonus'] += isset($insertData[$order_goods_id]['team_bonus']) ? ($insertData[$order_goods_id]['team_bonus'] + $val['bonus']) : $val['bonus'];
                }
                $orderbonusglobal = objToArr(Db::table('vsl_order_bonus_copy')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 1])->field('uid,bonus')->select());
                if($orderbonusglobal){
                    $orderbonusglobal = array_columns($orderbonusglobal, NULL, 'uid');
                    foreach($orderbonusglobal as $kg => $vg){
                        $orderbonusglobal[$kg]['tips'] = '_'.$vg['uid'].'_';
                        unset($orderbonusglobal[$kg]['uid']);
                    }
                    unset($vg);
                    $insertData[$order_goods_id]['global_bonus_details'] = json_encode($orderbonusglobal,true);
                }
                $orderbonusarea = objToArr(Db::table('vsl_order_bonus_copy')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 2])->field('uid,bonus')->select());
                if($orderbonusarea){
                    $orderbonusarea = array_columns($orderbonusarea, NULL, 'uid');
                    foreach($orderbonusarea as $ka => $va){
                        $orderbonusarea[$ka]['tips'] = '_'.$va['uid'].'_';
                        unset($orderbonusarea[$ka]['uid']);
                    }
                    unset($va);
                    $insertData[$order_goods_id]['area_bonus_details'] = json_encode($orderbonusarea,true);
                }
                $orderbonusteam = objToArr(Db::table('vsl_order_bonus_copy')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 3])->field('uid,bonus,level_award')->select());
                if($orderbonusteam){
                    $orderbonusteam = array_columns($orderbonusteam, NULL, 'uid');
                    foreach($orderbonusteam as $kt => $vt){
                        $orderbonusteam[$kt]['tips'] = '_'.$vt['uid'].'_';
                        unset($orderbonusteam[$kt]['uid']);
                    }
                    unset($vt);
                    $insertData[$order_goods_id]['team_bonus_details'] = json_encode($orderbonusteam,true);
                }
                if(isset($insertData[$order_goods_id]['order_id'])){
                    continue;
                }
                $insertData[$order_goods_id]['order_id'] = $val['order_id'];
                $insertData[$order_goods_id]['order_goods_id'] = $val['order_goods_id'];
                $insertData[$order_goods_id]['buyer_id'] = $val['buyer_id'];
                $insertData[$order_goods_id]['website_id'] = $val['website_id'];
                $insertData[$order_goods_id]['area_pay_status'] = $val['pay_status'];
                $insertData[$order_goods_id]['team_pay_status'] = $val['pay_status'];
                $insertData[$order_goods_id]['global_pay_status'] = $val['pay_status'];
                $insertData[$order_goods_id]['area_cal_status'] = $val['cal_status'];
                $insertData[$order_goods_id]['team_cal_status'] = $val['cal_status'];
                $insertData[$order_goods_id]['global_cal_status'] = $val['cal_status'];
                $insertData[$order_goods_id]['area_return_status'] = $val['return_status'];
                $insertData[$order_goods_id]['team_return_status'] = $val['return_status'];
                $insertData[$order_goods_id]['global_return_status'] = $val['return_status'];
                $insertData[$order_goods_id]['shop_id'] = $val['shop_id'];
                $insertData[$order_goods_id]['create_time'] = $val['create_time'];
                $insertData[$order_goods_id]['update_time'] = $val['finish_time'];
            }
            unset($val);
            $insertData = array_merge($insertData);
            $bonuslog = new \addons\bonus\model\VslOrderBonusLogModel();
            $bonuslog->saveAll($insertData);
            file_put_contents($path . '/start', $saveStart);
            file_put_contents($path . '/now', $start);
            $this->unlock('change_order_bonus_line');
            echo 'ojbk'.$saveStart;
        }
    }
    /**
     * 每小时执行事件
     */
    public function hoursTask(){
        $time = time()-3600;
        $cache = Cache::get("vsl_hours_task");
        if(!empty($cache) && $time<$cache)
        {
            return 1;
        }else{
            $event = new Events();
            $website = new websiteModel();
            $website_id = $website->Query([],'website_id');
            foreach ($website_id as $k=>$v){
                //秒杀结束修改商品promotion_type字段
                $event->updateSeckillGoodsPromotionType($v);
                //砍价结束修改商品promotion_type字段
                $event->updatebargainGoodsPromotionType($v);
                //限时折扣结束修改字段
                $event->updateDiscountGoodsPromotionType($v);
                //预售结束修改字段
                $event->updatePresellGoodsPromotionType($v);
            }
            Cache::set("vsl_hours_task", time());
            return 1;
        }
    }
    /**
     * 整点执行
     */
    public function hourlyTask()
    {
        $time = time()-3600;
        $cache = Cache::get("vsl_hourly_task");
        if(!empty($cache) && $time<$cache)
        {
            return 1;
        }else{

            $event = new Events();
            $website = new websiteModel();
            $website_id = $website->Query([],'website_id');
            foreach ($website_id as $k=>$v){
                //分销、渠道商升降级
                $event->autoDownDistributorLevel($v);
                $event->autoDownGlobalAgentLevel($v);
                $event->autoDownAreaAgentLevel($v);
                $event->autoDownTeamAgentLevel($v);
                $event->autoDownChannelAgentLevel($v);
            }
            Cache::set("vsl_hourly_task", strtotime(date('Y-m-d H:00:00')));
            return 1;
        }
    }

    public function changeOrderBonus()
    {
        if(!is_dir('addons/areabonus') && !is_dir('addons/globalbonus') && !is_dir('addons/teambonus')){
            echo 0;
            exit;
        }
        $file = file_get_contents('is_change_bonus');
        if(!$file){
            echo 0;
            exit;
        }
        $fileArr = json_decode($file, true);
        $time = $fileArr['time'];
        $page_size = 1000;
        $page = request()->get('page', 1);
        $start = $page_size * ($page - 1);
        $order = new \data\model\VslOrderModel();
        $limit = $start.','.$page_size;
        $orderids = $order->Query(['create_time' => ['<', $time]], 'order_id', $limit,'order_id asc');

        if(!$orderids){
            echo 0;
            exit;
        }
        $orderBonus = objToArr(Db::table('vsl_order_bonus')->alias('a')->join('vsl_order b','a.order_id=b.order_id')->where(['a.order_id' => ['in', $orderids]])->field('a.*,b.create_time,b.finish_time')->select());
        $insertData = [];

        if(!$orderBonus){
            echo 0;
            exit;
        }
        foreach($orderBonus as $val){
            $order_goods_id = $val['order_goods_id'];
            if($val['from_type'] == 1){
                $insertData[$order_goods_id]['global_bonus'] = isset($insertData[$order_goods_id]['global_bonus']) ? ($insertData[$order_goods_id]['global_bonus'] + $val['bonus']) : $val['bonus'];
            }
            if($val['from_type'] == 2){
                $insertData[$order_goods_id]['area_bonus'] = isset($insertData[$order_goods_id]['area_bonus']) ? ($insertData[$order_goods_id]['area_bonus'] + $val['bonus']) : $val['bonus'];
            }
            if($val['from_type'] == 3){
                $insertData[$order_goods_id]['team_bonus'] = isset($insertData[$order_goods_id]['team_bonus']) ? ($insertData[$order_goods_id]['team_bonus'] + $val['bonus']) : $val['bonus'];
            }
            $orderbonusglobal = objToArr(Db::table('vsl_order_bonus')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 1])->field('uid,bonus')->select());
            if($orderbonusglobal){
                $orderbonusglobal = array_columns($orderbonusglobal, NULL, 'uid');
                foreach($orderbonusglobal as $kg => $vg){
                    $orderbonusglobal[$kg]['tips'] = '_'.$vg['uid'].'_';
                    unset($orderbonusglobal[$kg]['uid']);
                }
                unset($vg);
                $insertData[$order_goods_id]['global_bonus_details'] = json_encode($orderbonusglobal,true);
            }
            $orderbonusarea = objToArr(Db::table('vsl_order_bonus')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 2])->field('uid,bonus')->select());
            if($orderbonusarea){
                $orderbonusarea = array_columns($orderbonusarea, NULL, 'uid');
                foreach($orderbonusarea as $ka => $va){
                    $orderbonusarea[$ka]['tips'] = '_'.$va['uid'].'_';
                    unset($orderbonusarea[$ka]['uid']);
                }
                unset($va);
                $insertData[$order_goods_id]['area_bonus_details'] = json_encode($orderbonusarea,true);
            }
            $orderbonusteam = objToArr(Db::table('vsl_order_bonus')->where(['order_goods_id' => $val['order_goods_id'],'from_type' => 3])->field('uid,bonus,level_award')->select());
            if($orderbonusteam){
                $orderbonusteam = array_columns($orderbonusteam, NULL, 'uid');
                foreach($orderbonusteam as $kt => $vt){
                    $orderbonusteam[$kt]['tips'] = '_'.$vt['uid'].'_';
                    unset($orderbonusteam[$kt]['uid']);
                }
                unset($vt);
                $insertData[$order_goods_id]['team_bonus_details'] = json_encode($orderbonusteam,true);
            }
            if(isset($insertData[$order_goods_id]['order_id'])){
                continue;
            }
            $check = Db::table('vsl_order_bonus_log')->where(['order_goods_id' => $val['order_goods_id']])->field('id')->find();
            if($check){
                unset($insertData[$order_goods_id]);
                continue;
            }
            $insertData[$order_goods_id]['order_id'] = $val['order_id'];
            $insertData[$order_goods_id]['order_goods_id'] = $val['order_goods_id'];
            $insertData[$order_goods_id]['buyer_id'] = $val['buyer_id'];
            $insertData[$order_goods_id]['website_id'] = $val['website_id'];
            $insertData[$order_goods_id]['area_pay_status'] = $val['pay_status'];
            $insertData[$order_goods_id]['team_pay_status'] = $val['pay_status'];
            $insertData[$order_goods_id]['global_pay_status'] = $val['pay_status'];
            $insertData[$order_goods_id]['area_cal_status'] = $val['cal_status'];
            $insertData[$order_goods_id]['team_cal_status'] = $val['cal_status'];
            $insertData[$order_goods_id]['global_cal_status'] = $val['cal_status'];
            $insertData[$order_goods_id]['area_return_status'] = $val['return_status'];
            $insertData[$order_goods_id]['team_return_status'] = $val['return_status'];
            $insertData[$order_goods_id]['global_return_status'] = $val['return_status'];
            $insertData[$order_goods_id]['shop_id'] = $val['shop_id'];
            $insertData[$order_goods_id]['create_time'] = $val['create_time'];
            $insertData[$order_goods_id]['update_time'] = $val['finish_time'];
        }
        unset($val);
        $insertData = array_merge($insertData);
        $bonuslog = new \addons\bonus\model\VslOrderBonusLogModel();
        $bonuslog->saveAll($insertData);
        echo ++$page;
        exit;
    }
    public function tc(){
        $orderServer = new \data\service\Order();
        $orderServer->sendCoupon(7);
        exit;
    }

    /**
 * 手动结算订单
 *
 * @throws \Exception
 */
    public function handSettleOrder(){
        $order_id = request()->param('order_id', 0);
        $order = new Order();
        $order->orderComplete($order_id, 1);
        echo "success";die;
    }

    /**
     * 手动结算订单直推佣金
     */
    public function handSettleOrderCommission(){
        $order_id = request()->param('order_id', 0);
        $params['order_id'] = $order_id;
        $params['website_id'] = 1;
        $distribution = new Distribution();
        $distribution->updateOrderCommission($params);

        echo "success";die;
    }

    /**
     * 手动结算订单
     *
     * @throws \Exception
     */
    public function handGrantTeamBonus(){
        $website_id = 1;
        $params['website_id'] = $website_id;
//        $params['type'] = 2;
        $team_status = getAddons('teambonus', $website_id);
        if ($team_status == 1) {
            hook('autoGrantTeamBonus', $params); //队长分红发放到账户余额，已发放分红增加，待发放分红减少
        }
        echo 'success';die;
    }
}
