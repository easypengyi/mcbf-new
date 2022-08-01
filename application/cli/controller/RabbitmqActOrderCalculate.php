<?php
namespace app\cli\controller;

use data\service\Events;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use think\Db;

class RabbitmqActOrderCalculate extends Command {

    protected function configure(){
        $this->setName('rabbitmq_act_order_calculate')->setDescription('订单分销分红计算处理');
    }
    /**
     * 启动服务端服务
     * @return \lib\crontab\IssServer
     */
    public function execute(Input $input,Output $output){
//        $host = config('rabbitmq.host');
//        $port = config('rabbitmq.port');
//        $user = config('rabbitmq.user');
//        $pass = config('rabbitmq.pass');
//        $exchange_name = config('rabbitmq.exchange_name');
//        $queue_name = config('rabbitmq.queue_name');
//        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
//        $channel = $connection->channel();
//        $channel->exchange_declare($exchange_name, 'fanout', false, true, false);
////        list($queue_name, ,) = $channel->queue_declare("", false, true, true, false);
//        $channel->queue_declare($queue_name, false, true, false, false);
//        $channel->queue_bind($queue_name, $exchange_name);
//        $callback = function ($msg) {
//            error_reporting(0);
            try{
                $order_id = 1;
//            $file_path = getcwd().'/test_rabbitmq.log';
//            file_put_contents($file_path, 'test_rabbitmq的订单id:'.$order_id.date('YmdHis').PHP_EOL, 8);
//                debugLog('进来时间：'.date('YmdHis').':'.$order_id);
                /*$data = json_decode($data, true);
                if ($data) {
                    $url = $data['url'].'?order_id='.$data['order_id'];
                }
                $curlObj = curl_init(); //初始化curl，
                curl_setopt($curlObj, CURLOPT_URL, $url); //设置网址
                curl_setopt($curlObj, CURLOPT_RETURNTRANSFER, 1); //将curl_exec的结果返回
                curl_setopt($curlObj, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($curlObj, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($curlObj, CURLOPT_HEADER, 0); //是否输出返回头信息
                $response = curl_exec($curlObj); //执行
                curl_close($curlObj); //关闭会话*/
                $events = new Events();
                $events->orderCalculate($order_id);
//                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            }catch(\Exception $e){
                //$file_path = getcwd().'/test_rabbitmq.log';
                //file_put_contents($file_path, $e->getMessage().PHP_EOL, 8);
            }
        }
//        $channel->basic_consume($queue_name, '', false, false, false, false, $callback);
//        while (count($channel->callbacks)) {
//            $channel->wait();
//        }
//    }
}
?>
