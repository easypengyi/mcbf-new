<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/4/8 0008 
 * Time: 11:16
 */

namespace addons\seckill\controller;

use addons\electroncard\model\VslElectroncardDataModel;
use addons\seckill\model\VslSeckGoodsModel;
use addons\seckill\Seckill as baseSeckill;
use addons\seckill\server\Seckill as SeckillServer;
use addons\seckill\model\VslSeckillModel;
use addons\seckill\model\VslSeckillGoodsdelInfoModel;
use data\model\VslMemberFavoritesModel;
use data\service\Goods;
use data\service\GoodsCalculate\GoodsCalculate;
use data\service\User;
use think\Config;
use think\Db;
use think\Validate;
use think\View;
use data\model\VslActiveListModel;
use data\service\ActiveList;
class Seckill extends baseSeckill
{
    public function __construct()
    {
        parent::__construct();
        $this->goods = new Goods();
    }
    /*
     * *获取platform的秒杀列表
     * **/
    public function seckillAllList()
    {
        $page_index = request()->post("page_index", 1);
        $page_size = request()->post("page_size", PAGESIZE);
        $seckillServer = new SeckillServer();
        $condition = array(
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
//            'seckill_name' => array(
//                'like',
//                '%' . $search_text . '%'
//            )
        );
        $list = $seckillServer->seckillAllList($page_index, $page_size, $condition);
        return $list;
    }

    /*
     * 获取进行中的商品数
     * **/
    public function todaySeckillList()
    {
        $website_id = request()->post('website_id',26);
        $seckill_name = request()->post('seckill_name',0);
        $page_index = request()->request('page_index',1);
        $search_text = request()->post('search_text','');
        $page_size = request()->post('page_size',PAGESIZE);
        $survive_time = getSeckillSurviveTime($this->website_id);
        //获取今天的日期时间戳
        $today = strtotime(date('Y-m-d'));
        $condition = [
            'ns.website_id'=>$website_id,
            'ns.seckill_now_time' => [
                [
                    '>',time()-$survive_time*3600
                ],
                [
                    '<=',time()
                ]
            ],
            'nsg.del_status'=>1,
            'nsg.check_status'=>1
        ];
        if(!empty($search_text)){
            $condition['g.goods_name'] = ['like', '%'.$search_text.'%'];
        }
        $seckillServer = new SeckillServer();
        $res = $seckillServer->getSeckillGoodsList($page_index, $page_size, $condition, 'ns.create_time desc');
        return $res;
    }
    /*
     * 删除活动商品
     * **/
    public function delSeckillGoods()
    {
        $content = request()->post('content','');
        $goods_id = request()->post('goods_id', 0);
        $seckill_id = request()->post('seckill_id', '');
        $skgd_mdl = new VslSeckillGoodsdelInfoModel();
        $survive_time = getSeckillSurviveTime($this->website_id);
        $goods_id_arr = explode(',', $goods_id);
        $seckill_id_arr = explode(',', $seckill_id);
        foreach($goods_id_arr as $k=>$goods_id){
            //判断当前商品是否是店铺的还是平台的
            $is_shop = $this->goods->getGoodsType($goods_id);
            //0 是店铺
            if($is_shop === 0){
                if(!empty($content)){
                    $goods_name = request()->post('goods_name', '');
                    if( empty($goods_name) ){
                        $goods_name = $this->goods->getGoodsDetailById($goods_id, 'goods_name')['goods_name'];
                    }
                    //判断数据库中是否存在该条对应的删除记录，如果有则更新，因为每删除一条是可以加回去的，加回去就有需要再移除的可能
                    $is_skgd_res = $skgd_mdl->where(['seckill_id'=>$seckill_id_arr[$k], 'goods_id'=>$goods_id])->find();
                    if($is_skgd_res){
                        $del_info['seckill_del_info'] = $content;
                        $skgd_mdl->where(['seckill_id'=>$seckill_id_arr[$k], 'goods_id'=>$goods_id])->update($del_info);
                    }else{
                        $params['seckill_id'] = $seckill_id_arr[$k];
                        $params['goods_id'] = $goods_id;
                        $params['seckill_del_info'] = $content;
                        $params['goods_name'] = $goods_name;
                        $skgd_mdl->data($params,true)->isUpdate(false)->save($params);
                    }
                }
            }
            $condition = [
                'goods_id' => $goods_id,
                'seckill_id' => $seckill_id_arr[$k],
            ];
            $sec_server = new SeckillServer();
            $res = $sec_server->delSeckillGoods($condition);
            if($res){
                //判断是否还有其它时间段的未过期的该类商品档
                $other_cond['sg.goods_id'] = $goods_id;
                $other_cond['sg.del_status'] = 1;
                $other_cond['s.seckill_now_time'] = ['>=', time()-$survive_time*3600];
                $is_other_seckill = $sec_server->isOtherSeckillExists($other_cond);
                if(!$is_other_seckill){
                    //将商品的促销类型去掉
                    $goods_condition = ['goods_id'=>$goods_id, 'promotion_type'=>1];
                    $goods_res['promotion_type'] = 0;
                    $this->goods->updateGoods($goods_condition, $goods_res);
                }
            }
            if($res){
                $this->addUserLog('移除/删除秒杀活动商品', $res);
            }

        }
        return AjaxReturn(1);
    }
    /*
     * 活动商品通过审核接口
     * **/
    public function passSeckillGoods()
    {
        $seckillServer = new SeckillServer();
        $seckill_id = request()->post('seckill_id',0);
        $goods_id = request()->post('goods_id',0);
        $seckill_id_arr = explode(',', $seckill_id);
        $goods_id_arr = explode(',', $goods_id);
        $res = 1;
        foreach($goods_id_arr as $k=>$gid){
            $sid = $seckill_id_arr[$k];
            //这里为什么goods_id seckill_id就够了，因为每个店铺报名后有且只有一个seckill_id，对应一个时间点，并且对应一个商品
            $condition['goods_id'] = $gid;
            $condition['seckill_id'] = $sid;
            $bool = $seckillServer->seckillGoodsChecked($condition);
            if(!$bool){
                $res = 0;
            }
        }
        if($res){
            $this->addUserLog('活动商品通过审核接口', $res);
        }
        return AjaxReturn($res);
    }
    /**
     * 店铺秒杀商品选择
     */
    public function modalSeckillGoodsList()
    {
        $index = request()->post('page_index', 1);
        $goods_type = request()->post('goods_type', 1);
        $search_text = request()->post('search_text');
        $seckill_time = request()->post('seckill_time');
        $seckill_name = request()->post('seckill_name');
        if($seckill_time && $seckill_name){
            $seckill_date = $seckill_time.' '.$seckill_name.':00:00';
        }
        if ($search_text) {
            $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }
        $condition['ng.website_id'] = $this->website_id;
        $condition['ng.shop_id'] = $this->instance_id;
        $condition['ng.state'] = 1;
        //0自营店 1全平台
        if ($goods_type == '0' || $this->instance_id > 0) {
            $condition['ng.shop_id'] = $this->instance_id;
        }
        $activeListServer = new ActiveList();
        $start_time = request()->post('start_time',0);
        //开始时间需要拼接时数
        $end_time = request()->post('end_time',0);
        if($end_time == 0){ //默认24小时结束
            $hours = getSeckillSurviveTime($this->website_id);
            $end_time = date('Y-m-d H:i:s',strtotime("$seckill_date+$hours hours"));
        }else{
            $hours = getSeckillSurviveTime($this->website_id);
            $end_time = date('Y-m-d H:i:s',strtotime("$end_time+$hours hours"));
        }
       
        $list = $this->goods->getModalGoodsList($index, $condition, $seckill_date);
        if($list['data']){
            foreach ($list['data'] as $key=>$value){ 
                $goodsActiveLists = $activeListServer->goodsActiveLists($value['goods_id'],$seckill_date,$end_time,1);
                $list['data'][$key]['active_list'] = $goodsActiveLists;
            }
        }
        return $list;
    }
    /*
     * 添加秒杀配置
     * **/
    public function secSetting()
    {
        if(!empty($_POST)){
            if( !isset($_POST['sk_quantum_str'] ) || ( empty($_POST['sk_quantum_str']) && $_POST['sk_quantum_str'] != '0' ) ){
                $code = -22;
                $message = '秒杀时段不能为空';
                return compact($code,$message);
            }
            if( !isset($_POST['pay_limit_time'] ) || empty($_POST['pay_limit_time']) ){
                $code = -22;
                $message = '支付限时不能为空';
                return compact($code,$message);
            }
        }
        $survive_time = getSeckillSurviveTime($this->website_id);
        $seckillServer = new SeckillServer();
        $is_open = $_POST['is_open'] ?: 0;
        unset($_POST['is_open']);
        
        $value = json_encode($_POST);
        $result = $seckillServer->saveSeckConfig($is_open,$value);
        if($result){
            if($is_open == 0){
                //找到正在开始的、未开始的秒杀活动
                $close_cond['seckill_now_time'] = ['>=', time() - $survive_time * 3600];
                $close_cond['website_id'] = $this->website_id;
                $seckill_mdl = new VslSeckillModel();
                $seckill_goods_mdl = new VslSeckGoodsModel();
                $seckill_id_arr = $seckill_mdl->where($close_cond)->column('seckill_id');
                if(count($seckill_id_arr) >= 1){
                    $close_cond2['seckill_id'] = ['in', $seckill_id_arr];
                    $goods_id_arr = $seckill_goods_mdl->where($close_cond2)->group('goods_id')->column('goods_id');
                    $sec_server = new SeckillServer();
                    $res = $sec_server->delSeckillGoods($close_cond2);
                    if($res){
                        $goods_condition = ['goods_id'=>['in', $goods_id_arr], 'promotion_type'=>1];
                        $goods_res['promotion_type'] = 0;
                        $this->goods->updateGoods($goods_condition, $goods_res);
                    }
                }
            }
            $this->addUserLog('添加秒杀配置', $result);
        }
        setAddons('seckill', $this->website_id, $this->instance_id);
        setAddons('seckill', $this->website_id, $this->instance_id, true);
        return AjaxReturn($result);
    }
    /*
     * 添加秒杀活动及其商品
     * **/
    public function addSecKill()
    {
        $sec_server = new SeckillServer();
        if(!getAddons('seckill', $this->website_id, $this->instance_id)){
            return ['code'=>-1, 'message'=>'秒杀应用未开启'];
        }
        $survive_time = getSeckillSurviveTime($this->website_id);
        $seckill_name = request()->post('seckill_name','');
        $seckill_time = request()->post('seckill_time','');
        $seckill_date = $seckill_time.' '.$seckill_name.':00:00';
        $seckill_date_time = strtotime($seckill_date);
        if($seckill_date_time < time()){
            return ['code'=>-1,'message'=>'秒杀时间不能为过期时间'];
        }
        if(empty($seckill_time)){
            return ['code'=>-1,'message'=>'活动时间不能为空'];
        }
        if(empty($seckill_name)&&$seckill_name !== '0'){
            return ['code'=>-1,'message'=>'秒杀场次不能为空'];
        }
        //判断是否是电子卡密商品，如果是，独立库存不能大于卡密库的库存
        $goods_info = $this->goods->getGoodsDetailById($_POST['goods_id']);
        if($goods_info['goods_type'] == 5) {
            if(getAddons('electroncard',$this->website_id)) {
                $electroncard_base_mdl = new VslElectroncardDataModel();
                $electroncard_base_stock = $electroncard_base_mdl->getCount(['electroncard_base_id' => $goods_info['electroncard_base_id'],'status' => 0]);
            }
        }
        //验证seckill_goods表
        $sku_goods = [];
        foreach($_POST['goods_info'] as $sku_id=>$goods){
            $sku_goods['seckill_num'] = $goods['seckill_num'];
            $sku_goods['seckill_price'] = $goods['seckill_price'];
            if(empty($sku_goods['seckill_num'])){
                return ['code'=>-333,'message'=>'商品所有规格的活动库存不能为空'];
            }
            //            if(empty($sku_goods['seckill_price']) && $sku_goods['seckill_price'] != 0){
            if(empty($sku_goods['seckill_price'])){
                return ['code'=>-333,'message'=>'商品所有规格的活动价格不能为空并且须大于0'];
            }
            //            //限购
            //            if(empty($goods['seckill_limit_buy'])){
            //                return ['code'=>-333,'message'=>'商品所有规格的限购数目不能为空并且须大于0'];
            //            }
            if($goods_info['goods_type'] == 5) {//电子卡密商品
                if($goods['seckill_num'] > $electroncard_base_stock) {
                    return ['code'=>-333,'message'=>'活动库存不能大于卡密库库存'];
                }
                if($goods['seckill_limit_buy'] > $electroncard_base_stock) {
                    return ['code'=>-333,'message'=>'限购数目不能大于卡密库库存'];
                }
            }
        }
        //查询24小时内该场次是否存在该商品了，如果存在，则返回该商品已经存在
        $where['ns.website_id'] = $this->website_id;
        $where['ns.shop_id'] = $this->instance_id;
        //seckill_now_time+24*3600>time()  seckill_now_time<=time()
        if((empty($_POST['seckill_name']) && $_POST['seckill_name'] !== '0') || empty($_POST['seckill_time'])){
            return ['code'=>-333,'message'=>'所选活动时间或活动场次不能为空'];
        }
        $post_seckill_now_time = $_POST['seckill_time'].' '.$_POST['seckill_name'].':00:00';
        $post_time = strtotime($post_seckill_now_time);
        $min_time = $post_time-$survive_time*3600;
        $max_time = $post_time+$survive_time*3600;
        $test = date('Y-m-d H:i:s',$max_time);

        $where['ns.seckill_now_time'] = [['>',$min_time], ['<',$max_time]];
        $where['nsg.goods_id'] = $_POST['goods_id'];
        //获取店铺审核方式 0-手动 1-自动
        $check_method = (int)$sec_server->getCheckMethod();
        $seckill_mdl = new VslSeckillModel();
        $goods_list = $seckill_mdl->getSeckillGoodsList($where);
        $del_status = (int)$goods_list[0]->del_status;
        //del_status为1说明该商品为正常存在，0为移除，如果继续添加，则将状态改为1。
        if($goods_list && $del_status === 1){
            return ['code'=>-334,'message'=>'该商品'. $survive_time .'小时内已有其它秒杀活动存在'];
        }
        //判断这个商品和秒杀时间点是否存在秒杀记录:通过选择的时间判断该商品是否存在秒杀记录，如果24小时内有其它时间档的商品，则会把该秒杀id查出，但是实际现在要添加的商品不存在这个秒杀id，如果进行更新，则会把原来的秒杀id更新掉，原来秒杀id对应的商品就会变成现在要添加的秒杀时间档。
        $seckill_id = (int)$goods_list[0]->seckill_id;
        $sec_cond['ns.seckill_id'] = $seckill_id;
        $sec_cond['seckill_name'] = $seckill_name;
        $sec_cond['nsg.goods_id'] = $_POST['goods_id'];
        $is_sec_record = $seckill_mdl->getSeckillGoodsList($sec_cond);
        $redis = connectRedis();
        if($is_sec_record){/*修改*/
            $ret_val = $sec_server->updateSeckillGoodsDelStatus($seckill_id,$check_method, $_POST, $redis);
        }else{/*新增*/
            $ret_val = $sec_server->addSecKill($_POST, $check_method, $redis);
            $seckill_id = $ret_val;
        }
        $str_date = $_POST['seckill_time'].' '.$_POST['seckill_name'].':00:00';
        $seckill_now_time = strtotime($str_date);
        if($ret_val){
            //延时队列处理砍价活动开始的状态
            $url = config('rabbit_interface_url.url');
            $back_url1 = $url.'/rabbitTask/activityStatus';
            $delay_time1 = $seckill_now_time - time();
            $this->actSeckillDelay($seckill_id, $delay_time1, $back_url1);
            //延时队列处理砍价活动结束的promotion_type
            $back_url2 = $url.'/rabbitTask/upActivityGoodsProType';
            $survive_time = getSeckillSurviveTime($this->website_id);
            $delay_time2 = $seckill_now_time - time() + $survive_time * 3600;
            $this->actSeckillDelay($seckill_id, $delay_time2, $back_url2);
            $this->addUserLog('添加秒杀活动及其商品', $ret_val);
        }
        return AjaxReturn(1);
    }

    /**
     * 处理活动过期
     * @param $seckill_id
     * @param $delay_time
     */
    public function actSeckillDelay($seckill_id, $delay_time, $back_url)
    {
        if(config('is_high_powered')){
            $website_id = $this->website_id;
            $config['delay_exchange_name'] = config('rabbit_delay_activity.delay_exchange_name');
            $config['delay_queue_name'] = config('rabbit_delay_activity.delay_queue_name');
            $config['delay_routing_key'] = config('rabbit_delay_activity.delay_routing_key');
            $delay_time = $delay_time * 1000;//毫秒
            $delay_time = $delay_time <= 0 ? 0 : $delay_time;
            $data = [
                'type' => 'seckill',
                'seckill_id' => $seckill_id,
                'website_id' => $website_id
            ];
            $data = json_encode($data);
            $custom_type = 'activity_promotion';//活动
            delayPushData($config, $delay_time, $data, $back_url, $custom_type);
        }
    }
    //ajax获取某个秒杀点的审核与未审核的商品列表信息
    public function getAjaxSeckNameGoodsList()
    {
        $seckillServer = new SeckillServer();
        $now_check_date = request()->post('seckill_time_str', '');
        $check_status = request()->post('check_status');
        $page_index = request()->post('page_index',1);
        $page_size = request()->post('page_size',PAGESIZE);
        $seckill_time = strtotime($now_check_date);
        $seckill_name = request()->post('seckill_name','');
        $search_text = request()->post('search_text','');
        $website_id = $this->website_id;
        $check_date_arr = $seckillServer->getSeckillCheckTime();
        $end_date = array_pop($check_date_arr);
        $end_time = strtotime($end_date);
        $condition = [
            'ns.website_id'=>$website_id,
            'ns.seckill_name' => $seckill_name,
            'nsg.check_status' => $check_status,
            'nsg.del_status' => 1,
            'ns.seckill_now_time'=> ['>=',time()],
        ];
        if($now_check_date == '更多'){
            $condition['ns.seckill_time'] = ['>', $end_time];
        }else{
            //获取某个时间段的商品
            $condition['ns.seckill_time'] = $seckill_time;
        }

        if(!empty($search_text)){
            $condition['g.goods_name'] = ['like', '%'.$search_text.'%'];
        }
        $sec_goods_list = $seckillServer->getSeckillGoodsList($page_index, $page_size, $condition, 'ns.create_time desc');
        return $sec_goods_list;
    }
    /*
     * 获取商品信息统计数据
     * **/
    public function getSecGoodsInfoCount(){
        $seckillServer = new SeckillServer();
        $page_index = request()->request('page_index',1);
        $page_size = request()->request('page_size',PAGESIZE);
        //条件
        $condition['nsg.goods_id'] = ['>', 0];
        $condition['nsg.seckill_price'] = ['>', 0];
        $condition['ns.website_id'] = $this->website_id;
        $search_text = request()->post('search_text','');
        $seckill_date = request()->post('seckill_date','');
        $seckill_name = request()->post('seckill_name','');
        if(!empty($search_text)){
            $condition['g.goods_name'] = ['like', '%'.$search_text.'%'];
        }
        if(!empty($seckill_date)){
            $seckill_time = strtotime($seckill_date);
            $condition['ns.seckill_time'] = $seckill_time;
        }
        if($seckill_name != '-1' && $seckill_name !== ''){
            $condition['ns.seckill_name'] = $seckill_name;
        }
        //排序
        $order = request()->post('order','');
        switch($order){
            case 'store_num_asc':
                $order = 'store_num ASC';
                break;
            case 'store_num_desc':
                $order = 'store_num DESC';
                break;
            case 'store_price_asc':
                $order = 'store_price ASC';
                break;
            case 'store_price_desc':
                $order = 'store_price DESC';
                break;
            case 'seckill_price_asc':
                $order = 'seckill_price ASC';
                break;
            case 'seckill_price_desc':
                $order = 'seckill_price DESC';
                break;
        }
        $goods_count_list = $seckillServer->getSecGoodsCountInfo($condition,$order);
        //处理每个商品对应的商品名称、图片、店铺
        $new_goods_count_info_arr = [];
        $new_goods_count_arr = [];
        $goods_quantity_total = 0;
        $store_price_total = 0;
        $store_num_total = 0;
        foreach($goods_count_list as $k=>$goods_count){
            $goods_id = $goods_count->goods_id;
            $every_goods_info = $this->goods->getGoodsDetailById($goods_id, 'goods_id,goods_name,picture,website_id,shop_id', 1, 0, 0, 1);
            if (empty($every_goods_info)) {
                continue;
            }
            $new_goods_count_info_arr[$k]['goods_name'] = $every_goods_info['goods_name'];
            $new_goods_count_info_arr[$k]['goods_img'] = getApiSrc($every_goods_info['album_picture']['pic_cover_small']);
            $new_goods_count_info_arr[$k]['shop_name'] = $every_goods_info['shop_name'];
            $new_goods_count_info_arr[$k]['store_price'] = $goods_count->store_price;
            $new_goods_count_info_arr[$k]['store_num'] = $goods_count->store_num;
            $new_goods_count_info_arr[$k]['seckill_price'] = $goods_count->seckill_price;
            //统计总商品件数、总销量、总销售额
            $goods_quantity_total++;
            $store_price_total = $store_price_total+$goods_count->store_price;
            $store_num_total = $store_num_total+$goods_count->store_num;
        }
        $new_goods_count_arr['goods_quantity_total'] = $goods_quantity_total;
        $new_goods_count_arr['store_price_total'] = $store_price_total;
        $new_goods_count_arr['store_num_total'] = $store_num_total;
        $total_count = count($goods_count_list);
        $page_count = ceil($total_count/$page_size);
        //分页起始量
        $start_offset = ($page_index-1)*$page_size;
        //分页末变量
        $end_offset = $page_index*$page_size;
        $res_goods_count_info = [];
        for($i=$start_offset;$i<$end_offset;$i++){
            $res_goods_count_info[$i] = $new_goods_count_info_arr[$i];
            if(empty($res_goods_count_info[$i])){
                unset($res_goods_count_info[$i]);
            }
        }
        return  [
            'data' => $res_goods_count_info,
            'goods_count_total'=>$new_goods_count_arr,
            'total_count' => $total_count,
            'page_count' => $page_count,
        ];
    }
    /*
     * Ajax根据条件获取后台秒杀商品列表-商户C端
     * **/
    public function getAdminSecKillList()
    {
        $seckillServer = new SeckillServer();
        $status = request()->post('status');
        $page_index = request()->post('page_index',1);
        $page_size = request()->post('page_size',PAGESIZE);
        $search_text = request()->post('search_text','');
        $date = request()->post('date','');
        $website_id = $this->website_id;
        $instance_id = $this->instance_id;
        $survive_time = getSeckillSurviveTime($website_id);
        $condition['ns.website_id'] = $website_id;
        $condition['ns.shop_id'] = $instance_id;
        //处理日期条件
        if( !empty($date) ){
           $date_arr = explode(' - ', $date);
           $start_date = $date_arr[0];
           $end_date = $date_arr[1];
           if($start_date == $end_date){
               $start_seckill_time = strtotime($start_date);
               $condition['ns.seckill_time'] = $start_seckill_time;
           }elseif( !empty($start_date) && !empty($end_date) ){
               $start_seckill_time = strtotime($start_date);
               $end_seckill_time = strtotime($end_date);
               $condition['ns.seckill_time'] = [
                   ['>', $start_seckill_time],
                   ['<', $end_seckill_time],
               ];
           }
        }
        //处理商品名称
        if( !empty($search_text) ){
            $condition['g.goods_name'] = ['like', '%'.$search_text.'%'];
        }
        //获取每个状态的总数
        $goods_status_count_arr = $seckillServer->getStatusGoodsCount($condition);
        //今天当前时间
        $h = date('H');
        $today = date('Y-m-d');
        $seckill_now_time = strtotime($today.' '.$h.':00:00');

        //处理状态条件
        switch($status){
            case 'going':
                //获取当前的时间点和今天日期
                $condition['ns.seckill_now_time'] = [
                    [
                        '>',time()-$survive_time*3600
                    ],
                    [
                        '<=',time()
                    ]
                ];
                $condition['nsg.check_status'] = 1;
                $condition['nsg.del_status'] = 1;
                break;
            case 'unstart':
                //获取当前的时间点和今天日期 seckill_now_time > time()
                $condition['ns.seckill_now_time'] = ['>', time()];
                $condition['nsg.check_status'] = 1;
                $condition['nsg.del_status'] = 1;
                break;
            case 'uncheck':
                //获取当前的时间点和今天日期
                $condition['ns.seckill_now_time'] = ['>', time()];
                $condition['nsg.check_status'] = 0;
                $condition['nsg.del_status'] = 1;
                break;
            case 'ended':
                //获取当前的时间点和今天日期 seckill_now_time + 24*3600 < time()
                $condition['ns.seckill_now_time'] = ['<', time() - $survive_time*3600];
                $condition['nsg.del_status'] = 1;
                break;
            case 'refused':
                $condition['nsg.del_status'] = 0;
                //获取vsl_seckill_delgoods_info中的数据
                break;
        }
        if($status != 'refused'){
            $sec_goods_list = $seckillServer->getSeckillGoodsList($page_index, $page_size, $condition, 'ns.create_time desc');
        }else{
            $sec_goods_list = $seckillServer->getStatusGoodsList($page_index, $page_size, $condition, 'ns.create_time desc');
        }
        $sec_goods_list['status_goods_total'] = $goods_status_count_arr;
        return $sec_goods_list;
    }
    /*
     * 获取并处理所有循环时间段
     * **/
    public function getAllSecTime()
    {
        $seckillServer = new SeckillServer();
        //先获取当前时间点
        $now = date('H');
        $now = (int)$now;
        //获取所有的报名时间段
        $sk_quantum_str = $seckillServer->getSeckTime();//0,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23
        $sk_quantum_arr = explode(',', $sk_quantum_str);
        $survive_time = getSeckillSurviveTime($this->website_id);
        $new_quantum_arr = [];
        //如果是设置了关闭好货疯抢，秒杀1小时，则去掉好货疯抢
        if($survive_time != 1){
            $new_quantum_arr = [
                [
                    'tag_name'=>'好货疯抢',
                    'tag_status'=>'started',//已疯抢
                    'condition_time' => 'good_rushed',
                    'condition_day' => 'good_rushed',
                ]
            ];
        }
        foreach($sk_quantum_arr as $k=>$time){
            if($time==$now){
                //抢购中
                $tag_arr = [
                    'tag_name' => $time.':00',
                    'tag_status' => 'going',//抢购中
                    'condition_time' => $time,
                    'condition_day' => 'today',
                ];
                array_push($new_quantum_arr,$tag_arr);
            }
        }
        foreach($sk_quantum_arr as $k2=>$time2){
            $time2 = (int)$time2;
            if($time2>$now && $time2<=23){
                //则为今日的时间点
                $tag_arr = [
                    'tag_name' => $time2.':00',
                    'tag_status' => 'unstart',//即将开抢
                    'condition_time' => $time2,
                    'condition_day' => 'today',
                ];
                array_push($new_quantum_arr,$tag_arr);
            }
        }
        foreach($sk_quantum_arr as $k3=>$time3){
            $time3 = (int)$time3;
            if($time3<$now){
                //则为今日的时间点
                $tag_arr = [
                    'tag_name' => $time3.':00',
                    'tag_status' => 'tomorrow_start',//即将开抢
                    'condition_time' => $time3,
                    'condition_day' => 'tomorrow',
                ];
                array_push($new_quantum_arr,$tag_arr);
            }
        }
        $res_arr['code'] = 1;
        $res_arr['message'] = '获取成功';
        $res_arr['data'] = $new_quantum_arr;
        return $res_arr;
    }
    /*
     * wap端各自条件秒杀商品列表接口
     * **/
    public function getSeckillGoodsList()
    {
        $seckillServer = new SeckillServer();
        $condition_time = request()->post('condition_time');
        $condition_day = request()->post('condition_day');
        $tag_status = request()->post('tag_status');
        $page_size = request()->post('page_size',PAGESIZE);
        $page_index = request()->post('page_index',1);
        $condition['ns.website_id'] = $this->website_id;
        $condition['nsg.check_status'] = 1;
        $condition['nsg.del_status'] = 1;
        $survive_time = getSeckillSurviveTime($this->website_id);
        if($condition_time == 'good_rushed' && $condition_day == 'good_rushed'){
            //当前时间大于开始时间，小于结束时间
            $time = time();
            $past_time = $time - $survive_time*3600;
            $condition['ns.seckill_now_time'] = [
                ['>=', $past_time],['<=', $time]
            ];
        }else{
            if($condition_day == 'today'){
                $seckill_name = $condition_time;
                $seckill_time = strtotime(date('Y-m-d'));
            }elseif($condition_day == 'tomorrow'){
                $seckill_name = $condition_time;
                $seckill_time = strtotime(date('Y-m-d', strtotime('+1 day')));
            }
            $condition['ns.seckill_name'] = $seckill_name;
            $condition['ns.seckill_time'] = $seckill_time;
        }
        // 获取该用户的权限
        if($this->uid) {
            $userService = new User();
            $userLevle = $userService->getUserLevelAndGroupLevel($this->uid);// code | <0 错误; 1系统会员; 2;分销商; 3会员
            if (!empty($userLevle)) {
                $level_flag = false;
                $sql1 = '';
                $sql2 = '(';
                // 会员权限
                if ($userLevle['user_level']) {
                    $level_flag = true;
                    $u_id = $userLevle['user_level'];
                    $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_u, ',' ), ',".$u_id.",' ) OR ";
                    $sql2 .= "vgd.browse_auth_u IS NULL OR vgd.browse_auth_u = '' ";
                }
                // 分销商权限
                if ($userLevle['distributor_level']) {
                    $level_flag = true;
                    $d_id = $userLevle['distributor_level'];
                    $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_d, ',' ), ',".$d_id.",' ) OR ";
                    $sql2 .= " OR vgd.browse_auth_d IS NULL OR vgd.browse_auth_d = '' ";
                }

                // 标签权限
                if ($userLevle['member_group']) {
                    $level_flag = true;
                    $g_ids = explode(',',$userLevle['member_group']);
                    foreach ($g_ids as $g_id) {
                        $sql1 .= "instr(CONCAT( ',', vgd.browse_auth_s, ',' ), ',".$g_id.",' ) OR ";
                        $sql2 .= " OR vgd.browse_auth_s IS NULL OR vgd.browse_auth_s = '' ";
                    }
                } else {
                    $sql1 .= "  ";
                }
                $sql2 .= " )";
                if ($level_flag){
                $condition[] = ['exp', $sql1 . $sql2];
                }
            }
        }

        //获取所有的已审核秒杀、及秒杀商品
        $order_by = 'ns.create_time desc';
        $seckill_goods_list = $seckillServer->getSeckillGoodsList($page_index, $page_size, $condition, $order_by);
        $seckill_goods_arr = objToArr($seckill_goods_list);
        //商品列表：商品名称、已抢（实际+虚拟抢购量）、总活动库存、抢购说明、百分数、秒杀价、原价。
        $new_data = [];
        $seck_goods_mdl = new VslSeckGoodsModel();
        foreach($seckill_goods_arr['data'] as $k=>$data){
            //查询该商品sku所有的活动库存、剩余库存、虚拟抢购量
            $seckill_id = $data['seckill_id'];
            $goods_id = $data['goods_id'];
            $sec_goods_info = $seck_goods_mdl->getInfo(['goods_id' => $goods_id, 'seckill_id' => $seckill_id], 'sum(seckill_num) as seckill_num, sum(remain_num) as remain_num, sum(seckill_sales) as seckill_sales, min(seckill_price) as seckill_price');
//            $sec_goods_info['seckill_vrit_num'] = $data['seckill_vrit_num'];
            //已抢购 活动库存-剩余库存+虚拟抢购量
//            $robbed_num = (int)$sec_goods_info['seckill_num'] - ((int)$sec_goods_info['remain_num'] + (int)$sec_goods_info['seckill_vrit_num']);
            $robbed_num = (int)$sec_goods_info['seckill_sales'] + $data['seckill_vrit_num'];// 调整：虚拟抢购量显示在进度条中
            $robbed_percent = round(($robbed_num/(int)$sec_goods_info['seckill_num']*100)>100 ? 100 : ($robbed_num/(int)$sec_goods_info['seckill_num']*100))."％"  ;
            //获取当前的时间点和今天日期
            $h = (int)date('H');
            if($condition_day == 'good_rushed' && $condition_time == 'good_rushed'){
                $rob_time = '马上抢';
            }elseif($condition_day == 'today' && $h == $condition_time){
                $rob_time = '马上抢';
            }elseif($condition_day == 'today' && $h < $condition_time){
                $rob_time = $seckill_name.'点开抢';
            }elseif($condition_day == 'tomorrow' && $h > $condition_time){
                $rob_time = '明日'.$seckill_name.'点开抢';
            }
            //判断商品是否收藏过
            $member_favorite_mdl = new VslMemberFavoritesModel();
            $is_collection = $member_favorite_mdl->getInfo(
                    ['fav_id'=>$data['goods_id'],
                    'fav_type'=>'goods',
                    'seckill_id'=>$data['seckill_id']],
                    '*');
            $is_collection = $is_collection ? true : false;
            $new_data[$k]['goods_id'] = $data['goods_id'];
            $new_data[$k]['goods_name'] = $data['goods_name'];
            $new_data[$k]['seckill_id'] = $data['seckill_id'];
            $new_data[$k]['goods_id'] = $data['goods_id'];
            $new_data[$k]['goods_img'] = $data['pic_cover_big'];
            $new_data[$k]['remain_num'] = $data['remain_num'];
            //已抢购百分数
            $new_data[$k]['robbed_num'] = $robbed_num;
            $new_data[$k]['robbed_percent'] = $robbed_percent;
            $new_data[$k]['seckill_price'] = $sec_goods_info['seckill_price'];
            $new_data[$k]['seckill_num'] = (int)$data['seckill_num'];
            $new_data[$k]['price'] = (float)$data['price'];
            $new_data[$k]['rob_time'] = $rob_time;
            $new_data[$k]['condition_day'] = $condition_day;
            $new_data[$k]['condition_time'] = $condition_time;
            $new_data[$k]['tag_status'] = $tag_status;
            $new_data[$k]['is_collection'] = $is_collection;
        }
        $seckill_goods_arr['data'] = $new_data;
        $res_arr['code'] = 1;
        $res_arr['message'] = '获取成功';
        $res_arr['data']['sec_goods_list'] = $seckill_goods_arr['data'];
        $res_arr['data']['page_count'] = $seckill_goods_arr['page_count'];
        $res_arr['data']['total_count'] = $seckill_goods_arr['total_count'];
        return $res_arr;
    }
    public function getIndexSeckillList(){
        //获取当前小时的上一个时间点
        $seckill_server = new SeckillServer();
        $survive_time = getSeckillSurviveTime($this->website_id);
        $times_list = $seckill_server->getSeckTime();
        $times_arr = explode(',',$times_list);
        //获取seckill_now_time
        $now_time = strtotime(date('Y-m-d H:00:00'));
        $start_now_time = $now_time - $survive_time*3600;
        $end_now_time = $now_time + $survive_time*3600;
        foreach($times_arr as $val){
            //获取当前时间前后$survive_time小时
            $hours_time = strtotime(date('Y-m-d '.$val.':00:00'));
            $hours_time1 = strtotime(date('Y-m-d '.$val.':00:00')) - $survive_time*3600;
            $hours_time2 = strtotime(date('Y-m-d '.$val.':00:00')) + $survive_time*3600;
            //当前时间点
            $seckill_now_time = $now_time;
            //过去时间点
            if($start_now_time<$hours_time1 && $hours_time1<$now_time){
                $seckill_pass_time_arr[] = $hours_time1;
            }
            if($start_now_time<$hours_time2 && $hours_time2<$now_time){
                $seckill_pass_time_arr[] = $hours_time2;
            }
            if($start_now_time<$hours_time && $hours_time<$now_time){
                $seckill_pass_time_arr[] = $hours_time;
            }
            //待开始时间点
            if($now_time<$hours_time1 && $hours_time1<$end_now_time){
                $seckill_unstart_time_arr[] = $hours_time1;
            }
            if($now_time<$hours_time2 && $hours_time2<$end_now_time){
                $seckill_unstart_time_arr[] = $hours_time2;
            }
            if($now_time<$hours_time && $hours_time<$end_now_time){
                $seckill_unstart_time_arr[] = $hours_time;
            }
        }
        sort($seckill_pass_time_arr);
        sort($seckill_unstart_time_arr);
        $goods_sort = request()->post('seckill_goods_sort');
        if (!isset($goods_sort)) {
            return json(AjaxReturn(LACK_OF_PARAMETER));
        }
        //活动申请时间升序 0
        if($goods_sort == 0){
            $order = 's.create_time asc';
        }
        //活动申请时间降序 1
        if($goods_sort == 1){
            $order = 's.create_time desc';
        }
        //销量升序 2
        if($goods_sort == 2){
            $order = 'sum(seckill_sales) asc';
        }
        //销量降序 3
        if($goods_sort == 3){
            $order = 'sum(seckill_sales) desc';
        }
        //收藏数升序 4
        if($goods_sort == 4){
            $order = 'g.collects asc';
        }
        //收藏数降序 5
        if($goods_sort == 5){
            $order = 'g.collects desc';
        }
        //优先取当前时间点
        $condition1['seckill_now_time'] = $seckill_now_time;
        $condition1['s.website_id'] = $this->website_id;
        $seckill_goods_list = $seckill_server->getIndexSeckillGoods($condition1, $order);
        $get_time = $seckill_now_time;
        $seckill_going_status = 'going';
        //当前时间点没有取未来$survive_time小时时间点
        if(!$seckill_goods_list && $seckill_unstart_time_arr){
            foreach($seckill_unstart_time_arr as $seckill_now_time1){
                $condition2['s.seckill_now_time'] = $seckill_now_time1;
                $condition2['s.website_id'] = $this->website_id;
                $seckill_goods_list = $seckill_server->getIndexSeckillGoods($condition2, $order);
                $get_time = $seckill_now_time1;
                $seckill_going_status = 'unstart';
                if($seckill_goods_list){
                    break;
                }
            }
            //未来时间点没有取过去$survive_time小时时间点
            if(!$seckill_goods_list && $seckill_pass_time_arr){
                foreach($seckill_pass_time_arr as $seckill_now_time2){
                    $condition3['s.seckill_now_time'] = $seckill_now_time2;
                    $condition3['s.website_id'] = $this->website_id;
                    $seckill_goods_list = $seckill_server->getIndexSeckillGoods($condition3, $order);
                    $get_time = $seckill_now_time2;
                    $seckill_going_status = 'going';
                    if($seckill_goods_list){
                        break;
                    }
                }
            }
        }
        if($seckill_goods_list){
            foreach($seckill_goods_list as $k=>$v){
                $data_list['seckill_time'] = $v['seckill_name']?:'';
                $data_list['seckill_going_status'] = $seckill_going_status?:'';
                $data_list['end_time'] = $get_time + $survive_time*3600;//结束时间
                $data_list['begin_time'] = $get_time;//开始时间
                $data_list['goods_list'][$k]['pic_cover'] = getApiSrc($v['pic_cover'])?:'';
                $data_list['goods_list'][$k]['goods_name'] = $v['goods_name']?:'';
                $data_list['goods_list'][$k]['goods_id'] = $v['goods_id']?:'';
                $data_list['goods_list'][$k]['seckill_price'] = $v['seckill_price']?:'';
            }
        }
        
        if(!$data_list){
            $data_list['seckill_time'] = '';
            $data_list['seckill_going_status'] = '';
            $data_list['end_time'] = '';//结束时间
            $data_list['begin_time'] = '';//开始时间
            $data_list['goods_list'] = [];
        }
        return json([
            'code'=>0,'message'=>'获取成功',
            'data'=>$data_list
        ]);
    }
    /*
     * 记录删除秒杀商品的删除原因
     * **/
    public function modalSeckillDelGoodsRecord()
    {
        $this->fetch('template/' . $this->module . '/seckillDelGoodsRecordDialog');
    }
}