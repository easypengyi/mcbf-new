<?php

namespace app\cli\controller;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use data\model\VslExcelsModel;
use data\service\ExcelsExport;

class ExcelsTasks extends Command {

    protected function configure() {
        $this->setName('excel_tasks_vsl')->setDescription('商城Excel导出任务处理');//
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output) {
        if(config('is_high_powered')){
            return;
        }
        //商城导出
        try {
            $excelsModel = new VslExcelsModel();
            $firstData = $excelsModel->getQuery(['status' => 0], '*', 'addtime asc');
            if ($firstData) {
                // status: 0未执行，1成功 2失败 3等待中 4处理中 5部分完成
                foreach ($firstData as $val) {
                    debugLog($val['id'], '==>cli导出-ID<==');
                    $excelsService = new ExcelsExport();
                    $condition = ['id' => $val['id']];
                    $excelsModel->save(['status' => 4], $condition);
                    //执行解压，上传
                    $res = $excelsService->exportExcel($val['id']);//返回0：错误 1：正确 2：部分错误
                }
            }
        } catch (\Exception $e) {
            //$msg = 'ExcelsTasks: '.$e->getMessage();
            //$log_dir = getcwd() . '/excels_export.log';
            //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
        }

        //超时状态的变更为超时错误
        try {
            $excelsModel = new VslExcelsModel();
            $firstData = $excelsModel->getQuery(['status' => 4], '*', 'addtime asc');
            if ($firstData) {
                // status: 0未执行，1成功 2失败 3等待中 4处理中 5部分完成
                foreach ($firstData as $val) {
                    $excelsService = new ExcelsExport();
                    $condition = ['id' => $val['id']];
                    //查询执行时间
                    $time = time()-$val['addtime'];
                    $t = $time / 3600;
                    if($t > 1){
                        $excelsModel->save(['status' => 2,'msg' => '执行超时，请缩小导出范围重新执行'], $condition);
                    }
                }
            }
        } catch (\Exception $e) {
            //$msg = 'ExcelsTasks: '.$e->getMessage();
            //$log_dir = getcwd() . '/excels_export.log';
            //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
        }
    }

}

?>