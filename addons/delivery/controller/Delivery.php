<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/12 0012
 * Time: 11:44
 */

namespace addons\delivery\controller;

use addons\delivery\Delivery as baseDelivery;
use addons\groupshopping\model\VslGroupShoppingRecordModel;
use addons\delivery\service\Delivery as deliveryService;
use data\model\VslExcelsModel;
use data\model\VslExpressCompanyShopRelationModel;
use data\model\VslOrderExpressCompanyModel;
use data\service\Address;
use data\service\ExcelsExport;
use data\service\Order as orderService;
use think\Cookie;
use data\service\Config;

class Delivery extends baseDelivery
{
    private $baseDelivery;

    public function __construct()
    {
        parent::__construct();
        $this->baseDelivery = new deliveryService();
    }
    
    /**
     * 打印机配置
     * @return array|void
     */
    public function printSetting()
    {
        $configService = new Config();
        try {
            $post_data = request()->post();
            $value = json_encode($post_data, JSON_UNESCAPED_UNICODE);
            $param = [
                'value' => $value,
                'website_id' => $this->website_id,
                'supplier_id' => $this->supplier_id,
                'instance_id' => 0,
                'key' => "DELIVERY_ASSISTANT",
                'desc' => "发货助手设置",
                'is_use' => 1,
            ];
            $res = $configService->setConfigOne($param);
            if($res){
                $this->addUserLog('保存打印设置', $res);
            }
            setAddons('delivery', $this->website_id, $this->instance_id);
            return ['code' => $res, 'message' => '修改成功'];
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => $e->getMessage()];
        }
    }
    /**
     * 电子面单
     */
    public function formList()
    {
        $page_index = request()->post('page_index') ?: 1;
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        if (request()->post('search_text')) {
            $condition['template_name'] = ['LIKE', '%' . request()->post('search_text') . '%'];
        }
        $data = $this->baseDelivery->formTemplateList($page_index, $page_size, $condition, 'create_time DESC');
        return $data;
    }
    
    /**
     * 发货单
     */
    public function deliveryList()
    {
        $page_index = request()->post('page_index') ?: 1;
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id; //供应商
        if (request()->post('search_text')) {
            $condition['template_name'] = ['LIKE', '%' . request()->post('search_text') . '%'];
        }
        $data = $this->baseDelivery->deliveryTemplateList($page_index, $page_size, $condition, 'create_time DESC');
        return $data;
    }
    
    /**
     * 快递单
     */
    public function expressList()
    {
        $page_index = request()->post('page_index') ?: 1;
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        if (request()->post('search_text')) {
            $condition['template_name'] = ['LIKE', '%' . request()->post('search_text') . '%'];
        }
        $data = $this->baseDelivery->expressTemplateList($page_index, $page_size, $condition, 'create_time DESC');
        return $data;
    }
    
    /**
     * 发货人
     */
    public function senderList()
    {
        $page_index = request()->post('page_index') ?: 1;
        $page_size = request()->post('page_size', PAGESIZE);
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        if (request()->post('search_text')) {
            $condition['template_name'] = ['LIKE', '%' . request()->post('search_text') . '%'];
        }
        $data = $this->baseDelivery->senderTemplateList($page_index, $page_size, $condition, 'create_time DESC');
        return $data;
    }
    
    /**
     * 删除电子面单
     */
    public function deleteFormTemplate()
    {
        $form_template_id = request()->post('form_template_id');
        if (empty($form_template_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['form_template_id'] = $form_template_id;
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $res = $this->baseDelivery->deleteFormTemplate($condition);
        if ($res) {
            $this->addUserLog('删除电子面单', $res);
        }
        return $res;

    }
    
    /**
     * 删除发货人
     */
    public function deleteSenderTemplate()
    {
        $sender_template_id = request()->post('sender_template_id');
        if (empty($sender_template_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['sender_template_id'] = $sender_template_id;
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $res = $this->baseDelivery->deleteSenderTemplate($condition);
        if ($res) {
            $this->addUserLog('删除发货人模板', $res);
        }
        return $res;
    }
    /**
     * 删除发货单
     */
    public function deleteDeliveryTemplate()
    {
        $delivery_template_id = request()->post('delivery_template_id');
        if (empty($delivery_template_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['delivery_template_id'] = $delivery_template_id;
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $res = $this->baseDelivery->deleteDeliveryTemplate($condition);
        if ($res) {
            $this->addUserLog('删除发货单模板', $res);
        }
        return $res;
    }

    public function saveFormTemplate()
    {
        $data = request()->post();
        if ($data['form_template_id']) {
            $condition['form_template_id'] = $data['form_template_id'];
            unset($data['form_template_id']);
            $data['modify_time'] = time();
        } else {
            $condition = [];
            $data['create_time'] = time();
            $data['website_id'] = $this->website_id;
            $data['shop_id'] = $this->instance_id;
            $data['supplier_id'] = $this->supplier_id;//供应商
        }
        if ($data['is_default']) {
            $condition_default['website_id'] = $this->website_id;
            $condition_default['shop_id'] = $this->instance_id;
            $condition_default['supplier_id'] = $this->supplier_id;//供应商
            $condition_default['is_default'] = 1;
            $this->baseDelivery->saveFormTemplate(['is_default' => 0], $condition_default);
        }
        $result = $this->baseDelivery->saveFormTemplate($data, $condition);
        if ($result) {
            $this->addUserLog('保存电子面单模板', $result);
        }
        return AjaxReturn($result);
    }

    public function saveExpressTemplate()
    {
        $id = request()->post('id');
        $template_data = request()->post('template_data/a');
        if (empty($id) || empty($template_data)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $data['create_time'] = time();
        $data['modify_time'] = time();
        $data['template_data'] = json_encode($template_data, true);
        $result = $this->baseDelivery->saveExpressTemplate($data, ['id' => $id]);
        if ($result) {
            $this->addUserLog('保存物流单模板', $result);
        }
        return AjaxReturn($result);
    }

    public function saveDeliveryTemplate()
    {
        $delivery_template_id = request()->post('delivery_template_id');
        $template_data = request()->post('template_data/a');
        $template_name = request()->post('template_name');
        if (empty($template_data) || empty($template_name)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition = [];
        if ($delivery_template_id) {
            $condition['delivery_template_id'] = $delivery_template_id;
            $data['modify_time'] = time();
        } else {
            $data['create_time'] = time();
            $data['shop_id'] = $this->instance_id;
            $data['website_id'] = $this->website_id;
            $data['supplier_id'] = $this->supplier_id;//供应商
        }
        $data['template_data'] = json_encode($template_data, true);
        $data['template_name'] = $template_name;
        $result = $this->baseDelivery->saveDeliveryTemplate($data, $condition);
        if ($result) {
            $this->addUserLog('保存发货单模板', $result);
        }
        return AjaxReturn($result);
    }

    public function saveSenderTemplate()
    {
        $data = request()->post();
        if ($data['sender_template_id']) {
            $condition['sender_template_id'] = $data['sender_template_id'];
            unset($data['sender_template_id']);
            $data['modify_time'] = time();
        } else {
            $condition = [];
            $data['create_time'] = time();
            $data['website_id'] = $this->website_id;
            $data['shop_id'] = $this->instance_id;
            $data['supplier_id'] = $this->supplier_id;//供应商
        }
        if ($data['is_default']) {
            $condition_default['website_id'] = $this->website_id;
            $condition_default['shop_id'] = $this->instance_id;
            $condition_default['supplier_id'] = $this->supplier_id;//供应商
            $condition_default['is_default'] = 1;
            $this->baseDelivery->saveSenderTemplate(['is_default' => 0], $condition_default);
        }
        $result = $this->baseDelivery->saveSenderTemplate($data, $condition);
        if ($result) {
            $this->addUserLog('保存发货人模板', $result);
        }
        return AjaxReturn($result);
    }
    
    /**
     * 电子面单模板设为默认
     */
    public function defaultForm()
    {
        $form_template_id = request()->post('form_template_id');
        if (empty($form_template_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $condition['is_default'] = 1;
        $this->baseDelivery->saveFormTemplate(['is_default' => 0], $condition);
        $result = $this->baseDelivery->saveFormTemplate(['is_default' => 1], ['form_template_id' => $form_template_id]);
        if ($result) {
            $this->addUserLog('设置默认电子面单模板', $result);
        }
        return AjaxReturn($result);
    }
    
    /**
     * 快递单设为默认
     */
    public function defaultExpress()
    {
        $id = request()->post('id');
        if (empty($id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $condition['is_default'] = 1;
        $this->baseDelivery->saveExpressTemplate(['is_default' => 0], $condition);
        $result = $this->baseDelivery->saveExpressTemplate(['is_default' => 1], ['id' => $id]);
        if ($result) {
            $this->addUserLog('设置默认快递单模板', $result);
        }
        return AjaxReturn($result);
    }
    
    /**
     * 发货单设为默认
     */
    public function defaultDelivery()
    {
        $delivery_template_id = request()->post('delivery_template_id');
        if (empty($delivery_template_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $condition['is_default'] = 1;
        $this->baseDelivery->saveDeliveryTemplate(['is_default' => 0], $condition);
        $result = $this->baseDelivery->saveDeliveryTemplate(['is_default' => 1], ['delivery_template_id' => $delivery_template_id]);
        if ($result) {
            $this->addUserLog('设置默认发货单模板', $result);
        }
        return AjaxReturn($result);
    }
    /**
     * 发货人设为默认
     */
    public function defaultSender()
    {
        $sender_template_id = request()->post('sender_template_id');
        if (empty($sender_template_id)) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition['website_id'] = $this->website_id;
        $condition['shop_id'] = $this->instance_id;
        $condition['supplier_id'] = $this->supplier_id;//供应商
        $condition['is_default'] = 1;
        $this->baseDelivery->saveSenderTemplate(['is_default' => 0], $condition);
        $result = $this->baseDelivery->saveSenderTemplate(['is_default' => 1], ['sender_template_id' => $sender_template_id]);
        if ($result) {
            $this->addUserLog('设置默认发货人模板', $result);
        }
        return AjaxReturn($result);
    }

    public function formExpressCompanyList()
    {
        return $this->baseDelivery->formExpressCompanyList();
    }

    public function formTemplateDetail()
    {
        $condition['form_template_id'] = request()->post('id');
        return $this->baseDelivery->formTemplateDetail($condition);
    }

    public function expressTemplateDetail()
    {
        $model = new VslExpressCompanyShopRelationModel();
        if (request()->post('id')) {
            $condition['id'] = request()->post('id');
        }
        if (request()->post('is_default')) {
            $condition[$model->table . '.website_id'] = $this->website_id;
            $condition[$model->table . '.shop_id'] = $this->instance_id;
            $condition[$model->table . '.supplier_id'] = $this->supplier_id;//供应商
            $condition[$model->table . '.is_default'] = 1;
        }
        return $this->baseDelivery->expressTemplateDetail($condition, ['express_company']);
    }

    public function deliveryTemplateDetail()
    {
        if (request()->post('id')) {
            $condition['delivery_template_id'] = request()->post('id');
        }
        if (request()->post('is_default')) {
            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = $this->instance_id;
            $condition['supplier_id'] = $this->supplier_id;//供应商
            $condition['is_default'] = 1;
        }

        return $this->baseDelivery->deliveryTemplateDetail($condition);
    }

    public function senderTemplateDetail()
    {
        $with = [];
        if (request()->post('id')) {
            $condition['sender_template_id'] = request()->post('id');
        }
        if (request()->post('is_default')) {
            $condition['vsl_sender_template.website_id'] = $this->website_id;
            $condition['vsl_sender_template.shop_id'] = $this->instance_id;
            $condition['vsl_sender_template.supplier_id'] = $this->supplier_id;//供应商
            $condition['vsl_sender_template.is_default'] = 1;
            $with = ['province', 'city', 'district'];
        }

        return $this->baseDelivery->senderTemplateDetail($condition, $with);
    }

    public function formPrint()
    {
        $order_goods_id_array = request()->post('order_goods_id_array/a');
        $receiver_info = request()->post('receiver_info/a');
        if (empty($order_goods_id_array)) {
            return ['code' => -1, 'message' => '请选择至少一项订单商品,'];
        }
        $result = $this->baseDelivery->formPrint($order_goods_id_array, $receiver_info);
        return $result;
    }

    public function area()
    {
        $area_service = new Address();
        $fields = ['sp.province_id', 'sp.province_name', 'sc.city_id', 'sc.city_name', 'sd.district_id', 'sd.district_name'];
        return $area_service->allArea([], $fields, 'sd.district_id');
    }

    /**
     * 发货助手一键发货
     */
    public function orderDeliveryModal()
    {
        $order_goods_id_string = Cookie::get('order_goods_id_string');
        Cookie::delete('order_goods_id_string');
        $order_service = new orderService();
        $order_list = $order_service->deliveryOrderList(['order_goods_id' => ['IN', $order_goods_id_string]], ['order.order_goods_express']);
        // 使用默认快递公司
        $express_template_model = new VslExpressCompanyShopRelationModel();
        $exp_template_info = $express_template_model->getInfo(
            [
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'supplier_id' => $this->supplier_id,
                'is_default' => 1
            ]
        );
        $list['default_company_name'] = '';
        if ($exp_template_info) {
            $express_company_model = new VslOrderExpressCompanyModel();
            $list['default_company_name'] = $express_company_model::get($exp_template_info['co_id'])['company_name'];
        }
        $list['list'] = $order_list;
        return $list;
    }

    public function orderDelivery()
    {
        $list = request()->post('list/a');
        $order_service = new orderService();
        $result = $order_service->ordersDelivery($list);
        if ($result) {
            $this->addUserLog('批量发货', $result);
        }
        return AjaxReturn($result);
    }

    /**
     * 待发货订单列表
     */
    public function orderList()
    {
        $order_service = new OrderService();

        if (request()->isAjax()) {
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $start_create_date = request()->post('start_create_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('start_create_date'));
            $end_create_date = request()->post('end_create_date') == "" ? 0 : getTimeTurnTimeStamp(request()->post('end_create_date'));
            $user = request()->post('user', '');
            $order_no = request()->post('order_no', '');

            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_create_date) {
                $condition['create_time'][] = ['>=', $start_create_date];
            }
            if ($end_create_date) {
                $condition['create_time'][] = ['<=', $end_create_date + 86399];
            }
            if (!empty($user)) {
                $condition['receiver_name|receiver_mobile|user_name|buyer_id'] = array(
                    'like',
                    '%' . $user . '%'
                );
            }
            if (!empty($order_no)) {
                $condition['order_no'] = array(
                    'like',
                    '%' . $order_no . '%'
                );
            }

            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = $this->instance_id;
            $condition['supplier_id'] = $this->supplier_id;//供应商
            if(isSupplierPort()){
                unset($condition['shop_id']);//供应商端不带shop_id查询订单数据
            }
//            $condition['shipping_status'] = 0; // 0 待发货
            $condition['pay_status'] = 2; // 2 已支付
            $condition['order_status'][] = array(
                'neq',
                2
            ); // 2 已发货
            $condition['order_status'][] = array(
                'neq',
                3
            ); // 3 已收货
            $condition['order_status'][] = array(
                'neq',
                4
            ); // 4 已完成
            $condition['order_status'][] = array(
                'neq',
                5
            ); // 5 关闭订单
            $condition['order_status'][] = array(
                'neq',
                -1
            ); // -1 售后
            $condition['shipping_type'] = 1; //快递配送

            $list = $order_service->getOrderList($page_index, $page_size, $condition, 'create_time desc');
            if($list['data']) {
                foreach ($list['data'] as $k => $v) {
                    //过滤虚拟商品订单
                    if(count($v['order_item_list']) == 1) {
                        if($v['order_item_list'][0]['goods_type'] == 3) {
                            unset($list['data'][$k]);
                        }
                    }
                    //过滤拼团待成团订单
                    if($v['order_type'] == 5 && $v['group_record_id']) {
                        $group_record_mdl = new VslGroupShoppingRecordModel();
                        $status = $group_record_mdl->Query(['record_id' => $v['group_record_id']],'status')[0];
                        if($status == 0) {
                            unset($list['data'][$k]);
                        }
                    }
                }
                $list['data'] = array_values($list['data']);
            }

            return $list;
        }
    }

    /**
     * 待发货订单导出Excel
     */
    public function orderDataExcel()
    {
        try{
            $xlsName = "待发货订单数据列表";
            $xlsCells = [
                1 => ['order_no', '订单号'],
                2 => ['create_time', '下单时间'],
                3 => ['warehouse_name','供应商'],
                4 => ['goods_name','商品名称'],
                5 => ['sku_name','商品规格'],
                6 => ['num','订购数量'],
                7 => ['goods_money','商品金额'],
                8 => ['receiver_name','收件人姓名'],
                9 => ['receiver_mobile','电话'],
                10 => ['receiver_address','详细地址'],
                11 => ['express_company_no', '快递公司(物流编号)'],
                12 => ['express_no', '快递单号'],
                13 => ['order_goods_id', '订单商品id(请勿修改)'],
                14 => ['item_no', '条形码']
            ];

            $start_create_date = request()->get('start_create_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_create_date'));
            $end_create_date = request()->get('end_create_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_create_date'));
            $user = request()->get('user', '');
            $order_no = request()->get('order_no', '');
            $ids = request()->get('ids', '');
            $filter = request()->get('filter');//1过滤售后 0不过滤

            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_create_date) {
                $condition['create_time'][] = ['>=', $start_create_date];
            }
            if ($end_create_date) {
                $condition['create_time'][] = ['<=', $end_create_date + 86399];
            }
            if (!empty($user)) {
                $condition['receiver_name|receiver_mobile|user_name|buyer_id'] = array(
                    'like',
                    '%' . $user . '%'
                );
            }
            if (!empty($order_no)) {
                $condition['order_no'] = array(
                    'like',
                    '%' . $order_no . '%'
                );
            }

            if($filter){
                $condition['filter'] = $filter;
            }
            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = $this->instance_id;
//            $condition['shipping_status'] = 0; // 0 待发货
            if(isSupplierPort()){
                $condition['supplier_id'] = $this->supplier_id;
                unset($condition['shop_id']);//供应商端不带shop_id查询订单数据
            }
            $condition['pay_status'] = 2; // 2 已支付
//            if ($filter){
//                $order_status_str = '-1,';
//            }
//            $order_status_str = '-1,0,2,3,4,5';
            $condition['order_status'] = 1;
            $condition['shipping_type'] = 1; //快递配送
            $xlsCell = [];
            if($ids){
                $ids = explode(',',$ids);
                foreach ($ids as $v) {
                    if(!empty($xlsCells[$v]))$xlsCell[] = $xlsCells[$v];
                }
            }

            //导出操作移到到计划任务统一执行
            $insert_data = array(
                'type' => 15,
                'status' => 0,
                'exname' => $xlsName,
                'website_id' => $this->website_id,
                'shop_id' => $this->instance_id,
                'supplier_id' => $this->supplier_id,
                'addtime' => time(),
                'ids' => serialize($xlsCell),
                'ids_md5' => md5(serialize($xlsCell)),
                'conditions' => serialize($condition),//这里如果是供应商，则不带上shop_id查询订单表，因为供应商订单包含所有店铺的
                'conditions_md5' => md5(serialize($condition)),
            );
            //TODO... 优化，不应该赢查询条件字段'conditions'
            $excels_export = new ExcelsExport();
            $res = $excels_export->insertData($insert_data);
            return $res;
        }catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 待发货订单导出Excel  C端
     */
    public function adminOrderDataExcel()
    {
        try{
            $xlsName = "待发货订单数据列表";
            $xlsCells = [
                1 => ['order_no', '订单号'],
                2 => ['order_money', '订单金额'],
                3 => ['express_company_no', '物流编号'],
                4 => ['express_no', '物流单号'],
            ];

            $start_create_date = request()->get('start_create_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('start_create_date'));
            $end_create_date = request()->get('end_create_date') == "" ? 0 : getTimeTurnTimeStamp(request()->get('end_create_date'));
            $user = request()->get('user', '');
            $order_no = request()->get('order_no', '');
            $ids = request()->get('ids', '');

            $condition['is_deleted'] = 0; // 未删除订单
            if ($start_create_date) {
                $condition['create_time'][] = ['>=', $start_create_date];
            }
            if ($end_create_date) {
                $condition['create_time'][] = ['<=', $end_create_date + 86399];
            }
            if (!empty($user)) {
                $condition['receiver_name|receiver_mobile|user_name|buyer_id'] = array(
                    'like',
                    '%' . $user . '%'
                );
            }
            if (!empty($order_no)) {
                $condition['order_no'] = array(
                    'like',
                    '%' . $order_no . '%'
                );
            }

            $condition['website_id'] = $this->website_id;
            $condition['shop_id'] = $this->instance_id;
            $condition['shipping_status'] = 0; // 0 待发货
            $condition['pay_status'] = 2; // 2 已支付
            $condition['order_status'][] = array(
                'neq',
                4
            ); // 4 已完成
            $condition['order_status'][] = array(
                'neq',
                5
            ); // 5 关闭订单
            $condition['order_status'][] = array(
                'neq',
                -1
            ); // -1 售后
            $condition['shipping_type'] = 1; //快递配送

            $xlsCell = [];
            if($ids){
                $ids = explode(',',$ids);
                foreach ($ids as $v) {
                    if(!empty($xlsCells[$v]))$xlsCell[] = $xlsCells[$v];
                }
            }

//            //导出操作移到到计划任务统一执行
//            $insert_data = array(
//                'type' => 15,
//                'status' => 0,
//                'exname' => $xlsName,
//                'website_id' => $this->website_id,
//                'addtime' => time(),
//                'ids' => serialize($xlsCell),
//                'conditions' => serialize($condition),
//            );
//            $excelsModel = new VslExcelsModel();
//            $check = $excelsModel->getInfo(['type' => 15,'status' => ['<',1],'exname' => $xlsName,'ids' => serialize($xlsCell),'conditions' => serialize($condition)]);
//            if($check){
//                return  ['code' => -1,'message' => '已存在该任务，请前往系统-计划任务中查看'];
//            }else{
//                $excelsModel->save($insert_data);
//                return  ['code' => 0,'message' => '新建导出任务成功，请前往系统-计划任务中查看'];
//            }
//            exit;

            $order_service = new OrderService();
            $list = $order_service->getOrderList(1, PAGESIZE, $condition, 'create_time desc');

            $data = [];
            $key = 0;
            foreach ($list["data"] as $k => $v) {
                $data[$key]["order_no"] = $v["order_no"];
                $data[$key]["order_money"] = $v['order_money'];
                $data[$key]["express_company_no"] = '';
                $data[$key]["express_no"] = '';
                $key +=1;
            }
            dataExcel($xlsName, $xlsCell, $data);
        }catch (\Exception $e) {
            return $e->getMessage();
        }
    }

    /**
     * 上传发货Excel
     */
    public function uploadFile()
    {
        if (request()->isPost()) {
            $file = request()->file('excel');

            if (!$file) {
                return ['code' => 0, 'message' => '请上传文件'];
            }

            $result = $this->baseDelivery->uploadFile($file);

            if ($result['code'] > 0) {
                $this->addUserLog($this->user->getUserInfo('','user_name')['user_name'].'导表发货', $result['message']);
            }

            return $result;
        }
    }

}