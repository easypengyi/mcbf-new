<?php

namespace addons\orderbarrage\server;

use addons\orderbarrage\model\VslOrderBarrageConfigModel;
use addons\orderbarrage\model\VslOrderBarrageRuleModel;
use addons\orderbarrage\model\VslOrderBarrageVirtualModel;
use data\service\BaseService;
use think\Exception;

/**
 * 发票助手数据处理OrderBarrge
 * Class OrderBarrage
 * @package addons\orderbarrage\server
 */
class OrderBarrage extends BaseService {
    const ORDER_BARRAGE_VIRTUAL_QUEUE = 'order_barrage_virtual_queue';//虚拟队列（虚拟+真实）队列键名
    const ORDER_BARRAGE_TRUE_QUEUE = 'order_barrage_true_queue';//真实队列键名，下单就存入该队列
    const ORDER_BARRAGE_CONFIG = 'order_barrage_config';//配置redis缓存
    const ORDER_BARRAGE_CONFIG_RULE_STATE = 'order_barrage_config_rule_state';//rule是否改变状态
    
    protected $virtual_queue;
    protected $true_queue;
    protected $config;
    protected $rule_state;
    protected $true_default_nums = 10;//真实数据默认取出10条
    protected $virtual_default_nums = 100;//虚拟数据默认取出100条
    protected $remove_time = 600;//过滤10分钟内的队列数据
    protected $virtual_time = 120;//虚拟数据离下单时间当前时间的最大值（秒）
    protected $true_time = 10;//真实数据的随机事件最大值（秒）
    protected $rule_state_time = 30;//后台修改规则后的这个时间段，前端会显示'change_rule_state'=1
    
    public function __construct($website_id=0, $instance_id=0) {
        parent::__construct();
        $this->website_id = $website_id ?:$this->website_id;
        $this->instance_id = $instance_id ?:$this->instance_id;
        $this->virtual_queue = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_VIRTUAL_QUEUE;//eg 2_0_order_barrage_virtual_queue
        $this->true_queue = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_TRUE_QUEUE;//eg 2_0_order_barrage_true_queue
        $this->config = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_CONFIG;//eg 2_0_order_barrage_config
        $this->rule_state = $this->website_id .'_'.$this->instance_id .'_'.self::ORDER_BARRAGE_CONFIG_RULE_STATE;
    }
    
    /**
     * 初始化虚拟数据
     * @throws Exception\DbException
     */
    public function initVirtualTableData ()
    {
        //判断config是否已经初始化
        $virtualModel = new VslOrderBarrageVirtualModel();
        $is_exist = $virtualModel::get(['id'=>['>=', 1]]);
        if ($is_exist){return;}
        try {
            //1、读取xls文件
            $basedir = 'public/static/uservir';
            $user_name_file = ROOT_PATH.$basedir.'/user.xls';
            $user_head = $basedir.'/user';
            $user_head_dir = ROOT_PATH.$user_head;
            $head_arr = [];
            if (is_dir($user_head_dir)){
                if ($dh = opendir($user_head_dir)){
                    while (($file = readdir($dh)) !== false){
                        if ($file == '.' || $file == '..') continue;
                        $head_arr[] = $user_head.'/'.$file;
                    }
                    closedir($dh);
                }
            }
            if (!is_array($head_arr) || !$head_arr){return;}
            Vendor('PhpExcel.PHPExcel');
            $objReader = \PHPExcel_IOFactory::createReader('Excel5');
            $obj_PHPExcel = $objReader->load($user_name_file, 'utf-8'); //加载文件内容,编码utf-8
            $excel_array = $obj_PHPExcel->getsheet(0)->toArray(); //转换为数组格式
            $insert_data = [];
            foreach ($excel_array as $excel){
                $insert_data[] = [
                    'website_id' => $this->website_id,
                    'shop_id' => 0,
                    'state' => 0,
                    'place_order_time' => time(),
                    'goods_name' => '虚拟商品',
                    'user_name' => $excel[0],
                    'header' => array_shift($head_arr)?:' ',
                ];
            }
            $virtualModel->saveAll($insert_data);
        }catch (\Exception $e){
            debugLog('导入错误:'.$e->getMessage());
        }
    }
    
    /**
     * 获取弹幕配置信息
     * @param $condtion
     * @param string $field
     * @return array|false|\PDOStatement|string|\think\Model
     */
    public function getOrderBarrageConfigInfo($condtion, $field = '*')
    {
        $barrage = new VslOrderBarrageConfigModel();
        $barrage_info = $barrage->getInfo($condtion, $field);
        return $barrage_info;
    }

    /**
     * 获取订单弹幕规则
     * @param $condition
     * @param string $field
     * @param string $order
     * @return mixed
     */
    public function getOrderBarrageRulesList($condition, $field = '*', $order = 'update_time')
    {
        $rule = new VslOrderBarrageRuleModel();
        $rule_list = $rule->getQuery($condition, $field, $order);
        return $rule_list;
    }

    /**
     * 获取商城对应订单弹幕配置以及对应的弹幕规则
     * @return VslOrderBarrageRuleModel
     * @throws Exception\DbException
     */
    public function getOrderBarrageConfigOfRule($website_id=0, $shop_id=0)
    {
        $website_id = $website_id ? : $this->website_id;
        $shop_id = $shop_id ? : $this->instance_id;
        $config = new VslOrderBarrageConfigModel();
        $list = $config::get([
            'website_id' => $website_id,
            'shop_id' => $shop_id
        ],
            [
             'rule'
            ]);
        return $list;
    }

    /**
     * 删除订单弹幕规则 - 单条
     * @param $condition
     * @return bool|string
     */
    public function deleteOrderBarrageRule($condition)
    {
        $rule = new VslOrderBarrageRuleModel();
        $result = $rule->delData($condition);

        return $result;
    }

    /**
     * 修改订单弹幕虚拟数据 - 单条
     * @param $data
     * @return false|int
     */
    public function postOrderBarrageVirtual($data)
    {
        $virtualModel = new VslOrderBarrageVirtualModel();
        $result = $virtualModel->save($data);

        return $result;

    }

    /**
     * 修改订单弹幕虚拟数据 - 多条
     * @param $data
     * @return array|false
     * @throws \Exception
     */
    public function postOrderBarrageVirtualData($data)
    {
        $virtualModel = new VslOrderBarrageVirtualModel();
        $result = $virtualModel->saveAll($data);

        return $result;
    }

    /**
     * 获取虚拟弹幕信息列表
     * @param $condition
     * @param int $page_size int [取多少条]
     * @return array
     */
    public function getOrderBarrageVirtualList($condition, $page_size = 0)
    {
        $virtualModel = new VslOrderBarrageVirtualModel();
        $result = $virtualModel->pageQuery(1, $page_size, $condition, 'rand()','' );//随机取
        foreach($result['data'] as $k=>$info){
            $result['data'][$k]['header'] = getApiSrc($info['header'], $info['website_id']);
        }
        return $result;
    }

    /**
     * 把虚拟数据加入队列并返回虚拟数据
     * @param $condition array [查询订单弹幕虚拟数据表的条件]
     * @param $page_size int [获取数据量]
     * @return array [数组：序列化的虚拟数据]
     */
//    public function postOrderBarrageVirtualQueue($condition, $page_size = 0)
//    {
//        $queue_page = $page_size ?: -1;
//        $result = [];
//        //把取出的虚拟数据压入队列
//        $virtualList = $this->getOrderBarrageVirtualList($condition, $page_size);
//        $this->clearOrderBarrageQueue();//清空订单弹幕队列
//        $redis = connectRedis();
//        foreach ($virtualList['data'] as $key => $val) {
//            $redis->lpush($this->virtual_queue, serialize($val));//虚拟数据压入虚拟数据队列
//            $result = $redis->lrange($this->virtual_queue, 0, $queue_page);//虚拟数据
//        }
//
//        return $result;
//    }

    /**
     * 清空订单弹幕队列
     */
    public function clearOrderBarrageQueue()
    {
        $redis = connectRedis();
        // 清空队列
        $redis->ltrim($this->true_queue, $redis->llen($this->true_queue), -1);
        $redis->ltrim($this->virtual_queue, $redis->llen($this->virtual_queue), -1);

        return true;
    }

    /**
     * 获取订单弹幕配置的redis数据
     * @return mixed array
     */
    public function getOrderBarrageConfigOfRedis()
    {
        $redis = connectRedis();
        $config = $redis->get($this->config);

        return unserialize($config);
    }
    
    /**
     * 获取当前订单弹幕规则的redis数据
     */
    public function getCurrentOrderBarrageRuleOfRedis ()
    {
        $redis = connectRedis();
        $redisRes = $redis->get($this->config);
        $rule_config = unserialize($redisRes);
        if (!$rule_config['rule']){return;}
        $temp_arr = [];
        foreach($rule_config['rule'] as $k => $rule){
            if($rule['start_time'] && $rule['end_time']){
                $start_arr  = explode(',',$rule['start_time']);
                $start_time = mktime($start_arr[0],$start_arr[1],$start_arr[2]);//开始时间戳
                $end_arr    = explode(',',$rule['end_time']);
                $end_time   = mktime($end_arr[0],$end_arr[1],$end_arr[2]);//结束时间戳
                $rule_config['rule'][$k]['start_time_unix'] = $start_time;
                $rule_config['rule'][$k]['end_time_unix'] = $end_time;
                if (time() > $start_time && time() < $end_time){
                    $temp_arr = $rule_config['rule'][$k];
                }
            }
        }
        $rule_config['rule'] = $temp_arr;
        return $rule_config;
    }
    
    /**
     * 修改订单弹幕配置的redis数据
     * @param $data
     * @return bool\
     */
    public function putOrderBarrageConfigOfRedis($data)
    {
        $redis = connectRedis();
        $redis->set($this->config, serialize($data));

        return true;
    }
    
    /**
     * 修改 $this->remove_time时间内用户是否改了规则配置
     * @return int
     */
    public function putOrderBarrageChangeRuleState ()
    {
        $redis = connectRedis();
        return $redis->set($this->rule_state,1);
    }
    
    /**
     * 获取$this->remove_time时间内用户是否改了规则配置
     * @return string
     */
    public function getOrderBarrageChangeRuleState ()
    {
        $redis = connectRedis();
        return $redis->get($this->rule_state);
    
    }
    
    /**
     * 清除$this->remove_time时间内用户是否改了规则配置的状态
     * @return mixed
     */
    public function delOrderBarrageChangeRuleState()
    {
        $redis = connectRedis();
        return $redis->set($this->rule_state,0);
    }
    
    /**
     * 获取当前规则对应信息
     * @param array $config [订单弹幕配置规则信息]
     * @return array [当前订单弹幕规则信息]
     */
    public function analysisConfig(array $config)
    {
        foreach ($config['rule'] as $k => $c) {
            if (time()>= $c['start_time'] && time()<= $c['end_time']) {//根据当前时间所在区间获取对应规则id
                $config['now_rule_id'] = $c['rule_id'];
                $config['now_virtual_num'] = $c['virtual_num'];
            }
        }

        unset($config['rule']);
        return $config;
    }
    
    /**
     * 获取当前时间符合的规则
     */
    public function getCurrentTimeOfOrderBarrageRule ($website_id=0, $shop_id=0)
    {
        $website_id = $website_id ? : $this->website_id;
        $shop_id = $shop_id ? : $this->instance_id;
        $rule_config = $this->getOrderBarrageConfigOfRule($website_id, $shop_id);
        if (!$rule_config['rule']){return;}
        $temp_arr = [];
        foreach($rule_config['rule'] as $k => $rule){
            if ($rule['start_time'] && $rule['end_time']){
                $start_arr  = explode(',',$rule['start_time']);
                $start_time = mktime($start_arr[0],$start_arr[1],$start_arr[2]);//开始时间戳
                $end_arr    = explode(',',$rule['end_time']);
                $end_time   = mktime($end_arr[0],$end_arr[1],$end_arr[2]);//结束时间戳
                $rule_config['rule'][$k]['start_time_unix'] = $start_time;
                $rule_config['rule'][$k]['end_time_unix'] = $end_time;
                if (time() > $start_time && time() < $end_time){
                    $temp_arr = $rule_config['rule'][$k];
                }
            }
        }
        $rule_config['rule'] = $temp_arr;
        return $rule_config;
    }
    
    /**
     * 获取下个时间段的规则时间范围
     */
    public function getNextTimeOfOrderBarrageRule ()
    {
        $rule_config = $this->getOrderBarrageConfigOfRule();
        if ($rule_config['state'] == 0){return;}
        if (!$rule_config['rule']){return;}
        //查找大于当前时间的规则
        $temp_arr = [];
        foreach($rule_config['rule'] as $k => $rule){
            if ($rule['start_time'] && $rule['end_time']){
                $start_arr  = explode(',',$rule['start_time']);
                $start_time = mktime($start_arr[0],$start_arr[1],$start_arr[2]);//开始时间戳
                $end_arr    = explode(',',$rule['end_time']);
                $end_time   = mktime($end_arr[0],$end_arr[1],$end_arr[2]);//结束时间戳
                $rule_config['rule'][$k]['start_time_unix'] = $start_time;
                $rule_config['rule'][$k]['end_time_unix'] = $end_time;
                if (time() < $start_time){
                    $temp_arr[] = $rule_config['rule'][$k];
                }
            }
        }
        if (!$temp_arr){return;}
        $nearest_time = current($temp_arr)['start_time_unix'];
        $nearest_time_arr = current($temp_arr);
        for ($i=0; $i< count($temp_arr); $i++){
            if ($nearest_time > $temp_arr[$i]['start_time_unix']){
                $nearest_time = $temp_arr[$i]['start_time_unix'];//离当前时间最近的值
                $nearest_time_arr = $temp_arr[$i];//规则id
            }
        }
        $nearest_time_arr['state'] = $rule_config['state'];
        $nearest_time_arr['type'] = $rule_config['type'];
        $nearest_time_arr['show_time'] = $rule_config['show_time'];
        $nearest_time_arr['is_circle'] = $rule_config['is_circle'];
        $nearest_time_arr['use_place'] = $rule_config['use_place'];

        return objToArr($nearest_time_arr);//离当前时间最近的下一个规则
    }
    
    /**
     * 初始化队列,重置队列
     * @param int $virtual_num
     * @return array
     */
    public function initOrderBarrageVirtualQueue($virtual_num=0)
    {
        //重新构建队列（只用重构虚拟队列）
        $virtual_num = $virtual_num ?: $this->virtual_default_nums;
        $condition = [
            'state' => 0,
        ];
        //把取出的虚拟数据压入队列
        $virtualList = $this->getOrderBarrageVirtualList($condition, $virtual_num);
//        $this->clearOrderBarrageQueue();//清空订单弹幕队列
        $redis = connectRedis();
//        $redis->set($this->virtual_queue,null);
        $redis->set($this->virtual_queue, serialize($virtualList['data']));
    }
    
    /**获取弹幕信息
     * @param     $type [弹幕类型]
     * @param int $virtual_num [除了type=1的真实数据，其他可以获取相应数量]
     * @param int $is_circle [1循环 0不循环]
     * @param int $second_request [0正常请求 1二次请求]
     * @return array|mixed
     */
    public function getOrderBarrageQueue($type, $virtual_num = 0, $is_circle=0, $second_request=0)
    {
        //1、真实数据：从真实队列取出，从真实队列取出至多10条 + 当前规则 + 下一次规则 + 是否用户改了配置状态state。
        //2、虚拟数据：从虚拟队列取出，返回至多100条（或者设置值） + 当前规格 + 下一次规则 + 是否用户改了配置状态state。
        # 前端带type表示是否第二次（second_request=1)请求。如果第二次且不循环则不返回数据; 如果第二次且循环则返回规则数据
        //3、真实/虚拟：优先真实队列+虚拟队列，返回至多100条（或者设置值）+ 当前规则 + 下一次规则 + 是否用户改了配置状态state。
        # 前端带type表示是否第二次（second_request=1)请求。如果第二次且不循环则不返回数据; 如果第二次且循环则返回规则数据
        
//        $currentRes = $this->getCurrentTimeOfOrderBarrageRule();
//        if (!$currentRes || $currentRes['state'] == 0){return [];}
//        if (!$currentRes['rule']){return [];}
        $get_nums = $virtual_num ?: $this->virtual_default_nums;
        // 获取投放量
        $result = [];
        $redis = connectRedis();
        if ($type == 1){
            # 真实
            $redisResult = $redis->get($this->true_queue);
            $redis_data = unserialize($redisResult);//TODO... 过滤时间
            if (!$redis_data || !is_array($redis_data)){
                return $result;
            }
            $redis_data = $this->removeData($redis_data);
            $start_data = array_slice($redis_data,0,$this->true_default_nums);
            $end_data = array_slice($redis_data,$this->true_default_nums);
            //TODO... 10条
            $result = $start_data;
            //过滤后的塞回队列
            $redis->set($this->true_queue,serialize($end_data));
            if ($result){
                foreach ($result as &$v){
//                    $v['place_order_time'] = $v['place_order_time'] - time() > 0 ? $v['place_order_time'] - time() : rand(1, $this->virtual_time);
                    $v['place_order_time'] = time() - $v['place_order_time'] > 0 ? time() - $v['place_order_time'] : rand(1, $this->true_time);
                }
            }
            
        } else if ($type == 2){
            # 虚拟
            if ($second_request==1 && $is_circle==0){return $result;}
            $this->initOrderBarrageVirtualQueue($get_nums);//state虚拟数据展示状态0未展示 1待展示 2已展示
            $redisResult = $redis->get($this->virtual_queue);
            $result = unserialize($redisResult);
            if ($result){
                foreach ($result as &$v){
//                    $v['place_order_time'] = time() - rand(0, $this->vairtual_time);
                    $v['place_order_time'] = rand(1, $this->virtual_time);
                }
            }
        } else if ($type == 3){
            # 真实+虚拟
            if ($second_request==1 && $is_circle==0){return $result;}
            $redisResult = $redis->get($this->true_queue);
            $redis_data = unserialize($redisResult);//TODO... 过滤时间
            if (is_array($redis_data)){
                $redis_data = $this->removeData($redis_data);
                $start_data = array_slice($redis_data,0,$this->true_default_nums);
                $end_data = array_slice($redis_data,$this->true_default_nums);
                $result = $start_data;
                //过滤后的塞回队列
                $redis->set($this->true_queue,serialize($end_data));
            }

            $true_length = count($result);
            if (($virtual_num = $get_nums - $true_length) > 0 ){/*优先取真实数据*/
                $this->initOrderBarrageVirtualQueue($virtual_num);
                $virtualRes = $redis->get($this->virtual_queue);
                $virtualData = unserialize($virtualRes);
                if ($virtualData){
                    foreach ($virtualData as &$v){
//                        $v['place_order_time'] = time() - rand(0, $this->virtual_time);
                        $v['place_order_time'] = rand(1, $this->virtual_time);
                        $v['goods_name'] = $v['goods_name'] ?: '虚拟商品';
                    }
                }
                $result = array_merge($result, $virtualData);
            }
        }
        
        $result = objToArr($result);
        foreach ($result as $k => $v){
            $result[$k]['header'] = __IMG($v['header']);
        }
        return $result;
    }

    /**
     * 比较弹幕保存规则是否与数据库已存在的一致，一致则不进行修改
     * @param $old_data [数据库数据]
     * @param $new_data [新加入数据]
     * @return bool
     */
    public function compareRuleIsChange ($old_data, $new_data)
    {
        if (!$old_data){return true;}
        if(count($old_data) != count($new_data)){return true;}
        
        $old_data = objToArr($old_data);
        $new_data = objToArr($new_data);

        foreach ($new_data as $k => $v){
            foreach ($v as $kk => $vv){
                if ($vv != $old_data[$k][$kk]){
                    return true;
                }
            }
        }
        return false;
    }
    
    /**
     * 过滤数据
     * @param array $redis_data
     * @return array
     */
    public function removeData (array $redis_data)
    {
        $new_redis_data = [];
        foreach ($redis_data as $key => $val){
            if(time() - $this->remove_time < $val['place_order_time']){
                $new_redis_data[] = $val;
            }
        }
        return $new_redis_data;
    }
}
