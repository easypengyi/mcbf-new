<?php
namespace data\service;
use addons\coupontype\model\VslCouponModel;
use addons\coupontype\server\Coupon as CouponServer;
use addons\distribution\service\Distributor;
use addons\giftvoucher\model\VslGiftVoucherRecordsModel;
use addons\giftvoucher\server\GiftVoucher as VoucherServer;
use addons\groupshopping\model\VslGroupShoppingRecordModel;
use addons\store\model\VslStoreModel;
use data\model\VslOrderModel;
use data\service\BaseService;
use data\model\VslExcelsModel;
use data\service\Order as OrderService;
use data\service\Member as MemberService;
use addons\distribution\service\Distributor as DistributorService;
use addons\shop\service\Shop as ShopService;
use addons\microshop\service\MicroShop as MicroShopService;
use data\model\UserModel;
use addons\electroncard\server\Electroncard as ElectroncardServer;
class ExcelsExport extends BaseService
{
    /**
     * 处理导出事件
     * id 待导出记录id
     */
    public function exportExcel($id='')
    {
        if(empty($id)){
            return;
        }
        $excelsModel = new VslExcelsModel();
        $info = $excelsModel->getInfo(['id'=>$id],'*');

        if($info['status'] == 1){
            return;
        }
        $info['exname'] = $this->get_all_py($info['exname']);
        $info['exname'] = $this->get_first_py($info['exname']);
        $type = $info['type'];
        switch($type){
            case '1': //订单表格 1
                $this->export_order($info);
                break;
            case '2': //会员表格 2
                $this->export_member($info);
                break;
            case '3': //分销商列表 3
                $this->export_commission($info);
                break;
            case '4': //余额提现表 4
                $this->export_bance($info);
                break;
            case '5': //余额流水 5
                $this->export_bance_detail($info);
                break;
            case '6': //积分流水 6
                $this->export_point($info);
                break;
            case '7': //店铺提现列表 7
                $this->export_shop_wal($info);
                break;
            case '8': //店铺账户列表 8
                $this->export_shop_acl($info);
                break;
            case '9': //佣金提现列表 9
                $this->export_commission_wal($info);
                break;
            case '10': //佣金流水 10
                $this->export_commission_detail($info);
                break;
            case '11': //分红流水 11
                $this->export_bonus_detail($info);
                break;
            case '12': //收益提现列表 12
                $this->export_profit_wal($info);
                break;
            case '13': //收益流水 13
                $this->export_profit_detail($info);
                break;
            case '14': //货款流水 14
                $this->export_proceed_list($info);
                break;
            case '15': //待发货订单数据 15
                $this->export_unDeliveryOrder_list($info);
                break;
            case '16': //电子卡密数据 15
                $this->export_electroncardData_list($info);
                break;
            case '17': //导出战略经销团队详情
                $this->export_teamDetail_list($info);
                break;
            case '18': //导出用户佣金明细
                $this->export_commissionLogDetail_list($info);
                break;
            case '19': //导出礼品劵列表
                $this->export_gift_list($info);
                break;
            case '20': //导出优惠劵列表
                $this->export_coupon_list($info);
                break;
        }
    }

    /**
     * 封装统一插入导出表
     * @param $insert_data
     * @return array
     */
    public function insertData($insert_data)
    {
        $excelsModel = new VslExcelsModel();
        $check = $excelsModel->getInfo(['type' => $insert_data['type'],'status' => ['<',1],'exname' => $insert_data['exname'],'ids' => $insert_data['ids'],'conditions' => $insert_data['conditions']]);
        if($check){
            return  ['code' => -1,'message' => '已存在该任务，请前往系统-计划任务中查看'];
        }else{
            $res = $excelsModel->save($insert_data);
            //加入rabbit导出队列
            $this->addExport($res);
            return  ['code' => 0,'message' => '新建导出任务成功，请前往系统-计划任务中查看'];
        }
    }

    /**
     * 添加导出队列
     */
    public function addExport($excel_id)
    {
        if(config('is_high_powered')) {
            $data['excel_id'] = $excel_id;
            $exchange_name = config('rabbit_export.exchange_name');
            $queue_name = config('rabbit_export.queue_name');
            $routing_key = config('rabbit_export.routing_key');
            $url = config('rabbit_interface_url.url');
            $url = $url.'/rabbitTask/listExport';
            $request_data = json_encode($data);
            $push_data = [
                "customType" => "export",//标识什么业务场景
                "data" => $request_data,//请求数据
                "requestMethod" => "POST",
                "timeOut" => 20,
                "url" => $url,
            ];
            $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
            $push_arr = json_decode($push_res, true);
            if($push_arr['code'] == 103){//未创建队列
                $create_res = createQueue($exchange_name, $queue_name, $routing_key);
                $create_arr = json_decode($create_res, true);
                if($create_arr['code'] != 200){
                    return ['code' => -1, 'message' => '未知错误：'.$create_arr];
                }elseif($create_arr['code'] == 200){
                    $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                }
            }
        }
    }
    //导出订单
    public function export_order($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $order_service = new OrderService();
//        $list = $order_service->getOrderList(1, 0, $condition, 'create_time desc');
        $list = $order_service->getOrderExcelsList(1, 1000, $condition, 'create_time desc',$info['supplier_id']);

        $data = [];
        $key = 0;
        foreach ($list["data"] as $k => $v) {
            if(!$v['order_item_list']){
                continue;
            }
            foreach ($v["order_item_list"] as $t => $m) {
                $data[$key]["shipping_money"] = $v['shipping_money'];
                $data[$key]["pay_money"] = $v['pay_money'];
                $data[$key]["first_money"] = $v['first_money'];
                $data[$key]["uid"] = $v['buyer_id'];
                $data[$key]["final_money"] = $v['final_money'];
                $data[$key]["order_status"] = $v['status_name'];
                $data[$key]["user_name"] = $v['user_name'];
                $data[$key]["user_tel"] = $v['user_tel']."\t";
                $data[$key]["receiver_name"] = $v['receiver_name'];
                $data[$key]["receiver_mobile"] = $v['receiver_mobile']."\t";
                if(empty($v['receiver_type'])) {
                    $data[$key]["receiver_address"] = $v['receiver_province_name'].$v['receiver_city_name'].$v['receiver_district_name'].$v['receiver_address'];
                }else{
                    $data[$key]["receiver_address"] = $v['receiver_address'];
                }
                $data[$key]["buyer_message"] = $v['buyer_message'];
                $data[$key]["create_time"] = $v["create_time"];
                $data[$key]["pay_time"] = $v["pay_time"];
                $data[$key]["consign_time"] = $v["consign_time"];
                $data[$key]["sign_time"] = $v["sign_time"];
                $data[$key]["finish_time"] = $v["finish_time"];
                $data[$key]["store_name"] = $v["store_name"];
                $data[$key]["assistant_name"] = $v["assistant_name"];
                $data[$key]["manjian_remark"] = $v["manjian_remark"];
                $data[$key]["pay_type_name"] = $v["pay_type_name"];
                $data[$key]["out_trade_no"] = $v["out_trade_no"]."\t";
                $data[$key]["receiver_addressB"]['receiver_province_name'] = $v["receiver_province_name"];
                $data[$key]["receiver_addressB"]['receiver_city_name'] = $v["receiver_city_name"];
                $data[$key]["receiver_addressB"]['receiver_district_name'] = $v["receiver_district_name"];
                $data[$key]["receiver_addressB"]['receiver_address'] = $v["receiver_address"];
                $data[$key]["shop_name"] = $v["shop_name"];
                $data[$key]["country_name"] = $v["country_name"];
                $data[$key]["order_type_name"] = $v["order_type_name"];
                $data[$key]["order_no"] = $v["order_no"]."\t";
                $data[$key]["goods_name"] = $m["goods_name"];
                $data[$key]["express_name"] = $m["express_name"];
                $data[$key]["express_no"] = $m["express_no"]."\t";
                $data[$key]["sku_name"] = $m["sku_name"];
                $data[$key]["price"] = $m["price"];
                $data[$key]["num"] = $m["num"];
                $data[$key]["goods_money"] = $m["goods_money"];
                $data[$key]["member_price"] = $m["member_price"]-$m["price"];
                $data[$key]["discount_price"] = $m["member_price"]-$m["discount_price"];
                $data[$key]["manjian_money"] = $m["manjian_money"];
                $data[$key]["coupon_money"] = $m["coupon_money"];
                $data[$key]["adjust_money"] = $m["adjust_money"];
                $data[$key]["shipping_fee"] = $m["shipping_fee"];
                $data[$key]["real_money"] = $m["real_money"];
                $data[$key]["commission"] = $m["commission"];
                $data[$key]["commissionA"] = $m["commissionA"];
                $data[$key]["commissionB"] = $m["commissionB"];
                $data[$key]["commissionC"] = $m["commissionC"];
                $data[$key]["bonus"] = $m["bonus"];
                $data[$key]["bonusA"] = $m["bonusA"];
                $data[$key]["bonusB"] = $m["bonusB"];
                $data[$key]["bonusC"] = $m["bonusC"];
                $data[$key]["profit"] = $m["profit"];
                $data[$key]["profitA"] = $m["profitA"];
                $data[$key]["profitB"] = $m["profitB"];
                $data[$key]["profitC"] = $m["profitC"];
                $data[$key]["cost_price"] = $m["cost_price"];
                $data[$key]["goods_code"] = $m["goods_code"]."\t";
                $data[$key]["item_no"] = $m["item_no"]."\t";

                if(isset($v["order_item_list"][$t+1]))$key +=1;
            }
            $key +=1;
        }
        unset($v);
        unset($m);
        $res = dataExcel($xlsName, $xlsCell, $data,$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出会员
    public function export_member($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $member = new MemberService();
        $list = $member->getMemberListToExcel(1, 1000, $condition, 'su.reg_time desc');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出分销商列表
    public function export_commission($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $distributor = new DistributorService();
        $list = $distributor->getDistributorList(0,1, 0, $condition,'become_distributor_time desc');

        $data = [];

        foreach ($list['data'] as $k => $v) {
            $data[$k]["uid"] = $v['uid'];
            $data[$k]["nick_name"] = iconv('gb2312//ignore', 'utf-8', iconv('utf-8', 'gb2312//ignore', $v['nick_name']));

            $data[$k]["referee_id"] = $v['referee_id'];
            $data[$k]["referee_nick_name"] = iconv('gb2312//ignore', 'utf-8', iconv('utf-8', 'gb2312//ignore', $v['referee_name']));

            $data[$k]["user_tel"] = $v['mobile']."\t";

            $data[$k]["isdistributor"] = $v['isdistributor'] == 2 ? "已审核" : ( $v['isdistributor'] == 1 ? "待审核" : "已拒绝");
            $data[$k]["level_name"] = $v['level_name'];
            $data[$k]["commission"] = $v['commission'];
            $data[$k]["withdrawals"] = $v['withdrawals'];

            $data[$k]["apply_distributor_time"] = $v['apply_distributor_time'] ? date('Y-m-d H:i:s',$v['apply_distributor_time']) : date('Y-m-d H:i:s',$v['become_distributor_time']);
            $data[$k]["become_distributor_time"] = date('Y-m-d H:i:s',$v['become_distributor_time']);

            //获取各分销商信息
            $d_info= $distributor->getDistributorInfo($v['uid']);

            $data[$k]["commission_total"] = $d_info['total_commission'] ? $d_info['total_commission'] : 0;
            $data[$k]["order_count"] = $d_info['extensionordercount'] ? $d_info['extensionordercount'] : 0;
            $data[$k]["order_money"] = $d_info['extensionmoney'] ? $d_info['extensionmoney'] : 0;
        }
        unset($v);
        $res = dataExcel($xlsName, $xlsCell, $data,$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出余额提现表
    public function export_bance($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $member = new MemberService();
        $list = $member->getMemberBalanceWithdrawToExcel(1,1000 ,$condition, 'ask_for_date desc');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出余额流水
    public function export_bance_detail($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $member = new MemberService();
        $list = $member->getAccountListToExcel(1,1000, $condition, $order = '', $field = '*');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出积分流水
    public function export_point($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $member = new MemberService();
        $list = $member->getPointListToExcel(1,1000, $condition, $order = '', $field = '*');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出店铺提现列表
    public function export_shop_wal($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $shop = new ShopService;
        $list = $shop->getShopAccountWithdrawListToExcel(1, 1000, $condition, 'ask_for_date desc');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出店铺账户列表
    public function export_shop_acl($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);

        $shop = new ShopService;
        $list = $shop->getShopAccountCountListToExcel(1, 1000, $condition, '');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);

        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    /**
    * 过滤文本中的emoji表情包（输出到excel文件中会导致问题）
    * @param string $text 原文本
    * @return string 过滤emoji表情包后的文本
    */
    function removeEmoji($text){
        $len = mb_strlen($text);
        $newText = '';
        for($i=0;$i<$len;$i++){
            $str = mb_substr($text, $i, 1, 'utf-8');
            if(strlen($str) >= 4) continue;//emoji表情为4个字节
            $newText .= $str;
        }
        return $newText;
    }
    //导出佣金提现列表
    public function export_commission_wal($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $commission = new DistributorService();
        $list = $commission->getCommissionWithdrawListToExcel(1,1000,$condition, 'ask_for_date desc');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出佣金流水
    public function export_commission_detail($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $commission = new DistributorService();
        $list = $commission->getAccountListToExcel(1,1000, $condition, $order = '', $field = '*');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出分红流水
    public function export_bonus_detail($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $member = new MemberService();
        $list = $member->getBonusRecordListToExcel(1,1000, $condition, $order = '', $field = '*');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出收益提现列表
    public function export_profit_wal($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $profit = new MicroShopService();
        $list = $profit->getProfitWithdrawListToExcel(1,1000,$condition, 'ask_for_date desc');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出收益流水
    public function export_profit_detail($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $profit = new MicroShopService();
        $list = $profit->getAccountListToExcel(1,1000, $condition, $order = '', $field = '*');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }
    //导出货款流水
    public function export_proceed_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $member = new MemberService();
        $list = $member->getAccountListToExcel(1,1000, $condition, $order = '', $field = '*');

        $res = dataExcel($xlsName, $xlsCell, $list['data'],$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

    /**
     * 中文转首写拼音
     */
    private $dict_list = array(
        'a' => -20319, 'ai' => -20317, 'an' => -20304, 'ang' => -20295, 'ao' => -20292,
        'ba' => -20283, 'bai' => -20265, 'ban' => -20257, 'bang' => -20242, 'bao' => -20230, 'bei' => -20051, 'ben' => -20036, 'beng' => -20032, 'bi' => -20026, 'bian' => -20002, 'biao' => -19990, 'bie' => -19986, 'bin' => -19982, 'bing' => -19976, 'bo' => -19805, 'bu' => -19784,
        'ca' => -19775, 'cai' => -19774, 'can' => -19763, 'cang' => -19756, 'cao' => -19751, 'ce' => -19746, 'ceng' => -19741, 'cha' => -19739, 'chai' => -19728, 'chan' => -19725, 'chang' => -19715, 'chao' => -19540, 'che' => -19531, 'chen' => -19525, 'cheng' => -19515, 'chi' => -19500, 'chong' => -19484, 'chou' => -19479, 'chu' => -19467, 'chuai' => -19289, 'chuan' => -19288, 'chuang' => -19281, 'chui' => -19275, 'chun' => -19270, 'chuo' => -19263, 'ci' => -19261, 'cong' => -19249, 'cou' => -19243, 'cu' => -19242, 'cuan' => -19238, 'cui' => -19235, 'cun' => -19227, 'cuo' => -19224,
        'da' => -19218, 'dai' => -19212, 'dan' => -19038, 'dang' => -19023, 'dao' => -19018, 'de' => -19006, 'deng' => -19003, 'di' => -18996, 'dian' => -18977, 'diao' => -18961, 'die' => -18952, 'ding' => -18783, 'diu' => -18774, 'dong' => -18773, 'dou' => -18763, 'du' => -18756, 'duan' => -18741, 'dui' => -18735, 'dun' => -18731, 'duo' => -18722,
        'e' => -18710, 'en' => -18697, 'er' => -18696,
        'fa' => -18526, 'fan' => -18518, 'fang' => -18501, 'fei' => -18490, 'fen' => -18478, 'feng' => -18463, 'fo' => -18448, 'fou' => -18447, 'fu' => -18446,
        'ga' => -18239, 'gai' => -18237, 'gan' => -18231, 'gang' => -18220, 'gao' => -18211, 'ge' => -18201, 'gei' => -18184, 'gen' => -18183, 'geng' => -18181, 'gong' => -18012, 'gou' => -17997, 'gu' => -17988, 'gua' => -17970, 'guai' => -17964, 'guan' => -17961, 'guang' => -17950, 'gui' => -17947,
        'gun' => -17931, 'guo' => -17928,
        'ha' => -17922, 'hai' => -17759, 'han' => -17752, 'hang' => -17733, 'hao' => -17730, 'he' => -17721, 'hei' => -17703, 'hen' => -17701, 'heng' => -17697, 'hong' => -17692, 'hou' => -17683, 'hu' => -17676, 'hua' => -17496, 'huai' => -17487, 'huan' => -17482, 'huang' => -17468, 'hui' => -17454,
        'hun' => -17433, 'huo' => -17427,
        'ji' => -17417, 'jia' => -17202, 'jian' => -17185, 'jiang' => -16983, 'jiao' => -16970, 'jie' => -16942, 'jin' => -16915, 'jing' => -16733, 'jiong' => -16708, 'jiu' => -16706, 'ju' => -16689, 'juan' => -16664, 'jue' => -16657, 'jun' => -16647,
        'ka' => -16474, 'kai' => -16470, 'kan' => -16465, 'kang' => -16459, 'kao' => -16452, 'ke' => -16448, 'ken' => -16433, 'keng' => -16429, 'kong' => -16427, 'kou' => -16423, 'ku' => -16419, 'kua' => -16412, 'kuai' => -16407, 'kuan' => -16403, 'kuang' => -16401, 'kui' => -16393, 'kun' => -16220, 'kuo' => -16216,
        'la' => -16212, 'lai' => -16205, 'lan' => -16202, 'lang' => -16187, 'lao' => -16180, 'le' => -16171, 'lei' => -16169, 'leng' => -16158, 'li' => -16155, 'lia' => -15959, 'lian' => -15958, 'liang' => -15944, 'liao' => -15933, 'lie' => -15920, 'lin' => -15915, 'ling' => -15903, 'liu' => -15889,
        'long' => -15878, 'lou' => -15707, 'lu' => -15701, 'lv' => -15681, 'luan' => -15667, 'lue' => -15661, 'lun' => -15659, 'luo' => -15652,
        'ma' => -15640, 'mai' => -15631, 'man' => -15625, 'mang' => -15454, 'mao' => -15448, 'me' => -15436, 'mei' => -15435, 'men' => -15419, 'meng' => -15416, 'mi' => -15408, 'mian' => -15394, 'miao' => -15385, 'mie' => -15377, 'min' => -15375, 'ming' => -15369, 'miu' => -15363, 'mo' => -15362, 'mou' => -15183, 'mu' => -15180,
        'na' => -15165, 'nai' => -15158, 'nan' => -15153, 'nang' => -15150, 'nao' => -15149, 'ne' => -15144, 'nei' => -15143, 'nen' => -15141, 'neng' => -15140, 'ni' => -15139, 'nian' => -15128, 'niang' => -15121, 'niao' => -15119, 'nie' => -15117, 'nin' => -15110, 'ning' => -15109, 'niu' => -14941,
        'nong' => -14937, 'nu' => -14933, 'nv' => -14930, 'nuan' => -14929, 'nue' => -14928, 'nuo' => -14926,
        'o' => -14922, 'ou' => -14921,
        'pa' => -14914, 'pai' => -14908, 'pan' => -14902, 'pang' => -14894, 'pao' => -14889, 'pei' => -14882, 'pen' => -14873, 'peng' => -14871, 'pi' => -14857, 'pian' => -14678, 'piao' => -14674, 'pie' => -14670, 'pin' => -14668, 'ping' => -14663, 'po' => -14654, 'pu' => -14645,
        'qi' => -14630, 'qia' => -14594, 'qian' => -14429, 'qiang' => -14407, 'qiao' => -14399, 'qie' => -14384, 'qin' => -14379, 'qing' => -14368, 'qiong' => -14355, 'qiu' => -14353, 'qu' => -14345, 'quan' => -14170, 'que' => -14159, 'qun' => -14151,
        'ran' => -14149, 'rang' => -14145, 'rao' => -14140, 're' => -14137, 'ren' => -14135, 'reng' => -14125, 'ri' => -14123, 'rong' => -14122, 'rou' => -14112, 'ru' => -14109, 'ruan' => -14099, 'rui' => -14097, 'run' => -14094, 'ruo' => -14092,
        'sa' => -14090, 'sai' => -14087, 'san' => -14083, 'sang' => -13917, 'sao' => -13914, 'se' => -13910, 'sen' => -13907, 'seng' => -13906, 'sha' => -13905, 'shai' => -13896, 'shan' => -13894, 'shang' => -13878, 'shao' => -13870, 'she' => -13859, 'shen' => -13847, 'sheng' => -13831, 'shi' => -13658, 'shou' => -13611, 'shu' => -13601, 'shua' => -13406, 'shuai' => -13404, 'shuan' => -13400, 'shuang' => -13398, 'shui' => -13395, 'shun' => -13391, 'shuo' => -13387, 'si' => -13383, 'song' => -13367, 'sou' => -13359, 'su' => -13356, 'suan' => -13343, 'sui' => -13340, 'sun' => -13329, 'suo' => -13326,
        'ta' => -13318, 'tai' => -13147, 'tan' => -13138, 'tang' => -13120, 'tao' => -13107, 'te' => -13096, 'teng' => -13095, 'ti' => -13091, 'tian' => -13076, 'tiao' => -13068, 'tie' => -13063, 'ting' => -13060, 'tong' => -12888, 'tou' => -12875, 'tu' => -12871, 'tuan' => -12860, 'tui' => -12858, 'tun' => -12852, 'tuo' => -12849,
        'wa' => -12838, 'wai' => -12831, 'wan' => -12829, 'wang' => -12812, 'wei' => -12802, 'wen' => -12607, 'weng' => -12597, 'wo' => -12594, 'wu' => -12585,
        'xi' => -12556, 'xia' => -12359, 'xian' => -12346, 'xiang' => -12320, 'xiao' => -12300, 'xie' => -12120, 'xin' => -12099, 'xing' => -12089, 'xiong' => -12074, 'xiu' => -12067, 'xu' => -12058, 'xuan' => -12039, 'xue' => -11867, 'xun' => -11861,
        'ya' => -11847, 'yan' => -11831, 'yang' => -11798, 'yao' => -11781, 'ye' => -11604, 'yi' => -11589, 'yin' => -11536, 'ying' => -11358, 'yo' => -11340, 'yong' => -11339, 'you' => -11324, 'yu' => -11303, 'yuan' => -11097, 'yue' => -11077, 'yun' => -11067,
        'za' => -11055, 'zai' => -11052, 'zan' => -11045, 'zang' => -11041, 'zao' => -11038, 'ze' => -11024, 'zei' => -11020, 'zen' => -11019, 'zeng' => -11018, 'zha' => -11014, 'zhai' => -10838, 'zhan' => -10832, 'zhang' => -10815, 'zhao' => -10800, 'zhe' => -10790, 'zhen' => -10780, 'zheng' => -10764, 'zhi' => -10587, 'zhong' => -10544, 'zhou' => -10533, 'zhu' => -10519, 'zhua' => -10331, 'zhuai' => -10329, 'zhuan' => -10328, 'zhuang' => -10322, 'zhui' => -10315, 'zhun' => -10309, 'zhuo' => -10307, 'zi' => -10296, 'zong' => -10281, 'zou' => -10274, 'zu' => -10270, 'zuan' => -10262,
        'zui' => -10260, 'zun' => -10256, 'zuo' => -10254
    );


    /**
     * 获取全部拼音，返回拼音的数组,如 '张三丰'  ==>  ['zhang','san','feng']
     * @param $chinese
     * @param string $charset
     * @return array
     */
    public function get_all_py($chinese, $charset = 'utf-8')
    {
        if ($charset != 'gb2312') $chinese = $this->_U2_Utf8_Gb($chinese);
        $py = $this->zh_to_pys($chinese);

        return $py;
    }

    /**
     * 获取拼音首字母，如['zhang','san','feng']  ==> zsf
     * @param $all_pys
     * @return string
     */
    public function get_first_py($all_pys)
    {
        if (count($all_pys) <= 0) {
            return '';
        }

        $result = [];
        foreach ($all_pys as $one) {
            if (is_null($one) || strlen($one) <= 0) {
                continue;
            }
            $result[] = substr($one, 0, 1);
        }
        unset($one);
        return join('', $result);
    }

    /**
     * 获取拼音首字母，如['zhang','san','feng']  ==> z
     * @param $all_pys
     * @return string
     */
    public function get_first_letter($all_pys)
    {
        if (count($all_pys) <= 0) {
            return '';
        }

        foreach ($all_pys as $one) {
            if (is_null($one) || strlen($one) <= 0) {
                continue;
            }
            return substr($one, 0, 1);
        }
        unset($one);
        return '';
    }

    private function _U2_Utf8_Gb($_C)
    {
        $_String = '';
        if ($_C < 0x80) $_String .= $_C;
        elseif ($_C < 0x800) {
            $_String .= chr(0xC0 | $_C >> 6);
            $_String .= chr(0x80 | $_C & 0x3F);
        } elseif ($_C < 0x10000) {
            $_String .= chr(0xE0 | $_C >> 12);
            $_String .= chr(0x80 | $_C >> 6 & 0x3F);
            $_String .= chr(0x80 | $_C & 0x3F);
        } elseif ($_C < 0x200000) {
            $_String .= chr(0xF0 | $_C >> 18);
            $_String .= chr(0x80 | $_C >> 12 & 0x3F);
            $_String .= chr(0x80 | $_C >> 6 & 0x3F);
            $_String .= chr(0x80 | $_C & 0x3F);
        }
        return iconv('UTF-8', 'GB2312', $_String);
    }

    private function zh_to_py($num, $blank = '')
    {
        if ($num > 0 && $num < 160) {
            return chr($num);
        } elseif ($num < -20319 || $num > -10247) {
            return $blank;
        } else {
            foreach ($this->dict_list as $py => $code) {
                if ($code > $num) break;
                $result = $py;
            }
            unset($code);
            return $result;
        }
    }

    private function zh_to_pys($chinese)
    {
        $result = array();
        for ($i = 0; $i < strlen($chinese); $i++) {
            $p = ord(substr($chinese, $i, 1));
            if ($p > 160) {
                $q = ord(substr($chinese, ++$i, 1));
                $p = $p * 256 + $q - 65536;
            }
            $result[] = $this->zh_to_py($p);
        }
        return $result;
    }
    /**
     * 删除文件
     */
    public function deleteExcels($website_id){
        $excelsModel = new VslExcelsModel();
        $list = $excelsModel->getQuery(['status' => 1, 'website_id' => $website_id], 'path,id,updatetime', 'addtime asc');
        if($list){
            foreach($list as $v){

                $time = time()-$v['updatetime'];
                $t = $time / 3600;
                if($t < 12){
                    continue;
                }
                if($v){
                    debugLog('删除文件'.$v['id']);
                    //删除文件并改变状态
                    $arr = explode('/',$v['path']);
                    if($arr[0] != 'excels'){
                        return; //非指定存放目录路径不允许删除操作
                    }
                    $path = './'.$v['path'];
                    if(file_exists($path)){
                        unlink($path);
                    }

                    //查询文件夹是否为空，是则删除文件夹
                    $dirs = './'.$arr[0].'/'.$arr[1].'/'.$arr[2].'/';
                    $check = array_diff(scandir($dirs),array('..','.'));
                    if(is_dir($dirs) && empty($check)){
                        rmdir($dirs);
                    }
                    $dirs2 = './'.$arr[0].'/'.$arr[1].'/';
                    $check2 = array_diff(scandir($dirs2),array('..','.'));
                    if(empty(is_dir($dirs2) && $check2)){
                        rmdir($dirs2);
                    }
                    $excelModel = new VslExcelsModel();
                    $excelModel->save(['status'=>6,'updatetime'=>time(),'msg'=>'已失效'],['id'=>$v['id']]);
                }
            }
            unset($v);
        }else{
            return;
        }
    }

    /**
     * 待发货订单数据导出
     */
    public function export_unDeliveryOrder_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');//TODO...
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $order_service = new OrderService();
//        $list = $order_service->getOrderList(1, 0, $condition, 'create_time desc');
        $list = $order_service->getExcelsExportOrderList(1, 1000, $condition, 'create_time desc');
        $excelsModel = new VslExcelsModel();
        if(!$list['data']){
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>'该查询条件没有订单,导出失败'],['id'=>$info['id']]);
        }
        foreach ($list["data"] as $k => $v) {
            //过滤虚拟商品订单
            if(count($v['order_item_list']) == 1) {
                if($v['order_item_list'][0]['goods_type'] == 3) {
                    unset($list['data'][$k]);
                }
            }
            //过滤拼团待成团订单
            if($v['order_type'] == 5 && $v['group_record_id']) {
                $group_record_mdl = new VslGroupShoppingRecordModel();
                $status = $group_record_mdl->Query(['record_id' => $v['group_record_id']],'status')[0];
                if($status == 0) {
                    unset($list['data'][$k]);
                }
            }
        }
        unset($v);
        $list['data'] = array_values($list['data']);

        $data = [];
        $key = 0;
        foreach ($list["data"] as $k => $v) {
            if(!$v['order_item_list']){
                continue;
            }
            foreach ($v["order_item_list"] as $t => $m) {
                $data[$key]["order_no"] = $v["order_no"];
                $data[$key]["create_time"] = getTimeStampTurnTime($v["create_time"]);
                $data[$key]["warehouse_name"] = $m["supplier_name"];
                $data[$key]["goods_name"] = $m["goods_name"];
                $data[$key]["express_company_no"] = '';
                $data[$key]["express_no"] = '';
                $data[$key]["sku_name"] = $m["sku_name"];
                $data[$key]["num"] = $m["num"];
                $data[$key]["goods_money"] = $m["real_money"];
                $data[$key]["receiver_name"] = $v["receiver_name"];
                $data[$key]["receiver_mobile"] = $v["receiver_mobile"];
                $data[$key]["receiver_address"] = $v["receiver_address"];
                $data[$key]["order_goods_id"] = $m["order_goods_id"];
                $data[$key]["item_no"] = $m["item_no"];
                $orderGoodsIds[] = $m["order_goods_id"];
                if(isset($v["order_item_list"][$t+1]))$key +=1;
            }
            $key +=1;
        }
        unset($v);
        $res = dataExcel($xlsName, $xlsCell, $data,$this->reset_file_path);
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

    /**
     * 电子卡密数据导出
     */
    public function export_electroncardData_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);

        $electroncard_server = new ElectroncardServer;
        $list = $electroncard_server->electroncardBaseDetail(1, 0, $condition, 'create_time desc');

        $data = [];
        $i = 0;
        foreach ($list["data"] as $k => $v) {
            foreach ($v['value'] as $key => $val) {
                $data[$i][$xlsCell[$key][0]] = $val;
            }
            unset($val);
            $data[$i]["status"] = $v["status"] == 0 ? '未出售' : '已出售';
            $data[$i]["create_time"] = $v['create_time'];
            $i +=1;
        }
        unset($v);
        $res = dataExcel($xlsName, $xlsCell, $data,$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

    /**
     * 导出战略经销团队详情列表
     */
    public function export_teamDetail_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);


        $order_model = new VslOrderModel();
        $list = $order_model->getViewList4(1, 0, $condition, 'nm.order_id desc');
        $data = [];
        foreach ($list["data"] as $k => $v) {
            $data[$k]['order_no'] = $v['order_no'];
            $data[$k]["uid"] = $v["uid"];
            $data[$k]["user_info"] = ($v['nick_name'])?$v['nick_name']:($v['user_name']?$v['user_name']:($v['user_tel']?$v['user_tel']:$v['uid']));
            $data[$k]["sign_time"] = date('Y-m-d H:i:s', $v['sign_time']);
            $data[$k]["order_money"] = $v['order_money'];
        }
        unset($v);
        $res = dataExcel($xlsName, $xlsCell, $data,$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

    /**
     * 导出用户佣金流水
     *
     * @throws \PHPExcel_Exception
     * @throws \PHPExcel_Reader_Exception
     * @throws \PHPExcel_Writer_Exception
     */
    public function export_commissionLogDetail_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);


        $member = new Distributor();
        $list = $member->getMemberCommissionList(1, 0, $condition);
        $data = [];

        foreach ($list["data"] as $k => $v) {
            $data[$k]['records_no'] = $v['records_no'];
            $data[$k]['data_id'] = $v['data_id'];
            $data[$k]["uid"] = $v["uid"];
            $data[$k]["user_info"] = $v["user_info"];
            $data[$k]["commission_type"] = $v["commission_type"];
            $data[$k]["change_type"] = '订单完成';
            $data[$k]["commission"] = $v["commission"];
            $data[$k]["create_time"] = $v["create_time"];
            $data[$k]["text"] = $v["text"];
        }
        unset($v);
        $res = dataExcel($xlsName, $xlsCell, $data,$this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

    //导出礼品劵
    public function export_gift_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = unserialize($info['conditions']);
        $VoucherServer = new VoucherServer();
        $fields = 'vgvr.*,su.user_tel,su.nick_name,su.user_name,vs.shop_name,vpg.gift_name,vpg.price';
        $list = $VoucherServer->getGiftVoucherHistory(1, 0, $condition, $fields,'vgvr.record_id desc');
        $store_ids = [];
        foreach ($list as $i){
            $store_ids[$i['store_id']] = $i['store_id'];
        }

        $store_list = VslStoreModel::where('store_id', 'in', $store_ids)->column('store_name', 'store_id');
        $data = [];
        foreach ($list['data'] as &$item){
            if ($item['user_name']) {
                $item['name'] = $item['user_name'];
            } elseif ($item['nick_name']) {
                $item['name'] = $item['nick_name'];
            } elseif ($item['user_tel']) {
                $item['name'] = $item['user_tel'];
            }
            //礼品券状态 -1冻结 1已领用（未使用） 2已使用 3已过期
            $state_name = '未使用';
            if($item['state'] == 2){
                $state_name = '已使用';
            }
            $type_name = VslGiftVoucherRecordsModel::types($item['get_type']);
            //是否是别人转赠
            if(!empty($item['send_uid'])){
                $type_name .= '(来自：'.$item['send_uid'].' 转赠)';
            }
            if($item['use_time']){
                $item['use_time'] = date('Y-m-d H:i:s', $item['use_time']);
            }else{
                $item['use_time'] = '';
            }
            $data[] = [
                'record_id'=> $item['record_id'],
                'uid'=> $item['uid'],
                'name'=> $item['name'],
                'gift_voucher_code'=> $item['gift_voucher_code'],
                'gift_name'=> $item['gift_name'],
                'state_name'=> $state_name,
                'type_name'=> $type_name,
                'fetch_time'=> date('Y-m-d H:i:s', $item['fetch_time']),
                'use_time'=> $item['use_time'],
                'shop_name'=> isset($store_list[$item['store_id']]) ? $store_list[$item['store_id']] : '',
            ];
        }

        $res = dataExcel($xlsName, $xlsCell, $data, $this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

    //导出礼品劵
    public function export_coupon_list($info){
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        define('DOWN_EXCEL', 'excels/' . $info['website_id'] . '/'.$info['type'].'/');
        $this->reset_file_path = DOWN_EXCEL;
        if (!file_exists($this->reset_file_path)) {
            $mode = intval('0777', 8);
            mkdir($this->reset_file_path, $mode, true);
        }
        $xlsName = $info['exname'];
        $xlsCell = unserialize($info['ids']);
        $condition = [];
        $where = unserialize($info['conditions']);
        $fields = 'nc.coupon_id,nc.coupon_code,nc.money,nc.discount,nc.use_time,nc.fetch_time,nc.state,nc.get_type,nc.uid,
            su.user_tel,su.nick_name,su.user_name,no.order_no,no.shop_id,ns.shop_name,nc.send_uid';
        $promotion = new CouponServer();
        $list = $promotion->getCouponHistory(1, 0, $condition, $where, $fields, 'nc.coupon_id desc');
        $data = [];
        foreach ($list['data'] as &$item){
            if ($item['user_name']) {
                $item['name'] = $item['user_name'];
            } elseif ($item['nick_name']) {
                $item['name'] = $item['nick_name'];
            } elseif ($item['user_tel']) {
                $item['name'] = $item['user_tel'];
            }

            if($item['use_time']){
                $item['use_time'] = date('Y-m-d H:i:s', $item['use_time']);
            }else{
                $item['use_time'] = '';
            }
            //礼品券状态 -1冻结 1已领用（未使用） 2已使用 3已过期
            $state_name = '未使用';
            if($item['state'] == 2){
                $state_name = '已使用';
            }
            $item['state_name'] = $state_name;
            $type_name = VslCouponModel::types($item['get_type']);
            //是否是别人转赠
            if(!empty($item['send_uid'])){
                $type_name .= '(来自：'.$item['send_uid'].' 转赠)';
            }
            $item['type_name'] = $type_name;

            $data[] = [
                'coupon_id'=> $item['coupon_id'],
                'uid'=> $item['uid'],
                'name'=> $item['name'],
                'coupon_code'=> $item['coupon_code'],
                'money'=> $item['money'],
                'state_name'=> $state_name,
                'type_name'=> $type_name,
                'fetch_time'=> date('Y-m-d H:i:s', $item['fetch_time']),
                'use_time'=> $item['use_time'],
                'order_no'=> empty($item['order_no']) ? '':$item['order_no'],
                'shop_name'=> $item['shop_id'] == 0 ? '自营店':$item['shop_name']
            ];
        }

        $res = dataExcel($xlsName, $xlsCell, $data, $this->reset_file_path);
        $excelsModel = new VslExcelsModel();
        if ($res['code'] > 0) { //导出成功
            $file = $res['data'];
            $excelsModel->save(['status'=>1,'updatetime'=>time(),'path'=>$file,'msg'=>'导出成功'],['id'=>$info['id']]);
        }else{//导出失败
            $excelsModel->save(['status'=>2,'updatetime'=>time(),'msg'=>$res['data']],['id'=>$info['id']]);
        }
    }

}
