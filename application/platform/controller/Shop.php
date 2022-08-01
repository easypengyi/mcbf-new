<?php
namespace app\platform\controller;

use addons\cpsunion\server\Cpsunion;
use addons\miniprogram\service\MiniProgram as MiniProgramServer;
use data\service\Address;
use data\service\Goods;
use data\model\AdminUserModel as AdminUserModel;

class Shop extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }
    /**
     * 获取省列表
     */
    public function getProvince()
    {
        $address = new Address();
        $province_list = $address->getProvinceList();
        return $province_list;
    }

    /**
     * 获取城市列表
     */
    public function getCity()
    {
        $address = new Address();
        $province_id = isset($_POST['province_id']) ? $_POST['province_id'] : 0;
        $city_list = $address->getCityList($province_id);
        return $city_list;
    }

    /**
     * 获取区域地址
     */
    public function getDistrict()
    {
        $address = new Address();
        $city_id = isset($_POST['city_id']) ? $_POST['city_id'] : 0;
        $district_list = $address->getDistrictList($city_id);
        return $district_list;
    }

    /**
     * 获取选择地址
     *
     */
    public function getSelectAddress()
    {
        $address = new Address();
        $province_list = $address->getProvinceList();
        $province_id = isset($_POST['province_id']) ? $_POST['province_id'] : 0;
        $city_id = isset($_POST['city_id']) ? $_POST['city_id'] : 0;
        $city_list = $address->getCityList($province_id);
        $district_list = $address->getDistrictList($city_id);
        $data["province_list"] = $province_list;
        $data["city_list"] = $city_list;
        $data["district_list"] = $district_list;
        return $data;
    }

    /**
     * 商品选择
     */
    public function modalGoodsList()
    {
        if (request()->post('page_index')) {
            $index = request()->post('page_index', 1);
            $goods_type = request()->post('goods_type', 1);
            $search_text = request()->post('search_text');
            $type = request()->post('type');//模板类型
            $promote_type = request()->post('promote_type');//装修（活动类型：1拼团2预售3砍价4限时抢购）
            //0自营店 1全平台
            if ($goods_type == '0') {
                $condition['ng.shop_id'] = 0;
            }
            if ($search_text) {
                $condition['goods_name'] = ['LIKE', '%' . $search_text . '%'];
            }

            $condition['ng.website_id'] = $this->website_id;
            $condition['ng.state'] = 1;
            //装修手动推荐（筛选活动商品）
            if($promote_type){
                $condition['ng.promotion_type'] = $promote_type;
            }

            $goods_service = new Goods();
            if($type == 9){
                $list = $goods_service->getIntegralGoodsList($index, PAGESIZE, $condition);
            }else{
                $list = $goods_service->getgoodslist($index, PAGESIZE, $condition,$order = 'ng.sort desc,ng.create_time desc', $field = '*');
            }
            $goods_list = [];
            //删除多余的字段
            foreach($list['data'] as $k => $v){
                $goods_list[$k]['goods_id'] = $v['goods_id'];
                $goods_list[$k]['goods_name'] = $v['goods_name'];
                $goods_list[$k]['price'] = $v['price'];
                $goods_list[$k]['shop_name'] = $v['shop_name'] ?: '';
                $goods_list[$k]['pic_cover'] = getApiSrc($v['pic_cover']);
                $goods_list[$k]['pic_cover_mid'] = getApiSrc($v['pic_cover_mid']);
                $goods_list[$k]['pic_cover_small'] = getApiSrc($v['pic_cover_small']);
                $goods_list[$k]['pic_cover_micro'] = getApiSrc($v['pic_cover_micro']);
                if($type == 9){
                    $goods_list[$k]['point_exchange'] = $v['goods_point'];
                    $goods_list[$k]['goods_type'] = $v['goods_exchange_type'];//0-正常商品 1-优惠券 2-礼品券 3-余额
                }
            }
            unset($v);
            $list['data'] = $goods_list;
            return $list;
        }
        if (request()->get('goods_type') != '') {//0自营 1全平台
            $this->assign('goods_type', request()->get('goods_type'));
        }
        if (request()->get('type') != '') {//0自动 1手动
            $this->assign('type', request()->get('type'));
        }
        return view($this->style . 'Shop/goodsDialog');
    }

    /**
     * 导航栏获取商品链接
     */
    public function getSearchGoods()
    {
        $search_text = request()->post('search_text', '');
        $condition = array(
            'goods_name' => ['LIKE', '%' . $search_text . '%']
        );
        $condition['shop_id'] = $this->instance_id;
        $condition['website_id'] = $this->website_id;
        $goods = new Goods();
        $list = $goods->getSearchGoodsList(1, 0, $condition);
        return $list;
    }

    /**
     * 小程序设置 - 订阅消息 - 商城变量
     */
    public function modalMinSubMessage()
    {

        $miniprogram =  new MiniProgramServer();
        $message_ids = $miniprogram->getMpMessageTemplateColumn([], 'template_id, template_name');
        $sub_list = $miniprogram->getMpSubKeys();

        if ($sub_list) {
            $config = [];
            foreach ($message_ids as $k => $name) {
                foreach ($sub_list as $key => $list) {
                    if ($k == $list['template_id']) {
//                        $config[$k]['template_name'] = $name;
                        $config[$k][] = $list;
                    }
                }
            }
        }



        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        return view($this->style . 'Shop/minSubMessageDialog');
    }

    /**
     * 链接选择Pc
     */
    public function modalLinkListPc() {
        $config['shop'] = getAddons('shop',$this->website_id,0,true);
        $config['distribution'] = getAddons('distribution',$this->website_id,0,true);
        $config['areabonus'] = getAddons('areabonus',$this->website_id,0,true);
        $config['globalbonus'] = getAddons('globalbonus',$this->website_id,0,true);
        $config['teambonus'] = getAddons('teambonus',$this->website_id,0,true);
        $config['coupontype'] = getAddons('coupontype',$this->website_id,0,true);
        $config['pcport'] = getAddons('pcport',$this->website_id, 0, true);
        $this->assign('pcCustomTemplateListUrl', __URL(call_user_func('addons_url_' . $this->module, 'pcport://Pcport/pccustomtemplatelist')));
        $this->assign('config', $config);
        $this->assign('shop_id',$this->instance_id);
        return view($this->style . 'Shop/linksPcDialog');
    }

    /**
     * 小程序装修底部选择图标
     */
    public function modalFooterIcons()
    {
        return view($this->style . 'Shop/iconFooterDialog');
    }

    /**
     * icon图标选择
     */
    public function modalIcons()
    {
        return view($this->style . 'Shop/iconDialog');
    }
    /**
     * wap_icon图标选择
     */
    public function modalWapIcons()
    {
        return view($this->style . 'Shop/wap_iconDialog');
    }
      /**
     * 链接选择
     */
    public function indexSetDialog()
    {
        $first_list = $this->user->getchildModuleQuery(0);
        $list = array();
        foreach ($first_list as $k => $v) {
            $submenu = $this->user->getchildModuleQuery($v['module_id']);
            $list[$k]['data'] = $v;
            $list[$k]['sub_menu'] = $submenu;
        }
        $this->assign("nav_list", $list);
        $condition['uid'] = $this->uid ;
        $user = new AdminUserModel();
        $entry_ids = $user->getInfo($condition,'entry_ids')['entry_ids'];
        $this->assign("entry_ids", $entry_ids);
        return view($this->style . 'Shop/indexSetDialog');
    }

    /**
     * CPS商品选择
     */
    public function modalCpsGoodsList()
    {
        if(getAddons('cpsunion',$this->website_id)) {
            if (request()->post('page_index')) {
                $page_index = request()->post('page_index', 1);
                $platform = request()->post('platform', '');
                $search_text = request()->post('search_text');

                $cpsunion_server = new Cpsunion();
                $list = $cpsunion_server->getCpsGoodsList($page_index, $platform, $search_text, $this->website_id);

                if($list['data']) {
                    $goods_list = [];
                    //删除多余的字段
                    if($platform == 'pdd') {
                        //拼多多商品
                        foreach($list['data'] as $k => $v){
                            $goods_list[$k]['goods_id'] = $v['goods_id'];
                            $goods_list[$k]['goods_name'] = $v['goods_name'];
                            $goods_list[$k]['price'] = $v['min_group_price'] / 100;
                            $goods_list[$k]['shop_name'] = $v['mall_name'];
                            $goods_list[$k]['pic_cover'] = $v['goods_thumbnail_url'];
                            $goods_list[$k]['commission'] = ($v['min_group_price'] / 100) * ($v['promotion_rate'] / 1000);
                            $goods_list[$k]['commission_rate'] = $v['promotion_rate'] / 10 . '%';
                            $goods_list[$k]['sales'] = $v['sales_tip'];
                            $goods_list[$k]['platform'] = 'pdd';
                            if($v['has_coupon']) {
                                $goods_list[$k]['coupon_money'] = $v['coupon_discount'] / 100;
                            }else{
                                $goods_list[$k]['coupon_money'] = 0;
                            }
                        }
                        unset($v);
                    }elseif ($platform == 'jd') {
                        //京东商品
                        foreach($list['data'] as $k => $v){
                            $goods_list[$k]['goods_id'] = $v['skuId'];
                            $goods_list[$k]['goods_name'] = $v['skuName'];
                            $goods_list[$k]['price'] = $v['priceInfo']['lowestCouponPrice'] ? : $v['priceInfo']['price'];
                            $goods_list[$k]['shop_name'] = $v['shopInfo']['shopName'];
                            $goods_list[$k]['pic_cover'] = $v['imageInfo']['imageList'][0]['url'];
                            $goods_list[$k]['commission'] = $v['commissionInfo']['commission'];
                            $goods_list[$k]['commission_rate'] = $v['commissionInfo']['commissionShare'] . '%';
                            $goods_list[$k]['sales'] = $v['inOrderCount30Days'];
                            $goods_list[$k]['platform'] = 'jd';
                            if($v['couponInfo']['couponList'][0]) {
                                $goods_list[$k]['coupon_money'] = $v['couponInfo']['couponList'][0]['discount'];
                            }else{
                                $goods_list[$k]['coupon_money'] = 0;
                            }
                        }
                        unset($v);
                    }

                    $list['data'] = $goods_list;
                }
                return $list;
            }
        }

        return view($this->style . 'Shop/cpsGoodsDialog');
    }
}
