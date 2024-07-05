<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008
 * Time: 11:16
 */

namespace addons\coupontype\controller;

use addons\coupontype\Coupontype as baseCoupon;
use addons\coupontype\model\VslCouponModel;
use addons\coupontype\model\VslShareCouponRecordsModel;
use addons\coupontype\server\Coupon as CouponServer;
use addons\coupontype\server\Coupon;
use addons\shop\model\VslShopModel;
use data\model\AlbumPictureModel;
use data\model\VslMemberModel;
use data\model\WebSiteModel;
use data\model\VslGoodsCategoryModel as VslGoodsCategoryModel;
class Coupontype extends baseCoupon
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 根据当前分类ID查询商品分类的三级分类ID
     * @param $category_id
     * @return array
     * @throws \think\Exception\DbException
     */
    private function getGoodsCategoryId($category_id)
    {
        // 获取分类层级
        $goods_category = new VslGoodsCategoryModel();
        $info = $goods_category->get($category_id);
        if ($info['level'] == 1) {
            return [
                $category_id,
                0,
                0
            ];
        }
        if ($info['level'] == 2) {
            // 获取父级
            return [
                $info['pid'],
                $category_id,
                0
            ];
        }
        if ($info['level'] == 3) {
            $info_parent = $goods_category->get($info['pid']);
            // 获取父级
            return [
                $info_parent['pid'],
                $info['pid'],
                $category_id
            ];
        }
    }
    
    /**
     * 我的优惠券
     */
    public function couponList()
    {
        // 获取该用户的所有已领取未使用的优惠券列表

        $couponServer = new CouponServer();
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $type = request()->post('type', 1);
        $list = $couponServer->getUserCouponList($type, '', $page_index, $page_size);
        return $list;
    }

    public function couponTypeList()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $end_receive_time = request()->post('end_receive_time');
        $coupon_type_ids = request()->post('coupon_type_id');
        
        $couponServer = new CouponServer();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        if ($end_receive_time) {
            $condition['end_receive_time'] = ['GT', time()];
        }
        if (input('post.search_text')){
            $condition['coupon_name'] = ['like', '%' . input('post.search_text') . '%'];
        }
        if (input('post.not_expired')) {
            $condition['end_time'] = ['GT', time()];
        }
        if (input('post.excepted_coupon_type_id/a')) {
            $condition['coupon_type_id'] = ['NOT IN', input('post.excepted_coupon_type_id/a')];
        }
        if ($coupon_type_ids){
            unset($condition['shop_id']);//前端接口使用
            $coupon_type_ids = explode(',', $coupon_type_ids);
            $condition['coupon_type_id'] = ['in', $coupon_type_ids];
        }

        $list = $couponServer->getCouponTypeList($page_index, $page_size, $condition, 'create_time desc,coupon_type_id desc', '*');
        //判断pc端、小程序、pc、app是否开启
        $is_coupontype = getAddons(self::$addons_name, $this->website_id);
        $addon_status = getPortIsOpen($this->website_id);
       
        $addon_status['is_coupontype'] = $is_coupontype;
        $list['addon_status'] =  $addon_status;
       
        return $list;
    }

    public function getCouponTypeInfo()
    {
        $coupon_type_id = $_POST['coupon_type_id'];
        $coupon = new CouponServer();
        $coupon_type = $coupon->getCouponTypeDetail($coupon_type_id);

        return $coupon_type;
    }

    public function addCouponType()
    {
        $input = request()->post();
        $input['start_receive_time'] = strtotime($input['start_receive_time']);
        $input['end_receive_time'] = strtotime($input['end_receive_time']) + (86400 - 1);
        $input['start_time'] = strtotime($input["start_time"]);
        $input['end_time'] = strtotime($input["end_time"]) + (86400 - 1);
        $input['create_time'] = time();
        $input['shop_id'] = $this->instance_id;
        $input['website_id'] = $this->website_id;
        $is_biz = request()->post('is_biz', 0);
        unset($input['is_biz']);
        $coupon = new CouponServer();
        // 1级扩展分类
        $extend_category_id_1s = "";
        // 2级扩展分类
        $extend_category_id_2s = "";
        // 3级扩展分类
        $extend_category_id_3s = "";
        $extend_category_id = $input['category_extend_id'];
        if (!empty($extend_category_id)) {
            $extend_category_id_str = explode(",", $extend_category_id);
            foreach ($extend_category_id_str as $extend_id) {
                $extend_category_list = $this->getGoodsCategoryId($extend_id);

                if ($extend_category_id_1s === "") {
                    $extend_category_id_1s = $extend_category_list[0];
                } else {
                    $extend_category_id_1s = $extend_category_id_1s . "," . $extend_category_list[0];
                }
                if ($extend_category_id_2s === "") {
                    $extend_category_id_2s = $extend_category_list[1];
                } else {
                    $extend_category_id_2s = $extend_category_id_2s . "," . $extend_category_list[1];
                }
                if ($extend_category_id_3s === "") {
                    $extend_category_id_3s = $extend_category_list[2];
                } else {
                    $extend_category_id_3s = $extend_category_id_3s . "," . $extend_category_list[2];
                }
            }
            unset($extend_id);
        }
        $input['extend_category_id_1s'] = $extend_category_id_1s;
        $input['extend_category_id_2s'] = $extend_category_id_2s;
        $input['extend_category_id_3s'] = $extend_category_id_3s;

        $ret_val = $coupon->addCouponType($input);
        if ($ret_val) {
            $this->addUserLog('添加优惠券', $ret_val);
        }
        if($is_biz){
            return json(AjaxReturn($ret_val));
        }
        return AjaxReturn($ret_val);
    }

    public function updateCouponType()
    {
        $input = request()->post();
        $input['start_receive_time'] = strtotime($input['start_receive_time']);
        $input['end_receive_time'] = strtotime($input['end_receive_time']) + (86400 - 1);
        $input['start_time'] = strtotime($input["start_time"]);
        $input['end_time'] = strtotime($input["end_time"]) + (86400 - 1);
        $input['update_time'] = time();
        $is_biz = request()->post('is_biz', 0);
        if($is_biz && $input['coupon_genre'] == 3 && ($input['at_least'] <= 0 || $input['discount'] <= 0 || $input['discount'] > 10)){
            return json(AjaxReturn(FAIL,[],'折扣信息设置有误'));
        }
        unset($input['is_biz']);
        $coupon = new CouponServer();
        // 1级扩展分类
        $extend_category_id_1s = "";
        // 2级扩展分类
        $extend_category_id_2s = "";
        // 3级扩展分类
        $extend_category_id_3s = "";
        $extend_category_id = $input['category_extend_id'];
        if (!empty($extend_category_id)) {
            $extend_category_id_str = explode(",", $extend_category_id);
            foreach ($extend_category_id_str as $extend_id) {
                $extend_category_list = $this->getGoodsCategoryId($extend_id);

                if ($extend_category_id_1s === "") {
                    $extend_category_id_1s = $extend_category_list[0];
                } else {
                    $extend_category_id_1s = $extend_category_id_1s . "," . $extend_category_list[0];
                }
                if ($extend_category_id_2s === "") {
                    $extend_category_id_2s = $extend_category_list[1];
                } else {
                    $extend_category_id_2s = $extend_category_id_2s . "," . $extend_category_list[1];
                }
                if ($extend_category_id_3s === "") {
                    $extend_category_id_3s = $extend_category_list[2];
                } else {
                    $extend_category_id_3s = $extend_category_id_3s . "," . $extend_category_list[2];
                }
            }
            unset($extend_id);
        }
        $input['extend_category_id_1s'] = $extend_category_id_1s;
        $input['extend_category_id_2s'] = $extend_category_id_2s;
        $input['extend_category_id_3s'] = $extend_category_id_3s;
        $ret_val = $coupon->updateCouponType($input);
        if ($ret_val) {
            $this->addUserLog('修改优惠券', $ret_val);
        }
        if($is_biz){
            return json(AjaxReturn($ret_val));
        }
        return AjaxReturn($ret_val);
    }

    public function deleteCouponType()
    {
        $coupon_type_id = request()->post('coupon_type_id', '');
        if (empty($coupon_type_id)) {
            $this->error("没有获取到优惠券信息");
        }
        $coupon = new CouponServer();
        $res = $coupon->deleteCouponType($coupon_type_id);
        if ($res) {
            $this->addUserLog('删除优惠券', $coupon_type_id);
        }
        return AjaxReturn($res);
    }

    public function historyCoupon()
    {
        $promotion = new CouponServer();
        $search_text = request()->post('search_text', '');
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $fields = 'nc.coupon_id,nc.coupon_code,nc.money,nc.discount,nc.use_time,su.user_tel,su.nick_name,no.order_no,no.shop_id,ns.shop_name,nc.send_uid';
        // 使用的历史记录,state = 2
        $where['nc.state'] = 2;
        $where['nc.coupon_type_id'] = request()->post('coupon_type_id');
        $where['nc.website_id'] = $this->website_id;
        $where['nc.shop_id'] = $this->instance_id;
        $condition = [];
        if ($search_text) {
            $condition['nc.coupon_code'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['ns.shop_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['su.nick_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['nct.coupon_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
        }

        $list = $promotion->getCouponHistory($page_index, $page_size, $condition, $where, $fields);
        return $list;
    }

    public function couponSetting()
    {
        $couponServer = new CouponServer();
        $is_coupon = $_POST['is_coupon'] ?: 0;

        $result = $couponServer->saveCouponConfig($is_coupon);
        if ($result) {
            $this->addUserLog('添加优惠券设置', $result);
        }
        setAddons(self::$addons_name, $this->website_id, $this->instance_id);
        setAddons(self::$addons_name, $this->website_id, $this->instance_id, true);
        return AjaxReturn($result);

    }

    public function getGoodsCouponType()
    {
        $coupon = new CouponServer();
        $goods_id_array = request()->post('goods_id_array/a');
        $coupon_type_array = $coupon->getGoodsCoupon($goods_id_array, $this->uid);

        return $coupon_type_array;
    }

    /**
     * wap 商品详情、购物车优惠券列表接口
     */
    public function goodsCouponList()
    {
        if (!getAddons(self::$addons_name, $this->website_id)) {
            return json(['code' => 1, 'message' => '应用已关闭', 'data' => []]);
        }
        $coupon = new CouponServer();
        $goods_id_array = request()->post('goods_id_array/a');
        if (empty($goods_id_array)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $coupon_type_array = $coupon->getGoodsCoupon($goods_id_array, $this->uid);
        $coupon_type_list = [];
        foreach ($coupon_type_array as $k => $v) {
            $temp = [];
            $temp['coupon_type_id'] = $v['coupon_type_id'];
            $temp['coupon_name'] = $v['coupon_name'];
            $temp['coupon_genre'] = $v['coupon_genre'];
            $temp['shop_range_type'] = $v['shop_range_type'];
            $temp['at_least'] = $v['at_least'];
            $temp['money'] = $v['money'];
            $temp['discount'] = $v['discount'];
            $temp['start_time'] = $v['start_time'];
            $temp['end_time'] = $v['end_time'];
            $temp['shop_id'] = $v['shop_id'];

            $coupon_type_list[] = $temp;
        }

        return json(AjaxReturn(SUCCESS,$coupon_type_list));
    
    }

    public function userArchiveCoupon()
    {
        $coupon = new CouponServer();
        $coupon_type_id = input('coupon_type_id');
        $get_type = input('get_type');
        if (empty($this->uid)) {
            return json(AjaxReturn(LOGIN_EXPIRE));
        }
        if ($coupon->isCouponTypeReceivable($coupon_type_id, $this->uid)) {
            $result = $coupon->userAchieveCoupon($this->uid, $coupon_type_id, $get_type);
            return json(AjaxReturn($result));
        } else {
            return json(AjaxReturn(NO_COUPON));
        }
    }

    /**
     * wap订单结算优惠券列表
     */
    public function confirmOrderCouponList()
    {
        $post = request()->post('post_data/a', '');
        if (empty($post)) {
            return json(['code' => 1, 'message' => '空数据', 'data' => []]);
        }
        // 重新处理post的数据结构，将shop_id 和 sku_id作为数组的key
        $new_data = [];
        foreach ($post as $v) {
            $new_data[$v['shop_id']][$v['sku_id']] = $v;
        }
        $couponServer = new CouponServer();
        $couponList = $couponServer->getMemberCouponListNew($new_data); // 获取优惠券
        $return_data = [];
        foreach ($couponList as $shop_id => $coupon) {
            foreach ($coupon['coupon_info'] as $key => $item) {
                $temp_coupon = [];
                $temp_coupon['coupon_id'] = $item['coupon_id'];
                $temp_coupon['shop_id'] = $item['coupon_type']['shop_id'];
                $temp_coupon['coupon_type_id'] = $item['coupon_type_id'];
                $temp_coupon['discount'] = $item['discount'];
                $temp_coupon['money'] = $item['money'];
                $temp_coupon['goods_limit'] = $item['goods_limit'];
                $temp_coupon['start_time'] = $item['coupon_type']['start_time'];
                $temp_coupon['end_time'] = $item['coupon_type']['end_time'];
                $temp_coupon['coupon_name'] = $item['coupon_type']['coupon_name'];
                $temp_coupon['coupon_genre'] = $item['coupon_type']['coupon_genre'];
                $temp_coupon['at_least'] = $item['coupon_type']['at_least'];
                //$temp_coupon['shop_range_type'] = $item['coupon_type']['shop_range_type'];
                $temp_coupon['range_type'] = $item['coupon_type']['range_type'];
                $temp_coupon['use_shop_id'] = $shop_id;

                $return_data[] = $temp_coupon;
            }
        }
        return json(AjaxReturn(SUCCESS, $return_data));
    }

    /**
     * 领券中心
     */
    public function couponCentre()
    {
        $page_index = input('post.page_index');
        if (empty($page_index)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $page_size = input('post.page_size') ?: PAGESIZE;
        $search_text = request()->post('search_text', '');
        $couponServer = new CouponServer();
        $coupon_model = new VslCouponModel();
        $website_model = new WebSiteModel();
        $is_shop = getAddons('shop', $this->website_id);
        $shop_model = $is_shop ? new VslShopModel() : '';
        $condition = [
            'website_id' => $this->website_id,
            'is_fetch' => 1,
            'start_receive_time' => ['ELT', time()],
            'end_receive_time' => ['EGT', time()],
            'coupon_name' => [
                'like',
                '%' . $search_text . '%'
            ]
        ];
        $list = $couponServer->getCouponTypeList($page_index, $page_size, $condition, 'start_time desc', '*');
        $return_data['list'] = [];
        $return_data['total_count'] = $list['total_count'];
        $return_data['page_count'] = $list['page_count'];
        $temp_shop_info = [];// 临时保存店铺的信息
        foreach ($list['data'] as $v) {
            $temp = [];
            $temp['coupon_type_id'] = $v['coupon_type_id'];
            $temp['coupon_name'] = $v['coupon_name'];
            $temp['shop_range'] = $v['shop_range_type'] == 1 ? '本店可用' : '全平台可用';
            $temp['goods_range'] = $v['range_type'] == 1 ? '全部商品可用' : '部分商品可用';
            $temp['coupon_genre'] = $v['coupon_genre'];
            $temp['at_least'] = $v['at_least'];
            $temp['money'] = $v['money'];
            $temp['count'] = $v['count'];
            $temp['max_fetch'] = $v['max_fetch'];
            $temp['discount'] = $v['discount'];
            $temp['start_time'] = $v['start_time'];
            $temp['end_time'] = $v['end_time'];
            $temp['receive_times'] = $coupon_model->getCount(['coupon_type_id' => $v['coupon_type_id']]);
            if (!isset($temp_shop_info[$v['shop_id']]) && $is_shop) {
                // shop logo
                $temp_shop_logo = getApiSrc($shop_model::get(['vsl_shop_model.shop_id' => $v['shop_id']], ['logo'])->logo->pic_cover);
                
                $temp_shop_info[$v['shop_id']]['logo'] = $temp_shop_logo ?: '';
            }
            $logo = $website_model->getInfo(['website_id' => $this->website_id])['logo'];
            $shop_logo = getApiSrc($logo);
            $temp['shop_logo'] = $temp_shop_info[$v['shop_id']]['logo'] ?: $shop_logo;

            $return_data['list'][] = $temp;
        }
        return json(AjaxReturn(SUCCESS, $return_data));
    
    }

    
    /**
     * wap优惠券详情
     */
    public function couponDetail(){
        $coupon_type_id = (int)input('post.coupon_type_id');
        $coupon_id = (int)input('post.coupon_id');
        $page_code = input('post.page_code');
        $coupon_time = input('post.coupon_time');
        $coupon = new CouponServer();
        $coupon_info = $coupon->getCouponTypeDetail($coupon_type_id);
        $data = $info = [];
        if($coupon_info){
            $send_data = [
                'coupon_id' => $coupon_id,
                'page_code' => $page_code,
                'coupon_time' => $coupon_time,
            ];
            $info['is_coupon'] = $coupon->isCouponTypeReceivable($coupon_type_id, $this->uid, 0, $send_data);
            $info['coupon_type_id'] = $coupon_info['coupon_type_id'];
            $info['coupon_name'] = $coupon_info['coupon_name'];
            $info['shop_id'] = $coupon_info['shop_id'];
            $website_model = new WebSiteModel();
            $websiteInfo = $website_model::get(['website_id' => $this->website_id]);
            $info['shop_name'] = $websiteInfo['mall_name'];
            $info['shop_logo'] = getApiSrc($websiteInfo['logo']);
            if(getAddons('shop', $this->website_id)){
                $shop_model = new VslShopModel();
                $shop_info = $shop_model->getInfo(['shop_id' => $coupon_info['shop_id'],'website_id'=>$this->website_id], 'shop_name,shop_logo');
                $album_pic = new AlbumPictureModel();
                $pic_cover = getApiSrc($album_pic->getInfo(['pic_id' => $shop_info['shop_logo']], 'pic_cover')['pic_cover']);
                $info['shop_logo'] = $pic_cover ? $pic_cover : '';
                $info['shop_name'] = $shop_info['shop_name'];
            }
            
            $info['coupon_genre'] = $coupon_info['coupon_genre'];
            $info['at_least'] = $coupon_info['at_least'];
            $info['discount'] = $coupon_info['discount'];
            $info['money'] = $coupon_info['money'];
            $info['start_time'] = $coupon_info['start_time'];
            $info['end_time'] = $coupon_info['end_time'];
            //判断优惠券是否可以赠送他人
            $info['is_send_friend'] = $coupon_info['is_send_friend'];
            $info['is_transcoupon'] = $coupon_info['is_transcoupon'];
            if($coupon_info['expire_time'] == 0){
                $coupon_info['expire_time'] = 1;
            }
            $expire_time = $coupon_info['expire_time'] * 3600 + time();
            if($coupon_id && $page_code){
                $share_cond['uid'] = $this->uid;
                $share_cond['page_code'] = $page_code;
                $sc_records = new VslShareCouponRecordsModel();
                $expire_time = $sc_records->getInfo($share_cond, 'expire_time')['expire_time'] ? : $expire_time;
            }
            $info['expire_time'] = $expire_time;
            $data['code'] = 1;
            $data['message'] = "获取成功";
            $data['data'] = $info;
        }else{
            $data['code'] = -1;
            $data['message'] = "获取失败";
        }
        return json($data);
    }

    /**
     * 将优惠券分享出去赠送给好友
     * @return \think\response\Json
     */
    public function sendCouponToFriend()
    {
        $coupon_id = request()->post('coupon_id', 0);
        $uid = $this->uid;
        if(!$uid){
            return json(AjaxReturn(LOGIN_EXPIRE));
    
        }
        if(!$coupon_id){
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $coupon_server = new Coupon();
        $user_coupon_info = $coupon_server->getUserCouponInfo($coupon_id);//赠送者的优惠券信息
        if(!$user_coupon_info){
            return json(AjaxReturn(FAIL,[], '优惠券已送出'));
    
        }
        //判断优惠券是否过期
        $end_time = $user_coupon_info['end_time'];
        $now_time = time();
        if($now_time > $end_time){
            return json(AjaxReturn(FAIL,[], '优惠券已过期'));
        }
        //判断优惠券是否已使用
        if($user_coupon_info['state'] == -1 || $user_coupon_info['state'] == 2){
            return json(AjaxReturn(FAIL,[], '优惠券已过期'));
        }
        if($uid == $user_coupon_info['uid']){
            return json(AjaxReturn(FAIL,[], '自己不能领取自己的券'));
        }
        //执行记录到账户，状态为未领取
        $res = $coupon_server->userAchieveFriendCoupon($uid, 14, $user_coupon_info);
        return json(AjaxReturn($res));
    }

    /**
     * 赠送多张优惠劵
     *
     * @return \think\response\Json
     */
    public function sendManyCouponToFriend(){
        $coupon_id = request()->post('coupon_id', 0);
        $uid = $this->uid;
        if(!$uid){
            return json(AjaxReturn(LOGIN_EXPIRE));
        }
        if(!$coupon_id){
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $user_no = request()->post('user_no', 0);
        if(!$user_no){
            return json(AjaxReturn(FAIL,[], '请输入用户ID'));
        }
        if($uid == $user_no){
            return json(AjaxReturn(FAIL,[], '自己不能赠送自己的券'));
        }
        //好友信息认证
        $user = VslMemberModel::where('uid', $user_no)->find();
        if(is_null($user)){
            return json(AjaxReturn(FAIL,[], 'ID有误'));
        }
//        if($user['referee_id'] != $uid){
//            return json(AjaxReturn(FAIL,[], '只能赠送给直接推荐的人哦'));
//        }
        $num = request()->post('num', 0);
        if($num<= 0){
            return json(AjaxReturn(FAIL,[], '请输入要赠送的数量'));
        }
        //同类型的优惠劵还有多少张
        $coupon_server = new Coupon();
        $user_coupon_info = $coupon_server->getUserCouponInfo($coupon_id);//赠送者的优惠券信息
        if(!$user_coupon_info){
            return json(AjaxReturn(FAIL,[], '当前优惠券已送出，请重新选择'));
        }
        $now_time = time();
        $lists = VslCouponModel::where('uid', $this->uid)
            ->where('coupon_type_id', $user_coupon_info['coupon_type_id'])
            ->where('state', 1)
            ->where('end_time', '>', $now_time)->limit($num)->select();
        $data = $this->object2array($lists);
        if($num != count($data)){
            return json(AjaxReturn(FAIL,[], '当前优惠券数量不足'));
        }
        $res_num = 0;
        foreach ($lists as $item){
            //执行记录到账户，状态为未领取
            $res = $coupon_server->userAchieveFriendCoupon($user_no, 14, $item);
        }

        return json(AjaxReturn($res));
    }

    /**
     * 返还优惠劵
     *
     * @return \think\response\Json
     */
    public function cancelSendCoupon(){
        $coupon_id = request()->post('coupon_id', 0);
        if(!$coupon_id){
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }

        $coupon_server = new Coupon();
        $user_coupon_info = $coupon_server->getUserCouponInfo($coupon_id);//优惠券信息
        if(is_null($user_coupon_info) || empty($user_coupon_info['send_uid'])){
            return json(AjaxReturn(FAIL,[], '优惠券信息有误，请刷新后尝试'));
        }
        $uid = $user_coupon_info['send_uid'];
        //判断优惠券是否过期
        $end_time = $user_coupon_info['end_time'];
        $now_time = time();
        if($now_time > $end_time){
            return json(AjaxReturn(FAIL,[], '优惠券已过期'));
        }
        //判断优惠券是否已使用
        if($user_coupon_info['state'] == -1 || $user_coupon_info['state'] == 2){
            return json(AjaxReturn(FAIL,[], '优惠券已过期或已使用'));
        }
        //执行记录到账户，状态为未领取
        $res = $coupon_server->userAchieveFriendCoupon($uid, 14, $user_coupon_info);
        return json(AjaxReturn($res));
    }

    /**
     * wap优惠券适用的商品列表
     */
    public function couponGoodsList(){
        $coupon_type_id = (int)input('coupon_type_id');
        $page_index = input('post.page_index');
        $page_size = input('post.page_size',PAGESIZE);
        $order = (input('post.order'))?input('post.order'):'create_time';
        $sort = (input('post.sort'))?input('post.sort'):'DESC';
        $min_price = input('post.max_price');
        $max_price = input('post.max_price');
        $is_recommend = request()->post('is_recommend',0);
        $is_new = request()->post('is_new',0);
        $is_hot = request()->post('is_hot',0);
        $is_promotion = request()->post('is_promotion',0);
        $is_shipping_free = request()->post('is_shipping_free',0);
        if (empty($page_index)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $group = 'ng.goods_id';
        $order_sort = 'ng.' . $order . ' ' . $sort;
        $condition['ng.state'] = 1;
        $condition['ng.website_id'] = $this->website_id;
        if ($min_price != '') {
            $condition['ngs.price'][] = ['>=', $min_price];
        }
        if ($max_price != '') {
            $condition['ngs.price'][] = ['<=', $max_price];
        }
        if($is_recommend==1){
            $condition['ng.is_recommend'] = 1;
        }
        if($is_new==1){
            $condition['ng.is_new'] = 1;
        }
        if($is_hot==1){
            $condition['ng.is_hot'] = 1;
        }
        if($is_promotion==1){
            $condition['ng.is_promotion'] = 1;
        }
        if($is_shipping_free==1){
            $condition['ng.is_shipping_free'] = 1;
        }
        $condition['vs.shop_state'] = 1;
        $condition['coupon_type_id'] = $coupon_type_id;
        $coupon = new CouponServer();
        $list = $coupon->couponGoodsList($page_index, $page_size, $condition, 'ng.goods_id, ng.goods_name, ng.sales,ng.real_sales, sap.pic_cover, ng.price as goods_price,ngs.market_price as market_price', $order_sort, $group);
        $goods_list = [];
        if($list['data']){
            foreach ($list['data'] as $k => $v) {
                $goods_list[$k]['goods_id'] = $v['goods_id'];
                $goods_list[$k]['goods_name'] = $v['goods_name'];
                $goods_list[$k]['price'] = $v['goods_price'];
                $goods_list[$k]['market_price'] = $v['market_price'];
                $goods_list[$k]['sales'] = $v['sales'] + $v['real_sales'];
                $goods_list[$k]['pic_cover'] = $v['pic_cover'] ? getApiSrc($v['pic_cover']) : '';
            }
        }
        $data['code'] = 1;
        $data['message'] = "获取成功";
        $data['data']['goods_list'] = $goods_list;
        $data['data']['page_count'] = $list['page_count'];
        $data['data']['total_count'] = $list['total_count'];
        return json($data);
    }
    
    /*
     * 移动商家端优惠券列表
     */
    public function couponTypeListBiz()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $search_text = trim(request()->post('search_text', ''));
        $couponServer = new CouponServer();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
        ];
        if ($search_text){
            $condition['coupon_name'] = ['like', '%' . $search_text . '%'];
        }
        $list = $couponServer->getCouponTypeList($page_index, $page_size, $condition, 'create_time desc,coupon_type_id desc', '*');
        $list['coupon_list'] = [];
        if($list['data']){
            foreach($list['data'] as $key => $val){
                $list['coupon_list'][$key]['coupon_type_id'] = $val['coupon_type_id'];
                $list['coupon_list'][$key]['coupon_name'] = $val['coupon_name'];
                $list['coupon_list'][$key]['coupon_genre'] = $val['coupon_genre'];
                $list['coupon_list'][$key]['discount'] = $val['discount'];
                $list['coupon_list'][$key]['money'] = $val['money'];
                $list['coupon_list'][$key]['count'] = $val['count'];
                $list['coupon_list'][$key]['surplus'] = $val['surplus'];
                $list['coupon_list'][$key]['start_receive_time'] = date('Y-m-d',$val['start_receive_time']);
                $list['coupon_list'][$key]['end_receive_time'] = date('Y-m-d',$val['end_receive_time']);
                $list['coupon_list'][$key]['start_time'] = date('Y-m-d',$val['start_time']);
                $list['coupon_list'][$key]['end_time'] = date('Y-m-d',$val['end_time']);
            }
        }
        unset($list['data']);
        return json(AjaxReturn(SUCCESS,$list));
    }
    
    /*
     * 移动商家端删除优惠券
     */
    public function deleteCouponTypeBiz()
    {
        $coupon_type_id = (int)request()->post('coupon_type_id', 0);
        if (!$coupon_type_id) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $coupon = new CouponServer();
        $res = $coupon->deleteCouponType($coupon_type_id);
        if ($res) {
            $this->addUserLog('删除优惠券', $coupon_type_id);
        }
        return json(AjaxReturn($res));
    }
    
    /*
     * 移动商家端优惠券记录
     */
    public function historyCouponBiz()
    {
        $promotion = new CouponServer();
        $search_text = trim(request()->post('search_text', ''));
        $page_index = (int)request()->post('page_index', 1);
        $page_size = (int)request()->post('page_size', PAGESIZE);
        $coupon_type_id = (int)request()->post('coupon_type_id', 0);
        if(!$coupon_type_id){
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        $fields = 'nc.coupon_id,nc.coupon_code,nc.money,nc.discount,nc.use_time,su.user_tel,su.nick_name,no.order_no,no.shop_id,ns.shop_name';
        // 使用的历史记录,state = 2
        $where['nc.state'] = 2;
        $where['nc.coupon_type_id'] = $coupon_type_id;
        $where['nc.website_id'] = $this->website_id;
        $where['nc.shop_id'] = $this->instance_id;
        $condition = [];
        if ($search_text) {
            $condition['nc.coupon_code'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['ns.shop_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['su.nick_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['nct.coupon_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
        }
        $list = $promotion->getCouponHistory($page_index, $page_size, $condition, $where, $fields);
        $list['history'] = [];
        if($list['data']){
            $list['history'] = array_columns($list['data'], 'shop_id,shop_name,order_no,user_tel,coupon_code,money,discount,use_time');
        }
        unset($list['data']);
        if($list['history']){
            foreach ($list['history'] as $key => $val){
                $list['history'][$key]['use_time'] = date('Y-m-d H:i:s', $val['use_time']);
            }
            unset($val);
        }
        return json(AjaxReturn(SUCCESS,$list));
    
    }
    
    /*
     * 移动商家端编辑优惠券获取优惠券信息
     */
    public function couponInfoBiz(){
        $coupon_type_id = (int)request()->post('coupon_type_id', 0);
        $coupon_model = new CouponServer();
        $coupon_type_info = $coupon_model->getCouponTypeDetail($coupon_type_id);
        if(!$coupon_type_info){
            return json(AjaxReturn(FAIL,[],'优惠券不存在'));
        }
        unset($coupon_type_info['coupons'],$coupon_type_info['coupon'],$coupon_type_info['website_id'],$coupon_type_info['create_time'],$coupon_type_info['update_time']);
        $coupon_type_info['start_time'] = date('Y-m-d H:i:s', $coupon_type_info['start_time']);
        $coupon_type_info['end_time'] = date('Y-m-d H:i:s', $coupon_type_info['end_time']);
        $coupon_type_info['start_receive_time'] = date('Y-m-d H:i:s', $coupon_type_info['start_receive_time']);
        $coupon_type_info['end_receive_time'] = date('Y-m-d H:i:s', $coupon_type_info['end_receive_time']);
        return json(AjaxReturn(SUCCESS,$coupon_type_info));
    
    }
    
    /**
     * 优惠券(装修)
     * @return array
     */
    public function couponTypePromotionList ()
    {
  
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
    
        $couponServer = new CouponServer();
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'end_receive_time' => ['>',time()]
        ];

        $list = $couponServer->getCouponTypeDatas($page_index, $page_size, $condition, 'create_time desc,coupon_type_id desc', '*');
        
        return $list;
    }
    
    /**
     * 获取优惠券（wap）
     * @return \multitype
     */
    public function getCouponTypeList ()
    {
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $coupon_type_ids = request()->post('coupon_type_id');
        $condition = [
            'website_id' => $this->website_id,
        ];
        if ($coupon_type_ids){
            $coupon_type_ids = explode(',',$coupon_type_ids);
            $condition['coupon_type_id'] = ['in',$coupon_type_ids];
        }
        $couponServer = new CouponServer();
        $list = $couponServer->getCouponTypeList($page_index, $page_size, $condition, 'create_time desc,coupon_type_id desc', '*');

        return json(AjaxReturn(SUCCESS, $list));
    }
    public function sendhistoryCoupon()
    {
        $promotion = new CouponServer();
        $search_text = request()->post('search_text', '');
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $fields = 'nc.send_uid,nc.coupon_id,nc.coupon_code,nc.money,nc.discount,nc.use_time,su.user_tel,su.nick_name,no.order_no,no.shop_id,ns.shop_name,nc.state,nc.end_time';
        // 使用的历史记录,state = 2
        // $where['nc.state'] = 2;
        $where['nc.coupon_type_id'] = request()->post('coupon_type_id');
        $where['nc.website_id'] = $this->website_id;
        $where['nc.shop_id'] = $this->instance_id;
        $where['nc.send_uid'] = ['neq',0];
        $condition = [];
        if ($search_text) {
            $condition['nc.coupon_code'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['ns.shop_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['su.nick_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
            $condition['nct.coupon_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
        }
        
        $list = $promotion->getCouponHistory($page_index, $page_size, $condition, $where, $fields);
        $time = time();

        foreach ($list['data'] as &$item){
            if($item['shop_id'] == 0){
                $item['shop_name'] = '自营店';
            }
            $status = 0;
            if($item['state'] == 1 && !empty($item['send_uid']) && $item['end_time'] > $time){
                $status = 1;
            }
            $item['status'] = $status;
            if(empty($item['order_no'])){
                $item['order_no'] = '';
            }
            if($item['use_time'] > 0){
                $item['use_time'] = date('Y-m-d H:i:s', $item['use_time']);
            }else{
                $item['use_time'] = '';
            }
        }
        return $list;
    }
}