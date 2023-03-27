<?php
namespace app\platform\controller;

use addons\bonus\model\VslOrderBonusLogModel;
use addons\distribution\model\VslOrderDistributorCommissionModel;
use addons\distribution\service\Distributor as DistributorService;
use data\model\UserModel;
use data\model\VslMemberModel;
use data\model\VslOrderGoodsModel;
use data\service\ExcelsExport;
use data\service\Order;
use data\service\Goods;
use data\service\Member;
use data\model\VslOrderGoodsViewModel;
use data\model\VslOrderModel;
use data\service\Order as OrderService;

/**
 * 系统模块控制器
 *
 * @author  www.vslai.com
 *
 */
class Statistics extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 会员统计
     */
    public function userStat()
    {
        $user = new Member();
        $user_count_num = $user->getMemberCount(array("website_id" => $this->website_id));
        $todaycondition["website_id"] = $this->website_id;
		$todaycondition["reg_time"] = [
            [
                ">",
                strtotime(date("Y-m-d", time()))
            ],
            [
                "<",
                strtotime(date("Y-m-d", time()))+3600*24
            ]
        ];
        $user_today_num = $user->getMemberCount($todaycondition);
        $month_end = date('Y-m-d', strtotime(date("Y-m-d"),time()));
        $month_begin = date('Y-m-d', strtotime("$month_end -1 month"));
        $condition["reg_time"] = [
            [
                ">",
                strtotime($month_begin)
            ],
            [
                "<",
                strtotime($month_end)
            ]
        ];

        $condition["website_id"] = $this->website_id;
        $user_month_num = $user->getMemberCount($condition);
        $this->assign("user_count_num", $user_count_num);
        $this->assign("user_today_num", $user_today_num);
        $this->assign("user_month_num", $user_month_num);
        $this->assign("start_date", $month_begin);
        $this->assign("end_date", $month_end);
        return view($this->style . 'Statistics/userStat');
    }

    /**
     * 会员统计
     */
    public function getMemberMonthCount()
    {
        $start_date = $_POST["start_date"] ? $_POST['start_date'] : '';
        $end_date = $_POST["end_date"]? $_POST['end_date'] : '';
        $member = new Member();
        $member_list = $member->getMemberMonthCount($start_date, $end_date);
        $date_string = array();
        $user_num = array();
        foreach ($member_list as $k => $v) {
            $date_string[] = $k;
            $user_num[] = $v;
        }
        $array = [
            $date_string,
            $user_num
        ];
        // 或区域一段时间内的用户数量
        return $array;
    }

    /*
     * 经营概况
     */
    public function businessProfile(){
        if (request()->isAjax()) {
            $start_date = ! empty($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = ! empty($_POST['end_date']) ?  $_POST['end_date'] : '';
            $shop_type = ! empty($_POST['shop_type']) ? $_POST['shop_type'] : 0;
            if($start_date){
                $begintime = strtotime($start_date);
            }
            if($end_date){
                $endtime = strtotime($end_date)+86399;
            }
            $between=5;
            if($endtime>$begintime){
                $between = ceil(($endtime-$begintime)/7/24/3600);
            }
            $shop = new Order();
            if ($begintime != "") {
                $condition["create_time"][] = [
                    ">",
                    $begintime
                ];
            }
            if ($endtime != "") {
                $condition["create_time"][] = [
                    "<",
                    $endtime
                ];
            }
            if ($shop_type == 1) {
                $condition["shop_id"] = 0;
            } elseif ($shop_type == 2) {
                $condition["shop_id"] = ['>', 0];
            }
            $condition["website_id"] = $this->website_id;
            $condition['is_deleted'] = 0;
            $list = $shop->getBusinessProfileList($begintime,$endtime,$condition);
            $date_string = array();
            $allOrderStat = array();
            $payOrderStat = array();
            $returnOrderStat = array();
            $turnover = array();
            $money = array();
            $sum = array();
            foreach ($list as $k => $v) {
                $date_string[] = $k;
                $allOrderStat['name'] = '订单量';
                $allOrderStat['data'][] = $v['count'];
                $payOrderStat['name'] = '付款订单';
                $payOrderStat['data'][] = $v['paycount'];
                $returnOrderStat['name'] = '售后订单';
                $returnOrderStat['data'][] = $v['returncount'];
                $turnover['name'] = '交易额';
                $turnover['data'][] = $v['sum'];
            }
            $sum[] = $allOrderStat;
            $sum[] = $payOrderStat;
            $sum[] = $returnOrderStat;
            $money[] = $turnover;
            $array = [
                $date_string,
                $sum,
                $money,
                $between
            ];
            return $array;
        } else {
            $month_end = date('Y-m-d', strtotime(date("Y-m-d"),time()));
            $month_begin = date('Y-m-d', strtotime("$month_end -1 month"));
            $this->assign("start_date", $month_begin);
            $this->assign("end_date", $month_end);
            return view($this->style . 'Statistics/businessProfile');
        }
    }
    /*
   * 获取订单数量
   */
    public function getOrderAccount(){
        $order= new Order();
        $start_date = ! empty($_POST['start_date']) ? $_POST['start_date'] : '';
        $end_date = ! empty($_POST['end_date']) ?  $_POST['end_date'] : '';
        $shop_type = ! empty($_POST['shop_type']) ? $_POST['shop_type'] : 0;
        $begintime = strtotime($start_date);
        $endtime = strtotime($end_date)+86399;
        if ($begintime != "") {
            $condition["create_time"][] = [
                ">",
                $begintime
            ];
        }
        if ($endtime != "") {
            $condition["create_time"][] = [
                "<",
                $endtime
            ];
        }
        if ($shop_type == 1) {
            $condition["shop_id"] = 0;
        } elseif ($shop_type == 2) {
            $condition["shop_id"] = ['>', 0];
        }
        $condition["website_id"] = $this->website_id;
        $condition['is_deleted'] = 0;
        //订单量
        $sale_num = $order->getShopSaleNumSum($condition);
        //付款订单
        $condition1=$condition;
        $condition1['order_status'] = [['>',0],['<',5]];
        $order_pay = $order->getOrderCount($condition1);
        //销售额
        $sale_money = $order->getShopSaleSum($condition1);
        //售后订单
        $condition2=$condition;
        $condition2['order_status'] = ['<',0];
        $order_return = $order->getOrderCount($condition2);
        $result = array(
            "sale_money"=>$sale_money,
            "sale_num"=>$sale_num,
            "order_return"=>$order_return,
            "order_pay"=>$order_pay
        );
        return $result;
    }
    /*
     * 商品分析
     */
    public function goodsAnalysis(){
        if (request()->isAjax()) {
            $pageindex = isset($_POST['page_index']) ? $_POST['page_index'] : 1;
            $start_date = ! empty($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = ! empty($_POST['end_date']) ? $_POST['end_date'] : '';
            $shop_type = ! empty($_POST['shop_type']) ? $_POST['shop_type'] : 0;
            $sort = ! empty($_POST['sort']) ? $_POST['sort'] : 0;
            $goods_name = ! empty($_POST['goods_name']) ? $_POST['goods_name'] : '';
            $condition = array();
            $shop_id=-1;
            if ($shop_type==1) {
                $condition["no.shop_id"] = 0;
                $shop_id=0;
            }elseif($shop_type==2){
                $condition["no.shop_id"] = ['>',0];
                $shop_id=['>',0];
            }
            if ($start_date != "") {
                $condition["no.create_time"][] = [
                    ">",
                    strtotime($start_date)
                ];
            }
            if ($end_date != "") {
                $condition["no.create_time"][] = [
                    "<",
                    strtotime($end_date)
                ];
            }
            if($goods_name){
                $condition["nog.goods_name"] = ["like","%" . $goods_name . "%"];
            }
            if($sort==1){
                $order = 'sum(nog.num) desc';
            }elseif($sort==2){
                // $order = 'sum(nog.real_money*nog.num) desc';
                $order = 'sumMoney desc';
            }
            $condition["no.website_id"] = $this->website_id;
            $condition["no.is_deleted"] = 0;
            $condition["no.order_status"] = [['>','0'],['<','5']];
            $orderGoods = new VslOrderGoodsViewModel();

            $list = $orderGoods->getOrderGoodsRankList($pageindex, PAGESIZE, $condition, $order);
            $list['account'] = ['1','2','4'];
            $money=0.00;
            $count=0;
            $goods = new Goods();
            if($shop_id==0 || $shop_id!=-1){
                $goodsCount=$goods->getGoodsCount(['website_id'=>$this->website_id,'shop_id'=>$shop_id]);
            }
            if($shop_id==-1){
                $goodsCount=$goods->getGoodsCount(['website_id'=>$this->website_id]);
            }
            foreach($list['data']['data'] as $v){
                $money += $v['sumMoney'];
                $count +=$v['sumCount'];
            }
            unset($v);
            $list['account'] = [$money,$count,$goodsCount];
            return $list;
        } else {
            $shop_id = isset($_GET["shop_id"]) ? $_GET["shop_id"] : 0;
            $this->assign("shop_id", $shop_id);
            return view($this->style . "Statistics/goodsAnalysis");
        }
    }

    /*
     * 订单分布
     */
    public function orderDistribution(){
        if (request()->isAjax()) {
            $data = array();
            $start_date = ! empty($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = ! empty($_POST['end_date']) ? $_POST['end_date'] : '';
            $shop_type = ! empty($_POST['shop_type']) ? $_POST['shop_type'] : 0;
            $province = ! empty($_POST['province']) ? $_POST['province'] : '';
            $city = ! empty($_POST['city']) ? $_POST['city'] : '';
            $condition = array();
            $condition1 = array();
            $condition2 = array();
            if ($shop_type==1) {
                $condition["no.shop_id"] = 0;
                $condition1['shop_id'] = 0;
                $condition2['shop_id'] = 0;
            }elseif($shop_type==2){
                $condition["no.shop_id"] = ['>',0];
                $condition1['shop_id'] = ['>',0];
                $condition2['shop_id'] = ['>',0];
            }
            if ($start_date != "") {
                $condition["no.create_time"][] = [
                    ">",
                    strtotime($start_date)
                ];
                $condition1["create_time"][] = [
                    ">",
                    strtotime($start_date)
                ];
                $condition2["create_time"][] = [
                    ">",
                    strtotime($start_date)
                ];
            }
            if ($end_date != "") {
                $condition["no.create_time"][] = [
                    "<",
                    strtotime($end_date)
                ];
                $condition1["create_time"][] = [
                    "<",
                    strtotime($end_date)
                ];
                $condition2["create_time"][] = [
                    "<",
                    strtotime($end_date)
                ];
            }
            $group = 'no.receiver_province';
            $field = 'sp.province_name as area,count(no.order_id) as count,sc.city_id,sp.province_id';
            $condition['no.receiver_province'] = ['>',0];
            $condition1['receiver_province'] = ['>',0];
            $condition['no.receiver_city'] = ['>',0];
            $condition1['receiver_city'] = ['>',0];
            if($province>0){
                $condition['no.receiver_province'] = $province;
                $condition1['receiver_province'] = $province;
                $group = 'no.receiver_city';
                $field = 'sc.city_name as area,count(no.order_id) as count,sc.city_id,sp.province_id';
            }
            if($city>0){
                $condition['no.receiver_city'] = $city;
                $condition1['receiver_city'] = $city;
                $group = 'no.receiver_district';
                $field = 'sd.district_name as area,count(no.order_id) as count,sc.city_id,sp.province_id';
            }
            $condition["no.website_id"] = $this->website_id;
            $condition["no.is_deleted"] = 0;
            $condition["no.order_status"] = ['neq',5];
            $condition1["website_id"] = $this->website_id;
            $condition1["is_deleted"] = 0;
            $condition1["order_status"] = ['neq',5];
            $condition2["website_id"] = $this->website_id;
            $condition2["is_deleted"] = 0;
            $condition2["order_status"] = ['neq',5];
            $order_goods = new VslOrderGoodsModel();
            $order_goods_id = $order_goods->Query(['website_id'=>$this->website_id,'goods_exchange_type'=>['neq',0]],'order_id');
            $order = new VslOrderModel();
            if($order_goods_id){
                $order_ids = $order->Query(['website_id'=>$this->website_id,'is_deleted'=>0,'order_status'=>['neq',5]],'order_id');
                $order_id = array_intersect($order_ids,$order_goods_id);
                if($order_id){
                    $order_real_id = implode(',',$order_id);
                    $condition["no.order_id"] = ['not in',$order_real_id];
                    $condition1["order_id"] = ['not in',$order_real_id];
                    $condition2["order_id"] = ['not in',$order_real_id];
                }
            }
            $list['area_info'] = $order->getOrderDistributionList(1, 0, $condition,'',$group,$field);

            $order_service = new Order();
            //订单来源
            $condition1['order_from'] = 1;// 微信
            $data['order_from1'] = $order_service->getOrderCount($condition1);
            $data['order_from1_member'] = $order_service->getMemberOrderCount($condition1);
            $data['order_from1_money'] = $order_service->getOrderMoneySum($condition1,'order_money');
            $condition1['order_from'] = 2;// 手机
            $data['order_from2'] = $order_service->getOrderCount($condition1);
            $data['order_from2_member'] = $order_service->getMemberOrderCount($condition1);
            $data['order_from2_money'] = $order_service->getOrderMoneySum($condition1,'order_money');
            $condition1['order_from'] = 3;//pc
            $data['order_from3'] = $order_service->getOrderCount($condition1);
            $data['order_from3_member'] = $order_service->getMemberOrderCount($condition1);
            $data['order_from3_money'] = $order_service->getOrderMoneySum($condition1,'order_money');
            $condition1['order_from'] = 4;// ios
            $data['order_from4'] = $order_service->getOrderCount($condition1);
            $data['order_from4_member'] = $order_service->getMemberOrderCount($condition1);
            $data['order_from4_money'] = $order_service->getOrderMoneySum($condition1,'order_money');
            $condition1['order_from'] = 5;// // Android
            $data['order_from5'] = $order_service->getOrderCount($condition1)+$data['order_from4'];
            $data['order_from5_member'] = $order_service->getMemberOrderCount($condition1)+$data['order_from4_member'];
            $data['order_from5_money'] = $order_service->getOrderMoneySum($condition1,'order_money')+$data['order_from4_money'];
            $condition1['order_from'] = 6;// // 小程序
            $data['order_from6'] = $order_service->getOrderCount($condition1);
            $data['order_from6_member'] = $order_service->getMemberOrderCount($condition1);
            $data['order_from6_money'] = $order_service->getOrderMoneySum($condition1,'order_money');
            $condition1['order_from'] = 7;// // 店员端
            $data['order_from7'] = $order_service->getOrderCount($condition1);
            $data['order_from7_member'] = $order_service->getMemberOrderCount($condition1);
            $data['order_from7_money'] = $order_service->getOrderMoneySum($condition1,'order_money');
            if($list['area_info']['data']){
                foreach($list['area_info']['data'] as $val){
                    $data['area_info']['areas'][] = $val['area'];
                    if($city>0){
                        $condition2['receiver_city'] = $val['city_id'];
                    }else{
                        $condition2['receiver_province'] = $val['province_id'];
                    }
                    $data['area_info']['order_from_member'][] = $order_service->getMemberOrderCount($condition2);
                    $data['area_info']['order_from_money'][]= $order_service->getOrderMoneySum($condition2,'order_money');
                    $data['area_info']['counts'][] = $val['count'];
                }
                unset($val);
            }
            return $data;
        } else {
            return view($this->style . "Statistics/orderAnalysis");
        }
    }
    /*
     * 订单分布概况
     */
    public function orderProfile(){
        if (request()->isAjax()) {
            $month_end = date('Y-m-d', strtotime(date("Y-m-d"),time()));
            $month_begin = date('Y-m-d', strtotime("$month_end -1 month"));
            $start_date = ! empty($_POST['start_date']) ? $_POST['start_date'] : $month_begin;
            $end_date = ! empty($_POST['end_date']) ?  $_POST['end_date'] : $month_end;
            $shop_type = ! empty($_POST['shop_type']) ? $_POST['shop_type'] : 0;
            $province = ! empty($_POST['province']) ? $_POST['province'] : '';
            $city = ! empty($_POST['city']) ? $_POST['city'] : '';
            if($province>0){
                $condition['receiver_province'] = $province;
            }
            if($city>0){
                $condition['receiver_city'] = $city;
            }
            $begintime = strtotime($start_date);
            $endtime = strtotime($end_date)+24*3600;
            $between=5;
            if($endtime>$begintime){
                $between = ceil(($endtime-$begintime)/7/24/3600);
            }
            if ($begintime != "") {
                $condition["create_time"][] = [
                    ">",
                    $begintime
                ];
            }
            if ($endtime != "") {
                $condition["create_time"][] = [
                    "<",
                    $endtime
                ];
            }
            if ($shop_type == 1) {
                $condition["shop_id"] = 0;
            } elseif ($shop_type == 2) {
                $condition["shop_id"] = ['>', 0];
            }
            $condition["website_id"] = $this->website_id;
            $condition['is_deleted'] = 0;
            $condition["order_status"] = ['neq',5];
            $shop = new Order();
            $list = $shop->getBusinessProfileList($begintime,$endtime,$condition);
            $date_string = array();
            $allOrderStat = array();
            $payOrderStat = array();
            $returnOrderStat = array();
            $turnover = array();
            $money = array();
            $sum = array();
            foreach ($list as $k => $v) {
                $date_string[] = $k;
                $allOrderStat['name'] = '订单量';
                $allOrderStat['data'][] = $v['count'];
                $payOrderStat['name'] = '付款订单';
                $payOrderStat['data'][] = $v['paycount'];
                $returnOrderStat['name'] = '售后订单';
                $returnOrderStat['data'][] = $v['returncount'];
                $turnover['name'] = '交易额';
                $turnover['data'][] = $v['sum'];
            }
            $sum[] = $allOrderStat;
            $sum[] = $payOrderStat;
            $sum[] = $returnOrderStat;
            $money[] = $turnover;
            $array = [
                $date_string,
                $sum,
                $money,
                $between
            ];
            return $array;
        }
    }

    /**
     * 基于订单统计积分情况
     *
     * @return Ambigous <multitype:number , multitype:number unknown >
     */
    public function balance() {
        if (request()->isAjax()) {
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $order_no = request()->post('order_no', '');
            $order_status = request()->post('order_status', '');
            $buyer_id = request()->post('buyer_id', '');

            $start_finish_date = request()->post('start_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_finish_date'));
            $end_finish_date = request()->post('end_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_finish_date'));

            if (!empty($order_no)) {
                $condition['order_no'] = array(
                    'like',
                    '%' . $order_no . '%'
                );
            }

            if (!empty($buyer_id)) {
                $condition['buyer_id'] = $buyer_id;
            }

            if (!empty($order_status)) {
                $condition['order_status'] = $order_status;
            }else{
                $condition['order_status'] = array('in', [3, 4]);
            }

            if ($start_finish_date) {
                $condition['sign_time'][] = ['>=', $start_finish_date];
            }
            if ($end_finish_date) {
                $condition['sign_time'][] = ['<=', $end_finish_date];
            }

            $order_commission = new VslOrderDistributorCommissionModel();
            $order_commission_ids =  $order_commission->getQuery(['website_id'=>1, 'pay_status'=> 1], 'order_id');
            $order_commission_ids = objToArr($order_commission_ids);
            $a_orderIds = dealArray(array_column($order_commission_ids, 'order_id'), false, true);
            //获取团队佣金
            $bonusLogModel = new VslOrderBonusLogModel();
            $order_bonus_ids = $bonusLogModel->getQuery(['website_id'=>1], 'order_id');
            $order_bonus_ids = objToArr($order_bonus_ids);
            $b_orderIds = dealArray(array_column($order_bonus_ids, 'order_id'), false, true);
            $order_ids = array_merge($a_orderIds, $b_orderIds);
            $condition['order_id'] = array('in', $order_ids);

            $order_service = new OrderService();
            $list = $order_service->getCommissionOrderList($page_index, $page_size, $condition, 'order_status asc, create_time desc');

            $total = 0.00;
            $sum_complete = 0.00;
            $sum_wait = 0.00;
            $order_model = new VslOrderModel();
            $order_lists = $order_model->getQuery($condition, 'order_id');
            if($order_lists){
                $order_ids = [];
                foreach ($order_lists as $item){
                    $order_ids[] = $item['order_id'];
                }
                $commission =  $order_commission->getSum(['website_id'=>1, 'pay_status'=> 1, 'order_id'=>array('in', $order_ids)], 'commission');
                $team_bonus = $bonusLogModel->getSum(['website_id'=>1, 'order_id'=>array('in', $order_ids)], 'team_bonus');
                $total = $commission + $team_bonus;

                //未发放
                $order_amount =  $order_commission->getSum(['website_id'=>1, 'pay_status'=> 1, 'cal_status' => 0, 'order_id'=> array('in', $order_ids)], 'commission');

                //获取团队佣金
                $bonusLogModel = new VslOrderBonusLogModel();
                $bonus_amount = $bonusLogModel->getSum(['website_id'=>1, 'order_id'=> array('in', $order_ids),'team_return_status'=>0, 'team_cal_status' => 0], 'team_bonus');
                $sum_wait = $order_amount + $bonus_amount;

                $sum_complete = $total - $sum_wait;
            }

            $list['account'] = [$total, $sum_complete, $sum_wait];
            return $list;

        } else {
            return view($this->style . "Statistics/memberBalance");
        }
    }

    /**
     * 战略经销统计
     *
     * @return \data\model\unknown|mixed|\think\response\View
     */
    public function zhlue() {
        if (request()->isAjax()) {
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $uid = request()->post('uid', '');
            $start_finish_date = request()->post('start_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_finish_date'));
            $end_finish_date = request()->post('end_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_finish_date'));

            if (!empty($uid)) {
                $condition['nm.uid'] = $uid;
            }
            $condition['nm.distributor_level_id']= 5;
            $userModel = new VslMemberModel();
            $list = $userModel->getViewList($page_index, $page_size, $condition, 'nm.uid asc');
            $list = objToArr($list);
            $distributor = new DistributorService();
            foreach ($list['data'] as &$v){
                $v['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
                //团队业绩
                $v['total_amount'] = $distributor->getTotalAmount($v['uid'], $start_finish_date, $end_finish_date);
                //平级业绩
                $v['level_amount'] = $distributor->getLevelAmount($v['uid'], $start_finish_date, $end_finish_date);
            }
            return $list;

        } else {
            return view($this->style . "Statistics/zhlue");
        }
    }

    /**
     * 获取平级详情
     *
     * @return array|\data\model\unknown
     */
    public function levelDetail(){

        if (request()->isAjax()) {
            $uid = request()->post('member_id');
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $member_id = request()->post('uid', '');
            $start_finish_date = request()->post('start_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_finish_date'));
            $end_finish_date = request()->post('end_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_finish_date'));
            $distributor = new DistributorService();
            $uids = $distributor->sort($uid);
            $lists = $distributor->sort_data($uids, 'uid', 'referee_id', 'children', $uid);
            $res = $distributor->rec_list_level($uid, $lists);
            $list['data'] = [];
            if (count($res)) {
                $condition['nm.uid'] = array('in', $res);
                $condition['nm.distributor_level_id']= 5;
                if (!empty($member_id) && in_array($member_id, $res)) {
                    $condition['nm.uid'] = $member_id;
                }

                $userModel = new VslMemberModel();
                $list = $userModel->getViewList($page_index, $page_size, $condition, 'nm.uid asc');
                $list = objToArr($list);
                foreach ($list['data'] as &$v){
                    $v['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
                    //团队业绩
                    $v['total_amount'] = $distributor->getTotalAmount($v['uid'], $start_finish_date, $end_finish_date);
                }
            }
            return $list;
        }else{
            $uid = request()->get('member_id');
            $this->assign('member_id', $uid);
            return view($this->style . 'Statistics/levelDetail');
        }
    }

    /**
     * 获取团队详情
     *
     * @return array|\data\model\unknown
     */
    public function teamDetail(){

        if (request()->isAjax()) {
            $uid = request()->post('member_id');
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $order_no = request()->post('order_no', '');
            $buyer_id = request()->post('buyer_id', '');
            $start_finish_date = request()->post('start_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_finish_date'));
            $end_finish_date = request()->post('end_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_finish_date'));
            $distributor = new DistributorService();
            $uids = $distributor->sort($uid);
            $lists = $distributor->sort_data($uids, 'uid', 'referee_id', 'children', $uid);
            $res = $distributor->rec_list_member($uid, $lists);
            $list['data'] = [];
            if (count($res)) {
                $ids = [];
                foreach ($res as $i) {
                    $ids[] = $i['uid'];
                }
                $condition['buyer_id'] = array('in', $ids);
                $condition['order_status'] = array('in', [3, 4]);
                if (!empty($order_no)) {
                    $condition['order_no'] = array(
                        'like',
                        '%' . $order_no . '%'
                    );
                }

                if (!empty($buyer_id) && in_array($buyer_id, $ids)) {
                    $condition['buyer_id'] = $buyer_id;
                }
                if ($start_finish_date) {
                    $condition['sign_time'][] = ['>=', $start_finish_date];
                }
                if ($end_finish_date) {
                    $condition['sign_time'][] = ['<=', $end_finish_date];
                }
                $order_model = new VslOrderModel();
                $list = $order_model->getViewList4($page_index, $page_size, $condition, 'nm.order_id desc');
                foreach ($list['data'] as &$v){
                    $v['sign_time'] = date('Y-m-d H:i:s', $v['sign_time']);
                    $v['user_info'] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
                }
            }

            return $list;
        }else{
            $uid = request()->get('member_id');
            $this->assign('member_id', $uid);
            return view($this->style . 'Statistics/teamDetail');
        }
    }

    /**
     * 数据excel导出
     */
    public function teamDetailExcel()
    {
        $xlsName = "战略经销团队详情列表";
        $xlsCell = [
            0=>['order_no','订单号'],
            1=>['uid','会员ID'],
            2=>['user_info','用户信息'],
            3=>['sign_time','收货时间'],
            4=>['order_money','订单金额']

        ];
        $uid = request()->post('member_id');
        $order_no = request()->post('order_no', '');
        $buyer_id = request()->post('buyer_id', '');
        $start_finish_date = request()->post('start_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_finish_date'));
        $end_finish_date = request()->post('end_finish_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_finish_date'));
        $distributor = new DistributorService();
        $uids = $distributor->sort($uid);
        $lists = $distributor->sort_data($uids, 'uid', 'referee_id', 'children', $uid);
        $res = $distributor->rec_list_member($uid, $lists);
        $list['data'] = [];
        $condition = [];
        if (count($res)) {
            $ids = [];
            foreach ($res as $i) {
                $ids[] = $i['uid'];
            }
            $condition['buyer_id'] = array('in', $ids);
            $condition['order_status'] = array('in', [3, 4]);
            if (!empty($order_no)) {
                $condition['order_no'] = array(
                    'like',
                    '%' . $order_no . '%'
                );
            }

            if (!empty($buyer_id) && in_array($buyer_id, $ids)) {
                $condition['buyer_id'] = $buyer_id;
            }
            if ($start_finish_date) {
                $condition['sign_time'][] = ['>=', $start_finish_date];
            }
            if ($end_finish_date) {
                $condition['sign_time'][] = ['<=', $end_finish_date];
            }
        }

        // edit for 2020/04/26 导出操作移到到计划任务统一执行
        $insert_data = array(
            'type' => 17,
            'status' => 0,
            'exname' => $xlsName,
            'website_id' => $this->website_id,
            'addtime' => time(),
            'ids' => serialize($xlsCell),
            'conditions' => serialize($condition),
        );
        $excels_export = new ExcelsExport();
        $res = $excels_export->insertData($insert_data);
        return $res;
    }


//    /**
//     * 获取我的团队总业绩
//     *
//     * @param $uid
//     * @return int
//     */
//    public function getTotalAmount($uid, $start_finish_date = null, $end_finish_date = null){
//        $uids = $this->sort($uid);
//        $lists = $this->sort_data($uids,'uid', 'referee_id', 'children', $uid);
//        $res = $this->rec_list_member($uid, $lists);
//        $amount = 0;
//        if(count($res)){
//            $ids = [];
//            foreach ($res as $i){
//                $ids[] = $i['uid'];
//            }
//            $condition['buyer_id'] = array('in', $ids);
//            $condition['order_status'] = array('in', [3, 4]);
//
//            if ($start_finish_date) {
//                $condition['finish_time'][] = ['>=', $start_finish_date];
//            }
//            if ($end_finish_date) {
//                $condition['finish_time'][] = ['<=', $end_finish_date];
//            }
//            $order_model = new VslOrderModel();
//            $amount = $order_model->getSum($condition, 'order_money');
////            foreach ($order_lists as $value){
////                $amount += $value['order_money'];
////            }
//        }
//        return sprintf("%01.2f", $amount);
//    }
//
//    /**
//     *  获取我的平级业绩
//     *
//     * @param $uid
//     * @return int
//     */
//    public function getLevelAmount($uid, $start_finish_date, $end_finish_date){
//        $uids = $this->sort($uid);
//        $lists = $this->sort_data($uids,'uid', 'referee_id', 'children', $uid);
//        $res = $this->rec_list_level($uid, $lists);
//        $amount = 0;
//        if(count($res)){
//            foreach ($res as $level_uid){
//                $amount += $this->getTotalAmount($level_uid, $start_finish_date, $end_finish_date);
//            }
//        }
//        return sprintf("%01.2f", $amount);
//    }
//
//    /**
//     * 获取代理下的所有用户
//     *
//     * @param $top_id
//     * @return array
//     */
//    public function sort($top_id)
//    {
//        $memberModel = new VslMemberModel();
//        $lists = $memberModel->getQuery(['referee_id'=>array('>', 0)], 'uid,referee_id,distributor_level_id');
//        $users = [];
//        foreach ($lists as $item){
//            $users[] = [
//                'uid'=> $item['uid'],
//                'referee_id'=> $item['referee_id'],
//                'distributor_level_id'=> $item['distributor_level_id']
//            ];
//        }
//        $arr = self::sort1($top_id, $users);
//        return $arr;
//    }
//
//    /**
//     *
//     *
//     * @param $id
//     * @param $data
//     * @return array
//     */
//    public static function sort1($id, $data)
//    {
//        $arr = [];
//        foreach($data as $k => $v){
//            //从小到大 排列
//            if($v['referee_id'] == $id){
//                $arr[] = $v;
//                $arr = array_merge(self::sort1($v['uid'], $data), $arr);
//            }
//        }
//        return $arr;
//    }
//
//    /**
//     * 过滤同个等级多个战略等级的情况
//     *
//     * @param $pid
//     * @param $from
//     * @return array
//     *
//     */
//    public function rec_list_member($pid, $from)
//    {
//        $arr = [];
//        foreach($from as $key=> $item) {
//            if($item['distributor_level_id'] == 5 && $item['uid'] != $pid) {
//                continue;
//            }
//            if(!isset($item['children'])){
//                $arr[] = $item;
//            }
//            if(isset($item['children'])){
//                $children = $item['children'];
//                unset($item['children']);
//                $arr[] = $item;
//                $arr = array_merge($arr, $this->rec_list_member($pid, $children));
//            }
//        }
//        return $arr;
//    }
//
//    /**
//     * 获取同个等级下多个战略
//     *
//     * @param $pid
//     * @param $from
//     * @return array
//     *
//     */
//    public function rec_list_level($pid, $from)
//    {
//        $arr = [];
//        foreach($from as $key=> $item) {
//            if($item['distributor_level_id'] == 5 && $item['uid'] != $pid) {
//                $arr[] = $item['uid'];
//                continue;
//            }
//            if(isset($item['children'])){
//                $arr = array_merge($arr, $this->rec_list_level($pid, $item['children']));
//            }
//        }
//        return $arr;
//    }
//
//    /**
//     * 生成树结构
//     *
//     * @param $data
//     * @param string $pk
//     * @param string $pid
//     * @param string $child
//     * @param int $root
//     * @return array|bool
//     */
//    public function sort_data($data, $pk = 'id', $pid = 'pid', $child = 'children', $root = 0)
//    {
//        // 创建Tree
//        $tree = [];
//        if (!is_array($data)) {
//            return false;
//        }
//
//        //创建基于主键的数组引用
//        $refer = [];
//        foreach ($data as $key => $value_data) {
//            $refer[$value_data[$pk]] = &$data[$key];
//        }
//        foreach ($data as $key => $value_data) {
//            // 判断是否存在parent
//            $parentId = $value_data[$pid];
//            if ($root == $parentId) {
//                $tree[] = &$data[$key];
//            } else {
//                if (isset($refer[$parentId])) {
//                    $parent = &$refer[$parentId];
//                    $parent[$child][] = &$data[$key];
//                }
//            }
//        }
//
//        return $tree;
//    }
}
