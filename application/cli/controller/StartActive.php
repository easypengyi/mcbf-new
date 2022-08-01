<?php

namespace app\cli\controller;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use data\model\VslActiveListModel;
use data\service\ActiveList;
class StartActive extends Command
{

    protected function configure()
    {
        $this->setName('start_active_status')->setDescription('开启活动');
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output)
    {
//        while (1) {
        try {
            echo 'test';
//            $activeListModel = new VslActiveListModel();
//            $activeList = $activeListModel->Query(['status' => 0,'stime'=> ['<=',time()]], 'aid');
//            if ($activeList) {
////                    sleep(2);
////                    continue;
//                //status: 2关闭
//                $activeListServer = new ActiveList();
//                foreach ($activeList as $val) {
//                    $activeListServer->startActive($val);
//                }
//            }
        } catch (\Exception $e) {
            //$msg = 'chekc_active_status: '.$e->getMessage();
            //$log_dir = getcwd() . '/active_list.log';
            //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
        }
//        }
    }

}

?>
