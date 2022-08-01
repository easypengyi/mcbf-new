<?php

namespace app\cli\controller;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use data\model\VslActiveListModel;
use data\service\ActiveList;
class CheckActive extends Command
{

    protected function configure()
    {
        $this->setName('chekc_active_status')->setDescription('关闭过期活动');
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output)
    {
        while (1) {
            try {
                if(!config('is_high_powered')){
                    sleep(2);
                    continue;
                }
                $activeListModel = new VslActiveListModel();
                //不管所有状态 超时就关闭
                $activeList = $activeListModel->Query(['status' => ['neq',2],'etime'=> ['<=',time()]], '*');
                if (!$activeList) {
                    sleep(2);
                    continue;
                }
                //status: 2关闭
                $activeListServer = new ActiveList();
                foreach ($activeList as $val) {
                    $activeListServer->changeActive($val['act_id'],$val['type'],2,$val['website_id']);
                }
            } catch (\Exception $e) {
                //$msg = 'chekc_active_status: '.$e->getMessage();
                //$log_dir = getcwd() . '/active_list.log';
                //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            }
        }
    }

}

?>