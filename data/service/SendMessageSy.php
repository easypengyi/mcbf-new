<?php

namespace data\service;

use data\service\Config;
use data\model\MessageCountModel;

class SendMessageSy extends BaseService {

    protected $timestamp = NULL;
    protected $username = NULL;
    protected $password = NULL;
    protected $alarm_num = NULL;
    protected $alarm_mobile = NULL;
    protected $alarm_num_master = NULL;
    protected $alarm_mobile_master = NULL;
    protected $jd_sign_name_master = NULL;
    protected $jd_sign_name = NULL;
    protected $count = 0;
    protected $url = 'http://api.shuyuanwl.com:8080/api/sms/';

    public function __construct() {
        parent::__construct();
        $configSer = new Config();
        $master_array = $configSer->getConfigMaster(0, 'MOBILEMESSAGE', 0, 1);

        $this->username = $master_array['sy_username'];
        $this->password = $master_array['sy_password'];
        $this->alarm_num_master = $master_array['alarm_num'];
        $this->alarm_mobile_master = $master_array['alarm_mobile'];
        $this->jd_sign_name_master = $master_array['jd_sign_name'];

        $this->timestamp = date('YmdHis', time());
        if ($this->website_id) {
            $messageCount = new MessageCountModel();
            $count = $messageCount->getInfo(['website_id' => $this->website_id], 'count');
            $this->count = $count ? $count['count'] : 0;
            $info_array = $configSer->getConfig(0, 'MOBILEMESSAGE', $this->website_id, 1);
            $this->alarm_num = $info_array['alarm_num'];
            $this->alarm_mobile = $info_array['alarm_mobile'];
            $this->jd_sign_name = $info_array['jd_sign_name'];
        }
    }

    public function sendsms($mobile, $content, $website_id = 0, $sign_master = 0) {
        if ($website_id) {
            $this->website_id = $website_id;
        }
        if ($this->website_id) {
            $configSer = new Config();
            $messageCount = new MessageCountModel();
            $count = $messageCount->getInfo(['website_id' => $this->website_id], 'count');
            $this->count = $count ? $count['count'] : 0;
            $info_array = $configSer->getConfig(0, 'MOBILEMESSAGE', $this->website_id, 1);
            $this->alarm_num = $info_array['alarm_num'];
            $this->alarm_mobile = $info_array['alarm_mobile'];
            $this->jd_sign_name = $info_array['jd_sign_name'];
        }
        if ($sign_master) {//使用平台签名
            if ($this->jd_sign_name_master) {
                $content = '【' . $this->jd_sign_name_master . '】' . $content;
            }
        } else {
            if ($this->jd_sign_name) {
                $content = '【' . $this->jd_sign_name . '】' . $content;
            }
        }

        $res = $this->sendPost($this->url . 'send', array(
            'mobiles' => $mobile,
            'content' => $content,
            'extno' => '',
            'batchno' => '',
        ));
        //$x = json_decode($pageContents, true);
        if ($res['code'] == 200) {
            $result['code'] = 0;
            $result['message'] = '发送成功';
            if ($this->website_id) {
                $addons = new Addons();
                $addons->reduceMessageCount(1, $this->website_id);
                if ($this->alarm_num && $this->count - 1 == $this->alarm_num) {
                    $addons->reduceMessageCount(1, $this->website_id);
                    $this->sendsmsAlbum($this->alarm_mobile, '短信余额数量到达预警数量,请及时充值', $sign_master);
                }
            }
            $remain = $this->queryBalance();
            if ($remain['count'] == $this->alarm_num_master) {
                $this->sendsmsAlbum($this->alarm_mobile_master, '短信余额数量为' . $remain['count'] . ',请及时充值', 1);
            }

            return $result;
        } else {
            return array('code' => -1, 'message' => $res['msg']);
        }
    }

    public function sendsmsAlbum($mobile, $content, $sign_master = 0) {
        if ($sign_master) {//使用平台签名
            if ($this->jd_sign_name_master) {
                $content = '【' . $this->jd_sign_name_master . '】' . $content ;
            }
        } else {
            if ($this->jd_sign_name) {
                $content = '【' . $this->jd_sign_name . '】' . $content;
            }
        }
        $this->sendPost($this->url . 'send', array(
            'mobiles' => $mobile,
            'content' => $content,
            'extno' => '',
            'batchno' => '',
        ));
    }

    public function sendPost($url, $post_data) {

        $post_data['account'] = $this->username;
        $post_data['password'] = $this->password;

        $postdata = http_build_query($post_data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'header' => 'Content-type:application/x-www-form-urlencoded',
                'content' => $postdata,
                'timeout' => 15 * 60
            )
        );
        $context = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        return json_decode($result,true);
    }
    
    /*
     * 查询余额,get方式
     */
    public function queryBalance() {
        $url = $this->url . 'queryBalance?account=' . $this->username . '&password=' . $this->password;
        $result = GetCurl($url);
        $return = ['balance' => 0,'count' => 0];
        if ($result['code'] != '200') {
            return $return;
        }
        $return['balance'] = $this->NumToStr($result['data']) * 1000;
        $return['count'] = intval($return['balance']/0.035);
        return $return;
    }

    function NumToStr($num) {
        if (stripos($num, 'e') === false)
            return $num;
        $num = trim(preg_replace('/[=\'"]/', '', $num, 1), '"'); //出现科学计数法，还原成字符串
        $result = "";
        while ($num > 0) {
            $v = $num - floor($num / 10) * 10;
            $num = floor($num / 10);
            $result = $v . $result;
        }
        return $result;
    }

}
