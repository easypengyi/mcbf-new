<?php

namespace app\cli\controller;

use addons\electroncard\model\VslElectroncardDataFileModel;
use addons\electroncard\server\Electroncard as ElectroncardServer;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class ElectroncardDataByExcelTask extends Command
{

    protected function configure()
    {
        $this->setName('electroncard_data_by_excel_task')->setDescription('电子卡密数据批量导入任务处理');
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output)
    {
        if(config('is_high_powerd')){
            return;
        }
        while (1) {
            try {
                $electroncardFile = new VslElectroncardDataFileModel();
                $firstData = $electroncardFile->getQuery(['status' => 3], '*', 'create_time asc');
                if (!$firstData) {
                    sleep(2);
                    continue;
                }
                //status: 0未执行，1成功 2失败 3等待中 4处理中 5部分完成
                foreach ($firstData as $val) {
                    $electroncard_server = new ElectroncardServer;
                    $condition = ['id' => $val['id']];
                    $electroncard_server->updateElectroncardFile(['status' => 4], $condition);
                    //执行解压，上传
                    $res = $electroncard_server->electroncardDataByExcel($val['excel_name'], $val['shop_id'], $val['website_id'], $val['electroncard_base_id']);//返回0：错误 1：正确 2：部分错误
                    if ($res['code'] == 0) {//失败
                        $electroncard_server->updateElectroncardFile(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
                    } else if ($res['code'] == 2) {//部分成功
                        $electroncard_server->updateElectroncardFile(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
                    } else if ($res['code'] == 1) {//成功
                        $electroncard_server->updateElectroncardFile(['status' => 1], $condition);
                    }
                }
            } catch (\Exception $e) {
                //$msg = 'ElectroncardDataByExcelTask: ' . $e->getMessage();
                //$log_dir = getcwd() . '/electroncard_data_by_excel_task.log';
                //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            }
        }
    }

}

?>