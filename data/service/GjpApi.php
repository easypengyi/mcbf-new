<?php

namespace data\service;

use addons\store\model\VslStoreModel;
use data\model\CityModel;
use data\model\DistrictModel;
use data\model\ProvinceModel;
use data\model\UserModel;
use data\model\VslAttributeModel;
use data\model\VslAttributeValueModel;
use data\model\VslGoodsAttributeModel;
use data\model\VslGoodsCategoryModel;
use data\model\VslGoodsModel;
use data\model\VslGoodsSkuModel;
use data\model\VslGoodsSpecModel;
use data\model\VslGoodsSpecValueModel;
use data\model\VslMemberAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslMemberLevelModel;
use data\model\VslMemberModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;

/**
 * 对接管家婆ERP需要的接口服务层
 */
class GjpApi extends BaseService
{
    /**
     * 获取订单
     */
    public function getOrder($post_data)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $goodsSer = new Goods();
        $member_model = new VslMemberModel();
        $member_level_model = new VslMemberLevelModel();
        $user_model = new UserModel();

        if ($post_data['StartTime'] && $post_data['EndTime']) {
            //时间格式转换
            $condition['create_time'][] = [
                '>',
                strtotime($post_data['StartTime'])
            ];
            $condition['create_time'][] = [
                '<',
                strtotime($post_data['EndTime'])
            ];
        }
        $condition['order_status'] = ['IN', [1, 2, 3, 4]];
        $order_list = $order_model->getQuery($condition, '*', '');
        if ($order_list) {
            foreach ($order_list as $k => $v) {
                //如果申请了售后的订单则删除
                $refund_status = $order_goods_model->getSum(['order_id' => $v['order_id']], 'refund_status');
                if ($refund_status != 0) {
                    unset($order_list[$k]);
                } else {
                    if ($v['shipping_type'] == 1) {
                        //快递配送
                        $buyer_company = '网上商城零售客户';
                        $department = '网上商城';
                        $delivery_store = '网上商城';
                    } elseif ($v['shipping_type'] == 2) {
                        //线下自提
                        $store_model = new VslStoreModel();
                        $store = $store_model->Query(['store_id' => $v['store_id']], 'store_name')[0];
                        $buyer_company = '线上' . $store . '零售客户';
                        $department = '线上' . $store;
                        $delivery_store = '线上' . $store;
                    }

                    //支付方式
                    switch ($v['payment_type']) {
                        case '1':
                            $payment_type = '微信';
                            break;
                        case '2':
                            $payment_type = '支付宝';
                            break;
                        case '3':
                            $payment_type = '银行卡';
                            break;
                        case '4':
                            $payment_type = '货到付款';
                            break;
                        case '5':
                            $payment_type = '余额支付';
                            break;
                        case '10':
                            $payment_type = '线下支付';
                            break;
                        case '16':
                            $payment_type = 'eth支付';
                            break;
                        case '17':
                            $payment_type = 'eos支付';
                            break;
                    }

                    //订单状态
                    switch ($v['order_status']) {
                        case '1':
                            $order_status_name = '等待卖家发货';
                            break;
                        case '2':
                            $order_status_name = '等待买家确认收货';
                            break;
                        case '3':
                            $order_status_name = '已收货';
                            break;
                        case '4':
                            $order_status_name = '已完成';
                            break;
                    }

                    //会员信息
                    $member_level_id = $member_model->Query(['uid' => $v['buyer_id']], 'member_level')[0];
                    $discount = $member_level_model->Query(['level_id' => $member_level_id], 'goods_discount')[0];
                    $member_real_name = $user_model->Query(['uid' => $v['buyer_id']], 'real_name')[0];

                    $result = [
                        'create_order_time' => date("Y-m-d H:i:s", $v['create_time']),
                        'order_no' => $v['order_no'],
                        'pay_time' => date("Y-m-d H:i:s", $v['pay_time']),
                        'buyer_company' => $buyer_company,
                        'department' => $department,
                        'delivery_store' => $delivery_store,
                        'discount' => $discount,
                        'member_id' => $v['buyer_id'],
                        'member_name' => $member_real_name,
                        'payment_type' => $payment_type,
                        'total_money' => $v['pay_money'],
                        'order_status_name' => $order_status_name,
                    ];

                    //订单下的商品信息
                    $order_goods_list = $order_goods_model->getQuery(['order_id' => $v['order_id']], '*', '');
                    foreach ($order_goods_list as $key => $val) {
                        $goods_info = $goodsSer->getGoodsDetailById($val['goods_id'], 'goods_id,gjp_goods_id,code');
                        if ($v['shipping_type'] == 1) {
                            //快递配送
                            $store_id = 0;
                            $store_name = '网上商城';
                        } elseif ($v['shipping_type'] == 2) {
                            //线下自提
                            $store_id = $v['store_id'];
                            $store_model = new VslStoreModel();
                            $store_name = '线上' . $store_model->Query(['store_id' => $v['store_id']], 'store_name')[0];
                        }
                        $res = [
                            'goods_id' => $goods_info['gjp_goods_id'],
                            'goods_name' => $val['goods_name'],
                            'goods_code' => $goods_info['code'],
                            'store_id' => $store_id,
                            'store_name' => $store_name,
                            'unit' => '',
                            'num' => $val['num'],
                            'price' => $val['price'],
                            'goods_money' => $val['goods_money'],
                            'discount_price' => $val['member_price'],
                            'discount_goods_money' => $val['real_money'],
                        ];
                        $result['goodinfos'][] = $res;
                    }
                    $orders[] = $result;
                }
            }
            unset($val);
            unset($v);
        } else {
            $orders = [];
        }

        return $orders;
    }

    /**
     * 获取退款退货单
     */
    public function getRefundOrder($post_data)
    {
        $order_model = new VslOrderModel();
        $order_goods_model = new VslOrderGoodsModel();
        $goodsSer = new Goods();
        $member_model = new VslMemberModel();
        $member_level_model = new VslMemberLevelModel();
        $user_model = new UserModel();

        if ($post_data['StartTime'] && $post_data['EndTime']) {
            //时间格式转换
            $condition['refund_time'][] = [
                '>',
                strtotime($post_data['StartTime'])
            ];
            $condition['refund_time'][] = [
                '<',
                strtotime($post_data['EndTime'])
            ];
        }
        $condition['refund_status'] = ['IN', [2, 3, 4, 5]];
        $refund_lists = $order_goods_model->getQuery($condition, '*', '');
        if ($refund_lists) {
            foreach ($refund_lists as $k => $v) {
                //主订单详情
                $order_info = $order_model->getInfo(['order_id' => $v['order_id']], '*');
                if ($order_info['shipping_type'] == 1) {
                    //快递配送
                    $buyer_company = '网上商城零售客户';
                    $department = '网上商城';
                    $delivery_store = '网上商城';
                    $store_id = 0;
                    $store_name = '网上商城';
                } elseif ($order_info['shipping_type'] == 2) {
                    //线下自提
                    $store_model = new VslStoreModel();
                    $store = $store_model->Query(['store_id' => $order_info['store_id']], 'store_name')[0];
                    $buyer_company = '线上' . $store . '零售客户';
                    $department = '线上' . $store;
                    $delivery_store = '线上' . $store;
                    $store_id = $order_info['store_id'];
                    $store_name = $store;
                }

                //支付方式
                switch ($order_info['payment_type']) {
                    case '1':
                        $payment_type = '微信';
                        break;
                    case '2':
                        $payment_type = '支付宝';
                        break;
                    case '3':
                        $payment_type = '银行卡';
                        break;
                    case '4':
                        $payment_type = '货到付款';
                        break;
                    case '5':
                        $payment_type = '余额支付';
                        break;
                    case '10':
                        $payment_type = '线下支付';
                        break;
                    case '16':
                        $payment_type = 'eth支付';
                        break;
                    case '17':
                        $payment_type = 'eos支付';
                        break;
                }

                //退款状态
                switch ($v['refund_status']) {
                    case '2':
                        $refund_status_name = '等回寄';
                        break;
                    case '3':
                        $refund_status_name = '待确认回寄';
                        break;
                    case '4':
                        $refund_status_name = '待打款';
                        break;
                    case '5':
                        $refund_status_name = '退款成功';
                        break;
                }

                //会员信息
                $member_level_id = $member_model->Query(['uid' => $order_info['buyer_id']], 'member_level')[0];
                $discount = $member_level_model->Query(['level_id' => $member_level_id], 'goods_discount')[0];
                $member_real_name = $user_model->Query(['uid' => $order_info['buyer_id']], 'real_name')[0];

                $result = [
                    'create_order_time' => date("Y-m-d H:i:s", $v['refund_time']),
                    'order_no' => $order_info['order_no'],
                    'pay_time' => date("Y-m-d H:i:s", $order_info['pay_time']),
                    'buyer_company' => $buyer_company,
                    'department' => $department,
                    'delivery_store' => $delivery_store,
                    'discount' => $discount,
                    'member_id' => $order_info['buyer_id'],
                    'member_name' => $member_real_name,
                    'payment_type' => $payment_type,
                    'total_money' => $order_info['pay_money'],
                    'refund_status_name' => $refund_status_name,
                ];

                //商品信息
                $goods_info = $goodsSer->getGoodsDetailById($v['goods_id'], 'goods_id,gjp_goods_id,code');
                $result['goodinfos'] = [
                    'goods_id' => $goods_info['gjp_goods_id'],
                    'goods_name' => $v['goods_name'],
                    'goods_code' => $goods_info['code'],
                    'store_id' => $store_id,
                    'store_name' => $store_name,
                    'unit' => '',
                    'num' => $v['num'],
                    'price' => $v['price'],
                    'goods_money' => $v['goods_money'],
                    'discount_price' => $v['member_price'],
                    'discount_goods_money' => $v['real_money'],
                ];
                $refund_orders[] = $result;
            }
            unset($v);
        } else {
            $refund_orders = [];
        }

        return $refund_orders;
    }

    /**
     * 获取会员信息
     */
    public function getMemberInfo($post_data)
    {
        $member_model = new VslMemberModel();
        $member_level_model = new VslMemberLevelModel();
        $user_model = new UserModel();
        $address_service = new Address();
        $member_account_model = new VslMemberAccountModel();

        if ($post_data['StartTime'] && $post_data['EndTime']) {
            //时间格式转换
            $condition['reg_time'][] = [
                '>',
                strtotime($post_data['StartTime'])
            ];
            $condition['reg_time'][] = [
                '<',
                strtotime($post_data['EndTime'])
            ];
        }
        $condition['is_member'] = 1;
        $member_list = $user_model->getQuery($condition, '*', '');
        if ($member_list) {
            foreach ($member_list as $k => $v) {
                //性别
                if ($v['sex'] == 0) {
                    $sex = '男';
                } elseif ($v['sex'] == 1) {
                    $sex = '男';
                } elseif ($v['sex'] == 2) {
                    $sex = '女';
                }
                //生日
                if (empty($v['birthday'])) {
                    $birthday = '1980-01-01';
                } else {
                    $birthday = date("Y-m-d", $v['birthday']);
                }
                //地区
                if ($v['province_id'] && $v['city_id'] && $v['district_id']) {
                    $province_name = $address_service->getProvinceName($v['province_id']);
                    $city_name = $address_service->getCityName($v['city_id']);
                    $dictrict_name = $address_service->getDistrictName($v['district_id']);
                    $address = $province_name . $city_name . $dictrict_name;
                } else {
                    $address = '';
                }

                //会员等级
                $member = $member_model->getInfo(['uid' => $v['uid']], 'member_level,referee_id');
                $level_name = $member_level_model->Query(['level_id' => $member['member_level']], 'level_name')[0];

                //余额，积分，累计消费金额
                $member_account_info = $member_account_model->getInfo(['uid' => $v['uid']], 'point,balance,member_cunsum');

                $member_info = [
                    'member_id' => $v['uid'],
                    'member_real_name' => $v['real_name'],
                    'member_nick_name' => $v['nick_name'],
                    'member_mobile' => $v['user_tel'],
                    'member_sex' => $sex,
                    'member_birthday' => $birthday,
                    'member_address' => $address,
                    'member_reg_time' => date("Y-m-d H:i:s", $v['reg_time']),
                    'member_levle_name' => $level_name,
                    'referee_id' => $member['referee_id'] ?: 0,
                    'member_balance' => $member_account_info['balance'],
                    'member_point' => $member_account_info['point'],
                    'member_cunsum' => $member_account_info['member_cunsum'],
                ];
                $member_lists[] = $member_info;
            }
            unset($v);
        } else {
            $member_lists = [];
        }

        return $member_lists;
    }

    /**
     * 库存同步
     */
    public function syncStock($post_data)
    {
        $redis = connectRedis();
        $goodsSer = new Goods();
        foreach ($post_data['stock_info'] as $k => $v) {
            $goods_model = new VslGoodsModel();//商品表更新
            $goods_sku_model = new VslGoodsSkuModel();

            $goods_info = $goods_model->getInfo(['gjp_goods_id' => $v['goods_id']], 'goods_id');
            if (empty($goods_info)) {
                return ['code' => -1, 'message' => '此商品不存在'];
            }

            $update_data = [
                'stock' => $v['stock']
            ];

            if (empty($v['sku_id'])) {
                //修改主商品的库存
                $res = $goods_model->save($update_data, ['gjp_goods_id' => $v['goods_id']]);
                $goods_id = $goods_model->Query(['gjp_goods_id' => $v['goods_id']], 'goods_id')[0];
                $res1 = $goods_sku_model->save($update_data, ['goods_id' => $goods_id]);
            } else {
                //修改指定规格库存
                $res = $goods_sku_model->save($update_data, ['gjp_sku_id' => $v['sku_id']]);

                $goods_id = $goods_model->Query(['gjp_goods_id' => $v['goods_id']], 'goods_id')[0];
                $stock = $goods_sku_model->getSum(['goods_id' => $goods_id], 'stock');
                $res1 = $goods_model->save(['stock' => $stock], ['goods_id' => $goods_id]);
                //同步商品库存到redis
                $sku_goods_info = $goods_sku_model->getInfo(['gjp_sku_id' => $v['sku_id']], 'sku_id, goods_id');
                $system_sku_id = $sku_goods_info['sku_id'] ? : 0;
                $system_goods_id = $sku_goods_info['goods_id'] ? : 0;
                $goods_key = 'goods_'.$system_goods_id.'_'.$system_sku_id;
                if(!$redis->get($goods_key)){
                    $redis->set($goods_key, $v['stock']);
                }
                $goodsSer->addOrUpdateGoodsToEs($goods_id);
            }
        }
        unset($v);

        if ($res && $res1) {
            return ['code' => 1];
        }
    }

    /**
     * 会员信息同步
     */
    public function syncMemberInfo($post_data, $website_id, $shop_id)
    {
        if (empty($post_data['member_info'])) {
            return ['code' => -1, 'message' => '参数不齐全'];
        }

        foreach ($post_data['member_info'] as $k => $v) {
            $member_model = new VslMemberModel();
            $member_info = $member_model->getInfo(['mobile' => $v['member_mobile']]);

            if (empty($member_info)) {
                //新增会员信息
                $res = $this->addMemberInfo($v, $website_id, $shop_id);
            } else {
                //修改会员信息
                $res = $this->updateMemberInfo($v, $member_info['uid']);
            }
        }
        unset($v);

        return $res;
    }

    /**
     * 新增会员信息
     */
    public function addMemberInfo($v, $website_id, $shop_id)
    {
        $user_model = new UserModel();
        $member_model = new VslMemberModel();
        $member_account_model = new VslMemberAccountModel();

        //如果有地址信息，需要转为地区id
        if ($v['province']) {
            $province_model = new ProvinceModel();
            $province_condition = [
                'province_name' => ['LIKE', $v['province'] . '%'],
            ];
            $province_id = $province_model->Query($province_condition, 'province_id')[0];
        } else {
            $province_id = 0;
        }

        if ($v['city']) {
            $city_model = new CityModel();
            $city_condition = [
                'city_name' => ['LIKE', $v['city'] . '%'],
            ];
            $city_id = $city_model->Query($city_condition, 'city_id')[0];
        } else {
            $city_id = 0;
        }

        if ($v['district']) {
            $district_model = new DistrictModel();
            $district_condition = [
                'district_name' => ['LIKE', $v['district'] . '%'],
            ];
            $district_id = $district_model->Query($district_condition, 'district_id')[0];
        } else {
            $district_id = 0;
        }

        //会员等级
        $member_level_condition = [
            'level_name' => ['LIKE', $v['member_level_name'] . '%']
        ];
        $member_level_model = new VslMemberLevelModel();
        $member_level_id = $member_level_model->Query($member_level_condition, 'level_id')[0];

        //推荐人
        if ($v['referee_mobile']) {
            $referee_id = $user_model->Query(['user_tel' => $v['referee_mobile']], 'uid')[0];
        } else {
            $referee_id = 0;
        }

        //开始组装数据,分别存入member,user,member_account表
        $user_data = [
            'instance_id' => $shop_id,
            'user_status' => 1,
            'is_system' => 0,
            'is_member' => 1,
            'user_tel' => $v['member_mobile'],
            'real_name' => $v['meber_real_name'],
            'sex' => $v['member_sex'],
            'reg_time' => $v['member_reg_time'],
            'birthday' => $v['member_birthday'],
            'website_id' => $website_id,
            'province_id' => $province_id,
            'city_id' => $city_id,
            'district_id' => $district_id,
        ];
        $user_model->save($user_data);
        $uid = $user_model->uid;

        $member_data = [
            'uid' => $uid,
            'member_level' => $member_level_id,
            'reg_time' => $v['member_reg_time'],
            'website_id' => $website_id,
            'referee_id' => $referee_id,
            'real_name' => $v['meber_real_name'],
            'mobile' => $v['member_mobile']
        ];
        $res = $member_model->save($member_data);

        $member_account_data = [
            'uid' => $uid,
            'shop_id' => $shop_id,
            'point' => $v['member_point'],
            'balance' => $v['member_balance'],
            'member_cunsum' => $v['member_cunsum'],
            'website_id' => $website_id,
        ];
        $res1 = $member_account_model->save($member_account_data);

        if ($uid && $res && $res1) {
            return 1;
        }
    }

    /**
     *更新会员信息
     */
    public function updateMemberInfo($v, $uid)
    {
        $user_model = new UserModel();
        $member_model = new VslMemberModel();
        $member_account_model = new VslMemberAccountModel();

        //如果有地址信息，需要转为地区id
        if ($v['province']) {
            $province_model = new ProvinceModel();
            $province_condition = [
                'province_name' => ['LIKE', $v['province'] . '%'],
            ];
            $province_id = $province_model->Query($province_condition, 'province_id')[0];
        } else {
            $province_id = 0;
        }

        if ($v['city']) {
            $city_model = new CityModel();
            $city_condition = [
                'city_name' => ['LIKE', $v['city'] . '%'],
            ];
            $city_id = $city_model->Query($city_condition, 'city_id')[0];
        } else {
            $city_id = 0;
        }

        if ($v['district']) {
            $district_model = new DistrictModel();
            $district_condition = [
                'district_name' => ['LIKE', $v['district'] . '%'],
            ];
            $district_id = $district_model->Query($district_condition, 'district_id')[0];
        } else {
            $district_id = 0;
        }

        //会员等级
        $member_level_condition = [
            'level_name' => ['LIKE', $v['member_levle_name'] . '%']
        ];
        $member_level_model = new VslMemberLevelModel();
        $member_level_id = $member_level_model->Query($member_level_condition, 'level_id')[0];


        //开始组装数据,分别存入member,user,member_account表
        $user_data = [
            'user_tel' => $v['member_mobile'],
            'real_name' => $v['meber_real_name'],
            'sex' => $v['member_sex'],
            'birthday' => $v['member_birthday'],
            'province_id' => $province_id,
            'city_id' => $city_id,
            'district_id' => $district_id,
        ];
        $res = $user_model->save($user_data, ['uid' => $uid]);

        $member_data = [
            'member_level' => $member_level_id,
            'real_name' => $v['meber_real_name'],
            'mobile' => $v['member_mobile']
        ];
        $res1 = $member_model->save($member_data, ['uid' => $uid]);

        $member_account_data = [
            'point' => $v['member_point'],
            'balance' => $v['member_balance'],
            'member_cunsum' => $v['member_cunsum'],
        ];
        $res2 = $member_account_model->save($member_account_data, ['uid' => $uid]);

        if ($res && $res1 && $res2) {
            return 1;
        }
    }

    /**
     * 商品信息同步
     */
    public function syncGoodsInfo($post_data, $website_id, $shop_id)
    {
        if (empty($post_data['goods_list'])) {
            return ['code' => -1, 'message' => '参数不齐全'];
        }
        $goodsSer = new Goods();
        try {
            foreach ($post_data['goods_list'] as $k => $v) {
                $goods_info = $goodsSer->getGoodsDetailByCondition(['gjp_goods_id' => $v['goods_id']], 'goods_id');

                if (empty($goods_info)) {
                    //新增商品信息
                    $res = $this->addGoodsInfo($v, $website_id, $shop_id);
                } else {
                    $res = $this->updateGoodsInfo($v, $website_id, $shop_id, $goods_info['goods_id']);
                }
            }
            unset($v);
        } catch (\Exception $e) {
            return ['code' => 1, 'message' => $e->getMessage()];
        }

        return $res;
    }

    /**
     *新增商品信息
     */
    public function addGoodsInfo($v, $website_id, $shop_id)
    {
        //开始组装数据存入商品表
        $goods_data = [
            'goods_name' => $v['goods_name'],
            'shop_id' => $shop_id,
            'goods_type' => $v['goods_type'],
            'market_price' => $v['market_price'] ?: 0,
            'price' => $v['price'],
            'cost_price' => $v['cost_price'] ?: 0,
            'promotion_price' => $v['promotion_price'] ?: 0,
            'state' => 0,
            'create_time' => time(),
            'website_id' => $website_id,
            'gjp_goods_id' => $v['goods_id'],
        ];
        $goods_model = new VslGoodsModel();//商品表更新
        $goods_id = $goods_model->save($goods_data);

        //处理分类、规格、属性
        $otherData = $this->setOtherData($goods_id, $v, $website_id, $shop_id);
        $goods_data = [
            'goods_attribute_id' => $otherData['attr_id'],
            'category_id' => $otherData['categoryArr']['category_id'],
            'category_id_1' => $otherData['categoryArr']['category_id_1'],
            'category_id_2' => $otherData['categoryArr']['category_id_2'],
            'category_id_3' => $otherData['categoryArr']['category_id_3'],
            'goods_spec_format' => json_encode($otherData['goods_spec_format']),
        ];
        $goods_model->save($goods_data, ['goods_id' => $goods_id]);
        $goodsSer = new Goods();
        return $goodsSer->addOrUpdateGoodsToEs($goods_id);
    }

    /**
     * 更新商品信息
     */
    public function updateGoodsInfo($v, $website_id, $shop_id, $goods_id)
    {
        //开始组装数据存入商品表
        $goods_data = [
            'goods_name' => $v['goods_name'],
            'shop_id' => $shop_id,
            'goods_type' => $v['goods_type'],
            'market_price' => $v['market_price'] ?: 0,
            'price' => $v['price'],
            'cost_price' => $v['cost_price'] ?: 0,
            'promotion_price' => $v['promotion_price'] ?: 0,
            'state' => 0,
            'create_time' => time(),
            'website_id' => $website_id,
            'gjp_goods_id' => $v['goods_id'],
        ];
        $goodsSer = new Goods();
        $goods_model = new VslGoodsModel();//商品表更新
        $goods_model->save($goods_data, ['goods_id' => $goods_id]);
        //处理分类、规格、属性
        $otherData = $this->setOtherData($goods_id, $v, $website_id, $shop_id);
        $goods_data = [
            'goods_attribute_id' => $otherData['attr_id'],
            'category_id' => $otherData['categoryArr']['category_id'],
            'category_id_1' => $otherData['categoryArr']['category_id_1'],
            'category_id_2' => $otherData['categoryArr']['category_id_2'],
            'category_id_3' => $otherData['categoryArr']['category_id_3'],
            'goods_spec_format' => json_encode($otherData['goods_spec_format']),
        ];
        $goods_model->save($goods_data, ['goods_id' => $goods_id]);
        return $goodsSer->addOrUpdateGoodsToEs($goods_id);
    }

    /**
     * 处理商品分类、规格、属性
     */
    public function setOtherData($goods_id, $v, $website_id, $shop_id)
    {
        if (!$goods_id || !$v) {
            return false;
        }

        $data = [];
        $attrModel = new VslAttributeModel();
        $attrModel->startTrans();
        try {
            //处理品类，默认一级分类为品类
            $checkAttr = $attrModel->getInfo(['attr_name' => $v['category_name_1'], 'website_id' => $website_id]);
            if ($checkAttr) {
                $data['attr_id'] = $checkAttr['attr_id'];
            } else {
                $data['attr_id'] = $attrModel->save(['attr_name' => $v['category_name_1'], 'is_use' => 1, 'create_time' => time(), 'sort' => 0, 'website_id' => $website_id]);
            }

            //根据品类id和分类名称处理商品分类
            $data['categoryArr'] = $this->setCategory($v, $data['attr_id'], $website_id);

            //处理规格
            $data['goods_spec_format'] = $this->setSpecAndSku($v['goods_spec_format'], $v['sku_list'], $goods_id, $data['attr_id'], $website_id, $shop_id);

            //处理属性
            if ($v['goods_attr_format']) {
                $data['attr_value'] = $this->setAttrValue($v['goods_attr_format'], $goods_id, $data['attr_id'], $website_id, $shop_id);
            }

            $attrModel->commit();
            return $data;
        } catch (\Exception $e) {
            $attrModel->rollback();
            return false;
        }
    }

    /**
     * 处理商品分类
     */
    public function setCategory($v, $attr_id, $website_id)
    {
        $category = array(
            'category_id' => 0,
            'category_id_1' => 0,
            'category_id_2' => 0,
            'category_id_3' => 0
        );

        if (!$v['category_name_1']) {
            return $category;
        }

        $cateModel = new VslGoodsCategoryModel();
        try {
            if ($v['category_name_2']) {//有二级分类
                $cate_1 = $cateModel->getInfo(['category_name' => $v['category_name_1'], 'level' => 1, 'website_id' => $website_id])['category_id'];
                if (!$cate_1) {
                    $cateModel = new VslGoodsCategoryModel();
                    $cate_1 = $cateModel->save(['category_name' => $v['category_name_1'], 'short_name' => $v['category_name_1'], 'level' => 1, 'is_visible' => 1, 'create_time' => time(), 'website_id' => $website_id]);
                }
                $category['category_id_1'] = $cate_1 ?: 0;
            } else {//没有二级分类
                $cate_1 = $cateModel->getInfo(['category_name' => $v['category_name_1'], 'level' => 1, 'attr_id' => $attr_id, 'website_id' => $website_id])['category_id'];
                if (!$cate_1) {
                    $cateModel = new VslGoodsCategoryModel();
                    $cate_1 = $cateModel->save(['category_name' => $v['category_name_1'], 'short_name' => $v['category_name_1'], 'level' => 1, 'attr_id' => $attr_id, 'is_visible' => 1, 'create_time' => time(), 'website_id' => $website_id]);
                }
                $category['category_id_1'] = $cate_1 ?: 0;
                $category['category_id'] = $cate_1 ?: 0;
                return $category;
            }

            if ($v['category_name_3']) {//有三级分类
                $cate_2 = $cateModel->getInfo(['category_name' => $v['category_name_2'], 'level' => 2, 'pid' => $category['category_id_1'], 'website_id' => $website_id])['category_id'];
                if (!$cate_2) {
                    $cateModel = new VslGoodsCategoryModel();
                    $cate_2 = $cateModel->save(['category_name' => $v['category_name_2'], 'short_name' => $v['category_name_2'], 'level' => 2, 'pid' => $category['category_id_1'], 'is_visible' => 1, 'create_time' => time(), 'website_id' => $website_id]);
                }
                $category['category_id_2'] = $cate_2 ?: 0;
            } else {//没有三级分类
                $cate_2 = $cateModel->getInfo(['category_name' => $v['category_name_2'], 'level' => 2, 'pid' => $category['category_id_1'], 'attr_id' => $attr_id, 'website_id' => $website_id])['category_id'];
                if (!$cate_2) {
                    $cateModel = new VslGoodsCategoryModel();
                    $cate_2 = $cateModel->save(['category_name' => $v['category_name_2'], 'short_name' => $v['category_name_2'], 'level' => 2, 'pid' => $category['category_id_1'], 'is_visible' => 1, 'attr_id' => $attr_id, 'create_time' => time(), 'website_id' => $website_id]);
                }
                $category['category_id_2'] = $cate_2 ?: 0;
                $category['category_id'] = $cate_2 ?: 0;
                return $category;
            }
            $cate_3 = $cateModel->getInfo(['category_name' => $v['category_name_3'], 'short_name' => $v['category_name_3'], 'level' => 3, 'pid' => $category['category_id_2'], 'attr_id' => $attr_id, 'website_id' => $website_id])['category_id'];
            if (!$cate_3) {
                $cateModel = new VslGoodsCategoryModel();
                $cate_3 = $cateModel->save(['category_name' => $v['category_name_3'], 'short_name' => $v['category_name_3'], 'level' => 3, 'pid' => $category['category_id_2'], 'is_visible' => 1, 'attr_id' => $attr_id, 'create_time' => time(), 'website_id' => $website_id]);
            }
            $category['category_id_3'] = $cate_3 ?: 0;
            $category['category_id'] = $cate_3 ?: 0;
            return $category;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 处理规格
     */
    public function setSpecAndSku($goods_spec_format, $sku_list, $goods_id, $attr_id, $website_id, $shop_id)
    {
        try {
            $specModel = new VslGoodsSpecModel();
            $specValueModel = new VslGoodsSpecValueModel();
            $skuModel = new VslGoodsSkuModel();

            $spec_arr_format = [];
            $sku_lists = [];
            $spec_id_arr = [];
            $is_platform = 1;
            if ($shop_id) {
                $is_platform = 0;
            }

            // 删除商品sku
            $skuModel->destroy(['goods_id' => $goods_id]);

            if (empty($goods_spec_format)) {//无规格
                $spec_arr_format = [];
                //存一条数据到goods_sku表
                foreach ($sku_list as $k => $v) {
                    $data = [
                        'goods_id' => $goods_id,
                        'sku_name' => '',
                        'attr_value_items' => '',
                        'attr_value_items_format' => '',
                        'market_price' => $v['market_price'] ?: 0,
                        'price' => $v['price'] ?: 0,
                        'cost_price' => $v['cost_price'] ?: 0,
                        'promote_price' => $v['promotion_price'] ?: 0,
                        'create_date' => time(),
                        'website_id' => $website_id,
                        'gjp_sku_id' => $v['sku_id'],
                    ];
                    $skuModel->save($data);
                }
                unset($v);
                return $spec_arr_format;
            } else {//多规格
                foreach ($goods_spec_format as $k => $v) {
                    //处理规格名
                    $check_spec = $specModel->getInfo(['spec_name' => $v['spec_name'], 'website_id' => $website_id, 'shop_id' => $shop_id, 'show_type' => 1], 'spec_id,goods_attr_id');
                    if (!$check_spec) {
                        $specModel = new VslGoodsSpecModel();
                        $spec_id = $specModel->save(['spec_name' => $v['spec_name'], 'website_id' => $website_id, 'shop_id' => $shop_id, 'show_type' => 1, 'goods_attr_id' => $attr_id, 'sort' => 0, 'create_time' => time(), 'is_visible' => 1, 'is_screen' => 0, 'is_platform' => $is_platform]);
                    } else {
                        $goods_attr_id_arr = explode(',', $check_spec['goods_attr_id']);
                        if (!in_array($attr_id, $goods_attr_id_arr)) {
                            array_push($goods_attr_id_arr, $attr_id);
                            $specModel->save(['goods_attr_id' => implode(',', $goods_attr_id_arr)], ['spec_id' => $check_spec['spec_id']]);
                        }
                        $spec_id = $check_spec['spec_id'];
                    }
                    $spec_id_arr[$k] = $spec_id;
                    $spec_arr_format[$k]['spec_name'] = $v['spec_name'];
                    $spec_arr_format[$k]['spec_id'] = $spec_id;
                    $spec_arr_format[$k]['value'] = array();
                    $spec_arr_format[$k]['value_id'] = array();
                }
                unset($v);
                if ($attr_id) {
                    $attrModel = new VslAttributeModel();
                    $attrModel->save(['spec_id_array' => implode(',', $spec_id_arr)], ['attr_id' => $attr_id]);
                }

                //处理规格值
                foreach ($goods_spec_format as $k => $v) {
                    foreach ($v['value'] as $k1 => $v1) {
                        $spec_value_id = $specValueModel->getInfo(['spec_value_name' => $v1['spec_value_name'], 'spec_id' => $spec_id_arr[$k], 'website_id' => $website_id, 'shop_id' => $shop_id], 'spec_value_id')['spec_value_id'];
                        if (!$spec_value_id) {
                            $specValueModel = new VslGoodsSpecValueModel();
                            $spec_value_id = $specValueModel->save(['spec_value_name' => $v1['spec_value_name'], 'spec_id' => $spec_id_arr[$k], 'is_visible' => 1, 'create_time' => time(), 'website_id' => $website_id, 'shop_id' => $shop_id]);
                        }
                        if (!in_array($spec_value_id, $spec_arr_format[$k]['value_id'])) {
                            array_push($spec_arr_format[$k]['value'], ['spec_value_name' => $v1['spec_value_name'], 'spec_name' => $v['spec_name'], 'spec_id' => $spec_id_arr[$k], 'spec_value_id' => $spec_value_id, 'spec_show_type' => 1, 'spec_value_data' => '']);
                            array_push($spec_arr_format[$k]['value_id'], $spec_value_id);
                        }
                    }
                    unset($v1);
                }
                unset($v);
                foreach ($spec_arr_format as $kk => $vv) {
                    unset($spec_arr_format[$kk]['value_id']);
                }
                 unset($vv);
                //处理sku信息到goods_sku表
                foreach ($sku_list as $k => $v) {
                    $sku_name = explode(' ', $v['sku_name']);
                    foreach ($sku_name as $k1 => $v1) {
                        $specValueModel = new VslGoodsSpecValueModel();
                        $spec_value_info = $specValueModel->getInfo(['spec_value_name' => $v1, 'website_id' => $website_id, 'shop_id' => $shop_id], 'spec_id,spec_value_id');
                        $attr_value_items[] = $spec_value_info['spec_id'] . ':' . $spec_value_info['spec_value_id'];
                    }
                    unset($v1);
                    $sku_lists[] = [
                        'goods_id' => $goods_id,
                        'sku_name' => $v['sku_name'],
                        'attr_value_items' => implode(';', $attr_value_items),
                        'attr_value_items_format' => implode(';', $attr_value_items),
                        'market_price' => $v['market_price'] ?: 0,
                        'price' => $v['price'] ?: 0,
                        'cost_price' => $v['cost_price'] ?: 0,
                        'promote_price' => $v['promotion_price'] ?: 0,
                        'create_date' => time(),
                        'website_id' => $website_id,
                        'gjp_sku_id' => $v['sku_id'],
                    ];

                    unset($attr_value_items);
                }
                unset($v);
                if ($sku_lists) {
                    $skuModel->saveAll($sku_lists, true);
                }

                return $spec_arr_format;
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 处理属性
     */
    public function setAttrValue($goods_attr_format, $goods_id, $attr_id, $website_id, $shop_id)
    {
        $goodsAttrModel = new VslGoodsAttributeModel();

        // 删除商品属性
        $goodsAttrModel->destroy(['goods_id' => $goods_id]);

        $goods_attr_arr = array();
        foreach ($goods_attr_format as $k => $v) {
            $attrValueModel = new VslAttributeValueModel();
            if (!$v['attr_name'] || !$v['attr_value_name']) {//没有属性或者属性值都不执行
                continue;
            }
            $attr_value_id = $attrValueModel->getInfo(['attr_value_name' => $v['attr_value_name'], 'attr_id' => $attr_id, 'type' => 1, 'website_id' => $website_id, 'shop_id' => $shop_id], 'attr_value_id')['attr_value_id'];
            if (!$attr_value_id) {
                $attr_value_id = $attrValueModel->save(['attr_value_name' => $v['attr_value_name'], 'attr_id' => $attr_id, 'type' => 1, 'website_id' => $website_id, 'shop_id' => $shop_id, 'is_search' => 1]);
            }

            $goods_attr_arr[$k] = array(
                'goods_id' => $goods_id,
                'shop_id' => $shop_id,
                'attr_value_id' => $attr_value_id,
                'attr_value' => $v['attr_name'],
                'attr_value_name' => $v['attr_value_name'],
                'create_time' => time(),
                'website_id' => $website_id,
            );
        }
        unset($v);
        return $goodsAttrModel->saveAll($goods_attr_arr, true);
    }

    /**
     * 余额明细同步
     */
    public function syncBalanceDetail($post_data, $website_id, $shop_id)
    {
        if (empty($post_data['balance_info'])) {
            return ['code' => -1, 'message' => '参数不齐全'];
        }

        $member_account_model = new VslMemberAccountModel();
        try {
            $member_model = new VslMemberModel();
            $member_account_records_model = new VslMemberAccountRecordsModel();
            foreach ($post_data['balance_info'] as $k => $v) {
                $uid = $member_model->Query(['mobile' => $v['member_mobile']], 'uid')[0];
                if (empty($uid)) {
                    return ['code' => -1, 'message' => '此会员不存在'];
                }

                //组装会员余额流水记录
                $data[] = [
                    'uid' => $uid,
                    'shop_id' => $shop_id,
                    'account_type' => 2,
                    'sign' => 0,
                    'number' => $v['change_money'],
                    'from_type' => 1,
                    'text' => '线下订单，余额支付',
                    'create_time' => $v['create_time'],
                    'website_id' => $website_id,
                    'records_no' => $v['records_no'],
                    'status' => 3,
                    'balance' => $v['balance'],
                    'point' => $v['point'],
                ];

                //扣减余额跟积分
                $account_obj = $member_account_model->getInfo(['uid' => $uid, 'website_id' => $website_id]);
                if ($account_obj) {
                    $member_account_model = new VslMemberAccountModel();
                    if ($v['is_add_point']) {
                        //增加积分
                        $data1 = array(
                            "balance" => $account_obj["balance"] - $v['change_money'] > 0 ? $account_obj["balance"] - $v['change_money'] : 0,
                            "point" => $account_obj["point"] + $v['change_point'] > 0 ? $account_obj["point"] + $v['change_point'] : 0,
                        );
                    } else {
                        //减少积分
                        $data1 = array(
                            "balance" => $account_obj["balance"] - $v['change_money'] > 0 ? $account_obj["balance"] - $v['change_money'] : 0,
                            "point" => $account_obj["point"] - $v['change_point'] > 0 ? $account_obj["point"] - $v['change_point'] : 0,
                        );
                    }

                    $res1 = $member_account_model->save($data1, [
                        'uid' => $uid,
                        'website_id' => $website_id
                    ]);
                }
            }
            unset($v);
            $res = $member_account_records_model->saveAll($data, true);
            $member_account_model->commit();
            return $res;
        } catch (\Exception $e) {
            $member_account_model->rollback();
            return ['code' => 1, 'message' => $e->getMessage()];
        }
    }
}