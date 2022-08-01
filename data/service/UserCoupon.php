<?php
namespace data\service;

/**
 * 优惠券 赠品卷处理
 */
use data\service\BaseService as BaseService;
use data\model\UserModel;

class UserCoupon extends BaseService
{
    function __construct() 
    {
        parent::__construct();
    }
    /**
     * 转赠
     * 卷类转赠
     * type 1优惠券 2赠品卷
     * coupon_id 对应优惠卷的id 赠品卷的id
     */
    public function transCoupon($param){
        switch ($param['type']) {
            case 1:
                # 优惠券
                if(!getAddons('coupontype',$this->website_id)){
                    return ['code'=>-1,'message'=>'优惠券应用已关闭，转赠失败'];
                }
                $res = $this->transCouponType($param);
                break;
            case 2:
                # 赠品券
                if(!getAddons('giftvoucher',$this->website_id)){
                    return ['code'=>-1,'message'=>'赠品券应用已关闭，转赠失败'];
                }
                $res = $this->transCouponGiftvoucher($param);
                break;
        }
        return $res;
    }
    public function transCouponType($param){
        $couponModel = new \addons\coupontype\model\VslCouponModel();
        $coupon_info = $couponModel->getInfo(['coupon_id'=>$param['coupon_id'],'website_id'=>$this->website_id,'uid'=>$param['uid']],'*');
        if(!$coupon_info){
            return ['code'=>-1,'message'=>'优惠券查询失败，请核实后重试'];
        }
        if($coupon_info['state'] != 1){
            return ['code'=>-1,'message'=>'当前赠品券非待使用状态，转赠失败'];
        }
        try {
            $arr = [
                'uid'=>$param['user_id'],
                'coupon_pid'=>$param['coupon_id'],
                'fetch_time'=>time(),
                'send_uid'=>$param['uid']
            ];
            $res = $couponModel->save($arr,['coupon_id'=>$param['coupon_id'],'website_id'=>$this->website_id]);
            if($res){
                return ['code'=>1,'message'=>'转赠成功'];
            }else{
                return ['code'=>-1,'message'=>'转赠失败'];
            }
        } catch (\Throwable $e) {
            return ['code'=>-1,'message'=>$e->getMessage()];
        }
    }
    public function transCouponGiftvoucher($param){
        $couponModel = new \addons\giftvoucher\model\VslGiftVoucherRecordsModel();
        $coupon_info = $couponModel->getInfo(['record_id'=>$param['coupon_id'],'website_id'=>$this->website_id,'uid'=>$param['uid']],'*');
        if(!$coupon_info){
            return ['code'=>-1,'message'=>'赠品券查询失败，请核实后重试'];
        }
        if($coupon_info['state'] != 1){
            return ['code'=>-1,'message'=>'当前赠品券非待使用状态，转赠失败'];
        }
        try {
            $arr = [
                'uid'=>$param['user_id'],
                'fetch_time'=>time(),
                'send_uid'=>$param['uid']
            ];
            $res = $couponModel->save($arr,['record_id'=>$param['coupon_id'],'website_id'=>$this->website_id]);
            if($res){
                return ['code'=>1,'message'=>'转赠成功'];
            }else{
                return ['code'=>-1,'message'=>'转赠失败'];
            }
        } catch (\Throwable $e) {
            return ['code'=>-1,'message'=>$e->getMessage()];
        }
    }
}
