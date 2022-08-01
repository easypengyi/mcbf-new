<?php
namespace app\cli\controller;

use addons\liveshopping\service\Liveshopping;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use PhpAmqpLib\Connection\AMQPStreamConnection;

class RabbitmqActLiveData extends Command {

    protected function configure(){
        $this->setName('rabbitmq_act_live_data')->setDescription('主播相关操作及数据统计');
    }
    /**
     * 启动服务端服务
     * @return \lib\crontab\IssServer
     */
    public function execute(Input $input,Output $output){
        $host = config('rabbitmq.host');
        $port = config('rabbitmq.port');
        $user = config('rabbitmq.user');
        $pass = config('rabbitmq.pass');
        $exchange_name = config('rabbitmq.live_data_exchange_name');
        $queue_name = config('rabbitmq.live_data_queue_name');
        $connection = new AMQPStreamConnection($host, $port, $user, $pass);
        $channel = $connection->channel();
        $channel->exchange_declare($exchange_name, 'fanout', false, true, false);
//        list($queue_name, ,) = $channel->queue_declare("", false, true, true, false);
        $channel->queue_declare($queue_name, false, true, false, false);
        $channel->queue_bind($queue_name, $exchange_name);
        $callback = function ($msg) {
            try{
                $param = $msg->body;
                $param_arr = json_decode($param, true);
                if(getAddons('liveshopping', $param_arr['website_id'])){
                    $live_shopping = new Liveshopping();
                    switch($param_arr['type']){
                        case 'gift':
                            $live_shopping->anchorGiftSend($param_arr);
                            break;
                        case 'like':
                            $live_shopping->addLikesRecord($param_arr);
                            break;
                        case 'share':
                            $live_shopping->addShareRecord($param_arr);
                            break;
                        case 'click_goods':
                            $live_shopping->addGoodsClickRecord($param_arr);
                            break;
//                        case 'add_car':
//                            break;
                        case 'now_online_num':
                            $live_shopping->addOnlineNum($param_arr);
                            break;
                        case 'watch_time':
                            $live_shopping->addUserWatchTime($param_arr);
                            break;
                        case 'comment':
                            $live_shopping->addUserComment($param_arr);
                            break;
                        case 'mute':
                            $live_shopping->actUserMute($param_arr);
                            break;
                        case 'leave':
                            $live_shopping->actAnchorLeave($param_arr);
                            break;
                    }
                }
                $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
            }catch(\Exception $e){
                //$file_path = getcwd().'/test_rabbitmq.log';
                //file_put_contents($file_path, $e->getMessage().PHP_EOL, 8);
            }
        };
        $channel->basic_consume($queue_name, '', false, false, false, false, $callback);
        while (count($channel->callbacks)) {
            $channel->wait();
        }
        $channel->close();
        $connection->close();
    }
}
?>