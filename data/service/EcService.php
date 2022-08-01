<?php

namespace data\service;

use think\Exception;

/**
 * EcService constructor.
 *
 * 查询条件：
 * 1.must
 * 文档 必须 匹配这些条件才能被包含进来。相当于sql中的 and
 * 2.must_not
 * 文档 必须不 匹配这些条件才能被包含进来。相当于sql中的 not
 * 3.should
 * 如果满足这些语句中的任意语句，将增加 _score ，否则，无任何影响。它们主要用于修正每个文档的相关性得分。相当于sql中的or
 * 4.filter
 * 必须 匹配，但它以不评分、过滤模式来进行。这些语句对评分没有贡献，只是根据过滤标准来排除或包含文档。
 *
 *
 */
class EcService
{

    const source_enable = true;

    const number_of_shards = 1; //当前只有一台ES，1就可以了

    const number_of_replicas = 0;  //副本0，因为只有一台ES

    const create_type = 'create'; // 批量创建类型

    const update_type = 'index'; // 批量更新类型

    const must_not = "must_not";

    const must = "must";

    private $type = "_doc"; // 索引类型 (MySQL 表名)

    private $db_name; // 索引名称 (MySQL 数据库名称)

    private $where = []; // 条件

    private $order = []; // 排序

    private $fields = []; // 字段  默认全部查询

    private $alias = []; // 别名数组

    private $from; // 查询起始位置(偏移量)

    private $size; // 分页大小

    private $client; // 客户端

    private $client_builder;

    private $result; // sql 结果集

    private $errors; // 错误信息

    private static $not_condition = ['<>', 'neq', 'not in'];
    private static $bet_condition = ['<=', '>=', '<', '>', 'gt', 'egt', 'lt', 'elt'];
    private static $eq_condition = ['=', 'eq'];


    /**
     * EcService constructor.
     *
     * config 配置文件新增
     *
     * 'ecs' => [
     * 'host' => '192.168.81.79', // 换自己的ip
     * 'port' => '9200',
     * 'scheme' => 'http',
     * 'user' => '',
     * 'pass' => ''
     * ],
     */
    public function __construct()
    {
        // hosts 可以配置多个节点
        $hosts = [
            config("ecs")
        ];
        $this->client_builder = \Elasticsearch\ClientBuilder::create();   // Instantiate a new ClientBuilder

        $this->client_builder->setHosts($hosts);           // Set the hosts
        $this->client = $this->client_builder
            ->setConnectionPool('\Elasticsearch\ConnectionPool\StaticNoPingConnectionPool', [])
            ->build();
    }

    /**
     * @param $table 索引类型  一个索引名称 可以对应多个索引类型
     * @return $this
     */
    public function table($table)
    {
        $this->reset();//初始化
        $this->db_name = config('database.database').'_'.$table;
        return $this;
    }

    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * 不能空 字段
     */
    public function isNotNull($params)
    {
        return $this->comExists($params, self::must);
    }

    /**
     * 不能空 字段
     */
    public function isNull($params)
    {
        return $this->comExists($params, self::must_not);
    }

    /**
     * 通用字段检测
     * @param $params
     * @param $type
     * @return $this
     */
    public function comExists($params, $type)
    {
        if (is_array($params) && count($params) > 0) {
            $this->genExistsData($params, $type);//$this->where['query']['bool'][$type] =
        } else {
            $this->where['query']['bool'][$type]['exists']['field'] = $params;   //类型名称
        }
        return $this;
    }


    /**
     * $fields
     */
    public function field($params)
    {
        $this->fields = $params;
        return $this;
    }

    /**
     * 索引建立
     * @param $properties 索引字段设置
     * @return array
     */
    public function createIndex($properties)
    {
        $params = [
            'index' => $this->db_name,
            'body' => [
                'settings' => [
                    'number_of_shards' => self::number_of_shards, //当前只有一台ES，1就可以了
                    'number_of_replicas' => self::number_of_replicas //副本0，因为只有一台ES
                ],
                'mappings' => [
                    '_source' => [
                        'enabled' => self::source_enable
                    ],
                    'properties' => $properties
                ]
            ]
        ];
        $ret = $this->client->indices()->create($params);
        return $ret;
    }


    /**
     * 修改索引类型
     * @param $properties
     * @return array
     */
    public function putMapping($properties)
    {
        $params = [
            'include_type_name' => true,
            'index' => $this->db_name,
            'type' => $this->type,
            'body' => [
                '_source' => [
                    'enabled' => self::source_enable
                ],
                'properties' => $properties
            ]
        ];
        $ret = $this->client->indices()->putMapping($params);
        return $ret;
    }


    /**
     * 批量删除索引中的字段
     *
     * @param $properties 索引字段设置
     * @return array
     */
    public function delIndexFields($fields)
    {
        $params = [];
        $params['index'] = $this->db_name;  //索引名称
        $params['type'] = $this->type;   //类型名称
        if ($this->where == null || count($this->where) <= 0) {
        } else {
            $params['body'] = $this->where;   //类型名称
        }
        if ($fields != null && $fields == "") return false;
        $script = "";
        if (is_array($fields)) {
            foreach ($fields as $key => $value) {
                $script .= "ctx._source.remove('" . $value . "');";
            }
        } else {
            $script = "ctx._source.remove('" . $fields . "')";
        }
        $params['body']['script'] = $script;
        $ret = $this->client->updateByQuery($params);
        return $ret;


    }


    /**
     * 刷新index索引数据
     * 默认数据结果 1秒后显示
     * @return array
     * @throws Exception
     */
    public function refreshIndex()
    {
        $this->checkdbName();
        $params = ['index' => $this->db_name];
        $ret = $this->client->indices()->refresh($params);
        return $ret;
    }


    /**
     * 新增
     * @param $data
     * @param $id 不指定id，系统会自动生成唯一id  推荐指定
     */
    public function insert($data, $id = null)
    {
        $this->checkdbName();
        // 添加清除where 排序条件
        $this->reset();
        $params = [];
        $params['body'] = $data;
        $params['index'] = $this->db_name;  //索引名称
        $params['type'] = "_doc";   //类型名称
        if ($id != null) {
            $params['id'] = $id;
        }
        $ret = $this->client->index($params);
        if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
            return true;
        }
        return false;
    }

    /**
     * 批量处理通用类
     * @param $data
     * @param $type
     * @return bool
     * @throws Exception
     */
    private function comAll($data, $type)
    {
        $this->checkdbName();
        // 添加清除where 排序条件
        $this->reset();
        $params = [];
        foreach ($data as $key => $value) {
            if (!isset($value['_id']) || $value['_id'] == null) continue;
            $params['body'][] = [$type => [#注意create也可换成index
                '_id' => $value['_id']
            ]];
            unset($value['_id']);
            $params['body'][] = $value;
        }
        $params['index'] = $this->db_name;  //索引名称
        $params['type'] = $this->type;   //类型名称
        $ret = $this->client->bulk($params);
        if ($ret && isset($ret['errors']) && $ret['errors'] == false) {
            return true;
        }
        return false;
    }

    public function insertAll($data)
    {
        return $this->comAll($data, self::create_type);
    }

    /**
     * 删除
     * @return mixed
     */
    public function deleteById($id)
    {
        try {
            $this->checkdbName();
            $params = [];
            if ($this->where != null) {
                $params['body'] = $this->where;
            }
            $params['id'] = $id;
            $params['index'] = $this->db_name;  //索引名称
            $params['type'] = $this->type;   //类型名称
            $ret = $this->client->delete($params);
            if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }

    }


    /**
     * 删除匹配的
     * @return array
     */
    public function deleteQuery()
    {
        $params = [];
        $params['index'] = $this->db_name;  //索引名称
        $params['type'] = $this->type;   //类型名称
        if ($this->where == null || count($this->where) <= 0) {
            return false;
        } else {
            $params['body'] = $this->where;   //类型名称
        }
        $ret = $this->client->deleteByQuery($params);
        return $ret;
    }

    /**
     * 删除所有
     * @return array
     */
    public function deleteAll()
    {
        $params = [];
        $params['index'] = $this->db_name;  //索引名称
        $params['type'] = $this->type;   //类型名称
        $params['body']['query']['match_all'] = new \stdClass();   //类型名称
        $ret = $this->client->deleteByQuery($params);
        return $ret;
    }


    /**
     * 删除索引
     * @return array
     * @throws Exception
     */
    public function deleteDB()
    {
        $this->checkdbName();
        $params = ['index' => $this->db_name];
        $ret = $this->client->indices()->delete($params);
        return $ret;
    }

    /**
     * 更新数据
     * @param $data
     * @param null $id
     * @return bool
     */
    public function update($data, $id = null)
    {
        $params = [];
        $params['index'] = $this->db_name;
        $params['type'] = $this->type;
        if ($id != null) {
            $params['id'] = $id;
        }
        if ($this->where != null && count($this->where) > 0) {
            $params['body'] = $this->where;
        }
        $params['body']['doc'] = $data;
        $ret = $this->client->update($params);
        if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
            return true;
        }
        return false;
    }

    public function updateQuery($data)
    {
        $params = [];
        $params['index'] = $this->db_name;  //索引名称
        $params['type'] = $this->type;   //类型名称
        if ($this->where == null || count($this->where) <= 0) {
        } else {
            $params['body'] = $this->where;   //类型名称
        }
        $script = "";
        foreach ($data as $key => $value) {
            $script .= "ctx._source['" . $key . "'] =" . (is_int($value) ? $value : "'" . $value . "'") . "; ";
        }
        $params['body']['script'] = $script;
        $ret = $this->client->updateByQuery($params);
        return $ret;
    }

    /**
     *  批量更新
     */
    public function updateAll($data)
    {
        return $this->comAll($data, self::update_type);
    }

    /**
     * 单个查询
     * @return mixed
     */
    public function find()
    {
        $params['index'] = $this->db_name;
        $params['type'] = $this->type;
        if ($this->from != "" && $this->size != "") {
            $params['from'] = $this->from;
            $params['size'] = $this->size;
        } else {
            $params['from'] = 0;
            $params['size'] = 1;
        }

        $params['body'] = $this->where;
        if ($this->fields != null && count($this->fields) > 0) {
            $params['_source'] = $this->fields;
        }
        if ($this->order != null && count($this->order) > 0) {
            $params['body']['sort'] = $this->order;
        }
        $ret = $this->client->search($params);
        if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
            return isset($ret['hits']['hits'][0]) ? $ret['hits']['hits'][0]['_source'] : false;
        }
        return false;
    }

    /**
     *  通过id单个查询
     * @param $id
     * @return bool|mixed
     */
    public function findById($id)
    {
        try {
            $params = [];
            $params['index'] = $this->db_name;
            $params['type'] = $this->type;
            $params['id'] = $id;
            if ($this->fields != null && count($this->fields) > 0) {
                $params['_source'] = $this->fields;
            }
            $ret = $this->client->get($params);
            if ($ret['found'] == true) return $ret['_source'];
            return false;
        } catch (\Exception $e) {
            return false;
        }

    }


    /**
     * 条数统计
     */
    public function count()
    {
        try {
            $params = [];
            $params['index'] = $this->db_name;
            $params['type'] = $this->type;
            $params['body'] = $this->where;
            $ret = $this->client->count($params);
            if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
                return $ret['count'];
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * distinct 去重统计
     * 字段类型 int 需要加入索引
     */
    public function distinctCount($field)
    {
        $this->checkdbName();
        $params = [];
        $params['index'] = $this->db_name;
        $params['type'] = $this->type;
        $params['from'] = 0;
        $params['size'] = 1;
        $params['body']['aggs'][$this->db_name]['cardinality']['field'] = $field;
        $ret = $this->client->search($params);
        if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
            return isset($ret['aggregations']) ? $ret['aggregations'][$this->db_name]['value'] : 0;
        } else {
            return 0;
        }
    }

    /**
     * 计算出一些统计信息（min、max、sum、count、avg5个值）
     */
    public function stats($field)
    {
        return $this->statistics($field, 'stats');
    }


    /**
     * 聚合查询
     * 字段数量累加
     */
    public function sum($field)
    {
        return $this->statistics($field, 'sum');
    }

    /**
     * 聚合查询
     * 字段平均值
     */
    public function avg($field)
    {
        return $this->statistics($field, 'avg');
    }

    /**
     * 聚合查询
     * 字段最小值
     */
    public function min($field)
    {
        return $this->statistics($field, 'min');
    }

    /**
     * 聚合查询
     * 字段最大值
     */
    public function max($field)
    {
        return $this->statistics($field, 'max');
    }

    private function statistics($field, $type)
    {
        try {
            $this->checkdbName();
            $params = [];
            $params['index'] = $this->db_name;
            $params['type'] = $this->type;
            $params['from'] = 0;
            $params['size'] = 1;
            if ($this->where == null || count($this->where) <= 0) {
            } else {
                $params['body'] = $this->where;   //类型名称
            }
            $params['body']['aggs'][$this->db_name][$type]['field'] = $field;
            $ret = $this->client->search($params);
            if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
                if ($type == "stats") return isset($ret['aggregations']) ? $ret['aggregations'][$this->db_name] : [];
                return isset($ret['aggregations']) ? ($ret['aggregations'][$this->db_name]['value'] == null ? 0 : $ret['aggregations'][$this->db_name]['value']) : 0;
            } else {
                return 0;
            }
        } catch (\Exception $e) {
            print($e->getMessage());
            return 0;
        }

    }

    /**
     * 查询列表
     */
    public function selectAll()
    {
        $params['index'] = $this->db_name;
        $params['type'] = $this->type;
        if ($this->from === null || $this->size === null) {
        } else {
            $params['from'] = $this->from;
            $params['size'] = $this->size - 1;
        }
        if ($this->where != null && count($this->where) > 0) {
            $params['body'] = $this->where;
        }
        if ($this->order != null && count($this->order) > 0) {
            $params['body']['sort'] = $this->order;
        }
        if ($this->fields != null && count($this->fields) > 0) {
            $params['_source'] = $this->fields;
        }
        $ret = $this->client->search($params);
        $data = [];
        if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
            foreach ($ret['hits']['hits'] as $k => $v) {
                $data[] = $v['_source'];
            }
            unset($ret); // 销毁结果对象
            return $data;
        } else {
            return $data;
        }
    }

    /**
     * 深度分页：
     * scroll滚动:
     * 适合非跳页场景
     * 为了解决上面的问题，elasticsearch提出了一个scroll滚动的方式，
     * 这个滚动的方式原理就是通过每次查询后，返回一个scroll_id。
     * 根据这个scroll_id 进行下一页的查询。可以把这个scroll_id理解为通常关系型数据库中的游标。
     * 但是，这种scroll方式的缺点是不能够进行反复查询，也就是说，只能进行下一页，不能进行上一页。
     * search_after：
     * 适合移动端  下拉加载
     * search_after 分页的方式和 scroll 有一些显著的区别，
     * 首先它是根据上一页的最后一条数据来确定下一页的位置，
     * 同时在分页请求的过程中，如果有索引数据的增删改查，这些变更也会实时的反映到游标上。
     * 为了找到每一页最后一条数据，每个文档必须有一个全局唯一值，
     * 这种分页方式其实和目前 moa 内存中使用rbtree 分页的原理一样，官方推荐使用 _uid 作为全局唯一值，其实使用业务层的 id 也可以。
     *
     *
     * from+ size分页、scroll滚动搜索、search_after 假分页比较
     *
     * 1.from + size分页性能低；优点是灵活性好，实现简单；缺点是存在深分页问题；
     * 适用于数据量比较小，能容忍深度分页存在的场景
     * 浅分页只适合少量数据, 因为隋from增大,查询的时间就会越大;而且数据越大,查询的效率指数下降
     *
     * 2.scroll滚动搜索性能中等；优点解决了深度分页问题；缺点是无法反应数据的实时性（快照版本），
     * 需要维护一个 scroll_id，成本高；适用于需要查询海量结果集场景
     *
     * 3.search_after 假分页性能高；优点性能最好，不存在深度分页问题，能够反映数据的实时变更；缺点是实现复杂，
     * 需要有一个全局唯一的字段，连续分页的时每一次查询都需要上次查询的结果；适用于需海量数据的分页的场景
     *
     * 分页查询
     * @param int $page
     * @param int $size
     * @param string $scroll 1m  1分钟  scroll_id 深度分页
     * @param string $scrollId
     * @return array
     *
     */
    public function pageAll($page = 1, $size = 20, $scroll = "", $scroll_id = "")
    {
        $data = [];
        $data['data'] = [];
        $data['page_index'] = (int)$page;
        if (($page * $size) > 10000) {
            throw new Exception("The maximum limit is 10000");
        }
        try {
            $params['index'] = $this->db_name;
            $params['type'] = $this->type;
            $params['from'] = $page == 1 ? 0 : (($page - 1) * $size);
            $params['size'] = $size - 1;
            $params['track_total_hits'] = true; // 显示真实总条数
            if ($scroll != null && $scroll != "") {
                $params['scroll'] = $scroll;
            }
            if ($scroll_id != "") {
                $params['scroll_id'] = $scroll_id;
            }
            if ($this->where != null && count($this->where) > 0 && $scroll_id == null) {
                $params['body'] = $this->where;
            }
            if ($this->order != null && count($this->order) > 0 && $scroll_id == null) {
                $params['body']['sort'] = $this->order;
            }
            if ($this->fields != null && count($this->fields) > 0) {
                $params['_source'] = $this->fields;
            }
            if ($scroll_id != null && $scroll_id != '') {
                unset($params['from']);// scroll from 需为0
                unset($params['index']);
                unset($params['size']);
                unset($params['track_total_hits']);
                unset($params['type']);
                $ret = $this->client->scroll($params);
            } else {
                $ret = $this->client->search($params);
            }
            $total = $ret['hits']['total']['value'];
            $totalPage = 1; // 默认值
            if ($total > 0 && $size > 0) {
                $totalPage = ceil($total / $size);
            }
            if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
                $last_id = "";
                foreach ($ret['hits']['hits'] as $k => $v) {
                    $data['data'][$k] = $v['_source'];
                    $data['data'][$k]['_id'] = $v['_id'];
                    $last_id = $v['_id'];
                }
                $data['last_id'] = (int)$last_id;
                $data['page_count'] = $totalPage;

                $data['total_count'] = $total;
                if ($scroll != "") {
                    $data['scroll_id'] = $ret['_scroll_id'];
                }
                unset($ret); // 销毁结果对象
                return $data;
            } else {
                return $data;
            }
        } catch (\Exception $e) {
            print_r($e->getMessage());
            $data['page_count'] = 1;
            $data['total_count'] = 0;
            return $data;
        }

    }


    /**
     * 清空 重置
     */
    public function reset()
    {
        $this->where = []; // where 条件
        $this->order = []; // 排序
        $this->fields = [];// 字段
    }

    /**
     * where 条件数组
     * @param $params
     * @return $this
     */
    public function where($params)
    {
        $this->argumentVerify($params);
        foreach ($params as $key => $value) {
            if ($value!==null && !(is_array($value)&&$value==[])){
                $this->where['query']['bool']['must'][]['match'][$key] = $value;
            }
        }
        return $this;
    }

    /**
     * condition
     */
    public function condition($params)
    {
        $this->argumentVerify($params);
        foreach ($params as $key => $value) {
            $keys = [];
            $type = is_array($value) && isset($value[0]) ? $value[0] : "";
            $val = is_array($value) && isset($value[1]) ? $value[1] : $value;
            $param = [];
            if (is_array($value) && count($value) >= 2) {
                $type = isset($value[0]) ? $value[0] : "";
                if (is_array($type)) {
                    $type = isset($type[0]) ? $type[0] : "";
                    $val = $value;
                }
            }
            if (strpos($key, "|") > 0) {
                $keys = explode("|", $key);
                $type = "or";
            } else if (!is_array($value)) {
                $type = "=";
            }
            if (count($keys) >= 2) {
                //多对一情况
                foreach ($keys as $key => $key_name) {
                    $param[trim($key_name)] = $val;
                }
            } else {
                $param = [$key => $val];
            }
            switch (strtolower($type)) {
                case "like" :
                    $this->whereLike($param);
                    break;
                case "in" :
                    $this->whereIn($key, $value[1]);
                    break;
                case "or" :
                    $this->whereOr($param);
                    break;
                case in_array($type, self::$not_condition):
                    $this->whereNot($key, $value[1]);
                    break;
                case in_array(strtolower($type), self::$bet_condition):
                    $this->whereBetween($param);
                    break;
                case in_array(strtolower($type), self::$eq_condition):
                    $this->where($param);
                    break;


            }
        }
        return $this;
    }

    /**
     * 模糊查询   变量需要携带 *  MySQL 则是 %
     * @param $params
     * @return $this
     */
    public function whereLike($params)
    {
        $this->argumentVerify($params);
        foreach ($params as $key => $value) {
            // $key =  后面加 .keyword 精准匹配
            //
            unset($params[$key]);
            $params[$key . ".keyword"] = $value != null ? str_replace("%", "*", $value) : $value;
        }
        $this->where['query']['bool']['must'][]['wildcard'] = $params;
        return $this;
    }

    /**
     * 指定查询条件 xor
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 xor
     * @return $this
     */
    public function whereOr($params)
    {
        $data = $this->genMatchData($params);
        $this->where['query']['bool']['should'] = $data;
        return $this;
    }

    /**
     * 指定查询条件  and
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 xor
     * @return $this
     */
    public function whereAnd($params)
    {
        $data = $this->genMatchData($params);
        $this->where['query']['bool']['must'][] = $data;
        return $this;
    }

    /**
     * @param $field 字段名称
     * @param $ids 1,2,3  或者 数组格式 [1,2,3]
     * @throws Exception
     */
    public function whereIn($field, $ids)
    {
        $this->argumentVerify($field);
        $this->argumentVerify($ids);
        if (is_array($ids)) {
            $data = $ids;
        } else {
            $data = explode(",", $ids);
        }
        foreach ($data as $key => $value) {
            if ($value) {
                $this->where['query']['bool']['must'][]['match'][$field] = $value;
            }
        }
        return $this;
    }

    /**
     * 不 匹配这些条件才能被包含进来
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 xor
     * @return $this
     */
    public function whereNot($field, $ids)
    {
        $this->argumentVerify($field);
        $this->argumentVerify($ids);
        if (is_array($ids)) {
            $data = $ids;
        } else {
            $data = explode(",", $ids);
        }
        foreach ($data as $key => $value) {
            if ($value) {
                $this->where['query']['bool']['must_not'][]['match'][$field] = $value;
            }
        }
        return $this;
    }


    /**
     * 指定Between查询条件
     * @access public
     * @param mixed $field 查询字段
     * @param mixed $condition 查询条件
     * @param string $logic 查询逻辑 and or xor
     * @return $this
     *
     * ['body']['query']['range']
     */
    public function whereBetween($params)
    {
        $data = [];
        foreach ($params as $key => $value) {
            if (isset($params[$key]) && is_array($params[$key])) {
                foreach ($params[$key] as $key1 => $value1) {
                    $key1 = is_array($value1) ? $value1[0] : $key1;
                    $default_key = $key1;
                    $val = is_array($value1) ? $value1[1] : $value1;
                    switch ($key1) {
                        case ">=":
                            $default_key = "gte";
                            break;
                        case ">":
                            $default_key = "gt";
                            break;
                        case "<":
                            $default_key = "lt";
                            break;
                        case "<=":
                            $default_key = "lte";
                            break;
                    }
                    $data[$key][$default_key] = $val;
                }
            }
        }
        $this->where['query']['bool']['filter'][]['range'] = $data;
        return $this;
    }

    /**
     * 使用search_after必须要设置from=0。
     * 这里我使用timestamp和_id作为唯一值排序。
     * @param $params
     * @return $this
     */
    public function searchAfter($params)
    {
        if ($params != null && is_array($params) && count($params) > 0) {
            foreach ($params as $key => $value) {
                if ($value === null || $value === '') return $this;
                if (is_numeric($value)) {
                    $params[$key] = (int)$value;
                }
            }
            $this->where['search_after'] = $params;
        }
        return $this;
    } 

    /**
     * 排序
     * @param $params
     * @return $this
     */
    public function order($params)
    {
        $this->order = $params;
        return $this;
    }

    /**
     * 分页限制
     * @param int $from 起始位置
     * @param int $size 大小限制
     * @return $this
     */
    public function limit($from = 0, $size = 10)
    {
        $this->from = $from;
        $this->size = $size;
        return $this;
    }

    /**
     * SQL 查询
     * @param $query
     * @param $fetch_size
     * @param string $cursor
     * @return mixed
     * @throws \Elasticsearch\Common\Exceptions\NoNodesAvailableException
     */
    public function query($query, $fetch_size, $cursor = "")
    {
        $is_cursor = false;
        $star_fields_data = [];//  ”字段.*“
        if ($cursor != "") {
            $is_cursor = true;
            $body = json_encode(['cursor' => $cursor]);
        } else {
            $body = json_encode(['query' => $query, 'fetch_size' => $fetch_size]);
        }
        $fields = $this->fieldsByQuery($query);
        // 如果存在无指定字段情况 先获取字段列表
        if ($fields == null || count($fields) == 0 || $fields[0] == "*") {
            $table_name = $this->getSqlTables($query)[0];//获取表名
            $this->translate("select * FROM " . $table_name, 1);
            $fields = $this->result['_source']['includes'];
            $this->result = [];//重新初始化
        } else {
            // 检查字段是否存在 ”字段.*“
            foreach ($fields as $k => $v) {
                if (strpos($v, ".*") !== false) {
                    $table_name = $this->getSqlTables($query)[0];//获取表名
                    $this->translate("select * FROM " . $table_name, 1);
                    $star_fields = $this->result['_source']['includes'];
                    // 筛选数据
                    foreach ($star_fields as $f_key => $f_value) {
                        $fields_arr = explode('.', $v);
                        if (strpos($f_value, $fields_arr[0] . ".") !== false && $f_value != "*") {
                            $fields_key = explode('.', $f_value);
                            $star_fields_data[$fields_key[0]][] = $fields_key[1];
                        } else {
                            unset($star_fields[$f_key]);
                        }
                    }
                    break;
                }
            }
        }
        $this->sql_query("/_sql", $body);

        $data = isset($this->result['rows']) ? $this->result['rows'] : [];
        if ($data != null && count($data) > 0 && $fields != null && count($fields) > 0 && $fields[0] != "*") {
            foreach ($data as $key => $value) {
                if (is_array($value) && count($value) > 0) {
                    $start_k = 0;
                    foreach ($value as $k => $v) {
                        if (isset($fields[$k])) {
                            // 设置别名
                            if (isset($this->alias[$fields[$k]])) {
                                $data[$key][$this->alias[$fields[$k]]] = $v;
                            } else {
                                //如果存在点
                                $fields_arr = explode('.', $fields[$k]);

                                if ($fields_arr != null && count($fields_arr) > 1) {
                                    // 如果第二个字段是星号
                                    // 则需要 translate
                                    if ($fields_arr[1] == "*") {
//                                       匹配”字段名.“的
//                                        总数组下标减一
                                        // 先获取已匹配的数组 然后进行对位匹配
                                        foreach ($star_fields_data[$fields_arr[0]] as $f_key => $f_value) {
                                            $data[$key][$fields_arr[0]][$f_value] = $data[$key][$k];
                                            unset($data[$key][$k]);
                                            $start_k = $k;
                                            $k++;
                                        }
                                    } else {
                                        $data[$key][$fields_arr[0]][$fields_arr[1]] = $data[$key][$k];
                                    }
                                } else {
                                    $data[$key][$fields[$k]] = $value[$k + $start_k];
                                }
                            }
                            unset($data[$key][$k]);
                        }
                    }
                }
            }
        }

        $this->result['rows'] = $data;
        $data = $this->result;
        if ($is_cursor) {
            // 获取字段
            $this->close($cursor);
        }
        return $data;
    }

    /**
     * 结合search_after
     * translate sql 转义成 普通es语句
     * @param $query
     * @param $fetch_size
     * @return array
     */
    public function queryByTranslate($query, $fetch_size, $last_field = "_id")
    {
        $params['body'] = $this->translate($query, $fetch_size);
        $params['index'] = $this->getSqlTables($query);
        $params['type'] = $this->type;
        $params['from'] = 0;
        $params['size'] = $fetch_size;
        $params['track_total_hits'] = true; // 显示真实总条数
        if (isset($this->where['search_after'])) {
            $params['body']['search_after'] = $this->where['search_after'];
        }
        if (isset($params['index'][1])) {
            unset($params['index'][1]);
        }
        if (isset($params["body"]['sort']) && is_array($params["body"]['sort'])) {
            foreach ($params["body"]['sort'] as $k => $v) {
                foreach ($params["body"]['sort'][$k] as $k1 => $v1) {
                    unset($params["body"]['sort'][$k][$k1]['missing']);
                    unset($params["body"]['sort'][$k][$k1]['unmapped_type']);
                }
            }
        }
        $ret = $this->client->search($params);
        $total_size = $ret['hits']['total']['value'];
        $data = ['data' => []];
        if ($ret && isset($ret['_shards']) && $ret['_shards']['failed'] == 0) {
            // 匹配条件
            // ["+"=>[a,b]]
            // ["-"=>[a,b]]
            // ["="=>]
            $match_cond = [];
            if ($last_field != null && is_array($last_field)) {
                foreach ($last_field as $k => $v) {
                    $add_pos = strpos($v, "+");
                    $sub_pos = strpos($v, "-");
                    if ($add_pos != false && $add_pos >= 0) {
                        $match_cond["+"][] = explode("+", $v);
                    } else if ($sub_pos != false && $sub_pos >= 0) {
                        $match_cond["-"][] = explode("-", $v);
                    } else {
                        $match_cond["="][] = $v;
                    }
                }
            }
            //每个字段都进行匹配
            $hits_size = count($ret['hits']['hits']);
            foreach ($ret['hits']['hits'] as $k => $v) {
                $data['data'][$k] = $v['_source'];
                $data['data'][$k]['_id'] = $v['_id'];
            }
            $last_str = "";
            if (isset($ret['hits']['hits'][$hits_size - 1]['_source'])) {
                $source = $ret['hits']['hits'][$hits_size - 1]['_source'];
                if (count($match_cond)) {
                    foreach ($match_cond as $k => $v) {
                        if (isset($match_cond[$k]) && $k == "=") {
                            if (is_array($v)) {
                                foreach ($match_cond[$k] as $eq_key => $eq_value) {
                                    foreach ($source as $v_key => $v_value) {
                                        if ($eq_value == $v_key) {
                                            $last_str .= $v_value . ",";
                                        }
                                    }
                                }
                            }
                        }
                        if (isset($match_cond[$k]) && $k == "+") {
                            if (is_array($match_cond[$k])) {
                                $total = 0;
                                foreach ($match_cond[$k] as $add_arr_k => $add_arr_value) {
                                    foreach ($add_arr_value as $add_k => $add_value)
                                        $total += $source[$add_value];
                                }
                                $last_str .= $total . ",";
                            }
                        }
                        if (isset($match_cond[$k]) && $k == "-") {
                            if (is_array($match_cond[$k])) {
                                //先统计
                                $total = 0;
                                foreach ($match_cond[$k] as $add_arr_k => $add_arr_value) {
                                    foreach ($add_arr_value as $add_k => $add_value)
                                        $total += $source[$add_value];
                                }
                                foreach ($match_cond[$k] as $add_arr_k => $sub_arr_value) {
                                    foreach ($sub_arr_value as $sub_k => $sub_value){
                                        if($sub_k==0){
                                            //第一个字段不做减
                                            continue;
                                        }
                                        $total -= $source[$add_value];
                                    }
                                }
                                $last_str .= $total . ",";
                            }
                        }
                    }
                    $last_str = substr($last_str, 0, strlen($last_str) - 1);
                } else {
                    $last_str = isset($ret['hits']['hits'][$hits_size - 1]['_id']) ? $ret['hits']['hits'][$hits_size - 1]['_id'] : "";
                }
            }
            $data['search_after'] = $last_str;
            $data['total_count'] = $total_size;
            unset($ret); // 销毁结果对象
        }
        return $data;
    }

    /**
     * SQ 别名
     */
    public function alias($alias)
    {
        $this->alias = $alias;
        return $this;
    }

    /**
     * SQL 获取字段数组
     */
    public function fieldsByQuery($query)
    {
        $fields = [];
        if ($query != null && $query != "") {
            $start = strpos($query, "SELECT");
            $end = strpos($query, "FROM");
            $result = trim(str_replace("SELECT", "", substr($query, $start, $end)));
            if ($result != null && $result != "") {
                $fields = explode(",", $result);
            }
        }
        return $fields;
    }

    /**
     *  sql转换为标准查询
     */
    public function translate($query, $fetch_size)
    {
        $body = json_encode(['query' => $query, 'fetch_size' => $fetch_size]);

        $this->sql_query("/_sql/translate", $body);
        return $this->result;
    }

    /**
     * 获取字段列表
     */
    public function getFieldLists()
    {
        // http://192.168.81.238:9200/_xpack/sql/translate?format=json

    }

    /**
     * #主动关闭session
     * #不主动close 会发生啥?
     * # 参数page_timeout 45s The timeout before a pagination request fails.
     * 关闭close
     */
    public function close($cursor)
    {
        $body = json_encode(['cursor' => $cursor]);
        $this->sql_query("/_sql/close", $body, 'POST');
        return $this->result;
    }

    /**
     * 通用SQL请求
     * @param $path
     * @param $body
     * @throws \Elasticsearch\Common\Exceptions\NoNodesAvailableException
     */
    public function sql_query($path, $body, $method = "GET")
    {
        $res = $this->client_builder->build()->transport->performRequest($method, $path . "?format=json", null, $body);
        $res->then(function ($res) {
            $this->result = $res;
        }, function ($error) {
            $this->errors = $error;
        });
        if ($this->errors != null) {
            $data = $this->errors;
            $this->errors = null; //初始化
            throw new Exception($data);
        }
    }


    /**
     * 检查表名是否正常
     * @throws Exception
     */
    private function checkdbName()
    {
        if ($this->db_name == null || $this->db_name == '') {
            throw new Exception("db name not found");
        }
    }

    /**
     * 组装match 数据
     * @param $params
     * @return array
     */
    private function genMatchData($params)
    {
        $data = [];
        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key => $value) {
                $data[]['match'] = [$key => $value];
            }
        }
        return $data;
    }

    /**
     * 生成字段数据
     * @param $params
     * @return array
     */
    private function genExistsData($params, $type)
    {
        $data = [];
        if (is_array($params) && count($params) > 0) {
            foreach ($params as $key => $value) {
                $this->where['query']['bool'][$type][]['exists']['field'] = $value;
            }
        }
        return $data;
    }

    /**
     * 参数验证
     */
    private function argumentVerify($arg)
    {
        if ($arg === null) {
            throw new Exception("arg is invaild");
        }
    }


    function getSqlTables($sqlString)
    {
        $sqlString = str_replace('`', '', trim($sqlString)) . ' AND 1=1 ';
        $key = strtolower(substr($sqlString, 0, 6));
        if ($key === 'select') {
            $tmp = explode('where', strtolower(trim($sqlString)));
            $tmp = explode('from', $tmp[0]);
            if (strpos($tmp[1], ',') !== false && !stristr($tmp[1], 'select')) {
                $tmp = explode(',', $tmp[1]);
                foreach ($tmp as $k => $v) {
                    $v = trim($v);
                    if (strpos($v, ' ') !== false) {
                        $tv = explode(' ', $v);
                        $return[] = $tv[0];
                    }
                }
                return $return;
            } else {
                $expression = '/((SELECT.+?FROM)|(LEFT\\s+JOIN|JOIN|LEFT))[\\s`]+?(\\w+)[\\s`]+?/is';
            }
        } else if ($key === 'delete') {
            $expression = '/DELETE\\s+?FROM[\\s`]+?(\\w+)[\\s`]+?/is';
        } else if ($key === 'insert') {
            $expression = '/INSERT\\s+?INTO[\\s`]+?(\\w+)[\\s`]+?/is';
        } else if ($key === 'update') {
            $tmp = explode('set', strtolower(str_replace('`', '', trim($sqlString))));
            $tmp = explode('update', $tmp[0]);
            if (strpos($tmp[1], ',') !== false && !stristr($tmp[1], 'update')) {
                $tmp = explode(',', $tmp[1]);
                foreach ($tmp as $k => $v) {
                    $v = trim($v);
                    if (strpos($v, ' ') !== false) {
                        $tv = explode(' ', $v);
                        $return[] = $tv[0];
                    }
                }
                return $return;
            } else {
                $expression = '/UPDATE[\\s`]+?(\\w+)[\\s`]+?/is';
            }
        }
        preg_match_all($expression, $sqlString, $matches);
        return array_unique(array_pop($matches));
    }
}
