<?php

namespace app\wapbiz\controller;

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
     *销售统计
     */
    public function getSaleStatistics(){
        $start_date = strtotime(date("Y-m-d"),time());//date('Y-m-d 00:00:00', time());
        $end_date = strtotime(date('Y-m-d',strtotime('+1 day')));//date('Y-m-d 00:00:00', strtotime('this day + 1 day'));
        $start_date1 = strtotime(date("Y-m-d"),time()-24*3600);//date('Y-m-d 00:00:00', time());
        $end_date1 = strtotime(date('Y-m-d',time()));//date('Y-m-d 00:00:00', strtotime('this day + 1 day'));
        $order= new OrderService();
        //今日和昨日销售额
        $condition1 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'order_status'=>[['>',0],['<',5]]];
        $condition2 = ['create_time' => [[">",$start_date1],["<",$end_date1]],'website_id'=>$this->website_id,'order_status'=>[['>',0],['<',5]]];
        if($this->port == 'admin'){
            $condition1['shop_id'] = $this->instance_id;
            $condition2['shop_id'] = $this->instance_id;
        }
        if($this->port == 'supplier'){
            $str = "FIND_IN_SET(" . $this->supplier_id . ",supplier_id)";
            $condition1[] = [
                [
                    "EXP",
                    $str
                ]
            ];
            $condition2[] = [
                [
                    "EXP",
                    $str
                ]
            ];
        }
        $sale_money_day1 = $order->getShopSaleSum($condition1);
        $sale_money_day2 = $order->getShopSaleSum($condition2);
        //今日和昨日支付订单量
        $sale_num_day1 = $order->getShopSaleNumSum($condition1);
        $sale_num_day2 = $order->getShopSaleNumSum($condition2);
        //今日和昨日支付人数
        $sale_member_day1 = $order->getShopSaleMemberNumSum($condition1);
        $sale_member_day2 = $order->getShopSaleMemberNumSum($condition2);
        //今日和昨日下单件数
        $sale_goods_num1 = $order->getShopSaleGoodsNum($condition1);
        $sale_goods_num2 = $order->getShopSaleGoodsNum($condition2);
        //今日和昨日实付金额
        $sale_pay_day1 = $order->getShopOrderPaySum($condition1);
        $sale_pay_day2 = $order->getShopOrderPaySum($condition2);
        $result = array(
            "sale_money_day1"=>$sale_money_day1,
            "sale_num_day1"=>$sale_num_day1,
            "sale_member_day1"=>$sale_member_day1,
            "sale_money_day2"=>$sale_money_day2,
            "sale_num_day2"=>$sale_num_day2,
            "sale_member_day2"=>$sale_member_day2,
            "sale_goods_num1"=>$sale_goods_num1,
            "sale_goods_num2"=>$sale_goods_num2,
            "sale_pay_day1"=>$sale_pay_day1,
            "sale_pay_day2"=>$sale_pay_day2,
        );
        return json(['code' => 1, 'message' => '获取成功', 'data' => $result]);
    }
}
