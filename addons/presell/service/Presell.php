<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/10/26 0026
 * Time: 14:41
 */

namespace addons\presell\service;

use data\model\VslActivityOrderSkuRecordModel;
use addons\presell\model\VslPresellGoodsModel;
use addons\presell\model\VslPresellModel;
use data\model\VslPushMessage;
use data\model\VslGoodsSkuModel;
use data\service\Goods;
use data\service\BaseService;
use data\service\ActiveList;
use think\db;

class Presell extends BaseService
{

    function __construct()
    {
        parent::__construct();
    }
    public function presellList($page_index,$page_size,$condition)
    {
        $presell = new VslPresellModel();
        $data = $presell->pageQuery($page_index, $page_size,$condition,'id desc','*');
        return $data;
    }
    //添加预售
    public function addPresell($base_data,$sku_data=[])
    {
        $presell = new VslPresellModel();
        $presell->startTrans();
        try{
            $base_data['status'] = 2;//默认未开启，由活动列表统一开启
            $id = $presell->save($base_data);
            $presell_id = $presell->id;
            $redis = connectRedis();
            if(!empty($sku_data)){
                foreach ($sku_data as $k=>$v){
                    $presell_goods = new VslPresellGoodsModel();
                    $data['goods_id']           = $base_data['goods_id'];
                    $data['sku_id']             = $k;
                    $data['max_buy']            = $v['max_buy'];
                    $data['first_money']        = $v['first_money'];
                    $data['all_money']          = $v['all_money'];
                    $data['presell_num']        = $v['presell_num'];
                    //                    $data['vr_num'] = $v['vr_num'];
                    //                    $data['vr_num'] = $base_data['vr_num'];//虚拟订购量改到外面
                    $data['presell_id']         = $id;
                    $data['start_time']         = $base_data['start_time'];
                    $data['end_time']           = $base_data['end_time'];
                    $data['max_buy_time']       = time();
                    $presell_goods->save($data);
                    //插入redis库存
                    $presell_key = 'presell_'.$presell_id.'_'.$k;
                    $redis->set($presell_key, $v['presell_num']);
                }
            }
            //设置活动类型 -- 变更至活动统一执行 
            $act_data = array(
                'shop_id' =>$base_data['shop_id'],
                'website_id'=>$base_data['website_id'],
                'status'=>0,
                'type'=>4,
                'act_id'=>$presell_id,
                'stime'=>$base_data['start_time'],
                'etime'=>$base_data['end_time'],
                'goods_id'=> $base_data['goods_id'],
                'category_extend_id'=> 0
            );
            $activeListServer = new ActiveList();
            $activeListServer->addActive($act_data);
            //延时队列处理砍价活动开始的状态
            $url = config('rabbit_interface_url.url');
            $back_url1 = $url.'/rabbitTask/activityStatus';
            $delay_time1 = $base_data['start_time'] - time();
            $this->actPresellDelay($presell_id, $delay_time1, $back_url1);
            //延时队列处理砍价活动结束的promotion_type
            $url = config('rabbit_interface_url.url');
            $back_url2 = $url.'/rabbitTask/upActivityGoodsProType';
            $delay_time2 = $base_data['end_time'] - time();
            $this->actPresellDelay($presell_id, $delay_time2, $back_url2);
            $presell->commit();
            return 1;
        }catch(\Exception $e){
            $presell->rollback();
            recordErrorLog($e);
            return -1;
        }
    }

    /**
     * 处理活动过期
     * @param $presell_id
     * @param $end_time
     */
    public function actPresellDelay($presell_id, $delay_time, $back_url)
    {
        if(config('is_high_powered')){
            $website_id = $this->website_id;
            $config['delay_exchange_name'] = config('rabbit_delay_activity.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_activity.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_activity.delay_routing_key');
            //查询出订单配置过期自动关闭时间
            $delay_time = $delay_time * 1000;//毫秒
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'type' => 'presell',
                'presell_id' => $presell_id,
                'website_id' => $website_id
            ];
            $data = json_encode($data);
            $custom_type = 'activity_promotion';//活动
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    
    //编辑
    public function updatePresell($base_data,$sku_data=[],$condition)
    {
        $presell = new VslPresellModel();
        //编辑预售 商品没有变更 开始时间可发生变更 检测该时间内是否有冲突 原活动id 跟类型
        $activeListServer = new ActiveList();
        $check_info = $activeListServer->activeCanUse($base_data['start_time'],$base_data['end_time'],$base_data['goods_id'],4,$condition['id']);
        if($check_info == false){
            return -3; //已存在 有冲突
        }
        $presell->startTrans();
        try{
            if(!empty($sku_data)){
                foreach ($sku_data as $k=>$v){
                    $presell_goods = new VslPresellGoodsModel();
                    $data['goods_id'] = $base_data['goods_id'];
                    $data['sku_id'] = $k;
                    $data['max_buy'] = $v['max_buy'];
                    $data['first_money'] = $v['first_money'];
                    $data['all_money'] = $v['all_money'];
                    $data['presell_num'] = $v['presell_num'];
                    //$data['vr_num'] = $v['vr_num'];
                    $where['presell_goods_id'] = $v['presell_goods_id'];
                    $where['max_buy_time'] = time();
                    $presell_goods->save($data,$where);
                }
            }
            $presell->save($base_data,$condition);
            //更新活动列表时间
            $activeListServer->updateActive($base_data['start_time'],$base_data['end_time'],$base_data['goods_id'],4,$condition['id']);
            $presell->commit();
            return 1;

        }catch(\Exception $e){
            $presell->rollback();
            recordErrorLog($e);
            return -1;
        }
    }
   
    
    //活动购买总人数
    public function getPresellCountPeople($presell_id){
        $sql = 'SELECT COUNT(DISTINCT(buyer_id)) AS num FROM `vsl_order` WHERE `presell_id` = '.$presell_id;
        return Db::query($sql);
    }

    //获取已购买的数量（总数）
    public function getPresellBuyNum($presell_id){
//        $sql = 'SELECT SUM(num) as buy_num FROM `vsl_order_goods` WHERE `presell_id` = '.$presell_id;
//        return Db::query($sql);
        $asor_mdl = new VslActivityOrderSkuRecordModel();
        $buy_num = $asor_mdl->where(['activity_id' => $presell_id, 'buy_type' => 4])->sum('num');
        return $buy_num;
    }

    //获取当前用户已购买总数
    public function getUserCount($presell_id){
//        $order_goods = new VslOrderGoodsModel();
//        $condition['buyer_id'] = $this->uid;
//        $condition['presell_id'] = $presell_id;
////        $count = $order_goods->getCount($condition);
//        $count = $order_goods->where($condition)->sum('num');
        $asor_mdl = new VslActivityOrderSkuRecordModel();
        $condition['activity_id'] = $presell_id;
        $condition['uid'] = $this->uid;
        $condition['buy_type'] = 4;
        $count = $asor_mdl->where($condition)->sum('num');
        return $count;
    }

    //规格商品已购买的数量
    public function getPresellSkuNum($presell_id,$sku_id){

//        $order = new VslPresellGoodsModel();
//        $num = $order->alias('a')->join('vsl_order_goods b','a.presell_id=b.presell_id','left')->field('b.num')->where(['a.presell_id'=>$presell_id,'b.sku_id'=>$sku_id])->SUM('num');
        $activity_goods = new VslActivityOrderSkuRecordModel();
        $num = $activity_goods->where(['activity_id' => $presell_id, 'sku_id' => $sku_id])->sum('num');
        return $num;
    }

    //获取各活动状态的数量status
    public function getStatusCount($status=''){
        $presell = new VslPresellModel;
        if(empty($status)){
            $count = $presell->where(['website_id'=>$this->website_id,'shop_id'=>$this->instance_id])->count();
        }else{
            $count = $presell->where(['website_id'=>$this->website_id,'status'=>$status,'shop_id'=>$this->instance_id])->count();
        }

        return $count;
    }

    //获取预售详情  ，根据预售ID
    public function getPresellInfo($id){

        $presell = new VslPresellModel();
        $goodsSer = new \data\service\Goods();
        $info = $presell->alias('a')->join('vsl_presell_goods g','a.id=g.presell_id','left')->field('a.*,g.presell_goods_id,g.sku_id,g.max_buy,g.presell_id,g.first_money,g.all_money,g.presell_num,g.vr_num')->where(['a.id'=>$id])->select();
        $pic_info = $goodsSer->getGoodsDetailById($info[0]['goods_id'], 'goods_id,goods_name,electroncard_base_id,picture', 1);
        $info[0]['pic_cover'] = getApiSrc($pic_info['album_picture']['pic_cover']);
        $info[0]['goods_name'] = $pic_info['goods_name'];
        return $info;
    }

    //获取预售详情  ，根据商品ID
    public function getPresellInfoByGoodsId($goods_id){

        $condition['a.end_time'] = ['EGT', time()];
        $condition['a.goods_id'] = $goods_id;
        $condition['a.active_status'] = 1;
        $condition['a.status'] = ['neq', 3];

        $presell = new VslPresellModel();
        $info = $presell->alias('a')->join('vsl_presell_goods g','a.id=g.presell_id','left')->field('a.*,g.sku_id,g.max_buy,g.presell_id,g.first_money,g.all_money,g.presell_num,g.vr_num')->where($condition)->select();
        return $info;
    }
    //获取预售详情  ，根据商品ID
    public function getPresellIsGoingByGoodsId($goods_id){
        $condition['a.end_time'] = ['EGT', time()];
        $condition['a.start_time'] = ['ELT', time()];
        $condition['a.goods_id'] = $goods_id;
        $condition['a.active_status'] = 1;
        $condition['a.status'] = ['neq', 3];
        $presell = new VslPresellModel();
        $info = $presell->alias('a')
            ->join('vsl_presell_goods g','a.id=g.presell_id','left')
            ->field('a.*,g.sku_id,g.max_buy,g.presell_id,g.first_money,g.all_money,g.presell_num,g.vr_num')
            ->where($condition)
            ->find();
        return $info;
    }
    //获取正在进行预售详情  ，根据商品ID
    public function getPresellInfoByGoodsIdIng($goods_id){

        $condition['a.start_time'] = ['LT', time()];
        $condition['a.end_time'] = ['EGT', time()];
        $condition['a.goods_id'] = $goods_id;
        $condition['a.active_status'] = 1;
//        $condition['a.status'] = 1;

        $presell = new VslPresellModel();
        $info = $presell->alias('a')->join('vsl_presell_goods g','a.id=g.presell_id','left')->field('a.*,g.sku_id,g.max_buy,g.presell_id,g.first_money,g.all_money,g.presell_num,g.vr_num')->where($condition)->select();
//        echo $presell->getLastSql();exit;
        return $info;
    }


    //获取预售信息，规格ID和预售ID
    public function getPresellBySku($presell_id,$sku_id){

        $presell = new VslPresellModel();
        $presell_goods = new VslPresellGoodsModel();
        $condition['presell_id'] = $presell_id;
        $condition['sku_id'] = $sku_id;
        //先从sku里面找，没有再从主表找
        $info = $presell_goods->getInfo($condition);
        if(!empty($info)){
            $time = $presell->getInfo(['id'=>$presell_id]);
            $data['maxbuy'] = $info['max_buy'];
            $data['firstmoney'] = $info['first_money'];
            $data['allmoney'] = $info['all_money'];
            $data['presellnum'] = $info['presell_num'];
            $data['vrnum'] = $info['vr_num'];
            $data['shop_id'] = $info['shop_id'];
            $data['pay_start_time'] = $time['pay_start_time'];
            $data['pay_end_time'] = $time['pay_end_time'];
        }else{
            $info = $presell->getInfo(['id'=>$presell_id,'sku_id'=>$sku_id]);
            if($info){
                $data['maxbuy'] = $info['maxbuy'];
                $data['firstmoney'] = $info['firstmoney'];
                $data['allmoney'] = $info['allmoney'];
                $data['presellnum'] = $info['presell_num'];
                $data['vrnum'] = $info['vrnum'];
                $data['shop_id'] = $info['shop_id'];
                $data['pay_start_time'] = $info['pay_start_time'];
                $data['pay_end_time'] = $info['pay_end_time'];
            }
        }
        return $data;
    }

    //获取预售详情---规格
    public function getPresellSkuinfo($condition){

        $presell = new VslPresellGoodsModel();
        $skuinfo = $presell->getInfo($condition);
        return $skuinfo;
    }

    //获取编辑的规格数据
    public function getSkuInfo($goods_id,$presell_id){
            $skuModel = new VslGoodsSkuModel();
            $goodsSer = new \data\service\Goods();
            $groupGoods = new VslPresellGoodsModel();
            $goods_spec_format = $goodsSer->getGoodsDetailById($goods_id, 'goods_id,goods_name,goods_spec_format')['goods_spec_format'];
            $goods_spec_arr = json_decode($goods_spec_format, true);
            $sku = $skuModel->where(['goods_id' => $goods_id])->select();
            if (!empty($sku[0]['attr_value_items'])) {
                foreach ($sku as $sku_key => $sku_value) {
                    $sku_val_item = $sku_value['attr_value_items'];
                    $sku_val_arr = explode(';', $sku_val_item);
                    $th_name_str = '';
                    $show_value_str = '';
                    $show_type_str = '';
                    foreach ($sku_val_arr as $sku_val_key => $sku_val_value) {
                        $sku_val_value_arr = explode(':', $sku_val_value);
                        //按照规格规则中的顺序定义tr头 删掉规格后会导致商品报错不显示规格，所以直接取商品表的goods_spec_format
                        $val_type_arr = [];
                        foreach ($goods_spec_arr as $k0 => $v0) {
                            foreach ($v0['value'] as $k01 => $v01) {
                                if($sku_val_value_arr[1] == $v01['spec_value_id']){
                                    $val_type_arr['goods_spec']['show_type'] = $v01['spec_show_type'];
                                    $val_type_arr['goods_spec']['spec_name'] = $v01['spec_name'];
                                    $val_type_arr['spec_value_name'] = $v01['spec_value_name'];
                                }
                            }
                            unset($v01);
                        }
                        unset($v0);
                        $show_type = $val_type_arr['goods_spec']['show_type'];
                        //根据show_type，获取规格的值，如图片的路径
                        if ($show_type == '3') {//图片
                            $val_type_str = $val_type_arr['spec_value_name'];//暂时展示中文。
                        } else if ($show_type == '2') {//颜色
                            $val_type_str = $val_type_arr['spec_value_name'];
                        } else {
                            $val_type_str = $val_type_arr['spec_value_name'];
                        }
                        //拼接所有规格展示类型对应的值
                        $show_value_str .= $val_type_str . '§';
                        //拼接th的名字
                        $th_name_str .= $val_type_arr['goods_spec']['spec_name'] . ' ';
                        //拼接展示类型
                        $show_type_str .= $show_type . ' ';
                    }
                    unset($sku_val_value);
                    $th_name_str = trim($th_name_str);//spec_name
                    $show_type_str = trim($show_type_str);//展示类型
                    $show_value_str = trim($show_value_str, '§');//spec_value_name
                    $sku_list = $sku_value->toArray();
                    //处理sku的id对应value
                    $sku_id_str = $sku_list['attr_value_items'];
                    $sku_id_str_arr = explode(';', $sku_id_str);
                    $sku_value_str = trim($show_value_str);
                    $sku_value_str_arr = explode('§', $sku_value_str);
                    $im_str = '';
                    $new_im_str = '';
                    for ($i = 0; $i < count($sku_value_str_arr); $i++) {
                        $im_str .= $sku_id_str_arr[$i] . ';';
                        $im_str = trim($im_str, ';');
                        $new_im_str .= $im_str . '=' . $sku_value_str_arr[$i] . '§';
                    }
                    $new_im_str = trim($new_im_str, '§');
                    $sku[$sku_key]['new_im_str'] = $new_im_str;
                    $sku[$sku_key]['th_name_str'] = $th_name_str;
                    $sku[$sku_key]['show_type_str'] = $show_type_str;
                    $groupSku = $groupGoods->getInfo(['sku_id' => $sku_value['sku_id'], 'goods_id' => $goods_id,'presell_id'=>$presell_id], '*');
                    $sku[$sku_key]['max_buy'] = $groupSku['max_buy'];
                    $sku[$sku_key]['first_money'] = $groupSku['first_money'];
                    $sku[$sku_key]['all_money'] = $groupSku['all_money'];
                    $sku[$sku_key]['presell_num'] = $groupSku['presell_num'];
                    $sku[$sku_key]['vr_num'] = $groupSku['vr_num'];
                    $sku[$sku_key]['presell_id'] = $groupSku['presell_id'];
                    $sku[$sku_key]['presell_goods_id'] = $groupSku['presell_goods_id'];
                }
                /*************************当sku规格错乱的时候排序****************************/
                $temp = [];
                foreach($sku as $k1=>$sort_sku){
                    $sort_arr = explode('§',$sort_sku['new_im_str']);
                    $sort_str = $sort_arr[0];
                    $temp[$sort_str][$k1] = $sort_sku;
                }
                unset($sort_sku);
                $i = 0;
                $sku_temp = [];
                foreach($temp as $k2=>$r){
                    foreach($r as $last_val){
                        $sku_temp[$i] = $last_val;
                        $i++;
                    }
                    unset($last_val);
                }
                unset($r);
                $sku = $sku_temp;
            } else {
                $sku = $sku[0];
                $groupSku = $groupGoods->getInfo(['sku_id' => $sku['sku_id'], 'goods_id' => $goods_id,'presell_id'=>$presell_id], '*');
                $sku['max_buy'] = $groupSku['max_buy'];
                $sku['first_money'] = $groupSku['first_money'];
                $sku['all_money'] = $groupSku['all_money'];
                $sku['presell_num'] = $groupSku['presell_num'];
                $sku['vr_num'] = $groupSku['vr_num'];
                $sku['presell_id'] = $groupSku['presell_id'];
                $sku['presell_goods_id'] = $groupSku['presell_goods_id'];
            }
            return $sku;
    }

    /*
     * 获取预售商品是否在活动中
     * **/
    public function getIsInPresell($goods_id)
    {
        $presell_info = $this->getPresellInfoByGoodsId($goods_id);
        $is_presell = false;
        if (!empty($presell_info)) {
            //判断状态是进行中还是
            if (time() > $presell_info[0]['start_time'] && time() < $presell_info[0]['end_time']) {//正在活动中
                $is_presell = $presell_info[0];
            } else if (time() < $presell_info[0]['start_time']) {//没开始
                $is_presell = false;
            } else {//结束了
                $is_presell = false;
            }
        }
        return $is_presell;
    }

    /*
     * 预售获取我还能买多少个
     * **/
    public function getMeCanBuy($presell_id, $sku_id)
    {
        $aosr_mdl = new VslActivityOrderSkuRecordModel();
        $presell_goods = new VslPresellGoodsModel();
        $already_num = $aosr_mdl->where(['activity_id' => $presell_id, 'sku_id'=>$sku_id, 'uid'=>$this->uid])->sum('num');
        $presell_sku_info = $presell_goods->getInfo(['presell_id'=>$presell_id, 'sku_id'=>$sku_id]);
        $max_buy = $presell_sku_info['max_buy'];
//        var_dump($max_buy);
        $can_buy = $max_buy - $already_num >= 0 ? $max_buy - $already_num : 0;
        return $can_buy;
    }
    
    /*
     * 获取预售基础表信息
     */
    public function getFirstPresellInfo ($condition, $order='', $field ='*')
    {
        $presell = new VslPresellModel();
        return $presell->getFirstData($condition, $order, $field);
    }

    /**
     * 获取预售列表
     * @param int    $page_index
     * @param int    $page_size
     * @param array  $condition
     * @param string $order
     * @return \data\model\multitype
     */
    public function presellGoodsList ($page_index = 1, $page_size = 0, array $condition = [],$order='')
    {
        $presellModel = new VslPresellModel();
        $presellList = $presellModel->getPresellGoodsViewList($page_index,$page_size,$condition,$order);
        return $presellList;
    }

    /**
     * 处理订单预售金额数据
     * @param \data\service\order $order_service
     * @param $payment_info
     * @param $va
     * @param $shipping_type
     * @param $shop_id
     * @param $is_free_shipping
     */
    public function actPresellOrder(\data\service\order $order_service, $payment_info, $va, $shipping_type, $shop_id, $is_free_shipping, $order_info)
    {
        $presell_id = $va['presell_id'];
        //如果是预售的商品，则更改其单价为预售价
        $presell_mdl = new VslPresellModel();
        $presell_condition['p.id'] = $presell_id;
        $presell_condition['pg.sku_id'] = $va['sku_id'];
        $presell_goods_info = $presell_mdl->alias('p')->where($presell_condition)->join('vsl_presell_goods pg', 'p.id = pg.presell_id', 'LEFT')->find();
        if ($presell_goods_info) {
            //预售商品 查看是否自提 自提门店是否开启 待处理 商品service可能已经处理
//            $v['sku'][$sku_id]['price'] = $presell_goods_info['allmoney'];
//            $v['sku'][$sku_id]['discount_price'] = $presell_goods_info['allmoney'];
            $out_trade_no2 = $order_service->getOrderTradeNo();
            $order_info['out_trade_no_presell'] = $out_trade_no2;
            $order_info['presell_id'] = $presell_id ? : 0;
            if ($shipping_type == 2 && $payment_info[$shop_id]['has_store'] > 0) {//等于2为自提
                $order_info['shipping_fee'] = 0;
            }
            if($is_free_shipping) {
                $order_info['shipping_fee'] = 0;
            }
            $order_info['final_money'] = $presell_goods_info['all_money'] * $va['num'] - $order_info['pay_money'] + $order_info['shipping_fee'];
            if($order_info['receive_money']){
                $order_info['final_money'] = $order_info['final_money'] - $order_info['receive_money'];
            }
            if ($order_info['invoice_tax']){
                $order_info['final_money'] += $order_info['invoice_tax'];
            }
            if($order_info['membercard_deduction_money']){
                $order_info['final_money'] = $order_info['final_money'] - $order_info['membercard_deduction_money'];
            }
            $order_info['final_money'] = $order_info['final_money'] >0 ? $order_info['final_money'] :0;
        }
        return $order_info;
    }
}