<?php
namespace addons\presell\controller;

use addons\electroncard\model\VslElectroncardDataModel;
use addons\presell\model\VslPresellModel;
use data\model\VslOrderModel; 
use addons\presell\model\VslPresellGoodsModel;
use addons\presell\service\Presell as PresellService;
use addons\presell\Presell as basePresell;
use data\service\Goods;
use data\service\ActiveList;
use data\service\AddonsConfig;
use data\service\Order as OrderService;

class Presell extends basePresell
{
    public function __construct()
    {
        parent::__construct();
    }

    
    /**
     * 预售列表
     *
     * @param array $params
     **/
    public function presellList()
    {
        $presell_service = new PresellService();
        if (request()->isAjax()) {
            $page_index = $_REQUEST['page_index']?$_REQUEST['page_index']:1;
            $page_size = $_REQUEST['page_size']?$_REQUEST['page_size']:PAGESIZE;
            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = $this->instance_id;
            if(!empty($_REQUEST['search_text'])){
                $condition['name'] = $_REQUEST['search_text'];
            }
            if(!empty($_REQUEST['status'])){
                $condition['status'] = $_REQUEST['status'];
            }
            $res = $presell_service->presellList($page_index,$page_size,$condition);
            //重拼所需展示数据
            foreach ($res['data'] as $k=>$v){
                //更新活动状态,默认进行中 活动状态变更统一由活动管理进行统一处理
                $res['data'][$k]['status_name'] = '进行中';
                $condition['id'] = $v['id'];
                if(time()>$v['end_time'] || $res['data'][$k]['status'] == 3){
                    //结束
                    $res['data'][$k]['status'] = 3;
                    $res['data'][$k]['status_name'] = '已结束';
                }
                if($v['start_time']>time()){
                    $res['data'][$k]['status'] = 2;
                    $res['data'][$k]['status_name'] = '未开始';
                }
                //进行中
                if ($v['start_time'] < time() && time() < $v['end_time'] && $res['data'][$k]['status'] != 3) {
                    $res['data'][$k]['status'] = 1;
                }
                $res['data'][$k]['first_pay_time'] = date('Y-m-d H:i:s',$v['start_time']).'~'.date('Y-m-d H:i:s',$v['end_time']);

                $res['data'][$k]['last_money'] = number_format($v['allmoney'] - $v['firstmoney'],2,'.','');
                //预定总人数
                $count_people = $presell_service->getPresellCountPeople($v['id']);
                $res['data'][$k]['count_people'] = $count_people[0]['num'];
                //已够数量
                $buy_num = $presell_service->getPresellBuyNum($v['id']);
                $res['data'][$k]['surplus_num'] = $v['presellnum'] - $buy_num[0]['buy_num'];
            }
            //判断pc端、 小程序是否开启
            $res['addon_status'] = getPortIsOpen($this->website_id);
            return $res;
        }
    }


    //增加
    public function addPresell(){
        $data['name'] = $_REQUEST['name']?$_REQUEST['name']:'';
        $data['goods_id'] =  $_REQUEST['goods_id']?$_REQUEST['goods_id']:'';
        $data['sku_id'] =  $_REQUEST['sku_id']?$_REQUEST['sku_id']:'';
        $data['start_time'] = strtotime($_REQUEST['start_time']);
        $data['end_time'] = strtotime($_REQUEST['end_time'].' 23:59:59');         //活动结束时间
        $data['pay_start_time'] = strtotime($_REQUEST['pay_start_time']);              //支付开始时间
        $data['pay_end_time'] = strtotime($_REQUEST['pay_end_time'].' 23:59:59'); //支付尾款时间
        $data['send_goods_time'] = strtotime($_REQUEST['send_goods_time']);            //发货时间
        $data['active_status'] = $_REQUEST['active_status']?$_REQUEST['active_status']:'1';
        $data['allmoney'] = $_REQUEST['allmoney']?$_REQUEST['allmoney']:'0';         //总价
        $data['firstmoney'] = $_REQUEST['firstmoney']?$_REQUEST['firstmoney']:'';    //定金
        $data['presellnum'] = $_REQUEST['presellnum']?$_REQUEST['presellnum']:'1';   //库存
        $data['maxbuy'] = $_REQUEST['maxbuy']?$_REQUEST['maxbuy']:'0';               //限购
        $data['vrnum'] = $_REQUEST['vrnum']?$_REQUEST['vrnum']:'0';                 //虚拟数量
        $data['create_time'] = time();
        $data['website_id'] = $this->website_id;
        $data['shop_id'] = $this->instance_id;
        $data['least_buy'] = $_REQUEST['least_buy'] ?: 0;//最低起购
        if(empty($data['goods_id'])){
            $data['code'] = -1;
            $data['message'] = "请选择商品";
            return $data;
        }
        if($data['allmoney']<$data['firstmoney'] || $data['allmoney']==$data['firstmoney']){
            $data['code'] = -1;
            $data['message'] = "预售价格必须大于定金";
            return $data;
        }

        if($data['start_time']>$data['end_time']){
            $data['code'] = -1;
            $data['message'] = "开始时间不能大于结束时间";
            return $data;
        }
        $now_time = strtotime(date('Y-m-d 00:00:00'));
        if ($data['end_time'] < $now_time) {
            $data['code'] = -1;
            $data['message'] = "结束时间不能小于当前时间";
            return $data;
        }
        if($data['pay_start_time']>$data['pay_end_time']){
            $data['code'] = -1;
            $data['message'] = "支付开始时间不能大于结束时间";
            return $data;
        }
        if($data['pay_start_time']<$data['start_time']){
            $data['code'] = -1;
            $data['message'] = "支付时间不能小于活动开始时间";
            return $data;
        }

        if($data['pay_end_time']<$data['end_time']){
            $data['code'] = -1;
            $data['message'] = "支付结束时间不能小于活动结束时间";
            return $data;
        }

        if($data['send_goods_time']<$data['pay_start_time']){
            $data['code'] = -1;
            $data['message'] = "发货时间不能小于支付开始时间";
            return $data;
        }

        //判断是否是电子卡密商品，如果是，独立库存不能大于卡密库的库存
        $goodsSer = new \data\service\Goods();
        $goods_info = $goodsSer->getGoodsDetailById($data['goods_id'], 'goods_id,goods_type,electroncard_base_id');
        if($goods_info['goods_type'] == 5) {
            if(getAddons('electroncard',$this->website_id)) {
                $electroncard_base_mdl = new VslElectroncardDataModel();
                $electroncard_base_stock = $electroncard_base_mdl->getCount(['electroncard_base_id' => $goods_info['electroncard_base_id'],'status' => 0]);
                if($data['presellnum'] > $electroncard_base_stock) {
                    $data['code'] = -1;
                    $data['message'] = "活动库存不能大于卡密库库存";
                    return $data;
                }
                if($data['maxbuy'] > $electroncard_base_stock) {
                    $data['code'] = -1;
                    $data['message'] = "限购数目不能大于卡密库库存";
                    return $data;
                }
            }
        }

        $presell = new PresellService();
        if(!empty($_REQUEST['goods_info'])){
            $new_array = [];
            $sku_data = explode('§',$_REQUEST['goods_info']);
            unset($sku_data[0]);
            array_values($sku_data);
            foreach ($sku_data as $k=>$v){
                $num = explode(',',$v);
                $new_array[$num['0']]['all_money'] = $num[1];//预售价
                $new_array[$num['0']]['first_money'] = $num[2];//定金
                $new_array[$num['0']]['presell_num'] = $num[3];//预售库存
                $new_array[$num['0']]['max_buy'] = $num[4];//限购
                //                    $new_array[$num['0']]['vr_num'] = $num[5];
                if(!empty($_REQUEST['presell_id'])){
                    //                        $new_array[$num['0']]['presell_goods_id'] = $num[6];
                    $new_array[$num['0']]['presell_goods_id'] = $num[5];
                }
                $new_array[$num['0']]['shop_id'] = $this->instance_id;
            }
        }else{
            $new_array = [];
        }
        //编辑则重置数据
        if(!empty($_REQUEST['presell_id'])){
            $condition['id'] = $_REQUEST['presell_id'];
            $result = $presell->updatePresell($data,$new_array,$condition);
            if($result && $result == -3){
                $res['code'] = -1;
                $res['message'] = "该时间段内，该商品已存在活动，请更改时间段后重试";
                return $res;
            }
        }else{
            $result = $presell->addPresell($data,$new_array);
        }
        return AjaxReturn($result);
    }
    
     //基础配置
    public function presellConfig(){
        $post_data = $_POST;
        $is_presell = $post_data['is_presell'];
        $AddonsConfig = new AddonsConfig();
        $group_shopping_info = $AddonsConfig->getAddonsConfig('presell', $this->website_id);
        try {
            if (!empty($group_shopping_info)) {
                $res = $AddonsConfig->updateAddonsConfig($post_data, '预售设置', $is_presell, 'presell');
            } else {
                $res = $AddonsConfig->addAddonsConfig($post_data, '预售设置', $is_presell, 'presell');
            }
            setAddons('presell', $this->website_id, $this->instance_id);
            setAddons('presell', $this->website_id, $this->instance_id, true);
            return AjaxReturn($res);
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }


    //删除预售
    public function delPresell(){

        $id = request()->post('id');
        $presell = new VslPresellModel();
        $presell_goods = new VslPresellGoodsModel();
        $condition['id'] = $id;
        //恢复商品活动类型
        $presell_goods_id = $presell->getInfo(['id'=>$id],'');
        $result = $presell->delData($condition);
        $result2 = $presell_goods->delData(['presell_id'=>$id]);
        //活动列表变更为结束
        $activeListServer = new ActiveList();
        $activeListServer->changeActive($id,4,2,$this->website_id);
        $activeListServer->delActive($id,4);
        $this->updateGoodsPromotion($presell_goods_id['goods_id']);
        return AjaxReturn($result);

    }

    //关闭预售
    public function closePresell()
    {
        $redis = connectRedis();
        $id = request()->post('id');
        $presell = new VslPresellModel();
        $order_mdl = new VslOrderModel();
        $condition_order['presell_id'] = $id;
        $condition_order['order_status'] = ['in', ['-1', '0', '1', '2', '3']];
        //判断当前这档活动是否还有未完成的订单，如果有则不能删除
        $is_delete_presell = $order_mdl->alias('o')->where($condition_order)->select();
        if($is_delete_presell){
            return ['code'=>-1, 'message'=>'当前活动包含未完成的订单，暂不能删除'];
        }
        $data['status'] = 3;
        $condition['id'] = $id;
        $result = $presell->save($data,$condition);
        //恢复商品活动类型
        $presell_goods_id = $presell->getInfo(['id'=>$id],'');
        $this->updateGoodsPromotion($presell_goods_id['goods_id']);
        $activeListServer = new ActiveList();
        $activeListServer->changeActive($id,4,2,$this->website_id);
        //去掉加入预售的redis库存
        $presell_key = 'presell_'.$id.'*';
        $redis->del($presell_key);
        //去掉加入预售的redis库存
        $presell_key = 'presell_'.$id.'*';
        $redis->del($presell_key);
        return AjaxReturn($result);

    }


    //恢复商品promotion
    public function updateGoodsPromotion($goods_id){
        $goodsSer = new Goods();//商品表更新
        $goodsSer->updateGoods(['goods_id'=>$goods_id, 'promotion_type'=>3, 'website_id' => $this->website_id], ['promotion_type'=>0]);
    }

    //获取商品的规格信息
    public function getSkuList(){

        $id = $_REQUEST['goods_id'];
        $presell_id = $_REQUEST['presell_id'];
        $presell = new PresellService();
        $list = $presell->getSkuInfo($id,$presell_id);
        return $list;
 
    }


    /**
     * 预购商品选择
     */
    public function presellGoodsList()
    {
        $index = request()->post('page_index', 1);
        $search_text = request()->post('search_text');
        if ($search_text) {
            $condition['ng.goods_name'] = ['LIKE', '%' . $search_text . '%'];
        }
        $condition['ng.website_id'] = $this->website_id;
        $condition['ng.state'] = 1;
        $condition['ng.shop_id'] = $this->instance_id;
        $condition['ng.goods_type'] = ['<>',4];
        $activeListServer = new ActiveList();
        $start_time = request()->post('start_time',0);
        $end_time = request()->post('end_time',0);
        $goods = new Goods();
        $list = $goods->getModalGoodsList($index, $condition, '');
        if($list['data']){
            foreach ($list['data'] as $key=>$value){
                $goodsActiveLists = $activeListServer->goodsActiveLists($value['goods_id'],$start_time,$end_time);
                $list['data'][$key]['active_list'] = $goodsActiveLists;
            }
        }
        return $list;
    }
    //订购记录
    public function orderRecord(){
        $order_status = $_REQUEST['status']?$_REQUEST['status']:'';
        if($order_status=='1'){//订购成功
            $condition['pay_status'] = 2;
            $condition['money_type'] = 2;
        }
        if($order_status=='2'){//待付定金
            $condition['order_status'] = ['neq', 5];
            $condition['pay_status'] = 0;
            $condition['money_type'] = 0;
            $condition['presell_id'] = ['>',0];
        }
        if($order_status=='3'){//待付尾款
            $condition['order_status'] = ['neq', 5];
            $condition['money_type'] = 1;
            $condition['pay_status'] = 0;
        }
        if($order_status=='4'){//订购失败
            $condition['order_status'] = 5;
        }
        $page_index = request()->post('page_index', 1);
        $page_size = request()->post('page_size', PAGESIZE);
        $goods_name = request()->post('goods_name', '');
        $condition['is_deleted'] = 0; // 未删除订单
        $condition['presell_id'] = request()->post('presell_id', ''); // 未删除订单
        if ($goods_name) {
            $condition['goods_name'] = ['LIKE', '%' . $goods_name . '%'];
        }

        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $order_service = new OrderService();
        $list = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');
        return $list;
    }
}