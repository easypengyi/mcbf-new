<?php
namespace app\platform\controller;

use addons\coupontype\model\VslCouponModel;
use addons\coupontype\server\Coupon as CouponServer;
use addons\giftvoucher\model\VslGiftVoucherRecordsModel;
use addons\giftvoucher\server\GiftVoucher as VoucherServer;
use addons\store\model\VslStoreModel;
use data\model\VslMemberCheckLogModel;
use data\model\VslMemberModel;
use data\service\ExcelsExport;
use data\service\Member as MemberService;
use data\service\Config;

/**
 * 会员管理
 *
 * @author  www.vslai.com
 *
 */
class User extends BaseController
{

    public function __construct()
    {
        parent::__construct();

    }

    public function coupons()
    {
        if (request()->isAjax()) {
            $promotion = new CouponServer();
            $member_id = request()->post('member_id', '');
            $search_text = request()->post('search_text', '');
            $page_index = request()->post('page_index', 1);
            $page_size = request()->post('page_size', PAGESIZE);
            $fields = 'nc.coupon_id,nc.coupon_code,nc.money,nc.discount,nc.use_time,nc.fetch_time,nc.state,nc.get_type,nc.uid,
            su.user_tel,su.nick_name,su.user_name,no.order_no,no.shop_id,ns.shop_name,nc.send_uid';

            $where['nc.website_id'] = $this->website_id;
            $where['nc.shop_id'] = $this->instance_id;

            if(!empty($member_id)){
                $where['nc.uid'] = $member_id;
            }
            $condition = [];
            if(!empty($search_text)){
                $where['su.nick_name|su.user_tel|su.user_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }
//            if ($search_text) {
//                $condition['nc.coupon_code'] = ['LIKE', '%' . $search_text . '%', 'or'];
//                $condition['ns.shop_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
//                $condition['su.nick_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
//                $condition['nct.coupon_name'] = ['LIKE', '%' . $search_text . '%', 'or'];
//            }

            $list = $promotion->getCouponHistory($page_index, $page_size, $condition, $where, $fields, 'nc.coupon_id desc');
            foreach ($list['data'] as &$item){
                if ($item['user_name']) {
                    $item['name'] = $item['user_name'];
                } elseif ($item['nick_name']) {
                    $item['name'] = $item['nick_name'];
                } elseif ($item['user_tel']) {
                    $item['name'] = $item['user_tel'];
                }

                $item['fetch_time'] = date('Y-m-d H:i:s', $item['fetch_time']);
                if($item['use_time']){
                    $item['use_time'] = date('Y-m-d H:i:s', $item['use_time']);
                }else{
                    $item['use_time'] = '';
                }
                //礼品券状态 -1冻结 1已领用（未使用） 2已使用 3已过期
                $state_name = '未使用';
                if($item['state'] == 2){
                    $state_name = '已使用';
                }
                $item['state_name'] = $state_name;
                $type_name = VslCouponModel::types($item['get_type']);
                //是否是别人转赠
                if(!empty($item['send_uid'])){
                    $type_name .= '(来自：'.$item['send_uid'].' 转赠)';
                }
                $item['type_name'] = $type_name;
                $item['order_no'] = empty($item['order_no']) ? '':$item['order_no'];
                $item['shop_name'] = $item['shop_id'] == 0 ? '自营店':$item['shop_name'];
            }

            return $list;
        }else {
            return view($this->style . 'User/coupons');
        }
    }

    /**
     * 优惠劵列表
     */
    public function gifts()
    {
        if (request()->isAjax()) {
            $page_index = request()->post('page_index',1);
            $page_size = request()->post('page_size',PAGESIZE);
            $member_id = request()->post('member_id', '');
            $search_text = request()->post('search_text', '');
            if(!empty($member_id)){
                $where['vgvr.uid'] = $member_id;
            }
            if(!empty($search_text)){
                $where['su.nick_name|su.user_tel|su.user_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }

            $where['vgvr.website_id'] = $this->website_id;
            $VoucherServer = new VoucherServer();
            $fields = 'vgvr.*,su.user_tel,su.nick_name,su.user_name,vs.shop_name,vpg.gift_name,vpg.price';
            $list = $VoucherServer->getGiftVoucherHistory($page_index, $page_size, $where, $fields,'vgvr.record_id desc');
            if(count($list['data']) == 0){
                return $list;
            }
            $store_ids = [];
            foreach ($list['data'] as $i){
                $store_ids[$i['store_id']] = $i['store_id'];
            }

            $store_list = VslStoreModel::where('store_id', 'in', $store_ids)->column('store_name', 'store_id');
            foreach ($list['data'] as &$item){
                $item['shop_id'] = $item['store_id'];
                $item['shop_name'] = isset($store_list[$item['store_id']]) ? $store_list[$item['store_id']] : '';
                if ($item['user_name']) {
                    $item['name'] = $item['user_name'];
                } elseif ($item['nick_name']) {
                    $item['name'] = $item['nick_name'];
                } elseif ($item['user_tel']) {
                    $item['name'] = $item['user_tel'];
                }

                $item['fetch_time'] = date('Y-m-d H:i:s', $item['fetch_time']);
                if($item['use_time']){
                    $item['use_time'] = date('Y-m-d H:i:s', $item['use_time']);
                }else{
                    $item['use_time'] = '';
                }
                //礼品券状态 -1冻结 1已领用（未使用） 2已使用 3已过期
                $state_name = '未使用';
                if($item['state'] == 2){
                    $state_name = '已使用';
                }
                $item['state_name'] = $state_name;
                $type_name = VslGiftVoucherRecordsModel::types($item['get_type']);
                //是否是别人转赠
                if(!empty($item['send_uid'])){
                    $type_name .= '(来自：'.$item['send_uid'].' 转赠)';
                }
                $item['type_name'] = $type_name;

            }
            return $list;
        } else {
            return view($this->style . 'User/gifts');
        }
    }

    /**
     * 用户数据excel导出
     */
    public function couponDataExcel()
    {
        $xlsName = "优惠劵列表";
        $xlsCell = [
            0=>['coupon_id','ID'],
            1=>['uid','用户ID'],
            2=>['name','会员信息'],
            3=>['coupon_code','优惠券号码'],
            4=>['money','优惠金额'],
            5=>['state_name','状态'],
            6=>['type_name','来源'],
            7=>['fetch_time','领取时间'],
            8=>['use_time','使用时间'],
            9=>['order_no','订单号'],
            10=>['shop_name','使用店铺']
        ];

        $member_id = request()->get('member_id', '');
        $search_text = request()->get('search_text', '');

        if(!empty($member_id)){
            $where['vgvr.uid'] = $member_id;
        }
        if(!empty($search_text)){
            $where['su.nick_name|su.user_tel|su.user_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $where['su.website_id'] = $this->website_id;

        // edit for 2020/04/26 导出操作移到到计划任务统一执行
        $insert_data = array(
            'type' => 20,
            'status' => 0,
            'exname' => $xlsName,
            'website_id' => $this->website_id,
            'addtime' => time(),
            'ids' => serialize($xlsCell),
            'conditions' => serialize($where),
        );
        $excels_export = new ExcelsExport();
        $res = $excels_export->insertData($insert_data);
        return $res;
    }

    /**
     * 用户数据excel导出
     */
    public function giftDataExcel()
    {
        $xlsName = "礼品劵列表";
        $xlsCell = [
            0=>['record_id','ID'],
            1=>['uid','用户ID'],
            2=>['name','会员信息'],
            3=>['gift_voucher_code','礼品券号码'],
            4=>['gift_name','赠品'],
            5=>['state_name','状态'],
            6=>['type_name','来源'],
            7=>['fetch_time','领取时间'],
            8=>['use_time','使用时间'],
            9=>['shop_name','核销门店'],

        ];

        $member_id = request()->get('member_id', '');
        $search_text = request()->get('search_text', '');

        if(!empty($member_id)){
            $condition['vgvr.uid'] = $member_id;
        }
        if(!empty($search_text)){
            $condition['su.nick_name|su.user_tel|su.user_name'] = [
                'like',
                '%' . $search_text . '%'
            ];
        }
        $condition['su.website_id'] = $this->website_id;

        // edit for 2020/04/26 导出操作移到到计划任务统一执行
        $insert_data = array(
            'type' => 19,
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
    }

    /**
     * 删除用户礼品劵
     *
     * @return \multitype
     */
    public function deleteRecords(){
        $record_ids = isset($_POST["id"]) ? $_POST["id"] : '';

        $retval = 0;
        if (!empty($record_ids)) {
            $record_ids = explode(',', $record_ids);
            $records = new VslGiftVoucherRecordsModel();
            $flag = false;
            foreach ($record_ids as $v) {
                $info = $records::where('record_id', $v)->find();
                if(is_null($info) || $info['state'] == 2){
                    $flag = true;
                    break;
                }
            }

            if($flag){
                return AjaxReturn(0, [], "含有已使用礼品劵");
            }
            foreach ($record_ids as $item) {
                $retval =  $records::destroy(['record_id' => $item]);
            }

            $this->addUserLogByParam("删除用户礼品劵", json_encode($record_ids));
        }
        return AjaxReturn($retval);
    }

    /**
     * 删除用户优惠劵
     *
     * @return \multitype
     */
    public function deleteCoupons(){
        $record_ids = isset($_POST["id"]) ? $_POST["id"] : '';
        $retval = 0;
        if (!empty($record_ids)) {
            $record_ids = explode(',', $record_ids);
            $records = new VslCouponModel();
            $flag = false;
            foreach ($record_ids as $v) {
                $info = $records::where('coupon_id', $v)->find();
                if(is_null($info) || $info['state'] == 2){
                    $flag = true;
                    break;
                }
            }

            if($flag){
                return AjaxReturn(0, [], "含有已使用优惠劵");
            }
            foreach ($record_ids as $item) {
                $retval =  $records::destroy(['coupon_id' => $item]);
            }

            $this->addUserLogByParam("删除用户优惠劵", json_encode($record_ids));
        }
        return AjaxReturn($retval);
    }

    /**
     * 打卡审核
     */
    public function checklog()
    {
        if (request()->isAjax()) {
            $page_index = request()->post('page_index',1);
            $page_size = request()->post('page_size',PAGESIZE);
            $member_id = request()->post('member_id', '');
            $search_text = request()->post('search_text', '');
            if(!empty($member_id)){
                $where['nm.uid'] = $member_id;
            }
            if(!empty($search_text)){
                $where['su.nick_name|su.user_tel|su.user_name'] = [
                    'like',
                    '%' . $search_text . '%'
                ];
            }

            $model = new VslMemberCheckLogModel();
            $list = $model->getViewList1($page_index, $page_size, $where,'nm.id desc');
            if(count($list['data']) == 0){
                return $list;
            }

            foreach ($list['data'] as &$item){
                if ($item['user_name']) {
                    $item['name'] = $item['user_name'];
                } elseif ($item['nick_name']) {
                    $item['name'] = $item['nick_name'];
                } elseif ($item['user_tel']) {
                    $item['name'] = $item['user_tel'];
                }
                $item['create_time'] = date('Y-m-d H:i:s', $item['create_time']);
                $status_name = '审核中';
                if($item['status'] == 0){
                    $status_name = '审核中';
                }else if($item['status'] == 1){
                    $status_name = '审核成功';
                }else if($item['status'] == 2){
                    $status_name = '审核失败';
                }
                $item['status_name'] = $status_name;
            }
            return $list;
        } else {
            return view($this->style . 'User/check_log');
        }
    }

    public function passRecord(){
        $id = isset($_POST["id"]) ? $_POST["id"] : '';
        if(empty($id)){
            return AjaxReturn(0, [], "参数错误，刷新后尝试");
        }

        $info = VslMemberCheckLogModel::where('status', 0)->findOrFail($id);
        if(is_null($info)){
            return AjaxReturn(0, [], "已审核或记录不存在");
        }

        if($info['score'] > 0){
            $member = new MemberService();
            $uid = $info['uid'];
            $type = 1;
            $num = $info['score'];
            $text = '打卡朋友圈奖励';
            $retval = $member->addMemberAccount2($type, $uid, $num, $text, 203);
        }
        $info->status = 1;
        $info->update_time = time();
        $info->save();

        return AjaxReturn(1);
    }

    public function refuseRecord(){
        $id = isset($_POST["id"]) ? $_POST["id"] : '';
        $remark = isset($_POST["remark"]) ? $_POST["remark"] : '不同意';
        if(empty($id)){
            return AjaxReturn(0, [], "参数错误，刷新后尝试");
        }

        $info = VslMemberCheckLogModel::where('status', 0)->findOrFail($id);
        if(is_null($info)){
            return AjaxReturn(0, [], "已审核或记录不存在");
        }
        $info->status = 2;
        $info->remark = empty($remark) ? '不同意':$remark;
        $info->update_time = time();
        $info->save();

        return AjaxReturn(1);

    }

}
