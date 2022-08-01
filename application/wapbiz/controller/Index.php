<?php

namespace app\wapbiz\controller;

use data\service\Order as OrderService;
use think\helper\Time;
use data\service\Article;
use data\model\VslExcelsModel;
use data\model\UserModel;
use data\service\Member;

/**
 * 后台主界面
 */
class Index extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }


    public function tipCount()
    {
        //订单统计数据
        $data = [];
        $order_data = $this->getOrderCount();
        $data['daifahuo'] = $order_data['daifahuo'];
        $data['tuikuanzhong'] = $order_data['tuikuanzhong'];
        //未读站内信
        $message = new Article();
        $unread = $message->getMessageStatus(0);
        $data['unread'] = $unread;
        $uncms = $message->getCmsStatus();
        $data['uncms'] = $uncms;
        $data['total_tips'] = $data['daifahuo'] + $data['tuikuanzhong'] + $data['unread'] + $data['uncms'];
        //获取导出任务数量
        $excelsModel = new VslExcelsModel();
        $excelslist = $excelsModel->Query(['is_view' => 1, 'website_id' => $this->website_id], 'id'); //查询id为mid的用户的直接下级
        $data['total_tips2'] = count($excelslist);
        return $data;
    }

    /**
     * 销售统计
     */
    public function getIndexCount()
    {
        $type = (int)request()->post('type', 1);
        if($type == 2){//昨日
            $start_date = strtotime(date("Y-m-d", time() - 24 * 3600)); //date('Y-m-d 00:00:00', time());
            $end_date = strtotime(date('Y-m-d', time())); //date('Y-m-d 00:00:00', strtotime('this day + 1 day'));
        }elseif($type == 3){//七天
            list($start, $end) = Time::dayToNow(6, true);
            $dateStart = date("Y-m-d H:i:s", $start);
            $dateEnd = date("Y-m-d H:i:s", $end);
            $start_date = strtotime($dateStart);
            $end_date = strtotime($dateEnd);
        }else{
            $start_date = strtotime(date("Y-m-d", time())); //date('Y-m-d 00:00:00', time());
            $end_date = strtotime(date('Y-m-d', strtotime('+1 day'))); //date('Y-m-d 00:00:00', strtotime('this day + 1 day'));
        }
        $order = new OrderService();
        //销售额
        $condition = ['create_time' => [[">", $start_date], ["<", $end_date]], 'website_id' => $this->website_id, 'is_deleted' => 0, 'order_status' => [['>', 0], ['<', 5]]];
        if($this->instance_id > 0 &&  $this->port == 'admin'){
            $condition['shop_id'] = $this->instance_id;
        }
        if($this->port == 'supplier'){
            $sql1 = "instr(CONCAT( ',', supplier_id, ',' ), '," . $this->supplier_id . ",' ) ";
            $condition[] = ['exp',$sql1];
        }
        $saleMoney = round($order->getShopSaleSum($condition),2);
        //支付订单量
        $saleCount = $order->getShopSaleNumSum($condition);
        //待付款
        $condition['order_status'] = 0;
        $unPayCount = $order->getOrderCount($condition); 
        //待发货
        $condition['order_status'] = 1;
        $unDeliveryCount = $order->getOrderCount($condition); 
        //售后订单
        $condition['order_status'] = ['<',0];
        $returnCount = $order->getOrderCount($condition); 
        $result = array(
            "sale_money" => $saleMoney,
            "sale_count" => $saleCount,
            "unpay_count" => $unPayCount,
            "undelivery_count" => $unDeliveryCount,
            "return_count" => $returnCount,
        );
        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $result
        ]);;
    }

    
    /**
     * 获取工作台功能
     */
    public function getMenu()
    {
        $first_list = $this->user->getchildModuleQuery(0, $this->port);
        //以控制器为主键来筛选工作台功能
        if(!$first_list){
            return json(AjaxReturn(-1));
        }
        $newList = array_column($first_list,'module_id','controller');
        $module_id_array = explode(',', $this->user->getSessionModuleIdArray());
        $data = [
            'goods' => 0,
            'order' => 0,
            'after_order' => 0,
            'member' => 0,
            'financial' => 0,
            'statistics' => 0,
            'addonslist' => 0,
            'auth' => 0,
            'addons_status' => [
                'shop' => getAddons('shop', $this->website_id),
                'microshop' => getAddons('microshop', $this->website_id),
                'distribution' => getAddons('distribution', $this->website_id),
                'globalbonus' => getAddons('globalbonus', $this->website_id),
                'areabonus' => getAddons('areabonus', $this->website_id),
                'teambonus' => getAddons('teambonus', $this->website_id)
            ]
        ];
        foreach ($newList as $k => $v) {
            if($k == 'goods' || $k =='Goods'){
                $data['goods'] = (int)in_array($v, $module_id_array);
            }
            if($k == 'order' || $k =='Order'){
                $orderModule = $this->website->getModuleIdByModule($k, 'orderlist', $this->port);
                $afterOrderModule = $this->website->getModuleIdByModule($k, 'afterorderlist', $this->port);
                $data['order'] = $orderModule ? (int)in_array($orderModule['module_id'], $module_id_array) : 0;
                $data['after_order'] = $afterOrderModule ? (int)in_array($afterOrderModule['module_id'], $module_id_array) : 0;
            }
            if($k == 'member'){
                $data['member'] = (int)in_array($v, $module_id_array);
            }
            if($k == 'financial' || $k == 'Finance'){
                $data['financial'] = (int)in_array($v, $module_id_array);
                
            }
            if($k == 'promotion' || $k == 'addonslist'){
                $data['addonslist'] = (int)in_array($v, $module_id_array);
            }
            if($k == 'auth' || $k == 'Auth'){
                $data['auth'] = (int)in_array($v, $module_id_array);
                
            }
            if($k == 'Statistics' || $k == 'statistics'){
                $data['statistics'] = (int)in_array($v, $module_id_array);
            }
        }
        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data
        ]);
        //$addons_sign_module = Session::get('addons_sign_module') ?: [];
    }
    
    /**
     * 修改密码
     */
    public function setNewPassword()
    {
        $password = request()->post('password', '');
        $newPassword = request()->post('new_password', '');
        if(!$password || !$newPassword){
            return json(AjaxReturn(-1006));
        }
        if($password == $newPassword){
            return json(AjaxReturn(SAME_PWD));
        }
        $user = new UserModel();
        $user_info = $user->getInfo(['uid' => $this->uid, 'user_password' => md5($password)], 'uid');
        if(!$user_info){
            return json(AjaxReturn(PASSWORD_ERROR));
        }
        $member = new Member();
        $condition = ['uid' => $this->uid];
        $res = $member->updatePassword($newPassword, $condition);
        return json(AjaxReturn($res));
    }

}
