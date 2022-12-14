<?php
namespace app\shop\controller;

use data\service\Goods;
use data\service\GoodsCategory;
use addons\shop\service\Shop;
use think\Db;
use think\Cookie;
use data\service\WebSite as WebSite;
use data\model\SysPcCustomConfigModel;
use data\service\Address;
use data\model\SysPcCustomNavConfigModel;
use data\extend\custom\Common;
use data\service\Config;
/**
 * 首页控制器
 */
class Index extends BaseController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function _empty($name)
    {}

    /*
     * 平台首页
     *
     * @return \think\response\View
     */
    public function index()
    {
        $this->web_site = new WebSite();
        $default_client = request()->cookie("default_client", "");
        $web_info = $this->web_site->getWebSiteInfo();
        $Config = new Config();
        $seoconfig = $Config->getSeoConfig(0);
        $this->assign("title", $seoconfig['seo_title']);
        $this->assign("title_before", $web_info['mall_name']);
        if ($default_client == "shop") {
            
        } elseif (request()->isMobile()&&$web_info['wap_status'] != 2) {
            $redirect = __URL(__URL__ . "/wap/mall/index");
            $this->redirect($redirect);
            exit();
        }
        $suffix =  trim(request()->get('suffix',''));
        $temp_type =  trim(request()->get('temp_type','home_templates'));
        $instance_id =  request()->get('instance_id',0);
        $preview = 1;
        $com = new Common($instance_id, $this->website_id);
        $pcCustomConfig = new SysPcCustomConfigModel();
        if (!$suffix &&!request()->isMobile()) {
            //使用模板
            $usedTem = $pcCustomConfig->getInfo(['type'=>2,'template_type'=> $temp_type,'shop_id'=>$instance_id,'website_id'=>$this->website_id],'code');
            $suffix = (isset($usedTem['code']) ? trim($usedTem['code']) : '');
            if (empty($suffix)) {
                //默认模板
                $defaultTem = $pcCustomConfig->getInfo(['type'=>1,'template_type'=> $temp_type,'shop_id'=>$instance_id,'website_id'=>$this->website_id],'code');
                $suffix = (isset($defaultTem['code']) ? trim($defaultTem['code']) : '');
            }
            $preview = 0;
        }
        $dir = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/'.$temp_type.'/'.$suffix;
        $dir_common = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/common';
        $dir_shop_common = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/common';
        
        if ($preview == 1) {
            $dir = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/'.$temp_type.'/'.$suffix;
            $dir_temp = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/'.$temp_type.'/'.$suffix . '/temp';
            $dir_common_temp = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/common/temp';
            $dir_shop_common_temp = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/common';
            if (is_dir($dir_temp)) {
                $dir = $dir_temp;
            }
            if (is_dir($dir_common_temp)) {
                $dir_common = $dir_common_temp;
            }
            if (is_dir($dir_shop_common_temp)) {
                $dir_shop_common = $dir_shop_common_temp;
            }
            if($temp_type!='home_templates'){
                $shop_id = $instance_id;
                $this->assign('shop_id', $shop_id);
            }
        }
        if(!file_exists($dir)){
            $com->createTem();
            $this->redirect($this->request->url());
        }
        if (!request()->isMobile() && $suffix) {
            $page = $com->get_html_file($dir . '/pc_html.php');
            $keywordList = $com->get_html_file($dir . '/header.php');
            $nav_page = $com->get_html_file($dir . '/nav_html.php');
            $topBanner = $com->get_html_file($dir . '/topBanner.php');
            $shopBanner = '';
            $ntype = 'index';
            if($temp_type!='home_templates' && $this->shopStatus && $temp_type!='custom_templates'){
                $shopBanner = $com->get_html_file($dir_shop_common . '/shopbanner_html.php');
                $ntype = 'shop';
            }
            $bottom = $com->get_html_file($dir_common . '/bottom_html.php');
            $logo_pic = $com->getLogo($suffix);
            /* 商品分类查询 */
            $navigator_list = $com->get_navigator($ntype);
            $navConfig = new SysPcCustomNavConfigModel();
            $navSet = $navConfig->getInfo(['website_id'=>$this->website_id,'code'=>$suffix,'template_type'=>$temp_type,'shop_id'=>$instance_id]);
            $pc_page['tem'] = $suffix;
            $this->assign('pc_page', $pc_page);
            $this->assign('nav_page', $nav_page);
            $this->assign('page', $page);
            $this->assign('keywordList', $keywordList);
            $this->assign('logo_pic', $logo_pic);
            $this->assign('navigator_list', $navigator_list);
            $this->assign('topBanner', $topBanner);
            $this->assign('shopBanner', $shopBanner);
            $this->assign('bottom', $bottom);
            $this->assign('navSet', $navSet);
            $this->assign('ntype', $ntype);
            return view($this->style . 'Index/index');
        }else{
            $this->error('页面不存在');
        }
    }

    /**
     * 得到当前时间戳的毫秒数
     * @return number
     */
    public function getCurrentTime()
    {
        $time = time();
        $time = $time * 1000;
        return $time;
    }


 
    /**
     * 发送短信
     */
    public function sms($mobile = '')
    {
        // if(request()->isPost()){
        $Send = new \data\extend\Send();
        $result = $Send->sms([
            'param' => [
                'code' => '123456',
                'time' => '60秒'
            ],
            'mobile' => $mobile,
            'template' => 'SMS_43210099'
        ]);
        if ($result !== true) {
            return $this->error($result);
        }
        return $this->success('短信下发成功！');
        // }
        // return $this->fetch();
    }


    /**
     * 删除设置页面打开cookie
     */
    public function deleteClientCookie()
    {
        Cookie::delete("default_client");
    }
    
    public function testTag(){
        return view($this->style."Index/testTag");
    }
    
   /*
    * 自定义pc页面
    */
    public function customPage(){
        $suffix =  trim(request()->get('suffix',''));
        $temp_type =  trim(request()->get('temp_type',''));
        $instance_id =  intval(request()->get('instance_id',0));
        $com = new Common($instance_id, $this->website_id);
        if (!$suffix) {
            $this->error('参数错误', __URL(__URL__ . "/index"));
        }
        $dir = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/'.$temp_type.'/'.$suffix;
        $dir_common = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/common';
        $dir_shop_common = ROOT_PATH . 'public/static/custompc/data/web_'.$this->website_id.'/shop_'.$instance_id.'/common';

        if (!request()->isMobile()) {
            if (file_exists($dir)) {
                $page = $com->get_html_file($dir . '/pc_html.php');
                $nav_page = $com->get_html_file($dir . '/nav_html.php');
                $topBanner = $com->get_html_file($dir . '/topBanner.php');
                $shopBanner = '';
                if ($temp_type!='custom_templates') {
                    $shopBanner = $com->get_html_file($dir_shop_common . '/shopbanner_html.php');
                }
                $bottom = $com->get_html_file($dir_common . '/bottom_html.php');
                $logo_pic = $com->getLogo($suffix);
                $categories_pro = $com->get_category_tree_leve_one(0);
                $ntype = 'index';
                if($temp_type!='home_templates' && $temp_type !='custom_templates'){
                    $ntype = 'shop';
                }
                $navigator_list = $com->get_navigator($ntype);
                
                $navConfig = new SysPcCustomNavConfigModel();
                $navSet = $navConfig->getInfo(['website_id'=>$this->website_id,'code'=>$suffix,'template_type'=>$temp_type,'shop_id'=>$instance_id]);
                $pc_page['tem'] = $suffix;
                $this->assign('pc_page', $pc_page);
                $this->assign('nav_page', $nav_page);
                $this->assign('page', $page);
                $this->assign('logo_pic', $logo_pic);
                $this->assign('categories_pro', $categories_pro);
                $this->assign('navigator_list', $navigator_list);
                $this->assign('topBanner', $topBanner);
                $this->assign('shopBanner', $shopBanner);
                $this->assign('bottom', $bottom);
                $this->assign('navSet', $navSet);
                $this->assign('ntype', $ntype);
                return view($this->style . 'Index/index');
            }else{
                $this->error('模板不存在', __URL(__URL__ . "/index"));
            }
        }
    }
    /*
     * 商城首页二维码
     */
    public function getQrcode(){
        $this->web_site = new WebSite(); 
        $web_info = $this->web_site->getWebSiteInfo();
        $logo = $web_info['logo'];
        $text = __URLS('APP_MAIN/mall/index');
        if(strpos($logo, '/') === 0){
            $logo = substr($logo,1);
        }
        getQRcodeNotSave($text,$logo);
    }
    /*
     * 商城首页二维码
     */
    public function getQrcodeForGoods(){
        $goods_id = request()->get('goods_id',0);
        $text = __URLS('APP_MAIN/goods/detail/'. $goods_id);
        getQRcodeNotSave($text,'');
    }
    /*
     * 店铺首页二维码
     */
    public function getQrcodeForShop(){
        $shop_id = request()->get('shop_id',0);
        if($this->shopStatus){
            $shop = new Shop();
            $shop_info = $shop->getShopDetail($shop_id);
            $logo = $shop_info['base_info']['shop_logo_img'];
        }
        $text = __URLS('APP_MAIN/shop/home/'.$shop_id);
        if(strpos($logo, '/') === 0){
            $logo = substr($logo,1);
        }
        getQRcodeNotSave($text,$logo);
    }
    /*
     * 分类导航，根据一级分类获取二级三级分类
     */
    public function getGoodsCategoryList(){
        $goodsCategory = new GoodsCategory();
        $goods_category_tree = $goodsCategory->getCategoryTreeUseInShopIndex();
        $newArray = [];
        foreach($goods_category_tree as &$val){
            $newArray[$val['category_id']] = $val;
        }
        unset($val);
        return json($newArray);
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
     *
     * @return Ambigous <multitype:\think\static , \think\false, \think\Collection, \think\db\false, PDOStatement, string, \PDOStatement, \think\db\mixed, boolean, unknown, \think\mixed, multitype:, array>
     */
    public function getCity()
    {
        $address = new Address();
        $province_id = request()->post('province_id', 0);
        $city_list = $address->getCityList($province_id);
        return $city_list;
    }

    /**
     * 获取区域地址
     */
    public function getDistrict()
    {
        $address = new Address();
        $city_id = request()->post('city_id', 0);
        $district_list = $address->getDistrictList($city_id);
        return $district_list;
    }
}