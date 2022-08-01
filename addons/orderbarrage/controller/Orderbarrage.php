<?php

namespace addons\orderbarrage\controller;

use addons\orderbarrage\model\VslOrderBarrageConfigModel;
use addons\orderbarrage\model\VslOrderBarrageRuleModel;
use addons\orderbarrage\model\VslOrderBarrageVirtualModel;
use addons\orderbarrage\Orderbarrage as baseOrderBarrage;
use data\model\AlbumPictureModel;
use addons\orderbarrage\server\OrderBarrage as OrderBarrageServer;
use data\model\UserModel;
use data\model\VslOrderModel;
use data\service\AddonsConfig;
use think\Db;

/**
 * 商品助手控制器OrderBarrge
 * Class Orderbarrage
 * @package addons\orderbarrage\controller
 */
class Orderbarrage extends baseOrderBarrage {

    const ORDER_BARRAGE_VIRTUAL_QUEUE = 'order_barrage_virtual_queue';//虚拟队列（虚拟+真实）队列键名
    const ORDER_BARRAGE_TRUE_QUEUE = 'order_barrage_true_queue';//真实队列键名，下单就存入该队列
    const ORDER_BARRAGE_CONFIG = 'order_barrage_config';//配置redis缓存
    const ORDER_BARRAGE_VIRTUAL_NUMS = 'order_barrage_virtual_nums';//虚拟订单条数
    protected $virtual_default_nums = 100; //虚拟条数
    protected $virtual_queue;
    protected $true_queue;
    protected $config;

    public function __construct()
    {
        parent::__construct();
        $this->virtual_queue = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_VIRTUAL_QUEUE;//eg 2_0_order_barrage_virtual_queue
        $this->true_queue = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_TRUE_QUEUE;//eg 2_0_order_barrage_true_queue
        $this->config = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_CONFIG;//eg 2_0_order_barrage_config
        $this->virtual_nums = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_VIRTUAL_NUMS;//eg 2_0_order_barrage_virtual_nums
    }

    /**
     * 订单弹幕配置修改
     * @return array
     */
    public function patchOrderBarrageSetting()
    {
        if (request()->isAjax()) {
            $config_id = request()->post('config_id') ?: '';//配置id
            $state = request()->post('state');//订单弹幕 1开启 0关闭
            $type = request()->post('type');//订单弹幕类型 1真实数据 2 虚拟数据 3真实+虚拟
            $is_circle = request()->post('is_circle');//循环投放 0不循环 1循环
            $use_place = request()->post('use_place');//展示模块 1商城首页
            $show_time = request()->post('show_time');//弹幕停留时间
            $rule = request()->post('rule');//字符串拼接的弹幕规则 'rule_id ¦开始时段 ¦结束时段 ¦投放量 ¦投放间隔 §'
            $barrageServer = new OrderBarrageServer();
            try {
                //订单弹幕配置表
                $config = new VslOrderBarrageConfigModel();
                $config_condition = [
                    'website_id' => $this->website_id,
                    'shop_id' => $this->instance_id
                ];
                if ($config_id) {
                    $config_condition['id'] = $config_id;
                }
                $order_barrage_res = $config->getInfo($config_condition);//先查询是否存在
                $config_arr = [
                    'website_id' => $this->website_id,
                    'shop_id' => $this->instance_id,
                    'state' => $state,
                    'type' => $type,
                    'use_place' => $use_place,
                    'is_circle' => $is_circle,
                    'show_time' => $show_time
                ];
                if ($order_barrage_res) {//存在，更新
                    $config_id = $config->saveGetPrimaryKey($config_arr , $config_condition);
                    //删除rule表，新建
//                    $ruleModel = new VslOrderBarrageRuleModel();
//                    $ruleModel->delData(['config_id' => $config_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
                } else{//新增
                    $config_id = $config->saveGetPrimaryKey($config_arr);
                }
    
                // 订单弹幕规则处理
                $rule_array = explode('§', $rule);
                $rule_arr = [];
                foreach ($rule_array as $key => $rule) {
                    $r_arr = explode("¦" , $rule);
                    $start_date_arr = explode(',', $r_arr[1]);
                    $end_date_arr = explode(',', $r_arr[2]);
                    $start_date = $start_date_arr[0] * 3600 + $start_date_arr[1] * 60 + $start_date_arr[2];
                    $end_date = $end_date_arr[0] * 3600 + $end_date_arr[1] * 60 + $end_date_arr[2];
                    $rule_arr[] = [
                        'website_id'    => $this->website_id,
                        'shop_id'       => $this->instance_id,
                        'start_time'    => $r_arr[1],/*字符串13,30,30表示时间段13:30:30*/
                        'end_time'      => $r_arr[2],/*字符串13,30,30表示时间段13:30:30*/
                        'start_date'    => $start_date,
                        'end_date'      => $end_date,
                        'virtual_num'   => $r_arr[3] ?: 0,
                        'space_end_time'=> $r_arr[4] ?: 0,
                        'is_default'    => $key == 0 ? 1 : 0,//第一个才是默认数据
                        'config_id'     => $config_id,
                    ];
                }
    
                //TODO 如果保存的规则和存储一致则不处理，否则删掉新建
                $ruleModel = new VslOrderBarrageRuleModel();
                $rule_condition = ['config_id' => $config_id, 'website_id' => $this->website_id, 'shop_id' => $this->instance_id];
                $old_rule_data = $ruleModel->getQuery($rule_condition,'rule_id,website_id,shop_id,start_time,end_time,virtual_num,space_end_time,is_default,config_id,start_date,end_date','rule_id asc');
                $compareRes = $barrageServer->compareRuleIsChange($old_rule_data,$rule_arr);
                if(config('is_high_powered')){
                    $redis = connectRedis();
                }
                if ($compareRes){
                    # 批量插入
                    if(config('is_high_powered')){
                        $configModel = new VslOrderBarrageConfigModel();
                        //删掉之前先取出rule_id、start_date、end_date
                        $config_info = $configModel->getInfo(['id' => $config_id]);
                        foreach($old_rule_data as $k => $rule_info){
                            if($config_info['type'] == 2 || $config_info['type'] == 3){
                                $redis_virt_key = 'virt_'.$rule_info['rule_id'].'_'.$rule_info['start_date'].'_'.$rule_info['end_date'];//virt_102_1618367947_1618390800
                                $redis->del($redis_virt_key);//删除掉原来存储的数据
                            }
                        }
                    }
                    $ruleModel->delData($rule_condition);
                    $rule_data = $ruleModel->saveAll($rule_arr);
                    
                    # 更新弹幕规则redis
                    $config_arr['rule'] = $rule_data;
                    // 订单弹幕redis配置处理
                    $barrageServer->putOrderBarrageConfigOfRedis($config_arr);
                }
                //用户修改了配置，要告诉前端
                if ($order_barrage_res || $compareRes){
                    $barrageServer->putOrderBarrageChangeRuleState();
                }
                $data = [
                    'addons' => self::$addons_name,
                    'value' => $config_arr,
                    'desc' => "订单弹幕配置",
                    'is_use' => $state
                ];
                $addonsConfigSer = new AddonsConfig();
                $res = $addonsConfigSer->setAddonsConfig($data);
                if ($res) {
                    if(config('is_high_powered')){
                        //优化为websocket推送
//                        $rule_list = $ruleModel->getQuery(['config_id' => $config_id], '', '');

                        $rule_list = $config::get(['id' => $config_id], ['rule']);
                        foreach($rule_list['rule'] as $k => $rule_info){
                            $rule_id = $rule_info['rule_id'];
                            //投放成功后，根据类型设置队列，若类型存在虚拟数据的话，入虚拟数据队列 key: $config_id_日开始点数日结束点数
                            $start_key = $rule_info['start_date'];
                            $end_key = $rule_info['end_date'];
                            $redis_virt_key = 'virt_'.$rule_id.'_'.$start_key.'_'.$end_key;//virt_102_1618367947_1618390800
                            //判断类型是否为虚拟
                            if($rule_list['type'] == 2 || $rule_list['type'] == 3){
                                $condition0 = [
                                    'website_id' => $this->website_id,
                                    'state' => 0,
                                ];
                                $virtual_num = $rule_info['virtual_num'] ? : $this->virtual_default_nums;
                                $barrage_list = $barrageServer->getOrderBarrageVirtualList($condition0, $virtual_num);
                                foreach($barrage_list['data'] as $k => $barrage_info){
                                    $barrage_info_str = json_encode($barrage_info);
                                    $redis->lpush($redis_virt_key, $barrage_info_str);
                                    $virtual_arr[$k]['id'] = $barrage_info['id'];
                                    $virtual_arr[$k]['state'] = 2;
                                }
                                if($virtual_arr){
                                    $virtual = new VslOrderBarrageVirtualModel();
                                    $virtual->saveAll($virtual_arr);
                                }
                            }
                        }
                    }
                    $this->addUserLog('订单弹幕设置',$res);
                    setAddons(self::$addons_name, $this->website_id, $this->instance_id);
                }
                
                return AjaxReturn(SUCCESS);
            } catch (\Exception $e) {
                return AjaxReturn(FAIL,[], $e->getMessage());
            }
        }
    }
    /**
     * 获取订单弹幕规则
     */
    public function getOrderBarrageRule()
    {
        $barrage = new OrderBarrageServer();
        $barrageConfig = $barrage->getOrderBarrageConfigInfo(
            [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id
            ],
            'id'
        );

        $rule_list = $barrage->getOrderBarrageRulesList(
            [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'config_id' => $barrageConfig['id']
            ]);
        foreach ($rule_list as $key => $rule) {
            $time = date('H:i:s', $rule['start_time']) . '-' . date('H:i:s', $rule['end_time']);
            $rule_list[$key]['time'] = $time;
        }
        return ['code'=> 1, 'data' => $rule_list];
    }

    /**
     * 前端获取商城订单弹幕规则
     * @return array|\multitype
     * @throws \think\Exception\DbException
     */
    public function getOrderBarrageRuleApiOld()
    {
        //TODO... 暂时不用
        $barrage = new OrderBarrageServer();
        $list = $barrage->getOrderBarrageConfigOfRule();

        if (!$list || $list['state'] == 0) {
            return ['code' => -1, 'message' => '应用关闭！'];
        }
        $rules = [];
        foreach ($list['invoice_config'] as $key => $config)
        {
            $rules[$key] = [
                'rule_id' => $config['rule_id'],
                'start_time' => $config['start_time'],
                'end_time' => $config['end_time'],
                'virtual_num' => $config['virtual_num'],
                'space_start_time' => $config['space_start_time'],
                'space_end_time' => $config['space_end_time'],
            ];
        }
        $rules = arrSortByValue($rules, 'start_time', 'ASC');
        $return_data = [];
        if ($list){
            $return_data = [
                'type' => $list['type'],
                'use_place' => $list['type'],
                'is_circle' => $list['is_circle'],
                'shop_id' => $list['shop_id'],
                'state' => $list['state'],
//            'rule' => $rules,//该条弹幕规则
            ];
        }

        return AjaxReturn(SUCCESS, $return_data);
    }

    /**
     * 删除订单弹幕规则
     * @return \multitype
     */
    public function deleteOrderBarrageRule()
    {
        if (request()->isAjax()) {
            $rule_id = request()->post('rule_ie') ?: '';//配置id
            if (!$rule_id) {
                return AjaxReturn(-1);
            }
            $barrage = new OrderBarrageServer();
            $condition = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'rule_id' => $rule_id
            ];
            $result = $barrage->deleteOrderBarrageRule($condition);

            return AjaxReturn(SUCCESS);
        }
    }

    /**
     * 初始化队列,重置队列
     * @param int $virtual_num int [队列条数]
     */
//    public function initOrderBarrageQueue($virtual_num = 0)
//    {
//        //重新构建队列（只用重构虚拟队列）
//        $barrage = new OrderBarrageServer();
//        $condition = [
////            'website_id' => $this->website_id,
////            'shop_id' => $this->instance_id,
//            'state' => 0,
//        ];
//        $barrage->postOrderBarrageVirtualQueue($condition, $virtual_num);
//    }

    /**
     * API - 获取订单弹幕
     * @return \multitype
     */
    public function getOrderBarrageApi()
    {
        //1、真实数据：从真实队列取出，从真实队列取出至多10条(筛选至多前1分钟) + 当前规则 + 下一次规则 + 是否用户改了配置状态state。
        //2、虚拟数据：从虚拟队列取出，返回至多100条（或者设置值） + 当前规格 + 下一次规则 + 是否用户改了配置状态state。
            # 前端带type表示是否第二次（second_request=1)请求。如果第二次且不循环则不返回数据; 如果第二次且循环则返回规则数据
        //3、真实/虚拟：优先真实队列+虚拟队列，返回至多100条（或者设置值）+ 当前规则 + 下一次规则 + 是否用户改了配置状态state。
            # 前端带type表示是否第二次（second_request=1)请求。如果第二次且不循环则不返回数据; 如果第二次且循环则返回规则数据
        //todo... 根据当前时间，（如果有配置返回最小100条记录）
        $second_request = request()->post('second_request');
        $change_rule_state = request()->post('change_rule_state');
        $barrage = new OrderBarrageServer();
        $currentRes = $barrage->getCurrentTimeOfOrderBarrageRule();
        if (!$currentRes || $currentRes['state'] == 0){return AjaxReturn(SUCCESS);}
        if (!$currentRes['rule']){return AjaxReturn(SUCCESS);}
        if ($change_rule_state==1){
            $barrage->delOrderBarrageChangeRuleState();
        }
        
        $queueRes = $barrage->getOrderBarrageQueue($currentRes['type'], $currentRes['rule']['virtual_num'],$currentRes['is_circle'],$second_request);
        //后台是否修改了状态
        $change_rule_state = $barrage->getOrderBarrageChangeRuleState();
        //获取相邻下一时段的规则配置
        $nextRule = $barrage->getNextTimeOfOrderBarrageRule();
        $nextRule = [
            'start_time' => $nextRule['start_time_unix'],
            'end_time' => $nextRule['end_time_unix'],
            'virtual_num' => $nextRule['virtual_num'],
            'space_time' => $nextRule['space_end_time'],
            'is_circle' => $nextRule['is_circle'],
            'use_place' => $currentRes['use_place'],
            'show_time' => $currentRes['show_time'],
        ];
        //当前规则
        $currentRule = [
            'start_time' => $currentRes['rule']['start_time_unix'],
            'end_time' => $currentRes['rule']['end_time_unix'],
            'virtual_num' => $currentRes['rule']['virtual_num'],
            'space_time' => $currentRes['rule']['space_end_time'],
            'is_circle' => $currentRes['is_circle'],
            'use_place' => $currentRes['use_place'],
            'show_time' => $currentRes['show_time'],
        ];
        
        $returnData = [
            'change_rule_state'  => $change_rule_state ?: 0,//用户是否改了配置规则
            'next_rule' => $nextRule,
            'current_rule' => $currentRule,
            'current_data' => $queueRes,
        ];
//        $returnData = [
////            'state'         => $currentRes['state'],//开启/关闭
////            'type'          => $currentRes['type'],//订单弹幕类型 1真实数据 2 虚拟数据 3真实+虚拟
////            'use_place'     => $currentRes['use_place'],//展示模块 1商城首页(暂时默认商城首页)
//            'show_time'     => $currentRes['show_time'],//弹幕停留时间
//            'is_circle'     => $currentRes['type']==1 ? 0 : $currentRes['is_circle'],//只有真实数据是不循环的
//            'virtual_num'   => $currentRes['rule']['virtual_num'],//该规则投放量
//            'space_time'    => $currentRes['rule']['space_end_time'],//投放间隔区间
//            'change_rule_state'    => $change_rule_state ?: 0,//用户是否改了配置规则
//            'next_rule'     => $nextRule,
//            'data'          => $queueRes,//弹幕数据
//        ];
        return AjaxReturn(SUCCESS,$returnData);
        
//        $ruleRes = $barrage->getCurrentTimeOfOrderBarrageRule();
//        if (!$ruleRes || $ruleRes['state'] == 0){return AjaxReturn(SUCCESS);}
//        if (!$ruleRes['rule']){return AjaxReturn(SUCCESS);}
//
//        $return_data = [
//            'state'         => $ruleRes['state'],//开启/关闭
//            'type'          => $ruleRes['state'],//订单弹幕类型 1真实数据 2 虚拟数据 3真实+虚拟
//            'use_place'     => $ruleRes['use_place'],//展示模块 1商城首页(暂时默认商城首页)
//            'show_time'     => $ruleRes['show_time'],//弹幕停留时间
//            'space_time'    => $ruleRes['rule']['space_end_time'],//投放间隔区间
//            'is_circle'     => $ruleRes['state'] == 1 ? 0 : $ruleRes['is_circle'],//只有真实数据是不循环的
//        ];

//        //todo 再写个定时， 每天去更新虚拟表数据
//        $virtual_num = request()->post('virtual_num', 0);//0为redis取出全部
//        $rule_id = request()->post('rule_id', 0);//规则id
//        $use_place = request()->post('use_place', 1);//展示地方 1商城首页
//        $type = request()->post('type', 0);//订单弹幕类型 1真实数据 2 虚拟数据 3真实+虚拟
//
//        //查询订单弹幕配置
//        $barrage = new OrderBarrageServer();
//        $config = $barrage->getOrderBarrageConfigOfRedis();
//        if (empty($rule_id) && empty($type)) {//说明第一次加载
//            return json(['code' => 2, 'message' => '获取配置成功', 'data' => $config]);
//        }
//        if (!getAddons(self::$addons_name, $this->website_id, $this->instance_id) || !$config || $config['state'] == 0) {//后台未开启
//            return json(['code' => -1, 'message' => '订单弹幕已经关闭']);
//        }
//
//        $result = '';
//        if ($use_place == 1) {//商城首页
//            $result = $this->getOrderBarrageQueueByType($type, $rule_id, $virtual_num);
//            if (empty($result)) {
//                return json(['code' => -2, 'message' => '订单弹幕已经结束']);
//            }
//        }
//
//        $return_arr = [
//            'user_name' => $result['user_name'] ?: '',
//            'header' => $result['header'] ?: '',
//            'goods_name' => $result['goods_name'] ?: '',
//            'state' => $config['state'],//规则状态
//            'is_circle' => $config['is_circle'],
//            'rule' => $config['rule'],
//        ];

//        return json(['code' => 1, 'message' => '获取成功', 'data' => $return_arr]);
    }
    
    public function  getOrderBarrageRuleApi()
    {
        $orderBarrageSer = new OrderBarrageServer();
        $nowRule = $orderBarrageSer->getCurrentTimeOfOrderBarrageRule();
        if ($nowRule && $nowRule['rule']){
            $nextRule = [
                'state' => $nowRule['state'],
                'type' => $nowRule['type'],
                'show_time' => $nowRule['show_time'],
                'is_circle' => $nowRule['is_circle'],
                'use_place' => $nowRule['use_place'],
                'start_time_unix' => $nowRule['rule']['start_time_unix'],
                'end_time_unix' => $nowRule['rule']['end_time_unix'],
                'virtual_num' => $nowRule['rule']['virtual_num'],
                'space_end_time' => $nowRule['rule']['space_end_time'],
            ];
        }else{
            $nextRule = $orderBarrageSer->getNextTimeOfOrderBarrageRule();
        }
        $orderbarrageData = [];
        if ($nextRule && $nextRule['state']){
            $orderbarrageData = [
                'state' => $nextRule['state'],
                'type' => $nextRule['type'],
                'show_time' => $nextRule['show_time'],
                'is_circle' => $nextRule['is_circle'],
                'use_place' => $nextRule['use_place'],
                'rule' => [
                    'start_time' => $nextRule['start_time_unix'],
                    'end_time' => $nextRule['end_time_unix'],
                    'virtual_num' => $nextRule['virtual_num'],
                    'space_end_time' => $nextRule['space_end_time'],
                ]
            ];
        }
        return AjaxReturn(SUCCESS,$orderbarrageData);
    }

    /**
     * 根据type返回对应的订单弹幕
     * @param $type int [弹幕类型]
     * @param $rule_id int [弹幕规则id]
     * @param $virtual_num int [订单投放量不传默认全部虚拟数据]
     * @return mixed array [订单弹幕array]
     */
    public function getOrderBarrageQueueByType($type, $rule_id, $virtual_num = 0)
    {
        //TODO... 暂时不用
        $redis = connectRedis();
        $config = $redis->get($this->config);
        $barrage = new OrderBarrageServer();
        $now_config = $barrage->analysisConfig(unserialize($config));
        $result = '';
        if ($type == 1) {
            $result = $redis->rpop($this->true_queue);//左入右出
        }
        if ($type == 2) {//虚拟
            if (($rule_id != $now_config['now_rule_id']) || ($virtual_num != $now_config['now_virtual_num'])) {
                $this->initOrderBarrageQueue($virtual_num);
            }
            $redis_length = $redis->llen($this->virtual_queue);
            if ($redis_length == 0 && $now_config['is_circle'] == 1) {
                $this->initOrderBarrageQueue($virtual_num);
            }
            $result = $redis->rpop($this->virtual_queue);//左入右出(有真实下单，真实订单右入)
        }
        if ($type == 3) {//真实+虚拟
            if ( ($rule_id != $now_config['now_rule_id']) || ($virtual_num != $now_config['now_virtual_num']) ){
                $this->initOrderBarrageQueue($virtual_num);
            }
            $true_redis_length = $redis->llen($this->true_queue);
            if ($true_redis_length != 0) {//先获取真实队列，再获取虚拟
                $result = $redis->rpop($this->true_queue);
            } else {
                $redis_length = $redis->llen($this->virtual_queue);
                if ($redis_length == 0 && $now_config['is_circle'] == 1) {
                    $this->initOrderBarrageQueue($virtual_num);
                }
                $result = $redis->rpop($this->virtual_queue);
            }
        }

        return unserialize($result);
    }
    
    /******************** 用作测试 start***************************/

    /**
     * 导入虚拟数据
     */
    public function postImportBarrageVirtual()
    {
        //todo... 1、获取上传EXCEL路径
        //todo... 2、获取图片文件路径
        //todo... 3、插入订单弹幕虚拟表
        //暂时这样处理
        $time = 1574067099;
        $imgs = $this->getHeadImgFrom($time);
        $res =  $this->postName(count($imgs));
        $data = [];
        for ($i=0; $i< count($imgs); $i++) {
            $data[] = [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'state' => 0,
                'place_order_time' => rand($time, 100),
                'user_name' => $res[$i],
                'header' => $imgs[$i]['pic_cover']
            ];
        }

        $barrage = new OrderBarrageServer();
        $result = $barrage->postOrderBarrageVirtualData($data);
        return $result;
    }

    /**
     * 随机创建昵称(可以优化)
     * @param int $list_num int [需要生成多少条数据]
     * @return array
     */
    public function postName($list_num = 10) {

        $a = "赵 钱 孙 李 周 吴 郑 王 冯 陈 楮 卫 蒋 沈 韩 杨 朱 秦 尤 许 何 吕 施 张 孔 曹 严 华 金 魏 陶 姜 戚 谢 邹 
        喻 柏 水 窦 章 云 苏 潘 葛 奚 范 彭 郎 鲁 韦 昌 马 苗 凤 花 方 俞 任 袁 柳 酆 鲍 史 唐 费 廉 岑 薛 雷 贺 倪 汤 
        滕 殷 罗 毕 郝 邬 安 常 乐 于 时 傅 皮 卞 齐 康 伍 余 元 卜 顾 孟 平 黄 和 穆 萧 尹 姚 邵 湛 汪 祁 毛 禹 狄 米 
        贝 明 臧 计 伏 成 戴 谈 宋 茅 庞 熊 纪 舒 屈 项 祝 董 梁 杜 阮 蓝 闽 席 季 麻 强 贾 路 娄 危 江 童 颜 郭 梅 盛 
        林 刁 锺 徐 丘 骆 高 夏 蔡 田 樊 胡 凌 霍 虞 万 支 柯 昝 管 卢 莫 经 房 裘 缪 干 解 应 宗 丁 宣 贲 邓 郁 单 杭 
        洪 包 诸 左 石 崔 吉 钮 龚 程 嵇 邢 滑 裴 陆 荣 翁 荀 羊 於 惠 甄 麹 家 封 芮 羿 储 靳 汲 邴 糜 松 井 段 富 巫 
        乌 焦 巴 弓 牧 隗 山 谷 车 侯 宓 蓬 全 郗 班 仰 秋 仲 伊 宫 宁 仇 栾 暴 甘 斜 厉 戎 祖 武 符 刘 景 詹 束 龙 叶 
        幸 司 韶 郜 黎 蓟 薄 印 宿 白 怀 蒲 邰 从 鄂 索 咸 籍 赖 卓 蔺 屠 蒙 池 乔 阴 郁 胥 能 苍 双 闻 莘 党 翟 谭 贡 
        劳 逄 姬 申 扶 堵 冉 宰 郦 雍 郤 璩 桑 桂 濮 牛 寿 通 边 扈 燕 冀 郏 浦 尚 农 温 别 庄 晏 柴 瞿 阎 充 慕 连 茹 
        习 宦 艾 鱼 容 向 古 易 慎 戈 廖 庾 终 暨 居 衡 步 都 耿 满 弘 匡 国 文 寇 广 禄 阙 东 欧 殳 沃 利 蔚 越 夔 隆 
        师 巩 厍 聂 晁 勾 敖 融 冷 訾 辛 阚 那 简 饶 空 曾 毋 沙 乜 养 鞠 须 丰 巢 关 蒯 相 查 后 荆 红 游 竺 权 逑 盖 
        益 桓 公 万俟 司马 上官 欧阳 夏侯 诸葛 闻人 东方 赫连 皇甫 尉迟 公羊 澹台 公冶 宗政 濮阳 淳于 单于 太叔 申屠 公孙 
        仲孙 轩辕 令狐 锺离 宇文 长孙 慕容 鲜于 闾丘 司徒 司空 丌官 司寇 仉 督 子车 颛孙 端木 巫马 公西 漆雕 乐正 壤驷 公良 
        拓拔 夹谷 宰父 谷梁 晋 楚 阎 法 汝 鄢 涂 钦 段干 百里 东郭 南门 呼延 归 海 羊舌 微生 岳 帅 缑 亢 况 后 有 琴 梁丘 
        左丘 东门 西门 商 牟 佘 佴 伯 赏 南宫 墨 哈 谯 笪 年 爱 阳 佟 第五 言 福";
        $aa = explode(' ',$a);

        $b = '彬 轩 含 蒲 乒 虚 行 亭 仑 蓝 影 韬 函 克 盛 衡 芝 晗 昊 诗 琦 至 涵 伦 时 映 志 菱 纶 士 永 致 嘉 旷 示 
        咏 智 安 轮 世 勇 中 昂 律 业 友 忠 敖 齐 轼 桓 林 言 群 书 有 宣 颁 略 伟 骢 州 清 宏 充 佑 洲 庭 马 濮 丹 乐 
        邦 迈 卫 平 乾 榜 宸 蔚 旲 东 宝 昴 树 材 纪 保 茂 泓 棋 竹 葆 浩 魏 妤 铸 劻 玫 晔 渝 壮 羚 阳 文 瑜 卓 掣 奎 
        船 与 萱 豹 梅 汶 旭 濯 驾 和 航 宇 孜 邶 望 武 羽 崊 霆 美 希 雨 淑 冰 蒙 才 凰 腾 备 密 溪 泰 子 辈 冕 帅 语 
        茜 蓓 淼 曦 玉 梓 弼 民 奇 禾 综 碧 洋 霞 连 祖 厚 晨 先 昱 选 昪 旻 虹 朔 济 彪 淏 贤 儋 冬 龄 馗 娴 钰 栋 飙 
        传 舷 御 端 澜 然 磊 裕 段 挺 名 春 誉 天 飚 明 灏 堂 碫 莱 鸣 双 渊 琳 坚 茗 一 元 倩 宾 村 宪 辉 铎 妍 铭 献 
        彭 思 策 谋 祥 序 伯 骞 牧 翔 启 恩 建 慕 向 沅 发 汗 穆 骁 溓 帆 健 恒 洪 媛 汉 键 威 晓 源 冀 勒 成 笑 远 弘 
        龙 仁 蕾 棠 凡 江 魁 伊 德 方 城 铿 顺 月 飞 萍 皓 朴 悦 学 骄 楠 啸 绪 强 鲛 妮 勰 跃 霖 劼 宁 兵 越 芬 杰 弩 
        淳 起 丰 洁 攀 心 云 风 柴 旁 昕 会 沣 婕 薇 欣 良 泊 同 沛 新 芸 川 悍 佩 依 颇 封 金 松 鸿 耘 峰 岩 日 竦 韵 
        勋 辰 朋 沂 坤 骥 晴 岚 怡 泽 锋 津 荣 信 增 澔 锦 容 立 波 乔 瑾 鹏 宜 登 凤 进 铖 达 承 豪 晋 榕 华 展 福 菁 
        韦 以 章 俯 彤 融 来 彰 恬 景 力 亿 涛 辅 炎 茹 义 梁 迅 璟 儒 瀚 浦 富 禅 采 艺 基 澉 颔 襦 星 钊 刚 庆 锐 议 
        昭 博 珑 斌 亦 照 纲 敬 瑞 佚 哲 合 靖 澎 励 喆 佳 驹 睿 易 绮 钢 聚 垒 奕 真 苓 万 尧 益 臻 阔 颜 若 淇 焘 聪 
        涓 飒 骅 沧 罡 娟 弛 朗 帝 高 军 森 兴 缜 歌 钧 砂 大 畅 弓 筠 山 谊 亮 功 丞 河 逸 稹 巩 全 善 意 舱 固 俊 超 
        溢 振 钦 隆 频 毅 朕 冠 翰 候 利 谦 部 彦 为 茵 震 谱 韩 劭 英 理 廷 昌 绍 琪 滔 家 骏 社 雄 镇 凌 珺 升 崇 征 
        光 竣 生 鹰 正 广 凯 圣 迎 诤 晷 铠 驰 寒 政 贵 康 胜 桦 琛 国 泉 晟 盈 殿 海 科 礼 代 之 卿 诚 耀 滢 吉 鑫 谚 
        亨 瀛 舜 延 可 维 逸';
        $bb = explode(' ',$b);

        $cc = array('快乐的','冷静的','醉熏的','潇洒的','糊涂的','积极的','冷酷的','深情的','粗暴的','温柔的','可爱的',
            '愉快的','义气的','认真的','威武的','帅气的','传统的','潇洒的','漂亮的','自然的','专一的','听话的','昏睡的',
            '狂野的','等待的','搞怪的','幽默的','魁梧的','活泼的','开心的','高兴的','超帅的','留胡子的','坦率的','直率的',
            '轻松的','痴情的','完美的','精明的','无聊的','有魅力的','丰富的','繁荣的','饱满的','炙热的','暴躁的','碧蓝的',
            '俊逸的','英勇的','健忘的','故意的','无心的','土豪的','朴实的','兴奋的','幸福的','淡定的','不安的','阔达的',
            '孤独的','独特的','疯狂的','时尚的','落后的','风趣的','忧伤的','大胆的','爱笑的','矮小的','健康的','合适的',
            '玩命的','沉默的','斯文的','香蕉','苹果','鲤鱼','鳗鱼','任性的','细心的','粗心的','大意的','甜甜的','酷酷的',
            '健壮的','英俊的','霸气的','阳光的','默默的','大力的','孝顺的','忧虑的','着急的','紧张的','善良的','凶狠的',
            '害怕的','重要的','危机的','欢喜的','欣慰的','满意的','跳跃的','诚心的','称心的','如意的','怡然的','娇气的',
            '无奈的','无语的','激动的','愤怒的','美好的','感动的','激情的','激昂的','震动的','虚拟的','超级的','寒冷的',
            '精明的','明理的','犹豫的','忧郁的','寂寞的','奋斗的','勤奋的','现代的','过时的','稳重的','热情的','含蓄的',
            '开放的','无辜的','多情的','纯真的','拉长的','热心的','从容的','体贴的','风中的','曾经的','追寻的','儒雅的',
            '优雅的','开朗的','外向的','内向的','清爽的','文艺的','长情的','平常的','单身的','伶俐的','高大的','懦弱的',
            '柔弱的','爱笑的','乐观的','耍酷的','酷炫的','神勇的','年轻的','唠叨的','瘦瘦的','无情的','包容的','顺心的',
            '畅快的','舒适的','靓丽的','负责的','背后的','简单的','谦让的','彩色的','缥缈的','欢呼的','生动的','复杂的',
            '慈祥的','仁爱的','魔幻的','虚幻的','淡然的','受伤的','雪白的','高高的','糟糕的','顺利的','闪闪的','羞涩的',
            '缓慢的','迅速的','优秀的','聪明的','含糊的','俏皮的','淡淡的','坚强的','平淡的','欣喜的','能干的','灵巧的',
            '友好的','机智的','机灵的','正直的','谨慎的','俭朴的','殷勤的','虚心的','辛勤的','自觉的','无私的','无限的',
            '踏实的','老实的','现实的','可靠的','务实的','拼搏的','个性的','粗犷的','活力的','成就的','勤劳的','单纯的',
            '落寞的','朴素的','悲凉的','忧心的','洁净的','清秀的','自由的','小巧的','单薄的','贪玩的','刻苦的','干净的',
            '壮观的','和谐的','文静的','调皮的','害羞的','安详的','自信的','端庄的','坚定的','美满的','舒心的','温暖的',
            '专注的','勤恳的','美丽的','腼腆的','优美的','甜美的','甜蜜的','整齐的','动人的','典雅的','尊敬的','舒服的',
            '妩媚的','秀丽的','喜悦的','甜美的','彪壮的','强健的','大方的','俊秀的','聪慧的','迷人的','陶醉的','悦耳的',
            '动听的','明亮的','结实的','魁梧的','标致的','清脆的','敏感的','光亮的','大气的','老迟到的','知性的','冷傲的',
            '呆萌的','野性的','隐形的','笑点低的','微笑的','笨笨的','难过的','沉静的','火星上的','失眠的','安静的','纯情的',
            '要减肥的','迷路的','烂漫的','哭泣的','贤惠的','苗条的','温婉的','发嗲的','会撒娇的','贪玩的','执着的','眯眯眼的',
            '花痴的','想人陪的','眼睛大的','高贵的','傲娇的','心灵美的','爱撒娇的','细腻的','天真的','怕黑的','感性的','飘逸的',
            '怕孤独的','忐忑的','高挑的','傻傻的','冷艳的','爱听歌的','还单身的','怕孤单的','懵懂的');

        $dd = array('嚓茶','凉面','便当','毛豆','花生','可乐','灯泡','哈密瓜','野狼','背包','眼神','缘分','雪碧','人生',
            '牛排','蚂蚁','飞鸟','灰狼','斑马','汉堡','悟空','巨人','绿茶','自行车','保温杯','大碗','墨镜','魔镜','煎饼',
            '月饼','月亮','星星','芝麻','啤酒','玫瑰','大叔','小伙','哈密瓜，数据线','太阳','树叶','芹菜','黄蜂','蜜粉',
            '蜜蜂','信封','西装','外套','裙子','大象','猫咪','母鸡','路灯','蓝天','白云','星月','彩虹','微笑','摩托','板栗',
            '高山','大地','大树','电灯胆','砖头','楼房','水池','鸡翅','蜻蜓','红牛','咖啡','机器猫','枕头','大船','诺言',
            '钢笔','刺猬','天空','飞机','大炮','冬天','洋葱','春天','夏天','秋天','冬日','航空','毛衣','豌豆','黑米','玉米',
            '眼睛','老鼠','白羊','帅哥','美女','季节','鲜花','服饰','裙子','白开水','秀发','大山','火车','汽车','歌曲',
            '舞蹈','老师','导师','方盒','大米','麦片','水杯','水壶','手套','鞋子','自行车','鼠标','手机','电脑','书本',
            '奇迹','身影','香烟','夕阳','台灯','宝贝','未来','皮带','钥匙','心锁','故事','花瓣','滑板','画笔','画板','学姐',
            '店员','电源','饼干','宝马','过客','大白','时光','石头','钻石','河马','犀牛','西牛','绿草','抽屉','柜子','往事',
            '寒风','路人','橘子','耳机','鸵鸟','朋友','苗条','铅笔','钢笔','硬币','热狗','大侠','御姐','萝莉','毛巾','期待',
            '盼望','白昼','黑夜','大门','黑裤','钢铁侠','哑铃','板凳','枫叶','荷花','乌龟','仙人掌','衬衫','大神','草丛',
            '早晨','心情','茉莉','流沙','蜗牛','战斗机','冥王星','猎豹','棒球','篮球','乐曲','电话','网络','世界','中心',
            '鱼','鸡','狗','老虎','鸭子','雨','羽毛','翅膀','外套','火','丝袜','书包','钢笔','冷风','八宝粥','烤鸡','大雁',
            '音响','招牌','胡萝卜','冰棍','帽子','菠萝','蛋挞','香水','泥猴桃','吐司','溪流','黄豆','樱桃','小鸽子','小蝴蝶',
            '爆米花','花卷','小鸭子','小海豚','日记本','小熊猫','小懒猪','小懒虫','荔枝','镜子','曲奇','金针菇','小松鼠',
            '小虾米','酒窝','紫菜','金鱼','柚子','果汁','百褶裙','项链','帆布鞋','火龙果','奇异果','煎蛋','唇彩','小土豆',
            '高跟鞋','戒指','雪糕','睫毛','铃铛','手链','香氛','红酒','月光','酸奶','银耳汤','咖啡豆','小蜜蜂','小蚂蚁',
            '蜡烛','棉花糖','向日葵','水蜜桃','小蝴蝶','小刺猬','小丸子','指甲油','康乃馨','糖豆','薯片','口红','超短裙',
            '乌冬面','冰淇淋','棒棒糖','长颈鹿','豆芽','发箍','发卡','发夹','发带','铃铛','小馒头','小笼包','小甜瓜','冬瓜',
            '香菇','小兔子','含羞草','短靴','睫毛膏','小蘑菇','跳跳糖','小白菜','草莓','柠檬','月饼','百合','纸鹤','小天鹅',
            '云朵','芒果','面包','海燕','小猫咪','龙猫','唇膏','鞋垫','羊','黑猫','白猫','万宝路','金毛','山水','音响');

        $name_list = [];//存随机生成的值
        for ($j=0; $j<$list_num; $j++) {
            $index = rand(1, 4);
            if (in_array($index, [1, 2])) {
                // 从 $a, $b 中取拼接
                $split_head = mt_rand(1,50);//头部拼接字符
//                $split_mid = mt_rand(1, 50);//中间拼接字符
                $split_end = mt_rand(1,20);//尾部拼接字符
                $split_head_end = mt_rand(1, 100);//是否首位拼接字符一样  eg: @傲娇小馒头@
                $is_split_head = false;
                if ($split_head > 45) {//自己控制头部拼接概率
                    $is_split_head = true;
                }
                /*                $is_split_mid = false;
                                if ($split_mid > 40) {//自己控制中间拼接概率
                                    $is_split_mid = true;
                                }*/
                $is_split_end = false;
                if ($split_end > 17) {//自己控制增加尾部拼接概率
                    $is_split_end = true;
                }
                $is_split_head_end = false;
                if ($split_head_end > 80) {//自己控制首尾相同拼接概率
                    $is_split_head_end = true;
                }
                // 开始创建随机字符串
                $str = '';
                $head_temp = '';
                if ($is_split_head) {
                    $head_temp = trim(chr(mt_rand(33, 127)));//头部生成随机ASCII码值
                    $str .= $head_temp;
                }
                $str .= $aa[array_rand($aa)];// 拼接$a一个值
                /*                if ($is_split_mid) {
                                    $str .= trim(chr(mt_rand(33, 127)));
                                    trim($str);
                                }*/
                $str .= $bb[array_rand($bb)];//拼接$b一个值
                if ($is_split_head_end && $is_split_head) {//首位相同字符
                    $str .= $head_temp;
                } else if ($is_split_end){//尾部拼接一个ASCII码值
                    $str .= trim(chr(mt_rand(33, 127)));
                }

                $name_list[] = strlen(trim($str)) > 0 ? trim($str) : $cc[array_rand($cc)]. $dd[array_rand($dd)];
            }

            if (in_array($index, [3, 4])) {
                // 从$b,$c中拼接
                $split_head = mt_rand(1,50);//头部拼接字符
                $split_mid = mt_rand(1, 50);//中间拼接字符
                $split_end = mt_rand(1,20);//尾部拼接字符
                $split_head_end = mt_rand(1, 100);//是否首位拼接字符一样  eg: @傲娇小馒头@
                $is_split_head = false;
                if ($split_head > 40) {//自己控制头部拼接概率
                    $is_split_head = true;
                }
                $is_split_mid = false;
                if ($split_mid > 40) {//自己控制中间拼接概率
                    $is_split_mid = true;
                }
                $is_split_end = false;
                if ($split_end > 15) {//自己控制增加尾部拼接概率
                    $is_split_end = true;
                }
                $is_split_head_end = false;
                if ($split_head_end > 70) {//自己控制首尾相同拼接概率
                    $is_split_head_end = true;
                }
                // 开始创建随机字符串
                $str = '';
                $head_temp = '';
                if ($is_split_head) {
                    $head_temp = trim(chr(mt_rand(33, 127)));//头部生成随机ASCII码值
                    $str .= $head_temp;
                }
                $str .= $cc[array_rand($cc)];// 拼接$a一个值
                if ($is_split_mid) {
                    $str .= trim(chr(mt_rand(33, 127)));
                    trim($str);
                }
                $str .= $dd[array_rand($dd)];//拼接$b一个值
                if ($is_split_head_end && $is_split_head) {//首位相同字符
                    $str .= $head_temp;
                } else if ($is_split_end){//尾部拼接一个ASCII码值
                    $str .= trim(chr(mt_rand(33, 127)));
                }

                $name_list[] = strlen(trim($str)) > 0 ? trim($str) : $cc[array_rand($cc)]. $dd[array_rand($dd)];
            }
        }
        return $name_list;
    }

    /**
     * 获取该时间戳后的上传图片作
     * @param $time int [时间戳]
     * @return array [图片]
     */
    public function getHeadImgFrom($time)
    {
        $album = new AlbumPictureModel();
        $condition = [
            'upload_time' => ['egt', $time]
        ];
        $images = $album->getQuery($condition, 'pic_cover','');
        return $images;
    }

    public function getOrderBarrageRedisList()
    {
        //todo... 1、先获取虚拟数据
        //todo... 2、有下单就加入队列
        //todo... 3、有请求就压出队列
        $page_size = request()->post('page_size', '0');
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'state' => 0,
        ];
        try {
            $barrage = new OrderBarrageServer();
            $virtualList = $barrage->postOrderBarrageVirtualQueue($condition , $page_size);

            return AjaxReturn(SUCCESS, $virtualList);
        } catch (\Exception $e) {
            return AjaxReturn(-1);
        }
    }
    /******************** /用作测试 end ***************************/

    public function test()
    {
    
//        $params = request()->post();
//        runhook('OrderBarrage','postOrderBarrageTrueQueue',$params);
        $redis = connectRedis();
        $redisResult = $redis->get('26_0_order_barrage_config_rule_state');
        $redis_data = unserialize($redisResult);//TODO... 过滤时间
        
        p($redisResult);die;
//        p(1111111111);die;
//        p($this->true_queue);die;
    
        //先判断订单弹幕是否开启
        $barrage = new OrderBarrageServer();
        $config = $barrage->getCurrentOrderBarrageRuleOfRedis();
        # 没有配置、关闭、或者弹幕类型为虚拟则不写入真实队列
        if (!$config || $config['state'] == 0) {return;}
        if (!$config['rule']){return;}
        # 清除真实队列
        $redis = connectRedis();
        p(unserialize($redis->get($this->true_queue)), '真实');die;
        
        if ($config['type'] == 2){
            $redis->set($this->true_queue, null);
            return;
        }
        //查询订单信息
        $orderModel = new VslOrderModel();
        $order = $orderModel::get(1);
        $order_info = $order->order_goods()->select();
        $goods_name = $order_info[0]['goods_name'];//暂时取多个商品中第一个
        # todo... 测试
        $params = [
            'website_id' => 26,
            'shop_id' => 0,
            'uid' => 1474,
        ];
        # todo... 测试/
        
        //查询用户信息
        $user = new UserModel();
        $user_condition = [
            'website_id' => $params['website_id'],
            'instance_id' => $params['shop_id'],
            'uid' => $params['uid']
        ];
        $user_info = $user->getInfo($user_condition, 'nick_name, user_name, user_tel, user_headimg');
        $user_name = $user_info['nick_name'] ? : ($user_info['user_name'] ?: $user_info['user_tel']);//用户名
        $user_headimg = $user_info['user_headimg'];
    
        $order_barrage_arr = [
            'website_id' => $params['website_id'],
            'shop_id' => $params['shop_id'],
            'user_name' => $user_name,
            'header' => $user_headimg,
            'goods_name' => $goods_name,
            'state' => 2,
            'place_order_time' => time(),
            'uid' => $params['uid']
        ];
        
        $redisRes = $redis->get($this->true_queue);
//        $redisRes = $redis->set($this->true_queue,null);
        $trueData = unserialize($redisRes);
        $trueData = $trueData ?: [];
        array_push($trueData, $order_barrage_arr);
        $redis->set($this->true_queue, serialize($trueData));
        $barrage->postOrderBarrageVirtual($order_barrage_arr) ;
//        $redis->lpush($this->true_queue, serialize($order_barrage_arr));//左压入真实数据队列，右出
//        p($redis->llen($this->true_queue),'长度');
//        p($redis->rpop($this->true_queue));
//        p(unserialize($redis->rpop($this->true_queue)));
    }
    
    public function importantVirData(){
        $file = '';
        $dir = 'public\platform\static\orderbarrage';
        $head_url = ROOT_PATH.$dir.'\user.xls';
        if (!is_dir($dir)){
            @mkdir($dir, 0777, true);
            @chmod($dir, 0777);
        }
        Vendor('PhpExcel.PHPExcel');
        $objReader = \PHPExcel_IOFactory::createReader('Excel5');
//        $objReader->setReadDataOnly(true);
        $obj_PHPExcel = $objReader->load($head_url, 'utf-8'); //加载文件内容,编码utf-8
        $excel_array = $obj_PHPExcel->getsheet(0)->toArray(); //转换为数组格式
        //TODO...
    }
}


