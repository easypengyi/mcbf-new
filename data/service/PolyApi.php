<?php

namespace data\service;

use data\model\AlbumPictureModel as AlbumPictureModel;
use data\model\DistrictModel;
use data\model\VslExpressCompanyModel;
use data\model\VslGoodsSkuModel;
use data\model\VslMemberCardModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use data\service\Order as Order;
use data\service\MemberCard as MemberCard;
use addons\store\server\Store as Store;

/**
 * 对接菠萝派ERP需要的接口服务层
 */
class PolyApi extends BaseService
{
    /**
     * 订单下载
     */
    public function getOrder($bizcontent)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $district_model = new DistrictModel();

        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        if ($bizcontent['PlatOrderNo']) {
            //根据订单号查询
            $condition['order_no'] = $bizcontent['PlatOrderNo'];
        } else {
            //按条件查询
            if ($bizcontent['OrderStatus'] === 'JH_01') {
                //等待买家付款
                $condition['order_status'] = 0;
            } elseif ($bizcontent['OrderStatus'] === 'JH_02') {
                //等待卖家发货
                $condition['order_status'] = 1;
            } elseif ($bizcontent['OrderStatus'] === 'JH_03') {
                //等待买家确认收货
                $condition['order_status'] = 2;
            } elseif ($bizcontent['OrderStatus'] === 'JH_04') {
                //交易成功
                $condition['order_status'] = 4;
            } elseif ($bizcontent['OrderStatus'] === 'JH_05') {
                //交易关闭
                $condition['order_status'] = 5;
            } elseif ($bizcontent['OrderStatus'] === 'JH_99') {
                //所有订单
                $condition['order_status'] = ['IN', [0, 1, 2, 4, 5]];
            }
            if ($bizcontent['StartTime'] && $bizcontent['EndTime']) {
                //时间格式转换
                $condition['create_time'][] = [
                    '>',
                    strtotime($bizcontent['StartTime'])
                ];
                $condition['create_time'][] = [
                    '<',
                    strtotime($bizcontent['EndTime'])
                ];
            }
        }
        $data = $order_model->pageQuery($bizcontent['PageIndex'], $bizcontent['PageSize'], $condition, '', '*');
        $order_list = $data['data'];
        if ($order_list) {
            foreach ($order_list as $k => $v) {
                //订单交易状态
                if ($v['order_status'] == 0) {
                    $trade_status = 'JH_01';
                } elseif ($v['order_status'] == 1) {
                    $trade_status = 'JH_02';
                } elseif ($v['order_status'] == 2) {
                    $trade_status = 'JH_03';
                } elseif ($v['order_status'] == 4) {
                    $trade_status = 'JH_04';
                } elseif ($v['order_status'] == 5) {
                    $trade_status = 'JH_05';
                }
                //省市区转换
                $address_info = $district_model::get($v['receiver_district'], ['city.province']);
                //配送方式
                if ($v['shipping_type'] == 1) {
                    $send_style = '快递配送';
                } elseif ($v['shipping_type'] == 2) {
                    $send_style = '线下自提';
                }
                //支付方式
                if ($v['payment_type'] == 1 || $v['payment_type'] == 2 || $v['payment_type'] == 16 || $v['payment_type'] == 17) {
                    $Should_pay_type = '担保交易';
                } elseif ($v['payment_type'] == 4) {
                    $Should_pay_type = '货到付款';
                } elseif ($v['payment_type'] == 5) {
                    $Should_pay_type = '客户预存款';
                } elseif ($v['payment_type'] == 10) {
                    $Should_pay_type = '担保交易';
                } elseif ($v['payment_type'] == 3) {
                    $Should_pay_type = '银行收款';
                }

                $res = [
                    'PlatOrderNo' => $v['order_no'],
                    'tradeStatus' => $trade_status,
                    'tradeStatusdescription' => '',
                    'tradetime' => date("Y-m-d H:i:s", $v['create_time']),
                    'payorderno' => $v['out_trade_no'],
                    'country' => '',
                    'province' => $address_info->city->province->province_name,
                    'city' => $address_info->city->city_name,
                    'area' => $address_info->district_name,
                    'town' => '',
                    'address' => $v['receiver_address'],
                    'zip' => '',
                    'phone' => '',
                    'mobile' => $v['receiver_mobile'],
                    'email' => '',
                    'customerremark' => $v['buyer_message'],
                    'sellerremark' => $v['seller_memo'],
                    'postfee' => $v['shipping_money'],
                    'goodsfee' => $v['goods_money'],
                    'totalmoney' => $v['order_money'],
                    'favourablemoney' => $v['platform_promotion_money'] + $v['shop_promotion_money'],
                    'commissionvalue' => '',
                    'taxamount' => '',
                    'tariffamount' => '',
                    'addedvalueamount' => '',
                    'consumptiondutyamount' => '',
                    'sendstyle' => $send_style,
                    'qq' => '',
                    'paytime' => date("Y-m-d H:i:s", $v['pay_time']),
                    'invoicetitle' => '',
                    'taxpayerident' => '',
                    'invoicetype' => '',
                    'invoicecontent' => '',
                    'registeredaddress' => '',
                    'registeredphone' => '',
                    'depositbank' => '',
                    'bankaccount' => '',
                    'codservicefee' => '',
                    'currencycode' => '',
                    'cardtype' => '',
                    'idcard' => '',
                    'idcardtruename' => '',
                    'receivername' => $v['receiver_name'],
                    'nick' => $v['user_name'],
                    'whsecode' => '',
                    'IsHwgFlag' => 0,
                    'ShouldPayType' => $Should_pay_type,
                ];
                //订单下的商品信息
                $order_goods_list = $order_goods_model->getQuery(['order_id' => $v['order_id']], '*', '');
                foreach ($order_goods_list as $key => $val) {
                    //退款状态
                    if ($val['refund_status'] == 0) {
                        $refund_status = 'JH_07';
                    } elseif ($val['refund_status'] == 1) {
                        $refund_status = 'JH_01';
                    } elseif ($val['refund_status'] == 2) {
                        $refund_status = 'JH_02';
                    } elseif ($val['refund_status'] == 3) {
                        $refund_status = 'JH_03';
                    } elseif ($val['refund_status'] == -1 || $val['refund_status'] == -3) {
                        $refund_status = 'JH_04';
                    } elseif ($val['refund_status'] == 5) {
                        $refund_status = 'JH_06';
                    }

                    $result = [
                        'ProductId' => $val['goods_id'],
                        'suborderno' => $val['order_goods_id'],
                        'tradegoodsno' => $val['goods_id'],
                        'tradegoodsname' => $val['goods_name'],
                        'tradegoodsspec' => $val['sku_name'],
                        'goodscount' => $val['num'],
                        'price' => $val['price'],
                        'discountmoney' => $val['price'] - $val['actual_price'],
                        'taxamount' => '',
                        'refundStatus' => $refund_status,
                        'Status' => '',
                        'remark' => $val['memo']
                    ];
                    $res['goodinfos'][] = $result;
                }
                unset($val);
                $orders[] = $res;
            }
            unset($v);
            $total_count = $data['total_count'];
        } else {
            $orders = [];
            $total_count = 0;
        }
        return ['orders' => $orders, 'total_count' => $total_count];
    }

    /**
     * 退款检测
     */
    public function checkRefundStatus($bizcontent)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();

        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        $order_id = $order_model->Query(['order_no' => $bizcontent['OrderID']], 'order_id')[0];
        if (empty($order_id)) {
            return ['code' => 40000, 'message' => '此订单不存在'];
        }

        $order_goods_list = $order_goods_model->getQuery(['order_id' => $order_id], '', '');
        //判断有没有退款订单
        foreach ($order_goods_list as $k => $v) {
            if ($v['refund_status'] != 0) {
                //退款状态
                if ($v['refund_status'] == 1) {
                    $refund_status = 'JH_01';
                } elseif ($v['refund_status'] == 2) {
                    $refund_status = 'JH_02';
                } elseif ($v['refund_status'] == 3) {
                    $refund_status = 'JH_03';
                } elseif ($v['refund_status'] == -1 || $v['refund_status'] == -3) {
                    $refund_status = 'JH_04';
                } elseif ($v['refund_status'] == 5) {
                    $refund_status = 'JH_06';
                }
                $data = [
                    'refundno' => $v['order_goods_id'],
                    'ProductName' => $v['goods_name'],
                    'refundStatus' => $refund_status,
                    'refundStatusdescription' => ''
                ];
                $childrenrefundStatus[] = $data;
            }
        }
        unset($v);
        if (empty($childrenrefundStatus)) {
            //没有退款订单
            $childrenrefundStatus = [];
            $out_refund_status = 'JH_07';
        } else {
            $out_refund_status = 'JH_09';
        }

        return [
            'code' => 10000,
            'refundStatus' => $out_refund_status,
            'refundStatusdescription' => '',
            'childrenrefundStatus' => $childrenrefundStatus,
        ];
    }

    /**
     * 订单发货
     */
    public function send($bizcontent)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $order_service = new Order();
        $express_company_model = new VslExpressCompanyModel();
        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        $order_info = $order_model->getInfo(['order_no' => $bizcontent['PlatOrderNo']], '*');

        if (empty($order_info)) {
            return ['code' => 40000, 'message' => '此订单不存在'];
        }
        if ($order_info['order_status'] != 1) {
            return ['code' => 40000, 'message' => '操作失败，此订单状态已改变'];
        }
        if ($order_info['shipping_status'] > 0) {
            return ['code' => 40000, 'message' => '操作失败，此订单状态已发货'];
        }

        if ($bizcontent['IsSplit'] == 0) {
            //整单发货
            $order_goods_ids = $order_goods_model->getQuery(['order_id' => $order_info['order_id']], 'order_goods_id', '');
            foreach ($order_goods_ids as $k => $v) {
                $order_goods_id_array[] = $v['order_goods_id'];
            }
            unset($v);
            $order_goods_id_array = implode(',', $order_goods_id_array);

            if ($bizcontent['SendType'] && $bizcontent['SendType'] === 'JH_03') {
                //无需物流
                //调用发货接口
                $res = $order_service->orderGoodsDelivery($order_info['order_id'], $order_goods_id_array);
            } else {
                //需要物流
                //模糊搜索快递名称查找express_company_id
                $condition = [
                    'company_name' => ['like', $bizcontent['LogisticName'] . '%'],
                    'shop_id' => $this->instance_id
                ];
                $express_company_id = $express_company_model->Query($condition, 'co_id')[0];

                //调用发货接口
                $res = $order_service->orderDelivery($order_info['order_id'], $order_goods_id_array, $bizcontent['LogisticName'], 1, $express_company_id, $bizcontent['LogisticNo']);
            }
        } elseif ($bizcontent['IsSplit'] == 1) {
            //拆单发货
            $bizcontent['SubPlatOrderNo'] = explode('|', $bizcontent['SubPlatOrderNo']);
            foreach ($bizcontent['SubPlatOrderNo'] as $k => $v) {
                $order_goods_id_array[] = substr($v, 0, strrpos($v, ":"));
            }
            unset($v);
            $order_goods_id_array = implode(',', $order_goods_id_array);

            $condition = [
                'company_name' => ['like', $bizcontent['LogisticName'] . '%'],
                'shop_id' => $this->instance_id
            ];
            $express_company_id = $express_company_model->Query($condition, 'co_id')[0];

            //调用发货接口
            $res = $order_service->orderDelivery($order_info['order_id'], $order_goods_id_array, $bizcontent['LogisticName'], 1, $express_company_id, $bizcontent['LogisticNo']);
        }

        return ['code' => 10000, 'data' => $res];
    }

    /**
     * 修改订单备注
     */
    public function updateSellerMemo($bizcontent)
    {
        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        $order_model = new VslOrderModel();
        $order_info = $order_model->getInfo(['order_no' => $bizcontent['PlatOrderNo']], '*');

        if (empty($order_info)) {
            return ['code' => 40000, 'message' => '此订单不存在'];
        }

        $order_service = new Order();

        $data['order_id'] = $order_info['order_id'];
        $data['memo'] = $bizcontent['SellerMemo'];
        $data['uid'] = $this->uid;
        $data['create_time'] = time();

        $res = $order_service->addOrderSellerMemoNew($data);

        return ['code' => 10000, 'data' => $res];
    }

    /**
     * 核销订单
     */
    public function consume($bizcontent)
    {
        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        $order_model = new VslOrderModel();
        $order_info = $order_model->getInfo(['order_no' => $bizcontent['PlatOrderNo']], '*');

        if (empty($order_info)) {
            return ['code' => 40000, 'message' => '此订单不存在'];
        }

        //判断是什么类型核销码
        $code = substr($bizcontent['VerifyCode'], '0', 1);
        if ($code == 'A') {
            //实物
            if ($bizcontent['VerifyCode'] != $order_info['verification_code']) {
                return ['code' => 40000, 'message' => '核销码不正确'];
            }
            $store_server = new Store();
            $res = $store_server->pickupOrder($order_info['order_id'], 0);
        } elseif ($code == 'B') {
            //计时计次核销
            $member_card_model = new VslMemberCardModel();
            $card_code = $member_card_model->Query(['card_id' => $order_info['card_ids']], 'card_code')[0];
            if ($bizcontent['VerifyCode'] != $card_code) {
                return ['code' => 40000, 'message' => '核销码不正确'];
            }
            $member_card_server = new MemberCard();
            $res = $member_card_server->getCardUse($bizcontent['VerifyCode'], $bizcontent['StoreId'], 0);
        }

        return ['code' => 10000, 'data' => $res];
    }

    /**
     * 商品下载
     */
    public function downloadProduct($bizcontent)
    {
        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        if ($bizcontent['ProductId']) {
            //根据商品id查询
            $condition['goods_id'] = $bizcontent['ProductId'];
        } elseif ($bizcontent['ProductName']) {
            $condition['goods_name'] = ['like', $bizcontent['ProductName'] . '%'];
        } elseif ($bizcontent['Status']) {
            if ($bizcontent['Status'] == 'JH_01') {
                //已上架商品
                $condition['state'] = 1;
            } elseif ($bizcontent['Status'] == 'JH_02') {
                //已下架商品
                $condition['state'] = 0;
            } elseif ($bizcontent['Status'] == 'JH_99') {
                //所有商品
                $condition['state'] = ['IN', [0, 1]];
            }
        }

        $goods_sku_model = new VslGoodsSkuModel();
        $albumPictureModel = new AlbumPictureModel();
        $goodsSer = new Goods();
        $data = $goodsSer->getPageGoodsList($bizcontent['PageIndex'], $bizcontent['PageSize'], $condition, '', '*');
        $goods_lists = $data['data'];

        foreach ($goods_lists as $k => $v) {
            //商品信息
            $goods_data = [
                'PlatProductID' => $v['goods_id'],
                'name' => $v['goods_name'],
                'OuterID' => $v['goods_id'],
                'price' => $v['price'],
                'num' => $v['stock'],
                'whsecode' => '',
            ];
            $goods_data['pictureurl'] = $albumPictureModel->Query(['pic_id' => $v['picture']], 'pic_cover')[0];

            //sku信息
            $sku_lists = $goods_sku_model->getQuery(['goods_id' => $v['goods_id']], '*', '');
            foreach ($sku_lists as $key => $val) {
                $sku_data = [
                    'SkuID' => $val['sku_id'],
                    'skuOuterID' => $val['sku_id'],
                    'skuprice' => $val['price'],
                    'skuQuantity' => $val['stock'],
                    'skuname' => $val['sku_name'],
                    'skuproperty' => '',
                    'skupictureurl' => '',
                ];
                $goods_data['skus'][] = $sku_data;
            }
            $goods_list[] = $goods_data;
        }
        unset($val);
        unset($v);
        return [
            'totalcount' => $data['total_count'],
            'goodslist' => $goods_list,
        ];
    }

    /**
     *商品库存同步
     */
    public function syncStock($bizcontent)
    {
        //json转数组
        $bizcontent = json_decode($bizcontent, true);
        $goods_sku_model = new VslGoodsSkuModel();
        $goodsSer = new GoodsService();
        $goods_info = $goodsSer->getGoodsDetailById($bizcontent['PlatProductID']);
        if (empty($goods_info)) {
            return ['code' => 40000, 'message' => '此商品不存在'];
        }

        $update_data = [
            'stock' => $bizcontent['Quantity']
        ];

        if (empty($bizcontent['SkuID'])) {
            //修改主商品的库存
            $res = $goodsSer->updateGoods(['goods_id' => $bizcontent['PlatProductID']], $update_data, $bizcontent['PlatProductID']);
            $res1 = $goods_sku_model->save($update_data, ['goods_id' => $bizcontent['PlatProductID']]);
        } else {
            $redis = connectRedis();
            //修改指定规格库存
            $res = $goods_sku_model->save($update_data, ['goods_id' => $bizcontent['PlatProductID'], 'sku_id' => $bizcontent['SkuID']]);
            $sku_goods_info = $goods_sku_model->getInfo(['goods_id' => $bizcontent['PlatProductID'], 'sku_id' => $bizcontent['SkuID']], 'sku_id, goods_id');
            $system_sku_id = $sku_goods_info['sku_id'] ? : 0;
            $system_goods_id = $sku_goods_info['goods_id'] ? : 0;
            $goods_key = 'goods_'.$system_goods_id.'_'.$system_sku_id;
            if(!$redis->get($goods_key)){
                $redis->set($goods_key, $bizcontent['Quantity']);
            }
            $stock = $goods_sku_model->getSum(['goods_id' => $bizcontent['PlatProductID']], 'stock');
            $res1 = $goodsSer->updateGoods(['goods_id' => $bizcontent['PlatProductID']], ['stock' => $stock], $bizcontent['PlatProductID']);
        }

        if ($res && $res1) {
            return ['code' => 10000, 'Quantity' => $bizcontent['Quantity']];
        }
    }

    /**
     * 退货退款单下载
     */
    public function getRefund($bizcontent)
    {
        //json转数组
        $bizcontent = json_decode($bizcontent, true);

        $condition['refund_time'][] = [
            '>',
            strtotime($bizcontent['BeginTime'])
        ];
        $condition['refund_time'][] = [
            '<',
            strtotime($bizcontent['EndTime'])
        ];
        $condition['refund_status'] = ['<>', 0];

        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $express_company_model = new VslExpressCompanyModel();

        $data = $order_goods_model->pageQuery($bizcontent['PageIndex'], $bizcontent['PageSize'], $condition, '', '*');
        $refund_lists = $data['data'];

        if ($refund_lists) {
            foreach ($refund_lists as $k => $v) {
                //退款状态
                if ($v['refund_status'] == 0) {
                    $refund_status = 'JH_07';
                } elseif ($v['refund_status'] == 1) {
                    $refund_status = 'JH_01';
                } elseif ($v['refund_status'] == 2) {
                    $refund_status = 'JH_02';
                } elseif ($v['refund_status'] == 3) {
                    $refund_status = 'JH_03';
                } elseif ($v['refund_status'] == -1 || $v['refund_status'] == -3) {
                    $refund_status = 'JH_04';
                } elseif ($v['refund_status'] == 5) {
                    $refund_status = 'JH_06';
                }

                //是否需要退货
                if ($v['refund_type'] == 1) {
                    $has_goods_return = false;
                } elseif ($v['refund_type'] == 2) {
                    $has_goods_return = true;
                }

                //退款原因
                if ($v['refund_reason'] == 1) {
                    $reason = '拍错/多拍/不想要';
                } elseif ($v['refund_reason'] == 2) {
                    $reason = '协商一致退款/退货';
                } elseif ($v['refund_reason'] == 3) {
                    $reason = '缺货';
                } elseif ($v['refund_reason'] == 4) {
                    $reason = '未按约定时间发货';
                } else {
                    $reason = '其他';
                }

                //物流公司
                if ($v['refund_shipping_company']) {
                    $logistic_name = $express_company_model->Query(['co_id' => $v['refund_shipping_company']], 'company_name')[0];
                }

                //主订单详情
                $order_info = $order_model->getInfo(['order_id' => $v['order_id']], '*');

                //商品状态
                if ($order_info['shipping_status'] == 0 || $order_info['shipping_status'] == 1) {
                    $goods_status = 'JH_01';
                } elseif ($order_info['shipping_status'] == 2) {
                    $goods_status = 'JH_02';
                } elseif ($v['refund_shipping_company']) {
                    $goods_status = 'JH_03';
                } else {
                    $goods_status = 'JH_98';
                }

                $refund_data = [
                    'refundno' => $v['order_goods_id'],
                    'platorderno' => $order_info['order_no'],
                    'subplatorderno' => $v['order_goods_id'],
                    'totalamount' => $order_info['goods_money'],
                    'payamount' => $order_info['pay_money'],
                    'buyernick' => $order_info['user_name'],
                    'sellernick' => $order_info['shop_name'],
                    'createtime' => date("Y-m-d H:i:s", $v['refund_time']),
                    'updatetime' => '',
                    'orderstatus' => 'JH_98',
                    'orderstatusdesc' => '',
                    'refundstatus' => $refund_status,
                    'refundstatusdesc' => '',
                    'goodsstatus' => $goods_status,
                    'goodsstatusdesc' => '',
                    'hasgoodsreturn' => $has_goods_return,
                    'reason' => $reason,
                    'desc' => '',
                    'productnum' => $v['num'],
                    'logisticname' => $logistic_name,
                    'logisticno' => $v['refund_shipping_code'],
                ];
                $refund_data['RefundGoods'] = [
                    'PlatProductId' => $v['goods_id'],
                    'OuterID' => $v['goods_id'],
                    'Sku' => $v['sku_name'],
                    'ProductName' => $v['goods_name'],
                    'RefundAmount' => $v['refund_require_money'],
                    'Reason' => $reason,
                    'ProductNum' => $v['num'],
                    'PoNo' => '',
                ];

                $refunds[] = $refund_data;
            }
            unset($v);
            $total_count = $data['total_count'];
        } else {
            $refunds = [];
            $total_count = 0;
        }

        return [
            'refunds' => $refunds,
            'totalcount' => $total_count
        ];
    }
}