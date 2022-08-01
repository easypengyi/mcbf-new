<?php
namespace data\service;
use Predis\Client;

class RedisPool extends BaseService
{
    private static $connections = array(); //定义一个对象池
    private static $servers = array(); //定义redis配置文件
    public static function addServer($conf) //定义添加redis配置方法
    {
        foreach ($conf as $alias => $data){
            self::$servers[$alias]=$data;
        }
    }

    /**
     * 连接redis
     * @param $alias
     * @param int $select
     * @return mixed
     */
    public static function getRedis($alias,$select = 0)//两个参数要连接的服务器KEY,要选择的库
    {
        if(!array_key_exists($alias,self::$connections)){  //判断连接池中是否存在
            $server = array(
                'host' => self::$servers[$alias][0],//IP
                'port' => self::$servers[$alias][1], //端口
                'password' => self::$servers[$alias][2]  //密码
            );
            $redis = new Client($server);
            self::$connections[$alias]=$redis;
        }
        self::$connections[$alias]->select($select);
        return self::$connections[$alias];
    }
}