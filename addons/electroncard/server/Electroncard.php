<?php

namespace addons\electroncard\server;

use addons\distribution\model\SysMessagePushModel;
use addons\electroncard\model\VslElectroncardBaseModel;
use addons\electroncard\model\VslElectroncardCategoryModel;
use addons\electroncard\model\VslElectroncardDataFileModel;
use addons\electroncard\model\VslElectroncardDataModel;
use addons\thingcircle\model\MsgReminderModel;
use data\model\VslGoodsSkuModel;
use data\service\BaseService;
use think\Db;


/**
 * 电子卡密
 */
class Electroncard extends BaseService
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * 新增或编辑卡密库分类
     */
    public function addOrUpdateCategory($data)
    {
        if (empty($data['category_id'])) {
            //新增
            $save_data = [
                'category_name' => $data['category_name'],
                'create_time' => time(),
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
            ];
            $electroncard_category_mdl = new VslElectroncardCategoryModel();
            $res = $electroncard_category_mdl->save($save_data);
        } else {
            //编辑
            $electroncard_category_mdl = new VslElectroncardCategoryModel();
            $res = $electroncard_category_mdl->save(['category_name' => $data['category_name']], ['id' => $data['category_id']]);
        }

        return $res;
    }

    /**
     * 卡密库分类列表
     */
    public function electroncardCategoryList($condition, $order)
    {
        $electroncard_category_mdl = new VslElectroncardCategoryModel();
        $list = $electroncard_category_mdl->getQuery($condition, '*', $order);
        if ($list) {
            //关联卡密库数量
            $electroncard_base_mdl = new VslElectroncardBaseModel();
            foreach ($list as $k => $v) {
                $list[$k]['num'] = $electroncard_base_mdl->getCount(['electroncard_base_category_id' => $v['id']]);
            }
        }

        return ['data' => $list];
    }

    /**
     * 删除卡密库分类
     */
    public function delElectroncardCategory($category_id)
    {
        $electroncard_category_mdl = new VslElectroncardCategoryModel();
        $res = $electroncard_category_mdl->delData(['id' => $category_id]);

        return $res;
    }

    /**
     * 添加或编辑卡密库
     */
    public function addOrUpdateElectroncardBase($data)
    {
        if ($data['id']) {
            //编辑
            $save_data = [
                'electroncard_base_name' => $data['electroncard_base_name'],
                'electroncard_base_category_id' => $data['electroncard_base_category'],
                'memo' => $data['memo'],
                'description' => $data['description'],
            ];
            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $res = $electroncard_base_mdl->save($save_data, ['id' => $data['id']]);
        } else {
            //新增
            $save_data = [
                'electroncard_base_name' => $data['electroncard_base_name'],
                'electroncard_base_category_id' => $data['electroncard_base_category'],
                'memo' => $data['memo'],
                'description' => $data['description'],
                'is_data_different' => $data['data_different'],
                'key_name' => implode(',', $data['key_list']),
                'shop_id' => $this->instance_id,
                'website_id' => $this->website_id,
                'create_time' => time(),
            ];
            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $res = $electroncard_base_mdl->save($save_data);
        }

        return $res;
    }

    /**
     * 卡密库列表
     */
    public function electroncardBaseList($page_index, $page_size, $condition, $order)
    {
        $electroncard_base_mdl = new VslElectroncardBaseModel();
        $viewObj = $electroncard_base_mdl->alias('eb')
            ->join('vsl_electroncard_category ec', 'eb.electroncard_base_category_id=ec.id', 'left')
            ->field('eb.id,eb.electroncard_base_name,eb.create_time,ec.category_name');
        $queryList = $electroncard_base_mdl->viewPageQuery($viewObj, $page_index, $page_size, $condition, $order);

        $queryCount = $electroncard_base_mdl->alias('eb')
            ->join('vsl_electroncard_category ec', 'eb.electroncard_base_category_id=ec.id', 'left')
            ->where($condition)
            ->field('eb.id')
            ->count();

        $list = $electroncard_base_mdl->setReturnList($queryList, $queryCount, $page_size);

        if ($list['data']) {
            $electroncard_data_mdl = new VslElectroncardDataModel();
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['total_stock'] = $electroncard_data_mdl->getCount(['electroncard_base_id' => $v['id']]);
                $list['data'][$k]['surplus_stock'] = $electroncard_data_mdl->getCount(['electroncard_base_id' => $v['id'], 'status' => 0]);
                $list['data'][$k]['sales'] = $list['data'][$k]['total_stock'] - $list['data'][$k]['surplus_stock'] ?: 0;
            }
        }

        return $list;
    }

    /**
     * 删除卡密库
     */
    public function delElectroncardBase($id)
    {
        //先判断该卡密库是否有关联商品，如果有则不能删除
        $goodsSer = new \data\service\Goods();
        $relation_data = $goodsSer->getGoodsDetailByCondition(['electroncard_base_id' => $id], 'electroncard_base_id');
        if ($relation_data) {
            return ['code' => -1, 'message' => '该卡密库有关联商品，不能删除'];
        }

        $electroncard_base_mdl = new VslElectroncardBaseModel();
        $res = $electroncard_base_mdl->delData(['id' => $id]);
        return ['code' => $res, 'message' => '操作成功'];
    }

    /**
     * 添加数据
     */
    public function addElectroncardData($data)
    {
        Db::startTrans();
        try {
            //先获取卡密库有没有开启数据去重
            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $is_data_different = $electroncard_base_mdl->Query(['id' => $data['id']], 'is_data_different')[0];

            foreach ($data['list'] as $k => $v) {
                $electroncard_data_mdl = new VslElectroncardDataModel();
                if ($is_data_different) {
                    //开启了数据去重
                    $value = implode(',', $v);
                    $repeat_data = $electroncard_data_mdl->getInfo(['electroncard_base_id' => $data['id'], 'value' => $value]);
                    if ($repeat_data) {
                        Db::rollback();
                        return ['code' => -1, 'message' => $value . '数据已存在'];
                    }
                }

                $save_data = [
                    'electroncard_base_id' => $data['id'],
                    'value' => implode(',', $v),
                    'status' => 0,
                    'shop_id' => $this->instance_id,
                    'website_id' => $this->website_id,
                    'create_time' => time(),
                ];
                $res = $electroncard_data_mdl->save($save_data);
            }
            //同步到商品
            $this->syncElectroncardStockToGoods($data['id']);
            Db::commit();
            return ['code' => $res, 'message' => '操作成功'];
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 卡密库详情
     */
    public function getElectroncardBaseDetail($id)
    {
        $electroncard_base_mdl = new VslElectroncardBaseModel();
        $info = $electroncard_base_mdl->alias('eb')
            ->join('vsl_electroncard_category ec', 'eb.electroncard_base_category_id=ec.id', 'left')
            ->field('eb.id,eb.electroncard_base_name,eb.memo,eb.description,eb.key_name,ec.category_name')
            ->where('eb.id', '=', $id)
            ->find();
        $info['key_name'] = explode(',', $info['key_name']);
        return $info;
    }

    /**
     * 卡密库详情
     */
    public function electroncardBaseDetail($page_index, $page_size, $condition, $order)
    {
        $electroncard_data_mdl = new VslElectroncardDataModel();
        $list = $electroncard_data_mdl->pageQuery($page_index, $page_size, $condition, $order, '*');

        if ($list['data']) {
            foreach ($list['data'] as $k => $v) {
                $list['data'][$k]['create_time'] = date('Y-m-d H:i:s', $v['create_time']);
                $list['data'][$k]['value'] = explode(',', $v['value']);
            }
        }

        //未出售
        $list['unsales'] = $electroncard_data_mdl->getCount(['electroncard_base_id' => $condition['electroncard_base_id'], 'status' => 0]);
        //已出售
        $list['sales'] = $electroncard_data_mdl->getCount(['electroncard_base_id' => $condition['electroncard_base_id'], 'status' => 1]);

        return $list;
    }

    /**
     * 删除卡密数据
     */
    public function delElectroncardData($id)
    {
        $electroncard_data_mdl = new VslElectroncardDataModel();
        $res = $electroncard_data_mdl->delData(['id' => $id]);
        return $res;
    }

    /**
     * 获取卡密数据详情
     */
    public function getElectroncardDataDetail($id)
    {
        $electroncard_data_mdl = new VslElectroncardDataModel();
        $electroncard_base_mdl = new VslElectroncardBaseModel();

        $data_info = $electroncard_data_mdl->getInfo(['id' => $id], 'electroncard_base_id,value');
        $key_name = $electroncard_base_mdl->Query(['id' => $data_info['electroncard_base_id']], 'key_name')[0];
        $key_name = explode(',', $key_name);
        $data_info['value'] = explode(',', $data_info['value']);

        $data = [];
        $res = [];
        foreach ($key_name as $k => $v) {
            $res[] = $v;
            $data[] = $res;
            unset($res);
        }

        for ($i = 0; $i < count($data); $i++) {
            array_push($data[$i], $data_info['value'][$i]);
        }

        return $data;
    }

    /**
     * 编辑卡密数据
     */
    public function updateElectroncardData($data)
    {
        try {
            $electroncard_data_mdl = new VslElectroncardDataModel();
            $electroncard_base_mdl = new VslElectroncardBaseModel();

            $data_info = $electroncard_data_mdl->getInfo(['id' => $data['id']], 'electroncard_base_id,value');
            $data['list'] = implode(',', $data['list']);

            if ($data['list'] != $data_info['value']) {
                //获取卡密库是否开启了数据去重
                $is_data_different = $electroncard_base_mdl->Query(['id' => $data_info['electroncard_base_id']], 'is_data_different')[0];
                if ($is_data_different) {
                    //开启了数据去重
                    $repeat_data = $electroncard_data_mdl->getInfo(['electroncard_base_id' => $data_info['electroncard_base_id'], 'value' => $data['list']]);
                    if ($repeat_data) {
                        return ['code' => -1, 'message' => '卡密数据重复'];
                    }
                }
            }

            $res = $electroncard_data_mdl->isUpdate(true)->save(['value' => $data['list']], ['id' => $data['id']]);
            if ($res > 0) {
                return ['code' => $res, 'message' => '操作成功'];
            } else {
                return ['code' => -1, 'message' => '操作失败'];
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 批量导入数据
     */
    public function uploadFile($file, $id)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');

        $base_path = 'upload' . DS . $this->website_id . DS . 'electroncard' . DS;
        if (!file_exists($base_path)) {
            $mode = intval('0777', 8);
            mkdir($base_path, $mode, true);
        }

        $info = $file->validate(['ext' => 'csv,xlsx,xls'])->move($base_path . 'Excel');

        if (!$info) {
            // 上传失败获取错误信息 
            return ['code' => 0, 'message' => '文件格式错误，请重新上传'];
        }

        $exclePath = $info->getSaveName(); //获取文件名
        $old_excel_name = $info->getInfo($exclePath);
        $file_types = explode(".", $old_excel_name['name']);

        $data = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'type' => end($file_types), //文件类型
            'excel_name' => $base_path . 'Excel' . DS . $exclePath,//Excel上传文件的地址,
            'old_excel_name' => $old_excel_name['name'] ?: '',
            'status' => 3,//等待中
            'create_time' => time(),
            'electroncard_base_id' => $id,
        ];

        $electroncardFile = new VslElectroncardDataFileModel();
        $save = $electroncardFile->isUpdate(false)->save($data);

        if (!$save) {
            return ['code' => 0, 'message' => '操作失败，请稍后重试'];
        }
        //加入队列
        $this->addElectroncardQueue($save);
        return ['code' => 1, 'message' => '已添加导入队列，等待执行'];
    }
    /**
     * 导表发货队列
     * @param $electroncard_id
     * @return array
     */
    public function addElectroncardQueue($electroncard_id)
    {
        if(config('is_high_powered')) {
            $data['electroncard_id'] = $electroncard_id;
            $exchange_name = config('rabbit_electroncard.exchange_name');
            $queue_name = config('rabbit_electroncard.queue_name');
            $routing_key = config('rabbit_electroncard.routing_key');
            $url = config('rabbit_interface_url.url');
            $electroncard_url = $url.'/rabbitTask/electroncardImport';
            $request_data = json_encode($data);
            $push_data = [
                "customType" => "electroncard",//标识什么业务场景
                "data" => $request_data,//请求数据
                "requestMethod" => "POST",
                "timeOut" => 20,
                "url" => $electroncard_url,
            ];
            $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
            $push_arr = json_decode($push_res, true);
            if($push_arr['code'] == 103){//未创建队列
                $create_res = createQueue($exchange_name, $queue_name, $routing_key);
                $create_arr = json_decode($create_res, true);
                if($create_arr['code'] != 200){
                    return ['code' => -1, 'message' => '未知错误：'.$create_arr];
                }elseif($create_arr['code'] == 200){
                    $push_res = pushData($push_data, $exchange_name, $queue_name, $routing_key, 0, 'DIRECT');
                }
            }
        }
    }
    /**
     * 获取批量导入文件
     */
    public function getElectroncardFileList($page_index, $page_size, $condition, $order)
    {
        $electroncardFile = new VslElectroncardDataFileModel();
        $list = $electroncardFile->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    /**
     * 修改批量导入文件
     */
    public function updateElectroncardFile($data, $condition)
    {
        $electroncardFile = new VslElectroncardDataFileModel();
        return $electroncardFile->save($data, $condition);
    }

    /**
     * 执行批量导入
     */
    public function electroncardDataByExcel($file, $shop_id, $website_id, $electroncard_base_id)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        Db::startTrans();
        try {
            //获取文件类型
            Vendor('PhpExcel.PHPExcel');
            $file_type = \PHPExcel_IOFactory::identify($file);
            if ($file_type) {
                $objReader = \PHPExcel_IOFactory::createReader($file_type);
                $obj_PHPExcel = $objReader->load($file); //加载文件内容,编码utf-8
                $excel_array = $obj_PHPExcel->getsheet()->toArray(); //转换为数组格式
            } else {
                return ['code' => 0, 'message' => '文件格式错误，请重新上传'];
            }

            $i = 0; //成功数量
            $j = 0; //全部数量
            $error_arr = [];
            $excel_error_message = '';

            $key_array = $excel_array[0];//标题
            unset($excel_array[0]);
            $excel_array = array_values($excel_array);

            if (empty($excel_array)) {
                return ['code' => 0, 'message' => 'Excel文件为空', 'data' => $file];
            }

            //获取卡密库的字段长度
            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $electroncard_base_info = $electroncard_base_mdl->getInfo(['id' => $electroncard_base_id], 'key_name,is_data_different');
            $key_name = explode(',', $electroncard_base_info['key_name']);

            foreach ($excel_array as $k => $v) {
                $err_num = 0;
                $j++;
                if (count($v) != count($key_name)) {
                    $excel_error_message .= $k + 1 . '行数据结构与字段结构不一致;';
                    $error_arr[] = $v;
                    continue;
                } else {
                    foreach ($v as $key => $val) {
                        if (empty($val)) {
                            $err_num++;
                            $excel_error_message .= $k + 1 . '行数据结构不完整;';
                            $error_arr[] = $v;
                            break;
                        }
                    }
                    if (empty($err_num)) {
                        //先判断卡密库是否开启数据去重
                        $electroncard_data_mdl = new VslElectroncardDataModel();
                        $v = implode(',', $v);
                        if ($electroncard_base_info['is_data_different']) {
                            //开启了数据去重
                            $repeat_data = $electroncard_data_mdl->getInfo(['electroncard_base_id' => $electroncard_base_id, 'value' => $v]);
                            if ($repeat_data) {
                                $excel_error_message .= $k + 1 . '行数据已存在;';
                                $error_arr[] = explode(',', $v);
                                continue;
                            }
                        }

                        $save_data = [
                            'electroncard_base_id' => $electroncard_base_id,
                            'value' => $v,
                            'status' => 0,
                            'shop_id' => $shop_id,
                            'website_id' => $website_id,
                            'create_time' => time(),
                        ];
                        $res = $electroncard_data_mdl->save($save_data);
                        if (!$res) {
                            $excel_error_message .= $k + 1 . '行数据导入失败;';
                            $error_arr[] = explode(',', $v);
                            continue;
                        }
                        $i++;
                    }
                }
            }
            //同步到商品
            $this->syncElectroncardStockToGoods($electroncard_base_id);

            $error_new_path = '';
            if ($error_arr) {
                $xlsName = getFileNameOfUrl($file, TRUE);
                $suffix = substr($xlsName, strripos($xlsName, '.'));
                $file_name = substr($xlsName, 0, strripos($xlsName, '.'));
                $data = $error_arr;
                $path = str_replace($xlsName, '', $file);
                $xlsCell = [];
                $c = 0;
                foreach ($key_array as $k1 => $v1) {
                    $xlsCell[$k1] = [$c, $v1];
                    $c++;
                }
                $res = dataExcel($file_name, $xlsCell, $data, $path, $suffix);
                if ($res['code'] > 0) {
                    $error_new_path = $res['data'];
                }
            }

            unset($res);
            if ($i == 0) {
                $res['code'] = 0;
                $res['message'] = $excel_error_message;
                $res['data'] = $error_new_path;
                Db::rollback();
            } else if ($i == $j) {
                $res['code'] = 1;
                $res['message'] = '导入总数量：' . $j . ',导入成功';
                Db::commit();
            } else {
                $res['code'] = 2;
                $res['message'] = '导入总数量：' . $j . ',导入成功数量：' . $i . ',失败原因：' . $excel_error_message;
                $res['data'] = $error_new_path;
                Db::commit();
            }
            @unlink($file);
            return $res;
        } catch (\Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
            $log_dir = getcwd() . '/electroncard_data_by_excel.log';
            file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    /**
     * 同步卡密库库存到商品
     */
    public function syncElectroncardStockToGoods($electroncard_base_id)
    {
        Db::startTrans();
        try {
            $goodsSer = new \data\service\Goods();
            //判断该卡密库有没有关联商品
            $goods_list = $goodsSer->getGoodsListByCondition(['goods_type' => 5, 'electroncard_base_id' => $electroncard_base_id], 'goods_id');

            if ($goods_list) {
                //查出该卡密库最新的库存
                $electroncard_data_mdl = new VslElectroncardDataModel();
                $stock = $electroncard_data_mdl->getCount(['electroncard_base_id' => $electroncard_base_id, 'status' => 0]);
                $redis = connectRedis();
                foreach ($goods_list as $k => $v) {
                    //商品表更新
                    $goodsSer->updateGoods(['goods_id' => $v['goods_id']],['stock' => $stock], $v['goods_id']);
                    //更新sku表
                    $goods_sku_model = new VslGoodsSkuModel();
                    $goods_sku_model->isUpdate(true)->save(['stock' => $stock], ['goods_id' => $v['goods_id']]);
                    $sku_id = $goods_sku_model->getInfo(['goods_id' => $v['goods_id']], 'sku_id')['sku_id'];
                    //维护普通商品库存到redis
                    $goods_key = 'goods_'.$v['goods_id'].'_'.$sku_id;
                    if(!$redis->get($goods_key)){
                        $redis->set($goods_key, $stock);
                    }
                }
            }
            Db::commit();
            return 1;
        } catch (\Exception $e) {
            Db::rollback();
            return $e->getMessage();
        }
    }

    /**
     * 随机分配卡密数据
     */
    public function randomElectroncardData($electroncard_base_id, $num)
    {
        try {
            $electroncard_data_mdl = new VslElectroncardDataModel();
            $where = [
                'electroncard_base_id' => $electroncard_base_id,
                'status' => 0,
            ];
            $electroncard_data_id = $electroncard_data_mdl
                ->where($where)
                ->order('rand()')
                ->field('id')
                ->limit($num)
                ->select();

            $electroncard_data_ids = '';
            if ($electroncard_data_id) {
                foreach ($electroncard_data_id as $k => $v) {
                    //改变状态为已售
                    $electroncard_data_mdl = new VslElectroncardDataModel();
                    $electroncard_data_mdl->isUpdate(true)->save(['status' => 1], ['id' => $v['id']]);
                    $electroncard_data_ids .= $v['id'] . ',';
                }
                $electroncard_data_ids = trim($electroncard_data_ids, ',');
            }

            return $electroncard_data_ids;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 获取电子卡密信息
     */
    public function getElectroncardMsg($electroncard_data_id)
    {
        $electroncard_data_mdl = new VslElectroncardDataModel();
        $electroncard_base_mdl = new VslElectroncardBaseModel();
        $electroncard_data_id = explode(',', $electroncard_data_id);
        $electroncard_msg = '';
        $electroncard_msgs = '';
        $description = '';
        $num = 0;

        foreach ($electroncard_data_id as $k => $v) {
            $num++;
            $electroncard_data_info = $electroncard_data_mdl->getInfo(['id' => $v], 'electroncard_base_id,value');
            $electroncard_data_info['value'] = explode(',', $electroncard_data_info['value']);

            //获取字段名
            $electroncard_base_info = $electroncard_base_mdl->getInfo(['id' => $electroncard_data_info['electroncard_base_id']], 'key_name,description');
            $key_name = explode(',', $electroncard_base_info['key_name']);
            $description = $electroncard_base_info['description'];

            //组装卡密信息
            if (count($electroncard_data_id) == 1) {
                foreach ($key_name as $k1 => $v1) {
                    $electroncard_msg .= $v1 . ':' . $electroncard_data_info['value'][$k1] . ',';
                }
                $electroncard_msg = trim($electroncard_msg, ',');
                $electroncard_msgs .= $electroncard_msg;
            } else {
                foreach ($key_name as $k1 => $v1) {
                    $electroncard_msg .= $v1 . ':' . $electroncard_data_info['value'][$k1] . ',';
                }
                $electroncard_msg = trim($electroncard_msg, ',');
                $electroncard_msgs .= '卡密信息' . $num . '(' . $electroncard_msg . ')' . ',';
                unset($electroncard_msg);
            }

        }
        $electroncard_msgs = trim($electroncard_msgs, ',');

        return [
            'electroncard_msg' => $electroncard_msgs,
            'description' => $description,
        ];
    }

    /**
     * 消息推送
     */
    public function pushMessage($uid, $electroncard_data_id, $goods_name, $website_id=0, $instance_id=0)
    {
        $this->website_id = $this->website_id ? $this->website_id : $website_id;
        $this->instance_id = $this->instance_id ? $this->instance_id : $instance_id;
        try {
            $push_message_mdl = new SysMessagePushModel();
            $content = $this->getElectroncardMsg($electroncard_data_id)['electroncard_msg'];

            //站内通知
            $station_notice_info = $push_message_mdl->getInfo(['template_type' => 'electroncard_station_notice', 'website_id' => $this->website_id], '*');
            if ($station_notice_info['is_enable']) {
                $message = str_replace('${goodsname}', $goods_name, $station_notice_info['template_content']);
                $message = str_replace('${electroncardmsg}', $content, $message);
                $save_data = [
                    'title' => '电子卡密信息',
                    'content' => $message,
                    'status' => 1,
                    'to_uid' => $uid,
                    'is_check' => 0,
                    'create_time' => time(),
                ];
                $msg_reminder = new MsgReminderModel();
                $msg_reminder->save($save_data);
            }

            //微信公众号客服消息
            $wx_notice_info = $push_message_mdl->getInfo(['template_type' => 'electroncard_wx_notice', 'website_id' => $this->website_id], '*');
            if ($wx_notice_info['is_enable']) {
                $message_str = str_replace('${goodsname}', $goods_name, $wx_notice_info['template_content']);
                $message_str = str_replace('${electroncardmsg}', $content, $message_str);
                runhook("Notify", "sendCustomMessage", ['messageType' => 'electroncard_wx_notice', "uid" => $uid, "message_str" => $message_str]);
            }

            //发送短信
            runhook("Notify", "sendElectroncardMsgBySms", ["uid" => $uid, "goods_name" => $goods_name, "content" => $content, "shop_id" => $this->instance_id]);

            return 1;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }
}
