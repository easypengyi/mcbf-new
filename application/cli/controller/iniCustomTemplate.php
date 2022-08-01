<?php

namespace app\cli\controller;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use data\service\Customtemplate;

class iniCustomTemplate extends Command {

    protected function configure() {
        $this->setName('ini_custom')->setDescription('新装修初始化数据');
    }

    /**
     * 启动服务端服务
     * @param Input $input
     * @param Output $output
     * @return int|void|null
     */
    public function execute(Input $input, Output $output) {
        while (1) {
            try {
                $customSer = new Customtemplate();
                $customSer->cliIniTemplate();
            } catch (\Exception $e) {
                //$msg = tryCachErrorMsg($e);
                //$log_dir = 'public/ErrorLog/custom/custom_err.txt';
                //debugFile($msg,'装修初始化',  $log_dir);
            }
        }
    }

}

?>