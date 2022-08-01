<?php

namespace app\cli\controller;

use think\console\Command;
use think\console\Input;
use think\console\Output;
use data\model\VslActiveListModel;
use data\service\ActiveList;
use addons\luckyspell\server\Luckyspell as luckySpellServer;
use addons\luckyspell\model\VslLuckySpellOrderRecordModel;
class SendLuckspell extends Command
{

    protected function configure()
    {
        $this->setName('send_lucky_spell')->setDescription('幸运拼团发放奖励');
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
//            try {
//                $luckySpellOrderRecordModel = new VslLuckySpellOrderRecordModel();
//            $luckySpellServer = new luckySpellServer();
//            $website_id = $luckySpellOrderRecordModel->Query(['send_status' => 1], 'distinct website_id');
//                if (!$website_id) {
//                    sleep(2);
//                    continue;
//                }
//                foreach ($website_id as $k => $v) {
//                    //每个website_id 可以处理10条
//                    $key = 'send_lucky_spell'.$v;
//                    $lock = lock($key, 60); //redis分布式锁，防止并发。
//                    if($lock){
//                        $luckySpellServer->autoSendLuckySpellFailureReward($v);
//                        unlock($key);
//                    }
//                }
//            } catch (\Exception $e) {
//                debugFile($e->getMessage(), 'send_lucky_spell', 1111112);
//            }
//        }
        try {
            $luckySpellOrderRecordModel = new VslLuckySpellOrderRecordModel();
            $luckySpellServer = new luckySpellServer();
            $website_id = $luckySpellOrderRecordModel->Query(['send_status' => 1], 'distinct website_id');
            if (!$website_id) {
                return;
            }
            foreach ($website_id as $k => $v) {
                //每个website_id 可以处理10条
                $key = 'send_lucky_spell'.$v;
                $lock = lock($key, 60); //redis分布式锁，防止并发。
                if($lock){
                    $luckySpellServer->autoSendLuckySpellFailureReward($v);
                    unlock($key);
                }
            }
        } catch (\Exception $e) {
            //debugFile($e->getMessage(), 'send_lucky_spell', 1111112);
        }
    }

}

?>