<?php

namespace app\cli\controller;

use addons\orderbarrage\model\VslOrderBarrageConfigModel;
use addons\orderbarrage\server\OrderBarrage;
use addons\wxmembermsg\server\WxMemberMsg as WxMemberMsgSer;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class OrderBarrageTask extends Command {

    protected function configure() {
        $this->setName('order_barrage_task')->setDescription('订单弹幕');
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output)
    {
        $order_barrage_config = new VslOrderBarrageConfigModel();
        $start_time = $current_res['rule']['start_time'];
        $end_time = $current_res['rule']['end_time'];
        //投放成功后，根据类型设置队列，若类型存在虚拟数据的话，入虚拟数据队列 key: $config_id_日开始点数日结束点数
        $start_key = strtotime(date('Y-m-d').' '.str_replace(',', ':', $start_time));
        $end_key = strtotime(date('Y-m-d').' '.str_replace(',', ':', $end_time));
        $redis_real_key = 'real_'.$current_res['rule']['rule_id'].'_'.$start_key.'_'.$end_key;

    }

}

?>