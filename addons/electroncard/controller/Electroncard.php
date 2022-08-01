<?php

namespace addons\electroncard\controller;

use addons\electroncard\Electroncard as baseElectroncard;
use addons\electroncard\model\VslElectroncardBaseModel;
use addons\electroncard\server\Electroncard as ElectroncardServer;
use addons\distribution\model\SysMessageItemModel;
use addons\distribution\model\SysMessagePushModel;
use data\model\VslExcelsModel;
use data\service\ExcelsExport;

class Electroncard extends baseElectroncard
{
    public function __construct()
    {
        parent::__construct();

    }

    /**
     * 新增或编辑卡密库分类
     */
    public function addOrUpdateCategory()
    {
        $data = request()->post();

        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->addOrUpdateCategory($data);

        return AjaxReturn($res);
    }

    /**
     * 卡密库分类列表
     */
    public function electroncardCategoryList()
    {
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
        ];

        $order = 'create_time desc';

        $electroncard_server = new ElectroncardServer;
        $list = $electroncard_server->electroncardCategoryList($condition, $order);

        return $list;
    }

    /**
     * 删除卡密库分类
     */
    public function delElectroncardCategory()
    {
        $category_id = request()->post('category_id', 0);

        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->delElectroncardCategory($category_id);

        return AjaxReturn($res);
    }

    /**
     * 基础设置
     */
    public function electroncardBaseSetting()
    {
        $message = new SysMessagePushModel();
        $list = $message->getQuery(['website_id' => $this->website_id, 'type' => 6], '*', 'template_id asc');
        if (empty($list)) {
            $item = new SysMessageItemModel();
            $goods_item_id = $item->Query(['item_name' => '商品名称'],'id')[0];
            if(empty($goods_item_id)) {
                $goods_data = [
                    'item_name' => '商品名称',
                    'show_name' => '[商品名称]',
                    'replace_name' => '${goodsname}',
                ];
                $item = new SysMessageItemModel();
                $goods_item_id = $item->save($goods_data);
            }
            $msg_item_id = $item->Query(['item_name' => '卡密信息'],'id')[0];
            if(empty($msg_item_id)) {
                $msg_data = [
                    'item_name' => '卡密信息',
                    'show_name' => '[卡密信息]',
                    'replace_name' => '${electroncardmsg}',
                ];
                $item = new SysMessageItemModel();
                $msg_item_id = $item->save($msg_data);
            }

            $arr = [
                [
                    'template_type' => 'electroncard_station_notice',
                    'template_title' => '电子卡密站内通知',
                    'template_content' => '您购买的${goodsname}，信息为${electroncardmsg}。',
                    'is_enable' => 1,
                    'website_id' => $this->website_id,
                    'sign_item' => $goods_item_id.','.$msg_item_id,
                    'type' => 6,
                ],
                [
                    'template_type' => 'electroncard_wx_notice',
                    'template_title' => '电子卡密公众号客服消息',
                    'template_content' => '您购买的${goodsname}，信息为${electroncardmsg}。',
                    'is_enable' => 0,
                    'website_id' => $this->website_id,
                    'sign_item' => $goods_item_id.','.$msg_item_id,
                    'type' => 6,
                ]
            ];
            $message = new SysMessagePushModel();
            $message->saveAll($arr,true);
            $list = $message->getQuery(['website_id' => $this->website_id, 'type' => 6], '*', 'template_id asc');
        }

        $item = new SysMessageItemModel();
        foreach ($list as $k => $v) {
            $list[$k]['sign'] = $item->getQuery(['id' => ['in', $v['sign_item']]], '*', '');
        }

        return $list;
    }

    /**
     * 保存基础设置
     */
    public function saveSetting()
    {
        $data = request()->post();

        foreach ($data['list'] as $k => $v) {
            $message = new SysMessagePushModel();
            $res = $message->isUpdate(true)->save(['is_enable' => $v[1], 'template_content' => $v[2]], ['template_id' => $v[0]]);
        }

        if ($res) {
            $this->addUserLog('电子卡密设置', $res);
        }

        return AjaxReturn($res);
    }

    /**
     * 卡密库列表
     */
    public function electroncardBaseList()
    {
        $page_index = request()->post('page_index', 1);
        $electroncard_base_name = request()->post('electroncard_name', '');
        $electroncard_category = request()->post('electroncard_category', '');

        if ($electroncard_base_name) {
            $condition['eb.electroncard_base_name'] = array('like', '%' . $electroncard_base_name . '%');
        }

        if ($electroncard_category) {
            $condition['eb.electroncard_base_category_id'] = $electroncard_category;
        }

        $condition['eb.shop_id'] = $this->instance_id;
        $condition['eb.website_id'] = $this->website_id;

        $order = 'eb.create_time desc';

        $electroncard_server = new ElectroncardServer;
        $list = $electroncard_server->electroncardBaseList($page_index, PAGESIZE, $condition, $order);

        return $list;
    }

    /**
     * 添加或编辑卡密库
     */
    public function addOrUpdateElectroncardBase()
    {
        $data = request()->post();

        if (empty($data['id'])) {
            if (empty($data['electroncard_base_name']) || empty($data['electroncard_base_category']) || empty($data['key_list'])) {
                return json(['code' => -1, 'message' => '缺少参数']);
            }
        } else {
            if (empty($data['electroncard_base_name']) || empty($data['electroncard_base_category'])) {
                return json(['code' => -1, 'message' => '缺少参数']);
            }
        }


        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->addOrUpdateElectroncardBase($data);

        return AjaxReturn($res);
    }

    /**
     * 删除卡密库
     */
    public function delElectroncardBase()
    {
        $id = request()->post('id', 0);

        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->delElectroncardBase($id);

        return $res;
    }

    /**
     * 自动填充
     */
    public function autoFill()
    {
        $key = request()->post('key', '');
        $num = request()->post('num', '');

        $data = [];
        $last_num = substr($key, -1);
        $str = substr($key, 0, strlen($key) - 1);

        if (strlen($key) == 1) {
            for ($i = $last_num; $i <= $num; $i++) {
                $data[] = $i;
            }
        } else {
            for ($i = $last_num; $i <= $num; $i++) {
                $data[] = $str . $i;
            }
        }

        return json([
            'code' => 1,
            'message' => '获取成功',
            'data' => $data,
        ]);
    }

    /**
     * 添加数据
     */
    public function addElectroncardData()
    {
        $data = request()->post();

        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->addElectroncardData($data);

        return $res;
    }

    /**
     * 下载模板
     */
    public function downTpl()
    {
        $id = request()->get('id');

        $electroncard_base_mdl = new VslElectroncardBaseModel();
        $key_name = $electroncard_base_mdl->Query(['id' => $id], 'key_name')[0];
        $key_name = explode(',', $key_name);

        $xlsName = "卡密数据模板";
        $xlsCells = [];
        $xlsCell = [];

        foreach ($key_name as $k => $v) {
            for ($i = 0; $i < 2; $i++) {
                $xlsCells[] = $v;
            }
            $xlsCell[$k] = $xlsCells;
            unset($xlsCells);
        }

        dataExcel($xlsName, $xlsCell, []);
    }

    /**
     * 卡密库详情
     */
    public function electroncardBaseDetail()
    {
        $page_index = request()->post('page_index', 1);
        $id = request()->post('id', 0);
        $status = request()->post('status', 0);
        $search_text = request()->post('search_text', '');

        $condition = array(
            'electroncard_base_id' => $id,
            'status' => $status,
            explode(',', 'value')[0] => array(
                'like',
                '%' . $search_text . '%'
            )
        );
        $order = 'create_time desc';

        $electroncard_server = new ElectroncardServer;
        $list = $electroncard_server->electroncardBaseDetail($page_index, PAGESIZE, $condition, $order);

        return $list;
    }

    /**
     * 删除卡密数据
     */
    public function delElectroncardData()
    {
        $id = request()->post('id', 0);

        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->delElectroncardData($id);

        return AjaxReturn($res);
    }

    /**
     * 编辑卡密数据
     */
    public function updateElectroncardData()
    {
        $data = request()->post();

        if (empty($data['list'])) {
            return ['code' => -1, 'message' => '卡密数据不能为空'];
        }

        $electroncard_server = new ElectroncardServer;
        $res = $electroncard_server->updateElectroncardData($data);

        return $res;
    }

    /**
     * 导出Excel
     */
    public function dataExcel()
    {
        try {
            $id = request()->get('id', 0);
            $status = request()->get('status', 0);
            $search_text = request()->get('search_text', '');

            $condition = array(
                'electroncard_base_id' => $id,
                'status' => $status,
                explode(',', 'value')[0] => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );

//            $electroncard_server = new ElectroncardServer;
//            $list = $electroncard_server->electroncardBaseDetail(1, PAGESIZE, $condition, $order);

            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $key_name = $electroncard_base_mdl->Query(['id' => $id], 'key_name')[0];
            $key_name = explode(',', $key_name);

            $xlsName = "电子卡密数据列表";
            $xlsCells = [];
            $xlsCell = [];

            foreach ($key_name as $k => $v) {
                for ($i = 0; $i < 2; $i++) {
                    $xlsCells[] = $v;
                }
                $xlsCell[$k] = $xlsCells;
                unset($xlsCells);
            }
            $status_key =
                [
                    0 => 'status',
                    1 => '状态'
                ];
            $create_time_key =
                [
                    0 => 'create_time',
                    1 => '添加时间'
                ];

            array_push($xlsCell, $status_key);
            array_push($xlsCell, $create_time_key);

//            $data = [];
//            $i = 0;
//            foreach ($list["data"] as $k => $v) {
//                foreach ($v['value'] as $key => $val) {
//                    $data[$i][$xlsCell[$key][0]] = $val;
//                }
//                $data[$i]["status"] = $v["status"] == 0 ? '未出售' : '已出售';
//                $data[$i]["create_time"] = $v['create_time'];
//                $i +=1;
//            }
//
//            dataExcel($xlsName, $xlsCell, $data);
            //导出操作移到到计划任务统一执行
            $insert_data = array(
                'type' => 16,
                'status' => 0,
                'exname' => $xlsName,
                'website_id' => $this->website_id,
                'addtime' => time(),
                'ids' => serialize($xlsCell),
                'conditions' => serialize($condition),
            );
            $excels_export = new ExcelsExport();
            $res = $excels_export->insertData($insert_data);
            return $res;
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * C端导出Excel
     */
    public function adminDataExcel()
    {
        try {
            $id = request()->get('id', 0);
            $status = request()->get('status', 0);
            $search_text = request()->get('search_text', '');

            $condition = array(
                'electroncard_base_id' => $id,
                'status' => $status,
                explode(',', 'value')[0] => array(
                    'like',
                    '%' . $search_text . '%'
                )
            );

            $electroncard_server = new ElectroncardServer;
            $list = $electroncard_server->electroncardBaseDetail(1, 0, $condition, 'create_time desc');

            $electroncard_base_mdl = new VslElectroncardBaseModel();
            $key_name = $electroncard_base_mdl->Query(['id' => $id], 'key_name')[0];
            $key_name = explode(',', $key_name);

            $xlsName = "电子卡密数据列表";
            $xlsCells = [];
            $xlsCell = [];

            foreach ($key_name as $k => $v) {
                for ($i = 0; $i < 2; $i++) {
                    $xlsCells[] = $v;
                }
                $xlsCell[$k] = $xlsCells;
                unset($xlsCells);
            }
            $status_key =
                [
                    0 => 'status',
                    1 => '状态'
                ];
            $create_time_key =
                [
                    0 => 'create_time',
                    1 => '添加时间'
                ];

            array_push($xlsCell, $status_key);
            array_push($xlsCell, $create_time_key);

            $data = [];
            $i = 0;
            foreach ($list["data"] as $k => $v) {
                foreach ($v['value'] as $key => $val) {
                    $data[$i][$xlsCell[$key][0]] = $val;
                }
                $data[$i]["status"] = $v["status"] == 0 ? '未出售' : '已出售';
                $data[$i]["create_time"] = $v['create_time'];
                $i += 1;
            }

            dataExcel($xlsName, $xlsCell, $data);
//            //导出操作移到到计划任务统一执行
//            $insert_data = array(
//                'type' => 16,
//                'status' => 0,
//                'exname' => $xlsName,
//                'website_id' => $this->website_id,
//                'addtime' => time(),
//                'ids' => serialize($xlsCell),
//                'conditions' => serialize($condition),
//            );
//            $excelsModel = new VslExcelsModel();
//            $check = $excelsModel->getInfo(['type' => 16, 'status' => ['<', 1], 'exname' => $xlsName, 'ids' => serialize($xlsCell), 'conditions' => serialize($condition)]);
//            if ($check) {
//                return ['code' => -1, 'message' => '已存在该任务，请前往系统-计划任务中查看'];
//            } else {
//                $excelsModel->save($insert_data);
//                return ['code' => 0, 'message' => '新建导出任务成功，请前往系统-计划任务中查看'];
//            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 批量导入数据
     */
    public function uploadFile()
    {
        if (request()->isPost()) {
            $file = request()->file('excel');
            $id = request()->post('id', 0);

            if (!$file) {
                return ['code' => 0, 'message' => '请上传文件'];
            }

            $electroncard_server = new ElectroncardServer;
            $result = $electroncard_server->uploadFile($file, $id);

            if ($result['code'] > 0) {
                $this->addUserLog($this->user->getUserInfo('', 'user_name')['user_name'] . '批量导入电子卡密数据', $result['message']);
            }

            return $result;
        }
    }
}