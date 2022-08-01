<?php

namespace app\supplier\controller;

use addons\shop\service\Shop;
use addons\supplier\server\Supplier as supplierServer;
use data\model\VslBankModel;
use data\service\Member;
use data\service\Order as OrderSer;
use addons\distribution\service\Distributor as  DistributorService;

/**
 * 账户控制器
 */
class Financial extends BaseController {
    public static $port_name = '供应商';

    public function __construct() {

        parent::__construct();
    }

//    /**
//     * 每日账户收益 //TODO... 供应商没有
//     *
//     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:>|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
//     */
//    public function getShopAccountMonthRecored() {
//        $shop = new Shop();
//        $shop_account_month_recored = $shop->getShopAccountMonthRecored($this->instance_id);
//        return $shop_account_month_recored;
//    }

    /**
     * 账户列表
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:>|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function supplierAccountList() {
        //TODO 供应商财务
        if (request()->isAjax()) {
            $pageindex = isset($_POST['page_index']) ? $_POST['page_index'] : 1;
//            $condition['shop_id'] = $this->instance_id;
            $condition['supplier_id'] = $this->supplier_id;
            $condition['website_id'] = $this->website_id;
//            $shop = new Shop();
            $suppilerSer = new supplierServer();
            $list = $suppilerSer->getSupplierBankAccountAll($pageindex, PAGESIZE, $condition, 'is_default desc');
            return $list;
        } else {
            return view($this->style . "Financial/reflectAccount");
        }
    }

    /**
     * 添加银行账户
     */
    public function addSupplierAccount() {
        //TODO 供应商财务
        if (request()->isAjax()) {
            $type = request()->post('type', 1);
            $realname = request()->post('realname', '');
            $account_number = request()->post('account_number', '');
            $remark = request()->post('remark', '');
            $bank_name = request()->post('bank_name', '');
            $bank_type = request()->post('bank_type', '');
            $bank_card = request()->post('bank_card', '');
            $suppilerSer = new supplierServer();
            $retval = $suppilerSer->addSupplierBankAccount($this->supplier_id, $type, $realname, $account_number, $remark,$bank_name,$bank_type,$bank_card);
            return ajaxReturn($retval);
        } else {
    
            $this->assign('bank_type',1);
            $this->assign('wx_type',2);
            $this->assign('ali_type',3);
            $bank = new VslBankModel();
            $bank_list = $bank->getQuery([],'*','');
            $this->assign('bank_list',$bank_list);
            return view($this->style . 'Financial/addReflectAccount');
        }
    }

    /**
     * 修改银行账户
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function updateSupplierAccount() {
        //TODO 供应商财务
        $suppilerSer = new supplierServer();
        if (request()->isAjax()) {
            $post = request()->post();
            $id = $post['id'];
            $type = $post['type'];
            $realname = $post['realname'];
            $account_number = $post['account_number'];
            $remark = $post['remark'];
            $bank_name = $post['bank_name'];
            $bank_type = $post['bank_type'];
            $bank_card = $post['bank_card'];
            $retval = $suppilerSer->updateSupplierBankAccount($this->supplier_id, $type, $realname, $account_number, $remark, $bank_name,$bank_type,$bank_card,$id);
            return ajaxReturn($retval);
        } else {
            $id = request()->get('id');
            $info = $suppilerSer->getSupplierBankAccountDetail($this->supplier_id, $id);
            $this->assign('info', $info);
    
            $this->assign('bank_type',1);
            $this->assign('wx_type',2);
            $this->assign('ali_type',3);
            $bank = new VslBankModel();
            $bank_list = $bank->getQuery([],'*','');
            $this->assign('bank_list',$bank_list);
            return view($this->style . 'Financial/updateReflectAccount');
        }
    }

    /**
     * 删除账户
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function deleteAccount() {
        //TODO 供应商财务
        $id = request()->post('id');
        if (!$id){
            return AjaxReturn(LACK_OF_PARAMETER);
        }
        $condition = [
            'website_id' => $this->website_id,
            'supplier_id' => $this->instance_id,
            'id' => $id
        ];
        
        $suppilerSer = new supplierServer();
        $retval = $suppilerSer->deleteSupplierBankAccouht($condition);
        return ajaxReturn($retval);
    }
    
    /**
     * 选择账户
     */
    public function selectMemberList() {
        if(request()->isPost()){
            $post = request()->post();
            $page_index = $post['page_index']?:1;
            $search_text = $post['search_text'];
    
            $condition = [
                'website_id'    => $this->website_id,
                'is_member'     => 1,
                'wx_openid'     => ['neq', ' '],
            ];
            if ($search_text) {
                $condition['user_tel | real_name'] = $search_text;
            }
            $shop = new Member();
            $retval = $shop->getUserLists($page_index,PAGESIZE, $condition, '');
            return $retval;
        }else{
            return view($this->style . "Financial/selectMember");
        }

    }
    /**
     * 供应商申请提现
     *
     * @return Ambigous <multitype:unknown, multitype:unknown unknown string >
     */
    public function applySupplierAccountWithdraw() {
        //TODO 供应商财务
        $suppilerSer = new supplierServer();
        if (request()->isAjax()) {
            $post = request()->post();
            $cash = $post["cash"];//提现金额
            if($cash <= 0){
                return AjaxReturn(FAIL,[],'提现金额必须大于等0');
            }
            $bank_account_id = $post["bank_account_id"];
            $retval = $suppilerSer->applySupplierAccountWithdraw($this->supplier_id, $bank_account_id, $cash);
            return AjaxReturn(SUCCESS);
        } else {
            $condition['supplier_id'] = $this->supplier_id;
            $list = $suppilerSer->getSupplierBankAccountAll(1, 0, $condition);
            $list = $list['data'];
            $supplier_account_info = $suppilerSer->getSupplierAccount($this->supplier_id);
            $this->assign('bank_type',1);
            $this->assign('wx_type',2);
            $this->assign('ali_type',3);
            $this->assign("supplier_account_info", $supplier_account_info);
            $this->assign("bank_list", $list);
            return view($this->style . "Financial/reflect");
        }
    }
    /**
     * 供应商账务明细
     *
     * @return Ambigous <multitype:number unknown , unknown>|Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function supplierAccountRecordsCount() {
        if (request()->isAjax()) {
            $pageindex = isset($_POST['page_index']) ? $_POST['page_index'] : 1;
            $condition['supplier_id'] = $this->supplier_id;
            $condition['website_id'] = $this->website_id;
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : '2020-9-15';
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : '2030-1-1';
            if ($start_date != "") {
                $condition["create_time"][] = [
                    ">",
                    getTimeTurnTimeStamp($start_date)
                ];
            }
            if ($end_date != "") {
                $condition["create_time"][] = [
                    "<",
                    getTimeTurnTimeStamp($end_date)
                ];
            }
            $supplier = new supplierServer();
            $list = $supplier->getSupplierAccountRecords($pageindex, PAGESIZE, $condition, 'create_time desc');
            return [
                "list" => $list,
            ];
        }
    }

    /**
     * 供应商销售订单
     *
     * @return multitype:unknown Ambigous <multitype:number unknown , unknown> |Ambigous <\think\response\View, \think\response\$this, \think\response\View>
     */
    public function supplierOrderAccountList() {
        if (request()->isAjax()) {
            $pageindex = isset($_POST['page_index']) ? $_POST['page_index'] : 1;
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : '';
            $condition = array();
            if ($start_date != "") {
                $condition["create_time"][] = [
                    ">",
                    getTimeTurnTimeStamp($start_date)
                ];
            }
            if ($end_date != "") {
                $condition["create_time"][] = [
                    "<",
                    getTimeTurnTimeStamp($end_date)
                ];
            }
            $sql1 = "instr(CONCAT( ',', nm.supplier_id, ',' ), '," . $this->supplier_id . ",' ) ";
            $condition[] = ['exp',$sql1];
            $order = new OrderSer();
            $list = $order->getOrderList($pageindex, PAGESIZE, $condition, 'create_time desc');
            return [
                "list" => $list
            ];
        }
    }

    /**
     * 供应商收入
     */
    public function supplierAccount() {

        $supplier = new supplierServer();
        $supplier_account_info = $supplier->getSupplierAccount($this->supplier_id);
        $this->assign("supplier_account_info", $supplier_account_info);
        return view($this->style . "Financial/supplierIncome");
    }

    /**
     * 供应商提现列表
     */
    public function supplierAccountWithdrawList() {
        if (request()->isAjax()) {
            $page_index = isset($_POST['page_index']) ? $_POST['page_index'] : 1;
            $start_date = !empty($_POST['start_date']) ? $_POST['start_date'] : '';
            $end_date = !empty($_POST['end_date']) ? $_POST['end_date'] : '';
            $condition['sp.supplier_id'] = $this->supplier_id;
            if ($start_date || $end_date) {
                $condition["ask_for_date"] = [
                    [
                        ">",
                        getTimeTurnTimeStamp($start_date)
                    ],
                    [
                        "<",
                        getTimeTurnTimeStamp($end_date)
                    ]
                ];
            }
            $suppilerSer = new supplierServer();
            $list = $suppilerSer->getSupplierAccountWithdrawList($page_index, PAGESIZE, $condition, '', 'ask_for_date desc');
            return $list;
        }
    }

    /**
     * 店铺提现详情
     *
     * 
     */
    public function supplierAccountWithdrawDetail() {
        $id = request()->post('id', 0);
        $suppilerSer = new supplierServer();
        $retval = $suppilerSer->supplierAccountWithdrawDetail($id);
        return $retval;
    }
}
