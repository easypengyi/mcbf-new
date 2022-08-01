<?php

namespace app\cli\controller;

use addons\delivery\model\VslDeliveryFileModel;
use addons\delivery\service\Delivery as Delivery;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class DeliveryByExcelTask extends Command
{

    protected function configure()
    {
        $this->setName('delivery_by_excel_task_vsl')->setDescription('导表发货任务处理');
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
        $deliveryServer = new Delivery();
        //while (1) {
            try {
                $deliveryFile = new VslDeliveryFileModel();
                $firstData = $deliveryFile->getQuery(['status' => 3], '*', 'create_time asc');
                if (!$firstData) {
                    sleep(2);
                    continue;
                }
                //status: 0未执行，1成功 2失败 3等待中 4处理中 5部分完成
                foreach ($firstData as $val) {
                    $condition = ['id' => $val['id']];
                    $deliveryServer->updateDeliveryFile(['status' => 4], $condition);
                    //执行解压，上传
                    $res = $deliveryServer->deliveryByExcel($val['excel_name'],$val['website_id'], $val['shop_id'], $val['supplier_id']);//返回0：错误 1：正确 2：部分错误
                    if ($res['code'] == 0) {//失败
                        $deliveryServer->updateDeliveryFile(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
                    } else if ($res['code'] == 2) {//部分成功
                        $deliveryServer->updateDeliveryFile(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
                    } else if ($res['code'] == 1) {//成功
                        $deliveryServer->updateDeliveryFile(['status' => 1], $condition);
                    }
                }
            } catch (\Exception $e) {
                //$msg = 'DeliveryByExcelTask: ' . $e->getMessage();
                //$log_dir = getcwd() . '/delivery_by_excel_task.log';
                //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            }
        //}
    }

}

?>