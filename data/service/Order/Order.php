<?php

namespace data\service\Order;

use addons\abroadreceivegoods\model\VslCountryListModel;
use addons\bargain\model\VslBargainRecordModel;
use addons\bargain\service\Bargain;
use addons\bonus\model\VslOrderBonusModel;
use addons\channel\model\VslChannelOrderGoodsModel;
use addons\channel\model\VslChannelOrderModel;
use addons\channel\model\VslChannelOrderSkuRecordModel;
use addons\channel\server\Channel;
use addons\coupontype\model\VslCouponTypeModel;
use addons\distribution\model\VslOrderDistributorCommissionModel;
use addons\electroncard\server\Electroncard;
use addons\giftvoucher\model\VslGiftVoucherModel;
use addons\invoice\server\Invoice as InvoiceServer;
use addons\liveshopping\model\LiveRecordModel;
use addons\merchants\server\Merchants;
use addons\receivegoodscode\server\ReceiveGoodsCode as ReceiveGoodsCodeSer;
use addons\seckill\server\Seckill;
use addons\store\server\Store;
use data\model\AlbumPictureModel;
use data\model\RabbitOrderRecordModel;
use data\model\VslActivityOrderSkuRecordModel;
use data\model\VslGoodsSkuModel;
use data\model\VslOrderActionModel as VslOrderActionModel;
use data\model\VslOrderExpressCompanyModel;
use data\model\VslOrderGoodsExpressModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderGoodsPromotionDetailsModel;
use data\model\VslOrderModel;
use data\model\VslOrderPromotionDetailsModel;
use data\model\VslOrderShopReturnModel;
use addons\presell\model\VslPresellModel;
use addons\fullcut\model\VslPromotionMansongRuleModel;
use data\model\UserModel as UserModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslOrderRefundModel;
use data\model\VslStoreGoodsModel;
use data\model\VslStoreGoodsSkuModel;
use data\service\Address;
use data\service\BaseService;
use data\service\GoodsCalculate\GoodsCalculate;
use data\service\Member\MemberAccount;
use data\service\Member as MemberService;
use addons\coupontype\server\Coupon as CouponServer;
use data\service\Order as OrderService;
use data\service\Order\OrderGoods as OrderGoodsService;
use data\service\Order as ServiceOrder;
use data\service\ShopAccount;
use data\service\UnifyPay;
use data\service\Config;
use addons\store\model\VslStoreModel;
use think\Exception;
use think\Log;
use data\model\VslOrderRefundAccountRecordsModel;
use addons\shop\model\VslShopModel;
use addons\groupshopping\server\GroupShopping;
use addons\gift\server\Gift;
use addons\giftvoucher\server\GiftVoucher;
use data\model\VslOrderCalculateModel;
use data\model\VslOrderScheduleModel;
use addons\gift\model\VslPromotionGiftModel;
use addons\membercard\server\Membercard as MembercardSer;
use data\service\Addons as AddonsSer;
use addons\agent\service\Agent as DistributorService;
use addons\systemform\server\Systemform as CustomFormServer;
use data\service\AddonsConfig;
use addons\supplier\server\Supplier as SupplierService;
use think\Request;
use data\service\MemberCard as MemberCardService; 
use addons\luckyspell\server\Luckyspell as luckySpellServer;

/**
 * ???????????????
 */
class Order extends BaseService
{

    public $order;

    // ????????????
    function __construct()
    {
        parent::__construct();
        $this->order = new VslOrderModel();
    }

    /**
     * ????????????
     * @param array $order_info
     * @return int|mixed
     */
    public function orderCreateNew(array $order_info)
    {
        $this->order->startTrans();
        $account_flow = new MemberAccount();
        $couponServer = getAddons('coupontype', $order_info['website_id']) ? new CouponServer() : '';
        $keyArray = ['IS_POINT', 'SHOPPING_BACK_POINTS', 'POINT_DEDUCTION_NUM'];
        // ???????????????????????????
        $config = new Config();
        $config_info = $config->getShopConfigNew(0,$keyArray,$order_info['website_id']);
        $is_point = $config_info['is_point'];
        $give_point_type = 0;
        $give_point = 0;
        $convert_rate = 0;
        if ($is_point == 1) {
            // ????????????????????????
            $give_point_type = $config_info['shopping_back_points'];
            //????????????????????????
            $convert_rate = $config_info['point_deduction_num'];

        }
        if(isset($order_info['custom_order']) && $order_info['custom_order'] != ''){
            $order_info['custom_order'] = str_replace('amp;', '', $order_info['custom_order']);
        }
        try {
            if (empty($order_info['presell_id'])) {
                $money_type = 0;
            }
            $data_order = array(
                'goods_type' => $order_info['goods_type'], //??????6
                'custom_order' => $order_info['custom_order'], //????????????
                'custom_id' => $order_info['custom_id'], //??????????????????Id
                'order_no' => $order_info['order_no'],
                'out_trade_no' => $order_info['out_trade_no'],
                'out_trade_no_presell' => $order_info['out_trade_no_presell'],
                'order_sn' => $order_info['order_sn'],
                'order_from' => $order_info['order_from'],
                'buyer_id' => $order_info['buyer_id'],
                'user_name' => $order_info['nick_name'],
                'buyer_ip' => $order_info['ip'],
                'buyer_message' => $order_info['leave_message'],
                'buyer_invoice' => $order_info['buyer_invoice'] ?: '',
                'shipping_time' => $order_info['shipping_time'] ?: 0, // datetime NOT NULL COMMENT '????????????????????????',
                'receiver_mobile' => $order_info['receiver_mobile'], // varchar(11) NOT NULL DEFAULT '' COMMENT '????????????????????????',
                'receiver_province' => $order_info['receiver_province'], // int(11) NOT NULL COMMENT '??????????????????',
                'receiver_city' => $order_info['receiver_city'], // int(11) NOT NULL COMMENT '?????????????????????',
                'receiver_district' => $order_info['receiver_district'], // int(11) NOT NULL COMMENT '?????????????????????',
                'receiver_address' => $order_info['receiver_address'], // varchar(255) NOT NULL DEFAULT '' COMMENT '?????????????????????',
                'receiver_zip' => $order_info['receiver_zip'], // varchar(6) NOT NULL DEFAULT '' COMMENT '???????????????',
                'receiver_name' => $order_info['receiver_name'], // varchar(50) NOT NULL DEFAULT '' COMMENT '???????????????',
                'shop_id' => $order_info['shop_id'], // int(11) NOT NULL COMMENT '????????????id',
                'shop_name' => $order_info['shop_name'], // varchar(100) NOT NULL DEFAULT '' COMMENT '??????????????????',
                'point' => $order_info['point'], // int(11) NOT NULL COMMENT '??????????????????',
                'coupon_id' => $order_info['coupon']['coupon_id'] ?: 0, // int(11) NOT NULL COMMENT '???????????????id',
                'give_point' => $order_info['total_return_point'], // int(11) NOT NULL COMMENT '??????????????????',, // int(11) NOT NULL COMMENT '??????????????????',
                'create_time' => $order_info['create_time'],
                'website_id' => $order_info['website_id'],
                'shipping_company_id' => $order_info['shipping_company_id'] ?: 0,

                'payment_type' => $order_info['pay_type'],
                'payment_type_presell' => 0,
                'shipping_type' => $order_info['shipping_type'],
                'order_type' => $order_info['order_type'],
                'order_status' => $order_info['order_status'], // tinyint(4) NOT NULL COMMENT '????????????',
                'pay_status' => $order_info['pay_status'], // tinyint(4) NOT NULL COMMENT '??????????????????',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'give_point_type' => $give_point_type,

                'point_money' => 0.00, // decimal(10, 2) NOT NULL COMMENT '??????????????????????????????',
                'coupon_money' => $order_info['coupon_reduction_amount'] ?: 0, // _money decimal(10, 2) NOT NULL COMMENT '?????????????????????',
                'user_money' => $order_info['user_money'], // decimal(10, 2) NOT NULL COMMENT '???????????????????????????',
                'user_platform_money' => $order_info['user_platform_money'], // ??????????????????
                'promotion_money' => $order_info['promotion_money'] ?: 0, // decimal(10, 2) NOT NULL COMMENT '?????????????????????',
                'shipping_money' => $order_info['shipping_fee'] ?: $order_info['shipping_money'], // decimal(10, 2) NOT NULL COMMENT '????????????',
                'pay_money' => $order_info['pay_money'], // decimal(10, 2) NOT NULL COMMENT '??????????????????',
                'channel_money' => $order_info['channel_money'] ?: 0, // ???????????????
                'normal_money' => $order_info['normal_money'] ?: 0, // ????????????
                'final_money' => $order_info['final_money'] ?: 0,//????????????
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '??????????????????',
                'coin_money' => $order_info['coin'] ?: 0,
                'goods_money' => $order_info['shop_total_amount'], // decimal(19, 2) NOT NULL COMMENT '????????????',
                'tax_money' => $order_info['tax_money'] ?: 0, // ??????
                'order_money' => $order_info['order_money'], // decimal(10, 2) NOT NULL COMMENT '????????????',
                'member_money' => $order_info['member_money'], // decimal(10,2)???????????????,
                'platform_promotion_money' => $order_info['platform_promotion_money'] ?: 0, // ?????????????????????????????????
                'shop_promotion_money' => $order_info['shop_promotion_money'] ?: 0, //?????????????????????????????????
                'promotion_free_shipping' => $order_info['promotion_free_shipping'] ?: 0, //????????????????????????????????????????????????????????????
                'group_id' => $order_info['group_id'] ?: 0, //????????????id
                'luckyspell_id' => $order_info['luckyspell_id'] ?: 0, //?????????????????????id
                'thresholdtype_point' => $order_info['thresholdtype_point'] ?: 0, //?????????????????????
                'luckyspell_record_id' => 0, //?????????????????????id
                'group_record_id' => $order_info['group_record_id'] ?: 0, //??????????????????id
                'luckyspell_money' => $order_info['luckyspell_money'] ?: 0, //??????????????????id
                'buy_type' => $order_info['buy_type'] ?: 0,
                'presell_id' => $order_info['presell_id'] ?: '',
                'store_id' => $order_info['store_id'] ?: 0,
                'bargain_id' => $order_info['bargain_id'] ?: 0,
                'verification_code' => $order_info['verification_code'] ?: 0,
                'money_type' => $money_type,
                'point_convert_rate' => $convert_rate,//????????????????????????
                'shopkeeper_id' => $order_info['shopkeeper_id'] ?: '',
                'card_store_id' => $order_info['card_store_id'] ?: 0,
                'deduction_money' => $order_info['deduction_money'] ?: 0,
                'deduction_point'=>$order_info['deduction_point']?:0,
                'shop_order_money' => $order_info['shop_order_money'], // decimal(10, 2) NOT NULL COMMENT '????????????????????????',
                'sign_time' => $order_info['sign_time'] ? : 0,
                'invoice_tax' => $order_info['invoice_tax'] ?: 0,
                'invoice_type' => $order_info['invoice_type'] ?: 0,
                'order_types' => $order_info['order_types'] ?: '',
//                'http_from' => isFromMp() ? 1: 0,
                'http_from' => $order_info['order_from'] == 6 ? 1 : 0,
                'is_mansong' => $order_info['man_song_full_cut'] ? 1: 0,
                'membercard_deduction_money' => $order_info['membercard_deduction_money'] ?: 0,
                'supplier_id' => $order_info['supplier_id'] ?: '',
                'receiver_country' => $order_info['receiver_country'],
                'receiver_type' => $order_info['receiver_type'],
            ); // datetime NOT NULL DEFAULT 'CURRENT_TIMESTAMP' COMMENT '??????????????????',
            if ($order_info['pay_status'] == 2) {
                $data_order['pay_time'] = time();
            }
            $order = new VslOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;

            $pay = new UnifyPay();
            $pay->createPayment($order_info['shop_id'], $order_info['out_trade_no'], $order_info['shop_name'] . '??????', $order_info['shop_name'] . '??????', $order_info['pay_money'], 1, $order_id, $order_info['create_time'], $order_info['website_id']);
            if ($order_info['presell_id']) {
                $pay->createPayment($order_info['shop_id'], $order_info['out_trade_no_presell'], $order_info['shop_name'] . '??????', $order_info['shop_name'] . '??????', $order_info['final_money'], 1, $order_id, $order_info['create_time']);
            }
            // ???????????????
            $order_goods = new OrderGoods();
            if($order_info['order_type'] == 12){
                if(!$order_info['store_id']){
                    $order_info['store_id'] = 0;
                }
                $res_order_goods = $order_goods->addOrderGoodsForStore($order_id, $order_info['sku_info'], 0, $order_info['buyer_id'], $order_info['website_id'],$order_info['order_type'], $order_info['pay_money'], $order_info['order_from'], $order_info['invoice_tax'],$order_info['store_id']);
            }else{
                if(!$order_info['order_types']){
                    $order_info['order_types'] = ''; 
                }
                $store_id = $order_info['store_id'] ?:($order_info['card_store_id'] ?: 0);
                $res_order_goods = $order_goods->addOrderGoodsNew($order_id, $order_info['sku_info'], 0, $order_info['buyer_id'], $order_info['website_id'],$order_info['order_type'], $order_info['pay_money'], $order_info['order_from'], $store_id, $order_info['invoice_tax'], $order_info['order_types']);
            }

            if (!($res_order_goods > 0)) {
                $this->order->rollback();
                return $res_order_goods;
            }
            //?????????????????? ??????????????????????????????????????? ?????????????????????????????????????????????????????? ,????????????????????????????????????  edit for 2020/0728 Z
            if($order_info['goods_type']==6 && $order_info['check_goods_id']){
                //???????????? ?????????????????????????????????????????????????????????
                $customFormServer = new CustomFormServer();
                $res_customForm = $customFormServer->addScheduleNum($order_info['custom_order'],$order_info['check_goods_id'],$order_info['custom_id'],$order_id);
               
                
            }
            // ??????????????????
            $order_promotion_details = new VslOrderPromotionDetailsModel();
            $data_promotion_details = [];
            if (!empty($order_info['man_song_full_cut'])) {
                foreach ($order_info['man_song_full_cut'] as $man_song_id => $man_song_info) {
                    $sysAddonsSer = new AddonsSer();
                    //????????????????????????????????????????????????
                    $is_mansong = [];
                    if ($man_song_info['coupon_type_id'] && getAddons('coupontype', $this->website_id)) {/*?????????*/
                        $couponRes = $couponServer->getCouponTypeData(['coupon_type_id' => $man_song_info['coupon_type_id']], 'coupon_name');
                        $coupon_addons_logo = $sysAddonsSer->getAddonsDataByName('coupontype', 'logo')['logo'];
                        if ($couponRes) {
                            $is_mansong['gift_coupon'] = [
                                'id'   => $man_song_info['coupon_type_id'],
                                'name' => $couponRes['coupon_name'],
                                'pic'  => $coupon_addons_logo,
                            ];
                        }
                    }
                    if ($man_song_info['gift_card_id'] && getAddons('giftvoucher', $this->website_id)){/*?????????*/
                        $giftvM = new VslGiftVoucherModel();
                        $giftVoucherRes = $giftvM->getVoucherDetail(['gift_voucher_id' => $man_song_info['gift_card_id']]);
                        $voucher_addons_logo = $sysAddonsSer->getAddonsDataByName('giftvoucher','logo')['logo'];
                        if ($giftVoucherRes) {
                            $is_mansong['gift_voucher'] = [
                                'id'    => $giftVoucherRes['gift_voucher_id'],
                                'name'  => $giftVoucherRes['giftvoucher_name'],
//                                'pic'   => $giftVoucherRes['gift'] ? $giftVoucherRes['gift']['pic_cover_mid'] : '',
                                'pic'   => $voucher_addons_logo,
                            ];
                        }
                    }
                    if ($man_song_info['gift_id'] && getAddons('gift', $this->website_id)) {/*??????*/
                        $giftM = new VslPromotionGiftModel();
                        $giftRes = $giftM->getInfo(['promotion_gift_id' => $man_song_info['gift_id']]);
                        if ($giftRes) {
                            $picture = new AlbumPictureModel();
                            $pic = $picture->getInfo(['pic_id' => $giftRes['picture']],'pic_cover_mid')['pic_cover_mid'] ?: '';
                            $is_mansong['gift'] = [
                                'id'    => $giftRes['promotion_gift_id'],
                                'name'  => $giftRes['gift_name'],
                                'price' => $giftRes['price'],
                                'pic'   => $pic,
                            ];
                        }
                    }
                    $data_promotion_details[] = [
                        'order_id' => $order_id,
                        'promotion_id' => $man_song_info['rule_id'],
                        'promotion_type_id' => 1,
                        'promotion_type' => 'MANJIAN',
                        'promotion_name' => '???????????????',
                        'promotion_condition' => '???' . $man_song_info['price'] . '?????????' . $man_song_info['discount'],
                        'discount_money' => $man_song_info['discount'],
                        'used_time' => time(),
                        'gift_value' => json_encode($is_mansong, JSON_UNESCAPED_UNICODE)
                    ];
                }
                unset($man_song_info);
            }

            if (!empty($order_info['coupon'])) {
                $data_promotion_details[] = [
                    'order_id' => $order_id,
                    'promotion_id' => $order_info['coupon']['coupon_id'],
                    'promotion_type_id' => 3,
                    'promotion_type' => 'COUPON',
                    'promotion_name' => '?????????',
                    'promotion_condition' => ($order_info['coupon']['coupon_genre'] != 3) ?
                        '???' . $order_info['coupon']['price'] . '?????????' . $order_info['coupon']['money'] :
                        '???' . $order_info['coupon']['price'] . '?????????' . $order_info['coupon']['coupon_discount'] . '???',
                    'discount_money' => $order_info['coupon']['discount'],
                    'used_time' => time()
                ];
            }
            if (!empty($data_promotion_details)) {
                $order_promotion_details->saveAll($data_promotion_details);
            }
            $order_goods_promotion_details = new VslOrderGoodsPromotionDetailsModel();
            $promotion_details = [];
            if (!empty($order_info['sku_info'])) {
                //$order_goods_promotion_details = new VslOrderGoodsPromotionDetailsModel();
                foreach ($order_info['sku_info'] as $key => $sku_info) {
                    // ???????????????????????????????????? ??????????????????????????????????????????
                    if (!empty($sku_info['promotion_id'])) {
                        $promotion_details[] = array(
                            'order_id' => $order_id,
                            'promotion_id' => $sku_info['promotion_id'],
                            'sku_id' => $sku_info['sku_id'],
                            'promotion_type' => 'MANJIAN',
                            'discount_money' => round($sku_info['full_cut_sku_percent'] * $sku_info['full_cut_sku_amount'], 2),
                            'used_time' => time()
                        );
                    }

                    // ???????????????????????????????????????????????????
                    if (!empty($sku_info['coupon_id'])) {
                        $promotion_details[] = array(
                            'order_id' => $order_id,
                            'promotion_id' => $sku_info['coupon_id'],
                            'sku_id' => $sku_info['sku_id'],
                            'promotion_type' => 'COUPON',
                            'discount_money' => $sku_info['coupon_sku_percent_amount'],
                            'used_time' => time()
                        );
                    }

                    // ???????????????????????????
                    if (!empty($sku_info['discount_id'])) {
                        $promotion_details[] = array(
                            'order_id' => $order_id,
                            'promotion_id' => $sku_info['discount_id'],
                            'sku_id' => $sku_info['sku_id'],
                            'promotion_type' => 'DISCOUNT',
                            'discount_money' => ($sku_info['member_price'] - $sku_info['discount_price']) * $sku_info['num'],
                            'used_time' => time()
                        );
                    }

                    //?????????????????????????????????????????????????????????sku?????????
                    if (!empty($sku_info['seckill_id'])) {
                        $promotion_details[] = array(
                            'order_id' => $order_id,
                            'promotion_id' => $sku_info['seckill_id'],
                            'sku_id' => $sku_info['sku_id'],
                            'promotion_type' => 'SECKILL',
                            'discount_money' => $sku_info['price'],
                            'used_time' => time()
                        );
                        //????????????????????????????????????sku?????????
                        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
                        $order_sku_data['activity_id'] = $sku_info['seckill_id'];
                        $order_sku_data['uid'] = $order_info['buyer_id'];
                        $order_sku_data['goods_id'] = $sku_info['goods_id'];
                        $order_sku_data['sku_id'] = $sku_info['sku_id'];
                        //1?????????
                        $order_sku_data['buy_type'] = 1;
                        $order_sku_data['website_id'] = $order_info['website_id'];
                        //????????????
                        $order_sku_data['create_time'] = time();
                        //???????????????????????????????????????
                        $activity_os_info = $this->getActivityOrderInfo($order_info['buyer_id'], $sku_info['sku_id'], $order_info['website_id'], 1, $sku_info['seckill_id']);
                        if ($activity_os_info) {
                            $sku_info['num'] = $sku_info['num'] + $activity_os_info['num'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $activity_order_sku_record_mdl->save($order_sku_data, ['uid' => $order_info['buyer_id'], 'sku_id' => $sku_info['sku_id'], 'website_id' => $order_info['website_id']]);
                        } else {
                            $order_sku_data['num'] = $sku_info['num'];
                            $activity_order_sku_record_mdl->save($order_sku_data);
                        }
                    }
                    if ($order_info['luckyspell_id']) {
                        //??????????????????????????????????????????sku?????????
                        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
                        //???????????????????????????????????????
                        $activity_os_info = $this->getActivityOrderInfo($order_info['buyer_id'], $sku_info['sku_id'], $order_info['website_id'], 6, $order_info['luckyspell_id']);
                        if ($activity_os_info) {
                            $sku_info['num'] = $sku_info['num'] + $activity_os_info['num'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $activity_order_sku_record_mdl->save($order_sku_data, ['order_sku_record_id' => $activity_os_info['order_sku_record_id']]);
                        } else {
                            $order_sku_data['activity_id'] = $order_info['luckyspell_id'];
                            $order_sku_data['uid'] = $order_info['buyer_id'];
                            //2?????????
                            $order_sku_data['buy_type'] = 6;
                            $order_sku_data['create_time'] = time();
                            $order_sku_data['goods_id'] = $sku_info['goods_id'];
                            $order_sku_data['sku_id'] = $sku_info['sku_id'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $order_sku_data['website_id'] = $order_info['website_id'];
                            $activity_order_sku_record_mdl->save($order_sku_data);
                        }
                    }
                    if ($order_info['group_id']) {
                        //????????????????????????????????????sku?????????
                        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
                        //???????????????????????????????????????
                        $activity_os_info = $this->getActivityOrderInfo($order_info['buyer_id'], $sku_info['sku_id'], $order_info['website_id'], 2, $order_info['group_id']);
                        if ($activity_os_info) {
                            $sku_info['num'] = $sku_info['num'] + $activity_os_info['num'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $activity_order_sku_record_mdl->save($order_sku_data, ['order_sku_record_id' => $activity_os_info['order_sku_record_id']]);
                        } else {
                            $order_sku_data['activity_id'] = $order_info['group_id'];
                            $order_sku_data['uid'] = $order_info['buyer_id'];
                            //2?????????
                            $order_sku_data['buy_type'] = 2;
                            $order_sku_data['create_time'] = time();
                            $order_sku_data['goods_id'] = $sku_info['goods_id'];
                            $order_sku_data['sku_id'] = $sku_info['sku_id'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $order_sku_data['website_id'] = $order_info['website_id'];
                            $activity_order_sku_record_mdl->save($order_sku_data);
                        }
                    }
                    //??????
                    if ($sku_info['presell_id']) {
                        //????????????????????????????????????sku?????????
                        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
                        //???????????????????????????????????????
                        $activity_os_info = $this->getActivityOrderInfo($order_info['buyer_id'], $sku_info['sku_id'], $order_info['website_id'], 4, $sku_info['presell_id']);
                        if ($activity_os_info) {
                            $sku_info['num'] = $sku_info['num'] + $activity_os_info['num'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $activity_order_sku_record_mdl->save($order_sku_data, ['order_sku_record_id' => $activity_os_info['order_sku_record_id']]);
                        } else {
                            $order_sku_data['activity_id'] = $sku_info['presell_id'];
                            $order_sku_data['uid'] = $order_info['buyer_id'];
                            //4?????????
                            $order_sku_data['buy_type'] = 4;
                            $order_sku_data['create_time'] = time();
                            $order_sku_data['goods_id'] = $sku_info['goods_id'];
                            $order_sku_data['sku_id'] = $sku_info['sku_id'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $order_sku_data['website_id'] = $order_info['website_id'];
                            $activity_order_sku_record_mdl->save($order_sku_data);
                        }
                    }
                    //??????
                    if ($sku_info['bargain_id']) {
                        //????????????????????????????????????sku?????????
                        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
                        //???????????????????????????????????????
                        $activity_os_info = $this->getActivityOrderInfo($order_info['buyer_id'], $sku_info['sku_id'], $order_info['website_id'], 3, $sku_info['bargain_id']);
                        if ($activity_os_info) {
                            $sku_info['num'] = $sku_info['num'] + $activity_os_info['num'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $activity_order_sku_record_mdl->save($order_sku_data, ['order_sku_record_id' => $activity_os_info['order_sku_record_id']]);
                        } else {
                            $order_sku_data['activity_id'] = $sku_info['bargain_id'];
                            $order_sku_data['uid'] = $order_info['buyer_id'];
                            //3?????????
                            $order_sku_data['buy_type'] = 3;
                            $order_sku_data['create_time'] = time();
                            $order_sku_data['goods_id'] = $sku_info['goods_id'];
                            $order_sku_data['sku_id'] = $sku_info['sku_id'];
                            $order_sku_data['num'] = $sku_info['num'];
                            $order_sku_data['website_id'] = $order_info['website_id'];
                            $activity_order_sku_record_mdl->save($order_sku_data);
                        }
                    }
                }
                unset($sku_info);
                if (!empty($promotion_details)) {
                    $order_goods_promotion_details->saveAll($promotion_details);
                }
            }
            // ???????????????
            if ($order_info['coupon']['coupon_id'] > 0 && getAddons('coupontype', $this->website_id)) {
                $return_val = $couponServer->useCoupon($order_info['coupon']['coupon_id'], $order_id);
                if ($return_val <= 0) {
                    $this->order->rollback();
                    return $return_val;
                }
            }

            /*// ????????????
            if ($order_info['point'] > 0) {
                $return_val_point = $account_flow->addMemberAccountData(1, $this->uid, 0, $order['point'], 1, $order_id, '????????????');
                if ($return_val_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }*/
            //????????????????????? thresholdtype_point
            if ($order_info['thresholdtype_point'] > 0) {
                $thresholdtype_point = $account_flow->addMemberAccountData(1, $order_info['buyer_id'], 0, -$order['thresholdtype_point'], 31, $order_id, '??????????????????????????????????????????');
                if ($thresholdtype_point < 0) {
                    debugFile($thresholdtype_point, 'addOrderGoodsNew-3-5', 1111112);
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }
            //????????????
            if ($order_info['deduction_point'] > 0) {
                $return_val_point = $account_flow->addMemberAccountData(1, $order_info['buyer_id'], 0, -$order['deduction_point'], 31, $order_id, '???????????????????????????');
                if ($return_val_point < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_POINT;
                }
            }

            if ($order_info['user_money'] > 0) {
                $return_val_user_money = $account_flow->addMemberAccountData(2, $order_info['buyer_id'], 0, $order_info['user_money'], 1, $order_id, '????????????');
                if ($return_val_user_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_USER_MONEY;
                }
            }
            if ($order_info['user_platform_money'] > 0) {
                $return_val_platform_money = $account_flow->addMemberAccountData(2, $order_info['buyer_id'], 0, $order_info['user_platform_money'], 1, $order_id, '???????????????????????????');
                if ($return_val_platform_money < 0) {
                    $this->order->rollback();
                    return ORDER_CREATE_LOW_PLATFORM_MONEY;
                }
            }
            $this->addOrderAction($order_id, $order_info['buyer_id'], '????????????');
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            debugFile($e->getMessage(), 'sku_record_arr-9', 1111112);
           
            recordErrorLog($e);
            $this->order->rollback();
//            return ORDER_CREATE_FAIL;
            return $e->getMessage();
        }
    }

    /**
     * ????????????(??????)
     * @param array $order_info
     * @return int|mixed
     */
    public function orderCreateReceive(array $order_info)
    {
        $this->order->startTrans();
        try {
            $data_order = array(
                'custom_order' => $order_info['custom_order'],
                'order_no' => $order_info['order_no'],
                'out_trade_no' => $order_info['out_trade_no'],
                'order_sn' => $order_info['order_sn'],
                'order_from' => $order_info['order_from'],
                'buyer_id' => $order_info['buyer_id'],
                'user_name' => $order_info['nick_name'],
                'buyer_ip' => $order_info['ip'],
                'buyer_message' => $order_info['leave_message'],
                'buyer_invoice' => $order_info['buyer_invoice'] ?: '',
                'shipping_time' => $order_info['shipping_time'] ?: 0, // datetime NOT NULL COMMENT '????????????????????????',
                'receiver_mobile' => $order_info['receiver_mobile'], // varchar(11) NOT NULL DEFAULT '' COMMENT '????????????????????????',
                'receiver_province' => $order_info['receiver_province'], // int(11) NOT NULL COMMENT '??????????????????',
                'receiver_city' => $order_info['receiver_city'], // int(11) NOT NULL COMMENT '?????????????????????',
                'receiver_district' => $order_info['receiver_district'], // int(11) NOT NULL COMMENT '?????????????????????',
                'receiver_address' => $order_info['receiver_address'], // varchar(255) NOT NULL DEFAULT '' COMMENT '?????????????????????',
                'receiver_zip' => $order_info['receiver_zip'], // varchar(6) NOT NULL DEFAULT '' COMMENT '???????????????',
                'receiver_name' => $order_info['receiver_name'], // varchar(50) NOT NULL DEFAULT '' COMMENT '???????????????',
                'shop_id' => $order_info['shop_id'], // int(11) NOT NULL COMMENT '????????????id',
                'shop_name' => $order_info['shop_name'], // varchar(100) NOT NULL DEFAULT '' COMMENT '??????????????????',
                'point' => 0, // int(11) NOT NULL COMMENT '??????????????????',
                'coupon_id' => 0, // int(11) NOT NULL COMMENT '???????????????id',
                'give_point' => 0, // int(11) NOT NULL COMMENT '??????????????????',, // int(11) NOT NULL COMMENT '??????????????????',
                'create_time' => $order_info['create_time'],
                'website_id' => $order_info['website_id'],
                'shipping_company_id' => 0,
                'payment_type' => 0,
                'shipping_type' => $order_info['shipping_type'],
                'order_type' => $order_info['order_type'],
                'order_status' => 1, // tinyint(4) NOT NULL COMMENT '????????????',
                'pay_status' => 2, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'shipping_status' => 0, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'review_status' => 0, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'feedback_status' => 0, // tinyint(4) NOT NULL COMMENT '??????????????????',
                'give_point_type' => 0,
                'shipping_money' => 0, // decimal(10, 2) NOT NULL COMMENT '????????????',
                'pay_money' => 0, // decimal(10, 2) NOT NULL COMMENT '??????????????????',
                'refund_money' => 0, // decimal(10, 2) NOT NULL COMMENT '??????????????????',
                'goods_money' => $order_info['shop_total_amount'], // decimal(19, 2) NOT NULL COMMENT '????????????',
                'tax_money' => $order_info['tax_money'], // ??????
                'order_money' => 0, // decimal(10, 2) NOT NULL COMMENT '????????????',
                'member_money' => 0, // decimal(10,2)???????????????,
                'buy_type' => $order_info['buy_type'] ?: 0,
                'card_store_id' => $order_info['card_store_id'] ?: 0
            );
            $order = new VslOrderModel();
            $order->save($data_order);
            $order_id = $order->order_id;
            // ???????????????
            if ($order_info['gift_id'] == 0) {
                $order_goods = new OrderGoods();
                $res_order_goods = $order_goods->addOrderGoodsNew($order_id, $order_info['sku_info'], 0);
                if (!($res_order_goods > 0)) {
                    $this->order->rollback();
                    return $res_order_goods;
                }
            }
            $this->addOrderAction($order_id, $this->uid, '????????????');
            $this->order->commit();
            return $order_id;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /*
     * ???????????????????????????????????????sku??????
     * **/
    public function getActivityOrderSkuNum($uid, $sku_id, $website_id, $type, $activity_id)
    {
        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
        $activty_condition['uid'] = $uid;
        $activty_condition['sku_id'] = $sku_id;
        $activty_condition['website_id'] = $website_id;
        $activty_condition['buy_type'] = $type;
        $activty_condition['activity_id'] = $activity_id;
        $activity_og_info = $activity_order_sku_record_mdl->where($activty_condition)->sum('num');
        return $activity_og_info;
    }

    /*
     * ????????????????????????????????????
     * **/
    public function getActivityOrderInfo($uid, $goods_id, $website_id, $type, $activity_id)
    {
        $activity_order_sku_record_mdl = new VslActivityOrderSkuRecordModel();
        $activty_condition['uid'] = $uid;
        $activty_condition['sku_id'] = $goods_id;
        $activty_condition['website_id'] = $website_id;
        $activty_condition['buy_type'] = $type;
        $activty_condition['activity_id'] = $activity_id;
        $activity_og_info = $activity_order_sku_record_mdl->getInfo($activty_condition, 'num, order_sku_record_id');
        $activity_og_info = objToArr($activity_og_info);
        if ($activity_og_info['order_sku_record_id'])
            return $activity_og_info;
        else
            return '';
    }

    /**
     * ????????????
     *
     * @param unknown $order_pay_no
     * @param unknown $pay_type (10:????????????)
     * @param unknown $status
     *            0:?????????????????? 1?????????????????????
     * @param string $seller_memo 
     * @return Exception
     */
    public function OrderPay($order_pay_no, $pay_type, $status,$joinpay=0)
    {
        $this->order->startTrans();
        try {
            // ??????????????????
//            $this->order->where([
//                'out_trade_no' => $order_pay_no
//            ])->select();

            // ??????????????????
            // ?????????????????????
            $order_id_array = $this->order->where([
                'out_trade_no' => $order_pay_no
            ])->whereOr([
                'out_trade_no_presell' => $order_pay_no
            ])->column('order_id');

            foreach ($order_id_array as $k => $order_id) {
                $order = new VslOrderModel();
                $order_info = $this->order->getInfo([
                    'order_id' => $order_id
                ], '*');
                
                if ($order_info['order_type'] == 5) {
                    if (!getAddons('groupshopping', $order_info['website_id'])) {
                        $this->order->rollback();
                        return false;
                    } else {
                        $is_offline_check = 0;
                        if($order_info['pay_voucher']) {
                            $is_offline_check = 1;
                        }
                        $group_server = new GroupShopping();
                        $checkGroup = $group_server->checkGroupIsCanByOrder($order_info['out_trade_no'], $is_offline_check);
                        if ($checkGroup < 0) {
                            $this->order->rollback();
                            return $checkGroup;
                        }
                    }
                }

                //???????????????????????????????????????????????????????????????????????????money_type?????????1
                if ($order_info['money_type'] == 0 && $order_info['presell_id'] != 0) {
                    if ($order_info['payment_type'] == 16 || $order_info['payment_type'] == 17 || $order_info['payment_type'] == 10) {
                        $data_money_type['order_status'] = 0;
                        $data_money_type['pay_status'] = 0;
                    }
                    $data_money_type['money_type'] = 1;
                    $order->save($data_money_type, ['order_id' => $order_id]);
                    //?????????????????????order???
                    $data = array(
                        'payment_type' => $pay_type,
                        'joinpay' => $joinpay,
                        'pay_time' => time(),
                    );
                    //?????????0???
                    if ($order_info['final_money'] == 0) {
                        $data['pay_status'] = 2;
                        $data['money_type'] = 2;
                        $data['order_status'] = 1;
                    }
                    $bool = $order->save($data, [
                        'order_id' => $order_id
                    ]);
                    if ($pay_type == 10) {
                        // ????????????
                        $this->addOrderAction($order_id, $this->uid, '????????????');
                    } else {
                        // ?????????????????????ID
                        $this->addOrderAction($order_id, $order_info['buyer_id'], '????????????');
                    }
                    //??????????????????????????????????????????
                    $presell = new  VslPresellModel();
                    $presell_info = $presell->getInfo(['id' => $order_info['presell_id']], 'pay_end_time');
                    $pay_end_time = $presell_info['pay_end_time'];
                    $delay_time = $pay_end_time - time();
                    //?????????????????????????????????????????????
                    $url = config('rabbit_interface_url.url');
                    $back_url = $url.'/rabbitTask/ordersClose';
                    $this->seconedPayEndPresell($order_id, $delay_time, $back_url);
                    $this->order->commit();
                    return 1;
                }
                if ($order_info['money_type'] == 1 && $order_info['presell_id'] != 0) {
                    $data = array(
                        'payment_type_presell' => $pay_type,
                        'joinpay' => $joinpay,
                        'money_type' => 2, //????????????????????????????????????
                        'pay_status' => 2,
                        'pay_time' => time(),
                        'order_status' => 1,
                        'order_money' => $order_info['pay_money'] + $order_info['final_money'] - $order_info['invoice_tax'],//?????????????????????????????????????????? ????????????;?????????????????????
                    );
                    //?????????????????????????????????????????????????????????????????????????????????????????????????????????????????????
                    $order_goods_mdl = new VslOrderGoodsModel();
                    $order_goods_info = $order_goods_mdl->getInfo(['order_id' => $order_id],'channel_stock,sku_id,discount_price');
                    if($order_goods_info['channel_stock']) {
                        $channel_money = 0;
                        $sku_modle = new VslGoodsSkuModel();
                        $sku_info  = $sku_modle->getInfo(['sku_id' => $order_goods_info['sku_id']],'price,market_price');
                        //????????????????????????????????????????????????
                        $config = new AddonsConfig();
                        $value = $config->getAddonsConfig('channel', $order_info['website_id'], 0, 1);
                        if($value['settle_type'] == 1) {
                            //????????????????????? price
                            $channel_money = $sku_info['price'] * $order_goods_info['channel_stock'];
                        }elseif ($value['settle_type'] == 2) {
                            //????????????????????? market_price
                            $channel_money = $sku_info['market_price'] * $order_goods_info['channel_stock'];
                        }elseif ($value['settle_type'] == 3) {
                            //???????????????????????? discount_price
                            $channel_money = $order_goods_info['discount_price'] * $order_goods_info['channel_stock'];
                        }
                        $data['channel_money'] = $channel_money;
                    }
                    
                    $order->save($data, [
                        'order_id' => $order_id
                    ]);
                }
                //??????order_id???sku_id
                $order_goods_info = $this->order->alias('o')
                    //->where(['o.order_id'=>$order_id,'o.buy_type'=>2,'og.channel_info'=>['neq',0]])//????????????????????????????????????????????????
                    ->where(['o.order_id' => $order_id])
                    ->join('vsl_order_goods og', 'og.order_id=o.order_id', 'LEFT')
                    ->select();
                if ($pay_type == 10) {
                    // ????????????
                    $this->addOrderAction($order_id, $this->uid, '????????????');
                } else {
                    // ?????????????????????ID
                    $this->addOrderAction($order_id, $order_info['buyer_id'], '????????????');
                }
                // ????????????????????????
                $account = new MemberAccount();
                $account->addMmemberConsum(0, $order_info['buyer_id'], $order_info['pay_money']);
                if ($order_info['order_type'] != 7) {//??????????????? ?????????????????????????????????????????????
                    // ??????????????????
                    $data = array(
                        'payment_type' => $pay_type,
                        'joinpay' => $joinpay,
                        'pay_status' => 2,
                        'pay_time' => time(),
                        'order_status' => 1
                    ); // ???????????????????????????
                    $bool = $order->save($data, [
                        'order_id' => $order_id
                    ]);
                }

                if ($order_info['group_id']) {
                    $group_server = new GroupShopping();
                    $group_server->createGroupRecord($order_id);
                }

                //??????????????????????????????????????????
                $this->calculateOrderMansong($order_id);
                //?????????????????????????????????????????????????????????
                if ($order_info['bargain_id'] && getAddons('bargain', $order_info['website_id'], $order_info['shop_id'])) {
                    $bargain_record_mdl = new VslBargainRecordModel();
                    $bargain_condition['bargain_id'] = $order_info['bargain_id'];
                    $bargain_condition['uid'] = $order_info['buyer_id'];
                    $change_fields['bargain_status'] = 2;//???????????????2
                    $change_fields['order_id'] = $order_id;//??????id
                    $bargain_record_mdl->save($change_fields, $bargain_condition);
                }
                
                /* ????????????????????????????????????????????????????????????
                //???????????????????????????????????????????????????/?????????
                if ($order_info['card_store_id']>0 && getAddons('store', $order_info['website_id'], $order_info['shop_id'])) {
                    //???????????????
                    $member_card = new MemberCard();
                    $rs = $member_card->saveData($order_id);
                    if ($rs) { 
                        // ??????????????????
                        $order = new VslOrderModel();
                        $order->save(['order_status' => 4, 'card_ids' => $rs], ['order_id' => $order_id]);
                        $ServiceOrder = new ServiceOrder();
                        $ServiceOrder->orderComplete($order_id, 0, 1);
                    }
                }
                //???????????????????????????????????????????????????????????????
                if(count($order_goods_info) == 1) {
                    if($order_goods_info[0]['goods_type'] == 4) {
                        $order = new VslOrderModel();
                        $order->save(['order_status' => 4], ['order_id' => $order_id]);
                        $ServiceOrder = new ServiceOrder();
                        $retval = $ServiceOrder->orderComplete($order_id, 0, 1);
                        if($retval == 1) {
                            //?????????????????????,?????????????????????????????????????????????????????????????????????????????????????????????????????????
                            $order_goods_model = new VslOrderGoodsModel();
                            $order_goods_condition= [
                                'goods_id' => $order_goods_info[0]['goods_id'],
                                'goods_type'=>$order_goods_info[0]['goods_type'],
                                'buyer_id'=>$order_goods_info[0]['buyer_id'],
                            ];
                            $order_ids = $order_goods_model->getQuery($order_goods_condition,'order_id','');
                            if($order_ids) {
                                foreach ($order_ids as $key => $val) {
                                    $order_status = $order->Query(['order_id' => $val['order_id']],'order_status')[0];
                                    if($order_status == 0) {
                                        //??????????????????
                                        $ServiceOrder = new ServiceOrder();
                                        $ServiceOrder->orderClose($val['order_id'],1);
                                    }
                                }
                                unset($val);
                            }
                        }
                    }
                }
                //????????????????????????????????????????????????????????????
                if (count($order_goods_info) == 1) {
                    if ($order_goods_info[0]['goods_type'] == 5) {
                        if (getAddons('electroncard', $order_info['website_id'])) {
                            $goods_mdl = new VslGoodsModel();
                            $goods_info = $goods_mdl->getinfo(['goods_id' => $order_goods_info[0]['goods_id']], 'electroncard_base_id');
                            $electroncard_server = new Electroncard();
                            $electroncard_data_id = $electroncard_server->randomElectroncardData($goods_info['electroncard_base_id'], $order_goods_info[0]['num']);

                            //????????????????????????????????????,???????????????????????????
                            $res = $this->order->save(['order_status' => 4, 'electroncard_data_id' => $electroncard_data_id], ['order_id' => $order_id]);
                            if ($res) {
                                $ServiceOrder = new ServiceOrder();
                                $ServiceOrder->orderComplete($order_id, 0, 1);
                                //???????????????????????????
                                $electroncard_server->syncElectroncardStockToGoods($goods_info['electroncard_base_id']);
                                //????????????
                                $electroncard_server->pushMessage($order_info['buyer_id'], $electroncard_data_id, $order_goods_info[0]['goods_name']);
                            }
                        }
                    }
                }

                //??????????????????????????????,?????????????????????????????????????????????????????????
                if (!$order_info['group_id']) {
                    if (count($order_goods_info) == 1) {
                        if ($order_goods_info[0]['goods_type'] == 3) {
                            $ServiceOrder = new ServiceOrder();
                            $order_service = new OrderService();
                            $goods_mdl = new VslGoodsModel();
                            $delivery_type = $goods_mdl->Query(['goods_id' => $order_goods_info[0]['goods_id']], 'delivery_type')[0];
                            if ($delivery_type && $delivery_type != 4) {
                                if ($delivery_type == 1) {
                                    //????????????
                                    $order_service->orderGoodsDelivery($order_id, $order_goods_info[0]['order_goods_id']);
                                } elseif ($delivery_type == 2) {
                                    //???????????????????????????
                                    $order_service->orderGoodsDelivery($order_id, $order_goods_info[0]['order_goods_id']);
                                    $order_service->OrderTakeDelivery($order_id);
                                } elseif ($delivery_type == 3) {
                                    //???????????????????????????
                                    $order_service->orderGoodsDelivery($order_id, $order_goods_info[0]['order_goods_id']);
                                    $order_service->OrderTakeDelivery($order_id);
                                    $ServiceOrder->orderComplete($order_id, 0, 1);
                                }
                            }
                        }
                    }
                }
                */
                $is_liveshopping = getAddons('liveshopping', $order_info['website_id']);
                //???????????????
                foreach ($order_goods_info as $k1 => $og) {
                    $goods_calculate = new GoodsCalculate();
                    //if($og['buy_type'] == 2 && $og['channel_info'] != 0 && getAddons('channel', $this->website_id)){
//                    if (getAddons('channel', $order_info['website_id']) && $og['channel_info'] != 0 &&  $og['channel_stock']) {
                    if (getAddons('channel', $order_info['website_id']) && $og['channel_info'] != 0) {
                        $channel = new Channel();
                        //????????????channel_order_sku_record
                        $sku_record_mdl = new VslChannelOrderSkuRecordModel();
                        $sku_record_arr['uid'] = $og['buyer_id'];
                        $sku_record_arr['order_id'] = $order_id;
                        $sku_record_arr['order_no'] = $og['order_no'];
                        //????????????????????????
                        $condition_channel['c.website_id'] = $order_info['website_id'];
                        $condition_channel['c.uid'] = $og['buyer_id'];
                        $channel_info = $channel->getMyChannelInfo($condition_channel);
                        $stock_list = $channel->getChannelSkuStore($og['sku_id'], $og['channel_info']);
                        //var_dump(objToArr($stock_list));exit;
                        $sku_record_arr['my_channel_id'] = $channel_info['channel_id'] ?: 0;
                        $sku_record_arr['channel_info'] = $og['channel_info'];
                        $sku_record_arr['goods_id'] = $og['goods_id'];
                        $sku_record_arr['sku_id'] = $og['sku_id'];
                        $sku_record_arr['total_num'] = $og['channel_stock'];
                        $sku_record_arr['num'] = $og['channel_stock'];
                        $sku_record_arr['price'] = $og['price'];
                        $sku_record_arr['shipping_fee'] = $og['shipping_fee'] ?: 0;
                        $sku_record_arr['channel_purchase_discount'] = $channel_info['purchase_discount'] ?: 0;
                        $goods_sku_model1 = new VslGoodsSkuModel();
                        $goods_sku_info = $goods_sku_model1->getInfo([
                            'sku_id' => $og['sku_id'],
                        ], '*');
                        $buy_type = ($og['buy_type'] == 2) ? 2 : 3;
                        if(!$goods_sku_info){
                            if ($buy_type == 2) {//??????
                                $goods_sku_info['price'] = 0;
                            }
                        }
                        $sku_record_arr['platform_price'] = $goods_sku_info['price'];
                        //?????????????????????sku?????????
                        $sku_record_arr['remain_num'] = $stock_list['stock'];
                        if ($buy_type == 3) {//?????????????????????????????????????????????
                            //???????????????????????????????????? ??????id:num:bili
                            $batch_ratio_record = $channel->getPurchaseBatchRatio($og['channel_info'], $og['channel_stock'], $og['sku_id']);//p1:?????????  p2:????????????
                            $sku_record_arr['batch_ratio_record'] = $batch_ratio_record ?: '';
                        }
                        $sku_record_arr['buy_type'] = $buy_type;//??????
                        $sku_record_arr['website_id'] = $order_info['website_id'];
                        $sku_record_arr['create_time'] = time();
                        
                        $is_record = $sku_record_mdl->where(['order_no' => $og['order_no']])->find();
                        if (!$is_record || $buy_type == 2) {
                            $id = $sku_record_mdl->save($sku_record_arr);
                        }
                        //????????????
                        $goods_calculate = new GoodsCalculate();
                        if ($og['channel_info'] && $og['channel_stock']) {
                            $goods_calculate->addChannelGoodsSales($og['goods_id'], $og['channel_stock'], $og['channel_info']);
                            //??????????????????sku?????????
                            $goods_calculate->addChannelSkuSales($og['sku_id'], $og['channel_stock'], $og['channel_info']);
                        }
                    }
                    if ($og['seckill_id'] != 0 && getAddons('seckill', $order_info['website_id'])) {
                        $goods_calculate->addSeckillSkuSales($og['seckill_id'], $og['sku_id'], $og['num']);
                    } elseif ($og['bargain_id'] != 0 && getAddons('bargain', $order_info['website_id'])) {
                        $goods_calculate->addBargainSkuSales($og['bargain_id'], $og['goods_id'], $og['num']);
                    } elseif($og['presell_id'] != 0 && getAddons('presell', $order_info['website_id'])){
                        $goods_calculate->addPresellSkuSales($og['presell_id'], $og['goods_id'], $og['num']);
                    } else {
                        if ($og['channel_stock'] == 0) {
                            $goods_calculate->addGoodsSales($og['goods_id'], $og['num']);
                        }else{
                            $goods_calculate->addGoodsSales($og['goods_id'], $og['num'] - $og['channel_stock']);
                        }
                    }
                    //???????????????????????????
                    if($is_liveshopping){
                        if($og['anchor_id'] && $og['play_time']){
                            //????????????
                            $pay_money = $og['num'] * $og['actual_price'] + $og['shipping_fee'];
                            $live_record = new LiveRecordModel();
                            $live_cond = [
                              'anchor_id' => $og['anchor_id'],
                              'play_time' => $og['play_time']
                            ];
                            $live_record_obj = $live_record->where($live_cond)->find();
                            if($live_record_obj){
                                $live_record_obj->pay_money = $live_record_obj->pay_money + $pay_money;
                                $live_record_obj->save();
                                $is_buy_cond['o.buyer_id'] = $order_info['buyer_id'];
                                $is_buy_cond['o.pay_status'] = 2;
                                $is_buy_cond['og.play_time'] = $og['play_time'];
                                $is_buy_cond['og.anchor_id'] = $og['anchor_id'];
                                $count = $this->order->alias('o')->join('vsl_order_goods og', 'o.order_id = og.order_id', 'left')->where($is_buy_cond)->count();
                                if($count <= 1){
                                    $res = $live_record->where($live_cond)->setInc('pay_member');
                                }
                            }
                        }
                    }
                }
                unset($og);
                }
                //?????????????????????????????????????????????????????????
                if($order_info['store_id'] || $order_info['card_store_id']) {
                    $store_id = $order_info['store_id'] ?: $order_info['card_store_id'];
                    $store_server = new Store();
                    $store_server ->orderMessagePushToClerk($order_info['order_no'], $order_info['order_money'], 1, $store_id, $order_info['shop_id'], $order_info['website_id']);
                }
                if ($status == 1) {
                    $res = $this->orderComplete($order_id);
                    if (!($res > 0)) {
                        $this->order->rollback();
                        return $res;
                    }
                    // ????????????????????????
            }
            unset($order_id);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            debugLog($e->getMessage(), '==>????????????-??????1<==');
            recordErrorLog($e);
            $this->order->rollback();
            Log::write('??????????????????' . $e->getMessage());
            return $e->getMessage();
        }
    }
    /**
     * ?????????????????????????????????
     * @param $order_id
     * @param $end_time
     */
    public function seconedPayEndPresell($order_id, $delay_time, $back_url)
    {
        if(config('is_high_powered')){
            $config['delay_exchange_name'] = config('rabbit_delay_order_close.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_order_close.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_order_close.delay_routing_key');
            //?????????????????????????????????????????????
            $delay_time = $delay_time * 1000;//??????
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'order_id' => $order_id,
                'order_type' => 'second_presell_pay'
            ];
            $data = json_encode($data);
            $custom_type = 'activity_promotion';//??????
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    /**
     * ????????????????????????
     * order_id int(11) NOT NULL COMMENT '??????id',
     * action varchar(255) NOT NULL DEFAULT '' COMMENT '????????????',
     * uid int(11) NOT NULL DEFAULT 0 COMMENT '?????????id',
     * user_name varchar(50) NOT NULL DEFAULT '' COMMENT '?????????',
     * order_status int(11) NOT NULL COMMENT '???????????????',
     * order_status_text varchar(255) NOT NULL DEFAULT '' COMMENT '??????????????????',
     * action_time datetime NOT NULL COMMENT '????????????',
     * PRIMARY KEY (action_id)
     *
     * @param unknown $order_id
     * @param unknown $uid
     * @param unknown $action_text
     */
    public function addOrderAction($order_id, $uid, $action_text, $is_store = 0)
    {
        $this->order->startTrans();
        try {
            $order_status = $this->order->getInfo([
                'order_id' => $order_id
            ], 'order_status,website_id');
            if ($uid != 0) {
                if($is_store && getAddons('store', $order_status['website_id'])){//????????????,???????????????
                    $user = new \addons\store\model\VslStoreAssistantModel();
                    $user_info = $user->getInfo([
                        'assistant_id' => $uid
                    ], 'assistant_name,assistant_tel');
                    $action_name = $user_info['assistant_name'] ?  : $user_info['assistant_tel'];
                }else{
                    $user = new UserModel();
                    $user_info = $user->getInfo([
                        'uid' => $uid
                    ], 'nick_name,user_name,user_tel');
                    $action_name = $user_info['user_name'] ?  : ($user_info['nick_name'] ? : $user_info['user_tel']);
                }
            } else {
                $action_name = 'system';
            }

            $data_log = array(
                'order_id' => $order_id,
                'action' => $action_text,
                'uid' => $uid,
                'user_name' => $action_name,
                'order_status' => $order_status['order_status'],
                'order_status_text' => $this->getOrderStatusName($order_id),
                'action_time' => time(),
                'website_id' => $order_status['website_id']
            );
            $order_action = new VslOrderActionModel();
            $order_action->save($data_log);
            $this->order->commit();
            return $order_action->action_id;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->order->rollback();
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * ???????????????????????? ??????
     *
     * @param unknown $order_id
     */
    public function getOrderStatusName($order_id)
    {
        $order_status = $this->order->getInfo([
            'order_id' => $order_id
        ], 'order_status');
        $status_array = OrderStatus::getOrderCommonStatus();
        foreach ($status_array as $k => $v) {
            if ($v['status_id'] == $order_status['order_status']) {
                return $v['status_name'];
            }
        }
        unset($v);
        return false;
    }

    /**
     * ????????????id ????????????????????????
     *
     * @param int|string $shop_id
     *
     * @return string $order_no
     */
    public function createOrderNo($shop_id)
    {
        $billno = date('YmdHis') . mt_rand(100000, 999999);
        
        while (1) {
            $order_model = new VslOrderModel();
            if (!getAddons('channel', $this->website_id)) {
                break;
            }
            
            $channel_order_model = new VslChannelOrderModel();
            $count = $order_model->getCount(['order_no' => $billno]);
            $count1 = $channel_order_model->getCount(['order_no' => $billno]);
            
            if ($count <= 0 && $count1 <= 0) {
                break;
            }
            $billno = date('YmdHis') . mt_rand(100000, 999999);
        }
        
        return $billno;
    }

    /**
     * ????????????????????????
     *
     * @param unknown $order_id
     */
    public function createOutTradeNo()
    {
        $pay_no = new UnifyPay();
        return $pay_no->createOutTradeNo();
    }

    /**
     * ???????????????????????????
     *
     * @param unknown $orderid
     */
    public function createNewOutTradeNo($orderid)
    {
        $order = new VslOrderModel();
        $new_no = $this->createOutTradeNo();
        $data = array(
            'out_trade_no' => $new_no
        );
        $retval = $order->save($data, [
            'order_id' => $orderid
        ]);
        if ($retval) {
            return $new_no;
        } else {
            return '';
        }
    }

    /**
     * ???????????????????????????
     *
     * @param unknown $orderid
     */
    public function createChannelNewOutTradeNo($orderid)
    {
        $order = new VslChannelOrderModel();
        $new_no = $this->createOutTradeNo();
        $new_no = 'QD'.$new_no;
        $data = array(
            'out_trade_no' => $new_no
        );
        $retval = $order->save($data, [
            'order_id' => $orderid
        ]);
        if ($retval) {
            return $new_no;
        } else {
            return '';
        }
    }

    /**
     * ????????????(????????????)(??????????????????)
     *
     * @param unknown $orderid
     */
    public function orderDoDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $order_item = new VslOrderGoodsModel();
            $count = $order_item->getCount([
                'order_id' => $orderid,
                'shipping_status' => 0,
                'refund_status' => array(
                    'ELT',
                    0
                )
            ]);
            if ($count == 0) {
                $data_delivery = array(
                    'shipping_status' => 1,
                    'order_status' => 2,
                    'consign_time' => time()
                );
                $order_model = new VslOrderModel();
                $order_model->save($data_delivery, [
                    'order_id' => $orderid
                ]);
                //??????????????????????????????????????????
                $ror_mdl = new RabbitOrderRecordModel();
                $ror_info = $ror_mdl->getInfo(['order_id' => $orderid]);
                if($ror_info){
                    $order_auto_delivery = $ror_info['order_auto_delivery_time'] ? : 0;
                }else{
                    //??????????????????????????????????????????
                    $config = new \data\service\Config();
                    $shopConfig = $config->getShopConfig(0, $this->website_id);
                    //??????????????????
                    $order_auto_delivery = $shopConfig['order_auto_delivery'] !== '' ? $shopConfig['order_auto_delivery'] : 7;
                }
                $order_auto_delivery = $order_auto_delivery * 24 * 60;//??????
                //???????????????????????????????????????
                $this->rabbitActDelivery($orderid, $order_auto_delivery, $this->website_id);
            }
            $this->addOrderAction($orderid, $this->uid, '????????????');
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            debugLog($e->getMessage(), '==>????????????-??????3<==');
            $this->order->rollback();
            return $e->getMessage();
        }
    }
    /**
     * ????????????????????????
     * @param $order_id
     * @param $order_auto_delivery
     */
    public function rabbitActDelivery($order_id, $order_auto_delivery, $website_id)
    {
        if(config('is_high_powered')){
            $config['delay_exchange_name'] = config('rabbit_delay_order_delivery.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_order_delivery.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_order_delivery.delay_routing_key');
            //?????????????????????????????????????????????
            $delay_time = $order_auto_delivery * 60 * 1000;//??????
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'order_id' => $order_id,
                'website_id' => $website_id
            ];
            $data = json_encode($data);
            $url = config('rabbit_interface_url.url');
            $back_url = $url.'/rabbitTask/ordersDelivery';
            $custom_type = 'order_delivery';//??????
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }

    /**
     * ????????????
     *
     * @param unknown $orderid
     */
    public function OrderTakeDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model = new VslOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);
            //???????????????????????????????????????
            $order_info = $order_model->getInfo(['order_id' => $orderid], 'website_id');
            $website_id = $order_info['website_id'];
            $ror_mdl = new RabbitOrderRecordModel();
            $ror_info = $ror_mdl->getInfo(['order_id' => $orderid]);
            if($ror_info){
                $order_delivery_complete_value = $ror_info['order_complete_time'] ? : 0;
            }else{
                $config = new \data\service\Config();
                $shopConfig = $config->getShopConfig(0, $website_id);
                $order_delivery_complete_value = $shopConfig['order_delivery_complete_time'] !== '' ? $shopConfig['order_delivery_complete_time'] : 7;
            }
            $order_delivery_complete_time = $order_delivery_complete_value * 24 * 60;//??????
            $url = config('rabbit_interface_url.url');
            $back_url1 = $url.'/rabbitTask/ordersComplete';
            //????????????
            $this->rabbitAutoComplete($orderid, $order_delivery_complete_time, $back_url1, $website_id);
            //????????????
            $back_url2 = $url.'/rabbitTask/orderComment';
            $translation_time = $ror_info['order_comment_time'] ? : 0;
            $order_delivery_complete_time2 = $order_delivery_complete_value * 24 * 60 + $translation_time * 24 * 60 + 0.15;//??????
            $this->rabbitAutoComplete($orderid, $order_delivery_complete_time2, $back_url2, $website_id);
            $this->addOrderAction($orderid, $this->uid, '????????????');
            // ??????????????????????????????????????????
            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * ??????????????????
     * @param $order_id
     * @param $order_delivery_complete_time
     */
    public function rabbitAutoComplete($order_id, $order_delivery_complete_time, $back_url, $website_id)
    {
        if(config('is_high_powered')){
            //????????????????????????
            $config['delay_exchange_name'] = config('rabbit_delay_order_complete.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_order_complete.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_order_complete.delay_routing_key');
            //?????????????????????????????????????????????
            $delay_time = $order_delivery_complete_time * 60 * 1000;//??????
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'order_id' => $order_id,
                'website_id' => $website_id
            ];
            $data = json_encode($data);
            $custom_type = 'order_complete';
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    /**
     * ?????????????????????
     *
     * @param unknown $orderid
     */
    public function channellOrderTakeDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model = new VslchannelOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);
            $this->addOrderAction($orderid, $this->uid, '????????????');
            // ??????????????????????????????????????????
//            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * ??????????????????
     *
     * @param unknown $orderid
     */
    public function orderAutoDelivery($orderid)
    {
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time()
            );
            $order_model = new VslOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $orderid
            ]);
            //???????????????????????????????????????
            $order_info = $order_model->getInfo(['order_id' => $orderid], 'website_id');
            $website_id = $order_info['website_id'];
            $ror_mdl = new RabbitOrderRecordModel();
            $ror_info = $ror_mdl->getInfo(['order_id' => $orderid]);
            if($ror_info){
                $order_delivery_complete_value = $ror_info['order_complete_time'] ? : 0;
            }else{
                $config = new \data\service\Config();
                $shopConfig = $config->getShopConfig(0, $website_id);
                $order_delivery_complete_value = $shopConfig['order_delivery_complete_time'] !== '' ? $shopConfig['order_delivery_complete_time'] : 7;
            }
            $order_delivery_complete_time = $order_delivery_complete_value * 24 * 60;//??????
            $url = config('rabbit_interface_url.url');
            $back_url1 = $url.'/rabbitTask/ordersComplete';
            //????????????
            $this->rabbitAutoComplete($orderid, $order_delivery_complete_time, $back_url1, $website_id);
            //????????????
            $back_url2 = $url.'/rabbitTask/orderComment';
            $translation_time = $ror_info['order_comment_time'] ? : 0;
            $order_delivery_complete_time2 = $order_delivery_complete_value * 24 * 60 + $translation_time * 24 * 60 + 0.15;//??????
            $this->rabbitAutoComplete($orderid, $order_delivery_complete_time2, $back_url2, $website_id);
            $this->addOrderAction($orderid, 0, '??????????????????');
            // ??????????????????????????????????????????
            $this->giveGoodsOrderPoint($orderid, 2);
            $this->order->commit();
            //????????????????????????
            runhook('Notify', 'orderCompleteBySms', ['order_id' => $orderid]);
            runhook('Notify', 'emailSend', ['order_id' => $orderid, 'notify_type' => 'user', 'template_code' => 'confirm_order']);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);

            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * ????????????????????????
     *
     * @param int $orderid
     * @param array $order_data
     */
    public function orderComplete($order_id)
    {
        try {
            $data_complete = array(
                'order_status' => 4,
                'finish_time' => time()
            );
            $order_model = new VslOrderModel();
            $res = $order_model->save($data_complete, [
                'order_id' => $order_id,
                'order_status' => 3,
            ]);
            if ($res) {
                //???????????????
//                $goods_calculate = new GoodsCalculate();
                $order_goods_list = $order_model->alias('o')->where(['o.order_id' => $order_id])->join('vsl_order_goods og', 'o.order_id=og.order_id', 'LEFT')->select();
                foreach ($order_goods_list as $k => $og_info) {
                    //??????????????????????????????????????????
//                    if(!$og_info['buy_type'] && $og_info['channel_info'] && getAddons('channel', $this->website_id)){//?????????????????? ??????
//                        $goods_calculate->addChannelGoodsSales($og_info['goods_id'], $og_info['num'], $og_info['channel_info']);
//                        //??????????????????sku?????????
//                        $goods_calculate->addChannelSkuSales($og_info['sku_id'], $og_info['num'], $og_info['channel_info']);
//                        if($og_info['seckill_id'] && getAddons('seckill', $this->website_id, $this->instance_id)){
//                            //???????????????
//                            $goods_calculate->addSeckillSkuSales($og_info['seckill_id'],$og_info['sku_id'], $og_info['num']);
//                        }
//                        //????????????channel_order_sku_record
//                        $channel = new Channel();
//                        $sku_record_mdl = new VslChannelOrderSkuRecordModel();
//                        $sku_record_arr['uid'] = $og_info['buyer_id'];
//                        $sku_record_arr['order_id'] = $order_id;
//                        $sku_record_arr['order_no'] = $og_info['order_no'];
//                        //????????????????????????
//                        $condition_channel['c.website_id'] = $og_info['website_id'];
//                        $condition_channel['c.uid'] = $og_info['buyer_id'];
//                        $channel_info = $channel->getMyChannelInfo($condition_channel);
//                        $stock_list = $channel->getChannelSkuStore($og_info['sku_id'], $og_info['channel_info'],$og_info['website_id']);
////                var_dump(objToArr($stock_list));exit;
//                        //??????????????????????????????
//                        $batch_ratio_record = '';
//                        //???????????????????????????????????? ??????id:num:bili
//                        $batch_ratio_record = $channel->getPurchaseBatchRatio($og_info['channel_info'], $og_info['num'], $og_info['sku_id']);//p1:?????????  p2:????????????
//                        $sku_record_arr['batch_ratio_record'] = $batch_ratio_record?:'';
//                        $sku_record_arr['my_channel_id'] = $channel_info['channel_id']?:0;
//                        $sku_record_arr['channel_info'] = $og_info['channel_info'];
//                        $sku_record_arr['goods_id'] = $og_info['goods_id'];
//                        $sku_record_arr['sku_id'] = $og_info['sku_id'];
//                        $sku_record_arr['num'] = $og_info['num'];
//                        //??????????????????
//                        $goods_sku = new VslGoodsSkuModel();
//                        $platform_price = $goods_sku->getInfo(['sku_id'=>$og_info['sku_id']], 'price')['price'];
//                        $sku_record_arr['price'] = $platform_price;
//                        $sku_record_arr['real_money'] = $og_info['real_money'];
//                        $sku_record_arr['shipping_fee'] = $og_info['shipping_fee']?:0;
////                        $sku_record_arr['channel_purchase_discount'] = $channel_info['purchase_discount']?:0;
//                        $goods_sku_model1 = new VslGoodsSkuModel();
//                        $goods_sku_info = $goods_sku_model1->getInfo([
//                            'sku_id' => $og_info['sku_id'],
//                        ], '*');
//                        $sku_record_arr['platform_price'] = $platform_price;
//                        //?????????????????????sku?????????
//                        $sku_record_arr['remain_num'] = $stock_list['stock'];
//                        $buy_type = 3;
//                        $sku_record_arr['buy_type'] = $buy_type;//??????
//                        $sku_record_arr['website_id'] = $og_info['website_id'];
//                        $sku_record_arr['create_time'] = time();
//                        $is_record = $sku_record_mdl->where(['order_no'=>$og_info['order_no'],'sku_id'=>$og_info['sku_id']])->find();
//                        if(!$is_record){
//                            $id = $sku_record_mdl->save($sku_record_arr);
//                        }
//                    }else
                    if ($og_info['seckill_id'] && getAddons('seckill', $this->website_id, $this->instance_id)) {//???????????????
                        //???????????????
//                        $goods_calculate->addSeckillSkuSales($og_info['seckill_id'],$og_info['sku_id'], $og_info['num']);
//                        $goods_calculate->addGoodsSales($og_info['goods_id'], $og_info['num']);
                    } else {//?????????
//                        $goods_calculate->addGoodsSales($og_info['goods_id'], $og_info['num']);
                    }
                }
                unset($og_info);
            }
            $order_info = $order_model->getInfo(['order_id' => $order_id], '*');
            $uid = $order_info['buyer_id'];
            $this->addOrderAction($order_id, $uid, '????????????');
            //????????????????????????
//            $this->calculateOrderMansong($order_id); //????????????????????????????????????????????????
            // ??????????????????????????????????????????
            $this->giveGoodsOrderPoint($order_id, 1);
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            throw new \Exception($e->getMessage());
        }
    }

    /**
     * ???????????????????????????????????????
     *
     * @param unknown $order_id
     */
    private function calculateOrderGivePoint($order_id)
    {
        $point = $this->order->getInfo([
            'order_id' => $order_id
        ], 'shop_id, give_point,buyer_id');
        $member_account = new MemberAccount();
        $member_account->addMemberAccountData(1, $point['buyer_id'], 1, $point['give_point'], 1, $order_id, '????????????????????????');
    }

    /**
     * ????????????????????????????????????
     *
     * @param unknown $order_id
     */
    public function calculateOrderMansong($order_id)
    {
        $order_info = $this->order->getInfo([
            'order_id' => $order_id
        ], 'shop_id, buyer_id');
        $order_promotion_details = new VslOrderPromotionDetailsModel();
        // ???????????????????????????
        $list = $order_promotion_details->getQuery([
            'order_id' => $order_id,
            'promotion_type_id' => 1,
            'promotion_type' => 'MANJIAN'
        ], 'promotion_id,id', '');
        if (!empty($list)) {
            $promotion_mansong_rule = new VslPromotionMansongRuleModel();
            foreach ($list as $k => $v) {
                $mansong_data = $promotion_mansong_rule->getInfo([
                    'rule_id' => $v['promotion_id']
                ], 'give_coupon,gift_id,gift_card_id');
                if (!empty($mansong_data)) {
//                    // ?????????????????????
//                    if ($mansong_data['give_point'] != 0) {
//                        $member_account = new MemberAccount();
//                        $member_account->addMemberAccountData(1, $order_info['buyer_id'], 1, $mansong_data['give_point'], 1, $order_id, '???????????????????????????');
//                    }
                    $goods_promotion = [];
                    $goods_promotion['id'] = $v['id'];
                    $promotion_detail = [];
                    // ????????????????????????
                    if ($mansong_data['give_coupon'] != 0 && getAddons('coupontype', $this->website_id)) {
                        $member_coupon = new CouponServer();
                        $member_coupon->userAchieveCoupon($order_info['buyer_id'], $mansong_data['give_coupon'], 1);
                        $coupon_type_model = new VslCouponTypeModel();
                        $give_coupon = $coupon_type_model::get($mansong_data['give_coupon']);
                        $promotion_detail['coupon'] = $give_coupon ? $give_coupon->toArray() : [];
                    }
                    // ????????????????????????????????????????????????
                    if ($mansong_data['gift_id'] != 0 && getAddons('gift', $this->website_id)) {
                        $order = new VslOrderModel();
                        $order_no = $order->getInfo(['order_id' => $order_id], 'order_no')['order_no'];
                        $gift_model = new VslPromotionGiftModel();
                        $promotionGift = $gift_model->getInfo(['promotion_gift_id' => $mansong_data['gift_id'], 'stock' => ['>', 0]], 'stock');
                        if ($promotionGift) {
                            $gift = new Gift();
                            $gift_info['uid'] = $order_info['buyer_id'];
                            $gift_info['type'] = 1;
                            $gift_info['num'] = 1;
                            $gift_info['no'] = $order_no;
                            $gift_info['promotion_gift_id'] = $mansong_data['gift_id'];
                            $gift_info['website_id'] = $order_info['website_id'];
                            $gift->addGiftRecord($gift_info);
                            $give_gift = $gift_model::get($mansong_data['gift_id']);
                            $promotion_detail['gift'] = $give_gift ? $give_gift->toArray() : [];
                        }

                    }
                    // ????????????????????????
                    if ($mansong_data['gift_card_id'] != 0 && getAddons('giftvoucher', $this->website_id)) {
                        $giftvoucher = new GiftVoucher();
                        $giftvoucher->getUserReceive($order_info['buyer_id'], $mansong_data['gift_card_id'], 1);
                        $gift_voucher_model = new VslGiftVoucherModel();
                        $give_gift_voucher = $gift_voucher_model::get($mansong_data['gift_card_id']);
                        $promotion_detail['gift_voucher'] = $give_gift_voucher ? $give_gift_voucher->toArray() : [];
                    }
                    if (!empty($promotion_detail)) {
                        $goods_promotion['remark'] = json_encode($promotion_detail, JSON_UNESCAPED_UNICODE);
                        $order_promotion_details->data($goods_promotion, true)->isUpdate(true)->save();
                    }
                }
            }
            unset($v);
        }
    }

    /**
     * ????????????????????????
     *
     * @param unknown $orderid
     * @return Exception
     */
    public function orderClose($orderid, $task_mark = 0)
    {
        $this->order->startTrans();
        try {
            $order_info = $this->order->getInfo([
                'order_id' => $orderid
            ], 'thresholdtype_point,goods_type,order_status,pay_status,point, coupon_id, user_money, buyer_id,shop_id,user_platform_money, coin_money, bargain_id, website_id,deduction_point,shipping_type,store_id,order_type,money_type,membercard_deduction_money');
            $data_close = array(
                'order_status' => 5
            );
            $this->order->save($data_close, [
                'order_id' => $orderid
            ]);
            if (getAddons('channel', $order_info['website_id'])) {
                //?????????????????????????????????????????????,????????????????????????????????????
                $cosr_mdl = new VslChannelOrderSkuRecordModel();
                $channel_retail_info = $cosr_mdl->getInfo(['order_id' => $orderid], "*");
                if ($channel_retail_info) {
                    $cosr_mdl->where(['order_id' => $orderid])->delete();
                }
            }
            //???????????????????????????????????????????????????????????????????????????????????????
            if($order_info['membercard_deduction_money'] > 0 && $order_info['order_type'] != 7 && $task_mark) {
                if(getAddons('membercard',$order_info['website_id'])) {
                    $membercard = new MembercardSer();
                    $membercard->adjustBalance($order_info['buyer_id'],$order_info['membercard_deduction_money'],'????????????',76,$orderid);
                }
            }
            //???????????? ???????????????????????????????????????????????????
            $presell = getAddons('presell', $order_info['website_id']);
            if($order_info['order_type'] == 7 && $order_info['money_type'] == 1 && $presell){
                $Distributor = new DistributorService();
                $Distributor->addCommissionDistributionPresell($orderid);
                $this->noEarnestRefound($orderid);
            }
            $account_flow = new MemberAccount();
            if ($order_info['point'] > 0 && $order_info['order_status'] == 0) {
                $account_flow->addMemberAccountData(1, $order_info['buyer_id'], 1, $order_info['point'], 2, $orderid, '????????????????????????');
            }
            if ($order_info['deduction_point'] > 0 && $order_info['order_status'] == 0) {
                $account_flow->addMemberAccountData(1, $order_info['buyer_id'], 1, $order_info['deduction_point'], 2, $orderid, '????????????????????????');
            }
            if ($order_info['thresholdtype_point'] > 0 && $order_info['order_status'] == 0) {
                $account_flow->addMemberAccountData(1, $order_info['buyer_id'], 1, $order_info['thresholdtype_point'], 2, $orderid, '?????????????????????????????????????????????');
            }
            if ($order_info['coin_money'] > 0) {
                $coin_convert_rate = $account_flow->getCoinConvertRate();
                $account_flow->addMemberAccountData(3, $order_info['buyer_id'], 1, $order_info['coin_money'] / $coin_convert_rate, 2, $orderid, '???????????????????????????');
            }

            // ??????????????? ???????????????????????????
            if ($order_info['coupon_id'] > 0 && getAddons('coupontype', $order_info['website_id']) && $task_mark) {
                $couponServer = new CouponServer();
                $couponServer->UserReturnCoupon($order_info['coupon_id']);
            }
            
            
            // ????????????
            $order_goods = new VslOrderGoodsModel();
            $order_goods_list = $order_goods->getQuery([
                'order_id' => $orderid
            ], '*', '');
            //?????????code_id
            $receive_order_goods_data = [];//???????????????????????????
            foreach ($order_goods_list as $k => $v) {
                if($order_info['goods_type'] == 6){
                    $orderScheduleModel = new VslOrderScheduleModel();
                    $order_schedule_info = $orderScheduleModel->getInfo(['order_id'=>$orderid,'website_id'=>$order_info['website_id'],'goods_id'=>$v['goods_id']],'sid');
                    if($order_schedule_info){
                        $orderScheduleModel->save(['status'=>0],['sid'=>$order_schedule_info['sid']]);
                    }
                }
                $return_stock = 0;
                $goods_sku_model = new VslGoodsSkuModel();
                $goods_sku_info = $goods_sku_model->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'goods_id, stock, sku_id, supplier_sku_id');
                if ($v['shipping_status'] != 1) {
                    // ???????????????
                    $return_stock = 1;
                } else {
                    // ???????????????,???????????????
                    if ($v['refund_type'] == 1) {
                        $return_stock = 0;
                    } else {
                        $return_stock = 1;
                    }
                }
                //                // ?????????????????? ?????????????????????
                if ($task_mark) {
                    $redis = connectRedis();
                    if ($return_stock == 1) {
                        $goods_calculate = new GoodsCalculate();
                        if (getAddons('seckill', $order_info['website_id'], $this->instance_id)) {
                            //????????????????????????????????????????????????????????????
                            $seckill_server = new Seckill();
                            $order_seckill_list = $seckill_server->orderSkuIsSeckill($orderid, $v['sku_id']);
                        }

                        //?????????
                        if ($v['channel_info'] && $v['channel_stock']) {
                            $channel_key = 'channel_'.$v['channel_info'].'_'.$goods_sku_info['sku_id'];
                            //????????????????????????
                            for($i=0; $i<$v['num']; $i++){
                                $redis->incr($channel_key);
                            }
                            //?????????
                            $goods_calculate->addChannelGoodsStock($goods_sku_info['goods_id'], $goods_sku_info['sku_id'], $v['channel_stock'], $v['channel_info']);
                            //???????????????
                            if ($order_info['pay_status'] == 2) {
                                $goods_calculate->subChannelSales($goods_sku_info['goods_id'], $v['sku_id'], $v['channel_stock'], $v['channel_info']);
                            }
                        }
                        //??????
                        if ($v['channel_info'] && $v['goods_money'] == 0) {
                            $channel_key = 'channel_'.$v['channel_info'].'_'.$goods_sku_info['sku_id'];
                            //????????????????????????
                            for($i=0; $i<$v['num']; $i++){
                                $redis->incr($channel_key);
                            }
                            //?????????
                            $goods_calculate->addChannelGoodsStock($goods_sku_info['goods_id'], $goods_sku_info['sku_id'], $v['num'], $v['channel_info']);
                            //???????????????
                            if ($order_info['pay_status'] == 2) {
                                $goods_calculate->subChannelSales($goods_sku_info['goods_id'], $v['sku_id'], $v['num'], $v['channel_info']);
                            }
                        }

                        if ($order_seckill_list) {
                            $seckill_id = $order_seckill_list['promotion_id'];
                            $redis_goods_sku_seckill_key = 'seckill_' . $seckill_id . '_' . $goods_sku_info['goods_id'] . '_' . $goods_sku_info['sku_id'];
                            //?????????????????????
                            $seckill_server->addSeckillGoodsStock($seckill_id, $v['sku_id'], $v['num']);
                            //????????????????????? lgq???
                            if ($order_info['pay_status'] == 2) {
                                $goods_calculate->subSeckillGoodsSales($seckill_id, $v['sku_id'], $v['num']);
                            }
                            $goods_id = $goods_sku_info['goods_id'];
                            $sku_id = $v['sku_id'];
                            //?????????????????????????????????
                            $aosr_mdl = new VslActivityOrderSkuRecordModel();
                            $activity_cond['activity_id'] = $seckill_id;
                            $activity_cond['uid'] = $order_info['buyer_id'];
                            $activity_cond['sku_id'] = $sku_id;
                            $activity_cond['buy_type'] = 1;
                            $aosr_mdl->where($activity_cond)->delete();
                            //????????????????????????
                            for($i=0; $i<$v['num']; $i++){
                                $redis->incr($redis_goods_sku_seckill_key);
                            }
                        } elseif ($order_info['bargain_id'] && getAddons('bargain', $order_info['website_id'], $this->instance_id)) {//????????????????????????
                            $bargain_key = 'bargain_'.$order_info['bargain_id'].'_'.$goods_sku_info['sku_id'];
                            $bargain_server = new Bargain();
                            $bargain_server->addBargainGoodsStock($order_info['bargain_id'], $v['num']);
                            //????????????????????????
                            for($i=0; $i<$v['num']; $i++){
                                $redis->incr($bargain_key);
                            }
                            //????????????????????? lgq???
                            if ($order_info['pay_status'] == 2) {
                                $goods_calculate->subBargainGoodsSales($order_info['bargain_id'], $goods_sku_info['goods_id'], $v['num']);
                            }
                        } elseif ($v['presell_id'] && getAddons('presell', $order_info['website_id'], $this->instance_id)) {
                            $presell_key = 'presell_'.$v['presell_id'].'_'.$goods_sku_info['sku_id'];
                            //????????????????????????
                            for($i=0; $i<$v['num']; $i++){
                                $redis->incr($presell_key);
                            }
                            //?????????????????????
                            $presell_cond['activity_id'] = $v['presell_id'];
                            $presell_cond['sku_id'] = $v['sku_id'];
                            $presell_cond['buy_type'] = 4;
                            $presell_cond['website_id'] = $order_info['website_id'];
                            $aosr_mdl = new VslActivityOrderSkuRecordModel();
                            $aosr_list = $aosr_mdl->where($presell_cond)->find();
                            $aosr_list->num = $aosr_list->num - $v['num'];
                            $aosr_list->save();
                            if ($order_info['pay_status'] == 2) {
                                //????????????
                                $presell_mdl = new VslPresellModel();
                                $presell_list = $presell_mdl->where(['id' => $v['presell_id']])->find();
                                $presell_list->presell_sales = ($presell_list->presell_sales - $v['num'] > 0) ? $presell_list->presell_sales - $v['num'] : 0;
                                $presell_list->save();
                            }
                        } else {
                            if ($order_info['shipping_type'] == 1) {
                                //????????????
                                if($goods_sku_info['supplier_sku_id']) {
                                    //???????????????
                                    $goods_calculate = new GoodsCalculate();
                                    $goods_calculate->addSupplierGoodsStock($goods_sku_info['goods_id'],$v['sku_id'],$v['num'] - $v['channel_stock']);
                                }else{
                                    $data_goods_sku = array(
                                        'stock' => $goods_sku_info['stock'] + $v['num'] - $v['channel_stock']
                                    );
                                    $goods_sku_model->save($data_goods_sku, [
                                        'sku_id' => $v['sku_id']
                                    ]);
                                    $count = $goods_sku_model->getSum([
                                        'goods_id' => $goods_sku_info['goods_id']
                                    ], 'stock');
                                    // ??????????????????
                                    $goodsSer = new \data\service\Goods();
                                    $goodsSer->updateGoods([
                                        'goods_id' => $goods_sku_info['goods_id']
                                    ], [
                                        'stock' => $count
                                    ], $goods_sku_info['goods_id']);
   
                                    $goods_key = 'goods_'.$goods_sku_info['goods_id'].'_'.$goods_sku_info['sku_id'];
                                    //????????????????????????
                                    for($i=0; $i<$v['num']; $i++){
                                        $redis->incr($goods_key);
                                    }
                                }
                                if ($order_info['pay_status'] == 2) {
                                    $goods_calculate->subGoodsSales($goods_sku_info['goods_id'], $v['num'] - $v['channel_stock']);
                                }
                            } elseif ($order_info['shipping_type'] == 2) {
                                $store_redis_key = 'store_goods_'.$goods_sku_info['goods_id'].'_'.$goods_sku_info['sku_id'];
                                //????????????????????????
                                for($i=0; $i<$v['num']; $i++){
                                    $redis->incr($store_redis_key);
                                }
                                //????????????
                                $store_goods_sku_model = new VslStoreGoodsSkuModel();
                                $store_sku_info = $store_goods_sku_model->getInfo([
                                    'sku_id' => $v['sku_id'], 'store_id' => $order_info['store_id']
                                ], 'goods_id, stock, sku_id');
                                //??????sku???????????????
                                $data_sku_stock = array(
                                    'stock' => $store_sku_info['stock'] + $v['num']
                                );
                                $store_goods_sku_model->save($data_sku_stock, [
                                    'sku_id' => $v['sku_id'], 'goods_id' => $store_sku_info['goods_id'], 'store_id' => $order_info['store_id']
                                ]);
                                //???????????????????????????
                                $store_goods_model = new VslStoreGoodsModel();
                                $store_goods_stock = $store_goods_model->getInfo([
                                    'store_id' => $order_info['store_id'], 'goods_id' => $store_sku_info['goods_id']
                                ], 'stock');
                                $data_goods_stock = [
                                    'stock' => $store_goods_stock['stock'] + $v['num']
                                ];
                                $store_goods_model->save($data_goods_stock, [
                                    'store_id' => $order_info['store_id'], 'goods_id' => $store_sku_info['goods_id']
                                ]);
                                if ($order_info['pay_status'] == 2) {
                                    //?????????????????????
                                    $goods_calculate->subGoodsSales($goods_sku_info['goods_id'], $v['num']);
                                    //??????????????????
                                    $goods_sales = $store_goods_model->getInfo(['goods_id' => $store_sku_info['goods_id'], 'store_id' => $order_info['store_id']], 'sales')['sales'];
                                    $data_goods_sales = [
                                        'sales' => $goods_sales - $v['num']
                                    ];
                                    $store_goods_model->save($data_goods_sales, [
                                        'goods_id' => $store_sku_info['goods_id'], 'store_id' => $order_info['store_id']
                                    ]);
                                }
                            }

                        }
                    }
                }
                //?????????????????????
                if($v['receive_order_goods_data']){
                    $receive_order_goods_data[]= json_decode(htmlspecialchars_decode($v['receive_order_goods_data']),true);
                }
            }
            unset($v);
            if($order_info['order_type'] != 15){
                $orderCal = new VslOrderCalculateModel();
                $orderCal->delData(['order_id' => $orderid]);
            }
            
            $this->addOrderAction($orderid, $order_info['buyer_id'], '????????????');
    
            // ??????????????????
            if (getAddons('invoice', $order_info['website_id'], $order_info['shop_id'])) {
                $invoice = new InvoiceServer();
                $invoice->updateOrderStatusByOrderId($orderid, 2);//??????????????????
            }
            //?????????
            if(getAddons('receivegoodscode',$order_info['website_id'],$order_info['shop_id']) && $receive_order_goods_data){
                $codeSer = new ReceiveGoodsCodeSer();
//                $codeSer->rollbackUserReceiveGoodsCodeByCodeId($code_id,$order_info['website_id'],$order_info['shop_id']);
                $code_ids = array_column($receive_order_goods_data,'code_id');
                foreach ($code_ids as $code_id) {
                    foreach ($code_id as $id){
                        $codeSer->rollbackUserReceiveGoodsCodeByCodeId($id,$order_info['website_id'],$order_info['shop_id']);
                    }
                }
            }
            //?????????
            if(getAddons('merchants',$order_info['website_id']) && $order_info['shop_id']) {
                $merchants_server = new Merchants();
                $merchants_server->deleteMerchantsFreezingBonus($orderid,0,$order_info['website_id'],0,1);
            }
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            
            recordErrorLog($e);
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * ?????????????????????????????????????????????????????????
     * ??????100 ???????????? ????????????100 ????????????
     * @param $order_id
     */
    public function noEarnestRefound($order_id)
    {
        #???????????????????????????
        $order_model = new VslOrderModel();
        $orderBonusModel = new VslOrderBonusModel();
        $shop_account = new ShopAccount();
        $order = new \data\service\Order();
        $pay_money = $this->getOrderRealPayShopMoney($order_id);
        $order_obj = $order_model->get($order_id);
        $shop_id = $order_obj["shop_id"];
        $order_no = $order_obj['order_no'];
        $area_bonus = $orderBonusModel->getSum(['order_id'=>$order_id,'from_type'=>2,'return_status'=>0,'shop_id'=>$order_obj['shop_id']],'bonus');//????????????
        $global_bonus = $orderBonusModel->getSum(['order_id'=>$order_id,'from_type'=>1,'return_status'=>0,'shop_id'=>$order_obj['shop_id']],'bonus');//????????????
        $team_bonus = $orderBonusModel->getSum(['order_id'=>$order_id,'from_type'=>3,'return_status'=>0,'shop_id'=>$order_obj['shop_id']],'bonus');//????????????
        //???????????????????????? ????????????????????????
        $order_commission = new VslOrderDistributorCommissionModel();
        $orders = $order_commission->Query(['order_id' => $order_id,'return_status'=>0,'shop_id'=>$order_obj['shop_id']], '*');
        $order_detail = array();
        if($orders){
            foreach ($orders as $key1 => $value) {
                if ($value['commissionA_id']) {
                    $order_detail['commissionA'] += $value['commissionA'];
                    $order_detail['pointA'] += $value['pointA'];
                }
                if ($value['commissionB_id']) {
                    $order_detail['commissionB'] += $value['commissionB'];
                    $order_detail['pointB'] += $value['pointB'];
                }
                if ($value['commissionC_id']) {
                    $order_detail['commissionC'] += $value['commissionC'];
                    $order_detail['pointC'] += $value['pointC'];
                }
            }
            $order_detail['commission'] = $order_detail['commissionA'] + $order_detail['commissionB'] + $order_detail['commissionC'];
            $order_detail['point'] = $order_detail['pointA'] + $order_detail['pointB'] + $order_detail['pointC'];
        }
        $commission_money = 0;
        if ($order_detail && floatval($order_detail['commission']) > 0 || floatval($order_detail['point']) > 0) {
            //?????????
            $config = new Config();
            $config_info = $config->getShopConfig(0, $order_obj['website_id']);
            $convert_rate = $config_info['convert_rate'] ? $config_info['convert_rate'] : 1; //????????????????????? ???????????????1:1
            $commission_point_money = floor($order_detail['point'] / $convert_rate);
            $commission_money = $commission_point_money + $order_detail['commission'];
        }
        $supplier_money_info = $order->calculSupplierMoney($order_id,4);
        // $real_pay_money = $pay_money - $commission_money - $area_bonus - $global_bonus - $team_bonus - $supplier_money_info['supplier_money'];
        $real_pay_money = $pay_money;

        //????????????????????? ==  ???????????? - ???????????? -?????? - ??????????????? == ??????????????? ???????????????
        $shop_account->updateShopAccountMoney($shop_id, $real_pay_money);
        //edit for 2020/09/28 ?????? ?????????????????????????????????
        $calculFreezingMoney = $order->calculFreezingMoney($order_id,$shop_id);
        $shop_account->updateShopAccountTotalMoney($shop_id, (-1) * $calculFreezingMoney);
        $shop_account->addShopAccountRecords(getSerialNo(), $shop_id, $real_pay_money, 5, $order_id, "????????????".$order_no."??????????????????????????????".$real_pay_money."??????????????????????????????????????????", "???????????????????????????", $order_obj['website_id']);
    }
    /**
     * ????????????????????????
     *
     * @param unknown $orderid
     * @return Exception
     */
    public function channelOrderClose($orderid)
    {
        $order = new VslChannelOrderModel();
        $order->startTrans();
        try {
            $order_info = $order->getInfo([
                'order_id' => $orderid
            ], 'order_status,pay_status, user_money, buyer_id,shop_id');
            $data_close = array(
                'order_status' => 5
            );
//            var_dump($orderid,$data_close);exit;
            $order->save($data_close, [
                'order_id' => $orderid
            ]);
            $account_flow = new MemberAccount();
            if ($order_info['order_status'] == 0) {
                // ??????????????????
                if ($order_info['user_money'] > 0) {
                    $account_flow->addMemberAccountData(2, $order_info['buyer_id'], 1, $order_info['user_money'], 2, $orderid, '??????????????????????????????');
                }
            }

            // ????????????
            $order_goods = new VslChannelOrderGoodsModel();
            $order_goods_list = $order_goods->getQuery([
                'order_id' => $orderid
            ], '*', '');
            foreach ($order_goods_list as $k => $v) {
                $return_stock = 0;
                $goods_sku_model = new VslGoodsSkuModel();
                $goods_sku_info = $goods_sku_model->getInfo([
                    'sku_id' => $v['sku_id']
                ], 'goods_id, stock, sku_id');
                if ($v['shipping_status'] != 1) {
                    // ???????????????
                    $return_stock = 1;
                } else {
                    // ???????????????,???????????????
                    if ($v['refund_type'] == 1) {
                        $return_stock = 0;
                    } else {
                        $return_stock = 1;
                    }
                }
                // ??????????????????
                if ($return_stock == 1) {
                    $goods_calculate = new GoodsCalculate();
                    //????????????????????????
                    if ($v['channel_info'] != 'platform') {
                        //?????????
                        $goods_calculate->addChannelGoodsStock($goods_sku_info['goods_id'], $goods_sku_info['sku_id'], $v['num'], $v['channel_info']);
//                        //?????????
//                        $goods_calculate->subChannelSales($goods_sku_info['goods_id'], $goods_sku_info['sku_id'], $v['num'], $v['channel_info']);
                    } else {
                        $data_goods_sku = array(
                            'stock' => $goods_sku_info['stock'] + $v['num']
                        );
                        $goods_sku_model->save($data_goods_sku, [
                            'sku_id' => $v['sku_id']
                        ]);
                        $count = $goods_sku_model->getSum([
                            'goods_id' => $goods_sku_info['goods_id']
                        ], 'stock');
                        // ??????????????????
                        $goodsSer = new \data\service\Goods();
                        $goodsSer->updateGoods([
                            'goods_id' => $goods_sku_info['goods_id']
                        ], [
                            'stock' => $count
                        ], $goods_sku_info['goods_id']);
//                        $goods_calculate->subGoodsSales($goods_sku_info['goods_id'], $v['num']);
                    }

                }
            }
            unset($v);
            $this->addOrderAction($orderid, $this->uid, '???????????????????????????');
            $order->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write($e->getMessage());
            $order->rollback();
            return $e->getMessage();
        }
    }
    /**
     * ??????????????????
     *
     * @param unknown $order_id
     * @param unknown $order_goods_id
     */
    public function orderGoodsRefundFinish($order_id,$order_goods_id='')
    {
        $order_model = new VslOrderModel();
        
        $orderInfo = $order_model::get($order_id);
        $order_model->startTrans();
        try {
            $order_goods_model = new VslOrderGoodsModel();
            $refunding_count = $order_goods_model->where("order_id=$order_id AND refund_status != 5 AND refund_status>0")->count();
            $total_count = $order_goods_model->where("order_id=$order_id")->count();

            $refunded_count = $order_goods_model->where("order_id=$order_id AND refund_status=5")->count();
            $shipping_status = $orderInfo->shipping_status;
            $order_status = $orderInfo->order_status;
            $all_refund = 0;
            if ($refunded_count == $total_count) {
                $all_refund = 1;

            } elseif (($refunding_count + $refunded_count) == $total_count) {
                // ????????????????????????????????????????????????????????????
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[-1]['status_id']; // ?????????
                

            } elseif ($shipping_status == OrderStatus::getShippingStatus()[0]['shipping_status']) {
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[1]['status_id']; // ?????????
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[1]['shipping_status']) {
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[2]['status_id']; // ?????????
            } elseif ($shipping_status == OrderStatus::getShippingStatus()[2]['shipping_status']) {
                $orderInfo->order_status = OrderStatus::getOrderCommonStatus()[3]['status_id']; // ?????????
            }
            //????????????????????????????????????????????????????????????????????????????????????????????????
            //edit for 2020/06/16 ????????????????????????????????????
            $refund_count = $order_goods_model->where("order_id=$order_id AND refund_status > 0 and refund_status < 5 ")->count();
            if($order_goods_id && $refund_count >= 0 && $total_count > 1){
                foreach ($order_goods_id as $key => $value) {
                    $order_info = $order_goods_model->getInfo(['order_id' => $order_id,'order_goods_id' => $value], 'order_status'); //->count()
                   //???????????????????????????
                   if($order_status == 2 && $order_info['order_status'] < 2){
                        $orderInfo->order_status = 1; //???????????????????????????????????????
                   }
                }
                unset($value);
            }
            //???????????????????????????????????? ??????????????????????????????
            // $send_count = $order_goods_model->where("order_id=$order_id and order_status < 2")->count();
            // if($send_count > 0){
            //     $orderInfo->order_status = 1; //???????????????????????????????????????
            // }
            // ????????????????????????
            if ($all_refund == 0) {
                $retval = $orderInfo->save();
//                if ($refunding_count == 0) {
//                    $this->orderDoDelivery($order_id);
//                }
            } else {
                // ???????????????????????????????????????
                $retval = $this->orderClose($order_id);
            }
            $order_model->commit();
            return $retval;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $order_model->rollback();
            return $e->getMessage();
        }
        return $retval;
    }

    /**
     * ??????????????????
     *
     * @param unknown $order_id
     */
    public function getDetail($order_id, $channel_status='')
    {
        $order_detail = $this->order->getInfo([
            "order_id" => $order_id,
            "is_deleted" => 0,
            "website_id" => $this->website_id
        ]);
        if (empty($order_detail)) {
            return array();
        }
        if (getAddons('shop', $this->website_id)) {
            $shop = new VslShopModel();
            $detail = $shop->getInfo(['shop_id' => (int)$order_detail['shop_id'], 'website_id' => $this->website_id], 'shop_phone,shop_name');
            $order_detail['shop_name'] = $detail['shop_name'];
            $order_detail['shop_phone'] = $detail['shop_phone'];
            if (!$order_detail['shop_id']) {
                $shop_return = new VslOrderShopReturnModel();
                $order_detail['shop_phone'] = $shop_return->getInfo(['website_id' => $this->website_id, 'shop_id' => $order_detail['shop_id'], 'is_default' => 1], 'mobile')['mobile"'];
            }
        } else {
            $shop_return = new VslOrderShopReturnModel();
            $order_detail['shop_name'] = $this->mall_name;
            $order_detail['shop_phone'] = $shop_return->getInfo(['website_id' => $this->website_id, 'shop_id' => $order_detail['shop_id'], 'is_default' => 1], 'mobile')['mobile"'];
        }
        // ????????????
        $temp_array = array();
        if ($order_detail["buyer_invoice"] != "") {
            $temp_array = explode("$", $order_detail["buyer_invoice"]);
        }
        $order_detail["buyer_invoice_info"] = $temp_array;
        if (empty($order_detail)) {
            return '';
        }
        $isGroupSuccess = 0;
        if (getAddons('groupshopping', $this->website_id, $this->instance_id)) {
            $group_server = new GroupShopping();
            $isGroupSuccess = $group_server->groupRecordDetail($order_detail['group_record_id'])['status'];
        }
        if (getAddons('luckyspell', $this->website_id, $this->instance_id) && $order_detail['luckyspell_id']) {
            $luckySpellServer = new luckySpellServer();
            $record = $luckySpellServer->groupluckySpellRecordDetail($order_detail['order_id']);
            $isGroupSuccess = $record['status'];
        }
        $order_detail['payment_type_name'] = OrderStatus::getPayType($order_detail['payment_type']);
        if ($order_detail['order_type'] == 10 && !(int)$order_detail['order_money']) {//???????????????????????????????????????????????????????????????????????????????????????
            $order_detail['payment_type_name'] = '';
        }
        if($order_detail['payment_type'] == 18 && $order_detail['membercard_deduction_money'] != 0.00) {
            $order_detail['payment_type_name'] = '???????????????';
        }
        $order_detail['payment_type_presell_name'] = OrderStatus::getPayType($order_detail['payment_type_presell']);
        $express_company_name = "";
        if ($order_detail['shipping_type'] == 1) {
            $order_detail['shipping_type_name'] = '????????????';
            $express_company = new VslOrderExpressCompanyModel();

            $express_obj = $express_company->getInfo([
                "co_id" => $order_detail["shipping_company_id"]
            ], "company_name");
            if (!empty($express_obj["company_name"])) {
                $express_company_name = $express_obj["company_name"];
            }
        } elseif ($order_detail['shipping_type'] == 2) {
            $order_detail['shipping_type_name'] = '????????????';
        } else {
            $order_detail['shipping_type_name'] = '';
        }
        $order_detail["shipping_company_name"] = $express_company_name;
        // ??????????????????
        $order_detail['order_goods'] = $this->getOrderGoods($order_id, $channel_status);
        
        //????????????????????????
        $status_of_2 = array_column(objToArr($order_detail['order_goods']),'order_status');//?????????
        //??????????????????????????????????????????
        if (getAddons('supplier',$this->website_id) && $this->model=='supplier'){
            $order_detail['order_goods'] = $this->fiflterNotSupplierOrderGoods($order_detail['order_goods'],$this->supplier_id);
        }
        //??????????????????????????????
        if ($order_detail['presell_id'] != 0) {
            $price = $order_detail['order_goods'][0]['price'];
            $num = $order_detail['order_goods'][0]['num'];
            $goods_price = $price * $num;
//            $order_price = $goods_price + $order_detail['shipping_money'];
            $order_detail['goods_money'] = $goods_price;
            $order_detail['can_presell_pay'] = '';
            $order_detail['can_presell_pay_reason'] = '';
            if ($order_detail['order_type'] == 7 && $order_detail['money_type'] == 0) {//?????????????????????
                $order_detail['first_money'] = $order_detail['pay_money'];
                $order_detail['final_money'] = $order_detail['final_money'];
//                $order_detail['order_money'] = $order_detail['order_money'];
                $order_detail['presell_status'] = 0;//????????????
            } elseif ($order_detail['order_type'] == 7 && $order_detail['money_type'] == 1) {//???????????????
                $presell_mdl = new VslPresellModel();
                $presell_info = $presell_mdl->getInfo(['id' => $order_detail['presell_id']], 'pay_start_time, pay_end_time');
                if (time() <= $presell_info['pay_end_time'] && time() >= $presell_info['pay_start_time']) {
                    $order_detail['can_presell_pay'] = 1;
                } else {
                    $order_detail['can_presell_pay'] = 0;
                    $order_detail['can_presell_pay_reason'] = '??????????????????????????????' . date('Y-m-d H:i:s', $presell_info['pay_start_time']) . '-' . date('Y-m-d H:i:s', $presell_info['pay_end_time']);
                }
                $order_detail['first_money'] = $order_detail['pay_money'];
                $order_detail['final_money'] = $order_detail['final_money'];
//                $order_detail['pay_money'] = $order_detail['pay_money'];
//                $order_detail['order_money'] = $order_detail['final_money'];
                $order_detail['presell_status'] = 1;//????????????
            } elseif ($order_detail['order_type'] == 7 && $order_detail['money_type'] == 2) {//?????????????????????
                $order_detail['first_money'] = $order_detail['pay_money'];
                $order_detail['final_money'] = $order_detail['final_money'];
                $order_detail['pay_money'] = $order_detail['final_money'] + $order_detail['pay_money'];
                $order_detail['order_money'] = $order_detail['final_money'] + $order_detail['first_money'];
                $order_detail['presell_status'] = 2;//????????????
            }
        }
        if ($order_detail['payment_type'] == 6 || $order_detail['shipping_type'] == 2) {
            $order_status = OrderStatus::getSinceOrderStatus();
        } else {
            // ???????????????
            $order_status = OrderStatus::getOrderCommonStatus($order_detail['order_type'], $isGroupSuccess, $order_detail['card_store_id'], $order_detail['order_goods'] ? $order_detail['order_goods'][0]['goods_type'] : 0);
        }

//
        // ???????????????????????????
        if ($order_detail['shipping_type'] == 2) {
            $storeModel = new VslStoreModel();
            $store_id = ($order_detail['card_store_id'] > 0) ? $order_detail['card_store_id'] : $order_detail['store_id'];
            $store = $storeModel->getInfo([
                'store_id' => $store_id,
                'shop_id' => $order_detail['shop_id'],
                'website_id' => $order_detail['website_id']
            ], 'province_id,city_id,district_id,address,store_name,store_tel');
            $address = new Address();
            $store['province_name'] = $address->getProvinceName($store['province_id']);
            $store['city_name'] = $address->getCityName($store['city_id']);
            $store['dictrict_name'] = $address->getDistrictName($store['district_id']);
            $store['store_name'] = $store['store_name'];
            $store['store_tel'] = $store['store_tel'];
            $store['address'] = $store['address'];
            $order_detail['order_pickup'] = $store;
            $user = new UserModel();
            $order_detail['order_pickup']['user_tel'] = $user->getInfo(['uid' => $order_detail['buyer_id']], 'user_tel')['user_tel'];
        } else {
            $order_detail['order_pickup'] = [];
        }
        // ??????????????????
        //TODO... ?????????BUG???unset $v_status???????????????????????????
        foreach ($order_status as $k_status => $v_status) {

            //??????????????????????????????
            if ($order_detail['pay_status'] == '0' && $order_detail['presell_id'] > 0) {
                unset($v_status['operation']['1']);
            }

            //???????????????????????????
            if ($order_detail['pay_status'] == '2' && $order_detail['money_type'] == '1') {
                unset($v_status['operation']['0']);
            }

            if ($v_status['status_id'] == $order_detail['order_status']) {
                $order_detail['operation'] = $v_status['operation'];
                $order_detail['status_name'] = $v_status['status_name'];
            }

            //???????????????????????????????????????????????????????????????????????????
            if ($this->model == 'supplier' && (array_diff($status_of_2,[2])) && $k_status ==2){
                unset($order_status[$k_status]['operation']['2']);
            }
        }
        unset($v_status);
        // ????????????????????????
        $order_action = new VslOrderActionModel();
        $order_detail['order_action'] = $order_action->getQuery(['order_id' => $order_id], '*', 'action_time desc,action_id desc');
        if ($order_detail['order_action']) {
            foreach ($order_detail['order_action'] as $koa => $voa) {
                if (!$voa['uid']) {
                    continue;
                }
                $user = new UserModel();
                $order_detail['order_action'][$koa]['user_name'] = $user->getInfo(['uid' => $voa['uid']], 'user_tel')['user_tel'];
            }
            unset($voa);
        }
        // ????????????????????????
        $order_refund = new VslOrderRefundModel();
        $orderGoodsSer = new OrderGoodsService();
        $supplier_money = 0;
        $order_detail['membercard_deduction_money'] = 0;
        foreach ($order_detail['order_goods'] as $kg => $vg) {
            $order_detail['order_refund'][$vg['order_goods_id']] = $order_refund->getQuery(['order_goods_id' => $vg['order_goods_id']], '*', 'action_time desc,id desc');
            $order_goods = $orderGoodsSer->getOrderGoodsData(['order_goods_id' => $vg['order_goods_id']], 'supplier_id');
            $supplier_id = current($order_goods)['supplier_id'];
            if ($order_detail['order_refund'][$vg['order_goods_id']]) {
                foreach ($order_detail['order_refund'][$vg['order_goods_id']] as $koa => $voa) {
                    //??????????????????????????????????????????
                    if ($this->supplier_id && $supplier_id == 0){continue;}
                    $order_detail['order_refund'][$vg['order_goods_id']][$koa]['goods_name'] = $vg['goods_name'];
                    $user = new UserModel();
//                    $order_detail['order_refund'][$vg['order_goods_id']][$koa]['user_name'] = $user->getInfo(['uid' => $voa['action_userid']], 'user_tel')['user_tel'];
                    $user_info = $user->getInfo(['uid' => $voa['action_userid']], 'user_tel,supplier_id');
                    $order_detail['order_refund'][$vg['order_goods_id']][$koa]['user_name'] = $user_info['user_tel'];
                    if (getAddons('supplier',$this->website_id) && $user_info['supplier_id']){
                        $supplierSer = new SupplierService();
                        $order_detail['order_refund'][$vg['order_goods_id']][$koa]['user_name'] = $supplierSer->getSupplierInfoBySupplierId($user_info['supplier_id'],'supplier_name')['supplier_name'];
                    }
                    $order_detail['order_refund'][$vg['order_goods_id']][$koa]['required_money'] = $vg['refund_require_money'];
                    if (!$voa['action_userid']) {
                        $order_detail['order_refund'][$vg['order_goods_id']][$koa]['user_name'] = '????????????';
                        continue;
                    }
                }
                unset($voa);
            }
            if ($vg['supplier_id'] == $this->supplier_id){
                $supplier_money += $vg['real_money'];
            }
            $order_detail['membercard_deduction_money'] += $vg['membercard_deduction_money'];
        }
        //?????????
        if (Request::instance()->module() == 'supplier'){
            //?????????????????????????????????pay_money??????????????????????????????????????????
            $order_list['order_money'] = $supplier_money;
        }
        unset($vg);
        $address_service = new Address();
        if(empty($order_detail['receiver_type'])) {
        $order_detail['address'] = $address_service->getAddress($order_detail['receiver_province'], $order_detail['receiver_city'], $order_detail['receiver_district']);
        $order_detail['address'] .= $order_detail["receiver_address"];
        }else{
            $country_list_model = new VslCountryListModel();
            $country_info = $country_list_model->getInfo(['id' => $order_detail['receiver_country']]);
            $order_detail['address'] = $country_info['chinese_country_name'] . '/' . $country_info['english_country_name'] . '/' . $order_detail["receiver_address"];
        }
        //???????????????????????????????????????????????????????????????????????????(1?????????????????????)
        $giftArr = [];
        if ($order_detail['is_mansong']){
            $orderPromotionModel = new VslOrderPromotionDetailsModel();
            $giftRes = $orderPromotionModel->getQuery(['order_id' => $order_id], 'gift_value');
            if ($giftRes) {
                foreach ($giftRes as $g_k => $g_v) {
                    $g_v = json_decode(htmlspecialchars_decode($g_v['gift_value']), true);
                    $giftValArr = [];
                    if ($g_v){
                        foreach ($g_v as $gg_k => $gg_v) {
                            $base_pre = '????????????';
                            if ($gg_k == 'gift_coupon') {
                                $base_pre = '???????????????';
                            }
                            if ($gg_k == 'gift_voucher') {
                                $base_pre = '???????????????';
                            }
                            $giftValArr[$g_k] = [
                                'is_gift'           => true,
                                'picture_info'      => ['pic_cover' => $gg_v['pic']],
                                'goods_name'        => $base_pre.$gg_v['name'],
                                'price'             => '0.00',
                                'num'               => 1,
                                'member_operation'  => []
                            ];
                            $giftArr = array_merge($giftArr, $giftValArr);
                        }
                        unset($gg_v);
                    }
                }
                unset($g_v);
            }
        }
        $order_detail['order_goods'] = array_merge($order_detail['order_goods'], $giftArr);
        return $order_detail;
    }

    /**
     * ??????????????????????????????
     *
     * @param unknown $order_id
     */
    public function getOrderGoods($order_id, $channel_status = '')
    {
        $order_goods = new VslOrderGoodsModel();
        $goods_sku = new VslGoodsSkuModel();
        if (!$channel_status) {
            $order_goods_list = $order_goods->all(['order_id' => $order_id]);
        } elseif ($channel_status == 'channel_retail') {
            $order_goods_list = $order_goods->all(['order_id' => $order_id, 'channel_info' => ['neq', 0]]);
        }
        foreach ($order_goods_list as $k => $v) {
            // ????????????sku?????????
            $goods_sku_info = $goods_sku->getInfo([
                'sku_id' => $v['sku_id']
            ], 'code');
            $order_goods_list[$k]['code'] = $goods_sku_info['code'];
            $order_goods_list[$k]['spec'] = [];
            if ($v['sku_attr']) {
                $order_goods_list[$k]['spec'] = json_decode(html_entity_decode($v['sku_attr']), true);
            }
            // ????????????sku??????
            $order_goods_list[$k]['express_info'] = $this->getOrderGoodsExpress($v['order_goods_id']);
            $shipping_status_array = OrderStatus::getShippingStatus();
            foreach ($shipping_status_array as $k_status => $v_status) {
                if ($v['shipping_status'] == $v_status['shipping_status']) {
                    $order_goods_list[$k]['shipping_status_name'] = $v_status['status_name'];
                }
            }
            unset($v_status);
            // ????????????
            $picture = new AlbumPictureModel();
            $picture_info = $picture->get($v['goods_picture']);
            if (empty($picture_info)) {
                $picture_info['pic_cover'] = '';
                $picture_info['pic_cover_micro'] = '';
                $picture_info['pic_cover_big'] = '';
                $picture_info['pic_cover_small'] = '';
            }
            $order_goods_list[$k]['picture_info'] = $picture_info;
            if ($v['refund_status'] != 0) {
                $order_refund_status = OrderStatus::getRefundStatus();
                foreach ($order_refund_status as $k_status => $v_status) {

                    if ($v_status['status_id'] == $v['refund_status']) {
                        $order_goods_list[$k]['refund_operation'] = $v_status['refund_operation'];
                        $order_goods_list[$k]['status_name'] = $v_status['status_name'];
                    }
                }
                unset($v_status);
            } else {
                $order_goods_list[$k]['refund_operation'] = '';
                $order_goods_list[$k]['status_name'] = '';
            }
        }
        unset($v);

        return $order_goods_list;
    }

    /**
     * ???????????????????????????
     *
     * @param unknown $order_id
     */
    public function getOrderExpress($order_id)
    {
        $order_goods_express = new VslOrderGoodsExpressModel();
        $order_express_list = $order_goods_express->all([
            'order_id' => $order_id
        ]);
        return $order_express_list;
    }

    /**
     * ??????????????????????????????
     *
     * @param unknown $order_goods_id
     * @return multitype:|Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:, array>
     */
    private function getOrderGoodsExpress($order_goods_id)
    {
        $order_goods = new VslOrderGoodsModel();
        $order_goods_info = $order_goods->getInfo([
            'order_goods_id' => $order_goods_id
        ], 'order_id,shipping_status');
        if ($order_goods_info['shipping_status'] == 0) {
            return array();
        } else {
            $order_express_list = $this->getOrderExpress($order_goods_info['order_id']);
            foreach ($order_express_list as $k => $v) {
                $order_goods_id_array = explode(",", $v['order_goods_id_array']);
                if (in_array($order_goods_id, $order_goods_id_array)) {
                    return $v;
                }
            }
            unset($v);
            return array();
        }
    }

    /**
     * ??????????????????
     *
     * @param int $order_id
     * @param float $order_adjust_money
     *            ?????????????????????
     * @param float $shipping_fee
     *            ??????????????????
     */
    public function orderAdjustMoney($order_id, $order_adjust_money, $shipping_fee)
    {
        $this->order->startTrans();
        try {
            $order_model = new VslOrderModel();
            $order_info = $order_model->getInfo([
                'order_id' => $order_id
            ], 'goods_money,shipping_money,order_money,pay_money,promotion_free_shipping,shop_order_money,shop_id');
            // ??????????????????
            $shipping_fee_adjust = $shipping_fee - ($order_info['shipping_money'] - $order_info['promotion_free_shipping']);
            $order_money = $order_info['order_money'] + $order_adjust_money + $shipping_fee_adjust;
            $pay_money = $order_info['pay_money'] + $order_adjust_money + $shipping_fee_adjust;
            //???????????????????????????????????????
            $shop_order_money['shop_order_money'] = $order_info['shop_order_money'] ? $order_info['shop_order_money'] : 0;
            if($order_info['shop_id'] > 0){
                $shop_order_money['shop_order_money'] = $shop_order_money['shop_order_money'] + $order_adjust_money + $shipping_fee_adjust;
                if($shop_order_money['shop_order_money'] < 0){
                    $shop_order_money['shop_order_money'] = 0;
                }
            }
            $data = array(
//                'goods_money' => $goods_money,
                'order_money' => $order_money,
                'shipping_money' => $shipping_fee + $order_info['promotion_free_shipping'],
                'pay_money' => $pay_money,
                'shop_order_money' => $shop_order_money['shop_order_money']
            );
//            var_dump($data);exit;
            $retval = $order_model->save($data, [
                'order_id' => $order_id
            ]);
            $this->addOrderAction($order_id, $this->uid, '????????????');
            // ????????????
            if (getAddons('invoice', $this->website_id, $this->instance_id)) {
                $invoice = new InvoiceServer();
                $invoice->changeInvoiceTaxByOrderId($order_id);
            }
            $this->order->commit();
            return $retval;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->order->rollback();
            return $e;
        }
    }

    /**
     * ??????????????????????????????(???????????????)
     *
     * @param unknown $order_id
     */
    public function getOrderGoodsMoney($order_id)
    {
        $order_goods = new VslOrderGoodsModel();
        $money = $order_goods->getSum([
            'order_id' => $order_id
        ], 'goods_money');
        if (empty($money)) {
            $money = 0;
        }
        return $money;
    }

    /**
     * ???????????????????????????
     *
     * @param unknown $order_goods_id
     *            ?????????ID
     */
    public function getOrderGoodsInfo($order_goods_id)
    {
        $order_goods = new VslOrderGoodsModel();
        return $order_goods->getInfo([
            'order_goods_id' => $order_goods_id
        ], 'goods_id,goods_name,goods_money,goods_picture,shop_id,website_id,real_money,num,actual_price,order_id');
    }

    /**
     * ????????????id ????????????????????????????????????
     *
     * @param unknown $order_id
     */
    public function getOrderRealPayMoney($order_id)
    {
        $order_model = new VslOrderModel();
        $order_info = $order_model::get($order_id);
        if ($order_info) {
            return $order_info['order_money'];
        } else {
            return 0;
        }
    }
    /**
     * ????????????id ??????????????????????????????????????????
     *
     * @param unknown $order_id
     */
    public function getOrderRealPayShopMoney($order_id)
    {
        $order_model = new VslOrderModel();
        $order_info = $order_model::get($order_id);
        if ($order_info) {
            return $order_info['shop_order_money'];
        } else {
            return 0;
        }
    }

    /**
     * ??????????????????
     *
     * @param unknown $order_id
     */
    public function giveGoodsOrderPoint($order_id, $type)
    {
        // ??????????????????????????????????????????
        $order_model = new VslOrderModel();
        $order_info = $order_model->getInfo([
            "order_id" => $order_id
        ], "give_point_type,shop_id,buyer_id,give_point,buyer_id");
        $member_account_records = new VslMemberAccountRecordsModel();
        $id = $member_account_records->getInfo(['uid' => $order_info['buyer_id'], 'website_id' => $this->website_id, 'data_id' => $order_id, 'sign' => 1], 'id');
        if ($order_info["give_point_type"] == $type && empty($id)) {
            if ($order_info["give_point"] > 0) {
                $member_account = new MemberAccount();
                $text = "";
                if ($order_info["give_point_type"] == 1) {
                    $text = "??????????????????????????????";
                } elseif ($order_info["give_point_type"] == 2) {
                    $text = "????????????????????????????????????";
                } elseif ($order_info["give_point_type"] == 3) {
                    $text = "????????????????????????????????????";
                }
                $member_account->addMemberAccountData(1, $order_info['buyer_id'], 1, $order_info['give_point'], 1, $order_id, $text);
            }
        }
    }

    /**
     * ??????????????????????????????
     *
     * {@inheritdoc}
     *
     * @see \data\api\IOrder::addOrderRefundAccountRecords()
     */
    public function addOrderRefundAccountRecords($order_goods_id, $refund_trade_no, $refund_money, $refund_way, $buyer_id, $remark)
    {
        $model = new VslOrderRefundAccountRecordsModel();

        $data = array(
            'order_goods_id' => $order_goods_id,
            'refund_trade_no' => $refund_trade_no,
            'refund_money' => $refund_money,
            'refund_way' => $refund_way,
            'buyer_id' => $buyer_id,
            'refund_time' => time(),
            'website_id' => $this->website_id,
            'remark' => $remark
        );
        $res = $model->save($data);
        return $res;
    }

    /**
     * ????????????
     *
     * @param unknown $order_id
     */
    public function pickupOrder($order_id, $assistantId)
    {
        // ???????????????????????????
        $checked = $this->order->getInfo(['order_id' => $order_id], 'order_id');
        if (!$checked) {
            return 0;
        }
        $this->order->startTrans();
        try {
            $data_take_delivery = array(
                'shipping_status' => 2,
                'order_status' => 3,
                'sign_time' => time(),
                'assistant_id' => $assistantId
            );
            $order_model = new VslOrderModel();
            $order_model->save($data_take_delivery, [
                'order_id' => $order_id
            ]);
            $this->addOrderAction($order_id, $this->uid, '????????????', 1);
            $order_goods_model = new VslOrderGoodsModel();
            $order_goods_model->save([
                'shipping_status' => 2
            ], [
                'order_id' => $order_id
            ]);
            $this->order->commit();
            return 1;
        } catch (\Exception $e) {
            recordErrorLog($e);
            $this->order->rollback();
            return $e->getMessage();
        }
    }

    /**
     * ????????????????????????
     *
     * @param unknown $type_id
     */
    public function getOrderTypeList()
    {
        $order_type = array(
            array(
                'type_id' => '1',
                'type_name' => '????????????'
            )
        );
        if (getAddons('microshop', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '2',
                    'type_name' => '????????????'
                ),
                array(
                    'type_id' => '3',
                    'type_name' => '????????????'
                ),
                array(
                    'type_id' => '4',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('groupshopping', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '5',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('seckill', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '6',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('presell', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '7',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('bargain', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '8',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('smashegg', $this->website_id, $this->instance_id) || getAddons('scratchcard', $this->website_id, $this->instance_id) || getAddons('wheelsurf', $this->website_id, $this->instance_id) || getAddons('paygift', $this->website_id, $this->instance_id) || getAddons('followgift', $this->website_id) || getAddons('festivalcare', $this->website_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '9',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('integral', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '10',
                    'type_name' => '????????????'
                )
            ));
        }
        if (getAddons('luckyspell', $this->website_id, $this->instance_id)) {
            $order_type = array_merge($order_type, array(
                array(
                    'type_id' => '15',
                    'type_name' => '??????????????????'
                )
            ));
        }
        return $order_type;
    }

    /**
     * ????????????????????????
     *
     */
    public function pointDeductionOrder($sku_lists, $is_deduction, $shipping_type = 2, $website_id = '', $uid = '',$sub_point = 0)
    {
        $config = new Config();
        if (!$website_id) $website_id = $this->website_id;
        if (!$uid) $uid = $this->uid;
        $info = $config->getShopConfig(0, $website_id);
        $info['convert_rate'] = (float)$info['convert_rate'];
        if(!$info['convert_rate']){
            $info['convert_rate'] = 0;
        }
        $member = new MemberService();
        $member_account = $member->getMemberAccount($uid, $website_id);
        $data = [];
        $member_account['point'] = $member_account['point'] - $sub_point; //????????????????????????
        $data['total_deduction_money'] = $total_deduction_money = $total_price = 0;
        $data['total_deduction_point'] = $total_deduction_point = 0;
        $data['all_point'] = $member_account['point'];
        if ($sku_lists && $info['is_point_deduction'] == 1 && $is_deduction == 1 && $member_account['point'] > 0) {/*??????????????????*/
            foreach ($sku_lists as $sku_id => $sku_info) {
                $sku_lists[$sku_id]['deduction_money'] = 0;
                $sku_lists[$sku_id]['deduction_point'] = 0;
                $sku_lists[$sku_id]['deduction_freight_point'] = 0;
                $real_money = $sku_info['discount_price'] * $sku_info['num']; //??????????????? discount_price
                if (!empty($sku_info['presell_id'])) {
                    $real_money = $sku_info['price'] * $sku_info['num'];//?????????
                }
                if($sku_info['receive_goods_code_data']){
                    $real_money = $sku_info['real_money'] * $sku_info['num'];
                }else{
                    if (!empty($sku_info['full_cut_sku_percent']) && !empty($sku_info['full_cut_sku_amount'])) {
                        $real_money -= $sku_info['full_cut_sku_percent'] * $sku_info['full_cut_sku_amount'];//?????????????????????
                    }
                    if (!empty($sku_info['coupon_sku_percent_amount']) && getAddons('coupontype', $this->website_id)) {
                        $real_money -= $sku_info['coupon_sku_percent_amount'];//?????????????????????
                    }
                }
                if ($shipping_type == 1) {
                    $real_money = $real_money + $sku_info['shipping_fee'];
                }
                $real_money = ($real_money > 0) ? $real_money : 0;
                $sku_lists[$sku_id]['real_money'] = $real_money;
                
                if ($sku_info['point_deduction_max'] > 0 || $sku_info['point_deduction_max'] == '') {
                    $price = 0;
                    if ($info['point_deduction_calculation'] == 1) {//????????????
                        if ($shipping_type == 1) {
                            //$price = $sku_info['num'] * ($sku_info['price'] + $sku_info['shipping_fee']);
                            $price = $sku_info['num'] * $sku_info['price'] + $sku_info['shipping_fee'];
                        } else {
                            $price = $sku_info['num'] * $sku_info['price'];
                        }
                    } elseif ($info['point_deduction_calculation'] == 2) {//????????????
                        $price = $sku_info['num'] * $sku_info['price'];
                    } elseif ($info['point_deduction_calculation'] == 3) {//????????????
                        $price = $real_money;
                    }
                    if ($sku_info['point_deduction_max'] > 0) {
                        $deduction_money = $price * $sku_info['point_deduction_max'] / 100;
                    } else {
                        $deduction_money = $price * $info['point_deduction_max'] / 100;
                    }
                    
                    $sku_lists[$sku_id]['deduction_money'] = $info['convert_rate'] ? round(floor($deduction_money * $info['convert_rate']) / $info['convert_rate'], 2) : $deduction_money;
                    $sku_lists[$sku_id]['deduction_point'] = floor($deduction_money * $info['convert_rate']);
                    //?????????????????????
                    if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                        $deduction_freight_money = $real_money - $sku_info['shipping_fee'] - $sku_lists[$sku_id]['deduction_money'];
                        if ($deduction_freight_money < 0 && ($info['point_deduction_calculation'] == 1 || $info['point_deduction_calculation'] == 3)) {
                            $sku_lists[$sku_id]['deduction_freight_point'] = floor((0 - $deduction_freight_money) * $info['convert_rate']);
                        }
                    }
                }
                
                $total_price += $real_money;
                $total_deduction_money += $sku_lists[$sku_id]['deduction_money'];
                $total_deduction_point += $sku_lists[$sku_id]['deduction_point'];
                //                # ?????????????????? - ??????????????????
                //                $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money'];
                $sku_lists[$sku_id]['is_deduction'] = 1;
            }
            unset($sku_info);
            
            if ($total_deduction_point > $member_account['point']) {//?????????????????????
                $data['total_deduction_money'] = $info['convert_rate'] ? round($member_account['point'] / $info['convert_rate'], 2) : $member_account['point'];
                $data['total_deduction_point'] = $member_account['point'];
                $deduction_money = $data['total_deduction_money'];
                foreach ($sku_lists as $sku_id => $sku_info) {
                    $sku_lists[$sku_id]['deduction_money'] = 0;
                    $sku_lists[$sku_id]['deduction_point'] = 0;
                    $sku_lists[$sku_id]['deduction_freight_point'] = 0;
                    if ($deduction_money > 0) {
                        $deduction_money = $sku_info['real_money'] - $deduction_money;
                        if ($deduction_money == 0) {
                            $sku_lists[$sku_id]['deduction_money'] = $sku_info['real_money'];
                            $deduction_money = 0;
                        } else if ($deduction_money > 0) {
                            $sku_lists[$sku_id]['deduction_money'] = $sku_info['real_money'] - $deduction_money;
                            $deduction_money = 0;
                        } else if ($deduction_money < 0) {
                            $sku_lists[$sku_id]['deduction_money'] = $sku_info['real_money'];
                            $deduction_money = 0 - $deduction_money;
                        }
                    }
                    $sku_lists[$sku_id]['deduction_point'] = floor($sku_lists[$sku_id]['deduction_money'] * $info['convert_rate']);
                    //?????????????????????
                    if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                        $deduction_freight_money = $sku_info['real_money'] - $sku_info['shipping_fee'] - $sku_lists[$sku_id]['deduction_money'];
                        if ($deduction_freight_money < 0 && ($info['point_deduction_calculation'] == 1 || $info['point_deduction_calculation'] == 3)) {
                            $sku_lists[$sku_id]['deduction_freight_point'] = floor((0 - $deduction_freight_money) * $info['convert_rate']);
                        }
                    }
                    # ?????????????????? - ??????????????????
                    $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money'];
                }
                unset($sku_info);
            } else if ($total_deduction_money > $total_price) {//????????????????????????
                
                $total_deduction_money = 0;
                $total_deduction_point = 0;
                foreach ($sku_lists as $sku_id => $sku_info) {
                    $sku_lists[$sku_id]['deduction_money'] = 0;
                    $sku_lists[$sku_id]['deduction_point'] = 0;
                    $sku_lists[$sku_id]['deduction_freight_point'] = 0;
                    if ($sku_info['point_deduction_max'] > 0 || $sku_info['point_deduction_max'] == '') {
                        if ($sku_info['point_deduction_max'] > 0) {
                            $deduction_money = $sku_info['real_money'] * $sku_info['point_deduction_max'] / 100;
                        } else {
                            $deduction_money = $sku_info['real_money'] * $info['point_deduction_max'] / 100;
                        }
                        $sku_lists[$sku_id]['deduction_money'] = $info['convert_rate'] ? round(floor($deduction_money * $info['convert_rate']) / $info['convert_rate'], 2) : $deduction_money;
                        $sku_lists[$sku_id]['deduction_point'] = floor($deduction_money * $info['convert_rate']);
                        //?????????????????????
                        if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                            $deduction_freight_money = $sku_info['real_money'] - $sku_info['shipping_fee'] - $sku_lists[$sku_id]['deduction_money'];
                            if ($deduction_freight_money < 0 && ($info['point_deduction_calculation'] == 1 || $info['point_deduction_calculation'] == 3)) {
                                $sku_lists[$sku_id]['deduction_freight_point'] = floor((0 - $deduction_freight_money) * $info['convert_rate']);
                            }
                        }
                    }
                    $total_deduction_money += $sku_lists[$sku_id]['deduction_money'];
                    $total_deduction_point += $sku_lists[$sku_id]['deduction_point'];
            
                    # ?????????????????? - ??????????????????
                    $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money'];
                }
                unset($sku_info);
                $data['total_deduction_money'] = $total_deduction_money;
                $data['total_deduction_point'] = $total_deduction_point;
            } else {
                
                foreach ($sku_lists as $sku_id => $sku_info) {
                    $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money'];
                   
                }
                $data['total_deduction_money'] = $total_deduction_money;
                $data['total_deduction_point'] = $total_deduction_point;
            }
    
            $data['sku_info'] = $sku_lists;
        }else{/*??????????????????????????????*/
            
            $temp_sku_lists = $sku_lists;//??????????????????????????????sku????????????????????????????????? TODO...???????????????????????????
            foreach ($sku_lists as $sku_id => $sku_info) {
                $sku_lists[$sku_id]['deduction_money'] = 0;
                $sku_lists[$sku_id]['deduction_point'] = 0;
                $sku_lists[$sku_id]['deduction_freight_point'] = 0;
                $real_money = $sku_info['discount_price'] * $sku_info['num'];
                //??????:
                if (!empty($sku_info['presell_id'])) {
                    $real_money = $sku_info['price'] * $sku_info['num'];
                }
                if($sku_info['receive_goods_code_data']){
                    $real_money = $sku_info['real_money'] * $sku_info['num'];
                }else{
                if (!empty($sku_info['full_cut_sku_percent']) && !empty($sku_info['full_cut_sku_amount'])) {  //todo...????????????????????????????????????????????????
                    $real_money -= $sku_info['full_cut_sku_percent'] * $sku_info['full_cut_sku_amount'];
                    }
                    if (!empty($sku_info['coupon_sku_percent_amount']) && getAddons('coupontype', $this->website_id)) {
                        $real_money -= $sku_info['coupon_sku_percent_amount'];
                    }
                }
            //                if ($shipping_type == 1) {
            //                    $real_money = $real_money + $sku_info['shipping_fee'];
            //                }
                $real_money = ($real_money > 0) ? $real_money : 0;
                $sku_lists[$sku_id]['actual_real_money'] = $sku_lists[$sku_id]['real_money'] = $real_money;
                //?????????????????????real_money?????????????????????
                if ($shipping_type == 1) {
                    $real_money = $sku_lists[$sku_id]['actual_real_money'] = $sku_lists[$sku_id]['actual_real_money'] + $sku_info['shipping_fee'];
                }
    
                if ($sku_info['point_deduction_max'] > 0 || $sku_info['point_deduction_max'] == '') {
                    $price = 0;
                    if ($info['point_deduction_calculation'] == 1) {//????????????
                        if ($shipping_type == 1) {
                    //                            $price = $sku_info['num'] * ($sku_info['price'] + $sku_info['shipping_fee']);
                            $price = $sku_info['num'] * $sku_info['price'] + $sku_info['shipping_fee'];
                        } else {
                            $price = $sku_info['num'] * $sku_info['price'];
                        }
                    } elseif ($info['point_deduction_calculation'] == 2) {//????????????
                        $price = $sku_info['num'] * $sku_info['price'];
                    } elseif ($info['point_deduction_calculation'] == 3) {//????????????
                        $price = $real_money;
                    }
                    if ($sku_info['point_deduction_max'] > 0) {
                        $deduction_money = $price * $sku_info['point_deduction_max'] / 100;
                    } else {
                        $deduction_money = $price * $info['point_deduction_max'] / 100;
                    }
                    $sku_lists[$sku_id]['deduction_money'] = $info['convert_rate'] ? round(floor($deduction_money * $info['convert_rate']) / $info['convert_rate'], 2) : $deduction_money;
                    $sku_lists[$sku_id]['deduction_point'] = floor($deduction_money * $info['convert_rate']);
                    //?????????????????????
                    if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                        $deduction_freight_money = $real_money - $sku_info['shipping_fee'] - $sku_lists[$sku_id]['deduction_money'];
                        if ($deduction_freight_money < 0 && ($info['point_deduction_calculation'] == 1 || $info['point_deduction_calculation'] == 3)) {
                            $sku_lists[$sku_id]['deduction_freight_point'] = floor((0 - $deduction_freight_money) * $info['convert_rate']);
                        }
                    }
                }
                $total_price += $real_money;
                $total_deduction_money += $sku_lists[$sku_id]['deduction_money'];
                $total_deduction_point += $sku_lists[$sku_id]['deduction_point'];
    
                # ?????????????????? - ??????????????????
                //                $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money']; //by sgw ????????????real_money?????????
            }
            unset($sku_info);
            if ($total_deduction_point > $member_account['point']) {//?????????????????????
                $data['total_deduction_money'] = $info['convert_rate'] ? round($member_account['point'] / $info['convert_rate'], 2) : $member_account['point'];
                $data['total_deduction_point'] = $member_account['point'];
                $deduction_money = $data['total_deduction_money'];
                foreach ($sku_lists as $sku_id => $sku_info) {
                    $sku_lists[$sku_id]['deduction_money'] = 0;
                    $sku_lists[$sku_id]['deduction_point'] = 0;
                    $sku_lists[$sku_id]['deduction_freight_point'] = 0;
                    if ($deduction_money > 0) {
                        $deduction_money = $sku_info['real_money'] - $deduction_money;
                        if ($deduction_money == 0) {
                            $sku_lists[$sku_id]['deduction_money'] = $sku_info['real_money'];
                            $deduction_money = 0;
                        } else if ($deduction_money > 0) {
                            $sku_lists[$sku_id]['deduction_money'] = $sku_info['real_money'] - $deduction_money;
                            $deduction_money = 0;
                        } else if ($deduction_money < 0) {
                            $sku_lists[$sku_id]['deduction_money'] = $sku_info['real_money'];
                            $deduction_money = 0 - $deduction_money;
                        }
                    }
                    $sku_lists[$sku_id]['deduction_point'] = floor($sku_lists[$sku_id]['deduction_money'] * $info['convert_rate']);
                    //?????????????????????
                    if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                        $deduction_freight_money = $sku_info['real_money'] - $sku_info['shipping_fee'] - $sku_lists[$sku_id]['deduction_money'];
                        if ($deduction_freight_money < 0 && ($info['point_deduction_calculation'] == 1 || $info['point_deduction_calculation'] == 3)) {
                            $sku_lists[$sku_id]['deduction_freight_point'] = floor((0 - $deduction_freight_money) * $info['convert_rate']);
                        }
                    }
                    # ?????????????????? - ??????????????????
                //                    $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money'];//by sgw ????????????real_money?????????
                }
                unset($sku_info);
            } else if ($total_deduction_money > $total_price) {//????????????????????????
                $total_deduction_money = 0;
                $total_deduction_point = 0;
                foreach ($sku_lists as $sku_id => $sku_info) {
                    $sku_lists[$sku_id]['deduction_money'] = 0;
                    $sku_lists[$sku_id]['deduction_point'] = 0;
                    $sku_lists[$sku_id]['deduction_freight_point'] = 0;
                    if ($sku_info['point_deduction_max'] > 0 || $sku_info['point_deduction_max'] == '') {
                        if ($sku_info['point_deduction_max'] > 0) {
                            $deduction_money = $sku_info['real_money'] * $sku_info['point_deduction_max'] / 100;
                        } else {
                            $deduction_money = $sku_info['real_money'] * $info['point_deduction_max'] / 100;
                        }
                        $sku_lists[$sku_id]['deduction_money'] = $info['convert_rate'] ? round(floor($deduction_money * $info['convert_rate']) / $info['convert_rate'], 2) : $deduction_money;
                        $sku_lists[$sku_id]['deduction_point'] = floor($deduction_money * $info['convert_rate']);
                        //?????????????????????
                        if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                            $deduction_freight_money = $sku_info['real_money'] - $sku_info['shipping_fee'] - $sku_lists[$sku_id]['deduction_money'];
                            if ($deduction_freight_money < 0 && ($info['point_deduction_calculation'] == 1 || $info['point_deduction_calculation'] == 3)) {
                                $sku_lists[$sku_id]['deduction_freight_point'] = floor((0 - $deduction_freight_money) * $info['convert_rate']);
                            }
                        }
                    }
                    $total_deduction_money += $sku_lists[$sku_id]['deduction_money'];
                    $total_deduction_point += $sku_lists[$sku_id]['deduction_point'];
            
                    # ?????????????????? - ??????????????????
                //                    $sku_lists[$sku_id]['real_money'] -= $sku_lists[$sku_id]['deduction_money'];//by sgw ????????????real_money?????????
                }
                unset($sku_info);
                $data['total_deduction_money'] = $total_deduction_money;
                $data['total_deduction_point'] = $total_deduction_point;
            } else {
                $data['total_deduction_money'] = $total_deduction_money;
                $data['total_deduction_point'] = $total_deduction_point;
            }
            //            $data['sku_info'] = $temp_sku_lists;
            $data['sku_info'] = $sku_lists;
        }
        //??????real_money
        
        foreach ($data['sku_info'] as $k => $sku_info) {
           
            //????????? ???????????????????????????real_money
            // ????????? -> ???????????? -> ???????????? -> ????????? -> ????????? -> ???????????? ->?????????-> ??????->?????? 
            //????????????????????? ???????????????????????? ???????????? 2020???12???31 ?????????????????????????????????-- ?????????????????????????????????*??????-??????+??????
            if ($is_deduction){
                $all_real_money = round($sku_info['real_money']-$sku_info['shipping_fee'],2);
                $real_money = round(($sku_info['real_money']-$sku_info['shipping_fee'])/$sku_info['num'],2);//real_money??????????????? //????????? ????????????????????? real_money?????????????????????????????????
                $act_real_money = round($sku_info['real_money']/$sku_info['num'],2);
            }else{
                $act_real_money = $real_money = round($sku_info['real_money']/$sku_info['num'],2);
                $all_real_money = $sku_info['real_money'];
            } 
            #???sku_info['real_money']+?????????
            if($act_real_money * $sku_info['num'] != $sku_info['real_money']){
                $remainder = bcsub($sku_info['real_money'],$act_real_money * $sku_info['num'],2);
                if(!isset($sku_info['remainder'])){
                    $data['sku_info'][$k]['remainder'] += bcadd($sku_info['remainder'],$remainder,2);;
                }else{
                    $data['sku_info'][$k]['remainder'] = $remainder;
                }
            }
            if(!isset($data['sku_info'][$k]['remainder'])){
                $data['sku_info'][$k]['remainder'] = 0; 
            }
            // 250 ??????????????? 3??? ?????? 250/3 ?????????   ?????????????????????????????? 
            // $real_money  = bcdiv($sku_info['real_money'],$sku_info['num'],2);
            //?????? ??????+0.01 
            if ($sku_info['presell_id']){
                $data['sku_info'][$k]['real_money'] = $real_money - $sku_info['shipping_fee'];
                if(!isset($data['sku_info'][$k]['all_real_money'])){
                    $data['sku_info'][$k]['all_real_money'] = $all_real_money - $sku_info['shipping_fee'];
                }
            }else{
                $data['sku_info'][$k]['real_money'] = $real_money;
                if(!isset($data['sku_info'][$k]['all_real_money'])){
                    $data['sku_info'][$k]['all_real_money'] = $all_real_money;
                }
                
            }
        }
        
        return $data;
    }
    
    /**
     * ????????????????????????
     *
     */
    public function pointReturnOrder($sku_lists, $shipping_type = 2, $website_id = '')
    {
        $config = new Config();
        if (!$website_id) $website_id = $this->website_id;
        $info = $config->getShopConfig(0, $website_id);
        $data = [];
        $data['total_return_point'] = $total_return_point = 0;
        if ($sku_lists && $info['is_point'] == 1) {
            foreach ($sku_lists as $sku_id => $sku_info) {
                $sku_lists[$sku_id]['return_point'] = 0;
                $sku_lists[$sku_id]['return_freight_point'] = 0;
                $real_money = $sku_info['discount_price'] * $sku_info['num'];
                if (!empty($sku_info['presell_id'])) {
                    $real_money = $sku_info['price'] * $sku_info['num'];
                }
                if($sku_info['receive_goods_code_data']){
                    $real_money = $sku_info['real_money'] * $sku_info['num'];
                }else{
                if (!empty($sku_info['coupon_sku_percent_amount']) && getAddons('coupontype', $this->website_id)) {
                    $real_money -= $sku_info['coupon_sku_percent_amount'];
                }
                if (!empty($sku_info['full_cut_sku_percent']) && !empty($sku_info['full_cut_sku_amount'])) {
                    $real_money -= $sku_info['full_cut_sku_percent'] * $sku_info['full_cut_sku_amount'];
                }
                }
                
                //                if ($shipping_type == 1) {
                //                    $real_money = $real_money + $sku_info['shipping_fee'];
                //                }
                $real_money = ($real_money > 0) ? $real_money : 0;
                $sku_lists[$sku_id]['actual_real_money'] = $real_money;
                //?????????????????????real_money?????????????????????
                if ($shipping_type == 1) {
                    $real_money = $sku_lists[$sku_id]['actual_real_money'] = $sku_lists[$sku_id]['actual_real_money'] + $sku_info['shipping_fee'];
                }

                if ($sku_info['point_return_max'] > 0 || $sku_info['point_return_max'] == '') {
                    $price = 0;
                    if ($info['integral_calculation'] == 1) {//????????????
                        if ($shipping_type == 1) {
                            $price = $sku_info['num'] * ($sku_info['price'] + $sku_info['shipping_fee']);
                        } else {
                            $price = $sku_info['num'] * $sku_info['price'];
                        }
                    } elseif ($info['integral_calculation'] == 2) {//????????????
                        $price = $sku_info['num'] * $sku_info['price'];
                    } elseif ($info['integral_calculation'] == 3) {//????????????
                        $price = $real_money;
                    }
                    if ($sku_info['point_return_max'] > 0) {
                        $return_money = $price * $sku_info['point_return_max'] / 100;
                    } else {
                        $return_money = $price * $info['point_invoice_tax'] / 100;
                    }
                    $sku_lists[$sku_id]['return_point'] = floor($return_money);
                    //?????????????????????
                    if ($shipping_type == 1 && $sku_info['shipping_fee'] > 0) {
                        if ($info['integral_calculation'] == 1 || $info['integral_calculation'] == 3) {
                            if ($sku_info['point_return_max'] > 0) {
                                $sku_lists[$sku_id]['return_freight_point'] = $sku_info['shipping_fee'] * $sku_info['point_return_max'] / 100;
                            } else {
                                $sku_lists[$sku_id]['return_freight_point'] = $sku_info['shipping_fee'] * $info['point_invoice_tax'] / 100;
                            }
                        }
                    }
                }
                $total_return_point += $sku_lists[$sku_id]['return_point'];
            }
            unset($sku_info);
            $data['total_return_point'] = $total_return_point;
        }
    
        //??????real_money
        $data['sku_info'] = $sku_lists;
        return $data;
    }
    
    /**
     * ????????????????????????????????????????????????
     * @param $datas
     * @return array
     */
    public function fiflterNotSupplierOrderGoods ($datas, $supplier_id)
    {
        $return_data = [];
        foreach ($datas as $data){
            if (!$data['supplier_id'] || $data['supplier_id'] != $supplier_id){
                continue;
            }
            $return_data[] = $data;
        }
        return $return_data;
    }
    /***
     * ?????????????????????????????????
     */
    public function autoOrderComplete($order_id){
        $order_info = $this->order->getInfo(['order_id' => $order_id], '*');
        //??????order_id???sku_id
        $order_goods_info = $this->order->alias('o')
        ->where(['o.order_id' => $order_id])
        ->join('vsl_order_goods og', 'og.order_id=o.order_id', 'LEFT')
        ->select();
        $merchants_status = getAddons('merchants', $order_info['website_id']);
        if($merchants_status && $order_info['shop_id']) {
            //?????????????????????
            hook('calculateMerchantsBonus',['order_id' => $order_id,'website_id' => $order_info['website_id']]);
        }
        //???????????????????????????????????????????????????/?????????
        if ($order_info['card_store_id']>0 && getAddons('store', $order_info['website_id'], $order_info['shop_id'])) {
            //???????????????
            
            $member_card = new MemberCardService();
            $rs = $member_card->saveData($order_id,$order_info['shop_id']);
            debugLog($rs,'autoOrderComplete-3');
            if ($rs) {
                // ??????????????????
                $order = new VslOrderModel();
                $rs2= $order->save(['order_status' => 4, 'card_ids' => $rs], ['order_id' => $order_id]);
                debugLog($rs2,'autoOrderComplete-4');
                $ServiceOrder = new ServiceOrder();
                $rs3 = $ServiceOrder->orderComplete($order_id, $order_info['website_id'], 1);
                debugLog($rs2,'autoOrderComplete-5');
            }
        }
        //???????????????????????????????????????????????????????????????
        if(count($order_goods_info) == 1) {
            if($order_goods_info[0]['goods_type'] == 4) {
                $order = new VslOrderModel();
                $order->save(['order_status' => 4], ['order_id' => $order_id]);
                $ServiceOrder = new ServiceOrder();
                $retval = $ServiceOrder->orderComplete($order_id, $order_info['website_id'], 1);
                if($retval == 1) {
                    //?????????????????????,?????????????????????????????????????????????????????????????????????????????????????????????????????????
                    $order_goods_model = new VslOrderGoodsModel();
                    $order_goods_condition= [
                        'goods_id' => $order_goods_info[0]['goods_id'],
                        'goods_type'=>$order_goods_info[0]['goods_type'],
                        'buyer_id'=>$order_goods_info[0]['buyer_id'],
                    ];
                    $order_ids = $order_goods_model->getQuery($order_goods_condition,'order_id','');
                    if($order_ids) {
                        foreach ($order_ids as $key => $val) {
                            $order_status = $order->Query(['order_id' => $val['order_id']],'order_status')[0];
                            if($order_status == 0) {
                                //??????????????????
                                $ServiceOrder = new ServiceOrder();
                                $ServiceOrder->orderClose($val['order_id'],1,$order_info['website_id'],$order_info['shop_id']);
                            }
                        }
                        unset($val);
                    }
                }
            }
        }
        //????????????????????????????????????????????????????????????
        if (count($order_goods_info) == 1) {
            if ($order_goods_info[0]['goods_type'] == 5) {
                if (getAddons('electroncard', $order_info['website_id'])) {
                    $goodsSer = new \data\service\Goods();
                    $goods_info = $goodsSer->getGoodsDetailById($order_goods_info[0]['goods_id']);
                    $electroncard_server = new Electroncard();
                    $electroncard_data_id = $electroncard_server->randomElectroncardData($goods_info['electroncard_base_id'], $order_goods_info[0]['num']);

                    //????????????????????????????????????,???????????????????????????
                    $res = $this->order->save(['order_status' => 4, 'electroncard_data_id' => $electroncard_data_id], ['order_id' => $order_id]);
                    if ($res) {
                        $ServiceOrder = new ServiceOrder();
                        $ServiceOrder->orderComplete($order_id, $order_info['website_id'], 1);
                        //???????????????????????????
                        $electroncard_server->syncElectroncardStockToGoods($goods_info['electroncard_base_id']);
                        //????????????
                        $electroncard_server->pushMessage($order_info['buyer_id'], $electroncard_data_id, $order_goods_info[0]['goods_name'],$order_info['website_id'],$order_info['shop_id']);
                    }
                }
            }
        }
        //??????????????????????????????,?????????????????????????????????????????????????????????
        if (!$order_info['group_id']) {
            if (count($order_goods_info) == 1) {
                if ($order_goods_info[0]['goods_type'] == 3) {
                    $ServiceOrder = new ServiceOrder();
                    $order_service = new OrderService();
                    $goodsSer = new \data\service\Goods();
                    $delivery_type = $goodsSer->getGoodsDetailById($order_goods_info[0]['goods_id'], 'goods_id,goods_name,price,collects,delivery_type')['delivery_type'];
                    if ($delivery_type && $delivery_type != 4) {
                        if ($delivery_type == 1) {
                            //????????????
                            $order_service->orderGoodsDelivery($order_id, $order_goods_info[0]['order_goods_id'],0, $order_info['website_id']);
                        } elseif ($delivery_type == 2) {
                            //???????????????????????????
                            $order_service->orderGoodsDelivery($order_id, $order_goods_info[0]['order_goods_id'],0, $order_info['website_id']);
                            $order_service->OrderTakeDelivery($order_id);
                        } elseif ($delivery_type == 3) {
                            
                            //???????????????????????????
                            $order_service->orderGoodsDelivery($order_id, $order_goods_info[0]['order_goods_id'],0, $order_info['website_id']);
                            $order_service->OrderTakeDelivery($order_id,$order_info['website_id']);
                            $ServiceOrder->orderComplete($order_id, $order_info['website_id'], 1);
                        }
                    }
                }
            }
        }
    }
    
    /**
     * ??????????????????????????????????????????
     * @param $payment_info
     * @param $total_deduction_money
     * @param $is_deduction
     * @return mixed
     */
    public function pointDeductionOrderForPresell ($payment_info,$total_deduction_money,$is_deduction)
    {
//        p($payment_info,'????????????');die;
        if($is_deduction==0){return $payment_info;}
        if($payment_info['presell_info']){/*?????????????????????*/
//            $presell_deduct_money = 0;
            $payment_info['presell_info']['firstmoney'] =  bcmul($payment_info['presell_info']['firstmoney'],$payment_info['presell_info']['goods_num'],2);
            $final_deduct = bcsub($payment_info['presell_info']['final_real_money'],$total_deduction_money,2);
            if ($final_deduct>=0){
                $payment_info['presell_info']['final_real_money'] = $final_deduct;
//                $presell_deduct_money += $total_deduction_money;
            }else{
                //????????????
                $first_deduct = bcsub($payment_info['presell_info']['firstmoney'],abs($final_deduct),2);
                if ($first_deduct>=0){
                    $payment_info['presell_info']['firstmoney'] = $first_deduct;
//                    $presell_deduct_money += abs($final_deduct);
                    $payment_info['total_amount'] = bcsub($payment_info['total_amount'],abs($final_deduct),2);
                }else{
                    $payment_info['presell_info']['firstmoney'] = 0;
//                    $presell_deduct_money += $payment_info['presell_info']['firstmoney'];
                    $payment_info['total_amount'] = bcsub($payment_info['total_amount'],$payment_info['presell_info']['firstmoney'],2);
                }
            }
            
//            p($presell_deduct_money,'$presell_deduct_money');die;
//            $payment_info['total_amount'] = bcsub($payment_info['total_amount'], $presell_deduct_money,2);
            $payment_info['presell_info']['firstmoney'] = roundLengthNumber($payment_info['presell_info']['firstmoney']/$payment_info['presell_info']['goods_num'],2);
        }
        return $payment_info;
    }
}