<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/26 0026
 * Time: 14:41
 */

namespace addons\bargain\service;

use addons\bargain\model\VslBargainDetailModel;
use addons\bargain\model\VslBargainModel;
use addons\bargain\model\VslBargainRecordModel;
use addons\electroncard\model\VslElectroncardDataModel;
use data\model\AlbumPictureModel;
use data\model\VslActivityOrderSkuRecordModel;
use data\model\VslGoodsSkuModel;
use data\model\VslOrderModel;
use data\service\BaseService;
use data\service\ActiveList;
use data\service\Goods;
use think\Request;

class Bargain extends BaseService
{
    protected $bargain_mdl;
    protected $goods_ser;
    function __construct()
    {
        parent::__construct();
        $this->bargain_mdl = new VslBargainModel();
        $this->goods_ser = new Goods();
    }
    //插入bargain活动表
    public function addBargain($data, $bargain_id)
    {
        //获取商品的名称
        $bargain_mdl = new VslBargainModel();
        $goods_id = $data['goods_id'];
        //判断该商品是否在其它活动中
         $goods_list = $this->goods_ser->getGoodsDetailById($goods_id);
        if($goods_list['goods_type'] == 5) {
            //判断是否是电子卡密商品，如果是，独立库存不能大于卡密库的库存
            if(getAddons('electroncard',$this->website_id)) {
                $electroncard_base_mdl = new VslElectroncardDataModel();
                $electroncard_base_stock = $electroncard_base_mdl->getCount(['electroncard_base_id' => $goods_list['electroncard_base_id'],'status' => 0]);
                if($data['bargain_stock'] > $electroncard_base_stock) {
                    return ['code'=>-1,'message'=>'活动库存不能大于卡密库库存'];
                }
            }
        }
        if(!$bargain_id){/*新增*/
            //edit for 2021/01/15 拉登 当前旧的判断去掉 变更为统一由活动管理进行检测，允许同一商品在不同时间内添加多次活动
        
            $time = time();
            $condition['website_id'] = $this->website_id;
            $condition['end_bargain_time'] = ['>=',$time];
            $condition['goods_id'] = $data['goods_id'];
            $condition['close_status'] = ['neq', 0];
                $data['goods_name'] = $goods_list['goods_name'];
                $data['picture'] = $goods_list['picture'];
                $data['website_id'] = $this->website_id;
                $data['shop_id'] = $this->instance_id;
                $data['create_time'] = time();
                $data['max_buy_time'] = time();
                $res = $bargain_mdl->save($data);
                $bargain_id = $bargain_mdl->bargain_id;
                //设置活动类型 -- 变更至活动统一执行 
                $act_data = array(
                    'shop_id' =>$data['shop_id'],
                    'website_id'=>$data['website_id'],
                    'status'=>0,
                    'type'=>5,
                    'act_id'=>$bargain_id,
                    'stime'=>$data['start_bargain_time'],
                    'etime'=>$data['end_bargain_time'],
                    'goods_id'=> $data['goods_id'],
                    'category_extend_id'=> 0
                );
                $activeListServer = new ActiveList();
                $activeListServer->addActive($act_data);

        }else{
            //编辑预售 商品没有变更 开始时间可发生变更 检测该时间内是否有冲突 原活动id 跟类型
            $activeListServer = new ActiveList();
            $check_info = $activeListServer->activeCanUse($data['start_bargain_time'],$data['end_bargain_time'],$data['goods_id'],5,$bargain_id);
            if($check_info == false){
                return ['code'=>-3,'message'=>'该时间段内，该商品已存在活动，请更改时间段后重试'];//已存在 有冲突
            }
            $data['goods_name'] = $goods_list['goods_name'];
            $data['picture'] = $goods_list['picture'];
            $data['website_id'] = $this->website_id;
            $data['shop_id'] = $this->instance_id;
            $data['create_time'] = time();
            $data['max_buy_time'] = time();
            $res = $bargain_mdl->save($data,['bargain_id'=>$bargain_id]);
            $activeListServer->updateActive($data['start_bargain_time'],$data['end_bargain_time'],$data['goods_id'],5,$bargain_id);
        }
        $redis = connectRedis();
        //插入redis库存
        $goods_sku_mdl = new VslGoodsSkuModel();
        $goods_sku_list = $goods_sku_mdl->getQuery(['goods_id' => $data['goods_id']], 'sku_id');
        foreach($goods_sku_list as $k => $sku_info){
            $bargain_key = 'bargain_'.$bargain_id.'_'.$sku_info['sku_id'];
            $redis->set($bargain_key, $data['bargain_stock']);
        }
        $url = config('rabbit_interface_url.url');
        //延时队列处理砍价活动开始的状态
        $back_url1 = $url.'/rabbitTask/activityStatus';
        $delay_time1 = $data['start_bargain_time'] - time();
        $this->actBargainDelay($bargain_id, $delay_time1, $back_url1);
        //延时队列处理砍价活动结束的promotion_type
        $back_url2 = $url.'/rabbitTask/upActivityGoodsProType';
        $delay_time2 = $data['end_bargain_time'] - time();
        $this->actBargainDelay($bargain_id, $delay_time2, $back_url2);
        return ['code'=>$res];
    }

    /**
     * 延时处理活动结束
     * @param $bargain_id
     * @param $goods_id
     * @param $end_bargain_time
     */
    public function actBargainDelay($bargain_id, $delay_time, $back_url)
    {
        if(config('is_high_powered')){
            $website_id = $this->website_id;
            //订单完成延时队列
            $config['delay_exchange_name'] = config('rabbit_delay_activity.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_activity.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_activity.delay_routing_key');
            //查询出订单配置过期自动关闭时间
            $delay_time = $delay_time * 1000;//毫秒
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'type' => 'bargain',
                'bargain_id' => $bargain_id,
                'website_id' => $website_id
            ];
            $data = json_encode($data);
            $custom_type = 'activity_promotion';//活动
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    /*
     * 获取bargain列表
     * **/
    public function bargainList($page_index, $page_size, $condition, $order)
    {
        $count = $this->bargain_mdl->alias('b')->where($condition)->join('sys_album_picture ap', 'b.picture=ap.pic_id', 'LEFT')->count();
        $page_count = ceil($count/$page_size);
        $offset = ($page_index-1)*$page_size;
        $bargain_list = $this->bargain_mdl->field('b.*, ap.pic_cover,ap.pic_cover_mid,vg.goods_name,vg.price,vg.shop_id,vg.activity_pic')
                                          ->alias('b')
                                          ->where($condition)
                                          ->join('sys_album_picture ap', 'b.picture=ap.pic_id', 'LEFT')
                                          ->join('vsl_goods vg', 'vg.goods_id=b.goods_id', 'LEFT')
                                          ->limit($offset, $page_size)
                                          ->order($order)
                                          ->select();
        $time = time();
        foreach($bargain_list as $k=>$v){
            $bargain_list[$k]['start_bargain_date'] = date('Y:m:d H:i:s', $v['start_bargain_time']);
            $bargain_list[$k]['end_bargain_date'] = date('Y:m:d H:i:s', $v['end_bargain_time']);
            //处理状态
            if ($v['start_bargain_time'] > $time) {
                //未开始
                $bargain_list[$k]['status'] = 0;
                if($v['close_status'] == 0){//活动已关闭
                    $bargain_list[$k]['status'] = 3;
                }
            } elseif ($v['start_bargain_time'] < $time && $v['end_bargain_time'] > $time) {
                //进行中
                $bargain_list[$k]['status'] = 1;
                if($v['close_status'] == 0){//活动已关闭
                    $bargain_list[$k]['status'] = 3;
                }
            } elseif ($v['end_bargain_time'] < $time) {
                //已结束
                $bargain_list[$k]['status'] = 2;
            }
            $bargain_list[$k]['pic_cover_url'] = getApiSrc($v['pic_cover']);
        }
        unset($v);
        return ['code'=>0,
            'data'=>$bargain_list,
            'addon_status'=> getPortIsOpen($this->website_id),////判断pc端、小程序是否开启
            'total_count' => $count,
            'page_count' => $page_count
        ];
    }
    /*
     * 获取列表的每个状态的数目
     * **/
    public function getBargainStatusCount($condition='')
    {
        $count = $this->bargain_mdl->where($condition)->count();
        return $count;
    }
    /*
     * 获取砍价记录
     * **/
    public function getBargainRecord($page_index=1,$page_size,$condition='',$order)
    {
        $count = $this->bargain_mdl->alias('b')
            ->where($condition)
            ->join('vsl_bargain_record br','b.bargain_id=br.bargain_id','LEFT')
            ->join('vsl_bargain_detail bd','br.bargain_record_id=bd.bargain_record_id','LEFT')
            ->join('sys_user u','br.uid=u.uid','LEFT')
            ->count();
        $offset = ($page_index-1)*$page_size;
        $page_count = ceil($count/$page_size);
        $record_list = $this->bargain_mdl->alias('b')
            ->field('b.end_bargain_time, br.*,u.user_name,u.nick_name,u.user_tel,u.user_headimg,ml.level_name')
            ->where($condition)
            ->join('vsl_bargain_record br','b.bargain_id=br.bargain_id','LEFT')
            ->join('sys_user u','br.uid=u.uid','LEFT')
            ->join('vsl_member m','m.uid=u.uid','LEFT')
            ->join('vsl_member_level ml','m.member_level=ml.level_id','LEFT')
            ->order($order)
            ->limit($offset, $page_size)
            ->select();
//        echo $this->bargain_mdl->getLastSql();
//        p(objToArr($record_list));exit;
        //处理会员
        foreach($record_list as $k=>$v){
            //判断当前时间是否大于end_bargain_time
            if ($v['end_bargain_time'] < time()) {
                $new_record_list[$k]['bargain_status'] = 3;
            }else{
                $new_record_list[$k]['bargain_status'] = $v['bargain_status'];
                if($v['bargain_status'] == 2){//已支付
                    //获取订单编号
                    $order_mdl = new VslOrderModel();
                    $order_cond['buyer_id'] = $v['uid'];
                    $order_cond['bargain_id'] = $v['bargain_id'];
                    $order_no = $order_mdl->getInfo($order_cond,'order_no')['order_no'];
                    $new_record_list[$k]['order_no'] = $order_no;
                }
            }
            $new_record_list[$k]['user_name'] = $v['nick_name']?:($v['user_name']?:$v['user_tel']);
            $new_record_list[$k]['now_bargain_money'] = $v['now_bargain_money'];
            $new_record_list[$k]['start_price'] = $v['start_money'];
            $new_record_list[$k]['already_bargain_money'] = $v['already_bargain_money'];
            $new_record_list[$k]['order_id'] = $v['order_id'];
            $new_record_list[$k]['help_count'] = $v['help_count'];
            $new_record_list[$k]['level_name'] = $v['level_name'];
            $new_record_list[$k]['pic_cover'] = getApiSrc($v['user_headimg']);
        }
        unset($v);
        return ['code'=>0,
            'data'=>$new_record_list,
            'total_count' => $count,
            'page_count' => $page_count
        ];
    }
    /*
     * 获取砍价统计记录 已支付、砍价中、失败
     * **/
    public function getBargainCount($condition)
    {
        $count = $this->bargain_mdl->alias('b')
            ->join('vsl_bargain_record br','b.bargain_id=br.bargain_id','LEFT')
            ->where($condition)
            ->count();
        return $count;
    }
    /*
     * 获取活动详情
     * **/
    public function getBargainDetail($bargain_id)
    {
        $detail_list = $this->bargain_mdl->alias('b')->field('b.*,ap.pic_cover')->join('sys_album_picture ap','b.picture=ap.pic_id')->where(['bargain_id'=>$bargain_id])->find();
        return $detail_list;
    }
    /*
     * 关闭砍价活动
     * **/
    public function bargainClose($bargain_id)
    {
        try{
            $bargain_mdl = new VslBargainModel();
            //关闭后，将商品的promotion_type归0
            $activeListServer = new ActiveList();
            $activeListServer->changeActive($bargain_id,5,2,$this->website_id);
            $res = $bargain_mdl->where(['bargain_id'=>$bargain_id])->update(['close_status'=>0]);
            return $res;
        }catch(\Exception $e){
            echo $e->getMessage();exit;
        }

    }
    /*
     * 移除砍价活动
     * **/
    public function bargainDelete($bargain_id)
    {
        $bargain_mdl = new VslBargainModel();
        $bargain_record_mdl = new VslBargainRecordModel();
        $bargain_detail_mdl = new VslBargainDetailModel();
        $goodsSer = new Goods();
        $activeListServer = new ActiveList();
        try{
            $bargain_mdl->startTrans();
            $bargain_record_id = $bargain_record_mdl->getInfo(['bargain_id'=>$bargain_id],'bargain_record_id')['bargain_record_id'];
            $goods_id = $bargain_mdl->getInfo(['bargain_id'=>$bargain_id],'goods_id')['goods_id'];
            $bargain_mdl->where(['bargain_id'=>$bargain_id])->delete();
            if($bargain_record_id){
                $res1 = $bargain_record_mdl->where(['bargain_id'=>$bargain_id])->delete();
                $res2 = $bargain_detail_mdl->where(['bargain_record_id'=>$bargain_record_id])->delete();
            }
            //清除掉goods的促销类型
            $promotion_arr = [
                'promotion_type' => 0
            ];
            $promotion_condition['goods_id'] = $goods_id;
            $promotion_condition['promotion_type'] = 4;
            $goodsSer->updateGoods($promotion_condition, $promotion_arr);
            $activeListServer->delActive($bargain_id,5);
            $bargain_mdl->commit();
            return 1;
        }catch(\Exception $e){
            $bargain_mdl->rollback();
            return -1;
        }
    }
    
    /*
     * 获取砍价信息
     */
    public function getBargainInfo ($condition, $field='*')
    {
        $bargain_mdl = new VslBargainModel();
        return $bargain_mdl->getInfo($condition, $field);
    }



    /***********************************************前端接口开始*****************************************************/
    /*
     * 获取前台bargain列表
     * **/
    public function frontBargainList($page_index, $page_size, $condition, $order)
    {
        $count = $this->bargain_mdl->alias('b')->where($condition)->join('sys_album_picture ap', 'b.picture=ap.pic_id', 'LEFT')->join('vsl_goods_discount vgd', 'vgd.goods_id = b.goods_id', 'LEFT')->count();
        $page_count = ceil($count/$page_size);
        $offset = ($page_index-1)*$page_size;
        $bargain_list = $this->bargain_mdl->field('b.bargain_id, b.goods_id, b.goods_name, b.start_bargain_time, b.end_bargain_time, b.start_money, ap.pic_cover')->alias('b')->where($condition)->join('sys_album_picture ap', 'b.picture=ap.pic_id', 'LEFT')->join('vsl_goods_discount vgd', 'vgd.goods_id = b.goods_id', 'LEFT')->limit($offset, $page_size)->order($order)->select();
        $time = time();
        foreach($bargain_list as $k=>$v){
//            $bargain_list[$k]['pic_cover'] = getApiSrc($v['pic_cover']);
            $bargain_list[$k]['start_bargain_date'] = date('Y:m:d H:i:s', $v['start_bargain_time']);
            $bargain_list[$k]['end_bargain_date'] = date('Y:m:d H:i:s', $v['end_bargain_time']);
            //处理状态
            if ($v['start_bargain_time'] > $time) {
                //未开始
                $bargain_list[$k]['status'] = 0;
            } elseif ($v['start_bargain_time'] < $time && $v['end_bargain_time'] > $time) {
                //进行中
                $bargain_list[$k]['status'] = 1;
            } elseif ($v['end_bargain_time'] < $time) {
                //已结束
                $bargain_list[$k]['status'] = 2;
            }
            $bargain_list[$k]['pic_cover_url'] = getApiSrc($v['pic_cover']);
        }
        unset($v);
//        return ['code'=>0,
//            'data'=>$bargain_list,
//            'total_count' => $count,
//            'page_count' => $page_count
//        ];
        return ['code'=>0,
                'data'=>[
                    'bargain_list'=>$bargain_list,
                    'total_count' => $count,
                    'page_count' => $page_count
                ]
        ];
    }
    /*
     * 判断砍价活动是否过期
     * **/
    public function isBargain($condition)
    {
        $is_addons = getAddons('bargain', $this->website_id);
        if(!$is_addons){
            return false;
        }
        $condition['close_status'] = 1;//未关闭
        $bargain_mdl = new VslBargainModel();
        $bargain_record_mdl = new VslBargainRecordModel();
        $bargain_list = $bargain_mdl
            ->field('bargain_id, bargain_name, lowest_money, start_bargain_time, end_bargain_time, start_money, is_my_bargain, first_bargain_money, bargain_method, fix_money, rand_lowest_money, rand_highest_money, limit_buy, bargain_stock, bargain_sales')
            ->where($condition)
            ->find();
        if($bargain_list['end_bargain_time'] < time()){
            return false;
        }else{
            if($bargain_list['end_bargain_time']>time() && $bargain_list['start_bargain_time']<time()){
                //判断我是否参与过砍价
                $my_bargain_condition['bargain_id'] = $bargain_list['bargain_id'];
                $my_bargain_condition['uid'] = $this->uid?:0;
                $my_bargain_condition['order_id'] = ['eq', 0];
                $my_bargain = $bargain_record_mdl->where($my_bargain_condition)->find();
                if($my_bargain){
                    $bargain_list['is_join_bargain'] = true;
                    $bargain_list['bargain_uid'] = $this->uid?:0;
                    $bargain_list['my_bargain'] = $my_bargain;
                }else{
                    $bargain_list['is_join_bargain'] = false;
                    $bargain_list['bargain_uid'] = $this->uid?:0;
                    $bargain_list['my_bargain'] = (object)[];
                }
                $bargain_list['status'] = 1;//正在进行
            }else{
                $bargain_list['status'] = 0;//未开始
            }
            return $bargain_list;
        }
    }
    /*
     * 判断砍价活动是否过期
     * **/
    public function isBargainByGoodsId($condition)
    {
        $is_addons = getAddons('bargain', $this->website_id);
        if(!$is_addons){
            return false;
        }
        $condition['close_status'] = 1;//未关闭
        $bargain_mdl = new VslBargainModel();
        $bargain_record_mdl = new VslBargainRecordModel();
        $bargain_list = $bargain_mdl
            ->field('bargain_id, bargain_name, lowest_money, start_bargain_time, end_bargain_time, start_money, is_my_bargain, first_bargain_money, bargain_method, fix_money, rand_lowest_money, rand_highest_money, limit_buy, bargain_stock, bargain_sales')
            ->where($condition)
            ->find();
        if($bargain_list['end_bargain_time'] < time()){
            return false;
        }
        if($bargain_list['end_bargain_time']>time() && $bargain_list['start_bargain_time']<time()){
            //判断我是否参与过砍价
            $my_bargain_condition['bargain_id'] = $bargain_list['bargain_id'];
            $my_bargain_condition['uid'] = $this->uid?:0;
            $my_bargain_condition['order_id'] = ['eq', 0];
            $my_bargain = $bargain_record_mdl->where($my_bargain_condition)->find();
            if($my_bargain){
                $bargain_list['is_join_bargain'] = true;
                $bargain_list['bargain_uid'] = $this->uid?:0;
                $bargain_list['my_bargain'] = $my_bargain;
            }else{
                $bargain_list['is_join_bargain'] = false;
                $bargain_list['bargain_uid'] = $this->uid?:0;
                $bargain_list['my_bargain'] = (object)[];
            }
            $bargain_list['status'] = 1;//正在进行
        }else{
            $bargain_list['status'] = 0;//未开始
        }
        return $bargain_list;
        
    }
    /*
     * 添加我的砍价
     * **/
    public function addMyBargain($data)
    {
        $bargain_record_mdl = new VslBargainRecordModel();
        $res = $bargain_record_mdl->save($data);
        return $res;
    }
    /*
     * 获取我的砍价详情
     * **/
    public function getFrontBargainDetail($condition)
    {
        $bargain_detail_list = $this->bargain_mdl->alias('b')
            ->field('b.bargain_id, br.bargain_record_id, b.goods_id, b.goods_name, b.lowest_money, b.end_bargain_time, br.now_bargain_money, br.now_bargain_money, br.start_money, br.bargain_record_id, br.uid, br.bargain_record_id, ap.pic_cover, bd.help_uid, bd.help_price, u.user_headimg, u.user_name, u.nick_name, u.user_tel')
            ->join('vsl_bargain_record br','b.bargain_id=br.bargain_id','LEFT')
            ->join('sys_album_picture ap','b.picture=ap.pic_id','LEFT')
            ->join('vsl_bargain_detail bd','br.bargain_record_id=bd.bargain_record_id','LEFT')
            ->join('sys_user u','bd.help_uid=u.uid','LEFT')
            ->where($condition)
            ->select();
//        echo $this->bargain_mdl->getLastSql();
//        p($bargain_detail_list);exit;
        //处理图片、帮砍用户信息
        foreach($bargain_detail_list as $k=>$v){
            $new_bargain_detail_list['bargain_id'] = $v['bargain_id'];
            $new_bargain_detail_list['bargain_uid'] = $this->uid?:0;
            $new_bargain_detail_list['bargain_record_id'] = $v['bargain_record_id'];
            $new_bargain_detail_list['goods_id'] = $v['goods_id'];
            $new_bargain_detail_list['goods_name'] = $v['goods_name'];
            $new_bargain_detail_list['can_bargain_money'] = $v['now_bargain_money'] - $v['lowest_money'] <= 0 ? 0 : $v['now_bargain_money'] - $v['lowest_money'];//还能砍多少
            $new_bargain_detail_list['pic_cover'] = getApiSrc($v['pic_cover']);
            $new_bargain_detail_list['start_money'] = $v['start_money'];//起始价
            $new_bargain_detail_list['now_bargain_money'] = $v['now_bargain_money'];//现价
            $new_bargain_detail_list['end_bargain_time'] = $v['end_bargain_time'];//结束时间
            if($v['help_price'] != 0){
                $new_bargain_detail_list['help_bargain_list'][$k]['help_user_headimg'] = getApiSrc($v['user_headimg']);
                $new_bargain_detail_list['help_bargain_list'][$k]['help_name'] = $v['nick_name']?:($v['user_name']?:$v['user_tel']);
                $new_bargain_detail_list['help_bargain_list'][$k]['help_price'] = $v['help_price'];
            }
        }
        unset($v);
        if(!isset($new_bargain_detail_list['help_bargain_list'])){
            $new_bargain_detail_list['help_bargain_list'] = [];
        }
//        p($new_bargain_detail_list);exit;
        return $new_bargain_detail_list;
    }
    /*
     * 通过用户记录id获取砍价活动信息
     * **/
    public function getBargainByRecord($bargain_record_id)
    {
        $bargain_record_mdl = new VslBargainRecordModel();
        $bargain_record_list = $bargain_record_mdl->alias('br')
            ->where(['bargain_record_id'=>$bargain_record_id])
            ->join('vsl_bargain b','br.bargain_id=b.bargain_id','LEFT')
            ->find();
        return $bargain_record_list;
    }
    /*
     * 减砍价库存
     * **/
    public function subBargainGoodsStock($bargain_id, $num){
        $bargain_mdl = new VslBargainModel();
        $bargain_list = $bargain_mdl->where(['bargain_id'=>$bargain_id])->find();
        $bargain_list->bargain_stock = $bargain_list->bargain_stock-$num;
        $bargain_list->save();
    }
    /*
     * 减redis砍价库存
     * **/
    public function subRedisBargainGoodsStock($bargain_id, $sku_id){
        $redis = connectRedis();
        $bargain_key = 'bargain_'.$bargain_id.'_'.$sku_id;
        $bargain_goods_store = $redis->get($bargain_key);
        $bargain_mdl = new VslBargainModel();
        $data['bargain_stock'] = $bargain_goods_store;
        $bargain_mdl->save($data, ['bargain_id'=>$bargain_id]);
//        $bargain_list = $bargain_mdl->where(['bargain_id'=>$bargain_id])->find();
//        $bargain_list->bargain_stock = $bargain_list->bargain_stock-$num;
//        $bargain_list->save();
    }
    /*
     * 加砍价库存
     * **/
    public function addBargainGoodsStock($bargain_id, $num){
        $bargain_mdl = new VslBargainModel();
        $bargain_list = $bargain_mdl->where(['bargain_id'=>$bargain_id])->find();
        $bargain_list->bargain_stock = $bargain_list->bargain_stock+$num;
        $bargain_list->save();
    }
    
    //获取已购买的数量（总数）
    public function getBargianBuyNum($bargain_id){
        $asor_mdl = new VslActivityOrderSkuRecordModel();
        $buy_num = $asor_mdl->where(['activity_id' => $bargain_id, 'buy_type' => 3])->sum('num');
        return $buy_num;
    }
}