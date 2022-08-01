<?php
namespace app\cli\controller;

use data\service\Events;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use think\Db;

class Test extends Command {

    protected function configure(){
        $this->setName('rabbitmq_test')->setDescription('41223132');
    }
    /**
     * 启动服务端服务
     * @return \lib\crontab\IssServer
     */
    public function execute(Input $input,Output $output){
        echo 'hello,word';
    }
}
?>
