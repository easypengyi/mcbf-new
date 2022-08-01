<?php

namespace app\cli\controller;

use addons\wxmembermsg\server\WxMemberMsg as WxMemberMsgSer;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class WxMsgTemplateSendTask extends Command {

    protected function configure() {
        $this->setName('wx_msg_template_send_task_vsl')->setDescription('微信群发会员消息');
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
        debugLog('群发消息测试');
        $templateSer = new WxMemberMsgSer();
        while (1) {
            try {
                $msg_template_ids = $templateSer->getMsgTemplates(['status' => 2],'id');
                if (!$msg_template_ids) {
                    sleep(2);
                    continue;
                }
                //status: 1无状态（已结束） 2请求群发 3群发中
                foreach ($msg_template_ids as $id) {
                    $templateSer->saveMsgTemplate(['status' => 3], ['id' => $id['id']]);
                    //组装数据，发送
                    $templateSer->createDataAndSendTemplateMsg($id['id']);
                }
            } catch (\Exception $e) {
                //$msg = 'WxMsgTemplateSendTask: '.$e->getMessage();
                //$log_dir = getcwd() . '/wx_msg_send.log';
                //file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            }
        }
    }

}

?>