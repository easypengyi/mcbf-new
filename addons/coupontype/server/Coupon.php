<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/25 0025
 * Time: 11:30
 */

namespace addons\coupontype\server;

use addons\coupontype\model\VslCouponGoodsModel;
use addons\coupontype\model\VslCouponModel;
use addons\coupontype\model\VslCouponTypeModel;
use addons\coupontype\model\VslShareCouponRecordsModel;
use addons\registermarketing\model\VslRegisterMarketingCouponTypeModel;
use data\model\AlbumPictureModel;
use addons\shop\model\VslShopModel;
use data\service\BaseService;
use think\Db;
use data\service\AddonsConfig as AddonsConfigService;
use data\service\Member;
use data\model\VslGoodsViewModel;
use data\model\VslGoodsCategoryModel as VslGoodsCategoryModel;
use data\service\Goods;
use data\model\UserModel;
class Coupon extends BaseService
{
    protected $addons_name = 'coupontype';
    protected $goods_ser = '';
    function __construct()
    {
        parent::__construct();
        $this->goods_ser = new Goods();
    }

    /**
     * 获取优惠券列表
     * @param int|string $page_index
     * @param int|string $page_size
     * @param array $condition
     * @param string $order
     * @param string $fields
     *
     * @return array $coupon_type_list
     */
    public function getCouponTypeList($page_index = 1, $page_size = 0, array $condition = [], $order = 'create_time desc', $fields = '*')
    {
        
        $coupon_type = new VslCouponTypeModel();
        $coupon_type_list = $coupon_type->pageQuery($page_index, $page_size, $condition, $order, $fields);
        if($coupon_type_list['data']){
            $coupon_model = new VslCouponModel();
            foreach ($coupon_type_list['data'] as $k => $v) {
                $coupon_type_list['data'][$k]['received'] = $coupon_model->where(['coupon_type_id'=>$v['coupon_type_id']])->count();
                $coupon_type_list['data'][$k]['surplus'] = $v['count'] - $coupon_type_list['data'][$k]['received'];
                $coupon_type_list['data'][$k]['surplus'] = ($coupon_type_list['data'][$k]['surplus']>0)?$coupon_type_list['data'][$k]['surplus']:0;
            }
        }
        return $coupon_type_list;
    }
    
    /*
     * 获取优惠券类型数据
     */
    public function getCouponTypeData ($condition, $field='*')
    {
        $coupon_type_model = new VslCouponTypeModel();
        return $coupon_type_model->getInfo($condition,$field);
    }
    
    /**
     * 获取优惠券数据
     */
    public function getCouponTypeDatas ($page_index, $page_size, $condition, $order, $fields)
    {
        $coupon_type = new VslCouponTypeModel();
        $coupon_type_list = $coupon_type->pageQuery($page_index, $page_size, $condition, $order, $fields);
        if($coupon_type_list['data']){
            $coupon_model = new VslCouponModel();
            foreach ($coupon_type_list['data'] as $k => $v) {
                $coupon_type_list['data'][$k]['received'] = $coupon_model->where(['coupon_type_id'=>$v['coupon_type_id']])->count();
                $coupon_type_list['data'][$k]['surplus'] = $v['count'] - $coupon_type_list['data'][$k]['received'];
                $coupon_type_list['data'][$k]['surplus'] = ($coupon_type_list['data'][$k]['surplus']>0)?$coupon_type_list['data'][$k]['surplus']:0;
            }
        }
        return $coupon_type_list;
    }
    
    public function getCouponTypeDetail($coupon_type_id)
    {
        $coupon_type_model = new VslCouponTypeModel();
        $coupon_goods = new VslCouponGoodsModel();
        $data = $coupon_type_model::get($coupon_type_id, ['coupons']);
        $goods_list = $coupon_goods->getCouponTypeGoodsList($coupon_type_id);
        $coupon['received'] = 0;//已领取数目
        $coupon['used'] = 0; //已使用数目
    
        $goods_id_array = [];
        $picture = new AlbumPictureModel();
        foreach ($goods_list as $k => $v) {
            if (!empty($v['picture'])) {
                $pic_db_info = $picture->getInfo(['pic_id' => $v['picture']],'pic_cover,pic_cover_mid,pic_cover_micro');
                $pic_info['pic_cover'] = getApiSrc($pic_db_info['pic_cover']);
                $pic_info['pic_cover_micro'] = getApiSrc($pic_db_info['pic_cover_micro']);
            } else {
                $pic_info['pic_cover'] = '';
                $pic_info['pic_cover_micro'] = '';
            }
            $goods_list[$k]['picture_info'] = $pic_info;

            if(getAddons('shop', $this->website_id)){
                $shop = new VslShopModel();
                $shop_info = $shop->getInfo(['shop_id' => $v['shop_id']],'shop_name');
            }else{
                $shop_info = [];
                $shop_info['shop_name'] = $this->mall_name;
            }
            $goods_list[$k]['shop_name'] = $shop_info['shop_name'];
    
            $goods_id_array[] = $v['goods_id'];
        }
        $coupon['received'] = $data->coupons()->where('state','<>',-1)->count();
        $coupon['used'] = $data->coupons()->where(['state' => 2])->count();
        $coupon['frozen'] = $data->coupons()->where(['state' => -1])->count();

        $data['goods_list'] = $goods_list;
        $data['goods_id_array'] = $goods_id_array;
        $data['coupon'] = $coupon;
        //获取分类 
        $extend_category_array = [];
        if (!empty($data['category_extend_id'])) {
            $extend_category_ids = $data['category_extend_id'];
            $extend_category_id_1s = $data['extend_category_id_1s'];
            $extend_category_id_2s = $data['extend_category_id_2s'];
            $extend_category_id_3s = $data['extend_category_id_3s'];
            $extend_category_id_str = explode(",", $extend_category_ids);
            $extend_category_id_1s_str = explode(",", $extend_category_id_1s);
            $extend_category_id_2s_str = explode(",", $extend_category_id_2s);
            $extend_category_id_3s_str = explode(",", $extend_category_id_3s);
            
            foreach ($extend_category_id_str as $k => $v) {
                $extend_category_name = $this->getGoodsCategoryName($extend_category_id_1s_str[$k], $extend_category_id_2s_str[$k], $extend_category_id_3s_str[$k]);
                $extend_category_array[] = [
                    "extend_category_name" => $extend_category_name,
                    "extend_category_id" => $v,
                    "extend_category_id_1" => $extend_category_id_1s_str[$k],
                    "extend_category_id_2" => $extend_category_id_2s_str[$k],
                    "extend_category_id_3" => $extend_category_id_3s_str[$k]
                ];
            }
        }
        $data['extend_category_name'] = "";
        $data['extend_category'] = $extend_category_array;

        return $data;
    }
    
    /**
     * 根据当前商品分类组装分类名称
     * category_name 转换为 short_name
     * @param $category_id_1
     * @param $category_id_2
     * @param $category_id_3
     * @return string
     */
    public function getGoodsCategoryName($category_id_1, $category_id_2, $category_id_3)
    {
        $name = '';
        $goods_category = new VslGoodsCategoryModel();
        $info_1 = $goods_category->getInfo([
            'category_id' => $category_id_1
        ], 'short_name');
        $info_2 = $goods_category->getInfo([
            'category_id' => $category_id_2
        ], 'short_name');
        $info_3 = $goods_category->getInfo([
            'category_id' => $category_id_3
        ], 'short_name');

        if (!empty($info_1['short_name'])) {
            $lg_symbol = !empty($info_2['short_name']) ? '>' : '';
            $name = $info_1['short_name'] . $lg_symbol;
        }
        if (!empty($info_2['short_name'])) {
            $lg_symbol = !empty($info_3['short_name']) ? '>' : '';
            $name = $name . '' . $info_2['short_name'] . $lg_symbol;
        }
        if (!empty($info_3['short_name'])) {
            $name = $name . '' . $info_3['short_name'];
        }
        return $name;
    }
    /**
     * @param array $input
     * @return int
     */
    public function addCouponType(array $input)
    {
        $coupon_type = new VslCouponTypeModel();
        $coupon_type->startTrans();
        try {
            $goods_list = $input['goods_list'];
            unset($input['goods_list']);
            $coupon_type_id = $coupon_type->save($input); 
            // 添加类型商品表
            if ($input['range_type'] == 0 && !empty($goods_list)) {
                $goods_list_array = explode(',', $goods_list);
                $all_list = [];
                foreach ($goods_list_array as $k => $v) {
                    $all_list[] = [
                        'coupon_type_id' => $coupon_type_id,
                        'goods_id' => $v,
                        'website_id' => $this->website_id
                    ];
                }
                $coupon_goods = new VslCouponGoodsModel();
                $coupon_goods->saveAll($all_list);
            }
            $coupon_type->commit();
            return 1;
        } catch (\Exception $e) {
            $coupon_type->rollback();
            return $e->getMessage();
        }
    }

    /**
     * @param array $input
     * @return int
     */
    public function updateCouponType(array $input)
    {
        $coupon_type = new VslCouponTypeModel();
        $coupon_type->startTrans();
        try {
            $goods_list = $input['goods_list'];
            unset($input['goods_list']);
            $coupon_type->save($input, [
                'coupon_type_id' => $input['coupon_type_id']
            ]);
            // 更新类型商品表
            $coupon_goods = new VslCouponGoodsModel();
            $coupon_goods->destroy([
                'coupon_type_id' => $input['coupon_type_id']
            ]);
            if ($input['range_type'] == 0 && !empty($goods_list)) {
                $goods_list_array = explode(',', $goods_list);
                $all_list = [];
                foreach ($goods_list_array as $k => $v) {
                    $all_list[] = [
                        'coupon_type_id' => $input['coupon_type_id'],
                        'goods_id' => $v,
                        'website_id' => $this->website_id
                    ];
                }
                $coupon_goods->saveAll($all_list);
            }
            // 修改优惠券时，更新优惠券的使用状态
            $coupon = new VslCouponModel();
            $coupon_condition['state'] = array(
                'in', [0, 3]
            ); // 未领用或者已过期的优惠券
            $coupon_condition['coupon_type_id'] = $input['coupon_type_id'];
            $coupon->save([
                'start_receive_time' => $input['start_receive_time'],
                'end_receive_time' => $input['end_receive_time'],
                'end_time' => $input['end_time'],
                'start_time' => $input['start_time'],
                'state' => 0
            ], $coupon_condition);
            $coupon_type->commit();
            return 1;
        } catch (\Exception $e) {
            $coupon_type->rollback();
            return 0;
        }
    }

    /**
     * @param int $page_index
     * @param int $page_size
     * @param array $condition
     * @param array $where
     * @param string $fields
     * @param string $order
     *
     * @return array $result
     */
    public function getCouponHistory($page_index = 1, $page_size = 0, array $condition = [], array $where = [], $fields = '*', $order = '')
    {
        $coupon_model = new VslCouponModel();
        $coupon_model->alias('nc')
            ->join('vsl_order no', 'nc.use_order_id = no.order_id', 'LEFT')
            ->join('vsl_shop ns', 'nc.shop_id = ns.shop_id AND nc.website_id = ns.website_id', 'LEFT')
            ->join('sys_user su', 'nc.uid = su.uid', 'LEFT')
            ->join('vsl_coupon_type nct', 'nc.coupon_type_id = nct.coupon_type_id', 'LEFT')
            ->field($fields)
            ->where($where);
        if (!empty($condition)) {
            $coupon_model->where(function ($query) use ($condition) {
                $query->whereOr($condition);
            });
        }
        if (!empty($order)) {
            $coupon_model->order($order);
        }

        if (!empty($page_index) && !empty($page_size)) {
            $coupon_model->limit($page_size * ($page_index - 1) . ',' . $page_size);
        }

        $list = $coupon_model->select();
        
        $coupon_model->alias('nc')
            ->join('vsl_order no', 'nc.use_order_id = no.order_id', 'LEFT')
            ->join('vsl_shop ns', 'nc.shop_id = ns.shop_id AND nc.website_id = ns.website_id', 'LEFT')
            ->join('sys_user su', 'nc.uid = su.uid', 'LEFT')
            ->join('vsl_coupon_type nct', 'nc.coupon_type_id = nct.coupon_type_id', 'LEFT')
            ->field($fields)
            ->where($where);
        if (!empty($condition)) {
            $coupon_model->where(function ($query) use ($condition) {
                $query->whereOr($condition);
            });
        }
        $count = $coupon_model->count();
        $result = $coupon_model->setReturnList($list, $count, $page_size);
        if($result['data']){
            $user = new UserModel();
            foreach ($result['data'] as $k => $v) {
                if($v['send_uid'] == 0){
                    continue;
                }
                $user_info = $user->getInfo(['uid' => $v['send_uid']], 'nick_name,user_name,user_tel,user_headimg');
                if ($user_info['user_name']) {
                    $result['data'][$k]['send_name'] = $user_info['user_name'];
                } elseif ($user_info['nick_name']) {
                    $result['data'][$k]['send_name'] = $user_info['nick_name'];
                } elseif ($user_info['user_tel']) {
                    $result['data'][$k]['send_name'] = $user_info['user_tel'];
                }
            }
        }
        
        return $result;
    }

    /**
     * 使用优惠券
     * @param $coupon_id
     * @param $order_id
     * @return false|int
     * @throws \think\Exception\DbException
     */
    public function useCoupon($coupon_id, $order_id)
    {
        $couponModel = new VslCouponModel();
        $coupon = $couponModel::get($coupon_id, ['coupon_type']);
        $data = array(
            'use_order_id' => $order_id,
            'state' => 2,
            'use_time' => time(),
            'create_order_id' => 0,
            'money' => $coupon->coupon_type->money,
            'discount' => $coupon->coupon_type->discount,
            'start_receive_time' => $coupon->coupon_type->start_receive_time,
            'end_receive_time' => $coupon->coupon_type->end_receive_time,
            'start_time' => $coupon->coupon_type->start_time,
            'end_time' => $coupon->coupon_type->end_time
        );
        $res = $couponModel->save($data, ['coupon_id' => $coupon_id]);
        return $res;
    }

    /**
     * 订单返还会员优惠券
     * @param int|string $coupon_id
     *
     * @return int $result
     */
    public function UserReturnCoupon($coupon_id)
    {
        $coupon = new VslCouponModel();
        $data = [
            'state' => 1,
            'website_id' => $this->website_id
        ];
        $result = $coupon->save($data, ['coupon_id' => $coupon_id]);
        return $result;
    }

    /**
     * 获取优惠券金额
     * @param int|string $coupon_id
     *
     * @return int
     */
    public function getCouponMoney($coupon_id)
    {
        $coupon = new VslCouponModel();
        $money = $coupon->getInfo(['coupon_id' => $coupon_id, 'website_id' => $this->website_id], 'money');
        if (!empty($money['money'])) {
            return $money['money'];
        } else {
            return 0;
        }
    }
    /**
     * 获取用户优惠券信息
     * @param int|string $coupon_id
     * @return int
     */
    public function getUserCouponInfo($coupon_id)
    {
        $coupon = new VslCouponModel();
        $user_coupon_info = $coupon->getInfo(['coupon_id' => $coupon_id, 'website_id' => $this->website_id], '*');
        return $user_coupon_info;
    }

    /**
     * 查询当前会员优惠券列表
     * @param int $state 1:未使用,2:已使用,3:已过期
     * @param int $shop_id
     * @param int $page_index
     * @param int $page_size
     *
     * @return array $coupon_list
     */
    public function getUserCouponList($state = '', $shop_id = '', $page_index = 1, $page_size = 0)
    {
        $condition['nc.uid'] = $this->uid;
        $condition['ct.website_id'] = $this->website_id;

        if ($state == 3) {
            $condition['ct.end_time'] = ['ELT', time()];
            $condition['nc.state'] = ['EQ', 1];
        } elseif($state == 2) {
            $condition['nc.state'] = $state;
        } else {
            $condition['nc.state'] = $state;
            $condition['ct.end_time'] = ['EGT', time()];
        }

        if (!empty($shop_id)) {
            $condition['ct.shop_id'] = $shop_id;
        }
        $coupon = new VslCouponModel();
        $coupon_list = $coupon->getCouponViewList($page_index, $page_size, $condition, 'nc.start_time desc');
        $list = [];
        $user = new Member();
        if (!empty($coupon_list['data'])) {
            foreach ($coupon_list['data'] as $k => $v) {
                if ($v['shop_range_type'] == 2) {
                    $list['data'][$k]['range'] = '全平台';
                } elseif ($v['shop_range_type'] == 1) {
                    $list['data'][$k]['range'] = '直营店';
                }
                if ($v['shop_id']) {
                    $list['data'][$k]['range'] = $v['shop_name'];
                }
                if ($v['state'] == 1) {
                    $list['data'][$k]['state_name'] = '未使用';
                } elseif ($v['state'] == 2) {
                    $list['data'][$k]['state_name'] = '已使用';
                } elseif ($v['state'] == 3) {
                    $list['data'][$k]['state_name'] = '已过期';
                }
                if ($v['coupon_genre']) {
                    $list['data'][$k]['genre'] = '无门槛券';
                }
                $list['data'][$k]['state'] = $state ?: $v['state'];
                $list['data'][$k]['coupon_code'] = $v['coupon_code'];
                $list['data'][$k]['start_time'] = date('Y-m-d H:i:s', $v['start_time']);
                $list['data'][$k]['end_time'] = date('Y-m-d H:i:s', $v['end_time']);
                $list['data'][$k]['shop_range_type'] = $v['shop_range_type'];
                $list['data'][$k]['range_type'] = $v['range_type'];
                $list['data'][$k]['coupon_genre'] = $v['coupon_genre'];
                $list['data'][$k]['discount'] = $v['discount'];
                $list['data'][$k]['money'] = $v['money'];
                $list['data'][$k]['coupon_name'] = $v['coupon_name'];
                $list['data'][$k]['at_least'] = $v['at_least'];
                $list['data'][$k]['shop_id'] = $v['shop_id'];
            }
            foreach ($list['data'] as $list2) {
                $list2["shop_id"] = $user->getShopNameByShopId($list2["shop_id"]);
                $list2["state"] = "未使用";
            }
        } else {
            $list['data'] = [];
        }
        $list['total_count'] = $coupon_list['total_count'];
        $list['page_count'] = $coupon_list['page_count'];
        return $list;
    }

    /**
     * 删除优惠券
     * @param int|string $coupon_type_id
     *
     * @return int 1
     */
    public function deleteCouponType($coupon_type_id)
    {
        $coupon_type = new VslCouponTypeModel();
        $coupon_type->startTrans();
        try {
            $coupon_type_info = $coupon_type::get($coupon_type_id, ['coupons']);
            if ($coupon_type_info->coupons()->count() == 0 || $coupon_type_info->end_time < time()) {
                $coupon_good_model = new VslCouponGoodsModel();
                $coupon_good_model::destroy(['coupon_type_id' => $coupon_type_id]);
                $relation_model = new VslRegisterMarketingCouponTypeModel();
                $relation_model::destroy(['coupon_type_id' => $coupon_type_id]);
                $coupon_model = new VslCouponModel();
                $coupon_model::destroy(['coupon_type_id' => $coupon_type_id]);
                $coupon_type::destroy(['coupon_type_id' => $coupon_type_id]);
                $coupon_type->commit();
                return 1;
            }
            return -1;
        } catch (\Exception $e) {
            $coupon_type->rollback();
            return $e->getMessage();
        }
    }

    /**
     * 获取会员下面的优惠券列表
     * @param int|string $shop_id
     * @param int|string $uid
     *
     * @return  array
     */
    public function getMemberCouponTypeList($shop_id, $uid)
    {
        // 查询可以发放的优惠券类型
        $coupon_type_model = new VslCouponTypeModel();
        $condition = [
            'start_receive_time' => ['ELT', time()],
            'end_receive_time' => ['EGT', time()],
            'start_time' => ['ELT', time()],
            'end_time' => ['EGT', time()],
            'is_fetch' => 1,
            'shop_id' => $shop_id,
            'website_id' => $this->website_id
        ];
        $coupon_type_list = $coupon_type_model->getQuery($condition, '*', '');
        if (!empty($uid)) {
            $list = [];
            if (!empty($coupon_type_list)) {
                $coupon = new VslCouponModel();
                foreach ($coupon_type_list as $k => $v) {
                    if ($v['max_fetch'] == 0) {
                        // 不限领
                        $list[] = $v;
                    } else {
                        $count = $coupon->getCount([
                            'uid' => $uid,
                            'coupon_type_id' => $v['coupon_type_id'],
                            'website_id' => $this->website_id
                        ]);
                        if ($count < $v['max_fetch']) {
                            $list[] = $v;
                        }
                    }
                }
            }
            return $list;
        } else {
            return $coupon_type_list;
        }
    }

    public function getShopCouponList(array $condition = [])
    {
        //获取用户全部可使用的优惠券
        $coupon_model = new VslCouponTypeModel();
        return $coupon_model::all($condition);
    }

    public function getMemberCouponListNew(array $cart_sku_info)
    {
        if (!getAddons($this->addons_name, $this->website_id)) {
            return [];
        }
        //获取用户全部可使用的优惠券
        $coupon_model = new VslCouponModel();
        $condition['vsl_coupon_model.uid'] = $this->uid;
        $condition['coupon_type.website_id'] = $this->website_id;
        $condition['coupon_type.start_time'] = ['ELT', time()];
        $condition['coupon_type.end_time'] = ['GT', time()];
        $condition['vsl_coupon_model.state'] = 1;
        // 判断优惠券是否赠送中
        $send_cond['c.uid'] = $this->uid;
        $send_cond['cr.expire_time'] = ['>=', time()];
        $coupon_records = new VslShareCouponRecordsModel();
        $sending_coupon_list = $coupon_records->alias('cr')->join('vsl_coupon c', 'cr.coupon_id = c.coupon_id', 'left')->where($send_cond)->select();
        if($sending_coupon_list){
            $sending_coupon_list = objToArr($sending_coupon_list);
            $sending_coupon_arr = array_column($sending_coupon_list, 'coupon_id');
            $condition['vsl_coupon_model.coupon_id'] = ['not in', $sending_coupon_arr];
        }
        $member_coupon_list = $coupon_model::all($condition, ['coupon_type.goods']);
        $coupon_lists = [];
        
        foreach ($cart_sku_info as $shop_id => $sku_info) {
            foreach ($member_coupon_list as $coupon) {
                
                if ($coupon->coupon_type->shop_range_type == 1 && $coupon->coupon_type->shop_id != $shop_id) {
                    //只有直营店或者本店可用,但是shop_id不匹配
                    continue;
                }
                if ($coupon->coupon_type->range_type == 1) {//全部商品使用范围
                    $total_price = 0.00;
                    foreach ($sku_info as $sku) {
                        $total_price += $sku['discount_price'] * $sku['num'];
                        if (isset($sku['full_cut_percent_amount'])) {
                            $total_price -= $sku['full_cut_percent_amount'];
                        }
                    }
                    if ($coupon->coupon_type->at_least <= $total_price && $total_price > 0) {
                        $coupon['goods_limit'] = [];
                        $coupon_lists[$shop_id]['coupon_info'][$coupon['coupon_id']] = $coupon;

                        if ($coupon->coupon_type->coupon_genre == 1 || $coupon->coupon_type->coupon_genre == 2) {
                            
                            $i = 0;
                            $percent = 0;
                            $money = 0;
                            $length = count($sku_info);
                            foreach ($sku_info as $sku_id => $sku) {
                                $i++;
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['sku_id'] = $sku_id;
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($coupon->coupon_type->money * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'], 2);
                                //优惠券优惠金额按价格占比分摊给每个商品,最后一个商品取剩下的优惠金额,避免误差
                                if($i != $length){
                                    $percent += round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                    $money +=  round($coupon->coupon_type->money * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'], 2);
                                }else{
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = 1 - $percent;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($coupon->coupon_type->money - $money,2);
                                }
                            }
                        } elseif ($coupon->coupon_type->coupon_genre == 3) {
                          	$i = 0;
                          	$percent = 0;
                                $money = 0;
                                $allCount = round($total_price * (10-$coupon->coupon_type->discount)/10, 2);
                                
                          	$length = count($sku_info);
                                foreach ($sku_info as $sku_id => $sku) {
                                $i++;
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['sku_id'] = $sku_id;
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round((10-$coupon->coupon_type->discount) * $total_price * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] / 10, 2);
                                if($i != $length){
                                    
                                    $percent += round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                    $money += round((10-$coupon->coupon_type->discount) * $total_price * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] / 10, 2);
                                }else{
                                    
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = 1- $percent;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($allCount - $money,2);
                                }
                            }
                        }
                    }
                } elseif ($coupon->coupon_type->range_type == 0) {//部分指定商品可用
                    $goods_list = [];
                    foreach ($coupon->coupon_type->goods as $coupon_goods) {
                        $goods_list[] = $coupon_goods['goods_id'];
                    }
                    $total_price = 0.00;
                    $all_goods_in_promotion = false;
                    $count_coupon_sku = [];
                    foreach ($sku_info as $sku) {
                        if (in_array($sku['goods_id'], $goods_list)) {
                            $all_goods_in_promotion = true;
                            $total_price += $sku['discount_price'] * $sku['num'];
                            $count_coupon_sku[] = $sku['sku_id'];
                            if (isset($sku['full_cut_percent_amount'])) {
                                
                                $total_price -= $sku['full_cut_percent_amount'];
                            }
                        }
                    }
                  
                    if ($coupon->coupon_type->at_least <= $total_price && $all_goods_in_promotion && $total_price > 0) {
                        $coupon['goods_limit'] = $goods_list;
                        $coupon_lists[$shop_id]['coupon_info'][$coupon['coupon_id']] = $coupon;

                        //计算每个sku的优惠金额比例 如果卷金额大于商品金额 则为100%;
                        if ($coupon->coupon_type->coupon_genre == 1 || $coupon->coupon_type->coupon_genre == 2) {
                                $i = 0;
                          	$percent = 0;
                          	$money = 0;
                          	$length = count($count_coupon_sku);
                              
                            //商品1（100）   商品2（100）
                            //卷（200） --》 商品1  总-200 == 0
                            //实际100   ==》 0
                            //先给定一个初始值 累计不超出 用完要复原
                            $coupon_sku_money = $coupon->coupon_type->money > $total_price ? $total_price : $coupon->coupon_type->money;
                            //满减送的优先级大于优惠券，由于同一商品 先被满减送抵扣了，所以总额应该变更为满减后的总额
                            $sku_total = 0;
                          	foreach ($sku_info as $sku_id => $sku) {
                                if (in_array($sku['goods_id'], $goods_list)) {
                                    $i++;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['sku_id'] = $sku_id;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($coupon_sku_money * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'], 2);
                                   if($i != $length){
                                        $percent += round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                        $money += round($coupon_sku_money * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'], 2);
                                    }else{
                                        $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = 1 - $percent;
                                        $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($coupon_sku_money - $money,2);
                                    }
                                    if($coupon->coupon_type->money >= $total_price){
                                        $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount'], 2);
                                    }
                                }
                            }
                            
                            
                        } elseif ($coupon->coupon_type->coupon_genre == 3) {
                            $i = 0;
                          	$percent = 0;
                          	$money = 0;
                          	$allCount = round($total_price * (10-$coupon->coupon_type->discount)/10, 2);
                          	$length = count($count_coupon_sku);
                          	foreach ($sku_info as $sku_id => $sku) {
                                if (in_array($sku['goods_id'], $goods_list)) {
                                   $i++;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['sku_id'] = $sku_id;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round((10-$coupon->coupon_type->discount) * $total_price * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] / 10, 2);
                                    if($i != $length){
                                        $percent += round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                        $money += round((10-$coupon->coupon_type->discount) * $total_price * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] / 10, 2);
                                    }else{
                                        $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = 1- $percent;
                                        $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($allCount - $money,2);
                                    }
                                }
                            }
                        }
                    }
                } elseif ($coupon->coupon_type->range_type == 2) {//指定分类商品可用
                   
                    $category_extend_id = $coupon->coupon_type->category_extend_id;
                    $extend_category_id_1s = $coupon->coupon_type->extend_category_id_1s;
                    $extend_category_id_2s = $coupon->coupon_type->extend_category_id_2s;
                    $extend_category_id_3s = $coupon->coupon_type->extend_category_id_3s;
                    $total_price = 0.00;
                    $all_goods_in_promotion = false;
                    $count_coupon_sku = [];
                    
                    foreach ($sku_info as $sku) {
                        //商品id换取分类信息
                        $goods_info = $this->goods_ser->getGoodsDetailById($sku['goods_id'], 'category_id_1,category_id_2,category_id_3');
                        $goods_category_arr = [
                            $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
                        ];
                        $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                        if (!$goods_category_arr || !array_intersect($goods_category_arr,explode(',', $category_extend_id))) {
                            continue;
                        }
                        $all_goods_in_promotion = true;
                        $total_price += $sku['discount_price'] * $sku['num'];
                        $count_coupon_sku[] = $sku['sku_id'];
                        if (isset($sku['full_cut_percent_amount'])) {
                            $total_price -= $sku['full_cut_percent_amount'];
                        }
                    }
                    
                    if ($coupon->coupon_type->at_least <= $total_price && $all_goods_in_promotion && $total_price > 0) {
                        // $coupon['goods_limit'] = $goods_list;
                        $coupon['goods_limit'] = [];
                        $coupon_lists[$shop_id]['coupon_info'][$coupon['coupon_id']] = $coupon;

                        //计算每个sku的优惠金额比例
                        if ($coupon->coupon_type->coupon_genre == 1 || $coupon->coupon_type->coupon_genre == 2) {
                            $i = 0;
                          	$percent = 0;
                          	$money = 0;
                          	$length = count($count_coupon_sku);
                            $coupon_sku_money = $coupon->coupon_type->money > $total_price ? $total_price : $coupon->coupon_type->money;
                            foreach ($sku_info as $sku_id => $sku) {
                                //商品id换取分类信息
                                $goods_info = $this->goods_ser->getGoodsDetailById($sku['goods_id'], 'category_id_1,category_id_2,category_id_3');
                                $goods_category_arr = [
                                    $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
                                ];
                                $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                                if (!$goods_category_arr || !array_intersect($goods_category_arr,explode(',', $category_extend_id))) {
                                    continue;
                                }
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['sku_id'] = $sku_id;
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($coupon_sku_money * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'], 2);
                                if($i != $length){
                                    $percent += round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                    $money += round($coupon_sku_money * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'], 2);
                                }else{
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = 1 - $percent;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($coupon_sku_money - $money,2);
                                }
                                if($coupon->coupon_type->money >= $total_price){
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount'], 2);
                                }
                            }
                           
                        } elseif ($coupon->coupon_type->coupon_genre == 3) {
                            $i = 0;
                          	$percent = 0;
                          	$money = 0;
                            $allCount = round($total_price * (10-$coupon->coupon_type->discount)/10, 2);
                          	$length = count($count_coupon_sku);
                            foreach ($sku_info as $sku_id => $sku) {
                                    //商品id换取分类信息
                                $goods_info = $this->goods_ser->getGoodsDetailById($sku['goods_id'], 'category_id_1,category_id_2,category_id_3');
                                $goods_category_arr = [
                                    $goods_info['category_id_1'],$goods_info['category_id_2'],$goods_info['category_id_3']
                                ];
                                $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                                if (!$goods_category_arr || !array_intersect($goods_category_arr,explode(',', $category_extend_id))) {
                                    continue;
                                }
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['sku_id'] = $sku_id;
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round((10-$coupon->coupon_type->discount) * $total_price * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] / 10, 2);
                                if($i != $length){
                                    $percent += round(($sku['discount_price'] * $sku['num'] - $sku['full_cut_percent_amount']) / $total_price, 2);
                                    $money += round((10-$coupon->coupon_type->discount) * $total_price * $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] / 10, 2);
                                }else{
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent'] = 1- $percent;
                                    $coupon_lists[$shop_id]['sku_percent'][$coupon['coupon_id']][$sku_id]['coupon_percent_amount'] = round($allCount - $money,2);
                                }
                            }
                        }
                    }
                }
            }
            
        }
        return $coupon_lists;
    }

    /**
     * 获取优惠券剩余数目
     * @param     $coupon_type_id
     * @param int $uid
     * @return int
     * @throws \think\Exception\DbException
     */
    public function getRestCouponType($coupon_type_id, $uid = 0)
    {
        $coupon_type_model = new VslCouponTypeModel();
        $coupon_type_info = $coupon_type_model::get($coupon_type_id, ['coupons']);
        $rest = $coupon_type_info['count'] - count($coupon_type_info->coupons);
        if($coupon_type_info['count']==0)$rest = 10000;//无限领
        if ($rest <= 0)return 0;
        //没有uid 返回该优惠券的剩余数目;有uid 返回该uid用户可领取该优惠券的数目
        if (empty($uid) || $coupon_type_info['max_fetch'] == 0) {
            return $rest;
        } else {
            $u_rest = $coupon_type_info['max_fetch'] - $coupon_type_info->coupons()->where('uid', $uid)->count();
            if($u_rest <= 0) return 0;
            return ($u_rest > $rest) ? $rest : $u_rest;
        }
    }

    /**
     * 判读优惠券是否科领取，不可领取时返回0，可领取返回可领取数目
     * @param       $coupon_type_id
     * @param       $uid
     * @param int   $time
     * @param array $send_data
     * @return int
     * @throws \think\Exception\DbException
     */
    public function isCouponTypeReceivable($coupon_type_id, $uid, $time = 0, $send_data = [])
    {
        if($send_data['coupon_id']){//有coupon_id就是用户优惠券id
            $coupon_id = $send_data['coupon_id'];
            $page_code = $send_data['page_code'];
            $coupon_time = $send_data['coupon_time'];
            $coupon_mdl = new VslCouponModel();
            $coupon_type = new VslCouponTypeModel();
            $coupon_info = $coupon_mdl->getInfo(['coupon_id' => $coupon_id], 'state, uid, coupon_type_id');
            $ct_info = $coupon_type->getInfo(['coupon_type_id' => $coupon_info['coupon_type_id']], 'expire_time');
            if($ct_info && $ct_info['expire_time'] != 0){
                if($page_code){
                    //验签
                    $sign = md5($coupon_id.$coupon_time.API_KEY);
                    if($sign != $page_code){
                        return -4;//已失效
                    }
                    $fetch_time = time();
                    //将过期时间跟优惠券存入关系表
                    $data['expire_time'] = $fetch_time + $ct_info['expire_time'] * 3600;
                    $data['coupon_id'] = $coupon_id;
                    $data['uid'] = $uid;
                    $data['page_code'] = $page_code;
                    $data['create_time'] = $fetch_time;
                    $this->saveCouponTime($data);
                    //查询时间是否过期
                    $share_cond['uid'] = $uid;
                    $share_cond['page_code'] = $page_code;
                    $sc_records = new VslShareCouponRecordsModel();
                    $expire_time = $sc_records->getInfo($share_cond, 'expire_time')['expire_time'];
                    if($expire_time < time()){
                        return -4;//已失效
                    }
                }else{
                    return -4;
                }
            }
            //点击链接进来的用户
            if($this->uid != 0){
                if($this->uid != $coupon_info['uid']){//进来用户id跟优惠券所属uid不一样
                    $cond['coupon_pid'] = $coupon_id;
                    $cond['uid'] = $this->uid;
                    $cond['get_type'] = 14;//赠送优惠券
                    $coupon_info2 = $coupon_mdl->getInfo($cond);
                    if($coupon_info2){
                        //被赠送者的优惠券是否过期，过期直接失效
                        if($coupon_info2['state'] == 1){
                            return 0;
                        }
                    }else{
                        if(!$coupon_info){//如果都找不到了，则送出去了。
                            return 0;//已领取
                        }else{
                            return 1;
                        }
                    }
                }else{
                    return -3;//自己不可以领取自己的券
                }
            }else{
                return 1;
            }
        }else{
            $coupon_type_model = new VslCouponTypeModel();
            if (empty($time)) {
                $time = time();
            }
            $coupon_type_info = $coupon_type_model::get($coupon_type_id, ['coupons']);
            if($time < $coupon_type_info['start_receive_time']){
                return -1;//未开始
            }
            if($time > $coupon_type_info['end_receive_time']){
                return -2;//已过期
            }

            // 已经被领取、使用了全部数目
            $rest = $coupon_type_info['count'] - count($coupon_type_info->coupons);
            if($coupon_type_info['count']==0)$rest=10000;
            if ($rest <= 0) {
                return 0;
            }
            //没有uid 返回该优惠券的剩余数目;有uid 返回该uid用户可领取该优惠券的数目 14为赠送优惠券，赠送优惠券跟领取优惠券是独立的，不会互相影响，故去掉14这种方式
            if (empty($uid) || $coupon_type_info['max_fetch'] == 0) {
                return $rest;
            } else {
                $u_rest = $coupon_type_info['max_fetch'] - $coupon_type_info->coupons()->where(['uid' => $uid, 'get_type' => ['NEQ', 14]])->whereOr(function($query) use($uid){
                        $query->where('send_uid',$uid)->where(['send_get_type'=>['not in', [14,0]]]);
                    })->count();
                return ($u_rest > $rest) ? $rest : $u_rest;
            }
        }
    }



    /**
     * 用户获取优惠券
     * @param int|string $uid
     * @param int|string $coupon_type_id
     * @param int $get_type
     * @param int $is_appointgivecoupon 是否是定向送优惠券
     *
     * @return  int $result
     */
    public function userAchieveCoupon($uid, $coupon_type_id, $get_type, $is_appointgivecoupon = 0)
    {
        $coupon = new VslCouponModel();
        $coupon_type = new VslCouponTypeModel();
        $coupon_type_detail = $coupon_type::get($coupon_type_id);
        //查询该优惠券总共领取了多少张
        $coupon_total_fetch = $coupon->where(['coupon_type_id'=>$coupon_type_id])->count();
        //查询该优惠券该用户领取了多少张
        $coupon_user_count = $coupon->where(['uid'=>$uid, 'coupon_type_id'=>$coupon_type_id, 'get_type' => ['NEQ', 14]])->whereOr(function($query) use($uid){
            $query->where('send_uid',$uid)->where(['send_get_type'=>['not in', [14,0]]]);
        })->count();
        //该优惠券每个用户最多可以领取多少张
        $max_fetch = $coupon_type_detail['max_fetch'];
        //该优惠券数量总额
        $counpon_total = $coupon_type_detail['count'];
        if ($coupon_type_detail) {
            if($coupon_total_fetch >= $counpon_total && $counpon_total!=0){
                return NO_COUPON;
            }
            if($coupon_user_count >= $max_fetch && $max_fetch!=0 && $is_appointgivecoupon == 0){
                return USER_GET_LIMIT;
            }
            $data = [
                'uid' => $uid,
                'state' => 1,
                'get_type' => $get_type,
                'money' => $coupon_type_detail['money'],
                'fetch_time' => time(),
                'coupon_type_id' => $coupon_type_id,
                'coupon_code' => time() . rand(111, 999),
                'shop_id' => $coupon_type_detail->shop_id,
                'website_id' => $coupon_type_detail->website_id,
                'start_receive_time' => $coupon_type_detail->start_receive_time,
                'end_receive_time' => $coupon_type_detail->end_receive_time,
                'start_time' => $coupon_type_detail->start_time,
                'end_time' => $coupon_type_detail->end_time
            ];
            $result = $coupon->save($data);
        } else {
            $result = NO_COUPON;
        }
        return $result;
    }
    
    /**
     * 用户获取朋友优惠券
     * @param int|string $uid
     * @param int $get_type
     * @return  int $result
     */
    public function userAchieveFriendCoupon($uid, $get_type, $user_coupon_info)
    {
        try{
            Db::startTrans();
            $coupon = new VslCouponModel();
            $coupon_type = new VslCouponTypeModel();
            $coupon_type_id = $user_coupon_info['coupon_type_id'];
            $coupon_type_detail = $coupon_type::get($coupon_type_id);
            //判断是否领取过这张券了
            if ($coupon_type_detail) {
                $cond1['uid'] = $user_coupon_info['uid'];
                $cond1['coupon_id'] = $user_coupon_info['coupon_id'];
                $origin_coupon = $coupon->getInfo($cond1);
                $cond['uid'] = $uid;
                $cond['coupon_pid'] = $user_coupon_info['coupon_id'];
                $is_get_coupon = $coupon->getInfo($cond);
                if(!$is_get_coupon){
                    $fetch_time = time();
                    $data = [
                        'uid' => $uid,
                        'state' => 1,
                        'get_type' => $get_type,
                        'money' => $coupon_type_detail['money'],
                        'fetch_time' => $fetch_time,
                        'coupon_type_id' => $coupon_type_id,
//                        'coupon_code' => time() . rand(111, 999),
                        'coupon_code' => $user_coupon_info['coupon_code'],
                        'shop_id' => $coupon_type_detail->shop_id,
                        'website_id' => $coupon_type_detail->website_id,
                        'start_receive_time' => $coupon_type_detail->start_receive_time,
                        'end_receive_time' => $coupon_type_detail->end_receive_time,
                        'start_time' => $coupon_type_detail->start_time,
                        'end_time' => $coupon_type_detail->end_time,
                        'send_uid' => $user_coupon_info['uid'],
                        'send_get_type' => $origin_coupon['get_type'],
                        'coupon_pid' => $user_coupon_info['coupon_id'],
                    ];
                    $res = $coupon->isUpdate(false)->save($data);
                    if($res){
                        $result = $coupon->where($cond1)->delete();
                    }
                }
            } else {
                $result = NO_COUPON;
            }
            Db::commit();
            return $result;
        }catch(\Exception $e){
            Db::rollback();
            echo $e->getMessage();exit;
        }
    }

    /**
     * 保存页面唯一标识
     * @param $data
     */
    public function saveCouponTime($data)
    {
        $sc_records = new VslShareCouponRecordsModel();
        $cond['coupon_id'] = $data['coupon_id'];
        $cond['uid'] = $data['uid'];
        $cond['page_code'] = $data['page_code'];
        $is_sc_records = $sc_records->getInfo($cond);
        if(!$is_sc_records){
            $sc_records->save($data);
        }
    }
    /**
     * 领取优惠券
     */
    public function getUserReceive($uid,$coupon_type_id,$get_type,$state=1)
    {
        $condition['coupon_type_id'] = $coupon_type_id;
        $coupon_type = new VslCouponTypeModel();
        $info = $coupon_type::get($condition);
        if($info){
            $coupon = new VslCouponModel();
            $data = [
                'uid' => $uid,
                'state' => $state,
                'get_type' => $get_type,
                'money' => $info['money'],
                'fetch_time' => time(),
                'coupon_type_id' => $coupon_type_id,
                'coupon_code' => time() . rand(111, 999),
                'shop_id' => $info->shop_id,
                'website_id' => $info->website_id,
                'start_receive_time' => $info->start_receive_time,
                'end_receive_time' => $info->end_receive_time,
                'start_time' => $info->start_time,
                'end_time' => $info->end_time
            ];
            $result = $coupon->save($data);
        }else{
            $result = 0;
        }
        return $result;
    }
    
    /**
     * 领取优惠券/冻结改领取
     */
    public function getUserThaw($uid,$coupon_id)
    {
        $condition = [];
        $condition['uid'] = $uid;
        $condition['coupon_id'] = $coupon_id;
        $coupon = new VslCouponModel();
        $info = $coupon->getInfo($condition);
        if($info && $info['state']==-1){
            $result = $coupon->where($condition)->update(['state' => '1']);
        }else{
            $result = 0;
        }
        return $result;
    }

    /**
     * 获取商品优惠劵
     * @param array $goods_id_array
     * @param       $uid
     * @return array
     * @throws \think\Exception\DbException
     */
    public function getGoodsCoupon(array $goods_id_array, $uid)
    {
        $coupon_goods = new VslCouponGoodsModel();
        $coupon_type = new VslCouponTypeModel();
        $couponModel = new VslCouponModel();
       
        $goods_info = $this->goods_ser->getGoodsListByCondition(['goods_id' => ['IN', $goods_id_array]], 'shop_id,category_id,category_id_1,category_id_2,category_id_3');
        
        $shop_id_array = [];
        $new_goods_info = []; //存在分类的商品
        foreach ($goods_info as $k => $goods) {
            if (!in_array($goods['shop_id'], $shop_id_array)) {
                array_push($shop_id_array, $goods['shop_id']);
            }
            if($goods['category_id_1'] || $goods['category_id_2'] || $goods['category_id_3']){
                array_push($new_goods_info, ['shop_id'=>$goods['shop_id'],'category_id'=>$goods['category_id'],'category_id_1'=>$goods['category_id_1'],'category_id_2'=>$goods['category_id_2'],'category_id_3'=>$goods['category_id_3']]);
            }
        }
        
        // 获取全商品优惠劵
        $conditions = [
            'start_receive_time' => ['ELT', time()],
            'end_receive_time' => ['EGT', time()],
            'start_time' => ['ELT', time()],
            'end_time' => ['EGT', time()],
            'range_type' => 1,
            'is_fetch' => 1,
            'website_id' => $this->website_id,
        ];
        if($shop_id_array){
			$whereOr['shop_id'] = ['IN', $shop_id_array, 'OR'];
		}
//      商品所在店鋪优惠券 OR 全平台可用优惠券
//      AND (`shop_id` IN (0, 26) OR (`shop_id` = 0 AND `shop_range_type` = 2))
        $coupon_list = $coupon_type->select(function ($query) use ($conditions, $whereOr) {
            $whereOrAnd['shop_id'] = 0;
            $whereOrAnd['shop_range_type'] = 2;
            $query->where($conditions)->where(function ($q1) use ($whereOr, $whereOrAnd) {
                $q1->where($whereOr)->whereOr(function ($q2) use ($whereOrAnd) {
                    $q2->where($whereOrAnd);
                });
            });
        });

        if (!empty($coupon_list)) {
            foreach ($coupon_list as $k => $v) {
                //全部领取完了
                $countCoupon = $couponModel->getCount(['coupon_type_id' => $v['coupon_type_id'],'state' => ['<>', 0]]);
                if ($v->count <= $countCoupon && $v->count!=0) {
                    unset($coupon_list[$k]);
                    continue;
                }
                //个人达到最高领取数目
                $countMaxCoupon = $couponModel->where(['coupon_type_id' => $v['coupon_type_id'],'uid'=>$uid, 'get_type' => ['<>', 14]])->whereOr(function($query) use($uid){
                            $query->where('send_uid',$uid)->where(['send_get_type'=>['not in', [14,0]]]);
                        })->count();
                if ($v->max_fetch != 0 && $v->max_fetch <= $countMaxCoupon && $v->max_fetch!=0) {
                    unset($coupon_list[$k]);
                    continue;
                }
                $coupon_list[$k]->receive_quantity = $countCoupon;
            }
            unset($v);
        }
        unset($conditions);
        // 通过商品id获取到优惠劵类型
        $coupon_goods_type_id_list = $coupon_goods->getQuery([
            'goods_id' => ['in', $goods_id_array]
        ], 'coupon_type_id', '');
        if ($coupon_goods_type_id_list) {
            $id = [];
            foreach ($coupon_goods_type_id_list as $k => $v) {
                $id[] = $v['coupon_type_id'];
            }
            $conditions = [
                'coupon_type_id' => ['IN', $id],
                'start_receive_time' => ['ELT', time()],
                'end_receive_time' => ['EGT', time()],
                'start_time' => ['ELT', time()],
                'end_time' => ['EGT', time()],
                'range_type' => 0,
                'is_fetch' => 1,
                'website_id' => $this->website_id
            ];
            $coupon_list_again = $coupon_type::all($conditions);
            
            if (!empty($coupon_list_again)) {
                foreach ($coupon_list_again as $k => $v) {
                    $countCoupon = $couponModel->getCount(['coupon_type_id' => $v['coupon_type_id'],'state' => [['>', 0],['<', 0],'or']]);
                    //全部领取完了
                    if ($v->count <= $countCoupon && $v->count!=0) {
                        unset($coupon_list_again[$k]);
                        continue;
                    }
                    //个人达到最高领取数目
                    $countMaxCoupon = $couponModel->where(['coupon_type_id' => $v['coupon_type_id'],'uid'=>$uid, 'get_type' => [['>', 14],['<', 14],'or']])->whereOr(function($query) use($uid){
                                $query->where('send_uid',$uid)->where(['send_get_type'=>['not in', [14,0]]]);
                            })->count();
                    if ($v->max_fetch != 0 && $v->max_fetch <= $countMaxCoupon) {
                        unset($coupon_list_again[$k]);
                        continue;
                    }
                    $coupon_list_again[$k]->receive_quantity = $countCoupon;
                }
                unset($v);
            }
            $coupon_list_again = objToArr($coupon_list_again);//这里合并不了 需要先转数组 edit for 2021/01/06
            $coupon_list = array_merge($coupon_list, $coupon_list_again); 
        }
        
        //通过商品其他分类获取优惠券列表 
        
        $where = ' website_id ='.$this->website_id;
        $where .= ' and range_type =2';
        $where .= ' and is_fetch =1';
        $where .= ' and end_time >='.time();
        $where .= ' and start_time <='.time();
        $where .= ' and end_receive_time >='.time();
        $where .= ' and start_receive_time <='.time();
        $cate_condition = '';
        if($new_goods_info){
            foreach ($new_goods_info as $ks => $vs) {
                //平台的优惠卷 开启全平台的 店铺的分类商品也可以使用 变更至精准到具体分类一致
                if($vs['category_id']){
                    $goods_category_arr = [
                        $vs['category_id_1'],$vs['category_id_2'],$vs['category_id_3']
                    ];
                    $goods_category_arr = array_diff($goods_category_arr,[0]);//去除值0
                    $category_id_str = implode(',',$goods_category_arr);
                    if($ks == count($new_goods_info) - 1) {
                        $cate_condition .= ' ( category_extend_id in ('.$category_id_str.') '; 
                        if($vs['shop_id'] > 0){ //待补充
                            $cate_condition .= ' and (shop_range_type = 2 or shop_id='.$vs['shop_id'].') ';
                        }
                        $cate_condition .= ' ) ';
                    }else{
                        $cate_condition .= ' ( category_extend_id in ('.$category_id_str.') ';
                        if($vs['shop_id'] > 0){ //待补充
                            $cate_condition .= ' and (shop_range_type = 2 or shop_id='.$vs['shop_id'].') ';
                        }
                        $cate_condition .= ' ) or ';
                    }
                    continue;
                }
            }
        }
        
        if($cate_condition){
            $where .= ' and (' . $cate_condition. ')';
        }
        
        //变更sql查询
        $sql = "select * from vsl_coupon_type where ".$where;

		
        $coupon_list_cate = Db::Query($sql);

        if (!empty($coupon_list_cate)) {
            $couponModel = new VslCouponModel();
            foreach ($coupon_list_cate as $kc => $vc) {
                //全部领取完了
                if ($vc['count'] <= $couponModel->getCount(['state' => ['NEQ', 0],'coupon_type_id'=>$vc['coupon_type_id']]) && $vc['count']!=0) {
                    unset($coupon_list_cate[$kc]);
                    continue;
                }
                //个人达到最高领取数目
                if ($vc['max_fetch'] != 0 && ($vc['max_fetch'] <= $couponModel->getCount(['uid' => $uid,'coupon_type_id'=>$vc['coupon_type_id']]))) {
                    unset($coupon_list_cate[$kc]);
                    continue;
                }
                $coupon_list_cate[$kc]['receive_quantity'] = $couponModel->getCount(['state' => ['NEQ', 0],'coupon_type_id'=>$vc['coupon_type_id']]);
            }
        }
        
        if($coupon_list_cate){
            $coupon_list = array_merge($coupon_list, $coupon_list_cate);
        }
        return $coupon_list;
    }

    public function saveCouponConfig($is_coupon)
    {
        $ConfigService = new AddonsConfigService();
        $coupon_info = $ConfigService->getAddonsConfig("coupontype");
        if (!empty($coupon_info)) {
            $res = $ConfigService->updateAddonsConfig('', "优惠券设置", $is_coupon, "coupontype");
        } else {
            $res = $ConfigService->addAddonsConfig('', '优惠券设置', $is_coupon, 'coupontype');
        }
        return $res;
    }
    
    /**
     * 获取优惠券商品列表
     */
    public function couponGoodsList($page_index = 1, $page_size = 0, $condition = [], $field = '*', $order = 'create_time desc',$group= [])
    {
        $coupon_type_model = new VslCouponTypeModel();
        $data = $coupon_type_model::get($condition['coupon_type_id']);
        if($data['shop_range_type']==1){
            $condition['vs.shop_id'] = $data['shop_id'];
        }
        
        if($data['range_type']==0){ //部分商品
            $coupon_goods = new VslCouponGoodsModel();
            $goods_list = $coupon_goods->getCouponGoodsList($page_index,$page_size,$condition,$field,$order,$group);
        }else if($data['range_type']==2){ //分类商品
            $condition['ng.category_id_1|ng.category_id_2|ng.category_id_3'] = ['in',$data['category_extend_id']];
            unset($condition['coupon_type_id']);
            $goodsViewModel = new VslGoodsViewModel();
            $goods_list = $goodsViewModel->getCouponGoodsLists($page_index,$page_size,$condition,$field,$order,$group);
        }else{
            unset($condition['coupon_type_id']);
            $goods_server = new VslGoodsViewModel();
            //供应商关闭，不显示供应商商品
            if (getAddons('supplier',$this->website_id)){
                $condition[] = ['exp', ' if(ng.supplier_info >0, s.status =1, 1)'];//供应商必须为开启
            }
            $goods_list = $goods_server->wapGoods($page_index,$page_size,$condition,$field,$order,$group);
        }
        return $goods_list;
    }

    /**
     * 超时将优惠券返还
     * @param $coupon_info
     * @return false|int
     */
    public function sendCouponBack($coupon_info)
    {
        try{
            Db::startTrans();
            $coupon_mdl = new VslCouponModel();
            $data['state'] = 1;
            $data['uid'] = $coupon_info['send_uid'];
            $cond = ['coupon_id' => $coupon_info['coupon_pid']];
            $res = $coupon_mdl->save($data, $cond);
            if($res){
                //将被赠送者的优惠券删除
                $cond2 = ['coupon_id' => $coupon_info['coupon_id']];
                $coupon_mdl->where($cond2)->delete();
            }
            Db::commit();
            return $res;
        }catch(\Exception $e){
            Db::rollback();
        }
    }
}