<?php

namespace addons\invoice\server;

use addons\invoice\model\VslInvoiceConfigModel;
use addons\invoice\model\VslInvoiceFileModel;
use addons\invoice\model\VslInvoiceModel;
use data\extend\WchatOauth;
use data\model\VslOrderGoodsModel;
use data\model\VslOrderModel;
use data\service\BaseService;
use data\service\Config as WebConfig;
use data\service\Upload\AliOss;
use think\Db;
use think\Exception;

/**
 * 发票助手数据处理
 * Class Invoice
 * @package addons\invoice\server
 */
class Invoice extends BaseService {

    private $upload_type = 1;

    public function __construct() {
        parent::__construct();
        $config = new WebConfig();
        $this->upload_type = $config->getConfigMaster(0, 'UPLOAD_TYPE', 0, 1);
    }

    public function getInvoicesCount($condition)
    {
        $invoice_model = new VslInvoiceModel();
//        $count = $invoice_model->where($condition)->count();
        $count = $invoice_model->getInvoiceViewCount($condition);
        return $count;

        // TODO Auto-generated method stub
    }
    /**
     * 发票列表
     * @param int $page_index
     * @param mixed $page_size
     * @param array $condition
     * @param string $order
     * @param string $field
     * @return array
     */
    public function invoiceList($page_index = 1, $page_size = 0, $condition = [], $order = '', $field = '*')
    {
        $invoice_model = new VslInvoiceModel();
//        $lists = $invoice_model->pageQuery($page_index, $page_size, $condition, $order, $field);
        $lists = $invoice_model->getInvoiceViewList($page_index, $page_size, $condition, $order, $field);
        foreach ($lists['data'] as $list) {
            $order = new VslOrderModel();
            $list['order_id'] = $order->getInfo(['order_no' => trim($list['order_no'])], 'order_id')['order_id'];
            // content 存入时是（eg: 服装>上衣 520¥__） (双下划线)
            $list['content'] = $this->changeInvoiceContentByCategory($list['content']);
        }
        return $lists;
    }
    /**
     * 获取发票助手上传文件列表
     * @param int $page_index
     * @param int $page_size
     * @param string $condition
     * @param string $order
     * @return array
     */
    public function getInvoiceFileList($page_index = 1, $page_size = 0, $condition = '', $order = '')
    {
        $invoiceFile = new VslInvoiceFileModel();
        $list = $invoiceFile->pageQuery($page_index, $page_size, $condition, $order, '*');
        return $list;
    }

    /**
     * 获取发票设置信息
     * @param $condition
     * @param $filed
     * @return VslInvoiceConfigModel
     * @throws \think\Exception\DbException
     */
    public function getInvoiceConfig($condition, $filed = '*')
    {
        $invoiceConfigModel = new VslInvoiceConfigModel();
        $result = $invoiceConfigModel->getInfo($condition, $filed);

        return $result;
    }
    /**
     * 计算店铺发票设置税费
     * @param $shop_id
     * @param $amount float [订单店铺商品总价]
     * @return $tax_amount [店铺商品应收税]
     */
    public function calculateShopInvoiceTax($shop_id, $amount=0)
    {
        $condition = [
            'website_id' => $this->website_id,
            'shop_id' => $shop_id,
        ];
        $invoiceModel = new VslInvoiceConfigModel();
        $invoiceInfo = $invoiceModel->getInfo($condition);
        if (empty($invoiceInfo)) {return (object)[];}
        //1电子普通发票 2 增值税专用发票
        $tax = [
            'pt' => roundLengthNumber($invoiceInfo['pt_invoice_tax'] * $amount/100, 2,false),
            'is_pt_show' => $invoiceInfo['is_pt_show'],
            'is_zy_show' => $invoiceInfo['is_zy_show'],
            'zy' => roundLengthNumber($invoiceInfo['zy_invoice_tax'] * $amount/100, 2,false),
        ];
        return $tax;
    }
    /**
     * 添加发票信息
     * @param $website_id
     * @param $shop_id
     * @param $info array [发票数据]
     * @return mixed
     */
    public function addInvoiceInfo($website_id, $shop_id, $info)
    {
        if (empty($data)) {return false;}
        $type = $info['type'];// 1电子普通发票 2 增值税专用发票

        //查询订单编号
        $order = new VslOrderModel();
        $order_no = $order->getInfo(['order_id' => $info['order_id']], 'order_no')['order_no'];

        $data = [
            'website_id' => $website_id,
            'shop_id' => $shop_id,
        ];
        $invoiceConfigModel = new VslInvoiceConfigModel();
        $invoice_config = $invoiceConfigModel::get($data);

        $data = [
            'status' => 0,
            'is_upload' => 0,
            'config_id' => $invoice_config['id'],
            'order_no' => $order_no,
            'order_status' => $info['order_status'],
            'belong_status' => $info['type']==1 ? $info['title'] : 2,//所属1个人，2公司
            'type' => $info['type'],
            'title' => $info['title'] ?: '',
            'title_name' => $info['title_name'] ?: '',
            'taxpayer_no' => $info['taxpayer_no'] ?: '',
            'company_name' => $info['company_name'] ?:'',
            'company_addr' => $info['company_addr'] ?:'',
            'mobile' => $info['mobile'] ?:'',
            'bank' => $info['bank'] ?:'',
            'card_no' => $info['card_no'] ?:'',
            'content_type' => $info['company_addr'],
            'content' => $info['content'] ?:'',
            'tax' => $type == 1 ? $invoice_config['pt_invoice_tax'] : $invoice_config['zy_invoice_tax'],
            'is_refund' => $invoice_config['is_refund'],
        ];

        $invoice = new VslInvoiceModel();
        $invoice->save($data);
    }
    /**
     * 支付时候修改订单发票为支付状态
     * @param $out_trade_no string [交易号]
     * @param int $type int [修改状态0未支付 1支付]
     * @return mixed
     */
    public function updateOrderStatusByOutTradeNo($out_trade_no, $type = 1)
    {

        if (empty($out_trade_no)) {return;}
        $order = new VslOrderModel();
        $orders = $order->getInfo(['out_trade_no' => $out_trade_no], 'order_no');
        if (empty($orders)) {return;}
        $invoiceModel = new VslInvoiceModel();
        return $invoiceModel->updateOrderStatus($orders, $type);
    }
    /**
     * 开票上传文件 - 单个
     * @param $invoice_id int [发票id]
     * @param $file binary [图片资源]
     * @param $type int [开票类型 1上传 0不上传]
     * @return mixed
     */
    public function invoiceImport($invoice_id, $file, $type, $tax_code = '', $tax_no = '', $is_again = 0) {
        $invoice = new VslInvoiceModel();
        if ($type == 0) {
            $invoice->save(['status' => 1, 'is_upload' => 0, 'file_path' => '', 'file_name' => ''], ['id' => $invoice_id]);//已开
            $order_no = $invoice->getInfo(['id' => $invoice_id], 'order_no')['order_no'];
            return ['code' => 1, 'message' => '修改成功', 'data' => $order_no];
        }
        if (!$file) {
            return ['code' => 0, 'message' => '请上传文件'];
        }

        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');

        $base_path = 'upload' . DS . $this->website_id . DS . 'invoice'. DS ;
        if (!file_exists($base_path)) {
            $mode = intval('0777', 8);
            mkdir($base_path, $mode, true);
            chmod($base_path, $mode);
        }
        $info = $file->validate(['ext' => 'jpg,png,pdf'])->move($base_path. 'image');

        if (!$info) {
            // 上传失败获取错误信息
            return ['code' => 0, 'message' => '文件格式错误，请重新上传'];
        }

        $imagePath = $info->getSaveName(); //获取文件名
        $infoa = $info->getInfo($imagePath);
        $file_types = explode(".", $infoa['name']);
        $file_name = $base_path.  'image' . DS . $imagePath; //图片地址
        if (!file_exists($file_name)) {
            $mode = intval('0777', 8);
            mkdir($file_name, $mode, true);
            chmod($file_name, $mode);
        }
        $file_path  = $file_name;
        //上传阿里云
        if ($this->upload_type == 2) {
            $alioss = new AliOss();
            $result = $alioss->setAliOssUplaod($file_name, $file_name);
            if($result['code']){
                $file_path = $result['path'];//云地址
                @unlink($file_name);
            }
        }

        if ($is_again == 1) {
            $invoiceInfo = $invoice->getInfo(['id' => $invoice_id], 'order_no, is_upload');
            //如果插卡成功，重置order_id
            if ($invoiceInfo['is_upload'] == 5) {
                $order_id = $invoiceInfo['order_no'] . time();
                $invoice->isUpdate()->save(['order_id' => $order_id], ['id' => $invoice_id]);
            }elseif (in_array($invoiceInfo['is_upload'], [3,4])) {
                //撤回开票
                $this->rejectInsert($invoiceInfo['order_no'], '重新开票');
            }
        }

        $data['file_path'] = $file_path; //上传文件的地址
        $data['file_name'] = $infoa['name'];
        $data['file_type'] = end($file_types);
        $data['is_upload'] = 1;
        $data['status'] = 1;
        $data['tax_code'] = $tax_code;
        $data['tax_no'] = $tax_no;
        $data['is_again'] = $is_again;
        $invoice = new VslInvoiceModel();
        $save = $invoice->isUpdate(false)->save($data, ['id' => $invoice_id]);
        if (!$save) {
            return ['code' => 0, 'message' => '操作失败，请稍后重试' ];
        }
        $order_no = $invoice->getInfo(['id' => $invoice_id], 'order_no')['order_no'];
        $result['code'] = 1;
        $result['message'] = '上传成功';
        $result['order_no'] = $order_no ?:'';
        return $result;
    }
    /**
     * 开票上传文件 - 批量
     * @param $file binary [图片资源]
     * @param $zip
     * @return mixed
     */
    public function invoiceQueueBatchImport($file, $zip)
    {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');

        $base_path = 'upload' . DS . $this->website_id . DS . 'invoice'. DS ;
        if (!file_exists($base_path)) {
            $mode = intval('0777', 8);
            mkdir($base_path, $mode, true);
            chmod($base_path, $mode);
        }

        $info = $file->validate(['ext' => 'csv,xlsx,xls'])->move($base_path. 'Excel');

        if (!$info) {
            // 上传失败获取错误信息 
            return ['code' => 0, 'message' => '文件格式错误，请重新上传' ];
        }
        $exclePath = $info->getSaveName(); //获取文件名
        $old_excel_name = $info->getInfo($exclePath);
        $file_types = explode(".", $old_excel_name['name']);
        $zipname = '';
        $old_zip_name = [];
        if ($zip) {
        $zipInfo = $zip->validate(['ext' => 'zip'])->move($base_path. 'Zip');
        if (!$zipInfo) {
            // 上传失败获取错误信息  
            return ['code' => 0, 'message' => '文件格式错误，请重新上传' ];
        }
        $zipPath = $zipInfo->getSaveName(); //获取文件名
        $old_zip_name = $zipInfo->getInfo($zipPath);
        $zipname = $base_path. 'Zip' . DS . $zipPath;
    }

        $data = [
            'website_id' => $this->website_id,
            'shop_id' => $this->instance_id,
            'type' => end($file_types), //文件类型
            'excel_name' => $base_path . 'Excel' . DS . $exclePath,//Excel上传文件的地址,
            'zip_name' => $zipname,
            'old_excel_name' => $old_excel_name['name'] ?: '',
            'old_zip_name' => $old_zip_name['name'] ?: '',
            'status' => 3,//等待中
        ];

        $invoiceFile = new VslInvoiceFileModel();
        $save = $invoiceFile->isUpdate(false)->save($data);
        if (!$save) {
            return ['code' => 0, 'message' => '操作失败，请稍后重试' ];
        }

        return ['code' => 1, 'message' => '已添加导入队列，等待执行' ];
    }
    /**
     * 导入发票信息
     * @param $file string [excel_name文件名]
     * @param $zip string [zip文件路径]
     * @param $shop_id
     * @param $website_id
     * @return array|false|int
     */
    public function addInvoiceByXls_Old($file, $zip, $shop_id, $website_id) {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        Db::startTrans();
        try {
            $shop_path = $shop_id ? '/'.$shop_id : '';
            $img_path = 'upload/' . $website_id .$shop_path. '/common/invoice/';
            $zip_error_info = [];
            if ($zip) {
                $zip_error_info =  $this->get_zip_originalsize($zip, $img_path, $website_id, $shop_id); //处理zip文件
                if (empty($zip_error_info['right_path'])) {
                    //储存excel路径
                    return ['code' => 0, 'message' => isset($zip_error_info['message']) ? $zip_error_info['message'] : 'Zip解压失败', 'data' => $file];
                }
            }
            //获取文件类型
            Vendor('PhpExcel.PHPExcel');
            $file_type = \PHPExcel_IOFactory::identify($file);
            if ($file_type) {
                $objReader = \PHPExcel_IOFactory::createReader($file_type);
//                $objReader->setReadDataOnly(true);
                $obj_PHPExcel = $objReader->load($file); //加载文件内容,编码utf-8
                $excel_array = $obj_PHPExcel->getsheet()->toArray(); //转换为数组格式
            } else {
                return ['code' => 0, 'message' => '文件格式错误，请重新上传'];
            }

            $i = 0; //成功数量
            $j = 0; //全部数量
            array_shift($excel_array);//发票助手删除第一行提示标语
            $key_array = $excel_array[0];//标题
            unset($excel_array[0]);
            $excel_array = array_values($excel_array);
            // excel为空
            if (empty($excel_array)) {
                return ['code' => 0, 'message' => 'Excel文件为空', 'data' => $file];
            }
            //查询店铺发票税点值
            $condition = [
                'website_id' => $website_id,
                'shop_id' => $shop_id,
            ];
            $invoiceModel = new VslInvoiceConfigModel();
            $invoiceConfig = $invoiceModel->getInfo($condition);
            $right_arr = [];
            $error_arr = [];
            $excel_error_message = '';

            $column = 13;// 这里是根据Excel图片所在列默认值
            $img_arr = [];
            $sheetCount = count($excel_array[0]);
            $excel_img_arr = array_column($excel_array, $column);//根据Excel知晓
            $s_repeat = arraySeparateRepeat($excel_img_arr);
            foreach ($excel_array as $k => $v) {
                $j++;
                //Excel中图片名是否重复
                if (in_array($v[$column], $s_repeat['n_repeat'])) {
                    //是否在正确的zip解压图片中
                    if (in_array($v[$column], $zip_error_info['right'])){//在zip正确中,去上传
                        //记录zipt图片上传并存储到invoice表
                        $z_k = array_search($v[$column], $zip_error_info['right']);//查找zip是这张图的键key,通过key查到对应right_path
                        $file_path = $zip_error_info['right_path'][$z_k];//正确待上传的图片路径
                        //上传云
                        if ($this->upload_type == 2) {
                            $alioss = new AliOss();
                            $result = $alioss->setAliOssUplaod($file_path, $file_path);
                            if($result['code']){
                                @unlink($file_path);
                                $file_path = $result['path'];//云返回图片路径
                            } else {//记录错误
                                $excel_error_message .= $k+1 .'行上传失败; ';
                                $error_arr[] = $v;
                                continue;
                            }
                        }
                        //储到invoice表
                        $excelData = array_combine($key_array, $v);
                        //参数转义
                        $type = mb_substr($excelData['发票类型'], 0, 2) == '电子' ? 1 : 2;
                        $belong_status = $title = $excelData['发票抬头'] == '个人' ? 1 : 2;
                        $company_name = $type== 2 ? $excelData['抬头名称/公司名称'] : '';
                        $content_type =  $excelData['发票内容类型'] == '商品明细' ? 1 : 2;
                        $tax = $type == 1 ? $invoiceConfig['pt_invoice_tax'] : $invoiceConfig['zy_invoice_tax'];
                        //查询订单状态

                        //查询invoice表中是否存在
                        $invoice = new VslInvoiceModel();
                        $invoice_id = $invoice->getInfo(['website_id' => $this->website_id, 'shop_id' => $this->instance_id, 'order_no' => $excelData['订单号']], 'id')['id'];

                        $data = [
                            'website_id' => $website_id,
                            'shop_id' => $shop_id,
                            'config_id' => $invoiceConfig['id'],
                            'order_no' => $excelData['订单号'],
                            'order_status' => 1,
                            'status' => 1,
                            'belong_status' => $belong_status,
                            'is_upload' => 1,
                            'type' => $type,
                            'title' => $title,
                            'title_name' => $excelData['抬头名称/公司名称'],
                            'taxpayer_no' => ' '.$excelData['纳税人识别号'],
                            'company_name' => $company_name,
                            'company_addr' => $excelData['注册地址'],
                            'mobile' => $excelData['注册电话'],
                            'bank' => $excelData['开户银行'],
                            'card_no' => ' '.$excelData['银行账户'],
                            'content_type' => $content_type,
                            'content' => $excelData['发票内容'],
                            'tax' => $tax,
                            'is_refund' => $invoiceConfig['is_refund'],
                            'file_path' => $file_path, //图片URL
                            'tax_code' => $excelData['发票代码'], //发票代码
                            'tax_no' => $excelData['发票号码'], //发票号码
                        ];
                        //存入invoice表
                        if ($invoice_id) {//更新
                            $result = $invoice->save($data, ['id' => $invoice_id]);
                        } else {
                            $result = $invoice->save($data);
                        }

                        if (!$result) {
                            $excel_error_message .= $k+1 .'行发票信息存储错误; ';
                            $error_arr[] = $v;
                            continue;
                        }
                        $i++;
                    } else {//错误不上传
                        $excel_error_message .= '第'.$k+1 .'行'.$v[$column]?:''. ' 解压失败; ';
                        $error_arr[] = $v;
                    }
                } else { //错误不上传
                    $excel_error_message .= $k+1 .'行图片名重复; ';
                    $error_arr[] = $v;
                }
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
                $res = dataExcel($file_name, $xlsCell, $data, $path, $suffix);
                if ($res['code'] > 0) {
                    $error_new_path = $res['data'];
                }
            }

            unset($res);
            if ($i == 0) {
                $res['code'] = 0;
                $res['message'] = $excel_error_message;
                Db::rollback();
            } else if ($i == $j ) {
                $res['code'] = 1;
                $res['message'] = '导入总数目：' . $j . ',导入成功';
                Db::commit();
            } else {
                $res['code'] = 2;
                $res['message'] = '导入总数目：' . $j . ',导入成功数目：' . $i.'失败原因：'.$excel_error_message;
                $res['data'] = $error_new_path;
                Db::rollback();
            }
            @unlink($file);
            @unlink($zip);
            return $res;
        } catch (\Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
            $log_dir = getcwd() . '/invoice_import.log';
            file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * 导入发票信息
     * @param $file string [excel_name文件名]
     * @param $zip string [zip文件路径]
     * @param $shop_id
     * @param $website_id
     * @return array|false|int
     */
    public function addInvoiceByXls($file, $zip, $shop_id, $website_id) {
        set_time_limit(0);
        ini_set('max_execution_time', '0');
        ini_set('memory_limit', '1000M');
        Db::startTrans();
        try {
            $shop_path = $shop_id ? '/'.$shop_id : '';
            $img_path = 'upload/' . $website_id .$shop_path. '/common/invoice/';
            $zip_error_info = [
                'error' => [],
                'error_path' => [],
                'right' => [],
                'right_path' => []
            ];
            if ($zip) {
                $zip_error_info =  $this->get_zip_originalsize($zip, $img_path, $website_id, $shop_id); //处理zip文件
                if (empty($zip_error_info['right_path'])) {
                    //储存excel路径
                    return ['code' => 0, 'message' => $zip_error_info['message'] ?: 'Zip解压失败', 'data' => $file];
                }
            }
            //获取文件类型
            Vendor('PhpExcel.PHPExcel');
            $file_type = \PHPExcel_IOFactory::identify($file);
            if ($file_type) {
                $objReader = \PHPExcel_IOFactory::createReader($file_type);
//                $objReader->setReadDataOnly(true);
                $obj_PHPExcel = $objReader->load($file); //加载文件内容,编码utf-8
                $excel_array = $obj_PHPExcel->getsheet()->toArray(); //转换为数组格式
            } else {
                return ['code' => 0, 'message' => '文件格式错误，请重新上传'];
            }
            
            $i = 0; //成功数量
            $j = 0; //全部数量
//            array_shift($excel_array);//发票助手删除第一行提示标语
            $key_array = $excel_array[0];//标题
            unset($excel_array[0]);
            $excel_array = array_values($excel_array);
            // excel为空
            if (empty($excel_array)) {
                return ['code' => 0, 'message' => 'Excel文件为空', 'data' => $file];
            }
            //查询店铺发票税点值
            $condition = [
                'website_id' => $website_id,
                'shop_id' => $shop_id,
            ];
            $invoiceModel = new VslInvoiceConfigModel();
            $invoiceConfig = $invoiceModel->getInfo($condition);
            $right_arr = [];
            $error_arr = [];
            $excel_error_message = '';
            $column = 15;// 这里是根据Excel图片所在列默认值
            $img_arr = [];
            $sheetCount = count($excel_array[0]);
            $excel_img_arr = array_column($excel_array, $column);//根据Excel知晓
            $s_repeat = arraySeparateRepeat($excel_img_arr);
            foreach ($excel_array as $k => $v) {
                $j++;
                //先查询订单号是否正确
                $orderModel = new VslOrderModel();
                $orderRes = $orderModel->getInfo(['order_no' => $v[0]],'order_id');
                if (!$orderRes){
                    $excel_error_message .= $k+1 .'订单号'.$v[0].'不存在; ';
                    $error_arr[] = $v;
                    continue;
                }
                //Excel中图片名是否重复
                if (in_array($v[$column], $s_repeat['n_repeat'])) {
                    //是否在正确的zip解压图片中
                    $file_path = '';
                    if (in_array($v[$column], $zip_error_info['right']) || !$zip){//在zip正确中,去上传
                        if ($zip){//有上传zip文件
                        //记录zipt图片上传并存储到invoice表
                        $z_k = array_search($v[$column], $zip_error_info['right']);//查找zip是这张图的键key,通过key查到对应right_path
                        $file_path = $zip_error_info['right_path'][$z_k];//正确待上传的图片路径
                        //上传云
                        if ($this->upload_type == 2) {
                            $alioss = new AliOss();
                            $result = $alioss->setAliOssUplaod($file_path, $file_path);
                            if($result['code']){
                                @unlink($file_path);
                                $file_path = $result['path'];//云返回图片路径
                            } else {//记录错误
                                $excel_error_message .= $k+1 .'行上传失败; ';
                                $error_arr[] = $v;
                                continue;
                            }
                        }
                        }
                        //储到invoice表
                        $excelData = array_combine($key_array, $v);
                        //参数转义
                        $type = mb_substr($excelData['发票类型'], 0, 2) == '电子' ? 1 : 2;
                        $belong_status = $title = $excelData['发票抬头'] == '个人' ? 1 : 2;
                        $company_name = $type== 2 ? $excelData['抬头名称/公司名称'] : '';
                        $content_type =  $excelData['发票内容类型'] == '商品明细' ? 1 : 2;
                        $tax = $type == 1 ? $invoiceConfig['pt_invoice_tax'] : $invoiceConfig['zy_invoice_tax'];
                        //查询订单状态
                        
                        //查询invoice表中是否存在
                        $invoice = new VslInvoiceModel();
                        $invoice_id = $invoice->getInfo(['website_id' => $website_id, 'shop_id' => $shop_id, 'order_no' => $excelData['订单号']], 'id')['id'];
                        
                        $data = [
                            'website_id' => $website_id,
                            'shop_id' => $shop_id,
                            'config_id' => $invoiceConfig['id'],
                            'order_no' => $excelData['订单号'],
                            'content' => $excelData['发票内容'],
                            'type' => $type,
                            'user_email' => $excelData['接收邮箱'],
                            'user_tel' => $excelData['接收手机号'],
                            'tax_code' => $excelData['发票代码'], //发票代码
                            'tax_no' => $excelData['发票号码'], //发票号码
                            'title' => $title,
                            'title_name' => $excelData['抬头名称/公司名称'],
                            'taxpayer_no' => ' '.$excelData['纳税人识别号'],
                            'company_addr' => $excelData['注册地址'],
                            'mobile' => $excelData['注册电话'],
                            'bank' => $excelData['开户银行'],
                            'card_no' => ' '.$excelData['银行账户'],
                            'file_name' => ' '.$excelData['发票文件名称'],
                            'order_status' => 1,
                            'status' => 1,
                            'belong_status' => $belong_status,
                            'is_upload' => 1,
                            'company_name' => $company_name,
                            'content_type' => $content_type,
                            'tax' => $tax,
                            'is_refund' => $invoiceConfig['is_refund'],
                            'file_path' => $file_path, //图片URL
                        ];
                        //存入invoice表
                        if ($invoice_id) {//更新
//                            unset($data['order_status'],$data['status'],$data['is_upload']);
                            unset($data['order_status']);
                            if(!$zip){
                                unset($data['file_path']);//未上传zip，则默认原来的
                            }
                            $result = $invoice->save($data, ['id' => $invoice_id]);
                        } else {
                            $result = $invoice->save($data);
                        }
                        if (!$result) {
                            $excel_error_message .= $k+1 .'行发票信息存储错误; ';
                            $error_arr[] = $v;
                            continue;
                        }
                        $i++;
                    } else {//错误不上传
                        $excel_error_message .= '第'.$k+1 .'行压缩包解压失败或PDF文件错误';
                        $error_arr[] = $v;
                    }
                } else { //错误不上传
                    $excel_error_message .= $k+1 .'行图片名重复; ';
                    $error_arr[] = $v;
                }
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
                $res = dataExcel($file_name, $xlsCell, $data, $path, $suffix);
                if ($res['code'] > 0) {
                    $error_new_path = $res['data'];
                }
            }
            
            unset($res);
            if ($i == 0) {
                $res['code'] = 0;
                $res['message'] = $excel_error_message;
                Db::rollback();
            } else if ($i == $j ) {
                $res['code'] = 1;
                $res['message'] = '导入总数目：' . $j . ',导入成功:'.$i;
                Db::commit();
            } else {
                $res['code'] = 2;
                $res['message'] = '导入总数目：' . $j . ',导入成功数目：' . $i.'失败原因：'.$excel_error_message;
                $res['data'] = $error_new_path;
                Db::rollback();
            }
            @unlink($file);
            @unlink($zip);
            return $res;
        } catch (\Exception $e) {
            Db::rollback();
            $msg = $e->getMessage();
            $log_dir = getcwd() . '/invoice_import.log';
            file_put_contents($log_dir, date('Y-m-d H:i:s') . $msg . PHP_EOL, 8);
            return ['code' => 0, 'message' => $e->getMessage()];
        }
    }
    /**
     * 处理zip文件，解压$filename文件到$path
     * @param $filename string [zip文件路径]
     * @param $path string [解压后存储的路径]
     * @return bool
     */
    public function get_zip_originalsize($filename, $path, $website_id = 0, $shop_id = 0) {

        $record_info = [
            'error' => [],
            'error_path' => [],
            'right' => [],
            'right_path' => []
        ];//用于记录错误信息， error:解压后错误图片名 right:解压并上传正确图片名（用与excel对应储存）
        if (!file_exists($filename)) {
            $record_info[ 'message'] = 'zip已被删除，请重新上传';
            return $record_info;
        }

        if (!is_dir($path)) {
            $mode = intval('0777', 8);
            mkdir($path, $mode, true);
            chmod($path, $mode);
        }
        $path = iconv('utf-8', 'gb2312', $path);
        $resource = zip_open($filename);
        if (!$resource) {
            $record_info[ 'message'] = 'zip打开失败';
            return $record_info;
        }
        $i = 1;
        while ($img = zip_read($resource)) {
            if (!$img){
                $record_info[ 'message'] = 'zip读取失败';
                return $record_info;
            }
            if (!zip_entry_open($resource, $img)) {
                break;
            }
            $img_i = zip_entry_name($img);//文件名

            if (strpos($img_i, '/')) {
                $img_i = substr($img_i, strripos($img_i, '/')+1);
            }
            $img_name = substr($img_i, 0, strlen($img_i) - 4);
            $img_name = str_replace(' ', '', $img_name);
            $file_name = $path . getFileNameOfUrl($filename) .'/' . $img_i;
            $file_name = str_replace(' ', '', $file_name);
            $file_path = substr($file_name, 0, strrpos($file_name, '/'));

            $i++;
            if (!is_dir($file_path)) {
                $mode = intval('0777', 8);
                mkdir($file_path, $mode, true);
                chmod($file_path, $mode);
            }

            if (!is_dir($file_name)) {
                $file_size = zip_entry_filesize($img);
                if ($file_size < (1024 * 1024 * 10)) {
                    $file_content = zip_entry_read($img, $file_size);
                    $ext = strrchr($file_name, '.');
                    if (!in_array($ext, ['.pdf'])) {
                        array_push($record_info['error'], $img_name);//记录错误图片名
                        array_push($record_info['error_path'], $file_name);//记录错误图片名地址
                        continue;
                    }

                    $file_res = file_put_contents($file_name, $file_content);
                    if (!$file_res) {
                        array_push($record_info['error'], $img_name);//记录错误图片名
                        array_push($record_info['error_path'], $file_name);//记录错误图片名地址
                        continue;
                    }

                    array_push($record_info['right'], $img_name);//记录上传云的正确图片名
                    array_push($record_info['right_path'], $file_name);//记录上传云的正确图片名地址
                }else{
                    array_push($record_info['error'], $img_name);//记录错误图片名
                    array_push($record_info['error_path'], $file_name);//记录错误图片名地址
                }
            }
            zip_entry_close($img);
        }
        zip_close($resource);
        return $record_info;
    }
    /**
     * 修改发票批量发布列表数据
     * @param $data [0未执行，1成功 2失败 3等待中 4处理中 5部分完成]
     * @param $condition
     * @return mixed
     */
    public function updateInvoiceFileInfo($data, $condition)
    {
        $invoiceFile = new VslInvoiceFileModel();
        return $invoiceFile->save($data, $condition);
    }
    /**
     * 解析发票内容中的格式
     * 因为存的是 （体育 > 篮球  500¥__体育 > 足球  300¥ ） 所以需要通过拆解 '双下划线'
     * @param $content string [发票商品分类内容]
     * @param int $length int [需要返回几个商品信息（因为一个发票内容存在多个商品信息）]
     * @return mixed|string
     */
    public function changeInvoiceContentByCategory($content, $length = 0)
    {
        if ($length > 0) {
            $category_arr = explode('__', rtrim($content, '__'));
            $content = '';
            for ($i=0; $i< count($category_arr); $i++) {
                $content .= $category_arr[$i] .', ';
            }
            $content = rtrim($content, ', ');
        }
        $content = str_replace('__', "<br/>", $content);

        return $content;
    }

    /**
     * 订单号 - 修改发票数据
     * @param $order_no
     * @param $data
     * @return false|int
     */
    public function updateInvoiceInfoByOrderNo($order_no, $data)
    {
        $invoice = new VslInvoiceModel();
        return $invoice->save($data, ['order_no' => $order_no]);
    }

    /**
     * 修改发票状态 - 订单id
     * @param $order_id string [订单id]
     * @param int $status int [修改状态]
     * @return false|int|void
     */
    public function updateOrderStatusByOrderId($order_id, $status = 2)
    {
        $condition = [
            'order_id' => $order_id,
            'invoice_tax' => ['>', 0]
        ];
        $order = new VslOrderModel();
        $order_no = $order->getInfo($condition, 'order_no')['order_no'];
        if (!$order_no) {
            return;
        }
        $invoiceModel = new VslInvoiceModel();
        return $invoiceModel->save(['order_status' => $status], [
            'website_id' => $this->website_id,
            'order_no' => $order_no
        ]);
    }

    /**
     * 通过订单交易号且检测发票配置比例是否改变，改变即修改税费
     * @param $out_trade_no
     * @throws Exception\DbException
     */
    public function checkInvoiceConfChangeByOutTradeNo($out_trade_no)
    {
        $order_condition = [
            'out_trade_no' => $out_trade_no,
        ];
        $orderModel = new VslOrderModel();
        $orders = $orderModel->getQuery($order_condition, 'order_id,website_id,shop_id,order_id,order_no,order_status,invoice_tax,invoice_type,shop_order_money', 'order_id asc');
//        if (!$orders) {return;}
        foreach($orders as $key => $order) {
            if ($order['order_status'] != 0) {continue;}//不是未支付状态
            if ($order['invoice_type'] == 0) {continue;}
            // 查询发票税费设置
            $config_condition = [
                'website_id' => $order['website_id'],
                'shop_id' => $order['shop_id'],
                'status' => 1
            ];
            $invoiceCof = $this->getInvoiceConfig($config_condition);
            if ($order['invoice_type'] == 1) {
                $tax_ratio = $invoiceCof['pt_invoice_tax'];
            } else {
                $tax_ratio = $invoiceCof['zy_invoice_tax'];
            }
            $tax = ($order['shop_order_money'] * $tax_ratio/ 100);
            Db::startTrans();
            try{
                if ( $tax != $order['invoice_tax']) {
                    //修改订单税费价格
                    unset($order_condition);
                    $order_condition = [
                        'order_id' => $order['order_id']
                    ];
                    $orderModel = new VslOrderModel();
                    $orderModel->save(['invoice_tax' => $tax], $order_condition);
                    $orderGoodsModel = new VslOrderGoodsModel();
                    $order_goods_condition = [
                        'order_id' => $order['order_id']
                    ];
                    //先查出总商品数，再均分
                    $order_goods = $orderGoodsModel->getQuery($order_goods_condition, 'num,invoice_tax,order_goods_id', 'order_goods_id asc');
                    $nums = $orderGoodsModel->getSum($order_goods_condition, 'num');
                    $count =count($order_goods);//有几条数据
                    $temp_tax = 0;
                    foreach ($order_goods as $good) {
                        $count--;
                        if ($count == 0) {
                            $good_tax = $tax - $temp_tax;
                        }
                        $good_tax = roundLengthNumber($good['num']/$nums, 2,false) * $tax;
                        $temp_tax += $good_tax;
                        // 存入order_goods表
                        $orderGoodsModel->save(['invoice_tax' => $good_tax], ['order_goods_id' => $good['order_goods_id'], 'order_id' => $order['order_id']]);
                    }
                }
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
            }
        }
        unset($order);
    }

    /**
     * 修改商品订单价格，更改税费
     * @param $order_id
     * @throws Exception\DbException
     */
    public function changeInvoiceTaxByOrderId($order_id)
    {
        $order_condition = [
            'order_id' => $order_id,
        ];
        $orderModel = new VslOrderModel();
        $orders = $orderModel->getQuery($order_condition, 'order_id,website_id,shop_id,order_id,order_no,order_status,invoice_tax,invoice_type,order_money,pay_money', 'order_id asc');
        foreach($orders as $key => $order) {
            if ($order['order_status'] != 0) {continue;}//不是未支付状态
            if ($order['invoice_type'] == 0) {continue;}

            // 查询发票税费设置
            $config_condition = [
                'website_id' => $order['website_id'],
                'shop_id' => $order['shop_id'],
                'status' => 1
            ];
            $invoiceCof = $this->getInvoiceConfig($config_condition);
            if ($order['invoice_type'] == 1) {
                $tax_ratio = $invoiceCof['pt_invoice_tax'];
            } else {
                $tax_ratio = $invoiceCof['zy_invoice_tax'];
            }
            $tax = (($order['order_money']) * $tax_ratio/ 100);
            //修改 订单价格，商品价格
            Db::startTrans();
            try {
                $orderModel = new VslOrderModel();
                $order_condition = [
                    'order_id' => $order['order_id']
                ];
                $order_data = [
                    'invoice_tax' => $tax,
//                    'order_money' => $order['order_money'] + $tax,
//                    'pay_money' => $order['pay_money'] + $tax,
                ];
                $orderModel->save($order_data, $order_condition);
                //订单商品
                $orderGoodsModel = new VslOrderGoodsModel();
                $order_goods_condition = [
                    'order_id' => $order['order_id']
                ];
                //先查出总商品数，再均分
                $order_goods = $orderGoodsModel->getQuery($order_goods_condition, 'num,invoice_tax,order_goods_id', 'order_goods_id asc');
                $nums = $orderGoodsModel->getSum($order_goods_condition, 'num');
                $count =count($order_goods);//有几条数据
                $temp_tax = 0;
                foreach ($order_goods as $good) {
                    $count--;
                    if ($count == 0) {
                        $good_tax = $tax - $temp_tax;
                    }
                    $good_tax = roundLengthNumber($good['num']/$nums, 2,false) * $tax;
                    $temp_tax += $good_tax;
                    // 存入order_goods表
                    $orderGoodsModel->save([
                        'invoice_tax' => $good_tax,
//                        'actual_price' => $good_tax,
                    ], ['order_goods_id' => $good['order_goods_id'], 'order_id' => $order['order_id']]);
                }
                Db::commit();
            }catch (\Exception $e) {
                Db::rollback();
            }
        }
        unset($order);

    }

    /**
     * 拒绝开票 - 重置微信开票授权链接
     * @param $order_no string [订单号]
     * @return array|void
     */
    public function rejectInsert($order_no, $reason = '')
    {
        if (empty($order_no)) {
            return;
        }
        $invoiceModel = new VslInvoiceModel();
        $condition = [
            'website_id' => $this->website_id,
            'order_no' => $order_no
        ];
        debugLog($condition, '发票-重新开票1=> ');
        $invoiceInfo = $invoiceModel->getInfo($condition);
        $wchat = new WchatOauth($invoiceInfo['website_id']);
        $order_id = $invoiceInfo['order_id'] ?: $order_no . time();
        $new_order_id = $order_no.time();
        $order = new VslOrderModel();
        $orderId = $order->getInfo(['order_no' => $order_no], 'order_id')['order_id'];
        $url = 'wap/order/detail/'.$orderId;
        $reason = $reason ?: '插卡失败';
        $data = [
            's_pappid' => $invoiceInfo['s_pappid'],
            'order_id' => $order_id,
            'reason' => $reason,
            'url' => __URL($url),
        ];
        debugLog($data, '发票-重新开票2=> ');
        $return = $wchat->rejectInsert($data);
        debugLog($return, '发票-重新开票3=> ');
        if ($return['errcode']) {
            $insert_data = [
                'is_upload' => 4,
                'card_status' => 9,
                'card_error' => json_encode(AjaxWXReturn($return['errcode'], [], $return['errmsg'])),
                'order_id' => $new_order_id
            ];
            $invoiceModel->isUpdate()->save($insert_data, $condition);
            return ['code' => -1, 'message' => $return['errmsg']];
        }
        $insert_data = [
            'is_upload' => 1,
            'card_status' => 9,
            'card_error' => json_encode(AjaxWXReturn($return['errcode'], [], $return['errmsg'])),
            'order_id' => $new_order_id
        ];
        debugLog($insert_data, '发票-重新开票4=> ');
        $invoiceModel->isUpdate()->save($insert_data, $condition);
    }

    /**
     * 处理确认订单商品规格价格，商品规格的实际价格(未使用积分抵扣-待优化)
     * @param $shop_info
     * @param $shop_total_tax [店铺总税费]
     * @return array [处理的商品规格，抵扣掉的总总金额]
     */
    public function invoiceReturnOrderGoodsSku($shop_info,$shop_total_tax)
    {
        $sku_info = $shop_info['sku_info'];
        //先计算优惠的价格满减 > 优惠券 > 积分抵扣
        $shop_goods_total_price = 0;
        foreach ($sku_info as &$sku){
            $temp_deduct_money = 0;
            //如果积分抵扣存在，则积分抵扣已经计算好了规格商品的实际价格
            if (isset($sku['is_deduction']) && $sku['is_deduction'] == 1){
                $sku['real_money'] -= $sku['shipping_fee'];
                $sku['real_money'] = roundLengthNumber($sku['real_money']/$sku['num'],2,false);
            }else{
                if($sku['full_cut_sku_amount']){
                    $temp_deduct_money += $sku['full_cut_sku_percent_amount'];
                }
                if($sku['coupon_id']){
                    $temp_deduct_money += $sku['coupon_sku_percent_amount'];
                }
                $sku['real_money'] = roundLengthNumber(($sku['discount_price']*$sku['num'] - $temp_deduct_money)/$sku['num'],2,false);//规格价-满减-优惠券
            }
            $sku['real_money'] = $sku['real_money'] > 0? $sku['real_money']: 0;
            $shop_goods_total_price += $sku['real_money']*$sku['num'];
        }
        //获取店铺商品原总价
//        $shop_goods_total_price = array_sum(array_column($sku_info, 'real_money'));
        $give_tax = 0;
        //满减 > 优惠券
        foreach ($sku_info as &$sku){
            if ($sku == end($sku_info)){
                //最后一个
                $sku_tax = $shop_total_tax - $give_tax;
            }else{
                $sku_tax = roundLengthNumber(($sku['real_money']*$sku['num']/$shop_goods_total_price)*$shop_total_tax,2,false);
                $give_tax += $sku_tax;
            }
            $sku['invoice_tax'] = $sku['tax'] = $sku_tax;
            $sku['real_money'] = $sku['real_money']*$sku['num'] + $sku['tax'];//real_money加上税费
            $sku['real_money'] = $sku['real_money']>0 ?$sku['real_money']:0;
        }
        
        return $sku_info;
    }
    /**
     * 处理确认订单商品规格价格，商品规格的实际价格(未使用积分抵扣-待优化)
     * @param $shop_info
     * @param $shop_total_tax [店铺总税费]
     * @return array [处理的商品规格，抵扣掉的总总金额]
     */
    public function invoiceReturnOrderGoodsSkuNew($sku_info,$shop_total_tax)
    {
//        $sku_info = $shop_info['goods_list'];
        //先计算优惠的价格满减 > 优惠券 > 积分抵扣
        $shop_goods_total_price = 0;
        foreach ($sku_info as &$sku){
            $temp_deduct_money = 0;
            if (!$sku['is_deduction'] && !$sku['receive_order_goods_data']){
                if($sku['full_cut_sku_amount']){
                    $temp_deduct_money += $sku['full_cut_sku_percent_amount'];
                }
                if($sku['coupon_id']){
                    $temp_deduct_money += $sku['coupon_sku_percent_amount'];
                }
                $sku['real_money'] = roundLengthNumber(($sku['discount_price']*$sku['num'] - $temp_deduct_money)/$sku['num'],2,false);//规格价-满减-优惠券
            }
            //如果积分抵扣存在，则积分抵扣已经计算好了规格商品的实际价格
            //    if (isset($sku['is_deduction']) && $sku['is_deduction'] == 1){
            //        $sku['real_money'] -= $sku['shipping_fee'];
            //        $sku['real_money'] = round($sku['real_money']/$sku['num'],2);
            //    }else{
            //        if($sku['full_cut_sku_amount']){
            //            $temp_deduct_money += $sku['full_cut_sku_percent_amount'];
            //        }
            //        if($sku['coupon_id']){
            //            $temp_deduct_money += $sku['coupon_sku_percent_amount'];
            //        }
            //        $sku['real_money'] = round(($sku['discount_price']*$sku['num'] - $temp_deduct_money)/$sku['num'],2);//规格价-满减-优惠券
            //    }
            $sku['real_money'] = $sku['real_money'] > 0? $sku['real_money']: 0;
            $shop_goods_total_price += $sku['real_money']*$sku['num'];
        }
        //获取店铺商品原总价
        //$shop_goods_total_price = array_sum(array_column($sku_info, 'real_money'));
        $give_tax = 0;
        
        //满减 > 优惠券
        foreach ($sku_info as &$sku){
            $all_real_money = isset($sku['all_real_money']) ? $sku['all_real_money'] : $sku['real_money']*$sku['num'];
            if ($sku == end($sku_info)){
                //最后一个
                $sku_tax = $shop_total_tax - $give_tax;
            }else{
                $sku_tax = roundLengthNumber(($all_real_money/$shop_goods_total_price)*$shop_total_tax,2,false);
                $give_tax += $sku_tax;
            }
            $sku['invoice_tax'] = $sku['tax'] = $sku_tax;
            $sku['real_money'] = $all_real_money + $sku['tax'];//real_money加上税费
            $sku['real_money'] = $sku['real_money']>0 ?$sku['real_money']:0;
        }
        //处理real_money
        foreach ($sku_info as $k => $v){
            $check_money = $v['real_money'];
            $sku_info[$k]['all_real_money'] = $v['real_money'];
            $sku_info[$k]['real_money'] = roundLengthNumber($v['real_money']/$v['num'],2,false);
            if($sku_info[$k]['real_money'] * $v['num'] != $check_money){
                $remainder = bcsub($check_money,$sku_info[$k]['real_money'] * $v['num'],2);
                $sku_info[$k]['remainder'] = $remainder;
            }else{
                $sku_info[$k]['remainder'] = 0;
            }
        }
        
        return $sku_info;
    }
    /**
     * 处理确认订单商品规格价格 (预售)
     */
    public function invoiceReturnPresellOrderGoodsSkuNew($shop_data)
    {
        $shop_data['presell_info']['final_real_money'] = bcadd($shop_data['presell_info']['final_real_money'],$shop_data['invoice_tax'],2);
        $shop_data['goods_list'][0]['real_money'] = bcadd($shop_data['goods_list'][0]['real_money'],roundLengthNumber($shop_data['invoice_tax']/$shop_data['goods_list'][0]['num'],2,false),2);
        $shop_data['goods_list'][0]['invoice_tax'] = roundLengthNumber($shop_data['invoice_tax']/$shop_data['goods_list'][0]['num'],2,false);
        return $shop_data;
    }
    
    /**
     * 确认订单 税费计算
     * @param $invoice_list
     * @param $shop_data
     */
    public function invoiceReturnOrderGoodsSkuForMany ($invoice_list,$shop_data)
    {
        $goods_list = $shop_data['goods_list'];
        //税费计算不计算运费
        $shop_id=$shop_data['shop_id'];
        $total_amount = $shop_data['total_amount'] - $shop_data['shipping_fee'] >=0 ? $shop_data['total_amount'] - $shop_data['shipping_fee']:0;
        $tax_result = $this->calculateShopInvoiceTax($shop_id, $total_amount);
        $tax_result = empty($tax_result) ? objToArr([]) : $tax_result;
        # 税费计算
        $total_tax = 0;
        if ($invoice_list[0]['tax_type'] >0) {
            foreach ($invoice_list as $invoice) {
                if ($invoice['shop_id'] == $shop_id){
                    //立即购买商品数量
                    $tax = 0;
                    if ($invoice['tax_type'] == 1) {
                        $tax = $tax_result['pt'];
                    }
                    if ($invoice['tax_type'] == 2) {
                        $tax = $tax_result['zy'];
                    }
                    $total_tax += $tax;
                }
            }
            $invoiceRes = $this->invoiceReturnOrderGoodsSkuNew($goods_list,$total_tax);
            $shop_data['goods_list'] = $invoiceRes;
            $shop_data['total_amount'] = $shop_data['total_amount'] + $total_tax;
            $shop_data['tax_fee'] = $tax_result;
            $shop_data['total_tax'] = $total_tax;
        }
        return $shop_data;
    }
    
    /**
     * 处理确认预售订单（预售） 【多码】
     * @param $sku_info
     * @return array [处理的商品规格，抵扣掉的总总金额]
     */
    public function invoiceReturnPresellOrderGoodsSkuForMany($invoice_list,$shop_data)
    {
        //先处理成单个商品金额
        $shop_id = $shop_data['shop_id'];
        $final_real_money = $shop_data['presell_info']['final_real_money'] - $shop_data['presell_info']['shipping_fee'];//先除去运费
        $total_amount = $shop_data['presell_info']['firstmoney'] + $final_real_money;
        $tax_result = $this->calculateShopInvoiceTax($shop_id, $total_amount);
        $tax_result = empty($tax_result) ? objToArr([]) : $tax_result;
    
        # 税费计算
        $total_tax = 0;
        if ($invoice_list[0]['tax_type'] >0) {
            foreach ($invoice_list as $invoice) {
                if ($invoice['shop_id'] == $shop_id){
                    //立即购买商品数量
                    $tax = 0;
                    if ($invoice['tax_type'] == 1) {
                        $tax = $tax_result['pt'];
                    }
                    if ($invoice['tax_type'] == 2) {
                        $tax = $tax_result['zy'];
                    }
                }
                $total_tax += $tax;
            }
            $shop_data['presell_info']['final_real_money'] += $total_tax;
            $shop_data['tax_fee'] = $tax_result;
            $shop_data['total_tax'] = $total_tax;
        }
        return $shop_data;
    }
}
