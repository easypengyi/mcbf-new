<?php

namespace app\cli\controller;

use addons\goodhelper\server\GoodHelper;
use addons\invoice\model\VslInvoiceFileModel;
use addons\invoice\server\Invoice as invoiceServer;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class InvoiceTask extends Command {

    protected function configure() {
        $this->setName('invoice_upload_task')->setDescription('发票助手批量上传任务处理');
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output) {
        //商品导入代码
        while (1) {
            try {
                $invoiceFile = new VslInvoiceFileModel();
                $firstData = $invoiceFile->getQuery(['status' => 3], '*', 'create_time asc');
                if (!$firstData) {
                    sleep(2);
                    continue;
                }
                //status: 0未执行，1成功 2失败 3等待中 4处理中 5部分完成
                foreach ($firstData as $val) {
                    $invoiceServer = new invoiceServer();
                    $condition = ['id' => $val['id']];
                    $invoiceServer->updateInvoiceFileInfo(['status' => 4], $condition);
                    //执行解压，上传
                    $res = $invoiceServer->addInvoiceByXls($val['excel_name'], $val['zip_name'], $val['shop_id'], $val['website_id']);//返回0：错误 1：正确 2：部分错误
                    if ($res['code'] == 0) {//失败
                        $invoiceServer->updateInvoiceFileInfo(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']] , $condition);
                    }else if ($res['code'] == 2) {//部分成功
                        $invoiceServer->updateInvoiceFileInfo(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
                    }else if ($res['code'] == 1) {//成功
                        $invoiceServer->updateInvoiceFileInfo(['status' => 1], $condition);
//                        $invoiceFile->delData(['id' => $val['id']]);//成功的删除
                    }
                }
            } catch (\Exception $e) {
                //$msg = 'InvoiceTask: '.$e->getMessage();
                //$log_dir = getcwd() . '/invoice_import.log';
                //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            }
        }
    }

}

?>