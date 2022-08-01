<?php

namespace app\cli\controller;

use addons\appointgivecoupon\model\VslAppointgivecouponModel;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class AppointgivecouponTask extends Command
{

    protected function configure()
    {
        $this->setName('appointgivecoupon_task_vsl')->setDescription('定向送券任务处理');//
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output)
    {
        if(config('is_high_powered')){
            return;
        }
        $appointgivecoupon = new \addons\appointgivecoupon\server\Appointgivecoupon();
        //while (1) {
            try {
                $appoint_give_coupon_mdl = new VslAppointgivecouponModel();
                $firstData = $appoint_give_coupon_mdl->getQuery(['status' => 0], '*', 'create_time asc');
                if (!$firstData) {
                    sleep(2);
                    continue;
                }
                //status: 0：等待执行   1：执行成功   2：执行失败  3：执行中
                foreach ($firstData as $val) {
                    $appointgivecoupon->updateAppointgivecoupon(['status' => 3], ['id' => $val['id']]);
                    //组装数据，执行任务
                    $appointgivecoupon->executeAppointgivecoupon($val['id']);
                }
            } catch (\Exception $e) {
                //$msg = 'AppointgivecouponTask: '.$e->getMessage();
                //$log_dir = getcwd() . '/appointgivecoupon.log';
                //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            }
        //}
    }

}

?>