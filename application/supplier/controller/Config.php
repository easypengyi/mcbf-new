<?php

namespace app\supplier\controller;

use data\model\VslOrderShopReturnModel;
use data\service\Address;
use data\service\Order as OrderService;

/**
 * 网站设置模块控制器
 *
 * @author  www.vslai.com
 *        
 */
class Config extends BaseController {

    protected $realm_ip;
    protected $realm_two_ip;
    protected $http;
    public function __construct() {
        parent::__construct();
        $is_ssl = \think\Request::instance()->isSsl();
        $this->http = "http://";
        if($is_ssl){
            $this->http = 'https://';
            $this->assign('ssl','https');
        }
        $web_info = $this->website->getWebSiteInfo();
        $this->realm_ip = $web_info['realm_ip'];
        $this->assign('realm_ip',$this->realm_ip);
        if($web_info['realm_two_ip']){
            $ip = top_domain($_SERVER['HTTP_HOST']);
            $web_info['realm_two_ip'] = $web_info['realm_two_ip'].'.'.$ip;
            $this->realm_two_ip = $web_info['realm_two_ip'];
            $this->assign('realm_two_ip',$this->realm_two_ip);
            $this->assign('top_ip',$ip);
        }
        if(empty($this->realm_ip)){
            $real_ip = $this->http.$this->realm_two_ip;
        }else{
            $real_ip = $this->http.$this->realm_ip;
        }
        $this->assign('real_ip',$real_ip);
    }

    /**
     * 退货地址列表
     *
     */
    public function returnSetting() {
        $order_service = new OrderService();
        $shop_id = -1;
        $supplier_id = $this->supplier_id;
        if (request()->isAjax()) {
            $return_id = request()->post('return_id', 0);
            $consigner = request()->post('consigner', '');
            $mobile = request()->post('mobile', '');
            $province = request()->post('province', '');
            $city = request()->post('city', '');
            $district = request()->post('district', '');
            $address = request()->post('address', '');
            $zip_code = request()->post('zip_code', '');
            $is_default = request()->post('is_default', 0);
            $retval = $order_service->updateShopReturnSet($shop_id,$return_id,$consigner,$mobile,$province,$city,$district,$address,$zip_code,$is_default,$supplier_id);
            if ($retval) {
                $this->addUserLog('退货地址设置', $retval);
            }
            return AjaxReturn($retval);
        } else {
            return view($this->style . "System/returnSetting");
        }
    }
    
    /**
     * 商家地址
     */
    public function getShopReturnList() {
        $shop_return = new VslOrderShopReturnModel();
        $list = $shop_return->getQuery([
            'shop_id' => -1,
            'website_id' => $this->website_id,
            'supplier_id' => $this->supplier_id,
        ], '*', 'is_default desc');
        if ($list) {
            $address = new Address();
            foreach ($list as $k => $v) {
                $list[$k]['province_name'] = $address->getProvinceName($v['province']);
                $list[$k]['city_name'] = $address->getCityName($v['city']);
                $list[$k]['dictrict_name'] = $address->getDistrictName($v['district']);
            }
        }
        return $list;
    }
    
    /**
     * 商家地址详情
     */
    public function getShopReturn() {
        $return_id = request()->post('return_id', 0);

        $shop_id = -1;
        $website_id = $this->website_id;
        $supplier_id = $this->supplier_id;
        $shop_return = new VslOrderShopReturnModel();
        $shop_return_obj = $shop_return->getInfo(['return_id' => $return_id, 'shop_id' => $shop_id, 'website_id' => $website_id,'supplier_id' => $supplier_id]);
        return $shop_return_obj;
    }
    
    /**
     * 商家地址
     */
    public function returnDelete() {
        $shop_id = -1;
        $supplier_id = $this->supplier_id;
        $return_id = request()->post('return_id', 0);

        $shop_return = new VslOrderShopReturnModel();
        $retval = $shop_return->where(['return_id' => $return_id, 'shop_id' => $shop_id,'supplier_id' => $supplier_id])->delete();
        if ($retval) {
            $this->addUserLog('系统商家地址删除', $retval);
        }
        return AjaxReturn($retval);
    }

}
