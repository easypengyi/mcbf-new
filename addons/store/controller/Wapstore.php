<?php

namespace addons\store\controller;

use addons\giftvoucher\model\VslGiftVoucherRecordsModel;
use addons\membercard\model\VslMembercardUserModel;
use addons\store\model\VslStoreMessageModel;
use addons\store\model\VslStoreModel;
use addons\store\Store as baseStore;
use addons\store\server\Store as storeServer;
use data\model\AlbumPictureModel;
use data\model\UserModel;
use data\model\VslAppointOrderModel;
use data\model\VslGoodsModel;
use data\model\VslGoodsSkuModel;
use data\model\VslMemberCardModel;
use data\model\VslMemberCardRecordsModel;
use data\model\VslOrderGoodsModel;
use data\service\Address;
use data\service\Order as OrderService;
use data\service\promotion\GoodsExpress;
use \think\Session as Session;
use data\model\DistrictModel;
use data\service\Config as WebConfig;
use data\service\Upload\AliOss;
use data\model\VslOrderModel;
use data\service\BaseService;
use addons\store\model\VslStoreAssistantModel;
use addons\giftvoucher\server\GiftVoucher as VoucherServer;
use data\service\MemberCard;
use data\extend\WchatOauth;
use addons\groupshopping\server\GroupShopping;
use think\Validate;
use data\service\StoreGoods;
use data\service\Member;
use data\service\Order\Order as OrderBusiness;
use think\Cookie;
use data\service\UnifyPay;
use think\Db;
use data\service\Feieyun;
use addons\registermarketing\server\RegisterMarketing;
use addons\store\model\VslStorePrinterModel;
use addons\invoice\controller\Invoice as InvoiceController;
use data\model\VslMemberBankAccountModel;
use data\service\Pay\Joinpay;
use data\model\VslBankModel;

/**
 * o2o门店控制器
 * Class GoodHelper
 * @package addons\store\controller
 */
class Wapstore extends baseStore
{

    public $assistantId;
    public $store_id; //店员选择的店铺id
    public $upload_avator;
    public $upload_type;
    public $instance_id;
    private $return = array();

    public function __construct()
    {
        parent::__construct();
        $base = new BaseService();
        $model = $base->getRequestModel();
        $website_id = checkUrl();
        if ($website_id && is_numeric($website_id)) {
            Session::set($model . 'website_id', $website_id);
            $this->website_id = $website_id;
        } elseif (Session::get($model . 'website_id')) {
            $this->website_id = Session::get($model . 'website_id');
        } else {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        if (!isApiLegal()) {
            $data['code'] = -2;
            $data['message'] = '接口签名错误-';
            echo json_encode($data, JSON_UNESCAPED_UNICODE);
            exit;
        }

        $this->initInfo();
        $this->store_id = Session::get($model . 'store_id');
        $this->checkStore();
        $this->upload_avator = 'upload/' . $this->website_id . '/avator/';
        $config = new WebConfig();
        $this->upload_type = $config->getConfigMaster(0, 'UPLOAD_TYPE', 0, 1);
        $store_model = new VslStoreModel();
        $this->instance_id = $store_model->Query(['store_id' => $this->store_id, 'website_id' => $this->website_id], 'shop_id')[0];
    }

    public function initInfo()
    {
        $store = new storeServer();
        $this->assistantId = $store->getAssistantId();
        if (empty($this->assistantId)) {
            echo json_encode(['code' => LOGIN_EXPIRE, 'message' => '登录信息已过期，请重新登陆'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    public function checkStore()
    {
        $action = $_SERVER['REQUEST_URI'];
        if ((empty($this->store_id) || strstr($this->store_id, ',')) && !strstr($action, 'storeList') && !strstr($action, 'selectStore') && !strstr($action, 'getOrderListByCode') && !strstr($action, 'consumerCardDetail')) {
            echo json_encode(['code' => CHOOSE_STORE, 'message' => '请选择门店'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    /*
     * 店员端根据订单id核销订单
     */

    public function pickupOrder()
    {
        $order_id = request()->post("order_id", 0);
        if (!$order_id) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $storeServer = new storeServer();
        $result = $storeServer->pickupOrder($order_id, $this->assistantId);
        if ($result <= 0) {
            return json(AjaxReturn($result));
        }
        return json(['code' => 1, 'message' => '操作成功']);
    }

    /*
     * 店员端根据核销码获取订单信息
     */

    public function getOrderListByCode()
    {
        $code = request()->post("code", 0);
        if (!$code) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $condition['is_deleted'] = 0; // 未删除订单
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['verification_code'] = $code;
        $order_service = new OrderService();
        $storeServer = new storeServer();
        $condition['wapstore'] = 1;
        $list = $order_service->getOrderList(1, 0, $condition, 'create_time DESC');
        if (!$list['data']) {
            return json([
                'code' => -1,
                'message' => '订单不存在',
            ]);
        }
        $order = $list['data'][0];
        $store_id = $order['store_id'];
        $storeInfo = $storeServer->storeDetail($store_id);
        if (!$storeServer->checkStore($store_id, $this->assistantId)) {//店员是否有权限管理该门店订单
            return json([
                'code' => -1,
                'message' => '该码为' . $storeInfo['store_name'] . '核销码，你无法操作，请提示会员到对应门店进行核销。',
            ]);
        }
        if (!$this->store_id || $this->store_id != $store_id) {//登陆未选择门店或者所选门店不一致引导店员重新选择门店
            return json([
                'code' => 0,
                'message' => '获取成功',
                'data' => [
                    'store_id' => $store_id,
                    'prompt' => '该码为' . $storeInfo['store_name'] . '核销码，是否切换门店进行核销？'
                ]
            ]);
        }
        $order_info = [];
        $order_info['order_id'] = $order['order_id'];
        $order_info['order_no'] = $order['order_no'];
        $order_info['out_order_no'] = $order['out_trade_no'];
        $order_info['shop_id'] = $order['shop_id'];
        $order_info['shop_name'] = $order['shop_name'] ?: '自营店';
        $order_info['order_money'] = $order['order_money'];
        $order_info['order_status'] = $order['order_status'];
        $order_info['status_name'] = $order['status_name'];
        $order_info['pay_type_name'] = $order['pay_type_name'];
        $order_info['is_evaluate'] = $order['is_evaluate'];
        $order_info['verification_code'] = $order['verification_code'];
        $order_info['verification_qrcode'] = __IMG($order['verification_qrcode']);
        $order_info['member_operation'] = array_merge($order['member_operation'], [['no' => 'detail', 'name' => '订单详情']]);
        $order_info['promotion_status'] = ($order['promotion_money'] + $order['coupon_money'] > 0) ?: false;

        foreach ($order['order_item_list'] as $key_sku => $item) {
            $order_info['order_item_list'][$key_sku]['order_goods_id'] = $item['order_goods_id'];
            $order_info['order_item_list'][$key_sku]['goods_id'] = $item['goods_id'];
            $order_info['order_item_list'][$key_sku]['sku_id'] = $item['sku_id'];
            $order_info['order_item_list'][$key_sku]['goods_name'] = $item['goods_name'];
            $order_info['order_item_list'][$key_sku]['price'] = $item['price'];
            $order_info['order_item_list'][$key_sku]['num'] = $item['num'];
            $order_info['order_item_list'][$key_sku]['pic_cover'] = getApiSrc($item['picture']['pic_cover']);
            $order_info['order_item_list'][$key_sku]['spec'] = $item['spec'];
            $order_info['order_item_list'][$key_sku]['status_name'] = $item['status_name'];
            $order_info['order_item_list'][$key_sku]['refund_status'] = $item['refund_status'];
        }

        // 当订单需要进行整单售后时，这个字段取订单商品第一个商品的售后状态（目前正确情况，所有商品的refund_status一样），用于判断整单售后操作
        $order_info['order_refund_status'] = reset($order_info['order_item_list'])['refund_status'];


        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'order_list' => $order_info,
            ]
        ]);
    }

    /*
     * 店员端获取门店订单
     */

    public function getStoreOrderList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size') ?: PAGESIZE;
        $order_status = request()->post('order_status');
        $search_text = request()->post('search_text');
        $condition['is_deleted'] = 0; // 未删除订单
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['store_id|card_store_id'] = $this->store_id;
        $condition['shipping_type'] = 2;
        if (is_numeric($search_text)) {
            $condition['order_no'] = ['LIKE', '%' . $search_text . '%'];
        } elseif (!empty($search_text)) {
            $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }
        if ($order_status != '') {
            // $order_status 1 待提货
            if ($order_status == 1) {
                // 订单状态为待发货实际为已经支付未完成还未发货的订单
                $condition['shipping_status'] = 0; // 0 待提货
                $condition['pay_status'] = 2; // 2 已支付
                $condition['order_status'][] = ['neq', 4]; // 4 已完成
                $condition['order_status'][] = ['neq', 5]; // 5 关闭订单
                $condition['order_status'][] = ['neq', -1]; // -1 售后订单
            } else {
                $condition['order_status'] = $order_status;
            }
        } else {
            $condition['order_status'][] = ['neq', 5];
            $condition['order_status'][] = ['neq', -1];
        }
        $order_service = new OrderService();
        $condition['wapstore'] = 1;
        $list = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time DESC');
        $order_list = [];
        foreach ($list['data'] as $k => $order) {
            $order_list[$k]['order_id'] = $order['order_id'];
            $order_list[$k]['order_no'] = $order['order_no'];
            $order_list[$k]['out_order_no'] = $order['out_trade_no'];
            $order_list[$k]['shop_id'] = $order['shop_id'];
            $order_list[$k]['shop_name'] = $order['shop_name'] ?: '自营店';
            $order_list[$k]['order_money'] = $order['order_money'];
            $order_list[$k]['order_status'] = $order['order_status'];
            $order_list[$k]['status_name'] = $order['status_name'];
            $order_list[$k]['pay_type_name'] = $order['pay_type_name'];
            $order_list[$k]['is_evaluate'] = $order['is_evaluate'];
            $order_list[$k]['member_operation'] = array_merge($order['member_operation'], [['no' => 'detail', 'name' => '订单详情']]);
            $order_list[$k]['promotion_status'] = ($order['promotion_money'] + $order['coupon_money'] > 0) ?: false;

            foreach ($order['order_item_list'] as $key_sku => $item) {
                $order_list[$k]['order_item_list'][$key_sku]['order_goods_id'] = $item['order_goods_id'];
                $order_list[$k]['order_item_list'][$key_sku]['goods_id'] = $item['goods_id'];
                $order_list[$k]['order_item_list'][$key_sku]['sku_id'] = $item['sku_id'];
                $order_list[$k]['order_item_list'][$key_sku]['goods_name'] = $item['goods_name'];
                $order_list[$k]['order_item_list'][$key_sku]['price'] = $item['price'];
                $order_list[$k]['order_item_list'][$key_sku]['num'] = $item['num'];
                $order_list[$k]['order_item_list'][$key_sku]['pic_cover'] = getApiSrc($item['picture']['pic_cover']);
                $order_list[$k]['order_item_list'][$key_sku]['spec'] = $item['spec'];
                $order_list[$k]['order_item_list'][$key_sku]['status_name'] = $item['status_name'];
                $order_list[$k]['order_item_list'][$key_sku]['refund_status'] = $item['refund_status'];
            }

            // 当订单需要进行整单售后时，这个字段取订单商品第一个商品的售后状态（目前正确情况，所有商品的refund_status一样），用于判断整单售后操作
            $order_list[$k]['order_refund_status'] = reset($order_list[$k]['order_item_list'])['refund_status'];
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'order_list' => $order_list,
                'page_count' => $list['page_count'],
                'total_count' => $list['total_count']
            ]
        ]);
    }

    /*
     * 店员端获取店员信息
     */

    public function getAssistantInfo()
    {
        $storeServer = new storeServer();
        $assistantInfo = $storeServer->assistantDetail($this->assistantId, $this->store_id);
        if (!$assistantInfo) {
            return json(AjaxReturn(0));
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $assistantInfo]);
    }

    /**
     * 订单详情
     */
    public function orderDetail()
    {
        $order_id = request()->post('order_id');
        if (empty($order_id)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        $district_model = new DistrictModel();
        $order_service = new OrderService();
        $order_info = $order_service->getOrderDetail($order_id);
        if ($order_info['presell_id'] == 0 || $order_info['money_type'] != 2) {//预售商品 尾款加上税费
            $order_info['order_money'] += $order_info['invoice_tax'];
        }

        $order_detail['order_id'] = $order_info['order_id'];
        $order_detail['order_no'] = $order_info['order_no'];
        $order_detail['shop_name'] = $order_info['shop_name'];
        $order_detail['shop_id'] = $order_info['shop_id'];
        $order_detail['order_status'] = $order_info['order_status'];
        $order_detail['payment_type_name'] = $order_info['payment_type_name'];
        $order_detail['promotion_status'] = ($order_info['promotion_money'] + $order_info['coupon_money'] > 0) ?: false;
        $order_detail['order_refund_status'] = reset($order_info['order_goods'])['refund_status'];
        $order_detail['is_evaluate'] = $order_info['is_evaluate'];
        $order_detail['order_money'] = $order_info['order_money'];
        $order_detail['invoice_tax'] = $order_info['invoice_tax'];
        $order_detail['goods_money'] = $order_info['goods_money'];
        $order_detail['shipping_fee'] = $order_info['shipping_money'] - $order_info['promotion_free_shipping'];
        $promotion_money = 0;

        $address_info = $district_model::get($order_info['receiver_district'], ['city.province']);
        $order_detail['receiver_name'] = $order_info['receiver_name'];
        $order_detail['receiver_mobile'] = $order_info['receiver_mobile'];
        $order_detail['receiver_province'] = $address_info->city->province->province_name;
        $order_detail['receiver_city'] = $address_info->city->city_name;
        $order_detail['receiver_district'] = $address_info->district_name;
        $order_detail['receiver_address'] = $order_info['receiver_address'];
        $order_detail['buyer_message'] = $order_info['buyer_message'];
        $order_detail['card_store_id'] = $order_info['card_store_id'];
        $order_detail['deduction_money'] = $order_info['deduction_money'];
        $order_detail['create_time'] = $order_info['create_time'];
        if ($order_info['store_id']) {
            $order_detail['store_id'] = $order_info['store_id'];
            $order_detail['verification_code'] = $order_info['verification_code'];
            $order_detail['verification_qrcode'] = __IMG($order_info['verification_qrcode']);
            $order_detail['store_name'] = $order_info['order_pickup']['store_name'];
            $order_detail['store_tel'] = $order_info['order_pickup']['store_tel'];
            $order_detail['receiver_province'] = $order_info['order_pickup']['province_name'];
            $order_detail['receiver_city'] = $order_info['order_pickup']['city_name'];
            $order_detail['receiver_district'] = $order_info['order_pickup']['dictrict_name'];
            $order_detail['receiver_address'] = $order_info['order_pickup']['address'];
        }
        $isGroupSuccess = -1;
        if (getAddons('groupshopping', $order_info['website_id'], $order_info['shop_id'])) {
            $group_server = new GroupShopping();
            $isGroupSuccess = $group_server->groupRecordDetail($order_info['group_record_id'])['status'];
        }
        $order_status = OrderService\OrderStatus::getSinceOrderStatusForStore($order_info['order_type'], $isGroupSuccess)[$order_info['order_status']];

        $order_detail['member_operation'] = $order_status['member_operation'];

        $order_detail['no_delivery_id_array'] = [];
        foreach ($order_info['order_goods_no_delive'] as $v_goods) {
            $order_detail['no_delivery_id_array'][] = $v_goods['order_goods_id'];
        }

        $goods_packet_list = [];
        foreach ($order_info['goods_packet_list'] as $k => $v_packet) {
            $goods_packet_list[$k]['packet_name'] = $v_packet['packet_name'];
            $goods_packet_list[$k]['shipping_info'] = $v_packet['shipping_info'];
            $goods_packet_list[$k]['order_goods_id_array'] = [];
            foreach ($v_packet['order_goods_list'] as $k_o => $v_goods) {
                $goods_packet_list[$k]['order_goods_id_array'][] = $v_goods['order_goods_id'];
            }
        }
        $order_detail['goods_packet_list'] = $goods_packet_list;

        $order_goods = [];
        foreach ($order_info['order_goods'] as $k => $v) {
            $order_goods[$k]['order_goods_id'] = $v['order_goods_id'];
            $order_goods[$k]['goods_id'] = $v['goods_id'];
            $order_goods[$k]['goods_name'] = $v['goods_name'];
            $order_goods[$k]['sku_id'] = $v['sku_id'];
            $order_goods[$k]['sku_name'] = $v['sku_name'];
            $order_goods[$k]['price'] = $v['price'];
            $order_goods[$k]['num'] = $v['num'];
            $order_goods[$k]['refund_status'] = $v['refund_status'];
            $order_goods[$k]['spec'] = $v['spec'];
            $order_goods[$k]['pic_cover'] = $v['picture_info']['pic_cover'] ? getApiSrc($v['picture_info']['pic_cover']) : '';

            $promotion_money += round(($v['price'] - $v['actual_price']) * $v['num'], 2) + $v['promotion_free_shipping'];
        }

        $order_detail['promotion_money'] = $order_info['platform_promotion_money'] != 0.00 ? $order_info['platform_promotion_money'] : $promotion_money;
        $order_detail['order_goods'] = $order_goods;
        //获取发票信息
        if (getAddons('invoice', $this->website_id, $this->instance_id)) {
            $invoiceController = new InvoiceController();
            $order_detail['invoice'] = $invoiceController->getInvoiceInfoByOrderNo($order_detail['order_no']);
        }

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $order_detail
        ]);
    }

    /*
     * 店员列表
     */

    public function assistantList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", 0);
        $storeServer = new storeServer();
        $condition = array(
            'vsa.website_id' => $this->website_id,
            'vsa.shop_id' => $this->instance_id,
            'vsa.store_id' => $this->store_id,
        );
        $list = $storeServer->assistantList($page_index, $page_size, $condition);
        $list['assistant_list'] = $list['data'];
        unset($list['data']);
        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $list
        ]);
    }

    /*
     * 添加店员时获取岗位列表
     */

    public function getJobsList()
    {
        $storeServer = new storeServer();
        $jobsList = $storeServer->jobsList(1, 0, ['shop_id' => $this->instance_id, 'website_id' => $this->website_id]);
        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $jobsList['data']
        ]);
    }

    /*
     * 添加修改店员
     * * */

    public function addOrUpdateAssistant()
    {
        $storeServer = new storeServer();
        $validate_data = [
            'jobs_id' => 'require',
            'assistant_name' => 'require',
            'assistant_tel' => 'require',
        ];
        if (!request()->post('assistant_id', 0)) {
            $validate_data['password'] = 'require';
        }
        //验证assistant表
        $validate = new Validate($validate_data);
        if (!$validate->check(request()->post())) {
            return json(['code' => -1, 'message' => $validate->getError()]);
        }
        if (request()->post('assistant_id', 0)) {
            $ret_val = $storeServer->updateAssistant(request()->post());
        } else {
            $data = request()->post();
            $data['store_id'] = $this->store_id;
            $ret_val = $storeServer->addAssistant($data);
        }
        if ($ret_val <= 0) {
            return json(AjaxReturn($ret_val));
        }
        return json(['code' => 1, 'message' => '操作成功']);
    }

    /*
     * 销售统计
     */

    public function saleStatistics()
    {
        $start_time = request()->post('start_time', '');
        $end_time = request()->post('end_time', '');

        if (empty($start_time) || empty($end_time)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        $data = array();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'store_id|card_store_id' => $this->store_id
        ];
        $condition['create_time'][] = [
            '>=',
            strtotime($start_time)
        ];
        $condition['create_time'][] = [
            '<=',
            strtotime($end_time)
        ];
        $condition['order_status'][] = ['<>', 0];
        $condition['order_status'][] = ['<>', 5];

        $orderModel = new VslOrderModel();

        $condition['order_type'] = 12;
        //门店收银订单额
        $data['store_cash_order_money'] = $orderModel->getSum($condition, 'order_money');
        //门店收银订单量
        $data['store_cash_order_count'] = $orderModel->getCount($condition);

        $condition['order_type'] = ['<>',12];
        //线上订单额
        $data['online_order_money'] = $orderModel->getSum($condition, 'order_money');
        //线上订单量
        $data['online_order_count'] = $orderModel->getCount($condition);

        //销售总额
        $data['total_order_money'] = $data['store_cash_order_money'] + $data['online_order_money'];
        //成交订单量
        $data['total_order_count'] = $data['store_cash_order_count'] + $data['online_order_count'];

        unset($condition['create_time']);
        $where = [
            'refund_status' => ['<>', 0],
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        $where['refund_time'][] = [
            '>=',
            strtotime($start_time)
        ];
        $where['refund_time'][] = [
            '<=',
            strtotime($end_time)
        ];
        $orderGoodsModel = new VslOrderGoodsModel();
        $order_ids = $orderGoodsModel->Query($where, 'order_id');
        if($order_ids) {
            $condition['order_id'] = ['IN',$order_ids];
            //售后总额
            $data['refund_order_money'] = $orderModel->getSum($condition, 'order_money');
            //售后订单量
            $data['refund_order_count'] = $orderModel->getCount($condition);
        }else{
            //售后总额
            $data['refund_order_money'] = 0;
            //售后订单量
            $data['refund_order_count'] = 0;
        }

        unset($condition['order_id']);
        $condition['sign_time'][] = [
            '>=',
            strtotime($start_time)
        ];
        $condition['sign_time'][] = [
            '<=',
            strtotime($end_time)
        ];
        $condition['order_status'] = [['>=', 3], ['<>', 5]];
        //核销订单数
        $data['finished_count'] = $orderModel->getCount($condition);

        unset($condition['order_status']);
        unset($condition['sign_time']);
        unset($condition['order_type']);
        unset($condition['shop_id']);
        unset($condition['store_id|card_store_id']);

        $condition['num'] = ['>', 0];
        $condition['store_id'] = $this->store_id;
        $member_card_model = new VslMemberCardModel();
        $member_card_records_model = new VslMemberCardRecordsModel();
        $card_ids = $member_card_model->Query($condition,'card_id');
        if($card_ids) {
            $card_condition['create_time'][] = [
                '>=',
                strtotime($start_time)
            ];
            $card_condition['create_time'][] = [
                '<=',
                strtotime($end_time)
            ];
            $card_condition['card_id'] = ['IN',$card_ids];
            //核销消费卡
            $data['member_card'] = $member_card_records_model->getCount($card_condition);
        }else{
            //核销消费卡
            $data['member_card'] = 0;
        }

        unset($condition['num']);
        $condition['shop_id'] = $this->instance_id;
        $condition['state'] = 2;
        $condition['use_time'][] = [
            '>=',
            strtotime($start_time)
        ];
        $condition['use_time'][] = [
            '<=',
            strtotime($end_time)
        ];
        $gift_voucher_records_model = new VslGiftVoucherRecordsModel();
        //核销礼品券
        $data['gift_voucher'] = $gift_voucher_records_model->getCount($condition);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /**
     * 功能说明：文件(图片)上传(存入相册)
     */

    public function uploadImage()
    {

        $file_path = $this->upload_avator;

        // 检测文件夹是否存在，不存在则创建文件夹
        if (!file_exists($file_path)) {
            $mode = intval('0777', 8);
            mkdir($file_path, $mode, true);
        }
        $file_name = $_FILES['file']['name']; // 文件原名
        $temp_file_name = $_FILES['file']['tmp_name']; //临时名称
        $file_size = $_FILES['file']['size']; // 文件大小
        $file_type = $_FILES['file']['type']; // 文件类型

        if ($file_size == 0) {
            $this->return['message'] = '文件大小为0MB';
            return $this->ajaxFileReturn();
        }

        // 验证文件
        if (!$this->validationFile($file_type, $file_size)) {
            return $this->ajaxFileReturn();
        }
        $file_name_explode = explode('.', $file_name); // 图片名称
        $suffix = count($file_name_explode) - 1;
        $ext = '.' . $file_name_explode[$suffix]; // 获取后缀名
        $new_file_name = time() . mt_rand(1000, 9999) . $ext; // 重新命名文件 ,mt_rand防止上传多张时重命名为同一名称

        $ok = $this->moveUploadFile($temp_file_name, $file_path . $new_file_name);
        if ($ok['code']) {
            $this->return['code'] = 1;
            $this->return['data'] = ['src' => getApiSrc($ok['path'])];
            $this->return['message'] = '上传成功';

            $condition['assistant_id'] = $this->assistantId;
            $condition['website_id'] = $this->website_id;
            $data['assistant_headimg'] = $ok['path'];
            $storeServer = new storeServer();
            $storeServer->updateAssistantFiled($data, $condition);

            //删除本地的图片
            if ($this->upload_type == 2) {
                @unlink($file_path . $new_file_name);
            }
        } else {
            // 强制将文件后缀改掉，文件流不同会导致上传文件失败
            $this->return['message'] = '请检查您的上传参数配置或上传的文件是否有误';
        }
        return $this->ajaxFileReturn();
    }

    /**
     *
     * @param unknown $this ->file_path
     *            文件路径
     * @param unknown $this ->file_size
     *            文件大小
     * @param unknown $this ->file_type
     *            文件类型
     * @return string|unknown|number|\think\false
     */

    private function validationFile($file_type, $file_size)
    {
        if ($file_type != 'image/gif' && $file_type != 'image/png' && $file_type != 'image/jpeg' && $file_size > 3000000) {
            $this->return['message'] = '文件上传失败,请检查您上传的文件类型,文件大小不能超过3MB';
            return false;
        }
        return true;
    }

    /**
     * 原图上传
     *
     * @param unknown $file
     * @param string $destination
     */

    public function moveUploadFile($file, $destination)
    {
        $ok = @move_uploaded_file($file, $destination);
        $result = [
            'code' => $ok,
            'path' => $destination,
            'domain' => '',
            'bucket' => ''
        ];
        if ($ok) {
            if ($this->upload_type == 2) {
                $alioss = new AliOss();
                $result = $alioss->setAliOssUplaod($destination, $destination);
                @unlink($_FILES['file_upload']);
            }
        }
        return $result;
    }

    /**
     * 上传文件后，ajax返回信息
     *
     *
     * @param array $return
     */

    private function ajaxFileReturn()
    {
        if (empty($this->return['code']) || null == $this->return['code'] || '' == $this->return['code']) {
            $this->return['code'] = -1; // 错误码
        }

        if (empty($this->return['message']) || null == $this->return['message'] || '' == $this->return['message']) {
            $this->return['message'] = ''; // 消息
        }

        if (empty($this->return['data']) || null == $this->return['data'] || '' == $this->return['data']) {
            $this->return['data'] = ''; // 数据
        }
        return json($this->return);
    }

    /*
     * 店员端获取所管理的门店列表
     */

    public function storeList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", 0);
        $storeServer = new storeServer();
        $list = $storeServer->storeListByAssistantId($this->assistantId, $page_index, $page_size);
        if (!$list['data']) {
            return json(AjaxReturn(0));
        }
        $list['store_list'] = $list['data'];
        foreach ($list['store_list'] as &$item){
            $appointOrderModel = new VslAppointOrderModel();
            $where = [
                'website_id' => $this->website_id,
                'store_id' => $item['store_id'],
                'is_deleted'=> 0,
                'pay_status'=> 1
            ];
            $start_date = date('Y-m-d' . ' 00:00:00', time());
            $end_date = date('Y-m-d' . ' 00:00:00', time() + 3600 * 24);
            $where['appoint_time'][] = [
                '>',
                $start_date
            ];
            $where['appoint_time'][] = [
                '<',
                $end_date
            ];

            $appoint_order_count = $appointOrderModel->getCount($where);
            $item['count'] = $appoint_order_count;
        }


        unset($list['data']);
        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $list
        ]);
    }

    /*
     * 店员端选择了门店
     */

    public function selectStore()
    {
        $store_id = request()->post("store_id", 0);
        if (!$store_id) {
            return json(['code' => -1,
                'message' => '参数错误，请重试'
            ]);
        }
        $storeServer = new storeServer();
        $checkStore = $storeServer->checkStore($store_id, $this->assistantId);
        if (!$checkStore) {
            return json(['code' => -1,
                'message' => '选择门店失败，请重新选择门店'
            ]);
        }
        $base = new BaseService();
        $model = $base->getRequestModel();
        Session::set($model . 'store_id', $store_id);
        $this->store_id = $store_id;
        return json(['code' => 1,
            'message' => '操作成功'
        ]);
    }

    /*
     * 修改密码验证密码
     */

    public function checkPassword()
    {
        $password = request()->post('password');
        if (empty($password)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $storeModel = new VslStoreAssistantModel();
        $condition['website_id'] = $this->website_id;
        $condition['assistant_id'] = $this->assistantId;
        $encryptionPw = md5($password);
        $result = $storeModel->getInfo($condition, 'password');
        if ($result['password'] != $encryptionPw) {
            return json(['code' => -1, 'message' => '密码不正确']);
        }
        return json(['code' => 1, 'message' => '验证通过']);
    }

    /*
     * 修改密码
     */

    public function updatePassword()
    {
        $password = request()->post('password');
        if (empty($password)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $storeServer = new storeServer();
        $condition['website_id'] = $this->website_id;
        $condition['assistant_id'] = $this->assistantId;
        $data['password'] = md5($password);

        $result = $storeServer->updateAssistantFiled($data, $condition);
        if ($result) {
            return json(['code' => 1, 'message' => '密码修改成功']);
        } else {
            return json(['code' => -1, 'message' => '密码修改失败']);
        }
    }

    /**
     * 礼品券详情
     */

    public function userGiftvoucherInfo()
    {
        $gift_voucher_code = input('gift_voucher_code');
        $VoucherServer = new VoucherServer();
        $info = $VoucherServer->getUserGiftvoucherInfo(0, $gift_voucher_code, $this->instance_id);
        if ($info) {
            if ($info['shop_id'] == $this->instance_id) {
                $data['code'] = 1;
                $data['message'] = "获取成功";
                $data['data'] = $info;
            } else {
                $data['code'] = -1;
                $data['message'] = "该二维码不是本店的核销码，无法核销。";
                $data['data'] = $info;
            }
        } else {
            $data['code'] = -1;
            $data['message'] = "获取失败";
        }
        return json($data);
    }

    /**
     * 礼品券核销
     */

    public function giftvoucherUse()
    {
        $gift_voucher_code = input('code');
        $VoucherServer = new VoucherServer();
        $result = $VoucherServer->getUserUse($gift_voucher_code, $this->instance_id, $this->store_id, $this->assistantId);
        if ($result) {
            $this->addUserLog('使用礼品券11:'.$this->assistantId, $result);
        }
        return json(AjaxReturn($result));
    }

    /**
     * 消费卡详情
     */

    public function consumerCardDetail()
    {
        $card_code = input('card_code');
        $member_card = new MemberCard();
        $info = $member_card->getCardDetail(0, $card_code);
        if ($info) {
            if ($info['store_id'] == $this->store_id) {
                $data['code'] = 1;
                $data['message'] = "获取成功";
                $data['data'] = $info;
            } else {
                $storeServer = new storeServer();
                $checkStore = $storeServer->checkStore($info['store_id'], $this->assistantId);
                if (!$checkStore) {
                    $data['code'] = -1;
                    $data['message'] = "该码为" . $info['store_name'] . "核销码，你无法操作，请提示会员到对应门店进行核销。";
                } else {
                    $data['code'] = 0;
                    $data['message'] = "获取成功";
                    $data['data']['store_id'] = $info['store_id'];
                    $data['data']['prompt'] = "该码为" . $info['store_name'] . "核销码，是否切换门店进行核销？";
                }
            }
        } else {
            $data['code'] = -1;
            $data['message'] = "获取失败";
        }
        return json($data);
    }

    /**
     * 消费卡核销
     */

    public function consumerCardUse()
    {
        $card_code = input('code');
        $member_card = new MemberCard();
        $result = $member_card->getCardUse($card_code, $this->store_id, $this->assistantId);
        return json(AjaxReturn($result));
    }

    /*
     * 微信分享接口
     */

    public function share()
    {
        $weixin = new WchatOauth($this->website_id);
        $url = request()->post('url', '');
        $wx_share = $weixin->shareWx(urldecode($url));
        return json(['code' => 1, 'message' => '成功获取', 'data' => $wx_share]);
    }

    /*
     * 经营概况
     */

    public function getIndexCount()
    {
        $start_date = strtotime(date('Y-m-d' . ' 00:00:00', time()));
        $end_date = strtotime(date('Y-m-d' . ' 00:00:00', time() + 3600 * 24));
        $orderModel = new VslOrderModel();
        $orderGoodsModel = new VslOrderGoodsModel();
        $appointOrderModel = new VslAppointOrderModel();

        $data = [];
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'store_id|card_store_id' => $this->store_id
        ];
        $condition['create_time'][] = [
            '>',
            $start_date
        ];
        $condition['create_time'][] = [
            '<',
            $end_date
        ];
        $condition['order_status'][] = ['>', 0];
        $condition['order_status'][] = ['<>', 5];

        //今日销售额
        $data['sale_money'] = $orderModel->getSum($condition, 'shop_order_money');
        //今日订单数
        $data['sale_count'] = $orderModel->getCount($condition);
        //待处理售后
        $orderIds = $orderGoodsModel->getQuery(['refund_status' => 1], 'order_id', '');
        $orderIds = array_unique($orderIds);
        foreach ($orderIds as $key => $val) {
            $order_id[] = $val['order_id'];
        }
        $data['unfinished_after_order'] = $orderModel->getCount(['order_id' => ['IN', $order_id], 'store_id' => $this->store_id]);

        unset($condition['create_time']);
        $condition['sign_time'][] = [
            '>',
            $start_date
        ];
        $condition['sign_time'][] = [
            '<',
            $end_date
        ];
        $condition['order_status'] = [['>=', 3], ['<>', 5]];
        //今日核销订单
        $data['finished_count'] = $orderModel->getCount($condition);
        unset($condition['order_status']);
        unset($condition['sign_time']);
        $condition['order_status'] = [['>', 0], ['<', 3]];
        $data['unfinished_count'] = $orderModel->getCount($condition);

        //未读消息数
        $message_condition = [
            'assistant_id' => $this->assistantId,
            'store_id' => $this->store_id,
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id,
            'message_status' => 0,
        ];
        $store_message_mdl = new VslStoreMessageModel();
        $data['message'] = $store_message_mdl->getCount($message_condition);

        $where = [
            'website_id' => $this->website_id,
            'store_id' => $this->store_id,
            'is_deleted'=> 0,
            'pay_status'=> 1
        ];
        $start_date = date('Y-m-d' . ' 00:00:00', time());
        $end_date = date('Y-m-d' . ' 00:00:00', time() + 3600 * 24);
        $where['appoint_time'][] = [
            '>',
            $start_date
        ];
        $where['appoint_time'][] = [
            '<',
            $end_date
        ];
//        var_dump($where);die;
        $data['appoint_order_count'] = $appointOrderModel->getCount($where);


        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 商品管理-返回此门店下已上架商品/仓库中商品的一级分类
     */

    public function getStoreGoodsCategoryList()
    {
        $type = request()->post("type", 0); //1:已上架商品的一级分类  2:仓库中商品的一级分类
        $store_id = request()->post("store_id", 0) ?: $this->store_id;

        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $store_model = new VslStoreModel();
        $storeServer = new storeServer();
        $stock_type = (int)$storeServer->getStoreSet($this->instance_id)['stock_type'] ?: 1;
        $shop_id = $store_model->Query(['store_id' => $store_id, 'website_id' => $this->website_id], 'shop_id')[0];

        if ($type == 1) {
            //已上架商品的一级分类
            if ($stock_type == 1) {
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'store_id' => $this->store_id,
                    'state' => 1
                );
            } elseif ($stock_type == 2) {
                //显示门店所属店铺的一级分类
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'state' => 1,
                    'store_list' => ['<>', '']
                );
            }
        } elseif ($type == 2) {
            //仓库中商品的一级分类
            if ($stock_type == 1) {
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'store_id' => $this->store_id,
                    'state' => 0
                );
            } elseif ($stock_type == 2) {
                //显示门店所属店铺的一级分类
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'state' => 1,
                );
            }
        } else {
            if ($stock_type == 1) {
                //已上架商品的一级分类
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'store_id' => $this->store_id,
                    'state' => 1
                );
            } elseif ($stock_type == 2) {
                //显示门店所属店铺的一级分类
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'state' => 1,
                    'store_list' => ['<>', '']
                );
            }
        }

        $storeServer = new storeServer();
        $data = $storeServer->getStoreGoodsCategoryList($condition);
        if (empty($data)) {
            $data = [];
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 商品管理-已上架商品/仓库中商品
     */

    public function getGoodsList()
    {
        $category_id = request()->post("category_id", 0);
        $type = request()->post("type", 1); //1:已上架商品  2:仓库中商品
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $search_text = request()->post('search_text');
        $store_id = request()->post("store_id", 0) ?: $this->store_id;

        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $store_model = new VslStoreModel();
        $storeServer = new storeServer();
        $stock_type = (int)$storeServer->getStoreSet($this->instance_id)['stock_type'] ?: 1;
        $shop_id = $store_model->Query(['store_id' => $store_id, 'website_id' => $this->website_id], 'shop_id')[0];

        if ($type == 1) {
            //已上架商品
            if ($search_text) {
                if ($stock_type == 1) {
                    $condition = [
                        'goods_name' => ['LIKE', '%' . $search_text . '%'],
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'store_id' => $this->store_id,
                        'state' => 1
                    ];
                } elseif ($stock_type == 2) {
                    //门店所属店铺的商品
                    $condition = [
                        'goods_name' => ['LIKE', '%' . $search_text . '%'],
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'state' => 1,
                        'store_list' => ['<>', ''],
                        'goods_type' => ['NOT IN', [3, 4]]
                    ];
                }
            } else {
                if ($stock_type == 1) {
                    $condition = [
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'store_id' => $this->store_id,
                        'category_id_1' => $category_id,
                        'state' => 1
                    ];
                } elseif ($stock_type == 2) {
                    //门店所属店铺的商品
                    $condition = [
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'state' => 1,
                        'category_id_1' => $category_id,
                        'store_list' => ['<>', ''],
                        'goods_type' => ['NOT IN', [3, 4]]
                    ];
                }
            }
        } elseif ($type == 2) {
            //仓库中商品
            if ($search_text) {
                if ($stock_type == 1) {
                    $condition = [
                        'goods_name' => ['LIKE', '%' . $search_text . '%'],
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'store_id' => $this->store_id,
                        'state' => 0
                    ];
                } elseif ($stock_type == 2) {
                    //门店所属店铺的商品
                    $condition = [
                        'goods_name' => ['LIKE', '%' . $search_text . '%'],
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'state' => 1,
                        'goods_type' => ['NOT IN', [3, 4]]
                    ];
                }
            } else {
                if ($stock_type == 1) {
                    $condition = [
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'store_id' => $this->store_id,
                        'category_id_1' => $category_id,
                        'state' => 0
                    ];
                } elseif ($stock_type == 2) {
                    //门店所属店铺的商品
                    $condition = [
                        'website_id' => $this->website_id,
                        'shop_id' => $shop_id,
                        'state' => 1,
                        'category_id_1' => $category_id,
                        'goods_type' => ['NOT IN', [3, 4]]
                    ];
                }
            }
        }

        $storeGoods = new StoreGoods();
        $data = $storeGoods->getGoodsList($page_index, $page_size, $condition, '*', '', $stock_type, $store_id, $type);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 商品管理-商品上架
     */

    public function goodsOnline()
    {
        $goods_id = request()->post('goods_id');

        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $storeServer = new storeServer();
        $stock_type = (int)$storeServer->getStoreSet($this->instance_id)['stock_type'] ?: 1;

        if($stock_type == 1) {
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'store_id' => $this->store_id,
                'goods_id' => $goods_id
            ];
        }else{
            $condition = [
                'website_id' => $this->website_id,
                'goods_id' => $goods_id
            ];
        }

        $storeGoods = new StoreGoods();
        $data = $storeGoods->goodsOnline($condition,$this->store_id);
        if ($data == 0) {
            return json(['code' => -1,
                'message' => '库存不足，不能上架',
                'data' => ''
            ]);
        } elseif ($data == -1) {
            return json(['code' => -1,
                'message' => '平台未上架此商品，不能上架',
                'data' => ''
            ]);
        }
        return AjaxReturn($data);
    }

    /*
     * 商品管理-商品下架
     */

    public function goodsOffline()
    {
        $goods_id = request()->post('goods_id');

        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $storeServer = new storeServer();
        $stock_type = (int)$storeServer->getStoreSet($this->instance_id)['stock_type'] ?: 1;

        if ($stock_type == 1) {
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'store_id' => $this->store_id,
                'goods_id' => $goods_id
            ];
        } elseif ($stock_type == 2) {
            $condition = [
                'website_id' => $this->website_id,
                'goods_id' => $goods_id
            ];
        }

        $storeGoods = new StoreGoods();
        $data = $storeGoods->goodsOffline($condition, $this->store_id);
        return AjaxReturn($data);
    }

    /*
     * 商品管理-仓库中商品移除
     */

    public function goodsDel()
    {
        $goods_id = request()->post('goods_id');
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'store_id' => $this->store_id,
            'goods_id' => $goods_id
        ];
        $storeGoods = new StoreGoods();
        $data = $storeGoods->goodsDel($condition);
        return AjaxReturn($data);
    }

    /*
     * 商品管理-编辑商品
     */

    public function goodsEdit()
    {
        $goods_id = request()->post('goods_id');
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'store_id' => $this->store_id,
            'goods_id' => $goods_id
        ];
        $storeGoods = new StoreGoods();
        $data = $storeGoods->goodsEdit($condition);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data[0]
        ]);
    }

    /*
     * 商品管理-保存商品
     */

    public function saveGoods()
    {
        $data = request()->post('product/a');
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'store_id' => $this->store_id,
            'goods_id' => $data['goods_id']
        ];
        $storeGoods = new StoreGoods();
        $data1 = $storeGoods->goodsSave($data, $condition);
        if ($data1 == 1) {
            return json(['code' => 1,
                'message' => '保存成功',
                'data' => []
            ]);
        } else {
            return json(['code' => -1,
                'message' => '保存失败',
                'data' => []
            ]);
        }
    }

    /*
     * 商品管理-添加商品-获取一级分类
     */

    public function getAddGoodsCategoryList()
    {
        $store_model = new VslStoreModel();
        $shop_id = $store_model->Query(['store_id' => $this->store_id, 'website_id' => $this->website_id], 'shop_id')[0];

        $condition = array(
            'website_id' => $this->website_id,
            'shop_id' => $shop_id,
            'store_list' => ['NOT IN', [$this->store_id]],
            'state' => 1
        );

        $storeGoods = new StoreGoods();
        $data = $storeGoods->getAddGoodsCategoryList($condition);
        if (empty($data)) {
            $data = [];
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 商品管理-添加商品-商品列表
     */

    public function getAddGoodsList()
    {
        $category_id = request()->post("category_id", 0);
        $search_text = request()->post('search_text');
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);

        $store_model = new VslStoreModel();
        $shop_id = $store_model->Query(['store_id' => $this->store_id, 'website_id' => $this->website_id], 'shop_id')[0];

        if ($search_text) {
            $condition = [
                'goods_name' => ['LIKE', $search_text . '%'],
                'website_id' => $this->website_id,
                'shop_id' => $shop_id,
                'state' => 1,
                'goods_type' => ['NOT IN', [3, 4]]
            ];
        } else {
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $shop_id,
                'category_id_1' => $category_id,
                'state' => 1,
                'goods_type' => ['NOT IN', [3, 4]]
            ];
        }

        $storeGoods = new StoreGoods();
        $data = $storeGoods->getAddGoodsList($page_index, $page_size, $condition, $this->store_id);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 商品管理-添加商品-添加
     */

    public function addGoods()
    {
        $goods_id = request()->post('goods_id');
        $storeGoods = new StoreGoods();
        $data = $storeGoods->addGoods($goods_id);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 售后订单
     */

    public function afterOrderList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size') ?: PAGESIZE;
        $order_status = request()->post('order_status', 0); //0全部，1待处理，2已打款
        $search_text = request()->post('search_text');
        $condition['is_deleted'] = 0; // 未删除订单
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['store_id'] = $this->store_id;
        $condition['shipping_type'] = 2;
        $condition['order_status'] = [['<>', 0]];
        if (is_numeric($search_text)) {
            $condition['order_no'] = ['LIKE', '%' . $search_text . '%'];
        } elseif (!empty($search_text)) {
            $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }

        $storeGoods = new StoreGoods();
        $data = $storeGoods->afterOrderList($search_text, $page_index, $page_size, $condition, 'create_time DESC', $order_status);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 核销记录
     */

    public function verificationLog()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size') ?: PAGESIZE;
        $status = request()->post('status', 0); //0全部，1订单，2礼品券，3消费卡
        $search_text = request()->post('search_text');

        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['store_id'] = $this->store_id;

        $storeGoods = new StoreGoods();
        $data = $storeGoods->verificationLog($page_index, $page_size, $status, $search_text, $condition);

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
    }

    /*
     * 同意审核并打款
     */

    public function orderGoodsConfirmRefund()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $order_id = request()->post('order_id');
        $password = request()->post('password/a');
        $order_goods_id = request()->post('order_goods_id/a');
        $refundtype = 0;
        if (empty($order_goods_id)) {
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
            $refundtype = 1;
        }
        if (empty($order_id) || empty($order_goods_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        $order_service = new OrderService();
        if (!empty($password)) {
            $retval = $order_service->orderGoodsConfirmRefunds($order_id, $password, $refundtype);
        } else {
            $retval = $order_service->orderGoodsConfirmRefund($order_id, $order_goods_id, $refundtype);
        }

        return $retval;
    }

    /*
     * 拒绝
     */

    public function orderGoodsRefuseOnce()
    {
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $order_id = request()->post('order_id', '');
        $order_goods_id = request()->post('order_goods_id/a', '');
        if (empty($order_goods_id)) {
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_info = $order_goods_model::all(['order_id' => $order_id]);
            $order_goods_id = [];
            foreach ($order_goods_info as $v) {
                $order_goods_id[] = $v->order_goods_id;
            }
        }
        $reason = request()->post('reason', '');
        if (empty($order_id) || empty($order_goods_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $order_service = new OrderService();
        $retval = $order_service->orderGoodsRefuseOnce($order_id, $order_goods_id, $reason);

        return AjaxReturn($retval);
    }

    /**
     * 新增门店会员
     */

    public function register()
    {
        $member = new Member();
        $password = mt_rand(10000000, 99999999); //门店注册,随机生成密码
        $mobile = request()->post('mobile', '');
        $verification_code = request()->post('verification_code');
        $mall_port = request()->post('mall_port', 0);
        if (empty($mobile) || empty($verification_code)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        if ($mobile != Session::get('sendMobile')) {
            return json(['code' => -1, 'message' => '手机已更改请重新获取验证码']);
        }
        if ($verification_code != Session::get('mobileVerificationCode')) {
            return json(['code' => -1, 'message' => '手机验证码错误']);
        }

        $retval = $member->registerMember('', '', $password, '', $mobile, '', '', '', '', '', '', '', '', '', $mall_port);

        if ($retval > 0) {
            runhook('Notify', 'sendPassWord', ['password' => $password, 'mobile' => $mobile, 'shop_id' => $this->instance_id, 'website_id' => $this->website_id]);
            Session::delete(['mobileVerificationCode', 'sendMobile']);
            //注册营销
            if (getAddons('registermarketing', $this->website_id)) {
                $registerMarketingServer = new RegisterMarketing();
                $registerMarketingServer->deliveryAward($retval);
            }

            return json([
                'code' => 1,
                'message' => '注册成功',
                'data' => [
                    'user_token' => md5($retval),
                    'user_tel' => $mobile
                ]
            ]);
        }

        return json(AjaxReturn($retval));
    }

    /**
     * 添加购物车
     */

    public function addCart()
    {
        $sku_id = request()->post('sku_id', 0);
        $num = request()->post('num', 0);
        $bar_code = request()->post('bar_code', '');

        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $storeServer = new storeServer();
        $stock_type = (int)$storeServer->getStoreSet($this->instance_id)['stock_type'] ?: 1;

        if ($stock_type == 1) {
            $sku_model = new \data\model\VslStoreGoodsSkuModel();
            $storeGoodsModel = new \data\model\VslStoreGoodsModel();
        } elseif ($stock_type == 2) {
            $sku_model = new VslGoodsSkuModel();
            $storeGoodsModel = new VslGoodsModel();//单条查询
        }

        if ($bar_code) {
            if ($stock_type == 1) {
                $sku = $sku_model->getInfo(['bar_code' => $bar_code, 'store_id' => $this->store_id], 'sku_id');
            } elseif ($stock_type == 2) {
                $sku = $sku_model->getInfo(['code' => $bar_code], 'sku_id');
            }
            $sku_id = $sku ? $sku['sku_id'] : 0;
        }
        if (empty($sku_id)) {
            return json(['code' => -1, 'message' => '商品不存在']);
        }
        if (empty($num)) {
            return json(['code' => -1, 'message' => '请输入正确数量']);
        }
        $storeServer = new storeServer();

        if ($stock_type == 1) {
            $sku_info = $sku_model->getInfo(['sku_id' => $sku_id, 'store_id' => $this->store_id], '*');
        } elseif ($stock_type == 2) {
            $sku_info = $sku_model->getInfo(['sku_id' => $sku_id], '*');
        }

        if (!$sku_info) {
            return json(['code' => -1, 'message' => '商品不存在']);
        }
        if ($stock_type == 1) {
            $pic = $storeGoodsModel->getInfo(['goods_id' => $sku_info['goods_id'], 'store_id' => $this->store_id], 'picture')['picture'];
        } elseif ($stock_type == 2) {
            $pic = $storeGoodsModel->getInfo(['goods_id' => $sku_info['goods_id']], 'picture')['picture'];
        }

        $goods_id = $sku_info['goods_id'];
        $shop_id = $this->instance_id;
        $storeId = $sku_info['store_id'] ?: $this->store_id;

        $picture_id = $pic;
        $result = $storeServer->addcartForStore($shop_id, $goods_id, $sku_id, $num, $picture_id, $storeId, $stock_type);
        if ($result > 0) {
            return json(['code' => 0, 'message' => '添加成功', 'data' => ['cart_id' => $result]]);
        } else {
            return json(AjaxReturn(ADD_FAIL));
        }
    }

    /**
     * 购物车页面
     */

    public function cart()
    {
        // 拉黑不能登录
        if ($this->uid) {
            $user_status = $this->user->getUserStatus($this->uid);
            if ($user_status == USER_LOCK) {
                return json(AjaxReturn(USER_LOCK));
            }
        }
        $is_deduction = request()->post('is_deduction', 0);
        $discount = floatval(request()->post('discount', 0)); //打多少折
        $manual_amout = floatval(request()->post('manual_amout', 0)); //优惠多少金额
        $is_membercard_deduction = request()->post('is_membercard_deduction', 0);//会员卡抵扣

        if ($discount && ($discount >= 10 || $discount <= 0)) {
            return json(['code' => -1, 'message' => '折扣有误', 'data' => []]);
        }


        $storeServer = new storeServer();
        $returnData = $storeServer->getCart($this->store_id, $is_deduction, $discount, $manual_amout, $this->instance_id, $is_membercard_deduction);
        $list['data'] = $returnData;
        if (!empty($returnData['msg'])) {
            $list['message'] = $returnData['msg'];
            $list['code'] = 3;
        } else {
            $list['message'] = "获取成功";
            $list['code'] = 0;
        }
        return json($list);
    }

    /*
     * 门店会员登录
     */

    public function memberLogin()
    {
        $mobile = request()->post('account', '');
        $verification_code = request()->post('verification_code'); // 短信验证码
        $mall_port = request()->post('mall_port'); // 短信验证码
        if (empty($mobile) || empty($verification_code)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $sendMobile = Session::get('sendMobile');
        # 判断短信 + 验证码登录 + 手机判断
        if ($verification_code && !empty($sendMobile) && $mobile != $sendMobile) {
            return json(['code' => -1, 'message' => '手机已更改请重新获取验证码']);
        }
            # 判断短信 + 验证码登录 + 验证码
            if ($verification_code && $verification_code != Session::get('mobileVerificationCode')) {
                return json(['code' => -1, 'message' => '手机验证码错误']);
        }
        $condition = ['user_tel' => $mobile, 'is_member' => 1, 'website_id' => $this->website_id];
        if ($mall_port) {
            $condition['mall_port'] = $mall_port;
        }
        $userList = Db::table('sys_user')->where($condition)->field('mall_port,user_tel')->select();

        if (count($userList) > 1) {
            foreach ($userList as $key => $val) {
                switch ($val['mall_port']) {
                    case 1:
                        $userList[$key]['port'] = '公众号端';
                        break;
                    case 2:
                        $userList[$key]['port'] = '小程序端';
                        break;
                    case 3:
                        $userList[$key]['port'] = '移动H5端';
                        break;
                    case 4:
                        $userList[$key]['port'] = 'PC端';
                        break;
                    case 5:
                        $userList[$key]['port'] = 'APP端';
                        break;
                    default :
                        $userList[$key]['port'] = '';
                        break;
                }
            }
            return json([
                'code' => 2,
                'message' => '有多端口账号,请先选择端口',
                'data' => $userList
            ]);
        }
        $base = new BaseService();
        $model = $base->getRequestModel();
        $oldInstanceId = Session::get($model . 'instance_id');
        $result = $this->user->loginNew($condition);
        Session::set($model . 'instance_id', $oldInstanceId);


        if (is_array($result)) {
            $member = new Member\MemberAccount();
            $member_account = $member->getMemberAccount($result['uid']); // 用户余额
            $balance = $member_account['balance'];
            Session::delete(['send_mobile_verification_times', 'sendMobile', 'mobileVerificationCode']);
            return json([
                'code' => 1,
                'message' => '登录成功',
                'data' => [
                    'user_token' => md5($result['uid']),
                    'user_name' => $result['user_name'],
                    'user_headimg' => getApiSrc($result['user_headimg']),
                    'balance' => $balance,
                    'user_tel' => $result['user_tel']
                ]
            ]);
        } else {
            return json(AjaxReturn($result));
        }
    }

    /*
     * 门店会员通过会员卡登录
     */

    public function memberLoginByMembercard()
    {
        $membercard_no = request()->post('membercard_no', ''); //会员卡号

        if (empty($membercard_no)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        if(getAddons('membercard',$this->website_id)) {
            $membercard_user_mdl = new VslMembercardUserModel();
            $uid = $membercard_user_mdl->Query(['membercard_no' => $membercard_no,'website_id' => $this->website_id],'uid')[0];
            $condition = [
                'uid' => $uid,
                'is_member' => 1,
                'website_id' => $this->website_id
            ];
        }

        $base = new BaseService();
        $model = $base->getRequestModel();
        $oldInstanceId = Session::get($model . 'instance_id');
        $result = $this->user->loginNew($condition);
        Session::set($model . 'instance_id', $oldInstanceId);

        if (is_array($result)) {
            $member = new Member\MemberAccount();
            $member_account = $member->getMemberAccount($result['uid']); // 用户余额
            $balance = $member_account['balance'];
            return json([
                'code' => 1,
                'message' => '登录成功',
                'data' => [
                    'user_token' => md5($result['uid']),
                    'user_name' => $result['user_name'],
                    'user_headimg' => getApiSrc($result['user_headimg']),
                    'balance' => $balance,
                    'user_tel' => $result['user_tel']
                ]
            ]);
        } else {
            return json(AjaxReturn($result));
        }
    }

    /*
     * 退出登录
     */

    public function memberLogout()
    {
        $this->user->Logout();
        return json(AjaxReturn(1));
    }

    /*
     * 清空购物车
     */

    public function deleteCart()
    {
        Session::set('store_cart_array' . $this->store_id, null);
        return json(AjaxReturn(1));
    }

    /**
     * 删除购物车商品
     * */

    public function deleteOneCart()
    {

        $cart_id = request()->post('cart_id');
        $cart_id_array = explode(',', $cart_id);
        $storeServer = new storeServer();
        $result = $storeServer->deleteStoreCookieCart($cart_id_array, $this->store_id);
        if ($result) {
            $data['message'] = "操作成功";
            $data['data'] = "";
            $data['code'] = 0;
            return json($data);
        } else {
            $data['message'] = "系统繁忙";
            $data['data'] = "";
            $data['code'] = '-1';
            return json($data);
        }
    }

    /**
     * 购物车修改数量
     */

    public function cartAdjustNum()
    {
        $cart_id = request()->post('cartid', '');
        $num = request()->post('num', '');
        if (empty($cart_id)) {
            $data['code'] = -1;
            $data['data'] = '';
            $data['message'] = "请选择购物车ID";
        }
        $storeServer = new storeServer();
        $retval = $storeServer->updateStoreCookieCartNum($cart_id, $num, $this->store_id);

        if ($retval) {
            $data['code'] = 0;
            $data['data'] = '';
            $data['message'] = "修改成功";
        } else {
            $data['code'] = 0;
            $data['data'] = '';
            $data['message'] = "修改失败";
        }

        return json($data);
    }

    /*
     * 获取支付方式
     */

    public function payType()
    {
        $config_model = new WebConfig();
        $base_config = [];
        $config_array = ['BPAY', 'ALIPAY', 'WPAY'];
        $config_list = $config_model->getConfigBatch(0, $config_array, $this->website_id);
        foreach ($config_list as $k => $v) {
            switch ($v['key']) {
                case 'BPAY':
                    if ($v['is_use'] == 0) {
                        $base_config['bpay'] = false;
                        break;
                    }
                    $base_config['bpay'] = true;
                    break;
                case 'ALIPAY':
                    if ($v['is_use'] == 0) {
                        $base_config['ali_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['ali_partnerid']) || empty($info['ali_seller']) || empty($info['ali_key'])) {
                        $base_config['ali_pay'] = false;
                        break;
                    }
                    $base_config['ali_pay'] = true;
                    break;
                case 'WPAY':
                    if ($v['is_use'] == 0) {
                        $base_config['wechat_pay'] = false;
                        break;
                    }
                    $info = json_decode($v['value'], true);
                    if (empty($info['appid']) || empty($info['mch_id']) || empty($info['mch_key'])) {
                        $base_config['wechat_pay'] = false;
                        break;
                    }
                    $base_config['wechat_pay'] = true;
                    break;
            }
        }
        $base_config['cash_pay'] = true;
        return json(['code' => 1, 'message' => '成功获取', 'data' => $base_config]);
    }

    /*
     * 门店订单支付
     * * */

    public function storePay()
    {
        if (!$this->uid) {
            return json(['code' => -2, 'message' => '未登录']);
        }
        $order_data = request()->post('order_data/a');
        if (!$order_data) {
            return json(AjaxReturn(-1006));
        }

        //生成外部支付号
        $order_service = new OrderService();
        $order_business = new OrderBusiness();
        $out_trade_no = 'MD' . $order_service->getOrderTradeNo();
        $order_no = $order_business->createOrderNo($this->instance_id);
        $order_data['out_trade_no'] = $out_trade_no;
        $order_data['order_no'] = $order_no;
        $uid = $this->uid;
        $order_data['uid'] = $uid;
        $order_data['website_id'] = $this->website_id;
        $order_data['shop_id'] = $this->instance_id;
        $order_data['store_id'] = $this->store_id;
        $order_data['assistant_id'] = $this->assistantId;
        $order_data['type'] = 7;
        //门店订单省市由门店地址获取,用户订单数据分布分析
        $storeModel = new VslStoreModel();
        $storeInfo = $storeModel->getInfo(['store_id' => $this->store_id], 'province_id,city_id');
        $order_data['receiver_province'] = $storeInfo['province_id'];
        $order_data['receiver_city'] = $storeInfo['city_id'];
        $ip = get_ip();
        $order_data['ip'] = $ip;
        $is_order_key = md5(json_encode($order_data));
        $storeServer = new storeServer();
        //判断是否是网络延迟造成多次请求
        if (Cookie::get($is_order_key)) {//有session说明创建成功了
            $cookie_data = unserialize(Cookie::get($is_order_key));
            $time = $cookie_data['create_time'] + 5;
            if (time() <= $time) {
                $message['code'] = 0;
                $message['message'] = "订单提交成功";
                $message['data']['out_trade_no'] = $cookie_data['out_trade_no'];
                Cookie::delete($is_order_key);
                return json($message);
            }
        }
        //数据判断并组合数据
        $return_status = $storeServer->validateData($order_data);
        if ($return_status['code'] < 0) {
            return json($return_status);
        } else {
            $order_data = $return_status['data'];
        }
        //用什么方式去支付
        $order_data['shipping_fee'] = 0;
        //0-在线支付 1-微信支付 2-支付宝 3-银联卡 4-货到付款 5-余额支付 10-现金支付  18-抵扣支付
        if($order_data['total_amount'] == 0) {
            $order_data['pay_type'] = 18;
        }
        $pay_type = $order_data['pay_type'];
        //将支付信息存入redis
        $key = 'store_pay_' . $out_trade_no;
        switch ($pay_type) {
            case '1': //微信支付
                $redis = connectRedis();
                $pay_data = $order_data;
                $pay_str = json_encode($pay_data);
                $redis->set($key, $pay_str);
                if (empty($out_trade_no)) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "没有获取到订单信息";
                    return json($data);
                }
                if ($pay_data['total_amount'] <= 0) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "订单金额为0,请选择其他支付方式";
                    return json($data);
                }
                $red_url = $this->realm_ip . "/wapapi/pay/wchatUrlBack";
                $pay = new UnifyPay();
                $res = $pay->wchatPays($out_trade_no, 'NATIVE', $red_url);
                if ($res["return_code"] == "SUCCESS") {
                    if (empty($res['code_url'])) {
                        $data['data'] = $res;
                        $data['code'] = -1;
                        $data['message'] = '支付失败,'.$res['err_code_des'];
                        return json($data);
                    } else {
                        //Session::delete('store_cart_array' . $this->store_id);
                        $code_url = $res['code_url'];
                        $path = getQRcode($code_url, "upload/" . $this->website_id . "/md_qrcode/pay", $out_trade_no . 'wx');
                        $real_path = __ROOT__ . $path;
                        $data['data']['img'] = $real_path;
                        $data['data']['out_trade_no'] = $out_trade_no;
                        $data['code'] = 0;
                        return json($data);
                    }
                } else {
                    $data['data'] = $res;
                    $data['code'] = -1;
                    $data['message'] = '支付失败,'.$res['err_code_des'];
                    return json($data);
                }
                break;
            case '2'://支付宝
                $redis = connectRedis();
                $pay_data = $order_data;
                $pay_str = json_encode($pay_data);
                $redis->set($key, $pay_str);
                $pay = new UnifyPay();
                $notify_url = $this->realm_ip . "/wapapi/pay/aliUrlBack";
                if (empty($out_trade_no)) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "没有获取到订单信息";
                    return json($data);
                }
                if ($pay_data['total_amount'] <= 0) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "订单金额为0,请选择其他支付方式";
                    return json($data);
                }
                $qrPayResult = $pay->aliPayNews($out_trade_no, $notify_url);
                if ($qrPayResult == -1 || $qrPayResult == -2 || $qrPayResult == -3) {
                    $data['data'] = $res;
                    $data['code'] = -1;
                    $data['message'] = '支付失败';
                    return json($data);
                } else {
                    //Session::delete('store_cart_array' . $this->store_id);
                    $path = getQRcode($qrPayResult, "upload/" . $this->website_id . "/md_qrcode/pay", $out_trade_no . 'al');
                    $real_path = __ROOT__ . $path;
                    $data['data']['img'] = $real_path;
                    $data['data']['out_trade_no'] = $out_trade_no;
                    $data['code'] = 0;
                    return json($data);
                }
                break;
            case '5'://余额支付
                $pay = new UnifyPay();
                $member = new Member\MemberAccount();
                $member_account = $member->getMemberAccount($this->uid); // 用户余额
                $balance = $member_account['balance'];
                $pay_money = $order_data['total_amount'];

                if ($balance < $pay_money) {
                    $data['code'] = -1;
                    $data['message'] = "余额不足。";
                    return json($data);
                } else {
                    //创建订单
                    $order_id = $storeServer->createStoreOrder($order_data);
                    if ($order_id > 0) {
                        $res = $order_service->orderOnLinePay($out_trade_no, 5, $order_id);
                        if ($res == 1) {
                            Session::delete('store_cart_array' . $this->store_id);
                            $data['code'] = 0;
                            $data['message'] = "订单创建成功";
                            $data['data']['out_trade_no'] = $out_trade_no;
                            return json($data);
                        }
                    }
                }
                break;
            case '10':
                $order_id = $storeServer->createStoreOrder($order_data);
                if ($order_id > 0) {
                    $res = $order_service->orderOnLinePay($out_trade_no, 10, $order_id);
                    if ($res == 1) {
                        Session::delete('store_cart_array' . $this->store_id);
                        $data['code'] = 0;
                        $data['message'] = "订单创建成功";
                        $data['data']['out_trade_no'] = $out_trade_no;
                        return json($data);
                    }
                }
                break;
            case '18':
                $order_id = $storeServer->createStoreOrder($order_data);
                if ($order_id > 0) {
                    $res = $order_service->orderOnLinePay($out_trade_no, 18, $order_id);
                    if ($res == 1) {
                        Session::delete('store_cart_array' . $this->store_id);
                        $data['code'] = 0;
                        $data['message'] = "订单创建成功";
                        $data['data']['out_trade_no'] = $out_trade_no;
                        return json($data);
                    }
                }
                break;
            default :
                $data['code'] = -1;
                $data['message'] = "支付方式错误,订单创建失败";
                return json($data);
        }
    }

    /*
     * 门店快捷收银
     * * */

    public function quickPay()
    {
        if (!$this->uid) {
            return json(['code' => -2, 'message' => '未登录']);
        }
        $order_data = request()->post('order_data/a');
        if (!$order_data) {
            return json(AjaxReturn(-1006));
        }
        $storeServer = new storeServer();
        //判断是否是网络延迟造成多次请求
        //生成外部支付号
        $order_service = new OrderService();
        $order_business = new OrderBusiness();
        $out_trade_no = 'MD' . $order_service->getOrderTradeNo();
        $order_no = $order_business->createOrderNo($this->instance_id);
        $order_data['out_trade_no'] = $out_trade_no;
        $order_data['order_no'] = $order_no;
        $uid = $this->uid;
        $order_data['uid'] = $uid;
        $order_data['website_id'] = $this->website_id;
        $order_data['shop_id'] = $this->instance_id;
        $order_data['store_id'] = $this->store_id;
        $order_data['assistant_id'] = $this->assistantId;
        $order_data['total_deduction_money'] = 0;
        $order_data['total_deduction_point'] = 0;
        $goods = new \data\service\Goods();
        $storeGoods = $goods->getGoodsDetailByCondition(['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'for_store' => 1]);
        if (!$storeGoods) {
//            $addGoods = $goods->addOrEditGoods(0, '门店快捷收银', $this->instance_id, 0, 0, 0, 0, 0, 0, '', 1, '1.00', '1.00', '1.00', 0, 0, 0, 0, 0, 0, 999999999, 0, 0, 0, 0, 0, 0, 5, 0, 0, 0, 0, 161103, '', '', '', '', '', 0, 0, 0, 0, 0, 161103, '', 1, '', 0, '', '', 0, 0, 0, 0, 0, 0, '', 2, 1, 2, 2, 1, '', 2, 0, 0, 2, [], [], 0, '', '', 0, 0, 2, 2, 1, '', [], 1);
            // by sgw
            $goods_data = [
                'goods_id'                      => 0,
                'goods_name'                    => '门店快捷收银',
                'shopid'                        => $this->instance_id,
                'goods_type'                    => 1,
                'market_price'                  => 1,
                'price'                         => 1,
                'cost_price'                    => 1,
                'stock'                         => 999999999,
                'picture'                       => 161103,
                'img_id_array'                  => 161103,
                'is_bonus_global'               => 2,
                'is_bonus_area'                 => 2,
                'is_bonus_team'                 => 1,
                'bonus_rule'                    => 2,
                'verificationinfo'              => [],
                'card_info'                     => [],
                'video_id'                      => 0,
                'buyagain'                      => 2,
                'buyagain_level_rule'           => 2,
                'buyagain_recommend_type'       => 1,
                'buyagain_distribution_val'     => '',
                'payment_content'               => [],
                'for_store'                     => 1
            ];
            $addGoods = $goods->addOrEditGoods($goods_data);

            if (!$addGoods) {
                return json(['code' => -2, 'message' => '无法使用快捷收银']);
            }
            $storeGoods = $goods->getGoodsDetailByCondition(['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'for_store' => 1]);
        }
        if (!$storeGoods) {
            return json(['code' => -2, 'message' => '操作异常,请稍后重试']);
        }
        $skuId = $goods->getSkuIdByGoodsId($storeGoods['goods_id']);
        $order_data['goods_list'][0] = [
            'shop_id' => $this->instance_id,
            'goods_id' => $storeGoods['goods_id'],
            'sku_id' => $skuId,
            'num' => 1,
            'goods_picture' => $storeGoods['picture'],
            'store_id' => $this->store_id,
            'cart_id' => 0,
            'price' => $order_data['total_amount'],
            'discount_price' => $order_data['total_amount'],
            'stock' => 0,
            'goods_name' => $storeGoods['goods_name']
        ];
        $order_data['goods_amount'] = $order_data['total_amount'];
        $ip = get_ip();
        $order_data['ip'] = $ip;
        //用什么方式去支付
        $order_data['shipping_fee'] = 0;
        //0-在线支付 1-微信支付 2-支付宝 3-银联卡 4-货到付款 5-余额支付 10-现金支付
        $pay_type = $order_data['pay_type'];
        $is_order_key = md5(json_encode($order_data));
        //判断是否是网络延迟造成多次请求
        if (Cookie::get($is_order_key)) {//有session说明创建成功了
            $cookie_data = unserialize(Cookie::get($is_order_key));
            $time = $cookie_data['create_time'] + 5;
            if (time() <= $time) {
                $message['code'] = 0;
                $message['message'] = "订单提交成功";
                $message['data']['out_trade_no'] = $cookie_data['out_trade_no'];
                Cookie::delete($is_order_key);
                return json($message);
            }
        }
        //将支付信息存入redis
        $key = 'store_pay_' . $out_trade_no;
        switch ($pay_type) {
            case '1': //微信支付
                $redis = connectRedis();
                $pay_data = $order_data;
                $pay_str = json_encode($pay_data);
                $redis->set($key, $pay_str);
                if (empty($out_trade_no)) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "没有获取到订单信息";
                    return json($data);
                }
                if ($pay_data['total_amount'] <= 0) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "订单金额为0,请选择其他支付方式";
                    return json($data);
                }
                $red_url = $this->realm_ip . "/wapapi/pay/wchatUrlBack";
                $pay = new UnifyPay();
                $res = $pay->wchatPays($out_trade_no, 'NATIVE', $red_url);
                if ($res["return_code"] == "SUCCESS") {
                    if (empty($res['code_url'])) {
                        $data['data'] = $res;
                        $data['code'] = -1;
                        $data['message'] = '支付失败,'.$res['err_code_des'];
                        return json($data);
                    } else {
                        //Session::delete('store_cart_array' . $this->store_id);
                        $code_url = $res['code_url'];
                        $path = getQRcode($code_url, "upload/" . $this->website_id . "/md_qrcode/pay", $out_trade_no . 'wx');
                        $real_path = __ROOT__ . $path;
                        $data['data']['img'] = $real_path;
                        $data['data']['out_trade_no'] = $out_trade_no;
                        $data['code'] = 0;
                        return json($data);
                    }
                } else {
                    $data['data'] = $res;
                    $data['code'] = -1;
                    $data['message'] = '支付失败,'.$res['err_code_des'];
                    return json($data);
                }
                break;
            case '2'://支付宝
                $redis = connectRedis();
                $pay_data = $order_data;
                $pay_str = json_encode($pay_data);
                $redis->set($key, $pay_str);
                $pay = new UnifyPay();
                $notify_url = $this->realm_ip . "/wapapi/pay/aliUrlBack";
                if (empty($out_trade_no)) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "没有获取到订单信息";
                    return json($data);
                }
                if ($pay_data['total_amount'] <= 0) {
                    $data['code'] = -1;
                    $data['data'] = '';
                    $data['message'] = "订单金额为0,请选择其他支付方式";
                    return json($data);
                }
                $qrPayResult = $pay->aliPayNews($out_trade_no, $notify_url);
                if ($qrPayResult == -1 || $qrPayResult == -2 || $qrPayResult == -3) {
                    $data['data'] = $res;
                    $data['code'] = -1;
                    $data['message'] = '支付失败';
                    return json($data);
                } else {
                    //Session::delete('store_cart_array' . $this->store_id);
                    $path = getQRcode($qrPayResult, "upload/" . $this->website_id . "/md_qrcode/pay", $out_trade_no . 'al');
                    $real_path = __ROOT__ . $path;
                    $data['data']['img'] = $real_path;
                    $data['data']['out_trade_no'] = $out_trade_no;
                    $data['code'] = 0;
                    return json($data);
                }
                break;
            case '5'://余额支付
                $pay = new UnifyPay();
                $member = new Member\MemberAccount();
                $member_account = $member->getMemberAccount($this->uid); // 用户余额
                $balance = $member_account['balance'];
                $pay_money = $order_data['total_amount'];

                if ($balance < $pay_money) {
                    $data['code'] = -1;
                    $data['message'] = "余额不足。";
                    return json($data);
                } else {
                    //创建订单
                    $order_id = $storeServer->createStoreOrder($order_data);
                    if ($order_id > 0) {
                        $res = $order_service->orderOnLinePay($out_trade_no, 5, $order_id);
                        if ($res == 1) {
                            Session::delete('store_cart_array' . $this->store_id);
                            $data['code'] = 0;
                            $data['message'] = "订单创建成功";
                            $data['data']['out_trade_no'] = $out_trade_no;
                            return json($data);
                        }
                    }
                }
                break;
            case '10':
                $order_id = $storeServer->createStoreOrder($order_data);
                if ($order_id > 0) {
                    $res = $order_service->orderOnLinePay($out_trade_no, 10, $order_id);
                    if ($res == 1) {
                        Session::delete('store_cart_array' . $this->store_id);
                        $data['code'] = 0;
                        $data['message'] = "订单创建成功";
                        $data['data']['out_trade_no'] = $out_trade_no;
                        return json($data);
                    }
                }
                break;
            default :
                $data['code'] = -1;
                $data['message'] = "支付方式错误,订单创建失败";
                return json($data);
        }
    }

    /*
     * 店员端获取某个分类下的所有商品
     */

    public function getGoods()
    {
        $category_id = request()->post("category_id", 0);
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $keyword = request()->post('search_text', '');

        //获取后台配置的库存方式 1:门店独立库存 2:店铺统一库存  默认为1
        $storeServer = new storeServer();
        $stock_type = (int)$storeServer->getStoreSet($this->instance_id)['stock_type'] ?: 1;

        if ($stock_type == 1) {
            //门店独立库存
            if ($keyword) {
                $condition = array(
                    'website_id' => $this->website_id,
                    'store_id' => $this->store_id,
                    'goods_name' => ['like', '%' . $keyword . '%'],
                    'state' => 1,
                );
            } else {
                $condition = array(
                    'website_id' => $this->website_id,
                    'store_id' => $this->store_id,
                    'category_id_1' => $category_id,
                    'state' => 1,
                );
            }
        } elseif ($stock_type == 2) {
            //店铺统一库存
            $store_model = new VslStoreModel();
            $shop_id = $store_model->Query(['store_id' => $this->store_id, 'website_id' => $this->website_id], 'shop_id')[0];
            if ($keyword) {
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'goods_name' => ['like', '%' . $keyword . '%'],
                    'state' => 1,
                    'store_list' => ['<>', ''],
                    'goods_type' => ['NOT IN', [3, 4]]
                );
            } else {
                $condition = array(
                    'website_id' => $this->website_id,
                    'shop_id' => $shop_id,
                    'category_id_1' => $category_id,
                    'state' => 1,
                    'store_list' => ['<>', ''],
                    'goods_type' => ['NOT IN', [3, 4]]
                );
            }
        }

        $storeServer = new storeServer();
        $goods_info = $storeServer->getStoreGoods($page_index, $page_size, $condition, 1, $this->store_id, $this->instance_id);
        if (empty($goods_info['goods_list'])) {
            return json([
                'code' => 0,
                'message' => '获取成功',
                'data' => $goods_info
            ]);
        }
        //开始处理活动信息
        foreach ($goods_info['goods_list'] as $key => $value) {
            $msg = '';
            $member_discount = $value['goods_detail']['member_discount'];
            $member_is_label = $value['goods_detail']['member_is_label']; // 是否取整
            // 获取限时折扣
            $promotion_info['discount_num'] = 10;
            $goods_detail['goods_id'] = $value['goods_id'];
            $goods_detail['state'] = $value['state'];
            $goods_detail['shop_id'] = $value['shop_id'];
            $goods_detail['goods_name'] = $value['goods_name'];
            $goods_detail['sales'] = $value['sales'];
            $goods_detail['stock'] = $value['stock'];
            $goods_detail['goods_img'] = __IMG($value['goods_img']);
            $goods_detail['min_buy'] = $value['goods_detail']['min_buy'];
            $goods_detail['max_buy'] = $value['goods_detail']['max_buy'];
            $goods_detail['collects'] = $value['goods_detail']['collects'];
            $goods_detail['goods_type'] = $value['goods_detail']['goods_type'];
            $goods_detail['single_limit_buy'] = $value['goods_detail']['single_limit_buy'];
            if ($value['goods_detail']['shipping_fee_type'] == 0) {
                $goods_detail['shipping_fee'] = '包邮';
            } elseif ($value['goods_detail']['shipping_fee_type'] == 1) {
                $goods_detail['shipping_fee'] = $value['goods_detail']['shipping_fee'];
            } elseif ($value['goods_detail']['shipping_fee_type'] == 2) {
                $user_location = get_city_by_ip();
                if ($user_location['status'] == 1) {
                    // 定位成功，查询当前城市的运费
                    $goods_express = new GoodsExpress();
                    $address = new Address();
                    $city = $address->getCityId($user_location["city"]);
                    $district = $address->getCityFirstDistrict($city['city_id']);
                    $express = $goods_express->getGoodsExpressTemplate([['goods_id' => $value['goods_id'], 'count' => 1]], $district)['totalFee'];
                    $goods_detail['shipping_fee'] = $express;
                }
            }
            if ($msg) {
                $code = 1;
                $msg = $msg;
            } else {
                $code = 0;
                $msg = '成功获取';
            }
            if ($promotion_info['discount_type'] == 2) {
                $discount = 1;
                $member_discount = 1;
            } else {
                $discount = $promotion_info['discount_num'] / 10;
            }
            if ($promotion_info['integer_type'] == 1) {
                $member_is_label = $promotion_info['integer_type'];
            }
            $spec_obj = [];
            $goods_detail['sku']['tree'] = [];
            if($value['spec_list']) {
                foreach ($value['spec_list'] as $i => $spec_info) {
                    $temp_spec = [];
                    foreach ($spec_info['value'] as $s => $spec_value) {
                        $temp_spec['k'] = $spec_info['spec_name'];
                        $temp_spec['k_id'] = $spec_info['spec_id'];
                        $temp_spec['v'][$s]['id'] = $spec_value['spec_value_id'];
                        $temp_spec['v'][$s]['name'] = $spec_value['spec_value_name'];
                        $temp_spec['k_s'] = 's' . $i;
                        $spec_obj[$spec_info['spec_id']] = $temp_spec['k_s'];
                        $goods_detail['sku']['tree'][$spec_info['spec_id']] = $temp_spec;
                    }
                }
            }
            //接口需要tree是数组，不是对象，去除tree以spec_id为key的值
            $goods_detail['sku']['tree'] = array_values($goods_detail['sku']['tree']);
            $goods_info['goods_list'][$key]['goods_detail']['sku']['tree'] = $goods_detail['sku']['tree'];
            //sku信息
            $temp_sku = [];
            $goods_detail['sku']['list'] = [];
            foreach ($value['sku_list'] as $k => $sku) {
                $temp_sku['id'] = $sku['sku_id'];
                $temp_sku['sku_name'] = $sku['sku_name'];
                $temp_sku['price'] = $sku['price'];
                $temp_sku['min_buy'] = 1;
                $temp_sku['group_price'] = '';
                $temp_sku['group_limit_buy'] = '';
                $temp_sku['market_price'] = $sku['market_price'];
                $temp_sku['stock_num'] = $sku['stock'];
                $temp_sku['attr_value_items'] = $sku['attr_value_items'];
                $sku_temp_spec_array = explode(';', $sku['attr_value_items']);
                $temp_sku['s'] = [];
                foreach ($sku_temp_spec_array as $spec_id => $spec_combination) {
                    $explode_spec = explode(':', $spec_combination);
                    $spec_id = $explode_spec[0];
                    $spec_value_id = $explode_spec[1];
                    // ios wants string
                    if ($spec_value_id) {
                        $temp_sku['s'][] = (string)$spec_value_id;
                        $temp_sku[$spec_obj[$spec_id] ?: 's0'] = (int)$spec_value_id;
                    }
                }
                $goods_detail['min_price'] = reset($value['sku_list'])['sku_id'] == $sku['sku_id'] ? $sku['price'] : ($goods_detail['min_price'] <= $sku['price'] ? $goods_detail['min_price'] : $sku['price']);
                if ($promotion_info['discount_type'] == 2) {
                    $goods_detail['min_price'] = $promotion_info['discount_num'];
                }
                $goods_detail['min_market_price'] = reset($value['sku_list'])['sku_id'] == $sku['sku_id'] ? $sku['market_price'] : ($goods_detail['min_market_price'] <= $sku['market_price'] ? $goods_detail['min_market_price'] : $sku['market_price']);
                $goods_detail['max_price'] = reset($value['sku_list'])['sku_id'] == $sku['sku_id'] ? $sku['price'] : ($goods_detail['max_price'] >= $sku['price'] ? $goods_detail['max_price'] : $sku['price']);
                $goods_detail['max_market_price'] = reset($value['sku_list'])['sku_id'] == $sku['sku_id'] ? $sku['market_price'] : ($goods_detail['max_market_price'] >= $sku['market_price'] ? $goods_detail['max_market_price'] : $sku['market_price']);
                $goods_detail['sku']['list'][] = $temp_sku;
            }
            $goods_info['goods_list'][$key]['goods_detail']['sku']['list'] = $goods_detail['sku']['list'];
            $goods_info['goods_list'][$key] = [
                'goods_detail' => $goods_detail
            ];
        }
        return json([
            'code' => $code, //原来是1，改为0 bylgq
            'message' => $msg,
            'data' => $goods_info
        ]);
    }

    /*
     * 打印小票
     */

    public function printTicket()
    {
        $order_id = request()->post("order_id", 0);
        if (!$order_id) {
            return json(AjaxReturn(-1006));
        }
        $assistant_id = $this->assistantId;
        $order_service = new OrderService();
        $order_info = $order_service->getOrderDetail($order_id);
        if (!$order_info) {
            return json([
                'code' => -1,
                'message' => '订单不存在'
            ]);
        }
        $storePrintModel = new VslStorePrinterModel();
        $defaultprint = $storePrintModel->getInfo(['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id, 'isdefault' => 1], 'print_sn');
        if (!$defaultprint) {
            return json([
                'code' => -1,
                'message' => '请先设置打印机'
            ]);
        }
        $SN = $defaultprint['print_sn'];
        $storeServer = new storeServer();
        $storeInfo = $storeServer->storeDetail($this->store_id);
        $config = new WebConfig();
        $printConfig = $config->getConfig($this->instance_id, 'PRINTER_INFO');
        if (!$printConfig) {
            return json([
                'code' => -1,
                'message' => '请先填写小票机配置'
            ]);
        }
        $printerInfo = $printConfig['value'];
        $assistantInfo = $storeServer->assistantDetail($assistant_id, $this->store_id);
        $orderInfo = '<B>' . $storeInfo['shop_name'] . '(' . $storeInfo['store_name'] . ')</B><BR><BR>';
        $orderInfo .= '销售时间:' . date('Y-m-d H:i:s') . '<BR>';
        $orderInfo .= '订单号:' . $order_info['order_no'] . '<BR>收银员:' . $assistantInfo['assistant_name'] . '<BR>';
        $orderInfo .= '--------------------------------<BR>';
        $orderInfo .= '商品名称    价格   数量   小计<BR><BR>';
        foreach ($order_info['order_goods'] as $row) {
            $orderInfo .= $row['goods_name'] . '<BR>';
            if (strlen($row['price']) < 8) {
                $len1 = 8 - strlen($row['price']);
                for ($q = 0; $q < $len1; $q++) {
                    $row['price'] .= ' ';
                }
            }
            $orderInfo .= '<RIGHT>' . $row['price'];
            if (strlen($row['num']) < 6) {
                $len2 = 6 - strlen($row['num']);
                for ($q = 0; $q < $len2; $q++) {
                    $row['num'] .= ' ';
                }
            }
            $orderInfo .= $row['num'];
            $orderInfo .= number_format($row['price'] * $row['num'], 2) . '</RIGHT><BR>';
        }
        $orderInfo .= '--------------------------------<BR><BR>';
        $orderInfo .= '备注:' . $order_info['buyer_message'] . '<BR>';
        $orderInfo .= '--------------------------------<BR><BR>';
        $orderInfo .= '获得积分:' . $order_info['give_point'] . '<BR>';
        $orderInfo .= '订单金额:' . $order_info['goods_money'] . '<BR>';
        $orderInfo .= '优惠金额:' . $order_info['order_promotion_money'] . '<BR>';
        $orderInfo .= '实付金额:' . $order_info['pay_money'] . '<BR><BR>';
        if ($printerInfo['slogan']) {
            $slogan = explode('\n', $printerInfo['slogan']);
            $count = count($slogan);
            foreach ($slogan as $key => $val) {
                $orderInfo .= '<C>' . $val . '</C>';
                if ($key < ($count - 1)) {
                    $orderInfo .= '<BR>';
                }
            }
            unset($val);
            $orderInfo .= '<CUT>';
        } else {
            $orderInfo .= '<C>谢谢惠顾，欢迎下次光临</C><CUT>';
        }


        $client = new Feieyun($this->instance_id);
        $res = $client->printing($SN, $orderInfo);
        if (!$res) {
            return json(['code' => -1,
                'message' => '打印失败'
            ]);
        }
        if ($res['msg'] == 'ok' && $res['data']) {
            return json(['code' => 1,
                'message' => '打印成功'
            ]);
        } else {
            return json(['code' => -1,
                'message' => $res['msg']
            ]);
        }
    }

    /*
     * 添加打印机
     */

    public function addOrUpdatePrinter()
    {
        $SN = request()->post("print_sn", "");
        $KEY = request()->post("print_key", "");
        if (empty($SN) || empty($KEY)) {
            return json(['code' => -1,
                'message' => '缺少参数',
                'data' => []
            ]);
        }
        $name = request()->post("print_name", "");
        $print_id = request()->post("print_id", "");
        $isdefault = request()->post("isdefault", 0);
        $snlist = $SN . "#" . $KEY . "#" . $name;
        $client = new Feieyun($this->instance_id);

        if (empty($print_id)) {
            $res = $client->addPrinter($snlist);
        } else {
            $res = $client->updatePrinter($SN, $name);
        }
        if ($res['msg'] == 'ok') {
            if (empty($print_id) && !$res['data']['ok']) {//添加打印机的时候,返回错误信息跟更新打印机不大一样
                return json(['code' => -1,
                    'message' => $res['data']['no'][0]
                ]);
            }
            $storePrintModel = new VslStorePrinterModel();
            if (empty($print_id)) {
                if ($isdefault == 1) {
                    $storePrintModel->update(['isdefault' => 0], ['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id]);
                }
                $printer = [
                    'print_name' => $name,
                    'print_type' => 1,
                    'store_id' => $this->store_id,
                    'shop_id' => $this->instance_id,
                    'website_id' => $this->website_id,
                    'assistant_id' => $this->assistantId,
                    'print_sn' => $SN,
                    'print_key' => $KEY,
                    'isdefault' => $isdefault,
                    'create_time' => time()
                ];
                $storePrintModel->create($printer);
            } else {
                if ($isdefault == 1) {
                    $storePrintModel->update(['isdefault' => 0], ['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id]);
                }
                $printer = [
                    'print_name' => $name,
                    'print_type' => 1,
                    'store_id' => $this->store_id,
                    'shop_id' => $this->instance_id,
                    'website_id' => $this->website_id,
                    'assistant_id' => $this->assistantId,
                    'print_sn' => $SN,
                    'print_key' => $KEY,
                    'isdefault' => $isdefault
                ];
                $storePrintModel->update($printer, ['print_id' => $print_id]);
            }
            return json(['code' => 1,
                'message' => '保存成功'
            ]);
        } else {
            return json(['code' => -1,
                'message' => $res['msg']
            ]);
        }
    }

    /*
     * 打印机列表
     */

    public function printerList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $storePrintModel = new VslStorePrinterModel();
        $list = $storePrintModel->pageQuery($page_index, $page_size, ['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id], 'isdefault desc', 'print_id,print_name,isdefault,status,print_sn,print_key');
        $client = new Feieyun($this->instance_id);
        if ($list['data']) {
            foreach ($list['data'] as &$row) {
                $row['online'] = $client->printerStatus($row['print_sn']);
            }
        }

        return json(['code' => 1,
            'message' => '获取成功',
            'data' => $list
        ]);
    }

    /*
     * 打印机设为默认
     */

    public function printerDefault()
    {
        $print_id = request()->post("print_id", 0);
        if (!$print_id) {
            return json(AjaxReturn(-1006));
        }
        $storePrintModel = new VslStorePrinterModel();
        $storePrintModel->update(['isdefault' => 0], ['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id]);
        $res = $storePrintModel->update(['isdefault' => 1], ['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id, 'print_id' => $print_id]);
        if (!$res) {
            return json(['code' => -1,
                'message' => '操作失败'
            ]);
        }
        return json(AjaxReturn(1));
    }

    /*
     * 删除打印机
     */

    public function printerDelete()
    {
        $print_id = request()->post("print_id", 0);
        if (!$print_id) {
            return json(AjaxReturn(-1006));
        }
        $storePrintModel = new VslStorePrinterModel();
        $printer = $storePrintModel->getInfo(['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'store_id' => $this->store_id, 'print_id' => $print_id], 'print_sn,print_id');
        if (!$printer) {
            return json(['code' => -1,
                'message' => '找不到打印机'
            ]);
        }
        $client = new Feieyun($this->instance_id);
        $res = $client->printerDelete($printer['print_sn']);

        if ($res['code'] > 0) {
            $storePrintModel->delData(['print_id' => $printer['print_id']]);
            return json(['code' => 1,
                'message' => '删除成功'
            ]);
        } else {
            return json(['code' => -1,
                'message' => $res['message']
            ]);
        }
    }

    /**
     * 二维码支付状态
     */

    public function qrcodePayResult()
    {
        $out_trade_no = request()->post("out_trade_no", "");
        $pay = new UnifyPay();
        $payResult = $pay->get_order_info($out_trade_no);
        if ($payResult['pay_status'] > 0) {
            $data['code'] = 1;
            $data['message'] = "订单支付成功";
            $data['data']['out_trade_no'] = $out_trade_no;
            $data['data']['order_id'] = $payResult['order_id'];
            return json($data);
        }
        $data['code'] = 0;
        $data['message'] = "暂未支付或支付失败";
        return json($data);
    }

    /**
     * 根据商品条码获取相关列表
     */

    public function getGoodsByCode()
    {
        $bar_code = request()->post('bar_code', '');
        if (!$bar_code) {
            return json(['code' => -1, 'message' => '参数错误']);
        }
        $sku_model = new \data\model\VslStoreGoodsSkuModel();
        $storeGoodsModel = new \data\model\VslStoreGoodsModel();
        $pictureModel = new \data\model\AlbumPictureModel();

        $sku_info = $sku_model->getQuery(['bar_code' => $bar_code, 'store_id' => $this->store_id, 'website_id' => $this->website_id], '*', 'create_time desc');
        if (!$sku_info) {
            return json(['code' => -1, 'message' => '商品不存在']);
        }
        foreach ($sku_info as $key => $val) {
            $goods = $storeGoodsModel->getInfo(['goods_id' => $val['goods_id']], 'goods_name,picture');
            if (!$goods) {
                unset($sku_info[$key]);
                continue;
            }
            $pic = $pictureModel->getInfo(['pic_id' => $goods['picture']], 'pic_cover');
            if (!$pic) {
                $sku_info[$key]['picture'] = '';
            }
            $sku_info[$key]['goods_name'] = $goods['goods_name'];
            $sku_info[$key]['picture'] = __IMG($pic['pic_cover']);
        }
        return json(['code' => 0, 'message' => '获取成功', 'data' => ['goodslist' => $sku_info, 'count' => count($sku_info)]]);
    }

    /**
     * 店员端获取消息列表
     */
    public function clerkGetMessageList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $is_loop = request()->post('is_loop', 0);

        $condition = [
            'assistant_id' => $this->assistantId,
            'store_id' => $this->store_id,
            'shop_id' => $this->instance_id,
            'website_id' => $this->website_id
        ];
        $order = 'create_time DESC';

        $storeServer = new storeServer();
        $list = $storeServer->clerkGetMessageList($page_index, $page_size, $condition, $order, $is_loop);

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $list
        ]);
    }

    /**
     * 店员端根据会员卡二维码获取订单列表
     */
    public function getOrderListByMembercardCode()
    {
        $code = request()->post("code", 0);
        $page_index = request()->post("page_index", 1);

        if (!$code) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        //先根据会员卡二维码查找会员
        if(getAddons('membercard',$this->website_id)) {
            $membercard_user_model = new VslMembercardUserModel();
            $uid = $membercard_user_model->Query(['membercard_no' => $code],'uid')[0];
            if(empty($uid)) {
                return json([
                    'code' => -1,
                    'message' => '无会员信息',
                ]);
            }
        }else{
            return json([
                'code' => -1,
                'message' => '未开启会员卡应用',
            ]);
        }
        $condition['is_deleted'] = 0; // 未删除订单
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['store_id'] = $this->store_id;
        $condition['buyer_id'] = $uid;
        $condition['order_status'] = 1;
        $condition['wapstore'] = 1;
        $order_service = new OrderService();
        $list = $order_service->getOrderList($page_index, PAGESIZE, $condition, 'create_time DESC');

        $order_list = [];
        if($list['data']) {
            foreach ($list['data'] as $k => $order) {
                $order_list[$k]['order_id'] = $order['order_id'];
                $order_list[$k]['order_no'] = $order['order_no'];
                $order_list[$k]['out_trade_no'] = $order['out_trade_no'];
                $order_list[$k]['shop_id'] = $order['shop_id'];
                $order_list[$k]['shop_name'] = $order['shop_name'] ?: '自营店';
                $order_list[$k]['order_money'] = $order['order_money'];
                $order_list[$k]['order_status'] = $order['order_status'];
                $order_list[$k]['status_name'] = $order['status_name'];
                $order_list[$k]['pay_type_name'] = $order['pay_type_name'];
                $order_list[$k]['is_evaluate'] = $order['is_evaluate'];
                $order_list[$k]['verification_code'] = $order['verification_code'];
                $order_list[$k]['verification_qrcode'] = __IMG($order['verification_qrcode']);
                $order_list[$k]['member_operation'] = array_merge($order['member_operation'], [['no' => 'detail', 'name' => '订单详情']]);
                $order_list[$k]['promotion_status'] = ($order['promotion_money'] + $order['coupon_money'] > 0) ?: false;

                foreach ($order['order_item_list'] as $key_sku => $item) {
                    $order_list[$k]['order_item_list'][$key_sku]['order_goods_id'] = $item['order_goods_id'];
                    $order_list[$k]['order_item_list'][$key_sku]['goods_id'] = $item['goods_id'];
                    $order_list[$k]['order_item_list'][$key_sku]['sku_id'] = $item['sku_id'];
                    $order_list[$k]['order_item_list'][$key_sku]['goods_name'] = $item['goods_name'];
                    $order_list[$k]['order_item_list'][$key_sku]['price'] = $item['price'];
                    $order_list[$k]['order_item_list'][$key_sku]['num'] = $item['num'];
                    $order_list[$k]['order_item_list'][$key_sku]['pic_cover'] = getApiSrc($item['picture']['pic_cover']);
                    $order_list[$k]['order_item_list'][$key_sku]['spec'] = $item['spec'];
                    $order_list[$k]['order_item_list'][$key_sku]['status_name'] = $item['status_name'];
                    $order_list[$k]['order_item_list'][$key_sku]['refund_status'] = $item['refund_status'];
                }

                // 当订单需要进行整单售后时，这个字段取订单商品第一个商品的售后状态（目前正确情况，所有商品的refund_status一样），用于判断整单售后操作
                $order_list[$k]['order_refund_status'] = reset($order_list[$k]['order_item_list'])['refund_status'];
            }
            $list['data'] = $order_list;
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $list
        ]);
    }
    /**
     * 奖金详情页
     */
    public function storeCommission(){
        $storeServer = new storeServer();
        $storeCommissionInfo = $storeServer->storeCommission($this->assistantId, $this->store_id);
        if (!$storeCommissionInfo) {
            return json(AjaxReturn(0));
        }
        return json(['code' => 1, 'message' => '获取成功', 'data' => $storeCommissionInfo]);
    }
    /**
     * 添加提现账户
     */
    public function addBankAccount(){
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $type = request()->post('type', '1');
        $account_number = request()->post('account_number', '');
        $bank_code = request()->post('bank_code', '');
        $bank_type = request()->post('bank_type', '00');
        $bank_username = request()->post('realname', '');
        $bank_card = request()->post('bank_card', '');
        $mobile = request()->post('mobile', '');
        $validdate = request()->post('valid_date', '');
        $cvv2 = request()->post('cvv2', '');
        $bank_name = request()->post('bank_name', '');
        $bank_account = new VslMemberBankAccountModel();
        $info = $bank_account->getInfo(['account_number' => $account_number, 'uid' => -$assistant_id],'id');
        if ($info) {
            $data['message'] = '该账号已存在';
            $data['code'] = -1;
            return json($data);
        }
        if ($type == 1 || $type == 4) {
            $url = "https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?cardNo=" . $account_number . "&cardBinCheck=true";
            $result = GetCurl($url);
            if ($result['validated'] == false) {
                $data['message'] = '银行卡号信息有误，请核实';
                $data['code'] = -1;
                return json($data);
            }
        }
        if ($type == 1) {
            //如果是汇聚自动打款，则不需要签约
            //变更 2020、09、21变更为签约模式
            $webConfig = new WebConfig();
            $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
            if($joinPay['is_use'] == 1 && $joinPay['value']['fastpay_is_use'] == 1){
                //汇聚签约流程流程开始--
                //bank_code 转变成汇聚的 银行编码
                switch($bank_code){
                    case '03080000': //招商银行
                        $q8_FrpCode = "CMBCHINA";
                    break;
                    case '01040000': //中国银行
                        $q8_FrpCode = "申请品牌代理";
                    break;
                    case '01050000': //中国建设银行
                        $q8_FrpCode = "CCB";
                    break;
                    case '01020000': //中国工商银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '01030000': //中国农业银行
                        $q8_FrpCode = "ABC";
                    break;
                    case '04030000': //中国邮政储蓄银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03060000': //广东发展银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03010000': //交通银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03020000': //中信银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03030000': //中国光大银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03070000': //平安银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03050000': //中国民生银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03040000': //华夏银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '03090000': //兴业银行
                        $q8_FrpCode = "ICBC";
                    break;
                    case '04012900': //上海银行
                        $q8_FrpCode = "ICBC";
                    break;
                }
                //组装申请信息
                $apl_info = array(
                    'account_number'=>$account_number,
                    'q8_FrpCode'=>$q8_FrpCode,
                    'bank_code'=>$bank_code,
                    'bank_type'=>$bank_type,
                    'bank_username'=>$bank_username,
                    'bank_card'=>$bank_card,
                    'mobile'=>$mobile,
                    'validdate'=>$validdate,
                    'cvv2'=>$cvv2,
                    'bank_name'=>$bank_name,
                );
                $joinpay = new Joinpay();
                $req = $joinpay->sendSignSms($joinPay['value'],$apl_info);
                if($req['resp_code'] == 'SUCCESS' && $req['biz_code'] == 'JS000000'){
                    $data['data']['q1_OrderNo'] =$req['q1_OrderNo'];
                    $data['message'] = '验证码已发送';
                    $data['code'] = 1;
                    return json($data);
                }else{
                    $data['code'] = -2;
                    $data['message'] = $req['biz_msg'] ? $req['biz_msg'] : $req['rc_CodeMsg'];
                    return json($data);
                }
            }
            $pay = new UnifyPay();
            $res = $pay->tlSigning($bank_type, $account_number, $bank_card, $bank_username, $mobile, $validdate, $cvv2,-$assistant_id, $this->website_id);
            if ($res['retcode'] == 'SUCCESS') {
                if ($res['trxstatus'] == 1999) {
                    $data['data']['thpinfo'] = $res['thpinfo'];
                    $data['message'] = '验证码已发送';
                    $data['code'] = 1;
                    return json($data);
                } else {
                    $data['message'] = $res['errmsg'];
                    $data['code'] = -2;
                    return json($data);
                }
            } else {
                $data['code'] = -2;
                $data['message'] = $res['retmsg'];
                return json($data);
            }
        } else {
            $memberServer = new Member();
            $retval = $memberServer->addMemberBankAccount($uid, $type, $account_number, $bank_code, $bank_type, $bank_username, $bank_card, $bank_name, $mobile, $validdate, $cvv2,$assistant_id);
            if ($retval > 0) {
                $data['code'] = 0;
                $data['message'] = "添加成功";
                return json($data);
            } else {
                $data['code'] = -1;
                $data['message'] = "添加失败";
                return json($data);
            }
        }
    }
    //删除提现账户
    public function delAccount(){
        $account_id = request()->post('account_id', '');
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $storeServer = new storeServer();
        $retval = $storeServer->delMemberBankAccount($account_id,$uid,$assistant_id);
        if ($retval > 0) {
            $data['code'] = 0;
            $data['message'] = "删除成功";
            return json($data);
        } else {
            $data['code'] = '-1';
            $data['message'] = "删除失败";
            return json($data);
        }
    }
    //编辑提现账户
    public function updateAccount(){
        $account_id = request()->post('account_id', '');
        $type = request()->post('type', '');
        $account_number = request()->post('account_number', '');
        $bank_code = request()->post('bank_code', '');
        $bank_type = request()->post('bank_type', '00');
        $bank_username = request()->post('realname', '');
        $bank_card = request()->post('bank_card', '');
        $mobile = request()->post('mobile', '');
        $validdate = request()->post('valid_date', '');
        $cvv2 = request()->post('cvv2', '');
        $bank_name = request()->post('bank_name', '');
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $bank_account = new VslMemberBankAccountModel();
        $checkHas = $bank_account->getInfo(['id' => $account_id, 'uid' => $uid, 'assistant_id' => $assistant_id],'id');
        if(!$checkHas){
            $data['message'] = '该账号不存在';
            $data['code'] = -1;
            return json($data);
        }
        if ($type == 1 || $type == 4) {
            $info = $bank_account->getInfo(['account_number' => $account_number, 'uid' => $uid, 'assistant_id' => $assistant_id],'id');
            if ($info && $info['id'] != $account_id) {
                $data['message'] = '该账号已存在';
                $data['code'] = -1;
                return json($data);
            }
            $url = "https://ccdcapi.alipay.com/validateAndCacheCardInfo.json?cardNo=" . $account_number . "&cardBinCheck=true";
            $result = GetCurl($url);
            if ($result['validated'] == false) {
                $data['message'] = '查询不到该卡号信息';
                $data['code'] = -1;
                return json($data);
            }
        }
            if($type==1){
                //如果是汇聚自动打款，则不需要签约
                //变更 2020、09、21变更为签约模式
                $webConfig = new WebConfig();
                $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
                if($joinPay['is_use'] == 1 && $joinPay['value']['fastpay_is_use'] == 1){
                    //汇聚签约流程流程开始--
                    //bank_code 转变成汇聚的 银行编码
                    switch($bank_code){
                        case '03080000': //招商银行
                            $q8_FrpCode = "CMBCHINA";
                        break;
                        case '01040000': //中国银行
                            $q8_FrpCode = "申请品牌代理";
                        break;
                        case '01050000': //中国建设银行
                            $q8_FrpCode = "CCB";
                        break;
                        case '01020000': //中国工商银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '01030000': //中国农业银行
                            $q8_FrpCode = "ABC";
                        break;
                        case '04030000': //中国邮政储蓄银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03060000': //广东发展银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03010000': //交通银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03020000': //中信银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03030000': //中国光大银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03070000': //平安银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03050000': //中国民生银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03040000': //华夏银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '03090000': //兴业银行
                            $q8_FrpCode = "ICBC";
                        break;
                        case '04012900': //上海银行
                            $q8_FrpCode = "ICBC";
                        break;
                    }
                    //组装申请信息
                    $apl_info = array(
                        'account_number'=>$account_number,
                        'q8_FrpCode'=>$q8_FrpCode,
                        'bank_code'=>$bank_code,
                        'bank_type'=>$bank_type,
                        'bank_username'=>$bank_username,
                        'bank_card'=>$bank_card,
                        'mobile'=>$mobile,
                        'validdate'=>$validdate,
                        'cvv2'=>$cvv2,
                        'bank_name'=>$bank_name,
                    );
                    $joinpay = new Joinpay();
                    $req = $joinpay->sendSignSms($joinPay['value'],$apl_info);
                    if($req['resp_code'] == 'SUCCESS' && $req['biz_code'] == 'JS000000'){
                        $data['data']['q1_OrderNo'] =$req['q1_OrderNo'];
                        $data['message'] = '验证码已发送';
                        $data['code'] = 1;
                        return json($data);
                    }else{
                        $data['code'] = -2;
                        $data['message'] = $req['biz_msg'] ? $req['biz_msg'] : $req['rc_CodeMsg'];
                        return json($data);
                    }
                }
            $pay = new UnifyPay();
            $res = $pay->tlSigning($bank_type, $account_number, $bank_card, $bank_username, $mobile, $validdate, $cvv2, $uid, $this->website_id);
            if ($res['retcode'] == 'SUCCESS') {
                if ($res['trxstatus'] == 1999) {
                    $data['data']['thpinfo'] = $res['thpinfo'];
                    $data['message'] = '验证码已发送';
                    $data['code'] = 1;
                    return json($data);
                } else {
                    $data['message'] = $res['errmsg'];
                    $data['code'] = -2;
                    return json($data);
                }
            } else {
                $data['code'] = -2;
                $data['message'] = $res['retmsg'];
                return json($data);
            }
        } else {
            $memberServer = new Member();
            $retval = $memberServer->updateMemberBankAccount($account_id, $type, $account_number, $bank_code, $bank_type, $bank_username, $bank_card, $bank_name, $mobile, $validdate, $cvv2, $assistant_id);
            if ($retval > 0) {
                $data['code'] = 0;
                $data['message'] = "修改成功";
                return json($data);
            } else {
                $data['code'] = '-1';
                $data['message'] = "修改失败";
                return json($data);
            }
        }
    }
    //提现账户列表
    public function bankAccount()
    {
        $assistant_id = $this->assistantId;
        $storeServer = new storeServer();
        $account_list = $storeServer->getMemberBankAccount($assistant_id);
        if ($account_list) {
            $bank = new VslBankModel();
            foreach ($account_list as $k => $v) {
                if ($v['bank_code']) {
                    $info = $bank->getInfo(['bank_code' => $v['bank_code']], 'bank_iocn,deposit_once,deposit_day,credit_once,credit_day');
                    $account_list[$k]['bank_iocn'] = $info['bank_iocn'];
                    if ($v['bank_type'] == '00') {
                        $account_list[$k]['once_money'] = $info['deposit_once'];
                        $account_list[$k]['day_money'] = $info['deposit_day'];
                    }
                    if ($v['bank_type'] == '02') {
                        $account_list[$k]['once_money'] = $info['credit_once'];
                        $account_list[$k]['day_money'] = $info['credit_day'];
                    }
                } else {
                    $account_list[$k]['bank_iocn'] = '';
                }
            }
        }
        $data['code'] = 1;
        $data['data'] = $account_list;
        return json($data);
    }
    //银行列表
    public function bankList(){
        $bank = new VslBankModel();
        $bank_list = $bank->getQuery([], '*', 'sort asc');
        if (empty($bank_list)) {
            $resBank = $bank->setBankList();
            if ($resBank) {
                $bank_list = $bank->getQuery([], '*', 'sort asc');
            }
        }
        $data['data'] = $bank_list;
        $data['code'] = 1;
        return json($data);
    }
    /**
     * 银行卡用户解绑银行卡
     */
    public function tlUntying(){
        $id = request()->post('id', '');
        $member = new VslMemberBankAccountModel();
        $info = $member->getInfo(['id'=>$id],'agree_id,signed_id'); //非通联绑定账号可以直接删除
        //查询是否为汇聚签约
        $webConfig = new WebConfig();
        $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        if($info && $info['signed_id'] && $joinPay['is_use'] == 1 && $joinPay['value']['fastpay_is_use'] == 1){
            $joinpay = new Joinpay();
            $res = $joinpay->unSign($joinPay['value'],$info['signed_id']);
            if($res['resp_code'] == 'SUCCESS' && $res['biz_code'] == 'JS000000'){
                $member = new VslMemberBankAccountModel();
                $member->delData(['id'=>$id]);
                $data['message'] = '解绑成功';
                $data['code'] = 1;
                return json($data);
            }else{
                $data['code'] = -1;
                $data['message'] = $res['biz_msg'];
                return json($data);
            }
        }else if($info && $info['agree_id']){
            $pay = new UnifyPay();
            $res = $pay->tlUntying($id,$this->website_id);
            if($res['retcode']=='SUCCESS'){
                $member = new VslMemberBankAccountModel();
                $member->delData(['id'=>$id]);
                $data['message'] = '解绑成功';
                $data['code'] = 1;
                return json($data);
            }else{
                $data['code'] = -1;
                $data['message'] = $res['errmsg'];
                return json($data);
            }
        }else{
            $member = new VslMemberBankAccountModel();
            $member->delData(['id'=>$id]);
            $data['message'] = '解绑成功';
            $data['code'] = 1;
            return json($data);
        }
    }
    /**
     * 银行卡用户签约重发签约验证码
     */
    public function tlSigning()
    {
        $accttype = request()->post('bank_type', '00');
        $acctno = request()->post('account_number', '');
        $idno = request()->post('bank_card', '');
        $acctname = request()->post('realname', '');
        $mobile = request()->post('mobile', '');
        $validdate = request()->post('validdate', '');
        $cvv2 = request()->post('cvv2', '');
        $thpinfo = htmlspecialchars_decode(stripslashes(request()->post('thpinfo', '')));
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        // $webConfig = new WebConfig();
        // $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $pay = new UnifyPay();
        $res = $pay->tlAgreeSms($accttype, $acctno, $idno, $acctname, $mobile, $validdate, $cvv2, $thpinfo, $uid, $this->website_id);
        if ($res['retcode'] == 'SUCCESS') {
            $data['message'] = '短信已发送';
            $data['code'] = 1;
            return json($data);
        } else {
            $data['code'] = -1;
            $data['message'] = $res['errmsg'] ? $res['errmsg'] : $res['retmsg'];
            return json($data);
        }
    }
    /**
     * 银行卡用户签约申请确认
     */
    public function tlAgreeSigning(){
        $account_id = request()->post('account_id', '');
        $account_number = request()->post('account_number', '');
        $bank_code = request()->post('bank_code', '');
        $bank_type = request()->post('bank_type', '00');
        $bank_username = request()->post('realname', '');
        $bank_card = request()->post('bank_card', '');
        $mobile = request()->post('mobile', '');
        $validdate = request()->post('valid_date', '');
        $cvv2 = request()->post('cvv2', '');
        $smscode = request()->post('smscode', '');
        $bank_name = request()->post('bank_name', '');
        $q1_OrderNo = request()->post('q1_OrderNo', ''); //汇聚签约使用
        $thpinfo = htmlspecialchars_decode(stripslashes(request()->post('thpinfo', '')));
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        //查询是否为汇聚签约
        $webConfig = new WebConfig();
        $joinPay = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        if($joinPay['is_use'] == 1 && $joinPay['value']['fastpay_is_use'] == 1){
            $apl_info = array(
                'account_number'=>$account_number,
                'bank_code'=>$bank_code,
                'bank_type'=>$bank_type,
                'bank_username'=>$bank_username,
                'bank_card'=>$bank_card,
                'mobile'=>$mobile,
                'validdate'=>$validdate,
                'cvv2'=>$cvv2,
                'bank_name'=>$bank_name,
                'smscode'=>$smscode,
                'q1_OrderNo'=>$q1_OrderNo,
            );
            $joinpay = new Joinpay();
            $req = $joinpay->smsSign($joinPay['value'],$apl_info);
            if($req['resp_code'] == 'SUCCESS' && $req['biz_code'] == 'JS000000'){
                if ($bank_code) {
                    $bank = new VslBankModel();
                    $open_bank = $bank->getInfo(['bank_code' => $bank_code], 'bank_short_name')['bank_short_name'];
                }
                $bank_account = new VslMemberBankAccountModel();
                $info = $bank_account->getInfo(['account_number' => $account_number, 'uid' => $uid,'assistant_id' => $assistant_id],'id');
                if ($account_id) {
                    $bank_account->save(['cvv2' => $cvv2, 'type' => 1, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $bank_code, 'open_bank' => $open_bank, 'signed_id' => $req['sign_no'], 'modify_date' => time(), 'account_number' => $account_number], ['id' => $account_id]);
                } elseif ($info) {
                    $bank_account->save(['cvv2' => $cvv2, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $bank_code, 'open_bank' => $open_bank, 'signed_id' => $req['sign_no'], 'modify_date' => time(), 'account_number' => $account_number], ['account_number' => $account_number, 'uid' => $uid,'assistant_id' => $assistant_id]);
                } else {
                    $bank_account->save(['assistant_id' => $assistant_id, 'uid' => $uid, 'cvv2' => $cvv2, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $bank_code, 'open_bank' => $open_bank, 'signed_id' => $req['sign_no'], 'account_number' => $account_number, 'type' => 1, 'create_date' => time(), 'website_id' => $this->website_id]);
                }
                $data['message'] = '签约成功';
                $data['code'] = 1;
                return json($data);
            }else{
                $data['code'] = -1;
                $data['message'] = $req['biz_msg'] ? $req['biz_msg'] : $req['rc_CodeMsg'];
                return json($data);
            }
            exit;
        }
        $pay = new UnifyPay();
        $res = $pay->tlAgreeSigning($bank_type, $account_number, $bank_card, $bank_username, $mobile, $validdate, $cvv2, $smscode, $thpinfo, $uid, $this->website_id);
        if ($res['retcode'] == 'SUCCESS') {
            if ($res['trxstatus'] == '0000') {
                if ($bank_code) {
                    $bank = new VslBankModel();
                    $open_bank = $bank->getInfo(['bank_name' => $res['bankname']], 'bank_short_name')['bank_short_name'];
                }
                $bank_account = new VslMemberBankAccountModel();
                $info = $bank_account->getInfo(['account_number' => $account_number, 'uid' => $uid,'assistant_id' => $assistant_id],'id');
                if ($account_id) {
                    $bank_account->save(['cvv2' => $cvv2, 'type' => 1, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $res['bankcode'], 'open_bank' => $res['bankname'], 'agree_id' => $res['agreeid'], 'modify_date' => time(), 'account_number' => $account_number, 'is_update' => 0], ['id' => $account_id]);
                } elseif ($info) {
                    $bank_account->save(['cvv2' => $cvv2, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $res['bankcode'], 'open_bank' => $res['bankname'], 'agree_id' => $res['agreeid'], 'modify_date' => time(), 'account_number' => $account_number, 'is_update' => 0], ['account_number' => $account_number, 'uid' => $uid,'assistant_id' => $assistant_id]);
                } else {
                    $bank_account->save(['assistant_id' => $assistant_id, 'uid' => $uid, 'cvv2' => $cvv2, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $res['bankcode'], 'open_bank' => $res['bankname'], 'agree_id' => $res['agreeid'], 'account_number' => $account_number, 'type' => 1, 'create_date' => time(), 'website_id' => $this->website_id, 'is_update' => 0]);
                }
                $data['message'] = '签约成功';
                $data['code'] = 1;
            } else {
                $data['code'] = -1;
                $data['message'] = $res['errmsg'];
            }
            return json($data);
        } else {
            $data['code'] = -1;
            $data['message'] = $res['retmsg'];
            return json($data);
        }
    }
    //更新银行账户 变更通联支付后需要升级更新
    public function updateBankAccount(){
        $id = request()->post('account_id', 0);
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $id = intval($id);
        if (empty($id)) {
            $data['message'] = '请选择需要更新的账户';
            $data['code'] = -1;
            return json($data);
        }
        $bank_account = new VslMemberBankAccountModel();
        $info = $bank_account->getInfo(['id' => $id],'uid,type,bank_type,account_number,bank_card,open_bank,mobile,validdate,cvv2,assistant_id');
        if($info['uid'] != $uid || $info['assistant_id'] != $assistant_id){
            $data['message'] = '非法操作！';
            $data['code'] = -1;
            return json($data);
        }
        if($info['type'] != 1){
            $data['message'] = '账户类型为银行卡自动的才需升级，请重新选择升级账户';
            $data['code'] = -1;
            return json($data);
        }
        $bank_type = $info['bank_type'];
        $account_number = $info['account_number'];
        $bank_card = $info['bank_card'];
        $bank_username = $info['open_bank'];
        $mobile = $info['mobile'];
        $validdate = $info['validdate'];
        $cvv2 = $info['cvv2'];
        $pay = new UnifyPay();
        $res = $pay->tlSigning($bank_type, $account_number, $bank_card, $bank_username, $mobile, $validdate, $cvv2, $uid, $this->website_id);
        if ($res['retcode'] == 'SUCCESS') {
            if ($res['trxstatus'] == 1999) {
                $data['data']['thpinfo'] = $res['thpinfo'];
                $data['message'] = '验证码已发送';
                $data['code'] = 1;
                return json($data);
            } else {
                $data['message'] = $res['errmsg'];
                $data['code'] = -2;
                return json($data);
            }
        } else {
            $data['code'] = -2;
            $data['message'] = $res['retmsg'];
            return json($data);
        }
    }
    //获取银行账户详情
    public function bankaccountDetail(){
        $id = request()->post('account_id', 0);
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $id = intval($id);
        if (empty($id)) {
            $data['message'] = '请选择需要更新的账户';
            $data['code'] = -1;
            return json($data);
        }
        $bank_account = new VslMemberBankAccountModel();
        $info = $bank_account->getInfo(['id' => $id],'uid,website_id,id,type,realname,account_number,open_bank,bank_type,bank_code,bank_card,mobile,cvv2,valid_date,is_update,assistant_id');
        if($info['uid'] != $uid || $info['assistant_id'] != $assistant_id || $info['website_id'] != $this->website_id){
            $data['message'] = '非法操作！';
            $data['code'] = -1;
            return json($data);
        }
        $data['data'] = $info;
        $data['message'] = '获取成功';
        $data['code'] = 1;
        return json($data);
    }
    //更新签约信息
    public function updateTlAgreeSigning(){
        $account_id = request()->post('account_id', '');
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $account_id = intval($account_id);
        if (empty($account_id)) {
            $data['message'] = '请选择需要更新的账户';
            $data['code'] = -1;
            return json($data);
        }
        //读取账户信息 换取以下结果
        $bank_account = new VslMemberBankAccountModel();
        $info = $bank_account->getInfo(['id' => $account_id],'uid,type,bank_type,account_number,bank_card,open_bank,mobile,validdate,cvv2,bank_code,realname,assistant_id');
        if($info['uid'] != $uid || $info['assistant_id'] != $assistant_id){
            $data['message'] = '非法操作！';
            $data['code'] = -1;
            return json($data);
        }
        if($info['type'] != 1){
            $data['message'] = '账户类型为银行卡自动的才需升级，请重新选择升级账户';
            $data['code'] = -1;
            return json($data);
        }
        $account_number = $info['account_number'];
        $bank_code = $info['bank_code'];
        $bank_type = $info['bank_type'];
        $bank_username = $info['realname'];
        $bank_card = $info['bank_card'];
        $mobile = $info['mobile'];
        $validdate = $info['valid_date'];
        $cvv2 = $info['cvv2'];
        $smscode = request()->post('smscode', '');
        $thpinfo = htmlspecialchars_decode(stripslashes(request()->post('thpinfo', '')));
        $pay = new UnifyPay();
        $res = $pay->tlAgreeSigning($bank_type, $account_number, $bank_card, $bank_username, $mobile, $validdate, $cvv2, $smscode, $thpinfo, $uid, $this->website_id);
        if ($res['retcode'] == 'SUCCESS') {
            if ($res['trxstatus'] == '0000') {
                if ($bank_code) {
                    $bank = new VslBankModel();
                    $open_bank = $bank->getInfo(['bank_name' => $res['bankname']], 'bank_short_name')['bank_short_name'];
                }
                $bank_account = new VslMemberBankAccountModel();
                $info = $bank_account->getInfo(['account_number' => $account_number, 'uid' => $uid, 'assistant_id' => $assistant_id],'id');
                if ($account_id) {
                    $bank_account->save(['cvv2' => $cvv2, 'type' => 1, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $res['bankcode'], 'open_bank' => $res['bankname'], 'agree_id' => $res['agreeid'], 'modify_date' => time(), 'account_number' => $account_number, 'is_update' => 0], ['id' => $account_id]);
                } elseif ($info) {
                    $bank_account->save(['cvv2' => $cvv2, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $res['bankcode'], 'open_bank' => $res['bankname'], 'agree_id' => $res['agreeid'], 'modify_date' => time(), 'account_number' => $account_number, 'is_update' => 0], ['account_number' => $account_number, 'uid' => $uid, 'assistant_id' => $assistant_id]);
                } else {
                    $bank_account->save(['assistant_id' => $assistant_id,'uid' => $uid, 'cvv2' => $cvv2, 'valid_date' => $validdate, 'mobile' => $mobile, 'bank_type' => $bank_type, 'realname' => $bank_username, 'bank_card' => $bank_card, 'bank_code' => $res['bankcode'], 'open_bank' => $res['bankname'], 'agree_id' => $res['agreeid'], 'account_number' => $account_number, 'type' => 1, 'create_date' => time(), 'website_id' => $this->website_id, 'is_update' => 0]);
                }
                $data['message'] = '签约成功';
                $data['code'] = 1;
            } else {
                $data['code'] = -1;
                $data['message'] = $res['errmsg'];
            }
            return json($data);
        } else {
            $data['code'] = -1;
            $data['message'] = $res['retmsg'];
            return json($data);
        }
    }
    //提现表单页
    public function withdrawForm(){
        $storeServer = new storeServer();
        $member_info = $storeServer->storeCommission($this->assistantId, $this->store_id);
        //获取可提现金额
        $data['data']['balance'] = $member_info['commission'];
        $data['data']['apl_commission'] = $member_info['apl_commission'];
        //提现相关设置
        $config_service = new WebConfig();
        $list = $config_service->getConfig(0, 'WITHDRAW_BALANCE');
        $data['data']['balance'] = $member_info['commission'];
        $data['data']['is_start'] = $list['is_use'];
        $data['data']['withdraw_cash_min'] = $list['value']['withdraw_cash_min'] ? $list['value']['withdraw_cash_min'] : '';
        if (empty($user_info['payment_password'])) {
            $data['data']['set_password'] = 1;
        } else {
            $data['data']['set_password'] = 0;
        }
        $data['data']['is_bank'] = 0;
        $data['data']['is_alipay'] = 0;
        $data['data']['is_wpy'] = 0;
        $data['data']['wx_openid'] = "";
        $data['data']['withdraw_message'] = explode(',', $list['value']['withdraw_message']) ? : [];
        if ($list['value']['withdraw_message']) {
            $withdraw_message = explode(',', $list['value']['withdraw_message']);
            if (in_array('1', $withdraw_message)) {
                $data['data']['is_bank'] = 1;
            }
            if (in_array('4', $withdraw_message)) {
                $data['data']['is_bank'] = 1;
            }
            if (in_array('3', $withdraw_message)) {
                $alipay = $config_service->getConfig(0, 'ALIPAY');
                $data['data']['is_alipay'] = ($alipay['is_use'] == 1) ? 1 : 0;
            }
            if (in_array('2', $withdraw_message)) {
                $wpy = $config_service->getConfig(0, 'WPAY');
                $data['data']['is_wpy'] = ($wpy['is_use'] == 1) ? 1 : 0;
                if ($data['data']['is_wpy'] == 1) {
                    if ($member_info['wx_openid']) {
                        $data['code'] = 1;
                        $data['data']['wx_openid'] = $member_info['wx_openid'];
                    } else {
                        $data['data']['is_wpy'] = 0;
                        $data['data']['wx_openid'] = "";
                    }
                }
            }
        }
        $data['code'] = 1;
        return json($data);
    }
    //提现
    public function withdraw()
    {
        $withdraw_no = 'BT' . time() . rand(111, 999);
        $bank_account_id = request()->post('bank_account_id', '');
        $cash = request()->post('cash', '');
        $type = request()->post('type', '');//1银行卡2微信3支付宝
        $password = request()->post('password', '');
        $assistant_id = $this->assistantId;
        $uid = -$assistant_id;
        $key = 'uid'.$uid.'_commissionWithdraw_website_id_'.$this->website_id;
        $is_lock = lock($key, 5);
        if(!$is_lock){
            $data['code'] = -1;
            $data['message'] = "正在处理,请勿频繁重复提交,请5秒后重试";
            return json($data);
        }
        //密码长度
        $config = new WebConfig();
        //验证是否开启通联
        $config_info = $config->getConfig(0, 'TLPAY', $this->website_id);
        $info = $config_info['value'];
        if (!empty($info) && $config_info['is_use'] == 0 && $type == 1) {
            $data['code'] = '-1';
            $data['message'] = "请先开启通联配置";
            return json($data);
        }
        if (empty($type) || (empty($bank_account_id) && $type != 2) || empty($cash)) {
            $data['code'] = '-1';
            $data['message'] = "提交参数有误";
            return json($data);
        }
        $cash = floatval($cash);
        $shop_id = $this->instance_id;
        $store_id = $this->store_id;
        $storeServer = new storeServer();
		$retval = $storeServer->addMemberBalanceWithdraw($shop_id, $withdraw_no, $uid, $bank_account_id, $cash, $type,$assistant_id,$store_id);
        unlock($key);
        if ($retval > 0) {
            $data['code'] = 0;
            $data['message'] = "提交申请成功";
            return json($data);
        } else {
            $data['code'] = '-1';
            $data['message'] = getErrorInfo($retval);
            return json($data);
        }
    }
    //余额流水
    public function storeCommissionList()
    {
        $assistant_id = $this->assistantId;
        $store_id = $this->store_id;
        $storeServer = new storeServer();
        $page_index = request()->post('page_index') ? request()->post('page_index') : 1;
        $condition['assistant_id'] = $assistant_id;
        $condition['store_id'] = $store_id;
        $field = 'id,balance,add_time_str,text,money,account_type,status';
        $list = $storeServer->getAccountLists($page_index, PAGESIZE, $condition,'',$field);
        $data['data'] = $list;
        $data['data']['page_index'] = $page_index;
        $data['code'] = 0;
        return json($data);
    }
    /*
     * 余额流水详情
     * */
    public function storeCommissionDetail()
    {
        $id = request()->post('id', '');
        $storeServer = new storeServer();
        $list = $storeServer->getAccountDetail($id);
        return json($list);
    }

    /**
     * 订单列表
     *
     * @return \data\model\unknown|\think\response\Json
     */
    public function getStoreAppointOrderList(){
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size') ?: PAGESIZE;
        $order_no =  request()->post('search_text');
        $order_status =  request()->post('order_status');

        $order_model = new VslAppointOrderModel();
        $condition['nm.is_deleted'] = 0; // 未删除订单
        $condition['nm.pay_status'] = 1;
        $condition['nm.store_id'] = $this->store_id;


        if($order_status ==1 ){
            $start_date = date('Y-m-d' . ' 00:00:00', time());
            $end_date = date('Y-m-d' . ' 00:00:00', time() + 3600 * 24);
            $condition['appoint_time'][] = [
                '>',
                $start_date
            ];
            $condition['appoint_time'][] = [
                '<',
                $end_date
            ];
        }
        if (!empty($order_no)) {

            $where['user_name|nick_name|user_tel'] = ['LIKE', '%' . $order_no . '%'];
            $user_model = new UserModel();
            $buyer_ids = $user_model::where($where)->column('uid');
            if(count($buyer_ids)){
                $condition['nm.buyer_id'] = array(
                    "in",
                    $buyer_ids
                );
            }else{
                $condition['nm.buyer_id'] = array(
                    "in",
                    [-1]
                );
            }

//            $condition['out_trade_no'] = array(
//                "like",
//                "%" . $order_no . "%"
//            );
        }
        $list = $order_model->getViewList($page_index, $page_size, $condition, 'appoint_time desc');
        if(count($list['data']) == 0){
            return $list;
        }

        $img_ids = [];
        foreach ($list['data'] as &$item){
            $order_status_name = '未支付';
            if($item['order_status'] == 1){
                $order_status_name = '已支付';
            }else if($item['order_status'] == 2){
                $order_status_name = '已完成';
            }else if($item['order_status'] == -1){
                $order_status_name = '已取消';
            }
            $item['order_status_name'] = $order_status_name;
            $item['order_real_money'] = $item['pay_money'];
            $item['pay_time'] = '下单时间：'.date('Y-m-d H:i:s', $item['pay_time']);
            $item['username'] = empty($item['name']) ? $item['nick_name'] : $item['name'];
            $img_ids[] = $item['picture'];
        }

        $pic = new AlbumPictureModel();
        $pic_list = $pic::where('pic_id', 'in', array_unique($img_ids))->column('pic_cover', 'pic_id');

        foreach ($list['data'] as $key=> $d){
            $order_item_list = [];
            $d['goods_image'] = isset($pic_list[$d['picture']]) ? $pic_list[$d['picture']] : '';
            $order_item_list[] = [
                'goods_name'=> $d['goods_name'],
                'pic_cover'=> $d['goods_image'],
                'user_name'=> "预约人：".$d['username'].'/'.$d['user_tel'],
                'sku_name'=> "预约时间：".$d['appoint_time']
            ];
            $list['data'][$key]['order_item_list'] = $order_item_list;
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => [
                'order_list' => $list['data'],
                'page_count' => $list['page_count'],
                'total_count' => $list['total_count']
            ]
        ]);
    }

    /**
     * 确认订单
     *
     * @return array|\multitype
     */
    public function appointOrderConfirm(){
        if ($this->merchant_expire == 1) {
            return AjaxReturn(-1);
        }
        $order_id = request()->post('order_id');
        $order = new VslAppointOrderModel();
        //是否被预约
        $info = $order->getInfo(['order_id' => $order_id, 'pay_status'=> 1, 'order_status'=> 1],
            'order_id,pay_money,pay_status,website_id,appoint_time');
        if(is_null($info)){
            return ['code' => -2,'message' => '未查找到订单，请刷新后重试'];
        }
        $date = date('Y-m-d H:i:s');
        if($info['appoint_time'] > $date){
            return ['code' => -2,'message' => '未到预约时间，不允许操作'];
        }

        //订单完成
        $order->save(['order_status'=> 2], ['order_id' => $order_id, 'pay_status'=> 1, 'order_status'=> 1]);
        return ['code' => 1,'message' => '订单完成'];
    }
}
