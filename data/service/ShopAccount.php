<?php
namespace data\service;
use addons\shop\model\VslShopModel;
use addons\shop\model\VslShopAccountModel;
use addons\shop\model\VslShopAccountRecordsModel;
use addons\shop\model\VslShopOrderReturnModel;
use addons\supplier\model\VslSupplierAccountModel;
use addons\supplier\model\VslSupplierAccountRecordsModel;
use data\model\VslOrderGoodsModel;
use addons\shop\model\VslShopOrderGoodsReturnModel;
use data\model\VslAccountModel;
use data\model\VslAccountOrderRecordsModel;
use data\model\VslAccountRecordsModel;
use data\model\VslAccountWithdrawUserRecordsModel;
use think\Log;
use data\service\Config as WebConfig;
use addons\shop\model\VslShopSeparateModel;
use addons\shop\model\VslShopWithdrawModel;
/**
 * 店铺账户管理
 */
class ShopAccount extends BaseService
{

    /**
     * **************************************************店铺账户计算--Start****************************************************************
     */
    /**
     * 更新店铺的可用总额
     *
     * @param unknown $shop_id
     * @param unknown $money
     */
    public function updateShopAccountMoney($shop_id,$money)
    {
        $account_model = new VslShopAccountModel();
        $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_total_money');
        // 没有的话新建账户
        if (empty($account_info)) {
            $data = array(
                'shop_id' => $shop_id,
            );
            $account_model->save($data);
            $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_total_money');
        }
        $data1 = array(
            "shop_total_money" => $account_info["shop_total_money"] + $money
        );
        $retval = $account_model->save($data1, [
            'shop_id' => $shop_id
        ]);
        return $retval;
    }
    /**
     * 更新店铺营业总额
     */
    public function updateShopRealAccountTotalMoney($shop_id, $money)
    {
        $account_model = new VslShopAccountModel();
        $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_real_entry_money');
        // 没有的话新建账户
        if (empty($account_info)) {
            $data = array(
                'shop_id' => $shop_id
            );
            $account_model->save($data);
            $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_real_entry_money');
        }
        $data1 = array(
            "shop_real_entry_money" => $account_info["shop_real_entry_money"] + $money
        );

        $retval = $account_model->save($data1, [
            'shop_id' => $shop_id,
        ]);
        return $retval;
    } 
    /**
     * 更新店铺的营业额
     * 
     * @param unknown $shop_id            
     * @param unknown $money            
     */
    public function updateShopAccountTotalMoney($shop_id, $money)
    {
        $account_model = new VslShopAccountModel();
        $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_entry_money');
        // 没有的话新建账户
        if (empty($account_info)) {
            $data = array(
                'shop_id' => $shop_id
            );
            $account_model->save($data);
            $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_entry_money');
        }
        $data1 = array(
            "shop_entry_money" => $account_info["shop_entry_money"] + $money
        );

        $retval = $account_model->save($data1, [
            'shop_id' => $shop_id,
        ]);
        return $retval;
    }
    /**
     * 更新店铺的优惠总额
     *
     * @param unknown $shop_id
     * @param unknown $money
     */
    public function updateShopPromotionMoney($shop_id,$money)
    {
        $account_model = new VslShopAccountModel();
        $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_promotion_money');
        // 没有的话新建账户
        if (empty($account_info)) {
            $data = array(
                'shop_id' => $shop_id
            );
            $account_model->save($data);
            $account_info = $account_model->getInfo(['shop_id'=>$shop_id],'shop_promotion_money');
        }
        $data1 = array(
            "shop_promotion_money" => $account_info["shop_promotion_money"] + $money
        );
        $retval = $account_model->save($data1, [
            'shop_id' => $shop_id
        ]);
        return $retval;
    }

    /**
     * 添加店铺的整体的资金流水
     * 
     * @param unknown $serial_no            
     * @param unknown $shop_id            
     * @param unknown $money            
     * @param unknown $account_type            
     * @param unknown $type_alis_id            
     * @param unknown $remark            
     * @param unknown $title            
     */
    public function addShopAccountRecords($serial_no, $shop_id, $money, $account_type, $type_alis_id, $remark, $title,$website_id='')
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $model = new VslShopAccountRecordsModel();
        $data = array(
            'shop_id' => $shop_id,
            'serial_no' => $serial_no,
            'account_type' => $account_type,
            'money' => $money,
            'type_alis_id' => $type_alis_id,
            'remark' => $remark,
            'title' => $title,
            'create_time' => time(),
            'website_id' => $websiteid
        );
         $model->save($data);
    }

    /**
     * 计算某个订单针对平台的利润
     *
     * @param unknown $order_id
     * @param unknown $order_no
     * @param unknown $shop_id
     * @param unknown $real_pay
     * @return unknown
     */
    public function addShopOrderAccountRecords($order_id, $order_no, $shop_id, $real_pay)
    {
        $shop_order_account_model = new VslShopOrderReturnModel();
        $order_goods_model = new VslOrderGoodsModel();
        $order_account_list = $shop_order_account_model->getInfo([
            "order_id" => $order_id
        ]);
        if (empty($order_account_list)) {
            $shop_order_account_model->startTrans();
            try {
                $rate = $this->getShopAccountRate( $shop_id);
                // 查询订单项的信息
                $condition["order_id"] = $order_id;
                $order_goods_list = $order_goods_model->getQuery($condition, '*', '');
                // 订单抽取的总额
                $order_return_money = 0;
                if (! empty($order_goods_list) && $rate >= 0) {
                    foreach ($order_goods_list as $k => $order_goods) {
                        // 订单项的订单商品实付金额
                        $order_goods_real_money = $order_goods['real_money'];
                        $order_goods_return_money = $order_goods_real_money * $rate / 100;
                        // 计算订单的抽取总额
                        $order_return_money = $order_return_money + $order_goods_return_money;
                        //更新店铺账户中平台抽取店铺利润总额shop_platform_commission
                        $shop = new VslShopAccountModel();
                        $account = $shop->getInfo(['shop_id'=>$shop_id],'*');
                        if($account){
                            $real_shop_platform_commission = $account['shop_platform_commission']+$order_return_money;
                            $shop->save(['shop_platform_commission'=>$real_shop_platform_commission],['shop_id'=>$shop_id]);
                            //更新平台账户中平台抽取利润总额account_return
                            $platform_shop = new VslAccountModel();
                            $platform_account = $platform_shop->getInfo(['website_id'=>$this->website_id],'*');
                            if($platform_account){
                                $real_platform_commission = $order_return_money+$platform_account['account_return'];
                                $platform_shop->save(['account_return'=>$real_platform_commission],['website_id'=>$this->website_id]);
                            }
                        }
                        $goods_data = array(
                            "shop_id" => $shop_id,
                            "order_id" => $order_id,
                            "order_goods_id" => $order_goods["order_goods_id"],
                            "goods_pay_money" => $order_goods_real_money,
                            "rate" => $rate,
                            "return_money" => $order_goods_return_money,
                            "create_time" => time(),
                            "website_id" => $this->website_id
                        );
                         $order_goods_return_model = new VslShopOrderGoodsReturnModel();
                         $order_goods_return_model->save($goods_data);
                    }
                    unset($order_goods);
                    $data = array(
                        "shop_id" => $shop_id,
                        "order_id" => $order_id,
                        "order_no" => $order_no,
                        "order_pay_money" => $real_pay,
                        "platform_money" => $order_return_money,
                        "create_time" => time(),
                        "website_id" => $this->website_id
                    );
                    $shop_order_account_model->save($data);
                    $shop_order_account_model->commit();
                }
            } catch (\Exception $e) {
            recordErrorLog($e);
                $shop_order_account_model->rollback();
                Log::write("错误addShopOrderAccountRecords".$e->getMessage());
            }
        }
    }
    /**
     * 订单退款 更新平台抽取金额
     * 
     * @param unknown $order_id            
     * @param unknown $order_goods_id            
     * @param unknown $shop_id            
     */
    public function updateShopOrderGoodsReturnRecords($order_id, $order_goods_id,$shop_id)
    {
        $order_goods_return_model = new VslShopOrderGoodsReturnModel();
        $order_goods_model = new VslOrderGoodsModel();
        $order_return_model = new VslShopOrderReturnModel();
        $order_goods_count = $order_goods_return_model->getCount([
            "order_goods_id" => $order_goods_id
        ]);
        if ($order_goods_count > 0) {
            try {
                $order_goods_return_model->startTrans();
                // 得到订单项的基本信息
                $order_goods = $order_goods_model->getInfo(['order_goods_id'=>$order_goods_id,'order_id'=>$order_id],'real_money,refund_require_money,website_id');
                // 获取商品利润的基本信息
                $order_goods_refund_info = $order_goods_return_model->getInfo(['order_goods_id'=>$order_goods_id,'order_id'=>$order_id],'return_money');
                // 获取利润的基本信息
                $order_return_info = $order_return_model->getInfo(['order_id'=>$order_id],'platform_money');
                // 订单项的实际付款金额
                $order_goods_real_money = $order_goods['real_money'];
                // 订单项的实际退款金额
                $order_goods_require_money = $order_goods['refund_require_money'];
                $return_data = array(
                    "platform_money" => $order_return_info['platform_money']- $order_goods_real_money,
                );
                $order_return_model->save($return_data, [
                    "order_id" => $order_id
                ]);
                //更新店铺账户中平台抽取店铺利润总额shop_platform_commission
                $shop = new VslShopAccountModel();
                $account = $shop->getInfo(['shop_id'=>$shop_id],'shop_platform_commission,shop_total_money');
                if($account){
                    $real_shop_platform_commission = $account['shop_platform_commission']-$order_goods_real_money;
                    //退款金额差价给店铺
                    $real_shop_commission = $account['shop_total_money']+$order_goods_real_money-$order_goods_require_money;
                    $shop->save(['shop_platform_commission'=>$real_shop_platform_commission,'shop_total_money'=>$real_shop_commission],['shop_id'=>$shop_id]);
                }
                //更新平台账户中平台抽取利润总额account_return
                $platform_shop = new VslAccountModel();
                $platform_account = $platform_shop->getInfo(['website_id'=>$order_goods['website_id']],'account_return');
                if($platform_account){
                    $real_platform_commission = $platform_account['account_return']-$order_goods_real_money;
                    $platform_shop->save(['account_return'=>$real_platform_commission],['website_id'=>$order_goods['website_id']]);
                }
                $goods_data = array(
                    "return_money" => $order_goods_refund_info['return_money']-$order_goods_real_money
                );
                $order_goods_return_model->save($goods_data, [
                    "order_id" => $order_id,
                    "order_goods_id" => $order_goods_id
                ]);
                $order_goods_return_model->commit();
            } catch (\Exception $e) {
            recordErrorLog($e);
                $order_goods_return_model->rollback();
            }
        }
    }



    /**
     * 得到订单项的的对平台的提成比率
     *
     * @param unknown $shop_id
     */
    private function getShopAccountRate($shop_id)
    {
        $shop_model = new VslShopModel();
        // 得到店铺的信息
        $shop_obj = $shop_model->getInfo(['shop_id'=>$shop_id],'shop_platform_commission_rate');
        if (empty($shop_obj)) {
            return 0;
        } else {
            return $shop_obj["shop_platform_commission_rate"];
        }
    }

    /**
     * 店铺详情
     *
     * @param unknown $shop_id
     * @param unknown $shop_name
     */
    public function getStoreInformation($shop_id,$shop_name)
    {
        $model = new VslShopModel();
        // 得到店铺的信息
        $shop_obj = $model->getInfo(['shop_id'=>$shop_id,'shop_name'=>$shop_name, 'website_id' => $this->website_id],'shop_name,shop_logo,comprehensive,shop_deliverycredit,shop_desccredit,shop_servicecredit');
        if (empty($shop_obj)) {
            return 0;
        } else {
            return $shop_obj;
        }
    }


    /**
     * 得到店铺的账户情况
     * 
     * @param unknown $shop_id            
     * @return \think\static
     */
    public function getShopAccount($shop_id)
    {
        // TODO Auto-generated method stub
        $shop_account = new VslShopAccountModel();
        $account_obj = $shop_account->get($shop_id);
        if (empty($account_obj)) {
            // 默认添加
            $data = array(
                "shop_id" => $shop_id,
                'website_id' => $this->website_id
            );
            $shop_account->save($data);
            $account_obj = $shop_account->get($shop_id);
        }
        // 店铺收益总额
        $shop_proceeds = $account_obj["shop_proceeds"];
        // 平台抽取利润总额
        $shop_platform_commission = $account_obj["shop_platform_commission"];
        // 店铺提现总额
        $shop_withdraw = $account_obj["shop_withdraw"];
        // 店铺可用总额
        $shop_balance = $shop_proceeds - $shop_platform_commission - $shop_withdraw;
        $account_obj["shop_balance"] = $shop_balance;
        return $account_obj;
    }

    /**
     * **************************************************店铺账户计算--End****************************************************************
     */
    
    /**
     * **************************************************平台账户--Start****************************************************************
     */
    /**
     * 添加平台的订单入帐记录
     * 
     * @param unknown $shop_id            
     * @param unknown $money            
     * @param unknown $account_type            
     * @param unknown $type_alis_id            
     * @param unknown $remark            
     */
    public function addAccountOrderRecords($shop_id, $money, $account_type, $type_alis_id, $remark,$uid=0,$website_id=0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $order_model = new VslAccountOrderRecordsModel();
        $order_model->startTrans();
        try {
            $data = array(
                'serial_no' => getSerialNo(),
                'shop_id' => $shop_id,
                'money' => $money,
                'account_type' => $account_type,
                'type_alis_id' => $type_alis_id,
                'create_time' => time(),
                'remark' => $remark,
                'website_id'=>$websiteid
            );
            $order_model->save($data);
            $order_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            Log::write("addAccountOrderRecords".$e->getMessage());
            $order_model->rollback();
        }
    }

    /**
     * 更新平台账户的订单总额
     * 
     * @param unknown $money            
     */
    public function updateAccountOrderMoney($money)
    {
        $account_model = new VslAccountModel();
        $account_obj = $account_model->getInfo([
            'website_id' => $this->website_id
        ],'account_order_money');
        if($account_obj){
            $data = array(
                "account_order_money" => $account_obj["account_order_money"] + abs($money)
            );
            $account_model->save($data, ['website_id'=>$this->website_id]);
        }else{
            $data = array(
                'website_id' => $this->website_id,
                "account_order_money" => abs($money)
            );
            $account_model->save($data);
        }
    }
    /**
     *  更新平台账户的订单总额和退款总额
     *
     * @param unknown $money
     */
    public function updateAccountMoney($money,$website_id=0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $account_model = new VslAccountModel();
        $account_obj = $account_model->getInfo([
            'website_id' => $websiteid
        ],'account_order_money,order_refund_money');
        if($account_obj){
            $data = array(
                "account_order_money" => $account_obj["account_order_money"] - abs($money),
                "order_refund_money" => $account_obj["order_refund_money"] + abs($money)
            );
            $account_model->save($data, [
                'website_id' => $websiteid
            ]);
        }
    }
    /**
     * 更新个人账户的订单余额支付总额
     *
     * @param unknown $money
     */
    public function updateAccountOrderBalance($money,$website_id=0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $account_model = new VslAccountModel();
        $account_obj = $account_model->getInfo([
            'website_id' => $websiteid
        ],'order_balance_money');
        if($account_obj){
            $data = array(
                "order_balance_money" => $account_obj["order_balance_money"] + $money
            );
            $account_model->save($data, [
                'website_id' => $websiteid
            ]);
        }else{
            $data = array(
                'website_id' => $websiteid,
                "order_balance_money" => $money
            );
            $account_model->save($data);
        }
    }
    /**
     * 更新个人账户的订单积分抵扣总额
     *
     * @param unknown $money
     */
    public function updateAccountOrderPoint($money,$website_id=0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $account_model = new VslAccountModel();
        $account_obj = $account_model->getInfo([
            'website_id' => $websiteid
        ],'order_point_money');
        if($account_obj){
            $data = array(
                "order_point_money" => $account_obj["order_point_money"] + $money
            );
            $account_model->save($data, [
                'website_id' => $websiteid
            ]);
        }else{
            $data = array(
                'website_id' => $websiteid,
                "order_point_money" => $money
            );
            $account_model->save($data);
        }
    }

    /**
     * 更新平台的抽取例利润的总额
     * 
     * @param unknown $money            
     */
    private function updateAccountReturn($money)
    {
        $account_model = new VslAccountModel();
        $account_obj = $account_model->getInfo([
            'website_id' => $this->website_id
        ]);
        $data = array(
            "account_return" => $account_obj["account_return"] + $money
        );
        $account_model->save($data, [
            'website_id' => $this->website_id
        ]);
    }

    /**
     * 更新店铺在平台端的提现字段
     * 
     * @param unknown $money            
     */
   public function updateAccountWithdraw($money)
    {
        $account_model = new VslAccountModel();
        $account_model->startTrans();
        $account_obj = $account_model->getInfo([
            'website_id' => $this->website_id
        ],'account_withdraw');
        $data = array(
            "account_withdraw" => $account_obj["account_withdraw"] + $money
        );
        try {
        $account_model->save($data, [
            'website_id' => $this->website_id
        ]);
        $account_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            $account_model->rollback();
        }
    }


    /**
     * 针对平台 会员的提现金额
     * 
     * @param unknown $shop_id            
     * @param unknown $money            
     * @param unknown $account_type            
     * @param unknown $type_alis_id            
     * @param unknown $remark            
     */
    public function addAccountWithdrawUserRecords($shop_id, $money, $account_type, $type_alis_id, $remark)
    {
        $withdraw_model = new VslAccountWithdrawUserRecordsModel();
        $withdraw_model->startTrans();
        try {
            $data = array(
                'serial_no' => 'MT'.getSerialNo(),
                'shop_id' => $shop_id,
                'money' => $money,
                'account_type' => $account_type,
                'type_alis_id' => $type_alis_id,
                'create_time' => time(),
                'remark' => $remark,
                "website_id" => $this->website_id
            );
            $withdraw_model->save($data);
            $withdraw_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            $withdraw_model->rollback();
        }
    }
    /**
     * 更新平台的 会员充值金额
     *
     * @param unknown $money
     */
    public function addAccountUserWithdraw($money,$data_id=0)
    {
        $account_model = new VslAccountModel();
        $account_model->startTrans();
        $account_obj = $account_model->getInfo([
            'website_id' => $this->website_id
        ],'account_order_money,back_recharge');
        if($data_id){
            $data = array(
                'account_order_money'=> $account_obj["account_order_money"] +$money,
            );
        }else{
            $data = array(
                'back_recharge'=> $account_obj["back_recharge"] +$money,
            );
        }
        try {
            $account_model->save($data, ['website_id' => $this->website_id]);
            $account_model->commit();
        } catch (\Exception $e) {
            recordErrorLog($e);
            $account_model->rollback();
        }
    }
    /**
     * 更新平台的 会员提现金额
     * 
     * @param unknown $money            
     */
    public function updateAccountUserWithdraw($money)
    {
        $account_model = new VslAccountModel();
        $account_obj = $account_model->getInfo([
            'website_id' => $this->website_id
        ],'account_user_withdraw');
        $data = array(
            "account_user_withdraw" => $account_obj["account_user_withdraw"] + abs($money)
        );
        $account_model->save($data, [
            'website_id' => $this->website_id
        ]);
    }

    /**
     * 添加平台的整体资金流水
     * 
     * @param unknown $shop_id            
     * @param unknown $user_id            
     * @param unknown $title            
     * @param unknown $money            
     * @param unknown $account_type            
     * @param unknown $type_alis_id
     * @param unknown $remark            
     */
    public function addAccountRecords($shop_id, $user_id, $title, $money, $account_type, $type_alis_id, $remark,$website_id=0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $account_model = new VslAccountRecordsModel();
        $plat_obj = $this->getPlatformAccount();
        $balance = $plat_obj["balance"];
        $data = array(
            "serial_no" => 'PT'.getSerialNo(),
            "shop_id" => $shop_id,
            "user_id" => $user_id,
            "title" => $title,
            "money" => $money,
            "account_type" => $account_type,
            "type_alis_id" => $type_alis_id,
            "balance" => $balance,
            "create_time" => time(),
            "remark" => $remark,
            "website_id" => $websiteid
        );
        $res = $account_model->save($data);
        return $res;
    }

    /**
     * 查询平台账户的资金情况
     * 
     * @return unknown
     */
    public function getPlatformAccount($website_id=0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $plat_model = new VslAccountModel();
        $plat_obj = $plat_model->getInfo([
            "website_id" => $websiteid
        ]);
        $plat_obj["balance"] = $plat_obj["account_order_money"];
        return $plat_obj;
    }

    /**
     * **************************************************平台账户--End****************************************************************
     */
    /** 开始处理店铺分账信息 **/
    public function separateAccounts($separate_info){
        //获取汇聚支付配置信息 如果开启分账则写入记录 未开启则不写入 以及写入是否开启自动
        $webConfig = new WebConfig();
        $joinpay_infos = $webConfig->getConfig($this->instance_id, 'JOINPAY', $this->website_id);
        $joinpay_info = $joinpay_infos['value'];
        if($joinpay_info['joinpay_alt_is_use'] != 1){
            return;
        }
        $separate_info['joinpay_alt_is_auto'] = $joinpay_info['joinpay_alt_is_auto'] == 1 ? 1 : 0;
        try {
            $shopSeparateModel = new VslShopSeparateModel();
            $info = $shopSeparateModel->getInfo(['order_id'=>$separate_info['order_id']],'id');
            if($info){
                return;
            }
            $shopSeparateModel->save($separate_info);
        } catch (\Throwable $th) {
            recordErrorLog($th);
        }
    }
    /**
     * 更新供应商的营业额、冻结金额
     */
    public function updateSupplierFreezingMoney($supplier_id, $money, $website_id, $type)
    {
        $account_model = new VslSupplierAccountModel();
        $account_info = $account_model->getInfo(['supplier_id' => $supplier_id,'website_id' => $website_id],'supplier_entry_money,supplier_freezing_money');
        // 没有的话新建账户
        if (empty($account_info)) {
            $data = array(
                'supplier_id' => $supplier_id,
                'website_id' => $website_id,
            );
            $account_model->save($data);
            $account_info = $account_model->getInfo(['supplier_id' => $supplier_id,'website_id' => $website_id],'supplier_entry_money,supplier_freezing_money');
        }
        if($type == 1) {//支付
            $data1 = array(
                "supplier_entry_money" => $account_info["supplier_entry_money"] + $money,
                "supplier_freezing_money" => $account_info["supplier_freezing_money"] + $money,
            );
        }elseif ($type == 5) {//退款
            $data1 = array(
                "supplier_freezing_money" => $account_info["supplier_freezing_money"] - $money >= 0 ? $account_info["supplier_freezing_money"] - $money : 0,
            );
        }

        $retval = $account_model->isUpdate(true)->save($data1, [
            'supplier_id' => $supplier_id,
            'website_id' => $website_id
        ]);
        return $retval;
    }

    /**
     * 更新供应商的可提现金额、冻结金额
     */
    public function updateSupplierTotalMoney($supplier_id, $money, $website_id)
    {
        $account_model = new VslSupplierAccountModel();
        $account_info = $account_model->getInfo(['supplier_id' => $supplier_id,'website_id' => $website_id],'supplier_total_money,supplier_freezing_money');
        // 没有的话新建账户
        if (empty($account_info)) {
            $data = array(
                'supplier_id' => $supplier_id,
                'website_id' => $website_id,
            );
            $account_model->save($data);
            $account_info = $account_model->getInfo(['supplier_id' => $supplier_id,'website_id' => $website_id],'supplier_total_money,supplier_freezing_money');
        }
        $data1 = array(
            "supplier_total_money" => $account_info["supplier_total_money"] + $money,
            "supplier_freezing_money" => $account_info["supplier_freezing_money"] - $money >= 0 ? $account_info["supplier_freezing_money"] - $money : 0,
        );

        $retval = $account_model->isUpdate(true)->save($data1, [
            'supplier_id' => $supplier_id,
            'website_id' => $website_id
        ]);
        return $retval;
    }

    /**
     * 添加供应商资金流水
     */
    public function addSupplierAccountRecords($serial_no, $supplier_id, $money, $account_type, $type_alis_id, $remark, $title,$website_id = 0)
    {
        if($website_id){
            $websiteid = $website_id;
        }else{
            $websiteid = $this->website_id;
        }
        $model = new VslSupplierAccountRecordsModel();

        $data = array(
            'serial_no' => $serial_no,
            'account_type' => $account_type,
            'money' => $money,
            'type_alis_id' => $type_alis_id,
            'remark' => $remark,
            'title' => $title,
            'create_time' => time(),
            'website_id' => $websiteid,
            'supplier_id' => $supplier_id,
        );
        $res = $model->save($data);
        return $res;
    }
    
    /* (non-PHPdoc)
    * @see 财务对账
    */
    public function getFinanceCount($start_date,$end_date)
    {
        $start_date = strtotime($start_date);//date('Y-m-d 00:00:00', time());
        $end_date = strtotime($end_date);//date('Y-m-d 00:00:00', strtotime('this day + 1 day'));
        $end_date = $end_date+24*3600-1;
        $account_info = array();
        $account_records = new VslAccountRecordsModel();
        //平台入账总金额(订单支付和充值)
        $condition4 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>12];
        $account_info['wx_balance_entry'] = $account_records->getSum($condition4,'money');//余额微信充值
        $condition30 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>13];
        $account_info['ali_balance_entry'] = $account_records->getSum($condition30,'money');//余额支付宝充值
		$condition32 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>47];
        $account_info['gp_balance_entry'] = $account_records->getSum($condition32,'money');//余额GlobePay充值
        $account_info['balance_entry'] = $account_info['wx_balance_entry'] + $account_info['ali_balance_entry'] + $account_info['gp_balance_entry'];//余额充值
        //平台成交额(订单支付)
        $condition1 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>14];
        $account_info['balance_payment'] = $account_records->getSum($condition1,'money');//余额支付
        $condition2 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>15];
        $account_info['wx_payment'] = $account_records->getSum($condition2,'money');//微信支付
        $condition3 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>16];
        $account_info['ali_payment'] = $account_records->getSum($condition3,'money');//支付宝支付
		$condition31 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>46];
        $account_info['gp_payment'] = $account_records->getSum($condition31,'money');//GlobePay支付
        $account_info['platform_trunover'] = $account_info['balance_payment']+$account_info['wx_payment']+$account_info['ali_payment']+$account_info['gp_payment'];
        $account_info['wx_payments'] = $account_info['wx_payment']+$account_info['wx_balance_entry'];//微信入账
        $account_info['ali_payments'] = $account_info['ali_payment']+$account_info['ali_balance_entry'];//支付宝入账
		$account_info['gp_payments'] = $account_info['gp_payment']+$account_info['gp_balance_entry'];//GlobePay入账
        $account_info['account_entry'] = $account_info['wx_payment']+$account_info['ali_payment']+$account_info['balance_entry'];
        //自营成交额
        $condition5 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'shop_id'=>0,'account_type'=>14];
        $account_info['self_balance_payment'] = $account_records->getSum($condition5,'money');//自营余额支付
        $condition6 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'shop_id'=>0,'account_type'=>15];
        $account_info['self_wx_payment'] = $account_records->getSum($condition6,'money');//自营微信支付
        $condition7 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'shop_id'=>0,'account_type'=>16];
        $account_info['self_ali_payment'] = $account_records->getSum($condition7,'money');//自营支付宝支付
        $account_info['self_trunover'] = $account_info['self_balance_payment']+$account_info['self_wx_payment']+$account_info['self_ali_payment'];
        //后台余额调整
        $condition8 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>11];
        $account_info['balance_adjust'] = $account_records->getSum($condition8,'money');
        //余额提现金额
        $condition9 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>7];
        $account_info['wx_balance_withdraw'] =$account_records->getSum($condition9,'money');//余额微信提现
        $condition10 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>8];
        $account_info['ali_balance_withdraw'] =$account_records->getSum($condition10,'money');//余额支付宝提现
        $condition11 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>36];
        $account_info['bank_balance_withdraw'] =$account_records->getSum($condition11,'money');//余额银行卡提现
        $account_info['balance_withdraw'] = $account_info['wx_balance_withdraw']+$account_info['ali_balance_withdraw']+$account_info['bank_balance_withdraw'];
        //佣金提现金额
        $condition12 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>1];
        $account_info['wx_commission_withdraw'] =$account_records->getSum($condition12,'money');//佣金微信提现
        $condition13 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>2];
        $account_info['ali_commission_withdraw'] =$account_records->getSum($condition13,'money');//佣金支付宝提现
        $condition14 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>38];
        $account_info['bank_commission_withdraw'] =$account_records->getSum($condition14,'money');//佣金银行卡提现
        $account_info['commission_withdraw'] = $account_info['wx_commission_withdraw']+$account_info['ali_commission_withdraw']+$account_info['bank_commission_withdraw'];
        //店铺提现金额
        $condition15 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>19];
        $account_info['wx_shop_withdraw'] =$account_records->getSum($condition15,'money');//店铺微信提现
        $condition16 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>20];
        $account_info['ali_shop_withdraw'] =$account_records->getSum($condition16,'money');//店铺支付宝提现
        $condition17 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>40];
        $account_info['bank_shop_withdraw'] =$account_records->getSum($condition17,'money');//店铺银行卡提现
        $account_info['shop_withdraw'] = $account_info['wx_shop_withdraw']+$account_info['ali_shop_withdraw']+$account_info['bank_shop_withdraw'];
        //收益提现金额
        $condition26 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>28];
        $account_info['wx_microshop_withdraw'] =$account_records->getSum($condition26,'money');//收益微信提现
        $condition27 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>29];
        $account_info['ali_microshop_withdraw'] =$account_records->getSum($condition27,'money');//收益支付宝提现
        $condition28 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>37];
        $account_info['bank_microshop_withdraw'] =$account_records->getSum($condition28,'money');//收益银行卡提现
        $account_info['microshop_withdraw'] = $account_info['wx_microshop_withdraw']+$account_info['ali_microshop_withdraw']+$account_info['bank_microshop_withdraw'];
        //微信提现
        $account_info['wx_withdraw'] = $account_info['wx_balance_withdraw']+$account_info['wx_commission_withdraw']+$account_info['wx_shop_withdraw']+$account_info['wx_microshop_withdraw'];
        //支付宝提现
        $account_info['ali_withdraw'] = $account_info['ali_balance_withdraw']+$account_info['ali_commission_withdraw']+$account_info['ali_shop_withdraw']+$account_info['ali_microshop_withdraw'];
        //银行卡提现
        $account_info['bank_withdraw'] = $account_info['bank_balance_withdraw']+$account_info['bank_commission_withdraw']+$account_info['bank_shop_withdraw']+$account_info['bank_microshop_withdraw'];
        //提现总金额
        $account_info['account_withdrawals'] = $account_info['balance_withdraw']+$account_info['shop_withdraw']+$account_info['commission_withdraw']+$account_info['microshop_withdraw'];
        //赠送佣金
        $condition18 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>5];
        $account_info['commission_total'] =$account_records->getSum($condition18,'money');
        //赠送收益
        $condition29 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>39];
        $account_info['microshop_total'] =$account_records->getSum($condition29,'money');
        //赠送分红
        $condition19 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>22];
        $account_info['bonus_total'] =$account_records->getSum($condition19,'money');
        //平台优惠
        $condition20 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>23];
        $account_info['platform_preference'] =$account_records->getSum($condition20,'money');
        //平台利润
        $condition25 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>41];
        $account_info['platform_profit'] =$account_records->getSum($condition25,'money');
        //个人所得税
        $condition21 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>['in','24,27']];
        $account_info['income_tax_total'] =$account_records->getSum($condition21,'money');
        //手续税
        $condition22 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>25];
        $account_info['service_charge_total'] =$account_records->getSum($condition22,'money');
        //积分抵扣
        $condition23 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>33];
        $account_info['point_discount_total'] =$account_records->getSum($condition23,'money');
        //订单退款
        $condition24 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>18];
        $account_info['refund_total'] = abs($account_records->getSum($condition24,'money'));
        //店铺待结算金额
        if(getAddons('shop', $this->website_id)){
            $shop_account = new VslShopWithdrawModel();
            $condition24 = ['ask_for_date' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'status'=>3];
            $account_info['shop_withdraw3'] =$shop_account->getSum($condition24,'cash');//已打款
            $shop_account_records = new VslAccountRecordsModel();
            $condition23 = ['create_time' => [[">",$start_date],["<",$end_date]],'website_id'=>$this->website_id,'account_type'=>50];
            $account_info['shop_total'] =$shop_account_records->getSum($condition23,'money');//入账金额
            $account_info['shop_preference'] =$account_info['shop_total']-$account_info['shop_withdraw3'];
        }
        return $account_info;
    }
    

}