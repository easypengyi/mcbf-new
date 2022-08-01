<?php

namespace app\shop\controller;

use addons\appointgivecoupon\server\Appointgivecoupon;
use addons\delivery\model\VslDeliveryFileModel;
use addons\goodhelper\model\VslGoodsHelpModel;
use addons\goodhelper\server\GoodHelper;
use addons\groupshopping\model\VslGroupShoppingRecordModel;
use addons\groupshopping\server\GroupShopping;
use addons\membercard\server\Membercard;
use addons\wxmembermsg\server\WxMemberMsg;
use app\wapapi\controller\mip;
use data\model\VslActiveListModel;
use data\model\VslExcelsModel;
use data\model\VslGoodsModel;
use data\model\VslOrderModel;
use data\model\ConfigModel;
use data\model\RabbitOrderRecordModel;
use data\service\ActiveList;
use data\service\AddonsConfig;
use data\service\Config;
use data\service\Events;
use data\service\Excel;
use data\service\ExcelsExport;
use data\service\Order;
use data\service\Order\Order as OrderService;
use data\service\RabbitEvents;
use think\Controller;


/**
 * 执行定时任务
 *
 * @author  www.vslai.com
 *
 */
class RabbitTask extends Controller
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 测试方法
     */
    public function test()
    {
        $order = new Order();
        $data = '{"order_id":"70925"}';
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $order_type = $data['order_type'] ? : '';
        $order_model = new VslOrderModel();
        $condition = array(
            'order_status' => 0,
            'payment_type' => array('neq', 6),
            'order_id' => $order_id
        );
        if($order_type == 'second_presell_pay'){
            $condition['money_type'] = ['eq', 1];
        }else{
            $condition['money_type'] = ['eq', 0];
        }
        $order_info = $order_model->getInfo($condition);
        if($order_info){
            $order->orderClose($order_id, 1);
        }
        echo 'success';
    }
    /**
     * 供rabbitmq消费者延时调用处理订单关闭
     * @throws \Exception
     */
    public function ordersClose()
    {
        $order = new Order();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
//        $data = '{"order_id":"70912"}';
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $order_type = $data['order_type'] ? : '';
        $order_model = new VslOrderModel();
        $ror_mdl = new RabbitOrderRecordModel();
        $order_close_time = $ror_mdl->getInfo(['order_id' => $order_id], 'order_close_time')['order_close_time'];
        $order_close_time = $order_close_time ? : 0;
        $time = time() - $order_close_time * 60; //订单自动关闭
        $condition = array(
            'order_status' => 0,
            'create_time' => array('<', $time),
            'payment_type' => array('neq', 6),
            'order_id' => $order_id
        );
        if($order_type == 'second_presell_pay'){
            $condition['money_type'] = ['eq', 1];
        }else{
            $condition['money_type'] = ['eq', 0];
        }
        $order_info = $order_model->getInfo($condition);
        if($order_info){
            $order->orderClose($order_id, 1);
        }
        echo 'success';
    }
    /**
     * 供rabbitmq消费者延时调用处理拼团订单关闭
     * @throws \Exception
     */
    public function groupOrdersClose()
    {
        $groupServer = new GroupShopping();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $record_id = $data['record_id'];
        $groupRecordModel = new VslGroupShoppingRecordModel();
        $group_record_info = $groupRecordModel->getInfo(['finish_time' => ['<', time()], 'status' => 0, 'record_id' => $record_id]);
        if($group_record_info){
            $groupServer->groupFail($record_id);
        }
        echo 'success';
    }
    /**
     * 供rabbitmq消费者延时调用处理订单自动收货
     * @throws \Exception
     */
    public function ordersDelivery()
    {
        $order = new OrderService();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $website_id = $data['website_id'];
        $order_model = new VslOrderModel();
        $ror_mdl = new RabbitOrderRecordModel();
        $ror_info = $ror_mdl->getInfo(['order_id' => $order_id], 'order_auto_delivery_time');
        if($ror_info){
            $order_auto_delivery_time = $ror_info['order_auto_delivery_time'] ? : 0;
        }else{
            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_AUTO_DELIVERY', $website_id);
            $order_auto_delivery_time = $config_info['value'] !== '' ? $config_info['value'] : 7;//默认7天
        }
        $time = time() - 3600 * 24 *  $order_auto_delivery_time; //订单自动收货
        $condition = array(
            'order_status' => 2,
            'consign_time' => array('LT', $time),
            'order_id' => $order_id
        );
        $order_info = $order_model->Query($condition, 'order_id');
        if($order_info){
            $order->orderAutoDelivery($order_id);
        }
        echo 'success';
    }
    /**
     * 供rabbitmq消费者延时调用处理订单完成
     * @throws \Exception
     */
    public function ordersComplete()
    {
        $order = new Order();
        $order_model = new VslOrderModel();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $website_id = $data['website_id'];
        $ror_mdl = new RabbitOrderRecordModel();
        $ror_info = $ror_mdl->getInfo(['order_id' => $order_id], 'order_complete_time');
        if($ror_info){
            $order_complete_time = $ror_info['order_complete_time'] ? : 0;
        }else{
            $config = new Config();
            $config_info = $config->getConfig(0, 'ORDER_DELIVERY_COMPLETE_TIME', $website_id);
            $order_complete_time = $config_info['value'] !== '' ? $config_info['value'] : 7;//默认7天
        }
        $time = time() - 3600 * 24 * $order_complete_time;
        $condition = array(
            'order_status' => 3,
            'sign_time' => array('LT', $time),
            'order_id' => $order_id
        );
        $order_info = $order_model->getInfo($condition);
        if($order_info){
            $order->orderComplete($order_id, $website_id);
        }
        echo 'success';
    }
    /**
     * 自动评论
     */
    public function orderComment()
    {
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $website_id = $data['website_id'];
        $configModel = new ConfigModel();
        $condition = array(
            'key' => 'IS_TRANSLATION',
            'value' => 1,
            'website_id' => $website_id
        );
        $is_comment = $configModel->getInfo($condition);
        if($is_comment){
            $event->rabbitOrdersComment($order_id, $website_id); //自动评论设置
        }
        echo 'success';
    }
    /**
     * 自动评论
     */
    public function orderComment2()
    {
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $order_id = 212648;
        $website_id = $data['website_id'];
        $website_id = 1;
        $event->rabbitOrdersComment($order_id, $website_id); //自动评论设置
        echo 'success';
    }
    /**
     * 供rabbitmq消费者调用处理分销分红
     * @return int
     */
    public function rabbitmqActDistributionOrCommition()
    {
        try{
            $data = request()->post('data');
            $data = str_replace('&quot;', '"', $data);
            $data = json_decode($data, true);
            $order_id = $data['order_id'];
            debugLog($data, '$data');
            debugLog($order_id, '$order_id');
            if($_GET['order_id']){
                $order_id = $_GET['order_id'];
            }
            $action = isset($data['action']) ? $data['action'] : '';
            $event = new Events();
            $event->orderCalculate($order_id, $action);
            echo 'success';
        }catch(\Exception $e){
            debugLog($e->getMessage(), '算佣金$e->getMessage()');
            echo $e->getMessage();
        }
    }

    /**
     *  活动过期更新活动状态
     */
    public function upActivityGoodsProType()
    {
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $data['type'] = 'presell';
        $type = $data['type'];
        switch($type){
            case 'seckill':
                $seckill_id = $data['seckill_id'];
                $website_id = $data['website_id'];
                $event->rabbitUpdateSeckillGoodsPromotionType($seckill_id, $website_id);
                break;
            case 'bargain':
                $bargain_id = $data['bargain_id'];
                $website_id = $data['website_id'];
                $event->rabbitUpdateBargainGoodsPromotionType($bargain_id, $website_id);
                break;
            case 'presell':
                $presell_id = $data['presell_id'];
                $website_id = $data['website_id'];
                $event->rabbitUpdatePresellGoodsPromotionType($presell_id, $website_id);
                break;
            case 'discount':
                $discount_id = $data['discount_id'];
                $website_id = $data['website_id'];
                $event->rabbitUpdateDiscountGoodsPromotionType($discount_id, $website_id);
                break;
        }
        echo 'success';
    }
    /*
     * 每分钟检查主播的直播间是否还差10分钟开播。
     * * */
    public function checkLiveCountDown()
    {
        if(!is_dir('addons/liveshopping')){
            return 1;
        }
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $live_id = $data['live_id'];
        $website_id = $data['website_id'];
        //预告开播时间 - 10 * 60 <= time() &&   time() <= 预告开播时间
        $cond['advance_time'] = [
            ['<=', time() + 10 * 60],
            ['>=', time()]
        ];
        $cond['status'] = 1;
        $cond['live_id'] = $live_id;
        $cond['has_remind'] = 0;
        $event->rabbitCheckLiveCountDown($cond, $website_id);
        echo 'success';
    }
    /*
     * 直播间主播10分钟未连上自动下播
     * * */
    public function actDisconnectLiveStatus()
    {
        if(!is_dir('addons/liveshopping')){
            return 1;
        }
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $live_id = $data['live_id'];
//        $live_id = 49;
        $event->rabbitActDisconnectLiveStatus($live_id);
        echo 'success';
    }
    /*
     * 已经禁播的主播判断时间是否已经到了解禁时间，自动解禁
     * * */
    public function unforbidAnchor()
    {
        if(!is_dir('addons/liveshopping')){
            return 1;
        }
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $anchor_id = $data['anchor_id'];
        $event->rabbitUnforbidAnchor($anchor_id);
        echo 'success';
    }

    /**
     * 会员卡周期送优惠券
     */
    public function membercardCircleGiveCoupon()
    {
        if(!is_dir('addons/membercard')){
            return 1;
        }
        $membercard = new Membercard();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $membercard_id = $data['membercard_id'];
        $uid = $data['uid'];
        $membercard->rabbitCircleGiveCoupon($membercard_id, $uid);
        echo 'success';
    }
    /**
     * rabbit延时队列处理砸金蛋、刮刮乐等活动，只需活动开始/到期走此方法。
     */
    public function smallPromotionOpenOrClose()
    {
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        switch($data['type']){
            case 'smash_egg':
                $smash_egg_id = $data['smash_egg_id'];
                $event->rabbitSmashEgg($smash_egg_id);
                break;
            case 'wheel_surf':
                $wheelsurf_id = $data['wheelsurf_id'];
                $event->rabbitWheelSurf($wheelsurf_id);
                break;
            case 'scratch_card':
                $scratch_card_id = $data['scratch_card_id'];
                $event->rabbitScratchCard($scratch_card_id);
                break;
            case 'member_prize':
                $member_prize_id = $data['member_prize_id'];
                $event->rabbitMemberPrize($member_prize_id);
                break;
            case 'pay_gift':
                $pay_gift_id = $data['pay_gift_id'];
                $event->rabbitPayGift($pay_gift_id);
                break;
            case 'follow_gift':
                $follow_gift_id = $data['follow_gift_id'];
                $event->rabbitFollowGift($follow_gift_id);
                break;
        }
        echo 'success';
    }
    /*
     * 渠道商订单关闭
     */
    public function channelOrdersClose(){
        if(!is_dir('addons/channel')){
            return 1;
        }
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $website_id = $data['website_id'];
        $event->rabbitChannelOrdersClose($order_id, $website_id); //渠道商订单关闭
        echo 'success';
    }
    /*
     * 增值应用订单关闭
     */
    public function incrementOrdersClose(){
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $order_id = $data['order_id'];
        $event->rabbitIncrementOrdersClose($order_id); //渠道商订单关闭
        echo 'success';
    }
    /**
     * 活动开始更新活动状态
     */
    public function activityStatus()
    {
        $activeListServer = new ActiveList();
        $active_mdl = new VslActiveListModel();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $website_id = $data['website_id'];
        $type = $data['type'];
        switch($type){
            case 'seckill':
                $seckill_id = $data['seckill_id'];
                $aid = $active_mdl->getInfo(['act_id' => $seckill_id, 'type' => 2, 'website_id' => $website_id])['aid'];
                break;
            case 'bargain':
                $bargain_id = $data['bargain_id'];
                $aid = $active_mdl->getInfo(['act_id' => $bargain_id, 'type' => 5, 'website_id' => $website_id])['aid'];
                break;
            case 'presell':
                $presell_id = $data['presell_id'];
                $aid = $active_mdl->getInfo(['act_id' => $presell_id, 'type' => 4, 'website_id' => $website_id])['aid'];
                break;
            case 'discount':
                $discount_id = $data['discount_id'];
                $aid = $active_mdl->getInfo(['act_id' => $discount_id, 'type' => 1, 'website_id' => $website_id])['aid'];
                break;
            case 'group':
                $group_id = $data['group_id'];
                $aid = $active_mdl->getInfo(['act_id' => $group_id, 'type' => 3, 'website_id' => $website_id])['aid'];
                break;
            case 'luckyspell':
                $group_id = $data['group_id'];
                $aid = $active_mdl->getInfo(['act_id' => $group_id, 'type' => 6, 'website_id' => $website_id])['aid'];
                break;
        }
        $activeListServer->startActive($aid);
        echo 'success';
    }

    /**
     * 定向送券
     */
    public function appointGiveCoupon()
    {
        if(!is_dir('addons/appointgivecoupon')){
            return;
        }
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $appoint = new Appointgivecoupon();
        $appoint->rabbitExecuteAppointgivecoupon($data);
    }
    /**
     * 商品导入
     */
    public function goodsImport()
    {
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $help_id = $data['help_id'];
        $goodsHelpServer = new GoodHelper();
        $goods_help_mdl = new VslGoodsHelpModel();
        $condition = ['help_id' => $help_id];
        $help_info = $goods_help_mdl->getInfo($condition);
        $goodsHelpServer->updateGoodsHelpInfo(['status' => 4], $condition);
        //执行解压，上传
        $res = $goodsHelpServer->addGoodsByXls($help_info['file_name'], $help_info['zip_name'], $help_info['add_type'], $help_info['type'], $help_info['website_id'],$help_info['shop_id'], $help_info['supplier_id']);
        if ($res['code'] == 0) {//失败
            $goodsHelpServer->updateGoodsHelpInfo(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']] , $condition);
        }else if ($res['code'] == 2) {//部分成功
            $goodsHelpServer->updateGoodsHelpInfo(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
        }else if ($res['code'] == 1) {//成功
            $goodsHelpServer->updateGoodsHelpInfo(['status' => 1], $condition);
        }
    }

    /**
     * 队列导出列表
     */
    public function listExport()
    {
        $excelsModel = new VslExcelsModel();
        $excelsService = new ExcelsExport();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $id = $data['excel_id'];
        $condition = ['id' => $id];
        $excelsModel->save(['status' => 4], $condition);
        //执行解压，上传
        $res = $excelsService->exportExcel($id);//返回0：错误 1：正确 2：部分错误
    }

    /**
     * 导表发货
     */
    public function deliveryByExcel()
    {
        $deliveryServer = new \addons\delivery\service\Delivery();
        $delivery_file = new VslDeliveryFileModel();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $delivery_id = $data['delivery_id'];
        $condition = ['id' => $delivery_id];
        $deliveryServer->updateDeliveryFile(['status' => 4], $condition);
        $delivery_file_info = $delivery_file->getInfo(['id' => $delivery_id]);
        //执行解压，上传
        $res = $deliveryServer->deliveryByExcel($delivery_file_info['excel_name'],$delivery_file_info['website_id'], $delivery_file_info['shop_id'], $delivery_file_info['supplier_id']);//返回0：错误 1：正确 2：部分错误
        if ($res['code'] == 0) {//失败
            $deliveryServer->updateDeliveryFile(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
        } else if ($res['code'] == 2) {//部分成功
            $deliveryServer->updateDeliveryFile(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
        } else if ($res['code'] == 1) {//成功
            $deliveryServer->updateDeliveryFile(['status' => 1], $condition);
        }
    }

    /**
     * 电子卡密导入数据
     */
    public function electroncardImport()
    {
        $electroncard_server = new ElectroncardServer;
        $electroncard_file = new VslElectroncardDataFileModel();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        $electroncard_id = $data['electroncard_id'];
        $condition = ['id' => $electroncard_id];
        $electroncard_server->updateElectroncardFile(['status' => 4], $condition);
        $electroncard_file_info = $electroncard_file->getInfo($condition);
        //执行解压，上传
        $res = $electroncard_server->electroncardDataByExcel($electroncard_file_info['excel_name'], $electroncard_file_info['shop_id'], $electroncard_file_info['website_id'], $electroncard_file_info['electroncard_base_id']);//返回0：错误 1：正确 2：部分错误
        if ($res['code'] == 0) {//失败
            $electroncard_server->updateElectroncardFile(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
        } else if ($res['code'] == 2) {//部分成功
            $electroncard_server->updateElectroncardFile(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
        } else if ($res['code'] == 1) {//成功
            $electroncard_server->updateElectroncardFile(['status' => 1], $condition);
        }
    }
    /**
     *  团开奖
     */
    public function openLuckyspell()
    {
        $event = new RabbitEvents();
        $data = request()->post('data');
        $data = str_replace('&quot;', '"', $data);
        $data = json_decode($data, true);
        debugLog($data, '==>openLuckyspell<==');
        $event->openLuckyspell($data['record_id']);
        echo 'success';
    }
}
