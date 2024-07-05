<?php

namespace app\cli\controller;

use addons\bonus\model\VslAgentAccountRecordsModel;
use data\model\VslMemberAccountModel;
use data\model\VslMemberAccountRecordsModel;
use data\model\VslOrderModel;
use data\model\VslOrderTeamLogModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class TeamSettleTasks extends Command {

    protected function configure() {
        $this->setName('team_settle_task')->setDescription('定时执行团队分红');//
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output) {
        $website_id = 1;
        $params['website_id'] = $website_id;
        $params['type'] = 2;
        $team_status = getAddons('teambonus', $website_id);
        if ($team_status == 1) {
            hook('autoGrantTeamBonus', $params); //队长分红发放到账户余额，已发放分红增加，待发放分红减少
        }

        //结算七天之前的极差订单
        $end_time = time()-24*3600*7;
        $model = new VslOrderTeamLogModel();
        $where = [
            'team_cal_status' => 0,
            'create_time'=> array('<=', $end_time)
        ];
        $order_ids = $model->where($where)->column('order_id');
        $data_take_delivery = array(
            'shipping_status' => 2,
            'order_status' => array('in', [3,4]),
            'order_id'=> array('in', $order_ids)
        );
        $order_model = new VslOrderModel();
        $order_list = $order_model->getQuery($data_take_delivery, 'order_id,order_no');
        $ids = [];
        foreach ($order_list as $item){
            $ids[] = $item['order_id'];
//            var_dump($item['order_id'], $item['order_no']);die;
            $this->addOrderTeamLog($item['order_id'], $item['order_no']);
        }
//        var_dump($ids);die;
    }

    /**
     * 发放团队分红
     *
     * @param $orderId
     * @param $order_no
     * @return int|string
     */
    public function addOrderTeamLog($orderId, $order_no){
        $model = new VslOrderTeamLogModel();
        $where = [
            'order_id' => $orderId,
            'team_cal_status' => 0
        ];
        $log = $model->getInfo($where);
        if(!is_null($log)){
            $agentAccountRecordsModel = new VslAgentAccountRecordsModel();
            $memberAccountRecordsModel = new VslMemberAccountRecordsModel();
            $member_model = new VslMemberAccountModel();
            $lists = json_decode($log['team_bonus_details'], true);
            $time = time();
            $insertData = [];
            $insertRecordData = [];
            $member_model->startTrans();
            try{
                foreach ($lists as $key=>$item){
                    $member = $member_model->getInfo(['uid'=> $item['uid']]);
                    $balance = $member['balance'] + $item['commission'];
                    $records_no = 'TBS' . $time . rand(111, 999);
                    //添加团队分红日志
                    $data_records = array(
                        'uid' => $item['uid'],
                        'data_id' => $order_no,
                        'website_id' => $log['website_id'],
                        'records_no' => $records_no,
                        'bonus' => abs($item['commission']),
                        'text' => '订单完成,极差分红发放到账户余额',
                        'create_time' => $time,
                        'bonus_type' => 3, //团队分红
                        'from_type' => 4, //订单支付成功
                    );
                    array_push($insertData, $data_records);
                    $member_model->save(['balance'=> $balance], ['uid'=> $item['uid']]);
                    $data_account_log = array(
                        'uid'=> $item['uid'],
                        'shop_id'=> 0,
                        'account_type'=> 2,
                        'sign'=> 0,
                        'number'=> abs($item['commission']),
                        'from_type'=> 13,
                        'data_id'=> $order_no,
                        'text'=> '团队分红成功发放到余额',
                        'create_time'=> $time,
                        'website_id'=> $log['website_id'],
                        'records_no'=> date('YmdHis') . rand(111, 999),
                        'balance'=> $balance
                    );
                    array_push($insertRecordData, $data_account_log);
                }

                $agentAccountRecordsModel->saveAll($insertData);
                $memberAccountRecordsModel->saveAll($insertRecordData);
                $model->save(['team_cal_status'=> 1], $where); //更新记录
                $member_model->commit();
                return 1;
            }catch (\Exception $e) {
                $member_model->rollback();
                return $e->getMessage();
            }
        }
    }




}

?>