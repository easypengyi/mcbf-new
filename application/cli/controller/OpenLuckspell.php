<?php

namespace app\cli\controller;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use addons\luckyspell\server\Luckyspell as luckySpellServer;
use addons\luckyspell\model\VslLuckySpellRecordModel;
class OpenLuckspell extends Command
{

    protected function configure()
    {
        $this->setName('open_lucky_spell')->setDescription('幸运拼团开奖');
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
                
                $luckySpellOrderRecordModel = new VslLuckySpellRecordModel();
                $luckySpellServer = new luckySpellServer();
                $website_id = $luckySpellOrderRecordModel->Query(['status' => 1,'open_status'=>0], 'distinct website_id');
                if (!$website_id) {
                    sleep(2);
                    continue;
                }
                foreach ($website_id as $k => $v) {
                    //每个website_id 可以处理10条
                    $key = 'open_lucky_spell'.$v;
                    $lock = lock($key, 60); //redis分布式锁，防止并发。
                    if($lock){
                        $luckySpellServer->autoCreateLuckySpell($v);
                        unlock($key);
                    }
                }
            } catch (\Exception $e) {
                //debugFile($e->getMessage(), 'open_lucky_spell', 1111112);
            }
        }
    }

}

?>