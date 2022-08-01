<?php

namespace app\admin\controller;

use data\service\Config as WebConfig;
use data\service\Order as OrderService;
use data\service\Goods as GoodsService;

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
        $shop_id = $this->instance_id;
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
            $retval = $order_service->updateShopReturnSet($shop_id,$return_id,$consigner,$mobile,$province,$city,$district,$address,$zip_code,$is_default);
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
        $order_service = new OrderService();
        $list= $order_service->getShopReturnList($this->instance_id, $this->website_id);
        return $list;
    }
    
    /**
     * 商家地址详情
     */
    public function getShopReturn() {
        $order_service = new OrderService();
        $return_id = request()->post('return_id', 0);
        $shop_id = $this->instance_id;
        $website_id = $this->website_id;
        $info = $order_service->getShopReturn($return_id,$shop_id,$website_id);
        return $info;
    }
    
    /**
     * 商家地址
     */
    public function returnDelete() {
        $order_service = new OrderService();
        $shop_id = $this->instance_id;
        $return_id = request()->post('return_id', 0);
        $retval = $order_service->deleteShopReturnSet($shop_id,$return_id);
        if ($retval) {
            $this->addUserLog('系统商家地址删除', $retval);
        }
        return AjaxReturn($retval);
    }

    /**
     * 设置默认商品详情页
     */
    public function setdefaultgoodsdetail() {

        $id = request()->post('id', 0);
        $web_config = new WebConfig();
        $retval = $web_config->setGoodsdetailTemplate($id, $this->instance_id);
        if ($retval) {
            $this->addUserLog('设置默认商品详情页', $id);
        }
        return AjaxReturn($retval);
    }

    /**
     * 设置默认会员中心
     */
    public function setdefaultmembercenter() {

        $id = request()->post('id', 0);
        $web_config = new WebConfig();
        $retval = $web_config->customtemplate_membercenter($id, $this->instance_id);
        if ($retval) {
            $this->addUserLog('设置默认会员中心页', $id);
        }
        return AjaxReturn($retval);
    }

    /**
     * 设置默认分销中心
     */
    public function setdefaultdistribution() {

        $id = request()->post('id', 0);
        $web_config = new WebConfig();
        $retval = $web_config->customtemplate_distribution($id, $this->instance_id);
        if ($retval) {
            $this->addUserLog('设置默认分销中心页', $id);
        }
        return AjaxReturn($retval);
    }

    /**
     * 开启关闭自定义模板
     */
    public function setIsEnableCustomTemplate() {
        $web_config = new WebConfig();
        $is_enable = request()->post("is_enable", 0);
        $retval = $web_config->setIsEnableCustomTemplate($this->instance_id, $is_enable);
        if ($retval) {
            $this->addUserLog($is_enable ? '开启' : '关闭' . '手机端自定义模板');
        }
        return AjaxReturn($retval);
    }

    /**
     * 导航栏获取商品链接
     */
    public function getSearchGoods() {
        $search_text = request()->post('search_text', '');
        $condition = array(
            'goods_name' => ['LIKE', '%' . $search_text . '%']
        );
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $goods_service = new GoodsService();
        $list = $goods_service->getSearchGoodsList(1, 0, $condition);
        return $list;
    }

    /**
     * 商品选择
     */
    public function modalGoodsList() {
        if (request()->post('page_index')) {
            $index = request()->post('page_index', 1);
            $search_text = request()->post('search_text');
            $promote_type = request()->post('promote_type');
            $condition['ng.shop_id'] = $this->instance_id;
            if ($search_text) {
                $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
            }

            $condition['ng.website_id'] = $this->website_id;
            $condition['ng.state'] = 1;
            //装修手动推荐（筛选活动商品）
            if($promote_type){
                $condition['ng.promotion_type'] = $promote_type;
            }
            $goods_service = new GoodsService();
            $list = $goods_service->getgoodslist($index, PAGESIZE, $condition);
            $goods_list = [];
            //删除多余的字段
            foreach ($list['data'] as $k => $v) {
                $goods_list[$k]['goods_id'] = $v['goods_id'];
                $goods_list[$k]['goods_name'] = $v['goods_name'];
                $goods_list[$k]['price'] = $v['price'];
                $goods_list[$k]['shop_name'] = $v['shop_name'] ?: '自营店';
                $goods_list[$k]['pic_cover'] = getApiSrc($v['pic_cover']);
                $goods_list[$k]['pic_cover_mid'] = getApiSrc($v['pic_cover_mid']);
                $goods_list[$k]['pic_cover_small'] = getApiSrc($v['pic_cover_small']);
                $goods_list[$k]['pic_cover_micro'] = getApiSrc($v['pic_cover_micro']);
            }
            $list['data'] = $goods_list;
            return $list;
        }
        return view($this->style . 'Shop/goodsDialog');
    }

    /**
     * 链接选择
     */
    public function modalLinkList() {
        $config['coupontype'] = getAddons('coupontype',$this->website_id,$this->instance_id,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,$this->instance_id,true);
        $config['integral'] = getAddons('integral',$this->website_id,$this->instance_id,true);
        $config['channel'] = getAddons('channel',$this->website_id,$this->instance_id,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,$this->instance_id,true);
        $config['presell'] = getAddons('presell',$this->website_id,$this->instance_id,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,$this->instance_id,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,$this->instance_id,true);
        $config['signin'] = getAddons('signin',$this->website_id,$this->instance_id,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,$this->instance_id,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,$this->instance_id,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,$this->instance_id,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,$this->instance_id,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,$this->instance_id,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,$this->instance_id,true);
        $config['qlkefu'] = getAddons('qlkefu',$this->website_id,$this->instance_id,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,$this->instance_id,true);
        $config['helpcenter'] = getAddons('helpcenter',$this->website_id,0,true);

        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        return view($this->style . 'Shop/linksDialog');
    }
    /**
     * 链接选择_小程序
     */
    public function modalLinkListMin() {
        $config['coupontype'] = getAddons('coupontype',$this->website_id,$this->instance_id,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,$this->instance_id,true);
        $config['integral'] = getAddons('integral',$this->website_id,$this->instance_id,true);
        $config['channel'] = getAddons('channel',$this->website_id,$this->instance_id,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,$this->instance_id,true);
        $config['presell'] = getAddons('presell',$this->website_id,$this->instance_id,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,$this->instance_id,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,$this->instance_id,true);
        $config['signin'] = getAddons('signin',$this->website_id,$this->instance_id,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,$this->instance_id,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,$this->instance_id,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,$this->instance_id,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,$this->instance_id,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,$this->instance_id,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,$this->instance_id,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,$this->instance_id,true);
        $config['liveshopping'] = getAddons('liveshopping',$this->website_id,$this->instance_id,true);
        $config['thingcircle'] = getAddons('thingcircle',$this->website_id,$this->instance_id,true);
        $config['miniprogram'] = getAddons('miniprogram',$this->website_id,$this->instance_id,true);

        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        return view($this->style . 'Shop/linksMinDialog');
    }

    // 图片热区设置 
    public function modalDrawregion() {
        return view('platform/Config/popupDrawregion');
    }
    // 风格弹窗 
    public function modalStyles() {
        return view('platform/Config/popupStyles');
    }
    // 商品弹窗 
    public function modalGoods() {
        return view('platform/Config/popupGoodsDialog');
    }
    // 活动商品弹窗 
    public function modalPromoteGoods() {
        return view('platform/Config/popupPromoteGoodsDialog');
    }
    // 优惠券弹窗 
    public function modalCoupon() {
        return view('platform/Config/popupCouponDialog');
    }
    // 预约表单弹窗 
    public function modalForm() {
        return view('platform/Config/popupFormDialog');
    }

    // 装修选择页面链接（多端统一）
    public function modalPageLinks() {
        $config['shop'] = getAddons('shop',$this->website_id,$this->instance_id,true);
        $config['store'] = getAddons('store',$this->website_id,$this->instance_id,true);
        $config['distribution'] = getAddons('distribution',$this->website_id,$this->instance_id,true);
        $config['areabonus'] = getAddons('areabonus',$this->website_id,$this->instance_id,true);
        $config['globalbonus'] = getAddons('globalbonus',$this->website_id,$this->instance_id,true);
        $config['teambonus'] = getAddons('teambonus',$this->website_id,$this->instance_id,true);
        $config['coupontype'] = getAddons('coupontype',$this->website_id,$this->instance_id,true);
        $config['microshop'] = getAddons('microshop',$this->website_id,$this->instance_id,true);
        $config['integral'] = getAddons('integral',$this->website_id,$this->instance_id,true);
        $config['channel'] = getAddons('channel',$this->website_id,$this->instance_id,true);
        $config['seckill'] = getAddons('seckill',$this->website_id,$this->instance_id,true);
        $config['presell'] = getAddons('presell',$this->website_id,$this->instance_id,true);
        $config['groupshopping'] = getAddons('groupshopping',$this->website_id,$this->instance_id,true);
        $config['bargain'] = getAddons('bargain',$this->website_id,$this->instance_id,true);
        $config['signin'] = getAddons('signin',$this->website_id,$this->instance_id,true);
        $config['followgift'] = getAddons('followgift',$this->website_id,$this->instance_id,true);
        $config['festivalcare'] = getAddons('festivalcare',$this->website_id,$this->instance_id,true);
        $config['paygift'] = getAddons('paygift',$this->website_id,$this->instance_id,true);
        $config['scratchcard'] = getAddons('scratchcard',$this->website_id,$this->instance_id,true);
        $config['smashegg'] = getAddons('smashegg',$this->website_id,$this->instance_id,true);
        $config['wheelsurf'] = getAddons('smashegg',$this->website_id,$this->instance_id,true);
        $config['qlkefu'] = getAddons('qlkefu',$this->website_id,$this->instance_id,true);
        $config['credential'] = getAddons('credential',$this->website_id,$this->instance_id,true);
        $config['taskcenter'] = getAddons('taskcenter',$this->website_id,$this->instance_id,true);
        $config['anticounterfeiting'] = getAddons('anticounterfeiting',$this->website_id,$this->instance_id,true);
        $config['liveshopping'] = getAddons('liveshopping',$this->website_id,$this->instance_id,true);
        $config['thingcircle'] = getAddons('thingcircle',$this->website_id,$this->instance_id,true);
        $config['blockchain'] = getAddons('blockchain',$this->website_id,$this->instance_id,true);
        $config['miniprogram'] = getAddons('miniprogram',$this->website_id,$this->instance_id,true);
        $config['giftvoucher'] = getAddons('giftvoucher',$this->website_id,$this->instance_id,true);
        $config['voucherpackage'] = getAddons('voucherpackage',$this->website_id,$this->instance_id,true);
        $config['helpcenter'] = getAddons('helpcenter',$this->website_id,$this->instance_id,true);
        $config['mplive'] = getAddons('mplive',$this->website_id,$this->instance_id,true);
    
        if($config['followgift'] || $config['festivalcare'] || $config['paygift'] || $config['scratchcard'] || $config['smashegg'] || $config['wheelsurf']){
            $config['memberprize'] = 1;
        }else{
            $config['memberprize'] = 0;
        }
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        if (request()->get('platform') == 'mini') {
            $this->assign('type', request()->get('template_type'));
        }
        $this->assign('platform',request()->get('platform'));//mini、app、wap
        return view('platform/Config/pageLinksDialog');
    }
    
    // 装修页面
    public function customTemplateList() {
        deleteCache();
        $redirect = __URL(__URL__ . '/' . $this->module . "/Customtemplate/customtemplatelist");
        $this->redirect($redirect);return;//新装修地址所以返回(勿删！)

    }
}
