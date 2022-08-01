<?php

namespace addons\invoice\controller;

use addons\invoice\Invoice as baseInvoice;
use addons\invoice\model\VslInvoiceConfigModel;
use addons\invoice\model\VslInvoiceFileModel;
use addons\invoice\model\VslInvoiceModel;
use addons\invoice\server\Invoice as InvoiceServer;
use data\extend\WchatOauth;
use data\model\VslOrderModel;
use data\service\AddonsConfig;
use data\service\Config;
use data\service\User;
use think\Db;
use think\Exception;
use addons\shop\service\Shop as ShopServer;

/**
 * 商品助手控制器
 * Class Invoice
 * @package addons\invoice\controller
 */
class Invoice extends baseInvoice {

    public function __construct() {
        parent::__construct();
    }

    public function invoiceList()
    {
        $page_size = request()->post('page_size', PAGESIZE);
        $page_index = request()->post('page_index', 1);
        $type = request()->post('type');
        $order_no = request()->post('order_no');
        $title_name  = request()->post('title_name');
        $taxpayer_no = request()->post('taxpayer_no');
        $content = request()->post('content');
        $status = request()->post('status');//0未开票 1已开票 2待处理 3已作废
        //订单实际状态：0待付款 1待发货（!=4,5,-1） 2待收货 3已收货 4已完成 5已关闭
        $actual_order_status = request()->post('actual_order_status');

        $id = request()->post('id');
        $condition = [
            'i.website_id' => $this->website_id,
            'i.shop_id' => $this->instance_id,
            'i.order_status' => ['>', 0],//订单已付款
            'i.type' => ['<>', 0],//0不开票 1电子普通发票 2 增值税专用发票
        ];
        if ($id) {
            $condition['i.id'] = $id;
        }
//        if (!$status) {
//            $condition['status'] = 0;
//        }else{
//            $condition['status'] = ['>', 0];
//        }
        
        if ($type) {
            $condition['i.type'] = $type;
        }
        if ($order_no) {
            $condition['i.order_no'] = ['LIKE', '%' . $order_no . '%'];
        }
        if ($title_name) {
            $condition['i.title_name'] = ['LIKE', '%' . $title_name . '%'];
        }
        if ($taxpayer_no) {
            $condition['i.taxpayer_no'] = $taxpayer_no;
        }
        if ($content) {
            $condition['i.content'] = ['LIKE', '%' . $content . '%'];
        }
        
        //改动关联订单状态
        if($status == 0){
            $condition['i.status'] = 0;
        }else if($status == 1 ){
            $condition['i.status'] = ['neq', 0];
        }else if($status == 2){
            $condition['i.status'] = ['>', 0];
        }else if($status == 3){
            $condition['i.status'] = -1;//发票列表中，作废为-1
        }
        //关联订单实际状态 2表示售后订单
        if($status == 2){
            $condition['o.order_status'] = -1;
        }
        if ($actual_order_status!=''){
            unset($condition['o.order_status']);//不用和发票交集
            if ($actual_order_status ==1){
                $condition['o.order_status'] = ['not in', '-1,4,5'];
            }else{
                $condition['o.order_status'] = $actual_order_status;
            }
        }
        $invoice = new InvoiceServer();
        $data = $invoice->invoiceList($page_index, $page_size, $condition, 'i.create_time DESC');
        return $data;
    }

    /**
     * 发票助手设置
     * @return mixed|void
     */
    public function postInvoiceSetting()
    {
        if (request()->isAjax()) {
            try {
                $post_data = request()->post();
                $invoiceModel = new VslInvoiceConfigModel();
                $invoice_info = $invoiceModel::get(['website_id' => $this->website_id, 'shop_id' => $this->instance_id]);
                if ($invoice_info['is_pt_show'] == 0 && $invoice_info['is_zy_show'] == 0) {
                    $status = 0;
                } else {
                    $status = 1;
                }
                if (!empty($invoice_info)) {
                    $res = $invoiceModel->save(
                        [
                            'is_pt_show' => $post_data['is_pt_show'],
                            'is_zy_show' => $post_data['is_zy_show'],
                            'pt_invoice_tax' => $post_data['pt_invoice_tax'],
                            'zy_invoice_tax' => $post_data['zy_invoice_tax'],
                            'is_refund' => $post_data['is_refund'],
                            'status' => $status
                        ],
                        [
                            'website_id' => $this->website_id,
                            'shop_id' => $this->instance_id
                        ]
                    );
                } else {
                    $data = [
                        'is_pt_show' => $post_data['is_pt_show'],
                        'is_zy_show' => $post_data['is_zy_show'],
                        'pt_invoice_tax' => $post_data['pt_invoice_tax'],
                        'zy_invoice_tax' => $post_data['zy_invoice_tax'],
                        'is_refund' => $post_data['is_refund'],
                        'website_id' => $this->website_id,
                        'shop_id' => $this->instance_id,
                        'status' => $status
                    ];
                    $res = $invoiceModel->save($data);
                }
                ob_clean();

                $is_refund = $post_data['is_refund'] ? '开启' : '关闭';
                $is_pt_show = $post_data['is_pt_show'] ? '开启' : '关闭';
                $is_zy_show = $post_data['is_zy_show'] ? '开启' : '关闭';
                $pt_tax = $post_data['is_pt_show'] ? ' 税点改为:'.$post_data['pt_invoice_tax'].'%; ' : '; ';
                $zy_tax = $post_data['is_zy_show'] ? ' 税点改为:'.$post_data['zy_invoice_tax'].'%; ' : '; ';

                $operation = '修改发票配置';
                $target = '电子普通发票改为:'.$is_pt_show.$pt_tax.' 增值税专用发票改为:'.$is_zy_show.$zy_tax.' 发票退款改为:'.$is_refund;
                $this->addUserLog($operation, $target);

                $is_use = 1;
                if (empty($post_data['is_pt_show']) && empty($post_data['is_zy_show'])) {
                    $is_use = 0;//关闭
                }
                $post_data['is_use'] = $is_use;
                $data = [
                    'value' => $post_data,
                    'addons' => "invoice",
                    'desc' => "发票助手",
                    'is_use' => $is_use
                ];
                $addonsConfigSer = new AddonsConfig();
                $res = $addonsConfigSer->setAddonsConfig($data,$this->instance_id);
                if ($res) {
                    $this->addUserLog('发票助手设置',$res);
                    setAddons('invoice', $this->website_id, $this->instance_id);
                }

                return ['code' => $res,'message' => '添加成功'];
            } catch (\Exception $e) {
                return ['code' => -1, 'message' => $e->getMessage()];
            }
        }
    }

    /**
     * 获取发票助手配置信息
     * @return array
     * @throws \think\Exception\DbException
     */
     public function getInvoiceSetting()
    {
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
        ];
        $invoiceServer = new InvoiceServer();
        $result = $invoiceServer->getInvoiceConfig($condition);

        return ['code' => 1, 'message' => '获取成功', 'data' => $result];
    }

    /**
     * 添加税费修改日志
     * @param $operation string [操作类型]
     * @param $target string [操作日志]
     */
    public function addUserLog($operation, $target)
    {
        $user_name = $this->user->getUserInfo('', 'user_name')['user_name'];
        $operation = $user_name . $operation;
        $this->user->addUserLog($this->uid, 1, $this->controller, $this->action, \think\Request::instance()->ip(), $target, $operation);
    }

    /**
     * 发票数量统计（已开，未开）
     * @return mixed
     */
    public function getInvoicesCount()
    {

        $invoice = new InvoiceServer();
    
        $invoice_count_array['no'] = $invoice->getInvoicesCount([
            'i.website_id' => $this->website_id,
            'i.shop_id' => $this->instance_id,
            'i.order_status' => ['>',0],
            'i.type' => ['<>',0],
            'i.status' => 0,
        ]);
        $invoice_count_array['yes'] = $invoice->getInvoicesCount([
            'i.website_id' => $this->website_id,
            'i.shop_id' => $this->instance_id,
            'i.order_status' => ['>',0],
            'i.type' => ['<>',0],
            'i.status' => ['neq',0],
        ]);
        $invoice_count_array['handle'] = $invoice->getInvoicesCount([
            'i.website_id' => $this->website_id,
            'i.shop_id' => $this->instance_id,
            'i.order_status' => ['>',0],
            'i.type' => ['<>',0],
            'i.status' => ['>',0],
            'o.order_status' => -1,
        ]);
        $invoice_count_array['del'] = $invoice->getInvoicesCount([
            'i.website_id' => $this->website_id,
            'i.shop_id' => $this->instance_id,
            'i.order_status' => ['>',0],
            'i.type' => ['<>',0],
            'i.status' => -1,
        ]);
        
        return $invoice_count_array;
    }

    /**
     * 获取发票信息
     * @param $order_no string [订单no]
     * @return $return_data array [订单详情发票信息]
     */
    public function getInvoiceInfoByOrderNo($order_no)
    {
        if (!$order_no) {return (object)[];}
        $invoiceModel = new VslInvoiceModel();
        $invoiceInfo = $invoiceModel->getInfo([
            'website_id' => $this->website_id,
            'order_no' => $order_no,
        ]);
        // 后台可以调整价格，所以税费用实际订单表中的税费价格
        $order_tax = $this->getOrderRealInvoiceTaxByOrderNo($order_no);
        if (!$invoiceInfo['type']) {//不开发票就不显示
            return (object)[];
        }
        $invoiceServer = new InvoiceServer();
        if ($invoiceInfo['title'] == 1) {
            $return_data = [
                'is_upload' => $invoiceInfo['is_upload'],
                'type' => 1,
                'title' => $invoiceInfo['title'],
                'title_name' => $invoiceInfo['title_name'],
                'content' => $invoiceServer->changeInvoiceContentByCategory($invoiceInfo['content'], 1),
                'tax' => $order_tax,
                'user_tel' => $invoiceInfo['user_tel'],
                'user_email' => $invoiceInfo['user_email'],
            ];
        } else {//增值
            $return_data = [
                'is_upload' => $invoiceInfo['is_upload'],
                'type' => $invoiceInfo['type'],
                'title' => $invoiceInfo['title'],
                'title_name' => $invoiceInfo['title_name'],
                'taxpayer_no' => $invoiceInfo['taxpayer_no'],
                'content' => $invoiceServer->changeInvoiceContentByCategory($invoiceInfo['content'], 1),
                'tax' => $order_tax,
                'company_name' => $invoiceInfo['company_name'],
                'company_addr' => $invoiceInfo['company_addr'],
                'bank' => $invoiceInfo['bank'],
                'mobile' => $invoiceInfo['mobile'],
                'card_no' => $invoiceInfo['card_no'],
                'user_tel' => $invoiceInfo['user_tel'],
                'user_email' => $invoiceInfo['user_email'],
            ];
        }
        return $return_data;
    }

    /**
     * 提交订单，发票数据入库
     * @param $data array [用户填写的发票数据]
     */
    public function postInvoiceByOrderCreate($data)
    {
        if (!$data) {return;}
        try {
            $this->website_id = $this->website_id ?: $data['website_id'];
            $invoiceConfigModel = new VslInvoiceConfigModel();
            $invoiceConfigRes = $invoiceConfigModel->getInfo([
                'website_id' => $this->website_id,
                'shop_id' => $data['shop_id']
            ],
                '*'
            );

            if (!$data['type']) {return;}
            //查询用户user_tel,user_email
//            $user_model = new UserModel();
//            $user_info = $user_model->getInfo(['uid' => $this->uid], 'user_tel,user_email');
            $order_status = $data['pay_money'] == 0 ? 1 : 0;
            //获取商品内容
            $invoice_data = [
                'website_id' => $this->website_id,
                'shop_id' => $data['shop_id'],
                'order_no' => $data['order_no'],
                'order_id' => $data['order_id'],
//                'order_status' => $data['price'] > 0 ? 0 : 2,//如果没有price，则不开发票
                'order_status' => $order_status,
                'belong_status' => $data['type'],
                'type' => $data['type'],
                'title' => $data['title'] ?: 2,
                'title_name' => $data['title_name'] ?: $data['company_name'],
                'company_name' => $data['company_name'] ?: NULL,
                'company_addr' => $data['company_addr'] ?: NULL,
                'taxpayer_no' => $data['taxpayer_no'] ?: NULL,
                'bank' => $data['bank'] ?: NULL,
                'card_no' => $data['card_no'] ?: NULL,
                'mobile' => $data['mobile'] ?: NULL,
                'is_upload' => 0,
                'config_id' => $invoiceConfigRes['id'],
                'content' => $data['content_type'] == 1 ? $data['invoice_goods_detail'] : $data['invoice_goods_category'],//商品明细/商品分类
                'content_type' => $data['content_type'] ?: NULL,
                'is_refund' => $invoiceConfigRes['is_refund'],
                'status' => 0,
                'tax' => $data['invoice_tax'] ?: 0,
                'price' => $data['price'],//订单总费用（去除邮费）
//                'user_tel' => $user_info['user_tel'],//订单总费用（去除邮费）
//                'user_email' => $user_info['user_email'],//订单总费用（去除邮费）
                'user_tel' => $data['user_tel']?:'',
                'user_email' => $data['user_email']?:'',
            ];
            $invoiceModel = new VslInvoiceModel();
            $res = $invoiceModel->save($invoice_data);
            return $res;
        } catch (\Exception $e) {
            debugLog($e->getMessage());
        }

    }

    /**
     * 开票 - 上传文件
     * @return mixed
     */
    public function invoiceConfirmImport()
    {
        if (request()->isPost()) {
            $file = request()->file('image');
            $invoice_id = request()->post('invoice_id');
            $type = (int) request()->post('type'); //开票类型1上传 0不上传
            $tax_code = request()->post('tax_code'); //发票代码
            $tax_no = request()->post('tax_no'); //发票号码
            $is_again = request()->post('is_again'); //发票号码

            $invoice = new InvoiceServer();
            $result = $invoice->invoiceImport($invoice_id, $file, $type, $tax_code, $tax_no, $is_again);
            if ($result['code'] >0) {
                $this->addUserLog('上传发票: '.$result['order_no'], $result['message']);
            }

            return $result;
        }
    }

    /**
     * 开票 - 批量上传文件（Excel,Zip）
     * @return mixed
     */
    public function invoiceBatchConfirmImport()
    {
        if (request()->isPost()) {
            $file = request()->file('excel');
            $zip = request()->file('zip');
            if (!$file || !$zip) {
                return ['code' => 0, 'message' => '请上传文件'];
            }
            $invoiceServer = new InvoiceServer();
            $result = $invoiceServer->invoiceQueueBatchImport($file, $zip);
            if ($result['code'] > 0) {
                $this->addUserLog($this->user->getUserInfo('','user_name')['user_name'].'批量上传发票', $result['message']);
            }
            return $result;
        }
    }

    /**
     * 导出Excel
     */
    public function invoiceExportExcel_Old()
    {
        $xlsName = "开票列表";
        $xlsCell = [
            ['order_no','订单号'],
            ['type','发票类型'],
            ['title','发票抬头'],
            ['title_name','抬头名称/公司名称'],//要判断是公司还是个人
            ['taxpayer_no','纳税人识别号'],
            ['company_addr','注册地址'],
            ['mobile','注册电话'],
            ['bank','开户银行'],
            ['content_type','发票内容类型'],
            ['content','发票内容'],
            ['file_name','发票文件名称'],//要去除后缀
            ['card_no','银行账户'],
        ];

        $type = request()->get('type', '');
        $order_no = request()->get('order_no', '');
        $title_name = request()->get('title_name', '');
        $taxpayer_no = request()->get('taxpayer_no', '');
        $content = request()->get('content', '');
        $status = request()->get('status', '');

        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'order_status' => 1,//订单已付款
            'type' => ['NEQ', 0],//0不开票 1电子普通发票 2 增值税专用发票
        ];
        if (isset($status)) {
            $condition['status'] = $status;
        }
        if ($type) {
            $condition['type'] = $type;
        }
        if ($order_no) {
            $condition['order_no'] = ['LIKE', '%' . $order_no . '%'];
        }
        if ($title_name) {
            $condition['title_name'] = ['LIKE', '%' . $title_name . '%'];
        }
        if ($taxpayer_no) {
            $condition['taxpayer_no'] = $taxpayer_no;
        }
        if ($content) {
            $condition['content'] = ['LIKE', '%' . $content . '%'];
        }
        $invoice = new InvoiceServer();
        $list = $invoice->invoiceList(1, 0, $condition, 'create_time DESC');
        $data = [];
        foreach ($list['data'] as $k => $v) {
            //参数转义
            $type = $v['type'] == 1 ? '电子普通发票' : '增值税专用发票';
            $title = $v['title'] == 1? '个人' : '公司';
            $title_name = $type == 2 ? $v['company_name'] : $v['title_name'];
            $content_type = $v['content_type'] == 1 ? '商品明细' : '商品分类';
            $file_name = $v['file_name'] ? str_replace(strrchr($v['file_name'], "."),"",$v['file_name']) : '';

            $data[$k]["order_no"] = $v['order_no'];
            $data[$k]["type"] = $type;
            $data[$k]["title"] = $title;
            $data[$k]["title_name"] = $title_name;
            $data[$k]["taxpayer_no"] = $v['taxpayer_no'];
            $data[$k]["company_addr"] = $v['company_addr'];
            $data[$k]["mobile"] = $v['mobile']."\t";
            $data[$k]["bank"] = $v['bank'];
            $data[$k]["card_no"] = $v['card_no']."\t";
            $data[$k]["content_type"] = $content_type;
            $data[$k]["content"] = $v['content'];
            $data[$k]["file_name"] = $file_name;
        }

        $this->addUserLog("导出发票列表excel",'');
        dataExcel($xlsName, $xlsCell, $data);
    }
    /**
     * 导出Excel
     */
    public function invoiceExportExcel()
    {
        $xlsName = "开票列表";
        $xlsCell = [
            ['order_no','订单号'],
            ['content','发票内容'],
            ['order_money','订单总额'],
            ['type','发票类型'],
            ['user_email','接收邮箱'],
            ['user_tel','接收手机号'],
            ['tax_code','发票代码'],
            ['tax_no','发票号码'],
            ['title','发票抬头'],
            ['title_name','抬头名称/公司名称'],//要判断是公司还是个人
            ['taxpayer_no','纳税人识别号'],
            ['company_addr','注册地址'],
            ['mobile','注册电话'],
            ['bank','开户银行'],
            ['card_no','银行账户'],
            ['file_name','发票文件名称'],//要去除后缀
        ];
        
        $type = request()->get('type', '');
        $order_no = request()->get('order_no', '');
        $title_name = request()->get('title_name', '');
        $taxpayer_no = request()->get('taxpayer_no', '');
        $content = request()->get('content', '');
        $status = request()->get('status', '');
        //订单实际状态：0待付款 1待发货（!=4,5,-1） 2待收货 3已收货 4已完成 5已关闭
        $actual_order_status = request()->post('actual_order_status');
        
        $condition = [
            'i.website_id' => $this->website_id,
            'i.shop_id' => $this->instance_id,
            'i.order_status' => 1,//订单已付款
            'i.type' => ['NEQ', 0],//0不开票 1电子普通发票 2 增值税专用发票
        ];
//        if (isset($status)) {
//            $condition['status'] = $status;
//        }
        if ($type) {
            $condition['i.type'] = $type;
        }
        if ($order_no) {
            $condition['i.order_no'] = ['LIKE', '%' . $order_no . '%'];
        }
        if ($title_name) {
            $condition['i.title_name'] = ['LIKE', '%' . $title_name . '%'];
        }
        if ($taxpayer_no) {
            $condition['i.taxpayer_no'] = $taxpayer_no;
        }
        if ($content) {
            $condition['i.content'] = ['LIKE', '%' . $content . '%'];
        }
        //改动关联订单状态
        if($status == 0){
            $condition['i.status'] = 0;
        }else if($status == 1 ){
            $condition['i.status'] = ['neq', 0];
        }else if($status == 2){
            $condition['i.status'] = ['>', 0];
        }else if($status == 3){
            $condition['i.status'] = -1;//发票列表中，作废为-1
        }
        //关联订单实际状态 2表示售后订单
        if($status == 2){
            $condition['o.order_status'] = -1;
        }
        if ($actual_order_status!=''){
            unset($condition['o.order_status']);//不用和发票交集
            if ($actual_order_status ==1){
                $condition['o.order_status'] = ['not in', '-1,4,5'];
            }else{
                $condition['o.order_status'] = $actual_order_status;
            }
        }
        $invoice = new InvoiceServer();
        $list = $invoice->invoiceList(1, 0, $condition, 'i.create_time DESC');
        $data = [];
        foreach ($list['data'] as $k => $v) {
            //参数转义
            $type = $v['type'] == 1 ? '电子普通发票' : '增值税专用发票';
            $title = $v['title'] == 1? '个人' : '公司';
            $title_name = $type == 2 ? $v['company_name'] : $v['title_name'];
//            $content_type = $v['content_type'] == 1 ? '商品明细' : '商品分类';
            $file_name = $v['file_name'] ? str_replace(strrchr($v['file_name'], "."),"",$v['file_name']) : '';
            $data[$k]["order_no"] = $v['order_no'];
            $data[$k]["content"] = $v['content'];
            $data[$k]["order_money"] = $v['order_money'];
            $data[$k]["type"] = $type;
            $data[$k]["user_email"] = $v['user_email']."\t";
            $data[$k]["user_tel"] = $v['user_tel']."\t";
            $data[$k]["tax_code"] = $v['tax_code'];
            $data[$k]["tax_no"] = $v['tax_no'];
            $data[$k]["title"] = $title;
            $data[$k]["title_name"] = $title_name;
            $data[$k]["taxpayer_no"] = $v['taxpayer_no'];
            $data[$k]["company_addr"] = $v['company_addr'];
            $data[$k]["mobile"] = $v['mobile'];
            $data[$k]["bank"] = $v['bank'];
            $data[$k]["card_no"] = $v['card_no'];
            $data[$k]["file_name"] = $file_name;
        }
        
        $this->addUserLog("导出发票列表excel",'');
        dataExcel($xlsName, $xlsCell, $data);
    }
    /**
     * 获取发票信息
     * @return \multitype
     * @throws Exception\DbException
     */
    public function getInvoiceImg()
    {
        $order_no = request()->post('order_no');
        if (!$order_no) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        //1、开票平台识别码
        $settingRes = $this->getInvoiceSetting();
        if ($settingRes['data']['is_pt_show'] == 0 && $settingRes['data']['is_zy_show'] == 0 ) {//未开启
            return AjaxReturn(-1);
        }
        // 获取发票
        $invoiceModel = new VslInvoiceModel();
        $invoiceInfo = $invoiceModel->getInfo(['website_id' => $this->website_id, 'order_no' => $order_no], 'order_status, file_path');//发票信息
        if (!$invoiceInfo || $invoiceInfo['order_status'] != 1 ) {
            return AjaxReturn(-1);
        }

        # pdf转图片，因为全端不能显示pdf文件
        $pdf = __IMG($invoiceInfo['file_path']);
        $path = 'upload/'.$this->website_id .'/'.$this->instance_id . '/pdf2img/';
        $newFName = pdf2Img($pdf, $path, 'invoice');
        if ($newFName){
            return AjaxReturn(SUCCESS, ['data' => $newFName]);

        }
        return AjaxReturn(-1);

    }

    /**
     * 获取授权回调
     * @return array|\multitype
     */
    public function getAuthUrl()
    {
        $order_no = request()->post('order_no');
        $redirect_url = request()->post('redirect_url', __URL('wap/mall/index'));
        $source = request()->post('source', 'wap');
        // 获取发票
        $invoiceModel = new VslInvoiceModel();
        $condition = [
            'website_id' => $this->website_id,
            'order_no' => $order_no
        ];
        $invoiceInfo = $invoiceModel->getInfo($condition);//发票信息
        $wchat = new WchatOauth($invoiceInfo['website_id']);
        //如果是开票中，返回空地址给前端防止点击
        if (in_array($invoiceInfo['is_upload'] , [0, 3])) {
            return AjaxReturn(SUCCESS, ['data' => '']);
        }
        // 1、获取开票平台标识s_pappid
        if (!$s_pappid = $invoiceInfo['s_pappid']) {
            $s_pappidRes = $wchat->getInvoiceStorePlatformAppId();
            $insert_data = [
                'card_status' => 1,
                'card_error' => json_encode(AjaxWXReturn($s_pappidRes['errcode'], [],$s_pappidRes['errmsg']))
            ];
            $invoiceModel->isUpdate()->save($insert_data, $condition);
            if ($s_pappidRes['errcode'] != 0) {
                return ['code' => -1, 'message' => $s_pappidRes['errmsg']];
            }
            $invoice_url = $s_pappidRes['invoice_url'];
            $tempArr = explode('s_pappid=', $invoice_url);
            $s_pappid = $tempArr[1];
            $invoiceModel->save(['s_pappid' => $s_pappid], $condition);
        }
        $user  = new User();
        $phone = $user->getShopFirstMobile();
        $contact = [
            'contact' => [
                'phone' => $phone,
                'time_out' => 30,//开票超时时间
            ]
        ];
        $setBizRes = $wchat->postInvoiceSetbizattr($contact);
        $insert_data = [
            'card_status' => 2,//设置商户联系方式
            'card_error' => json_encode(AjaxWXReturn($setBizRes['errcode'], [], $setBizRes['errmsg']))
        ];
        $invoiceModel->save($insert_data, $condition);
        if ($setBizRes['code'] != 0) {
            return ['code' => -1, 'message' => $setBizRes['message']];
        }
//        }
        //3、获取授权页ticket
        $auth_ticket = empty($invoiceInfo['auth_ticket']) ? null : json_decode($invoiceInfo['auth_ticket'], true);
        if ($auth_ticket && $auth_ticket['expire'] > time()) {
            $ticket = $auth_ticket['auth_ticket'];
        } else {
            $ticketRes = $wchat->getInvoiceTicket();
            $insert_data = [
                'card_status' => 3,
                'card_error' => json_encode(AjaxWXReturn($ticketRes['errcode'], [],$ticketRes['errmsg']))
            ];
            $invoiceModel->save($insert_data, $condition);
            if ($ticketRes['errcode'] != 0) {
                return ['code' => -1, 'message' => $ticketRes['errmsg']];
            }
            $ticket = $ticketRes['ticket'];
            $auth_ticket = [
                'auth_ticket' => $ticket,
                'expire' => time() + $ticketRes['expires_in'] - 200,
            ];
            $invoiceModel->save(['auth_ticket' => json_encode($auth_ticket)], $condition);
        }
        $order_id = $invoiceInfo['order_id'] ?: $order_no.time();

        //4、获取授权页链接
        $post_data = [
            's_pappid' => $s_pappid,
            'order_id' => $order_id,
            'money' => $invoiceInfo['price'] * 100,//以分为单位
            'timestamp' => time(),
            'source' => $source,//开票来源，app：app开票，web：微信h5开票，wxa：小程序开发票，wap：普通网页开票
            'redirect_url' => $redirect_url,
            'ticket' => $ticket,
            'type' => 2,//授权类型，0：开票授权，1：填写字段开票授权，2：领票授权
        ];
        $urlRes = $wchat->getInvoiceAuthUrl($post_data);
        $insert_data = [
            'card_status' => 4,
            'card_error' => json_encode(AjaxWXReturn($urlRes['errcode'], [], $urlRes['errmsg'])),
            'order_id' => $order_id,
        ];
        $invoiceModel->save($insert_data, $condition);
        if ($urlRes['errcode'] != 0) {
            return ['code' => -1, 'message' => $urlRes['errmsg']];
        }
        $auth_url = $urlRes['auth_url'];
        return AjaxReturn(SUCCESS, ['data' => $auth_url]);
    }

    /**
     * 添加发票到微信卡包 - 前端没调
     * @return array|\multitype
     * @throws Exception\DbException
     */
    public function post2WxCardPackage($order_no)
    {
//        $order_no = request()->post('order_no');
        if (!$order_no) {return AjaxReturn(LACK_OF_PARAMETER);}
        // 获取发票
        $invoiceModel = new VslInvoiceModel();
        $condition = [
            'website_id' => $this->website_id,
            'order_no' => $order_no
        ];
        $invoiceInfo = $invoiceModel->getInfo($condition);//发票信息
        if (!$invoiceInfo) {return ['code' => -1, 'message' => '发票信息不存在'];}
        // 先判断是否有appid
        $config = new Config();
        $wchat_config = $config->getConfig($invoiceInfo['shop_id'],'SHOPWCHAT', $invoiceInfo['website_id']);
        if (!$appId = $wchat_config['value']['appid']) {
            return ['code' => -1, 'message' => 'appId为空'];
        }

        //6、创建发票卡券模板
        $cardRes = $this->getInvoiceCreateCard($order_no);//获取卡券模板id

        if ($cardRes['code'] < 0) {
            $invoiceModel->save(['is_upload' => 4], $condition);
            return $cardRes;
        }
        $card_id = $cardRes['data']['card_id'];

        //7、上传发票PDF
        $mediaRes = $this->uploadPdfAndgetInvoiceMediaId($order_no);//上传发票PDF，获取电子发票PDF的id

        if ($mediaRes['code'] < 0) {
            $invoiceModel->save(['is_upload' => 4], $condition);
            return $mediaRes;
        }
        $s_media_id = $mediaRes['data'];//电子发票pdf的id

        //组装数据
        $data = [
            'order_id' => $invoiceInfo['order_id'],
            'card_id' => $card_id,
            'appid' => $appId,
            /*发票具体内容*/
            'card_ext' => [
                'nonce_str' => time(),
                /*用户信息结构体*/
                'user_card' => [
                    "invoice_user_data" => [
                        'fee' => $invoiceInfo['price'] * 100,//发票的金额，以分为单位
                        'title' => $invoiceInfo['title_name'],
                        'billing_time' => $invoiceInfo['update_time'],
                        'billing_no' => $invoiceInfo['tax_no'],//发票代码
                        'billing_code' => $invoiceInfo['tax_code'],//发票号码
                        'fee_without_tax' => $invoiceInfo['price'] * 100 - $invoiceInfo['tax'] * 100,//不含税金额，以分为单位
                        'tax' => $invoiceInfo['tax'] * 100,//税额，以分为单位
                        's_pdf_media_id' => $s_media_id,
                        'check_code' => $invoiceInfo['order_no'],
                    ]
                ],
            ],
        ];

        $wchat = new WchatOauth($invoiceInfo['website_id']);
        //8、插卡
        $cardP = $wchat->postInvoiceInsertCardPackage($data);

        $insert_data = [
            'card_status' => 8,
            'card_error' => json_encode(AjaxWXReturn($cardP['errcode'], [], $cardP['errmsg']))
        ];
        $invoiceModel->save($insert_data, $condition);
        if ($cardP['errcode'] !=0 ){
            $invoiceModel->save([
                'is_upload' => 4,//插卡失败
            ],
                [
                    'website_id' => $invoiceInfo['website_id'],
                    'order_no' => $order_no
                ]
            );
            return AjaxWXReturn($cardP['errcode'], $cardP['errmsg']);
        }
        $invoiceModel->save([
            'code' => $cardP['code'],
            'openid' => $cardP['openid'],
            'is_upload' => 5,//插卡成功
            'is_auth' => 0,
        ],
            [
                'website_id' => $invoiceInfo['website_id'],
                'order_no' => $order_no
            ]
        );

        return AjaxReturn(SUCCESS);

    }

    /**
     * 上传发票PDF，获取电子发票PDF的id
     * @param $order_no string [发票对应订单no]
     * @return array|\multitype [$s_media_id:电子发票pdf的id]
     * @throws Exception\DbException
     */
    public function uploadPdfAndgetInvoiceMediaId($order_no)
    {
        $order_no = $order_no ?: request()->post('order_no');
        if (!$order_no) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        //1、开票平台识别码
        $settingRes = $this->getInvoiceSetting();
        if ($settingRes['data']['is_pt_show'] == 0 && $settingRes['data']['is_zy_show'] == 0 ) {//未开启
            return ['code' => -1, 'message' => '未开启发票应用'];
        }
        // 获取发票
        $invoiceModel = new VslInvoiceModel();
        $condition = [
            'website_id' => $this->website_id,
            'order_no' => $order_no
        ];
        $invoiceInfo = $invoiceModel->getInfo($condition);//发票信息
        if (!$invoiceInfo || $invoiceInfo['order_status'] != 1 ) {
            return ['code' => -1, 'message' => '发票信息不存在'];
        }
        if (!$invoiceInfo['file_path']) {
            return ['code' => -1, 'message' => '上传的发票图片不存在'];
        }

        //如果是pdf结尾不处理
        if (substr(strrchr($invoiceInfo['file_path'], '.'), 0) == '.pdf' ) {
            $fileName = getFileNameOfUrl($invoiceInfo['file_path'], true);//文件名
            $binary = file_get_contents($invoiceInfo['file_path']);
        } else {
            $fileName = getFileNameOfUrl($invoiceInfo['file_path']);//获取文件名（不含后缀）
            $tempDir = 'upload' . DS . $this->website_id . DS . 'invoice'. DS .'temp' ;
            if (!is_dir($tempDir)) {
                $mode = intval('0777', 8);
                mkdir($tempDir, $mode, true);
                chmod($tempDir, $mode);
            }
            $tempPDF = $tempDir. DS . $fileName . '.pdf';
            $fileName = getFileNameOfUrl($tempPDF, true);
            $img_file = ROOT_PATH . $invoiceInfo['file_path'];
            $pdf_file = ROOT_PATH . $tempPDF;
            if (file_exists($pdf_file)){
                @unlink($pdf_file);
                $pdf_file = ROOT_PATH . $tempPDF;
            }
            img2Pdf($img_file, $pdf_file, 'F');//把原图转存PDF，再把该PDF上传微信接口，成功后删除该PDF
            $binary = file_get_contents($pdf_file);
        }

        $fields = [
            'type' => 'pdf',
            'filename' => $fileName,
            'filesize' => strlen($binary),
            'offset' => 0,
            'filetype' => '.pdf',
            'originName' => $fileName,
            'upload'=> $binary
        ];
        try {
            $wchat = new WchatOauth($invoiceInfo['website_id']);
            $pdfRes = $wchat->postInvoiceUploadPdf($fields);
            $insert_data = [
                'card_status' => 7,
                'card_error' => json_encode(AjaxWXReturn($pdfRes['errcode'], [], $pdfRes['errmsg']))
            ];
            $invoiceModel->save($insert_data, $condition);
            if (file_exists($pdf_file)) {
                @unlink($pdf_file);
            }
            if ($pdfRes && $pdfRes['errcode'] != 0) {
                $invoiceModel->save(['is_upload' => 4], $condition);
                return AjaxWXReturn($pdfRes['errcode'], $pdfRes['errmsg']);
            }
            $s_media_id = $pdfRes['s_media_id'];/*s_media_id有效期有3天，3天内若未将s_media_id关联到发票卡券，pdf将自动销毁*/
            $invoiceModel->save([
                's_media_id' => $s_media_id,
                'is_upload' => 2,//已上传pdf到微信
            ],
                [
                'website_id' => $invoiceInfo['website_id'],
                'order_no' => $order_no,
                ]
            );
            return ['code' => 1, 'message' => '上传PDF成功', 'data' => $s_media_id];

        } catch (\Exception $e) {
            return ['code' => -1, 'message' => '上传错误'];
        }

    }

    /**
     * 获取卡券模板id
     * @param $order_no
     * @return array|\multitype [card_id: 卡券模板的编号]
     * @throws Exception\DbException
     */
    public function getInvoiceCreateCard($order_no)
    {
        $order_no = $order_no ?: request()->post('order_no');
        if (!$order_no) {
            return AjaxReturn(LACK_OF_PARAMETER);
        }

        //1、开票平台识别码
        $settingRes = $this->getInvoiceSetting();
        if ($settingRes['data']['is_pt_show'] == 0 && $settingRes['data']['is_zy_show'] == 0 ) {//未开启
            return ['code' => -1, 'message' => '未开启发票应用'];
        }
        // 获取发票
        $invoiceModel = new VslInvoiceModel();
        $condition = [
            'website_id' => $this->website_id,
            'order_no' => $order_no
        ];
        $invoiceInfo = $invoiceModel->getInfo($condition);//发票信息
        if (!$invoiceInfo || $invoiceInfo['order_status'] != 1 ) {
            return ['code' => -1, 'message' => '发票信息不存在'];
        }
        $type = $invoiceInfo['type'] == 1 ? '电子普通发票' : '增值税专用发票';
        $title = mb_substr($invoiceInfo['title_name'], 0, 9, 'utf-8');
        $shopServer = new ShopServer();
        $shop_logo = $shopServer->getShopLogo($invoiceInfo['shop_id']);//店铺logo
        $cardParams = [
            'invoice_info' => [
                'payee' => $invoiceInfo['title_name'],
                'type' => $type,
                'base_info' => [
                    'logo_url' => $shop_logo,/*如果有可以通过微信素材获取*/
                    'title' => $title,//最长9个汉字
                ],
            ]
        ];
        try {
            $wchat = new WchatOauth($invoiceInfo['website_id']);
            $cardRes = $wchat->postInvoiceCreateCard($cardParams);
            $insert_data = [
                'card_status' => 6,
                'card_error' => json_encode(AjaxWXReturn($cardRes['errcode'], [], $cardRes['errmsg']))
            ];
            $invoiceModel->save($insert_data, $condition);
            # todo... card_id可以从数据库先取出，没有再调接口
            if ($cardRes['errcode'] != 0) {
                return AjaxWXReturn($cardRes['errcode'], $cardRes['errmsg']);
            }
            $invoiceModel->save([
                'card_id' => $cardRes['card_id']
            ],
                [
                    'website_id' => $invoiceInfo['website_id'],
                    'order_no' => $order_no
                ]
            );

            return AjaxReturn(SUCCESS, ['card_id' => $cardRes['card_id']]);
        } catch (\Exception $e) {
            return ['code' => -1, 'message' => $e->getMessage()];
        }

    }

    /**
     * 用户授权后，微信会推动消息，根据推送消息，添加发票到微信卡包 - 微信推送回来
     * @param $invoice_data
     * @throws Exception\DbException
     */
    public function userGetInvoiceFromWchat($invoice_data)
    {
        // todo... 查询授权情况
        $invoiceModel = new VslInvoiceModel();
        if (is_object($invoice_data)) {
            $invoice_data = objToArr($invoice_data);
        }
        if (!$invoice_data || $invoice_data['f_order_no']) {//失败
            $insert_data = [
                'card_status' => 5,//授权失败的订单
                'card_error' => json_encode($invoice_data),
                'is_auth' => 1,
                'is_upload' => 4,
            ];
            $f_condition = [
                'website_id' => $this->website_id,
                'order_id' => $invoice_data['f_order_no']
            ];
            $order_result = $invoiceModel->getInfo($f_condition, 'order_no');
            if ($order_result && $order_result['order_no']) {
                $order_no = $order_result['order_no'];
                //重新上传的PDF，需要重置开票授权链接
                $this->rejectInsert($order_no, '授权失败');
            }
            $invoiceModel->save($insert_data, $f_condition);
            return;
        }
        $insert_data = [
            'user_auth_time' => $invoice_data['create_time'],
            'is_upload' => 3,//用户授权该发票（领取发票 - 开票中）
            'source' => $invoice_data['source'],
            'openid' => $invoice_data['openid'] ?: '',
            'card_status' => 5,//微信回调
            'card_error' => json_encode($invoice_data),
            'is_auth' => 1,
        ];
        $s_conditon = [
            'website_id' => $this->website_id,
            'order_id' => $invoice_data['s_order_no']
        ];
        $res =  $invoiceModel->save($insert_data, $s_conditon);
        $order_result = $invoiceModel->getInfo($s_conditon, 'order_no');
        if ($order_result && $order_result['order_no']) {
            $order_no = $order_result['order_no'];
        }
        if ($res) {
            $result = $this->post2WxCardPackage($order_no);
            if ($result['code'] != 1) {
                //重新上传的PDF，需要重置开票授权链接
                $this->rejectInsert($order_no, $result['message'] ?: '插卡失败');
                return;
            }
        }
    }

    /**
     * 获取真实订单税费
     * @param $order_no
     * @return int
     */
    public function getOrderRealInvoiceTaxByOrderNo($order_no)
    {
        $order = new VslOrderModel();
        $orderInfo = $order->getInfo([
            'order_no' => $order_no
        ], 'invoice_tax');
        if (!$orderInfo) {return 0;}
        return $orderInfo['invoice_tax'];
    }

    /**
     * 拒绝开票 - 重置微信开票授权链接
     * @param $order_no string [订单号]
     * @return array|void
     */
    public function rejectInsert($order_no, $reason = '')
    {
        $invoice = new InvoiceServer();
        $invoice->rejectInsert($order_no, $reason);
    }
    
    /**
     * 修改发票状态
     * @return \multitype
     */
    public function updateInvoiceStatus ()
    {
        $id = request()->post('id');
        $invoiceModel = new VslInvoiceModel();
        $invoiceModel->save(['status'=> -1], ['id'=> $id]);
        return AjaxReturn(SUCCESS);
    }

    /*************************** test **********************************/
    /**
     * 模拟定时任务执行发票上传
     */
    public function copyInvoiceTask()
    {
        try {
        $invoiceFile = new VslInvoiceFileModel();
        $firstData = $invoiceFile->getQuery(['status' => 3], '*', 'create_time asc');
        if (!$firstData) {
            exit;
        }
        //status: 0未执行，1成功 2失败 3等待中 4处理中 5部分完成
        foreach ($firstData as $val) {
            $invoiceServer = new invoiceServer();
            $condition = ['id' => $val['id']];
//            $invoiceServer->updateInvoiceFileInfo(['status' => 4], $condition);
            //执行解压，上传
            $res = $invoiceServer->addInvoiceByXls($val['excel_name'], $val['zip_name'], $val['shop_id'], $val['website_id']);//返回0：错误 1：正确 2：部分错误
            if ($res['code'] == 0) {//失败
                $invoiceServer->updateInvoiceFileInfo(['status' => 2, 'error_excel_path' => $res['data'], 'error_info' => $res['message']] , $condition);
            }else if ($res['code'] == 2) {//部分成功
                $invoiceServer->updateInvoiceFileInfo(['status' => 5, 'error_excel_path' => $res['data'], 'error_info' => $res['message']], $condition);
            }else if ($res['code'] == 1) {//成功
                $invoiceServer->updateInvoiceFileInfo(['status' => 1], $condition);
//                        $invoiceFile->delData(['id' => $val['id']]);//成功的删除
            }
        }
            return ['code' => 1, 'message' => '上传成功'];
        } catch (\Exception $e) {
            $msg = 'InvoiceTask: '.$e->getMessage();
        }

    }
    /*************************** /test **********************************/
    }
