<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/13 0013
 * Time: 9:44
 */

namespace addons\delivery\service;

use addons\delivery\model\VslDeliveryFileModel;
use addons\delivery\model\VslDeliveryTemplateModel;
use addons\delivery\model\VslFormExpressCompanyModel;
use addons\delivery\model\VslFormTemplateModel;
use addons\delivery\model\VslSenderTemplateModel;
use data\extend\Kdniao;
use data\model\CityModel;
use data\model\DistrictModel;
use data\model\ProvinceModel;
use data\model\VslExpressCompanyModel;
use data\model\VslExpressCompanyShopRelationModel;
use data\model\VslOrderExpressCompanyModel;
use data\model\VslOrderGoodsExpressModel;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use data\service\BaseService;
use data\service\Express as ExpressService;
use data\service\Order;
use data\service\Order as OrderService;
use think\Db;

class delivery extends BaseService
{
    function __construct()
    {
        parent::__construct();
    }

    public function formTemplateList($page_index = 1, $page_size = 0, array $condition = [], $order = '', $field = '*')
    {
        $form_template_model = new VslFormTemplateModel();
        $form_express_company = new VslFormExpressCompanyModel();
//        $list = $form_template_model::all(function ($query) use ($condition, $page_index, $page_size, $order, $field,$form_template_model) {
//            $query->where($condition)->field($field)->order($order)->limit(($page_index - 1) * $page_size, $page_size);
//            $query->form_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
//        }, $with);
        $data = $form_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach ($data['data'] as &$v) {
            $v['company_name'] = $form_express_company::get($v['form_express_company_id'])['company_name'];
        }
        unset($v);
        return $data;
    }

    public function senderTemplateList($page_index = 1, $page_size = 0, array $condition = [], $order = '', $field = '*')
    {
        $sender_template_model = new VslSenderTemplateModel();
        $data = $sender_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        $province_model = new ProvinceModel();
        $city_model = new CityModel();
        $district_model = new DistrictModel();
        foreach ($data['data'] as &$v) {
            $v['province_name'] = $province_model::get($v['province_id'])['province_name'] ?: '';
            $v['city_name'] = $city_model::get($v['city_id'])['city_name'] ?: '';
            $v['district_name'] = $district_model::get($v['district_id'])['district_name'] ?: '';
        }
        unset($v);
        return $data;
    }

    public function deliveryTemplateList($page_index = 1, $page_size = 0, array $condition = [], $order = '', $field = '*')
    {
        $delivery_template_model = new VslDeliveryTemplateModel();
        $data = $delivery_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        return $data;
    }

    public function expressTemplateList($page_index = 1, $page_size = 0, array $condition = [], $order = '', $field = '*')
    {
        $express_template_model = new VslExpressCompanyShopRelationModel();
        $express_company_model = new VslOrderExpressCompanyModel();
        $data = $express_template_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        foreach ($data['data'] as &$v) {
            $v['express_company_name'] = $express_company_model::get($v['co_id'])['company_name'];
        }
        unset($v);
        return $data;
    }

    public function saveFormTemplate(array $data, array $condition = [])
    {
        $form_template_model = new VslFormTemplateModel();
        return $form_template_model->save($data, $condition);
    }

    public function saveExpressTemplate(array $data, array $condition = [])
    {
        $express_template_model = new VslExpressCompanyShopRelationModel();
        return $express_template_model->save($data, $condition);
    }

    public function saveDeliveryTemplate(array $data, array $condition = [])
    {
        $delivery_template_model = new VslDeliveryTemplateModel();
        return $delivery_template_model->save($data, $condition);
    }

    public function saveSenderTemplate(array $data, array $condition = [])
    {
        $sender_template_model = new VslSenderTemplateModel();
        return $sender_template_model->save($data, $condition);
    }

    public function deleteFormTemplate(array $condition)
    {
        $form_template_model = new VslFormTemplateModel();
        return $form_template_model::destroy($condition);
    }

    public function deleteSenderTemplate(array $condition)
    {
        $sender_template_model = new VslSenderTemplateModel();
        return $sender_template_model::destroy($condition);
    }

    public function deleteDeliveryTemplate(array $condition)
    {
        $delivery_template_model = new VslDeliveryTemplateModel();
        return $delivery_template_model::destroy($condition);
    }

    public function formExpressCompanyList(array $condition = [])
    {
        $form_express_company_model = new VslFormExpressCompanyModel();
        $list = $form_express_company_model::all($condition);
        $return_data = [];
        foreach ($list as $v) {
            $temp_company = [];
            $temp_company['form_express_company_id'] = $v['form_express_company_id'];
            $temp_company['company_name'] = $v['company_name'];

            $return_data['company_list'][] = $temp_company;
            foreach ($v->form_style()->select() as $f) {
                $temp_style = [];
                $temp_style['form_style_id'] = $f['form_style_id'];
                $temp_style['style_name'] = $f['style_name'];

                $return_data['style_list'][$v['form_express_company_id']][] = $temp_style;
            }
        }
        return $return_data;
    }

    public function formTemplateDetail(array $condition, array $with = [])
    {
        $form_template_model = new VslFormTemplateModel();
        return $form_template_model::get($condition, $with);
    }

    public function senderTemplateDetail(array $condition, array $with = [])
    {
        $sender_template_model = new VslSenderTemplateModel();
        return $sender_template_model::get($condition, $with);
    }

    public function expressTemplateDetail(array $condition, array $with = [])
    {
        $express_company_relation_model = new VslExpressCompanyShopRelationModel();
        $return_data = $express_company_relation_model::get($condition, $with);
        $return_data['template_data'] = json_decode(htmlspecialchars_decode($return_data['template_data']), true);
        return $return_data;
    }

    public function deliveryTemplateDetail(array $condition)
    {
        $delivery_template_model = new VslDeliveryTemplateModel();
        $return_data = $delivery_template_model::get($condition);
        $return_data['template_data'] = json_decode(htmlspecialchars_decode($return_data['template_data']), true);
        return $return_data;
    }

    public function formPrint($order_goods_id_array, $receiver_info = '')
    {
        $order_service = new Order();
        $result = []; // 快递鸟接口返回结果
        $kdn = new Kdniao($this->website_id, $this->instance_id, 'form');
        $form_condition['website_id'] = $this->website_id;
        $form_condition['shop_id'] = $this->instance_id;
        $form_condition['vsl_form_template_model.is_default'] = 1;
        $with = ['form_express_company', 'form_style'];
        $form_template_data = $this->formTemplateDetail($form_condition, $with);
        unset($form_condition, $with);
        if (empty($form_template_data)) {
            return ['code' => -1, 'message' => '缺少电子面单模板数据'];
        }
        $companyModel = new \data\model\VslExpressCompanyModel();
        $company = $companyModel->getInfo(['co_id' => $form_template_data->form_express_company->express_company_id], 'co_id,express_no,company_name');
        $sender_condition['vsl_sender_template.website_id'] = $this->website_id;
        $sender_condition['vsl_sender_template.shop_id'] = $this->instance_id;
        $sender_condition['vsl_sender_template.is_default'] = 1;
        $with = ['province', 'city', 'district'];
        $sender_template_data = $this->senderTemplateDetail($sender_condition, $with);
        unset($sender_condition, $with);
        if (empty($sender_template_data)) {
            return ['code' => -1, 'message' => '缺少发货人模板数据'];
        }
        $list = $order_service->printOrderList(['order_goods_id' => ['IN', $order_goods_id_array]], ['order']);
        foreach ($list as $order) {
            $form_data = [];
            $form_data['ShipperCode'] = $form_template_data->form_express_company->shipper_code;// 快递公司code
            $form_data['OrderCode'] = $order['order_no'];//订单编号
            $form_data['PayType'] = $form_template_data->pay_type;//结算方式
            $form_data['ExpType'] = 1;//快递类型：1-标准快件
            if ($form_template_data->form_express_company->need_customer_name){
                $form_data['CustomerName'] = $form_template_data->client_account;
            }
            if ($form_template_data->form_express_company->need_customer_pwd){
                $form_data['CustomerPwd'] = $form_template_data->client_pwd;
            }
            if ($form_template_data->form_express_company->need_send_site){
                $form_data['SendSite'] = $form_template_data->send_site;
            }
            if ($form_template_data->form_express_company->need_month_code){
                $form_data['MonthCode'] = $form_template_data->monthly_code;
            }
            if ($form_template_data->form_express_company->need_send_staff){
                $form_data['SendStaff'] = '';
            }
            if ($form_template_data->form_express_company->need_logistics_code){
                $form_data['LogisticCode'] = '';
            }

            $sender = [];
            $sender['Name'] = $sender_template_data->sender;
            $sender['Mobile'] = $sender_template_data->mobile;
            $sender['ProvinceName'] = $sender_template_data->province->province_name;
            $sender['CityName'] = $sender_template_data->city->city_name;
            $sender['ExpAreaName'] = $sender_template_data->district->district_name;
            $sender['Address'] = $sender_template_data->address;
            $sender['PostCode'] = $sender_template_data->zip_code;

            // $order->receiver_name,批量打印收货人信息;$receiver_info['name'],单个打印收货人信息
            $receiver = [];
            $receiver['Name'] = !empty($receiver_info['name']) ? $receiver_info['name'] : $order['receiver_name'];
            $receiver['Mobile'] = !empty($receiver_info['mobile']) ? $receiver_info['mobile'] : $order['receiver_mobile'];
            $receiver['PostCode'] = !empty($receiver_info['zip']) ? $receiver_info['zip'] : ($order['receiver_zip']?:000000);
            $receiver['ProvinceName'] = !empty($receiver_info['province_name']) ? $receiver_info['province_name'] : $order['receiver_province_name'];
            $receiver['CityName'] = !empty($receiver_info['city_name']) ? $receiver_info['city_name'] : $order['receiver_city_name'];
            $receiver['ExpAreaName'] = !empty($receiver_info['district_name']) ? $receiver_info['district_name'] : $order['receiver_district_name'];
            $receiver['Address'] = !empty($receiver_info['address']) ? $receiver_info['address'] : $order['receiver_address'];

            $commodity = [];
            $customArea = '';
            // 只打印第一个商品名称
            //$commodity[]['GoodsName'] = str_replace(['+','&','#','>','<'], ['','','','',''], reset($order['goods_list'])['goods_name']);
            $i = 0;
            foreach ($order['goods_list'] as $goods) {
                $goods_name = str_replace(['+','&','#','>','<'], [' ','','','',''], $goods['short_name'] ?: $goods['goods_name']);
                $commodity[$i]['GoodsName'] = $goods_name;
                $goods_num = (int)$goods['num'];
                $goods_sku = '';
                if($goods['sku_name']){
                    $goods_sku = str_replace(['+','&','#','>','<'], [' ','','','',''], $goods['sku_name']);
                }
                if(isset($form_template_data->form_style->custom_area) && $form_template_data->form_style->custom_area == 1){
                    $customArea .= $goods_name.' 数量:'.$goods_num.' '.$goods_sku.'<br>';
                }else{
                    $commodity[$i]['Goodsquantity'] = (int)$goods['num'];
                    if($goods_sku){
                        $commodity[$i]['GoodsDesc'] = $goods_sku;
                    }
                }
                $i++;
                $result[$order['order_id']]['order_goods_id_array'][] = $goods['order_goods_id'];
            }
            
            
            $form_data['Sender'] = $sender;
            $form_data['Receiver'] = $receiver;
            $form_data['Commodity'] = $commodity;
            $form_data['CustomArea'] = $customArea;
            $form_data['Quantity'] = 1;//包裹数
            $form_data['IsReturnPrintTemplate'] = 1;//返回电子面单
            $form_data['IsNotice'] = $form_template_data->is_notice ? 0 : 1;//是否通知快递员上门取件 本系统，0：不通知，1：通知，快递鸟相反

            if (!$form_template_data->form_style->is_default) {
                // 不是默认的电子面单需要传面单大小
                $form_data['TemplateSize'] = $form_template_data->form_style->template_size;
            }
            $kdn_result = $kdn->form($form_data);
            $result[$order['order_id']]['result'] = $kdn_result;
            if ($form_template_data->auto_delivery && $kdn_result['Success'] && $kdn_result['Order']['LogisticCode']) {
                // 自动发货 && 返回物流单号
                $delivery_result = $order_service->orderDelivery($order['order_id'], implode(',', $result[$order['order_id']]['order_goods_id_array']), $company ? $company['company_name'] : '', 1, $company ? $company['co_id'] : '', $kdn_result['Order']['LogisticCode']);
                $result[$order['order_id']]['auto_delivery_result'] = $delivery_result;
            }
        }

        return $result;
    }

    /**
     * 上传发货Excel
     */
    public function uploadFile($file)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');

        $base_path = 'upload' . DS . $this->website_id . DS . 'delivery'. DS ;
        if (!file_exists($base_path)) {
            $mode = intval('0777', 8);
            mkdir($base_path, $mode, true);
        }

        $info = $file->validate(['ext' => 'csv,xlsx,xls'])->move($base_path. 'Excel');

        if (!$info) {
            // 上传失败获取错误信息 
            return ['code' => 0, 'message' => '文件格式错误，请重新上传' ];
        }

        $exclePath = $info->getSaveName(); //获取文件名
        $old_excel_name = $info->getInfo($exclePath);
        $file_types = explode(".", $old_excel_name['name']);

        $data = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'supplier_id' => $this->supplier_id,
            'type' => end($file_types), //文件类型
            'excel_name' => $base_path . 'Excel' . DS . $exclePath,//Excel上传文件的地址,
            'old_excel_name' => $old_excel_name['name'] ?: '',
            'status' => 3,//等待中
            'create_time' => time(),
        ];

        $deliveryFile = new VslDeliveryFileModel();
        $save = $deliveryFile->isUpdate(false)->save($data);

        if (!$save) {
            return ['code' => 0, 'message' => '操作失败，请稍后重试' ];
        }
        //添加队列导标发货
        $this->addDeliveryQueue($save);
        return ['code' => 1, 'message' => '已添加导入队列，等待执行' ];
    }

    /**
     * 导表发货队列
     * @param $delivery_id
     * @return array
     */
    public function addDeliveryQueue($delivery_id)
    {
        if(config('is_high_powered')) {
            $data['delivery_id'] = $delivery_id;
            $exchange_name = config('rabbit_delivery_by_excel.exchange_name');
            $queue_name = config('rabbit_delivery_by_excel.queue_name');
            $routing_key = config('rabbit_delivery_by_excel.routing_key');
            $url = config('rabbit_interface_url.url');
            $delivery_url = $url.'/rabbitTask/deliveryByExcel';
            $request_data = json_encode($data);
            $push_data = [
                "customType" => "delivery_by_excel",//标识什么业务场景
                "data" => $request_data,//请求数据
                "requestMethod" => "POST",
                "timeOut" => 20,
                "url" => $delivery_url,
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
     * 获取导表发货文件
     */
    public function getDeliveryFileList($page_index, $page_size, $condition, $order)
    {
        $deliveryFile = new VslDeliveryFileModel();
        $list = $deliveryFile->pageQuery($page_index, $page_size, $condition, $order,'*');
        return $list;
    }

    /**
     * 修改导表发货文件
     */
    public function updateDeliveryFile($data, $condition)
    {
        $deliveryFile = new VslDeliveryFileModel();
        return $deliveryFile->save($data, $condition);
    }

    /**
     * 导表发货
     */
    public function deliveryByExcel($file, $shop_id, $website_id, $supplier_id=0)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');

        try{
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
            $order_service = new OrderService();
            $finalyData = [];
            $expressNos = [];
            foreach ($excel_array as $k => $v) {
                $j++;
                //去除空格
                $v[0] = trim($v[0]);
                if(empty($v[10]) || empty($v[11]) || !$v[12]) {
                    $excel_error_message .= $v[0] .'订单物流编号或物流单号或订单商品id为空;';
                    $error_arr[] = $v;
                    continue;
                }
                //先判断订单是否存在
                $order_mdl = new VslOrderModel();
                $order_info = $order_mdl->getInfo(['order_no' => $v[0]],'order_id,order_status,shipping_type');
                if(!$order_info){
                    $excel_error_message .= $v[0] .'订单不存在;';
                    $error_arr[] = $v;
                    continue;
                }
                if($order_info['shipping_type'] != 1){
                    $excel_error_message .= $v[0] .'订单不是快递配送订单;';
                    $error_arr[] = $v;
                    continue;
                }
                if($order_info['order_status'] != 1 && $order_info['order_status'] != 2){
                    $excel_error_message .= $v[0] .'订单状态已改变;';
                    $error_arr[] = $v;
                    continue;
                }
                //筛选出可发货的order_goods_id
                $order_goods_mdl = new VslOrderGoodsModel();
                $order_goods_list = $order_goods_mdl->getInfo(['order_goods_id' => $v[12]],'order_goods_id,goods_type,refund_status,shipping_status,supplier_id');

                if($order_goods_list['goods_type'] == 3 || $order_goods_list['refund_status'] > 0) {
                    //虚拟商品,不执行
                    $excel_error_message .= $v[0] .'为虚拟商品订单或已进行售后;';
                    $error_arr[] = $v;
                    continue;
                }else if ($supplier_id==0 && $order_goods_list['supplier_id']){
                    $excel_error_message .= $v[0] .'为供应商端商品，需供应商端发货;';
                    $error_arr[] = $v;
                    continue;
                }else if ($supplier_id && $order_goods_list['supplier_id']==0) {
                    $excel_error_message .= $v[0] . '非供应商端商品，需对应店铺端发货;';
                    $error_arr[] = $v;
                    continue;
                }
                //判断物流公司是否存在
                $express_company_mdl = new VslExpressCompanyModel();
                $express_company_info = $express_company_mdl->getInfo(['express_no' => $v[10]],'co_id,company_name');
                if(!$express_company_info){
                    $excel_error_message .= $v[0] .'订单物流公司不存在;';
                    $error_arr[] = $v;
                    continue;
                }
                $this->dealDeliveryGoods($order_goods_list['order_goods_id']);
                if(in_array($v[11], array_column($expressNos, 'express_no'))){
                    $key = array_search($v[11], array_column($expressNos, 'express_no','id'));
                    if($finalyData[$key]['order_id'] == $order_info['order_id']){
                        $finalyData[$key]['order_goods_id'][] = $order_goods_list['order_goods_id'];
                        $i++;
                        continue;
                    }
                }
                //判断是否启用了该物流公司，如果没有开启，就去开启
                $express_company_shop_relation_mdl = new VslExpressCompanyShopRelationModel();
                $is_use_express_company = $express_company_shop_relation_mdl->getInfo(['co_id' => $express_company_info['co_id'],'shop_id' => $shop_id,'website_id' => $website_id,'supplier_id' => $supplier_id,],'*');
                if(empty($is_use_express_company)) {
                    //启用该物流公司
                    $data['co_id'] = $express_company_info['co_id'];
                    $data['shop_id'] = $shop_id;
                    $data['website_id'] = $website_id;
                    $data['supplier_id'] = $supplier_id;
                    $express_server = new ExpressService();
                    $express_server->setUseExpressCompany($data);
                }
                $expressNos[$k]['id'] = $k;
                $expressNos[$k]['express_no'] = $v[11];
                $finalyData[$k]['order_id'] = $order_info['order_id'];
                $finalyData[$k]['order_goods_id'][] = $order_goods_list['order_goods_id'];
                $finalyData[$k]['express_no'] = $v[11];
                $finalyData[$k]['company_name'] = $express_company_info['company_name'];
                $finalyData[$k]['co_id'] = $express_company_info['co_id'];
                $finalyData[$k]['order_no'] = $v[0];
                $i++;
            }
            unset($v,$expressNos);
            if($finalyData){
                foreach($finalyData as $key => $val){
                    if(!$val['order_goods_id']){
                        $i--;
                        continue;
                    }
                    Db::startTrans();
                    $res = $order_service->orderDelivery($val['order_id'], implode(',', $val['order_goods_id']), $val['company_name'], 1, $val['co_id'], $val['express_no']);
                    if (!$res) {
                        $i--;
                        $excel_error_message .= $val['order_no'] .'订单发货失败; ';
                        $error_arr[] = $val;
                        Db::rollback();
                        continue;
                    }
                    Db::commit();
                }
                unset($val);
            }
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
                    $c ++;
                }
                $res = dataExcel($file_name, $xlsCell, $data, $path, $suffix, 1);
                if ($res['code'] > 0) {
                    $error_new_path = $res['data'];
                }
            }

            unset($res);
            if ($i == 0) {
                $res['code'] = 0;
                $res['message'] = $excel_error_message;
                $res['data'] = $error_new_path;
            } else if ($i == $j ) {
                $res['code'] = 1;
                $res['message'] = '发货总数量：' . $j . ',发货成功';
            } else {
                $res['code'] = 2;
                $res['message'] = '发货总数量：' . $j . ',发货成功数量：' . $i.',失败原因：'.$excel_error_message;
                $res['data'] = $error_new_path;
            }
            @unlink($file);
            return $res;
        }catch (\Exception $e) {
            $msg = $e->getMessage();
            $log_dir = getcwd() . '/delivery_import.log';
            file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }

    /*
     * 处理已经发过货并且准备重新发货的订单商品
     */
    public function dealDeliveryGoods($orderGoodsId = 0){
        if(!$orderGoodsId){
            return false;
        }
        $orderExpressModel = new VslOrderGoodsExpressModel();
        //先删除这个商品单独在一个包裹的情况
        $orderExpressModel->where(['order_goods_id_array' => $orderGoodsId])->delete();
        $checkMerge = $orderExpressModel->where('FIND_IN_SET('.$orderGoodsId.',order_goods_id_array)')->field('order_goods_id_array,id')->select();
        if(count($checkMerge) == 1){
            $arr = explode(',', $checkMerge[0]['order_goods_id_array']);
            $arr = array_merge(array_diff($arr, array($orderGoodsId)));
            if(!$arr){
                return false;
            }
            $orderExpressModel->isUpdate(true)->save(['order_goods_id_array' => implode(',', $arr)],['id' => $checkMerge[0]['id']]);
        }else{
            foreach($checkMerge as $key => $val){
                $arr = explode(',', $val['order_goods_id_array']);
                $arr = array_merge(array_diff($arr, array($orderGoodsId)));
                if(!$arr){
                    continue;
                }
                $orderExpressModel = new VslOrderGoodsExpressModel();
                $orderExpressModel->isUpdate(true)->save(['order_goods_id_array' => implode(',', $arr)],['id' => $val['id']]);
            }
        }
        return true;
    }
}